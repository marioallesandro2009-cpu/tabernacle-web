<?php

namespace App\Http\Controllers\Admin;

use App\Exports\LeadsExport;
use App\Exports\LeadsSheet;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Quotation;
use App\Models\Tour;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class LeadController extends Controller
{
    /** @return Builder<Lead> */
    protected function filtered(Request $request): Builder
    {
        $q = Lead::query()->with(['tour:id,name', 'assignee:id,name']);
        foreach (['status', 'type', 'source', 'tour_id', 'assigned_to'] as $col) {
            if (($v = $request->query($col)) !== null && $v !== '') {
                $q->where($col, $v);
            }
        }
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('phone', 'like', "%$term%")
                ->orWhere('email', 'like', "%$term%")->orWhere('tour_name', 'like', "%$term%")->orWhere('message', 'like', "%$term%"));
        }

        return $q;
    }

    /** @return array<string, mixed> */
    protected function row(Lead $l): array
    {
        return [
            'id' => $l->id, 'type' => $l->type, 'name' => $l->name, 'phone' => $l->phone, 'email' => $l->email,
            'tour' => $l->tour?->name ?: $l->tour_name, 'pax' => $l->pax, 'status' => $l->status,
            'source' => $l->source, 'assignee' => $l->assignee?->name, 'created_at' => $l->created_at?->format('d M Y H:i'),
            'message' => $l->message ? mb_substr($l->message, 0, 140) : null,
        ];
    }

    public function index(Request $request): Response
    {
        $leads = $this->filtered($request)->latest('id')->paginate(25)->withQueryString()->through(fn (Lead $l) => $this->row($l));

        return Inertia::render('Admin/Leads/Index', [
            'leads' => $leads,
            'query' => $request->only(['q', 'status', 'type', 'source', 'tour_id', 'assigned_to', 'from', 'to']),
            'statuses' => collect(Lead::STATUSES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'counts' => Lead::query()->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'sources' => Lead::query()->whereNotNull('source')->distinct()->orderBy('source')->pluck('source'),
            'tours' => Tour::orderBy('name')->get(['id', 'name']),
            'staff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'canExport' => auth()->user()->can('leads.export'),
        ]);
    }

    public function show(string $id): Response
    {
        $lead = Lead::with(['tour:id,name,slug', 'assignee:id,name', 'events.user:id,name'])->findOrFail($id);

        return Inertia::render('Admin/Leads/Show', [
            'lead' => [
                ...$lead->only(['id', 'type', 'name', 'phone', 'email', 'tour_name', 'pax', 'message', 'status', 'assigned_to', 'notes', 'page_url', 'referrer', 'source', 'utm_source', 'utm_medium', 'utm_campaign', 'user_agent']),
                'plan_date' => $lead->plan_date?->format('Y-m-d'),
                'created_at' => $lead->created_at?->format('d M Y H:i'),
                'tour' => $lead->tour ? ['name' => $lead->tour->name, 'url' => url('/packages/'.$lead->tour->slug)] : null,
                'wa_url' => $lead->phone ? 'https://wa.me/'.preg_replace('/\D+/', '', $lead->phone) : null,
            ],
            'events' => $lead->events->map(fn (LeadEvent $e) => [
                'id' => $e->id, 'event' => $e->event, 'from' => $e->from_value, 'to' => $e->to_value, 'note' => $e->note,
                'user' => $e->user?->name ?: 'Sistem', 'at' => $e->created_at?->format('d M Y H:i'),
            ]),
            'statuses' => collect(Lead::STATUSES)->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'staff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'quotations' => Quotation::where('lead_id', $lead->id)->latest('id')->get()->map(fn (Quotation $q) => [
                'id' => $q->id, 'number' => $q->displayNumber(), 'status' => Quotation::STATUSES[$q->status] ?? $q->status,
                'total' => Quotation::money($q->totals()['total'], $q->currency), 'issued' => $q->issued_on->locale('id')->isoFormat('D MMM YYYY'),
            ]),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Lead::STATUSES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $uid = $request->user()->id;
        if ($lead->status !== $data['status']) {
            LeadEvent::create(['lead_id' => $lead->id, 'user_id' => $uid, 'event' => 'status', 'from_value' => $lead->status, 'to_value' => $data['status']]);
        }
        if ((int) $lead->assigned_to !== (int) ($data['assigned_to'] ?? 0)) {
            LeadEvent::create(['lead_id' => $lead->id, 'user_id' => $uid, 'event' => 'assign',
                'from_value' => $lead->assignee?->name, 'to_value' => isset($data['assigned_to']) ? User::find($data['assigned_to'])?->name : null]);
        }
        $lead->update($data);

        return back()->with('success', 'Lead diperbarui.');
    }

    public function note(Request $request, string $id): RedirectResponse
    {
        $lead = Lead::findOrFail($id);
        $data = $request->validate(['note' => ['required', 'string', 'max:3000']]);
        LeadEvent::create(['lead_id' => $lead->id, 'user_id' => $request->user()->id, 'event' => 'note', 'note' => $data['note']]);

        return back()->with('success', 'Catatan ditambahkan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        Lead::findOrFail($id)->delete();

        return redirect('/admin/leads')->with('success', 'Lead dihapus.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['status', 'delete'])],
            'status' => ['required_if:action,status', 'nullable', Rule::in(array_keys(Lead::STATUSES))],
        ]);
        $leads = Lead::whereIn('id', $data['ids'])->get();
        if ($data['action'] === 'delete') {
            Lead::whereIn('id', $leads->pluck('id'))->delete();

            return back()->with('success', $leads->count().' lead dihapus.');
        }
        foreach ($leads as $l) {
            if ($l->status !== $data['status']) {
                LeadEvent::create(['lead_id' => $l->id, 'user_id' => $request->user()->id, 'event' => 'status', 'from_value' => $l->status, 'to_value' => $data['status']]);
                $l->update(['status' => $data['status']]);
            }
        }

        return back()->with('success', $leads->count().' lead diperbarui.');
    }

    /** xlsx / csv / pdf with the same filters as the list, plus a summary. */
    public function export(Request $request, string $format)
    {
        abort_unless(in_array($format, ['xlsx', 'csv', 'pdf'], true), 404);
        $leads = $this->filtered($request)->latest('id')->limit(20000)->get();
        $summary = [
            'total' => $leads->count(),
            'by_status' => $leads->groupBy('status')->map->count()->all(),
            'by_source' => $leads->groupBy(fn ($l) => $l->source ?: 'direct')->map->count()->sortDesc()->all(),
            'by_tour' => $leads->groupBy(fn ($l) => $l->tour?->name ?: ($l->tour_name ?: '–'))->map->count()->sortDesc()->take(10)->all(),
            'form' => $leads->where('type', 'form')->count(),
            'wa_click' => $leads->where('type', 'wa_click')->count(),
            'range' => ($request->query('from') ?: '…').' s/d '.($request->query('to') ?: 'sekarang'),
        ];
        $name = 'leads-'.now()->format('Ymd-His');

        if ($format === 'pdf') {
            return Pdf::loadView('admin.leads-pdf', ['leads' => $leads, 'summary' => $summary, 'statuses' => Lead::STATUSES, 'site' => setting('site_name')])
                ->setPaper('a4', 'landscape')->download($name.'.pdf');
        }

        if ($format === 'csv') {
            return Excel::download(new LeadsSheet($leads), $name.'.csv', ExcelFormat::CSV);
        }

        return Excel::download(new LeadsExport($leads, $summary), $name.'.xlsx', ExcelFormat::XLSX);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\Tour;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Price quotations: made from a lead or from scratch, edited freely in the admin and downloaded
 * as a PDF on the company letterhead. Changing a quotation that was already sent keeps the old
 * version and raises the revision number.
 */
class QuotationController extends Controller
{
    public function index(Request $request): Response
    {
        $q = Quotation::query()->with('lead:id,name')->latest('id');
        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.$term.'%';
            $q->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('client_name', 'like', $like)->orWhere('client_company', 'like', $like)->orWhere('title', 'like', $like));
        }
        $status = $request->query('status');
        if (is_string($status) && isset(Quotation::STATUSES[$status])) {
            $q->where('status', $status);
        }
        $rows = $q->paginate(25)->withQueryString()->through(fn (Quotation $x) => [
            'id' => $x->id, 'number' => $x->displayNumber(), 'client' => $x->client_name, 'company' => $x->client_company, 'title' => $x->title,
            'status' => $x->status, 'total' => Quotation::money($x->totals()['total'], $x->currency), 'issued' => $x->issued_on->locale('id')->isoFormat('D MMM YYYY'),
            'expired' => $x->status === 'sent' && $x->valid_until && $x->valid_until->lt(today()), 'lead_id' => $x->lead_id,
        ]);

        return Inertia::render('Admin/Quotations/Index', [
            'rows' => $rows, 'filters' => $request->only(['q', 'status']), 'statuses' => Quotation::STATUSES,
            'counts' => collect(Quotation::STATUSES)->map(fn ($l, $k) => Quotation::where('status', $k)->count()),
        ]);
    }

    public function create(Request $request): Response
    {
        $lead = $request->integer('lead') ? Lead::with('tour')->find($request->integer('lead')) : null;

        return $this->form(null, $this->blank($lead));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $quote = DB::transaction(fn () => Quotation::create($data + [
            'number' => Quotation::nextNumber(), 'status' => 'draft', 'created_by' => $request->user()->id,
            'lead_id' => $request->integer('lead_id') ?: null,
        ]));
        if ($quote->lead_id) {
            LeadEvent::create(['lead_id' => $quote->lead_id, 'user_id' => $request->user()->id, 'event' => 'note', 'note' => 'Penawaran '.$quote->number.' dibuat.']);
        }

        return redirect('/admin/quotations/'.$quote->id.'/edit')->with('success', 'Penawaran '.$quote->number.' dibuat. Cek pratinjaunya lalu unduh PDF.');
    }

    public function edit(string $id): Response
    {
        $quote = Quotation::with(['lead:id,name', 'revisions.creator:id,name'])->findOrFail($id);
        $data = $quote->snapshot();
        $data['items'] = array_values($quote->items ?? []);

        return $this->form($quote, $data);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $quote = Quotation::findOrFail($id);
        $data = $this->validated($request);
        $revised = false;
        DB::transaction(function () use ($quote, $data, $request, &$revised) {
            $quote->fill($data);
            if (! $quote->isDirty()) {
                return;
            }
            // the client may already hold the earlier version: keep it, and number this one as a revision
            if ($quote->status !== 'draft') {
                $before = Quotation::find($quote->id);
                QuotationRevision::create(['quotation_id' => $quote->id, 'revision' => (int) $before?->revision, 'snapshot' => $before?->snapshot() ?? [], 'created_by' => $request->user()->id, 'created_at' => now()]);
                $quote->revision = (int) $before?->revision + 1;
                $revised = true;
            }
            $quote->save();
        });

        return back()->with('success', $revised ? 'Disimpan sebagai '.$quote->displayNumber().'. Versi sebelumnya tetap tersimpan di riwayat revisi.' : 'Penawaran disimpan.');
    }

    public function status(Request $request, string $id): RedirectResponse
    {
        $quote = Quotation::findOrFail($id);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Quotation::STATUSES))]]);
        $quote->update(['status' => $data['status'], 'sent_at' => $data['status'] === 'sent' ? ($quote->sent_at ?? now()) : $quote->sent_at]);
        if ($quote->lead_id) {
            LeadEvent::create(['lead_id' => $quote->lead_id, 'user_id' => $request->user()->id, 'event' => 'note', 'note' => 'Penawaran '.$quote->displayNumber().': '.Quotation::STATUSES[$data['status']].'.']);
        }

        return back()->with('success', 'Status penawaran: '.Quotation::STATUSES[$data['status']].'.');
    }

    public function duplicate(Request $request, string $id): RedirectResponse
    {
        $from = Quotation::findOrFail($id);
        $copy = $from->replicate(['number', 'revision', 'status', 'sent_at', 'created_by']);
        $copy->fill(['number' => Quotation::nextNumber(), 'revision' => 0, 'status' => 'draft', 'sent_at' => null, 'created_by' => $request->user()->id,
            'issued_on' => today(), 'valid_until' => today()->addDays($this->validityDays())]);
        $copy->save();

        return redirect('/admin/quotations/'.$copy->id.'/edit')->with('success', 'Salinan dibuat sebagai '.$copy->number.'.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $quote = Quotation::findOrFail($id);
        $quote->delete();

        return redirect('/admin/quotations')->with('success', 'Penawaran '.$quote->number.' dihapus.');
    }

    /** The PDF. ?download=1 saves it, otherwise it opens in the browser as a preview. */
    public function pdf(Request $request, string $id): \Symfony\Component\HttpFoundation\Response
    {
        $quote = Quotation::with('tour:id,name')->findOrFail($id);

        return $this->render($quote->snapshot(), $request->boolean('download'));
    }

    public function revisionPdf(string $id, string $revision): \Symfony\Component\HttpFoundation\Response
    {
        $rev = QuotationRevision::where('quotation_id', $id)->where('revision', (int) $revision)->firstOrFail();

        return $this->render($rev->snapshot, false, true);
    }

    /** @param array<string, mixed> $doc */
    protected function render(array $doc, bool $download, bool $old = false): \Symfony\Component\HttpFoundation\Response
    {
        $doc['number_label'] = $doc['number'].((int) ($doc['revision'] ?? 0) > 0 ? ' Rev. '.$doc['revision'] : '');
        $pdf = Pdf::loadView('admin.quotation-pdf', [
            'q' => $doc, 't' => Quotation::compute($doc['items'] ?? [], (float) ($doc['discount'] ?? 0), (float) ($doc['tax_percent'] ?? 0), $doc['currency'] ?? 'IDR'),
            'company' => $this->company(), 'old' => $old,
        ])->setPaper('a4');
        $name = 'Penawaran-'.Str::slug(str_replace('/', '-', $doc['number_label'])).'-'.Str::slug((string) $doc['client_name']).'.pdf';

        return $download ? $pdf->download($name) : $pdf->stream($name);
    }

    /** @return array<string, mixed> */
    protected function company(): array
    {
        $logo = null;
        $id = setting('logo_dark') ?: setting('logo_light');
        $m = $id ? (media_map()[(int) $id] ?? null) : null;
        if ($m && in_array($m['mime'], ['image/png', 'image/jpeg'], true)) {
            $file = public_path('uploads/'.$m['path']);
            $logo = is_file($file) ? 'data:'.$m['mime'].';base64,'.base64_encode((string) file_get_contents($file)) : null;
        }

        return [
            'name' => setting('company_name') ?: setting('site_name', config('app.name')),
            'suffix' => is_array(setting('site_suffix')) ? tr(setting('site_suffix'), 'id') : setting('site_suffix'),
            'address' => tr(setting('address'), 'id'),
            'phone' => setting('wa_display') ?: setting('wa_number'),
            'email' => setting('contact_email') ?: config('mail.from.address'),
            'web' => preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')),
            'logo' => $logo,
            'navy' => setting('color_navy', '#0b1a5c') ?: '#0b1a5c',
        ];
    }

    protected function validityDays(): int
    {
        return max(1, min(365, (int) (setting('quote_validity_days') ?: 14)));
    }

    /** A new quotation, filled from the lead when there is one. @return array<string, mixed> */
    protected function blank(?Lead $lead): array
    {
        $tour = $lead?->tour;
        $price = $tour ? $this->priceFor($tour, $lead->plan_date) : 0;
        $pax = $lead?->pax ?: 1;
        $title = $tour?->name ?: ($lead?->tour_name ?: '');

        return [
            'lead_id' => $lead?->id, 'tour_id' => $tour?->id, 'currency' => 'IDR',
            'issued_on' => today()->toDateString(), 'valid_until' => today()->addDays($this->validityDays())->toDateString(),
            'client_name' => (string) $lead?->name, 'client_company' => '', 'client_email' => (string) $lead?->email, 'client_phone' => (string) $lead?->phone,
            'client_address' => '', 'title' => $title, 'travel_date' => $lead?->plan_date?->toDateString(), 'pax' => $lead?->pax,
            'items' => [['description' => $title ? 'Paket '.$title.($tour?->duration ? ' ('.$tour->duration.')' : '') : '', 'qty' => $pax, 'unit' => 'pax', 'price' => $price]],
            'discount' => 0, 'tax_percent' => 0, 'tax_label' => 'PPN', 'notes' => '',
            'terms' => (string) setting('quote_terms', ''), 'payment_info' => (string) setting('quote_payment_info', ''),
            'signatory_name' => (string) setting('quote_signatory_name', ''), 'signatory_title' => (string) setting('quote_signatory_title', ''),
        ];
    }

    /** The adult price of the departure on (or nearest after) the planned date, otherwise "price from". */
    protected function priceFor(Tour $tour, ?DateTimeInterface $date): float
    {
        $deps = collect($tour->departures ?? [])->filter(fn ($d) => ! empty($d['date']) && ! empty($d['price_adult']))->sortBy('date');
        if ($date && $deps->isNotEmpty()) {
            $match = $deps->first(fn ($d) => $d['date'] >= $date->format('Y-m-d')) ?? $deps->last();

            return (float) $match['price_adult'];
        }

        return (float) ($tour->price_from ?? 0);
    }

    /** @param array<string, mixed> $data */
    protected function form(?Quotation $quote, array $data): Response
    {
        return Inertia::render('Admin/Quotations/Form', [
            'quote' => $quote ? [
                'id' => $quote->id, 'number' => $quote->displayNumber(), 'status' => $quote->status, 'lead' => $quote->lead ? ['id' => $quote->lead->id, 'name' => $quote->lead->name] : null,
                'sent_at' => $quote->sent_at?->locale('id')->isoFormat('D MMM YYYY'),
                'revisions' => $quote->revisions->map(fn (QuotationRevision $r) => ['revision' => $r->revision, 'label' => $r->revision ? 'Rev. '.$r->revision : 'Versi awal', 'by' => $r->creator?->name, 'at' => $r->created_at?->locale('id')->isoFormat('D MMM YYYY, HH:mm')]),
            ] : null,
            'data' => $data,
            'tours' => Tour::orderBy('name')->get(['id', 'name', 'duration', 'price_from', 'departures'])->map(fn (Tour $t) => [
                'id' => $t->id, 'name' => $t->name, 'duration' => $t->duration, 'price' => $this->priceFor($t, null),
                'departures' => collect($t->departures ?? [])->filter(fn ($d) => ! empty($d['date']) && ! empty($d['price_adult']))->map(fn ($d) => ['date' => $d['date'], 'price' => (float) $d['price_adult']])->sortBy('date')->values(),
            ]),
            'statuses' => Quotation::STATUSES, 'currencies' => Quotation::CURRENCIES,
        ]);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request): array
    {
        $d = $request->validate([
            'tour_id' => ['nullable', 'integer', 'exists:tours,id'],
            'currency' => ['required', Rule::in(array_keys(Quotation::CURRENCIES))],
            'issued_on' => ['required', 'date'], 'valid_until' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'client_name' => ['required', 'string', 'max:160'], 'client_company' => ['nullable', 'string', 'max:190'],
            'client_email' => ['nullable', 'email', 'max:190'], 'client_phone' => ['nullable', 'string', 'max:60'], 'client_address' => ['nullable', 'string', 'max:1000'],
            'title' => ['required', 'string', 'max:255'], 'travel_date' => ['nullable', 'date'], 'pax' => ['nullable', 'integer', 'between:1,10000'],
            'items' => ['required', 'array', 'min:1', 'max:40'],
            'items.*.description' => ['required', 'string', 'max:500'], 'items.*.qty' => ['required', 'numeric', 'min:0', 'max:100000'],
            'items.*.unit' => ['nullable', 'string', 'max:30'], 'items.*.price' => ['required', 'numeric', 'min:-1000000000000', 'max:1000000000000'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:1000000000000'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'], 'tax_label' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'], 'terms' => ['nullable', 'string', 'max:10000'], 'payment_info' => ['nullable', 'string', 'max:3000'],
            'signatory_name' => ['nullable', 'string', 'max:120'], 'signatory_title' => ['nullable', 'string', 'max:120'],
        ], [
            'client_name.required' => 'Nama klien wajib diisi.', 'title.required' => 'Judul penawaran wajib diisi (misalnya nama paket).',
            'items.required' => 'Isi minimal satu baris item.', 'items.*.description.required' => 'Deskripsi item wajib diisi.',
            'valid_until.after_or_equal' => 'Masa berlaku tidak boleh sebelum tanggal penawaran.',
        ]);
        $d['items'] = collect($d['items'])->map(fn ($i) => ['description' => trim($i['description']), 'qty' => (float) $i['qty'], 'unit' => trim((string) ($i['unit'] ?? '')), 'price' => (float) $i['price']])->values()->all();
        $d['discount'] = (float) ($d['discount'] ?? 0);
        $d['tax_percent'] = (float) ($d['tax_percent'] ?? 0);
        foreach (['issued_on', 'valid_until', 'travel_date'] as $f) {
            $d[$f] = filled($d[$f] ?? null) ? Carbon::parse($d[$f])->toDateString() : null;
        }

        return $d;
    }
}

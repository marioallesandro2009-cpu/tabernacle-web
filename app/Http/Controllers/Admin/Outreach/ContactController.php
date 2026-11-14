<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Jobs\Outreach\ValidateContactsJob;
use App\Models\ActivityLog;
use App\Models\Outreach\BlockedDomain;
use App\Models\Outreach\Contact;
use App\Models\Outreach\ContactImport;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Suppression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public const STATUS_FILTERS = ['sendable', 'pending', 'invalid_format', 'no_mx', 'empty', 'suppressed'];

    public function index(Request $request): Response
    {
        $q = $this->filtered($request)->with('segment:id,name')->orderByDesc('id');
        $contacts = $q->paginate(25)->withQueryString()->through(fn (Contact $c) => [
            'id' => $c->id, 'name' => $c->name, 'title' => $c->title, 'organization' => $c->organization, 'email' => $c->email,
            'phone' => $c->phone, 'segment_id' => $c->segment_id, 'segment' => $c->segment?->name, 'notes' => $c->notes,
            'status' => $c->suppressed_at ? 'suppressed' : $c->validation_status, 'note' => $c->suppressed_at ? $this->reasonLabel($c->suppressed_reason) : $c->validation_note,
        ]);

        return Inertia::render('Admin/Outreach/Contacts', [
            'contacts' => $contacts,
            'filters' => $request->only(['q', 'segment', 'status', 'org']),
            'segments' => Segment::orderBy('name')->get(['id', 'name']),
            'organizations' => Contact::whereNotNull('organization')->distinct()->orderBy('organization')->limit(300)->pluck('organization'),
            'counts' => [
                'total' => Contact::count(),
                'sendable' => Contact::sendable()->count(),
                'attention' => Contact::whereNull('suppressed_at')->whereIn('validation_status', ['invalid_format', 'no_mx', 'empty'])->count(),
                'pending' => Contact::whereNull('suppressed_at')->where('validation_status', 'pending')->count(),
                'suppressed' => Contact::whereNotNull('suppressed_at')->count(),
            ],
            'recentImports' => ContactImport::latest('id')->limit(3)->get(['id', 'filename', 'status', 'created_count', 'duplicate_count', 'created_at'])
                ->map(fn ($i) => ['id' => $i->id, 'filename' => $i->filename, 'status' => $i->status, 'created' => $i->created_count, 'duplicates' => $i->duplicate_count, 'at' => $i->created_at?->locale('id')->diffForHumans()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $contact = Contact::create($data);
        $this->checkNow($contact);

        return back()->with('success', 'Kontak ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $contact = Contact::findOrFail($id);
        $data = $this->validated($request, $contact);
        $emailChanged = mb_strtolower((string) ($data['email'] ?? '')) !== (string) $contact->email;
        $contact->update($data);
        if ($emailChanged) {
            $this->checkNow($contact->refresh());
        }

        return back()->with('success', 'Kontak disimpan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        Contact::findOrFail($id)->delete();

        return back()->with('success', 'Kontak dihapus.');
    }

    /** Actions on several ticked contacts at once. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['move', 'delete', 'revalidate', 'suppress'])],
            'segment_id' => ['nullable', 'integer', 'exists:outreach_segments,id'],
            'new_segment' => ['nullable', 'string', 'max:120'],
        ]);
        $query = Contact::whereIn('id', $data['ids']);
        $n = (clone $query)->count();
        switch ($data['action']) {
            case 'move':
                $segmentId = filled($data['new_segment'] ?? null)
                    ? Segment::firstOrCreate(['name' => trim($data['new_segment'])])->id
                    : ($data['segment_id'] ?? null);
                $query->update(['segment_id' => $segmentId]);
                $msg = "$n kontak dipindahkan".($segmentId ? ' ke segmen.' : ' (tanpa segmen).');
                break;
            case 'delete':
                $query->delete();
                $msg = "$n kontak dihapus.";
                break;
            case 'revalidate':
                $query->update(['validation_status' => 'pending', 'validation_note' => null, 'validated_at' => null]);
                ValidateContactsJob::dispatch();
                $msg = "$n kontak sedang dicek ulang.";
                break;
            default:
                $query->get()->each(fn (Contact $c) => $c->email ? Suppression::add($c->email, 'manual', 'Ditandai manual oleh admin') : null);
                $msg = "$n kontak ditandai jangan dikirimi.";
        }
        ActivityLog::record('update', 'Kontak Outreach', null, "$n kontak: ".$data['action']);

        return back()->with('success', $msg);
    }

    /**
     * @return Builder<Contact>
     */
    protected function filtered(Request $request): Builder
    {
        $q = Contact::query();
        if ($term = trim((string) $request->query('q'))) {
            $like = '%'.$term.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('organization', 'like', $like)->orWhere('title', 'like', $like));
        }
        if ($org = trim((string) $request->query('org'))) {
            $q->where('organization', $org);
        }
        $segment = $request->query('segment');
        if ($segment === 'none') {
            $q->whereNull('segment_id');
        } elseif (is_numeric($segment)) {
            $q->where('segment_id', (int) $segment);
        }
        $status = $request->query('status');
        if ($status === 'sendable') {
            $q->sendable();
        } elseif ($status === 'suppressed') {
            $q->whereNotNull('suppressed_at');
        } elseif (in_array($status, ['pending', 'invalid_format', 'no_mx', 'empty'], true)) {
            $q->whereNull('suppressed_at')->where('validation_status', $status);
        }

        return $q;
    }

    /** Checks a single address right away (one DNS lookup); a larger list goes through the queue. */
    protected function checkNow(Contact $contact): void
    {
        $contact->update(['validation_status' => 'pending', 'validation_note' => null, 'validated_at' => null]);
        if ($contact->email !== null && Suppression::where('email', $contact->email)->exists()) {
            $contact->update(['suppressed_at' => now(), 'suppressed_reason' => Suppression::where('email', $contact->email)->value('reason')]);
        } elseif (BlockedDomain::blocks($contact->email)) {
            $contact->update(['suppressed_at' => now(), 'suppressed_reason' => BlockedDomain::REASON]);
        }
        ValidateContactsJob::dispatch();
    }

    protected function reasonLabel(?string $reason): string
    {
        return ['unsubscribed' => 'Berhenti berlangganan', 'bounced' => 'Email ditolak (bounce)', 'manual' => 'Ditandai manual', BlockedDomain::REASON => 'Domain diblokir'][$reason ?? ''] ?? 'Jangan dikirimi';
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Contact $contact): array
    {
        // compare addresses case-insensitively, as the database does
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);

        return $request->validate([
            'name' => ['nullable', 'string', 'max:160'], 'title' => ['nullable', 'string', 'max:160'], 'organization' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'string', 'max:190', Rule::unique('outreach_contacts', 'email')->ignore($contact?->id)],
            'phone' => ['nullable', 'string', 'max:60'], 'notes' => ['nullable', 'string', 'max:2000'],
            'segment_id' => ['nullable', 'integer', 'exists:outreach_segments,id'],
        ], ['email.unique' => 'Email ini sudah ada di daftar kontak.']);
    }
}

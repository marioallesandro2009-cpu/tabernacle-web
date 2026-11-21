<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Jobs\Outreach\ValidateContactsJob;
use App\Jobs\Outreach\VerifyContactsJob;
use App\Models\ActivityLog;
use App\Models\Outreach\Account;
use App\Models\Outreach\BlockedDomain;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\ContactImport;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Suppression;
use App\Models\Outreach\Template;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\Verification\VerifierFactory;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public const STATUS_FILTERS = ['sendable', 'pending', 'invalid_format', 'no_mx', 'empty', 'disposable', 'suppressed'];

    public const VERIFY_FILTERS = ['valid', 'invalid', 'catch_all', 'unknown', 'disposable', 'none'];

    public function index(Request $request): Response
    {
        $q = $this->filtered($request)->with('segment:id,name')->orderByDesc('id');
        $contacts = $q->paginate(25)->withQueryString()->through(fn (Contact $c) => [
            'id' => $c->id, 'name' => $c->name, 'title' => $c->title, 'organization' => $c->organization, 'email' => $c->email,
            'phone' => $c->phone, 'segment_id' => $c->segment_id, 'segment' => $c->segment?->name, 'notes' => $c->notes,
            'is_role' => $c->is_role, 'verify_status' => $c->verify_status, 'verify_note' => $c->verify_note,
            'status' => $c->suppressed_at ? 'suppressed' : $c->validation_status, 'note' => $c->suppressed_at ? $this->reasonLabel($c->suppressed_reason) : $c->validation_note,
        ]);

        return Inertia::render('Admin/Outreach/Contacts', [
            'contacts' => $contacts,
            'filters' => $request->only(['q', 'segment', 'status', 'org', 'verify', 'role']),
            'verifier' => app(VerifierFactory::class)->make()?->name(), 'verifyError' => Cache::get('outreach:verify-error'),
            'segments' => Segment::orderBy('name')->get(['id', 'name']),
            // for "Kirim email" on ticked contacts: who sends and which text
            'senders' => Account::where('is_active', true)->orderBy('name')->get()->map(fn (Account $a) => ['id' => $a->id, 'name' => $a->name, 'email' => $a->email, 'limit_today' => $a->limitToday()]),
            'templates' => Template::with('segment:id,name')->where('is_active', true)->orderBy('name')->get()->map(fn (Template $t) => ['id' => $t->id, 'name' => $t->name, 'segment_id' => $t->segment_id, 'segment' => $t->segment?->name, 'subject' => $t->subject, 'body' => $t->body]),
            'organizations' => Contact::whereNotNull('organization')->distinct()->orderBy('organization')->limit(300)->pluck('organization'),
            'counts' => [
                'total' => Contact::count(),
                'sendable' => Contact::sendable()->count(),
                'attention' => Contact::whereNull('suppressed_at')->whereIn('validation_status', ['invalid_format', 'no_mx', 'empty', 'disposable'])->count(),
                'role' => Contact::where('is_role', true)->count(),
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
    /**
     * Before "Kirim email" on ticked contacts: how many will really get it, how many are left out
     * because their address may not be mailed, and how many are already in another campaign.
     */
    public function sendCheck(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer']]);
        $ids = Contact::whereIn('id', $data['ids'])->pluck('id');
        $busy = CampaignContact::whereIn('contact_id', $ids)->whereIn('status', CampaignService::ALREADY_IN)->distinct()->pluck('contact_id');
        $free = $ids->diff($busy);
        $sendable = Contact::whereIn('id', $free)->sendable()->count();
        $pending = Contact::whereIn('id', $free)->whereNull('suppressed_at')->where('validation_status', 'pending')->count();

        return response()->json([
            'total' => $ids->count(), 'sendable' => $sendable, 'pending' => $pending,
            'invalid' => $free->count() - $sendable - $pending, 'busy' => $busy->count(),
        ]);
    }

    /**
     * "Kirim email" on ticked contacts: makes a small campaign with exactly those people, one
     * sender account and one template, and starts it. The emails still go out one by one inside
     * the sending hours and the account's daily limit (warm-up), never all at once.
     */
    public function send(Request $request, CampaignService $service): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer'],
            'account_id' => ['required', 'integer', Rule::exists('outreach_accounts', 'id')->where('is_active', true)],
            'template_id' => ['required', 'integer', Rule::exists('outreach_templates', 'id')->where('is_active', true)],
        ], ['account_id.required' => 'Pilih email pengirim.', 'account_id.exists' => 'Akun pengirim tidak aktif.', 'template_id.required' => 'Pilih template.']);
        $template = Template::findOrFail($data['template_id']);
        try {
            [$campaign, $result] = DB::transaction(function () use ($data, $template, $request, $service) {
                $campaign = Campaign::create([
                    'name' => 'Kirim manual – '.$template->name.' – '.now(Campaign::TIMEZONE)->locale('id')->isoFormat('D MMM YYYY HH:mm'),
                    'channel' => 'email', 'manual' => true, 'segment_id' => null, 'status' => 'draft', 'allow_catch_all' => false,
                    'window_start' => '08:00', 'window_end' => '16:00', 'send_days' => [1, 2, 3, 4, 5],
                    'gap_min_seconds' => 180, 'gap_max_seconds' => 480, 'created_by' => $request->user()->id,
                ]);
                $campaign->accounts()->sync([$data['account_id']]);
                $campaign->steps()->create(['step' => 1, 'template_id' => $template->id, 'day_offset' => 0]);

                return [$campaign, $service->startManual($campaign, array_map('intval', $data['ids']))];
            });
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        if ($result['added'] === 0) {
            $campaign->delete();

            return back()->with('error', 'Tidak ada yang bisa dikirimi: '.$result['busy'].' kontak sudah ada di kampanye lain dan '.$result['invalid'].' alamat tidak boleh dikirimi.');
        }
        ActivityLog::record('create', 'Kampanye Outreach', $campaign->id, $campaign->name);
        $skipped = array_filter([$result['busy'] ? $result['busy'].' sudah ada di kampanye lain' : null, $result['invalid'] ? $result['invalid'].' alamat tidak boleh dikirimi' : null]);

        return redirect(admin_path().'/outreach/campaigns/'.$campaign->id)->with('success', $result['added'].' email masuk antrean dan dikirim bertahap pada jam kirim.'.($skipped ? ' Dilewati: '.implode(', ', $skipped).'.' : ''));
    }

    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'], 'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['move', 'delete', 'revalidate', 'verify', 'suppress'])],
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
            case 'verify':
                $msg = $this->queueVerify($query->pluck('id')->all());
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
        } elseif (in_array($status, ['pending', 'invalid_format', 'no_mx', 'empty', 'disposable'], true)) {
            $q->whereNull('suppressed_at')->where('validation_status', $status);
        }
        $verify = $request->query('verify');
        if ($verify === 'none') {
            $q->whereNull('verify_status');
        } elseif (in_array($verify, self::VERIFY_FILTERS, true)) {
            $q->where('verify_status', $verify);
        }
        if ($request->query('role') === '1') {
            $q->where('is_role', true);
        }

        return $q;
    }

    /** "Verifikasi ulang" for one contact or a whole segment (selected contacts go through bulk()). */
    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['contact_id' => ['nullable', 'integer', 'exists:outreach_contacts,id'], 'segment_id' => ['nullable', 'integer', 'exists:outreach_segments,id']]);
        $ids = match (true) {
            ! empty($data['contact_id']) => [(int) $data['contact_id']],
            ! empty($data['segment_id']) => Contact::where('segment_id', $data['segment_id'])->whereNotNull('email')->pluck('id')->all(),
            default => [],
        };
        if (! $ids) {
            return back()->with('error', 'Tidak ada kontak dengan email untuk diverifikasi.');
        }
        ActivityLog::record('update', 'Kontak Outreach', null, count($ids).' kontak: verifikasi ulang');

        return back()->with('success', $this->queueVerify($ids));
    }

    /** @param array<int, int> $ids */
    protected function queueVerify(array $ids): string
    {
        Cache::forget('outreach:verify-error');
        VerifyContactsJob::dispatch(array_values($ids));
        $service = app(VerifierFactory::class)->make();

        return count($ids).' kontak masuk antrean verifikasi'.($service ? ' (termasuk '.ucfirst($service->name()).').' : '. Layanan verifikasi belum diatur, jadi hanya cek format, domain, MX, alamat peran, dan email sekali pakai.');
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
        return ['unsubscribed' => 'Berhenti berlangganan', 'bounced' => 'Email ditolak (bounce)', 'manual' => 'Ditandai manual', BlockedDomain::REASON => 'Domain diblokir', 'disposable' => 'Email sekali pakai'][$reason ?? ''] ?? 'Jangan dikirimi';
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

<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outreach\BlockedDomain;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Suppression;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Who never gets an email: whole domains (competitors, clients) and single addresses. */
class BlocklistController extends Controller
{
    public const REASONS = ['unsubscribed' => 'Berhenti berlangganan', 'bounced' => 'Email ditolak (bounce)', 'manual' => 'Ditandai manual'];

    public function index(Request $request): Response
    {
        $domains = BlockedDomain::orderBy('domain')->get()->map(fn (BlockedDomain $d) => [
            'id' => $d->id, 'domain' => $d->domain, 'note' => $d->note, 'at' => $d->created_at?->locale('id')->isoFormat('D MMM YYYY'),
            'contacts' => Contact::where(fn ($q) => $q->where('email', 'like', '%@'.$d->domain)->orWhere('email', 'like', '%.'.$d->domain))->count(),
        ]);
        $q = Suppression::query()->orderByDesc('id');
        if ($term = trim((string) $request->query('q'))) {
            $q->where('email', 'like', '%'.$term.'%');
        }
        $reason = $request->query('reason');
        if (is_string($reason) && isset(self::REASONS[$reason])) {
            $q->where('reason', $reason);
        }
        $emails = $q->paginate(30)->withQueryString()->through(fn (Suppression $s) => [
            'id' => $s->id, 'email' => $s->email, 'reason' => $s->reason, 'reason_label' => self::REASONS[$s->reason] ?? $s->reason,
            'note' => $s->note, 'at' => $s->created_at?->locale('id')->isoFormat('D MMM YYYY'),
        ]);

        return Inertia::render('Admin/Outreach/Blocklist', [
            'domains' => $domains,
            'emails' => $emails,
            'filters' => $request->only(['q', 'reason']),
            'reasons' => self::REASONS,
            'counts' => collect(self::REASONS)->map(fn ($l, $k) => Suppression::where('reason', $k)->count()),
        ]);
    }

    public function storeDomain(Request $request): RedirectResponse
    {
        $request->merge(['domain' => BlockedDomain::normalize((string) $request->input('domain'))]);
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:190', 'unique:outreach_blocked_domains,domain', function ($attr, $value, $fail) {
                if (! BlockedDomain::isValidDomain((string) $value)) {
                    $fail('Tulis domainnya saja, misalnya kompetitor.com');
                }
            }],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['domain.unique' => 'Domain ini sudah ada di daftar blokir.', 'domain.required' => 'Domain wajib diisi.']);
        $row = BlockedDomain::block($data['domain'], $data['note'] ?? null);
        $n = Contact::where('suppressed_reason', BlockedDomain::REASON)->where(fn ($q) => $q->where('email', 'like', '%@'.$row->domain)->orWhere('email', 'like', '%.'.$row->domain))->count();
        ActivityLog::record('create', 'Blokir domain', $row->id, $row->domain);

        return back()->with('success', "Domain {$row->domain} diblokir.".($n ? " $n kontak dari domain ini tidak akan dikirimi lagi." : ''));
    }

    public function destroyDomain(string $id): RedirectResponse
    {
        $row = BlockedDomain::findOrFail($id);
        ActivityLog::record('delete', 'Blokir domain', $row->id, $row->domain);
        $row->unblock();

        return back()->with('success', "Blokir {$row->domain} dibuka. Kontaknya bisa masuk kampanye baru lagi.");
    }

    /** Adds single addresses (one per line, or separated by commas) to the do-not-contact list. */
    public function storeEmails(Request $request): RedirectResponse
    {
        $data = $request->validate(['emails' => ['required', 'string', 'max:20000'], 'note' => ['nullable', 'string', 'max:255']], ['emails.required' => 'Isi minimal satu alamat email.']);
        $list = collect(preg_split('/[\s,;]+/', mb_strtolower($data['emails'])) ?: [])->map(fn ($e) => trim($e))->filter()->unique();
        $good = $list->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL));
        if ($good->isEmpty()) {
            return back()->withErrors(['emails' => 'Tidak ada alamat email yang valid.']);
        }
        $good->each(fn ($e) => Suppression::add($e, 'manual', $data['note'] ?? 'Ditambahkan manual di daftar blokir'));
        ActivityLog::record('create', 'Daftar blokir', null, $good->count().' email');
        $bad = $list->count() - $good->count();

        return back()->with('success', $good->count().' email masuk daftar blokir.'.($bad ? " $bad baris bukan email dan dilewati." : ''));
    }

    /**
     * Only addresses blocked by hand can be released. Opt-outs must stay (the person asked not to be
     * written to) and so must bounces (the mailbox does not exist).
     */
    public function destroyEmail(string $id): RedirectResponse
    {
        $row = Suppression::findOrFail($id);
        if ($row->reason !== 'manual') {
            return back()->with('error', 'Hanya email yang ditandai manual yang bisa dibuka. Orang yang berhenti berlangganan atau email bounce tetap diblokir.');
        }
        $row->delete();
        Contact::where('email', $row->email)->where('suppressed_reason', 'manual')->get()->each(function (Contact $c) {
            BlockedDomain::blocks($c->email)
                ? $c->update(['suppressed_reason' => BlockedDomain::REASON])
                : $c->update(['suppressed_at' => null, 'suppressed_reason' => null]);
        });
        ActivityLog::record('delete', 'Daftar blokir', $row->id, $row->email);

        return back()->with('success', "{$row->email} dikeluarkan dari daftar blokir.");
    }
}

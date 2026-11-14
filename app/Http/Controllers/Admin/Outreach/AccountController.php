<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outreach\Account;
use App\Services\Outreach\ConnectionTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The mailboxes on the company domain that send campaigns and receive the replies. */
class AccountController extends Controller
{
    public function index(): Response
    {
        $accounts = Account::orderBy('id')->get()->map(fn (Account $a) => [
            'id' => $a->id, 'channel' => $a->channel, 'name' => $a->name, 'email' => $a->email,
            'smtp_host' => $a->smtp_host, 'smtp_port' => $a->smtp_port, 'smtp_encryption' => $a->smtp_encryption,
            'imap_host' => $a->imap_host, 'imap_port' => $a->imap_port, 'imap_encryption' => $a->imap_encryption,
            'username' => $a->username, 'daily_limit' => $a->daily_limit, 'is_active' => $a->is_active,
            'warmup_enabled' => $a->warmup_enabled, 'warmup_start' => $a->warmup_start, 'warmup_step' => $a->warmup_step,
            'forward_enabled' => $a->forward_enabled, 'forward_to' => (string) $a->forward_to, 'relay_enabled' => $a->relay_enabled,
            'relay_senders' => (string) $a->relay_senders, 'relay_mode' => $a->relay_mode,
            'limit_today' => $a->limitToday(), 'warming_up' => $a->warmingUp(), 'warmup_week' => $a->warmupWeek(), 'warmup_started' => $a->warmup_started_on !== null,
            'smtp_ok' => $a->smtp_ok, 'smtp_error' => $a->smtp_error, 'smtp_tested_at' => $a->smtp_tested_at?->locale('id')->diffForHumans(),
            'imap_ok' => $a->imap_ok, 'imap_error' => $a->imap_error, 'imap_tested_at' => $a->imap_tested_at?->locale('id')->diffForHumans(),
            'imap_polled_at' => $a->imap_polled_at?->locale('id')->diffForHumans(),
        ]);

        return Inertia::render('Admin/Outreach/Accounts', ['accounts' => $accounts, 'presets' => Account::PRESETS]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $account = Account::create($data + ['channel' => 'email']);
        ActivityLog::record('create', 'Akun pengirim', $account->id, $account->email);

        return back()->with('success', 'Akun pengirim ditambahkan. Klik "Tes koneksi" untuk memastikan loginnya benar.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $account = Account::findOrFail($id);
        $data = $this->validated($request, $account);
        if (blank($data['password'] ?? null)) {
            unset($data['password']); // blank = keep the saved password
        }
        $account->update($data);
        ActivityLog::record('update', 'Akun pengirim', $account->id, $account->email);

        return back()->with('success', 'Akun pengirim disimpan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $account = Account::findOrFail($id);
        ActivityLog::record('delete', 'Akun pengirim', $account->id, $account->email);
        $account->delete();

        return back()->with('success', 'Akun pengirim dihapus.');
    }

    /** Logs in to SMTP and IMAP (nothing is sent) and remembers the result. */
    public function test(string $id, ConnectionTester $tester): RedirectResponse
    {
        $account = Account::findOrFail($id);
        $smtp = $tester->smtp($account);
        $imap = $tester->imap($account);
        $account->update([
            'smtp_ok' => $smtp['ok'], 'smtp_error' => $smtp['ok'] ? null : $smtp['message'], 'smtp_tested_at' => now(),
            'imap_ok' => $imap['ok'], 'imap_error' => $imap['ok'] ? null : $imap['message'], 'imap_tested_at' => now(),
        ]);
        if ($smtp['ok'] && $imap['ok']) {
            return back()->with('success', 'SMTP dan IMAP berhasil login. '.$imap['message']);
        }

        return back()->with('error', 'SMTP: '.$smtp['message'].' | IMAP: '.$imap['message']);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Account $account): array
    {
        $enc = Rule::in(Account::ENCRYPTIONS);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('outreach_accounts', 'email')->ignore($account?->id)],
            'smtp_host' => ['required', 'string', 'max:190'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', $enc],
            'imap_host' => ['nullable', 'string', 'max:190'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required', $enc],
            'username' => ['required', 'string', 'max:190'],
            'password' => [$account ? 'nullable' : 'required', 'string', 'max:500'],
            'daily_limit' => ['required', 'integer', 'between:1,500'],
            'is_active' => ['boolean'],
            'warmup_enabled' => ['boolean'],
            'warmup_start' => ['sometimes', 'integer', 'between:1,500'],
            'warmup_step' => ['sometimes', 'integer', 'between:0,100'],
            'forward_enabled' => ['boolean'], 'forward_to' => ['nullable', 'string', 'max:2000'],
            'relay_enabled' => ['boolean'], 'relay_senders' => ['nullable', 'string', 'max:2000'],
            'relay_mode' => ['sometimes', Rule::in(array_keys(Account::RELAY_MODES))],
        ], [
            'email.unique' => 'Alamat email ini sudah terdaftar sebagai akun pengirim.',
            'password.required' => 'Password email wajib diisi.',
            'warmup_start.between' => 'Angka awal warm-up antara 1 dan 500.',
        ]);

        return $data + $this->replyHandling($request, mb_strtolower($data['email']));
    }

    /**
     * Forwarding and "answer from a personal mailbox": lists cleaned to one address per line, and
     * the combinations that cannot work refused with a clear message.
     *
     * @return array<string, mixed>
     */
    protected function replyHandling(Request $request, string $own): array
    {
        $errors = [];
        foreach (['forward_to', 'relay_senders'] as $f) {
            $bad = collect(preg_split('/[\s,;]+/', (string) $request->input($f)) ?: [])->map(fn ($e) => trim($e))->filter()->reject(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->values();
            if ($bad->isNotEmpty()) {
                $errors[$f] = 'Bukan alamat email: '.$bad->take(3)->implode(', ');
            }
        }
        $forward = Account::emailList($request->input('forward_to'));
        $senders = Account::emailList($request->input('relay_senders'));
        $forwardOn = $request->boolean('forward_enabled');
        $relayOn = $request->boolean('relay_enabled');
        if ($forwardOn && ! $forward) {
            $errors['forward_to'] ??= 'Isi minimal satu alamat tujuan terusan.';
        }
        if (in_array($own, $forward, true) || in_array($own, $senders, true)) {
            $errors[in_array($own, $forward, true) ? 'forward_to' : 'relay_senders'] = 'Alamat akun pengirim ini sendiri tidak boleh dipakai di sini.';
        }
        if ($relayOn && ! $forwardOn) {
            $errors['relay_enabled'] = 'Aktifkan terusan balasan dulu: balasan dari email pribadi dikirim lewat email terusan itu.';
        }
        if ($relayOn && ! $senders) {
            $errors['relay_senders'] ??= 'Isi minimal satu email pribadi yang boleh membalas.';
        }
        if (count($forward) > 10 || count($senders) > 10) {
            $errors[count($forward) > 10 ? 'forward_to' : 'relay_senders'] = 'Maksimal 10 alamat.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'forward_enabled' => $forwardOn, 'forward_to' => $forward ? implode("\n", $forward) : null,
            'relay_enabled' => $relayOn, 'relay_senders' => $senders ? implode("\n", $senders) : null,
            'relay_mode' => $request->input('relay_mode', 'review') === 'direct' ? 'direct' : 'review',
        ];
    }
}

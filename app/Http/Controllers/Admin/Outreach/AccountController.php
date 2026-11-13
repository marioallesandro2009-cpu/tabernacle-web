<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outreach\Account;
use App\Services\Outreach\ConnectionTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

        return $request->validate([
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
        ], [
            'email.unique' => 'Alamat email ini sudah terdaftar sebagai akun pengirim.',
            'password.required' => 'Password email wajib diisi.',
        ]);
    }
}

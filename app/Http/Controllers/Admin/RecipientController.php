<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** The (max 10) email addresses that receive every new lead. */
class RecipientController extends Controller
{
    public function index(): Response
    {
        $rows = LeadRecipient::orderBy('id')->get(['id', 'email', 'name', 'is_active']);

        return Inertia::render('Admin/Recipients', [
            'recipients' => $rows,
            'max' => LeadRecipient::MAX,
            'enabled' => (bool) setting('lead_notify_enabled', true),
            'mailer' => config('mail.default'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (LeadRecipient::count() >= LeadRecipient::MAX) {
            return back()->with('error', 'Maksimal '.LeadRecipient::MAX.' email penerima. Hapus salah satu dulu.');
        }
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('lead_recipients', 'email')],
            'name' => ['nullable', 'string', 'max:120'],
        ], ['email.unique' => 'Email ini sudah ada di daftar.']);
        $data['email'] = strtolower(trim($data['email']));
        LeadRecipient::create($data + ['is_active' => true]);

        return back()->with('success', 'Email penerima ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $r = LeadRecipient::findOrFail($id);
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('lead_recipients', 'email')->ignore($r->id)],
            'name' => ['nullable', 'string', 'max:120'],
            'is_active' => ['boolean'],
        ]);
        $data['email'] = strtolower(trim($data['email']));
        $r->update($data);

        return back()->with('success', 'Disimpan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        LeadRecipient::findOrFail($id)->delete();

        return back()->with('success', 'Email penerima dihapus.');
    }

    /** Sends a short test message so the admin can confirm SMTP works. */
    public function test(string $id): RedirectResponse
    {
        $r = LeadRecipient::findOrFail($id);
        try {
            Mail::raw('Ini email uji dari '.setting('site_name').'. Jika Anda menerimanya, notifikasi lead ke alamat ini berfungsi.', function ($m) use ($r) {
                $m->to($r->email)->subject('Tes notifikasi lead — '.setting('site_name'));
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Gagal mengirim: '.mb_substr($e->getMessage(), 0, 200));
        }
        $note = config('mail.default') === 'log' ? ' (MAIL_MAILER masih "log": email hanya dicatat di storage/logs, belum dikirim. Isi SMTP di .env.)' : '';

        return back()->with('success', 'Email uji dikirim ke '.$r->email.'.'.$note);
    }
}

<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1b2236">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0b1a5c;color:#ffffff;padding:20px 28px">
        <div style="font-size:12px;letter-spacing:.12em;font-weight:bold;opacity:.8">{{ strtoupper((string) setting('site_name', config('app.name'))) }} · OUTREACH</div>
        <div style="margin-top:6px;font-size:20px;font-weight:bold">Ada balasan baru</div>
        <div style="margin-top:2px;font-size:13px;opacity:.8">Balas secepatnya: peluang paling besar ada di jam-jam pertama.</div>
    </td></tr>
    <tr><td style="padding:24px 28px">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5">
            <tr><td style="padding:4px 0;color:#5d6578;width:120px">Dari</td><td style="padding:4px 0;font-weight:bold">{{ $contact?->name ?: '-' }} &lt;{{ $contact?->email }}&gt;</td></tr>
            @if ($contact?->title || $contact?->organization)
                <tr><td style="padding:4px 0;color:#5d6578">Jabatan</td><td style="padding:4px 0">{{ collect([$contact->title, $contact->organization])->filter()->implode(' · ') }}</td></tr>
            @endif
            @if ($campaign)
                <tr><td style="padding:4px 0;color:#5d6578">Kampanye</td><td style="padding:4px 0">{{ $campaign->name }}</td></tr>
            @endif
            <tr><td style="padding:4px 0;color:#5d6578">Subjek</td><td style="padding:4px 0">{{ $m->subject ?: '(tanpa subjek)' }}</td></tr>
            <tr><td style="padding:4px 0;color:#5d6578">Diterima ke</td><td style="padding:4px 0">{{ $m->account?->email }}</td></tr>
        </table>
        <div style="margin-top:16px;padding:14px 16px;background:#f4f6fb;border-radius:10px;font-size:14px;line-height:1.6;white-space:pre-wrap">{{ $snippet }}</div>
        <p style="margin:20px 0 0">
            <a href="{{ $repliesUrl }}" style="display:inline-block;background:#0b1a5c;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:11px 18px;border-radius:10px">Buka halaman Balasan</a>
        </p>
        <p style="margin:16px 0 0;font-size:12px;color:#8b93a7;line-height:1.6">Balas dari kotak email {{ $m->account?->email }} supaya percakapannya tetap satu thread. Follow-up untuk orang ini sudah otomatis dihentikan.</p>
    </td></tr>
</table>
</body>
</html>

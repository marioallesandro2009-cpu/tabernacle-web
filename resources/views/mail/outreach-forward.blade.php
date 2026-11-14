<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1b2236">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0b1a5c;color:#ffffff;padding:18px 26px">
        <div style="font-size:12px;letter-spacing:.12em;font-weight:bold;opacity:.8">{{ strtoupper((string) setting('site_name', config('app.name'))) }} · BALASAN PROSPEK</div>
        <div style="margin-top:6px;font-size:19px;font-weight:bold">{{ $contact?->name ?: $contact?->email }}@if ($contact?->organization) · {{ $contact->organization }}@endif</div>
    </td></tr>
    <tr><td style="padding:22px 26px">
        @if ($relay)
            <p style="margin:0 0 16px;padding:10px 12px;background:#eef6ff;border-radius:8px;font-size:13px;line-height:1.5;color:#1b3a66">
                Anda bisa langsung <b>membalas email ini</b>. Balasan Anda dikirim ke prospek dari akun {{ $r->account?->email }}. Jangan ubah kode <b>[#{{ $token }}]</b> di subjek.
            </p>
        @endif
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5">
            <tr><td style="padding:3px 0;color:#5d6578;width:110px">Dari</td><td style="padding:3px 0"><b>{{ $contact?->name ?: '-' }}</b> &lt;{{ $contact?->email }}&gt;</td></tr>
            @if ($contact?->title)<tr><td style="padding:3px 0;color:#5d6578">Jabatan</td><td style="padding:3px 0">{{ $contact->title }}</td></tr>@endif
            @if ($campaign)<tr><td style="padding:3px 0;color:#5d6578">Kampanye</td><td style="padding:3px 0">{{ $campaign->name }}</td></tr>@endif
            @if ($segment)<tr><td style="padding:3px 0;color:#5d6578">Segmen</td><td style="padding:3px 0">{{ $segment }}</td></tr>@endif
            <tr><td style="padding:3px 0;color:#5d6578">Diterima di</td><td style="padding:3px 0">{{ $r->account?->email }}</td></tr>
        </table>

        <div style="margin:16px 0 6px;font-size:12px;font-weight:bold;letter-spacing:.08em;color:#5d6578">BALASAN PROSPEK</div>
        <div style="padding:14px 16px;background:#f4f6fb;border-radius:10px;font-size:14px;line-height:1.6;white-space:pre-wrap">{{ $r->body }}</div>

        @if ($lastBody)
            <div style="margin:18px 0 6px;font-size:12px;font-weight:bold;letter-spacing:.08em;color:#8b93a7">EMAIL TERAKHIR KITA{{ $last?->sent_at ? ' · '.$last->sent_at->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMM YYYY, HH:mm') : '' }}</div>
            <div style="padding:10px 14px;border-left:3px solid #d6dbe7;font-size:13px;line-height:1.55;color:#5d6578;white-space:pre-wrap">{{ $lastBody }}</div>
        @endif

        <p style="margin:22px 0 0"><a href="{{ $threadUrl }}" style="display:inline-block;background:#0b1a5c;color:#ffffff;text-decoration:none;font-weight:bold;font-size:14px;padding:11px 18px;border-radius:10px">Buka di website</a></p>
    </td></tr>
</table>
</body>
</html>

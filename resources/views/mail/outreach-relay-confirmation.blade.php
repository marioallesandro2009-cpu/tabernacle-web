<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1b2236">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0b1a5c;color:#ffffff;padding:18px 26px">
        <div style="font-size:12px;letter-spacing:.12em;font-weight:bold;opacity:.8">{{ strtoupper((string) setting('site_name', config('app.name'))) }} · OUTREACH</div>
        <div style="margin-top:6px;font-size:19px;font-weight:bold">{{ $word }}</div>
    </td></tr>
    <tr><td style="padding:22px 26px;font-size:14px;line-height:1.6">
        <p style="margin:0 0 12px">{{ $sentence }}</p>
        @if ($attachmentsIgnored)
            <p style="margin:0 0 12px;padding:10px 12px;background:#fff7e6;border-radius:8px;color:#8a5a00">Lampiran di email Anda tidak ikut dikirim. Untuk saat ini balasan dari email pribadi hanya meneruskan teks.</p>
        @endif
        <p style="margin:0 0 4px;color:#5d6578">Prospek: <b style="color:#1b2236">{{ $m->contact?->name ?: '-' }}</b> &lt;{{ $m->contact?->email }}&gt;</p>
        @if (trim((string) $m->body) !== '')
            <div style="margin-top:12px;padding:12px 14px;background:#f4f6fb;border-radius:10px;white-space:pre-wrap">{{ \Illuminate\Support\Str::limit((string) $m->body, 1200) }}</div>
        @endif
        <p style="margin:18px 0 0"><a href="{{ $threadUrl }}" style="display:inline-block;background:#0b1a5c;color:#ffffff;text-decoration:none;font-weight:bold;padding:10px 16px;border-radius:10px">Buka di website</a></p>
    </td></tr>
</table>
</body>
</html>

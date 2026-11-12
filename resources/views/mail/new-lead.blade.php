<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f6efe4;font-family:Arial,Helvetica,sans-serif;color:#1d1813">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff">
    <tr><td style="background:#0b1a5c;color:#ffffff;padding:18px 24px;font-size:13px;letter-spacing:.12em;font-weight:bold">{{ strtoupper((string) setting('site_name', config('app.name'))) }} · LEAD BARU</td></tr>
    <tr><td style="padding:24px">
        <p style="margin:0 0 16px;font-size:16px">Ada permintaan baru dari website.</p>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.5">
            @foreach ([
                'Nama' => $lead->name,
                'WhatsApp' => $lead->phone ? '+'.$lead->phone : null,
                'Email' => $lead->email,
                'Paket' => $lead->tour_name,
                'Jumlah peserta' => $lead->pax,
                'Rencana tanggal' => $lead->plan_date?->translatedFormat('d F Y'),
                'Pesan' => $lead->message,
                'Halaman asal' => $lead->page_url,
                'Sumber' => trim(($lead->source ?: '').($lead->utm_campaign ? ' / '.$lead->utm_campaign : '')),
            ] as $label => $value)
                @if ($value !== null && $value !== '')
                    <tr>
                        <td style="padding:6px 12px 6px 0;color:#6a5f53;vertical-align:top;white-space:nowrap">{{ $label }}</td>
                        <td style="padding:6px 0;vertical-align:top">{{ $value }}</td>
                    </tr>
                @endif
            @endforeach
        </table>
        <p style="margin:24px 0 0">
            @if ($lead->phone)
                <a href="https://wa.me/{{ $lead->phone }}" style="display:inline-block;background:#e8973f;color:#071140;padding:12px 18px;text-decoration:none;font-weight:bold;font-size:13px;margin-right:8px">Balas lewat WhatsApp</a>
            @endif
            <a href="{{ $adminUrl }}" style="display:inline-block;border:1px solid #0b1a5c;color:#0b1a5c;padding:11px 18px;text-decoration:none;font-weight:bold;font-size:13px">Buka di admin</a>
        </p>
    </td></tr>
    <tr><td style="padding:14px 24px;background:#f6efe4;color:#6a5f53;font-size:12px">Email otomatis dari {{ parse_url(config('app.url'), PHP_URL_HOST) }}. Daftar penerima diatur di Admin &gt; Leads &gt; Penerima notifikasi.</td></tr>
</table>
</body>
</html>

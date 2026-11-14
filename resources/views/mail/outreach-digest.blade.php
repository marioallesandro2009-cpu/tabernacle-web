<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1b2236">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0b1a5c;color:#ffffff;padding:20px 28px">
        <div style="font-size:12px;letter-spacing:.12em;font-weight:bold;opacity:.8">{{ strtoupper((string) setting('site_name', config('app.name'))) }} · OUTREACH</div>
        <div style="margin-top:6px;font-size:20px;font-weight:bold">Ringkasan minggu ini</div>
        <div style="margin-top:2px;font-size:13px;opacity:.8">{{ $d['from']->copy()->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMM') }} – {{ $d['to']->copy()->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMM YYYY') }}</div>
    </td></tr>
    <tr><td style="padding:24px 28px">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            @foreach ([[['Email terkirim', $d['sent']], ['Balasan', $d['replies']], ['Leads baru', $d['new_leads']]], [['Tertarik', $d['interested']], ['Bounce', $d['bounces']], ['Berhenti langganan', $d['unsubscribed']]]] as $row)
                <tr>
                    @foreach ($row as [$label, $value])
                        <td width="33%" style="padding:6px">
                            <div style="background:#f4f6fb;border-radius:10px;padding:12px 14px">
                                <div style="font-size:26px;font-weight:bold;color:#0b1a5c">{{ number_format($value, 0, ',', '.') }}</div>
                                <div style="font-size:12px;color:#5d6578">{{ $label }}</div>
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
        <p style="margin:16px 6px 0;font-size:13px;color:#5d6578;line-height:1.6">
            {{ $d['running'] }} kampanye sedang berjalan.
            @if ($d['hard_bounces']) {{ $d['hard_bounces'] }} bounce permanen sudah otomatis masuk daftar jangan dikirimi. @endif
            @if ($d['auto']) {{ $d['auto'] }} balasan otomatis (cuti atau out of office) tercatat. @endif
        </p>

        @if (count($d['segments']))
            <div style="margin:24px 6px 8px;font-size:12px;font-weight:bold;letter-spacing:.08em;color:#5d6578">PER SEGMEN</div>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;border-top:1px solid #e6e9f2">
                <tr style="color:#8b93a7;font-size:12px"><td style="padding:8px 6px">Segmen</td><td align="right" style="padding:8px 6px">Terkirim</td><td align="right" style="padding:8px 6px">Balasan</td><td align="right" style="padding:8px 6px">Bounce</td></tr>
                @foreach ($d['segments'] as $s)
                    <tr style="border-top:1px solid #e6e9f2">
                        <td style="padding:9px 6px;font-weight:bold">{{ $s['segment'] }}</td>
                        <td align="right" style="padding:9px 6px">{{ $s['sent'] }}</td>
                        <td align="right" style="padding:9px 6px;color:#1a9b5d">{{ $s['replies'] }}</td>
                        <td align="right" style="padding:9px 6px;color:#c0392b">{{ $s['bounces'] }}</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <p style="margin:26px 6px 0">
            <a href="{{ $repliesUrl }}" style="display:inline-block;background:#0b1a5c;color:#ffffff;padding:12px 18px;text-decoration:none;font-weight:bold;font-size:13px;border-radius:8px;margin-right:8px">Lihat balasan</a>
            <a href="{{ $adminUrl }}" style="display:inline-block;border:1px solid #0b1a5c;color:#0b1a5c;padding:11px 18px;text-decoration:none;font-weight:bold;font-size:13px;border-radius:8px">Buka kampanye</a>
        </p>
    </td></tr>
    <tr><td style="padding:14px 28px;background:#f4f6fb;color:#8b93a7;font-size:12px">Email otomatis tiap Senin pukul 07.00 WIB dari {{ parse_url(config('app.url'), PHP_URL_HOST) }}. Penerima diatur di Admin &gt; Pengaturan &gt; SEO &amp; fitur.</td></tr>
</table>
</body>
</html>

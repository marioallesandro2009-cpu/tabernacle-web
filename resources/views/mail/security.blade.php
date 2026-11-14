<!doctype html>
<html lang="id">
<body style="margin:0;padding:24px;background:#f4f6fb;font-family:Arial,Helvetica,sans-serif;color:#1b2236">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden">
    <tr><td style="background:#0b1a5c;color:#ffffff;padding:18px 26px">
        <div style="font-size:12px;letter-spacing:.12em;font-weight:bold;opacity:.8">{{ strtoupper((string) setting('site_name', config('app.name'))) }} · KEAMANAN AKUN</div>
        <div style="margin-top:6px;font-size:19px;font-weight:bold">{{ $title }}</div>
    </td></tr>
    <tr><td style="padding:22px 26px;font-size:14px;line-height:1.6">
        @foreach ($lines as $line)
            <p style="margin:0 0 12px">{{ $line }}</p>
        @endforeach
        @if (! empty($rows))
            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:4px 0 14px;font-size:14px">
                @foreach ($rows as $k => $v)
                    <tr><td style="padding:2px 14px 2px 0;color:#5d6578">{{ $k }}</td><td style="padding:2px 0"><b>{{ $v }}</b></td></tr>
                @endforeach
            </table>
        @endif
        @if (! empty($button))
            <p style="margin:18px 0 0"><a href="{{ $button[1] }}" style="display:inline-block;background:#0b1a5c;color:#ffffff;text-decoration:none;font-weight:bold;padding:11px 18px;border-radius:10px">{{ $button[0] }}</a></p>
        @endif
        @if (! empty($note))
            <p style="margin:18px 0 0;font-size:12px;color:#8b93a7">{{ $note }}</p>
        @endif
    </td></tr>
</table>
</body>
</html>

<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Berhenti berlangganan | {{ setting('site_name', config('app.name')) }}</title>
<style>
  *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f6f7fb;color:#1b2236;font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}
  main{width:100%;max-width:440px;background:#fff;border-radius:20px;padding:32px;box-shadow:0 1px 2px rgba(16,24,48,.06),0 12px 32px -16px rgba(16,24,48,.2);text-align:center}
  h1{margin:0 0 8px;font-size:22px;letter-spacing:-.01em}p{margin:0 0 14px;color:#5d6578}small{display:block;margin-top:18px;color:#8b93a7}
  button{margin-top:6px;border:0;border-radius:12px;background:#0b1a5c;color:#fff;font:600 15px system-ui,sans-serif;padding:13px 22px;cursor:pointer}
  button:hover{background:#071140}.mail{font-weight:600;color:#1b2236}.ok{display:inline-grid;place-items:center;width:52px;height:52px;border-radius:50%;background:#e8f7ef;color:#1a9b5d;font-size:26px;margin-bottom:12px}
</style>
</head>
<body>
<main>
  @if ($state === 'done')
    <div class="ok" aria-hidden="true">✓</div>
    <h1>Anda sudah berhenti berlangganan</h1>
    <p>@if ($masked)<span class="mail">{{ $masked }}</span> tidak akan menerima email dari kami lagi.@else Anda tidak akan menerima email dari kami lagi.@endif</p>
    <small>You have been unsubscribed and will not receive further emails from us.</small>
  @else
    <h1>Berhenti berlangganan?</h1>
    <p>@if ($masked)Email <span class="mail">{{ $masked }}</span> tidak akan dikirimi pesan dari {{ setting('site_name', config('app.name')) }} lagi.@else Anda tidak akan dikirimi pesan dari kami lagi.@endif</p>
    <form method="post" action="{{ url('/u/'.$token) }}">
      <button type="submit">Ya, berhenti berlangganan</button>
    </form>
    <small>Unsubscribe from {{ setting('site_name', config('app.name')) }} emails.</small>
  @endif
</main>
</body>
</html>

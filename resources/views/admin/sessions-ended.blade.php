<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Semua sesi diakhiri</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f4f6fb; font-family: Arial, Helvetica, sans-serif; color: #1b2236; padding: 16px; }
        .card { max-width: 460px; background: #fff; border-radius: 14px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
        h1 { margin: 0 0 10px; font-size: 20px; }
        p { line-height: 1.6; font-size: 15px; margin: 0 0 12px; }
        a { color: #0b1a5c; font-weight: bold; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Semua sesi sudah diakhiri</h1>
        <p>Semua perangkat yang sedang masuk ke akun <b>{{ $email }}</b> sudah dikeluarkan, termasuk yang memakai "Ingat saya".</p>
        <p>Langkah berikutnya: ganti password sekarang lewat <a href="{{ $forgot }}">Lupa password</a>. Saat mengatur password baru, centang "cabut semua sesi dan perangkat tepercaya".</p>
    </div>
</body>
</html>

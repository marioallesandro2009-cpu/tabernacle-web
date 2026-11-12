# Tabernacle Ways: situs + CMS

Website Tabernacle Ways Tour & Travel (PT Jalan Elyon Eskol Indowisata) berbasis **Laravel 12 + Vue 3**, dengan CMS admin lengkap.
Semua konten (teks, gambar, harga, nomor WA, email, sosial media, menu, warna, SEO) diatur dari admin.

* Publik: Blade + Vue (island untuk formulir lead). Tampilan identik dengan situs statis lama.
* Admin (`/admin`): Inertia + Vue 3 + Tailwind, editor TipTap, grafik Chart.js.
* Peran: `super_admin`, `admin`, `editor`. Tidak ada registrasi publik.
* Bahasa: Indonesia dan English (semua konten dua bahasa).

## Pengembangan lokal

```bash
composer install
npm install
cp .env.example .env        # lalu isi DB_*; set APP_ENV=local, APP_DEBUG=true, MAIL_MAILER=log
php artisan key:generate
php artisan migrate
php artisan db:seed         # konten awal dari situs lama (tanpa akun admin)
php artisan app:create-super-admin
php artisan serve           # dan di terminal lain: npm run dev
```

## Perintah penting

| Perintah | Fungsi |
|---|---|
| `php artisan test` | Jalankan Pest (database SQLite memori, aman untuk data asli) |
| `vendor/bin/phpstan analyse` | Analisis statis (Larastan) |
| `npm run build` | Bangun frontend. **Commit isi `public/build`** karena server cPanel tanpa Node.js |
| `php artisan app:create-super-admin` | Buat akun super admin pertama (interaktif) |
| `php artisan db:backup` | Backup database ke `storage/app/backups` |
| `php artisan schedule:run` | Tugas terjadwal (cron tiap menit di server) |

## Deploy

Lihat **[DEPLOY.md](DEPLOY.md)** untuk panduan lengkap cPanel (Rumahweb) lewat Terminal, dan `deploy.sh` untuk update.

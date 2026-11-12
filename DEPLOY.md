# Panduan deploy ke cPanel (Rumahweb)

Panduan ini untuk memasang **Tabernacle Ways** (Laravel + Vue) di shared hosting cPanel lewat **Terminal cPanel**.
Semua perintah dijalankan di Terminal cPanel (menu *Advanced > Terminal*). Tidak perlu Node.js di server karena
hasil build frontend (`public/build`) sudah ikut di repository.

Contoh di bawah memakai akun `exos7198` dan folder `/home/exos7198/apps/tabernacle-web`. Ganti sesuai akun Anda.

---

## 0. Yang perlu disiapkan dulu

| Kebutuhan | Keterangan |
|---|---|
| PHP 8.2 atau lebih baru | Dipilih di langkah 1 |
| MySQL/MariaDB | Dibuat di langkah 3 |
| Email GoDaddy | Alamat email, password, dan SMTP `smtpout.secureserver.net` port `465` (SSL). Semua email aplikasi lewat sini |
| Akses git | Repository GitHub privat. Lihat langkah 4 untuk cara login |
| Domain | Sudah mengarah ke hosting dan SSL (HTTPS) sudah aktif |

Password email dan database **hanya** ditulis di file `.env` di server. Jangan kirim lewat chat atau commit ke git.

---

## 1. Pilih versi PHP dan aktifkan ekstensi

1. cPanel > **MultiPHP Manager** > pilih domain > pilih **PHP 8.3** (atau 8.2) > *Apply*.
2. cPanel > **MultiPHP INI Editor** atau **Select PHP Version > Extensions**. Pastikan aktif:
   `gd`, `mbstring`, `xml`, `zip`, `fileinfo`, `intl`, `curl`, `pdo_mysql`, `openssl`, `bcmath`, `exif`.
   `gd` wajib bisa membuat WebP (hampir semua server modern sudah bisa).
3. Naikkan batas unggah (opsional, untuk video dan PDF besar): `upload_max_filesize = 64M`, `post_max_size = 64M`,
   `memory_limit = 256M`.

Cek di Terminal. Perintah `php` di Terminal kadang masih versi lama, jadi pakai jalur lengkap:

```bash
ls /opt/cpanel | grep ea-php
/opt/cpanel/ea-php83/root/usr/bin/php -v
```

Agar singkat, buat alias (sekali saja):

```bash
echo 'alias php83="/opt/cpanel/ea-php83/root/usr/bin/php"' >> ~/.bashrc
source ~/.bashrc
php83 -v
```

Di bawah ini `php83` berarti PHP versi yang Anda pilih. Jika memakai 8.2, ganti dengan `ea-php82`.

---

## 2. Letak folder

Kode aplikasi ditaruh **di luar** `public_html` supaya `.env`, `vendor`, dan `storage` tidak bisa diakses dari web.
Hanya folder `public` yang menjadi document root.

```
/home/exos7198/apps/tabernacle-web      <- kode (git clone di sini)
/home/exos7198/apps/tabernacle-web/public  <- document root domain
```

---

## 3. Buat database

1. cPanel > **MySQL Databases** > buat database, contoh `exos7198_tabernacle`.
2. Buat user database dengan password kuat, contoh `exos7198_tabuser`.
3. *Add User To Database* > pilih keduanya > centang **ALL PRIVILEGES**.
4. Catat nama database, user, dan password untuk langkah 6.

---

## 4. Ambil kode dari GitHub

Repository privat butuh izin. Cara paling mudah memakai **Deploy Key** (hanya-baca):

```bash
ssh-keygen -t ed25519 -f ~/.ssh/tabernacle_deploy -N "" -C "cpanel-deploy"
cat ~/.ssh/tabernacle_deploy.pub
```

Salin hasil `cat` ke GitHub: repository > *Settings* > *Deploy keys* > *Add deploy key* (jangan centang write access).
Lalu:

```bash
cat >> ~/.ssh/config <<'EOF'
Host github-tabernacle
  HostName github.com
  User git
  IdentityFile ~/.ssh/tabernacle_deploy
  IdentitiesOnly yes
EOF
chmod 600 ~/.ssh/config

mkdir -p ~/apps && cd ~/apps
git clone git@github-tabernacle:NAMA-AKUN-GITHUB/tabernacle-web.git
cd tabernacle-web
```

---

## 5. Pasang Composer dan dependensi

Jika perintah `composer` tidak ada di Terminal, unduh `composer.phar` ke folder aplikasi:

```bash
cd ~/apps/tabernacle-web
curl -sS https://getcomposer.org/installer | php83
php83 composer.phar --version
```

Pasang dependensi **tanpa** paket pengembangan:

```bash
php83 composer.phar install --no-dev --optimize-autoloader
```

(Jika `composer` sudah tersedia: `composer install --no-dev --optimize-autoloader`.)

---

## 6. Atur `.env`

```bash
cp .env.example .env
nano .env
```

Isi minimal:

| Kunci | Isi |
|---|---|
| `APP_URL` | `https://domain-anda.com` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | **`false`** (jangan pernah `true` di server) |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | dari langkah 3 |
| `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | email GoDaddy Anda (alamat pengirim harus sama dengan `MAIL_USERNAME`) |

Pengaturan SMTP GoDaddy sudah terisi di `.env.example`: `smtpout.secureserver.net`, port `465`, skema `smtps`.
Simpan di nano dengan `Ctrl+O`, `Enter`, keluar `Ctrl+X`. Lalu:

```bash
chmod 600 .env
php83 artisan key:generate --force
```

---

## 7. Buat tabel dan isi data awal

```bash
php83 artisan migrate --force
php83 artisan db:seed --force
```

`db:seed` mengisi peran (super_admin, admin, editor), seluruh konten awal dari situs lama (39 paket tour, destinasi,
halaman, menu, teks, gambar, video), dan redirect URL lama. **Jalankan sekali saja.** Proses ini mengolah gambar
menjadi WebP sehingga bisa memakan beberapa menit; tunggu sampai selesai.
Seeder **tidak membuat akun admin** dan tidak berisi password apa pun.

Buat akun super admin pertama (interaktif, password diketik langsung dan tidak tersimpan di kode):

```bash
php83 artisan app:create-super-admin
```

---

## 8. Arahkan domain ke folder `public`

**Cara A (disarankan): ubah document root.**
cPanel > **Domains** > *Manage* di domain Anda > **Document Root** > isi:
`apps/tabernacle-web/public` > Save.

**Cara B: symlink.** Jika document root tidak bisa diubah, ganti `public_html` dengan tautan ke `public`.
Pindahkan isi lama dulu sebagai cadangan:

```bash
cd ~
mv public_html public_html_lama
ln -s ~/apps/tabernacle-web/public public_html
```

Buka `https://domain-anda.com`. Halaman depan harus tampil. Admin ada di `https://domain-anda.com/admin/login`.

> Tidak perlu `php artisan storage:link`. Berkas unggahan disimpan langsung di `public/uploads`.
> Pastikan folder bisa ditulis: `chmod -R u+rwX storage bootstrap/cache public/uploads`.

---

## 9. Cache untuk produksi

```bash
php83 artisan config:cache
php83 artisan route:cache
php83 artisan view:cache
```

Setelah mengubah `.env`, jalankan lagi `php83 artisan config:cache`.

---

## 10. Cron job (wajib)

Satu baris cron menjalankan semua tugas terjadwal: rangkuman statistik, artikel terjadwal, backup database mingguan,
**dan pengiriman email notifikasi lead (antrean)**. Tanpa cron, lead tetap tersimpan tetapi email tidak terkirim.

cPanel > **Cron Jobs** > *Common Settings*: **Once Per Minute (* * * * *)**, isi perintah:

```
* * * * * /opt/cpanel/ea-php83/root/usr/bin/php /home/exos7198/apps/tabernacle-web/artisan schedule:run >> /dev/null 2>&1
```

Antrean dijalankan oleh `schedule:run` memakai `queue:work --stop-when-empty` tiap menit, jadi tidak perlu proses
tambahan yang terus hidup (shared hosting biasanya tidak mengizinkannya).

---

## 11. Uji email

1. Masuk ke admin > **Penerima notifikasi** > tambahkan sampai 10 alamat email penerima lead.
2. Klik **Kirim tes** di salah satu alamat. Email harus tiba dalam beberapa detik.
3. Jika gagal, pesan error tampil di layar. Penyebab umum: `MAIL_FROM_ADDRESS` berbeda dari `MAIL_USERNAME`,
   password salah, atau `config:cache` belum dijalankan ulang setelah mengubah `.env`.
4. Uji alur nyata: kirim formulir di halaman paket, lalu cek Leads di admin dan kotak masuk penerima
   (maksimal 1 menit karena antrean).

---

## 12. Isi data yang spesifik untuk bisnis Anda

Di admin > **Pengaturan situs**, periksa dan lengkapi: nama, logo, favicon, warna, nomor WhatsApp, email, alamat, jam
buka, Google Maps, media sosial, kode tracking (Google Analytics/Meta Pixel/Search Console), dan `robots.txt`.
Lalu isi testimoni asli, galeri trip, FAQ, tim, dan artikel blog. Jangan membuat testimoni palsu.

---

## Update situs (setelah ada perubahan kode)

```bash
cd ~/apps/tabernacle-web
bash deploy.sh
```

`deploy.sh` melakukan: `git pull`, `composer install --no-dev`, `migrate --force`, peran/izin, membangun ulang cache,
dan merestart antrean. Gunakan `PHP_BIN=/opt/cpanel/ea-php82/root/usr/bin/php bash deploy.sh` bila memakai PHP 8.2.

Mengubah tampilan/JS admin atau situs publik? Jalankan `npm run build` **di komputer pengembangan**, commit isi
`public/build`, push, lalu `bash deploy.sh` di server.

---

## Backup

* Otomatis tiap Minggu 02:00 (butuh cron langkah 10). Menyimpan 8 file terbaru di `storage/app/backups`.
* Manual: admin > **Backup database** > *Buat backup sekarang*, atau `php83 artisan db:backup`.
* Unduh dan simpan salinan di tempat lain secara berkala. Backup hanya berisi database. Gambar ada di
  `public/uploads`, cadangkan juga lewat cPanel *Backup* atau `tar czf uploads.tgz public/uploads`.

---

## Pemecahan masalah

| Gejala | Penyebab dan solusi |
|---|---|
| Halaman putih / 500 | Lihat `storage/logs/laravel-*.log`. Cek izin `storage` dan `bootstrap/cache`, dan `.env` ada. |
| `Class "Intervention..." not found` | `composer install` belum dijalankan atau gagal. Ulangi langkah 5. |
| 404 untuk semua halaman selain beranda | Document root belum mengarah ke `public`, atau `.htaccess` tidak ikut tersalin. |
| Gambar tidak muncul | Cek `public/uploads` bisa ditulis dan `APP_URL` benar (https). |
| Email tidak terkirim | Pastikan cron aktif (langkah 10) dan ikuti langkah 11. Lihat `storage/logs`. |
| Perubahan `.env` tidak berlaku | Jalankan `php83 artisan config:cache` (atau `config:clear`). |
| Lupa password admin | Pakai "Lupa password" di halaman login (butuh email berfungsi), atau super admin lain mengirim tautan reset dari menu Akun admin. |
| Semua super admin terkunci | Buat akun baru: `php83 artisan app:create-super-admin`. |

## Keamanan yang sudah aktif

CSRF di semua formulir, validasi input, pembersihan HTML (HTMLPurifier), validasi unggahan (file executable ditolak),
header keamanan, batas percobaan login 5 per menit, batas kirim formulir per IP, honeypot anti-spam, sesi otomatis
berakhir, kata sandi di-hash, IP pengunjung tidak disimpan mentah, dan `APP_DEBUG=false`.
Aktifkan **2FA** untuk akun super admin di menu *Profil & keamanan*.

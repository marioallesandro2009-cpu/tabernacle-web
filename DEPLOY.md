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

> **Server Rumahweb mematikan `proc_open`.** Akibatnya skrip otomatis Composer gagal dengan pesan
> "The Process class relies on proc_open". Itu tidak merusak instalasi. Jalankan dengan `--no-scripts`
> lalu satu perintah tambahan:
>
> ```bash
> php83 composer.phar install --no-dev --optimize-autoloader --no-scripts
> php83 artisan package:discover
> ```
>
> Tugas terjadwal di aplikasi ini sudah dibuat agar tetap berjalan tanpa `proc_open`.

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

* CSRF di semua formulir, validasi input, pembersihan HTML (HTMLPurifier), dan validasi unggahan (file executable ditolak).
* Header keamanan: HSTS, X-Frame-Options, nosniff, Referrer-Policy, dan Content-Security-Policy dengan nonce.
* Perlindungan login yang tidak pernah mengunci pemilik akun. Rinciannya ada di bagian "Keamanan login".
* Notifikasi email untuk login dari perangkat atau IP baru, dan sesi admin berakhir setelah 2 jam tidak aktif.
* Password admin minimal 12 karakter. 2FA tetap opsional (menu *Profil & keamanan*).
* Batas kirim formulir per IP, honeypot anti-spam, password di-hash, IP pengunjung tidak disimpan mentah, dan `APP_DEBUG=false`.

---

## Alamat panel admin (`ADMIN_PATH`)

Bawaannya panel admin ada di `/admin`. Untuk memindahkannya:

1. Di `.env` server isi misalnya `ADMIN_PATH=ruang-kerja-tw`. Hanya huruf, angka, `-`, `_`, atau `/`. Pilih kata yang tidak mudah ditebak.
2. Jalankan `bash deploy.sh`, atau `php83 artisan config:cache && php83 artisan route:cache`.
3. Panel sekarang ada di `https://domain-anda.com/ruang-kerja-tw`. Alamat `/admin` dan semua di bawahnya menjawab 404.
4. Simpan alamat baru itu di bookmark. Link di email (reset password, notifikasi balasan, dll.) otomatis memakai alamat baru.

Kalau `ADMIN_PATH` dikosongkan atau berisi karakter yang tidak diizinkan, panel tetap di `/admin`.

---

## Keamanan login

Pemilik akun tidak akan pernah terkunci (pendekatan *device cookie* dari OWASP):

| Pembatasan | Aturan |
|---|---|
| Per alamat IP | 5 gagal dalam 15 menit → IP diblokir 15 menit. Berikutnya 30 menit, 1 jam, … sampai maksimal 24 jam. |
| Per akun + IP | Sama, tetapi hanya untuk kombinasi akun dan IP itu. |
| Perangkat tepercaya | Browser yang pernah berhasil masuk mendapat cookie 90 hari (HttpOnly, Secure, SameSite=Strict). Perangkat ini hanya dibatasi 10 gagal per 15 menit dan tidak terkena pembatasan akun. |
| Mode terlindungi | Lebih dari 20 gagal per jam pada satu akun → akun **tidak** dikunci. Login dari perangkat baru wajib lolos Turnstile **dan** klik link konfirmasi yang dikirim ke email akun (berlaku 15 menit, sekali pakai). Pemilik akun mendapat email peringatan. Mode ini berakhir sendiri setelah 24 jam tanpa percobaan gagal. |
| Reset password lewat email | Membuka semua pembatasan untuk akun itu, dengan pilihan untuk mencabut semua sesi dan perangkat tepercaya. |

Perangkat tepercaya, blokir, dan mode terlindungi terlihat di **Riwayat login**. Dari halaman itu perangkat bisa dicabut dan blokir bisa dibuka lebih awal.

### Mengaktifkan Cloudflare Turnstile

1. Masuk ke https://dash.cloudflare.com → **Turnstile** → *Add widget*. Akun gratis cukup, dan domain tidak harus memakai DNS Cloudflare.
2. Isi nama widget dan tambahkan domain `tabernaclewaystour.com`. Mode: *Managed*.
3. Salin **Site Key** dan **Secret Key** ke `.env` server:
   ```
   TURNSTILE_SITE_KEY=0x4AAAA...
   TURNSTILE_SECRET_KEY=0x4AAAA...
   ```
4. Jalankan `php83 artisan config:cache`.
5. Buka halaman login: kotak Turnstile muncul di atas tombol Masuk. Dari perangkat tepercaya pun kotak itu tetap tampil, tetapi pemeriksaannya hanya wajib untuk perangkat baru.

Kalau kedua key kosong, Turnstile nonaktif dan login tetap berjalan normal.

### Content-Security-Policy

`CSP_MODE=enforce` (bawaan) memblokir script yang tidak diizinkan. Yang diizinkan:
- script dari situs ini sendiri;
- GA4/Google Tag Manager, Meta Pixel, YouTube, dan Turnstile;
- kode yang ditempel di Pengaturan → Kode tracking (otomatis diberi nonce).

Kalau setelah menambah layanan pihak ketiga baru ada yang tidak jalan, sementara isi `CSP_MODE=report` (masalah hanya dilaporkan di console browser), lalu hubungi pengembang untuk menambahkan domain layanan itu.

---

## Outreach: terusan dan balasan dari email pribadi

Balasan prospek tetap masuk ke kotak email akun pengirim dan dibaca sistem lewat IMAP, termasuk dari folder Spam/Junk. Pengaturannya ada di **Outreach → Akun pengirim → Ubah**, bagian "Balasan prospek":

1. **Teruskan balasan asli ke email pribadi**: centang, lalu isi satu alamat per baris (maksimal 10). Setiap balasan asli (bukan bounce, bukan balasan otomatis) diteruskan dengan subjek `[Balasan] … – Nama [#KODE]`. Isinya: data prospek, kampanye, segmen, isi balasan, kutipan email terakhir kita, dan tombol "Buka di website".
2. **Izinkan balas dari email pribadi** (butuh terusan aktif): isi email pribadi yang boleh membalas, lalu pilih mode:
   * **Tinjau dulu**: balasan dari email pribadi disimpan sebagai draf, untuk dicek dan dikirim dari website;
   * **Langsung kirim**: balasan langsung dikirim ke prospek dari akun pengirim, di thread yang sama.
3. Cara membalas dari email pribadi: cukup **Reply** pada email terusan. Jangan ubah kode `[#…]` di subjek. Kutipan dan tanda tangan bawaan aplikasi email dibuang otomatis. Lampiran belum ikut dikirim, dan hal ini disebutkan di email konfirmasi.
4. Balasan dari email pribadi hanya diteruskan kalau ketiga syarat ini terpenuhi:
   * pengirimnya ada di daftar;
   * kode `[#…]` valid untuk akun itu;
   * server email penerima mencatat SPF atau DKIM *pass* untuk domain pengirim (Gmail, Outlook, dan Yahoo selalu lolos).

   Kalau salah satu tidak terpenuhi, balasan ditolak dan alasannya dicatat. Pengirim yang ada di daftar mendapat email "Ditolak: alasan".
5. Semua balasan kita terlihat di **Balasan → Balasan kita** dengan status (draf, diterima, ditolak, antre, terkirim, gagal, bounce), sumber (website / email pribadi), dan riwayat statusnya. Yang gagal bisa dikirim ulang.

Membalas dari website: buka balasan → **Buka percakapan & balas**, atau buka profil kontak. Draf tersimpan otomatis saat mengetik.

---

## Outreach: API verifikasi email

Tanpa layanan API pun sistem tetap mengecek format, domain, MX, email sekali pakai, dan alamat peran (info@, sales@, …). Untuk verifikasi kotak email yang lebih akurat:

1. Buat akun di **ZeroBounce** (https://www.zerobounce.net) atau **Reoon** (https://www.reoon.com/email-verifier/), lalu beli kredit sesuai jumlah kontak.
2. Ambil API key dari dashboard layanan itu.
3. Di admin: **Pengaturan → SEO & fitur**. Pilih *Layanan verifikasi email*, tempel API key (disimpan terenkripsi dan tidak pernah ditampilkan lagi), lalu simpan.
4. Verifikasi dijalankan lewat tombol **Verifikasi ulang**: satu kontak (ikon centang), kontak terpilih, atau satu segmen (halaman Segmen). Prosesnya lewat antrean. Opsi "Verifikasi otomatis setiap kali kontak diimpor" memakai kredit untuk setiap impor.
5. Hasilnya: valid, catch-all, tidak pasti, tidak valid, atau sekali pakai. Kampanye hanya mengirim ke yang valid, atau yang belum diverifikasi lewat API. Catch-all bisa diizinkan per kampanye.
6. Kalau kredit habis atau key salah, muncul pesan di halaman Kontak dan hasil cek dasar tetap tersimpan.

Server ini tidak pernah mengetes kotak email penerima secara langsung (tanpa SMTP probing).

---

## Google Search Console dan Google Analytics 4

**Search Console**

1. Buka https://search.google.com/search-console → *Tambahkan properti* → **Awalan URL** → `https://tabernaclewaystour.com`.
2. Pilih metode verifikasi **Tag HTML**. Salin hanya nilai `content="…"` dari tag yang diberikan.
3. Di admin: **Pengaturan → Kode tracking → Google Search Console**, tempel nilainya, lalu simpan.
4. Kembali ke Search Console dan klik *Verifikasi*.
5. Di menu *Sitemap*, kirim `sitemap.xml`. Sitemap sudah berisi versi Indonesia dan Inggris.

Alternatif tanpa tag: verifikasi lewat DNS (properti *Domain*) dengan menambah record TXT di DNS Rumahweb.

**Google Analytics 4**

1. Buka https://analytics.google.com → *Admin* → buat properti dan **aliran data Web** untuk `https://tabernaclewaystour.com`.
2. Salin **Measurement ID** (`G-XXXXXXX`).
3. Di admin: **Pengaturan → Kode tracking → Google Analytics 4**, tempel ID-nya, lalu simpan.
4. GA4 hanya dimuat di halaman publik, tidak di panel admin, dan sudah diizinkan oleh CSP. Tidak perlu menempel kode gtag manual.
5. Hubungkan GA4 dengan Search Console di GA4 → *Admin* → *Penautan Search Console*.

---

## Versi bahasa Inggris

Situs Indonesia ada di alamat biasa, situs Inggris di bawah `/en` (contoh `/en/packages/...`).

* **Tag bahasa:** setiap halaman punya hreflang `id`, `en`, dan `x-default`, plus canonical per bahasa.
* **Konten yang belum diterjemahkan** tetap tampil di `/en` (dalam bahasa Indonesia). Canonical-nya menunjuk ke versi Indonesia, dan halamannya tidak masuk sitemap Inggris.
* **Daftar terjemahan yang belum lengkap** ada di **Konten situs → Terjemahan belum lengkap**.
* **Link lama** dengan `?lang=en` otomatis dipindahkan (301) ke alamat `/en`.
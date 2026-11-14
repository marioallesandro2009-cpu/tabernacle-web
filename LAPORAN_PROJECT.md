# Laporan Project Website Tabernacle Ways

Diperbarui: 16 November 2026 (sesudah pengembangan bagian 1–6).
Lokasi kode: `C:\Users\Acer\Downloads\tabernacle-web` (repo GitHub privat `tabernacle-web`, cabang `main`).
Situs live: https://tabernaclewaystour.com (cPanel Rumahweb).

> **Status deploy:** semua perubahan bagian 1–6 sudah di-commit, **belum di-push dan belum di-deploy** ke server. Isi situs live masih sama seperti laporan sebelumnya sampai deploy dilakukan.

Tidak ada nilai rahasia (password, API key, token) yang ditulis di laporan ini.

---

## Ringkasan perubahan (bagian 1–6)

| # | Bagian | Isi |
|---|---|---|
| 1 | Pengelolaan balasan Outreach | Terusan balasan ke email pribadi dengan kode `[#TOKEN]`; halaman percakapan dan profil kontak; balas dari website di thread yang sama; balas dari email pribadi (relay, dengan cek pengirim, token, dan SPF/DKIM); status balasan kita beserta riwayatnya; kirim ulang; deteksi bounce |
| 2 | IMAP | Folder Spam/Junk ikut dibaca, UID terakhir disimpan per folder, dan pesan dari Spam ditandai |
| 3 | Validasi email | Alamat peran (peringatan), email sekali pakai (daftar bisa diperbarui), driver ZeroBounce dan Reoon (key terenkripsi), opsi catch-all per kampanye, tombol Verifikasi ulang |
| 4 | Keamanan login | `ADMIN_PATH`, Cloudflare Turnstile, blokir per IP / akun+IP / perangkat yang naik bertahap, perangkat tepercaya, mode terlindungi + link email, notifikasi login baru, keluar otomatis setelah 2 jam, CSP dengan nonce, password minimal 12 karakter, opsi "ingat perangkat 30 hari" untuk 2FA |
| 5 | Versi Inggris | URL `/en/...`, hreflang id/en/x-default, canonical per bahasa, sitemap dua bahasa, pengalih bahasa ke halaman yang sama, link `?lang=` lama dipindahkan, RSS Inggris, halaman admin "Terjemahan belum lengkap" |
| 6a | SEO teknis | Preload font dan gambar utama, gambar pertama tidak lazy, width/height di semua gambar, meta description sekitar 160 karakter, schema TravelAgency lengkap, artikel terkait di halaman destinasi, kolom GA4 dan Search Console, 404 yang ramah, filter "Tanpa teks alt". Audit sebelum/sesudah ada di `AUDIT_SEO.md` |
| 6b | Struktur konten | 4 halaman layanan (pilar), modul Studi Kasus, 5 template halaman kepercayaan, footer lengkap dengan data legal, breadcrumb di semua halaman dalam |

Tes otomatis: **262 tes lolos** (27 file). PHPStan dan Pint bersih. Bagian yang bergantung database juga dites di MariaDB.

---

## 1. GAMBARAN UMUM

### Versi dan paket

| Komponen | Versi |
|---|---|
| Laravel | 12.72 |
| PHP server | 8.3 (`composer.json`: `^8.2`) |
| Database server | MariaDB 11.4 |
| Inertia / Vue | inertia-laravel 3.7 + @inertiajs/vue3 3.11 / Vue 3.5 (panel admin) |
| Tailwind | 4 (Vite 7) |

Paket penting: spatie/laravel-permission, barryvdh/laravel-dompdf, maatwebsite/excel, webklex/laravel-imap, mews/purifier, intervention/image, pragmarx/google2fa. Tes memakai Pest 3, Larastan, dan Pint. Tidak ada paket baru di bagian 1–6.

### Struktur folder utama

| Folder | Isi |
|---|---|
| `app/Http/Controllers/Site` | Situs publik: halaman, blog, layanan dan studi kasus (`ServiceController`), form lead, bahasa, SEO, berhenti langganan |
| `app/Http/Controllers/Admin` | Panel admin; `Resources/` (CRUD umum), `Outreach/` (kampanye email) |
| `app/Services` | `LoginGuard` (perlindungan login), `Translator`, `Outreach/` (penjadwal, mailer, IMAP, relay, thread, verifikasi) |
| `app/Support` | `Locale` (URL per bahasa), `Seo`, `SecretSetting`, registry pengaturan dan section, helper |
| `resources/views/site` | Tampilan publik (Blade) |
| `resources/js/Pages/Admin` | Halaman admin (Vue). Semua link admin lewat helper `au()` |
| `resources/data/disposable_domains.txt` | Daftar bawaan domain email sekali pakai |
| `config/admin.php` | Alamat panel, batas waktu tidak aktif, mode CSP |
| `AUDIT_SEO.md` | Audit performa/SEO sebelum dan sesudah |
| `DEPLOY.md` | Panduan deploy, keamanan login, Turnstile, Outreach (terusan/relay, API verifikasi), Search Console, GA4 |

### Driver dan variabel `.env`

| Fungsi | Variabel | Nilai di server |
|---|---|---|
| Database | `DB_CONNECTION` (+ `DB_*`) | `mysql` (MariaDB) |
| Antrean | `QUEUE_CONNECTION` | `database` |
| Cache | `CACHE_STORE` | `file` |
| Sesi | `SESSION_DRIVER` | `database` |
| Email sistem | `MAIL_*` | SMTP GoDaddy, port 465 |
| **Baru** | `ADMIN_PATH` | belum diisi (= `admin`) |
| **Baru** | `ADMIN_IDLE_MINUTES` | belum diisi (= 120) |
| **Baru** | `CSP_MODE` | belum diisi (= `enforce`) |
| **Baru** | `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | belum diisi (Turnstile nonaktif) |

API key verifikasi email tidak ada di `.env`. Key itu diisi di Pengaturan dan disimpan terenkripsi di database.

---

## 2. HALAMAN PUBLIK

Semua halaman ada dalam dua bahasa: Indonesia di alamat biasa, Inggris di bawah `/en`.

| URL (ID / EN) | Isi |
|---|---|
| `/` · `/en` | Beranda: hero dengan pencarian paket, layanan, paket unggulan, perjalanan, visa, klien, legalitas, FAQ, kontak. Section **Studi kasus** dan **Daftar layanan** bisa ditambahkan dari admin |
| `/about` | Tentang, CEO, tim, angka, milestone, portofolio |
| `/packages`, `/packages/{slug}` | Daftar paket (filter) dan detail paket (itinerary, jadwal, harga, form permintaan, paket terkait) |
| `/destinations`, `/destinations/{slug}` | Destinasi, paketnya, dan **artikel terkait** (baru) |
| `/blog`, `/blog/{slug}` | Artikel, artikel dan paket terkait |
| `/contact` | FAQ, kontak, form permintaan |
| **`/layanan`, `/layanan/{slug}`** (baru) | Halaman pilar layanan: paket dari kategori terpilih, destinasinya, studi kasus, artikel terkait, ajakan kontak. **4 halaman sudah dibuat tetapi masih nonaktif** |
| **`/studi-kasus`, `/studi-kasus/{slug}`** (baru) | Daftar dan detail studi kasus. **Masih kosong**; halamannya 404 selama tidak ada data |
| `/{slug}` | Halaman buatan admin. **5 template halaman kepercayaan** (Kebijakan Privasi, Syarat & Ketentuan, Karier, Media & Press, Mitra) sudah dibuat **nonaktif dan kosong** |
| `/u/{token}` | Berhenti berlangganan email Outreach |
| `/sitemap.xml`, `/robots.txt`, `/feed.xml`, `/en/feed.xml` | SEO dan RSS |

### Formulir

| Formulir | Ke mana |
|---|---|
| Form permintaan (detail paket, kontak) | `POST /leads` → tabel `leads` + email notifikasi. Bahasa jawaban mengikuti halaman asal |
| Klik WhatsApp | `POST /track/wa` → `leads` (tipe `wa_click`) |
| Pencarian paket (hero, 404) | `GET /packages?q=` |
| Berhenti berlangganan | `outreach_suppressions` |

### Status SEO

| Item | Status |
|---|---|
| Meta title dan description | Ada. Description dirapikan sekitar 160 karakter |
| Canonical | Ada, per bahasa. Konten tanpa terjemahan memakai canonical versi Indonesia |
| **hreflang** id / en / x-default | **Ada** (baru) |
| **URL bahasa Inggris** `/en/...` | **Ada** (baru). Link `?lang=` lama dipindahkan dengan 301 |
| Open Graph, Twitter Card | Ada |
| `sitemap.xml` | Ada, dua bahasa, dengan `xhtml:link` alternates. Halaman layanan dan studi kasus ikut masuk begitu aktif |
| `robots.txt` | Ada |
| Schema JSON-LD | TravelAgency (legalName, foundingDate, alamat lengkap, contactPoint, sameAs, logo; bagian yang kosong tidak ditampilkan), TouristTrip + Offer, Article, FAQPage, BreadcrumbList, Service (halaman layanan) |
| Breadcrumb terlihat | Ada di semua halaman dalam (baru) |
| Halaman 404 | Ramah: pencarian paket, paket populer, tautan cepat, dua bahasa (baru) |
| Google Analytics 4 | **Kolom sudah ada** (Pengaturan → Kode tracking). ID belum diisi |
| Google Search Console | **Kolom tag verifikasi sudah ada**. Belum diverifikasi |
| Google Business Profile | Belum (menunggu data) |
| Performa | Lihat `AUDIT_SEO.md`. Hambatan terbesar yang tersisa adalah font (sekitar 380 KB) |

---

## 3. HALAMAN ADMIN

Alamat panel: `/{ADMIN_PATH}` (bawaan `/admin`).

| Grup | Menu |
|---|---|
| — | Dashboard |
| Leads | Leads, Penawaran harga, Penerima notifikasi |
| Outreach | Kontak (dengan profil kontak dan semua percakapan), Segmen (tombol verifikasi ulang per segmen), Balasan (tab **Balasan kita**, kartu draf/gagal/ditolak, tanda folder Spam), Template email, Kampanye (opsi catch-all), Akun pengirim (terusan dan relay balasan), Daftar blokir |
| Paket tour | Paket tour, Destinasi, Kategori paket |
| Blog | Artikel, Kategori, Tag |
| Konten situs | Halaman & section, Menu, **Halaman layanan** (baru), **Studi kasus** (baru), Testimoni, Galeri, FAQ, Tim, Teks antarmuka, **Terjemahan belum lengkap** (baru), Redirect |
| Media | Media library (filter **Tanpa teks alt**, baru) |
| Sistem | Pengaturan situs (grup baru: **Data perusahaan**, **Penawaran harga**; GA4/Search Console; layanan verifikasi email), Akun admin, Riwayat login (perangkat tepercaya, blokir, mode terlindungi), Log aktivitas, Backup |

### Login dan peran

* **Peran:** Super Admin (semua izin), Admin (konten, paket, blog, media, leads, analitik, Outreach), Editor (blog dan media).
* **Perlindungan login:** blokir per IP dan per akun+IP (15 menit naik bertahap sampai maksimal 24 jam), perangkat tepercaya dengan cookie 90 hari (batasnya sendiri 10 gagal per 15 menit), dan mode terlindungi untuk lebih dari 20 gagal per jam (perangkat baru wajib lolos Turnstile dan klik link email). **Akun tidak pernah dikunci total.**
* **Notifikasi dan sesi:** email untuk login dari perangkat atau IP baru, berisi link "akhiri semua sesi". Admin keluar otomatis setelah 2 jam tidak aktif.
* **Password dan 2FA:** password minimal 12 karakter. 2FA tetap opsional, dengan pilihan "ingat perangkat ini 30 hari". Reset password membuka semua pembatasan, dengan opsi mencabut semua sesi dan perangkat.
* **Akun:** di server live hanya ada 1 akun Super Admin, dan 2FA-nya belum aktif.

---

## 4. MODUL OUTREACH / KAMPANYE EMAIL

Status di server live: modul terpasang, tetapi belum ada akun, kontak, atau kampanye. Fitur bagian 1–3 belum dideploy.

### 4.1 Tabel (perubahan)

| Tabel | Kolom baru |
|---|---|
| `outreach_accounts` | `forward_enabled`, `forward_to`, `relay_enabled`, `relay_senders`, `relay_mode` (review / direct), `imap_folder_uids` (UID terakhir per folder) |
| `outreach_campaign_contacts` | `thread_token` (kode acak unik 10 karakter per percakapan) |
| `outreach_messages` | `reply_status` (draft / received / rejected / queued / sent / failed / bounced), `status_log` (riwayat + alasan), `source` (website / email), `user_id` (admin pengirim), `relay_from`, `inbound_message_id`, `folder` |
| `outreach_contacts` | `is_role`, `verify_status` (valid / invalid / catch_all / unknown / disposable), `verify_note`, `verify_provider`, `verified_at` |
| `outreach_campaigns` | `allow_catch_all` |

Tabel lama (akun, segmen, kontak, impor, daftar blokir, domain diblokir, template, kampanye, langkah, pesan) tetap seperti laporan sebelumnya.

### 4.2 Akun pengirim

* **Tambah akun:** lewat admin (preset Hostinger dan GoDaddy). Password terenkripsi, ada tes koneksi SMTP/IMAP, dan warm-up otomatis.
* **Pengaturan balasan (baru):**
  * terusan ke maksimal 10 email pribadi;
  * balas dari email pribadi, dengan daftar pengirim yang diizinkan dan mode "Tinjau dulu" atau "Langsung kirim";
  * kombinasi yang tidak mungkin jalan ditolak dengan pesan yang jelas (relay tanpa terusan, alamat akun sendiri, bukan email).

### 4.3 Impor, segmen, validasi

* **Impor:** xlsx/xls/csv dengan pemetaan kolom otomatis, duplikat dilewati, daftar blokir dicek.
* **Validasi dasar:** format, domain, MX, **email sekali pakai** (baru; daftar bawaan dan diperbarui otomatis tiap bulan lewat `outreach:disposable-update`).
* **Alamat peran** seperti info@, sales@, cs@, noreply@ ditandai sebagai peringatan dan bisa difilter, tetapi tetap boleh dikirimi.
* **Verifikasi API (baru):** ZeroBounce atau Reoon, dipilih di Pengaturan. Hasilnya valid / invalid / catch_all / unknown / disposable.
  * Kampanye hanya mengirim ke yang valid, atau yang belum pernah diverifikasi lewat API.
  * Catch-all hanya dikirimi kalau opsi kampanyenya dicentang.
  * Kontak yang belakangan ternyata tidak valid otomatis dihentikan dari kampanye yang sedang berjalan.
* **Tombol Verifikasi ulang:** untuk satu kontak, kontak terpilih, atau satu segmen, lewat antrean dalam potongan 50.
* **Tanpa SMTP probing** dari server.

### 4.4 Template dan pengiriman

* **Template:** variabel `{nama}`, `{jabatan}`, `{organisasi}`, `{nama_pengirim}`. Email teks biasa dengan footer dan tautan berhenti berlangganan. Maksimal 3 langkah (hari 0 / 4 / 10). Ada uji A/B subjek.
* **Alur kirim:** tidak berubah. Penjadwal berjalan tiap menit di jam kirim (WIB), satu email per akun dengan jeda acak 1–4 menit, batas harian dan warm-up dihormati, antrean database, dan semuanya dicek ulang sebelum terkirim.
* **Balasan manual** tidak dihitung ke batas harian kampanye.

### 4.5 Balasan (IMAP)

* **Folder yang dibaca:** `outreach:poll-replies` tiap 10 menit membaca **INBOX dan folder Spam/Junk**. Folder Spam dikenali dari flag `\Junk`, atau dari nama seperti Spam, Junk, Bulk Mail, `[Gmail]/Spam`.
* **Klasifikasi:** balasan asli, bounce (permanen atau sementara), atau balasan otomatis. Aturannya sama untuk semua folder.
* **Saat balasan asli masuk:** follow-up berhenti, email notifikasi dikirim ke tim, dan balasan **diteruskan ke email pribadi** kalau fitur terusan aktif.
* **Balas dari website:** dari halaman percakapan atau profil kontak. Email dikirim dari akun yang sama, dengan In-Reply-To dan References yang benar, tanpa footer berhenti berlangganan. Draf tersimpan otomatis.
* **Relay dari email pribadi:** syaratnya pengirim ada di daftar, token valid, dan header Authentication-Results teratas menunjukkan SPF atau DKIM pass untuk domain pengirim.
  * Kutipan dan tanda tangan bawaan aplikasi email dibuang otomatis.
  * Kalau gagal salah satu syarat → ditolak dengan alasan, dan pengirim yang ada di daftar mendapat email konfirmasi.
  * Lampiran diabaikan dan hal itu diberitahukan.
* **Status setiap balasan kita** tampil sebagai badge dan bisa difilter di halaman Balasan, percakapan, dan profil kontak, lengkap dengan riwayat waktu.
* **Tautan berhenti berlangganan:** ada, termasuk satu klik dari aplikasi email.

### 4.6 Laporan

Statistik kampanye, A/B, unduh Excel, dan ringkasan mingguan tetap seperti sebelumnya. Baru: kartu draf / gagal / ditolak di halaman Balasan.

---

## 5. PENJADWALAN DAN OTOMATISASI

Satu cron: `* * * * * php artisan schedule:run` (sudah terpasang di server).

| Tugas | Jadwal |
|---|---|
| `queue:work --stop-when-empty --max-time=50` | setiap menit |
| `outreach:tick` | setiap menit |
| `outreach:poll-replies` (INBOX + Spam) | setiap 10 menit |
| `outreach:weekly-digest` | Senin 07.00 WIB |
| **`outreach:disposable-update`** (baru) | tanggal 1 setiap bulan, 03.00 WIB |
| `posts:publish-due` | setiap 5 menit |
| `analytics:aggregate` | setiap jam + 00.10 |
| `db:backup` (simpan 14, salinan ke email) | setiap hari 02.00 WIB |
| `queue:prune-failed` | setiap hari |

Queue worker dijalankan per menit lewat scheduler, cocok untuk shared hosting. Fitur baru membutuhkan akses HTTPS keluar ke:
- `challenges.cloudflare.com` (Turnstile);
- `api.zerobounce.net` atau `emailverifier.reoon.com` (verifikasi email);
- `raw.githubusercontent.com` (pembaruan daftar email sekali pakai).

---

## 6. PENGIRIMAN DAN DELIVERABILITY

* Panduan SPF/DKIM/DMARC untuk pindah ke Hostinger ada di percakapan, belum di `DEPLOY.md`.
* Dengan **akun pengirim GoDaddy** (seperti di spesifikasi bagian 1), DNS saat ini sudah mengizinkan pengiriman (SPF `include:secureserver.net`). DMARC `p=reject` tetap aman karena GoDaddy menandatangani email dengan DKIM. Sebaiknya kirim email uji ke Gmail dulu untuk memastikan SPF, DKIM, dan DMARC semuanya PASS.
* **Fitur yang membantu deliverability:** pembacaan folder Spam memperlihatkan balasan dan bounce yang tersesat; verifikasi API dan email sekali pakai menurunkan bounce; warm-up otomatis.
* **Risiko yang tetap ada:**
  * domain utama dipakai untuk cold email;
  * belum ada pemantauan blacklist atau Postmaster Tools;
  * laporan DMARC (`rua`) masih dikirim ke alamat GoDaddy.

---

## 7. KONDISI DAN CELAH

### Sudah selesai dan dites (lokal; belum dideploy)

Semua fitur bagian 1–6 di atas: 262 tes otomatis lolos, termasuk di MariaDB. Pengecekan di browser lokal:
- CSP tidak menghasilkan error di beranda, paket, kontak, blog, login, dashboard (dengan grafik), editor artikel, Riwayat login, dan halaman Outreach;
- versi `/en` tampil benar;
- form permintaan dari halaman Inggris menjawab dalam bahasa Inggris;
- posisi breadcrumb dan isi footer sudah dicek.

### Belum dites dengan layanan sungguhan

* **IMAP/SMTP dengan kotak email asli:** termasuk pendeteksian folder Spam di GoDaddy dan relay dari Gmail. Sejauh ini hanya dites dengan server tiruan.
* **ZeroBounce / Reoon:** format jawaban API mengikuti dokumentasi publik, tetapi belum dicoba dengan key asli.
* **Turnstile:** belum dicoba dengan key asli.
* **Angka Core Web Vitals asli:** PageSpeed Insights sedang kena kuota saat audit; ukur ulang setelah deploy.

### Risiko dan catatan

* **CSP:** layanan pihak ketiga baru (misalnya chat widget) harus ditambahkan ke daftar CSP. Sementara bisa pakai `CSP_MODE=report`.
* **Mengubah `ADMIN_PATH`** mengubah alamat login. Simpan alamat baru sebelum deploy.
* **Kode `[#TOKEN]`** di subjek terusan jangan dihapus saat membalas dari email pribadi.
* **Font sekitar 380 KB** masih jadi beban terbesar halaman (lihat `AUDIT_SEO.md`).
* **Hanya 1 akun admin** dan 2FA belum aktif. Fitur perangkat tepercaya mencegah pemilik terkunci, tetapi akun cadangan tetap disarankan.

### Sebelum kampanye email pertama

1. Deploy, lalu tambahkan akun pengirim GoDaddy dan klik Tes koneksi.
2. Aktifkan terusan (dan relay bila mau). Uji sekali: kampanye kecil ke alamat sendiri, balas dari email pribadi, dan cek statusnya di "Balasan kita".
3. (Opsional) Isi API verifikasi email lalu verifikasi segmen.
4. Isi data perusahaan dan alamat footer, lalu cek footer email.

---

## 8. RINGKASAN

**Yang sudah bisa dipakai setelah deploy:**
1. Situs dua bahasa dengan URL Inggris sendiri, hreflang, dan sitemap dua bahasa: siap diindeks Google dengan benar.
2. Outreach lengkap: balasan diteruskan ke email pribadi, bisa dibalas dari website atau langsung dari email pribadi, folder Spam ikut dibaca, verifikasi email lebih ketat.
3. Login admin jauh lebih aman, tanpa risiko pemilik terkunci.
4. SEO teknis yang lebih rapi (preload, schema lengkap, 404 ramah, breadcrumb) dan struktur konten perusahaan mapan (layanan, studi kasus, halaman kepercayaan, footer legal).
5. Penawaran harga, warm-up, A/B, daftar blokir, terjemah otomatis (dari pekerjaan sebelumnya).

**5 hal paling penting berikutnya:**
1. **Push dan deploy**, lalu uji Outreach dengan kotak email asli (terusan, relay, folder Spam).
2. **Isi konten yang masih kosong:** teks 4 halaman layanan, studi kasus, 5 halaman kepercayaan, data perusahaan (NIB, AHU, tahun berdiri), logo, testimoni. Aktifkan masing-masing setelah diisi.
3. **Pasang Search Console dan GA4** lewat kolom di Pengaturan, kirim sitemap, lalu ukur ulang Core Web Vitals.
4. **Lengkapi terjemahan Inggris** lewat halaman "Terjemahan belum lengkap".
5. **Kecilkan font** (potong ke huruf Latin) dan tambahkan ukuran gambar 720 px untuk HP.

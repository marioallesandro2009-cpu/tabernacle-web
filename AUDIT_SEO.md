# Audit SEO teknis dan performa: sebelum dan sesudah

Tanggal: 15 November 2026. Halaman yang diaudit: beranda, daftar paket, detail paket, destinasi, artikel.

## Cara mengukur

PageSpeed Insights (Lighthouse milik Google) tidak bisa dipakai hari itu karena kuota gratis hariannya habis. Panel browser yang tersedia juga tidak memberi angka waktu render (FCP/LCP).

Karena itu dipakai skrip audit sendiri. Skrip ini mengambil setiap halaman seperti HP 375 px (layar retina) dan mengunduh semua yang dibutuhkan layar pertama tanpa cache dan tanpa kompresi, agar server live dan server lokal bisa dibandingkan:
- HTML
- CSS yang menghambat render
- font
- gambar pertama

Skrip yang sama dijalankan:
- **sebelum**: di situs live `tabernaclewaystour.com`;
- **sesudah**: di versi baru yang berjalan lokal.

Waktu respons server (TTFB) tidak dibandingkan, karena server lokal berbeda dari server live.

**Perlu dicek ulang:** setelah deploy, jalankan PageSpeed Insights di https://pagespeed.web.dev untuk lima halaman ini. Angka Core Web Vitals asli (LCP, CLS, INP dari pengunjung Chrome) muncul di Search Console setelah ada cukup kunjungan.

## Hasil

| Halaman | Gambar utama | Preload | Gambar tanpa ukuran | Meta description | hreflang |
|---|---|---|---|---|---|
| Beranda | eager + high → sama, **di-preload** | 0 → 3 (2 font + hero) | **6 → 0** | 180 → **158** | 0 → 3 |
| Daftar paket | **lazy → eager + high** | 0 → 2 (font) | 0 → 0 | 129 → 134 | 0 → 3 |
| Detail paket | eager + high → sama, **di-preload** | 0 → 3 | 0 → 0 | **284 → 155** | 0 → 3 |
| Destinasi | **lazy → eager + high** | 0 → 2 | 0 → 0 | 180 → **158** | 0 → 3 |
| Artikel | eager + high → sama, **di-preload** | 0 → 3 | 0 → 0 | 146 → 140 | 0 → 3 |

Ukuran yang tidak berubah: CSS sekitar 73 KB, JavaScript publik sekitar 8 KB (tidak menghambat render), dan font sekitar 381 KB.

## Yang diperbaiki

1. **Preload font utama.** Archivo dan Newsreader dipakai di layar pertama. Sebelumnya font baru ditemukan browser setelah CSS selesai diunduh; sekarang diunduh bersamaan dengan CSS. Ini mempercepat munculnya teks dalam font yang benar dan mengurangi "lompatan" teks.
2. **Preload gambar utama (LCP)** di beranda (hero), detail paket (cover), dan artikel (cover), lengkap dengan `imagesrcset`/`imagesizes`. Browser memilih ukuran gambar yang sama dengan `<img>`-nya.
3. **Gambar pertama di daftar paket dan destinasi tidak lagi lazy.** Sebelumnya gambar paket paling atas baru diunduh belakangan, padahal terlihat di layar pertama. Sekarang langsung diunduh dengan prioritas tinggi; sisanya tetap lazy.
4. **Ukuran (width/height) untuk semua gambar.** Bendera di bagian visa di beranda sebelumnya tanpa ukuran, sehingga tata letak bisa bergeser (CLS).
5. **Meta description dirapikan** maksimal sekitar 160 karakter, dipotong di batas kata dengan "…". Sebelumnya bisa sampai 300 karakter dan terpotong sembarangan di hasil Google.
6. **hreflang id/en/x-default dan canonical per bahasa** (dari bagian 5).
7. **Schema TravelAgency dilengkapi**: legalName, foundingDate, alamat lengkap (jalan, kota, provinsi, kode pos), contactPoint, sameAs, dan logo. Data yang belum diisi di Pengaturan → Data perusahaan tidak ditampilkan.
8. **Tautan internal**: halaman destinasi kini juga menampilkan artikel terkait. Detail paket sudah punya paket terkait; artikel sudah punya artikel dan paket terkait.
9. **GA4 dan Search Console** bisa diisi di Pengaturan → Kode tracking (ID GA4 dan kode verifikasi). GA4 hanya dimuat di halaman publik, tidak di panel admin, dan lolos CSP.
10. **Halaman 404 yang ramah**: pencarian paket, paket populer, tautan cepat, dan bahasanya mengikuti alamat (`/en/...` → Inggris).
11. **Alt text**: Media library punya filter "Tanpa teks alt", supaya gambar yang belum punya alt mudah ditemukan dan dilengkapi. Alt tetap wajib diisi saat upload.

Yang sudah baik sejak awal dan dipertahankan:
- semua gambar WebP dengan beberapa ukuran (srcset);
- gambar di bawah layar pertama sudah lazy;
- JavaScript publik kecil (sekitar 8 KB) dan tidak menghambat render;
- tidak ada script yang menghambat di `<head>`.

## Yang belum, dan langkah berikutnya yang paling berdampak

1. **Font sekitar 380 KB** adalah bagian terbesar dari layar pertama (sekitar 70%). Font bisa dipotong hanya ke huruf Latin dan dipisah per bobot, dengan perkiraan penghematan 40–60%. Pekerjaan ini butuh alat pemotong font (misalnya `pyftsubset` dari fonttools), yang tidak tersedia di server maupun di komputer kerja ini. Alternatifnya: pakai satu font saja untuk teks biasa.
2. **Ukuran gambar menengah.** Saat ini ukuran gambar yang dibuat 480, 960, dan 1600 px. HP retina (sekitar 750 px) mengambil 960 px, misalnya cover artikel 110 KB. Menambah ukuran 720 px akan menghemat sekitar 30–40% per gambar, tetapi semua gambar lama harus dibuat ulang. Itu berat untuk shared hosting, jadi sebaiknya dijalankan bertahap.
3. **AVIF.** GD di server mendukung AVIF. AVIF sekitar 20–30% lebih kecil dari WebP, tetapi proses pembuatannya jauh lebih lambat di shared hosting. Disarankan setelah poin 2.

<?php

namespace App\Support;

/** All site-wide settings editable in the admin, grouped in tabs. */
class SettingsRegistry
{
    /** @return array<int, array{key: string, label: string, fields: array<int, array<string, mixed>>}> */
    public static function groups(): array
    {
        return [
            ['key' => 'umum', 'label' => 'Umum', 'fields' => [
                F::text('site_name', 'Nama situs', ['required' => true]),
                F::text('site_suffix', 'Keterangan di samping nama (contoh TOUR & TRAVEL)'),
                F::text('company_name', 'Nama perusahaan'),
                F::t('tagline', 'Tagline'),
                F::tarea('default_description', 'Deskripsi situs bawaan (SEO)'),
                F::text('title_suffix', 'Akhiran judul tab browser (contoh " | Tabernacle Ways")'),
            ]],
            ['key' => 'tampilan', 'label' => 'Logo & warna', 'fields' => [
                F::image('logo_light', 'Logo untuk latar gelap (opsional, menggantikan teks logo)'),
                F::image('logo_dark', 'Logo untuk latar terang (opsional)'),
                F::image('favicon', 'Favicon'),
                F::image('og_default_image', 'Gambar bawaan saat tautan dibagikan (OG image)'),
                F::color('color_navy', 'Warna utama (navy)', ['required' => true]),
                F::color('color_navy_deep', 'Warna utama, lebih gelap', ['required' => true]),
                F::color('color_amber', 'Warna aksen (amber)', ['required' => true]),
                F::color('color_amber_deep', 'Warna aksen, lebih gelap', ['required' => true]),
                F::color('color_cream', 'Warna latar halaman', ['required' => true]),
                F::color('color_sand', 'Warna latar bagian selang-seling (sedikit lebih gelap dari latar halaman)'),
                F::color('color_paper', 'Warna kartu dan kotak (harga, formulir, pencarian)'),
                F::color('color_teal', 'Warna pelengkap (teal)', ['required' => true]),
            ]],
            ['key' => 'kontak', 'label' => 'Kontak', 'fields' => [
                F::text('wa_number', 'Nomor WhatsApp, format 628xxxxxxxxxx tanpa + atau spasi', ['required' => true, 'max' => 20]),
                F::text('wa_display', 'Nomor WhatsApp yang ditampilkan (contoh +62 812 9797 1563)'),
                F::email('contact_email', 'Email kontak publik'),
                F::tarea('address', 'Alamat lengkap'),
                F::textarea('address_footer', 'Alamat singkat di footer (boleh beberapa baris)'),
                F::t('hours', 'Jam operasional'),
                F::text('maps_url', 'Tautan Google Maps', ['max' => 800]),
            ]],
            ['key' => 'kepercayaan', 'label' => 'Poin kepercayaan', 'fields' => [
                F::repeat('trust_points', 'Poin singkat di kotak harga setiap paket (hanya fakta yang benar, maksimal 5)', [
                    F::t('text', 'Teks', ['required' => true]),
                ], ['item_label' => 'text', 'max' => 5]),
            ]],
            ['key' => 'sosial', 'label' => 'Media sosial', 'fields' => [
                F::url('social_instagram', 'Instagram'), F::url('social_tiktok', 'TikTok'),
                F::url('social_facebook', 'Facebook'), F::url('social_youtube', 'YouTube'),
            ]],
            ['key' => 'tracking', 'label' => 'Kode tracking', 'fields' => [
                F::textarea('tracking_head', 'Kode di dalam <head> (Google Analytics, Meta Pixel, Search Console)', ['max' => 20000]),
                F::textarea('tracking_body', 'Kode tepat setelah <body> (Tag Manager noscript)', ['max' => 20000]),
            ]],
            ['key' => 'penawaran', 'label' => 'Penawaran harga', 'fields' => [
                F::text('quote_prefix', 'Awalan nomor penawaran (contoh TW/QUO, menjadi TW/QUO/2026/11/001)', ['max' => 30]),
                F::number('quote_validity_days', 'Masa berlaku bawaan (hari)'),
                F::text('quote_signatory_name', 'Nama penanda tangan bawaan'),
                F::text('quote_signatory_title', 'Jabatan penanda tangan bawaan'),
                F::textarea('quote_payment_info', 'Informasi pembayaran (rekening bank, dll.), tampil di PDF penawaran', ['max' => 3000]),
                F::textarea('quote_terms', 'Syarat & ketentuan bawaan di penawaran baru', ['max' => 10000]),
            ]],
            ['key' => 'sistem', 'label' => 'SEO & fitur', 'fields' => [
                F::textarea('robots_txt', 'Isi robots.txt', ['max' => 5000]),
                F::toggle('lead_notify_enabled', 'Kirim email notifikasi saat ada lead baru', ['default' => true]),
                F::toggle('analytics_enabled', 'Aktifkan analitik pengunjung bawaan', ['default' => true]),
                F::text('outreach_digest_email', 'Penerima ringkasan mingguan Outreach (beberapa email dipisah koma; kosong = semua Super Admin). Dikirim tiap Senin pukul 07.00 WIB', ['max' => 400]),
                F::select('verify_provider', 'Layanan verifikasi email Outreach (opsional; tanpa layanan, sistem tetap memakai cek format, domain, MX, alamat peran, dan email sekali pakai)', [['value' => 'zerobounce', 'label' => 'ZeroBounce'], ['value' => 'reoon', 'label' => 'Reoon']]),
                F::text('verify_api_key', 'API key layanan verifikasi (disimpan terenkripsi; kosongkan untuk tidak mengubah)', ['input' => 'password', 'secret' => true, 'max' => 500]),
                F::toggle('verify_on_import', 'Verifikasi otomatis lewat layanan di atas setiap kali kontak diimpor (memakai kredit layanan)', ['default' => false]),
                F::email('translate_email', 'Email untuk layanan terjemahan otomatis MyMemory (opsional; kalau diisi, kuota gratis naik dari sekitar 5.000 menjadi 50.000 karakter per hari)'),
                F::email('backup_email', 'Kirim salinan backup database harian ke email ini (opsional; simpanan di luar server, dikirim tiap malam pukul 02.00 WIB)'),
            ]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function allFields(): array
    {
        return collect(self::groups())->flatMap(fn ($g) => $g['fields'])->all();
    }
}

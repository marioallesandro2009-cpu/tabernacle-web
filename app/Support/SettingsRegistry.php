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
            ['key' => 'sistem', 'label' => 'SEO & fitur', 'fields' => [
                F::textarea('robots_txt', 'Isi robots.txt', ['max' => 5000]),
                F::toggle('lead_notify_enabled', 'Kirim email notifikasi saat ada lead baru', ['default' => true]),
                F::toggle('analytics_enabled', 'Aktifkan analitik pengunjung bawaan', ['default' => true]),
            ]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function allFields(): array
    {
        return collect(self::groups())->flatMap(fn ($g) => $g['fields'])->all();
    }
}

<?php

namespace App\Support;

/** Every kind of section a public page can contain, with the fields editable in the admin. */
class SectionRegistry
{
    /** @return array<string, array{label: string, fields: array<int, array<string, mixed>>}> */
    public static function types(): array
    {
        $heads = fn (bool $kicker = true) => array_values(array_filter([
            $kicker ? F::t('kicker', 'Label kecil (kicker)') : null,
            F::t('title_a', 'Judul, bagian tebal'),
            F::t('title_b', 'Judul, bagian miring'),
        ]));
        $alt = fn (string $key = 'image_alt') => F::t($key, 'Teks alternatif gambar (opsional, menimpa teks bawaan gambar)');

        return [
            'hero' => ['label' => 'Hero (bagian paling atas)', 'fields' => [
                F::image('image', 'Gambar latar', ['required' => true]), $alt(),
                F::t('line1', 'Judul baris 1', ['required' => true]), F::t('line2', 'Judul baris 2 (miring)'),
                F::tarea('text', 'Teks pengantar'),
                F::t('btn1_label', 'Tombol utama, teks'), F::text('btn1_url', 'Tombol utama, tautan (isi "wa" untuk WhatsApp)'),
                F::t('btn2_label', 'Tombol kedua, teks'), F::text('btn2_url', 'Tombol kedua, tautan'),
                F::toggle('show_finder', 'Tampilkan kotak pencarian paket (Mau ke mana?)', ['default' => true]),
            ]],
            'intro' => ['label' => 'Perkenalan + angka singkat', 'fields' => [
                F::t('kicker', 'Label kecil'), F::thtml('heading', 'Kalimat utama (boleh <em>miring</em>)', ['required' => true]),
                F::trich('body', 'Isi'), F::t('link_label', 'Tautan, teks'), F::text('link_url', 'Tautan, alamat'),
                F::image('image', 'Foto'), $alt(),
                F::repeat('facts', 'Angka singkat', [F::t('label', 'Label'), F::text('value', 'Nilai, contoh 5+'), F::toggle('count', 'Animasi hitung naik')], ['item_label' => 'value']),
            ]],
            'film' => ['label' => 'Film perusahaan (video)', 'fields' => [
                F::file('video', 'Video (mp4/webm)', 'video', ['required' => true]), F::image('poster', 'Gambar pratinjau'),
                F::t('kicker', 'Label kecil'), F::t('title_a', 'Judul tebal'), F::t('title_b', 'Judul miring'),
                F::t('play_label', 'Teks tombol putar'), F::t('aria', 'Deskripsi video (aksesibilitas)'),
            ]],
            'services' => ['label' => 'Daftar layanan', 'fields' => [
                ...$heads(), F::tarea('intro', 'Pengantar'), F::image('image', 'Foto lebar'), $alt(),
                F::repeat('items', 'Layanan', [F::t('title', 'Nama layanan', ['required' => true]), F::t('text', 'Keterangan')], ['item_label' => 'title']),
                F::thtml('note', 'Catatan di bawah daftar'),
            ]],
            'packages_featured' => ['label' => 'Paket unggulan (otomatis dari Paket Tour)', 'fields' => [
                ...$heads(), F::tarea('intro', 'Pengantar (:n = jumlah paket)'), F::t('all_label', 'Teks tombol semua paket (:n = jumlah)'),
            ]],
            'terms_block' => ['label' => 'Private trip + catatan penting', 'fields' => [
                F::t('private_kicker', 'Private, label kecil'), F::t('private_title_a', 'Private, judul tebal'), F::t('private_title_b', 'Private, judul miring'),
                F::tarea('private_text', 'Private, teks'), F::t('private_btn', 'Private, teks tombol'),
                F::t('terms_title', 'Judul catatan'),
                F::repeat('terms', 'Poin catatan', [F::tarea('text', 'Teks', ['required' => true])], ['item_label' => null]),
            ]],
            'vision' => ['label' => 'Visi dan misi', 'fields' => [
                ...$heads(), F::tarea('text', 'Teks visi'),
                F::repeat('mission', 'Misi', [F::t('title', 'Judul', ['required' => true]), F::t('text', 'Teks')], ['item_label' => 'title']),
                F::image('image', 'Foto'), $alt(),
            ]],
            'journeys' => ['label' => 'Galeri perjalanan (per bab)', 'fields' => [
                ...$heads(), F::tarea('intro', 'Pengantar'),
                F::repeat('chapters', 'Bab', [
                    F::t('title', 'Judul bab', ['required' => true]), F::t('meta', 'Keterangan (kanan)'),
                    F::repeat('tiles', 'Foto', [
                        F::image('image', 'Foto', ['required' => true]),
                        F::select('size', 'Ukuran kotak', [['value' => 'g-a', 'label' => 'Besar lebar (8 kolom, 2 baris)'], ['value' => 'g-b', 'label' => 'Kecil (4 kolom)'], ['value' => 'g-c', 'label' => 'Kecil (4 kolom)'], ['value' => 'g-d', 'label' => 'Besar (6 kolom, 2 baris)'], ['value' => 'g-e', 'label' => 'Sedang (3 kolom, 2 baris)']], ['default' => 'g-b']),
                        F::t('caption', 'Keterangan di foto'), F::t('alt', 'Teks alternatif'), F::t('modal_title', 'Judul saat diperbesar'), F::t('modal_meta', 'Keterangan saat diperbesar'),
                        F::text('focus', 'Titik fokus foto, contoh 40% 40%'),
                    ], ['item_label' => null]),
                ], ['item_label' => 'title']),
            ]],
            'visa' => ['label' => 'Referensi visa (papan keberangkatan)', 'fields' => [
                ...$heads(), F::t('note', 'Catatan'), F::t('note_small', 'Catatan kecil'),
                F::t('head_a', 'Kepala kolom kiri'), F::t('head_b', 'Kepala kolom kanan'), F::t('doc_label', 'Label dokumen (Visa)'),
                F::repeat('rows', 'Negara', [F::image('flag', 'Bendera'), F::t('country', 'Nama negara', ['required' => true])], ['item_label' => 'country']),
            ]],
            'clients' => ['label' => 'Logo klien', 'fields' => [
                ...$heads(), F::repeat('logos', 'Klien', [F::image('image', 'Logo', ['required' => true]), F::text('name', 'Nama klien')], ['item_label' => 'name']),
            ]],
            'legal' => ['label' => 'Legalitas perusahaan', 'fields' => [
                F::t('title_a', 'Judul tebal'), F::t('title_b', 'Judul miring'),
                F::repeat('rows', 'Baris data', [F::text('label', 'Label'), F::text('value', 'Nilai'), F::toggle('scramble', 'Animasi acak angka')], ['item_label' => 'label']),
            ]],
            'contact' => ['label' => 'Kontak (alamat, WhatsApp, email)', 'fields' => [
                ...$heads(), F::t('btn_wa', 'Tombol WhatsApp, teks'), F::t('btn_email', 'Tombol email, teks'),
                F::image('image', 'Foto'), $alt(), F::toggle('show_form', 'Tampilkan formulir permintaan di bagian ini'),
            ]],
            'lead_form' => ['label' => 'Formulir permintaan (leads)', 'fields' => [...$heads(), F::tarea('intro', 'Pengantar')]],
            'about_hero' => ['label' => 'Pembuka halaman Tentang', 'fields' => [
                F::t('line1', 'Judul baris 1'), F::t('line2', 'Judul baris 2 (miring)'), F::tarea('intro', 'Pengantar'),
                F::repeat('pics', 'Foto (2 foto)', [F::image('image', 'Foto', ['required' => true]), F::t('alt', 'Teks alternatif'), F::t('caption', 'Keterangan')], ['max' => 2, 'item_label' => null]),
            ]],
            'ceo' => ['label' => 'Pendiri / CEO', 'fields' => [
                F::image('photo', 'Foto'), F::t('photo_alt', 'Teks alternatif foto'), F::t('kicker', 'Jabatan'),
                F::text('first', 'Nama depan'), F::text('last', 'Nama belakang'), F::tarea('quote', 'Kutipan'), F::trich('letter', 'Surat / sambutan'), F::t('sign', 'Tanda tangan'),
            ]],
            'numbers' => ['label' => 'Angka pencapaian', 'fields' => [
                F::repeat('items', 'Angka', [F::number('value', 'Angka'), F::text('suffix', 'Akhiran, contoh +'), F::toggle('comma', 'Pakai pemisah ribuan'), F::t('label', 'Label')], ['item_label' => 'value']),
            ]],
            'milestones' => ['label' => 'Tonggak perjalanan', 'fields' => [
                ...$heads(), F::repeat('items', 'Tonggak', [F::text('year', 'Tahun'), F::t('title', 'Judul'), F::t('text', 'Keterangan')], ['item_label' => 'year']),
            ]],
            'portfolio' => ['label' => 'Portofolio perjalanan (dengan filter)', 'fields' => [
                ...$heads(), F::t('filter_aria', 'Deskripsi filter (aksesibilitas)'),
                F::repeat('filters', 'Pilihan filter', [F::text('key', 'Kunci (contoh corporate)'), F::t('label', 'Label')], ['item_label' => 'key']),
                F::repeat('items', 'Perjalanan', [
                    F::text('cats', 'Kategori (kunci filter, pisah spasi)'), F::image('image', 'Foto', ['required' => true]), F::t('place', 'Nama'), F::t('meta', 'Jenis / tahun'),
                    F::t('modal_title', 'Judul saat diperbesar'), F::t('modal_meta', 'Keterangan saat diperbesar'), F::t('alt', 'Teks alternatif'),
                ], ['item_label' => 'place']),
            ]],
            'cta' => ['label' => 'Ajakan bertindak', 'fields' => [F::t('title_a', 'Judul tebal'), F::t('title_b', 'Judul miring'), F::t('btn_label', 'Teks tombol'), F::text('btn_url', 'Tautan tombol')]],
            'page_header' => ['label' => 'Judul halaman', 'fields' => [...$heads(), F::thtml('intro', 'Pengantar (:n, :r diganti otomatis di halaman paket)')]],
            'tour_list' => ['label' => 'Daftar semua paket (otomatis, dengan pencarian)', 'fields' => []],
            'tour_credits' => ['label' => 'Kredit foto paket (otomatis)', 'fields' => []],
            'text_block' => ['label' => 'Teks bebas', 'fields' => [...$heads(), F::trich('body', 'Isi')]],
            'faq' => ['label' => 'FAQ (otomatis dari modul FAQ)', 'fields' => [...$heads()]],
            'testimonials' => ['label' => 'Testimoni (otomatis dari modul Testimoni)', 'fields' => [...$heads()]],
            'albums' => ['label' => 'Album galeri (otomatis dari modul Galeri)', 'fields' => [...$heads()]],
            'team' => ['label' => 'Tim (otomatis dari modul Tim)', 'fields' => [...$heads()]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function fields(string $type): array
    {
        return self::types()[$type]['fields'] ?? [];
    }
}

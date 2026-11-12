<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** @extends ResourceController<Tour> */
class TourController extends ResourceController
{
    protected string $model = Tour::class;

    protected string $slug = 'tours';

    protected string $title = 'Paket tour';

    protected string $singular = 'Paket';

    protected array $with = ['category', 'destinations'];

    protected array $search = ['name', 'route', 'duration'];

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        $cats = TourCategory::orderBy('sort')->get()->map(fn ($c) => ['value' => $c->id, 'label' => tr($c->name)])->all();
        $dests = Destination::orderBy('sort')->get()->map(fn ($d) => ['value' => $d->id, 'label' => tr($d->name)])->all();
        $regions = collect(['asia' => 'Asia', 'europe' => 'Eropa', 'america' => 'Amerika', 'oceania' => 'Australia & Selandia Baru', 'middle-east' => 'Timur Tengah', 'central-asia' => 'Asia Tengah', 'africa' => 'Afrika'])
            ->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all();

        return [
            F::text('name', 'Nama paket', ['required' => true]),
            F::slug('slug', 'Slug (alamat halaman)', 'name'),
            F::select('category_id', 'Kategori', $cats, ['required' => true]),
            F::text('duration', 'Durasi, contoh 6D5N'),
            F::text('route', 'Rute, contoh Osaka – Kyoto – Tokyo'),
            F::multi('destinations_ids', 'Destinasi / negara yang dikunjungi', $dests, ['relation' => 'destinations']),
            F::t('area_label', 'Label wilayah di kartu, contoh Western Europe'),
            F::select('region', 'Wilayah (untuk filter)', $regions, ['required' => true, 'default' => 'asia']),
            F::image('cover_id', 'Foto cover', ['required' => true]),
            F::text('cover_credit', 'Kredit foto (teks | tautan, opsional)'),
            F::images('gallery', 'Galeri foto'),
            F::file('pdf_id', 'File PDF itinerary', 'file'),
            F::trich('description', 'Deskripsi singkat (opsional)'),
            F::list('highlights', 'Highlight (satu per baris)'),
            F::repeat('itinerary', 'Itinerary per hari', [
                F::number('n', 'Hari ke-', ['min' => 1]), F::text('title', 'Judul hari', ['required' => true]),
                F::text('meals', 'Makan, contoh MP,MS,MM'), F::textarea('text', 'Deskripsi'), F::text('hotel', 'Hotel'),
            ], ['item_label' => 'title']),
            F::text('dates_label', 'Keterangan tanggal keberangkatan (teks bebas)'),
            F::repeat('departures', 'Jadwal keberangkatan', [
                F::date('date', 'Tanggal', ['required' => true]),
                F::number('price_adult', 'Harga dewasa (Rp)'), F::number('price_child', 'Harga anak (Rp)'), F::number('single_supp', 'Single supplement (Rp)'),
                F::number('quota', 'Kuota kursi'), F::number('remaining', 'Sisa kursi'),
                F::select('status', 'Status', [['value' => 'available', 'label' => 'Tersedia'], ['value' => 'limited', 'label' => 'Hampir penuh'], ['value' => 'full', 'label' => 'Penuh']], ['default' => 'available']),
                F::text('note', 'Catatan'),
            ], ['item_label' => 'date']),
            F::number('price_from', 'Harga mulai dari (Rp), dipakai di kartu. Kosongkan untuk ambil harga termurah dari jadwal'),
            F::number('min_pax', 'Minimum peserta'),
            F::number('dp_min', 'DP minimal (Rp)'),
            F::toggle('flights_included', 'Sudah termasuk tiket pesawat'),
            F::list('includes', 'Harga termasuk (satu per baris)'),
            F::list('excludes', 'Harga tidak termasuk (satu per baris)'),
            F::rich('terms', 'Catatan & syarat'),
            F::toggle('has_shopping', 'Tur bersubsidi dengan kunjungan toko wajib'),
            F::t('promo_label', 'Label promo / early bird (contoh Early Bird)'),
            F::toggle('is_featured', 'Paket unggulan (tampil di halaman utama)'),
            F::number('featured_sort', 'Urutan di paket unggulan (kecil lebih dulu)'),
            F::toggle('is_active', 'Tampil di situs', ['default' => true]),
            F::t('meta_title', 'SEO: judul (kosongkan untuk otomatis)'),
            F::tarea('meta_description', 'SEO: deskripsi (kosongkan untuk otomatis)'),
            F::image('og_image_id', 'SEO: gambar untuk dibagikan (kosongkan untuk pakai foto cover)'),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cover_id', 'label' => '', 'type' => 'image'],
            ['key' => 'name', 'label' => 'Paket', 'type' => 'text'],
            ['key' => 'duration', 'label' => 'Durasi', 'type' => 'text'],
            ['key' => 'category', 'label' => 'Kategori', 'type' => 'badge', 'get' => fn (Tour $t) => tr($t->category?->name)],
            ['key' => 'price', 'label' => 'Harga mulai', 'type' => 'text', 'get' => fn (Tour $t) => rupiah($t->fromPrice()) ?: '–'],
            ['key' => 'is_featured', 'label' => 'Unggulan', 'type' => 'bool'],
        ];
    }

    protected function filters(): array
    {
        return [['key' => 'category_id', 'label' => 'Kategori', 'options' => TourCategory::orderBy('sort')->get()->map(fn ($c) => ['value' => $c->id, 'label' => tr($c->name)])->all()]];
    }

    protected function beforeSave(array &$data, ?Model $model, Request $request): void
    {
        // keep the day numbers in order when the editor left them blank
        $data['itinerary'] = collect($data['itinerary'] ?? [])->values()->map(function ($d, $i) {
            $d['n'] = $d['n'] ?: $i + 1;

            return $d;
        })->all();
    }
}

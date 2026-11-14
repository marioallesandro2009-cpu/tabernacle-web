<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Pillar;
use App\Models\PostCategory;
use App\Models\TourCategory;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<Pillar> */
class PillarController extends ResourceController
{
    protected string $model = Pillar::class;

    protected string $slug = 'pillars';

    protected string $title = 'Halaman layanan (pilar)';

    protected string $singular = 'Halaman layanan';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        $tourCats = TourCategory::orderBy('id')->get()->map(fn ($c) => ['value' => $c->id, 'label' => tr($c->name, 'id')])->values()->all();
        $postCats = PostCategory::orderBy('name')->get()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->values()->all();

        return [
            F::t('title', 'Nama layanan', ['required' => true]),
            F::slug('slug', 'Alamat (tampil di /layanan/…)', 'title'),
            F::trich('intro', 'Penjelasan layanan (tampil di atas daftar paket)'),
            F::image('cover_id', 'Gambar utama'),
            F::multi('tour_category_ids', 'Paket dari kategori ini ditampilkan', $tourCats),
            F::multi('post_category_ids', 'Artikel dari kategori ini ditampilkan', $postCats),
            F::text('keywords', 'Kata kunci tambahan untuk artikel terkait (pisah koma, contoh: ziarah, umrah, gereja)', ['max' => 500]),
            F::t('meta_title', 'SEO: judul'),
            F::tarea('meta_description', 'SEO: deskripsi'),
            F::toggle('is_active', 'Tampil di situs', ['default' => false]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cover_id', 'label' => '', 'type' => 'image'],
            ['key' => 'title', 'label' => 'Layanan', 'type' => 'tr'],
            ['key' => 'slug', 'label' => 'Alamat', 'type' => 'text'],
        ];
    }
}

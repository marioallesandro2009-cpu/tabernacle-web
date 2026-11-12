<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Destination;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<Destination> */
class DestinationController extends ResourceController
{
    protected string $model = Destination::class;

    protected string $slug = 'destinations';

    protected string $title = 'Destinasi';

    protected string $singular = 'Destinasi';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        $regions = collect(['asia' => 'Asia', 'europe' => 'Eropa', 'america' => 'Amerika', 'oceania' => 'Australia & Selandia Baru', 'middle-east' => 'Timur Tengah', 'central-asia' => 'Asia Tengah', 'africa' => 'Afrika'])
            ->map(fn ($l, $v) => ['value' => $v, 'label' => $l])->values()->all();

        return [
            F::t('name', 'Nama negara / kota', ['required' => true]),
            F::slug('slug', 'Slug', 'name'),
            F::select('region', 'Wilayah', $regions, ['required' => true]),
            F::trich('intro', 'Pengantar halaman destinasi'),
            F::image('cover_id', 'Foto cover'),
            F::toggle('is_active', 'Tampil di situs', ['default' => true]),
            F::t('meta_title', 'SEO: judul'), F::tarea('meta_description', 'SEO: deskripsi'),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Destinasi', 'type' => 'tr'],
            ['key' => 'region', 'label' => 'Wilayah', 'type' => 'badge'],
            ['key' => 'tours', 'label' => 'Jumlah paket', 'type' => 'text', 'get' => fn (Destination $d) => $d->tours()->count()],
        ];
    }

    protected function blockDelete(Model $model): ?string
    {
        return $model->tours()->exists() ? 'Destinasi ini masih dipakai paket tour. Lepaskan dari paketnya dulu.' : null;
    }
}

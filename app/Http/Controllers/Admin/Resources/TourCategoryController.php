<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\TourCategory;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<TourCategory> */
class TourCategoryController extends ResourceController
{
    protected string $model = TourCategory::class;

    protected string $slug = 'tour-categories';

    protected string $title = 'Kategori paket';

    protected string $singular = 'Kategori';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        return [
            F::t('name', 'Nama kategori', ['required' => true]),
            F::slug('slug', 'Slug', 'name'),
            F::select('kind', 'Jenis (menentukan label di kartu dan filter)', [
                ['value' => 'group', 'label' => 'Tur grup / konsorsium'], ['value' => 'fit', 'label' => 'FIT / grup kecil'],
                ['value' => 'private', 'label' => 'Private trip / by request'], ['value' => 'worship', 'label' => 'Ziarah / worship tour'],
            ], ['required' => true]),
            F::toggle('flights_included', 'Biasanya sudah termasuk tiket pesawat'),
            F::toggle('is_active', 'Aktif', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Kategori', 'type' => 'tr'],
            ['key' => 'kind', 'label' => 'Jenis', 'type' => 'badge'],
            ['key' => 'tours', 'label' => 'Jumlah paket', 'type' => 'text', 'get' => fn (TourCategory $c) => $c->tours()->count()],
        ];
    }

    protected function blockDelete(Model $model): ?string
    {
        return $model->tours()->exists() ? 'Kategori ini masih punya paket. Pindahkan paketnya dulu.' : null;
    }
}

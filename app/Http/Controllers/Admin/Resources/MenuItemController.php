<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\MenuItem;
use App\Support\F;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** @extends ResourceController<MenuItem> */
class MenuItemController extends ResourceController
{
    protected string $model = MenuItem::class;

    protected string $slug = 'menu-items';

    protected string $title = 'Menu navigasi';

    protected string $singular = 'Menu';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        $parents = MenuItem::whereNull('parent_id')->orderBy('location')->orderBy('sort')->get()
            ->reject(fn ($m) => $model && $m->id === $model->id)
            ->map(fn ($m) => ['value' => $m->id, 'label' => ($m->location === 'header' ? 'Header' : 'Footer').': '.tr($m->label)])->all();

        return [
            F::select('location', 'Lokasi', [['value' => 'header', 'label' => 'Header (atas)'], ['value' => 'footer', 'label' => 'Footer (bawah)']], ['required' => true]),
            F::t('label', 'Teks menu', ['required' => true]),
            F::text('url', 'Tautan (contoh /packages, /#services, https://...)', ['required' => true, 'max' => 500]),
            F::select('parent_id', 'Induk menu (untuk submenu, kosongkan jika menu utama)', $parents),
            F::toggle('new_tab', 'Buka di tab baru'),
            F::toggle('is_active', 'Tampil', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'label', 'label' => 'Menu', 'type' => 'tr'],
            ['key' => 'location', 'label' => 'Lokasi', 'type' => 'badge'],
            ['key' => 'url', 'label' => 'Tautan', 'type' => 'text'],
            ['key' => 'parent', 'label' => 'Induk', 'type' => 'text', 'get' => fn (MenuItem $m) => $m->parent_id ? tr(MenuItem::find($m->parent_id)?->label) : ''],
        ];
    }

    protected function filters(): array
    {
        return [['key' => 'location', 'label' => 'Lokasi', 'options' => [['value' => 'header', 'label' => 'Header'], ['value' => 'footer', 'label' => 'Footer']]]];
    }

    protected function beforeSave(array &$data, ?Model $model, Request $request): void
    {
        if (empty($model) && ! isset($data['sort'])) {
            $data['sort'] = (int) MenuItem::where('location', $data['location'])->max('sort') + 1;
        }
        // a child cannot be its own parent, and only one level deep
        if (! empty($data['parent_id']) && $model && (int) $data['parent_id'] === $model->id) {
            $data['parent_id'] = null;
        }
    }

    protected function blockDelete(Model $model): ?string
    {
        return MenuItem::where('parent_id', $model->getKey())->exists() ? 'Menu ini punya submenu. Hapus atau pindahkan submenunya dulu.' : null;
    }

    /** @param Builder<Model> $q */
    protected function scope(Builder $q, Request $request): void
    {
        $q->orderBy('location');
    }
}

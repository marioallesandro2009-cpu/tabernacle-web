<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\UiString;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** @extends ResourceController<UiString> */
class UiStringController extends ResourceController
{
    protected string $model = UiString::class;

    protected string $slug = 'ui-strings';

    protected string $title = 'Teks antarmuka';

    protected string $singular = 'Teks';

    protected bool $hasActive = false;

    protected array $search = ['key', 'text_en', 'text_id'];

    protected int $perPage = 60;

    protected string $orderBy = 'group';

    protected string $orderDir = 'asc';

    protected function fields(?Model $model = null): array
    {
        return [
            F::text('key', 'Kunci (jangan diubah kecuali menambah baru)', ['required' => true]),
            F::text('group', 'Kelompok', ['default' => 'umum']),
            F::textarea('text_en', 'Teks Inggris', ['required' => true, 'max' => 2000]),
            F::textarea('text_id', 'Teks Indonesia', ['max' => 2000]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'group', 'label' => 'Kelompok', 'type' => 'badge'],
            ['key' => 'key', 'label' => 'Kunci', 'type' => 'text'],
            ['key' => 'text_en', 'label' => 'Inggris', 'type' => 'text'],
            ['key' => 'text_id', 'label' => 'Indonesia', 'type' => 'text'],
        ];
    }

    protected function filters(): array
    {
        return [['key' => 'group', 'label' => 'Kelompok', 'options' => UiString::query()->distinct()->orderBy('group')->pluck('group')->map(fn ($g) => ['value' => $g, 'label' => $g])->all()]];
    }

    protected function extraRules(?Model $model, Request $request): array
    {
        return ['key' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9._-]+$/i', Rule::unique('ui_strings', 'key')->ignore($model?->getKey())]];
    }

    protected function blockDelete(Model $model): ?string
    {
        return 'Teks antarmuka dipakai langsung oleh template situs, jadi tidak bisa dihapus. Ubah isinya saja.';
    }
}

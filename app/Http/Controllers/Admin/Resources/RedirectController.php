<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Redirect;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** @extends ResourceController<Redirect> */
class RedirectController extends ResourceController
{
    protected string $model = Redirect::class;

    protected string $slug = 'redirects';

    protected string $title = 'Redirect (301/302)';

    protected string $singular = 'Redirect';

    protected array $search = ['from_path', 'to_url'];

    protected int $perPage = 50;

    protected function fields(?Model $model = null): array
    {
        return [
            F::text('from_path', 'Dari alamat (contoh /halaman-lama)', ['required' => true]),
            F::text('to_url', 'Ke alamat (contoh /halaman-baru atau https://...)', ['required' => true, 'max' => 1000]),
            F::select('code', 'Jenis', [['value' => '301', 'label' => '301, permanen (disarankan untuk SEO)'], ['value' => '302', 'label' => '302, sementara']], ['required' => true, 'default' => '301']),
            F::toggle('is_active', 'Aktif', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'from_path', 'label' => 'Dari', 'type' => 'text'],
            ['key' => 'to_url', 'label' => 'Ke', 'type' => 'text'],
            ['key' => 'code', 'label' => 'Kode', 'type' => 'badge'],
            ['key' => 'hits', 'label' => 'Dipakai', 'type' => 'text'],
        ];
    }

    protected function extraRules(?Model $model, Request $request): array
    {
        return [
            'from_path' => ['required', 'string', 'max:500', 'starts_with:/', Rule::unique('redirects', 'from_path')->ignore($model?->getKey())],
            'to_url' => ['required', 'string', 'max:1000', fn ($a, $v, $fail) => preg_match('/^\s*(javascript|data|vbscript):/i', (string) $v) ? $fail('Tujuan tidak valid.') : null,
                fn ($a, $v, $fail) => rtrim(strtolower((string) $v), '/') === rtrim(strtolower((string) $request->input('from_path')), '/') ? $fail('Tujuan tidak boleh sama dengan alamat asal.') : null],
        ];
    }

    protected function beforeSave(array &$data, ?Model $model, Request $request): void
    {
        $data['from_path'] = '/'.ltrim(strtolower(trim((string) $data['from_path'])), '/');
        $data['code'] = (int) $data['code'];
    }
}

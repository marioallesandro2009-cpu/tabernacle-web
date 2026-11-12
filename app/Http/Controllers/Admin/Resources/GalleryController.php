<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Gallery;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<Gallery> */
class GalleryController extends ResourceController
{
    protected string $model = Gallery::class;

    protected string $slug = 'galleries';

    protected string $title = 'Galeri trip';

    protected string $singular = 'Album';

    protected bool $sortable = true;

    protected function fields(?Model $model = null): array
    {
        return [
            F::t('title', 'Judul album', ['required' => true]),
            F::text('trip', 'Trip / keterangan (contoh Bandung 2026)'),
            F::tarea('description', 'Deskripsi'),
            F::image('cover_id', 'Foto sampul'),
            F::repeat('items', 'Foto di album', [F::image('media_id', 'Foto', ['required' => true]), F::t('caption', 'Keterangan')], ['item_label' => null]),
            F::toggle('is_active', 'Tampil di situs', ['default' => true]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cover_id', 'label' => '', 'type' => 'image'],
            ['key' => 'title', 'label' => 'Album', 'type' => 'tr'],
            ['key' => 'trip', 'label' => 'Trip', 'type' => 'text'],
            ['key' => 'photos', 'label' => 'Foto', 'type' => 'text', 'get' => fn (Gallery $g) => count($g->items ?: [])],
        ];
    }
}

<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\PostCategory;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<PostCategory> */
class PostCategoryController extends ResourceController
{
    protected string $model = PostCategory::class;

    protected string $slug = 'post-categories';

    protected string $title = 'Kategori artikel';

    protected string $singular = 'Kategori';

    protected bool $hasActive = false;

    protected string $orderBy = 'name';

    protected string $orderDir = 'asc';

    protected function fields(?Model $model = null): array
    {
        return [F::text('name', 'Nama kategori', ['required' => true]), F::slug('slug', 'Slug', 'name'), F::textarea('description', 'Deskripsi')];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Kategori', 'type' => 'text'],
            ['key' => 'posts', 'label' => 'Artikel', 'type' => 'text', 'get' => fn (PostCategory $c) => $c->posts()->count()],
        ];
    }
}

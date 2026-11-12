<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Models\Tag;
use App\Support\F;
use Illuminate\Database\Eloquent\Model;

/** @extends ResourceController<Tag> */
class TagController extends ResourceController
{
    protected string $model = Tag::class;

    protected string $slug = 'tags';

    protected string $title = 'Tag';

    protected string $singular = 'Tag';

    protected bool $hasActive = false;

    protected string $orderBy = 'name';

    protected string $orderDir = 'asc';

    protected function fields(?Model $model = null): array
    {
        return [F::text('name', 'Nama tag', ['required' => true]), F::slug('slug', 'Slug', 'name')];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'name', 'label' => 'Tag', 'type' => 'text'],
            ['key' => 'posts', 'label' => 'Artikel', 'type' => 'text', 'get' => fn (Tag $t) => $t->posts()->count()],
        ];
    }
}

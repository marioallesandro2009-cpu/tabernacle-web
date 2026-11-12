<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostCategory extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    /** Shows the English name on the English site (display only, never saved). */
    public function localize(): static
    {
        if (app()->getLocale() === 'en' && filled($this->name_en) && $this->name !== $this->name_en) {
            $this->setAttribute('name', $this->name_en);
            $this->syncOriginal();
        }

        return $this;
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'category_id');
    }
}

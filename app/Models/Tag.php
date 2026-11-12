<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use FlushesContent;

    protected $guarded = ['id'];

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }
}

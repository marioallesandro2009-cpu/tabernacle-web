<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = ['label' => 'array', 'new_tab' => 'boolean', 'is_active' => 'boolean'];

    /** @return HasMany<MenuItem, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->where('is_active', true)->orderBy('sort');
    }
}

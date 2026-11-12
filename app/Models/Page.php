<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'title' => 'array', 'meta_title' => 'array', 'meta_description' => 'array',
        'noindex' => 'boolean', 'is_active' => 'boolean',
    ];

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort')->orderBy('id');
    }
}

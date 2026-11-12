<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TourCategory extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = ['name' => 'array', 'flights_included' => 'boolean', 'is_active' => 'boolean'];

    /** @return HasMany<Tour, $this> */
    public function tours(): HasMany
    {
        return $this->hasMany(Tour::class, 'category_id');
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tour extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'area_label' => 'array', 'highlights' => 'array', 'description' => 'array', 'gallery' => 'array',
        'itinerary' => 'array', 'departures' => 'array', 'includes' => 'array', 'excludes' => 'array',
        'promo_label' => 'array', 'meta_title' => 'array', 'meta_description' => 'array',
        'flights_included' => 'boolean', 'has_shopping' => 'boolean', 'is_featured' => 'boolean', 'is_active' => 'boolean',
    ];

    /** @return BelongsTo<TourCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TourCategory::class, 'category_id');
    }

    /** @return BelongsToMany<Destination, $this> */
    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class);
    }

    /** @param Builder<Tour> $q */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** Lowest adult price: the manual "from" price, else the cheapest open departure. */
    public function fromPrice(): ?int
    {
        if ($this->price_from) {
            return (int) $this->price_from;
        }
        $prices = collect($this->departures ?: [])->pluck('price_adult')->filter()->map(fn ($p) => (int) $p);

        return $prices->min();
    }
}

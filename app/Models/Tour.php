<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use App\Support\Today;
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
        'available_until' => 'date',
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
    /**
     * Visible on the public site: switched on AND not expired. A package expires the day after
     * its `available_until` date (WIB), so it stays visible on its last departure day.
     */
    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true)
            ->where(fn (Builder $w) => $w->whereNull('available_until')->orWhereDate('available_until', '>=', Today::date()));
    }

    /** @param Builder<Tour> $q */
    public function scopeExpired(Builder $q): void
    {
        $q->whereNotNull('available_until')->whereDate('available_until', '<', Today::date());
    }

    public function isExpired(): bool
    {
        return $this->available_until !== null && $this->available_until->toDateString() < Today::date();
    }

    /**
     * Departures that have not happened yet (past dates are hidden on the site automatically).
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcomingDepartures(): array
    {
        $today = Today::date();

        return collect($this->departures ?: [])->filter(fn ($d) => ! empty($d['date']) && $d['date'] >= $today)->sortBy('date')->values()->all();
    }

    /** Lowest adult price: the manual "from" price, else the cheapest open departure. */
    public function fromPrice(): ?int
    {
        if ($this->price_from) {
            return (int) $this->price_from;
        }
        $prices = collect($this->upcomingDepartures() ?: $this->departures ?: [])->pluck('price_adult')->filter()->map(fn ($p) => (int) $p);

        return $prices->min();
    }
}

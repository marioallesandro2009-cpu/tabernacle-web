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
        'itinerary_en' => 'array', 'includes_en' => 'array', 'excludes_en' => 'array',
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

    protected function english(): bool
    {
        return app()->getLocale() === 'en';
    }

    /** Package name in the visitor's language (English name when set, else the Indonesian one). */
    public function displayName(): string
    {
        return (string) ($this->english() && filled($this->name_en) ? $this->name_en : $this->name);
    }

    /**
     * Itinerary in the visitor's language: on the English site each day takes its English
     * title / text / hotel (matched by position) and falls back to Indonesian when empty.
     *
     * @return array<int, array<string, mixed>>
     */
    public function localItinerary(): array
    {
        $days = array_values($this->itinerary ?: []);
        if (! $this->english()) {
            return $days;
        }
        $en = array_values($this->itinerary_en ?: []);

        return collect($days)->map(function ($d, $i) use ($en) {
            foreach (['title', 'text', 'hotel'] as $k) {
                if (! empty($en[$i][$k])) {
                    $d[$k] = $en[$i][$k];
                }
            }

            return $d;
        })->all();
    }

    /** True when the English site still shows (part of) the itinerary in Indonesian. */
    public function itineraryUntranslated(): bool
    {
        $en = array_values($this->itinerary_en ?: []);

        return collect($this->itinerary ?: [])->values()->contains(fn ($d, $i) => ! empty($d['text']) && empty($en[$i]['text']));
    }

    /** @return array<int, string> */
    public function localIncludes(): array
    {
        return ($this->english() && ! empty($this->includes_en)) ? $this->includes_en : ($this->includes ?: []);
    }

    /** @return array<int, string> */
    public function localExcludes(): array
    {
        return ($this->english() && ! empty($this->excludes_en)) ? $this->excludes_en : ($this->excludes ?: []);
    }

    public function localDatesLabel(): ?string
    {
        return ($this->english() && $this->dates_label_en) ? $this->dates_label_en : $this->dates_label;
    }

    public function localTerms(): ?string
    {
        return ($this->english() && $this->terms_en) ? $this->terms_en : $this->terms;
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

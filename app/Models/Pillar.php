<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A service line (Corporate & MICE, Wisata Rohani …) with its own page linking everything related. */
class Pillar extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'title' => 'array', 'intro' => 'array', 'meta_title' => 'array', 'meta_description' => 'array',
        'tour_category_ids' => 'array', 'post_category_ids' => 'array', 'is_active' => 'boolean',
    ];

    /** @return HasMany<CaseStudy, $this> */
    public function caseStudies(): HasMany
    {
        return $this->hasMany(CaseStudy::class);
    }

    /** @return array<int, string> */
    public function keywordList(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->keywords)), fn ($k) => mb_strlen($k) >= 3));
    }

    public function hasEnglish(): bool
    {
        $intro = (array) ($this->intro ?? []);

        return trim((string) ($this->title['en'] ?? '')) !== ''
            && (trim(strip_tags((string) ($intro['id'] ?? ''))) === '' || trim(strip_tags((string) ($intro['en'] ?? ''))) !== '');
    }
}

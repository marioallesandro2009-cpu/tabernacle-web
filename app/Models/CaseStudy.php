<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** A program the company ran for a client: goal, destinations, size, photos and outcome. */
class CaseStudy extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'title' => 'array', 'objective' => 'array', 'period' => 'array', 'results' => 'array', 'gallery' => 'array',
        'meta_title' => 'array', 'meta_description' => 'array', 'is_active' => 'boolean', 'participants' => 'integer',
    ];

    /** @return BelongsTo<Pillar, $this> */
    public function pillar(): BelongsTo
    {
        return $this->belongsTo(Pillar::class);
    }

    /** @return BelongsToMany<Destination, $this> */
    public function destinations(): BelongsToMany
    {
        return $this->belongsToMany(Destination::class, 'case_study_destination');
    }

    public function hasEnglish(): bool
    {
        $obj = (array) ($this->objective ?? []);

        return trim((string) ($this->title['en'] ?? '')) !== ''
            && (trim(strip_tags((string) ($obj['id'] ?? ''))) === '' || trim(strip_tags((string) ($obj['en'] ?? ''))) !== '');
    }
}

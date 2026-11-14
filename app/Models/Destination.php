<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Destination extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'name' => 'array', 'intro' => 'array', 'meta_title' => 'array', 'meta_description' => 'array', 'is_active' => 'boolean',
    ];

    /** @return BelongsToMany<Tour, $this> */
    public function tours(): BelongsToMany
    {
        return $this->belongsToMany(Tour::class);
    }

    /** English name, and the English intro when there is an intro at all. */
    public function hasEnglish(): bool
    {
        $intro = (array) ($this->intro ?? []);
        $hasIntro = trim(strip_tags((string) ($intro['id'] ?? ''))) !== '';

        return trim((string) ($this->name['en'] ?? '')) !== '' && (! $hasIntro || trim(strip_tags((string) ($intro['en'] ?? ''))) !== '');
    }
}

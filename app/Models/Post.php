<?php

namespace App\Models;

use App\Models\Concerns\FlushesContent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Post extends Model
{
    use FlushesContent, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = ['faq' => 'array', 'faq_en' => 'array', 'published_at' => 'datetime'];

    /** Texts that have an English version (column + "_en"). */
    public const TRANSLATED = ['title', 'excerpt', 'content', 'meta_title', 'meta_description', 'faq'];

    protected bool $localized = false;

    /**
     * On the English site, swaps in the English texts (when the article has an English title)
     * for display only. Never save a localized model.
     */
    public function localize(): static
    {
        if ($this->localized || app()->getLocale() !== 'en') {
            return $this;
        }
        $this->localized = true;
        if (filled($this->title_en)) {
            foreach (self::TRANSLATED as $k) {
                $en = $this->getAttribute($k.'_en');
                if (filled($en)) {
                    $this->setAttribute($k, $en);
                }
            }
            $this->syncOriginal();
        }
        if ($this->relationLoaded('category')) {
            $this->category?->localize();
        }

        return $this;
    }

    /** @return BelongsTo<PostCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** Live articles: published, or scheduled and the time has come. @param Builder<Post> $q */
    public function scopeLive(Builder $q): void
    {
        $q->whereIn('status', ['published', 'scheduled'])->where('published_at', '<=', now());
    }
}

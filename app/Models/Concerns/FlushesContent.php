<?php

namespace App\Models\Concerns;

use App\Support\Content;

/** Clears the public-site cache whenever this record is saved or deleted. */
trait FlushesContent
{
    public static function bootFlushesContent(): void
    {
        $flush = fn () => Content::bump();
        static::saved($flush);
        static::deleted($flush);
    }
}

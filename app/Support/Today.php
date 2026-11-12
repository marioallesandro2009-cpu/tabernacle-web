<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * "Today" as the business sees it (Indonesia, WIB), independent of the server clock (UTC).
 * Used to decide when a package has expired.
 */
class Today
{
    public const TIMEZONE = 'Asia/Jakarta';

    public static function date(): string
    {
        return Carbon::now(self::TIMEZONE)->toDateString();
    }
}

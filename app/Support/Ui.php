<?php

namespace App\Support;

use App\Models\UiString;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Interface strings (button labels, headings that repeat) kept in the database. */
class Ui
{
    /** @var array<string, array{0: string, 1: string}>|null */
    protected static ?array $loaded = null;

    /** @return array<string, array{0: string, 1: string}> */
    public static function all(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }
        try {
            if (! Schema::hasTable('ui_strings')) {
                return self::$loaded = [];
            }
        } catch (\Throwable) {
            return self::$loaded = [];
        }

        return self::$loaded = Cache::rememberForever('ui_strings_all', function () {
            return UiString::query()->get(['key', 'text_en', 'text_id'])
                ->mapWithKeys(fn ($s) => [$s->key => [(string) $s->text_en, (string) $s->text_id]])->all();
        });
    }

    /** @param array<string, string|int> $replace */
    public static function get(string $key, array $replace = []): string
    {
        $row = self::all()[$key] ?? null;
        if (! $row) {
            return $key;
        }
        $text = app()->getLocale() === 'id' ? ($row[1] !== '' ? $row[1] : $row[0]) : ($row[0] !== '' ? $row[0] : $row[1]);
        foreach ($replace as $k => $v) {
            $text = str_replace(':'.$k, (string) $v, $text);
        }

        return $text;
    }

    public static function forget(): void
    {
        Cache::forget('ui_strings_all');
        self::$loaded = null;
    }
}

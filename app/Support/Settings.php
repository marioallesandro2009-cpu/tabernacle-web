<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Settings
{
    /** @var array<string, mixed>|null */
    protected static ?array $loaded = null;

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }
        try {
            if (! Schema::hasTable('settings')) {
                return self::$loaded = [];
            }
        } catch (\Throwable) {
            return self::$loaded = [];
        }

        return self::$loaded = Cache::rememberForever('settings_all', function () {
            return Setting::query()->get()->mapWithKeys(fn ($s) => [$s->key => json_decode((string) $s->value, true)])->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $v = self::all()[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }

    public static function forget(): void
    {
        Cache::forget('settings_all');
        self::$loaded = null;
    }
}

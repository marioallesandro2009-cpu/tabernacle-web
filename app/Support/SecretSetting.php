<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Settings that are secrets (API keys). They are stored encrypted with APP_KEY, never sent back to
 * the browser, and a blank field on save keeps the stored value.
 */
class SecretSetting
{
    public static function get(string $key): ?string
    {
        $raw = Settings::get($key);
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null; // APP_KEY changed: the key has to be entered again
        }
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== null;
    }

    public static function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }
}

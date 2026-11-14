<?php

namespace App\Services\Outreach\Verification;

use App\Support\SecretSetting;

/** The verification service chosen in Pengaturan, or null when none is set (basic checks only). */
class VerifierFactory
{
    public const PROVIDERS = ['zerobounce' => 'ZeroBounce', 'reoon' => 'Reoon'];

    public function make(): ?EmailVerifier
    {
        $provider = (string) setting('verify_provider', '');
        $key = SecretSetting::get('verify_api_key');
        if ($key === null || $key === '') {
            return null;
        }

        return match ($provider) {
            'zerobounce' => new ZeroBounceVerifier($key),
            'reoon' => new ReoonVerifier($key),
            default => null,
        };
    }
}

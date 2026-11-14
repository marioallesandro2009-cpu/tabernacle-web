<?php

namespace App\Services\Outreach\Verification;

use Illuminate\Support\Facades\Http;

/** Reoon Email Verifier single-address API, "power" mode (https://emailverifier.reoon.com/api/v1/verify). */
class ReoonVerifier implements EmailVerifier
{
    public const URL = 'https://emailverifier.reoon.com/api/v1/verify';

    public function __construct(protected string $key) {}

    public function name(): string
    {
        return 'reoon';
    }

    public function verify(string $email): array
    {
        try {
            $res = Http::timeout(60)->acceptJson()->get(self::URL, ['email' => $email, 'key' => $this->key, 'mode' => 'power']);
        } catch (\Throwable $e) {
            throw new VerificationUnavailable('Reoon tidak bisa dihubungi.');
        }
        $d = (array) $res->json();
        if (! $res->successful() || ! isset($d['status']) || $d['status'] === 'error') {
            throw new VerificationUnavailable('Reoon: '.(string) ($d['reason'] ?? $d['message'] ?? 'jawaban tidak dikenal ('.$res->status().')'));
        }
        $raw = strtolower((string) $d['status']);
        $status = match (true) {
            ! empty($d['is_disposable']) || $raw === 'disposable' => 'disposable',
            in_array($raw, ['safe', 'valid', 'role_account'], true) => 'valid',
            in_array($raw, ['catch_all', 'catch-all'], true) => 'catch_all',
            in_array($raw, ['unknown', 'inbox_full'], true) => 'unknown',
            default => 'invalid', // invalid, disabled, spamtrap
        };

        return ['status' => $status, 'note' => 'Reoon: '.$raw];
    }
}

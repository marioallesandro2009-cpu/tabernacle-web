<?php

namespace App\Services\Outreach\Verification;

use Illuminate\Support\Facades\Http;

/** ZeroBounce single-address API (https://api.zerobounce.net/v2/validate). */
class ZeroBounceVerifier implements EmailVerifier
{
    public const URL = 'https://api.zerobounce.net/v2/validate';

    public function __construct(protected string $key) {}

    public function name(): string
    {
        return 'zerobounce';
    }

    public function verify(string $email): array
    {
        try {
            $res = Http::timeout(30)->acceptJson()->get(self::URL, ['api_key' => $this->key, 'email' => $email, 'ip_address' => '']);
        } catch (\Throwable $e) {
            throw new VerificationUnavailable('ZeroBounce tidak bisa dihubungi.');
        }
        $d = (array) $res->json();
        if (! $res->successful() || isset($d['error']) || ! isset($d['status'])) {
            throw new VerificationUnavailable('ZeroBounce: '.(string) ($d['error'] ?? 'jawaban tidak dikenal ('.$res->status().')'));
        }
        $sub = strtolower((string) ($d['sub_status'] ?? ''));
        $status = match (strtolower((string) $d['status'])) {
            'valid' => 'valid',
            'catch-all', 'catch_all' => 'catch_all',
            'unknown' => 'unknown',
            'do_not_mail' => $sub === 'disposable' ? 'disposable' : ($sub === 'role_based' ? 'valid' : 'invalid'),
            default => 'invalid', // invalid, spamtrap, abuse
        };

        return ['status' => $status, 'note' => trim('ZeroBounce: '.$d['status'].($sub !== '' ? ' ('.$sub.')' : ''))];
    }
}

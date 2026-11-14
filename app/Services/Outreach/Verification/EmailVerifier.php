<?php

namespace App\Services\Outreach\Verification;

/**
 * An email verification service. It answers with one of: valid, invalid, catch_all, unknown,
 * disposable, plus a short note. The service checks the mailbox itself; our server never does.
 */
interface EmailVerifier
{
    public const STATUSES = ['valid', 'invalid', 'catch_all', 'unknown', 'disposable'];

    /**
     * @return array{status: string, note: ?string}
     *
     * @throws VerificationUnavailable when the service cannot answer now (no credits, bad key, network)
     */
    public function verify(string $email): array;

    public function name(): string;
}

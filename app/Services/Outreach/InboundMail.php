<?php

namespace App\Services\Outreach;

use Illuminate\Support\Carbon;

/** One email found in a sender account's inbox, reduced to what Outreach needs. */
class InboundMail
{
    /**
     * @param  array<int, string>  $references  Message-IDs without angle brackets
     * @param  array<string, string>  $headers  lowercase header name => value
     * @param  string  $dsn  raw text of the message (delivery reports carry the failed address and status here)
     */
    public function __construct(
        public int $uid,
        public ?string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public string $fromEmail,
        public ?string $fromName,
        public string $subject,
        public ?Carbon $date,
        public string $body,
        public array $headers = [],
        public string $dsn = '',
        public bool $hasAttachments = false,
    ) {}

    /** Message-ID, trimmed of < >. */
    public static function cleanId(?string $id): ?string
    {
        $id = trim((string) $id, " \t\r\n<>");

        return $id === '' ? null : $id;
    }
}

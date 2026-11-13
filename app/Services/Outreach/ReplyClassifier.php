<?php

namespace App\Services\Outreach;

/**
 * Decides what a message in the inbox is: a real reply, an automatic reply (out of office), or a
 * bounce (the mail server telling us an address could not be reached). It only looks at the
 * message; it changes nothing.
 */
class ReplyClassifier
{
    /** Subjects of out-of-office and similar automatic answers. */
    protected const AUTO_SUBJECT = '/^\s*((auto(matic)?(ally)?[\s-]*(reply|response|replied)|autoreply|out of (the )?office|ooo\b|away from|on (leave|vacation)|balasan otomatis|jawaban otomatis|di luar kantor|sedang cuti|tidak berada di kantor)\b|(re:\s*)?(automatic|auto)\s)/i';

    protected const DEAD_ADDRESS = '/user unknown|no such user|does not exist|mailbox (not found|unavailable|full)|unknown (user|recipient)|recipient (address )?rejected|address (rejected|not found)|invalid recipient|\b5\.1\.[1-6]\b|\b550\b/i';

    /**
     * @return array{kind: string, hard: bool, recipients: array<int, string>}
     *                                                                         kind: bounce | auto_reply | reply. For a bounce, "hard" means the address is dead for good.
     */
    public function classify(InboundMail $m): array
    {
        if ($this->isBounce($m)) {
            return ['kind' => 'bounce', 'hard' => $this->isHard($m), 'recipients' => $this->failedRecipients($m)];
        }
        if ($this->isAutoReply($m)) {
            return ['kind' => 'auto_reply', 'hard' => false, 'recipients' => []];
        }

        return ['kind' => 'reply', 'hard' => false, 'recipients' => []];
    }

    public function isBounce(InboundMail $m): bool
    {
        $type = strtolower($m->headers['content-type'] ?? '');
        $local = strtolower(strstr($m->fromEmail, '@', true) ?: $m->fromEmail);
        $report = str_contains($type, 'multipart/report') && str_contains($type, 'delivery-status');

        return $report || in_array($local, ['mailer-daemon', 'mail-daemon', 'postmaster'], true);
    }

    public function isAutoReply(InboundMail $m): bool
    {
        $h = $m->headers;
        $auto = strtolower(trim($h['auto-submitted'] ?? ''));
        if ($auto !== '' && $auto !== 'no') {
            return true;
        }
        if (isset($h['x-autoreply']) || isset($h['x-autorespond']) || isset($h['x-auto-response-suppress'])) {
            return true;
        }
        if (in_array(strtolower(trim($h['precedence'] ?? '')), ['auto_reply', 'auto-reply', 'bulk', 'junk'], true)) {
            return true;
        }

        return (bool) preg_match(self::AUTO_SUBJECT, $m->subject);
    }

    /** A delivery report says "failed" with a 5.x.x code; a 4.x.x code or "delayed" is only a delay. */
    public function isHard(InboundMail $m): bool
    {
        if (preg_match('/^Action:\s*(\w+)/mi', $m->dsn, $a)) {
            return strtolower($a[1]) === 'failed';
        }
        if (preg_match('/^Status:\s*([245])\.\d+\.\d+/mi', $m->dsn, $s)) {
            return $s[1] === '5';
        }

        return (bool) preg_match(self::DEAD_ADDRESS, $m->subject."\n".$m->body."\n".$m->dsn);
    }

    /** Addresses the report says it could not deliver to. @return array<int, string> */
    public function failedRecipients(InboundMail $m): array
    {
        $found = [];
        preg_match_all('/^(?:Final|Original)-Recipient:\s*(?:rfc822;)?\s*<?([^\s>;]+@[^\s>;]+)/mi', $m->dsn, $a);
        $found = array_merge($found, $a[1]);
        if (isset($m->headers['x-failed-recipients'])) {
            preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $m->headers['x-failed-recipients'], $b);
            $found = array_merge($found, $b[0]);
        }

        return array_values(array_unique(array_map('strtolower', $found)));
    }
}

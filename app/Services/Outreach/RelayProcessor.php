<?php

namespace App\Services\Outreach;

use App\Jobs\Outreach\SendRelayConfirmation;
use App\Models\Outreach\Account;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Message;

/**
 * Answers written in a personal mailbox, sent back to the sender account (Reply-To of a forwarded
 * reply). Only passed on to the prospect when all three hold: the sender is on the account's list
 * of personal addresses, the subject carries a valid [#TOKEN] of a conversation of this account, and
 * the receiving server's Authentication-Results says SPF or DKIM passed for the sender's domain.
 * Anything else is recorded as "rejected" with its reason and never reaches the prospect.
 */
class RelayProcessor
{
    public const TOKEN_PATTERN = '/\[#([A-HJKMNP-Z2-9]{10})\]/';

    public function __construct(protected ThreadService $threads = new ThreadService) {}

    /** Mail that looks like an answer from a personal mailbox (a token in the subject, or a listed sender). */
    public function isCandidate(Account $account, InboundMail $mail): bool
    {
        return (bool) preg_match(self::TOKEN_PATTERN, $mail->subject)
            || in_array(mb_strtolower($mail->fromEmail), $account->relaySenderList(), true);
    }

    /** @return Message|null null when this relay email was already handled */
    public function process(Account $account, InboundMail $mail): ?Message
    {
        if ($mail->messageId && Message::where('account_id', $account->id)->where('inbound_message_id', $mail->messageId)->exists()) {
            return null;
        }
        $from = mb_strtolower($mail->fromEmail);
        $allowed = in_array($from, $account->relaySenderList(), true);
        preg_match(self::TOKEN_PATTERN, $mail->subject, $t);
        $cc = isset($t[1]) ? CampaignContact::where('thread_token', $t[1])->where('account_id', $account->id)->first() : null;
        $body = $this->clean($mail->body);

        $reason = match (true) {
            ! $account->relay_enabled => 'Balas dari email pribadi tidak diaktifkan untuk akun '.$account->email.'.',
            ! $allowed => 'Pengirim '.$from.' tidak ada di daftar email pribadi yang diizinkan.',
            ! isset($t[1]) => 'Kode percakapan [#…] tidak ada di subjek.',
            ! $cc => 'Kode percakapan [#'.$t[1].'] tidak dikenal untuk akun ini.',
            ! $this->authenticated($mail, $from) => 'Email tidak lolos pemeriksaan SPF/DKIM untuk domain pengirim.',
            $body === '' => 'Isi balasan kosong setelah kutipan dan tanda tangan dibuang.',
            default => null,
        };

        $m = Message::create([
            'channel' => 'email', 'campaign_id' => $cc?->campaign_id, 'campaign_contact_id' => $cc?->id, 'contact_id' => $cc?->contact_id,
            'account_id' => $account->id, 'direction' => 'out', 'kind' => Message::MANUAL, 'status' => 'manual', 'source' => 'email',
            'relay_from' => $from, 'inbound_message_id' => $mail->messageId, 'subject' => $cc ? $this->threads->subject($cc) : mb_substr($mail->subject, 0, 500),
            'body' => mb_substr($body !== '' ? $body : $mail->body, 0, 20000), 'imap_uid' => $mail->uid,
        ]);
        $m->moveTo('received');

        if ($reason) {
            $m->moveTo('rejected', $reason);
            if ($allowed) {
                SendRelayConfirmation::dispatch($m->id, $mail->hasAttachments); // a stranger gets no answer at all
            }

            return $m;
        }
        if ($account->relay_mode === 'direct') {
            $this->threads->queue($m); // the confirmation follows once it is sent (or failed)
            if ($mail->hasAttachments) {
                SendRelayConfirmation::dispatch($m->id, true);
            }
        } else {
            $m->moveTo('draft');
            SendRelayConfirmation::dispatch($m->id, $mail->hasAttachments);
        }

        return $m;
    }

    /**
     * The topmost Authentication-Results header (written by the server that received the mail; ones
     * further down could have been added by the sender) must show spf=pass for the sender's domain
     * (smtp.mailfrom) or dkim=pass signed by it (header.d / header.i).
     */
    public function authenticated(InboundMail $mail, string $from): bool
    {
        $ar = strtolower((string) ($mail->headers['authentication-results'] ?? ''));
        $domain = substr((string) strrchr($from, '@'), 1);
        if ($ar === '' || $domain === '') {
            return false;
        }
        $matches = fn (string $d) => ($d = trim($d, " <>@;\"'")) !== '' && ($d === $domain || str_ends_with($domain, '.'.$d) || str_ends_with($d, '.'.$domain));
        $domainOf = fn (string $v) => str_contains($v, '@') ? substr((string) strrchr($v, '@'), 1) : $v;
        foreach (preg_split('/;/', $ar) ?: [] as $part) {
            if (preg_match('/\bspf\s*=\s*pass\b.*?\bsmtp\.mailfrom\s*=\s*([^\s;]+)/', $part, $s) && $matches($domainOf($s[1]))) {
                return true;
            }
            if (preg_match('/\bdkim\s*=\s*pass\b.*?\bheader\.(?:d|i)\s*=\s*([^\s;]+)/', $part, $d) && $matches($domainOf($d[1]))) {
                return true;
            }
        }

        return false;
    }

    /** Only what the person typed: no quoted earlier mail, no "Sent from my iPhone", no signature block. */
    public function clean(string $body): string
    {
        $lines = preg_split('/\R/', str_replace("\u{00A0}", ' ', trim($body))) ?: [];
        $out = [];
        foreach ($lines as $i => $line) {
            $next = $lines[$i + 1] ?? '';
            $quoteStart = preg_match('/^\s*(On .{2,200} wrote:|Pada .{2,200} menulis:|-{2,}\s*(Original Message|Pesan Asli|Forwarded message)|_{5,}|From:\s.+|Dari:\s.+|Le .{2,200} a écrit\s*:)\s*$/i', $line)
                || (preg_match('/^\s*(On|Pada)\s.{2,200}$/i', $line) && preg_match('/^\s*.{0,200}(wrote|menulis):\s*$/i', $next));
            if ($quoteStart || preg_match('/^\s*--\s*$/', $line)) {
                break; // quoted mail or the signature delimiter: everything after is not the answer
            }
            if (preg_match('/^\s*(Sent from my|Sent from Mail|Sent from Outlook|Get Outlook for|Dikirim dari|Kirim dari|Sent via|Diketik di)\b/i', $line)) {
                break;
            }
            if (str_starts_with(ltrim($line), '>')) {
                continue;
            }
            $out[] = rtrim($line);
        }

        return trim(implode("\n", $out));
    }
}

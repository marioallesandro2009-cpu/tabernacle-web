<?php

namespace App\Services\Outreach;

use App\Models\Outreach\Account;
use App\Models\Outreach\Message;
use Illuminate\Support\Carbon;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message as ImapMessage;

/**
 * Reads new mail from an account's INBOX. Messages are read without being marked as read, so the
 * people using the mailbox still see what is new. Only emails newer than the last one handled
 * are fetched; the very first time, it looks back to the first email the account ever sent.
 */
class ImapInbox
{
    /** @return array{mails: array<int, InboundMail>, last_uid: int} */
    public function fetch(Account $a, int $limit = 100): array
    {
        $client = (new ClientManager)->make([
            'host' => (string) $a->imap_host, 'port' => (int) $a->imap_port, 'protocol' => 'imap',
            'encryption' => $a->imap_encryption === 'none' ? false : $a->imap_encryption,
            'validate_cert' => true, 'username' => $a->username, 'password' => (string) $a->password,
            'authentication' => null, 'timeout' => 30,
        ]);
        $client->connect();
        try {
            $folder = $client->getFolder('INBOX');
            $last = (int) $a->imap_last_uid;
            if (! $folder) {
                return ['mails' => [], 'last_uid' => $last];
            }
            $mails = [];
            $max = $last;
            if ($last === 0) {
                $firstSent = Message::where('account_id', $a->id)->where('direction', 'out')->where('status', 'sent')->min('sent_at');
                if ($firstSent) {
                    $since = Carbon::parse($firstSent)->subDay()->format('d-M-Y');
                    $found = $folder->query()->leaveUnread()->whereSince($since)->limit($limit)->get();
                    foreach ($found as $m) {
                        $mails[] = $this->convert($m);
                        $max = max($max, (int) $m->getUid());
                    }
                }
                if ($max === 0) { // nothing to handle yet: remember where the inbox ends now
                    $max = max(0, (int) ($folder->examine()['uidnext'] ?? 1) - 1);
                }
            } else {
                $n = 0;
                $found = $folder->query()->leaveUnread()->filter(function ($uid) use ($last, &$n, $limit) {
                    if ($uid <= $last || $n >= $limit) {
                        return false;
                    }
                    $n++;

                    return true;
                });
                foreach ($found as $m) {
                    $mails[] = $this->convert($m);
                    $max = max($max, (int) $m->getUid());
                }
            }

            return ['mails' => $mails, 'last_uid' => $max];
        } finally {
            $client->disconnect();
        }
    }

    protected function convert(ImapMessage $m): InboundMail
    {
        $from = $m->getFrom()->first();
        $date = $m->getDate()->first();
        $text = $m->hasTextBody() ? $m->getTextBody() : strip_tags($m->getHTMLBody());
        $refs = array_values(array_filter(array_map([InboundMail::class, 'cleanId'], preg_split('/\s+/', trim($m->getReferences()->toString())) ?: [])));

        return new InboundMail(
            uid: (int) $m->getUid(),
            messageId: InboundMail::cleanId($m->getMessageId()->toString()),
            inReplyTo: InboundMail::cleanId($m->getInReplyTo()->toString()),
            references: $refs,
            fromEmail: mb_strtolower((string) ($from->mail ?? '')),
            fromName: ($from->personal ?? '') ?: null,
            subject: trim($m->getSubject()->toString()),
            date: $date instanceof Carbon ? $date : null,
            body: $text,
            headers: $this->parseHeaders((string) ($m->getHeader()->raw ?? '')),
            dsn: mb_substr($m->getRawBody(), 0, 60000),
        );
    }

    /** @return array<string, string> lowercase name => value; the first occurrence of a header wins */
    public function parseHeaders(string $raw): array
    {
        $raw = preg_replace("/\r?\n[ \t]+/", ' ', $raw) ?? $raw;
        $out = [];
        foreach (preg_split('/\r?\n/', $raw) ?: [] as $line) {
            if (preg_match('/^([A-Za-z0-9\-]+):\s*(.*)$/', $line, $m)) {
                $out[strtolower($m[1])] ??= trim($m[2]);
            }
        }

        return $out;
    }
}

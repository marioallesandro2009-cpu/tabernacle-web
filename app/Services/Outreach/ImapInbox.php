<?php

namespace App\Services\Outreach;

use App\Models\Outreach\Account;
use App\Models\Outreach\Message;
use Illuminate\Support\Carbon;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Message as ImapMessage;

/**
 * Reads new mail from an account's INBOX and its Spam/Junk folder (a reply or a bounce can land
 * there). Messages are read without being marked as read, so the people using the mailbox still
 * see what is new. Per folder only emails newer than the last one handled are fetched; the very
 * first time, it looks back to the first email the account ever sent.
 */
class ImapInbox
{
    /** Folder names that mean "spam", when the server does not flag the folder as \Junk. */
    public const JUNK_NAMES = ['spam', 'junk', 'junk e-mail', 'junk email', 'junk mail', 'bulk', 'bulk mail', 'spam mail', 'spambox', 'junkmail', 'surat sampah', 'spam folder'];

    /** @return array{mails: array<int, InboundMail>, last_uid: int, uids?: array<string, int>} uids = last UID per folder */
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
            $known = (array) ($a->imap_folder_uids ?? []);
            $known['INBOX'] ??= (int) $a->imap_last_uid;
            $paths = ['INBOX'];
            try {
                $list = (array) $client->getConnection()->folders('', '*')->validatedData();
                $paths = array_merge($paths, self::junkFolders(array_map(fn ($i) => (array) ($i['flags'] ?? []), $list)));
            } catch (\Throwable) {
                // listing folders is optional: the inbox alone still works
            }
            $mails = [];
            $uids = [];
            foreach (array_unique($paths) as $path) {
                $folder = $path === 'INBOX' ? $client->getFolder('INBOX') : $client->getFolderByPath($path, true, true);
                if (! $folder) {
                    continue;
                }
                [$found, $uids[$path]] = $this->read($a, $folder, (int) ($known[$path] ?? 0), $limit);
                foreach ($found as $mail) {
                    $mail->folder = $path;
                    $mails[] = $mail;
                }
            }

            return ['mails' => $mails, 'last_uid' => $uids['INBOX'] ?? (int) $a->imap_last_uid, 'uids' => $uids];
        } finally {
            $client->disconnect();
        }
    }

    /**
     * The Spam/Junk folders among a mailbox's folders: flagged \Junk by the server, or named like it
     * ("Spam", "INBOX.Junk", "[Gmail]/Spam", "Bulk Mail" …).
     *
     * @param  array<string, array<int, string>>  $list  folder path => IMAP flags
     * @return array<int, string>
     */
    public static function junkFolders(array $list): array
    {
        $flagged = [];
        $named = [];
        foreach ($list as $path => $flags) {
            $path = (string) $path;
            $flags = array_map('strtolower', $flags);
            if (in_array('\noselect', $flags, true) || strtoupper($path) === 'INBOX') {
                continue;
            }
            if (in_array('\junk', $flags, true)) {
                $flagged[] = $path;

                continue;
            }
            $leaf = strtolower(trim((string) preg_replace('/^.*[.\/]/', '', $path)));
            if (in_array($leaf, self::JUNK_NAMES, true)) {
                $named[] = $path;
            }
        }

        return $flagged ?: $named;
    }

    /**
     * New mail of one folder, and the highest UID now handled there.
     *
     * @return array{0: array<int, InboundMail>, 1: int}
     */
    protected function read(Account $a, Folder $folder, int $last, int $limit): array
    {
        $mails = [];
        $max = $last;
        if ($last === 0) {
            $firstSent = Message::where('account_id', $a->id)->where('direction', 'out')->where('status', 'sent')->min('sent_at');
            if ($firstSent) {
                $since = Carbon::parse($firstSent)->subDay()->format('d-M-Y');
                foreach ($folder->query()->leaveUnread()->whereSince($since)->limit($limit)->get() as $m) {
                    $mails[] = $this->convert($m);
                    $max = max($max, (int) $m->getUid());
                }
            }
            if ($max === 0) { // nothing to handle yet: remember where the folder ends now
                $max = max(0, (int) ($folder->examine()['uidnext'] ?? 1) - 1);
            }

            return [$mails, $max];
        }
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

        return [$mails, $max];
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
            hasAttachments: $m->hasAttachments(),
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

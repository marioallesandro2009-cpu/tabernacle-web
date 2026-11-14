<?php

namespace App\Services\Outreach;

use App\Models\Outreach\Account;
use Throwable;

/** Checks every active account's inbox for replies and bounces. */
class ReplyPoller
{
    public function __construct(
        protected ImapInbox $inbox = new ImapInbox,
        protected ReplyProcessor $processor = new ReplyProcessor,
        protected ConnectionTester $tester = new ConnectionTester,
    ) {}

    /** @return array{accounts: int, stored: int, errors: int} */
    public function pollAll(): array
    {
        $result = ['accounts' => 0, 'stored' => 0, 'errors' => 0];
        foreach (Account::where('is_active', true)->whereNotNull('imap_host')->where('imap_host', '!=', '')->get() as $account) {
            $result['accounts']++;
            $n = $this->poll($account);
            if ($n === null) {
                $result['errors']++;
            } else {
                $result['stored'] += $n;
            }
        }

        return $result;
    }

    /** @return int|null messages filed, or null when the inbox could not be read */
    public function poll(Account $account): ?int
    {
        try {
            $r = $this->inbox->fetch($account);
            $stored = 0;
            foreach ($r['mails'] as $mail) {
                if ($this->processor->process($account, $mail)) {
                    $stored++;
                }
            }
            $uids = (array) ($account->imap_folder_uids ?? []);
            foreach ($r['uids'] ?? ['INBOX' => $r['last_uid']] as $folder => $uid) {
                $uids[$folder] = max((int) ($uids[$folder] ?? 0), (int) $uid);
            }
            $account->update(['imap_last_uid' => max((int) $account->imap_last_uid, $r['last_uid']), 'imap_folder_uids' => $uids, 'imap_polled_at' => now(), 'imap_ok' => true, 'imap_error' => null]);

            return $stored;
        } catch (Throwable $e) {
            $account->update(['imap_ok' => false, 'imap_error' => $this->tester->friendly($e), 'imap_polled_at' => now()]);

            return null;
        }
    }
}

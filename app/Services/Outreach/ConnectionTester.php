<?php

namespace App\Services\Outreach;

use App\Models\Outreach\Account;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;
use Webklex\PHPIMAP\ClientManager;

/** Tries to log in to an account's SMTP and IMAP servers. Nothing is sent and nothing is read. */
class ConnectionTester
{
    /** @return array{ok: bool, message: string} */
    public function smtp(Account $a): array
    {
        try {
            $t = new EsmtpTransport($a->smtp_host, (int) $a->smtp_port, $a->smtp_encryption === 'ssl');
            if ($a->smtp_encryption === 'none') {
                $t->setAutoTls(false);
            }
            $t->setUsername($a->username);
            $t->setPassword((string) $a->password);
            $stream = $t->getStream();
            if ($stream instanceof SocketStream) {
                $stream->setTimeout(15);
            }
            $t->start();
            $t->stop();

            return ['ok' => true, 'message' => 'SMTP berhasil login.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendly($e)];
        }
    }

    /** @return array{ok: bool, message: string} */
    public function imap(Account $a): array
    {
        if (! $a->imap_host) {
            return ['ok' => false, 'message' => 'Server IMAP belum diisi.'];
        }
        try {
            $client = (new ClientManager)->make([
                'host' => $a->imap_host, 'port' => (int) $a->imap_port, 'protocol' => 'imap',
                'encryption' => $a->imap_encryption === 'none' ? false : $a->imap_encryption,
                'validate_cert' => true, 'username' => $a->username, 'password' => (string) $a->password,
                'authentication' => null, 'timeout' => 20,
            ]);
            $client->connect();
            $inbox = $client->getFolder('INBOX');
            $count = $inbox ? ($inbox->examine()['exists'] ?? 0) : 0;
            $client->disconnect();

            return ['ok' => true, 'message' => 'IMAP berhasil login. Kotak masuk berisi '.number_format((int) $count, 0, ',', '.').' email.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $this->friendly($e)];
        }
    }

    public function friendly(Throwable $e): string
    {
        $m = $e->getMessage();
        if (preg_match('/authenticat|535|login|credential|AUTHENTICATIONFAILED|invalid/i', $m)) {
            return 'Login ditolak. Periksa username dan password.';
        }
        if (preg_match('/timed? ?out|connect|resolve|refused|unreachable|stream/i', $m)) {
            return 'Tidak bisa terhubung ke server. Periksa alamat server, port, dan jenis enkripsi.';
        }

        return 'Gagal: '.mb_substr(preg_replace('/\s+/', ' ', $m), 0, 200);
    }
}

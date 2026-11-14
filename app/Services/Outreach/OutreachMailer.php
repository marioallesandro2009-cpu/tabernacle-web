<?php

namespace App\Services\Outreach;

use App\Models\Outreach\Account;
use App\Models\Outreach\Contact;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** Sends one plain-text email through the SMTP server of one specific account. */
class OutreachMailer
{
    public function transport(Account $a): TransportInterface
    {
        $t = new EsmtpTransport($a->smtp_host, (int) $a->smtp_port, $a->smtp_encryption === 'ssl');
        if ($a->smtp_encryption === 'none') {
            $t->setAutoTls(false);
        }
        $t->setUsername($a->username);
        $t->setPassword((string) $a->password);
        $stream = $t->getStream();
        if ($stream instanceof SocketStream) {
            $stream->setTimeout(30);
        }

        return $t;
    }

    /**
     * @param  string  $messageId  without the angle brackets
     * @param  string|null  $threadRoot  Message-ID of the first email of the thread (follow-ups only)
     */
    public function send(Account $a, Contact $to, string $subject, string $body, string $messageId, string $unsubscribeUrl, ?string $threadRoot = null): void
    {
        $email = (new Email)
            ->from(new Address($a->email, $a->name))
            ->to(new Address((string) $to->email, (string) $to->name))
            ->subject($subject)
            ->text($body);
        $h = $email->getHeaders();
        $h->addIdHeader('Message-ID', $messageId);
        if ($threadRoot) {
            $h->addIdHeader('In-Reply-To', $threadRoot);
            $h->addIdHeader('References', $threadRoot);
        }
        // one-click unsubscribe in the mail client (RFC 8058)
        $h->addTextHeader('List-Unsubscribe', '<'.$unsubscribeUrl.'>');
        $h->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');

        $this->transport($a)->send($email);
    }

    /**
     * A reply written by a person (from the website or a personal mailbox): same account, same thread,
     * no unsubscribe footer or header.
     *
     * @param  array<int, string>  $references  Message-IDs without angle brackets, oldest first
     */
    public function sendReply(Account $a, Contact $to, string $subject, string $body, string $messageId, ?string $inReplyTo, array $references): void
    {
        $email = (new Email)
            ->from(new Address($a->email, $a->name))
            ->to(new Address((string) $to->email, (string) $to->name))
            ->subject($subject)
            ->text($body);
        $h = $email->getHeaders();
        $h->addIdHeader('Message-ID', $messageId);
        if ($inReplyTo) {
            $h->addIdHeader('In-Reply-To', $inReplyTo);
        }
        if ($references) {
            $h->addIdHeader('References', $references);
        }

        $this->transport($a)->send($email);
    }
}

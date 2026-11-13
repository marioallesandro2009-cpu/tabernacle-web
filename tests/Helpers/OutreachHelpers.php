<?php

use App\Jobs\Outreach\SendOutreachMessage;
use App\Models\Outreach\Account;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Message;
use App\Models\Outreach\Template;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\TemplateRenderer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;

/** Stands in for a real SMTP server: remembers what would have been sent, or fails on demand. */
class FakeSmtp implements TransportInterface
{
    /** @var array<int, RawMessage> */
    public static array $sent = [];

    public static ?Throwable $fail = null;

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (self::$fail) {
            throw self::$fail;
        }
        self::$sent[] = $message;

        return null;
    }

    public function __toString(): string
    {
        return 'fake://smtp';
    }
}

class FakeMailer extends OutreachMailer
{
    public function transport(Account $a): TransportInterface
    {
        return new FakeSmtp;
    }
}

function oAccount(array $o = []): Account
{
    static $n = 0;
    $n++;

    return Account::create($o + ['name' => "Pengirim $n", 'email' => "kirim$n@domain-kita.test", 'smtp_host' => 'smtp.domain-kita.test', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'imap_host' => 'imap.domain-kita.test', 'imap_port' => 993, 'imap_encryption' => 'ssl', 'username' => "kirim$n@domain-kita.test", 'password' => 'rahasia-uji', 'daily_limit' => 30, 'is_active' => true]);
}

function oContact(string $email, array $o = []): Contact
{
    return Contact::create($o + ['name' => ucfirst(strtok($email, '@')), 'email' => $email, 'organization' => 'GKI Contoh', 'title' => 'Ketua', 'validation_status' => 'valid']);
}

/** A campaign with the given templates as steps 1..n, at day offsets 0, 4, 10. */
function oCampaign(array $accounts, int $steps = 2, array $o = []): Campaign
{
    $c = Campaign::create($o + ['name' => 'Tur Turki', 'channel' => 'email', 'status' => 'draft', 'send_days' => [1, 2, 3, 4, 5], 'window_start' => '08:00', 'window_end' => '17:00', 'gap_min_seconds' => 60, 'gap_max_seconds' => 240]);
    $c->accounts()->sync(collect($accounts)->pluck('id')->all());
    foreach (array_slice([[1, 0, 'Halo {nama}'], [2, 4, 'Menyusul untuk {organisasi}'], [3, 10, 'Terakhir']], 0, $steps) as [$step, $offset, $subject]) {
        $t = Template::create(['name' => "Template $step", 'subject' => $subject, 'body' => "Salam, {nama} ({jabatan}) di {organisasi}.\n\n{nama_pengirim}"]);
        $c->steps()->create(['step' => $step, 'template_id' => $t->id, 'day_offset' => $offset]);
    }

    return $c;
}

function runJobs(): void
{
    foreach (Message::where('status', 'scheduled')->orderBy('scheduled_at')->get() as $m) {
        (new SendOutreachMessage($m->id))->handle(app(OutreachMailer::class), app(TemplateRenderer::class), app(CampaignScheduler::class));
    }
}

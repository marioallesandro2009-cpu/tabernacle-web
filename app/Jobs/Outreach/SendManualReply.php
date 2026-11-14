<?php

namespace App\Jobs\Outreach;

use App\Models\Outreach\Message;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\ThreadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one of our manual replies to the prospect, from the account that wrote to them, inside the
 * same thread. A failure is kept with its reason so the admin can press "Kirim ulang".
 */
class SendManualReply implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public int $messageId) {}

    public function handle(OutreachMailer $mailer, ThreadService $threads): void
    {
        $lock = Cache::lock('outreach:manual-reply:'.$this->messageId, 120);
        if (! $lock->get()) {
            return; // another worker has it
        }
        try {
            $m = Message::with(['campaignContact', 'account', 'contact'])->find($this->messageId);
            if (! $m || $m->kind !== Message::MANUAL || $m->reply_status !== 'queued') {
                return;
            }
            $cc = $m->campaignContact;
            $reason = match (true) {
                ! $cc || ! $m->contact => 'Percakapan atau kontaknya sudah tidak ada.',
                ! $m->account => 'Akun pengirim sudah dihapus.',
                ! $m->account->is_active => 'Akun pengirim sedang nonaktif.',
                blank($m->contact->email) => 'Kontak tidak punya alamat email.',
                trim((string) $m->body) === '' => 'Isi balasan kosong.',
                default => null,
            };
            if ($reason) {
                $this->finish($m, 'failed', $reason);

                return;
            }
            $h = $threads->headers($cc);
            $subject = $threads->subject($cc);
            $messageId = Str::uuid()->toString().'@'.(substr(strrchr($m->account->email, '@') ?: '@localhost', 1));
            try {
                $mailer->sendReply($m->account, $m->contact, $subject, rtrim((string) $m->body), $messageId, $h['in_reply_to'], $h['references']);
            } catch (Throwable $e) {
                $this->finish($m, 'failed', mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 450));

                return;
            }
            $m->forceFill(['subject' => $subject, 'message_id' => $messageId, 'sent_at' => now(), 'in_reply_to' => $h['in_reply_to'], 'references' => implode(' ', $h['references'])])->save();
            $this->finish($m, 'sent');
        } finally {
            $lock->release();
        }
    }

    protected function finish(Message $m, string $status, ?string $reason = null): void
    {
        $m->moveTo($status, $reason);
        if ($m->source === 'email' && $m->relay_from) {
            SendRelayConfirmation::dispatch($m->id);
        }
    }
}

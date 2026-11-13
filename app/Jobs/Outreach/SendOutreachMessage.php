<?php

namespace App\Jobs\Outreach;

use App\Models\Outreach\Account;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Message;
use App\Models\Outreach\Suppression;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\TemplateRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends ONE scheduled email. Everything is checked again right before sending, because minutes
 * may have passed since it was scheduled: the campaign could have been paused, the person could
 * have replied or opted out, or the sending hours could be over.
 */
class SendOutreachMessage implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public const MAX_FAILURES = 3;

    public function __construct(public int $messageId) {}

    public function handle(OutreachMailer $mailer, TemplateRenderer $renderer, CampaignScheduler $scheduler): void
    {
        // claim it, so two workers never send the same email
        if (! Message::whereKey($this->messageId)->where('status', 'scheduled')->update(['status' => 'sending'])) {
            return;
        }
        $message = Message::with(['campaignContact.contact', 'account'])->find($this->messageId);
        $cc = $message?->campaignContact;
        $campaign = $cc ? Campaign::with('steps.template')->find($cc->campaign_id) : null;
        $account = $message?->account;
        $contact = $cc?->contact;
        if (! $message || ! $cc || ! $campaign || ! $account || ! $contact) {
            $message?->update(['status' => 'cancelled', 'error' => 'Data tidak lengkap.']);

            return;
        }
        if ($reason = $this->blocker($message, $cc, $campaign, $account, $contact, $scheduler)) {
            $message->update(['status' => 'cancelled', 'error' => $reason]);

            return;
        }
        $template = $campaign->steps->firstWhere('step', $message->step)?->template;
        if (! $template || ! $template->is_active) {
            $message->update(['status' => 'cancelled', 'error' => 'Template untuk langkah ini tidak ada atau nonaktif.']);

            return;
        }
        [$subject, $body] = $renderer->render($template->subject, $template->body, $contact, $account);
        $messageId = Str::uuid()->toString().'@'.(substr(strrchr($account->email, '@') ?: '@localhost', 1));
        try {
            $mailer->send($account, $contact, $subject, $body, $messageId, $renderer->unsubscribeUrl($contact), $message->step > 1 ? $cc->root_message_id : null);
        } catch (Throwable $e) {
            $this->failed($message, $cc, $account, $contact, $e);

            return;
        }
        $this->sent($message, $cc, $campaign, $subject, $body, $messageId);
    }

    protected function blocker(Message $m, CampaignContact $cc, Campaign $campaign, Account $account, Contact $contact, CampaignScheduler $scheduler): ?string
    {
        return match (true) {
            $campaign->status !== 'running' => 'Kampanye sedang tidak berjalan.',
            ! $campaign->inWindow() => 'Di luar jam kirim.',
            ! in_array($cc->status, ['queued', 'sent'], true) => 'Kontak sudah tidak perlu dikirimi.',
            ! $contact->isSendable() => 'Email kontak tidak valid atau kontak berhenti.',
            $cc->current_step + 1 !== (int) $m->step => 'Langkah ini sudah terkirim atau berubah.',
            ! $account->is_active => 'Akun pengirim tidak aktif.',
            $scheduler->sentToday($account) > $account->daily_limit => 'Batas kirim harian akun tercapai.',
            default => null,
        };
    }

    protected function sent(Message $message, CampaignContact $cc, Campaign $campaign, string $subject, string $body, string $messageId): void
    {
        $step = (int) $message->step;
        $message->update(['status' => 'sent', 'sent_at' => now(), 'subject' => $subject, 'body' => $body, 'message_id' => $messageId, 'error' => null]);
        $first = $cc->first_sent_at ?? now();
        $next = $campaign->steps->where('template_id', '!=', null)->firstWhere('step', $step + 1);
        $cc->update([
            'status' => 'sent', 'current_step' => $step, 'first_sent_at' => $first, 'last_sent_at' => now(),
            'root_message_id' => $cc->root_message_id ?: $messageId,
            'next_send_at' => $next ? $first->copy()->addDays((int) $next->day_offset) : null,
        ]);
    }

    protected function failed(Message $message, CampaignContact $cc, Account $account, Contact $contact, Throwable $e): void
    {
        $text = mb_substr(preg_replace('/\s+/', ' ', $e->getMessage()) ?? '', 0, 450);
        $message->update(['status' => 'failed', 'error' => $text]);
        // the server says this mailbox does not exist: treat it as a bounce and never write again
        if (preg_match('/got code "55[0134]"/', $text) && preg_match('/user|mailbox|recipient|address|unknown|exist|invalid/i', $text)) {
            Suppression::add((string) $contact->email, 'bounced', $text);

            return;
        }
        // trouble with the account or the network: rest that account for a while instead of retrying every minute
        Cache::put('outreach:cooldown:'.$account->id, true, now()->addMinutes(15));
        if (Message::where('campaign_contact_id', $cc->id)->where('step', $message->step)->where('status', 'failed')->count() >= self::MAX_FAILURES) {
            $cc->update(['status' => 'failed', 'next_send_at' => null]);
        }
    }
}

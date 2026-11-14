<?php

namespace App\Jobs\Outreach;

use App\Mail\ForwardedReplyMail;
use App\Models\Outreach\Message;
use App\Services\Outreach\ThreadService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Forwards a prospect's real reply to the personal addresses set on the sender account. */
class ForwardReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $messageId) {}

    public function handle(ThreadService $threads): void
    {
        $reply = Message::with(['contact.segment', 'campaign', 'account', 'campaignContact'])->find($this->messageId);
        if (! $reply || $reply->direction !== 'in' || $reply->kind !== 'reply' || ! $reply->account || ! $reply->campaignContact) {
            return;
        }
        $to = $reply->account->forwardList();
        if (! $to) {
            return;
        }
        $token = $reply->campaignContact->token();
        $last = Message::where('campaign_contact_id', $reply->campaign_contact_id)->where('direction', 'out')
            ->where(fn ($q) => $q->where('status', 'sent')->orWhere('reply_status', 'sent'))
            ->where('sent_at', '<=', $reply->sent_at ?? now())->orderByDesc('sent_at')->orderByDesc('id')->first() ?? $threads->lastSent($reply->campaignContact);
        foreach ($to as $email) {
            try {
                Mail::to($email)->send(new ForwardedReplyMail($reply, $token, $last));
            } catch (\Throwable $e) {
                Log::error('Terusan balasan Outreach #'.$reply->id.' ke '.$email.' gagal: '.$e->getMessage());
            }
        }
    }
}

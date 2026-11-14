<?php

namespace App\Services\Outreach;

use App\Jobs\Outreach\SendManualReply;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Message;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * One conversation with one prospect (a campaign contact): every email we sent, every email they
 * sent back, and our manual replies, in order. Also builds the headers that keep a manual reply in
 * the same thread in the prospect's mailbox.
 */
class ThreadService
{
    /** @return Collection<int, Message> */
    public function messages(CampaignContact $cc): Collection
    {
        return Message::where('campaign_contact_id', $cc->id)
            ->where(fn ($q) => $q->where('kind', Message::MANUAL)
                ->orWhere(fn ($w) => $w->where('direction', 'in'))
                ->orWhere(fn ($w) => $w->where('direction', 'out')->where('status', 'sent')))
            ->with('user:id,name')->orderByRaw('coalesce(sent_at, created_at)')->orderBy('id')->get();
    }

    /** The last email that actually went out to the prospect (campaign email or manual reply). */
    public function lastSent(CampaignContact $cc): ?Message
    {
        return Message::where('campaign_contact_id', $cc->id)->where('direction', 'out')
            ->where(fn ($q) => $q->where('status', 'sent')->orWhere('reply_status', 'sent'))
            ->orderByDesc('sent_at')->orderByDesc('id')->first();
    }

    /** "Re: <first subject>", without piling up "Re: Re:". */
    public function subject(CampaignContact $cc): string
    {
        $first = Message::where('campaign_contact_id', $cc->id)->where('direction', 'out')->where('status', 'sent')->orderBy('sent_at')->value('subject')
            ?? Message::where('campaign_contact_id', $cc->id)->whereNotNull('subject')->orderBy('id')->value('subject') ?? '';
        $base = trim((string) preg_replace('/^\s*((re|fw|fwd|aw|balas)\s*:\s*)+/i', '', (string) $first));

        return 'Re: '.($base !== '' ? $base : 'Percakapan kita');
    }

    /**
     * In-Reply-To = the newest message of the thread that has a Message-ID; References = every
     * Message-ID of the thread, oldest first (the first email always kept, at most 20).
     *
     * @return array{in_reply_to: ?string, references: array<int, string>}
     */
    public function headers(CampaignContact $cc): array
    {
        $ids = Message::where('campaign_contact_id', $cc->id)->whereNotNull('message_id')
            ->where(fn ($q) => $q->where('direction', 'in')->orWhere('status', 'sent')->orWhere('reply_status', 'sent'))
            ->orderByRaw('coalesce(sent_at, created_at)')->orderBy('id')->pluck('message_id')
            ->map(fn ($id) => InboundMail::cleanId($id))->filter()->unique()->values();
        if ($cc->root_message_id && ! $ids->contains($cc->root_message_id)) {
            $ids->prepend($cc->root_message_id);
        }
        $refs = $ids->count() > 20 ? $ids->take(1)->merge($ids->slice(-19))->values() : $ids;

        return ['in_reply_to' => $ids->last(), 'references' => $refs->all()];
    }

    /** The website draft of this admin for this thread, created on first save. */
    public function saveDraft(CampaignContact $cc, User $user, string $body, ?int $draftId = null): Message
    {
        $draft = $draftId
            ? Message::where('campaign_contact_id', $cc->id)->where('kind', Message::MANUAL)->where('reply_status', 'draft')->findOrFail($draftId)
            : Message::where('campaign_contact_id', $cc->id)->where('kind', Message::MANUAL)->where('reply_status', 'draft')
                ->where('source', 'website')->where('user_id', $user->id)->latest('id')->first();
        if ($draft) {
            $draft->update(['body' => $body]);

            return $draft;
        }
        $draft = Message::create([
            'channel' => 'email', 'campaign_id' => $cc->campaign_id, 'campaign_contact_id' => $cc->id, 'contact_id' => $cc->contact_id,
            'account_id' => $cc->account_id, 'direction' => 'out', 'kind' => Message::MANUAL, 'status' => 'manual', 'source' => 'website',
            'user_id' => $user->id, 'subject' => $this->subject($cc), 'body' => $body,
        ]);
        $draft->moveTo('draft');

        return $draft;
    }

    /** Puts a draft (or a failed reply) in line to be sent by the queue. */
    public function queue(Message $m, ?User $user = null): void
    {
        if ($user && ! $m->user_id) {
            $m->update(['user_id' => $user->id]);
        }
        $m->moveTo('queued');
        SendManualReply::dispatch($m->id);
    }
}

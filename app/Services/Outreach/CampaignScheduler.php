<?php

namespace App\Services\Outreach;

use App\Jobs\Outreach\SendOutreachMessage;
use App\Models\Outreach\Account;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Runs every minute. For each running campaign inside its sending hours it hands every sender
 * account at most ONE next email, placed a random 1-4 minutes after that account's previous one,
 * and never beyond the account's daily limit. The email itself is sent later by the queue.
 */
class CampaignScheduler
{
    /** @return int number of emails scheduled */
    public function tick(?Carbon $now = null): int
    {
        $now ??= now();
        $this->releaseStuck($now);
        $scheduled = 0;
        foreach (Campaign::where('status', 'running')->with(['steps', 'accounts'])->get() as $campaign) {
            $this->dropDeadEnds($campaign);
            if ($campaign->inWindow($now)) {
                $scheduled += $this->scheduleFor($campaign, $now);
            }
            $this->completeIfDone($campaign);
        }

        return $scheduled;
    }

    protected function scheduleFor(Campaign $campaign, Carbon $now): int
    {
        $steps = $campaign->steps->whereNotNull('template_id')->values();
        if ($steps->isEmpty()) {
            return 0;
        }
        $count = 0;
        $accounts = $campaign->accounts->where('is_active', true)->reject(fn (Account $a) => Cache::has('outreach:cooldown:'.$a->id))
            ->sortBy(fn (Account $a) => $this->sentToday($a, $now))->values(); // the least used account goes first
        foreach ($accounts as $account) {
            if ($this->pending($account) || $this->sentToday($account, $now) >= $account->limitToday($now)) {
                continue;
            }
            $cc = $this->nextContact($campaign, $account, $steps->count(), $now);
            if (! $cc) {
                continue;
            }
            $at = $this->slot($account, $campaign, $now);
            $message = Message::create([
                'channel' => $campaign->channel, 'campaign_id' => $campaign->id, 'campaign_contact_id' => $cc->id, 'contact_id' => $cc->contact_id,
                'account_id' => $account->id, 'direction' => 'out', 'kind' => 'email', 'step' => $cc->current_step + 1,
                'status' => 'scheduled', 'scheduled_at' => $at,
            ]);
            SendOutreachMessage::dispatch($message->id)->delay($at);
            $count++;
        }

        return $count;
    }

    /** A follow-up that is due (from the same account), otherwise the next person not yet written to. */
    protected function nextContact(Campaign $campaign, Account $account, int $stepCount, Carbon $now): ?CampaignContact
    {
        $noPending = fn ($m) => $m->whereIn('status', ['scheduled', 'sending']);
        $followUp = CampaignContact::where('campaign_id', $campaign->id)->where('account_id', $account->id)->whereIn('status', ['sent', 'auto_replied'])
            ->where('current_step', '<', $stepCount)->where('next_send_at', '<=', $now)
            ->whereDoesntHave('messages', $noPending)->orderBy('next_send_at')->first();
        if ($followUp) {
            return $followUp;
        }
        $first = CampaignContact::where('campaign_id', $campaign->id)->where('status', 'queued')->where('current_step', 0)
            ->where(fn ($q) => $q->whereNull('account_id')->orWhere('account_id', $account->id))
            ->whereHas('contact', fn ($c) => $c->sendable())->whereDoesntHave('messages', $noPending)->orderBy('id')->first();
        $first?->update(['account_id' => $account->id]);

        return $first;
    }

    /** When this account may send next: a random gap after its previous email. */
    protected function slot(Account $account, Campaign $campaign, Carbon $now): Carbon
    {
        $last = Message::where('account_id', $account->id)->where('direction', 'out')->whereIn('status', ['scheduled', 'sending', 'sent'])->max('scheduled_at');
        $gap = random_int($campaign->gap_min_seconds, max($campaign->gap_min_seconds, $campaign->gap_max_seconds));
        $earliest = $last ? Carbon::parse($last)->addSeconds($gap) : $now->copy();

        return $earliest->greaterThan($now) ? $earliest : $now->copy();
    }

    /** Emails of this account counted against today's limit (Jakarta day). */
    public function sentToday(Account $account, ?Carbon $now = null): int
    {
        $day = ($now ?? now())->copy()->timezone(Campaign::TIMEZONE);

        return Message::where('account_id', $account->id)->where('direction', 'out')->whereIn('status', ['scheduled', 'sending', 'sent'])
            ->whereBetween('scheduled_at', [$day->copy()->startOfDay()->utc(), $day->copy()->endOfDay()->utc()])->count();
    }

    protected function pending(Account $account): bool
    {
        return Message::where('account_id', $account->id)->where('direction', 'out')->whereIn('status', ['scheduled', 'sending'])->exists();
    }

    /** A job that started but never finished (server restart) must not block the account for ever. */
    protected function releaseStuck(Carbon $now): void
    {
        Message::where('status', 'sending')->where('updated_at', '<', $now->copy()->subMinutes(10))
            ->update(['status' => 'failed', 'error' => 'Terhenti sebelum selesai, akan dicoba lagi.']);
    }

    /** People who became unreachable while waiting (address found bad, or opted out). */
    protected function dropDeadEnds(Campaign $campaign): void
    {
        // people promised to an account that was switched off or removed go back in the general line
        CampaignContact::where('campaign_id', $campaign->id)->where('status', 'queued')->where('current_step', 0)->whereNotNull('account_id')
            ->whereNotIn('account_id', $campaign->accounts->where('is_active', true)->pluck('id'))->update(['account_id' => null]);
        CampaignContact::where('campaign_id', $campaign->id)->where('status', 'queued')
            ->whereHas('contact', fn ($c) => $c->whereIn('validation_status', CampaignService::BAD)->orWhereNotNull('suppressed_at'))
            ->update(['status' => 'invalid']);
    }

    protected function completeIfDone(Campaign $campaign): void
    {
        $open = CampaignContact::where('campaign_id', $campaign->id)->where(function ($q) {
            $q->where('status', 'queued')->orWhere(fn ($w) => $w->whereIn('status', ['sent', 'auto_replied'])->whereNotNull('next_send_at'));
        })->exists();
        if (! $open && ! Message::where('campaign_id', $campaign->id)->whereIn('status', ['scheduled', 'sending'])->exists()) {
            $campaign->update(['status' => 'completed', 'completed_at' => now()]);
        }
    }
}

<?php

namespace App\Services\Outreach;

use App\Models\Lead;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\Message;
use App\Models\Outreach\Suppression;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** The numbers and recipients of the weekly Outreach summary email. */
class Digest
{
    /**
     * @return array{from: Carbon, to: Carbon, sent: int, replies: int, interested: int, bounces: int, hard_bounces: int, auto: int, unsubscribed: int, new_leads: int, running: int, segments: array<int, array{segment: string, sent: int, replies: int, bounces: int}>}
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $in = fn ($kind) => Message::where('direction', 'in')->where('kind', $kind)->whereBetween('sent_at', [$from, $to]);
        $segments = DB::table('outreach_messages')
            ->leftJoin('outreach_contacts as c', 'c.id', '=', 'outreach_messages.contact_id')
            ->leftJoin('outreach_segments as s', 's.id', '=', 'c.segment_id')
            ->whereBetween('outreach_messages.sent_at', [$from, $to])
            ->selectRaw("coalesce(s.name, 'Tanpa segmen') as segment,
                sum(case when outreach_messages.direction = 'out' and outreach_messages.status = 'sent' then 1 else 0 end) as sent,
                sum(case when outreach_messages.direction = 'in' and outreach_messages.kind = 'reply' then 1 else 0 end) as replies,
                sum(case when outreach_messages.direction = 'in' and outreach_messages.kind = 'bounce' then 1 else 0 end) as bounces")
            ->groupBy(DB::raw("coalesce(s.name, 'Tanpa segmen')"))->orderByDesc('sent')->get()
            ->map(fn ($r) => ['segment' => (string) $r->segment, 'sent' => (int) $r->sent, 'replies' => (int) $r->replies, 'bounces' => (int) $r->bounces])->all();

        return [
            'from' => $from, 'to' => $to,
            'sent' => Message::where('direction', 'out')->where('status', 'sent')->whereBetween('sent_at', [$from, $to])->count(),
            'replies' => $in('reply')->count(),
            'interested' => $in('reply')->where('label', 'interested')->count(),
            'bounces' => $in('bounce')->count(),
            'hard_bounces' => $in('bounce')->where('body', 'like', '[Permanen]%')->count(),
            'auto' => $in('auto_reply')->count(),
            'unsubscribed' => Suppression::where('reason', 'unsubscribed')->whereBetween('created_at', [$from, $to])->count(),
            'new_leads' => Lead::where('source', 'outreach')->whereBetween('created_at', [$from, $to])->count(),
            'running' => Campaign::where('status', 'running')->count(),
            'segments' => $segments,
        ];
    }

    /** Nothing happened and nothing is running: no point in emailing a page of zeros. @param array<string, mixed> $d */
    public function hasActivity(array $d): bool
    {
        return $d['running'] > 0 || ($d['sent'] + $d['replies'] + $d['bounces'] + $d['auto'] + $d['unsubscribed'] + $d['new_leads']) > 0;
    }

    /**
     * The addresses in the "ringkasan mingguan" setting (commas, semicolons or spaces between them),
     * or every active Super Admin when that setting is empty.
     *
     * @return array<int, string>
     */
    public function recipients(): array
    {
        $set = collect(preg_split('/[\s,;]+/', (string) setting('outreach_digest_email', '')) ?: [])
            ->map(fn ($e) => mb_strtolower(trim($e)))->filter(fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
        if ($set) {
            return $set;
        }

        return User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->where('is_active', true)->pluck('email')->filter()->map(fn ($e) => mb_strtolower($e))->unique()->values()->all();
    }
}

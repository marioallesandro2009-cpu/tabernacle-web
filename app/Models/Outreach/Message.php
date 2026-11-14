<?php

namespace App\Models\Outreach;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Every message of a campaign, sent (out) or received (in), on any channel. */
class Message extends Model
{
    protected $table = 'outreach_messages';

    protected $guarded = ['id'];

    protected $casts = ['scheduled_at' => 'datetime', 'sent_at' => 'datetime', 'status_log' => 'array'];

    /** A reply we wrote ourselves (from the website or a personal mailbox), not a campaign email. */
    public const MANUAL = 'reply_manual';

    /** Life of one of our manual replies, in the order it usually goes. */
    public const REPLY_STATUSES = [
        'draft' => 'Draf', 'received' => 'Diterima', 'rejected' => 'Ditolak', 'queued' => 'Antre kirim',
        'sent' => 'Terkirim', 'failed' => 'Gagal', 'bounced' => 'Bounce',
    ];

    public const SOURCES = ['website' => 'dari website', 'email' => 'dari email pribadi'];

    /** Moves a manual reply to a new status and remembers when (and why). */
    public function moveTo(string $status, ?string $reason = null): void
    {
        $log = $this->status_log ?? [];
        $log[] = array_filter(['status' => $status, 'at' => now()->toIso8601String(), 'reason' => $reason], fn ($v) => $v !== null);
        $this->forceFill(['reply_status' => $status, 'status_log' => $log, 'error' => $reason ? mb_substr($reason, 0, 500) : ($status === 'sent' ? null : $this->error)])->save();
    }

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /** @return BelongsTo<CampaignContact, $this> */
    public function campaignContact(): BelongsTo
    {
        return $this->belongsTo(CampaignContact::class, 'campaign_contact_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}

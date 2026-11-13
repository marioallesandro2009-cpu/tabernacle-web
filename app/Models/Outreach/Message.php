<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Every message of a campaign, sent (out) or received (in), on any channel. */
class Message extends Model
{
    protected $table = 'outreach_messages';

    protected $guarded = ['id'];

    protected $casts = ['scheduled_at' => 'datetime', 'sent_at' => 'datetime'];

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

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }
}

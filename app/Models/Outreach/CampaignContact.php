<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One contact inside one campaign: which step they are on and how it ended. */
class CampaignContact extends Model
{
    protected $table = 'outreach_campaign_contacts';

    protected $guarded = ['id'];

    protected $casts = ['next_send_at' => 'datetime', 'first_sent_at' => 'datetime', 'last_sent_at' => 'datetime'];

    /** Statuses after which no further email is ever sent. */
    public const STOPPED = ['replied', 'bounced', 'auto_replied', 'unsubscribed', 'invalid', 'failed'];

    /** @return BelongsTo<Campaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'campaign_contact_id');
    }
}

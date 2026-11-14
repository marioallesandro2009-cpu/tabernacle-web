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
    public const STOPPED = ['replied', 'bounced', 'unsubscribed', 'invalid', 'failed'];

    /** Letters and digits that cannot be mistaken for each other (no 0/O, 1/I/L). */
    public const TOKEN_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /** The thread's random code, used as [#CODE] in forwarded subjects. Made the first time it is needed. */
    public function token(): string
    {
        if (! $this->thread_token) {
            do {
                $token = '';
                for ($i = 0; $i < 10; $i++) {
                    $token .= self::TOKEN_ALPHABET[random_int(0, strlen(self::TOKEN_ALPHABET) - 1)];
                }
            } while (static::where('thread_token', $token)->exists());
            $this->forceFill(['thread_token' => $token])->save();
        }

        return (string) $this->thread_token;
    }

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

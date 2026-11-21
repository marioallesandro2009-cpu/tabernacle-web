<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Campaign extends Model
{
    public const TIMEZONE = 'Asia/Jakarta';

    protected $table = 'outreach_campaigns';

    protected $guarded = ['id'];

    protected $casts = ['send_days' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'allow_catch_all' => 'boolean', 'manual' => 'boolean'];

    /** @return BelongsTo<Segment, $this> */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }

    /** @return HasMany<CampaignStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(CampaignStep::class, 'campaign_id')->orderBy('step');
    }

    /** @return BelongsToMany<Account, $this> */
    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'outreach_campaign_accounts', 'campaign_id', 'account_id');
    }

    /** @return HasMany<CampaignContact, $this> */
    public function campaignContacts(): HasMany
    {
        return $this->hasMany(CampaignContact::class, 'campaign_id');
    }

    /** Is it a day and hour (Jakarta time) when this campaign may send? */
    public function inWindow(?Carbon $at = null): bool
    {
        $local = ($at ?? now())->copy()->timezone(self::TIMEZONE);
        if (! in_array($local->dayOfWeekIso, array_map('intval', $this->send_days ?? []), true)) {
            return false;
        }
        $hm = $local->format('H:i');

        return $hm >= $this->window_start && $hm < $this->window_end;
    }
}

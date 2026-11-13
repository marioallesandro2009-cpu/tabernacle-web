<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignStep extends Model
{
    public $timestamps = false;

    protected $table = 'outreach_campaign_steps';

    protected $guarded = ['id'];

    /** @return BelongsTo<Template, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }
}

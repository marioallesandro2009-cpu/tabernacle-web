<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read int|null $contacts_count
 * @property-read int|null $sendable_count
 */
class Segment extends Model
{
    protected $table = 'outreach_segments';

    protected $guarded = ['id'];

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'segment_id');
    }
}

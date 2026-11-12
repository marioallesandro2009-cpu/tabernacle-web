<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use LogsActivity;

    public const STATUSES = ['baru' => 'Baru', 'dihubungi' => 'Dihubungi', 'negosiasi' => 'Negosiasi', 'dp' => 'DP', 'lunas' => 'Lunas', 'batal' => 'Batal'];

    protected $guarded = ['id'];

    protected $casts = ['plan_date' => 'date'];

    /** @return BelongsTo<Tour, $this> */
    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<LeadEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class)->latest('id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A browser an admin signed in from successfully; it is recognised by a long-lived cookie. */
class TrustedDevice extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['last_used_at' => 'datetime', 'two_factor_until' => 'datetime', 'revoked_at' => 'datetime'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->updated_at?->gt(now()->subDays(90));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A temporary block after too many failed sign-ins: per IP, per account+IP, or per trusted device. */
class LoginBlock extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['blocked_until' => 'datetime', 'lifted_at' => 'datetime', 'created_at' => 'datetime', 'level' => 'integer'];

    public const SCOPES = ['ip' => 'Alamat IP', 'account_ip' => 'Akun + IP', 'device' => 'Perangkat tepercaya'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

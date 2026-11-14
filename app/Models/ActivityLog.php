<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log. Rows are only ever inserted: there is no route or admin screen that edits
 * or deletes them, and no secret (password, 2FA key, token) is ever written here.
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    /** Sign-in and account-security events, shown on the "Riwayat login" page. */
    public const AUTH_ACTIONS = [
        'login' => 'Masuk',
        'logout' => 'Keluar',
        'login_failed' => 'Gagal masuk (password salah)',
        'login_blocked' => 'Ditolak (akun nonaktif)',
        'login_throttled' => 'Diblokir sementara (terlalu banyak percobaan)',
        'two_factor_failed' => 'Kode 2FA salah',
        'forced_logout' => 'Dikeluarkan (akun dinonaktifkan)',
        'password_reset_requested' => 'Minta reset password',
        'password_reset' => 'Reset password lewat email',
        'password_changed' => 'Ganti password',
        'two_factor_enabled' => '2FA diaktifkan',
        'two_factor_disabled' => '2FA dimatikan',
        'reset_link_sent' => 'Tautan reset dikirim oleh super admin',
        'login_protected' => 'Mode terlindungi aktif (banyak percobaan gagal)',
        'login_protection_ended' => 'Mode terlindungi berakhir',
        'login_confirm_sent' => 'Link konfirmasi login dikirim',
        'login_confirmed' => 'Login dikonfirmasi lewat email',
        'sessions_ended' => 'Semua sesi diakhiri',
        'device_revoked' => 'Perangkat tepercaya dicabut',
        'block_lifted' => 'Blokir dibuka manual',
        'idle_logout' => 'Keluar otomatis (2 jam tidak aktif)',
    ];

    /** Actions that point at a possible attack or misuse. */
    public const WARNING_ACTIONS = ['login_failed', 'login_blocked', 'login_throttled', 'two_factor_failed', 'forced_logout', 'login_protected', 'sessions_ended'];

    /** Manual entry for actions that are not a single model save. */
    public static function record(string $action, string $type, ?int $id, string $label, ?array $changes = null): void
    {
        try {
            self::create([
                'user_id' => auth()->id(), 'action' => $action, 'subject_type' => $type, 'subject_id' => $id,
                'subject_label' => mb_substr($label, 0, 250), 'changes' => $changes, ...self::client(), 'created_at' => now(),
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * A sign-in / account-security event. $user is the account it concerns (may be null for an
     * unknown email); $label is what was typed (e.g. the email), never a password.
     */
    public static function auth(string $action, ?User $user, ?string $label = null, ?int $actorId = null): void
    {
        try {
            self::create([
                'user_id' => $actorId ?? $user?->id, 'action' => $action, 'subject_type' => 'Auth', 'subject_id' => $user?->id,
                'subject_label' => mb_substr((string) ($label ?? $user?->email), 0, 250), ...self::client(), 'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // logging must never block signing in or out
        }
    }

    /** @return array{ip: ?string, user_agent: ?string} */
    public static function client(): array
    {
        $r = request();

        return ['ip' => $r->ip(), 'user_agent' => $r->userAgent() ? mb_substr((string) $r->userAgent(), 0, 255) : null];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_id');
    }
}

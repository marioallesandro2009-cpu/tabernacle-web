<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** A mailbox on the company domain used to send (SMTP) and read replies (IMAP). */
class Account extends Model
{
    protected $table = 'outreach_accounts';

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'is_active' => 'boolean', 'warmup_enabled' => 'boolean', 'warmup_started_on' => 'date', 'smtp_ok' => 'boolean', 'imap_ok' => 'boolean',
        'smtp_tested_at' => 'datetime', 'imap_tested_at' => 'datetime', 'imap_polled_at' => 'datetime',
    ];

    /** Ready-made server settings, so a new account needs only the address and password. */
    public const PRESETS = [
        'hostinger' => ['label' => 'Hostinger', 'smtp_host' => 'smtp.hostinger.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'imap_host' => 'imap.hostinger.com', 'imap_port' => 993, 'imap_encryption' => 'ssl'],
        'godaddy' => ['label' => 'GoDaddy', 'smtp_host' => 'smtpout.secureserver.net', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'imap_host' => 'imap.secureserver.net', 'imap_port' => 993, 'imap_encryption' => 'ssl'],
    ];

    public const ENCRYPTIONS = ['ssl', 'tls', 'none'];

    /**
     * How many emails this account may send today. During warm-up the limit starts low and grows
     * every week (start, start + step, start + 2 × step, …) until it reaches daily_limit.
     */
    public function limitToday(?Carbon $now = null): int
    {
        if (! $this->warmup_enabled) {
            return (int) $this->daily_limit;
        }

        return min((int) $this->daily_limit, (int) $this->warmup_start + (int) $this->warmup_step * ($this->warmupWeek($now) - 1));
    }

    /** Week 1 is the week of the first email sent (Jakarta days); before any email it is week 1 too. */
    public function warmupWeek(?Carbon $now = null): int
    {
        if (! $this->warmup_started_on) {
            return 1;
        }
        $today = ($now ?? now())->copy()->timezone(Campaign::TIMEZONE)->startOfDay();
        $start = Carbon::parse($this->warmup_started_on->format('Y-m-d'), Campaign::TIMEZONE);

        return intdiv(max(0, (int) $start->diffInDays($today)), 7) + 1;
    }

    public function warmingUp(?Carbon $now = null): bool
    {
        return $this->warmup_enabled && $this->limitToday($now) < (int) $this->daily_limit;
    }
}

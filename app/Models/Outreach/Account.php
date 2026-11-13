<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Model;

/** A mailbox on the company domain used to send (SMTP) and read replies (IMAP). */
class Account extends Model
{
    protected $table = 'outreach_accounts';

    protected $guarded = ['id'];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'encrypted',
        'is_active' => 'boolean', 'smtp_ok' => 'boolean', 'imap_ok' => 'boolean',
        'smtp_tested_at' => 'datetime', 'imap_tested_at' => 'datetime', 'imap_polled_at' => 'datetime',
    ];

    /** Ready-made server settings, so a new account needs only the address and password. */
    public const PRESETS = [
        'hostinger' => ['label' => 'Hostinger', 'smtp_host' => 'smtp.hostinger.com', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'imap_host' => 'imap.hostinger.com', 'imap_port' => 993, 'imap_encryption' => 'ssl'],
        'godaddy' => ['label' => 'GoDaddy', 'smtp_host' => 'smtpout.secureserver.net', 'smtp_port' => 465, 'smtp_encryption' => 'ssl', 'imap_host' => 'imap.secureserver.net', 'imap_port' => 993, 'imap_encryption' => 'ssl'],
    ];

    public const ENCRYPTIONS = ['ssl', 'tls', 'none'];
}

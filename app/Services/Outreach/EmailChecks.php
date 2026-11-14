<?php

namespace App\Services\Outreach;

use Illuminate\Support\Facades\Storage;

/**
 * Two checks that need no network: role addresses (info@, sales@, noreply@ …), which are a warning
 * only, and throwaway email services, which are never mailed. The throwaway list ships with the code
 * and can be refreshed with `php artisan outreach:disposable-update`.
 */
class EmailChecks
{
    /** Local parts that belong to a function or a shared inbox, not to one person. */
    public const ROLE_NAMES = [
        'info', 'informasi', 'admin', 'administrator', 'sales', 'penjualan', 'marketing', 'pemasaran', 'cs', 'customerservice', 'customer.service',
        'customercare', 'care', 'support', 'help', 'helpdesk', 'service', 'services', 'layanan', 'noreply', 'no-reply', 'no_reply', 'donotreply',
        'do-not-reply', 'office', 'kantor', 'contact', 'contactus', 'kontak', 'hello', 'halo', 'hi', 'mail', 'email', 'enquiry', 'enquiries',
        'inquiry', 'inquiries', 'billing', 'finance', 'keuangan', 'accounting', 'invoice', 'hr', 'hrd', 'jobs', 'career', 'careers', 'karir',
        'recruitment', 'rekrutmen', 'webmaster', 'postmaster', 'hostmaster', 'abuse', 'security', 'privacy', 'legal', 'team', 'tim', 'staff',
        'humas', 'pr', 'press', 'media', 'sekretariat', 'secretary', 'sekretaris', 'reservation', 'reservations', 'reservasi', 'booking',
        'bookings', 'admisi', 'tata.usaha', 'tu', 'all', 'everyone', 'news', 'newsletter', 'notifications', 'notification', 'mailer-daemon',
    ];

    public const STORED_LIST = 'outreach/disposable_domains.txt';

    /** @var array<string, true>|null */
    protected ?array $disposable = null;

    public function isRole(?string $email): bool
    {
        $local = strtolower((string) strstr((string) $email, '@', true));

        return $local !== '' && in_array($local, self::ROLE_NAMES, true);
    }

    public function isDisposable(?string $email): bool
    {
        $domain = strtolower(trim((string) substr((string) strrchr((string) $email, '@'), 1)));
        if ($domain === '') {
            return false;
        }
        $list = $this->disposableList();
        // also covers subdomains such as "abc.mailinator.com"
        $parts = explode('.', $domain);
        for ($i = 0; $i < count($parts) - 1; $i++) {
            if (isset($list[implode('.', array_slice($parts, $i))])) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, true> */
    public function disposableList(): array
    {
        if ($this->disposable !== null) {
            return $this->disposable;
        }
        $text = Storage::disk('local')->exists(self::STORED_LIST)
            ? (string) Storage::disk('local')->get(self::STORED_LIST)
            : (string) @file_get_contents(resource_path('data/disposable_domains.txt'));

        return $this->disposable = self::parse($text);
    }

    /** @return array<string, true> */
    public static function parse(string $text): array
    {
        $out = [];
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = strtolower(trim($line));
            if ($line !== '' && $line[0] !== '#' && preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $line)) {
                $out[$line] = true;
            }
        }

        return $out;
    }
}

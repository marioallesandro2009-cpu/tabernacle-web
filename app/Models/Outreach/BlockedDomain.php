<?php

namespace App\Models\Outreach;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A domain no campaign may write to (a competitor, an existing client). Blocking "kompetitor.com"
 * also covers its subdomains, such as "mail.kompetitor.com".
 */
class BlockedDomain extends Model
{
    public $timestamps = false;

    protected $table = 'outreach_blocked_domains';

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public const REASON = 'blocked_domain';

    /** "Budi@Mail.Kompetitor.com" → "mail.kompetitor.com"; also accepts "@kompetitor.com" or a URL. */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('#^[a-z]+://#', '', $value);
        $value = str_contains($value, '@') ? substr((string) strrchr($value, '@'), 1) : $value;
        $value = explode('/', $value)[0];

        return trim((string) preg_replace('/^www\./', '', $value), '. ');
    }

    public static function isValidDomain(string $domain): bool
    {
        return (bool) preg_match('/^(?=.{3,190}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $domain);
    }

    /** @return array<int, string> */
    public static function domains(): array
    {
        return static::query()->pluck('domain')->all();
    }

    /** @param  array<int, string>|null  $domains  pass the list when checking many addresses */
    public static function blocks(?string $email, ?array $domains = null): bool
    {
        if (! $email || ! str_contains($email, '@')) {
            return false;
        }
        $host = static::normalize($email);
        foreach ($domains ?? static::domains() as $d) {
            if ($host === $d || str_ends_with($host, '.'.$d)) {
                return true;
            }
        }

        return false;
    }

    /** Blocks a domain: its contacts become "do not send" and their campaigns stop. */
    public static function block(string $domain, ?string $note = null): self
    {
        $row = static::firstOrCreate(['domain' => $domain], ['note' => $note, 'created_at' => now()]);
        $ids = static::matching($domain)->pluck('id');
        Contact::whereIn('id', $ids)->whereNull('suppressed_at')->update(['suppressed_at' => now(), 'suppressed_reason' => self::REASON]);
        CampaignContact::whereIn('contact_id', $ids)->whereIn('status', ['queued', 'sent', 'auto_replied'])->update(['status' => 'invalid', 'next_send_at' => null]);

        return $row;
    }

    /**
     * Unblocks a domain. Its contacts may be emailed again by NEW campaigns, unless they are on the
     * do-not-contact list for another reason (opted out, bounced) or another blocked domain covers them.
     */
    public function unblock(): void
    {
        $domain = $this->domain;
        $this->delete();
        static::matching($domain)->where('suppressed_reason', self::REASON)->get()->each(function (Contact $c) {
            if (! static::blocks($c->email) && ! Suppression::where('email', $c->email)->exists()) {
                $c->update(['suppressed_at' => null, 'suppressed_reason' => null]);
            }
        });
    }

    /** @return Builder<Contact> */
    protected static function matching(string $domain)
    {
        return Contact::query()->where(fn ($q) => $q->where('email', 'like', '%@'.$domain)->orWhere('email', 'like', '%.'.$domain));
    }
}

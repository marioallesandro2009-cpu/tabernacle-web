<?php

namespace App\Services\Outreach;

use Illuminate\Support\Facades\Cache;

/**
 * Checks an address before it is ever mailed: empty, malformed, or a domain that cannot receive
 * mail (no MX record). It never talks to the recipient's mail server, so nothing is "tested"
 * on real mailboxes. Domain answers are cached for a day.
 */
class EmailValidator
{
    /** @param (callable(string): (array<int, array<string, mixed>>|false))|null $resolver returns MX records, [] when none, false when DNS itself failed */
    public function __construct(protected $resolver = null)
    {
        $this->resolver ??= fn (string $domain) => @dns_get_record($domain.'.', DNS_MX);
    }

    /** @return array{0: string, 1: ?string} [status, note] where status is valid | empty | invalid_format | no_mx | pending */
    public function validate(?string $email): array
    {
        $email = trim((string) $email);
        if ($email === '') {
            return ['empty', 'Email kosong'];
        }
        if (mb_strlen($email) > 190 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! str_contains(explode('@', $email)[1] ?? '', '.')) {
            return ['invalid_format', 'Format email salah'];
        }
        $domain = strtolower(explode('@', $email)[1]);
        $has = $this->hasMx($domain);
        if ($has === null) {
            return ['pending', 'DNS belum menjawab, akan dicoba lagi'];
        }

        return $has ? ['valid', null] : ['no_mx', 'Domain tidak punya MX record'];
    }

    /** @return bool|null null when the DNS lookup itself failed (try again later) */
    protected function hasMx(string $domain): ?bool
    {
        $known = Cache::get('outreach:mx:'.$domain);
        if ($known !== null) {
            return $known === 1;
        }
        $records = ($this->resolver)($domain);
        if ($records === false) {
            return null;
        }
        $ok = collect($records)->contains(fn ($r) => ! empty($r['target']) && $r['target'] !== '.');
        Cache::put('outreach:mx:'.$domain, $ok ? 1 : 0, now()->addDay());

        return $ok;
    }
}

<?php

namespace App\Services;

use App\Mail\ProtectedModeMail;
use App\Models\ActivityLog;
use App\Models\LoginBlock;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sign-in protection that never locks the owner out (OWASP "device cookie" approach):
 *  - per IP: 5 failures in 15 minutes block that IP, 15 min → 30 min → 1 h … at most 24 h;
 *  - per account + IP: the same, for that combination only;
 *  - a trusted device (cookie from an earlier successful sign-in, 90 days) only has its own limit
 *    (10 failures in 15 minutes) and is never touched by the account-wide measures;
 *  - more than 20 failures in an hour on one account: no lock, but "protected" mode. Unknown
 *    devices then need Turnstile and a one-time link sent to the account's e-mail. The mode ends
 *    by itself after 24 hours without failures.
 */
class LoginGuard
{
    public const COOKIE = 'tw_device';

    public const DEVICE_DAYS = 90;

    public const WINDOW_SECONDS = 900;

    public const LIMITS = ['ip' => 5, 'account_ip' => 5, 'device' => 10];

    public const ANOMALY_PER_HOUR = 20;

    public const PROTECT_HOURS = 24;

    // --- trusted devices ---------------------------------------------------------------------

    /** The trusted device of this browser for this account, if its cookie is valid. */
    public function device(Request $request, ?User $user): ?TrustedDevice
    {
        $token = (string) $request->cookie(self::COOKIE, '');
        if (! $user || strlen($token) < 40) {
            return null;
        }

        return TrustedDevice::where('user_id', $user->id)->where('token_hash', hash('sha256', $token))->whereNull('revoked_at')
            ->where('updated_at', '>', now()->subDays(self::DEVICE_DAYS))->first();
    }

    /**
     * After a successful sign-in: refresh the trusted device of this browser, or make one and give
     * the browser its cookie (HttpOnly, Secure on HTTPS, SameSite=Strict, 90 days).
     *
     * @return array{0: TrustedDevice, 1: bool} [device, whether it is new]
     */
    public function trust(Request $request, User $user, ?TrustedDevice $device): array
    {
        $info = ['user_agent' => mb_substr((string) $request->userAgent(), 0, 255), 'ip_last' => $request->ip(), 'last_used_at' => now()];
        if ($device) {
            $device->fill($info)->touch();
            $device->save();

            return [$device, false];
        }
        $token = Str::random(64);
        $device = TrustedDevice::create($info + ['user_id' => $user->id, 'token_hash' => hash('sha256', $token)]);
        Cookie::queue(Cookie::make(self::COOKIE, $token, self::DEVICE_DAYS * 24 * 60, '/', null, $request->isSecure() || app()->isProduction(), true, false, 'strict'));

        return [$device, true];
    }

    // --- blocks ------------------------------------------------------------------------------

    public function blocked(string $scope, string $key): ?LoginBlock
    {
        return LoginBlock::where('scope', $scope)->where('key', $key)->whereNull('lifted_at')->where('blocked_until', '>', now())->latest('id')->first();
    }

    /** One failure on a counter; at the limit the counter becomes a block (longer each time). */
    public function fail(string $scope, string $key, ?User $user, ?string $ip): ?LoginBlock
    {
        $counter = 'login-fail|'.$scope.'|'.$key;
        $hits = (int) Cache::get($counter, 0) + 1;
        Cache::put($counter, $hits, self::WINDOW_SECONDS);
        if ($hits < self::LIMITS[$scope]) {
            return null;
        }
        Cache::forget($counter);
        $level = min(7, LoginBlock::where('scope', $scope)->where('key', $key)->whereNull('lifted_at')->where('created_at', '>', now()->subDay())->count());
        $minutes = min(15 * (2 ** $level), 24 * 60);
        $block = LoginBlock::create(['scope' => $scope, 'key' => $key, 'user_id' => $user?->id, 'ip' => $ip, 'level' => $level,
            'blocked_until' => now()->addMinutes($minutes), 'created_at' => now()]);
        ActivityLog::auth('login_throttled', $user, LoginBlock::SCOPES[$scope].' diblokir '.self::duration($minutes).($ip ? ' ('.$ip.')' : ''));

        return $block;
    }

    /** Key of the account + IP counter. It changes after a password reset, which resets the counter. */
    public function accountKey(?User $user, string $email, string $ip): string
    {
        $who = $user ? 'u'.$user->id.'g'.(int) Cache::get('login-gen|'.$user->id, 0) : 'e'.mb_strtolower($email);

        return $who.'|'.$ip;
    }

    public static function duration(int $minutes): string
    {
        return $minutes >= 60 ? ($minutes / 60).' jam' : $minutes.' menit';
    }

    // --- protected mode ----------------------------------------------------------------------

    public function isProtected(User $user): bool
    {
        if (! $user->protected_at) {
            return false;
        }
        $last = ActivityLog::where('action', 'login_failed')->where('subject_id', $user->id)->max('created_at');
        $quietSince = $last ? max(strtotime((string) $last), $user->protected_at->getTimestamp()) : $user->protected_at->getTimestamp();
        if ($quietSince < now()->subHours(self::PROTECT_HOURS)->getTimestamp()) {
            $user->forceFill(['protected_at' => null])->saveQuietly();
            ActivityLog::auth('login_protection_ended', $user, 'Mode terlindungi berakhir (24 jam tanpa percobaan gagal)');

            return false;
        }

        return true;
    }

    /** Many failures on one account from many places: switch on protected mode and warn the owner. */
    public function checkAnomaly(User $user): void
    {
        $failures = ActivityLog::where('action', 'login_failed')->where('subject_id', $user->id)->where('created_at', '>', now()->subHour())->count();
        if ($failures <= self::ANOMALY_PER_HOUR || $user->protected_at) {
            return;
        }
        $user->forceFill(['protected_at' => now()])->saveQuietly();
        ActivityLog::auth('login_protected', $user, "Mode terlindungi aktif: $failures percobaan gagal dalam 1 jam");
        try {
            Mail::to($user->email)->send(new ProtectedModeMail($user, $failures));
        } catch (\Throwable $e) {
            Log::error('Email peringatan mode terlindungi gagal: '.$e->getMessage());
        }
    }

    // --- after a password reset --------------------------------------------------------------

    /** Lifts every restriction of this account (and the IP the reset came from). */
    public function liftAll(User $user, ?string $ip): void
    {
        LoginBlock::whereNull('lifted_at')->where('blocked_until', '>', now())
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere(fn ($w) => $w->where('scope', 'ip')->where('key', (string) $ip))
                ->orWhere(fn ($w) => $w->where('scope', 'device')->whereIn('key', TrustedDevice::where('user_id', $user->id)->pluck('id')->map(fn ($id) => (string) $id))))
            ->update(['lifted_at' => now()]);
        Cache::increment('login-gen|'.$user->id);
        if ($ip) {
            Cache::forget('login-fail|ip|'.$ip);
        }
        $user->forceFill(['protected_at' => null])->saveQuietly();
    }

    /** Signs the account out everywhere: every session, the "remember me" cookie, and (optionally) every trusted device. */
    public function endAllSessions(User $user, bool $devicesToo): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        if ($devicesToo) {
            TrustedDevice::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        }
    }

    // --- Cloudflare Turnstile ----------------------------------------------------------------

    public static function turnstileEnabled(): bool
    {
        return filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'));
    }

    /** True when Turnstile is off, or Cloudflare confirms the token of this request. */
    public function turnstilePasses(Request $request): bool
    {
        if (! self::turnstileEnabled()) {
            return true;
        }
        $token = (string) $request->input('cf-turnstile-response', '');
        if ($token === '') {
            return false;
        }
        try {
            $res = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('services.turnstile.secret_key'), 'response' => $token, 'remoteip' => $request->ip(),
            ]);

            return (bool) $res->json('success');
        } catch (\Throwable) {
            return false;
        }
    }
}

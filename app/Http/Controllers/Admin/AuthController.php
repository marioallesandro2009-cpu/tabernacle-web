<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\LoginConfirmMail;
use App\Mail\NewLoginMail;
use App\Models\ActivityLog;
use App\Models\LoginConfirmation;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\LoginGuard;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/**
 * Signing in to the admin panel. The protection never locks the owner out: see LoginGuard.
 */
class AuthController extends Controller
{
    public const PASSWORD_MIN = 12;

    public function __construct(protected LoginGuard $guard) {}

    public function showLogin(): Response
    {
        return Inertia::render('Admin/Auth/Login', ['turnstile' => LoginGuard::turnstileEnabled() ? config('services.turnstile.site_key') : null]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $email = Str::lower($data['email']);
        $ip = (string) $request->ip();
        $user = User::where('email', $email)->first();
        $device = $this->guard->device($request, $user);
        $accountKey = $this->guard->accountKey($user, $email, $ip);

        if ($device) {
            // a trusted device only answers to its own limit
            if ($block = $this->guard->blocked('device', (string) $device->id)) {
                $this->refuse($user, $email, 'Perangkat ini diblokir sementara karena terlalu banyak percobaan. Coba lagi '.$block->blocked_until->locale('id')->diffForHumans().'.');
            }
        } else {
            if (! $this->guard->turnstilePasses($request)) {
                throw ValidationException::withMessages(['email' => 'Verifikasi keamanan (Turnstile) belum lolos. Coba lagi.']);
            }
            if ($block = $this->guard->blocked('ip', $ip) ?? $this->guard->blocked('account_ip', $accountKey)) {
                $this->refuse($user, $email, 'Terlalu banyak percobaan dari jaringan ini. Coba lagi '.$block->blocked_until->locale('id')->diffForHumans().'.');
            }
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            ActivityLog::auth('login_failed', $user, $email);
            if ($device) {
                $this->guard->fail('device', (string) $device->id, $user, $ip);
            } else {
                $this->guard->fail('ip', $ip, $user, $ip);
                $this->guard->fail('account_ip', $accountKey, $user, $ip);
                if ($user) {
                    $this->guard->checkAnomaly($user);
                }
            }
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }
        if (! $user->is_active) {
            ActivityLog::auth('login_blocked', $user);
            throw ValidationException::withMessages(['email' => 'Akun ini dinonaktifkan. Hubungi super admin.']);
        }

        // protected mode: an unknown device also needs the one-time link from the account's e-mail
        if (! $device && $this->guard->isProtected($user)) {
            $token = Str::random(64);
            LoginConfirmation::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'ip' => $ip, 'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'remember' => $request->boolean('remember'), 'expires_at' => now()->addMinutes(15), 'created_at' => now()]);
            try {
                Mail::to($user->email)->send(new LoginConfirmMail($user, admin_url('/login/confirm/'.$token), $ip, (string) $request->userAgent()));
            } catch (\Throwable $e) {
                Log::error('Email konfirmasi login gagal: '.$e->getMessage());
            }
            ActivityLog::auth('login_confirm_sent', $user, 'Link konfirmasi login dikirim (mode terlindungi)');

            return back()->with('success', 'Untuk keamanan, kami mengirim link konfirmasi ke email akun ini. Buka email itu dan klik linknya dalam 15 menit untuk menyelesaikan login.');
        }

        return $this->passed($request, $user, $request->boolean('remember'), $device);
    }

    /** The one-time link from the protected-mode e-mail. */
    public function confirm(Request $request, string $token): RedirectResponse
    {
        $c = LoginConfirmation::where('token_hash', hash('sha256', $token))->first();
        if (! $c || $c->used_at || $c->expires_at->isPast() || ! $c->user || ! $c->user->is_active) {
            return redirect(admin_path('/login'))->withErrors(['email' => 'Link konfirmasi tidak berlaku lagi (sudah dipakai atau lewat 15 menit). Silakan masuk lagi.']);
        }
        $c->update(['used_at' => now()]);
        ActivityLog::auth('login_confirmed', $c->user, 'Login dikonfirmasi lewat email');

        return $this->passed($request, $c->user, $c->remember, null);
    }

    /** Password (and, when needed, the e-mailed confirmation) passed: 2FA next, or straight in. */
    protected function passed(Request $request, User $user, bool $remember, ?TrustedDevice $device): RedirectResponse
    {
        if ($user->hasTwoFactor() && ! ($device && $device->two_factor_until?->isFuture())) {
            $request->session()->put('2fa.user', $user->id);
            $request->session()->put('2fa.remember', $remember);

            return redirect(admin_path('/two-factor'));
        }

        return $this->finish($request, $user, $remember, false);
    }

    public function showTwoFactor(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('2fa.user')) {
            return redirect(admin_path('/login'));
        }

        return Inertia::render('Admin/Auth/TwoFactor');
    }

    public function twoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string'], 'trust' => ['boolean']]);
        $user = User::find($request->session()->get('2fa.user'));
        if (! $user) {
            return redirect(admin_path('/login'));
        }
        $key = '2fa|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            ActivityLog::auth('login_throttled', $user, $user->email.' (2FA)');
            throw ValidationException::withMessages(['code' => 'Terlalu banyak percobaan. Tunggu sebentar.']);
        }
        if (! (new Google2FA)->verifyKey((string) $user->two_factor_secret, preg_replace('/\D/', '', $request->input('code')) ?: '000000')) {
            RateLimiter::hit($key, 60);
            ActivityLog::auth('two_factor_failed', $user);
            throw ValidationException::withMessages(['code' => 'Kode salah atau sudah kedaluwarsa.']);
        }
        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('2fa.remember', false);
        $request->session()->forget('2fa.user');

        return $this->finish($request, $user, $remember, $request->boolean('trust'));
    }

    protected function finish(Request $request, User $user, bool $remember, bool $trust2fa): RedirectResponse
    {
        $known = $this->guard->device($request, $user);
        $ipSeen = ActivityLog::where('action', 'login')->where('subject_id', $user->id)->where('ip', $request->ip())->exists();

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $request->session()->put('admin_last_seen', now()->getTimestamp());
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        [$device] = $this->guard->trust($request, $user, $known);
        if ($trust2fa) {
            $device->forceFill(['two_factor_until' => now()->addDays(30)])->save(); // "ingat perangkat ini 30 hari"
        }
        ActivityLog::auth('login', $user, $user->email.($user->hasTwoFactor() ? ' (dengan 2FA)' : '').($known ? '' : ' · perangkat baru'));

        if (! $known || ! $ipSeen) {
            try {
                Mail::to($user->email)->send(new NewLoginMail($user, (string) $request->ip(), (string) $request->userAgent(), ! $known, ! $ipSeen));
            } catch (\Throwable $e) {
                Log::error('Email login baru gagal: '.$e->getMessage());
            }
        }

        return redirect()->intended(admin_path());
    }

    public function logout(Request $request): RedirectResponse
    {
        ActivityLog::auth('logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(admin_path('/login'));
    }

    /** "Bukan saya: akhiri semua sesi" from the new-login e-mail (signed link, 7 days). */
    public function endSessions(Request $request, string $user): \Illuminate\Http\Response
    {
        $u = User::findOrFail($user);
        $this->guard->endAllSessions($u, false);
        ActivityLog::auth('sessions_ended', $u, 'Semua sesi diakhiri lewat link di email');

        return response()->view('admin.sessions-ended', ['email' => $u->email, 'forgot' => admin_url('/forgot-password')]);
    }

    /** @return never */
    protected function refuse(?User $user, string $email, string $message): void
    {
        ActivityLog::auth('login_throttled', $user, $email);
        throw ValidationException::withMessages(['email' => $message]);
    }

    public function showForgot(): Response
    {
        return Inertia::render('Admin/Auth/Forgot', ['turnstile' => LoginGuard::turnstileEnabled() ? config('services.turnstile.site_key') : null]);
    }

    public function forgot(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        if (! $this->guard->turnstilePasses($request)) {
            throw ValidationException::withMessages(['email' => 'Verifikasi keamanan (Turnstile) belum lolos. Coba lagi.']);
        }
        $key = 'forgot|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak permintaan. Coba lagi nanti.']);
        }
        RateLimiter::hit($key, 300);
        ActivityLog::auth('password_reset_requested', User::where('email', $request->input('email'))->first(), (string) $request->input('email'));
        // same answer whether the email exists or not
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Jika email terdaftar, tautan untuk mengatur ulang password sudah dikirim.');
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Admin/Auth/Reset', ['token' => $token, 'email' => $request->query('email'), 'min' => self::PASSWORD_MIN]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:'.self::PASSWORD_MIN, 'confirmed'],
            'end_everything' => ['boolean'],
        ], ['password.min' => 'Password minimal '.self::PASSWORD_MIN.' karakter.']);
        $everything = $request->boolean('end_everything');
        $ip = (string) $request->ip();
        $status = Password::reset($request->only(['email', 'password', 'password_confirmation', 'token']), function (User $user, string $password) use ($everything, $ip) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
            // a reset through the account's own e-mail lifts every restriction on the account
            $this->guard->liftAll($user, $ip);
            if ($everything) {
                $this->guard->endAllSessions($user, true);
            }
            ActivityLog::auth('password_reset', $user, $user->email.($everything ? ' · semua sesi dan perangkat tepercaya dicabut' : ''));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan tidak valid atau sudah kedaluwarsa.']);
        }

        return redirect(admin_path('/login'))->with('success', 'Password berhasil diubah dan semua pembatasan login untuk akun ini dibuka. Silakan masuk.');
    }
}

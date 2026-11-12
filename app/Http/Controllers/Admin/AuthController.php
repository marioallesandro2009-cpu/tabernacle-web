<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Admin/Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = Str::lower($data['email']).'|'.$request->ip();

        // 5 tries per minute per email and address
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $s = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['email' => "Terlalu banyak percobaan. Coba lagi dalam {$s} detik."]);
        }

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            ActivityLog::create(['user_id' => $user?->id, 'action' => 'login_failed', 'subject_label' => $data['email'], 'ip' => $request->ip(), 'created_at' => now()]);
            throw ValidationException::withMessages(['email' => 'Email atau password salah.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'Akun ini dinonaktifkan. Hubungi super admin.']);
        }
        RateLimiter::clear($key);

        if ($user->hasTwoFactor()) {
            $request->session()->put('2fa.user', $user->id);
            $request->session()->put('2fa.remember', $request->boolean('remember'));

            return redirect('/admin/two-factor');
        }

        return $this->finish($request, $user, $request->boolean('remember'));
    }

    public function showTwoFactor(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('2fa.user')) {
            return redirect('/admin/login');
        }

        return Inertia::render('Admin/Auth/TwoFactor');
    }

    public function twoFactor(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = User::find($request->session()->get('2fa.user'));
        if (! $user) {
            return redirect('/admin/login');
        }
        $key = '2fa|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['code' => 'Terlalu banyak percobaan. Tunggu sebentar.']);
        }
        if (! (new Google2FA)->verifyKey((string) $user->two_factor_secret, preg_replace('/\D/', '', $request->input('code')) ?: '000000')) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['code' => 'Kode salah atau sudah kedaluwarsa.']);
        }
        RateLimiter::clear($key);
        $remember = (bool) $request->session()->pull('2fa.remember', false);
        $request->session()->forget('2fa.user');

        return $this->finish($request, $user, $remember);
    }

    protected function finish(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        ActivityLog::create(['user_id' => $user->id, 'action' => 'login', 'subject_label' => $user->email, 'ip' => $request->ip(), 'created_at' => now()]);

        return redirect()->intended('/admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        ActivityLog::create(['user_id' => $request->user()?->id, 'action' => 'logout', 'subject_label' => $request->user()?->email, 'ip' => $request->ip(), 'created_at' => now()]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login');
    }

    public function showForgot(): Response
    {
        return Inertia::render('Admin/Auth/Forgot');
    }

    public function forgot(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $key = 'forgot|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak permintaan. Coba lagi nanti.']);
        }
        RateLimiter::hit($key, 300);
        // same answer whether the email exists or not
        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Jika email terdaftar, tautan untuk mengatur ulang password sudah dikirim.');
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Admin/Auth/Reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'], 'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Tautan tidak valid atau sudah kedaluwarsa.']);
        }

        return redirect('/admin/login')->with('success', 'Password berhasil diubah. Silakan masuk.');
    }
}

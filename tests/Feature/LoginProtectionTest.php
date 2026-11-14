<?php

use App\Mail\LoginConfirmMail;
use App\Mail\NewLoginMail;
use App\Mail\ProtectedModeMail;
use App\Models\ActivityLog;
use App\Models\LoginBlock;
use App\Models\LoginConfirmation;
use App\Models\Setting;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Services\LoginGuard;
use App\Support\Content;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    Cache::flush();
    Mail::fake();
});

afterEach(fn () => Carbon::setTestNow());

const PW = 'kata-sandi-pemilik-123';

function owner(array $o = []): User
{
    return adminUser('super_admin', $o + ['email' => 'pemilik@kita.test', 'password' => PW]);
}

/** Signs in from $ip (optionally with a device cookie), returns the response. */
function tryLogin(string $ip, string $password = PW, ?string $device = null, array $extra = [])
{
    $t = test()->withServerVariables(['REMOTE_ADDR' => $ip]);
    if ($device) {
        $t = $t->withCookie(LoginGuard::COOKIE, $device);
    }

    return $t->post('/admin/login', ['email' => 'pemilik@kita.test', 'password' => $password] + $extra);
}

/** First sign-in from the owner's laptop: returns the trusted-device cookie it got. */
function trustedLaptop(): string
{
    $r = tryLogin('10.0.0.1')->assertRedirect('/admin');
    $cookie = $r->getCookie(LoginGuard::COOKIE)?->getValue();
    expect($cookie)->toBeString()->and(strlen($cookie))->toBe(64);
    test()->post('/admin/logout');

    return $cookie;
}

// --- the three scenarios ---------------------------------------------------------------------

it('an attack from many IPs never locks the owner out on a trusted device', function () {
    $u = owner();
    $laptop = trustedLaptop();

    foreach (range(1, 25) as $i) {
        tryLogin("203.0.113.$i", 'tebakan-salah-'.$i)->assertSessionHasErrors('email');
    }
    expect($u->fresh()->protected_at)->not->toBeNull()
        ->and(ActivityLog::where('action', 'login_failed')->where('subject_id', $u->id)->count())->toBe(25);
    Mail::assertSent(ProtectedModeMail::class, fn ($m) => $m->hasTo('pemilik@kita.test'));
    Mail::assertSent(ProtectedModeMail::class, 1); // warned once, not 5 times

    // the owner's own laptop: straight in, no link, no lock
    tryLogin('10.0.0.1', PW, $laptop)->assertRedirect('/admin');
    $this->assertAuthenticatedAs($u);
    Mail::assertNotSent(LoginConfirmMail::class);
});

it('a new device gets in during protected mode through the one-time link in the e-mail', function () {
    $u = owner(['protected_at' => now()]);
    ActivityLog::auth('login_failed', $u, 'x'); // a recent failure keeps the mode on

    tryLogin('198.51.100.7')->assertSessionHas('success');
    $this->assertGuest();
    $url = null;
    Mail::assertSent(LoginConfirmMail::class, function (LoginConfirmMail $m) use (&$url) {
        $url = $m->url;

        return $m->hasTo('pemilik@kita.test');
    });
    expect($url)->toContain('/admin/login/confirm/');

    $this->get($url)->assertRedirect('/admin');
    $this->assertAuthenticatedAs($u);
    expect(TrustedDevice::where('user_id', $u->id)->count())->toBe(1);

    // the link works once only, and not after 15 minutes
    $this->post('/admin/logout');
    $this->get($url)->assertRedirect('/admin/login')->assertSessionHasErrors('email');
    tryLogin('198.51.100.8');
    $late = LoginConfirmation::latest('id')->first();
    $late->update(['expires_at' => now()->subMinute()]);
    expect(LoginConfirmation::count())->toBe(2);
    $this->assertGuest();
});

it('a password reset by e-mail lifts every restriction of the account', function () {
    $u = owner();
    $laptop = trustedLaptop();
    foreach (range(1, 6) as $i) {
        tryLogin('192.0.2.50', 'salah-'.$i);
    }
    tryLogin('192.0.2.50')->assertSessionHasErrors('email'); // blocked even with the right password
    $u->forceFill(['protected_at' => now()])->save();
    expect(LoginBlock::whereNull('lifted_at')->where('blocked_until', '>', now())->count())->toBeGreaterThan(0);

    $token = Password::createToken($u);
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])->post('/admin/reset-password', [
        'token' => $token, 'email' => $u->email, 'password' => 'sandi-baru-yang-panjang', 'password_confirmation' => 'sandi-baru-yang-panjang', 'end_everything' => true,
    ])->assertRedirect('/admin/login');

    expect($u->fresh()->protected_at)->toBeNull()
        ->and(LoginBlock::whereNull('lifted_at')->where('blocked_until', '>', now())->count())->toBe(0)
        ->and(TrustedDevice::where('user_id', $u->id)->whereNull('revoked_at')->count())->toBe(0); // "cabut semua perangkat"
    tryLogin('192.0.2.50', 'sandi-baru-yang-panjang', $laptop)->assertRedirect('/admin'); // the revoked cookie no longer counts, but the IP is free again
    $this->assertAuthenticatedAs($u);
});

// --- the limits ------------------------------------------------------------------------------

it('blocks an IP after 5 failures, longer each time, and records it', function () {
    owner();
    foreach (range(1, 5) as $i) {
        tryLogin('192.0.2.9', 'salah');
    }
    $b = LoginBlock::where('scope', 'ip')->where('key', '192.0.2.9')->first();
    expect($b->level)->toBe(0)->and((int) round(now()->diffInMinutes($b->blocked_until)))->toBe(15);
    tryLogin('192.0.2.9')->assertSessionHasErrors('email');
    $this->assertGuest();
    tryLogin('192.0.2.10')->assertRedirect('/admin'); // another network is not affected
    $this->post('/admin/logout');

    Carbon::setTestNow(now()->addMinutes(16));
    foreach (range(1, 5) as $i) {
        tryLogin('192.0.2.9', 'salah');
    }
    $second = LoginBlock::where('scope', 'ip')->where('key', '192.0.2.9')->latest('id')->first();
    expect($second->level)->toBe(1)->and((int) round(now()->diffInMinutes($second->blocked_until)))->toBe(30);
    expect(ActivityLog::where('action', 'login_throttled')->count())->toBeGreaterThanOrEqual(2);
});

it('limits a trusted device to 10 failures in 15 minutes, without touching other devices', function () {
    owner();
    $laptop = trustedLaptop();
    foreach (range(1, 10) as $i) {
        tryLogin('10.0.0.1', 'salah', $laptop);
    }
    tryLogin('10.0.0.1', PW, $laptop)->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(LoginBlock::where('scope', 'device')->count())->toBe(1)->and(LoginBlock::where('scope', 'ip')->count())->toBe(0);
});

it('asks Cloudflare Turnstile on unknown devices when the keys are set, not on trusted ones', function () {
    config(['services.turnstile.site_key' => 'site', 'services.turnstile.secret_key' => 'secret']);
    Http::fake(['challenges.cloudflare.com/*' => fn ($r) => Http::response(['success' => $r['response'] === 'token-baik'])]);
    owner();

    $this->get('/admin/login')->assertOk()->assertSee('"turnstile":"site"', false);
    tryLogin('10.1.1.1')->assertSessionHasErrors('email');
    tryLogin('10.1.1.1', PW, null, ['cf-turnstile-response' => 'token-palsu'])->assertSessionHasErrors('email');
    $r = tryLogin('10.1.1.1', PW, null, ['cf-turnstile-response' => 'token-baik'])->assertRedirect('/admin');
    $cookie = $r->getCookie(LoginGuard::COOKIE)->getValue();
    $this->post('/admin/logout');
    tryLogin('10.1.1.1', PW, $cookie)->assertRedirect('/admin'); // trusted device: no challenge

    $this->post('/admin/logout');
    $this->post('/admin/forgot-password', ['email' => 'pemilik@kita.test'])->assertSessionHasErrors('email');
});

// --- after signing in ------------------------------------------------------------------------

it('e-mails the owner on a sign-in from a new device or IP, with a link that ends every session', function () {
    $u = owner();
    $laptop = trustedLaptop();
    Mail::assertSent(NewLoginMail::class, 1); // the very first sign-in is from a new device

    tryLogin('10.0.0.1', PW, $laptop)->assertRedirect('/admin');
    $this->post('/admin/logout');
    Mail::assertSent(NewLoginMail::class, 1); // same laptop, same IP: no e-mail

    tryLogin('10.0.0.2', PW, $laptop)->assertRedirect('/admin');
    Mail::assertSent(NewLoginMail::class, 2);
    Mail::assertSent(NewLoginMail::class, fn ($m) => $m->newIp && ! $m->newDevice && str_contains($m->render(), '10.0.0.2'));

    config(['session.driver' => 'database']); // as on the server
    DB::table('sessions')->insert(['id' => 'sesi-lain', 'user_id' => $u->id, 'payload' => '', 'last_activity' => time()]);
    $this->get('/admin/sessions/end/'.$u->id)->assertForbidden(); // only through the signed link
    $this->get(URL::temporarySignedRoute('admin.sessions.end', now()->addDay(), ['user' => $u->id]))->assertOk()->assertSee('Semua sesi sudah diakhiri');
    expect(DB::table('sessions')->where('user_id', $u->id)->count())->toBe(0)->and(ActivityLog::where('action', 'sessions_ended')->exists())->toBeTrue();
});

it('signs an admin out after 2 hours without activity', function () {
    owner();
    tryLogin('10.0.0.1')->assertRedirect('/admin');
    $this->get('/admin')->assertOk();
    Carbon::setTestNow(now()->addMinutes(119));
    $this->get('/admin')->assertOk();
    Carbon::setTestNow(now()->addMinutes(121));
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->assertGuest();
    expect(ActivityLog::where('action', 'idle_logout')->exists())->toBeTrue();
});

it('can remember a device for 30 days for 2FA, and 2FA stays optional', function () {
    $g = new Google2FA;
    $secret = $g->generateSecretKey();
    owner(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);

    tryLogin('10.0.0.1')->assertRedirect('/admin/two-factor');
    $r = $this->post('/admin/two-factor', ['code' => $g->getCurrentOtp($secret), 'trust' => true])->assertRedirect('/admin');
    $laptop = $r->getCookie(LoginGuard::COOKIE)->getValue();
    expect(TrustedDevice::first()->two_factor_until->isFuture())->toBeTrue();
    $this->post('/admin/logout');

    // the same laptop within 30 days: no code asked
    tryLogin('10.0.0.1', PW, $laptop)->assertRedirect('/admin');
    $this->post('/admin/logout');
    // after 30 days the code is asked again
    TrustedDevice::first()->update(['two_factor_until' => now()->subDay()]);
    tryLogin('10.0.0.1', PW, $laptop)->assertRedirect('/admin/two-factor');
    $this->post('/admin/two-factor', ['code' => $g->getCurrentOtp($secret)])->assertRedirect('/admin');
    $this->post('/admin/logout');
    // a browser that is not trusted is always asked
    tryLogin('10.0.0.9')->assertRedirect('/admin/two-factor');
});

it('requires passwords of at least 12 characters', function () {
    $u = owner();
    $token = Password::createToken($u);
    $this->post('/admin/reset-password', ['token' => $token, 'email' => $u->email, 'password' => 'pendek12345', 'password_confirmation' => 'pendek12345'])->assertSessionHasErrors('password');
    $this->actingAs($u)->put('/admin/profile/password', ['current_password' => PW, 'password' => 'pendek12345', 'password_confirmation' => 'pendek12345'])->assertSessionHasErrors('password');
});

it('lists trusted devices and blocks in the sign-in history, and can revoke or lift them', function () {
    $u = owner();
    $laptop = trustedLaptop();
    foreach (range(1, 5) as $i) {
        tryLogin('192.0.2.77', 'salah');
    }
    $this->actingAs($u);
    $props = $this->get('/admin/logins')->assertOk()->viewData('page')['props'];
    expect($props['devices'])->toHaveCount(1)->and(collect($props['blocks'])->pluck('ip'))->toContain('192.0.2.77');

    $this->post('/admin/logins/devices/'.$props['devices'][0]['id'].'/revoke')->assertSessionHas('success');
    expect(TrustedDevice::first()->revoked_at)->not->toBeNull();
    $block = collect($props['blocks'])->firstWhere('active', true);
    $this->post('/admin/logins/blocks/'.$block['id'].'/lift')->assertSessionHas('success');
    expect(LoginBlock::find($block['id'])->lifted_at)->not->toBeNull();
    $this->actingAs(adminUser('admin', ['email' => 'staf@kita.test']))->post('/admin/logins/blocks/'.$block['id'].'/lift')->assertForbidden();
});

// --- headers -----------------------------------------------------------------------------------

it('sends security headers and a CSP whose nonce is on the inline and pasted tracking scripts', function () {
    Setting::updateOrCreate(['key' => 'tracking_head'], ['value' => json_encode('<script async src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script><script>gtag("config","G-TEST")</script>')]);
    Content::bump();
    Settings::forget();
    $r = $this->get('/');
    $csp = (string) $r->headers->get('Content-Security-Policy');
    preg_match("/'nonce-([^']+)'/", $csp, $m);
    expect($m[1] ?? '')->not->toBe('')
        ->and($csp)->toContain('https://www.googletagmanager.com')->toContain('https://connect.facebook.net')->toContain('https://www.youtube.com')->toContain('https://challenges.cloudflare.com')
        ->toContain("object-src 'none'")->toContain("frame-ancestors 'self'")
        ->and($r->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')->and($r->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($r->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
    $html = $r->getContent();
    expect(substr_count($html, 'nonce="'.$m[1].'"'))->toBeGreaterThanOrEqual(4)
        ->and($html)->toContain('<script nonce="'.$m[1].'" async src="https://www.googletagmanager.com/gtag/js?id=G-TEST">');

    config(['admin.csp' => 'report']);
    expect($this->get('/')->headers->has('Content-Security-Policy-Report-Only'))->toBeTrue();
    config(['admin.csp' => 'off']);
    expect($this->get('/')->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('sends HSTS on HTTPS', function () {
    expect($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');
});

<?php

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PragmaRX\Google2FA\Google2FA;

const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

function lastAuth(string $action): ?ActivityLog
{
    return ActivityLog::where('action', $action)->latest('id')->first();
}

it('records sign-in, sign-out and failed sign-in with IP and browser', function () {
    $u = adminUser('admin', ['password' => 'a-long-test-password']);
    $this->withHeaders(['User-Agent' => UA]);

    $this->post('/admin/login', ['email' => $u->email, 'password' => 'wrong-password-1'])->assertSessionHasErrors();
    $fail = lastAuth('login_failed');
    expect($fail->subject_id)->toBe($u->id)->and($fail->ip)->toBe('127.0.0.1')->and($fail->user_agent)->toContain('Chrome')
        ->and(json_encode($fail->toArray()))->not->toContain('wrong-password-1');

    $this->post('/admin/login', ['email' => 'nobody@example.test', 'password' => 'whatever-123'])->assertSessionHasErrors();
    expect(lastAuth('login_failed')->subject_id)->toBeNull()->and(lastAuth('login_failed')->subject_label)->toBe('nobody@example.test');

    $this->post('/admin/login', ['email' => $u->email, 'password' => 'a-long-test-password'])->assertRedirect('/admin');
    expect(lastAuth('login')->subject_id)->toBe($u->id);

    $this->post('/admin/logout')->assertRedirect('/admin/login');
    expect(lastAuth('logout')->subject_id)->toBe($u->id)->and(lastAuth('logout')->user_agent)->toContain('Chrome');
});

it('records blocked, throttled and wrong 2FA attempts', function () {
    $off = adminUser('admin', ['password' => 'a-long-test-password', 'is_active' => false]);
    $this->post('/admin/login', ['email' => $off->email, 'password' => 'a-long-test-password']);
    expect(lastAuth('login_blocked')->subject_id)->toBe($off->id);

    $u = adminUser('admin', ['password' => 'a-long-test-password']);
    foreach (range(1, 6) as $i) {
        $this->post('/admin/login', ['email' => $u->email, 'password' => 'nope-nope-nope']);
    }
    expect(lastAuth('login_throttled'))->not->toBeNull();

    $g = new Google2FA;
    $tfa = adminUser('admin', ['password' => 'a-long-test-password', 'two_factor_secret' => $g->generateSecretKey(), 'two_factor_confirmed_at' => now()]);
    // the failures above blocked that IP for 15 minutes; this sign-in comes from another network
    $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.9'])->post('/admin/login', ['email' => $tfa->email, 'password' => 'a-long-test-password'])->assertRedirect('/admin/two-factor');
    $this->post('/admin/two-factor', ['code' => '000000']);
    expect(lastAuth('two_factor_failed')->subject_id)->toBe($tfa->id);
});

it('records password and 2FA changes without storing secrets', function () {
    Notification::fake();
    $u = adminUser('admin', ['password' => 'a-long-test-password']);
    $this->actingAs($u)->put('/admin/profile/password', ['current_password' => 'a-long-test-password', 'password' => 'brand-new-password-9', 'password_confirmation' => 'brand-new-password-9'])->assertSessionHasNoErrors();
    expect(lastAuth('password_changed')->subject_id)->toBe($u->id);

    $this->post('/admin/profile/2fa');
    $secret = session('2fa.setup');
    $this->post('/admin/profile/2fa/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSessionHasNoErrors();
    expect(lastAuth('two_factor_enabled')->subject_id)->toBe($u->id);

    $all = json_encode(ActivityLog::all()->toArray());
    expect($all)->not->toContain('brand-new-password-9')->not->toContain($secret)->not->toContain('$2y$');

    $this->post('/admin/logout');
    $this->post('/admin/forgot-password', ['email' => $u->email]);
    expect(lastAuth('password_reset_requested')->subject_id)->toBe($u->id);
});

it('records when a deactivated admin is thrown out mid-session', function () {
    $u = adminUser('admin');
    $this->actingAs($u);
    $u->forceFill(['is_active' => false])->save();
    $this->get('/admin')->assertRedirect('/admin/login');
    expect(lastAuth('forced_logout')->subject_id)->toBe($u->id);
});

it('shows the login history to super admins only, with filters and CSV export', function () {
    $admin = adminUser('admin', ['password' => 'a-long-test-password']);
    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'a-long-test-password']);
    $this->post('/admin/logout');
    $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password-1']);

    $this->actingAs(adminUser('admin'))->get('/admin/logins')->assertForbidden();
    $this->actingAs(adminUser('editor'))->get('/admin/logins')->assertForbidden();

    $super = adminUser('super_admin');
    $this->actingAs($super);
    $this->get('/admin/logins')->assertOk()->assertSee($admin->email);
    $this->get('/admin/logins?action=warnings')->assertOk();
    $this->get('/admin/logins?user_id='.$admin->id.'&from='.now()->toDateString())->assertOk();
    $csv = $this->get('/admin/logins/export?user_id='.$admin->id);
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Masuk')->toContain('Gagal masuk')->toContain($admin->email);
});

it('offers no way to edit or delete audit entries', function () {
    // revoking a trusted device or lifting a block changes no audit entry (and is itself logged)
    $routes = collect(Route::getRoutes())->filter(fn ($r) => (str_contains($r->uri(), 'activity') || str_contains($r->uri(), 'logins')) && ! preg_match('#logins/(devices|blocks)/#', $r->uri()));
    expect($routes->flatMap(fn ($r) => $r->methods())->unique()->values()->all())->each->toBeIn(['GET', 'HEAD']);
});

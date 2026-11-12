<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(fn () => RateLimiter::clear('x'));

it('shows the login page and redirects guests away from the panel', function () {
    $this->get('/admin/login')->assertOk();
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->get('/admin/leads')->assertRedirect('/admin/login');
});

it('has no public registration', function () {
    $this->get('/register')->assertNotFound();
    $this->get('/admin/register')->assertNotFound();
    expect($this->post('/admin/register', ['email' => 'a@b.co'])->status())->toBeIn([404, 405]);
});

it('logs a user in with correct credentials and rejects wrong ones', function () {
    $u = adminUser('admin', ['password' => 'a-long-test-password']);
    $this->post('/admin/login', ['email' => $u->email, 'password' => 'wrong-password-123'])->assertSessionHasErrors('email');
    $this->assertGuest();
    $this->post('/admin/login', ['email' => $u->email, 'password' => 'a-long-test-password'])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($u);
});

it('blocks deactivated accounts', function () {
    $u = adminUser('admin', ['password' => 'a-long-test-password', 'is_active' => false]);
    $this->post('/admin/login', ['email' => $u->email, 'password' => 'a-long-test-password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('rate limits login to 5 attempts a minute', function () {
    $u = adminUser('admin', ['password' => 'a-long-test-password']);
    foreach (range(1, 5) as $i) {
        $this->post('/admin/login', ['email' => $u->email, 'password' => 'nope-nope-nope'])->assertSessionHasErrors('email');
    }
    // the correct password is refused while locked out
    $this->post('/admin/login', ['email' => $u->email, 'password' => 'a-long-test-password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('sends a password reset link by email for known accounts', function () {
    Notification::fake();
    $u = adminUser('admin');
    $this->post('/admin/forgot-password', ['email' => $u->email])->assertRedirect();
    Notification::assertSentTo($u, ResetPasswordNotification::class);
});

it('enforces roles: editor only gets blog and media', function () {
    $this->actingAs(adminUser('editor'));
    $this->get('/admin/posts')->assertOk();
    $this->get('/admin/media')->assertOk();
    foreach (['/admin/tours', '/admin/leads', '/admin/settings', '/admin/users', '/admin/pages', '/admin/backup', '/admin/activity', '/admin/redirects'] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('enforces roles: admin cannot manage accounts or system settings', function () {
    $this->actingAs(adminUser('admin'));
    foreach (['/admin/tours', '/admin/leads', '/admin/pages', '/admin/posts', '/admin/redirects'] as $url) {
        $this->get($url)->assertOk();
    }
    foreach (['/admin/users', '/admin/settings', '/admin/backup', '/admin/activity'] as $url) {
        $this->get($url)->assertForbidden();
    }
});

it('lets a super admin open everything', function () {
    $this->actingAs(adminUser('super_admin'));
    foreach (['/admin', '/admin/users', '/admin/settings', '/admin/backup', '/admin/activity', '/admin/recipients', '/admin/profile'] as $url) {
        $this->get($url)->assertOk();
    }
});

it('never lets a super admin delete or deactivate themselves, or the last super admin', function () {
    $me = adminUser('super_admin');
    $this->actingAs($me);
    $this->delete('/admin/users/'.$me->id)->assertSessionHas('error');
    expect(User::find($me->id))->not->toBeNull();
    $this->post('/admin/users/'.$me->id.'/toggle')->assertSessionHas('error');
    expect($me->fresh()->is_active)->toBeTrue();

    // a second super admin may be removed by the first
    $other = adminUser('super_admin');
    $this->delete('/admin/users/'.$other->id)->assertRedirect('/admin/users');
    expect(User::find($other->id))->toBeNull();
});

it('keeps at least one active super admin when demoting', function () {
    $me = adminUser('super_admin');
    $this->actingAs($me);
    $this->put('/admin/users/'.$me->id, ['name' => $me->name, 'email' => $me->email, 'role' => 'admin', 'is_active' => true, 'password' => ''])
        ->assertSessionHasErrors('role');
    expect($me->fresh()->isSuperAdmin())->toBeTrue();
});

it('creates an admin account with a hashed password', function () {
    $this->actingAs(adminUser('super_admin'));
    $this->post('/admin/users', ['name' => 'Staf Baru', 'email' => 'staf@example.test', 'role' => 'editor', 'password' => 'long-enough-pass', 'is_active' => true])->assertRedirect();
    $u = User::where('email', 'staf@example.test')->first();
    expect($u)->not->toBeNull()->and($u->password)->not->toBe('long-enough-pass')->and($u->hasRole('editor'))->toBeTrue();
    $this->post('/admin/users', ['name' => 'X', 'email' => 'x2@example.test', 'role' => 'editor', 'password' => 'short', 'is_active' => true])->assertSessionHasErrors('password');
});

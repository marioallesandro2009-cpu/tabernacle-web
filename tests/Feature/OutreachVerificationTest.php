<?php

require_once __DIR__.'/../Helpers/OutreachHelpers.php';

use App\Jobs\Outreach\ValidateContactsJob;
use App\Jobs\Outreach\VerifyContactsJob;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\CampaignContact;
use App\Models\Outreach\Contact;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Template;
use App\Models\Setting;
use App\Services\Outreach\CampaignScheduler;
use App\Services\Outreach\CampaignService;
use App\Services\Outreach\EmailChecks;
use App\Services\Outreach\EmailValidator;
use App\Services\Outreach\OutreachMailer;
use App\Services\Outreach\Verification\ReoonVerifier;
use App\Services\Outreach\Verification\VerificationUnavailable;
use App\Services\Outreach\Verification\VerifierFactory;
use App\Services\Outreach\Verification\ZeroBounceVerifier;
use App\Support\Content;
use App\Support\SecretSetting;
use App\Support\Settings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    FakeSmtp::$sent = [];
    FakeSmtp::$fail = null;
    Cache::flush();
    Settings::forget(); // settings are kept in memory between tests
    app()->bind(OutreachMailer::class, fn () => new FakeMailer);
    // every domain has a mail server, except one
    app()->bind(EmailValidator::class, fn () => new EmailValidator(fn (string $d) => $d === 'tanpa-mx.test' ? [] : [['target' => 'mx.'.$d]]));
    Carbon::setTestNow(Carbon::parse('2026-11-16 09:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

function setVerifier(string $provider, string $key = 'kunci-uji'): void
{
    Setting::updateOrCreate(['key' => 'verify_provider'], ['value' => json_encode($provider)]);
    Setting::updateOrCreate(['key' => 'verify_api_key'], ['value' => json_encode(SecretSetting::encrypt($key))]);
    Content::bump();
    Settings::forget();
}

/** Reoon answers by the address: the local part says what to answer. */
function fakeReoon(): void
{
    Http::fake(['emailverifier.reoon.com/*' => function (HttpRequest $r) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
        $status = match (strtok($q['email'], '@')) {
            'baik', 'info' => 'safe', 'semua' => 'catch_all', 'ragu' => 'unknown', 'mati' => 'invalid', default => 'safe'
        };

        return Http::response(['email' => $q['email'], 'status' => $status, 'is_disposable' => false]);
    }]);
}

// --- checks without network ------------------------------------------------------------------

it('flags role addresses and throwaway email services', function () {
    $c = new EmailChecks;
    expect($c->isRole('Info@gereja.test'))->toBeTrue()->and($c->isRole('sales@pt.test'))->toBeTrue()->and($c->isRole('noreply@x.test'))->toBeTrue()
        ->and($c->isRole('pdt.yosua@gereja.test'))->toBeFalse()->and($c->isRole(null))->toBeFalse()
        ->and($c->isDisposable('a@mailinator.com'))->toBeTrue()->and($c->isDisposable('a@sub.yopmail.com'))->toBeTrue()
        ->and($c->isDisposable('a@gmail.com'))->toBeFalse()->and($c->isDisposable('bukan'))->toBeFalse();
    expect(app(EmailValidator::class)->validate('orang@mailinator.com'))->toBe(['disposable', 'Layanan email sekali pakai']);
});

it('refreshes the throwaway list from a source and refuses a list that is too short', function () {
    Storage::fake('local');
    Http::fake(['raw.githubusercontent.com/*' => Http::response(implode("\n", array_map(fn ($i) => "buang$i.test", range(1, 600)))."\n# komentar\n")]);
    $this->artisan('outreach:disposable-update')->expectsOutputToContain('domain email sekali pakai disimpan')->assertSuccessful();
    expect((new EmailChecks)->isDisposable('x@buang42.test'))->toBeTrue()->and((new EmailChecks)->isDisposable('x@mailinator.com'))->toBeTrue(); // built-in kept

    Http::fake(['contoh.test/*' => Http::response("a.test\nb.test")]);
    $this->artisan('outreach:disposable-update', ['url' => 'https://contoh.test/daftar.txt'])->assertFailed();
});

// --- the verification services ----------------------------------------------------------------

it('reads ZeroBounce answers', function (array $answer, string $expected) {
    Http::fake(['api.zerobounce.net/*' => Http::response($answer)]);
    expect((new ZeroBounceVerifier('k'))->verify('a@b.test')['status'])->toBe($expected);
})->with([
    [['status' => 'valid', 'sub_status' => ''], 'valid'],
    [['status' => 'invalid', 'sub_status' => 'mailbox_not_found'], 'invalid'],
    [['status' => 'catch-all', 'sub_status' => ''], 'catch_all'],
    [['status' => 'unknown', 'sub_status' => 'timeout_exceeded'], 'unknown'],
    [['status' => 'do_not_mail', 'sub_status' => 'disposable'], 'disposable'],
    [['status' => 'do_not_mail', 'sub_status' => 'role_based'], 'valid'],
    [['status' => 'spamtrap', 'sub_status' => ''], 'invalid'],
]);

it('reads Reoon answers', function (array $answer, string $expected) {
    Http::fake(['emailverifier.reoon.com/*' => Http::response($answer)]);
    expect((new ReoonVerifier('k'))->verify('a@b.test')['status'])->toBe($expected);
})->with([
    [['status' => 'safe'], 'valid'], [['status' => 'role_account'], 'valid'], [['status' => 'invalid'], 'invalid'], [['status' => 'disabled'], 'invalid'],
    [['status' => 'catch_all'], 'catch_all'], [['status' => 'unknown'], 'unknown'], [['status' => 'inbox_full'], 'unknown'],
    [['status' => 'safe', 'is_disposable' => true], 'disposable'], [['status' => 'disposable'], 'disposable'],
]);

it('says when a service cannot answer (no credits, wrong key)', function () {
    Http::fake(['api.zerobounce.net/*' => Http::response(['error' => 'Invalid API Key or your account ran out of credits'])]);
    expect(fn () => (new ZeroBounceVerifier('salah'))->verify('a@b.test'))->toThrow(VerificationUnavailable::class, 'ran out of credits');
    Http::fake(['emailverifier.reoon.com/*' => Http::response(['status' => 'error', 'reason' => 'Not enough credits'], 402)]);
    expect(fn () => (new ReoonVerifier('salah'))->verify('a@b.test'))->toThrow(VerificationUnavailable::class);
});

// --- the API key in the settings ---------------------------------------------------------------

it('keeps the API key encrypted, never shows it, and keeps it when the field is left blank', function () {
    $this->actingAs(adminUser());
    $values = $this->get('/admin/settings')->assertOk()->viewData('page')['props']['values'];
    $settings = fn () => $this->get('/admin/settings')->viewData('page')['props'];
    expect($values['verify_api_key'])->toBe('');
    $payload = array_merge($values, ['site_name' => 'Uji', 'wa_number' => '628123', 'color_navy' => '#0b1a5c', 'color_navy_deep' => '#071040', 'color_amber' => '#c9a24a', 'color_amber_deep' => '#a8822e', 'color_cream' => '#faf7f0', 'color_teal' => '#1d6b6b']);
    $payload['verify_provider'] = 'reoon';
    $payload['verify_api_key'] = 'rahasia-reoon-123';
    $this->put('/admin/settings', $payload)->assertSessionHasNoErrors();

    $raw = json_decode((string) DB::table('settings')->where('key', 'verify_api_key')->value('value'), true);
    expect($raw)->not->toContain('rahasia-reoon-123')->and(SecretSetting::get('verify_api_key'))->toBe('rahasia-reoon-123');
    $props = $settings();
    expect($props['values']['verify_api_key'])->toBe('')->and(json_encode($props))->not->toContain('rahasia-reoon-123')->toContain('Sudah tersimpan');

    $payload['verify_api_key'] = '';
    $this->put('/admin/settings', $payload)->assertSessionHasNoErrors();
    expect(SecretSetting::get('verify_api_key'))->toBe('rahasia-reoon-123');
});

// --- verifying contacts -------------------------------------------------------------------------

it('verifies one contact, the ticked ones, or a whole segment through the queue', function () {
    setVerifier('reoon');
    fakeReoon();
    $this->actingAs(adminUser());
    $seg = Segment::create(['name' => 'Gereja']);
    $baik = oContact('baik@gereja.test', ['segment_id' => $seg->id]);
    $semua = oContact('semua@gereja.test', ['segment_id' => $seg->id]);
    $ragu = oContact('ragu@gereja.test');
    $info = oContact('info@gereja.test');
    $buang = oContact('x@yopmail.com');
    $nomx = oContact('a@tanpa-mx.test');

    $this->post('/admin/outreach/contacts/verify', ['contact_id' => $ragu->id])->assertSessionHas('success');
    expect($ragu->fresh()->verify_status)->toBe('unknown')->and($ragu->fresh()->verify_provider)->toBe('reoon');

    $this->post('/admin/outreach/contacts/verify', ['segment_id' => $seg->id])->assertSessionHas('success');
    expect($baik->fresh()->verify_status)->toBe('valid')->and($semua->fresh()->verify_status)->toBe('catch_all');

    $this->post('/admin/outreach/contacts/bulk', ['ids' => [$info->id, $buang->id, $nomx->id], 'action' => 'verify'])->assertSessionHas('success');
    expect($info->fresh()->is_role)->toBeTrue()->and($info->fresh()->verify_status)->toBe('valid')
        ->and($buang->fresh()->validation_status)->toBe('disposable')->and($buang->fresh()->verify_status)->toBe('disposable')
        ->and($nomx->fresh()->validation_status)->toBe('no_mx')->and($nomx->fresh()->verify_status)->toBeNull();
    Http::assertSentCount(4); // the throwaway and the no-MX address never reach the paid service

    $this->get('/admin/outreach/contacts?role=1')->assertOk()->assertSee('info@gereja.test')->assertDontSee('baik@gereja.test');
    $this->get('/admin/outreach/contacts?verify=catch_all')->assertOk()->assertSee('semua@gereja.test')->assertDontSee('baik@gereja.test');
    $this->get('/admin/outreach/contacts?verify=none')->assertOk()->assertSee('a@tanpa-mx.test')->assertDontSee('baik@gereja.test');
});

it('works without a service, and stops asking the service when it runs out of credits', function () {
    Http::fake(['emailverifier.reoon.com/*' => Http::response(['status' => 'error', 'reason' => 'Not enough credits'], 402)]);
    $a = oContact('satu@gereja.test');
    $b = oContact('dua@gereja.test');
    (new VerifyContactsJob([$a->id, $b->id]))->handle(app(EmailValidator::class), new EmailChecks, app(VerifierFactory::class));
    expect($a->fresh()->validation_status)->toBe('valid')->and($a->fresh()->verify_status)->toBeNull();
    Http::assertNothingSent(); // no service set

    setVerifier('reoon');
    (new VerifyContactsJob([$a->id, $b->id]))->handle(app(EmailValidator::class), new EmailChecks, app(VerifierFactory::class));
    Http::assertSentCount(1);
    expect(Cache::get('outreach:verify-error'))->toContain('Not enough credits')->and($b->fresh()->validation_status)->toBe('valid');
    $this->actingAs(adminUser())->get('/admin/outreach/contacts')->assertSee('Not enough credits');
});

it('verifies new contacts after an import only when "verify on import" is on', function () {
    setVerifier('reoon');
    fakeReoon();
    $c = Contact::create(['email' => 'baik@gereja.test', 'validation_status' => 'pending']);
    Queue::fake();
    (new ValidateContactsJob)->handle(app(EmailValidator::class), new EmailChecks, app(VerifierFactory::class));
    Queue::assertNotPushed(VerifyContactsJob::class);

    Setting::updateOrCreate(['key' => 'verify_on_import'], ['value' => json_encode(true)]);
    Content::bump();
    Settings::forget();
    Contact::whereKey($c->id)->update(['validation_status' => 'pending', 'validated_at' => null]);
    (new ValidateContactsJob)->handle(app(EmailValidator::class), new EmailChecks, app(VerifierFactory::class));
    Queue::assertPushed(VerifyContactsJob::class, fn ($j) => $j->ids === [$c->id]);
});

// --- what a campaign sends to ------------------------------------------------------------------

it('mails verified-valid and never-verified addresses, catch-all only when the campaign allows it', function () {
    Queue::fake();
    $a = oAccount(['daily_limit' => 100]);
    $c = oCampaign([$a], 1);
    oContact('belum@gereja.test');                                       // never verified: basic checks decide
    oContact('valid@gereja.test', ['verify_status' => 'valid']);
    oContact('catch@gereja.test', ['verify_status' => 'catch_all']);
    oContact('ragu@gereja.test', ['verify_status' => 'unknown']);
    oContact('mati@gereja.test', ['verify_status' => 'invalid']);
    app(CampaignService::class)->start($c);
    for ($i = 0; $i < 8; $i++) {
        app(CampaignScheduler::class)->tick();
        runJobs();
        Carbon::setTestNow(now()->addMinutes(5));
    }
    $to = collect(FakeSmtp::$sent)->map(fn ($m) => $m->getTo()[0]->getAddress())->sort()->values()->all();
    expect($to)->toBe(['belum@gereja.test', 'valid@gereja.test'])
        ->and(CampaignContact::whereHas('contact', fn ($q) => $q->where('email', 'catch@gereja.test'))->value('status'))->toBe('invalid');

    // a second campaign that allows catch-all
    FakeSmtp::$sent = [];
    $c2 = oCampaign([$a], 1, ['name' => 'Catch-all boleh', 'allow_catch_all' => true]);
    app(CampaignService::class)->start($c2);
    for ($i = 0; $i < 8; $i++) {
        app(CampaignScheduler::class)->tick();
        runJobs();
        Carbon::setTestNow(now()->addMinutes(5));
    }
    expect(collect(FakeSmtp::$sent)->map(fn ($m) => $m->getTo()[0]->getAddress())->sort()->values()->all())->toBe(['belum@gereja.test', 'catch@gereja.test', 'valid@gereja.test']);
});

it('stops the follow-ups of someone a later verification finds invalid', function () {
    setVerifier('reoon');
    fakeReoon();
    $a = oAccount();
    $c = oCampaign([$a], 2);
    $mati = oContact('mati@gereja.test');
    app(CampaignService::class)->start($c);
    app(CampaignScheduler::class)->tick();
    runJobs();
    expect(CampaignContact::first()->status)->toBe('sent');

    (new VerifyContactsJob([$mati->id]))->handle(app(EmailValidator::class), new EmailChecks, app(VerifierFactory::class));
    expect(CampaignContact::first()->status)->toBe('invalid')->and(CampaignContact::first()->next_send_at)->toBeNull();
});

it('saves the catch-all option of a campaign from the form', function () {
    $this->actingAs(adminUser());
    $a = oAccount();
    $t = Template::create(['name' => 'T', 'subject' => 'Halo', 'body' => 'Isi']);
    $this->post('/admin/outreach/campaigns', ['name' => 'C', 'account_ids' => [$a->id], 'window_start' => '08:00', 'window_end' => '17:00', 'send_days' => [1], 'gap_min' => 1, 'gap_max' => 4,
        'allow_catch_all' => true, 'steps' => [['template_id' => $t->id, 'day_offset' => 0], ['template_id' => null, 'day_offset' => 4], ['template_id' => null, 'day_offset' => 10]]])->assertSessionHasNoErrors();
    expect(Campaign::first()->allow_catch_all)->toBeTrue();
});

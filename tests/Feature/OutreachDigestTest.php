<?php

require_once __DIR__.'/../Helpers/OutreachHelpers.php';

use App\Mail\OutreachDigestMail;
use App\Models\Lead;
use App\Models\Outreach\Campaign;
use App\Models\Outreach\Message;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Suppression;
use App\Models\Setting;
use App\Services\Outreach\Digest;
use App\Support\Content;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-23 07:00', 'Asia/Jakarta')); // Monday 07:00 WIB
});

afterEach(fn () => Carbon::setTestNow());

function digestSetting(string $value): void
{
    Setting::updateOrCreate(['key' => 'outreach_digest_email'], ['value' => json_encode($value)]);
    Content::bump();
}

/** A week of activity: 3 emails (2 gereja, 1 perusahaan), a reply, a bounce, an unsubscribe and a lead. */
function weekOfActivity(): void
{
    $gereja = Segment::create(['name' => 'Gereja']);
    $pt = Segment::create(['name' => 'Perusahaan']);
    $a = oAccount();
    $when = now()->subDays(2);
    foreach ([['g1@x.test', $gereja], ['g2@x.test', $gereja], ['p1@x.test', $pt]] as [$email, $seg]) {
        $c = oContact($email, ['segment_id' => $seg->id]);
        Message::create(['campaign_id' => null, 'contact_id' => $c->id, 'account_id' => $a->id, 'direction' => 'out', 'kind' => 'email', 'status' => 'sent', 'sent_at' => $when, 'scheduled_at' => $when]);
    }
    $g1 = Message::first()->contact_id;
    Message::create(['contact_id' => $g1, 'account_id' => $a->id, 'direction' => 'in', 'kind' => 'reply', 'status' => 'received', 'label' => 'interested', 'sent_at' => $when]);
    Message::create(['contact_id' => Message::skip(2)->first()->contact_id, 'account_id' => $a->id, 'direction' => 'in', 'kind' => 'bounce', 'status' => 'received', 'body' => '[Permanen] Undelivered', 'sent_at' => $when]);
    Message::create(['contact_id' => $g1, 'account_id' => $a->id, 'direction' => 'in', 'kind' => 'auto_reply', 'status' => 'received', 'sent_at' => $when]);
    Suppression::create(['email' => 'pergi@x.test', 'reason' => 'unsubscribed', 'created_at' => $when]);
    Lead::create(['type' => 'form', 'name' => 'Budi', 'source' => 'outreach', 'status' => 'baru']);
    // older than a week: must not count
    Message::create(['contact_id' => $g1, 'account_id' => $a->id, 'direction' => 'out', 'kind' => 'email', 'status' => 'sent', 'sent_at' => now()->subDays(10), 'scheduled_at' => now()->subDays(10)]);
}

it('counts the last seven days, in total and per segment', function () {
    weekOfActivity();
    $d = app(Digest::class)->build(now()->subDays(7), now());

    expect($d)->toMatchArray(['sent' => 3, 'replies' => 1, 'interested' => 1, 'bounces' => 1, 'hard_bounces' => 1, 'auto' => 1, 'unsubscribed' => 1, 'new_leads' => 1, 'running' => 0]);
    $by = collect($d['segments'])->keyBy('segment');
    expect($by['Gereja'])->toMatchArray(['sent' => 2, 'replies' => 1, 'bounces' => 0])->and($by['Perusahaan'])->toMatchArray(['sent' => 1, 'replies' => 0, 'bounces' => 1]);
});

it('uses the addresses from the setting, or every active Super Admin when it is empty', function () {
    adminUser('super_admin', ['email' => 'owner@kita.test']);
    adminUser('super_admin', ['email' => 'off@kita.test', 'is_active' => false]);
    adminUser('admin', ['email' => 'staff@kita.test']);
    expect(app(Digest::class)->recipients())->toBe(['owner@kita.test']);

    digestSetting('boss@kita.test, Direktur@Kita.test;  bukan-email ; boss@kita.test');
    expect(app(Digest::class)->recipients())->toBe(['boss@kita.test', 'direktur@kita.test']);
});

it('emails the summary only when something happened, and says so when there are no recipients', function () {
    Mail::fake();
    digestSetting('boss@kita.test');

    $this->artisan('outreach:weekly-digest')->expectsOutputToContain('tidak dikirim')->assertSuccessful();
    Mail::assertNothingSent();

    weekOfActivity();
    $this->artisan('outreach:weekly-digest')->expectsOutputToContain('boss@kita.test')->assertSuccessful();
    Mail::assertSent(OutreachDigestMail::class, function (OutreachDigestMail $m) {
        $html = $m->render();

        return $m->hasTo('boss@kita.test') && str_contains($m->envelope()->subject, 'Ringkasan Outreach mingguan: 16 Nov – 23 Nov 2026')
            && str_contains($html, 'Gereja') && str_contains($html, 'Perusahaan') && str_contains($html, 'Email terkirim') && str_contains($html, '/admin/outreach/replies');
    });

    // a running campaign is worth a summary even in a quiet week
    Mail::fake();
    Message::query()->delete();
    Suppression::query()->delete();
    Lead::query()->delete();
    Campaign::create(['name' => 'Jalan', 'status' => 'running', 'send_days' => [1], 'window_start' => '08:00', 'window_end' => '17:00']);
    $this->artisan('outreach:weekly-digest')->assertSuccessful();
    Mail::assertSent(OutreachDigestMail::class, 1);

    // nobody to send to
    Mail::fake();
    digestSetting('');
    $this->artisan('outreach:weekly-digest')->expectsOutputToContain('Tidak ada penerima')->assertSuccessful();
    Mail::assertNothingSent();
});

it('is scheduled for Monday 07:00 Jakarta time and the setting can be edited in the admin', function () {
    $e = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->description, 'outreach:weekly-digest'));
    expect($e->expression)->toBe('0 7 * * 1')->and($e->timezone)->toBe('Asia/Jakarta');

    $this->actingAs(adminUser());
    $this->get('/admin/settings')->assertOk()->assertSee('outreach_digest_email');
});

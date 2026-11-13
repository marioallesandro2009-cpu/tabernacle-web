<?php

use App\Models\Outreach\Account;
use App\Models\Outreach\Contact;
use App\Models\Outreach\ContactImport;
use App\Models\Outreach\Segment;
use App\Models\Outreach\Suppression;
use App\Services\Outreach\ConnectionTester;
use App\Services\Outreach\ContactImporter;
use App\Services\Outreach\EmailValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** DNS is never queried in tests: every domain has mail except "tanpa-mx.example". */
function fakeDns(): void
{
    Cache::flush();
    app()->bind(EmailValidator::class, fn () => new EmailValidator(fn (string $d) => $d === 'tanpa-mx.example' ? [] : [['target' => 'mx.'.$d]]));
}

function accountPayload(array $over = []): array
{
    return array_merge([
        'name' => 'Tim Tabernacle', 'email' => 'outreach@contoh.test', 'smtp_host' => 'smtp.contoh.test', 'smtp_port' => 465, 'smtp_encryption' => 'ssl',
        'imap_host' => 'imap.contoh.test', 'imap_port' => 993, 'imap_encryption' => 'ssl', 'username' => 'outreach@contoh.test',
        'password' => 'rahasia-uji-123', 'daily_limit' => 30, 'is_active' => true,
    ], $over);
}

// --- who may open the module -------------------------------------------------------------

it('lets Super Admin and Admin in, and keeps Editors out', function () {
    $this->actingAs(adminUser('super_admin'))->get('/admin/outreach/contacts')->assertOk();
    $this->actingAs(adminUser('admin', ['email' => 'admin2@example.test']))->get('/admin/outreach/accounts')->assertOk();
    auth()->logout();
    $this->actingAs(adminUser('editor', ['email' => 'editor@example.test']))->get('/admin/outreach/contacts')->assertForbidden();
});

// --- email checks ------------------------------------------------------------------------

it('classifies addresses: empty, malformed, no mail server, fine, and DNS trouble', function () {
    $v = new EmailValidator(fn (string $d) => match ($d) {
        'tanpa-mx.example' => [], 'dns-error.example' => false, 'null-mx.example' => [['target' => '.']], default => [['target' => 'mx.'.$d]],
    });
    Cache::flush();
    expect($v->validate('')[0])->toBe('empty')
        ->and($v->validate(null)[0])->toBe('empty')
        ->and($v->validate('bukan email')[0])->toBe('invalid_format')
        ->and($v->validate('a@b')[0])->toBe('invalid_format')
        ->and($v->validate('a@tanpa-mx.example')[0])->toBe('no_mx')
        ->and($v->validate('a@null-mx.example')[0])->toBe('no_mx')
        ->and($v->validate('a@dns-error.example')[0])->toBe('pending')
        ->and($v->validate('orang@gereja.example')[0])->toBe('valid');
});

// --- sender accounts ---------------------------------------------------------------------

it('keeps the account password encrypted and never sends it to the browser', function () {
    $this->actingAs(adminUser());
    $this->post('/admin/outreach/accounts', accountPayload())->assertRedirect();
    $a = Account::first();
    expect($a->password)->toBe('rahasia-uji-123')
        ->and(DB::table('outreach_accounts')->value('password'))->not->toContain('rahasia-uji-123');

    $page = $this->get('/admin/outreach/accounts')->assertOk();
    expect($page->getContent())->not->toContain('rahasia-uji-123');

    // a blank password on edit keeps the saved one
    $this->put('/admin/outreach/accounts/'.$a->id, accountPayload(['password' => '', 'daily_limit' => 20]))->assertRedirect();
    expect($a->fresh()->password)->toBe('rahasia-uji-123')->and($a->fresh()->daily_limit)->toBe(20);
    $this->put('/admin/outreach/accounts/'.$a->id, accountPayload(['password' => 'baru-uji-456']))->assertRedirect();
    expect($a->fresh()->password)->toBe('baru-uji-456');
});

it('validates the account form', function () {
    $this->actingAs(adminUser());
    $this->post('/admin/outreach/accounts', accountPayload(['email' => 'salah', 'smtp_port' => 99999, 'password' => '']))->assertSessionHasErrors(['email', 'smtp_port', 'password']);
    $this->post('/admin/outreach/accounts', accountPayload())->assertSessionHasNoErrors();
    $this->post('/admin/outreach/accounts', accountPayload())->assertSessionHasErrors('email'); // same address twice
});

it('tests the connection and remembers the result', function () {
    $this->actingAs(adminUser());
    $this->post('/admin/outreach/accounts', accountPayload());
    $a = Account::first();
    app()->bind(ConnectionTester::class, fn () => new class extends ConnectionTester
    {
        public function smtp(Account $a): array
        {
            return ['ok' => true, 'message' => 'ok'];
        }

        public function imap(Account $a): array
        {
            return ['ok' => false, 'message' => 'Login ditolak. Periksa username dan password.'];
        }
    });
    $this->post('/admin/outreach/accounts/'.$a->id.'/test')->assertRedirect()->assertSessionHas('error');
    $a->refresh();
    expect($a->smtp_ok)->toBeTrue()->and($a->imap_ok)->toBeFalse()->and($a->imap_error)->toContain('Login ditolak')->and($a->smtp_tested_at)->not->toBeNull();
});

// --- importing contacts ------------------------------------------------------------------

it('guesses the column mapping from Indonesian and English headers', function () {
    $g = (new ContactImporter)->guess(['No', 'Nama Lengkap', 'Jabatan', 'Nama Gereja', 'E-mail', 'No HP', 'Kategori', 'Keterangan']);
    expect($g)->toMatchArray(['name' => 1, 'title' => 2, 'organization' => 3, 'email' => 4, 'phone' => 5, 'segment' => 6, 'notes' => 7]);
});

it('imports a semicolon CSV: maps columns, skips duplicates, keeps empties, honours the do-not-contact list', function () {
    fakeDns();
    Storage::fake('local');
    Suppression::add('berhenti@gereja.example', 'unsubscribed');
    Contact::create(['name' => 'Sudah Ada', 'email' => 'ada@gereja.example']);

    $csv = "Nama;Jabatan;Gereja;Email;HP;Kategori;Catatan\n"
        ."Budi;Pendeta;GKI Sejahtera;Budi@Gereja.example;081234567;Gereja;pernah ikut tur\n"
        ."Sari;Sekretaris;PT Maju;sari@maju.example;;Perusahaan;\n"
        ."Dobel;Ketua;GKI Sejahtera;budi@gereja.example;;Gereja;\n"          // same address as Budi, other case
        ."Ada;Ketua;GKI;ada@gereja.example;;Gereja;\n"                        // already a contact
        ."Tanpa Email;Bendahara;GBI;;0812999;Gereja;\n"
        ."Ngawur;Ketua;PT Ngawur;orang@tanpa-mx.example;;Perusahaan;\n"
        ."Salah;Ketua;PT Salah;bukan-email;;Perusahaan;\n"
        ."Berhenti;Ketua;GKI;berhenti@gereja.example;;Gereja;\n"
        .";;;;;;\n"; // blank row
    $this->actingAs(adminUser());
    $res = $this->post('/admin/outreach/import', ['file' => UploadedFile::fake()->createWithContent('kontak.csv', $csv)]);
    $import = ContactImport::first();
    $res->assertRedirect('/admin/outreach/import/'.$import->id.'/mapping');

    $this->get('/admin/outreach/import/'.$import->id.'/mapping')->assertOk()->assertInertia(fn ($p) => $p
        ->component('Admin/Outreach/ImportMapping')->where('headers.3', 'Email')->where('guess.organization', 2)->where('guess.segment', 5)->has('rows', 8));

    $segmentId = Segment::create(['name' => 'Umum'])->id;
    $this->post('/admin/outreach/import/'.$import->id.'/run', ['mapping' => ['name' => 0, 'title' => 1, 'organization' => 2, 'email' => 3, 'phone' => 4, 'segment' => 5, 'notes' => 6], 'default_segment_id' => $segmentId])
        ->assertRedirect('/admin/outreach/import/'.$import->id);

    $import->refresh();
    expect($import->status)->toBe('done')->and($import->total_rows)->toBe(8)->and($import->created_count)->toBe(6)
        ->and($import->duplicate_count)->toBe(2)->and($import->empty_count)->toBe(1)->and($import->suppressed_count)->toBe(1);
    Storage::disk('local')->assertMissing($import->path); // the uploaded list is deleted after reading

    $by = fn (string $email) => Contact::where('email', $email)->first();
    expect($by('budi@gereja.example')->validation_status)->toBe('valid')
        ->and($by('budi@gereja.example')->organization)->toBe('GKI Sejahtera')
        ->and($by('budi@gereja.example')->segment->name)->toBe('Gereja')      // taken from the column
        ->and($by('sari@maju.example')->segment->name)->toBe('Perusahaan')
        ->and($by('orang@tanpa-mx.example')->validation_status)->toBe('no_mx')
        ->and($by('bukan-email')->validation_status)->toBe('invalid_format')
        ->and($by('berhenti@gereja.example')->suppressed_reason)->toBe('unsubscribed')
        ->and(Contact::whereNull('email')->first()->validation_status)->toBe('empty')
        ->and(Segment::whereIn('name', ['Gereja', 'Perusahaan'])->count())->toBe(2);

    // only valid, not-suppressed contacts may be emailed
    expect(Contact::sendable()->pluck('email')->sort()->values()->all())->toBe(['ada@gereja.example', 'budi@gereja.example', 'sari@maju.example'])
        ->and($by('berhenti@gereja.example')->isSendable())->toBeFalse();
});

it('rejects an import without an email column and a file that is not a sheet', function () {
    Storage::fake('local');
    $this->actingAs(adminUser());
    $this->post('/admin/outreach/import', ['file' => UploadedFile::fake()->create('foto.png', 10, 'image/png')])->assertSessionHasErrors('file');
    $this->post('/admin/outreach/import', ['file' => UploadedFile::fake()->createWithContent('k.csv', "Nama,Email\nA,a@b.example\n")]);
    $import = ContactImport::first();
    $this->post('/admin/outreach/import/'.$import->id.'/run', ['mapping' => ['name' => 0]])->assertSessionHasErrors('mapping');
    expect($import->fresh()->status)->toBe('uploaded');
});

// --- managing contacts -------------------------------------------------------------------

it('filters contacts and applies bulk actions', function () {
    fakeDns();
    $this->actingAs(adminUser());
    $gereja = Segment::create(['name' => 'Gereja']);
    $a = Contact::create(['name' => 'A', 'email' => 'a@gereja.example', 'organization' => 'GKI', 'segment_id' => $gereja->id, 'validation_status' => 'valid']);
    $b = Contact::create(['name' => 'B', 'email' => 'b@tanpa-mx.example', 'organization' => 'PT B', 'validation_status' => 'no_mx']);
    $c = Contact::create(['name' => 'C', 'email' => 'c@gereja.example', 'organization' => 'GKI', 'validation_status' => 'valid']);

    $this->get('/admin/outreach/contacts?segment='.$gereja->id)->assertInertia(fn ($p) => $p->has('contacts.data', 1)->where('contacts.data.0.email', 'a@gereja.example'));
    $this->get('/admin/outreach/contacts?segment=none')->assertInertia(fn ($p) => $p->has('contacts.data', 2));
    $this->get('/admin/outreach/contacts?status=no_mx')->assertInertia(fn ($p) => $p->has('contacts.data', 1)->where('contacts.data.0.email', 'b@tanpa-mx.example'));
    $this->get('/admin/outreach/contacts?org=GKI')->assertInertia(fn ($p) => $p->has('contacts.data', 2));
    $this->get('/admin/outreach/contacts?q=PT+B')->assertInertia(fn ($p) => $p->has('contacts.data', 1));

    // move to a segment made on the spot
    $this->post('/admin/outreach/contacts/bulk', ['ids' => [$b->id, $c->id], 'action' => 'move', 'new_segment' => 'Perusahaan'])->assertRedirect();
    expect($b->fresh()->segment->name)->toBe('Perusahaan')->and($c->fresh()->segment->name)->toBe('Perusahaan');

    // mark as do-not-contact
    $this->post('/admin/outreach/contacts/bulk', ['ids' => [$a->id], 'action' => 'suppress'])->assertRedirect();
    expect($a->fresh()->suppressed_reason)->toBe('manual')->and(Suppression::where('email', 'a@gereja.example')->exists())->toBeTrue()
        ->and(Contact::sendable()->count())->toBe(1);
    $this->get('/admin/outreach/contacts?status=suppressed')->assertInertia(fn ($p) => $p->has('contacts.data', 1));

    // the do-not-contact list survives deleting the contact, so a re-import stays blocked
    $this->post('/admin/outreach/contacts/bulk', ['ids' => [$a->id], 'action' => 'delete'])->assertRedirect();
    expect(Contact::where('email', 'a@gereja.example')->exists())->toBeFalse()->and(Suppression::where('email', 'a@gereja.example')->exists())->toBeTrue();
});

it('adds, edits and validates single contacts; the same email cannot be added twice', function () {
    fakeDns();
    $this->actingAs(adminUser());
    $this->post('/admin/outreach/contacts', ['name' => 'Rina', 'email' => ' Rina@Gereja.example ', 'organization' => 'GKI'])->assertRedirect();
    $r = Contact::first();
    expect($r->email)->toBe('rina@gereja.example')->and($r->validation_status)->toBe('valid')->and($r->unsub_token)->toHaveLength(40);
    $this->post('/admin/outreach/contacts', ['name' => 'Dobel', 'email' => 'RINA@gereja.example'])->assertSessionHasErrors('email');
    $this->put('/admin/outreach/contacts/'.$r->id, ['name' => 'Rina', 'email' => 'rina@tanpa-mx.example'])->assertRedirect();
    expect($r->fresh()->validation_status)->toBe('no_mx');
});

it('keeps segments tidy: unique names and contacts survive deleting a segment', function () {
    $this->actingAs(adminUser());
    $this->post('/admin/outreach/segments', ['name' => 'Gereja'])->assertRedirect();
    $this->post('/admin/outreach/segments', ['name' => 'Gereja'])->assertSessionHasErrors('name');
    $s = Segment::first();
    $c = Contact::create(['email' => 'x@gereja.example', 'segment_id' => $s->id]);
    $this->get('/admin/outreach/segments')->assertInertia(fn ($p) => $p->where('segments.0.contacts_count', 1));
    $this->delete('/admin/outreach/segments/'.$s->id)->assertRedirect();
    expect($c->fresh()->segment_id)->toBeNull();
});

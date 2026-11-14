<?php

use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\Quotation;
use App\Models\QuotationRevision;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\Content;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-11-16 10:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

function qTour(): Tour
{
    $cat = TourCategory::create(['name' => ['id' => 'Ziarah', 'en' => 'Pilgrimage'], 'slug' => 'ziarah']);

    return Tour::create(['category_id' => $cat->id, 'name' => 'Turki 7 Gereja', 'slug' => 'turki-7-gereja', 'duration' => '9H7M', 'region' => 'europe', 'is_active' => true,
        'price_from' => 25000000, 'departures' => [
            ['date' => '2027-03-10', 'price_adult' => 26500000, 'status' => 'open'],
            ['date' => '2027-05-12', 'price_adult' => 27900000, 'status' => 'open'],
        ]]);
}

/** @return array<string, mixed> */
function qPayload(array $o = []): array
{
    return array_replace([
        'currency' => 'IDR', 'issued_on' => '2026-11-16', 'valid_until' => '2026-11-30', 'client_name' => 'Pdt. Andreas', 'client_company' => 'GKI Contoh',
        'client_email' => 'andreas@gki.test', 'client_phone' => '0812', 'client_address' => "Jl. Damai 1\nJakarta", 'title' => 'Turki 7 Gereja', 'travel_date' => '2027-03-10', 'pax' => 20,
        'items' => [['description' => 'Paket Turki 7 Gereja', 'qty' => 20, 'unit' => 'pax', 'price' => 26500000], ['description' => 'Single supplement', 'qty' => 2, 'unit' => 'pax', 'price' => 4500000]],
        'discount' => 3000000, 'tax_percent' => 1.1, 'tax_label' => 'PPN', 'notes' => 'Belum termasuk tipping.', 'terms' => 'DP 30%.', 'payment_info' => 'Transfer ke rekening perusahaan.',
        'signatory_name' => 'Budi', 'signatory_title' => 'Direktur',
    ], $o);
}

it('numbers quotations per month and works out the totals', function () {
    expect(Quotation::nextNumber())->toBe('TW/QUO/2026/11/001');
    Quotation::create(qPayload(['number' => 'TW/QUO/2026/11/001']));
    Quotation::create(qPayload(['number' => 'TW/QUO/2026/11/007']));
    expect(Quotation::nextNumber())->toBe('TW/QUO/2026/11/008')
        ->and(Quotation::nextNumber(Carbon::parse('2026-12-01')))->toBe('TW/QUO/2026/12/001');

    Setting::updateOrCreate(['key' => 'quote_prefix'], ['value' => json_encode('/TWT/PNW/')]);
    Content::bump();
    expect(Quotation::nextNumber())->toBe('TWT/PNW/2026/11/001');

    $t = Quotation::compute([['qty' => 20, 'price' => 26500000], ['qty' => 2, 'price' => 4500000]], 3000000, 1.1);
    expect($t['subtotal'])->toEqual(539000000)->and($t['after_discount'])->toEqual(536000000)->and($t['tax'])->toEqual(5896000)->and($t['total'])->toEqual(541896000);
    expect(Quotation::compute([['qty' => 1, 'price' => 100]], 500, 0)['total'])->toEqual(0); // a discount never goes below zero
    expect(Quotation::money(-26500000))->toBe('− Rp 26.500.000');
    expect(Quotation::money(1250.5, 'USD'))->toBe('US$ 1,250.50')->and(Quotation::money(12500000))->toBe('Rp 12.500.000');
});

it('starts a quotation from a lead with the client, the package and the departure price filled in', function () {
    $this->actingAs(adminUser());
    $tour = qTour();
    $lead = Lead::create(['type' => 'form', 'name' => 'Ibu Maria', 'email' => 'maria@x.test', 'phone' => '0813', 'tour_id' => $tour->id, 'pax' => 12, 'plan_date' => '2027-04-01', 'status' => 'baru']);

    $page = $this->get('/admin/quotations/create?lead='.$lead->id)->assertOk();
    $data = $page->viewData('page')['props']['data'];
    expect($data)->toMatchArray(['lead_id' => $lead->id, 'tour_id' => $tour->id, 'client_name' => 'Ibu Maria', 'client_email' => 'maria@x.test', 'title' => 'Turki 7 Gereja', 'pax' => 12, 'valid_until' => '2026-11-30'])
        ->and($data['items'][0])->toMatchArray(['description' => 'Paket Turki 7 Gereja (9H7M)', 'qty' => 12, 'price' => 27900000.0]); // the May departure, the first on or after 1 April

    $this->post('/admin/quotations', qPayload(['lead_id' => $lead->id, 'tour_id' => $tour->id]))->assertRedirect();
    $q = Quotation::first();
    expect($q->number)->toBe('TW/QUO/2026/11/001')->and($q->lead_id)->toBe($lead->id)->and($q->status)->toBe('draft')
        ->and(LeadEvent::where('lead_id', $lead->id)->where('note', 'like', '%TW/QUO/2026/11/001%')->exists())->toBeTrue();
    $this->get('/admin/leads/'.$lead->id)->assertOk()->assertSee('TW\/QUO\/2026\/11\/001', false);
});

it('validates the form', function () {
    $this->actingAs(adminUser());
    $this->post('/admin/quotations', qPayload(['client_name' => '', 'items' => [], 'valid_until' => '2026-11-01', 'currency' => 'EUR']))
        ->assertSessionHasErrors(['client_name', 'items', 'valid_until', 'currency']);
    $this->post('/admin/quotations', qPayload(['items' => [['description' => '', 'qty' => 1, 'price' => 1]]]))->assertSessionHasErrors('items.0.description');
    expect(Quotation::count())->toBe(0);
});

it('edits a draft in place, but keeps the old version as a revision once it was sent', function () {
    $this->actingAs(adminUser());
    $this->post('/admin/quotations', qPayload());
    $q = Quotation::first();

    $this->put('/admin/quotations/'.$q->id, qPayload(['notes' => 'Draf diubah.']))->assertSessionHas('success', 'Penawaran disimpan.');
    expect($q->fresh()->revision)->toBe(0)->and(QuotationRevision::count())->toBe(0);

    $this->post('/admin/quotations/'.$q->id.'/status', ['status' => 'sent'])->assertRedirect();
    expect($q->fresh()->status)->toBe('sent')->and($q->fresh()->sent_at)->not->toBeNull();

    // saving the same content again changes nothing
    $this->put('/admin/quotations/'.$q->id, qPayload(['notes' => 'Draf diubah.']));
    expect(QuotationRevision::count())->toBe(0);

    $this->put('/admin/quotations/'.$q->id, qPayload(['notes' => 'Draf diubah.', 'discount' => 5000000]))->assertSessionHas('success');
    $q = $q->fresh();
    expect($q->revision)->toBe(1)->and($q->displayNumber())->toBe('TW/QUO/2026/11/001 Rev. 1')->and($q->discount)->toEqual(5000000)
        ->and(QuotationRevision::first()->revision)->toBe(0)->and(QuotationRevision::first()->snapshot['discount'])->toEqual(3000000);

    $this->put('/admin/quotations/'.$q->id, qPayload(['notes' => 'Ketiga.']));
    expect($q->fresh()->revision)->toBe(2)->and(QuotationRevision::pluck('revision')->sort()->values()->all())->toBe([0, 1]);
    $this->get('/admin/quotations/'.$q->id.'/edit')->assertOk()->assertSee('Rev. 2')->assertSee('Versi awal');
});

it('makes a PDF of the current version and of each earlier one', function () {
    $this->actingAs(adminUser());
    $this->post('/admin/quotations', qPayload());
    $q = Quotation::first();

    $html = view('admin.quotation-pdf', ['q' => $q->snapshot() + ['number_label' => $q->displayNumber()], 't' => $q->totals(), 'company' => ['name' => 'Tabernacle Ways', 'suffix' => 'Tour & Travel', 'address' => 'Jl. Uji', 'phone' => '+62 812', 'email' => 'contact@x.test', 'web' => 'x.test', 'logo' => null, 'navy' => '#0b1a5c'], 'old' => false])->render();
    expect($html)->toContain('PENAWARAN HARGA')->toContain('TW/QUO/2026/11/001')->toContain('Pdt. Andreas')->toContain('Rp 530.000.000')->toContain('Rp 9.000.000')
        ->toContain('− Rp 3.000.000')->toContain('PPN 1,1%')->toContain('Rp 541.896.000')->toContain('Transfer ke rekening perusahaan.')->toContain('Direktur')->not->toContain('VERSI LAMA');

    // the company address is stored in two languages; the PDF uses the Indonesian one
    Setting::updateOrCreate(['key' => 'address'], ['value' => json_encode(['id' => 'Jl. Kantor Pusat', 'en' => 'Head Office St.'])]);
    Content::bump();
    $res = $this->get('/admin/quotations/'.$q->id.'/pdf')->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf')->and($res->headers->get('content-disposition'))->toContain('inline');
    $res = $this->get('/admin/quotations/'.$q->id.'/pdf?download=1')->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('attachment')->toContain('Penawaran-tw-quo-2026-11-001-pdt-andreas.pdf');

    $this->post('/admin/quotations/'.$q->id.'/status', ['status' => 'sent']);
    $this->put('/admin/quotations/'.$q->id, qPayload(['discount' => 0]));
    $this->get('/admin/quotations/'.$q->id.'/revisions/0/pdf')->assertOk();
    $this->get('/admin/quotations/'.$q->id.'/revisions/5/pdf')->assertNotFound();
});

it('lists, filters, duplicates and deletes', function () {
    $this->actingAs(adminUser());
    $this->post('/admin/quotations', qPayload());
    $this->post('/admin/quotations', qPayload(['client_name' => 'PT Maju', 'title' => 'Jepang Sakura']));
    $first = Quotation::orderBy('id')->first();
    $this->post('/admin/quotations/'.$first->id.'/status', ['status' => 'accepted']);

    $this->get('/admin/quotations')->assertOk()->assertSee('PT Maju')->assertSee('Pdt. Andreas');
    $this->get('/admin/quotations?status=accepted')->assertOk()->assertSee('Pdt. Andreas')->assertDontSee('PT Maju');
    $this->get('/admin/quotations?q=sakura')->assertOk()->assertSee('PT Maju')->assertDontSee('Pdt. Andreas');

    $this->post('/admin/quotations/'.$first->id.'/duplicate')->assertRedirect();
    $copy = Quotation::latest('id')->first();
    expect($copy->number)->toBe('TW/QUO/2026/11/003')->and($copy->status)->toBe('draft')->and($copy->client_name)->toBe('Pdt. Andreas')->and($copy->items)->toHaveCount(2);

    $this->delete('/admin/quotations/'.$copy->id)->assertRedirect('/admin/quotations');
    expect(Quotation::count())->toBe(2);
});

it('is for staff who manage leads, and its defaults live in the settings', function () {
    $this->actingAs(adminUser('editor', ['email' => 'ed@kita.test']))->get('/admin/quotations')->assertForbidden();
    $this->actingAs(adminUser())->get('/admin/settings')->assertOk()->assertSee('quote_payment_info')->assertSee('quote_signatory_name');
});

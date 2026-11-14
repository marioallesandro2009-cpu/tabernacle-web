<?php

use App\Models\Setting;
use App\Services\Translator;
use App\Support\Content;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** A fake MyMemory: answers "EN(<text>)" so the tests can see what was sent where. */
function fakeMyMemory(?array $answer = null): void
{
    Http::fake(['api.mymemory.translated.net/*' => function (HttpRequest $r) use ($answer) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);

        return Http::response($answer ?? ['responseData' => ['translatedText' => 'EN('.$q['q'].')'], 'responseStatus' => 200]);
    }]);
}

beforeEach(fn () => Cache::flush());

it('translates plain text line by line and keeps the line breaks', function () {
    fakeMyMemory();
    $out = app(Translator::class)->translate("Hari pertama.\n\nTiba di Istanbul.");
    expect($out)->toBe("EN(Hari pertama.)\n\nEN(Tiba di Istanbul.)");
    Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), 'langpair=id|en'));
});

it('keeps HTML tags and translates only the text between them', function () {
    fakeMyMemory();
    $out = app(Translator::class)->translate('<p>Harga <strong>termasuk</strong> tiket &amp; hotel.</p><ul><li>Visa</li><li>2027</li></ul>', true);
    expect($out)->toBe('<p>EN(Harga) <strong>EN(termasuk)</strong> EN(tiket &amp; hotel.)</p><ul><li>EN(Visa)</li><li>2027</li></ul>');
});

it('cuts long text into pieces the service accepts, and never asks twice for the same piece', function () {
    fakeMyMemory();
    $long = implode(' ', array_fill(0, 30, 'Kami mengunjungi gereja tua yang bersejarah di kota ini.')); // ~1700 bytes
    $pieces = app(Translator::class)->pieces($long);
    expect(count($pieces))->toBeGreaterThan(3);
    foreach ($pieces as $p) {
        expect(strlen($p))->toBeLessThanOrEqual(Translator::PIECE);
    }
    expect(implode(' ', $pieces))->toBe($long);
    expect(app(Translator::class)->pieces(str_repeat('kata ', 200)))->each(fn ($p) => $p->toBeString()); // one huge "sentence" is cut at spaces

    app(Translator::class)->translate('Selamat pagi.');
    app(Translator::class)->translate('Selamat pagi.');
    Http::assertSentCount(1);
});

it('sends the registered email when one is set', function () {
    fakeMyMemory();
    Setting::updateOrCreate(['key' => 'translate_email'], ['value' => json_encode('tim@kita.test')]);
    Content::bump();
    app(Translator::class)->translate('Halo');
    Http::assertSent(fn (HttpRequest $r) => str_contains(urldecode($r->url()), 'de=tim@kita.test'));
});

it('explains when the free quota is used up or the service fails', function () {
    fakeMyMemory(['responseData' => ['translatedText' => 'MYMEMORY WARNING: YOU USED ALL AVAILABLE FREE TRANSLATIONS FOR TODAY.'], 'responseStatus' => 429, 'quotaFinished' => true]);
    expect(fn () => app(Translator::class)->translate('Halo'))->toThrow(RuntimeException::class, 'Kuota terjemahan gratis hari ini sudah habis');

});

it('explains a failed translation', function () {
    fakeMyMemory(['responseData' => ['translatedText' => ''], 'responseStatus' => 500]);
    expect(fn () => app(Translator::class)->translate('Halo lagi'))->toThrow(RuntimeException::class, 'Terjemahan gagal');
});

it('answers the admin buttons, for signed-in staff only', function () {
    fakeMyMemory();
    $this->postJson('/admin/translate', ['items' => [['text' => 'Halo']]])->assertUnauthorized();

    $this->actingAs(adminUser('editor', ['email' => 'ed@kita.test']));
    $this->postJson('/admin/translate', ['items' => [['text' => 'Halo', 'html' => false], ['text' => '<b>Terima kasih</b>', 'html' => true], ['text' => '']]])
        ->assertOk()->assertExactJson(['items' => ['EN(Halo)', '<b>EN(Terima kasih)</b>', '']]);

    $this->postJson('/admin/translate', ['items' => [['text' => str_repeat('a', 30001)]]])->assertStatus(422)->assertJsonFragment(['message' => 'Teksnya terlalu panjang untuk sekali terjemah (30.001 karakter, maksimal 30.000).']);
    $this->postJson('/admin/translate', ['items' => []])->assertStatus(422);
});

it('passes the reason to the admin when the service fails', function () {
    fakeMyMemory(['responseData' => ['translatedText' => ''], 'responseStatus' => 503]);
    $this->actingAs(adminUser());
    $this->postJson('/admin/translate', ['items' => [['text' => 'Gagal ya']]])->assertStatus(503)->assertJsonFragment(['message' => 'Terjemahan gagal (503). Coba lagi, atau terjemahkan manual.']);
});

it('does not trust a wrong top answer when the other matches agree on something else', function () {
    $t = app(Translator::class);
    $matches = [
        ['translation' => 'What matters is heli', 'quality' => 74, 'match' => 0.99, 'created-by' => 'Public Web'],
        ['translation' => 'Good morning. ', 'quality' => 74, 'match' => 0.99, 'created-by' => 'MateCat'],
        ['translation' => 'Good morning.', 'quality' => 74, 'match' => 0.99, 'created-by' => 'MateCat'],
    ];
    expect($t->best($matches, 'What matters is heli'))->toBe('Good morning.');

    // one human entry against the machine translation: the machine wins the tie
    expect($t->best([['translation' => 'Odd text', 'match' => 1, 'quality' => 80, 'created-by' => 'Public Web'], ['translation' => 'Day 1: Arrival.', 'match' => 0.85, 'quality' => 70, 'created-by' => 'MT!']], 'Odd text'))->toBe('Day 1: Arrival.');
    // fuzzy matches of other sentences are ignored
    expect($t->best([['translation' => 'Something else', 'match' => 0.7, 'created-by' => 'Public Web']], 'Top answer'))->toBe('Top answer');
    expect($t->best([], 'Top answer'))->toBe('Top answer');

    Http::fake(['api.mymemory.translated.net/*' => Http::response(['responseData' => ['translatedText' => 'What matters is heli'], 'responseStatus' => 200, 'matches' => $matches])]);
    expect($t->translate('Selamat pagi.'))->toBe('Good morning.');
});

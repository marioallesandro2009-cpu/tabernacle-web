<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\TeamMember;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\Locale;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Terjemahan belum lengkap": everything with Indonesian text and no English yet. Pages listed
 * as "not on the English site" are shown in Indonesian under /en and left out of the English sitemap.
 */
class TranslationStatusController extends Controller
{
    public function index(): Response
    {
        $rows = [];
        $add = function (string $type, string $name, array $missing, string $edit, ?string $public, bool $blocking) use (&$rows) {
            if ($missing) {
                $rows[] = ['type' => $type, 'name' => $name, 'missing' => array_values($missing), 'edit' => $edit, 'public' => $public, 'blocking' => $blocking];
            }
        };

        foreach (Tour::active()->orderBy('name')->get() as $t) {
            $missing = array_filter([
                'Itinerary' => ! empty($t->itinerary) && empty($t->itinerary_en),
                'Deskripsi' => $this->gap($t->description),
                'Harga termasuk' => ! empty($t->includes) && empty($t->includes_en),
                'Harga tidak termasuk' => ! empty($t->excludes) && empty($t->excludes_en),
                'Catatan & syarat' => filled(strip_tags((string) $t->terms)) && blank(strip_tags((string) $t->terms_en)),
                'Keterangan tanggal' => filled($t->dates_label) && blank($t->dates_label_en),
                'Label wilayah' => $this->gap($t->area_label),
            ]);
            $add('Paket tour', $t->name, array_keys($missing), admin_path('/tours/'.$t->id.'/edit'), Locale::path('/packages/'.$t->slug, 'en'), ! $t->hasEnglish());
        }
        foreach (Post::orderByDesc('id')->get() as $p) {
            $missing = array_filter([
                'Judul' => blank($p->getAttribute('title_en')),
                'Isi artikel' => blank(strip_tags((string) $p->getAttribute('content_en'))),
                'Ringkasan' => filled($p->excerpt) && blank($p->getAttribute('excerpt_en')),
                'SEO judul' => filled($p->meta_title) && blank($p->getAttribute('meta_title_en')),
                'SEO deskripsi' => filled($p->meta_description) && blank($p->getAttribute('meta_description_en')),
                'FAQ' => ! empty($p->faq) && empty($p->faq_en),
            ]);
            $add('Artikel', (string) $p->title, array_keys($missing), admin_path('/posts/'.$p->id.'/edit'), Locale::path('/blog/'.$p->slug, 'en'), ! $p->hasEnglish());
        }
        foreach (Destination::orderBy('slug')->get() as $d) {
            $missing = array_filter(['Nama' => $this->gap($d->name), 'Intro' => $this->gap($d->intro), 'SEO judul' => $this->gap($d->meta_title), 'SEO deskripsi' => $this->gap($d->meta_description)]);
            $add('Destinasi', tr($d->name, 'id'), array_keys($missing), admin_path('/destinations/'.$d->id.'/edit'), Locale::path('/destinations/'.$d->slug, 'en'), ! $d->hasEnglish());
        }
        foreach (Page::with('sections')->orderBy('is_system', 'desc')->orderBy('id')->get() as $pg) {
            $missing = $this->gap($pg->title) ? ['Judul halaman'] : [];
            foreach ($pg->sections as $s) {
                $fields = collect((array) $s->data)->filter(fn ($v) => $this->gap($v))->keys();
                if ($fields->isNotEmpty() && $s->is_visible) {
                    $missing[] = 'Section "'.$s->type.'": '.$fields->implode(', ');
                }
            }
            $add($pg->is_system ? 'Halaman' : 'Halaman buatan', tr($pg->title, 'id') ?: $pg->key, $missing, admin_path('/pages/'.$pg->id.'/edit'),
                $pg->is_active ? Locale::path($pg->key === 'home' ? '/' : '/'.$pg->slug, 'en') : null, ! $pg->hasEnglish());
        }
        foreach ([[TourCategory::class, 'Kategori paket', 'name', '/tour-categories'], [Faq::class, 'FAQ', 'question', '/faqs'], [Testimonial::class, 'Testimoni', 'content', '/testimonials'], [TeamMember::class, 'Tim', 'role', '/team']] as [$model, $type, $field, $path]) {
            foreach ($model::query()->get() as $m) {
                $missing = collect($m->getCasts())->filter(fn ($c) => $c === 'array')->keys()->filter(fn ($k) => $this->gap($m->getAttribute($k)))->values()->all();
                $add($type, mb_substr(strip_tags(tr($m->getAttribute($field), 'id')), 0, 80) ?: '#'.$m->getKey(), $missing, admin_path($path.'/'.$m->getKey().'/edit'), null, false);
            }
        }

        return Inertia::render('Admin/Translations', [
            'rows' => $rows,
            'counts' => ['total' => count($rows), 'blocking' => collect($rows)->where('blocking', true)->count()],
        ]);
    }

    /** Indonesian text present, English missing. */
    protected function gap(mixed $v): bool
    {
        return is_array($v) && array_key_exists('id', $v) && trim(strip_tags((string) ($v['id'] ?? ''))) !== '' && trim(strip_tags((string) ($v['en'] ?? ''))) === '';
    }
}

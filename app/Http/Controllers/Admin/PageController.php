<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\PageController as SitePageController;
use App\Models\Page;
use App\Models\Section;
use App\Support\Content;
use App\Support\F;
use App\Support\MediaInfo;
use App\Support\Schema;
use App\Support\SectionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/** Pages (SEO + the ordered list of sections) and the sections themselves. */
class PageController extends Controller
{
    /** @return array<int, array<string, mixed>> */
    protected function pageFields(?Page $page = null): array
    {
        $custom = $page && ! $page->is_system;

        return array_values(array_filter([
            F::t('title', 'Judul halaman', ['required' => $custom]),
            $custom ? F::slug('slug', 'Alamat halaman (contoh syarat-ketentuan menjadi /syarat-ketentuan)', 'title') : null,
            F::t('meta_title', 'SEO: judul (50–60 karakter)'),
            F::tarea('meta_description', 'SEO: deskripsi (120–160 karakter)'),
            F::image('og_image_id', 'Gambar saat dibagikan (OG image)'),
            F::toggle('noindex', 'Sembunyikan dari mesin pencari (noindex)'),
            F::toggle('is_active', 'Halaman aktif', ['default' => true]),
        ]));
    }

    protected function publicUrl(Page $p): string
    {
        return $p->publicUrl();
    }

    /** @return array<int, mixed> */
    protected function slugRules(?Page $page): array
    {
        return ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Page::RESERVED_SLUGS),
            Rule::unique('pages', 'slug')->ignore($page?->id)];
    }

    /** @return array<string, string> */
    protected function slugMessages(): array
    {
        return ['slug.not_in' => 'Alamat ini sudah dipakai situs. Pilih alamat lain.', 'slug.unique' => 'Alamat ini sudah dipakai halaman lain.'];
    }

    /** Admins add their own pages (terms, privacy, ...); each starts inactive with one free-text section. */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title.en') ?: $request->input('title.id')))]);
        $data = $request->validate([
            'title' => ['required', 'array'], 'title.en' => ['required', 'string', 'max:200'], 'title.id' => ['nullable', 'string', 'max:200'],
            'slug' => $this->slugRules(null),
        ], $this->slugMessages());
        $base = 'c-'.substr($data['slug'], 0, 30);
        $key = $base;
        $i = 2;
        while (Page::where('key', $key)->exists()) {
            $key = $base.'-'.$i++;
        }
        $title = ['en' => $data['title']['en'], 'id' => (string) ($data['title']['id'] ?? '')];
        $page = Page::create(['key' => $key, 'slug' => $data['slug'], 'title' => $title, 'is_active' => false, 'is_system' => false]);
        $page->sections()->create(['type' => 'text_block', 'name' => 'Isi halaman', 'sort' => 1, 'is_visible' => true,
            'data' => ['kicker' => null, 'title_a' => $title, 'title_b' => null, 'body' => null]]);

        return redirect('/admin/pages/'.$page->id.'/edit')->with('success', 'Halaman dibuat (belum aktif). Isi kontennya, pratinjau, lalu aktifkan.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $page = Page::findOrFail($id);
        if ($page->is_system) {
            return back()->with('error', 'Halaman bawaan situs tidak bisa dihapus. Anda bisa menonaktifkannya.');
        }
        $page->delete();

        return redirect('/admin/pages')->with('success', 'Halaman dihapus.');
    }

    /** Shows a custom page as visitors would see it, even while inactive. Never indexed. */
    public function preview(string $id): View|RedirectResponse
    {
        $page = Page::with(['sections' => fn ($q) => $q->where('is_visible', true)])->findOrFail($id);
        if ($page->is_system) {
            return redirect($page->publicUrl());
        }

        return app(SitePageController::class)->show($page, preview: true);
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::withCount('sections')->orderBy('id')->get()->map(fn (Page $p) => [
                'id' => $p->id, 'key' => $p->key, 'title' => tr($p->title) ?: $p->key, 'sections' => $p->sections_count,
                'is_active' => $p->is_active, 'url' => $this->publicUrl($p), 'is_system' => $p->is_system,
            ]),
        ]);
    }

    public function edit(string $id): Response
    {
        $page = Page::with('sections')->findOrFail($id);
        $fields = $this->pageFields($page);
        $values = Schema::forForm($fields, $page->attributesToArray());

        return Inertia::render('Admin/Pages/Edit', [
            'page' => [
                'id' => $page->id, 'key' => $page->key, 'url' => $this->publicUrl($page), 'is_system' => $page->is_system, 'is_active' => $page->is_active,
                'title' => tr($page->title) ?: $page->key, 'preview_url' => $page->is_system ? null : url('/admin/pages/'.$page->id.'/preview'),
            ],
            'fields' => $fields,
            'values' => $values,
            'media' => MediaInfo::for($fields, $values),
            'sections' => $page->sections->map(fn (Section $s) => [
                'id' => $s->id, 'type' => $s->type, 'label' => SectionRegistry::types()[$s->type]['label'] ?? $s->type,
                'name' => $s->name, 'is_visible' => $s->is_visible,
            ])->values(),
            'types' => collect(SectionRegistry::types())->map(fn ($t, $k) => ['value' => $k, 'label' => $t['label']])->values(),
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $page = Page::findOrFail($id);
        $fields = $this->pageFields($page);
        $rules = Schema::rules($fields);
        if (! $page->is_system) {
            $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title.en')))]);
            $rules['slug'] = $this->slugRules($page);
        }
        $request->validate($rules, $this->slugMessages());
        $page->update(Schema::clean($fields, $request->all()));

        return back()->with('success', 'Halaman disimpan.');
    }

    public function sectionStore(Request $request, string $pageId): RedirectResponse
    {
        $page = Page::findOrFail($pageId);
        $data = $request->validate(['type' => ['required', 'in:'.implode(',', array_keys(SectionRegistry::types()))]]);
        $section = $page->sections()->create([
            'type' => $data['type'], 'data' => [], 'is_visible' => true,
            'name' => SectionRegistry::types()[$data['type']]['label'],
            'sort' => (int) $page->sections()->max('sort') + 1,
        ]);

        return redirect('/admin/sections/'.$section->id.'/edit')->with('success', 'Section ditambahkan. Isi kontennya di bawah.');
    }

    public function sectionEdit(string $id): Response
    {
        $section = Section::with('page')->findOrFail($id);
        $fields = SectionRegistry::fields($section->type);
        $values = Schema::forForm($fields, (array) $section->data);

        return Inertia::render('Admin/Pages/Section', [
            'section' => ['id' => $section->id, 'type' => $section->type, 'label' => SectionRegistry::types()[$section->type]['label'] ?? $section->type, 'name' => $section->name, 'is_visible' => $section->is_visible],
            'page' => ['id' => $section->page->id, 'key' => $section->page->key,
                // an inactive custom page is only reachable through the admin preview
                'url' => ($section->page->is_active || $section->page->is_system) ? $this->publicUrl($section->page) : url('/admin/pages/'.$section->page->id.'/preview')],
            'fields' => $fields,
            'values' => $values,
            'media' => MediaInfo::for($fields, $values),
        ]);
    }

    public function sectionUpdate(Request $request, string $id): RedirectResponse
    {
        $section = Section::findOrFail($id);
        $fields = SectionRegistry::fields($section->type);
        $request->validate(Schema::rules($fields) + ['name' => ['nullable', 'string', 'max:120'], 'is_visible' => ['boolean']]);
        $section->update([
            'data' => Schema::clean($fields, $request->all()),
            'name' => $request->input('name', $section->name),
            'is_visible' => $request->boolean('is_visible', true),
        ]);

        return back()->with('success', 'Section disimpan.');
    }

    public function sectionToggle(string $id): RedirectResponse
    {
        $s = Section::findOrFail($id);
        $s->update(['is_visible' => ! $s->is_visible]);

        return back();
    }

    public function sectionDestroy(string $id): RedirectResponse
    {
        $s = Section::findOrFail($id);
        $pageId = $s->page_id;
        $s->delete();

        return redirect('/admin/pages/'.$pageId.'/edit')->with('success', 'Section dihapus.');
    }

    public function sectionReorder(Request $request, string $pageId): JsonResponse
    {
        foreach (array_map('intval', (array) $request->input('ids', [])) as $i => $sid) {
            Section::where('page_id', $pageId)->whereKey($sid)->update(['sort' => $i + 1]);
        }
        Content::bump();

        return response()->json(['ok' => true]);
    }
}

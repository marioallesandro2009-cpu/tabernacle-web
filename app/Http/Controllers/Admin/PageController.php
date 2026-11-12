<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
use Inertia\Inertia;
use Inertia\Response;

/** Pages (SEO + the ordered list of sections) and the sections themselves. */
class PageController extends Controller
{
    /** @return array<int, array<string, mixed>> */
    protected function pageFields(): array
    {
        return [
            F::t('title', 'Judul halaman'),
            F::t('meta_title', 'SEO: judul (50–60 karakter)'),
            F::tarea('meta_description', 'SEO: deskripsi (120–160 karakter)'),
            F::image('og_image_id', 'Gambar saat dibagikan (OG image)'),
            F::toggle('noindex', 'Sembunyikan dari mesin pencari (noindex)'),
            F::toggle('is_active', 'Halaman aktif', ['default' => true]),
        ];
    }

    protected function publicUrl(Page $p): string
    {
        return $p->key === 'home' ? url('/') : url('/'.ltrim($p->slug, '/'));
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Pages/Index', [
            'pages' => Page::withCount('sections')->orderBy('id')->get()->map(fn (Page $p) => [
                'id' => $p->id, 'key' => $p->key, 'title' => tr($p->title) ?: $p->key, 'sections' => $p->sections_count,
                'is_active' => $p->is_active, 'url' => $this->publicUrl($p),
            ]),
        ]);
    }

    public function edit(string $id): Response
    {
        $page = Page::with('sections')->findOrFail($id);
        $fields = $this->pageFields();
        $values = Schema::forForm($fields, $page->attributesToArray());

        return Inertia::render('Admin/Pages/Edit', [
            'page' => ['id' => $page->id, 'key' => $page->key, 'url' => $this->publicUrl($page)],
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
        $fields = $this->pageFields();
        $request->validate(Schema::rules($fields));
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
            'page' => ['id' => $section->page->id, 'key' => $section->page->key, 'url' => $this->publicUrl($section->page)],
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

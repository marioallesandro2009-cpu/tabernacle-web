<?php

namespace App\Http\Controllers\Admin\Resources;

use App\Http\Controllers\Admin\ResourceController;
use App\Http\Controllers\Site\BlogController;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\Tour;
use App\Models\User;
use App\Support\ContentTools;
use App\Support\F;
use App\Support\Schema;
use App\Support\SeoAnalyzer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;

/** @extends ResourceController<Post> */
class PostController extends ResourceController
{
    protected string $model = Post::class;

    protected string $slug = 'posts';

    protected string $title = 'Artikel blog';

    protected string $singular = 'Artikel';

    protected bool $hasActive = false;

    protected array $with = ['category', 'author'];

    protected array $search = ['title', 'focus_keyword'];

    /** @var array<int, string> */
    protected array $tagNames = [];

    protected function fields(?Model $model = null): array
    {
        $cats = PostCategory::orderBy('name')->get()->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all();
        $authors = User::where('is_active', true)->orderBy('name')->get()->map(fn ($u) => ['value' => $u->id, 'label' => $u->name])->all();

        return [
            F::text('title', 'Judul artikel', ['required' => true]),
            F::slug('slug', 'Slug (alamat artikel)', 'title'),
            F::textarea('excerpt', 'Ringkasan singkat (tampil di daftar artikel)', ['max' => 600]),
            F::rich('content', 'Isi artikel'),
            F::image('cover_id', 'Gambar utama'),
            F::select('category_id', 'Kategori', $cats),
            F::list('tags', 'Tag', ['max' => 15]),
            F::select('author_id', 'Penulis', $authors),
            F::select('status', 'Status', [['value' => 'draft', 'label' => 'Draft'], ['value' => 'scheduled', 'label' => 'Terjadwal'], ['value' => 'published', 'label' => 'Terbit']], ['required' => true, 'default' => 'draft']),
            F::datetime('published_at', 'Tanggal terbit / jadwal'),
            F::text('focus_keyword', 'Kata kunci utama (focus keyword)', ['max' => 120]),
            F::text('meta_title', 'SEO: judul (50–60 karakter)', ['max' => 190]),
            F::textarea('meta_description', 'SEO: deskripsi (120–160 karakter)', ['max' => 400]),
            F::image('og_image_id', 'Gambar saat dibagikan (OG image)'),
            F::repeat('faq', 'FAQ artikel (muncul sebagai schema FAQPage)', [F::text('q', 'Pertanyaan', ['required' => true]), F::textarea('a', 'Jawaban', ['required' => true, 'max' => 2000])], ['item_label' => 'q', 'max' => 15]),
        ];
    }

    protected function columns(): array
    {
        return [
            ['key' => 'cover_id', 'label' => '', 'type' => 'image'],
            ['key' => 'title', 'label' => 'Judul', 'type' => 'text'],
            ['key' => 'category', 'label' => 'Kategori', 'type' => 'badge', 'get' => fn (Post $p) => $p->category?->name],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'get' => fn (Post $p) => match ($p->status) {
                'published' => 'Terbit', 'scheduled' => 'Terjadwal '.$p->published_at?->format('d M H:i'), default => 'Draft'
            }],
            ['key' => 'seo_score', 'label' => 'Skor SEO', 'type' => 'score'],
            ['key' => 'views', 'label' => 'Dibaca', 'type' => 'text'],
            ['key' => 'published_at', 'label' => 'Tanggal', 'type' => 'date'],
        ];
    }

    protected function filters(): array
    {
        return [['key' => 'status', 'label' => 'Status', 'options' => [['value' => 'draft', 'label' => 'Draft'], ['value' => 'scheduled', 'label' => 'Terjadwal'], ['value' => 'published', 'label' => 'Terbit']]]];
    }

    protected function valuesOf(?Model $model, array $fields): array
    {
        $v = parent::valuesOf($model, $fields);
        if ($model) {
            /** @var Post $model */
            $v['tags'] = $model->tags->pluck('name')->all();
            $v['published_at'] = $model->published_at?->format('Y-m-d\TH:i');
        } else {
            $v['author_id'] = auth()->id();
        }

        return $v;
    }

    protected function form(?Model $model): Response
    {
        $fields = $this->fields($model);
        $values = Schema::forForm($fields, $this->valuesOf($model?->load('tags'), $fields));

        return Inertia::render('Admin/Posts/Form', [
            'resource' => $this->meta(),
            'fields' => $fields,
            'values' => $values,
            'id' => $model?->getKey(),
            'media' => $this->mediaIn($fields, $values),
            'site_url' => url('/blog'),
            'all_tags' => Tag::orderBy('name')->pluck('name'),
            'preview_url' => $model && $model->status === 'published' ? url('/blog/'.$model->slug) : null,
            'draft_preview_url' => $model ? url('/admin/posts/'.$model->getKey().'/preview') : null,
        ]);
    }

    protected function beforeSave(array &$data, ?Model $model, Request $request): void
    {
        $this->tagNames = collect($data['tags'] ?? [])->map(fn ($t) => Str::limit(trim((string) $t), 60, ''))->filter()->unique()->values()->all();
        unset($data['tags']);

        $status = $data['status'];
        $at = ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : null;
        if ($status === 'draft') {
            $data['published_at'] = $at ?: ($model?->published_at);
        } elseif ($status === 'scheduled') {
            if (! $at || $at->isPast()) {
                throw ValidationException::withMessages(['published_at' => 'Untuk status Terjadwal, isi tanggal terbit di masa depan.']);
            }
            $data['published_at'] = $at;
        } else {
            $data['published_at'] = ($at && $at->isPast()) ? $at : ($model?->published_at && $model->status === 'published' ? $model->published_at : now());
        }
        if (empty($data['author_id'])) {
            $data['author_id'] = $model?->author_id ?: $request->user()->id;
        }
        $data['reading_time'] = ContentTools::readingTime($data['content'] ?? '');
        $data['seo_score'] = SeoAnalyzer::analyze($data + ['slug' => $data['slug'] ?? ''])['score'];
    }

    protected function afterSave(Model $model, array $data, Request $request): void
    {
        /** @var Post $model */
        $ids = collect($this->tagNames)->map(fn ($name) => Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id)->all();
        $model->tags()->sync($ids);
    }

    /** Shows the saved article as visitors would see it, whatever its status. Never indexed. */
    public function preview(string $id): View
    {
        return app(BlogController::class)->render(Post::findOrFail($id), preview: true);
    }

    /** Live SEO panel: scores the current (unsaved) form. */
    public function analyze(Request $request): JsonResponse
    {
        $p = $request->only(['title', 'slug', 'focus_keyword', 'meta_title', 'meta_description', 'content', 'cover_id', 'og_image_id']);
        $p['content'] = clean_html((string) ($p['content'] ?? ''));
        if (empty($p['slug']) && ! empty($p['title'])) {
            $p['slug'] = Str::slug((string) $p['title']);
        }

        $result = SeoAnalyzer::analyze($p);

        // internal link ideas: tours and other articles related to the focus keyword
        $kw = trim((string) ($p['focus_keyword'] ?? ''));
        $like = $kw !== '' ? '%'.$kw.'%' : null;
        $tours = Tour::query()->where('is_active', true)->when($like, fn ($q) => $q->where('name', 'like', $like))->orderByDesc('is_featured')->limit(5)->get(['name', 'slug']);
        $posts = Post::query()->live()->when($like, fn ($q) => $q->where('title', 'like', $like))->when($request->input('id'), fn ($q, $id) => $q->whereKeyNot($id))->latest('published_at')->limit(5)->get(['title', 'slug']);
        $result['suggestions'] = [
            ...$tours->map(fn ($t) => ['label' => 'Paket: '.$t->name, 'url' => '/packages/'.$t->slug])->all(),
            ...$posts->map(fn ($t) => ['label' => 'Artikel: '.$t->title, 'url' => '/blog/'.$t->slug])->all(),
        ];

        return response()->json($result);
    }
}

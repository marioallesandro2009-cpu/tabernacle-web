<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Content;
use App\Support\MediaInfo;
use App\Support\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * @template TModel of Model
 *
 * One controller drives every simple CMS module. A subclass only describes the
 * model, its form fields (see F) and the table columns.
 */
abstract class ResourceController extends Controller
{
    /** @var class-string<TModel> */
    protected string $model;

    /** URL part, e.g. "tours" -> /admin/tours */
    protected string $slug;

    protected string $title;

    protected string $singular;

    /** @var array<int, string> */
    protected array $with = [];

    protected bool $sortable = false;

    protected string $sortColumn = 'sort';

    /** @var array<int, string> columns searched by the box above the table */
    protected array $search = [];

    protected int $perPage = 25;

    protected string $orderBy = 'id';

    protected string $orderDir = 'desc';

    protected bool $hasActive = true;

    /**
     * @param  TModel|null  $model
     * @return array<int, array<string, mixed>>
     */
    abstract protected function fields(?Model $model = null): array;

    /** @return array<int, array<string, mixed>> [['key','label','type', 'get' => fn]] */
    abstract protected function columns(): array;

    /** @return array<int, array<string, mixed>> [['key','label','options'=>[{value,label}]]] */
    protected function filters(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  TModel|null  $model
     */
    protected function beforeSave(array &$data, ?Model $model, Request $request): void {}

    /**
     * @param  TModel  $model
     * @param  array<string, mixed>  $data
     */
    protected function afterSave(Model $model, array $data, Request $request): void {}

    /**
     * Return an error message to stop a delete.
     *
     * @param  TModel  $model
     */
    protected function blockDelete(Model $model): ?string
    {
        return null;
    }

    /**
     * @param  TModel|null  $model
     * @return array<string, mixed>
     */
    protected function extraRules(?Model $model, Request $request): array
    {
        return [];
    }

    /** Extra scope applied to the list query. @param Builder<Model> $q */
    protected function scope(Builder $q, Request $request): void {}

    /**
     * @param  TModel|null  $model
     * @return array<string, mixed>
     */
    protected function formExtras(?Model $model): array
    {
        return [];
    }

    protected function meta(): array
    {
        return [
            'slug' => $this->slug, 'title' => $this->title, 'singular' => $this->singular, 'sortable' => $this->sortable,
            'hasActive' => $this->hasActive, 'url' => admin_path().'/'.$this->slug,
        ];
    }

    public function index(Request $request): Response
    {
        $q = ($this->model)::query()->with($this->with);
        $term = trim((string) $request->query('q', ''));
        if ($term !== '' && $this->search) {
            $q->where(function (Builder $w) use ($term) {
                foreach ($this->search as $col) {
                    $w->orWhere($col, 'like', '%'.$term.'%');
                }
            });
        }
        foreach ($this->filters() as $f) {
            $v = $request->query($f['key']);
            if ($v !== null && $v !== '') {
                // a filter may bring its own query (e.g. "expired"), otherwise it matches a column
                isset($f['apply']) ? $f['apply']($q, $v) : $q->where($f['key'], $v);
            }
        }
        $this->scope($q, $request);
        $this->sortable ? $q->orderBy($this->sortColumn)->orderBy('id') : $q->orderBy($this->orderBy, $this->orderDir);

        $columns = collect($this->columns());
        $mapRow = function (Model $m) use ($columns) {
            $row = ['id' => $m->getKey()];
            if ($this->hasActive) {
                $row['is_active'] = (bool) $m->getAttribute('is_active');
            }
            foreach ($columns as $c) {
                $row[$c['key']] = isset($c['get']) ? $c['get']($m) : $this->cell($m, $c);
            }

            return $row;
        };

        $rows = $this->sortable ? $q->get()->map($mapRow)->values() : $q->paginate($this->perPage)->withQueryString()->through($mapRow);

        return Inertia::render('Admin/Resource/Index', [
            'resource' => $this->meta(),
            'columns' => $columns->map(fn ($c) => Arr::except($c, ['get']))->values(),
            'filters' => collect($this->filters())->map(fn ($f) => Arr::except($f, ['apply']))->values(),
            'rows' => $rows,
            'query' => $request->only(['q', ...array_column($this->filters(), 'key')]),
        ]);
    }

    /** @param array<string, mixed> $c */
    protected function cell(Model $m, array $c): mixed
    {
        $v = $m->getAttribute($c['key']);

        return match ($c['type'] ?? 'text') {
            'tr' => tr($v),
            'image' => $v ? media_url($v, 480) : null,
            'bool' => (bool) $v,
            'money' => $v ? rupiah($v) : '',
            'date' => $v ? Carbon::parse($v)->translatedFormat('d M Y H:i') : '',
            default => is_array($v) ? json_encode($v) : $v,
        };
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(string $id): Response
    {
        return $this->form(($this->model)::with($this->with)->findOrFail($id));
    }

    /** @param TModel|null $model */
    protected function form(?Model $model): Response
    {
        $fields = $this->fields($model);
        $values = Schema::forForm($fields, $this->valuesOf($model, $fields));

        return Inertia::render('Admin/Resource/Form', [
            'resource' => $this->meta(),
            'fields' => $fields,
            'values' => $values,
            'id' => $model?->getKey(),
            'media' => $this->mediaIn($fields, $values),
            'extras' => $this->formExtras($model),
        ]);
    }

    /**
     * @param  TModel|null  $model
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    protected function valuesOf(?Model $model, array $fields): array
    {
        if (! $model) {
            return [];
        }
        $out = $model->attributesToArray();
        foreach ($fields as $f) {
            if ($f['type'] === 'multi' && ! empty($f['relation'])) {
                $out[$f['key']] = $model->{$f['relation']}()->pluck($model->{$f['relation']}()->getRelated()->getQualifiedKeyName())->all();
            }
        }

        return $out;
    }

    protected function mediaIn(array $fields, array $values): array
    {
        return MediaInfo::for($fields, $values);
    }

    public function store(Request $request): RedirectResponse
    {
        $model = new $this->model;
        $this->persist($model, $request);

        return redirect(admin_path().'/'.$this->slug.'/'.$model->getKey().'/edit')->with('success', $this->singular.' dibuat.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $model = ($this->model)::findOrFail($id);
        $this->persist($model, $request);

        return back()->with('success', $this->singular.' disimpan.');
    }

    protected function persist(Model $model, Request $request): void
    {
        $fields = $this->fields($model->exists ? $model : null);
        // extra rules replace the generic ones for the same field (array_merge: later keys win)
        $request->validate(array_merge(Schema::rules($fields), $this->extraRules($model->exists ? $model : null, $request)));
        $data = Schema::clean($fields, $request->all());

        // multi-select relations are synced after the row exists
        $syncs = [];
        foreach ($fields as $f) {
            if ($f['type'] === 'multi' && ! empty($f['relation'])) {
                $syncs[$f['relation']] = $data[$f['key']] ?? [];
                unset($data[$f['key']]);
            }
        }
        // slugs: fill from the source field, keep them unique
        foreach ($fields as $f) {
            if ($f['type'] === 'slug') {
                $src = $data[$f['source']] ?? null;
                $base = $data[$f['key']] ?: Str::slug(is_array($src) ? ($src['en'] ?? '') : (string) $src);
                $data[$f['key']] = $this->uniqueSlug($model, $f['key'], $base ?: Str::random(6));
            }
        }
        $this->beforeSave($data, $model->exists ? $model : null, $request);

        $model->fill(collect($data)->all());
        $model->save();
        foreach ($syncs as $rel => $ids) {
            $model->{$rel}()->sync($ids);
        }
        $this->afterSave($model, $data, $request);
    }

    protected function uniqueSlug(Model $model, string $column, string $base): string
    {
        $slug = $base;
        $i = 2;
        while (($this->model)::where($column, $slug)->when($model->exists, fn ($q) => $q->whereKeyNot($model->getKey()))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function destroy(string $id): RedirectResponse
    {
        $model = ($this->model)::findOrFail($id);
        if ($msg = $this->blockDelete($model)) {
            return back()->with('error', $msg);
        }
        $model->delete();

        return redirect(admin_path().'/'.$this->slug)->with('success', $this->singular.' dihapus.');
    }

    public function toggle(string $id): RedirectResponse
    {
        abort_unless($this->hasActive, 404);
        $model = ($this->model)::findOrFail($id);
        $turnOn = ! (bool) $model->getAttribute('is_active');
        if (! $turnOn && ($msg = $this->blockDeactivate($model))) {
            return back()->with('error', $msg);
        }
        $model->update(['is_active' => $turnOn]);

        return back();
    }

    /** @param TModel $model */
    protected function blockDeactivate(Model $model): ?string
    {
        return null;
    }

    public function reorder(Request $request): JsonResponse
    {
        abort_unless($this->sortable, 404);
        $ids = array_map('intval', (array) $request->input('ids', []));
        foreach ($ids as $i => $id) {
            ($this->model)::whereKey($id)->update([$this->sortColumn => $i + 1]);
        }
        Content::bump();

        return response()->json(['ok' => true]);
    }
}

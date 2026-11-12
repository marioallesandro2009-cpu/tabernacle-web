<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\MediaService;
use App\Support\MediaInfo;
use App\Support\MediaUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    public function __construct(protected MediaService $media) {}

    /** JSON list used by the picker dialog and the library page. */
    public function list(Request $request): JsonResponse
    {
        $q = Media::query()->latest('id');
        if ($kind = $request->query('kind')) {
            $q->where('kind', $kind);
        }
        if ($folder = $request->query('folder')) {
            $q->where('folder', $folder);
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$term%")->orWhere('alt', 'like', "%$term%"));
        }
        if ($request->boolean('unused')) {
            $q->whereNotIn('id', MediaUsage::usedIds());
        }
        $page = $q->paginate(36)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn (Media $m) => MediaInfo::row($m))->values(),
            'next' => $page->nextPageUrl(), 'total' => $page->total(),
        ]);
    }

    public function index(): Response
    {
        return Inertia::render('Admin/Media', [
            'folders' => Media::query()->select('folder')->distinct()->orderBy('folder')->pluck('folder'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:51200'],
            'folder' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9\-_]+$/i'],
            'alt' => ['nullable', 'string', 'max:255'],
        ], ['files.*.max' => 'Ukuran file maksimal 50 MB.']);

        $out = [];
        $errors = [];
        foreach ($request->file('files') as $file) {
            $check = $this->media->validate($file);
            if (! $check['ok']) {
                $errors[] = $file->getClientOriginalName().': '.$check['error'];

                continue;
            }
            try {
                $m = $this->media->store($file, (string) ($request->input('folder') ?: 'umum'), $request->input('alt'));
                $out[] = MediaInfo::row($m);
            } catch (\Throwable $e) {
                report($e);
                $errors[] = $file->getClientOriginalName().': gagal diproses.';
            }
        }

        return response()->json(['data' => $out, 'errors' => $errors], ($out || ! $errors) ? 200 : 422);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $m = Media::findOrFail($id);
        $data = $request->validate(
            ['alt' => ['required', 'string', 'max:255'], 'name' => ['nullable', 'string', 'max:255']],
            ['alt.required' => 'Teks alternatif (alt) wajib diisi untuk aksesibilitas dan SEO.']
        );
        $m->update(array_filter($data, fn ($v) => $v !== null));

        return response()->json(MediaInfo::row($m->fresh()));
    }

    public function usage(string $id): JsonResponse
    {
        return response()->json(['usage' => MediaUsage::of((int) $id)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $m = Media::findOrFail($id);
        $usage = MediaUsage::of($m->id);
        if ($usage) {
            return response()->json(['message' => 'Media masih dipakai di: '.implode(', ', array_slice($usage, 0, 5)).'.'], 422);
        }
        $this->media->delete($m);

        return response()->json(['ok' => true]);
    }

    /** Delete everything nobody uses. */
    public function purgeUnused(): RedirectResponse
    {
        $used = MediaUsage::usedIds();
        $n = 0;
        Media::query()->whereNotIn('id', $used)->each(function (Media $m) use (&$n) {
            $this->media->delete($m);
            $n++;
        });

        return back()->with('success', $n.' media yang tidak dipakai dihapus.');
    }
}

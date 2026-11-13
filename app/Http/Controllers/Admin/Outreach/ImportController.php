<?php

namespace App\Http\Controllers\Admin\Outreach;

use App\Http\Controllers\Controller;
use App\Jobs\Outreach\ImportContactsJob;
use App\Models\ActivityLog;
use App\Models\Outreach\ContactImport;
use App\Models\Outreach\Segment;
use App\Services\Outreach\ContactImporter;
use App\Services\Outreach\SheetReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Upload, map the columns, import. The list is read by the queue so a big file cannot time out. */
class ImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Admin/Outreach/ImportUpload', [
            'recent' => ContactImport::latest('id')->limit(5)->get()->map(fn ($i) => $this->summary($i)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt']], [
            'file.required' => 'Pilih file Excel atau CSV dulu.',
            'file.mimes' => 'File harus berformat .xlsx, .xls, atau .csv.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);
        $file = $request->file('file');
        $path = $file->storeAs('outreach-imports', Str::random(24).'.'.$file->getClientOriginalExtension(), 'local');
        $import = ContactImport::create(['user_id' => $request->user()->id, 'filename' => mb_substr($file->getClientOriginalName(), 0, 250), 'path' => $path]);

        return redirect('/admin/outreach/import/'.$import->id.'/mapping');
    }

    public function mapping(string $id, ContactImporter $importer, SheetReader $reader): Response|RedirectResponse
    {
        $import = ContactImport::findOrFail($id);
        if ($import->status !== 'uploaded') {
            return redirect('/admin/outreach/import/'.$import->id);
        }
        try {
            $preview = $reader->preview(Storage::disk('local')->path($import->path));
        } catch (Throwable) {
            return redirect('/admin/outreach/import')->with('error', 'File tidak bisa dibaca. Pastikan formatnya Excel (.xlsx) atau CSV yang benar.');
        }
        if (empty($preview['headers'])) {
            return redirect('/admin/outreach/import')->with('error', 'File kosong atau baris pertama (judul kolom) tidak ada.');
        }

        return Inertia::render('Admin/Outreach/ImportMapping', [
            'batch' => ['id' => $import->id, 'filename' => $import->filename],
            'headers' => $preview['headers'], 'rows' => $preview['rows'],
            'guess' => $importer->guess($preview['headers']),
            'fields' => collect(ContactImporter::FIELDS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
            'segments' => Segment::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function run(Request $request, string $id): RedirectResponse
    {
        $import = ContactImport::findOrFail($id);
        abort_unless($import->status === 'uploaded', 422);
        $data = $request->validate([
            'mapping' => ['required', 'array'], 'mapping.*' => ['nullable', 'integer', 'min:0', 'max:200'],
            'default_segment_id' => ['nullable', 'integer', 'exists:outreach_segments,id'],
        ]);
        if (! array_key_exists('email', $data['mapping']) || $data['mapping']['email'] === null) {
            return back()->withErrors(['mapping' => 'Pilih kolom mana yang berisi email.']);
        }
        $mapping = collect($data['mapping'])->only(array_keys(ContactImporter::FIELDS))->all();
        $import->update(['mapping' => $mapping, 'default_segment_id' => $data['default_segment_id'] ?? null, 'status' => 'processing', 'started_at' => now()]);
        ActivityLog::record('create', 'Impor kontak', $import->id, $import->filename);
        ImportContactsJob::dispatch($import->id);

        return redirect('/admin/outreach/import/'.$import->id);
    }

    /** Progress and result. The page reloads itself while the import is still running. */
    public function show(string $id): Response
    {
        $import = ContactImport::findOrFail($id);

        return Inertia::render('Admin/Outreach/ImportResult', ['batch' => $this->summary($import)]);
    }

    /** @return array<string, mixed> */
    protected function summary(ContactImport $i): array
    {
        return [
            'id' => $i->id, 'filename' => $i->filename, 'status' => $i->status, 'error' => $i->error,
            'total' => $i->total_rows, 'created' => $i->created_count, 'duplicates' => $i->duplicate_count,
            'empty' => $i->empty_count, 'suppressed' => $i->suppressed_count,
            'at' => $i->created_at?->locale('id')->diffForHumans(),
        ];
    }
}

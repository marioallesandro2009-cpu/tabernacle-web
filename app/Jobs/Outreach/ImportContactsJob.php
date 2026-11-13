<?php

namespace App\Jobs\Outreach;

use App\Models\Outreach\ContactImport;
use App\Services\Outreach\ContactImporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportContactsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 280;

    public int $tries = 1;

    public function __construct(public int $importId) {}

    public function handle(ContactImporter $importer): void
    {
        $import = ContactImport::find($this->importId);
        if (! $import || $import->status !== 'processing') {
            return;
        }
        try {
            $importer->run($import, Storage::disk('local')->path($import->path));
            $import->update(['status' => 'done', 'finished_at' => now()]);
            ValidateContactsJob::dispatch();
        } catch (Throwable $e) {
            report($e);
            $import->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 400), 'finished_at' => now()]);
        } finally {
            // the uploaded list holds personal data, so it is not kept once it has been read
            Storage::disk('local')->delete($import->path);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(protected DatabaseBackup $backup) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Backup', ['files' => $this->backup->list()]);
    }

    public function store(): RedirectResponse
    {
        try {
            $path = $this->backup->create();
            $this->backup->prune();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'Backup gagal: '.mb_substr($e->getMessage(), 0, 200));
        }
        ActivityLog::record('backup', 'Backup', null, basename($path));

        return back()->with('success', 'Backup dibuat: '.basename($path));
    }

    public function download(string $name): BinaryFileResponse
    {
        abort_unless(preg_match('/^backup-\d{8}-\d{6}\.sql\.gz$/', $name) === 1, 404);
        $path = $this->backup->dir().DIRECTORY_SEPARATOR.$name;
        abort_unless(File::exists($path), 404);
        ActivityLog::record('download', 'Backup', null, $name);

        return response()->download($path);
    }

    public function destroy(string $name): RedirectResponse
    {
        abort_unless(preg_match('/^backup-\d{8}-\d{6}\.sql\.gz$/', $name) === 1, 404);
        File::delete($this->backup->dir().DIRECTORY_SEPARATOR.$name);

        return back()->with('success', 'Backup dihapus.');
    }
}

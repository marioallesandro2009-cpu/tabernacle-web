<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Plain-PHP SQL dump (shared hosting often has no mysqldump). Files go to
 * storage/app/backups, which is outside the web root.
 */
class DatabaseBackup
{
    public function dir(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /** @return string absolute path of the new file */
    public function create(): string
    {
        $path = $this->dir().DIRECTORY_SEPARATOR.'backup-'.now()->format('Ymd-His').'.sql.gz';
        $gz = gzopen($path, 'wb9');
        if (! $gz) {
            throw new \RuntimeException('Tidak bisa menulis file backup.');
        }
        $w = fn (string $s) => gzwrite($gz, $s);
        $w('-- Backup '.config('app.name').' '.now()->toDateTimeString()."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");

        $pdo = DB::connection()->getPdo();
        $tables = collect(DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"'))->map(fn ($r) => array_values((array) $r)[0]);
        foreach ($tables as $table) {
            $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
            $w("DROP TABLE IF EXISTS `$table`;\n".array_values($create)[1].";\n\n");
            $offset = 0;
            do {
                $rows = DB::table($table)->offset($offset)->limit(500)->get();
                if ($rows->isEmpty()) {
                    break;
                }
                $cols = '`'.implode('`,`', array_keys((array) $rows->first())).'`';
                $values = $rows->map(fn ($r) => '('.implode(',', array_map(
                    fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)),
                    array_values((array) $r)
                )).')')->implode(",\n");
                $w("INSERT INTO `$table` ($cols) VALUES\n$values;\n");
                $offset += 500;
            } while ($rows->count() === 500);
            $w("\n");
        }
        $w("SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        return $path;
    }

    /** Keep only the newest $keep files. */
    public function prune(int $keep = 8): int
    {
        $files = collect(File::files($this->dir()))->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))->sortByDesc(fn ($f) => $f->getMTime())->values();
        $old = $files->slice($keep);
        $old->each(fn ($f) => File::delete($f->getPathname()));

        return $old->count();
    }

    /** @return array<int, array{name: string, size: string, at: string}> */
    public function list(): array
    {
        return collect(File::files($this->dir()))->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql.gz'))
            ->sortByDesc(fn ($f) => $f->getMTime())->values()->map(fn ($f) => [
                'name' => $f->getFilename(), 'size' => number_format($f->getSize() / 1024, 0, ',', '.').' KB',
                'at' => date('d M Y H:i', $f->getMTime()),
            ])->all();
    }
}

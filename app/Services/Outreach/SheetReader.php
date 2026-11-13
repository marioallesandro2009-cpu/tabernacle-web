<?php

namespace App\Services\Outreach;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Facades\Excel;

/** Reads an uploaded .xlsx / .xls / .csv in chunks, so a big list does not fill the memory. */
class SheetReader
{
    /**
     * First rows for the mapping screen.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<int, string>>}
     */
    public function preview(string $path, int $rows = 8): array
    {
        $all = [];
        $this->read($path, function (array $chunk) use (&$all) {
            $all = array_merge($all, $chunk);
        }, $rows + 1);
        $headers = array_map(fn ($h) => $h === '' ? '(kolom tanpa judul)' : $h, $all[0] ?? []);

        return ['headers' => $headers, 'rows' => array_slice($all, 1, $rows)];
    }

    /**
     * Calls $each with chunks of rows (arrays of strings). The first row of the file is the
     * header and arrives as the first element of the first chunk.
     *
     * @param  callable(array<int, array<int, string>>): void  $each
     */
    public function read(string $path, callable $each, ?int $limit = null): void
    {
        $delimiter = $this->delimiter($path);
        $import = new class($each, $delimiter, $limit) implements ToCollection, WithChunkReading, WithCustomCsvSettings, WithLimit
        {
            public function __construct(private $each, private string $delimiter, private ?int $limit) {}

            /** @param Collection<int, Collection<int, mixed>> $rows */
            public function collection(Collection $rows): void
            {
                $out = [];
                foreach ($rows as $row) {
                    $out[] = array_map(fn ($v) => SheetReader::cell($v), $row->toArray());
                }
                ($this->each)($out);
            }

            public function chunkSize(): int
            {
                return 500;
            }

            public function limit(): int
            {
                return $this->limit ?? PHP_INT_MAX;
            }

            /** @return array<string, mixed> */
            public function getCsvSettings(): array
            {
                return ['delimiter' => $this->delimiter, 'input_encoding' => 'UTF-8'];
            }
        };
        Excel::import($import, $path);
    }

    /** One cell as trimmed text; numbers such as phone numbers lose a pointless ".0". */
    public static function cell(mixed $v): string
    {
        if (is_float($v) && floor($v) === $v && abs($v) < 1e15) {
            return (string) (int) $v;
        }

        return trim((string) ($v ?? ''));
    }

    /** CSV exported by Indonesian Excel uses a semicolon instead of a comma. */
    protected function delimiter(string $path): string
    {
        if (! preg_match('/\.(csv|txt)$/i', $path)) {
            return ',';
        }
        $handle = fopen($path, 'rb');
        $line = (string) fgets($handle);
        fclose($handle);
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}

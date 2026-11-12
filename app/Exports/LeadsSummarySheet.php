<?php

namespace App\Exports;

use App\Models\Lead;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadsSummarySheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** @param array<string, mixed> $s */
    public function __construct(protected array $s) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function array(): array
    {
        $rows = [['Ringkasan leads'], ['Periode', $this->s['range']], ['Total lead', $this->s['total']], ['Dari form', $this->s['form']], ['Klik WhatsApp', $this->s['wa_click']], [], ['Per status']];
        foreach ($this->s['by_status'] as $k => $v) {
            $rows[] = [Lead::STATUSES[$k] ?? $k, $v];
        }
        $rows[] = [];
        $rows[] = ['Per sumber'];
        foreach ($this->s['by_source'] as $k => $v) {
            $rows[] = [$k, $v];
        }
        $rows[] = [];
        $rows[] = ['10 paket teratas'];
        foreach ($this->s['by_tour'] as $k => $v) {
            $rows[] = [$k, $v];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'size' => 14]]];
    }
}

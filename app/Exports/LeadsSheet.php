<?php

namespace App\Exports;

use App\Models\Lead;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LeadsSheet implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    /** @param Collection<int, Lead> $leads */
    public function __construct(protected Collection $leads) {}

    public function title(): string
    {
        return 'Leads';
    }

    public function array(): array
    {
        $rows = [['Tanggal', 'Jenis', 'Nama', 'Telepon', 'Email', 'Paket', 'Pax', 'Rencana berangkat', 'Pesan', 'Status', 'Ditangani', 'Sumber', 'UTM source', 'UTM medium', 'UTM campaign', 'Halaman', 'Catatan']];
        foreach ($this->leads as $l) {
            $rows[] = [
                $l->created_at?->format('Y-m-d H:i'), $l->type === 'wa_click' ? 'Klik WhatsApp' : 'Form', $this->safe($l->name), $this->safe($l->phone), $this->safe($l->email),
                $this->safe($l->tour?->name ?: $l->tour_name), $l->pax, $l->plan_date?->format('Y-m-d'), $this->safe($l->message),
                Lead::STATUSES[$l->status] ?? $l->status, $l->assignee?->name, $l->source, $l->utm_source, $l->utm_medium, $l->utm_campaign, $l->page_url, $this->safe($l->notes),
            ];
        }

        return $rows;
    }

    /** Stops spreadsheet formula injection from visitor-typed text. */
    protected function safe(?string $v): ?string
    {
        return ($v !== null && $v !== '' && str_contains('=+-@', $v[0])) ? "'".$v : $v;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

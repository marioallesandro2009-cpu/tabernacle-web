<?php

namespace App\Exports;

use App\Models\Lead;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Leads sheet + summary sheet. CSV only gets the first one. */
class LeadsExport implements WithMultipleSheets
{
    /**
     * @param  Collection<int, Lead>  $leads
     * @param  array<string, mixed>  $summary
     */
    public function __construct(protected Collection $leads, protected array $summary) {}

    public function sheets(): array
    {
        return [new LeadsSheet($this->leads), new LeadsSummarySheet($this->summary)];
    }
}

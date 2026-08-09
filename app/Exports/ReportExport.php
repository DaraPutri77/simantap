<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportExport implements WithMultipleSheets
{
    use Exportable;

    /** @param array<string, mixed> $report */
    public function __construct(private readonly array $report) {}

    /** @return list<object> */
    public function sheets(): array
    {
        return [new ReportSummarySheet($this->report), new ReportDataSheet($this->report)];
    }
}

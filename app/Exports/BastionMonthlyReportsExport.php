<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class BastionMonthlyReportsExport implements WithMultipleSheets
{
    public function __construct(private readonly array $reports) {}

    public function sheets(): array
    {
        $sheets = [];
        foreach ($this->reports as $month => $rows) {
            $sheets[] = new BastionReportExport($rows, $month);
        }

        return $sheets;
    }
}

<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class ReportesExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function view(): View
    {
        return view('reportes.report-excel', $this->reportData);
    }

    public function title(): string
    {
        return 'Reportes';
    }
}


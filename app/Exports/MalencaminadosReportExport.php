<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MalencaminadosReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function sheets(): array
    {
        return [
            new MalencaminadosResumenSheetExport($this->reportData),
            new MalencaminadosDetalleSheetExport($this->reportData),
        ];
    }
}

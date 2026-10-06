<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PaqueteriaFlowExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function sheets(): array
    {
        return [
            new PaqueteriaFlowDetailsSheet($this->reportData),
            new PaqueteriaFlowResolutionSheet($this->reportData),
            new PaqueteriaFlowDispatchSheet($this->reportData),
            new PaqueteriaFlowTransitReceiptSheet($this->reportData),
            new PaqueteriaFlowCourierResolutionSheet($this->reportData),
        ];
    }
}

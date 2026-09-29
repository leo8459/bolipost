<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AreaContratosEntregadosExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Builder $query,
        private readonly Collection $summaryRows,
        private readonly array $totals,
        private readonly array $filters = []
    ) {
    }

    public function sheets(): array
    {
        $sheets = [];
        $originExpression = "COALESCE(NULLIF(TRIM(origen), ''), 'SIN ORIGEN')";

        foreach ($this->summaryRows as $summaryRow) {
            $origin = (string) $summaryRow->origen;
            $query = (clone $this->query)
                ->whereRaw("{$originExpression} = ?", [$origin])
                ->orderBy('fecha_recojo')
                ->orderBy('id');

            $sheets[] = new AreaContratosEntregadosSheetExport(
                $origin,
                $query,
                (int) $summaryRow->total,
                (float) $summaryRow->peso,
                (float) $summaryRow->subtotal,
                $this->filters
            );
        }

        if ($sheets === []) {
            $sheets[] = new AreaContratosEntregadosSheetExport(
                'SIN DATOS',
                (clone $this->query)->whereRaw('1 = 0'),
                0,
                0.0,
                0.0,
                $this->filters
            );
        }

        $sheets[] = new AreaContratosEntregadosResumenSheetExport(
            $this->summaryRows->map(fn ($row) => [
                'origin' => (string) $row->origen,
                'weight' => (float) $row->peso,
                'count' => (int) $row->total,
                'subtotal' => (float) $row->subtotal,
            ])->all(),
            $this->totals,
            $this->filters
        );

        return $sheets;
    }
}

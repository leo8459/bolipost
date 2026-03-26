<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AreaContratosEntregadosExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $filters = []
    ) {
    }

    public function sheets(): array
    {
        $groups = $this->rows
            ->groupBy(fn ($row) => $this->normalizeOrigin((string) ($row->origen ?? '')));

        if ($groups->isEmpty()) {
            return [
                new AreaContratosEntregadosSheetExport(
                    'SIN DATOS',
                    collect(),
                    $this->filters
                ),
                new AreaContratosEntregadosResumenSheetExport(
                    $this->rows,
                    $this->filters
                ),
            ];
        }

        $detailSheets = $groups
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Collection $items, string $origin) => new AreaContratosEntregadosSheetExport(
                $origin,
                $items->values(),
                $this->filters
            ))
            ->values()
            ->all();

        $detailSheets[] = new AreaContratosEntregadosResumenSheetExport(
            $this->rows,
            $this->filters
        );

        return $detailSheets;
    }

    private function normalizeOrigin(string $origin): string
    {
        $origin = trim($origin);

        return $origin !== '' ? $origin : 'SIN ORIGEN';
    }
}

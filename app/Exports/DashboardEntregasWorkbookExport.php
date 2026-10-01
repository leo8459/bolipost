<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DashboardEntregasWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function sheets(): array
    {
        $entregadores = collect($this->reportData['entregadores'] ?? []);
        $porDepartamento = $entregadores->groupBy(function ($item) {
            $departamento = strtoupper(trim((string) ($item->ciudad ?? '')));

            return $departamento !== '' ? $departamento : 'SIN DEPARTAMENTO';
        });

        $departamentoSeleccionado = strtoupper(trim((string) ($this->reportData['departamentoCartero'] ?? '')));
        $departamentosDisponibles = $departamentoSeleccionado !== ''
            ? [$departamentoSeleccionado]
            : ($this->reportData['departamentosDisponibles'] ?? []);

        $departamentos = collect($departamentosDisponibles)
            ->map(fn ($departamento) => strtoupper(trim((string) $departamento)))
            ->concat($porDepartamento->keys())
            ->filter()
            ->unique()
            ->values();

        $sheets = [new DashboardEntregasResumenExport($this->reportData)];

        foreach ($departamentos as $departamento) {
            $sheetData = $this->reportData;
            $sheetData['entregadores'] = $porDepartamento->get($departamento, new Collection())->values();

            $sheets[] = new DashboardEntregasRendimientoExport(
                $sheetData,
                $this->formatSheetTitle($departamento)
            );
        }

        return $sheets;
    }

    private function formatSheetTitle(string $departamento): string
    {
        $title = mb_convert_case(mb_strtolower($departamento, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $title = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $title));
        $title = $title !== '' ? $title : 'Sin departamento';

        return mb_substr($title, 0, 31, 'UTF-8');
    }
}

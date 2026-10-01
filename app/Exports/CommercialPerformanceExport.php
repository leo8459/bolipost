<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CommercialPerformanceExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function sheets(): array
    {
        $lineRows = collect($this->reportData['lineRows'] ?? []);
        $serviceRows = collect($this->reportData['serviceRows'] ?? []);
        $totals = $this->reportData['commercialTotals'] ?? [];
        $kpis = $this->reportData['commercialKpis'] ?? [];
        $coverageRows = collect([])
            ->concat(collect(data_get($kpis, 'heatmap.origenes', []))->map(fn (array $row) => [
                'Origen',
                (string) ($row['ubicacion'] ?? ''),
                (int) ($row['cantidad'] ?? 0),
                (float) ($row['peso'] ?? 0),
            ]))
            ->concat(collect(data_get($kpis, 'heatmap.destinos', []))->map(fn (array $row) => [
                'Destino',
                (string) ($row['ubicacion'] ?? ''),
                (int) ($row['cantidad'] ?? 0),
                (float) ($row['peso'] ?? 0),
            ]))
            ->concat(collect(data_get($kpis, 'heatmap.rutas', []))->map(fn (array $row) => [
                'Ruta',
                (string) ($row['ruta'] ?? ''),
                (int) ($row['cantidad'] ?? 0),
                (float) ($row['peso'] ?? 0),
            ]))
            ->values()
            ->all();

        return [
            new CommercialPerformanceSheetExport(
                'Resumen comercial',
                ['Periodo', 'Líneas seleccionadas', 'Total de líneas', 'Registros', 'Entregados', 'No entregados', 'Efectividad %', 'Peso total (kg)', 'Línea con mayor volumen'],
                [[
                    !empty($this->reportData['from']) || !empty($this->reportData['to'])
                        ? (($this->reportData['from'] ?: 'inicio') . ' - ' . ($this->reportData['to'] ?: 'fin'))
                        : 'Todo el historial',
                    !empty($this->reportData['selectedLines']) ? implode(', ', $this->reportData['selectedLines']) : 'Todas',
                    (int) ($totals['lineas'] ?? 0),
                    (int) ($totals['registros'] ?? 0),
                    (int) ($totals['entregados'] ?? 0),
                    (int) ($totals['no_entregados'] ?? 0),
                    (float) data_get($kpis, 'effectiveness.efectividad_pct', 0),
                    (float) ($totals['peso_total'] ?? 0),
                    (string) ($totals['top_linea'] ?? '-'),
                ]]
            ),
            new CommercialPerformanceSheetExport(
                'Líneas de negocio',
                ['#', 'Línea', 'Registros', 'Entregados', 'No entregados', 'Efectividad %', 'Peso (kg)', 'Servicio principal', 'Cantidad servicio principal', 'Último registro'],
                $lineRows->values()->map(fn (array $row, int $index) => [
                    $index + 1,
                    (string) ($row['linea'] ?? ''),
                    (int) ($row['cantidad'] ?? 0),
                    (int) ($row['entregados'] ?? 0),
                    (int) ($row['no_entregados'] ?? 0),
                    (int) ($row['cantidad'] ?? 0) > 0 ? round(((int) ($row['entregados'] ?? 0) / (int) $row['cantidad']) * 100, 2) : 0,
                    (float) ($row['peso'] ?? 0),
                    (string) ($row['top_servicio'] ?? ''),
                    (int) ($row['top_servicio_cantidad'] ?? 0),
                    (string) ($row['ultimo_registro'] ?? ''),
                ])->all()
            ),
            new CommercialPerformanceSheetExport(
                'Servicios',
                ['#', 'Línea', 'Servicio', 'Registros', 'Entregados', 'No entregados', 'Peso (kg)', 'Último registro'],
                $serviceRows->values()->map(fn (array $row, int $index) => [
                    $index + 1,
                    (string) ($row['linea'] ?? ''),
                    (string) ($row['servicio'] ?? ''),
                    (int) ($row['cantidad'] ?? 0),
                    (int) ($row['entregados'] ?? 0),
                    (int) ($row['no_entregados'] ?? 0),
                    (float) ($row['peso'] ?? 0),
                    (string) ($row['ultimo_registro'] ?? ''),
                ])->all()
            ),
            new CommercialPerformanceSheetExport(
                'Efectividad',
                ['Línea', 'Total', 'Entregados', 'Devoluciones', 'Rezago', 'Pendientes', 'Efectividad %'],
                collect(data_get($kpis, 'effectiveness.rows', []))->map(fn (array $row) => [
                    (string) ($row['linea'] ?? ''),
                    (int) ($row['total'] ?? 0),
                    (int) ($row['entregados'] ?? 0),
                    (int) ($row['devoluciones'] ?? 0),
                    (int) ($row['rezago'] ?? 0),
                    (int) ($row['pendientes'] ?? 0),
                    (float) ($row['efectividad_pct'] ?? 0),
                ])->all()
            ),
            new CommercialPerformanceSheetExport(
                'Tiempos SLA',
                ['Línea', 'Entregados', 'Promedio (horas)', 'Promedio', 'Mínimo', 'Máximo', 'Peso (kg)'],
                collect(data_get($kpis, 'sla.rows', []))->map(fn (array $row) => [
                    (string) ($row['linea'] ?? ''),
                    (int) ($row['entregados'] ?? 0),
                    (float) ($row['promedio_horas'] ?? 0),
                    (string) ($row['promedio'] ?? '-'),
                    (string) ($row['minimo'] ?? '-'),
                    (string) ($row['maximo'] ?? '-'),
                    (float) ($row['peso'] ?? 0),
                ])->all()
            ),
            new CommercialPerformanceSheetExport(
                'Cobertura operativa',
                ['Tipo', 'Ubicación o ruta', 'Registros', 'Peso (kg)'],
                $coverageRows
            ),
        ];
    }
}

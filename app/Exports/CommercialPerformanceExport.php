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

        return [
            new CommercialPerformanceSheetExport(
                'Resumen comercial',
                ['Rango', 'Lineas', 'Registros', 'Entregados', 'No entregados', 'Peso total', 'Ingresos Bs', 'Top linea'],
                [[
                    !empty($this->reportData['from']) || !empty($this->reportData['to'])
                        ? (($this->reportData['from'] ?: 'inicio') . ' - ' . ($this->reportData['to'] ?: 'fin'))
                        : 'todos',
                    !empty($this->reportData['selectedLines']) ? implode(', ', $this->reportData['selectedLines']) : 'Todas',
                    (int) ($totals['registros'] ?? 0),
                    (int) ($totals['entregados'] ?? 0),
                    (int) ($totals['no_entregados'] ?? 0),
                    (float) ($totals['peso_total'] ?? 0),
                    (float) ($totals['precio_total'] ?? 0),
                    (string) ($totals['top_linea'] ?? '-'),
                ]]
            ),
            new CommercialPerformanceSheetExport(
                'Lineas negocio',
                ['#', 'Linea', 'Cantidad', 'Entregados', 'No entregados', 'Peso', 'Bs', 'Servicio lider', 'Ultimo registro'],
                $lineRows->values()->map(fn (array $row, int $index) => [
                    $index + 1,
                    (string) ($row['linea'] ?? ''),
                    (int) ($row['cantidad'] ?? 0),
                    (int) ($row['entregados'] ?? 0),
                    (int) ($row['no_entregados'] ?? 0),
                    (float) ($row['peso'] ?? 0),
                    (float) ($row['precio'] ?? 0),
                    (string) ($row['top_servicio'] ?? ''),
                    (string) ($row['ultimo_registro'] ?? ''),
                ])->all()
            ),
            new CommercialPerformanceSheetExport(
                'Servicios',
                ['#', 'Linea', 'Servicio', 'Cantidad', 'Entregados', 'No entregados', 'Peso', 'Bs', 'Ultimo registro'],
                $serviceRows->values()->map(fn (array $row, int $index) => [
                    $index + 1,
                    (string) ($row['linea'] ?? ''),
                    (string) ($row['servicio'] ?? ''),
                    (int) ($row['cantidad'] ?? 0),
                    (int) ($row['entregados'] ?? 0),
                    (int) ($row['no_entregados'] ?? 0),
                    (float) ($row['peso'] ?? 0),
                    (float) ($row['precio'] ?? 0),
                    (string) ($row['ultimo_registro'] ?? ''),
                ])->all()
            ),
            new CommercialPerformanceSheetExport(
                'KPI Efectividad',
                ['Linea', 'Total', 'Entregados', 'Devoluciones', 'Rezago', 'Pendientes', 'Efectividad %'],
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
                'KPI SLA',
                ['Linea', 'Entregados', 'Promedio horas', 'Promedio', 'Minimo', 'Maximo', 'Peso'],
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
                'KPI Presupuesto',
                ['Empresa', 'Codigo cliente', 'Presupuesto', 'Consumido', 'Saldo', 'Ejecucion %', 'Alerta', 'Envios', 'Ultimo registro'],
                collect(data_get($kpis, 'budget.rows', []))->map(fn (array $row) => [
                    (string) ($row['empresa'] ?? ''),
                    (string) ($row['codigo_cliente'] ?? ''),
                    (float) ($row['presupuesto'] ?? 0),
                    (float) ($row['consumido'] ?? 0),
                    (float) ($row['saldo'] ?? 0),
                    (float) ($row['ejecucion_pct'] ?? 0),
                    (string) ($row['alerta'] ?? ''),
                    (int) ($row['envios'] ?? 0),
                    (string) ($row['ultimo_registro'] ?? ''),
                ])->all()
            ),
            new CommercialPerformanceSheetExport(
                'KPI Heatmap',
                ['Tipo', 'Origen', 'Destino', 'Cantidad', 'Peso', 'Bs'],
                collect([])
                    ->concat(collect(data_get($kpis, 'heatmap.origenes', []))->map(fn (array $row) => [
                        'Origen',
                        (string) ($row['ubicacion'] ?? ''),
                        '',
                        (int) ($row['cantidad'] ?? 0),
                        (float) ($row['peso'] ?? 0),
                        (float) ($row['precio'] ?? 0),
                    ]))
                    ->concat(collect(data_get($kpis, 'heatmap.destinos', []))->map(fn (array $row) => [
                        'Destino',
                        '',
                        (string) ($row['ubicacion'] ?? ''),
                        (int) ($row['cantidad'] ?? 0),
                        (float) ($row['peso'] ?? 0),
                        (float) ($row['precio'] ?? 0),
                    ]))
                    ->concat(collect(data_get($kpis, 'heatmap.rutas', []))->map(function (array $row) {
                        $ruta = (string) ($row['ruta'] ?? '');
                        [$origen, $destino] = array_pad(explode(' -> ', $ruta, 2), 2, '');

                        return [
                            'Ruta',
                            $origen,
                            $destino,
                            (int) ($row['cantidad'] ?? 0),
                            (float) ($row['peso'] ?? 0),
                            (float) ($row['precio'] ?? 0),
                        ];
                    }))
                    ->values()
                    ->all()
            ),
            new CommercialPerformanceSheetExport(
                'KPI Cobranza',
                ['Empresa', 'Facturado', 'Cobrado', 'Pendiente', 'Cobranza %', 'Observacion'],
                collect(data_get($kpis, 'collections.rows', []))->map(fn (array $row) => [
                    (string) ($row['empresa'] ?? ''),
                    (float) ($row['facturado'] ?? 0),
                    (float) ($row['cobrado'] ?? 0),
                    (float) ($row['pendiente'] ?? 0),
                    (float) ($row['cobranza_pct'] ?? 0),
                    (string) ($row['observacion'] ?? ''),
                ])->all()
            ),
        ];
    }
}

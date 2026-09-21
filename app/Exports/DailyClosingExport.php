<?php

namespace App\Exports;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class DailyClosingExport implements WithMultipleSheets
{
    public const DEPARTMENTS = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'CHUQUISACA', 'TARIJA', 'BENI', 'PANDO'];

    public function __construct(private readonly array $report) {}

    public function sheets(): array
    {
        $summary = [
            ['Cierre diario', $this->report['date'].' (Bolivia)'],
            ['Servicio', 'Registrados', 'Entregados', 'Movimientos', 'Envíos con movimiento'],
        ];
        $groups = array_fill_keys(self::DEPARTMENTS, []);
        $groups['SIN DEPARTAMENTO'] = [];

        foreach ($this->report['modules'] as $module) {
            $summary[] = [
                $module['name'],
                $module['registered'],
                $module['delivered'],
                $module['movements']->count(),
                $module['moved_packages'],
            ];

            foreach ($module['movements'] as $movement) {
                $department = $this->departmentFor($movement->destino);
                $groups[$department][] = [
                    $module['name'],
                    $movement->codigo,
                    $movement->origen ?: 'Sin origen',
                    trim((string) ($movement->provincia_origen ?? '')) ?: 'Sin provincia registrada',
                    $movement->destino ?: 'Sin destino',
                    trim((string) ($movement->provincia_destino ?? '')) ?: 'Sin provincia registrada',
                    $movement->nombre_evento ?? 'Evento '.$movement->evento_id,
                    $movement->estado ?: 'Sin estado',
                    $movement->user_name ?: 'Usuario no disponible',
                    $movement->cartero ?: 'Sin cartero',
                    $movement->event_date,
                ];
            }
        }

        $summary[] = [];
        $summary[] = ['Departamento de destino', 'Movimientos contratos', 'Movimientos EMS', 'Total'];
        foreach ($groups as $department => $rows) {
            $contracts = count(array_filter($rows, fn (array $row) => $row[0] === 'Contratos'));
            $ems = count(array_filter($rows, fn (array $row) => $row[0] === 'EMS'));
            $summary[] = [$department, $contracts, $ems, count($rows)];
        }

        $sheets = [new DailyClosingSheet('Resumen', $summary, 2)];
        foreach ($groups as $department => $rows) {
            if ($rows === []) {
                continue;
            }
            $sheets[] = new DailyClosingSheet($department, [
                ['Servicio', 'Código', 'Origen', 'Provincia de origen', 'Destino', 'Provincia de destino', 'Evento', 'Estado actual', 'Usuario', 'Cartero actual', 'Fecha y hora (Bolivia)'],
                ...$rows,
            ]);
        }

        return $sheets;
    }

    private function departmentFor(?string $destination): string
    {
        $normalized = strtoupper(trim(Str::ascii((string) $destination)));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return match ($normalized) {
            'SUCRE' => 'CHUQUISACA',
            'TRINIDAD' => 'BENI',
            'COBIJA' => 'PANDO',
            'EL ALTO' => 'LA PAZ',
            default => in_array($normalized, self::DEPARTMENTS, true) ? $normalized : 'SIN DEPARTAMENTO',
        };
    }
}

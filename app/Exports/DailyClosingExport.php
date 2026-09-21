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
        $summary = [['Cierre diario', $this->report['cutoff'].' (Bolivia)'],
            ['Servicio', 'Registrados hoy', 'Entregados hoy', 'Pendientes', 'Sin cartero activo']];
        $groups = array_fill_keys(self::DEPARTMENTS, []);
        $groups['SIN DEPARTAMENTO'] = [];
        $historyRows = [['Servicio', 'Código', 'Origen', 'Provincia de origen', 'Destino', 'Provincia de destino', 'Estado o evento registrado', 'Fecha y hora (Bolivia)']];
        foreach ($this->report['modules'] as $module) {
            $summary[] = [$module['name'], $module['registered'], $module['delivered'], $module['pending']->count(), $module['unassigned']];
            foreach ($module['pending'] as $row) {
                $destination = strtoupper(trim(Str::ascii((string) $row->destino)));
                $destination = preg_replace('/\s+/', ' ', $destination);
                $department = match ($destination) {
                    'SUCRE' => 'CHUQUISACA',
                    'TRINIDAD' => 'BENI',
                    'COBIJA' => 'PANDO',
                    'EL ALTO' => 'LA PAZ',
                    default => in_array($destination, self::DEPARTMENTS, true) ? $destination : 'SIN DEPARTAMENTO',
                };
                $history = $row->history ?? [];
                $historyText = implode("\n", array_map(fn ($event) => $event['date'].' · '.$this->eventWithUser($event), $history));
                if (mb_strlen($historyText) > 32000) {
                    $historyText = mb_substr($historyText, 0, 31900)."\nHistorial completo en la hoja Historial.";
                }
                $provinceOrigin = trim((string) ($row->provincia_origen ?? '')) ?: 'Sin provincia registrada';
                $provinceDestination = trim((string) ($row->provincia_destino ?? '')) ?: 'Sin provincia registrada';
                $groups[$department][] = [$module['name'], $row->codigo, $row->origen ?? 'Sin origen', $provinceOrigin, $row->destino, $provinceDestination, $row->estado ?? 'Sin estado', $row->cartero ?? 'Sin cartero activo', $row->created_at, $historyText ?: 'Sin eventos registrados'];
                foreach ($history as $event) {
                    $historyRows[] = [$module['name'], $row->codigo, $row->origen ?? 'Sin origen', $provinceOrigin, $row->destino, $provinceDestination, $this->eventWithUser($event), $event['date']];
                }
            }
        }
        $summary[] = [];
        $summary[] = ['Departamento de destino', 'Pendientes contratos', 'Pendientes EMS', 'Total'];
        foreach ($groups as $department => $rows) {
            $contracts = count(array_filter($rows, fn ($row) => $row[0] === 'Contratos'));
            $ems = count(array_filter($rows, fn ($row) => $row[0] === 'EMS'));
            $summary[] = [$department, $contracts, $ems, count($rows)];
        }
        $sheets = [new DailyClosingSheet('Resumen', $summary, 2)];
        foreach ($groups as $department => $rows) {
            if ($department === 'SIN DEPARTAMENTO' && $rows === []) {
                continue;
            }
            $sheets[] = new DailyClosingSheet($department, [
                ['Servicio', 'Código', 'Origen', 'Provincia de origen', 'Destino', 'Provincia de destino', 'Estado actual', 'Cartero', 'Fecha de registro', 'Historial de estados y eventos'],
                ...$rows,
            ]);
        }
        $sheets[] = new DailyClosingSheet('Historial', $historyRows);

        return $sheets;
    }

    private function eventWithUser(array $event): string
    {
        $user = trim((string) ($event['user'] ?? '')) ?: 'Usuario no disponible';

        return $event['event'].' ('.$user.')';
    }
}

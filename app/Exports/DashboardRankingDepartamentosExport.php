<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class DashboardRankingDepartamentosExport implements FromCollection, ShouldAutoSize, WithHeadings, WithTitle
{
    public function __construct(
        private readonly array $reportData
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->reportData['rankingDepartamentos'] ?? [])
            ->map(function ($item) {
                $modulos = (array) ($item->entregados_por_modulo ?? []);

                return [
                    'puesto' => (int) ($item->puesto ?? 0),
                    'departamento' => (string) ($item->departamento ?? ''),
                    'registrados' => (int) ($item->total ?? 0),
                    'entregados' => (int) ($item->entregados ?? 0),
                    'transito' => (int) ($item->transito ?? 0),
                    'pendientes' => (int) ($item->pendientes ?? 0),
                    'total_nacional' => (int) ($item->total_nacional ?? 0),
                    'parte_nacional_porcentaje' => (float) ($item->participacion_nacional ?? 0),
                    'cumplimiento_porcentaje' => (float) ($item->cumplimiento ?? 0),
                    'aporte_entregado_nacional_porcentaje' => (float) ($item->aporte_entregado_nacional ?? 0),
                    'valor_ranking_porcentaje' => (float) ($item->puntaje_ranking ?? 0),
                    'peso_cumplimiento_porcentaje' => (int) ($item->ranking_cumplimiento_peso ?? 70),
                    'peso_parte_nacional_porcentaje' => (int) ($item->ranking_participacion_peso ?? 30),
                    'quien_entrega_mas' => (string) ($item->top_entregador ?? ''),
                    'entregas_top_entregador' => (int) ($item->top_entregador_total ?? 0),
                    'ems_entregados' => (int) ($modulos['EMS'] ?? 0),
                    'contratos_entregados' => (int) ($modulos['CONTRATOS'] ?? 0),
                    'certificados_entregados' => (int) ($modulos['CERTIFICADOS'] ?? 0),
                    'ordinarios_entregados' => (int) ($modulos['ORDINARIOS'] ?? 0),
                    'rango' => (string) ($this->reportData['rangoLabel'] ?? ''),
                    'modulos' => collect($this->reportData['modulosSeleccionados'] ?? [])
                        ->map(fn ($key) => $this->reportData['modulosDisponibles'][$key]['label'] ?? strtoupper((string) $key))
                        ->implode(', '),
                    'emitido_en' => now()->format('Y-m-d H:i:s'),
                ];
            });
    }

    public function headings(): array
    {
        return [
            'Puesto',
            'Departamento',
            'Registrados',
            'Entregados',
            'Transito',
            'Pendientes',
            'Total nacional',
            'Parte nacional %',
            'Cumplimiento %',
            'Aporte entregado nacional %',
            'Valor ranking %',
            'Peso cumplimiento %',
            'Peso parte nacional %',
            'Quien entrega mas',
            'Entregas top entregador',
            'EMS entregados',
            'Contratos entregados',
            'Certificados entregados',
            'Ordinarios entregados',
            'Rango',
            'Modulos',
            'Emitido en',
        ];
    }

    public function title(): string
    {
        return 'Ranking departamentos';
    }
}

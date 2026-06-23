<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class VehicleMaintenanceReportController extends Controller
{
    public function show(Request $request, Vehicle $vehicle)
    {
        $vehicle->load([
            'brand:id,nombre',
            'vehicleClass:id,nombre',
            'assignments.driver:id,nombre',
            'maintenanceLogs' => fn ($query) => $query
                ->with('maintenanceType:id,nombre')
                ->orderByDesc('fecha')
                ->orderByDesc('id'),
        ]);

        $currentAssignment = $vehicle->assignments
            ->filter(function ($assignment) {
                $today = now()->toDateString();

                return (bool) ($assignment->activo ?? false)
                    && (!$assignment->fecha_inicio || $assignment->fecha_inicio->toDateString() <= $today)
                    && (!$assignment->fecha_fin || $assignment->fecha_fin->toDateString() >= $today);
            })
            ->sortByDesc(fn ($assignment) => optional($assignment->fecha_inicio)?->timestamp ?? 0)
            ->sortByDesc('id')
            ->first();

        $regional = trim((string) ($request->user()?->sucursal?->departamento
            ?? $request->user()?->ciudad
            ?? ''));

        $pdf = Pdf::loadView('reports.vehicle-maintenance-history', [
            'vehicle' => $vehicle,
            'maintenanceLogs' => $vehicle->maintenanceLogs,
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
            'currentAssignment' => $currentAssignment,
            'regional' => $regional !== '' ? $regional : '-',
        ])->setPaper('letter', 'landscape');

        $filename = sprintf(
            'mantenimientos-%s.pdf',
            strtolower(str_replace(' ', '-', (string) $vehicle->placa))
        );

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}

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
<<<<<<< Updated upstream
            'assignments' => fn ($query) => $query
                ->with([
                    'driver.user.sucursal:id,municipio,departamento',
                ])
                ->where('activo', true)
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('updated_at')
                ->orderByDesc('id'),
=======
            'assignments.driver:id,nombre',
>>>>>>> Stashed changes
            'maintenanceLogs' => fn ($query) => $query
                ->with('maintenanceType:id,nombre')
                ->orderByDesc('fecha')
                ->orderByDesc('id'),
        ]);

<<<<<<< Updated upstream
        $currentAssignment = $vehicle->assignments->first();
        $assignedDriver = $currentAssignment?->driver;
        $assignedUser = $assignedDriver?->user;
        $regional = trim((string) ($assignedUser?->regionalesTexto() ?? ''));

        if ($regional === '') {
            $regional = trim(collect([
                $assignedUser?->sucursal?->departamento,
                $assignedUser?->sucursal?->municipio,
            ])->filter()->implode(' - '));
        }
=======
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
>>>>>>> Stashed changes

        $pdf = Pdf::loadView('reports.vehicle-maintenance-history', [
            'vehicle' => $vehicle,
            'maintenanceLogs' => $vehicle->maintenanceLogs,
            'currentAssignment' => $currentAssignment,
            'assignedDriver' => $assignedDriver,
            'regional' => $regional !== '' ? $regional : null,
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
<<<<<<< Updated upstream
=======
            'currentAssignment' => $currentAssignment,
            'regional' => $regional !== '' ? $regional : '-',
>>>>>>> Stashed changes
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

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
            'assignments' => fn ($query) => $query
                ->with([
                    'driver.user.sucursal:id,municipio,departamento',
                ])
                ->where('activo', true)
                ->orderByDesc('fecha_inicio')
                ->orderByDesc('updated_at')
                ->orderByDesc('id'),
            'maintenanceLogs' => fn ($query) => $query
                ->with('maintenanceType:id,nombre')
                ->orderByDesc('fecha')
                ->orderByDesc('id'),
        ]);

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

        $pdf = Pdf::loadView('reports.vehicle-maintenance-history', [
            'vehicle' => $vehicle,
            'maintenanceLogs' => $vehicle->maintenanceLogs,
            'currentAssignment' => $currentAssignment,
            'assignedDriver' => $assignedDriver,
            'regional' => $regional !== '' ? $regional : null,
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
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

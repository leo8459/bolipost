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
            'maintenanceLogs' => fn ($query) => $query
                ->with('maintenanceType:id,nombre')
                ->orderByDesc('fecha')
                ->orderByDesc('id'),
        ]);

        $pdf = Pdf::loadView('reports.vehicle-maintenance-history', [
            'vehicle' => $vehicle,
            'maintenanceLogs' => $vehicle->maintenanceLogs,
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
        ])->setPaper('letter', 'portrait');

        return $pdf->download(sprintf(
            'mantenimientos-%s.pdf',
            strtolower(str_replace(' ', '-', (string) $vehicle->placa))
        ));
    }
}

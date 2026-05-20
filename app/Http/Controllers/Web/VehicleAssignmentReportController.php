<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class VehicleAssignmentReportController extends Controller
{
    public function exportPdf(Request $request)
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion'], true), 403);

        $payload = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'group_by' => ['required', 'string', 'in:vehicle,driver'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'status' => ['nullable', 'string', 'in:all,active,inactive'],
        ]);

        $from = Carbon::parse((string) $payload['date_from'])->startOfDay();
        $to = Carbon::parse((string) $payload['date_to'])->endOfDay();
        $groupBy = (string) $payload['group_by'];
        $status = (string) ($payload['status'] ?? 'all');
        $vehicleId = (int) ($payload['vehicle_id'] ?? 0);
        $driverId = (int) ($payload['driver_id'] ?? 0);

        $query = VehicleAssignment::query()
            ->with(['driver', 'vehicle.vehicleClass'])
            ->whereNotNull('driver_id')
            ->whereNotNull('vehicle_id')
            ->when($vehicleId > 0, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->when($driverId > 0, fn ($q) => $q->where('driver_id', $driverId))
            ->when($status === 'active', fn ($q) => $q->where('activo', true))
            ->when($status === 'inactive', fn ($q) => $q->where('activo', false))
            ->where(function ($q) use ($to) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $to->toDateString());
            })
            ->where(function ($q) use ($from) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $from->toDateString());
            })
            ->orderBy('fecha_inicio')
            ->orderBy('id');

        $assignments = $query->get();
        $groupedAssignments = $assignments->groupBy(function (VehicleAssignment $assignment) use ($groupBy) {
            return $groupBy === 'vehicle'
                ? (string) ($assignment->vehicle_id ?? 0)
                : (string) ($assignment->driver_id ?? 0);
        });

        $pdf = Pdf::loadView('reports.vehicle-assignments-pdf', [
            'assignments' => $assignments,
            'groupedAssignments' => $groupedAssignments,
            'from' => $from,
            'to' => $to,
            'groupBy' => $groupBy,
            'status' => $status,
            'vehicle' => $vehicleId > 0 ? Vehicle::query()->find($vehicleId) : null,
            'driver' => $driverId > 0 ? Driver::query()->find($driverId) : null,
            'generatedAt' => now(),
        ])->setPaper('A4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-asignaciones-vehiculos-' . now()->format('Ymd-His') . '.pdf');
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}

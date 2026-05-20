<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MaintenanceAppointment;
use App\Models\User;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class MaintenanceApprovedAppointmentReportController extends Controller
{
    public function exportPdf(Request $request)
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion'], true), 403);

        $payload = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'group_by' => ['nullable', 'string', 'in:day,vehicle,driver'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
        ]);

        $from = Carbon::parse((string) $payload['date_from'])->startOfDay();
        $to = Carbon::parse((string) $payload['date_to'])->endOfDay();
        $groupBy = (string) ($payload['group_by'] ?? 'day');
        $vehicleId = (int) ($payload['vehicle_id'] ?? 0);
        $driverId = (int) ($payload['driver_id'] ?? 0);

        $appointments = MaintenanceAppointment::query()
            ->active()
            ->with(['vehicle.brand', 'vehicle.vehicleClass', 'driver', 'tipoMantenimiento', 'approvedBy'])
            ->where('estado', MaintenanceAppointment::STATUS_APPROVED)
            ->when($vehicleId > 0, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when($driverId > 0, fn ($query) => $query->where('driver_id', $driverId))
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('approved_at', [$from, $to])
                    ->orWhere(function ($fallback) use ($from, $to) {
                        $fallback->whereNull('approved_at')->whereBetween('updated_at', [$from, $to]);
                    });
            })
            ->orderByRaw('COALESCE(approved_at, updated_at) ASC')
            ->orderBy('id')
            ->get();

        $groupedAppointments = $appointments->groupBy(function (MaintenanceAppointment $appointment) use ($groupBy) {
            if ($groupBy === 'vehicle') {
                return (string) ($appointment->vehicle_id ?? 0);
            }

            if ($groupBy === 'driver') {
                return (string) ($appointment->driver_id ?? 0);
            }

            return $this->approvalDate($appointment)->format('Y-m-d');
        });

        $pdf = Pdf::loadView('reports.maintenance-approved-appointments-pdf', [
            'appointments' => $appointments,
            'groupedAppointments' => $groupedAppointments,
            'from' => $from,
            'to' => $to,
            'groupBy' => $groupBy,
            'vehicle' => $vehicleId > 0 ? Vehicle::query()->find($vehicleId) : null,
            'driver' => $driverId > 0 ? Driver::query()->find($driverId) : null,
            'generatedAt' => now(),
        ])->setPaper('A4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-citas-aprobadas-' . now()->format('Ymd-His') . '.pdf');
    }

    private function approvalDate(MaintenanceAppointment $appointment): Carbon
    {
        return $appointment->approved_at ?: $appointment->updated_at;
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}

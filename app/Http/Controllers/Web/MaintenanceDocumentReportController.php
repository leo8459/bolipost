<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceLog;
use App\Models\Vehicle;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MaintenanceDocumentReportController extends Controller
{
    public function exportPdf(Request $request)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion'], true), 403);

        [$from, $to] = $this->resolveRange($request);
        $vehicleId = $request->integer('vehicle_id');
        $search = trim((string) $request->query('search', ''));

        $logs = MaintenanceLog::query()
            ->active()
            ->with(['vehicle.brand', 'maintenanceType'])
            ->when($from, fn ($query) => $query->whereDate('fecha', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('fecha', '<=', $to->toDateString()))
            ->when($vehicleId > 0, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('tipo', 'like', "%{$search}%")
                        ->orWhere('descripcion', 'like', "%{$search}%")
                        ->orWhere('observaciones', 'like', "%{$search}%")
                        ->orWhereHas('vehicle', fn ($vehicleQuery) => $vehicleQuery->where('placa', 'like', "%{$search}%"));
                });
            })
            ->orderBy('fecha')
            ->get();

        $appointments = MaintenanceAppointment::query()
            ->active()
            ->with(['vehicle.brand', 'tipoMantenimiento', 'driver'])
            ->when($from, fn ($query) => $query->whereDate('fecha_programada', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('fecha_programada', '<=', $to->toDateString()))
            ->when($vehicleId > 0, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->orderBy('fecha_programada')
            ->get();

        $alerts = MaintenanceAlert::query()
            ->with(['vehicle.brand', 'maintenanceType'])
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to->toDateString()))
            ->when($vehicleId > 0, fn ($query) => $query->where('vehicle_id', $vehicleId))
            ->orderBy('created_at')
            ->get();

        $vehicle = $vehicleId > 0 ? Vehicle::query()->find($vehicleId) : null;

        $pdf = Pdf::loadView('reports.maintenance-documents-pdf', [
            'from' => $from,
            'to' => $to,
            'vehicle' => $vehicle,
            'logs' => $logs,
            'appointments' => $appointments,
            'alerts' => $alerts,
            'generatedAt' => now(),
        ])->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-documentos-mantenimiento-' . now()->format('Ymd-His') . '.pdf');
    }

    private function resolveRange(Request $request): array
    {
        $defaultFrom = now()->startOfMonth();
        $defaultTo = now()->endOfDay();

        $from = $this->resolveDate($request->query('date_from'), $defaultFrom)->startOfDay();
        $to = $this->resolveDate($request->query('date_to'), $defaultTo)->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function resolveDate(mixed $raw, Carbon $fallback): Carbon
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($value, config('app.timezone'));
        } catch (\Throwable) {
            return $fallback;
        }
    }
}

<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverIncentiveReport;
use App\Models\MaintenanceAppointment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DriverIncentiveService
{
    public const MAX_STARS = 5;
    private const DISCOUNTABLE_STATUSES = [
        MaintenanceAppointment::STATUS_APPROVED,
        MaintenanceAppointment::STATUS_COMPLETED,
    ];

    public function generateMonthlyReport(Carbon $month): Collection
    {
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $rangeReports = $this->reportsForRange($periodStart, $periodEnd);

        return $rangeReports->map(function ($report) use ($periodStart) {
                return DriverIncentiveReport::query()->updateOrCreate(
                [
                    'driver_id' => $report->driver_id,
                    'report_year' => (int) $periodStart->year,
                    'report_month' => (int) $periodStart->month,
                ],
                [
                    'stars_start' => self::MAX_STARS,
                    'stars_end' => $report->stars_end,
                    'non_preventive_requests' => $report->non_preventive_requests,
                    'preventive_requests' => $report->preventive_requests,
                    'discountable_events' => $report->discountable_events,
                    'generated_at' => now(),
                ]
            );
        });
    }

    public function reportsForRange(Carbon $from, Carbon $to): Collection
    {
        $periodStart = $from->copy()->startOfDay();
        $periodEnd = $to->copy()->endOfDay();

        return Driver::query()
            ->orderBy('nombre')
            ->get()
            ->map(function (Driver $driver) use ($periodStart, $periodEnd) {
                $appointments = MaintenanceAppointment::query()
                    ->with([
                        'tipoMantenimiento:id,nombre,es_preventivo',
                        'vehicle:id,placa,modelo,marca_id,vehicle_class_id,anio',
                        'vehicle.brand:id,nombre',
                    ])
                    ->where('driver_id', $driver->id)
                    ->whereBetween('solicitud_fecha', [$periodStart, $periodEnd])
                    ->get();

                $appointmentsAffectingStars = $appointments
                    ->filter(fn (MaintenanceAppointment $appointment) => in_array((string) $appointment->estado, self::DISCOUNTABLE_STATUSES, true))
                    ->values();

                $discountedAppointments = $appointmentsAffectingStars
                    ->filter(fn (MaintenanceAppointment $appointment) => !(bool) ($appointment->tipoMantenimiento?->es_preventivo ?? false))
                    ->map(function (MaintenanceAppointment $appointment) {
                        return (object) [
                            'id' => (int) $appointment->id,
                            'vehicle_label' => (string) ($appointment->vehicle?->display_name ?? $appointment->vehicle?->placa ?? 'Vehiculo no identificado'),
                            'vehicle_plate' => (string) ($appointment->vehicle?->placa ?? 'N/A'),
                            'maintenance_type' => (string) ($appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento'),
                            'request_date' => optional($appointment->solicitud_fecha ?? $appointment->created_at)?->format('d/m/Y H:i') ?? '-',
                            'scheduled_date' => optional($appointment->fecha_programada)?->format('d/m/Y H:i') ?? '-',
                            'status' => (string) $appointment->estado,
                            'reason' => 'Descuenta estrellas por no ser preventivo y estar aprobado/realizado.',
                        ];
                    })
                    ->values();

                $nonDiscountedAppointments = $appointmentsAffectingStars
                    ->filter(fn (MaintenanceAppointment $appointment) => (bool) ($appointment->tipoMantenimiento?->es_preventivo ?? false))
                    ->map(function (MaintenanceAppointment $appointment) {
                        return (object) [
                            'id' => (int) $appointment->id,
                            'vehicle_label' => (string) ($appointment->vehicle?->display_name ?? $appointment->vehicle?->placa ?? 'Vehiculo no identificado'),
                            'vehicle_plate' => (string) ($appointment->vehicle?->placa ?? 'N/A'),
                            'maintenance_type' => (string) ($appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento'),
                            'request_date' => optional($appointment->solicitud_fecha ?? $appointment->created_at)?->format('d/m/Y H:i') ?? '-',
                            'scheduled_date' => optional($appointment->fecha_programada)?->format('d/m/Y H:i') ?? '-',
                            'status' => (string) $appointment->estado,
                            'reason' => 'No descuenta estrellas porque corresponde a mantenimiento preventivo.',
                        ];
                    })
                    ->values();

                $preventiveRequests = $appointmentsAffectingStars
                    ->filter(fn (MaintenanceAppointment $appointment) => (bool) ($appointment->tipoMantenimiento?->es_preventivo ?? false))
                    ->count();

                $nonPreventiveRequests = $appointmentsAffectingStars
                    ->filter(fn (MaintenanceAppointment $appointment) => !(bool) ($appointment->tipoMantenimiento?->es_preventivo ?? false))
                    ->count();

                return (object) [
                    'driver_id' => (int) $driver->id,
                    'driver' => $driver,
                    'stars_start' => self::MAX_STARS,
                    'stars_end' => max(self::MAX_STARS - $nonPreventiveRequests, 0),
                    'non_preventive_requests' => $nonPreventiveRequests,
                    'preventive_requests' => $preventiveRequests,
                    'discountable_events' => $nonPreventiveRequests,
                    'total_requests' => $appointmentsAffectingStars->count(),
                    'discounted_appointments' => $discountedAppointments,
                    'non_discounted_appointments' => $nonDiscountedAppointments,
                    'period_start' => $periodStart->copy(),
                    'period_end' => $periodEnd->copy(),
                ];
            })
            ->sortBy([
                ['stars_end', 'desc'],
                ['non_preventive_requests', 'asc'],
                [fn ($report) => mb_strtolower((string) ($report->driver?->nombre ?? '')), 'asc'],
            ])
            ->values();
    }

    public function reportsForMonth(Carbon $month): Collection
    {
        $this->generateMonthlyReport($month);

        return DriverIncentiveReport::query()
            ->with('driver')
            ->where('report_year', (int) $month->year)
            ->where('report_month', (int) $month->month)
            ->orderByDesc('stars_end')
            ->orderBy('driver_id')
            ->get();
    }

    public function latestClosedMonth(): Carbon
    {
        return now()->copy()->subMonthNoOverflow()->startOfMonth();
    }
}

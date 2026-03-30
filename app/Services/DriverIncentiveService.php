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

    public function generateMonthlyReport(Carbon $month): Collection
    {
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $drivers = Driver::query()
            ->orderBy('nombre')
            ->get();

        return $drivers->map(function (Driver $driver) use ($periodStart, $periodEnd) {
            $appointments = MaintenanceAppointment::query()
                ->with('tipoMantenimiento:id,es_preventivo')
                ->where('driver_id', $driver->id)
                ->whereBetween('solicitud_fecha', [$periodStart, $periodEnd])
                ->whereNotIn('estado', [
                    MaintenanceAppointment::STATUS_CANCELLED,
                    MaintenanceAppointment::STATUS_REJECTED,
                ])
                ->get();

            $preventiveRequests = $appointments
                ->filter(fn (MaintenanceAppointment $appointment) => (bool) ($appointment->tipoMantenimiento?->es_preventivo ?? false))
                ->count();

            $nonPreventiveRequests = $appointments->count() - $preventiveRequests;
            $starsEnd = max(self::MAX_STARS - $nonPreventiveRequests, 0);

            return DriverIncentiveReport::query()->updateOrCreate(
                [
                    'driver_id' => $driver->id,
                    'report_year' => (int) $periodStart->year,
                    'report_month' => (int) $periodStart->month,
                ],
                [
                    'stars_start' => self::MAX_STARS,
                    'stars_end' => $starsEnd,
                    'non_preventive_requests' => $nonPreventiveRequests,
                    'preventive_requests' => $preventiveRequests,
                    'generated_at' => now(),
                ]
            );
        });
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

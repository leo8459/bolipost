<?php

namespace App\Livewire;

use App\Models\MaintenanceAppointment;
use App\Models\MaintenanceAlert;
use App\Models\MaintenanceLog;
use App\Models\VehicleAssignment;
use Carbon\Carbon;
use Livewire\Component;

class MaintenanceCalendarManager extends Component
{
    public int $year;
    public int $month;
    public ?string $selected_date = null;

    public function mount(): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion', 'conductor']), 403);

        $now = now();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
        $this->selected_date = $now->toDateString();
    }

    public function previousMonth(): void
    {
        $cursor = Carbon::create($this->year, $this->month, 1)->subMonthNoOverflow();
        $this->year = (int) $cursor->year;
        $this->month = (int) $cursor->month;
    }

    public function nextMonth(): void
    {
        $cursor = Carbon::create($this->year, $this->month, 1)->addMonthNoOverflow();
        $this->year = (int) $cursor->year;
        $this->month = (int) $cursor->month;
    }

    public function goToCurrentMonth(): void
    {
        $now = now();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
        $this->selected_date = $now->toDateString();
    }

    public function updatedSelectedDate($value): void
    {
        if (!$value) {
            return;
        }

        try {
            $selected = Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return;
        }

        $this->selected_date = $selected->toDateString();
        $this->year = (int) $selected->year;
        $this->month = (int) $selected->month;
    }

    public function selectDate(string $date): void
    {
        $this->updatedSelectedDate($date);
    }

    public function render()
    {
        $currentMonth = Carbon::create($this->year, $this->month, 1)->startOfDay();
        $today = now()->startOfDay();

        $gridStart = $currentMonth->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $currentMonth->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);

        $vehicleIds = $this->visibleVehicleIdsForCurrentUser();
        $eventsByDate = [];

        $alertsQuery = MaintenanceAlert::query()
            ->with(['vehicle:id,placa', 'maintenanceType:id,nombre'])
            ->with(['maintenanceAppointment:id,fecha_programada'])
            ->where('status', MaintenanceAlert::STATUS_ACTIVE)
            ->where(function ($q) use ($gridStart, $gridEnd) {
                $q->whereBetween('created_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
                    ->orWhereHas('maintenanceAppointment', function ($qa) use ($gridStart, $gridEnd) {
                        $qa->whereBetween('fecha_programada', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()]);
                    });
            });

        if ($vehicleIds !== null) {
            if (empty($vehicleIds)) {
                $alertsQuery->whereRaw('1=0');
            } else {
                $alertsQuery->whereIn('vehicle_id', $vehicleIds);
            }
        }

        $alerts = $alertsQuery->orderBy('created_at')->orderBy('id')->get();
        $alertedAppointmentIds = $alerts
            ->pluck('maintenance_appointment_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        foreach ($alerts as $alert) {
            $calendarAt = $alert->maintenanceAppointment?->fecha_programada ?? $alert->created_at;
            if (!$calendarAt) {
                continue;
            }

            $dateKey = $calendarAt->toDateString();
            $css = match ($alert->status) {
                MaintenanceAlert::STATUS_RESOLVED => 'border-success bg-success-subtle text-success-emphasis',
                MaintenanceAlert::STATUS_OMITTED => 'border-secondary bg-light text-secondary',
                default => 'border-danger bg-danger-subtle text-danger-emphasis',
            };

            $eventsByDate[$dateKey][] = [
                'stage' => $alert->status ?? MaintenanceAlert::STATUS_ACTIVE,
                'css' => $css,
                'title' => ($alert->vehicle?->placa ?? 'N/A') . ' - ' . ($alert->tipo ?: 'Alerta'),
                'detail' => (string) ($alert->mensaje ?? 'Sin detalle') . ' | ' . $calendarAt->format('d/m/Y H:i'),
                'source' => 'Alerta',
                'sort_ts' => $calendarAt->timestamp,
            ];
        }

        $appointmentsQuery = MaintenanceAppointment::query()
            ->with(['vehicle:id,placa', 'tipoMantenimiento:id,nombre'])
            ->where('estado', '!=', 'Realizado')
            ->whereBetween('fecha_programada', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()]);

        if ($vehicleIds !== null) {
            if (empty($vehicleIds)) {
                $appointmentsQuery->whereRaw('1=0');
            } else {
                $appointmentsQuery->whereIn('vehicle_id', $vehicleIds);
            }
        }

        $appointments = $appointmentsQuery->orderBy('fecha_programada')->get();
        foreach ($appointments as $appointment) {
            if (!$appointment->fecha_programada) {
                continue;
            }
            if (in_array((int) $appointment->id, $alertedAppointmentIds, true)) {
                continue;
            }

            $dateKey = $appointment->fecha_programada->toDateString();
            [$stage, $css] = $this->stageForAppointment($appointment, $today);
            $eventsByDate[$dateKey][] = [
                'stage' => $stage,
                'css' => $css,
                'title' => ($appointment->vehicle?->placa ?? 'N/A') . ' - ' . ($appointment->tipoMantenimiento?->nombre ?? 'Cita'),
                'detail' => 'Programado: ' . $appointment->fecha_programada->format('d/m/Y H:i'),
                'source' => 'Cita',
                'sort_ts' => $appointment->fecha_programada->timestamp,
            ];
        }

        $logsQuery = MaintenanceLog::query()
            ->with(['vehicle:id,placa,kilometraje_actual,kilometraje_inicial,kilometraje', 'maintenanceType:id,nombre'])
            ->whereNotNull('proxima_fecha')
            ->whereDate('proxima_fecha', '>=', $gridStart->toDateString())
            ->whereDate('proxima_fecha', '<=', $gridEnd->toDateString());

        if ($vehicleIds !== null) {
            if (empty($vehicleIds)) {
                $logsQuery->whereRaw('1=0');
            } else {
                $logsQuery->whereIn('vehicle_id', $vehicleIds);
            }
        }

        $logs = $logsQuery->orderBy('proxima_fecha')->orderBy('id')->get();
        foreach ($logs as $log) {
            if (!$log->proxima_fecha) {
                continue;
            }

            $dateKey = $log->proxima_fecha->toDateString();
            [$stage, $css, $detail] = $this->stageForLog($log, $today);
            $eventsByDate[$dateKey][] = [
                'stage' => $stage,
                'css' => $css,
                'title' => ($log->vehicle?->placa ?? 'N/A') . ' - ' . ($log->maintenanceType?->nombre ?? $log->tipo),
                'detail' => $detail,
                'source' => 'Proximo',
                'sort_ts' => $log->proxima_fecha->copy()->startOfDay()->timestamp,
            ];
        }

        foreach ($eventsByDate as $dateKey => $events) {
            usort($events, function (array $a, array $b): int {
                return ($a['sort_ts'] ?? 0) <=> ($b['sort_ts'] ?? 0);
            });
            $eventsByDate[$dateKey] = $events;
        }

        $weeks = [];
        $cursor = $gridStart->copy();
        try {
            $selectedDate = $this->selected_date
                ? Carbon::parse($this->selected_date)->startOfDay()
                : $today->copy();
        } catch (\Throwable) {
            $selectedDate = $today->copy();
            $this->selected_date = $selectedDate->toDateString();
        }
        $selectedDateKey = $selectedDate->toDateString();

        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $cursor->toDateString();
                $week[] = [
                    'date' => $cursor->copy(),
                    'date_key' => $dateKey,
                    'is_today' => $cursor->isSameDay($today),
                    'is_selected' => $dateKey === $selectedDateKey,
                    'is_current_month' => $cursor->month === $currentMonth->month,
                    'events' => $eventsByDate[$dateKey] ?? [],
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $selectedDayEvents = $eventsByDate[$selectedDateKey] ?? [];

        return view('livewire.maintenance-calendar-manager', [
            'monthLabel' => $this->spanishMonthLabel($currentMonth),
            'weeks' => $weeks,
            'weekDays' => ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'],
            'selectedDateLabel' => $selectedDate->format('d/m/Y'),
            'selectedDayEvents' => $selectedDayEvents,
        ]);
    }

    private function visibleVehicleIdsForCurrentUser(): ?array
    {
        if (auth()->user()?->role !== 'conductor') {
            return null;
        }

        $driverId = (int) (auth()->user()?->resolvedDriver()?->id ?? 0);
        if (!$driverId) {
            return [];
        }

        $today = now()->toDateString();

        $activeIds = VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->where('activo', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('fecha_inicio')->orWhereDate('fecha_inicio', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('fecha_fin')->orWhereDate('fecha_fin', '>=', $today);
            })
            ->pluck('vehicle_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();

        if (!empty($activeIds)) {
            return $activeIds;
        }

        return VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->pluck('vehicle_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    private function stageForAppointment(MaintenanceAppointment $appointment, Carbon $today): array
    {
        if ($appointment->estado === 'Cancelado') {
            return ['Cancelado', 'border-secondary bg-light text-secondary'];
        }

        if ($appointment->estado === 'Realizado') {
            return ['Realizado', 'border-success bg-success-subtle text-success-emphasis'];
        }

        $daysTo = $today->diffInDays($appointment->fecha_programada->copy()->startOfDay(), false);

        if ($daysTo < 0) {
            return ['Vencido', 'border-danger bg-danger-subtle text-danger-emphasis'];
        }

        if ($daysTo <= 2) {
            return ['Proximo (<=2 dias)', 'border-warning bg-warning-subtle text-warning-emphasis'];
        }

        return ['Planificado', 'border-success bg-success-subtle text-success-emphasis'];
    }

    private function stageForLog(MaintenanceLog $log, Carbon $today): array
    {
        $daysTo = $log->proxima_fecha ? $today->diffInDays($log->proxima_fecha->copy()->startOfDay(), false) : null;

        $vehicleKm = $log->vehicle?->kilometraje_actual
            ?? $log->vehicle?->kilometraje_inicial
            ?? $log->vehicle?->kilometraje;
        $kmTo = null;
        if ($log->proximo_kilometraje !== null && $vehicleKm !== null) {
            $kmTo = (float) $log->proximo_kilometraje - (float) $vehicleKm;
        }

        if (($daysTo !== null && $daysTo < 0) || ($kmTo !== null && $kmTo < 0)) {
            return [
                'Vencido',
                'border-danger bg-danger-subtle text-danger-emphasis',
                $this->logDetailLine($daysTo, $kmTo),
            ];
        }

        if (($daysTo !== null && $daysTo <= 2) || ($kmTo !== null && $kmTo <= 5)) {
            return [
                'Alerta (<=2 dias o <=5 km)',
                'border-warning bg-warning-subtle text-warning-emphasis',
                $this->logDetailLine($daysTo, $kmTo),
            ];
        }

        return [
            'En ventana segura',
            'border-success bg-success-subtle text-success-emphasis',
            $this->logDetailLine($daysTo, $kmTo),
        ];
    }

    private function logDetailLine(?int $daysTo, ?float $kmTo): string
    {
        $parts = [];

        if ($daysTo !== null) {
            $parts[] = $daysTo >= 0 ? "Faltan {$daysTo} dias" : 'Atrasado por ' . abs($daysTo) . ' dias';
        }

        if ($kmTo !== null) {
            $kmText = number_format(abs($kmTo), 0, '.', '');
            $parts[] = $kmTo >= 0 ? "Faltan {$kmText} km" : "Pasado por {$kmText} km";
        }

        if (empty($parts)) {
            return 'Sin metrica de dias/km disponible';
        }

        return implode(' | ', $parts);
    }

    private function spanishMonthLabel(Carbon $date): string
    {
        $months = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return ($months[(int) $date->month] ?? $date->format('F')) . ' ' . $date->year;
    }
}

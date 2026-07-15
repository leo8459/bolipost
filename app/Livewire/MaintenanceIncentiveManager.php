<?php

namespace App\Livewire;

use App\Models\MaintenanceAppointment;
use App\Services\DriverIncentiveService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class MaintenanceIncentiveManager extends Component
{
    public string $date_from = '';
    public string $date_to = '';
    public string $search = '';
    public string $viewMode = 'panel';
    public string $statusFilter = 'todos';
    public bool $onlyPerfect = false;
    public ?int $detailDriverId = null;
    public bool $detailModalVisible = false;
    public array $detailFullHistory = [];
    public array $detailHistoryStats = [];

    public function mount(DriverIncentiveService $service, Request $request): void
    {
        $user = auth()->user();

        abort_unless(
            in_array($user?->role, ['admin', 'recepcion'], true)
                || (method_exists($user, 'can') && $user->can('livewire.maintenance-incentives')),
            403
        );

        $baseMonth = $service->latestClosedMonth();
        $this->date_from = $baseMonth->copy()->startOfMonth()->toDateString();
        $this->date_to = $baseMonth->copy()->endOfMonth()->toDateString();

        $view = trim((string) $request->query('view', ''));
        if (in_array($view, ['panel', 'reporte'], true)) {
            $this->viewMode = $view;
        }

        $status = trim((string) $request->query('status', ''));
        if (in_array($status, ['todos', 'excelente', 'regular'], true)) {
            $this->statusFilter = $status;
        }

        $this->search = trim((string) $request->query('search', $this->search));
        $this->onlyPerfect = (bool) $request->boolean('perfect', $this->onlyPerfect);

        $requestedFrom = trim((string) $request->query('date_from', ''));
        if ($requestedFrom !== '') {
            try {
                $this->date_from = Carbon::parse($requestedFrom)->toDateString();
            } catch (\Throwable) {
                // keep default
            }
        }

        $requestedTo = trim((string) $request->query('date_to', ''));
        if ($requestedTo !== '') {
            try {
                $this->date_to = Carbon::parse($requestedTo)->toDateString();
            } catch (\Throwable) {
                // keep default
            }
        }

        $detailDriverId = (int) $request->query('detail_driver_id', 0);
        if ($detailDriverId > 0) {
            $this->detailDriverId = $detailDriverId;
            $this->detailModalVisible = true;
            try {
                $this->loadDriverHistory($detailDriverId);
            } catch (\Throwable) {
                $this->detailFullHistory = [];
                $this->detailHistoryStats = [
                    'total' => 0,
                    'discounts' => 0,
                    'ok' => 0,
                    'pending_review' => 0,
                ];
            }
        }
    }

    public function render(DriverIncentiveService $service)
    {
        $from = filled($this->date_from)
            ? Carbon::parse($this->date_from)->startOfDay()
            : $service->latestClosedMonth()->copy()->startOfMonth();
        $to = filled($this->date_to)
            ? Carbon::parse($this->date_to)->endOfDay()
            : $service->latestClosedMonth()->copy()->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $baseReports = $service->reportsForRange($from, $to);
        $reports = $baseReports;

        if ($this->onlyPerfect) {
            $reports = $reports->where('stars_end', DriverIncentiveService::MAX_STARS)->values();
        }

        $search = mb_strtolower(trim($this->search));
        if ($search !== '') {
            $reports = $reports->filter(function ($report) use ($search) {
                $driverName = mb_strtolower(trim((string) ($report->driver?->nombre ?? '')));
                return str_contains($driverName, $search);
            })->values();
        }

        $reports = match ($this->statusFilter) {
            'excelente' => $reports->where('stars_end', DriverIncentiveService::MAX_STARS)->values(),
            'regular' => $reports->where('stars_end', '<', DriverIncentiveService::MAX_STARS)->values(),
            default => $reports->values(),
        };

        $selectedReport = $this->detailDriverId
            ? $baseReports->firstWhere('driver_id', $this->detailDriverId)
            : null;
        $scoreGlobal = $reports->count() > 0
            ? round($reports->avg(fn ($report) => (float) $report->stars_end), 1)
            : 0.0;
        $rankingTop = $reports->take(3)->values();
        $bestDriver = $rankingTop->first();
        $estimatedSavings = $reports->sum(fn ($report) => (int) $report->preventive_requests) * 250;
        $pendingBonuses = $reports
            ->where('stars_end', DriverIncentiveService::MAX_STARS)
            ->count() * 150;
        $deductions = collect([
            [
                'label' => 'Correctivos no reportados',
                'count' => (int) $reports->sum(fn ($report) => (int) $report->non_preventive_requests),
            ],
            [
                'label' => 'Descaste prematuro',
                'count' => (int) $reports->filter(fn ($report) => (int) $report->stars_end <= max(DriverIncentiveService::MAX_STARS - 2, 0))->count(),
            ],
            [
                'label' => 'Omision de cita',
                'count' => (int) $reports->sum(fn ($report) => max((int) $report->discountable_events - (int) $report->non_preventive_requests, 0)),
            ],
        ])->filter(fn (array $item) => $item['count'] > 0)->values();

        return view('livewire.maintenance-incentive-manager', [
            'reports' => $reports,
            'selectedReport' => $selectedReport,
            'periodLabel' => $from->translatedFormat('d/m/Y') . ' al ' . $to->translatedFormat('d/m/Y'),
            'perfectCount' => $reports->where('stars_end', DriverIncentiveService::MAX_STARS)->count(),
            'discountedCount' => $reports->where('stars_end', '<', DriverIncentiveService::MAX_STARS)->count(),
            'maxStars' => DriverIncentiveService::MAX_STARS,
            'monthTitle' => $from->translatedFormat('F Y'),
            'scoreGlobal' => $scoreGlobal,
            'rankingTop' => $rankingTop,
            'bestDriver' => $bestDriver,
            'estimatedSavings' => $estimatedSavings,
            'pendingBonuses' => $pendingBonuses,
            'deductions' => $deductions,
        ]);
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['panel', 'reporte'], true) ? $mode : 'panel';
    }

    public function showDetail(int $driverId): void
    {
        $this->detailDriverId = $driverId;
        $this->detailModalVisible = true;
        try {
            $this->loadDriverHistory($driverId);
        } catch (\Throwable) {
            $this->detailFullHistory = [];
            $this->detailHistoryStats = [
                'total' => 0,
                'discounts' => 0,
                'ok' => 0,
                'pending_review' => 0,
            ];
        }
    }

    public function closeDetail(): void
    {
        $this->detailModalVisible = false;
        $this->detailDriverId = null;
        $this->detailFullHistory = [];
        $this->detailHistoryStats = [];
    }

    public function exportReportPdf()
    {
        return redirect()->route('maintenance-incentives.export.pdf', [
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'search' => $this->search,
            'status' => $this->statusFilter,
            'perfect' => $this->onlyPerfect ? 1 : 0,
        ]);
    }

    private function loadDriverHistory(int $driverId): void
    {
        $discountableStatuses = [
            MaintenanceAppointment::STATUS_APPROVED,
            MaintenanceAppointment::STATUS_COMPLETED,
        ];

        $history = MaintenanceAppointment::query()
            ->with([
                'tipoMantenimiento:id,nombre,es_preventivo',
                'vehicle',
                'vehicle.brand',
            ])
            ->where('driver_id', $driverId)
            ->orderByDesc('solicitud_fecha')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function (MaintenanceAppointment $appointment) use ($discountableStatuses) {
                $status = (string) $appointment->estado;
                $isDiscountableStatus = in_array($status, $discountableStatuses, true);
                $isPreventive = (bool) ($appointment->tipoMantenimiento?->es_preventivo ?? false);
                $impact = 'No evaluado';
                $impactClass = 'secondary';
                $impactReason = 'Solo los mantenimientos aprobados o realizados impactan en el incentivo.';

                if ($isDiscountableStatus && !$isPreventive) {
                    $impact = 'Descuenta estrella';
                    $impactClass = 'danger';
                    $impactReason = 'No es preventivo y su estado es aprobado/realizado.';
                } elseif ($isDiscountableStatus && $isPreventive) {
                    $impact = 'No descuenta';
                    $impactClass = 'success';
                    $impactReason = 'Es preventivo y se considera cumplimiento.';
                }

                $requestDate = $appointment->solicitud_fecha ?? $appointment->created_at;

                return [
                    'id' => (int) $appointment->id,
                    'maintenance_type' => (string) ($appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento'),
                    'vehicle_label' => (string) ($appointment->vehicle?->display_name ?? $appointment->vehicle?->placa ?? 'Vehiculo no identificado'),
                    'vehicle_plate' => (string) ($appointment->vehicle?->placa ?? 'N/A'),
                    'status' => $status,
                    'request_date' => optional($requestDate)?->format('d/m/Y H:i') ?? '-',
                    'scheduled_date' => optional($appointment->fecha_programada)?->format('d/m/Y H:i') ?? '-',
                    'approved_date' => optional($appointment->approved_at)?->format('d/m/Y H:i') ?? '-',
                    'origin' => (string) ($appointment->origen_solicitud ?? 'No especificado'),
                    'impact' => $impact,
                    'impact_class' => $impactClass,
                    'impact_reason' => $impactReason,
                    'evidence_url' => $this->resolvePublicFileUrl((string) ($appointment->evidencia_path ?? '')),
                    'form_url' => $this->resolvePublicFileUrl((string) ($appointment->formulario_documento_path ?? '')),
                ];
            })
            ->values();

        $this->detailFullHistory = $history->all();
        $this->detailHistoryStats = [
            'total' => $history->count(),
            'discounts' => $history->where('impact_class', 'danger')->count(),
            'ok' => $history->where('impact_class', 'success')->count(),
            'pending_review' => $history->where('impact_class', 'secondary')->count(),
        ];
    }

    private function resolvePublicFileUrl(string $path): ?string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, 'http://') || str_starts_with($trimmed, 'https://')) {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '/storage/')) {
            return asset($trimmed);
        }

        if (str_starts_with($trimmed, 'storage/')) {
            return asset('/' . $trimmed);
        }

        try {
            return Storage::disk('public')->url($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }
}

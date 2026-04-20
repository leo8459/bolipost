<?php

namespace App\Livewire;

use App\Services\DriverIncentiveService;
use Carbon\Carbon;
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

    public function mount(DriverIncentiveService $service): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion']), 403);

        $baseMonth = $service->latestClosedMonth();
        $this->date_from = $baseMonth->copy()->startOfMonth()->toDateString();
        $this->date_to = $baseMonth->copy()->endOfMonth()->toDateString();
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

        $reports = $service->reportsForRange($from, $to);

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
            ? $reports->firstWhere('driver_id', $this->detailDriverId)
            : null;
        $scoreGlobal = $reports->count() > 0
            ? round($reports->avg(fn ($report) => (float) $report->stars_end), 1)
            : 0.0;
        $rankingTop = $reports->take(3)->values();
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
    }

    public function closeDetail(): void
    {
        $this->detailModalVisible = false;
        $this->detailDriverId = null;
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
}

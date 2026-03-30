<?php

namespace App\Livewire;

use App\Services\DriverIncentiveService;
use Carbon\Carbon;
use Livewire\Component;

class MaintenanceIncentiveManager extends Component
{
    public string $month = '';
    public bool $onlyPerfect = false;

    public function mount(DriverIncentiveService $service): void
    {
        abort_unless(in_array(auth()->user()?->role, ['admin', 'recepcion']), 403);

        $this->month = $service->latestClosedMonth()->format('Y-m');
    }

    public function render(DriverIncentiveService $service)
    {
        $period = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $reports = $service->reportsForMonth($period);

        if ($this->onlyPerfect) {
            $reports = $reports->where('stars_end', DriverIncentiveService::MAX_STARS)->values();
        }

        return view('livewire.maintenance-incentive-manager', [
            'reports' => $reports,
            'periodLabel' => $period->translatedFormat('F Y'),
            'perfectCount' => $reports->where('stars_end', DriverIncentiveService::MAX_STARS)->count(),
            'discountedCount' => $reports->where('stars_end', '<', DriverIncentiveService::MAX_STARS)->count(),
            'maxStars' => DriverIncentiveService::MAX_STARS,
        ]);
    }
}

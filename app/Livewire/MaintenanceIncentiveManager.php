<?php

namespace App\Livewire;

use App\Services\DriverIncentiveService;
use Carbon\Carbon;
use Livewire\Component;

class MaintenanceIncentiveManager extends Component
{
    public string $date_from = '';
    public string $date_to = '';
    public bool $onlyPerfect = false;

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

        return view('livewire.maintenance-incentive-manager', [
            'reports' => $reports,
            'periodLabel' => $from->translatedFormat('d/m/Y') . ' al ' . $to->translatedFormat('d/m/Y'),
            'perfectCount' => $reports->where('stars_end', DriverIncentiveService::MAX_STARS)->count(),
            'discountedCount' => $reports->where('stars_end', '<', DriverIncentiveService::MAX_STARS)->count(),
            'maxStars' => DriverIncentiveService::MAX_STARS,
        ]);
    }
}

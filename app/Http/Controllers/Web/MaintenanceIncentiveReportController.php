<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\DriverIncentiveService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MaintenanceIncentiveReportController extends Controller
{
    public function exportPdf(Request $request, DriverIncentiveService $service)
    {
        abort_unless(in_array($request->user()?->role, ['admin', 'recepcion'], true), 403);

        $baseMonth = $service->latestClosedMonth();
        $from = $this->resolveDate($request->query('date_from'), $baseMonth->copy()->startOfMonth())->startOfDay();
        $to = $this->resolveDate($request->query('date_to'), $baseMonth->copy()->endOfMonth())->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $reports = $service->reportsForRange($from, $to);
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $status = trim((string) $request->query('status', 'todos'));
        $onlyPerfect = (bool) $request->boolean('perfect');

        if ($search !== '') {
            $reports = $reports->filter(function ($report) use ($search) {
                $driverName = mb_strtolower(trim((string) ($report->driver?->nombre ?? '')));
                return str_contains($driverName, $search);
            })->values();
        }

        if ($onlyPerfect) {
            $reports = $reports->where('stars_end', DriverIncentiveService::MAX_STARS)->values();
        }

        $reports = match ($status) {
            'excelente' => $reports->where('stars_end', DriverIncentiveService::MAX_STARS)->values(),
            'regular' => $reports->where('stars_end', '<', DriverIncentiveService::MAX_STARS)->values(),
            default => $reports->values(),
        };

        $scoreGlobal = $reports->count() > 0
            ? round($reports->avg(fn ($report) => (float) $report->stars_end), 1)
            : 0.0;
        $rankingTop = $reports->take(3)->values();
        $estimatedSavings = $reports->sum(fn ($report) => (int) $report->preventive_requests) * 250;
        $pendingBonuses = $reports
            ->where('stars_end', DriverIncentiveService::MAX_STARS)
            ->count() * 150;

        $pdf = Pdf::loadView('reports.maintenance-incentives-pdf', [
            'reports' => $reports,
            'from' => $from,
            'to' => $to,
            'scoreGlobal' => $scoreGlobal,
            'rankingTop' => $rankingTop,
            'estimatedSavings' => $estimatedSavings,
            'pendingBonuses' => $pendingBonuses,
            'maxStars' => DriverIncentiveService::MAX_STARS,
            'generatedAt' => now(),
        ])->setPaper('A4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'incentivos-mantenimiento-' . now()->format('Ymd-His') . '.pdf');
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

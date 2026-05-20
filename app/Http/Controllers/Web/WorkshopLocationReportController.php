<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workshop;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkshopLocationReportController extends Controller
{
    public function exportPdf(Request $request)
    {
        abort_unless(in_array($this->currentUser()?->role, ['admin', 'recepcion', 'taller'], true), 403);

        $isWorkshopUser = $request->user()?->role === 'taller';

        $workshops = Workshop::query()
            ->active()
            ->with(['vehicle.brand', 'driver', 'workshopCatalog.user', 'maintenanceAlert.maintenanceType', 'maintenanceAppointment.tipoMantenimiento'])
            ->whereIn('estado', [
                Workshop::STATUS_PENDING,
                Workshop::STATUS_DISPATCHED,
                Workshop::STATUS_DIAGNOSIS,
                Workshop::STATUS_APPROVED,
                Workshop::STATUS_REPAIR,
                Workshop::STATUS_READY,
            ])
            ->when($isWorkshopUser, fn ($query) => $query->whereHas('workshopCatalog', fn ($catalogQuery) => $catalogQuery->where('user_id', $request->user()?->id)))
            ->orderBy('workshop_catalog_id')
            ->orderBy('fecha_ingreso')
            ->get();

        $groupedWorkshops = $workshops->groupBy(fn (Workshop $workshop) => (string) ($workshop->workshopCatalog?->nombre ?? $workshop->nombre_taller ?? 'Sin taller'));

        $pdf = Pdf::loadView('reports.workshop-location-report-pdf', [
            'workshops' => $workshops,
            'groupedWorkshops' => $groupedWorkshops,
            'generatedAt' => now(),
        ])->setPaper('A4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'reporte-vehiculos-en-taller-' . now()->format('Ymd-His') . '.pdf');
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}

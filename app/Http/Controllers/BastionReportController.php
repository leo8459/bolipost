<?php

namespace App\Http\Controllers;

use App\Exports\BastionMonthlyReportsExport;
use App\Services\BastionReportService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BastionReportController extends Controller
{
    public function index(Request $request, BastionReportService $service): View
    {
        $month = (string) $request->query('mes', 'abril');
        $source = $service->source($month);
        $rows = $service->rows(collect($source['paquetes']));
        $sheets = collect($source['paquetes'])->pluck('hoja')->unique();
        $search = trim((string) $request->query('buscar', ''));
        $sheet = (string) $request->query('hoja', '');
        $status = (string) $request->query('estado', '');
        $totals = ['filas' => $rows->count(), 'codigos' => $rows->pluck('codigo')->unique()->count(), 'entregados' => $rows->where('entregado', true)->count(), 'sin_registro' => $rows->where('encontrado', false)->count()];
        $filtered = $this->filteredRows($request, $rows);
        $page = max(1, (int) $request->query('page', 1));
        $paquetes = new LengthAwarePaginator($filtered->forPage($page, 25), $filtered->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('bastiones.reporte', compact('paquetes', 'totals', 'sheets', 'search', 'sheet', 'status', 'source', 'month'));
    }

    public function excel(Request $request, BastionReportService $service): BinaryFileResponse
    {
        $reports = [];
        foreach (BastionReportService::MONTHS as $month => $label) {
            $reports[$label] = $this->filteredRows($request, $service->rows(collect($service->source($month)['paquetes'])));
        }

        return Excel::download(new BastionMonthlyReportsExport($reports), 'reporte-bastion-abril-mayo-'.now()->format('Y-m-d-His').'.xlsx');
    }

    private function filteredRows(Request $request, Collection $rows): Collection
    {
        $search = trim((string) $request->query('buscar', ''));
        $sheet = (string) $request->query('hoja', '');
        $status = (string) $request->query('estado', '');

        return $rows->filter(fn ($row) => ($sheet === '' || $row['hoja'] === $sheet)
            && ($search === '' || str_contains(mb_strtolower($row['codigo'].' '.$row['destinatario']), mb_strtolower($search)))
            && ($status !== 'entregado' || $row['entregado'])
            && ($status !== 'pendiente' || ($row['encontrado'] && ! $row['entregado']))
            && ($status !== 'sin_registro' || ! $row['encontrado']))->values();
    }
}

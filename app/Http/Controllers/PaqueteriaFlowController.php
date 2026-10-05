<?php

namespace App\Http\Controllers;

use App\Exports\PaqueteriaFlowExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class PaqueteriaFlowController extends Controller
{
    private const MONTHS = [
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

    public function index(Request $request)
    {
        [$year, $selectedMonths] = $this->validatedFilters($request);

        return view('reportes.flujo-paqueteria', $this->buildReportData($year, $selectedMonths));
    }

    public function exportExcel(Request $request)
    {
        [$year, $selectedMonths] = $this->validatedFilters($request);
        $data = $this->buildReportData($year, $selectedMonths);

        return Excel::download(
            new PaqueteriaFlowExport($data),
            'flujo-paqueteria-'.$year.'.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        [$year, $selectedMonths] = $this->validatedFilters($request);
        $data = $this->buildReportData($year, $selectedMonths);
        $pdf = Pdf::loadView('reportes.flujo-paqueteria-pdf', $data)
            ->setPaper('A4', 'landscape');
        $filename = 'flujo-paqueteria-'.$year.'.pdf';

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf->output();
        }, $filename);
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,'.now()->year],
            'meses' => ['sometimes', 'array', 'min:1', 'max:12'],
            'meses.*' => ['integer', 'between:1,12', 'distinct'],
        ]);

        $year = (int) ($validated['anio'] ?? now()->year);
        $selectedMonths = isset($validated['meses'])
            ? array_values(array_unique(array_map('intval', $validated['meses'])))
            : [7, 8, 9];
        sort($selectedMonths);

        return [$year, $selectedMonths];
    }

    private function buildReportData(int $year, array $selectedMonths): array
    {
        $months = [];
        $companiesById = [];
        $excludedTestCompanyIds = DB::table('empresa')
            ->where(function ($query): void {
                $query->whereRaw("LOWER(TRIM(COALESCE(nombre, ''))) LIKE ?", ['%prueba%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(nombre, ''))) = ?", ['EMPRESA']);
            })
            ->pluck('id')
            ->all();
        $companyIdExpression = 'COALESCE(pc.empresa_id, usuario.empresa_id)';
        // Usa el último registro por CN-33, igual que el listado de bitácoras.
        $latestBitacoraIds = DB::table('bitacoras')
            ->selectRaw('MAX(id)')
            ->whereNotNull('cod_especial')
            ->whereRaw("TRIM(COALESCE(cod_especial, '')) <> ''")
            ->groupByRaw('UPPER(TRIM(cod_especial))');

        foreach ($selectedMonths as $monthNumber) {
            $monthName = self::MONTHS[$monthNumber];
            $start = Carbon::create($year, $monthNumber, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $dateRange = [$start->toDateTimeString(), $end->format('Y-m-d H:i:s.u')];

            $contractQuery = DB::table('paquetes_contrato as pc')
                ->leftJoin('users as usuario', 'usuario.id', '=', 'pc.user_id')
                ->whereBetween('pc.created_at', $dateRange);
            if ($excludedTestCompanyIds !== []) {
                $contractQuery->where(function ($query) use ($companyIdExpression, $excludedTestCompanyIds): void {
                    $query->whereRaw($companyIdExpression.' IS NULL')
                        ->orWhereNotIn(DB::raw($companyIdExpression), $excludedTestCompanyIds);
                });
            }
            $emsQuery = DB::table('paquetes_ems')
                ->whereBetween('created_at', $dateRange);

            $contractGuides = (int) (clone $contractQuery)->distinct('pc.codigo')->count('pc.codigo');
            $contractWeight = (float) (clone $contractQuery)->sum('pc.peso');
            $emsGuides = (int) (clone $emsQuery)->distinct('codigo')->count('codigo');

            $bitacorasCn33 = DB::table('bitacoras as b')
                ->whereIn('b.id', clone $latestBitacoraIds)
                ->whereBetween('b.created_at', $dateRange)
                ->get(['b.cod_especial', 'b.transportadora', 'b.peso']);

            $transport = [
                'aereo' => 0.0,
                'terrestre' => 0.0,
            ];

            foreach ($bitacorasCn33 as $bitacora) {
                $key = $this->isAerialCarrier($bitacora->transportadora)
                    ? 'aereo'
                    : 'terrestre';
                $transport[$key] += (float) ($bitacora->peso ?? 0);
            }

            $ems = (clone $emsQuery)
                ->selectRaw('COALESCE(SUM(cantidad), 0) as paquetes')
                ->selectRaw('COALESCE(SUM(peso), 0) as peso')
                ->first();

            $companyNameExpression = "COALESCE(NULLIF(TRIM(empresa_directa.nombre), ''), NULLIF(TRIM(empresa_usuario.nombre), ''), 'Empresa sin nombre')";
            $companyRows = DB::table('paquetes_contrato as pc')
                ->leftJoin('users as usuario', 'usuario.id', '=', 'pc.user_id')
                ->leftJoin('empresa as empresa_directa', 'empresa_directa.id', '=', 'pc.empresa_id')
                ->leftJoin('empresa as empresa_usuario', 'empresa_usuario.id', '=', 'usuario.empresa_id')
                ->whereBetween('pc.created_at', $dateRange)
                ->whereRaw($companyIdExpression.' IS NOT NULL')
                ->when($excludedTestCompanyIds !== [], fn ($query) => $query->whereNotIn(DB::raw($companyIdExpression), $excludedTestCompanyIds))
                ->selectRaw($companyIdExpression.' as empresa_id')
                ->selectRaw($companyNameExpression.' as empresa')
                ->selectRaw('COUNT(DISTINCT pc.codigo) as guias')
                ->selectRaw('COALESCE(SUM(pc.peso), 0) as peso')
                ->groupByRaw($companyIdExpression.', '.$companyNameExpression)
                ->get();

            foreach ($companyRows as $companyRow) {
                $companyId = (string) $companyRow->empresa_id;
                $companiesById[$companyId] ??= [
                    'id' => (int) $companyRow->empresa_id,
                    'empresa' => (string) $companyRow->empresa,
                    'meses' => [],
                    'guias_total' => 0,
                    'peso_total' => 0.0,
                ];
                $companiesById[$companyId]['meses'][$monthNumber] = [
                    'guias' => (int) $companyRow->guias,
                    'peso' => (float) $companyRow->peso,
                ];
                $companiesById[$companyId]['guias_total'] += (int) $companyRow->guias;
                $companiesById[$companyId]['peso_total'] += (float) $companyRow->peso;
            }

            $emsPackages = (int) ($ems->paquetes ?? 0);
            $emsWeight = (float) ($ems->peso ?? 0);

            $months[$monthNumber] = [
                'numero' => $monthNumber,
                'nombre' => $monthName,
                'guias_contrato' => $contractGuides,
                'peso_contrato' => $contractWeight,
                'guias_ems' => $emsGuides,
                'guias_total' => $contractGuides + $emsGuides,
                'paquetes_ems' => $emsPackages,
                'peso_ems' => $emsWeight,
                'transporte' => $transport,
            ];
        }

        $companyRows = collect(array_values($companiesById))
            ->map(function (array $company) use ($selectedMonths) {
                foreach ($selectedMonths as $monthNumber) {
                    $company['meses'][$monthNumber] ??= ['guias' => 0, 'peso' => 0.0];
                }

                return $company;
            })
            ->sortByDesc('guias_total')
            ->values();
        $topByGuides = $companyRows->first();
        $topByWeight = $companyRows->sortByDesc('peso_total')->first();
        $yearOptions = range((int) now()->year, max(2000, (int) now()->year - 9));
        if (! in_array($year, $yearOptions, true)) {
            $yearOptions[] = $year;
            rsort($yearOptions);
        }
        $selectedNames = array_map(fn (int $month) => self::MONTHS[$month], $selectedMonths);
        $isContinuous = count($selectedMonths) < 2
            || $selectedMonths === range(min($selectedMonths), max($selectedMonths));
        $periodLabel = count($selectedNames) === 1
            ? $selectedNames[0].' '.$year
            : ($isContinuous
                ? $selectedNames[0].' - '.end($selectedNames).' '.$year
                : implode(', ', $selectedNames).' '.$year);
        $totals = [
            'guias_contrato' => (int) collect($months)->sum('guias_contrato'),
            'peso_contrato' => (float) collect($months)->sum('peso_contrato'),
            'guias_ems' => (int) collect($months)->sum('guias_ems'),
            'guias_total' => (int) collect($months)->sum('guias_total'),
            'paquetes_ems' => (int) collect($months)->sum('paquetes_ems'),
            'peso_ems' => (float) collect($months)->sum('peso_ems'),
            'aereo' => (float) collect($months)->sum(fn (array $row) => $row['transporte']['aereo']),
            'terrestre' => (float) collect($months)->sum(fn (array $row) => $row['transporte']['terrestre']),
        ];
        $totals['peso_recibido'] = $totals['peso_contrato'] + $totals['peso_ems'];

        return [
            'anio' => $year,
            'yearOptions' => $yearOptions,
            'monthOptions' => self::MONTHS,
            'selectedMonths' => $selectedMonths,
            'periodLabel' => $periodLabel,
            'months' => $months,
            'totals' => $totals,
            'companyRows' => $companyRows->all(),
            'topByGuides' => $topByGuides,
            'topByWeight' => $topByWeight,
        ];
    }

    private function isAerialCarrier(?string $transportadora): bool
    {
        $normalized = strtoupper(trim(Str::ascii((string) $transportadora)));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return $normalized === 'BOA'
            || str_contains($normalized, 'BOA CARGO')
            || str_contains($normalized, 'BOLIVIANA DE AVIACION');
    }
}

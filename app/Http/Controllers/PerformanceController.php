<?php

namespace App\Http\Controllers;

use App\Exports\PerformanceExport;
use App\Models\Evento;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class PerformanceController extends Controller
{
    private const SERVICE_OPTIONS = [
        'EMS' => 'EMS',
        'CONTRATO' => 'CONTRATOS',
        'CERTI' => 'CERTIFICADOS',
        'ORDI' => 'ORDINARIOS',
        'TIKTOKER' => 'TIKTOKER',
        'DESPACHO' => 'DESPACHOS',
    ];

    private const MONTH_LABELS = [
        1 => 'Ene',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dic',
    ];

    private const MAX_EVENT_COLUMNS = 12;
    private const DEPARTMENT_ALIASES = [
        'COBIJA' => 'PANDO',
        'PANDO' => 'PANDO',
        'TRINIDAD' => 'BENI',
        'BENI' => 'BENI',
        'RIBERALTA' => 'BENI',
        'RURRENABAQUE' => 'BENI',
        'SANTA ANA-TRINIDAD' => 'BENI',
        'SUCRE' => 'CHUQUISACA',
        'CHUQUISACA' => 'CHUQUISACA',
        'LA PAZ - NACIONAL' => 'LA PAZ',
        'LA PAZ' => 'LA PAZ',
        'EL ALTO' => 'LA PAZ',
        'COCHABAMBA' => 'COCHABAMBA',
        'SANTA CRUZ' => 'SANTA CRUZ',
        'MONTERO' => 'SANTA CRUZ',
        'ORURO' => 'ORURO',
        'POTOSI' => 'POTOSI',
        'TARIJA' => 'TARIJA',
        'YACUIBA' => 'TARIJA',
        'ENTREGA LOCAL' => 'LA PAZ',
    ];

    public function index(Request $request)
    {
        $filters = $this->extractFilters($request);
        $viewData = $this->buildViewData($filters);

        return view('performance.index', $viewData);
    }

    public function exportExcel(Request $request)
    {
        $filters = $this->extractFilters($request);
        $exportData = $this->buildExportData($filters);
        $filename = 'performance-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new PerformanceExport($exportData), $filename);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->extractFilters($request);
        $exportData = $this->buildExportData($filters);

        $pdf = Pdf::loadView('performance.report-pdf', $exportData)
            ->setPaper('A4', 'landscape');

        return $pdf->stream('performance-' . now()->format('Ymd-His') . '.pdf');
    }

    private function buildViewData(array $filters): array
    {
        $filteredBaseQuery = $this->applyFilters(
            DB::query()->fromSub($this->buildUnionQuery(), 'performance_rows'),
            $filters
        );

        $matrixSource = (clone $filteredBaseQuery)
            ->selectRaw('origen, destino, evento_nombre, EXTRACT(YEAR FROM created_at)::int as anio, EXTRACT(MONTH FROM created_at)::int as mes, COUNT(*)::int as total')
            ->groupBy('origen', 'destino', 'evento_nombre', DB::raw('EXTRACT(YEAR FROM created_at)'), DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->orderBy('origen')
            ->orderBy('destino')
            ->orderBy(DB::raw('EXTRACT(YEAR FROM created_at)'))
            ->orderBy(DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->get();

        [$matrixRows, $eventColumns, $matrixTotals] = $this->buildMatrix($matrixSource);
        $eventLegend = $this->buildEventLegend($eventColumns);
        [$transitionRows, $transitionSummary] = $this->buildTransitionMetrics(clone $filteredBaseQuery);

        $details = (clone $filteredBaseQuery)
            ->orderByDesc('created_at')
            ->orderBy('codigo')
            ->paginate((int) $filters['per_page'])
            ->withQueryString();

        $summary = [
            'total_registros' => (int) $matrixSource->sum('total'),
            'origenes' => $matrixSource->pluck('origen')->filter()->unique()->count(),
            'destinos' => $matrixSource->pluck('destino')->filter()->unique()->count(),
            'eventos' => count($eventColumns),
        ];

        return [
            'filters' => $filters,
            'serviceOptions' => self::SERVICE_OPTIONS,
            'monthOptions' => self::MONTH_LABELS,
            'originOptions' => $this->originOptions(),
            'destinationOptions' => $this->destinationOptions(),
            'eventOptions' => Evento::query()->orderBy('nombre_evento')->get(['id', 'nombre_evento']),
            'yearOptions' => $this->yearOptions(),
            'eventLegend' => $eventLegend,
            'matrixRows' => $matrixRows,
            'eventColumns' => $eventColumns,
            'matrixTotals' => $matrixTotals,
            'transitionRows' => $transitionRows,
            'transitionSummary' => $transitionSummary,
            'details' => $details,
            'summary' => $summary,
            'filterSummary' => $this->buildFilterSummary($filters),
        ];
    }

    private function buildExportData(array $filters): array
    {
        $filteredBaseQuery = $this->applyFilters(
            DB::query()->fromSub($this->buildUnionQuery(), 'performance_rows'),
            $filters
        );

        $matrixSource = (clone $filteredBaseQuery)
            ->selectRaw('origen, destino, evento_nombre, EXTRACT(YEAR FROM created_at)::int as anio, EXTRACT(MONTH FROM created_at)::int as mes, COUNT(*)::int as total')
            ->groupBy('origen', 'destino', 'evento_nombre', DB::raw('EXTRACT(YEAR FROM created_at)'), DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->orderBy('origen')
            ->orderBy('destino')
            ->orderBy(DB::raw('EXTRACT(YEAR FROM created_at)'))
            ->orderBy(DB::raw('EXTRACT(MONTH FROM created_at)'))
            ->get();

        [$matrixRows, $eventColumns, $matrixTotals] = $this->buildMatrix($matrixSource);
        $eventLegend = $this->buildEventLegend($eventColumns);
        [$transitionRows, $transitionSummary] = $this->buildTransitionMetrics(clone $filteredBaseQuery);

        $details = (clone $filteredBaseQuery)
            ->orderByDesc('created_at')
            ->orderBy('codigo')
            ->get();

        return [
            'generatedAt' => now(),
            'filters' => $filters,
            'filterSummary' => $this->buildFilterSummary($filters),
            'eventLegend' => $eventLegend,
            'matrixRows' => $matrixRows,
            'eventColumns' => $eventColumns,
            'matrixTotals' => $matrixTotals,
            'transitionRows' => $transitionRows,
            'transitionSummary' => $transitionSummary,
            'details' => $details,
            'summary' => [
                'total_registros' => (int) $matrixSource->sum('total'),
                'origenes' => $matrixSource->pluck('origen')->filter()->unique()->count(),
                'destinos' => $matrixSource->pluck('destino')->filter()->unique()->count(),
                'eventos' => count($eventColumns),
            ],
        ];
    }

    private function extractFilters(Request $request): array
    {
        $currentYear = (int) now()->format('Y');
        $currentMonth = (int) now()->format('n');
        $fromYear = (int) $request->query('from_year', $currentYear);
        $toYear = (int) $request->query('to_year', $currentYear);
        $fromMonth = max(1, min(12, (int) $request->query('from_month', $currentMonth)));
        $toMonth = max(1, min(12, (int) $request->query('to_month', $currentMonth)));

        $fromStamp = ($fromYear * 100) + $fromMonth;
        $toStamp = ($toYear * 100) + $toMonth;

        if ($toStamp < $fromStamp) {
            [$fromYear, $toYear] = [$toYear, $fromYear];
            [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'servicio' => trim((string) $request->query('servicio', '')),
            'origen' => trim((string) $request->query('origen', '')),
            'destino' => trim((string) $request->query('destino', '')),
            'evento_id' => max(0, (int) $request->query('evento_id', 0)),
            'from_year' => $fromYear,
            'to_year' => $toYear,
            'from_month' => $fromMonth,
            'to_month' => $toMonth,
            'per_page' => max(10, min(150, (int) $request->query('per_page', 50))),
        ];
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $servicio = trim((string) ($filters['servicio'] ?? ''));
        $origen = trim((string) ($filters['origen'] ?? ''));
        $destino = trim((string) ($filters['destino'] ?? ''));
        $eventoId = (int) ($filters['evento_id'] ?? 0);
        $fromYear = (int) ($filters['from_year'] ?? now()->year);
        $toYear = (int) ($filters['to_year'] ?? now()->year);
        $fromMonth = max(1, min(12, (int) ($filters['from_month'] ?? now()->month)));
        $toMonth = max(1, min(12, (int) ($filters['to_month'] ?? now()->month)));
        $fromDate = Carbon::create($fromYear, $fromMonth, 1)->startOfDay();
        $toDate = Carbon::create($toYear, $toMonth, 1)->endOfMonth()->endOfDay();

        return $query
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $sub) use ($search) {
                    $sub->where('codigo', 'ILIKE', '%' . $search . '%')
                        ->orWhere('evento_nombre', 'ILIKE', '%' . $search . '%')
                        ->orWhere('actor_nombre', 'ILIKE', '%' . $search . '%')
                        ->orWhere('origen', 'ILIKE', '%' . $search . '%')
                        ->orWhere('destino', 'ILIKE', '%' . $search . '%')
                        ->orWhere('servicio', 'ILIKE', '%' . $search . '%');
                });
            })
            ->when($servicio !== '', fn (Builder $builder) => $builder->where('servicio', $servicio))
            ->when($origen !== '', fn (Builder $builder) => $builder->where('origen', $origen))
            ->when($destino !== '', fn (Builder $builder) => $builder->where('destino', $destino))
            ->when($eventoId > 0, fn (Builder $builder) => $builder->where('evento_id', $eventoId));
    }

    private function buildUnionQuery(): Builder
    {
        $queries = [];

        if (Schema::hasTable('eventos_ems')) {
            $queries[] = DB::table('eventos_ems as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->leftJoinSub(
                    DB::table('paquetes_ems as pe')
                        ->leftJoin('paquetes_ems_formulario as pf', 'pf.paquete_ems_id', '=', 'pe.id')
                        ->selectRaw("
                            TRIM(UPPER(pe.codigo)) as codigo_key,
                            COALESCE(NULLIF(TRIM(pe.origen), ''), NULLIF(TRIM(pf.origen), ''), 'SIN ORIGEN') as origen_label,
                            COALESCE(NULLIF(TRIM(pe.ciudad), ''), NULLIF(TRIM(pf.ciudad), ''), 'SIN DESTINO') as destino_label
                        "),
                    'pkg',
                    DB::raw('TRIM(UPPER(t.codigo))'),
                    '=',
                    'pkg.codigo_key'
                )
                ->selectRaw("
                    t.id as record_id,
                    'EMS' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(pkg.origen_label), ''), NULLIF(TRIM(u.ciudad), ''), 'SIN ORIGEN')") . " as origen,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(pkg.destino_label), ''), 'SIN DESTINO')") . " as destino,
                    COALESCE(NULLIF(TRIM(u.name), ''), 'Sin actor') as actor_nombre,
                    t.created_at
                ");
        }

        if (Schema::hasTable('eventos_contrato')) {
            $queries[] = DB::table('eventos_contrato as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->leftJoinSub(
                    DB::table('paquetes_contrato as pc')
                        ->selectRaw("
                            TRIM(UPPER(pc.codigo)) as codigo_key,
                            COALESCE(NULLIF(TRIM(pc.origen), ''), 'SIN ORIGEN') as origen_label,
                            COALESCE(NULLIF(TRIM(pc.destino), ''), 'SIN DESTINO') as destino_label
                        "),
                    'pkg',
                    DB::raw('TRIM(UPPER(t.codigo))'),
                    '=',
                    'pkg.codigo_key'
                )
                ->selectRaw("
                    t.id as record_id,
                    'CONTRATO' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(pkg.origen_label), ''), NULLIF(TRIM(u.ciudad), ''), 'SIN ORIGEN')") . " as origen,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(pkg.destino_label), ''), 'SIN DESTINO')") . " as destino,
                    COALESCE(NULLIF(TRIM(u.name), ''), 'Sin actor') as actor_nombre,
                    t.created_at
                ");
        }

        if (Schema::hasTable('eventos_certi')) {
            $queries[] = DB::table('eventos_certi as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->leftJoinSub(
                    DB::table('paquetes_certi as pc')
                        ->selectRaw("
                            TRIM(UPPER(pc.codigo)) as codigo_key,
                            COALESCE(NULLIF(TRIM(pc.cuidad), ''), 'SIN DESTINO') as destino_label
                        "),
                    'pkg',
                    DB::raw('TRIM(UPPER(t.codigo))'),
                    '=',
                    'pkg.codigo_key'
                )
                ->selectRaw("
                    t.id as record_id,
                    'CERTI' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(u.ciudad), ''), 'SIN ORIGEN')") . " as origen,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(pkg.destino_label), ''), 'SIN DESTINO')") . " as destino,
                    COALESCE(NULLIF(TRIM(u.name), ''), 'Sin actor') as actor_nombre,
                    t.created_at
                ");
        }

        if (Schema::hasTable('eventos_ordi')) {
            $queries[] = DB::table('eventos_ordi as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->leftJoinSub(
                    DB::table('paquetes_ordi as po')
                        ->selectRaw("
                            TRIM(UPPER(po.codigo)) as codigo_key,
                            COALESCE(NULLIF(TRIM(po.ciudad), ''), 'SIN DESTINO') as destino_label
                        "),
                    'pkg',
                    DB::raw('TRIM(UPPER(t.codigo))'),
                    '=',
                    'pkg.codigo_key'
                )
                ->selectRaw("
                    t.id as record_id,
                    'ORDI' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(u.ciudad), ''), 'SIN ORIGEN')") . " as origen,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(pkg.destino_label), ''), 'SIN DESTINO')") . " as destino,
                    COALESCE(NULLIF(TRIM(u.name), ''), 'Sin actor') as actor_nombre,
                    t.created_at
                ");
        }

        if (Schema::hasTable('eventos_tiktoker')) {
            $queries[] = DB::table('eventos_tiktoker as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->leftJoinSub(
                    DB::table('solicitud_clientes as sc')
                        ->selectRaw("
                            TRIM(UPPER(COALESCE(sc.codigo_solicitud, sc.barcode, sc.cod_especial, ''))) as codigo_key,
                            COALESCE(NULLIF(TRIM(sc.origen), ''), 'SIN ORIGEN') as origen_label,
                            COALESCE(NULLIF(TRIM(sc.ciudad), ''), 'SIN DESTINO') as destino_label
                        "),
                    'pkg',
                    DB::raw('TRIM(UPPER(t.codigo))'),
                    '=',
                    'pkg.codigo_key'
                )
                ->selectRaw("
                    t.id as record_id,
                    'TIKTOKER' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(pkg.origen_label), ''), NULLIF(TRIM(u.ciudad), ''), 'SIN ORIGEN')") . " as origen,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(pkg.destino_label), ''), 'SIN DESTINO')") . " as destino,
                    COALESCE(NULLIF(TRIM(u.name), ''), 'Sin actor') as actor_nombre,
                    t.created_at
                ");
        }

        if (Schema::hasTable('eventos_despacho')) {
            $queries[] = DB::table('eventos_despacho as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->selectRaw("
                    t.id as record_id,
                    'DESPACHO' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    " . $this->normalizeDepartmentSql("COALESCE(NULLIF(TRIM(u.ciudad), ''), 'SIN ORIGEN')") . " as origen,
                    'SIN DESTINO' as destino,
                    COALESCE(NULLIF(TRIM(u.name), ''), 'Sin actor') as actor_nombre,
                    t.created_at
                ");
        }

        if ($queries === []) {
            return DB::table('eventos')->selectRaw("
                NULL::bigint as record_id,
                '' as servicio,
                '' as codigo,
                NULL::bigint as evento_id,
                '' as evento_nombre,
                '' as origen,
                '' as destino,
                '' as actor_nombre,
                NULL::timestamp as created_at
            ")->whereRaw('1 = 0');
        }

        $union = array_shift($queries);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function buildMatrix(Collection $aggregatedRows): array
    {
        $eventColumns = $aggregatedRows
            ->groupBy(fn (object $row) => $this->normalizeEventLabel((string) $row->evento_nombre))
            ->map(fn (Collection $rows) => (int) $rows->sum('total'))
            ->sortDesc()
            ->keys()
            ->filter(fn ($name) => trim((string) $name) !== '')
            ->take(self::MAX_EVENT_COLUMNS)
            ->values()
            ->all();

        $hasOtherBucket = $aggregatedRows
            ->map(fn (object $row) => $this->normalizeEventLabel((string) $row->evento_nombre))
            ->filter(fn ($name) => trim((string) $name) !== '' && ! in_array((string) $name, $eventColumns, true))
            ->isNotEmpty();

        if ($hasOtherBucket) {
            $eventColumns[] = 'OTROS';
        }

        $matrixRows = $aggregatedRows
            ->groupBy(fn (object $row) => implode('|', [
                (string) $row->origen,
                (string) $row->destino,
                (string) $row->anio,
                str_pad((string) $row->mes, 2, '0', STR_PAD_LEFT),
            ]))
            ->map(function (Collection $rows) use ($eventColumns) {
                $first = $rows->first();
                $counts = array_fill_keys($eventColumns, 0);

                foreach ($rows as $row) {
                    $eventName = $this->normalizeEventLabel((string) $row->evento_nombre);
                    $columnKey = in_array($eventName, $eventColumns, true) ? $eventName : 'OTROS';

                    if (! array_key_exists($columnKey, $counts)) {
                        continue;
                    }

                    $counts[$columnKey] += (int) $row->total;
                }

                return [
                    'origen' => (string) $first->origen,
                    'destino' => (string) $first->destino,
                    'anio' => (int) $first->anio,
                    'mes' => (int) $first->mes,
                    'mes_label' => self::MONTH_LABELS[(int) $first->mes] ?? (string) $first->mes,
                    'counts' => $counts,
                    'total' => array_sum($counts),
                ];
            })
            ->sortBy([
                ['origen', 'asc'],
                ['destino', 'asc'],
                ['anio', 'asc'],
                ['mes', 'asc'],
            ])
            ->values();

        $matrixTotals = array_fill_keys($eventColumns, 0);
        $grandTotal = 0;

        foreach ($matrixRows as $row) {
            foreach ($eventColumns as $eventColumn) {
                $matrixTotals[$eventColumn] += (int) ($row['counts'][$eventColumn] ?? 0);
            }

            $grandTotal += (int) $row['total'];
        }

        return [$matrixRows, $eventColumns, ['events' => $matrixTotals, 'grand_total' => $grandTotal]];
    }

    private function buildTransitionMetrics(Builder $filteredBaseQuery): array
    {
        $transitionSteps = DB::query()->fromSub(
            (clone $filteredBaseQuery)->selectRaw("
                servicio,
                codigo,
                origen,
                destino,
                evento_nombre,
                created_at,
                LEAD(evento_nombre) OVER (
                    PARTITION BY servicio, codigo
                    ORDER BY created_at, record_id
                ) as siguiente_evento,
                LEAD(created_at) OVER (
                    PARTITION BY servicio, codigo
                    ORDER BY created_at, record_id
                ) as siguiente_fecha
            "),
            'transition_steps'
        );

        $transitionRows = DB::query()
            ->fromSub($transitionSteps, 'transitions')
            ->selectRaw("
                servicio,
                origen,
                destino,
                evento_nombre as evento_origen,
                siguiente_evento as evento_destino,
                COUNT(*)::int as total_transiciones,
                ROUND(AVG(EXTRACT(EPOCH FROM (siguiente_fecha - created_at)) / 86400.0)::numeric, 2) as promedio_dias,
                ROUND(MIN(EXTRACT(EPOCH FROM (siguiente_fecha - created_at)) / 86400.0)::numeric, 2) as minimo_dias,
                ROUND(MAX(EXTRACT(EPOCH FROM (siguiente_fecha - created_at)) / 86400.0)::numeric, 2) as maximo_dias
            ")
            ->whereNotNull('siguiente_evento')
            ->whereNotNull('siguiente_fecha')
            ->whereRaw('siguiente_fecha >= created_at')
            ->groupBy('servicio', 'origen', 'destino', 'evento_nombre', 'siguiente_evento')
            ->orderByDesc('total_transiciones')
            ->orderBy('servicio')
            ->orderBy('origen')
            ->orderBy('destino')
            ->orderBy('evento_nombre')
            ->orderBy('siguiente_evento')
            ->get()
            ->map(function (object $row) {
                return [
                    'servicio' => (string) $row->servicio,
                    'origen' => (string) $row->origen,
                    'destino' => (string) $row->destino,
                    'evento_origen' => $this->normalizeEventLabel((string) $row->evento_origen),
                    'evento_destino' => $this->normalizeEventLabel((string) $row->evento_destino),
                    'total_transiciones' => (int) $row->total_transiciones,
                    'promedio_dias' => (float) $row->promedio_dias,
                    'minimo_dias' => (float) $row->minimo_dias,
                    'maximo_dias' => (float) $row->maximo_dias,
                ];
            })
            ->values();

        $totalTransitions = (int) $transitionRows->sum('total_transiciones');
        $weightedAverageDays = $totalTransitions > 0
            ? round((float) $transitionRows->sum(
                fn (array $row) => $row['promedio_dias'] * $row['total_transiciones']
            ) / $totalTransitions, 2)
            : 0.0;

        return [
            $transitionRows,
            [
                'total_transiciones' => $totalTransitions,
                'rutas' => $transitionRows->count(),
                'promedio_general_dias' => $weightedAverageDays,
            ],
        ];
    }

    private function buildFilterSummary(array $filters): array
    {
        return [
            'Busqueda' => $filters['q'] !== '' ? $filters['q'] : 'Todos',
            'Servicio' => $filters['servicio'] !== '' ? $filters['servicio'] : 'Todos',
            'Origen' => $filters['origen'] !== '' ? $filters['origen'] : 'Todos',
            'Destino' => $filters['destino'] !== '' ? $filters['destino'] : 'Todos',
            'Evento' => (int) $filters['evento_id'] > 0
                ? (string) Evento::query()->whereKey((int) $filters['evento_id'])->value('nombre_evento')
                : 'Todos',
            'Rango' => (self::MONTH_LABELS[(int) $filters['from_month']] ?? $filters['from_month'])
                . ' ' . $filters['from_year']
                . ' - '
                . (self::MONTH_LABELS[(int) $filters['to_month']] ?? $filters['to_month'])
                . ' ' . $filters['to_year'],
        ];
    }

    private function yearOptions(): array
    {
        $years = DB::query()
            ->fromSub($this->buildUnionQuery(), 'performance_rows')
            ->selectRaw('DISTINCT EXTRACT(YEAR FROM created_at)::int as anio')
            ->orderByDesc('anio')
            ->pluck('anio')
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();

        if ($years === []) {
            $years = [(int) now()->format('Y')];
        }

        return $years;
    }

    private function originOptions(): array
    {
        return DB::query()
            ->fromSub($this->buildUnionQuery(), 'performance_rows')
            ->select('origen')
            ->whereNotNull('origen')
            ->whereRaw("TRIM(origen) <> ''")
            ->distinct()
            ->orderBy('origen')
            ->pluck('origen')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }

    private function destinationOptions(): array
    {
        return DB::query()
            ->fromSub($this->buildUnionQuery(), 'performance_rows')
            ->select('destino')
            ->whereNotNull('destino')
            ->whereRaw("TRIM(destino) <> ''")
            ->distinct()
            ->orderBy('destino')
            ->pluck('destino')
            ->map(fn ($value) => (string) $value)
            ->values()
            ->all();
    }

    private function normalizeDepartmentSql(string $expression): string
    {
        $normalized = "UPPER(TRIM({$expression}))";
        $cases = [];

        foreach (self::DEPARTMENT_ALIASES as $raw => $department) {
            $cases[] = "WHEN {$normalized} = '" . str_replace("'", "''", $raw) . "' THEN '" . str_replace("'", "''", $department) . "'";
        }

        $containsMappings = [
            'BENI' => 'BENI',
            'RIBERALTA' => 'BENI',
            'RURRENABAQUE' => 'BENI',
            'RURRENAAQUE' => 'BENI',
            'SANTA ANA' => 'BENI',
            'TRINIDAD' => 'BENI',
            'PANDO' => 'PANDO',
            'COBIJA' => 'PANDO',
            'CHUQUISACA' => 'CHUQUISACA',
            'SUCRE' => 'CHUQUISACA',
            'COCHABAMBA' => 'COCHABAMBA',
            'LA PAZ' => 'LA PAZ',
            'EL ALTO' => 'LA PAZ',
            'SANTA CRUZ' => 'SANTA CRUZ',
            'MONTERO' => 'SANTA CRUZ',
            'ORURO' => 'ORURO',
            'POTOSI' => 'POTOSI',
            'TARIJA' => 'TARIJA',
            'YACUIBA' => 'TARIJA',
        ];

        foreach ($containsMappings as $needle => $department) {
            $cases[] = "WHEN {$normalized} LIKE '%" . str_replace("'", "''", $needle) . "%' THEN '" . str_replace("'", "''", $department) . "'";
        }

        return "(CASE " . implode(' ', $cases) . " ELSE {$normalized} END)";
    }

    private function buildEventLegend(array $eventColumns): array
    {
        $legend = [];

        foreach (array_values($eventColumns) as $index => $eventName) {
            $legend[] = [
                'key' => $this->alphabetKey($index),
                'label' => (string) $eventName,
            ];
        }

        return $legend;
    }

    private function alphabetKey(int $index): string
    {
        $index = max(0, $index);
        $result = '';

        do {
            $result = chr(65 + ($index % 26)) . $result;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);

        return $result;
    }

    private function normalizeEventLabel(string $eventName): string
    {
        $value = trim($eventName);
        $normalized = mb_strtoupper($value, 'UTF-8');

        if (str_starts_with($normalized, 'PAQUETE EN CAMINO PARA ENTREGA FISICA')) {
            return 'Paquete en camino para entrega fisica.';
        }

        return $value;
    }
}

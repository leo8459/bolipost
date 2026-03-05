<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private const EVENTO_ENTREGADO_ID = 316;

    private const MODULOS = [
        'ems' => [
            'label' => 'EMS',
            'table' => 'paquetes_ems',
            'estado_column' => 'estado_id',
            'peso_column' => 'peso',
            'precio_column' => 'precio',
            'event_table' => 'eventos_ems',
            'registro_eventos' => [295],
            'late_hours' => 48,
            'start_expression' => 'coalesce(t.created_at, t.created_at)',
        ],
        'contrato' => [
            'label' => 'CONTRATOS',
            'table' => 'paquetes_contrato',
            'estado_column' => 'estados_id',
            'peso_column' => 'peso',
            'precio_column' => 'precio',
            'event_table' => 'eventos_contrato',
            'registro_eventos' => [318, 295],
            'late_hours' => 72,
            'start_expression' => 'coalesce(t.fecha_recojo, t.created_at)',
        ],
        'certi' => [
            'label' => 'CERTIFICADOS',
            'table' => 'paquetes_certi',
            'estado_column' => 'fk_estado',
            'peso_column' => 'peso',
            'precio_column' => null,
            'event_table' => 'eventos_certi',
            'registro_eventos' => [168],
            'late_hours' => 24 * 15,
            'start_expression' => 'coalesce(t.created_at, t.created_at)',
        ],
        'ordi' => [
            'label' => 'ORDINARIOS',
            'table' => 'paquetes_ordi',
            'estado_column' => 'fk_estado',
            'peso_column' => 'peso',
            'precio_column' => null,
            'event_table' => 'eventos_ordi',
            'registro_eventos' => [295],
            'late_hours' => 24 * 15,
            'start_expression' => 'coalesce(t.created_at, t.created_at)',
        ],
    ];

    public function index(Request $request)
    {
        $modulosSeleccionados = $this->resolveModulosSeleccionados($request);
        [$desde, $hasta, $rangoLabel, $rangoKey] = $this->resolveRangoFechas($request);
        $agrupacion = $this->resolveAgrupacion($request);

        $estadoEntregadoId = $this->resolveEstadoIdByName('ENTREGADO');
        $estadoRezagoId = $this->resolveEstadoIdByName('REZAGO');

        $resumenPorModulo = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $query = DB::table($config['table']);
            $this->applyDateFilter($query, 'created_at', $desde, $hasta);

            $total = (int) (clone $query)->count();
            $entregados = $estadoEntregadoId
                ? (int) (clone $query)->where($config['estado_column'], $estadoEntregadoId)->count()
                : 0;
            $rezago = $estadoRezagoId
                ? (int) (clone $query)->where($config['estado_column'], $estadoRezagoId)->count()
                : 0;

            $pendientes = max(0, $total - $entregados);
            $atrasados = $estadoEntregadoId
                ? $this->countLateDeliveredForModulo($config, $estadoEntregadoId, $desde, $hasta)
                : 0;

            $pesoTotal = (float) (clone $query)->sum(
                DB::raw('coalesce(' . $config['peso_column'] . ', 0)')
            );

            $ingresos = 0.0;
            if (!empty($config['precio_column'])) {
                $ingresos = (float) (clone $query)->sum(
                    DB::raw('coalesce(' . $config['precio_column'] . ', 0)')
                );
            }

            $resumenPorModulo[$moduloKey] = [
                'key' => $moduloKey,
                'label' => $config['label'],
                'total' => $total,
                'entregados' => $entregados,
                'pendientes' => $pendientes,
                'atrasados' => $atrasados,
                'rezago' => $rezago,
                'peso_total' => round($pesoTotal, 3),
                'ingresos' => round($ingresos, 2),
                'tasa_entrega' => $total > 0 ? round(($entregados * 100) / $total, 1) : 0.0,
            ];
        }

        $totales = [
            'paquetes' => (int) array_sum(array_column($resumenPorModulo, 'total')),
            'entregados' => (int) array_sum(array_column($resumenPorModulo, 'entregados')),
            'pendientes' => (int) array_sum(array_column($resumenPorModulo, 'pendientes')),
            'atrasados' => (int) array_sum(array_column($resumenPorModulo, 'atrasados')),
            'rezago' => (int) array_sum(array_column($resumenPorModulo, 'rezago')),
            'peso_total' => round((float) array_sum(array_column($resumenPorModulo, 'peso_total')), 3),
            'ingresos' => round((float) array_sum(array_column($resumenPorModulo, 'ingresos')), 2),
        ];

        $totales['porcentaje_entrega'] = $totales['paquetes'] > 0
            ? round(($totales['entregados'] * 100) / $totales['paquetes'], 1)
            : 0.0;

        $kpisPeriodo = $this->buildKpisPeriodo($modulosSeleccionados);
        [$trendLabels, $trendSeries, $rangoTendenciaLabel] = $this->buildTrendSeries(
            $modulosSeleccionados,
            $desde,
            $hasta,
            $rangoLabel,
            $rangoKey,
            $agrupacion
        );

        $rankingEntregadores = $this->buildRankingEntregadores($modulosSeleccionados, $desde, $hasta);
        $rankingRegistradores = $this->buildRankingRegistradores($modulosSeleccionados, $desde, $hasta);

        return view('dashboard', [
            'modulosDisponibles' => self::MODULOS,
            'modulosSeleccionados' => $modulosSeleccionados,
            'estadoEntregadoDisponible' => (bool) $estadoEntregadoId,
            'estadoRezagoDisponible' => (bool) $estadoRezagoId,
            'rangoDesde' => $desde ? $desde->toDateString() : null,
            'rangoHasta' => $hasta ? $hasta->toDateString() : null,
            'rangoLabel' => $rangoLabel,
            'rangoKey' => $rangoKey,
            'agrupacion' => $agrupacion,
            'resumenPorModulo' => $resumenPorModulo,
            'totales' => $totales,
            'kpisPeriodo' => $kpisPeriodo,
            'chartModulos' => [
                'labels' => array_values(array_column($resumenPorModulo, 'label')),
                'totales' => array_values(array_column($resumenPorModulo, 'total')),
            ],
            'chartEstados' => [
                'labels' => array_values(array_column($resumenPorModulo, 'label')),
                'entregados' => array_values(array_column($resumenPorModulo, 'entregados')),
                'pendientes' => array_values(array_column($resumenPorModulo, 'pendientes')),
                'rezago' => array_values(array_column($resumenPorModulo, 'rezago')),
            ],
            'trendLabels' => $trendLabels,
            'trendSeries' => $trendSeries,
            'rangoTendenciaLabel' => $rangoTendenciaLabel,
            'rankingEntregadores' => $rankingEntregadores,
            'rankingRegistradores' => $rankingRegistradores,
        ]);
    }

    private function resolveModulosSeleccionados(Request $request): array
    {
        $allKeys = array_keys(self::MODULOS);
        $requested = $request->query('modules', $allKeys);
        $requested = is_array($requested) ? $requested : [$requested];

        $selected = array_values(array_filter(
            array_unique(array_map(static fn ($item) => strtolower(trim((string) $item)), $requested)),
            static fn ($key) => in_array($key, $allKeys, true)
        ));

        return empty($selected) ? $allKeys : $selected;
    }

    private function resolveAgrupacion(Request $request): string
    {
        $value = strtolower(trim((string) $request->query('group', 'day')));
        if (!in_array($value, ['day', 'week', 'month'], true)) {
            return 'day';
        }

        return $value;
    }

    private function resolveRangoFechas(Request $request): array
    {
        $now = now();
        $range = strtolower((string) $request->query('range', '30d'));
        $fromInput = trim((string) $request->query('from', ''));
        $toInput = trim((string) $request->query('to', ''));

        if ($range === 'all' && $fromInput === '' && $toInput === '') {
            return [null, null, 'Todo el historial', 'all'];
        }

        if ($fromInput !== '' || $toInput !== '') {
            $from = $this->safeParseDate($fromInput)?->startOfDay();
            $to = $this->safeParseDate($toInput)?->endOfDay();

            if ($from && !$to) {
                $to = (clone $from)->endOfDay();
            } elseif (!$from && $to) {
                $from = (clone $to)->startOfDay();
            }

            if ($from && $to && $from->gt($to)) {
                [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
            }

            if ($from && $to) {
                return [$from, $to, 'Rango personalizado', 'custom'];
            }
        }

        if ($range === 'today') {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'Hoy', 'today'];
        }

        if ($range === '7d') {
            return [
                $now->copy()->subDays(6)->startOfDay(),
                $now->copy()->endOfDay(),
                'Ultimos 7 dias',
                '7d',
            ];
        }

        if ($range === 'month') {
            return [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfDay(),
                'Mes actual',
                'month',
            ];
        }

        return [
            $now->copy()->subDays(29)->startOfDay(),
            $now->copy()->endOfDay(),
            'Ultimos 30 dias',
            '30d',
        ];
    }

    private function safeParseDate(string $value): ?Carbon
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function applyDateFilter(Builder $query, string $column, ?Carbon $from, ?Carbon $to): void
    {
        if ($from && $to) {
            $query->whereBetween($column, [$from, $to]);
            return;
        }

        if ($from) {
            $query->where($column, '>=', $from);
            return;
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }
    }

    private function resolveEstadoIdByName(string $estadoNombre): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($estadoNombre))])
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function countLateDeliveredForModulo(array $config, int $estadoEntregadoId, ?Carbon $from, ?Carbon $to): int
    {
        $entregadoSub = DB::table($config['event_table'])
            ->select('codigo', DB::raw('MIN(created_at) as entregado_at'))
            ->where('evento_id', self::EVENTO_ENTREGADO_ID)
            ->groupBy('codigo');

        $query = DB::table($config['table'] . ' as t')
            ->joinSub($entregadoSub, 'ev', function ($join) {
                $join->on('ev.codigo', '=', 't.codigo');
            })
            ->where('t.' . $config['estado_column'], $estadoEntregadoId)
            ->whereRaw(
                'EXTRACT(EPOCH FROM (ev.entregado_at - ' . $config['start_expression'] . '))/3600 > ?',
                [(int) $config['late_hours']]
            );

        $this->applyDateFilter($query, 't.created_at', $from, $to);

        return (int) $query->count();
    }

    private function buildKpisPeriodo(array $modulosSeleccionados): array
    {
        $now = now();
        $periodos = [
            'dia' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'semana' => [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfDay()],
            'mes' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        ];

        $resultados = [
            'registros' => [],
            'entregas' => [],
        ];

        foreach ($periodos as $periodoKey => [$desde, $hasta]) {
            $countRegistros = 0;
            $countEntregas = 0;

            foreach ($modulosSeleccionados as $moduloKey) {
                $config = self::MODULOS[$moduloKey];

                $countRegistros += (int) DB::table($config['table'])
                    ->whereBetween('created_at', [$desde, $hasta])
                    ->count();

                $countEntregas += (int) DB::table($config['event_table'])
                    ->where('evento_id', self::EVENTO_ENTREGADO_ID)
                    ->whereBetween('created_at', [$desde, $hasta])
                    ->count(DB::raw('distinct codigo'));
            }

            $resultados['registros'][$periodoKey] = $countRegistros;
            $resultados['entregas'][$periodoKey] = $countEntregas;
        }

        return $resultados;
    }

    private function buildTrendSeries(
        array $modulosSeleccionados,
        ?Carbon $from,
        ?Carbon $to,
        string $rangoLabel,
        string $rangoKey,
        string $agrupacion
    ): array {
        [$chartFrom, $chartTo, $chartLabel] = $this->resolveChartRange($from, $to, $rangoLabel, $rangoKey, $agrupacion);
        [$labels, $bucketExpression] = $this->buildBuckets($chartFrom, $chartTo, $agrupacion);

        $registrosMap = array_fill_keys($labels, 0);
        $entregadosMap = array_fill_keys($labels, 0);

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];

            $rowsRegistros = DB::table($config['table'])
                ->selectRaw($bucketExpression . ' as bucket, COUNT(*) as total')
                ->whereBetween('created_at', [$chartFrom, $chartTo])
                ->groupBy(DB::raw($bucketExpression))
                ->pluck('total', 'bucket')
                ->toArray();

            foreach ($rowsRegistros as $bucket => $count) {
                if (array_key_exists($bucket, $registrosMap)) {
                    $registrosMap[$bucket] += (int) $count;
                }
            }

            $rowsEntregados = DB::table($config['event_table'])
                ->selectRaw($bucketExpression . ' as bucket, COUNT(DISTINCT codigo) as total')
                ->where('evento_id', self::EVENTO_ENTREGADO_ID)
                ->whereBetween('created_at', [$chartFrom, $chartTo])
                ->groupBy(DB::raw($bucketExpression))
                ->pluck('total', 'bucket')
                ->toArray();

            foreach ($rowsEntregados as $bucket => $count) {
                if (array_key_exists($bucket, $entregadosMap)) {
                    $entregadosMap[$bucket] += (int) $count;
                }
            }
        }

        return [
            array_values($labels),
            [
                'registros' => array_values($registrosMap),
                'entregados' => array_values($entregadosMap),
            ],
            $chartLabel,
        ];
    }

    private function resolveChartRange(
        ?Carbon $from,
        ?Carbon $to,
        string $rangoLabel,
        string $rangoKey,
        string $agrupacion
    ): array {
        if ($from && $to) {
            return [$from->copy(), $to->copy(), $rangoLabel];
        }

        $now = now();
        if ($rangoKey === 'all') {
            if ($agrupacion === 'month') {
                return [
                    $now->copy()->subMonths(11)->startOfMonth(),
                    $now->copy()->endOfDay(),
                    'Ultimos 12 meses',
                ];
            }

            if ($agrupacion === 'week') {
                return [
                    $now->copy()->subWeeks(11)->startOfWeek(Carbon::MONDAY),
                    $now->copy()->endOfDay(),
                    'Ultimas 12 semanas',
                ];
            }

            return [
                $now->copy()->subDays(29)->startOfDay(),
                $now->copy()->endOfDay(),
                'Ultimos 30 dias',
            ];
        }

        return [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), $rangoLabel];
    }

    private function buildBuckets(Carbon $from, Carbon $to, string $agrupacion): array
    {
        $labels = [];
        $cursor = $from->copy();

        if ($agrupacion === 'month') {
            $cursor = $cursor->startOfMonth();
            $end = $to->copy()->endOfMonth();
            while ($cursor->lte($end)) {
                $labels[] = $cursor->format('Y-m');
                $cursor->addMonth();
            }

            return [$labels, "to_char(date_trunc('month', created_at), 'YYYY-MM')"];
        }

        if ($agrupacion === 'week') {
            $cursor = $cursor->startOfWeek(Carbon::MONDAY);
            $end = $to->copy()->endOfWeek(Carbon::SUNDAY);
            while ($cursor->lte($end)) {
                $labels[] = $cursor->format('o-\WW');
                $cursor->addWeek();
            }

            return [$labels, "to_char(date_trunc('week', created_at), 'IYYY-\"W\"IW')"];
        }

        $cursor = $cursor->startOfDay();
        $end = $to->copy()->endOfDay();
        while ($cursor->lte($end)) {
            $labels[] = $cursor->format('Y-m-d');
            $cursor->addDay();
        }

        return [$labels, "to_char(date_trunc('day', created_at), 'YYYY-MM-DD')"];
    }

    private function buildRankingEntregadores(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to)
    {
        $queries = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $query = DB::table($config['event_table'])
                ->select([
                    'user_id',
                    DB::raw("'" . $config['label'] . "' as modulo"),
                    DB::raw('COUNT(DISTINCT codigo) as total'),
                ])
                ->where('evento_id', self::EVENTO_ENTREGADO_ID)
                ->groupBy('user_id');

            $this->applyDateFilter($query, 'created_at', $from, $to);
            $queries[] = $query;
        }

        return $this->resolveRankingUsuarios($queries, 'total_entregados');
    }

    private function buildRankingRegistradores(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to)
    {
        $queries = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $registroEventos = (array) ($config['registro_eventos'] ?? []);
            if (empty($registroEventos)) {
                continue;
            }

            $query = DB::table($config['event_table'])
                ->select([
                    'user_id',
                    DB::raw("'" . $config['label'] . "' as modulo"),
                    DB::raw('COUNT(DISTINCT codigo) as total'),
                ])
                ->whereIn('evento_id', $registroEventos)
                ->groupBy('user_id');

            $this->applyDateFilter($query, 'created_at', $from, $to);
            $queries[] = $query;
        }

        return $this->resolveRankingUsuarios($queries, 'total_registrados');
    }

    private function resolveRankingUsuarios(array $queries, string $totalAlias)
    {
        if (empty($queries)) {
            return collect();
        }

        $base = array_shift($queries);
        foreach ($queries as $nextQuery) {
            $base->unionAll($nextQuery);
        }

        return DB::query()
            ->fromSub($base, 'r')
            ->join('users', 'users.id', '=', 'r.user_id')
            ->select([
                'users.id',
                'users.name',
                DB::raw('SUM(r.total) as ' . $totalAlias),
                DB::raw("SUM(CASE WHEN r.modulo = 'EMS' THEN r.total ELSE 0 END) as ems"),
                DB::raw("SUM(CASE WHEN r.modulo = 'CONTRATOS' THEN r.total ELSE 0 END) as contrato"),
                DB::raw("SUM(CASE WHEN r.modulo = 'CERTIFICADOS' THEN r.total ELSE 0 END) as certi"),
                DB::raw("SUM(CASE WHEN r.modulo = 'ORDINARIOS' THEN r.total ELSE 0 END) as ordi"),
            ])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc($totalAlias)
            ->limit(10)
            ->get();
    }
}

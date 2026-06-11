<?php

namespace App\Http\Controllers;

use App\Exports\DashboardReportExport;
use App\Exports\DashboardEntregasRendimientoExport;
use App\Exports\DashboardRankingDepartamentosExport;
use App\Models\Estado;
use App\Models\Recojo;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    private const EVENTO_ENTREGADO_ID = 316;
    private const EVENTO_ENVIADO_VENTANILLA_ID = 312;
    private const EVENTO_EMS_SOLICITUD_ID = 295;
    private const CERTI_ORDI_GREEN_DAYS = 7;
    private const CERTI_ORDI_YELLOW_DAYS = 15;
    private const RANKING_CUMPLIMIENTO_WEIGHT = 0.70;
    private const RANKING_PARTICIPACION_WEIGHT = 0.30;
    private const DESTINOS_LARGA_DISTANCIA = [
        'SANTA CRUZ',
        'TRINIDAD',
        'TARIJA',
    ];
    private const DESTINOS_BASE = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'SUCRE',
        'TRINIDAD',
        'COBIJA',
    ];
    private const DESTINOS_CAPITALES = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'SUCRE',
        'TRINIDAD',
        'COBIJA',
    ];

    private const MODULOS = [
        'ems' => [
            'label' => 'EMS',
            'table' => 'paquetes_ems',
            'estado_column' => 'estado_id',
            'departamento_column' => 'ciudad',
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
            'departamento_column' => 'destino',
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
            'departamento_column' => 'cuidad',
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
            'departamento_column' => 'ciudad',
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
        $data = $this->buildDashboardData($request);

        return view('dashboard', $data);
    }

    public function entregas(Request $request)
    {
        return view('entregas', $this->buildEntregasData($request));
    }

    public function exportEntregasExcel(Request $request)
    {
        $data = $this->buildEntregasData($request);
        $filename = 'entregas-rendimiento-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new DashboardEntregasRendimientoExport($data), $filename);
    }

    private function buildEntregasData(Request $request): array
    {
        $modulosSeleccionados = $this->resolveModulosSeleccionados($request);
        [$desde, $hasta, $rangoLabel, $rangoKey] = $this->resolveRangoFechas($request);
        $departamentoCartero = $this->resolveDepartamentoFiltroPorCampo($request, 'cartero_departamento');

        $entregadores = $this->buildRankingEntregadores($modulosSeleccionados, $desde, $hasta, null, '', $departamentoCartero)
            ->map(function ($row) {
                $ems = (int) ($row->ems ?? 0);
                $contrato = (int) ($row->contrato ?? 0);
                $certi = (int) ($row->certi ?? 0);
                $ordi = (int) ($row->ordi ?? 0);

                $porServicio = [
                    'EMS' => $ems,
                    'CONTRATOS' => $contrato,
                    'CERTIFICADOS' => $certi,
                    'ORDINARIOS' => $ordi,
                ];

                $maximo = max($porServicio);
                $masEntregados = $maximo > 0
                    ? collect($porServicio)->filter(fn ($total) => (int) $total === (int) $maximo)->keys()->values()->all()
                    : [];

                $row->total_entregados = (int) ($row->total_entregados ?? 0);
                $row->ems = $ems;
                $row->contrato = $contrato;
                $row->certi = $certi;
                $row->ordi = $ordi;
                $row->servicio_mas_entregado = empty($masEntregados) ? 'SIN DATOS' : implode(' / ', $masEntregados);
                $row->servicio_mas_entregado_total = (int) $maximo;

                return $row;
            });

        $asignados = $this->buildRankingAsignadosCartero($modulosSeleccionados, $desde, $hasta, null, '', $departamentoCartero)
            ->keyBy('id');
        $ventanilla = $this->buildRankingEntregasVentanilla($modulosSeleccionados, $desde, $hasta, null, '', $departamentoCartero)
            ->keyBy('id');

        $entregadores = $entregadores
            ->keyBy('id')
            ->union($asignados)
            ->union($ventanilla)
            ->map(function ($row, $userId) use ($entregadores, $asignados, $ventanilla) {
                $entregadoRow = $entregadores->firstWhere('id', $userId);
                $asignadoRow = $asignados->get($userId);
                $ventanillaRow = $ventanilla->get($userId);

                $row->name = $entregadoRow->name ?? $asignadoRow->name ?? $ventanillaRow->name ?? $row->name;
                $row->ciudad = $entregadoRow->ciudad ?? $asignadoRow->ciudad ?? $ventanillaRow->ciudad ?? $row->ciudad;
                $row->total_entregados = (int) ($entregadoRow->total_entregados ?? 0);
                $row->total_ventanilla = (int) ($ventanillaRow->total_ventanilla ?? 0);
                $row->total_cartero_entregados = max(0, $row->total_entregados - $row->total_ventanilla);
                $row->ems = (int) ($entregadoRow->ems ?? 0);
                $row->contrato = (int) ($entregadoRow->contrato ?? 0);
                $row->certi = (int) ($entregadoRow->certi ?? 0);
                $row->ordi = (int) ($entregadoRow->ordi ?? 0);
                $row->ventanilla_ems = (int) ($ventanillaRow->ems ?? 0);
                $row->ventanilla_contrato = (int) ($ventanillaRow->contrato ?? 0);
                $row->ventanilla_certi = (int) ($ventanillaRow->certi ?? 0);
                $row->ventanilla_ordi = (int) ($ventanillaRow->ordi ?? 0);
                $row->total_asignados = (int) ($asignadoRow->total_asignados ?? 0);
                $row->asignado_ems = (int) ($asignadoRow->ems ?? 0);
                $row->asignado_contrato = (int) ($asignadoRow->contrato ?? 0);
                $row->asignado_certi = (int) ($asignadoRow->certi ?? 0);
                $row->asignado_ordi = (int) ($asignadoRow->ordi ?? 0);
                $row->pendientes_asignados = max(0, $row->total_asignados - $row->total_cartero_entregados);
                $row->cumplimiento_asignados = $row->total_asignados > 0
                    ? round(($row->total_cartero_entregados * 100) / $row->total_asignados, 1)
                    : 0.0;

                $porServicio = [
                    'EMS' => $row->ems,
                    'CONTRATOS' => $row->contrato,
                    'CERTIFICADOS' => $row->certi,
                    'ORDINARIOS' => $row->ordi,
                ];
                $maximo = max($porServicio);
                $masEntregados = $maximo > 0
                    ? collect($porServicio)->filter(fn ($total) => (int) $total === (int) $maximo)->keys()->values()->all()
                    : [];
                $row->servicio_mas_entregado = empty($masEntregados) ? 'SIN DATOS' : implode(' / ', $masEntregados);
                $row->servicio_mas_entregado_total = (int) $maximo;

                return $row;
            })
            ->sortByDesc(fn ($row) => ((int) $row->total_asignados * 1000000) + (int) $row->total_entregados + (int) $row->total_ventanilla)
            ->values();

        return [
            'entregadores' => $entregadores,
            'modulosDisponibles' => self::MODULOS,
            'modulosSeleccionados' => $modulosSeleccionados,
            'rangoDesde' => $desde ? $desde->toDateString() : null,
            'rangoHasta' => $hasta ? $hasta->toDateString() : null,
            'rangoLabel' => $rangoLabel,
            'rangoKey' => $rangoKey,
            'departamentoCartero' => $departamentoCartero,
            'departamentosDisponibles' => self::DESTINOS_BASE,
        ];
    }

    public function reportes(Request $request)
    {
        $data = $this->buildDashboardData($request);

        return view('reportes.index', $data);
    }

    public function exportExcel(Request $request)
    {
        $data = $this->buildDashboardData($request);

        if ($request->boolean('ranking_departamentos')) {
            $filename = 'dashboard-competencia-departamentos-' . now()->format('Ymd-His') . '.xlsx';

            return Excel::download(new DashboardRankingDepartamentosExport($data), $filename);
        }

        $filename = 'dashboard-reporte-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new DashboardReportExport($data), $filename);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->buildDashboardData($request);

        if ($request->boolean('ranking_departamentos')) {
            $pdf = Pdf::loadView('dashboard.ranking-departamentos-pdf', $data)->setPaper('A4', 'landscape');
            $filename = 'dashboard-competencia-departamentos-' . now()->format('Ymd-His') . '.pdf';

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, $filename);
        }

        $departamentoReporte = strtoupper(trim((string) $request->query('departamento_reporte', '')));
        if ($departamentoReporte !== '') {
            $departamentoData = collect($data['rankingDepartamentos'] ?? [])
                ->first(fn ($row) => strtoupper(trim((string) ($row->departamento ?? ''))) === $departamentoReporte);

            if ($departamentoData) {
                $pdf = Pdf::loadView('dashboard.departamento-report-pdf', array_merge($data, [
                    'departamentoReporte' => $departamentoData,
                ]))->setPaper('A4', 'landscape');
                $filename = 'dashboard-departamento-' . str_replace(' ', '-', strtolower($departamentoReporte)) . '-' . now()->format('Ymd-His') . '.pdf';

                return response()->streamDownload(function () use ($pdf) {
                    echo $pdf->output();
                }, $filename);
            }
        }

        $pdf = Pdf::loadView('dashboard.report-pdf', $data)->setPaper('A4', 'landscape');
        $filename = 'dashboard-reporte-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function exportReportesPdf(Request $request)
    {
        $data = $this->buildDashboardData($request);
        $pdf = Pdf::loadView('dashboard.report-pdf', $data)->setPaper('A4', 'landscape');
        $filename = 'reporte-ejecutivo-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    private function buildDashboardData(Request $request): array
    {
        $modulosSeleccionados = $this->resolveModulosSeleccionados($request);
        [$desde, $hasta, $rangoLabel, $rangoKey] = $this->resolveRangoFechas($request);
        $agrupacion = $this->resolveAgrupacion($request);
        $departamento = $this->resolveDepartamentoFiltro($request);
        $authUser = Auth::user();
        $userCity = strtoupper(trim((string) optional($authUser)->ciudad));
        $allowedSoundRoles = ['encargado_ems', 'cartero_ems'];
        $roleNames = ($authUser && method_exists($authUser, 'getRoleNames'))
            ? $authUser->getRoleNames()->toArray()
            : [];
        $userRoles = collect($roleNames)
            ->map(fn ($role) => mb_strtolower(trim((string) $role)))
            ->all();
        $canPlayPickupAlertSound = count(array_intersect($allowedSoundRoles, $userRoles)) > 0;

        $estadoEntregadoId = $this->resolveEstadoIdByName('ENTREGADO');
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');
        $estadoRezagoId = $this->resolveEstadoIdByName('REZAGO');
        $estadoSolicitudId = $this->resolveEstadoIdByName('SOLICITUD');

        $resumenPorModulo = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $query = DB::table($config['table']);
            $this->applyDateFilter($query, 'created_at', $desde, $hasta);
            $this->applyDepartamentoFilter($query, $config, $departamento);

            $querySinCancelados = clone $query;
            $this->excludeCanceledState($querySinCancelados, $config['estado_column'], $estadoCanceladoId);

            $total = (int) (clone $querySinCancelados)->count();
            $entregados = $estadoEntregadoId
                ? (int) (clone $querySinCancelados)->where($config['estado_column'], $estadoEntregadoId)->count()
                : 0;
            $cancelados = $estadoCanceladoId
                ? (int) (clone $query)->where($config['estado_column'], $estadoCanceladoId)->count()
                : 0;
            $situacionInventario = $this->countSituacionInventarioByIndicadorLogic(
                $moduloKey,
                $config,
                $estadoEntregadoId,
                $desde,
                $hasta,
                $departamento
            );
            $correctos = (int) ($situacionInventario['correcto'] ?? 0);
            $atrasados = (int) ($situacionInventario['retraso'] ?? 0);
            $rezago = (int) ($situacionInventario['rezago'] ?? 0);

            $pendientes = max(0, $total - $entregados);

            $pesoTotal = (float) (clone $querySinCancelados)->sum(
                DB::raw('coalesce(' . $config['peso_column'] . ', 0)')
            );

            $ingresos = 0.0;
            if (!empty($config['precio_column'])) {
                $ingresos = (float) (clone $querySinCancelados)->sum(
                    DB::raw('coalesce(' . $config['precio_column'] . ', 0)')
                );
            }

            $resumenPorModulo[$moduloKey] = [
                'key' => $moduloKey,
                'label' => $config['label'],
                'total' => $total,
                'entregados' => $entregados,
                'pendientes' => $pendientes,
                'correctos' => $correctos,
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
            'correctos' => (int) array_sum(array_column($resumenPorModulo, 'correctos')),
            'atrasados' => (int) array_sum(array_column($resumenPorModulo, 'atrasados')),
            'rezago' => (int) array_sum(array_column($resumenPorModulo, 'rezago')),
            'peso_total' => round((float) array_sum(array_column($resumenPorModulo, 'peso_total')), 3),
            'ingresos' => round((float) array_sum(array_column($resumenPorModulo, 'ingresos')), 2),
        ];

        $totales['porcentaje_entrega'] = $totales['paquetes'] > 0
            ? round(($totales['entregados'] * 100) / $totales['paquetes'], 1)
            : 0.0;

        $kpisPeriodo = $this->buildKpisPeriodo($modulosSeleccionados, $departamento);
        [$trendLabels, $trendSeries, $rangoTendenciaLabel] = $this->buildTrendSeries(
            $modulosSeleccionados,
            $desde,
            $hasta,
            $rangoLabel,
            $rangoKey,
            $agrupacion,
            $departamento
        );

        $rankingEntregadores = $this->buildRankingEntregadores($modulosSeleccionados, $desde, $hasta, null, $departamento);
        $rankingDepartamentos = $this->buildRankingDepartamentos($modulosSeleccionados, $desde, $hasta);
        $rankingRegistradores = $this->buildRankingRegistradores($modulosSeleccionados, $desde, $hasta, $departamento);
        $insightsEjecutivos = $this->buildExecutiveInsights(
            $totales,
            $resumenPorModulo,
            $trendLabels,
            $trendSeries,
            $rankingEntregadores,
            $rankingRegistradores,
            $rankingDepartamentos
        );

        $contratosPorRecoger = 0;
        if ($estadoSolicitudId && $userCity !== '') {
            $contratosPorRecoger = (int) Recojo::query()
                ->where('estados_id', $estadoSolicitudId)
                ->whereRaw('trim(upper(origen)) = ?', [$userCity])
                ->count();
        }

        return [
            'modulosDisponibles' => self::MODULOS,
            'modulosSeleccionados' => $modulosSeleccionados,
            'estadoEntregadoDisponible' => (bool) $estadoEntregadoId,
            'estadoRezagoDisponible' => (bool) $estadoRezagoId,
            'rangoDesde' => $desde ? $desde->toDateString() : null,
            'rangoHasta' => $hasta ? $hasta->toDateString() : null,
            'rangoLabel' => $rangoLabel,
            'rangoKey' => $rangoKey,
            'agrupacion' => $agrupacion,
            'departamento' => $departamento,
            'departamentosDisponibles' => self::DESTINOS_BASE,
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
                'correctos' => array_values(array_column($resumenPorModulo, 'correctos')),
                'retraso' => array_values(array_column($resumenPorModulo, 'atrasados')),
                'rezago' => array_values(array_column($resumenPorModulo, 'rezago')),
            ],
            'chartVersus' => [
                'labels' => ['Entregados', 'Pendientes'],
                'totales' => [(int) $totales['entregados'], (int) $totales['pendientes']],
            ],
            'trendLabels' => $trendLabels,
            'trendSeries' => $trendSeries,
            'rangoTendenciaLabel' => $rangoTendenciaLabel,
            'rankingEntregadores' => $rankingEntregadores,
            'rankingDepartamentos' => $rankingDepartamentos,
            'rankingRegistradores' => $rankingRegistradores,
            'insightsEjecutivos' => $insightsEjecutivos,
            'userCity' => $userCity,
            'contratosPorRecoger' => $contratosPorRecoger,
            'canPlayPickupAlertSound' => $canPlayPickupAlertSound,
        ];
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

    private function resolveDepartamentoFiltro(Request $request): string
    {
        return $this->resolveDepartamentoFiltroPorCampo($request, 'departamento');
    }

    private function resolveDepartamentoFiltroPorCampo(Request $request, string $field): string
    {
        $value = strtoupper(trim((string) $request->query($field, '')));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return in_array($value, self::DESTINOS_BASE, true) ? $value : '';
    }

    private function resolveRangoFechas(Request $request): array
    {
        $now = now();
        $range = strtolower((string) $request->query('range', 'all'));
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

        return [null, null, 'Todo el historial', 'all'];
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

    private function excludeCanceledState(Builder $query, string $estadoColumn, ?int $estadoCanceladoId): void
    {
        if (!$estadoCanceladoId) {
            return;
        }

        $query->where(function (Builder $sub) use ($estadoColumn, $estadoCanceladoId) {
            $sub->whereNull($estadoColumn)
                ->orWhere($estadoColumn, '<>', $estadoCanceladoId);
        });
    }

    private function excludeCanceledPackageForEvent(Builder $query, array $config, string $eventAlias): void
    {
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');
        if (!$estadoCanceladoId) {
            return;
        }

        $alias = 'pkg_no_cancelado_' . $eventAlias;
        $alias = str_replace(['.', '-'], '_', $alias);

        $query->join($config['table'] . ' as ' . $alias, $alias . '.codigo', '=', $eventAlias . '.codigo');
        $this->excludeCanceledState($query, $alias . '.' . $config['estado_column'], $estadoCanceladoId);
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

    private function countSituacionInventarioByIndicadorLogic(
        string $moduloKey,
        array $config,
        ?int $estadoEntregadoId,
        ?Carbon $from,
        ?Carbon $to,
        string $departamento = ''
    ): array {
        $query = null;
        $now = now();
        $resumen = [
            'correcto' => 0,
            'retraso' => 0,
            'rezago' => 0,
            'sin_datos' => 0,
        ];

        if ($moduloKey === 'contrato') {
            $query = DB::table($config['table'] . ' as t')
                ->select([
                    't.id',
                    't.destino',
                    't.provincia',
                    't.fecha_recojo',
                    't.created_at',
                ]);
            $this->applyNoEntregadoScope($query, 't.estados_id', $estadoEntregadoId);
            $this->applyDateFilter($query, 't.created_at', $from, $to);
            $this->applyDepartamentoFilter($query, $config, $departamento, 't');

            foreach ($query->orderBy('t.id')->cursor() as $row) {
                $inicio = $this->safeCarbonValue($row->fecha_recojo ?? null);
                $esProvincia = trim((string) ($row->provincia ?? '')) !== '';
                $umbral = $this->resolveEmsThresholdDays((string) ($row->destino ?? ''), $esProvincia);
                $bucket = $this->resolveSituacionBucket($inicio, $now, (int) $umbral['green'], (int) $umbral['yellow']);
                $resumen[$bucket]++;
            }

            return $resumen;
        }

        if ($moduloKey === 'ems') {
            $solicitudSub = DB::table('eventos_ems')
                ->select('codigo', DB::raw('MIN(created_at) as solicitud_at'))
                ->where('evento_id', self::EVENTO_EMS_SOLICITUD_ID)
                ->groupBy('codigo');

            $query = DB::table($config['table'] . ' as t')
                ->leftJoinSub($solicitudSub, 'ev_solicitud', function ($join) {
                    $join->on('ev_solicitud.codigo', '=', 't.codigo');
                })
                ->select([
                    't.id',
                    't.ciudad as destino',
                    't.created_at',
                    'ev_solicitud.solicitud_at',
                ]);
            $this->applyNoEntregadoScope($query, 't.estado_id', $estadoEntregadoId);
            $this->applyDateFilter($query, 't.created_at', $from, $to);
            $this->applyDepartamentoFilter($query, $config, $departamento, 't');

            foreach ($query->orderBy('t.id')->cursor() as $row) {
                $inicio = $this->safeCarbonValue($row->solicitud_at ?? null)
                    ?? $this->safeCarbonValue($row->created_at ?? null);
                $destino = (string) ($row->destino ?? '');
                $esProvincia = $this->isEmsProvincia($destino);
                $umbral = $this->resolveEmsThresholdDays($destino, $esProvincia);
                $bucket = $this->resolveSituacionBucket($inicio, $now, (int) $umbral['green'], (int) $umbral['yellow']);
                $resumen[$bucket]++;
            }

            return $resumen;
        }

        if (in_array($moduloKey, ['certi', 'ordi'], true)) {
            $inicioSub = DB::table($config['event_table'])
                ->select('codigo', DB::raw('MIN(created_at) as primer_evento_at'))
                ->groupBy('codigo');

            $query = DB::table($config['table'] . ' as t')
                ->leftJoinSub($inicioSub, 'ev_inicio', function ($join) {
                    $join->on('ev_inicio.codigo', '=', 't.codigo');
                })
                ->select([
                    't.id',
                    't.created_at',
                    'ev_inicio.primer_evento_at',
                ]);
            $this->applyNoEntregadoScope($query, 't.' . $config['estado_column'], $estadoEntregadoId);
            $this->applyDateFilter($query, 't.created_at', $from, $to);
            $this->applyDepartamentoFilter($query, $config, $departamento, 't');

            foreach ($query->orderBy('t.id')->cursor() as $row) {
                $inicio = $this->safeCarbonValue($row->primer_evento_at ?? null)
                    ?? $this->safeCarbonValue($row->created_at ?? null);
                $bucket = $this->resolveSituacionBucket(
                    $inicio,
                    $now,
                    self::CERTI_ORDI_GREEN_DAYS,
                    self::CERTI_ORDI_YELLOW_DAYS
                );
                $resumen[$bucket]++;
            }

            return $resumen;
        }

        return $resumen;
    }

    private function resolveSituacionBucket(?Carbon $inicio, Carbon $fin, int $greenDays, int $yellowDays): string
    {
        if (!$inicio || $fin->lessThan($inicio)) {
            return 'sin_datos';
        }

        $horas = $inicio->diffInHours($fin);
        if ($horas <= ($greenDays * 24)) {
            return 'correcto';
        }

        if ($horas <= ($yellowDays * 24)) {
            return 'retraso';
        }

        return 'rezago';
    }

    private function applyNoEntregadoScope(Builder $query, string $stateColumn, ?int $estadoEntregadoId): void
    {
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');

        if (!$estadoEntregadoId) {
            $this->excludeCanceledState($query, $stateColumn, $estadoCanceladoId);
            return;
        }

        $query->where(function (Builder $sub) use ($stateColumn, $estadoEntregadoId, $estadoCanceladoId) {
            $sub->whereNull($stateColumn)
                ->orWhere($stateColumn, '<>', $estadoEntregadoId);

            if ($estadoCanceladoId) {
                $sub->where(function (Builder $cancelados) use ($stateColumn, $estadoCanceladoId) {
                    $cancelados->whereNull($stateColumn)
                        ->orWhere($stateColumn, '<>', $estadoCanceladoId);
                });
            }
        });
    }

    private function resolveEmsThresholdDays(string $destino, bool $esProvincia): array
    {
        $baseDestino = $this->resolveEmsBaseDestino($destino);
        $verde = in_array($baseDestino, self::DESTINOS_LARGA_DISTANCIA, true) ? 2 : 1;
        $amarillo = $verde + 1;

        if ($esProvincia) {
            $verde += 1;
            $amarillo += 1;
        }

        return [
            'green' => $verde,
            'yellow' => $amarillo,
        ];
    }

    private function resolveEmsBaseDestino(string $destino): string
    {
        $normalized = $this->normalizeDestino($destino);

        if (str_contains($normalized, 'SANTA CRUZ')) {
            return 'SANTA CRUZ';
        }

        if (str_contains($normalized, 'TARIJA')) {
            return 'TARIJA';
        }

        if (str_contains($normalized, 'TRINIDAD') || str_contains($normalized, 'TRINIDAD')) {
            return 'TRINIDAD';
        }

        foreach (self::DESTINOS_BASE as $base) {
            if (str_contains($normalized, $base)) {
                return $base;
            }
        }

        return $normalized;
    }

    private function isEmsProvincia(string $destino): bool
    {
        $normalized = $this->normalizeDestino($destino);
        if ($normalized === '' || $normalized === '-') {
            return false;
        }

        if (str_contains($normalized, 'PROV')) {
            return true;
        }

        if (in_array($normalized, self::DESTINOS_BASE, true) || in_array($normalized, self::DESTINOS_CAPITALES, true)) {
            return false;
        }

        foreach (self::DESTINOS_BASE as $base) {
            if ($normalized === $base) {
                return false;
            }

            if (
                str_starts_with($normalized, $base . ' ') ||
                str_starts_with($normalized, $base . '-') ||
                str_starts_with($normalized, $base . ',') ||
                str_starts_with($normalized, $base . '/')
            ) {
                return true;
            }
        }

        return true;
    }

    private function normalizeDestino(string $value): string
    {
        $value = strtoupper(trim($value));
        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function safeCarbonValue($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildKpisPeriodo(array $modulosSeleccionados, string $departamento = ''): array
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

                $registrosQuery = DB::table($config['table'])
                    ->whereBetween('created_at', [$desde, $hasta]);
                $this->applyDepartamentoFilter($registrosQuery, $config, $departamento);
                $this->excludeCanceledState($registrosQuery, $config['estado_column'], $this->resolveEstadoIdByName('CANCELADO'));
                $countRegistros += (int) $registrosQuery->count();

                $entregasQuery = DB::table($config['event_table'])
                    ->where('evento_id', self::EVENTO_ENTREGADO_ID)
                    ->whereBetween($config['event_table'] . '.created_at', [$desde, $hasta]);
                $this->applyEventDepartamentoFilter($entregasQuery, $config, $departamento);
                $this->excludeCanceledPackageForEvent($entregasQuery, $config, $config['event_table']);
                $countEntregas += (int) $entregasQuery->count(DB::raw('distinct ' . $config['event_table'] . '.codigo'));
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
        string $agrupacion,
        string $departamento = ''
    ): array {
        [$chartFrom, $chartTo, $chartLabel] = $this->resolveChartRange($from, $to, $rangoLabel, $rangoKey, $agrupacion);
        [$labels, $bucketExpression] = $this->buildBuckets($chartFrom, $chartTo, $agrupacion);

        $registrosMap = array_fill_keys($labels, 0);
        $entregadosMap = array_fill_keys($labels, 0);

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];

            $rowsRegistros = DB::table($config['table'])
                ->selectRaw($bucketExpression . ' as bucket, COUNT(*) as total')
                ->whereBetween('created_at', [$chartFrom, $chartTo]);
            $this->applyDepartamentoFilter($rowsRegistros, $config, $departamento);
            $this->excludeCanceledState($rowsRegistros, $config['estado_column'], $this->resolveEstadoIdByName('CANCELADO'));
            $rowsRegistros = $rowsRegistros
                ->groupBy(DB::raw($bucketExpression))
                ->pluck('total', 'bucket')
                ->toArray();

            foreach ($rowsRegistros as $bucket => $count) {
                if (array_key_exists($bucket, $registrosMap)) {
                    $registrosMap[$bucket] += (int) $count;
                }
            }

            $eventBucketExpression = str_replace('created_at', $config['event_table'] . '.created_at', $bucketExpression);
            $rowsEntregados = DB::table($config['event_table'])
                ->selectRaw($eventBucketExpression . ' as bucket, COUNT(DISTINCT ' . $config['event_table'] . '.codigo) as total')
                ->where('evento_id', self::EVENTO_ENTREGADO_ID)
                ->whereBetween($config['event_table'] . '.created_at', [$chartFrom, $chartTo]);
            $this->applyEventDepartamentoFilter($rowsEntregados, $config, $departamento);
            $this->excludeCanceledPackageForEvent($rowsEntregados, $config, $config['event_table']);
            $rowsEntregados = $rowsEntregados
                ->groupBy(DB::raw($eventBucketExpression))
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

    private function buildRankingEntregadores(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, ?int $limit = 10, string $departamento = '', string $departamentoCartero = '')
    {
        $queries = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $query = DB::table($config['event_table'])
                ->select([
                    $config['event_table'] . '.user_id as user_id',
                    DB::raw("'" . $config['label'] . "' as modulo"),
                    DB::raw('COUNT(DISTINCT ' . $config['event_table'] . '.codigo) as total'),
                ])
                ->where('evento_id', self::EVENTO_ENTREGADO_ID)
                ->groupBy($config['event_table'] . '.user_id');

            $this->applyDateFilter($query, $config['event_table'] . '.created_at', $from, $to);
            $this->applyEventDepartamentoFilter($query, $config, $departamento);
            $this->excludeCanceledPackageForEvent($query, $config, $config['event_table']);
            $queries[] = $query;
        }

        return $this->resolveRankingUsuarios($queries, 'total_entregados', $limit, $departamentoCartero);
    }

    private function buildRankingAsignadosCartero(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, ?int $limit = 10, string $departamento = '', string $departamentoCartero = '')
    {
        $queries = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $eventTable = $config['event_table'];

            $query = DB::table($eventTable)
                ->join('eventos', 'eventos.id', '=', $eventTable . '.evento_id')
                ->select([
                    $eventTable . '.user_id as user_id',
                    DB::raw("'" . $config['label'] . "' as modulo"),
                    DB::raw('COUNT(DISTINCT ' . $eventTable . '.codigo) as total'),
                ])
                ->where(function ($q) {
                    $q->whereRaw('LOWER(eventos.nombre_evento) LIKE ?', ['%asignado a cartero%'])
                        ->orWhereRaw('LOWER(eventos.nombre_evento) LIKE ?', ['%camino para entrega fisica%'])
                        ->orWhereRaw('LOWER(eventos.nombre_evento) LIKE ?', ['%transferido al agente de entrega%']);
                })
                ->groupBy($eventTable . '.user_id');

            $this->applyDateFilter($query, $eventTable . '.created_at', $from, $to);
            $this->applyEventDepartamentoFilter($query, $config, $departamento);
            $this->excludeCanceledPackageForEvent($query, $config, $eventTable);
            $queries[] = $query;
        }

        return $this->resolveRankingUsuarios($queries, 'total_asignados', $limit, $departamentoCartero);
    }

    private function buildRankingEntregasVentanilla(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, ?int $limit = 10, string $departamento = '', string $departamentoCartero = '')
    {
        $queries = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $eventTable = $config['event_table'];

            $query = DB::table($eventTable . ' as delivered')
                ->select([
                    'delivered.user_id as user_id',
                    DB::raw("'" . $config['label'] . "' as modulo"),
                    DB::raw('COUNT(DISTINCT delivered.codigo) as total'),
                ])
                ->where('delivered.evento_id', self::EVENTO_ENTREGADO_ID)
                ->whereExists(function ($sub) use ($eventTable) {
                    $sub->selectRaw('1')
                        ->from($eventTable . ' as ventanilla_event')
                        ->whereColumn('ventanilla_event.codigo', 'delivered.codigo')
                        ->where('ventanilla_event.evento_id', self::EVENTO_ENVIADO_VENTANILLA_ID)
                        ->whereColumn('ventanilla_event.created_at', '<=', 'delivered.created_at');
                })
                ->groupBy('delivered.user_id');

            $this->applyDateFilter($query, 'delivered.created_at', $from, $to);
            $this->applyEventDepartamentoFilter($query, $config, $departamento, 'delivered');
            $this->excludeCanceledPackageForEvent($query, $config, 'delivered');
            $queries[] = $query;
        }

        return $this->resolveRankingUsuarios($queries, 'total_ventanilla', $limit, $departamentoCartero);
    }

    private function buildRankingDepartamentos(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to)
    {
        $estadoEntregadoId = $this->resolveEstadoIdByName('ENTREGADO');
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');

        $rows = collect($this->departamentoAliasMap())
            ->map(function (array $aliases, string $departamento) use ($modulosSeleccionados, $from, $to, $estadoEntregadoId, $estadoCanceladoId) {
                $total = 0;
                $entregados = 0;
                $cancelados = 0;

                foreach ($modulosSeleccionados as $moduloKey) {
                    $config = self::MODULOS[$moduloKey];

                    $query = DB::table($config['table']);
                    $this->applyDateFilter($query, 'created_at', $from, $to);
                    $this->applyDepartamentoAliasesFilter($query, $config, $aliases);

                    $querySinCancelados = clone $query;
                    $this->excludeCanceledState($querySinCancelados, $config['estado_column'], $estadoCanceladoId);

                    $total += (int) (clone $querySinCancelados)->count();
                    $entregados += $estadoEntregadoId
                        ? (int) (clone $querySinCancelados)->where($config['estado_column'], $estadoEntregadoId)->count()
                        : 0;
                    $cancelados += $estadoCanceladoId
                        ? (int) (clone $query)->where($config['estado_column'], $estadoCanceladoId)->count()
                        : 0;
                }

                $pendientes = max(0, $total - $entregados);
                $cumplimiento = $total > 0 ? round(($entregados * 100) / $total, 1) : 0.0;
                $topEntregador = $this->buildTopEntregadorDepartamento($modulosSeleccionados, $from, $to, $aliases);
                $detalleEntregados = $this->buildDepartamentoDeliveredDetails($modulosSeleccionados, $from, $to, $aliases);
                $detallePendientes = $this->buildDepartamentoPendingDetails($modulosSeleccionados, $from, $to, $aliases, $estadoEntregadoId, $estadoCanceladoId);

                return (object) [
                    'departamento' => $departamento,
                    'total' => $total,
                    'entregados' => $entregados,
                    'pendientes' => $pendientes,
                    'cumplimiento' => $cumplimiento,
                    'top_entregador' => $topEntregador?->name ?? 'SIN DATOS',
                    'top_entregador_total' => (int) ($topEntregador?->total_entregados ?? 0),
                    'entregados_por_modulo' => $detalleEntregados['totales'],
                    'entregados_detalle' => $detalleEntregados['rows'],
                    'pendientes_por_modulo' => $detallePendientes['totales'],
                    'pendientes_detalle' => $detallePendientes['rows'],
                ];
            });

        $totalNacional = (int) $rows->sum('total');
        $entregadosNacional = (int) $rows->sum('entregados');

        return $rows
            ->map(function ($row) use ($totalNacional, $entregadosNacional) {
                $row->total_nacional = $totalNacional;
                $row->entregados_nacional = $entregadosNacional;
                $row->participacion_nacional = $totalNacional > 0
                    ? round(((int) $row->total * 100) / $totalNacional, 1)
                    : 0.0;
                $row->aporte_entregado_nacional = $totalNacional > 0
                    ? round(((int) $row->entregados * 100) / $totalNacional, 1)
                    : 0.0;
                $row->participacion_entregas_nacionales = $entregadosNacional > 0
                    ? round(((int) $row->entregados * 100) / $entregadosNacional, 1)
                    : 0.0;
                $row->ranking_cumplimiento_peso = (int) (self::RANKING_CUMPLIMIENTO_WEIGHT * 100);
                $row->ranking_participacion_peso = (int) (self::RANKING_PARTICIPACION_WEIGHT * 100);
                $row->puntaje_ranking = round(
                    ((float) $row->cumplimiento * self::RANKING_CUMPLIMIENTO_WEIGHT)
                    + ((float) ($row->participacion_nacional ?? 0) * self::RANKING_PARTICIPACION_WEIGHT),
                    1
                );

                return $row;
            })
            ->sortByDesc(fn ($row) => sprintf(
                '%08.1f-%08.1f-%08.1f-%08d',
                (float) ($row->puntaje_ranking ?? 0),
                (float) $row->cumplimiento,
                (float) ($row->participacion_nacional ?? 0),
                (int) $row->entregados
            ))
            ->values()
            ->map(function ($row, int $index) {
                $row->puesto = $index + 1;
                return $row;
            });
    }

    private function buildTopEntregadorDepartamento(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases)
    {
        $queries = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $eventTable = $config['event_table'];

            $query = DB::table($eventTable)
                ->join($config['table'] . ' as pkg_departamento', 'pkg_departamento.codigo', '=', $eventTable . '.codigo')
                ->select([
                    $eventTable . '.user_id as user_id',
                    DB::raw("'" . $config['label'] . "' as modulo"),
                    DB::raw('COUNT(DISTINCT ' . $eventTable . '.codigo) as total'),
                ])
                ->where($eventTable . '.evento_id', self::EVENTO_ENTREGADO_ID)
                ->groupBy($eventTable . '.user_id');

            $this->applyDateFilter($query, $eventTable . '.created_at', $from, $to);
            $this->applyDepartamentoAliasesFilter($query, $config, $aliases, 'pkg_departamento');
            $this->excludeCanceledState($query, 'pkg_departamento.' . $config['estado_column'], $this->resolveEstadoIdByName('CANCELADO'));
            $queries[] = $query;
        }

        return $this->resolveRankingUsuarios($queries, 'total_entregados', 1)->first();
    }

    private function buildDepartamentoDeliveredDetails(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases): array
    {
        $totales = [
            'EMS' => 0,
            'CONTRATOS' => 0,
            'CERTIFICADOS' => 0,
            'ORDINARIOS' => 0,
        ];
        $rows = collect();

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $eventTable = $config['event_table'];
            $label = $config['label'];

            $query = DB::table($eventTable)
                ->join($config['table'] . ' as pkg_departamento', 'pkg_departamento.codigo', '=', $eventTable . '.codigo')
                ->leftJoin('users', 'users.id', '=', $eventTable . '.user_id')
                ->select([
                    DB::raw("'" . $label . "' as modulo"),
                    $eventTable . '.codigo as codigo',
                    DB::raw("coalesce(MAX(users.name), 'SIN USUARIO') as usuario"),
                    DB::raw('MIN(' . $eventTable . '.created_at) as entregado_at'),
                ])
                ->where($eventTable . '.evento_id', self::EVENTO_ENTREGADO_ID)
                ->groupBy($eventTable . '.codigo');

            $this->applyDateFilter($query, $eventTable . '.created_at', $from, $to);
            $this->applyDepartamentoAliasesFilter($query, $config, $aliases, 'pkg_departamento');
            $this->excludeCanceledState($query, 'pkg_departamento.' . $config['estado_column'], $this->resolveEstadoIdByName('CANCELADO'));

            $moduleRows = $query->orderByDesc(DB::raw('MIN(' . $eventTable . '.created_at)'))->get();
            $totales[$label] = (int) $moduleRows->count();
            $rows = $rows->concat($moduleRows);
        }

        return [
            'totales' => $totales,
            'rows' => $rows
                ->sortByDesc(fn ($row) => (string) ($row->entregado_at ?? ''))
                ->values()
                ->map(fn ($row) => [
                    'modulo' => (string) ($row->modulo ?? ''),
                    'codigo' => (string) ($row->codigo ?? ''),
                    'usuario' => (string) ($row->usuario ?? ''),
                    'entregado_at' => (string) ($row->entregado_at ?? ''),
                ])
                ->all(),
        ];
    }

    private function buildDepartamentoPendingDetails(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases, ?int $estadoEntregadoId, ?int $estadoCanceladoId): array
    {
        $totales = [
            'EMS' => 0,
            'CONTRATOS' => 0,
            'CERTIFICADOS' => 0,
            'ORDINARIOS' => 0,
        ];
        $rows = collect();

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $label = $config['label'];
            $estadoColumn = $config['estado_column'];

            $identityColumns = $this->packageIdentityColumns($moduloKey, $config);

            $query = DB::table($config['table'] . ' as t')
                ->leftJoin('estados as e', 'e.id', '=', 't.' . $estadoColumn)
                ->select([
                    DB::raw("'" . $label . "' as modulo"),
                    't.codigo as codigo',
                    DB::raw("coalesce(e.nombre_estado, 'SIN ESTADO') as estado"),
                    DB::raw($identityColumns['origen'] . ' as origen'),
                    DB::raw($identityColumns['destino'] . ' as destino'),
                    DB::raw($identityColumns['destinatario'] . ' as destinatario'),
                    't.created_at as creado_at',
                ]);

            $this->applyDateFilter($query, 't.created_at', $from, $to);
            $this->applyDepartamentoAliasesFilter($query, $config, $aliases, 't');

            if ($estadoEntregadoId) {
                $query->where(function (Builder $sub) use ($estadoColumn, $estadoEntregadoId) {
                    $sub->whereNull('t.' . $estadoColumn)
                        ->orWhere('t.' . $estadoColumn, '<>', $estadoEntregadoId);
                });
            }

            if ($estadoCanceladoId) {
                $query->where(function (Builder $sub) use ($estadoColumn, $estadoCanceladoId) {
                    $sub->whereNull('t.' . $estadoColumn)
                        ->orWhere('t.' . $estadoColumn, '<>', $estadoCanceladoId);
                });
            }

            $moduleRows = $query->orderByDesc('t.created_at')->get();
            $totales[$label] = (int) $moduleRows->count();
            $rows = $rows->concat($moduleRows);
        }

        return [
            'totales' => $totales,
            'rows' => $rows
                ->sortByDesc(fn ($row) => (string) ($row->creado_at ?? ''))
                ->values()
                ->map(fn ($row) => [
                    'modulo' => (string) ($row->modulo ?? ''),
                    'codigo' => (string) ($row->codigo ?? ''),
                    'estado' => (string) ($row->estado ?? ''),
                    'origen' => (string) ($row->origen ?? ''),
                    'destino' => (string) ($row->destino ?? ''),
                    'destinatario' => (string) ($row->destinatario ?? ''),
                    'creado_at' => (string) ($row->creado_at ?? ''),
                ])
                ->all(),
        ];
    }

    private function packageIdentityColumns(string $moduloKey, array $config): array
    {
        $destinoColumn = "coalesce(t." . $config['departamento_column'] . ", '-')";

        return match ($moduloKey) {
            'contrato' => [
                'origen' => "coalesce(t.origen, '-')",
                'destino' => $destinoColumn,
                'destinatario' => "coalesce(t.nombre_d, '-')",
            ],
            'ems' => [
                'origen' => "coalesce(t.origen, '-')",
                'destino' => $destinoColumn,
                'destinatario' => "coalesce(t.nombre_destinatario, '-')",
            ],
            default => [
                'origen' => "'-'",
                'destino' => $destinoColumn,
                'destinatario' => "coalesce(t.destinatario, '-')",
            ],
        };
    }

    private function applyDepartamentoFilter(Builder $query, array $config, string $departamento, string $tableAlias = ''): void
    {
        if ($departamento === '') {
            return;
        }

        $column = (string) ($config['departamento_column'] ?? '');
        if ($column === '') {
            return;
        }

        $qualifiedColumn = ($tableAlias !== '' ? $tableAlias . '.' : '') . $column;
        $query->whereRaw('trim(upper(' . $qualifiedColumn . ')) = ?', [$departamento]);
    }

    private function applyDepartamentoAliasesFilter(Builder $query, array $config, array $aliases, string $tableAlias = ''): void
    {
        $column = (string) ($config['departamento_column'] ?? '');
        $aliases = collect($aliases)
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($column === '' || empty($aliases)) {
            return;
        }

        $qualifiedColumn = ($tableAlias !== '' ? $tableAlias . '.' : '') . $column;
        $query->whereIn(DB::raw('trim(upper(' . $qualifiedColumn . '))'), $aliases);
    }

    private function departamentoAliasMap(): array
    {
        return [
            'LA PAZ' => ['LA PAZ'],
            'COCHABAMBA' => ['COCHABAMBA'],
            'SANTA CRUZ' => ['SANTA CRUZ'],
            'ORURO' => ['ORURO'],
            'POTOSI' => ['POTOSI'],
            'TARIJA' => ['TARIJA'],
            'CHUQUISACA' => ['CHUQUISACA', 'SUCRE'],
            'BENI' => ['BENI', 'TRINIDAD'],
            'PANDO' => ['PANDO', 'COBIJA'],
        ];
    }

    private function applyEventDepartamentoFilter(Builder $query, array $config, string $departamento, string $eventAlias = ''): void
    {
        if ($departamento === '') {
            return;
        }

        $eventCodeColumn = ($eventAlias !== '' ? $eventAlias : $config['event_table']) . '.codigo';
        $query->join($config['table'] . ' as pkg_departamento', 'pkg_departamento.codigo', '=', $eventCodeColumn);
        $this->applyDepartamentoFilter($query, $config, $departamento, 'pkg_departamento');
    }

    private function buildRankingRegistradores(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, string $departamento = '')
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
                    $config['event_table'] . '.user_id as user_id',
                    DB::raw("'" . $config['label'] . "' as modulo"),
                    DB::raw('COUNT(DISTINCT ' . $config['event_table'] . '.codigo) as total'),
                ])
                ->whereIn('evento_id', $registroEventos)
                ->groupBy($config['event_table'] . '.user_id');

            $this->applyDateFilter($query, $config['event_table'] . '.created_at', $from, $to);
            $this->applyEventDepartamentoFilter($query, $config, $departamento);
            $queries[] = $query;
        }

        return $this->resolveRankingUsuarios($queries, 'total_registrados');
    }

    private function resolveRankingUsuarios(array $queries, string $totalAlias, ?int $limit = 10, string $departamentoCartero = '')
    {
        if (empty($queries)) {
            return collect();
        }

        $base = array_shift($queries);
        foreach ($queries as $nextQuery) {
            $base->unionAll($nextQuery);
        }

        $query = DB::query()
            ->fromSub($base, 'r')
            ->join('users', 'users.id', '=', 'r.user_id')
            ->select([
                'users.id',
                'users.name',
                'users.ciudad',
                DB::raw('SUM(r.total) as ' . $totalAlias),
                DB::raw("SUM(CASE WHEN r.modulo = 'EMS' THEN r.total ELSE 0 END) as ems"),
                DB::raw("SUM(CASE WHEN r.modulo = 'CONTRATOS' THEN r.total ELSE 0 END) as contrato"),
                DB::raw("SUM(CASE WHEN r.modulo = 'CERTIFICADOS' THEN r.total ELSE 0 END) as certi"),
                DB::raw("SUM(CASE WHEN r.modulo = 'ORDINARIOS' THEN r.total ELSE 0 END) as ordi"),
            ])
            ->groupBy('users.id', 'users.name', 'users.ciudad')
            ->orderByDesc($totalAlias);

        if ($departamentoCartero !== '') {
            $query->whereRaw('trim(upper(users.ciudad)) = ?', [$departamentoCartero]);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    private function buildExecutiveInsights(
        array $totales,
        array $resumenPorModulo,
        array $trendLabels,
        array $trendSeries,
        $rankingEntregadores,
        $rankingRegistradores,
        $rankingDepartamentos = null
    ): array {
        $modulos = array_values($resumenPorModulo);
        $mejorModulo = collect($modulos)->sortByDesc('tasa_entrega')->first();
        $riesgoModulo = collect($modulos)->sortByDesc(function ($fila) {
            $base = max(1, (int) $fila['total']);
            return (($fila['rezago'] + $fila['atrasados']) / $base);
        })->first();
        $moduloMayorCarga = collect($modulos)->sortByDesc('total')->first();

        $topEntregador = $rankingEntregadores->first();
        $topRegistrador = $rankingRegistradores->first();
        $topDepartamento = $rankingDepartamentos ? $rankingDepartamentos->first() : null;

        $registrosTrend = $trendSeries['registros'] ?? [];
        $entregasTrend = $trendSeries['entregados'] ?? [];
        $ultimoReg = (int) ($registrosTrend[count($registrosTrend) - 1] ?? 0);
        $anteriorReg = (int) ($registrosTrend[count($registrosTrend) - 2] ?? 0);
        $ultimoEnt = (int) ($entregasTrend[count($entregasTrend) - 1] ?? 0);
        $anteriorEnt = (int) ($entregasTrend[count($entregasTrend) - 2] ?? 0);

        $varRegPct = $anteriorReg > 0 ? round((($ultimoReg - $anteriorReg) * 100) / $anteriorReg, 1) : null;
        $varEntPct = $anteriorEnt > 0 ? round((($ultimoEnt - $anteriorEnt) * 100) / $anteriorEnt, 1) : null;

        $rezagoRatio = $totales['paquetes'] > 0
            ? round(($totales['rezago'] * 100) / $totales['paquetes'], 1)
            : 0.0;
        $atrasoRatio = $totales['paquetes'] > 0
            ? round(($totales['atrasados'] * 100) / $totales['paquetes'], 1)
            : 0.0;

        $resumenEjecutivo = [];
        $resumenEjecutivo[] = 'Se registraron ' . number_format((int) $totales['paquetes']) . ' envios, con una tasa de entrega global de ' . number_format((float) $totales['porcentaje_entrega'], 1) . '%.';
        $resumenEjecutivo[] = 'El modulo con mayor volumen fue ' . ($moduloMayorCarga['label'] ?? 'N/D') . ' (' . number_format((int) ($moduloMayorCarga['total'] ?? 0)) . ' registros).';
        $resumenEjecutivo[] = 'El rezago representa ' . number_format($rezagoRatio, 1) . '% y el retraso en inventario ' . number_format($atrasoRatio, 1) . '% del total procesado.';

        $hallazgos = [];
        if ($mejorModulo) {
            $hallazgos[] = 'Mejor desempeno: ' . $mejorModulo['label'] . ' con ' . number_format((float) $mejorModulo['tasa_entrega'], 1) . '% de cumplimiento.';
        }
        if ($riesgoModulo) {
            $base = max(1, (int) $riesgoModulo['total']);
            $riesgoPct = round((($riesgoModulo['rezago'] + $riesgoModulo['atrasados']) * 100) / $base, 1);
            $hallazgos[] = 'Mayor riesgo operativo: ' . $riesgoModulo['label'] . ' con ' . number_format($riesgoPct, 1) . '% entre rezago y retraso.';
        }
        if ($varRegPct !== null) {
            $hallazgos[] = 'Variacion de registros del ultimo periodo: ' . ($varRegPct >= 0 ? '+' : '') . number_format($varRegPct, 1) . '%.';
        }
        if ($varEntPct !== null) {
            $hallazgos[] = 'Variacion de entregas del ultimo periodo: ' . ($varEntPct >= 0 ? '+' : '') . number_format($varEntPct, 1) . '%.';
        }
        if ($topEntregador) {
            $hallazgos[] = 'Top entregador: ' . $topEntregador->name . ' (' . number_format((int) $topEntregador->total_entregados) . ' entregas).';
        }
        if ($topRegistrador) {
            $hallazgos[] = 'Top registrador: ' . $topRegistrador->name . ' (' . number_format((int) $topRegistrador->total_registrados) . ' registros).';
        }
        if ($topDepartamento) {
            $hallazgos[] = 'Departamento #1: ' . $topDepartamento->departamento . ' con ' . number_format((float) $topDepartamento->cumplimiento, 1) . '% de cumplimiento y ' . number_format((int) $topDepartamento->entregados) . ' entregados.';
        }

        $recomendaciones = [];
        if ($rezagoRatio >= 8) {
            $recomendaciones[] = 'Priorizar plan de descarga de rezago en modulos criticos con ventana operativa diaria y seguimiento por responsable.';
        } else {
            $recomendaciones[] = 'Mantener el control de rezago con cortes semanales y alertas tempranas por incremento de inventario no entregado.';
        }
        if ($atrasoRatio >= 10) {
            $recomendaciones[] = 'Revisar tiempos de ciclo operativo para reducir volumen en retraso antes de que pase a rezago.';
        } else {
            $recomendaciones[] = 'Sostener el control de tiempos con monitoreo por modulo y auditoria de excepciones.';
        }
        $recomendaciones[] = 'Alinear metas por modulo con el porcentaje de entrega objetivo y seguimiento semanal en comite operativo.';

        return [
            'resumen_ejecutivo' => $resumenEjecutivo,
            'hallazgos' => $hallazgos,
            'recomendaciones' => $recomendaciones,
            'modulo_mejor' => $mejorModulo,
            'modulo_riesgo' => $riesgoModulo,
            'modulo_mayor_carga' => $moduloMayorCarga,
            'ratios' => [
                'rezago_pct' => $rezagoRatio,
                'atraso_pct' => $atrasoRatio,
            ],
            'variaciones' => [
                'registros_pct' => $varRegPct,
                'entregas_pct' => $varEntPct,
            ],
            'top' => [
                'entregador' => $topEntregador?->name,
                'registrador' => $topRegistrador?->name,
                'departamento' => $topDepartamento?->departamento,
            ],
            'ultimo_periodo' => [
                'label' => $trendLabels[count($trendLabels) - 1] ?? null,
                'registros' => $ultimoReg,
                'entregados' => $ultimoEnt,
            ],
        ];
    }
}


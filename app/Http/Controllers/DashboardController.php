<?php

namespace App\Http\Controllers;

use App\Exports\DashboardReportExport;
use App\Exports\DashboardEntregasWorkbookExport;
use App\Exports\DashboardRankingDepartamentosExport;
use App\Models\Cartero;
use App\Models\Estado;
use App\Models\Recojo;
use App\Models\SolicitudCliente;
use App\Support\BitacoraCn33Service;
use App\Support\BoliviaBusinessCalendar;
use App\Support\DeliveryFulfillment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    public function __construct(
        private readonly BitacoraCn33Service $cn33Service,
        private readonly BoliviaBusinessCalendar $businessCalendar
    ) {
    }

    private array $estadoIdCache = [];

    private const EVENTO_ENTREGADO_ID = 316;
    private const EVENTO_ENVIADO_VENTANILLA_ID = 312;
    private const EVENTO_EMS_SOLICITUD_ID = 295;
    private const CERTI_ORDI_GREEN_DAYS = 7;
    private const CERTI_ORDI_YELLOW_DAYS = 15;
    private const DASHBOARD_CACHE_SECONDS = 300;
    private const DASHBOARD_ALERT_CACHE_SECONDS = 20;
    private const DASHBOARD_HEAVY_ALERT_CACHE_SECONDS = 300;
    private const DASHBOARD_MAX_EXECUTION_SECONDS = 180;
    private const DASHBOARD_INLINE_DEPARTMENT_MAX_ROWS = 100000;
    private const DASHBOARD_MODULE_KEYS = ['ems', 'contrato'];
    private const ENTREGAS_EXCLUDED_COURIER_NAMES = [
        'pasante',
        'leonardo doria medina ochoa',
    ];
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
    private const CHART_ORIGIN_ALIASES = [
        'CHUQUISACA' => 'SUCRE',
        'BENI' => 'TRINIDAD',
        'PANDO' => 'COBIJA',
        'SANTA CRUZ DE LA SIERRA' => 'SANTA CRUZ',
    ];

    private const MODULOS = [
        'ems' => [
            'label' => 'EMS',
            'table' => 'paquetes_ems',
            'estado_column' => 'estado_id',
            'origen_column' => 'origen',
            'departamento_column' => 'ciudad',
            'peso_column' => 'peso',
            'event_table' => 'eventos_ems',
            'registro_eventos' => [295],
            'operational_start_events' => [295],
            'late_hours' => 48,
            'start_expression' => 'coalesce(t.created_at, t.created_at)',
        ],
        'contrato' => [
            'label' => 'CONTRATOS',
            'table' => 'paquetes_contrato',
            'estado_column' => 'estados_id',
            'origen_column' => 'origen',
            'departamento_column' => 'destino',
            'peso_column' => 'peso',
            'event_table' => 'eventos_contrato',
            'registro_eventos' => [318, 295],
            'operational_start_events' => [295],
            'late_hours' => 72,
            'start_expression' => 'coalesce(t.fecha_recojo, t.created_at)',
        ],
        'certi' => [
            'label' => 'CERTIFICADOS',
            'table' => 'paquetes_certi',
            'estado_column' => 'fk_estado',
            'origen_column' => null,
            'departamento_column' => 'cuidad',
            'peso_column' => 'peso',
            'event_table' => 'eventos_certi',
            'registro_eventos' => [168],
            'operational_start_events' => [168],
            'late_hours' => 24 * 15,
            'start_expression' => 'coalesce(t.created_at, t.created_at)',
        ],
        'ordi' => [
            'label' => 'ORDINARIOS',
            'table' => 'paquetes_ordi',
            'estado_column' => 'fk_estado',
            'origen_column' => null,
            'departamento_column' => 'ciudad',
            'peso_column' => 'peso',
            'event_table' => 'eventos_ordi',
            'registro_eventos' => [295],
            'operational_start_events' => [295],
            'late_hours' => 24 * 15,
            'start_expression' => 'coalesce(t.created_at, t.created_at)',
        ],
    ];

    public function index(Request $request)
    {
        @set_time_limit(self::DASHBOARD_MAX_EXECUTION_SECONDS);
        @ini_set('max_execution_time', (string) self::DASHBOARD_MAX_EXECUTION_SECONDS);

        $data = Cache::flexible(
            $this->dashboardCacheKey($request),
            [self::DASHBOARD_CACHE_SECONDS, self::DASHBOARD_CACHE_SECONDS * 3],
            fn () => $this->buildDashboardData($request, false, false)
        );

        // El resumen puede servirse unos minutos mientras se actualiza en segundo
        // plano. Las alertas operativas conservan su cache corto independiente.
        $data = array_replace($data, $this->cachedDashboardAlerts(Auth::user(), self::DASHBOARD_MODULE_KEYS));

        return view('dashboard', $data);
    }

    public function chartVolumeData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 3)],
            'meses' => ['required', 'array', 'min:1', 'max:12'],
            'meses.*' => ['required', 'integer', 'distinct', 'between:1,12'],
            'departamentos' => ['required', 'array', 'min:1', 'max:9'],
            'departamentos.*' => ['required', 'string', 'distinct', Rule::in(self::DESTINOS_BASE)],
        ]);

        $year = (int) $validated['anio'];
        $months = collect($validated['meses'])->map(fn ($month): int => (int) $month)->sort()->values()->all();
        $departments = collect($validated['departamentos'])->sort()->values()->all();
        $filterDepartments = count($departments) < count(self::DESTINOS_BASE);
        $deliveredId = $this->resolveEstadoIdByName('ENTREGADO');
        $cancelledId = $this->resolveEstadoIdByName('CANCELADO');
        $byDepartment = array_fill_keys($departments, [
            'registrados' => 0,
            'peso' => 0.0,
            'entregas' => 0,
        ]);
        $aliases = collect($departments)
            ->flatMap(fn (string $department): array => [
                $department,
                ...array_keys(self::CHART_ORIGIN_ALIASES, $department, true),
            ])
            ->all();

        foreach (self::DASHBOARD_MODULE_KEYS as $moduleKey) {
            $config = self::MODULOS[$moduleKey];
            $query = DB::table($config['table']);
            $this->applyChartMonthsFilter($query, $year, $months);
            if ($filterDepartments) {
                $this->applyOrigenAliasesFilter($query, $config, $aliases);
            }
            $this->excludeTestCompanyPackages($query, $config);

            $originExpression = 'trim(upper('.$this->effectiveOrigenExpression($config).'))';
            $stateColumn = $config['estado_column'];
            $notCancelled = $cancelledId
                ? "{$stateColumn} IS NULL OR {$stateColumn} <> ".(int) $cancelledId
                : 'TRUE';
            $delivered = $deliveredId ? "{$stateColumn} = ".(int) $deliveredId : 'FALSE';
            $aggregates = $query
                ->selectRaw("{$originExpression} as origen")
                ->selectRaw("COUNT(DISTINCT CASE WHEN ({$notCancelled}) THEN codigo END) as total")
                ->selectRaw("COUNT(DISTINCT CASE WHEN ({$notCancelled}) AND ({$delivered}) THEN codigo END) as entregados")
                ->selectRaw("COALESCE(SUM(CASE WHEN ({$notCancelled}) THEN COALESCE({$config['peso_column']}, 0) ELSE 0 END), 0) as peso_total")
                ->groupByRaw($originExpression)
                ->get();

            foreach ($aggregates as $aggregate) {
                $origin = (string) $aggregate->origen;
                $department = self::CHART_ORIGIN_ALIASES[$origin] ?? $origin;
                if (! in_array($department, self::DESTINOS_BASE, true)) {
                    if ($filterDepartments) {
                        continue;
                    }
                    $department = 'SIN ORIGEN ASIGNADO';
                }
                $byDepartment[$department] ??= ['registrados' => 0, 'peso' => 0.0, 'entregas' => 0];
                $byDepartment[$department]['registrados'] += (int) $aggregate->total;
                $byDepartment[$department]['peso'] += (float) $aggregate->peso_total;
                $byDepartment[$department]['entregas'] += (int) $aggregate->entregados;
            }
        }

        $labels = array_keys($byDepartment);
        $registered = array_column($byDepartment, 'registrados');
        $weights = array_map(fn (array $values): float => round($values['peso'], 3), array_values($byDepartment));
        $deliveries = array_column($byDepartment, 'entregas');

        return response()->json([
            'labels' => $labels,
            'registrados' => $registered,
            'peso' => $weights,
            'entregas' => $deliveries,
            'totales' => [
                'registrados' => array_sum($registered),
                'peso' => round(array_sum($weights), 3),
                'entregas' => array_sum($deliveries),
            ],
            'meses' => $months,
            'anio' => $year,
            'departamentos' => $departments,
        ]);
    }

    private function applyChartMonthsFilter(Builder $query, int $year, array $months): void
    {
        $periods = [];
        $first = $previous = $months[0];
        foreach (array_slice($months, 1) as $month) {
            if ($month !== $previous + 1) {
                $periods[] = [$first, $previous];
                $first = $month;
            }
            $previous = $month;
        }
        $periods[] = [$first, $previous];

        $query->where(function (Builder $selectedMonths) use ($year, $periods): void {
            foreach ($periods as [$firstMonth, $lastMonth]) {
                $start = Carbon::create($year, $firstMonth, 1)->startOfDay();
                $end = Carbon::create($year, $lastMonth, 1)->endOfMonth()->endOfDay();
                $selectedMonths->orWhereBetween('created_at', [$start, $end]);
            }
        });
    }

    public function departmentAlertDetails(Request $request)
    {
        $type = strtolower(trim((string) $request->query('type', '')));
        abort_unless(in_array($type, ['pickup', 'pending'], true), 404);

        $requestedDepartment = strtoupper(trim(preg_replace(
            '/\s+/',
            ' ',
            (string) $request->query('department', '')
        ) ?? ''));
        $department = $type === 'pending'
            ? $this->normalizePendingAlertDepartment($requestedDepartment)
            : $requestedDepartment;
        $allowedDepartments = array_merge(
            self::DESTINOS_BASE,
            ['SUCRE', 'TRINIDAD', 'COBIJA', 'SIN DEPARTAMENTO', 'CHUQUISACA', 'BENI', 'RURRENABAQUE', 'PANDO']
        );
        abort_unless(in_array($department, $allowedDepartments, true), 404);

        $authUser = $request->user();
        $hasGlobalDepartmentAccess = (bool) ($authUser?->hasGlobalDepartmentAccess() ?? false);
        $userCity = strtoupper(trim((string) ($authUser?->ciudad ?? '')));
        $departmentScope = $this->normalizePendingAlertDepartment($department);
        abort_unless(
            $hasGlobalDepartmentAccess
                || ($userCity !== '' && $departmentScope === $this->normalizePendingAlertDepartment($userCity)),
            403
        );

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $result = $type === 'pickup'
            ? $this->buildPickupDepartmentAlertPage($department, $page, $perPage)
            : $this->buildPendingDepartmentAlertPage(
                $department,
                $userCity,
                $hasGlobalDepartmentAccess,
                $page,
                $perPage,
                $request->query('scope') === 'dashboard' ? self::DASHBOARD_MODULE_KEYS : null
            );

        return response()->json([
            'type' => $type,
            'department' => $department,
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $perPage,
            'last_page' => $result['last_page'],
            'items' => $result['items'],
        ]);
    }

    public function welcome(Request $request)
    {
        $authUser = Auth::user();

        $data = $this->userHasRole($authUser, 'empresa')
            ? []
            : $this->cachedDashboardAlerts($authUser);

        return view('home.welcome', $data);
    }

    private function dashboardCacheKey(Request $request): string
    {
        $filters = [
            'modules' => $this->resolveDashboardModulosSeleccionados($request),
            'range' => strtolower(trim((string) $request->query('range', 'all'))),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
            'group' => $this->resolveAgrupacion($request),
            'departamento' => $this->resolveDepartamentoFiltro($request),
            'departamento_origen' => $this->resolveDepartamentoOrigenFiltro($request),
            // Evita reutilizar rangos relativos al cambiar de dia.
            'date' => now()->toDateString(),
        ];

        return 'dashboard:v14:' . sha1(json_encode($filters, JSON_UNESCAPED_UNICODE));
    }

    private function cachedDashboardAlerts($authUser, ?array $moduleKeys = null): array
    {
        if (!$authUser) {
            return [];
        }

        $scope = [
            'id' => (int) $authUser->id,
            'ciudad' => strtoupper(trim((string) ($authUser->ciudad ?? ''))),
            'regionales' => method_exists($authUser, 'regionalesLista')
                ? $authUser->regionalesLista()
                : [],
            'roles' => method_exists($authUser, 'getRoleNames')
                ? $authUser->getRoleNames()->sort()->values()->all()
                : [],
            'modules' => $moduleKeys,
        ];

        $key = 'dashboard-alerts:v3:' . sha1(json_encode($scope, JSON_UNESCAPED_UNICODE));

        return Cache::remember(
            $key,
            now()->addSeconds(self::DASHBOARD_ALERT_CACHE_SECONDS),
            fn () => $this->buildDashboardAlerts($authUser, $moduleKeys)
        );
    }

    private function buildDashboardAlerts($authUser, ?array $moduleKeys = null): array
    {
        $hasGlobalDepartmentAccess = (bool) ($authUser?->hasGlobalDepartmentAccess() ?? false);
        $userCity = strtoupper(trim((string) optional($authUser)->ciudad));
        $roleNames = ($authUser && method_exists($authUser, 'getRoleNames'))
            ? $authUser->getRoleNames()->toArray()
            : [];
        $userRoles = collect($roleNames)
            ->map(fn ($role) => mb_strtolower(trim((string) $role)))
            ->all();
        $canPlayPickupAlertSound = count(array_intersect(
            ['encargado_ems', 'cartero_ems'],
            $userRoles
        )) > 0;
        $estadoSolicitudId = $this->resolveEstadoIdByName('SOLICITUD');

        $contratosPorRecoger = 0;
        $contratosPorRecogerPorDepartamento = collect();
        if ($estadoSolicitudId && ($hasGlobalDepartmentAccess || $userCity !== '')) {
            $pickupQuery = Recojo::query()
                ->where('estados_id', $estadoSolicitudId)
                ->when(!$hasGlobalDepartmentAccess, function ($query) use ($userCity) {
                    $query->whereRaw('trim(upper(origen)) = ?', [$userCity]);
                });

            $contratosPorRecoger = (int) (clone $pickupQuery)->count();

            if ($hasGlobalDepartmentAccess) {
                $departmentExpression = "coalesce(nullif(trim(upper(origen)), ''), 'SIN DEPARTAMENTO')";
                $contratosPorRecogerPorDepartamento = (clone $pickupQuery)
                    ->selectRaw("{$departmentExpression} as departamento, count(*) as total")
                    ->groupByRaw($departmentExpression)
                    ->orderByDesc('total')
                    ->orderBy('departamento')
                    ->get()
                    ->map(function ($row) {
                        $row->departamento = (string) ($row->departamento ?? 'SIN DEPARTAMENTO');
                        $row->total = (int) ($row->total ?? 0);

                        return $row;
                    })
                    ->values();
            }
        }

        $regionalAlertKey = 'dashboard-alert-part:v1:regional:' . sha1(json_encode([
            $hasGlobalDepartmentAccess,
            $userCity,
            $moduleKeys,
        ]));
        $regionalPendingAlert = Cache::remember(
            $regionalAlertKey,
            now()->addSeconds(self::DASHBOARD_HEAVY_ALERT_CACHE_SECONDS),
            fn () => $this->buildRegionalPendingAlert($userCity, $hasGlobalDepartmentAccess, $moduleKeys)
        );

        $carteroAlertKey = 'dashboard-alert-part:v4:cartero:' . (int) $authUser->id . ':' . sha1(json_encode($moduleKeys));
        $carteroAlerts = Cache::remember(
            $carteroAlertKey,
            now()->addSeconds(self::DASHBOARD_HEAVY_ALERT_CACHE_SECONDS),
            fn () => [
                'alert' => $this->buildCarteroPendingAlert($authUser, $userRoles, $moduleKeys),
                'summary' => $this->buildCarteroPendingSummary(
                    $authUser,
                    $userRoles,
                    $hasGlobalDepartmentAccess,
                    $userCity,
                    $moduleKeys
                ),
            ]
        );

        $pendingCn33Regional = $this->resolvePendingCn33RegionalScope($authUser);
        $pendingCn33Alert = Cache::remember(
            'dashboard-alert-part:v1:cn33:' . sha1((string) $pendingCn33Regional),
            now()->addSeconds(self::DASHBOARD_HEAVY_ALERT_CACHE_SECONDS),
            fn () => $this->cn33Service->getPendingRegistrationAlert(regional: $pendingCn33Regional)
        );

        return [
            'userCity' => $userCity,
            'contratosPorRecoger' => $contratosPorRecoger,
            'contratosPorRecogerPorDepartamento' => $contratosPorRecogerPorDepartamento,
            'pickupAlertIsNational' => $hasGlobalDepartmentAccess,
            'canPlayPickupAlertSound' => $canPlayPickupAlertSound,
            'deliveryExpressPickupAlert' => $this->buildDeliveryExpressPickupAlert(
                $estadoSolicitudId,
                $authUser,
                $hasGlobalDepartmentAccess,
                $userCity
            ),
            'regionalPendingAlert' => $regionalPendingAlert,
            'carteroPendingAlert' => $carteroAlerts['alert'],
            'carteroPendingSummary' => $carteroAlerts['summary'],
            'pendingCn33Alert' => $pendingCn33Alert,
        ];
    }

    public function entregas(Request $request)
    {
        return view('entregas', $this->buildEntregasData($request));
    }

    public function exportEntregasExcel(Request $request)
    {
        $data = $this->buildEntregasData($request);
        $filename = 'entregas-rendimiento-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new DashboardEntregasWorkbookExport($data), $filename);
    }

    public function exportEntregasPdf(Request $request)
    {
        $data = $this->buildEntregasData($request);
        $pdf = Pdf::loadView('entregas.pdf', $data)
            ->setPaper('A4', 'landscape')
            ->setOptions([
                'dpi' => 72,
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => false,
            ]);

        $pdf->render();
        $pdf->getDomPDF()->getCanvas()->page_text(
            748,
            562,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            null,
            8,
            [0.36, 0.42, 0.52]
        );

        $filename = 'entregas-ejecutivo-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    private function buildEntregasData(Request $request): array
    {
        $modulosSeleccionados = $this->resolveModulosSeleccionados($request);
        [$desde, $hasta, $rangoLabel, $rangoKey] = $this->resolveRangoFechas($request);
        $departamentoCartero = $this->resolveDepartamentoFiltroPorCampo($request, 'cartero_departamento');
        $diasLaborables = $this->countDeliveryWorkingDays(
            $modulosSeleccionados,
            $desde,
            $hasta
        );

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
            ->map(function ($row, $userId) use ($entregadores, $asignados, $ventanilla, $diasLaborables) {
                $entregadoRow = $entregadores->firstWhere('id', $userId);
                $asignadoRow = $asignados->get($userId);
                $ventanillaRow = $ventanilla->get($userId);

                $row->name = $entregadoRow->name ?? $asignadoRow->name ?? $ventanillaRow->name ?? $row->name;
                $row->ciudad = $entregadoRow->ciudad ?? $asignadoRow->ciudad ?? $ventanillaRow->ciudad ?? $row->ciudad;
                $row->total_entregados = (int) ($entregadoRow->total_entregados ?? 0);
                $row->promedio_diario = $diasLaborables > 0
                    ? $row->total_entregados / $diasLaborables
                    : 0;
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
                $row->cumplimiento_asignados = DeliveryFulfillment::percentage(
                    $row->total_asignados,
                    $row->total_cartero_entregados,
                    $row->total_ventanilla
                );

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
            ->reject(function ($row) {
                $nombre = preg_replace('/\s+/u', ' ', trim((string) ($row->name ?? '')));
                $nombre = mb_strtolower($nombre ?? '', 'UTF-8');

                return in_array($nombre, self::ENTREGAS_EXCLUDED_COURIER_NAMES, true);
            })
            ->sortByDesc(fn ($row) => ((int) $row->total_asignados * 1000000) + (int) $row->total_entregados + (int) $row->total_ventanilla)
            ->values();

        $totalEntregados = (int) $entregadores->sum('total_entregados');

        $resumenDepartamentos = $entregadores
            ->groupBy(function ($row) {
                $departamento = strtoupper(trim((string) ($row->ciudad ?? '')));

                return $departamento !== '' ? $departamento : 'SIN DEPARTAMENTO';
            })
            ->map(function ($carteros, string $departamento) use ($diasLaborables) {
                $totalAsignados = (int) $carteros->sum('total_asignados');
                $totalCarteroEntregados = (int) $carteros->sum('total_cartero_entregados');
                $totalVentanilla = (int) $carteros->sum('total_ventanilla');
                $totalEntregadosDepartamento = (int) $carteros->sum('total_entregados');
                $pendientesDepartamento = (int) $carteros->sum('pendientes_asignados');

                return [
                    'departamento' => $departamento,
                    'carteros' => $carteros->sortByDesc('total_entregados')->values(),
                    'cantidad_carteros' => $carteros->count(),
                    'total_asignados' => $totalAsignados,
                    'total_cartero_entregados' => $totalCarteroEntregados,
                    'total_ventanilla' => $totalVentanilla,
                    'total_entregados' => $totalEntregadosDepartamento,
                    'promedio_diario' => $diasLaborables > 0
                        ? $totalEntregadosDepartamento / $diasLaborables
                        : 0,
                    'pendientes_asignados' => $pendientesDepartamento,
                    'cumplimiento' => DeliveryFulfillment::percentageFromPending(
                        $totalAsignados,
                        $pendientesDepartamento,
                        $totalVentanilla
                    ),
                ];
            })
            ->sortByDesc('total_entregados')
            ->values();

        $totalAsignados = (int) $entregadores->sum('total_asignados');
        $totalVentanilla = (int) $entregadores->sum('total_ventanilla');
        $totalPendientes = (int) $entregadores->sum('pendientes_asignados');
        $cumplimientoGeneral = DeliveryFulfillment::percentageFromPending(
            $totalAsignados,
            $totalPendientes,
            $totalVentanilla
        );

        return [
            'entregadores' => $entregadores,
            'resumenDepartamentos' => $resumenDepartamentos,
            'cumplimientoGeneral' => $cumplimientoGeneral,
            'diasLaborables' => $diasLaborables,
            'promedioDiarioGeneral' => $diasLaborables > 0 ? $totalEntregados / $diasLaborables : 0,
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

    private function countDeliveryWorkingDays(
        array $modulosSeleccionados,
        ?Carbon $from,
        ?Carbon $to
    ): int {
        if (!$from || !$to) {
            $firstDeliveryAt = null;
            $lastDeliveryAt = null;

            foreach ($modulosSeleccionados as $moduloKey) {
                $config = self::MODULOS[$moduloKey];
                $query = DB::table($config['event_table'] . ' as delivered')
                    ->where('delivered.evento_id', self::EVENTO_ENTREGADO_ID);

                $this->excludeCanceledPackageForEvent($query, $config, 'delivered');

                $bounds = $query
                    ->selectRaw('MIN(delivered.created_at) as first_delivery_at, MAX(delivered.created_at) as last_delivery_at')
                    ->first();

                if (!empty($bounds->first_delivery_at)) {
                    $first = Carbon::parse($bounds->first_delivery_at);
                    $last = Carbon::parse($bounds->last_delivery_at);
                    if (!$firstDeliveryAt || $first->lt($firstDeliveryAt)) {
                        $firstDeliveryAt = $first;
                    }
                    if (!$lastDeliveryAt || $last->gt($lastDeliveryAt)) {
                        $lastDeliveryAt = $last;
                    }
                }
            }

            if (!$firstDeliveryAt || !$lastDeliveryAt) {
                return 0;
            }

            $from = $firstDeliveryAt;
            $to = $lastDeliveryAt;
        }

        $day = $from->copy()->startOfDay();
        $lastDay = $to->copy()->startOfDay();
        $workingDays = 0;

        while ($day->lte($lastDay)) {
            if ($day->dayOfWeek !== Carbon::SUNDAY) {
                $workingDays++;
            }

            $day->addDay();
        }

        return $workingDays;
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

    private function buildDashboardData(
        Request $request,
        bool $includeAlerts = true,
        bool $includeDepartmentDetails = true
    ): array
    {
        $modulosSeleccionados = $this->resolveDashboardModulosSeleccionados($request);
        [$desde, $hasta, $rangoLabel, $rangoKey] = $this->resolveRangoFechas($request);
        $agrupacion = $this->resolveAgrupacion($request);
        $departamento = $this->resolveDepartamentoFiltro($request);
        $departamentoOrigen = $this->resolveDepartamentoOrigenFiltro($request);
        $authUser = Auth::user();

        $estadoEntregadoId = $this->resolveEstadoIdByName('ENTREGADO');
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');
        $estadoRezagoId = $this->resolveEstadoIdByName('REZAGO');
        $resumenPorModulo = [];

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $query = DB::table($config['table']);
            $this->applyDateFilter($query, 'created_at', $desde, $hasta);
            $this->applyDepartamentoFilter($query, $config, $departamento);
            $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen);
            $this->excludeTestCompanyPackages($query, $config);

            $stateColumn = $config['estado_column'];
            $notCanceledSql = $estadoCanceladoId
                ? "{$stateColumn} IS NULL OR {$stateColumn} <> " . (int) $estadoCanceladoId
                : 'TRUE';
            $deliveredSql = $estadoEntregadoId
                ? "{$stateColumn} = " . (int) $estadoEntregadoId
                : 'FALSE';
            $aggregate = $query
                ->selectRaw("COUNT(DISTINCT CASE WHEN ({$notCanceledSql}) THEN codigo END) as total")
                ->selectRaw("COUNT(DISTINCT CASE WHEN ({$notCanceledSql}) AND ({$deliveredSql}) THEN codigo END) as entregados")
                ->selectRaw("COUNT(DISTINCT CASE WHEN " . ($estadoCanceladoId ? "{$stateColumn} = " . (int) $estadoCanceladoId : 'FALSE') . " THEN codigo END) as cancelados")
                ->selectRaw("COALESCE(SUM(CASE WHEN ({$notCanceledSql}) THEN COALESCE({$config['peso_column']}, 0) ELSE 0 END), 0) as peso_total")
                ->first();

            $total = (int) ($aggregate->total ?? 0);
            $entregados = (int) ($aggregate->entregados ?? 0);
            $situacionInventario = $this->countSituacionInventarioByIndicadorLogic(
                $moduloKey,
                $config,
                $estadoEntregadoId,
                $desde,
                $hasta,
                $departamento,
                $departamentoOrigen
            );
            $correctos = (int) ($situacionInventario['correcto'] ?? 0);
            $atrasados = (int) ($situacionInventario['retraso'] ?? 0);
            $rezago = (int) ($situacionInventario['rezago'] ?? 0);

            // Un pendiente operativo empieza cuando ya existe su recojo/recepcion.
            // Los registros sin fecha o evento inicial quedan en "sin datos" y no
            // deben inflar Pendientes, En plazo, Retraso ni Rezago.
            $pendientes = $correctos + $atrasados + $rezago;

            $resumenPorModulo[$moduloKey] = [
                'key' => $moduloKey,
                'label' => $config['label'],
                'total' => $total,
                'entregados' => $entregados,
                'pendientes' => $pendientes,
                'correctos' => $correctos,
                'atrasados' => $atrasados,
                'rezago' => $rezago,
                'peso_total' => round((float) ($aggregate->peso_total ?? 0), 3),
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
        ];

        $totales['porcentaje_entrega'] = $totales['paquetes'] > 0
            ? round(($totales['entregados'] * 100) / $totales['paquetes'], 1)
            : 0.0;

        [$trendLabels, $trendSeries, $rangoTendenciaLabel] = $this->buildTrendSeries(
            $modulosSeleccionados,
            $desde,
            $hasta,
            $rangoLabel,
            $rangoKey,
            $agrupacion,
            $departamento,
            $departamentoOrigen
        );

        $includeDepartmentRanking = $includeDepartmentDetails
            || $totales['paquetes'] <= self::DASHBOARD_INLINE_DEPARTMENT_MAX_ROWS;
        $includeInlineRankings = $includeDepartmentRanking;
        $rankingEntregadores = $includeInlineRankings
            ? $this->buildRankingEntregadores($modulosSeleccionados, $desde, $hasta, null, $departamento, '', $departamentoOrigen)
            : collect();
        $rankingDepartamentos = $includeDepartmentRanking
            ? $this->buildRankingDepartamentos($modulosSeleccionados, $desde, $hasta, $includeDepartmentDetails, $departamentoOrigen)
            : collect();
        $rankingRegistradores = $includeInlineRankings
            ? $this->buildRankingRegistradores($modulosSeleccionados, $desde, $hasta, $departamento, $departamentoOrigen)
            : collect();
        $insightsEjecutivos = $this->buildExecutiveInsights(
            $totales,
            $resumenPorModulo,
            $trendLabels,
            $trendSeries,
            $rankingEntregadores,
            $rankingRegistradores,
            $rankingDepartamentos
        );

        $alertData = $includeAlerts ? $this->buildDashboardAlerts($authUser, self::DASHBOARD_MODULE_KEYS) : [];

        return [
            'modulosDisponibles' => array_intersect_key(self::MODULOS, array_flip(self::DASHBOARD_MODULE_KEYS)),
            'modulosSeleccionados' => $modulosSeleccionados,
            'estadoEntregadoDisponible' => (bool) $estadoEntregadoId,
            'estadoRezagoDisponible' => (bool) $estadoRezagoId,
            'rangoDesde' => $desde ? $desde->toDateString() : null,
            'rangoHasta' => $hasta ? $hasta->toDateString() : null,
            'rangoLabel' => $rangoLabel,
            'rangoKey' => $rangoKey,
            'agrupacion' => $agrupacion,
            'departamento' => $departamento,
            'departamentoOrigen' => $departamentoOrigen,
            'departamentosDisponibles' => self::DESTINOS_BASE,
            'departamentosOrigenDisponibles' => array_keys($this->departamentoAliasMap()),
            'resumenPorModulo' => $resumenPorModulo,
            'totales' => $totales,
            'chartVersus' => [
                'labels' => ['Entregados', 'Pendientes'],
                'totales' => [(int) $totales['entregados'], (int) $totales['pendientes']],
            ],
            'chartVolumen' => [
                'labels' => array_column($resumenPorModulo, 'label'),
                'registrados' => array_column($resumenPorModulo, 'total'),
                'peso' => array_column($resumenPorModulo, 'peso_total'),
                'entregas' => array_column($resumenPorModulo, 'entregados'),
                'totales' => [
                    'registrados' => $totales['paquetes'],
                    'peso' => $totales['peso_total'],
                    'entregas' => $totales['entregados'],
                ],
            ],
            'trendLabels' => $trendLabels,
            'trendSeries' => $trendSeries,
            'rangoTendenciaLabel' => $rangoTendenciaLabel,
            'rankingEntregadores' => $rankingEntregadores,
            'rankingDepartamentos' => $rankingDepartamentos,
            'rankingRegistradores' => $rankingRegistradores,
            'insightsEjecutivos' => $insightsEjecutivos,
        ] + $alertData;
    }

    private function resolvePendingCn33RegionalScope($user): ?string
    {
        if (!$user) {
            return null;
        }

        if (method_exists($user, 'hasGlobalDepartmentAccess') && $user->hasGlobalDepartmentAccess()) {
            return null;
        }

        if (!method_exists($user, 'hasRole')) {
            return null;
        }

        if ($user->hasRole('encargado_ems') || $user->hasRole('cartero_ems')) {
            $regional = strtoupper(trim((string) ($user->ciudad ?? '')));

            return $regional !== '' ? $regional : null;
        }

        return null;
    }

    private function userHasRole($user, string $role): bool
    {
        return $user
            && method_exists($user, 'hasRole')
            && $user->hasRole($role);
    }

    private function buildRegionalPendingAlert(string $userCity, bool $hasGlobalDepartmentAccess = false, ?array $moduleKeys = null): array
    {
        $regional = strtoupper(trim($userCity));
        if (!$hasGlobalDepartmentAccess && $regional === '') {
            return [
                'count' => 0,
                'regional' => '',
                'hours' => 72,
                'scope' => 'regional',
                'departments' => collect(),
            ];
        }

        $estadoEntregadoId = $this->resolveEstadoIdByName('ENTREGADO');
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');
        $pendingCount = 0;
        $pendingByDepartment = [];

        foreach ($moduleKeys ?? array_keys(self::MODULOS) as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $query = DB::table($config['table'] . ' as t');
            if (!$hasGlobalDepartmentAccess) {
                $this->applyDepartamentoFilter($query, $config, $regional, 't');
            }
            $this->excludeCanceledState($query, 't.' . $config['estado_column'], $estadoCanceladoId);

            if ($estadoEntregadoId) {
                $query->where('t.' . $config['estado_column'], '!=', $estadoEntregadoId);
            }

            $startExpression = $moduloKey === 'contrato'
                ? 'coalesce(t.fecha_recojo, t.created_at)'
                : 't.created_at';
            $departmentExpression = $this->effectiveDepartamentoExpression($config, 't');

            // Agrupar por hora mantiene exactitud operativa suficiente para la
            // alerta y evita materializar millones de paquetes en PHP.
            $rows = $query
                ->selectRaw("date_trunc('hour', {$startExpression}) as start_hour")
                ->selectRaw(($departmentExpression !== '' ? $departmentExpression : "''") . ' as departamento')
                ->selectRaw('COUNT(*) as total')
                ->groupByRaw("date_trunc('hour', {$startExpression})")
                ->when($departmentExpression !== '', fn ($alertQuery) => $alertQuery->groupByRaw($departmentExpression))
                ->get();

            foreach ($rows as $row) {
                if ($this->hasExceededBusinessHours($row->start_hour ?? null, 72)) {
                    $total = (int) ($row->total ?? 0);
                    $pendingCount += $total;

                    if ($hasGlobalDepartmentAccess) {
                        $department = $this->normalizePendingAlertDepartment(
                            (string) ($row->departamento ?? '')
                        );
                        $pendingByDepartment[$department] = ($pendingByDepartment[$department] ?? 0) + $total;
                    }
                }
            }
        }

        $departments = collect($pendingByDepartment)
            ->map(fn ($total, $department) => (object) [
                'departamento' => (string) $department,
                'total' => (int) $total,
            ])
            ->sort(function ($left, $right) {
                return $right->total <=> $left->total
                    ?: strcmp($left->departamento, $right->departamento);
            })
            ->values();

        return [
            'count' => $pendingCount,
            'regional' => $hasGlobalDepartmentAccess ? '' : $regional,
            'hours' => 72,
            'scope' => $hasGlobalDepartmentAccess ? 'nacional' : 'regional',
            'departments' => $departments,
        ];
    }

    private function buildPickupDepartmentAlertPage(string $department, int $page, int $perPage): array
    {
        $estadoSolicitudId = $this->resolveEstadoIdByName('SOLICITUD');
        if (!$estadoSolicitudId) {
            return ['total' => 0, 'page' => 1, 'last_page' => 1, 'items' => []];
        }

        $query = Recojo::query()
            ->where('estados_id', $estadoSolicitudId)
            ->whereRaw("coalesce(nullif(trim(upper(origen)), ''), 'SIN DEPARTAMENTO') = ?", [$department]);

        $total = (int) (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $items = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get(['codigo', 'estados_id', 'origen', 'destino', 'nombre_d', 'created_at'])
            ->map(fn ($row) => [
                'modulo' => 'CONTRATOS',
                'codigo' => (string) ($row->codigo ?? ''),
                'estado' => 'SOLICITUD',
                'origen' => (string) ($row->origen ?? '-'),
                'destino' => (string) ($row->destino ?? '-'),
                'destinatario' => (string) ($row->nombre_d ?? '-'),
                'fecha' => optional($row->created_at)->format('d/m/Y H:i') ?? '-',
            ])
            ->values()
            ->all();

        return [
            'total' => $total,
            'page' => $page,
            'last_page' => $lastPage,
            'items' => $items,
        ];
    }

    private function buildPendingDepartmentAlertPage(
        string $department,
        string $userCity,
        bool $hasGlobalDepartmentAccess,
        int $page,
        int $perPage,
        ?array $moduleKeys = null
    ): array {
        $estadoEntregadoId = $this->resolveEstadoIdByName('ENTREGADO');
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');
        $departmentAliases = match ($department) {
            'SUCRE' => ['SUCRE', 'CHUQUISACA'],
            'TRINIDAD' => ['TRINIDAD', 'BENI', 'RURRENABAQUE'],
            'COBIJA' => ['COBIJA', 'PANDO'],
            default => [$department],
        };
        $departmentRows = collect();
        $eligibleHours = [];
        $minimumStart = now()->subHours(72)->startOfHour();

        foreach ($moduleKeys ?? array_keys(self::MODULOS) as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $stateColumn = 't.' . $config['estado_column'];
            $startExpression = $moduloKey === 'contrato'
                ? 'coalesce(t.fecha_recojo, t.created_at)'
                : 't.created_at';
            $departmentExpression = $this->effectiveDepartamentoExpression($config, 't');

            if ($departmentExpression === '') {
                continue;
            }

            $identityColumns = $this->packageIdentityColumns($moduloKey, $config);
            $query = DB::table($config['table'] . ' as t')
                ->leftJoin('estados as e', 'e.id', '=', $stateColumn)
                ->select([
                    DB::raw("'" . $config['label'] . "' as modulo"),
                    't.codigo as codigo',
                    DB::raw("coalesce(e.nombre_estado, 'SIN ESTADO') as estado"),
                    DB::raw($identityColumns['origen'] . ' as origen'),
                    DB::raw($identityColumns['destino'] . ' as destino'),
                    DB::raw($identityColumns['destinatario'] . ' as destinatario'),
                    DB::raw($startExpression . ' as iniciado_at'),
                ])
                ->whereRaw($startExpression . ' <= ?', [$minimumStart]);

            if (!$hasGlobalDepartmentAccess) {
                $this->applyDepartamentoFilter($query, $config, $userCity, 't');
            }

            if ($department === 'SIN DEPARTAMENTO') {
                $query->whereRaw("nullif(trim(upper({$departmentExpression})), '') is null");
            } else {
                $query->whereIn(DB::raw('trim(upper(' . $departmentExpression . '))'), $departmentAliases);
            }

            $this->excludeCanceledState($query, $stateColumn, $estadoCanceladoId);
            if ($estadoEntregadoId) {
                $query->where($stateColumn, '<>', $estadoEntregadoId);
            }

            $rows = $query
                ->orderByRaw($startExpression . ' asc')
                ->orderBy('t.codigo')
                ->get();

            foreach ($rows as $row) {
                if (empty($row->iniciado_at)) {
                    continue;
                }

                $startedAt = Carbon::parse($row->iniciado_at);
                $hourStart = $startedAt->copy()->startOfHour();
                $hourKey = $hourStart->format('Y-m-d H:i:s');
                if (!array_key_exists($hourKey, $eligibleHours)) {
                    $eligibleHours[$hourKey] = $this->hasExceededBusinessHours($hourStart, 72);
                }

                if (!$eligibleHours[$hourKey]) {
                    continue;
                }

                $departmentRows->push([
                    'sort' => $startedAt->timestamp,
                    'item' => [
                        'modulo' => (string) ($row->modulo ?? ''),
                        'codigo' => (string) ($row->codigo ?? ''),
                        'estado' => (string) ($row->estado ?? 'SIN ESTADO'),
                        'origen' => (string) ($row->origen ?? '-'),
                        'destino' => (string) ($row->destino ?? '-'),
                        'destinatario' => (string) ($row->destinatario ?? '-'),
                        'fecha' => $startedAt->format('d/m/Y H:i'),
                    ],
                ]);
            }
        }

        $departmentRows = $departmentRows->sortBy('sort')->values();
        $total = $departmentRows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $items = $departmentRows
            ->forPage($page, $perPage)
            ->pluck('item')
            ->values()
            ->all();

        return [
            'total' => $total,
            'page' => $page,
            'last_page' => $lastPage,
            'items' => $items,
        ];
    }

    /**
     * Solicitudes Delivery Express que todavia deben recogerse en el origen.
     * Los usuarios regionales solo ven los departamentos que tienen asignados;
     * los perfiles con alcance global reciben el resumen nacional.
     */
    private function buildDeliveryExpressPickupAlert(
        ?int $estadoSolicitudId,
        $authUser,
        bool $hasGlobalDepartmentAccess,
        string $userCity
    ): array {
        // El equipo de Delivery Express gestiona recojos de todo el pais, aunque
        // el usuario tenga una regional asignada y no sea un perfil global.
        $hasNationalPickupAccess = $hasGlobalDepartmentAccess
            || $this->userHasRole($authUser, 'delivery_express');

        $emptyAlert = [
            'count' => 0,
            'is_national' => $hasNationalPickupAccess,
            'scope_label' => $userCity !== '' ? $userCity : 'TU DEPARTAMENTO',
            'departments' => collect(),
            'requests' => collect(),
        ];

        if (!$estadoSolicitudId || !$authUser) {
            return $emptyAlert;
        }

        $assignedDepartments = method_exists($authUser, 'regionalesLista')
            ? collect($authUser->regionalesLista())
            : collect([$userCity]);

        $assignedDepartments = $assignedDepartments
            ->map(fn ($department) => strtoupper(trim((string) $department)))
            ->filter()
            ->unique()
            ->values();

        if (!$hasNationalPickupAccess && $assignedDepartments->isEmpty()) {
            return $emptyAlert;
        }

        $pickupQuery = SolicitudCliente::query()
            ->where('estado_id', $estadoSolicitudId)
            ->when(!$hasNationalPickupAccess, function ($query) use ($assignedDepartments) {
                $query->where(function ($departmentQuery) use ($assignedDepartments) {
                    foreach ($assignedDepartments as $department) {
                        $departmentQuery->orWhereRaw('trim(upper(origen)) = ?', [$department]);
                    }
                });
            });

        $count = (int) (clone $pickupQuery)->count();
        if ($count === 0) {
            return array_merge($emptyAlert, [
                'scope_label' => $hasNationalPickupAccess
                    ? 'NIVEL NACIONAL'
                    : $assignedDepartments->implode(', '),
            ]);
        }

        $departmentExpression = "coalesce(nullif(trim(upper(origen)), ''), 'SIN DEPARTAMENTO')";
        $departments = (clone $pickupQuery)
            ->selectRaw("{$departmentExpression} as departamento, count(*) as total")
            ->groupByRaw($departmentExpression)
            ->orderByDesc('total')
            ->orderBy('departamento')
            ->get()
            ->map(function ($row) {
                $row->departamento = (string) ($row->departamento ?? 'SIN DEPARTAMENTO');
                $row->total = (int) ($row->total ?? 0);

                return $row;
            })
            ->values();

        $requests = (clone $pickupQuery)
            ->with(['servicioExtra:id,nombre,descripcion'])
            ->latest('id')
            ->limit(50)
            ->get([
                'id',
                'codigo_solicitud',
                'barcode',
                'origen',
                'direccion_recojo',
                'nombre_remitente',
                'telefono_remitente',
                'contenido',
                'cantidad',
                'servicio_extra_id',
                'created_at',
            ]);

        return [
            'count' => $count,
            'is_national' => $hasNationalPickupAccess,
            'scope_label' => $hasNationalPickupAccess
                ? 'NIVEL NACIONAL'
                : $assignedDepartments->implode(', '),
            'departments' => $departments,
            'requests' => $requests,
        ];
    }

    private function normalizePendingAlertDepartment(string $department): string
    {
        $department = strtoupper(trim($department));

        return match ($department) {
            'CHUQUISACA' => 'SUCRE',
            'BENI', 'RURRENABAQUE' => 'TRINIDAD',
            'PANDO' => 'COBIJA',
            '' => 'SIN DEPARTAMENTO',
            default => $department,
        };
    }

    private function buildCarteroPendingAlert($authUser, array $userRoles, ?array $moduleKeys = null): array
    {
        $isCartero = collect($userRoles)->contains(fn ($role) => str_contains((string) $role, 'cartero'));
        if (!$authUser || !$isCartero) {
            return [
                'count' => 0,
                'name' => '',
            ];
        }

        $estadoCarteroId = $this->resolveEstadoIdByName('CARTERO');
        if (!$estadoCarteroId) {
            return [
                'count' => 0,
                'name' => (string) ($authUser->name ?? ''),
            ];
        }

        $query = Cartero::query()
            ->where('id_user', $authUser->id)
            ->where('id_estados', $estadoCarteroId);

        $this->constrainActiveCarteroAssignments($query, $estadoCarteroId, $moduleKeys);

        $count = (int) $query->count();

        return [
            'count' => $count,
            'name' => trim((string) ($authUser->name ?? '')),
        ];
    }

    private function buildCarteroPendingSummary($authUser, array $userRoles, bool $hasGlobalDepartmentAccess, string $userCity, ?array $moduleKeys = null): array
    {
        $isCartero = collect($userRoles)->contains(fn ($role) => str_contains((string) $role, 'cartero'));
        $isEncargadoEms = collect($userRoles)->contains(fn ($role) => (string) $role === 'encargado_ems');

        if (!$authUser || $isCartero || (!$hasGlobalDepartmentAccess && !$isEncargadoEms)) {
            return [
                'enabled' => false,
                'scope' => '',
                'rows' => collect(),
                'departments' => collect(),
            ];
        }

        $estadoCarteroId = $this->resolveEstadoIdByName('CARTERO');
        if (!$estadoCarteroId) {
            return [
                'enabled' => false,
                'scope' => '',
                'rows' => collect(),
                'departments' => collect(),
            ];
        }

        $query = Cartero::query()
            ->join('users', 'users.id', '=', 'cartero.id_user')
            ->where('cartero.id_estados', $estadoCarteroId)
            ->whereNotNull('cartero.id_user')
            ->selectRaw('users.id, users.name, users.ciudad, count(*) as pendientes')
            ->groupBy('users.id', 'users.name', 'users.ciudad')
            ->havingRaw('count(*) > 0')
            ->orderByDesc('pendientes')
            ->orderBy('users.name');

        $this->constrainActiveCarteroAssignments($query, $estadoCarteroId, $moduleKeys);

        if (!$hasGlobalDepartmentAccess) {
            if ($userCity === '') {
                return [
                    'enabled' => false,
                    'scope' => '',
                    'rows' => collect(),
                    'departments' => collect(),
                ];
            }

            $query->whereRaw('trim(upper(users.ciudad)) = ?', [$userCity]);
        }

        $rows = $query->get()->map(function ($row) {
            $row->pendientes = (int) ($row->pendientes ?? 0);
            $row->name = (string) ($row->name ?? 'Sin nombre');
            $row->ciudad = strtoupper(trim((string) ($row->ciudad ?? '')));

            return $row;
        })->values();

        $details = $this->buildCarteroPendingDetails($rows->pluck('id')->all(), $estadoCarteroId, $moduleKeys);
        foreach ($rows as $row) {
            $row->detalle = $details->get($row->id, collect());
        }

        return [
            'enabled' => $rows->isNotEmpty(),
            'scope' => $hasGlobalDepartmentAccess ? 'nacional' : 'regional',
            'rows' => $rows,
            'departments' => $hasGlobalDepartmentAccess
                ? $this->completeDepartmentPendingSummary($rows)
                : collect(),
        ];
    }

    private function buildCarteroPendingDetails(array $userIds, int $estadoCarteroId, ?array $moduleKeys = null)
    {
        $details = collect();
        if ($userIds === []) {
            return $details;
        }

        $types = [
            ['ems', 'id_paquetes_ems', 'paquetes_ems', 'estado_id', 'codigo', 'ciudad', 'eventos_ems', [295]],
            ['certi', 'id_paquetes_certi', 'paquetes_certi', 'fk_estado', 'codigo', 'cuidad', 'eventos_certi', [168]],
            ['ordi', 'id_paquetes_ordi', 'paquetes_ordi', 'fk_estado', 'codigo', 'ciudad', 'eventos_ordi', [295]],
            ['contrato', 'id_paquetes_contrato', 'paquetes_contrato', 'estados_id', 'codigo', 'destino', 'eventos_contrato', [295]],
            ['solicitud', 'id_solicitud_cliente', 'solicitud_clientes', 'estado_id', 'codigo_solicitud', 'ciudad', 'eventos_tiktoker', [295]],
        ];
        $now = now();
        foreach ($types as [$type, $foreignKey, $table, $state, $code, $destination, $events, $startEvents]) {
            if ($moduleKeys !== null && !in_array($type, $moduleKeys, true)) {
                continue;
            }
            $packageCodeSql = $type === 'solicitud'
                ? "COALESCE(NULLIF(TRIM(p.codigo_solicitud), ''), NULLIF(TRIM(p.barcode), ''))"
                : 'p.'.$code;
            $packageCode = DB::raw($packageCodeSql);
            // La ultima asignacion/cambio evita usar la fecha de una asignacion anterior.
            $assignmentEvents = DB::table($events.' as ep')
                ->join('eventos as e', 'e.id', '=', 'ep.evento_id')
                ->where(function ($query) {
                    $query->whereIn('e.nombre_evento', [
                        \App\Support\CarteroEvent::ASIGNADO,
                        \App\Support\CarteroEvent::CAMBIADO,
                        \App\Support\EncargadoEvent::CARTERO_CAMBIADO,
                    ])->orWhere('e.nombre_evento', 'like', '%Asignado a CARTERO%');
                })
                ->select('ep.codigo', DB::raw('MAX(ep.created_at) as assigned_at'))
                ->groupBy('ep.codigo');
            $start = DB::table($events)->whereIn('evento_id', $startEvents)
                ->select('codigo', DB::raw('MIN(created_at) as started_at'))->groupBy('codigo');
            $packages = DB::table('cartero as c')
                ->join($table.' as p', 'p.id', '=', 'c.'.$foreignKey)
                ->leftJoinSub($assignmentEvents, 'assignment', fn ($join) => $join->on('assignment.codigo', '=', $packageCode))
                ->leftJoinSub($start, 'start', fn ($join) => $join->on('start.codigo', '=', $packageCode))
                ->whereIn('c.id_user', $userIds)
                ->where('c.id_estados', $estadoCarteroId)->where('p.'.$state, $estadoCarteroId)
                ->select('c.id_user', 'p.created_at as generated_at',
                    'p.'.$destination.' as destino', 'assignment.assigned_at', 'start.started_at', 'c.created_at as first_assignment_at');
            $packages->selectRaw($packageCodeSql.' as codigo');
            if ($type === 'contrato') {
                $packages->addSelect('p.provincia');
            }
            foreach ($packages->get() as $package) {
                $package->tipo = self::MODULOS[$type]['label'] ?? 'SOLICITUD';
                $package->assignment_estimated = empty($package->assigned_at);
                $package->assigned_at = $package->assigned_at ?? $package->first_assignment_at;
                $thresholds = in_array($type, ['certi', 'ordi'], true)
                    ? ['green' => self::CERTI_ORDI_GREEN_DAYS, 'yellow' => self::CERTI_ORDI_YELLOW_DAYS]
                    : $this->resolveEmsThresholdDays((string) $package->destino,
                        $type === 'contrato' ? trim((string) ($package->provincia ?? '')) !== '' : $this->isEmsProvincia((string) $package->destino));
                $startAt = $this->safeCarbonValue($package->started_at);
                $package->situacion = $this->resolveSituacionBucket($startAt, $now, $thresholds['green'], $thresholds['yellow']);
                $package->dias_atraso = $startAt ? max(0, $startAt->diffInSeconds($now, false) / 86400 - $thresholds['green']) : null;
                $package->dias_rezago = $startAt ? max(0, $startAt->diffInSeconds($now, false) / 86400 - $thresholds['yellow']) : null;
                $details->push($package);
            }
        }

        return $details->sortBy('assigned_at')->groupBy('id_user');
    }

    /**
     * Excluye asignaciones huerfanas o cuyo paquete ya dejo el estado CARTERO.
     * La tabla cartero puede conservar historial, pero no debe inflar pendientes.
     */
    private function constrainActiveCarteroAssignments($query, int $estadoCarteroId, ?array $moduleKeys = null): void
    {
        $packageTypes = [
            'ems' => ['assignment' => 'id_paquetes_ems', 'table' => 'paquetes_ems', 'state' => 'estado_id'],
            'certi' => ['assignment' => 'id_paquetes_certi', 'table' => 'paquetes_certi', 'state' => 'fk_estado'],
            'ordi' => ['assignment' => 'id_paquetes_ordi', 'table' => 'paquetes_ordi', 'state' => 'fk_estado'],
            'contrato' => ['assignment' => 'id_paquetes_contrato', 'table' => 'paquetes_contrato', 'state' => 'estados_id'],
            'solicitud' => ['assignment' => 'id_solicitud_cliente', 'table' => 'solicitud_clientes', 'state' => 'estado_id'],
        ];

        if ($moduleKeys !== null) {
            $packageTypes = array_intersect_key($packageTypes, array_flip($moduleKeys));
        }

        $query->where(function ($assignments) use ($packageTypes, $estadoCarteroId) {
            foreach ($packageTypes as $type) {
                $assignments->orWhere(function ($assignment) use ($type, $estadoCarteroId) {
                    $assignment
                        ->whereNotNull('cartero.'.$type['assignment'])
                        ->whereExists(function ($package) use ($type, $estadoCarteroId) {
                            $package
                                ->selectRaw('1')
                                ->from($type['table'])
                                ->whereColumn($type['table'].'.id', 'cartero.'.$type['assignment'])
                                ->where($type['table'].'.'.$type['state'], $estadoCarteroId);
                        });
                });
            }
        });
    }

    private function completeDepartmentPendingSummary($rows)
    {
        $grouped = collect($rows)
            ->groupBy(fn ($row) => $this->normalizePendingAlertDepartment((string) ($row->ciudad ?? '')))
            ->map(function ($departmentRows, $department) {
                return (object) [
                    'department' => (string) $department,
                    'total_carteros' => $departmentRows->count(),
                    'total_pendientes' => (int) $departmentRows->sum(fn ($row) => (int) ($row->pendientes ?? 0)),
                    'rows' => $departmentRows->sortByDesc(fn ($row) => (int) ($row->pendientes ?? 0))->values(),
                ];
            });

        return collect(self::DESTINOS_BASE)
            ->map(function ($department) use ($grouped) {
                return $grouped->get($department, (object) [
                    'department' => $department,
                    'total_carteros' => 0,
                    'total_pendientes' => 0,
                    'rows' => collect(),
                ]);
            })
            ->values();
    }

    private function hasExceededBusinessHours($startAt, int $hours): bool
    {
        if (empty($startAt) || $hours <= 0) {
            return false;
        }

        $start = $startAt instanceof Carbon ? $startAt->copy() : Carbon::parse($startAt);

        return $this->businessCalendar->addBusinessHours($start, $hours)->lte(now());
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

    private function resolveDashboardModulosSeleccionados(Request $request): array
    {
        $selected = array_values(array_intersect(
            self::DASHBOARD_MODULE_KEYS,
            $this->resolveModulosSeleccionados($request)
        ));

        return $selected ?: self::DASHBOARD_MODULE_KEYS;
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

    private function resolveDepartamentoOrigenFiltro(Request $request): string
    {
        $value = strtoupper(trim((string) $request->query('departamento_origen', '')));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return array_key_exists($value, $this->departamentoAliasMap()) ? $value : '';
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
        $this->excludeTestCompanyEvents($query, $config, $eventAlias);
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');
        if (!$estadoCanceladoId) {
            return;
        }

        $alias = 'pkg_no_cancelado_' . $eventAlias;
        $alias = str_replace(['.', '-'], '_', $alias);

        $query->join($config['table'] . ' as ' . $alias, $alias . '.codigo', '=', $eventAlias . '.codigo');
        $this->excludeCanceledState($query, $alias . '.' . $config['estado_column'], $estadoCanceladoId);
    }

    private function excludedCompanyQuery(string $packageAlias): Builder
    {
        return DB::table('empresa as excluded_company')
            ->selectRaw('1')
            ->whereRaw('excluded_company.id = COALESCE('.$packageAlias.'.empresa_id, '
                .'(SELECT company_user.empresa_id FROM users as company_user WHERE company_user.id = '.$packageAlias.'.user_id))')
            ->where(function (Builder $company): void {
                $company->whereRaw("LOWER(TRIM(COALESCE(excluded_company.nombre, ''))) LIKE ?", ['%prueba%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(excluded_company.nombre, ''))) = ?", ['EMPRESA']);
            });
    }

    private function excludeTestCompanyPackages(Builder $query, array $config, string $tableAlias = ''): void
    {
        if ($config['table'] === 'paquetes_contrato') {
            $query->whereNotExists($this->excludedCompanyQuery($tableAlias !== '' ? $tableAlias : $config['table']));
        }
    }

    private function excludeTestCompanyEvents(Builder $query, array $config, string $eventAlias): void
    {
        if ($config['table'] === 'paquetes_contrato') {
            $query->whereNotExists(
                DB::table('paquetes_contrato as excluded_package')
                    ->selectRaw('1')
                    ->whereColumn('excluded_package.codigo', $eventAlias.'.codigo')
                    ->whereExists($this->excludedCompanyQuery('excluded_package'))
            );
        }
    }

    private function resolveEstadoIdByName(string $estadoNombre): ?int
    {
        $cacheKey = strtoupper(trim($estadoNombre));
        if (array_key_exists($cacheKey, $this->estadoIdCache)) {
            return $this->estadoIdCache[$cacheKey];
        }

        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($estadoNombre))])
            ->value('id');

        $resolvedId = $id ? (int) $id : null;
        $this->estadoIdCache[$cacheKey] = $resolvedId;

        return $resolvedId;
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
        string $departamento = '',
        string $departamentoOrigen = ''
    ): array {
        $useIndexedEventLookup = DB::connection()->getDriverName() === 'pgsql'
            && (($from && $to && $from->diffInDays($to) <= 366)
                || $departamento !== ''
                || $departamentoOrigen !== '');

        if ($useIndexedEventLookup) {
            // Con filtros selectivos, busca el evento inicial por cada paquete
            // usando el indice codigo/evento/fecha, sin agrupar todo el historico.
            $startSub = DB::table($config['event_table'] . ' as start_event')
                ->selectRaw('MIN(start_event.created_at) as start_at')
                ->whereColumn('start_event.codigo', 't.codigo')
                ->whereIn('start_event.evento_id', $config['operational_start_events']);

            $query = DB::table($config['table'] . ' as t')
                ->leftJoinLateral($startSub, 'operational_start');
        } else {
            $startSub = DB::table($config['event_table'])
                ->select('codigo', DB::raw('MIN(created_at) as start_at'))
                ->whereIn('evento_id', $config['operational_start_events'])
                ->groupBy('codigo');

            $query = DB::table($config['table'] . ' as t')
                ->leftJoinSub($startSub, 'operational_start', function ($join) {
                    $join->on('operational_start.codigo', '=', 't.codigo');
                });
        }

        $this->applyNoEntregadoScope($query, 't.' . $config['estado_column'], $estadoEntregadoId);
        $this->applyDateFilter($query, 't.created_at', $from, $to);
        $this->applyDepartamentoFilter($query, $config, $departamento, 't');
        $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 't');
        $this->excludeTestCompanyPackages($query, $config, 't');

        $startAt = 'operational_start.start_at';
        $elapsedDays = "EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - {$startAt})) / 86400.0";

        if ($moduloKey === 'ems') {
            $greenDays = $this->emsGreenDaysSql('t.ciudad');
            $yellowDays = "({$greenDays} + 1)";
        } elseif ($moduloKey === 'contrato') {
            $greenDays = $this->emsGreenDaysSql('t.destino', 't.provincia');
            $yellowDays = "({$greenDays} + 1)";
        } else {
            $greenDays = (string) self::CERTI_ORDI_GREEN_DAYS;
            $yellowDays = (string) self::CERTI_ORDI_YELLOW_DAYS;
        }

        $row = $query
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$startAt} IS NOT NULL AND {$elapsedDays} <= {$greenDays} THEN t.codigo END) as correcto")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$startAt} IS NOT NULL AND {$elapsedDays} > {$greenDays} AND {$elapsedDays} <= {$yellowDays} THEN t.codigo END) as retraso")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$startAt} IS NOT NULL AND {$elapsedDays} > {$yellowDays} THEN t.codigo END) as rezago")
            ->selectRaw("COUNT(DISTINCT CASE WHEN {$startAt} IS NULL THEN t.codigo END) as sin_datos")
            ->first();

        return [
            'correcto' => (int) ($row->correcto ?? 0),
            'retraso' => (int) ($row->retraso ?? 0),
            'rezago' => (int) ($row->rezago ?? 0),
            'sin_datos' => (int) ($row->sin_datos ?? 0),
        ];
    }

    private function emsGreenDaysSql(string $destinationColumn, ?string $provinceColumn = null): string
    {
        $destination = "upper(trim(coalesce({$destinationColumn}, '')))";
        $longDistance = "({$destination} LIKE '%SANTA CRUZ%' OR {$destination} LIKE '%TRINIDAD%' OR {$destination} LIKE '%TARIJA%')";
        $baseDestinations = "{$destination} IN ('LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA')";

        if ($provinceColumn !== null) {
            $isProvince = "trim(coalesce({$provinceColumn}, '')) <> ''";
        } else {
            $isProvince = "({$destination} <> '' AND NOT {$baseDestinations})";
        }

        return "(CASE WHEN {$longDistance} THEN 2 ELSE 1 END + CASE WHEN {$isProvince} THEN 1 ELSE 0 END)";
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
                $this->excludeTestCompanyPackages($registrosQuery, $config);
                $this->excludeCanceledState($registrosQuery, $config['estado_column'], $this->resolveEstadoIdByName('CANCELADO'));
                $countRegistros += (int) $registrosQuery->distinct()->count('codigo');

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
        string $departamento = '',
        string $departamentoOrigen = ''
    ): array {
        [$chartFrom, $chartTo, $chartLabel] = $this->resolveChartRange($from, $to, $rangoLabel, $rangoKey, $agrupacion);
        [$labels, $bucketExpression] = $this->buildBuckets($chartFrom, $chartTo, $agrupacion);

        $registrosMap = array_fill_keys($labels, 0);
        $entregadosMap = array_fill_keys($labels, 0);

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];

            $rowsRegistros = DB::table($config['table'])
                ->selectRaw($bucketExpression . ' as bucket, COUNT(DISTINCT codigo) as total')
                ->whereBetween('created_at', [$chartFrom, $chartTo]);
            $this->applyDepartamentoFilter($rowsRegistros, $config, $departamento);
            $this->applyOrigenDepartamentoFilter($rowsRegistros, $config, $departamentoOrigen);
            $this->excludeTestCompanyPackages($rowsRegistros, $config);
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
            $this->applyEventOrigenDepartamentoFilter($rowsEntregados, $config, $departamentoOrigen);
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

    private function buildRankingEntregadores(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, ?int $limit = 10, string $departamento = '', string $departamentoCartero = '', string $departamentoOrigen = '')
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
            $this->applyEventOrigenDepartamentoFilter($query, $config, $departamentoOrigen);
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

    private function buildRankingDepartamentos(
        array $modulosSeleccionados,
        ?Carbon $from,
        ?Carbon $to,
        bool $includeDetails = true,
        string $departamentoOrigen = ''
    )
    {
        $estadoEntregadoId = $this->resolveEstadoIdByName('ENTREGADO');
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');
        $estadoTransitoId = $this->resolveEstadoIdByName('TRANSITO');

        $rows = collect($this->departamentoAliasMap())
            ->map(function (array $aliases, string $departamento) use ($modulosSeleccionados, $from, $to, $estadoEntregadoId, $estadoCanceladoId, $estadoTransitoId, $includeDetails, $departamentoOrigen) {
                $total = 0;
                $entregados = 0;
                $cancelados = 0;

                foreach ($modulosSeleccionados as $moduloKey) {
                    $config = self::MODULOS[$moduloKey];

                    $query = DB::table($config['table']);
                    $this->applyDateFilter($query, 'created_at', $from, $to);
                    $this->applyDepartamentoAliasesFilter($query, $config, $aliases);
                    $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen);
                    $this->excludeTestCompanyPackages($query, $config);

                    $querySinCancelados = clone $query;
                    $this->excludeCanceledState($querySinCancelados, $config['estado_column'], $estadoCanceladoId);

                    $total += (int) (clone $querySinCancelados)->distinct()->count('codigo');
                    $entregados += $estadoEntregadoId
                        ? (int) (clone $querySinCancelados)->where($config['estado_column'], $estadoEntregadoId)->distinct()->count('codigo')
                        : 0;
                    $cancelados += $estadoCanceladoId
                        ? (int) (clone $query)->where($config['estado_column'], $estadoCanceladoId)->distinct()->count('codigo')
                        : 0;
                }

                $detalleTransito = $includeDetails
                    ? $this->buildDepartamentoTransitoDetails($modulosSeleccionados, $from, $to, $aliases, $estadoTransitoId, $departamento, $departamentoOrigen)
                    : $this->buildDepartamentoTransitoSummary($modulosSeleccionados, $from, $to, $aliases, $estadoTransitoId, $departamentoOrigen);
                $transito = (int) array_sum($detalleTransito['totales']);
                $cumplimiento = $total > 0 ? round(($entregados * 100) / $total, 1) : 0.0;
                $topEntregador = $this->buildTopEntregadorDepartamento($modulosSeleccionados, $from, $to, $aliases, $departamentoOrigen);
                $detalleEntregados = $includeDetails
                    ? $this->buildDepartamentoDeliveredDetails($modulosSeleccionados, $from, $to, $aliases, $departamentoOrigen)
                    : $this->buildDepartamentoDeliveredSummary($modulosSeleccionados, $from, $to, $aliases, $departamentoOrigen);
                $detallePendientes = $includeDetails
                    ? $this->buildDepartamentoPendingDetails($modulosSeleccionados, $from, $to, $aliases, $estadoEntregadoId, $estadoCanceladoId, $estadoTransitoId, $departamentoOrigen)
                    : $this->buildDepartamentoPendingSummary($modulosSeleccionados, $from, $to, $aliases, $estadoEntregadoId, $estadoCanceladoId, $estadoTransitoId, $departamentoOrigen);
                $pendientes = (int) array_sum($detallePendientes['totales']);

                return (object) [
                    'departamento' => $departamento,
                    'total' => $total,
                    'entregados' => $entregados,
                    'transito' => $transito,
                    'pendientes' => $pendientes,
                    'cumplimiento' => $cumplimiento,
                    'top_entregador' => $topEntregador?->name ?? 'SIN DATOS',
                    'top_entregador_total' => (int) ($topEntregador?->total_entregados ?? 0),
                    'entregados_por_modulo' => $detalleEntregados['totales'],
                    'entregados_detalle' => $detalleEntregados['rows'],
                    'transito_por_modulo' => $detalleTransito['totales'],
                    'transito_detalle' => $detalleTransito['rows'],
                    'transito_grupos' => $detalleTransito['grupos'],
                    'pendientes_por_modulo' => $detallePendientes['totales'],
                    'pendientes_detalle' => $detallePendientes['rows'],
                    'pendientes_grupos' => $detallePendientes['grupos'],
                    'details_loaded' => $includeDetails,
                ];
            });

        return $rows
            ->sortByDesc(fn ($row) => (float) $row->cumplimiento)
            ->values()
            ->map(function ($row, int $index) {
                $row->puesto = $index + 1;
                return $row;
            });
    }

    private function buildTopEntregadorDepartamento(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases, string $departamentoOrigen = '')
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
            $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 'pkg_departamento');
            $this->excludeTestCompanyPackages($query, $config, 'pkg_departamento');
            $this->excludeCanceledState($query, 'pkg_departamento.' . $config['estado_column'], $this->resolveEstadoIdByName('CANCELADO'));
            $queries[] = $query;
        }

        return $this->resolveRankingUsuarios($queries, 'total_entregados', 1)->first();
    }

    private function buildDepartamentoTransitoSummary(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases, ?int $estadoTransitoId, string $departamentoOrigen = ''): array
    {
        $totales = array_fill_keys(['EMS', 'CONTRATOS', 'CERTIFICADOS', 'ORDINARIOS'], 0);
        if (!$estadoTransitoId) {
            return ['totales' => $totales, 'rows' => [], 'grupos' => []];
        }

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $query = DB::table($config['table'] . ' as t')
                ->where('t.' . $config['estado_column'], $estadoTransitoId);

            $this->applyDateFilter($query, 't.created_at', $from, $to);
            $this->applyOrigenAliasesFilter($query, $config, $aliases, 't');
            $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 't');
            $this->excludeTestCompanyPackages($query, $config, 't');
            $totales[$config['label']] = (int) $query->distinct()->count('t.codigo');
        }

        return ['totales' => $totales, 'rows' => [], 'grupos' => []];
    }

    private function buildDepartamentoDeliveredSummary(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases, string $departamentoOrigen = ''): array
    {
        $totales = array_fill_keys(['EMS', 'CONTRATOS', 'CERTIFICADOS', 'ORDINARIOS'], 0);
        $estadoCanceladoId = $this->resolveEstadoIdByName('CANCELADO');

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $eventTable = $config['event_table'];
            $query = DB::table($eventTable . ' as delivered')
                ->where('delivered.evento_id', self::EVENTO_ENTREGADO_ID)
                ->join($config['table'] . ' as package', 'package.codigo', '=', 'delivered.codigo')
                ->selectRaw('COUNT(DISTINCT delivered.codigo) as total');

            $this->applyDateFilter($query, 'delivered.created_at', $from, $to);
            $this->applyDepartamentoAliasesFilter($query, $config, $aliases, 'package');
            $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 'package');
            $this->excludeTestCompanyPackages($query, $config, 'package');
            $this->excludeCanceledState($query, 'package.' . $config['estado_column'], $estadoCanceladoId);
            $totales[$config['label']] = (int) ($query->value('total') ?? 0);
        }

        return ['totales' => $totales, 'rows' => []];
    }

    private function buildDepartamentoPendingSummary(
        array $modulosSeleccionados,
        ?Carbon $from,
        ?Carbon $to,
        array $aliases,
        ?int $estadoEntregadoId,
        ?int $estadoCanceladoId,
        ?int $estadoTransitoId,
        string $departamentoOrigen = ''
    ): array {
        $totales = array_fill_keys(['EMS', 'CONTRATOS', 'CERTIFICADOS', 'ORDINARIOS'], 0);
        $estadoSolicitudId = $this->resolveEstadoIdByName('SOLICITUD');

        foreach ($modulosSeleccionados as $moduloKey) {
            $config = self::MODULOS[$moduloKey];
            $stateColumn = 't.' . $config['estado_column'];
            $query = DB::table($config['table'] . ' as t')
                ->whereExists(function (Builder $eventQuery) use ($config): void {
                    $eventQuery->selectRaw('1')
                        ->from($config['event_table'] . ' as operational_start')
                        ->whereColumn('operational_start.codigo', 't.codigo')
                        ->whereIn('operational_start.evento_id', $config['operational_start_events']);
                });

            $this->applyDateFilter($query, 't.created_at', $from, $to);
            $this->applyPendingDepartamentoAliasesFilter($query, $config, $aliases, 't');
            $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 't');
            $this->excludeTestCompanyPackages($query, $config, 't');

            foreach ([$estadoEntregadoId, $estadoCanceladoId, $estadoTransitoId, $estadoSolicitudId] as $excludedState) {
                if ($excludedState) {
                    $query->where(function (Builder $sub) use ($stateColumn, $excludedState): void {
                        $sub->whereNull($stateColumn)->orWhere($stateColumn, '<>', $excludedState);
                    });
                }
            }

            $totales[$config['label']] = (int) $query->distinct()->count('t.codigo');
        }

        return ['totales' => $totales, 'rows' => [], 'grupos' => []];
    }

    private function buildDepartamentoDeliveredDetails(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases, string $departamentoOrigen = ''): array
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
            $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 'pkg_departamento');
            $this->excludeTestCompanyPackages($query, $config, 'pkg_departamento');
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

    private function buildDepartamentoPendingDetails(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases, ?int $estadoEntregadoId, ?int $estadoCanceladoId, ?int $estadoTransitoId, string $departamentoOrigen = ''): array
    {
        $totales = [
            'EMS' => 0,
            'CONTRATOS' => 0,
            'CERTIFICADOS' => 0,
            'ORDINARIOS' => 0,
        ];
        $rows = collect();
        $estadoSolicitudId = $this->resolveEstadoIdByName('SOLICITUD');

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

            $query->whereExists(function (Builder $eventQuery) use ($config) {
                $eventQuery->selectRaw('1')
                    ->from($config['event_table'] . ' as inicio_operativo')
                    ->whereColumn('inicio_operativo.codigo', 't.codigo')
                    ->whereIn('inicio_operativo.evento_id', $config['operational_start_events']);
            });

            $this->applyDateFilter($query, 't.created_at', $from, $to);
            $this->applyPendingDepartamentoAliasesFilter($query, $config, $aliases, 't');
            $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 't');
            $this->excludeTestCompanyPackages($query, $config, 't');

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

            if ($estadoTransitoId) {
                $query->where(function (Builder $sub) use ($estadoColumn, $estadoTransitoId) {
                    $sub->whereNull('t.' . $estadoColumn)
                        ->orWhere('t.' . $estadoColumn, '<>', $estadoTransitoId);
                });
            }

            if ($estadoSolicitudId) {
                $query->where(function (Builder $sub) use ($estadoColumn, $estadoSolicitudId) {
                    $sub->whereNull('t.' . $estadoColumn)
                        ->orWhere('t.' . $estadoColumn, '<>', $estadoSolicitudId);
                });
            }

            $moduleRows = $query->whereNotNull('t.codigo')->orderByDesc('t.created_at')->orderByDesc('t.id')->get()->uniqueStrict('codigo')->values();
            $totales[$label] = (int) $moduleRows->count();
            $rows = $rows->concat($moduleRows);
        }

        $mappedRows = $rows
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
            ]);

        $grupos = $mappedRows
            ->groupBy(fn (array $row) => strtoupper(trim($row['estado'] !== '' ? $row['estado'] : 'SIN ESTADO')))
            ->map(function ($items, $estado) {
                $items = collect($items)->values();
                $modulos = $items
                    ->groupBy('modulo')
                    ->map(fn ($moduleItems) => $moduleItems->count())
                    ->sortKeys()
                    ->all();
                $fechas = $items
                    ->pluck('creado_at')
                    ->filter()
                    ->values();

                return [
                    'estado' => (string) $estado,
                    'total' => (int) $items->count(),
                    'modulos' => $modulos,
                    'modulos_label' => collect($modulos)
                        ->map(fn ($total, $modulo) => $modulo . ': ' . $total)
                        ->implode(' | '),
                    'fecha_min' => (string) ($fechas->min() ?? ''),
                    'fecha_max' => (string) ($fechas->max() ?? ''),
                    'paquetes' => $items->all(),
                ];
            })
            ->sortByDesc(function (array $grupo) {
                return sprintf(
                    '%08d-%s-%s',
                    (int) ($grupo['total'] ?? 0),
                    (string) ($grupo['fecha_max'] ?? ''),
                    (string) ($grupo['estado'] ?? '')
                );
            })
            ->values()
            ->all();

        return [
            'totales' => $totales,
            'rows' => $mappedRows->all(),
            'grupos' => $grupos,
        ];
    }

    private function buildDepartamentoTransitoDetails(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, array $aliases, ?int $estadoTransitoId, string $departamento, string $departamentoOrigen = ''): array
    {
        $totales = [
            'EMS' => 0,
            'CONTRATOS' => 0,
            'CERTIFICADOS' => 0,
            'ORDINARIOS' => 0,
        ];
        $rows = collect();

        if (!$estadoTransitoId) {
            return [
                'totales' => $totales,
                'rows' => [],
            ];
        }

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
                    DB::raw("coalesce(nullif(trim(coalesce(t.cod_especial::text, '')), ''), 'SIN CODIGO ESPECIAL') as cod_especial"),
                    DB::raw("coalesce(e.nombre_estado, 'SIN ESTADO') as estado"),
                    DB::raw($identityColumns['origen'] . ' as origen'),
                    DB::raw($identityColumns['destino'] . ' as destino'),
                    DB::raw($identityColumns['destinatario'] . ' as destinatario'),
                    't.created_at as creado_at',
                ])
                ->where('t.' . $estadoColumn, $estadoTransitoId);

            $this->applyDateFilter($query, 't.created_at', $from, $to);
            $this->applyOrigenAliasesFilter($query, $config, $aliases, 't');
            $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 't');
            $this->excludeTestCompanyPackages($query, $config, 't');

            $moduleRows = $query->whereNotNull('t.codigo')->orderByDesc('t.created_at')->orderByDesc('t.id')->get()->uniqueStrict('codigo')->values();
            $totales[$label] = (int) $moduleRows->count();
            $rows = $rows->concat($moduleRows);
        }

        $mappedRows = $rows
            ->sortByDesc(fn ($row) => (string) ($row->creado_at ?? ''))
            ->values()
            ->map(function ($row) use ($departamento) {
                $origen = (string) ($row->origen ?? '');

                return [
                    'modulo' => (string) ($row->modulo ?? ''),
                    'codigo' => (string) ($row->codigo ?? ''),
                    'cod_especial' => (string) ($row->cod_especial ?? 'SIN CODIGO ESPECIAL'),
                    'estado' => (string) ($row->estado ?? ''),
                    'origen' => $origen,
                    'origen_grupo' => $this->normalizeDepartamentoDesdeOrigen($origen, $departamento),
                    'destino' => (string) ($row->destino ?? ''),
                    'destinatario' => (string) ($row->destinatario ?? ''),
                    'creado_at' => (string) ($row->creado_at ?? ''),
                ];
            });

        $grupos = $mappedRows
            ->groupBy(function (array $row) {
                return strtoupper(trim($row['origen_grupo'] . '|' . $row['cod_especial']));
            })
            ->map(function ($items) {
                $items = collect($items)->values();
                $primero = $items->first();
                $modulos = $items
                    ->groupBy('modulo')
                    ->map(fn ($moduleItems) => $moduleItems->count())
                    ->sortKeys()
                    ->all();
                $fechas = $items
                    ->pluck('creado_at')
                    ->filter()
                    ->values();

                return [
                    'origen' => (string) ($primero['origen_grupo'] ?? '-'),
                    'cod_especial' => (string) ($primero['cod_especial'] ?? 'SIN CODIGO ESPECIAL'),
                    'total' => (int) $items->count(),
                    'modulos' => $modulos,
                    'modulos_label' => collect($modulos)
                        ->map(fn ($total, $modulo) => $modulo . ': ' . $total)
                        ->implode(' | '),
                    'fecha_min' => (string) ($fechas->min() ?? ''),
                    'fecha_max' => (string) ($fechas->max() ?? ''),
                    'paquetes' => $items->all(),
                ];
            })
            ->sortByDesc(function (array $grupo) {
                return sprintf(
                    '%08d-%s-%s',
                    (int) ($grupo['total'] ?? 0),
                    (string) ($grupo['fecha_max'] ?? ''),
                    (string) ($grupo['cod_especial'] ?? '')
                );
            })
            ->values()
            ->all();

        return [
            'totales' => $totales,
            'rows' => $mappedRows->all(),
            'grupos' => $grupos,
        ];
    }

    private function normalizeDepartamentoDesdeOrigen(string $origen, string $fallbackDepartamento = ''): string
    {
        $normalized = strtoupper(trim($origen));

        if ($normalized !== '') {
            foreach ($this->departamentoAliasMap() as $departamento => $aliases) {
                $normalizedAliases = collect($aliases)
                    ->map(fn ($alias) => strtoupper(trim((string) $alias)))
                    ->all();

                if (in_array($normalized, $normalizedAliases, true)) {
                    return $departamento;
                }
            }
        }

        $fallback = strtoupper(trim($fallbackDepartamento));

        return $fallback !== '' ? $fallback : ($normalized !== '' ? $normalized : '-');
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

        $expression = $this->effectiveDepartamentoExpression($config, $tableAlias);
        if ($expression === '') {
            return;
        }

        $query->whereRaw('trim(upper(' . $expression . ')) = ?', [$departamento]);
    }

    private function applyDepartamentoAliasesFilter(Builder $query, array $config, array $aliases, string $tableAlias = ''): void
    {
        $aliases = collect($aliases)
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $expression = $this->effectiveDepartamentoExpression($config, $tableAlias);
        if ($expression === '' || empty($aliases)) {
            return;
        }

        $query->whereIn(DB::raw('trim(upper(' . $expression . '))'), $aliases);
    }

    private function applyOrigenDepartamentoFilter(Builder $query, array $config, string $departamentoOrigen, string $tableAlias = ''): void
    {
        $departamentoOrigen = strtoupper(trim($departamentoOrigen));
        if ($departamentoOrigen === '') {
            return;
        }

        $aliases = $this->departamentoAliasMap()[$departamentoOrigen] ?? [$departamentoOrigen];
        $this->applyOrigenAliasesFilter($query, $config, $aliases, $tableAlias);
    }

    private function applyPendingDepartamentoAliasesFilter(Builder $query, array $config, array $aliases, string $tableAlias = ''): void
    {
        $aliases = collect($aliases)
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $expression = $this->effectivePendingDepartamentoExpression($config, $tableAlias);
        if ($expression === '' || empty($aliases)) {
            return;
        }

        $query->whereIn(DB::raw('trim(upper(' . $expression . '))'), $aliases);
    }

    private function applyOrigenAliasesFilter(Builder $query, array $config, array $aliases, string $tableAlias = ''): void
    {
        $aliases = collect($aliases)
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $expression = $this->effectiveOrigenExpression($config, $tableAlias);
        if ($expression === '' || empty($aliases)) {
            // Los módulos sin columna de origen no coinciden cuando se filtra por origen.
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn(DB::raw('trim(upper(' . $expression . '))'), $aliases);
    }

    private function effectiveDepartamentoExpression(array $config, string $tableAlias = ''): string
    {
        $destinoColumn = (string) ($config['departamento_column'] ?? '');
        if ($destinoColumn === '') {
            return '';
        }

        $qualifiedDestino = ($tableAlias !== '' ? $tableAlias . '.' : '') . $destinoColumn;
        $origenColumn = (string) ($config['origen_column'] ?? '');
        $estadoColumn = (string) ($config['estado_column'] ?? '');
        $estadoAlmacenId = $this->resolveEstadoIdByName('ALMACEN');

        if ($origenColumn === '' || $estadoColumn === '' || !$estadoAlmacenId) {
            return 'coalesce(' . $qualifiedDestino . ", '')";
        }

        $qualifiedOrigen = ($tableAlias !== '' ? $tableAlias . '.' : '') . $origenColumn;
        $qualifiedEstado = ($tableAlias !== '' ? $tableAlias . '.' : '') . $estadoColumn;

        return 'case when '
            . $qualifiedEstado . ' = ' . (int) $estadoAlmacenId
            . " then coalesce(nullif(trim(" . $qualifiedOrigen . "), ''), " . $qualifiedDestino . ")"
            . ' else coalesce(' . $qualifiedDestino . ", '') end";
    }

    private function effectivePendingDepartamentoExpression(array $config, string $tableAlias = ''): string
    {
        $destinoColumn = (string) ($config['departamento_column'] ?? '');
        if ($destinoColumn === '') {
            return '';
        }

        $qualifiedDestino = ($tableAlias !== '' ? $tableAlias . '.' : '') . $destinoColumn;
        $origenColumn = (string) ($config['origen_column'] ?? '');
        $estadoColumn = (string) ($config['estado_column'] ?? '');
        $estadoAlmacenId = $this->resolveEstadoIdByName('ALMACEN');
        $estadoAdmisionesId = $this->resolveEstadoIdByName('ADMISIONES');

        if ($origenColumn === '' || $estadoColumn === '') {
            return 'coalesce(' . $qualifiedDestino . ", '')";
        }

        $qualifiedOrigen = ($tableAlias !== '' ? $tableAlias . '.' : '') . $origenColumn;
        $qualifiedEstado = ($tableAlias !== '' ? $tableAlias . '.' : '') . $estadoColumn;
        $origenConFallback = 'coalesce(nullif(trim(' . $qualifiedOrigen . "), ''), " . $qualifiedDestino . ')';

        $cases = [];

        if ($estadoAdmisionesId) {
            $cases[] = 'when ' . $qualifiedEstado . ' = ' . (int) $estadoAdmisionesId
                . ' then ' . $origenConFallback;
        }

        if ($estadoAlmacenId) {
            $cases[] = 'when ' . $qualifiedEstado . ' = ' . (int) $estadoAlmacenId
                . ' then case when trim(upper(coalesce(' . $qualifiedOrigen . ", ''))) = trim(upper(coalesce(" . $qualifiedDestino . ", '')))"
                . ' then coalesce(' . $qualifiedDestino . ", '') else '' end";
        }

        if (empty($cases)) {
            return 'coalesce(' . $qualifiedDestino . ", '')";
        }

        return 'case '
            . implode(' ', $cases)
            . ' else coalesce(' . $qualifiedDestino . ", '') end";
    }

    private function effectiveOrigenExpression(array $config, string $tableAlias = ''): string
    {
        $origenColumn = (string) ($config['origen_column'] ?? '');
        if ($origenColumn === '') {
            return '';
        }

        $qualifiedOrigen = ($tableAlias !== '' ? $tableAlias . '.' : '') . $origenColumn;

        return 'coalesce(nullif(trim(' . $qualifiedOrigen . "), ''), '')";
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

    private function applyEventOrigenDepartamentoFilter(Builder $query, array $config, string $departamentoOrigen, string $eventAlias = ''): void
    {
        if ($departamentoOrigen === '') {
            return;
        }

        $eventCodeColumn = ($eventAlias !== '' ? $eventAlias : $config['event_table']) . '.codigo';
        $query->join($config['table'] . ' as pkg_origen_departamento', 'pkg_origen_departamento.codigo', '=', $eventCodeColumn);
        $this->applyOrigenDepartamentoFilter($query, $config, $departamentoOrigen, 'pkg_origen_departamento');
    }

    private function buildRankingRegistradores(array $modulosSeleccionados, ?Carbon $from, ?Carbon $to, string $departamento = '', string $departamentoOrigen = '')
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
            $this->applyEventOrigenDepartamentoFilter($query, $config, $departamentoOrigen);
            $this->excludeTestCompanyEvents($query, $config, $config['event_table']);
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

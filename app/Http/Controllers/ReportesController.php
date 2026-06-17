<?php

namespace App\Http\Controllers;

use App\Exports\ReportesExport;
use App\Models\Estado;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportesController extends Controller
{
    private const EVENTO_ENTREGADO_ID = 316;
    private const EVENTO_EMS_SOLICITUD_ID = 295;
    private const EVENTO_CONTRATO_RECOGIDO_ID = 295;
    private const CERTI_ORDI_GREEN_DAYS = 7;
    private const CERTI_ORDI_YELLOW_DAYS = 15;
    private const DESTINOS_LARGA_DISTANCIA = ['SANTA CRUZ', 'TRINIDAD', 'TARIJA'];
    private const DESTINOS_BASE = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
    private const DESTINOS_CAPITALES = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
    private const SCOPES = ['general', 'contrato', 'ems', 'certi', 'ordi'];

    private const MODULES = [
        'contrato' => ['label' => 'CONTRATOS', 'table' => 'paquetes_contrato', 'state_col' => 'estados_id', 'origen_col' => 'origen', 'destino_col' => 'destino'],
        'ems' => ['label' => 'EMS', 'table' => 'paquetes_ems', 'state_col' => 'estado_id', 'origen_col' => 'origen', 'destino_col' => 'ciudad'],
        'certi' => ['label' => 'CERTIFICADOS', 'table' => 'paquetes_certi', 'state_col' => 'fk_estado', 'origen_col' => null, 'destino_col' => 'cuidad'],
        'ordi' => ['label' => 'ORDINARIOS', 'table' => 'paquetes_ordi', 'state_col' => 'fk_estado', 'origen_col' => null, 'destino_col' => 'ciudad'],
    ];

    public function index(Request $request)
    {
        return redirect()->route('reportes.scope', array_merge(['scope' => 'general'], $request->query()));
    }

    public function show(Request $request, string $scope)
    {
        $scope = $this->normalizeScope($scope);
        $data = $this->buildReportData($request, $scope, false);

        return view('reportes.index', $data);
    }

    public function exportExcel(Request $request, string $scope)
    {
        $scope = $this->normalizeScope($scope);
        $data = $this->buildReportData($request, $scope, true);
        $filename = 'reportes-' . $scope . '-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new ReportesExport($data), $filename);
    }

    public function exportPdf(Request $request, string $scope)
    {
        $scope = $this->normalizeScope($scope);
        $data = $this->buildReportData($request, $scope, true);
        $pdf = Pdf::loadView('reportes.report-pdf', $data)->setPaper('A4', 'landscape');
        $filename = 'reportes-' . $scope . '-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function globalIngreso(Request $request)
    {
        $request->query->set('limit', 'all');

        $data = $this->buildReportData($request, 'general', false);
        $data['scopeLabel'] = 'Global Nivel Nacional (Ingreso)';
        $data['globalIngresoMode'] = true;

        return view('reportes.global-ingreso', $data);
    }

    public function globalPorServicio(Request $request)
    {
        $data = $this->buildGlobalPorServicioData($request);

        return view('reportes.global-por-servicio', $data);
    }

    public function exportGlobalPorServicioExcel(Request $request)
    {
        @set_time_limit(300);

        $data = $this->buildGlobalPorServicioData($request);
        $filename = 'global-por-servicio-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new \App\Exports\GlobalPorServicioExport($data), $filename);
    }

    public function exportGlobalPorServicioPdf(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '1024M');

        $data = $this->buildGlobalPorServicioData($request);
        $pdf = Pdf::loadView('reportes.global-por-servicio-pdf', $data)->setPaper('A4', 'landscape');
        $filename = 'global-por-servicio-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function exportGlobalIngresoExcel(Request $request)
    {
        @set_time_limit(300);
        $request->query->set('limit', 'all');

        $data = $this->buildReportData($request, 'general', true);
        $data['scopeLabel'] = 'Global Nivel Nacional (Ingreso)';
        $data['globalIngresoMode'] = true;
        $filename = 'global-nivel-nacional-ingreso-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new ReportesExport($data), $filename);
    }

    public function exportGlobalIngresoPdf(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '1024M');
        $request->query->set('limit', 'all');

        $data = $this->buildReportData($request, 'general', true);
        $data['scopeLabel'] = 'Global Nivel Nacional (Ingreso)';
        $data['globalIngresoMode'] = true;
        $allRows = collect($data['rows'] ?? []);
        $data['pdfTotalRows'] = $allRows->count();
        $data['pdfRowsLimit'] = 1000;
        $data['rows'] = $allRows->take($data['pdfRowsLimit'])->values();
        $pdf = Pdf::loadView('reportes.global-ingreso-pdf', $data)
            ->setPaper('A4', 'landscape')
            ->setOptions([
                'dpi' => 72,
                'defaultFont' => 'DejaVu Sans',
                'isRemoteEnabled' => false,
                'isHtml5ParserEnabled' => false,
            ]);
        $filename = 'global-nivel-nacional-ingreso-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function administrativeSummary(Request $request)
    {
        $request->merge(['limit' => 'all']);

        $data = $this->buildReportData($request, 'general', true);
        $data['scopeLabel'] = 'Resumen Ejecutivo';
        $data['administrativeSummary'] = $this->buildAdministrativeSummary(collect($data['rows']), $data['departamentoOrigen'] ?? '');
        $data['administrativeSummary']['malencaminados'] = $this->buildAdministrativeMalencaminadosSummary(
            $request,
            $data['departamentoOrigen'] ?? '',
            $data['departamentoDestino'] ?? ''
        );

        return view('reportes.resumen-administrativo', $data);
    }

    public function exportAdministrativePdf(Request $request)
    {
        $request->merge(['limit' => 'all']);

        $data = $this->buildReportData($request, 'general', true);
        $data['scopeLabel'] = 'Resumen Ejecutivo';
        $data['administrativeSummary'] = $this->buildAdministrativeSummary(collect($data['rows']), $data['departamentoOrigen'] ?? '');
        $data['administrativeSummary']['malencaminados'] = $this->buildAdministrativeMalencaminadosSummary(
            $request,
            $data['departamentoOrigen'] ?? '',
            $data['departamentoDestino'] ?? ''
        );

        $pdf = Pdf::loadView('reportes.resumen-administrativo-pdf', $data)->setPaper('A4', 'landscape');
        $filename = 'resumen-ejecutivo-paquetes-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    private function buildReportData(Request $request, string $scope, bool $forExport): array
    {
        $selectedModules = $this->resolveSelectedModules($scope, $request);
        [$from, $to, $range] = $this->resolveDateRange($request);
        $selectedMonths = $this->resolveSelectedMonthFilters($request);
        $monthDateRanges = $this->buildMonthDateRanges($selectedMonths);
        if (!empty($monthDateRanges)) {
            $from = null;
            $to = null;
            $range = 'months';
        }
        $search = trim((string) $request->query('q', ''));
        $departamentoOrigen = $this->resolveDepartamentoFiltro($request, 'departamento_origen');
        $departamentoDestino = $this->resolveDepartamentoFiltro($request, 'departamento_destino');
        if ($departamentoDestino === '') {
            $departamentoDestino = $this->resolveDepartamentoFiltro($request, 'departamento');
        }
        $statuses = $this->resolveStatusFilters($request);
        $selectedServices = $this->resolveServiceFilters($request);
        $estadoIds = [];
        $limit = $this->resolveLimit($request);
        $perPage = $this->resolvePerPage($request);
        $estadoEntregadoId = $this->resolveEstadoEntregadoId();
        $estadoCanceladoId = $this->resolveEstadoCanceladoId();

        $rows = collect();
        foreach ($selectedModules as $moduleKey) {
            $rows = $rows->concat(
                $this->fetchRowsForModule(
                    $moduleKey,
                    $search,
                    'all',
                    [],
                    $estadoEntregadoId,
                    $estadoCanceladoId,
                    $from,
                    $to,
                    $monthDateRanges,
                    $departamentoDestino
                )
            );
        }

        $rows = $this->filterRowsWithoutCanceled($rows);
        $rows = $this->filterRowsByDepartamentoOrigen($rows, $departamentoOrigen);
        $registradosTotal = $rows->count();
        $rows = $this->filterRowsByState($rows, $statuses);
        $serviceOptions = $this->serviceOptionsFromRows($rows);
        $rows = $this->filterRowsByService($rows, $selectedServices);
        $filteredTotal = $rows->count();
        $summary = $this->buildSummary($rows);
        $summary['registrados'] = $registradosTotal;
        $summary['total_filtrado'] = $filteredTotal;

        $rows = $rows->sortByDesc(fn ($row) => $row['created_at_ts'] ?? 0)->values();
        if ($limit !== null) {
            $rows = $rows->take($limit)->values();
        }

        $moduleSummary = $this->buildModuleSummary($rows, $selectedModules);
        $serviceSummary = $this->buildServiceSummary($rows);
        $totals = $this->buildTotals($rows);
        $rowsView = $forExport ? $rows : $this->paginateCollection($rows, $perPage, $request);

        return [
            'scope' => $scope,
            'scopeLabel' => $this->scopeLabel($scope),
            'moduleLabels' => array_map(fn ($m) => self::MODULES[$m]['label'], $selectedModules),
            'selectedModules' => $selectedModules,
            'states' => Estado::query()->orderBy('nombre_estado')->get(['id', 'nombre_estado']),
            'selectedEstadoIds' => $estadoIds,
            'search' => $search,
            'statuses' => $statuses,
            'selectedServices' => $selectedServices,
            'serviceOptions' => $serviceOptions,
            'departamento' => $departamentoDestino,
            'departamentoOrigen' => $departamentoOrigen,
            'departamentoDestino' => $departamentoDestino,
            'departamentosDisponibles' => array_keys($this->departamentoAliasMap()),
            'monthOptions' => $this->monthFilterOptions(),
            'selectedMonths' => $selectedMonths,
            'selectedMonthLabels' => array_map(fn ($month) => $this->monthFilterLabel($month), $selectedMonths),
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'range' => $range,
            'limit' => $limit === null ? 'all' : (string) $limit,
            'perPage' => $perPage,
            'rows' => $rowsView,
            'summary' => $summary,
            'moduleSummary' => $moduleSummary,
            'serviceSummary' => $serviceSummary,
            'totals' => $totals,
            'isExport' => $forExport,
        ];
    }

    private function fetchRowsForModule(
        string $moduleKey,
        string $search,
        string $status,
        array $estadoIds,
        ?int $estadoEntregadoId,
        ?int $estadoCanceladoId,
        ?Carbon $from,
        ?Carbon $to,
        array $monthDateRanges,
        string $departamentoDestino
    ): Collection {
        $config = self::MODULES[$moduleKey];
        $query = match ($moduleKey) {
            'contrato' => $this->buildContratoQuery(),
            'ems' => $this->buildEmsQuery(),
            'certi' => $this->buildCertiQuery(),
            'ordi' => $this->buildOrdiQuery(),
        };

        $this->applyDateFilter($query, 't.created_at', $from, $to, $monthDateRanges);
        $this->applyStatusFilter($query, 't.' . $config['state_col'], $status, $estadoEntregadoId);
        if (!empty($estadoIds)) {
            $query->whereIn('t.' . $config['state_col'], $estadoIds);
        }

        $this->applyDepartamentoFilter($query, $moduleKey, $departamentoDestino, 'destino');
        $this->applySearchFilter($query, $moduleKey, $search);

        return $query->get()->map(function ($row) use ($moduleKey, $estadoEntregadoId, $estadoCanceladoId) {
            return $this->decorateRow($moduleKey, $row, $estadoEntregadoId, $estadoCanceladoId);
        });
    }

    private function buildContratoQuery(): Builder
    {
        $deliveredSub = $this->deliveredEventSubquery('eventos_contrato');
        $pickupSub = $this->eventUserSubquery('eventos_contrato', self::EVENTO_CONTRATO_RECOGIDO_ID);
        $rolesSub = $this->roleNamesSubquery();
        $regionalesUserExpression = $this->regionalesValueExpression('u');
        $regionalesPickupExpression = $this->regionalesValueExpression('up');
        $empresaUserCondition = "(coalesce(ur.role_names, '') like '%empresa%' or u.empresa_id is not null)";

        return DB::table('paquetes_contrato as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.estados_id')
            ->leftJoin('empresa as emp', 'emp.id', '=', 't.empresa_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->leftJoinSub($rolesSub, 'ur', function ($join) {
                $join->on('ur.model_id', '=', 'u.id');
            })
            ->leftJoinSub($pickupSub, 'ev_r', function ($join) {
                $join->on('ev_r.codigo', '=', 't.codigo');
            })
            ->leftJoin('users as up', 'up.id', '=', 'ev_r.user_id')
            ->leftJoinSub($rolesSub, 'upr', function ($join) {
                $join->on('upr.model_id', '=', 'up.id');
            })
            ->leftJoin('tarifa_contrato as tc', 'tc.id', '=', 't.tarifa_contrato_id')
            ->leftJoinSub($deliveredSub, 'ev_d', function ($join) {
                $join->on('ev_d.codigo', '=', 't.codigo');
            })
            ->leftJoin('users as ud', 'ud.id', '=', 'ev_d.user_id')
            ->leftJoinSub($rolesSub, 'udr', function ($join) {
                $join->on('udr.model_id', '=', 'ud.id');
            })
            ->select([
                DB::raw("'contrato' as modulo_key"),
                DB::raw("'CONTRATOS' as modulo_label"),
                't.codigo',
                DB::raw('t.estados_id as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("coalesce(t.origen, '-') as origen"),
                DB::raw("coalesce(t.destino, '-') as destino"),
                DB::raw("coalesce(t.nombre_r, '-') as remitente"),
                DB::raw("coalesce(t.nombre_d, '-') as destinatario"),
                DB::raw("coalesce(emp.nombre, '-') as empresa"),
                DB::raw("case when {$empresaUserCondition} then coalesce(up.id, u.id, 0) else coalesce(u.id, 0) end as usuario_id"),
                DB::raw("case when {$empresaUserCondition} then coalesce(up.name, u.name, '-') else coalesce(u.name, '-') end as usuario"),
                DB::raw("case when {$empresaUserCondition} then coalesce(upr.role_names, '') else coalesce(ur.role_names, '') end as usuario_roles"),
                DB::raw("case when {$empresaUserCondition} then coalesce(up.ciudad, u.ciudad, '-') else coalesce(u.ciudad, '-') end as usuario_regional"),
                DB::raw("case when {$empresaUserCondition} then {$regionalesPickupExpression} else {$regionalesUserExpression} end as usuario_regionales"),
                DB::raw("coalesce(tc.servicio, 'CONTRATOS') as servicio_nombre"),
                DB::raw("coalesce(ev_d.user_id, 0) as entregado_por_id"),
                DB::raw("coalesce(ud.name, 'Sin entrega registrada') as entregado_por"),
                DB::raw("coalesce(udr.role_names, '') as entregado_por_roles"),
                'ev_d.delivered_at',
                DB::raw("case when {$empresaUserCondition} then case when up.deleted_at is null and up.id is not null then 1 else 0 end else case when u.deleted_at is null and u.id is not null then 1 else 0 end end as usuario_activo"),
                DB::raw("case when {$empresaUserCondition} then 1 else 0 end as usuario_empresa_gestora"),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('coalesce(t.precio, 0) as precio'),
                't.created_at',
                't.updated_at',
                't.fecha_recojo',
                't.provincia',
            ]);
    }

    private function buildEmsQuery(): Builder
    {
        $solicitudSub = DB::table('eventos_ems')
            ->select('codigo', DB::raw('MIN(created_at) as solicitud_at'))
            ->where('evento_id', self::EVENTO_EMS_SOLICITUD_ID)
            ->groupBy('codigo');
        $deliveredSub = $this->deliveredEventSubquery('eventos_ems');
        $pickupSub = $this->eventUserSubquery('eventos_ems', self::EVENTO_EMS_SOLICITUD_ID);

        $rolesSub = $this->roleNamesSubquery();
        $regionalesUserExpression = $this->regionalesValueExpression('u');
        $regionalesPickupExpression = $this->regionalesValueExpression('up');
        $empresaUserCondition = "(coalesce(ur.role_names, '') like '%empresa%' or u.empresa_id is not null)";

        return DB::table('paquetes_ems as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.estado_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->leftJoin('tarifario as tar', 'tar.id', '=', 't.tarifario_id')
            ->leftJoin('servicio as srv', 'srv.id', '=', 'tar.servicio_id')
            ->leftJoinSub($rolesSub, 'ur', function ($join) {
                $join->on('ur.model_id', '=', 'u.id');
            })
            ->leftJoinSub($pickupSub, 'ev_r', function ($join) {
                $join->on('ev_r.codigo', '=', 't.codigo');
            })
            ->leftJoin('users as up', 'up.id', '=', 'ev_r.user_id')
            ->leftJoinSub($rolesSub, 'upr', function ($join) {
                $join->on('upr.model_id', '=', 'up.id');
            })
            ->leftJoinSub($solicitudSub, 'ev_s', function ($join) {
                $join->on('ev_s.codigo', '=', 't.codigo');
            })
            ->leftJoinSub($deliveredSub, 'ev_d', function ($join) {
                $join->on('ev_d.codigo', '=', 't.codigo');
            })
            ->leftJoin('users as ud', 'ud.id', '=', 'ev_d.user_id')
            ->leftJoinSub($rolesSub, 'udr', function ($join) {
                $join->on('udr.model_id', '=', 'ud.id');
            })
            ->select([
                DB::raw("'ems' as modulo_key"),
                DB::raw("'EMS' as modulo_label"),
                't.codigo',
                DB::raw('t.estado_id as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("coalesce(t.origen, '-') as origen"),
                DB::raw("coalesce(t.ciudad, '-') as destino"),
                DB::raw("coalesce(t.nombre_remitente, '-') as remitente"),
                DB::raw("coalesce(t.nombre_destinatario, '-') as destinatario"),
                DB::raw("'-' as empresa"),
                DB::raw("case when {$empresaUserCondition} then coalesce(up.id, 0) else coalesce(u.id, 0) end as usuario_id"),
                DB::raw("case when {$empresaUserCondition} then coalesce(up.name, '-') else coalesce(u.name, '-') end as usuario"),
                DB::raw("case when {$empresaUserCondition} then coalesce(up.ciudad, '-') else coalesce(u.ciudad, '-') end as usuario_regional"),
                DB::raw("case when {$empresaUserCondition} then {$regionalesPickupExpression} else {$regionalesUserExpression} end as usuario_regionales"),
                DB::raw("case when {$empresaUserCondition} then coalesce(upr.role_names, '') else coalesce(ur.role_names, '') end as usuario_roles"),
                DB::raw("'EMS' as servicio_nombre"),
                DB::raw("coalesce(ev_d.user_id, 0) as entregado_por_id"),
                DB::raw("coalesce(ud.name, 'Sin entrega registrada') as entregado_por"),
                DB::raw("coalesce(udr.role_names, '') as entregado_por_roles"),
                'ev_d.delivered_at',
                DB::raw("case when {$empresaUserCondition} then case when up.deleted_at is null and up.id is not null then 1 else 0 end else case when u.deleted_at is null and u.id is not null then 1 else 0 end end as usuario_activo"),
                DB::raw("case when {$empresaUserCondition} then 1 else 0 end as usuario_empresa_gestora"),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('coalesce(t.precio, 0) as precio'),
                't.created_at',
                't.updated_at',
                'ev_s.solicitud_at',
            ]);
    }

    private function buildCertiQuery(): Builder
    {
        $inicioSub = DB::table('eventos_certi')
            ->select('codigo', DB::raw('MIN(created_at) as primer_evento_at'), DB::raw('MIN(user_id) as registro_user_id'))
            ->groupBy('codigo');
        $deliveredSub = $this->deliveredEventSubquery('eventos_certi');
        $rolesSub = $this->roleNamesSubquery();
        $regionalesExpression = $this->regionalesValueExpression('u') . ' as usuario_regionales';

        return DB::table('paquetes_certi as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.fk_estado')
            ->leftJoin('servicio as srv', 'srv.id', '=', 't.servicio_id')
            ->leftJoinSub($inicioSub, 'ev_i', function ($join) {
                $join->on('ev_i.codigo', '=', 't.codigo');
            })
            ->leftJoin('users as u', 'u.id', '=', 'ev_i.registro_user_id')
            ->leftJoinSub($rolesSub, 'ur', function ($join) {
                $join->on('ur.model_id', '=', 'u.id');
            })
            ->leftJoinSub($deliveredSub, 'ev_d', function ($join) {
                $join->on('ev_d.codigo', '=', 't.codigo');
            })
            ->leftJoin('users as ud', 'ud.id', '=', 'ev_d.user_id')
            ->leftJoinSub($rolesSub, 'udr', function ($join) {
                $join->on('udr.model_id', '=', 'ud.id');
            })
            ->select([
                DB::raw("'certi' as modulo_key"),
                DB::raw("'CERTIFICADOS' as modulo_label"),
                't.codigo',
                DB::raw('t.fk_estado as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("'-' as origen"),
                DB::raw("coalesce(t.cuidad, '-') as destino"),
                DB::raw("'-' as remitente"),
                DB::raw("coalesce(t.destinatario, '-') as destinatario"),
                DB::raw("'-' as empresa"),
                DB::raw("coalesce(u.id, 0) as usuario_id"),
                DB::raw("coalesce(u.name, '-') as usuario"),
                DB::raw("coalesce(ur.role_names, '') as usuario_roles"),
                DB::raw("coalesce(u.ciudad, '-') as usuario_regional"),
                DB::raw($regionalesExpression),
                DB::raw("coalesce(nullif(trim(t.tipo), ''), srv.nombre_servicio, 'CERTIFICADOS') as servicio_nombre"),
                DB::raw("coalesce(ev_d.user_id, 0) as entregado_por_id"),
                DB::raw("coalesce(ud.name, 'Sin entrega registrada') as entregado_por"),
                DB::raw("coalesce(udr.role_names, '') as entregado_por_roles"),
                'ev_d.delivered_at',
                DB::raw("case when u.deleted_at is null and u.id is not null then 1 else 0 end as usuario_activo"),
                DB::raw("0 as usuario_empresa_gestora"),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('coalesce(t.precio, 0) as precio'),
                't.created_at',
                't.updated_at',
                'ev_i.primer_evento_at',
            ]);
    }

    private function buildOrdiQuery(): Builder
    {
        $inicioSub = DB::table('eventos_ordi')
            ->select('codigo', DB::raw('MIN(created_at) as primer_evento_at'), DB::raw('MIN(user_id) as registro_user_id'))
            ->groupBy('codigo');
        $deliveredSub = $this->deliveredEventSubquery('eventos_ordi');
        $rolesSub = $this->roleNamesSubquery();
        $regionalesExpression = $this->regionalesValueExpression('u') . ' as usuario_regionales';

        return DB::table('paquetes_ordi as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.fk_estado')
            ->leftJoin('servicio as srv', 'srv.id', '=', 't.servicio_id')
            ->leftJoinSub($inicioSub, 'ev_i', function ($join) {
                $join->on('ev_i.codigo', '=', 't.codigo');
            })
            ->leftJoin('users as u', 'u.id', '=', 'ev_i.registro_user_id')
            ->leftJoinSub($rolesSub, 'ur', function ($join) {
                $join->on('ur.model_id', '=', 'u.id');
            })
            ->leftJoinSub($deliveredSub, 'ev_d', function ($join) {
                $join->on('ev_d.codigo', '=', 't.codigo');
            })
            ->leftJoin('users as ud', 'ud.id', '=', 'ev_d.user_id')
            ->leftJoinSub($rolesSub, 'udr', function ($join) {
                $join->on('udr.model_id', '=', 'ud.id');
            })
            ->select([
                DB::raw("'ordi' as modulo_key"),
                DB::raw("'ORDINARIOS' as modulo_label"),
                't.codigo',
                DB::raw('t.fk_estado as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("'-' as origen"),
                DB::raw("coalesce(t.ciudad, '-') as destino"),
                DB::raw("'-' as remitente"),
                DB::raw("coalesce(t.destinatario, '-') as destinatario"),
                DB::raw("'-' as empresa"),
                DB::raw("coalesce(u.id, 0) as usuario_id"),
                DB::raw("coalesce(u.name, '-') as usuario"),
                DB::raw("coalesce(ur.role_names, '') as usuario_roles"),
                DB::raw("coalesce(u.ciudad, '-') as usuario_regional"),
                DB::raw($regionalesExpression),
                DB::raw("coalesce(srv.nombre_servicio, 'ORDINARIOS') as servicio_nombre"),
                DB::raw("coalesce(ev_d.user_id, 0) as entregado_por_id"),
                DB::raw("coalesce(ud.name, 'Sin entrega registrada') as entregado_por"),
                DB::raw("coalesce(udr.role_names, '') as entregado_por_roles"),
                'ev_d.delivered_at',
                DB::raw("case when u.deleted_at is null and u.id is not null then 1 else 0 end as usuario_activo"),
                DB::raw("0 as usuario_empresa_gestora"),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('coalesce(t.precio, 0) as precio'),
                't.created_at',
                't.updated_at',
                'ev_i.primer_evento_at',
            ]);
    }

    private function decorateRow(string $moduleKey, object $row, ?int $estadoEntregadoId, ?int $estadoCanceladoId): array
    {
        $estadoId = (int) ($row->estado_id ?? 0);
        $isEntregado = $estadoEntregadoId && $estadoId === $estadoEntregadoId;
        $isCancelado = $estadoCanceladoId && $estadoId === $estadoCanceladoId;
        $bucket = 'sin_datos';
        $situacion = 'Sin datos';

        if ($isEntregado) {
            $bucket = 'entregado';
            $situacion = 'Entregado';
        } elseif ($isCancelado) {
            $bucket = 'cancelado';
            $situacion = 'Cancelado';
        } else {
            $inicio = match ($moduleKey) {
                'contrato' => $this->safeCarbon($row->fecha_recojo ?? null),
                'ems' => $this->safeCarbon($row->solicitud_at ?? null) ?? $this->safeCarbon($row->created_at ?? null),
                default => $this->safeCarbon($row->primer_evento_at ?? null) ?? $this->safeCarbon($row->created_at ?? null),
            };

            if ($moduleKey === 'contrato') {
                $esProvincia = trim((string) ($row->provincia ?? '')) !== '';
                $umbral = $this->resolveEmsThresholdDays((string) ($row->destino ?? ''), $esProvincia);
                $bucket = $this->resolveSituacionBucket($inicio, now(), $umbral['green'], $umbral['yellow']);
            } elseif ($moduleKey === 'ems') {
                $destino = (string) ($row->destino ?? '');
                $esProvincia = $this->isEmsProvincia($destino);
                $umbral = $this->resolveEmsThresholdDays($destino, $esProvincia);
                $bucket = $this->resolveSituacionBucket($inicio, now(), $umbral['green'], $umbral['yellow']);
            } else {
                $bucket = $this->resolveSituacionBucket($inicio, now(), self::CERTI_ORDI_GREEN_DAYS, self::CERTI_ORDI_YELLOW_DAYS);
            }

            $situacion = match ($bucket) {
                'correcto' => 'En plazo',
                'retraso' => 'Retraso',
                'rezago' => 'Rezago',
                default => 'Sin datos',
            };
        }

        $createdAt = $this->safeCarbon($row->created_at ?? null);
        $updatedAt = $this->safeCarbon($row->updated_at ?? null);
        $deliveredAt = $this->safeCarbon($row->delivered_at ?? null);
        $deliveryHours = ($createdAt && $deliveredAt && $deliveredAt->greaterThanOrEqualTo($createdAt))
            ? round($createdAt->diffInMinutes($deliveredAt) / 60, 2)
            : null;
        $regional = $this->resolveRegionalText($row->usuario_regional ?? null, $row->usuario_regionales ?? null);
        $origen = (string) ($row->origen ?? '-');
        $origenRegistro = in_array($moduleKey, ['certi', 'ordi'], true) || trim($origen) === '' || trim($origen) === '-'
            ? ($this->firstDepartamentoFromList($regional) ?: $regional)
            : $origen;
        $usuarioEmpresaGestora = (bool) ((int) ($row->usuario_empresa_gestora ?? 0));
        $canalRecepcion = match ($moduleKey) {
            'contrato' => $usuarioEmpresaGestora ? 'Empresa' : 'Registro interno',
            'ems' => $usuarioEmpresaGestora ? 'Empresa' : 'Admisión',
            default => 'Registro interno',
        };

        return [
            'modulo_key' => $row->modulo_key,
            'modulo_label' => $row->modulo_label,
            'codigo' => (string) ($row->codigo ?? '-'),
            'estado' => (string) ($row->estado_nombre ?? '-'),
            'origen' => $origen,
            'origen_registro' => $origenRegistro,
            'destino' => (string) ($row->destino ?? '-'),
            'remitente' => (string) ($row->remitente ?? '-'),
            'destinatario' => (string) ($row->destinatario ?? '-'),
            'empresa' => (string) ($row->empresa ?? '-'),
            'usuario_id' => (int) ($row->usuario_id ?? 0),
            'usuario' => (string) ($row->usuario ?? '-'),
            'usuario_roles' => (string) ($row->usuario_roles ?? ''),
            'regional' => $regional,
            'servicio' => $this->normalizeServiceName((string) ($row->servicio_nombre ?? '-')),
            'entregado_por_id' => (int) ($row->entregado_por_id ?? 0),
            'entregado_por' => (string) ($row->entregado_por ?? 'Sin entrega registrada'),
            'entregado_por_roles' => (string) ($row->entregado_por_roles ?? ''),
            'usuario_activo' => (bool) ((int) ($row->usuario_activo ?? 0)),
            'usuario_empresa_gestora' => $usuarioEmpresaGestora,
            'canal_recepcion' => $canalRecepcion,
            'peso' => (float) ($row->peso ?? 0),
            'precio' => (float) ($row->precio ?? 0),
            'is_entregado' => $isEntregado,
            'is_cancelado' => $isCancelado,
            'situacion_bucket' => $bucket,
            'situacion_class' => $this->situacionBadgeClass($bucket),
            'situacion' => $situacion,
            'created_at' => $createdAt?->format('d/m/Y H:i') ?? '-',
            'updated_at' => $updatedAt?->format('d/m/Y H:i') ?? '-',
            'delivered_at' => $deliveredAt?->format('d/m/Y H:i') ?? '-',
            'created_at_ts' => $createdAt?->timestamp ?? 0,
            'delivered_at_ts' => $deliveredAt?->timestamp ?? 0,
            'delivery_hours' => $deliveryHours,
            'estado_id' => (int) ($row->estado_id ?? 0),
        ];
    }

    private function situacionBadgeClass(string $bucket): string
    {
        return match ($bucket) {
            'correcto' => 'badge-success',
            'retraso' => 'badge-warning',
            'rezago' => 'badge-danger',
            'entregado' => 'badge-primary',
            default => 'badge-secondary',
        };
    }

    private function buildSummary(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'entregados' => $rows->where('is_entregado', true)->count(),
            'no_entregados' => $rows
                ->where('is_entregado', false)
                ->where('is_cancelado', false)
                ->count(),
            'correcto' => $rows->where('situacion_bucket', 'correcto')->count(),
            'retraso' => $rows->where('situacion_bucket', 'retraso')->count(),
            'rezago' => $rows->where('situacion_bucket', 'rezago')->count(),
        ];
    }

    private function buildModuleSummary(Collection $rows, array $selectedModules): array
    {
        $result = [];
        foreach ($selectedModules as $moduleKey) {
            $label = self::MODULES[$moduleKey]['label'] ?? strtoupper($moduleKey);
            $moduleRows = $rows->where('modulo_key', $moduleKey)->values();

            $result[] = [
                'key' => $moduleKey,
                'label' => $label,
                'total' => $moduleRows->count(),
                'entregados' => $moduleRows->where('is_entregado', true)->count(),
                'no_entregados' => $moduleRows
                    ->where('is_entregado', false)
                    ->where('is_cancelado', false)
                    ->count(),
                'correcto' => $moduleRows->where('situacion_bucket', 'correcto')->count(),
                'retraso' => $moduleRows->where('situacion_bucket', 'retraso')->count(),
                'rezago' => $moduleRows->where('situacion_bucket', 'rezago')->count(),
                'peso' => round((float) $moduleRows->sum('peso'), 3),
                'precio' => round((float) $moduleRows->sum('precio'), 2),
            ];
        }

        return $result;
    }

    private function buildServiceSummary(Collection $rows): array
    {
        return $rows
            ->groupBy(fn (array $row) => $this->normalizeServiceName((string) ($row['servicio'] ?? 'SIN SERVICIO')))
            ->map(function (Collection $items, string $service) {
                return [
                    'servicio' => $service,
                    'cantidad' => $items->count(),
                    'peso' => round((float) $items->sum('peso'), 3),
                    'precio' => round((float) $items->sum('precio'), 2),
                ];
            })
            ->sortByDesc('precio')
            ->values()
            ->all();
    }

    private function buildTotals(Collection $rows): array
    {
        return [
            'peso_total' => round((float) $rows->sum('peso'), 3),
            'precio_total' => round((float) $rows->sum('precio'), 2),
        ];
    }

    private function buildAdministrativeSummary(Collection $rows, string $departamentoOrigen = ''): array
    {
        $rows = $rows
            ->filter(fn (array $row) => $this->hasValidAdministrativeUser($row))
            ->filter(fn (array $row) => $this->empresaReplacementMatchesOrigin($row, $departamentoOrigen))
            ->values();
        $total = max(0, $rows->count());
        $pesoTotal = round((float) $rows->sum('peso'), 3);
        $costoTotal = round((float) $rows->sum('precio'), 2);
        $ranking = $rows
            ->groupBy(fn (array $row) => trim((string) ($row['usuario'] ?? '-')) ?: 'Sin usuario')
            ->map(function (Collection $items, string $usuario) {
                $firstTs = (int) $items->min('created_at_ts');
                $lastTs = (int) $items->max('created_at_ts');
                $regionales = $items
                    ->pluck('regional')
                    ->flatMap(fn ($value) => explode(',', (string) $value))
                    ->map(fn ($value) => strtoupper(trim($value)))
                    ->filter(fn ($value) => $value !== '' && $value !== '-')
                    ->unique()
                    ->values();
                $servicios = $items
                    ->map(fn (array $row) => trim((string) ($row['servicio'] ?? '')))
                    ->filter(fn ($value) => $value !== '' && $value !== '-')
                    ->countBy()
                    ->sortDesc();
                $serviciosDetalle = $servicios
                    ->map(fn ($cantidad, $servicio) => [
                        'nombre' => (string) $servicio,
                        'cantidad' => (int) $cantidad,
                    ])
                    ->values()
                    ->all();
                $serviciosTexto = $servicios->isNotEmpty()
                    ? $servicios->map(fn ($cantidad, $servicio) => $servicio . ' ' . number_format((int) $cantidad))->values()->implode(' / ')
                    : 'EMS 0';
                $origenes = $items
                    ->pluck('origen_registro')
                    ->map(fn ($value) => strtoupper(trim((string) $value)))
                    ->filter(fn ($value) => $value !== '' && $value !== '-')
                    ->unique()
                    ->values();
                $destinos = $items
                    ->map(fn (array $row) => strtoupper(trim((string) ($row['destino'] ?? ''))))
                    ->filter(fn ($value) => $value !== '' && $value !== '-')
                    ->countBy()
                    ->sortDesc();
                $destinosDetalle = $destinos
                    ->map(fn ($cantidad, $destino) => [
                        'nombre' => (string) $destino,
                        'cantidad' => (int) $cantidad,
                    ])
                    ->values()
                    ->all();
                $destinosTexto = $destinos->isNotEmpty()
                    ? $destinos->map(fn ($cantidad, $destino) => $destino . ' ' . number_format((int) $cantidad))->values()->implode(' / ')
                    : 'SIN DESTINO 0';
                $entregadores = $items
                    ->map(fn (array $row) => trim((string) ($row['entregado_por'] ?? '')))
                    ->filter(fn ($value) => $value !== '')
                    ->countBy()
                    ->sortDesc();
                $entregadoresDetalle = $entregadores
                    ->map(fn ($cantidad, $usuario) => [
                        'nombre' => (string) $usuario,
                        'cantidad' => (int) $cantidad,
                    ])
                    ->values()
                    ->all();
                $entregadoresTexto = $entregadores->isNotEmpty()
                    ? $entregadores->map(fn ($cantidad, $usuario) => $usuario . ' ' . number_format((int) $cantidad))->values()->implode(' / ')
                    : 'Sin entrega registrada 0';

                return [
                    'usuario' => $usuario,
                    'regional' => $regionales->isNotEmpty() ? $regionales->implode(', ') : 'SIN REGIONAL',
                    'servicio' => $serviciosTexto,
                    'servicios' => $serviciosDetalle,
                    'origen' => $origenes->isNotEmpty() ? $origenes->implode(', ') : 'SIN ORIGEN',
                    'destino' => $destinosTexto,
                    'destinos' => $destinosDetalle,
                    'entregado_por' => $entregadoresTexto,
                    'entregadores' => $entregadoresDetalle,
                    'total' => $items->count(),
                    'peso' => round((float) $items->sum('peso'), 3),
                    'precio' => round((float) $items->sum('precio'), 2),
                    'primera_admision' => $firstTs > 0 ? Carbon::createFromTimestamp($firstTs)->format('d/m/Y H:i') : '-',
                    'ultima_admision' => $lastTs > 0 ? Carbon::createFromTimestamp($lastTs)->format('d/m/Y H:i') : '-',
                ];
            })
            ->sortByDesc('total')
            ->values();

        $rankingOrigenes = $this->buildAdministrativeLocationRanking($rows, 'origen_registro', 'SIN ORIGEN');
        $rankingDestinos = $this->buildAdministrativeLocationRanking($rows, 'destino', 'SIN DESTINO');
        $pesoPorModulo = $this->buildAdministrativeModuleWeightSummary($rows);
        $ventanillaPorModulo = $this->buildAdministrativeVentanillaSummary($rows);
        $entregasTop = $this->buildDeliveryTopSummary($rows);

        return [
            'total_admisiones' => $total,
            'usuarios_activos' => $ranking->count(),
            'peso_total' => $pesoTotal,
            'costo_total' => $costoTotal,
            'peso_por_modulo' => $pesoPorModulo,
            'ventanilla_por_modulo' => $ventanillaPorModulo,
            'top_ventanilla' => $ventanillaPorModulo->sortByDesc('total')->first() ?? ['servicio' => 'SIN DATOS', 'total' => 0, 'peso' => 0],
            'entregas_ventanilla_top' => $entregasTop['ventanilla'],
            'entregas_cartero_top' => $entregasTop['cartero'],
            'top_origen' => $rankingOrigenes->first() ?? ['nombre' => 'SIN ORIGEN', 'total' => 0],
            'top_destino' => $rankingDestinos->first() ?? ['nombre' => 'SIN DESTINO', 'total' => 0],
            'ranking_origenes' => $rankingOrigenes,
            'ranking_destinos' => $rankingDestinos,
            'eficiencia_servicios' => $this->buildServiceEfficiencySummary($rows),
            'ranking' => $ranking,
        ];
    }

    private function buildGlobalPorServicioData(Request $request): array
    {
        @set_time_limit(300);
        $request->query->set('limit', 'all');

        $baseRequest = $request->duplicate();
        $baseRequest->query->remove('servicios');
        $baseRequest->query->remove('canales');
        $baseRequest->query->set('limit', 'all');

        $data = $this->buildReportData($baseRequest, 'general', true);
        $selectedServices = $this->resolveServiceFilters($request);
        $selectedReceptionChannels = collect($request->query('canales', []))
            ->map(fn ($value) => $this->normalizeReceptionChannel((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $rows = collect($data['rows'] ?? []);

        if (!empty($selectedServices)) {
            $selectedServiceMap = array_fill_keys($selectedServices, true);
            $rows = $rows
                ->filter(fn (array $row) => isset($selectedServiceMap[$this->normalizeServiceName((string) ($row['servicio'] ?? ''))]))
                ->values();
        }

        if (!empty($selectedReceptionChannels)) {
            $selectedChannelMap = array_fill_keys($selectedReceptionChannels, true);
            $rows = $rows
                ->filter(fn (array $row) => isset($selectedChannelMap[(string) ($row['canal_recepcion'] ?? '')]))
                ->values();
        }

        $serviceOptionMatrix = collect($data['serviceOptions'] ?? [])
            ->map(function (string $service) use ($data) {
                $modules = collect($data['rows'] ?? [])
                    ->filter(fn (array $row) => $this->normalizeServiceName((string) ($row['servicio'] ?? '')) === $service)
                    ->map(fn (array $row) => (string) ($row['modulo_key'] ?? ''))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'service' => $service,
                    'modules' => $modules,
                ];
            })
            ->values()
            ->all();

        $receptionOptionMatrix = collect(['Empresa', 'Admisión', 'Registro interno'])
            ->map(function (string $channel) use ($data) {
                $modules = collect($data['rows'] ?? [])
                    ->filter(fn (array $row) => (string) ($row['canal_recepcion'] ?? '') === $channel)
                    ->map(fn (array $row) => (string) ($row['modulo_key'] ?? ''))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'channel' => $channel,
                    'modules' => $modules,
                ];
            })
            ->filter(fn (array $item) => !empty($item['modules']))
            ->values()
            ->all();

        $serviceRows = $rows
            ->groupBy(fn (array $row) => trim((string) ($row['servicio'] ?? '')) ?: 'SIN SERVICIO')
            ->map(function (Collection $items, string $service) {
                $ordered = $items->sortByDesc(fn (array $row) => $row['created_at_ts'] ?? 0)->values();
                $modules = $items
                    ->map(fn (array $row) => (string) ($row['modulo_label'] ?? '-'))
                    ->filter()
                    ->unique()
                    ->values();
                $canales = $items
                    ->map(fn (array $row) => trim((string) ($row['canal_recepcion'] ?? 'Sin clasificar')) ?: 'Sin clasificar')
                    ->countBy()
                    ->sortDesc();

                return [
                    'servicio' => $service,
                    'cantidad' => $items->count(),
                    'entregados' => $items->where('is_entregado', true)->count(),
                    'no_entregados' => $items->where('is_entregado', false)->where('is_cancelado', false)->count(),
                    'peso' => round((float) $items->sum('peso'), 3),
                    'precio' => round((float) $items->sum('precio'), 2),
                    'modulos' => $modules->all(),
                    'modulos_texto' => $modules->implode(', '),
                    'canales' => $canales->all(),
                    'canales_texto' => $canales->map(fn ($cantidad, $canal) => $canal . ': ' . number_format((int) $cantidad))->values()->implode(' / '),
                    'empresa_count' => (int) ($canales['Empresa'] ?? 0),
                    'admision_count' => (int) ($canales['Admisión'] ?? 0),
                    'interno_count' => (int) ($canales['Registro interno'] ?? 0),
                    'ultimo_registro' => (string) ($ordered->first()['created_at'] ?? '-'),
                ];
            })
            ->sortByDesc('precio')
            ->values();

        $data['scopeLabel'] = 'Global por servicio';
        $data['globalPorServicioMode'] = true;
        $data['serviceRows'] = $serviceRows;
        $data['serviceOptions'] = array_column($serviceOptionMatrix, 'service');
        $data['serviceOptionMatrix'] = $serviceOptionMatrix;
        $data['selectedServices'] = $selectedServices;
        $data['receptionChannelOptions'] = array_column($receptionOptionMatrix, 'channel');
        $data['receptionOptionMatrix'] = $receptionOptionMatrix;
        $data['selectedReceptionChannels'] = $selectedReceptionChannels;
        $data['serviceTotals'] = [
            'servicios' => $serviceRows->count(),
            'registros' => $rows->count(),
            'entregados' => $rows->where('is_entregado', true)->count(),
            'no_entregados' => $rows->where('is_entregado', false)->where('is_cancelado', false)->count(),
            'empresa_count' => $rows->where('canal_recepcion', 'Empresa')->count(),
            'admision_count' => $rows->where('canal_recepcion', 'Admisión')->count(),
            'interno_count' => $rows->where('canal_recepcion', 'Registro interno')->count(),
            'peso_total' => round((float) $rows->sum('peso'), 3),
            'precio_total' => round((float) $rows->sum('precio'), 2),
        ];

        return $data;
    }

    private function normalizeReceptionChannel(string $value): string
    {
        $value = strtolower(trim($value));

        return match ($value) {
            'empresa' => 'Empresa',
            'admision', 'admisión' => 'Admisión',
            'registro interno' => 'Registro interno',
            default => '',
        };
    }

    private function buildAdministrativeModuleWeightSummary(Collection $rows): Collection
    {
        return collect(self::MODULES)
            ->map(function (array $module, string $moduleKey) use ($rows) {
                $moduleRows = $rows->where('modulo_key', $moduleKey);

                return [
                    'key' => $moduleKey,
                    'servicio' => $module['label'],
                    'total' => $moduleRows->count(),
                    'peso' => round((float) $moduleRows->sum('peso'), 3),
                ];
            })
            ->values();
    }

    private function buildAdministrativeVentanillaSummary(Collection $rows): Collection
    {
        $ventanillaRows = $rows
            ->filter(fn (array $row) => $this->isAdministrativeVentanillaRow($row))
            ->values();

        return collect(self::MODULES)
            ->map(function (array $module, string $moduleKey) use ($ventanillaRows) {
                $moduleRows = $ventanillaRows->where('modulo_key', $moduleKey);

                return [
                    'key' => $moduleKey,
                    'servicio' => $module['label'],
                    'total' => $moduleRows->count(),
                    'peso' => round((float) $moduleRows->sum('peso'), 3),
                ];
            })
            ->values();
    }

    private function isAdministrativeVentanillaRow(array $row): bool
    {
        $estado = $this->normalizeDestino((string) ($row['estado'] ?? ''));
        $moduleKey = (string) ($row['modulo_key'] ?? '');

        if (str_contains($estado, 'VENTANILLA')) {
            return true;
        }

        return $moduleKey === 'ordi' && $estado === 'RECIBIDO';
    }

    private function buildDeliveryTopSummary(Collection $rows): array
    {
        $deliveredRows = $rows
            ->filter(fn (array $row) => (bool) ($row['is_entregado'] ?? false))
            ->filter(fn (array $row) => (int) ($row['entregado_por_id'] ?? 0) > 0)
            ->filter(fn (array $row) => trim((string) ($row['entregado_por'] ?? '')) !== '')
            ->filter(fn (array $row) => trim((string) ($row['entregado_por'] ?? '')) !== 'Sin entrega registrada')
            ->values();

        return [
            'ventanilla' => $this->buildDeliveryTopForChannel(
                $deliveredRows->reject(fn (array $row) => $this->isCarteroRoleNames((string) ($row['entregado_por_roles'] ?? '')))->values()
            ),
            'cartero' => $this->buildDeliveryTopForChannel(
                $deliveredRows->filter(fn (array $row) => $this->isCarteroRoleNames((string) ($row['entregado_por_roles'] ?? '')))->values()
            ),
        ];
    }

    private function buildDeliveryTopForChannel(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row) => trim((string) ($row['entregado_por'] ?? 'SIN DATO')) ?: 'SIN DATO')
            ->map(function (Collection $items, string $usuario) {
                $servicios = $items
                    ->map(fn (array $row) => trim((string) ($row['servicio'] ?? '')))
                    ->filter(fn ($value) => $value !== '' && $value !== '-')
                    ->countBy()
                    ->sortDesc();

                return [
                    'usuario' => $usuario,
                    'total' => $items->count(),
                    'peso' => round((float) $items->sum('peso'), 3),
                    'servicio' => $servicios->isNotEmpty()
                        ? $servicios->map(fn ($cantidad, $servicio) => $servicio . ' ' . number_format((int) $cantidad))->values()->implode(' / ')
                        : 'SIN SERVICIO 0',
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    private function isCarteroRoleNames(string $roleNames): bool
    {
        return str_contains(mb_strtolower(trim($roleNames)), 'cartero');
    }

    private function buildAdministrativeMalencaminadosSummary(Request $request, string $departamentoOrigen = '', string $departamentoDestino = ''): array
    {
        [$from, $to] = $this->resolveDateRange($request);
        $monthDateRanges = $this->buildMonthDateRanges($this->resolveSelectedMonthFilters($request));
        if (!empty($monthDateRanges)) {
            $from = null;
            $to = null;
        }

        $search = trim((string) $request->query('q', ''));
        $departamentoExpr = "coalesce(
            nullif(trim(upper(m.departamento_origen)), ''),
            nullif(trim(upper(pe.origen)), ''),
            nullif(trim(upper(pc.origen)), ''),
            'SIN ORIGEN'
        )";
        $tipoExpr = "case
            when m.paquetes_ems_id is not null then 'ems'
            when m.paquetes_contrato_id is not null then 'contrato'
            when m.paquetes_certi_id is not null then 'certi'
            when m.paquetes_ordi_id is not null then 'ordi'
            else '-'
        end";

        $query = DB::table('malencaminados as m')
            ->leftJoin('paquetes_ems as pe', 'pe.id', '=', 'm.paquetes_ems_id')
            ->leftJoin('paquetes_contrato as pc', 'pc.id', '=', 'm.paquetes_contrato_id')
            ->leftJoin('paquetes_certi as pce', 'pce.id', '=', 'm.paquetes_certi_id')
            ->leftJoin('paquetes_ordi as po', 'po.id', '=', 'm.paquetes_ordi_id')
            ->selectRaw("
                m.id,
                m.codigo,
                {$departamentoExpr} as departamento_origen,
                coalesce(nullif(trim(upper(m.destino_anterior)), ''), '-') as destino_anterior,
                coalesce(nullif(trim(upper(m.destino_nuevo)), ''), '-') as destino_nuevo,
                coalesce(m.malencaminamiento, 1) as malencaminamiento,
                coalesce(m.observacion, '') as observacion,
                m.created_at,
                {$tipoExpr} as modulo_key
            ");

        $this->applyDateFilter($query, 'm.created_at', $from, $to, $monthDateRanges);

        if ($search !== '') {
            $like = '%' . mb_strtolower($search) . '%';
            $query->where(function (Builder $sub) use ($like) {
                $sub->whereRaw("LOWER(COALESCE(CAST(m.codigo AS TEXT), '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(CAST(m.destino_anterior AS TEXT), '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(CAST(m.destino_nuevo AS TEXT), '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(CAST(m.observacion AS TEXT), '')) LIKE ?", [$like]);
            });
        }

        $rows = $query
            ->orderByDesc('m.created_at')
            ->orderByDesc('m.id')
            ->get()
            ->map(function (object $row) {
                $createdAt = $this->safeCarbon($row->created_at ?? null);

                return [
                    'id' => (int) ($row->id ?? 0),
                    'codigo' => (string) ($row->codigo ?? '-'),
                    'modulo_key' => (string) ($row->modulo_key ?? '-'),
                    'servicio' => self::MODULES[$row->modulo_key ?? '']['label'] ?? strtoupper((string) ($row->modulo_key ?? '-')),
                    'departamento_origen' => (string) ($row->departamento_origen ?? 'SIN ORIGEN'),
                    'destino_anterior' => (string) ($row->destino_anterior ?? '-'),
                    'destino_nuevo' => (string) ($row->destino_nuevo ?? '-'),
                    'malencaminamiento' => (int) ($row->malencaminamiento ?? 1),
                    'observacion' => (string) ($row->observacion ?? ''),
                    'created_at' => $createdAt?->format('d/m/Y H:i') ?? '-',
                    'created_at_ts' => $createdAt?->timestamp ?? 0,
                ];
            })
            ->filter(fn (array $row) => $this->matchesDepartamentoValue((string) ($row['departamento_origen'] ?? ''), $departamentoOrigen))
            ->filter(fn (array $row) => $this->matchesDepartamentoValue((string) ($row['destino_nuevo'] ?? ''), $departamentoDestino))
            ->values();

        $porModulo = collect(self::MODULES)
            ->map(function (array $module, string $moduleKey) use ($rows) {
                $moduleRows = $rows->where('modulo_key', $moduleKey);

                return [
                    'key' => $moduleKey,
                    'servicio' => $module['label'],
                    'total' => $moduleRows->count(),
                    'malencaminamientos' => (int) $moduleRows->sum('malencaminamiento'),
                ];
            })
            ->values();

        return [
            'total' => $rows->count(),
            'total_malencaminamientos' => (int) $rows->sum('malencaminamiento'),
            'por_modulo' => $porModulo,
            'ultimos' => $rows->take(10)->values(),
        ];
    }

    private function matchesDepartamentoValue(string $value, string $departamento): bool
    {
        if ($departamento === '') {
            return true;
        }

        $canonical = $this->canonicalDepartamentoName($value);
        if ($canonical === '') {
            return false;
        }

        return $canonical === $departamento;
    }

    private function hasValidAdministrativeUser(array $row): bool
    {
        $usuarioId = (int) ($row['usuario_id'] ?? 0);
        $usuario = trim((string) ($row['usuario'] ?? ''));

        if ($usuarioId <= 0 || $usuario === '' || $usuario === '-') {
            return false;
        }

        if (! (bool) ($row['usuario_activo'] ?? false)) {
            return false;
        }

        return ! $this->isEmpresaRoleNames((string) ($row['usuario_roles'] ?? ''));
    }

    private function empresaReplacementMatchesOrigin(array $row, string $departamentoOrigen = ''): bool
    {
        if (! (bool) ($row['usuario_empresa_gestora'] ?? false)) {
            return true;
        }

        $origen = $departamentoOrigen !== ''
            ? $departamentoOrigen
            : (string) ($row['origen_registro'] ?? $row['origen'] ?? '');
        $origenCanonico = $this->canonicalDepartamentoName($origen);

        if ($origenCanonico === '') {
            return true;
        }

        $regionales = collect(explode(',', (string) ($row['regional'] ?? '')))
            ->map(fn ($regional) => $this->canonicalDepartamentoName($regional))
            ->filter(fn ($regional) => $regional !== '')
            ->unique()
            ->values();

        return $regionales->contains($origenCanonico);
    }

    private function deliveredEventSubquery(string $eventTable): Builder
    {
        return $this->eventUserSubquery($eventTable, self::EVENTO_ENTREGADO_ID);
    }

    private function eventUserSubquery(string $eventTable, int $eventId): Builder
    {
        return DB::table($eventTable)
            ->select('codigo', DB::raw('MAX(user_id) as user_id'), DB::raw('MIN(created_at) as delivered_at'))
            ->where('evento_id', $eventId)
            ->whereNotNull('user_id')
            ->groupBy('codigo');
    }

    private function buildServiceEfficiencySummary(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (array $row) => (bool) ($row['is_entregado'] ?? false))
            ->filter(fn (array $row) => (float) ($row['delivery_hours'] ?? 0) > 0)
            ->groupBy(fn (array $row) => trim((string) ($row['servicio'] ?? '-')) ?: 'SIN SERVICIO')
            ->map(function (Collection $items, string $servicio) {
                $avgHours = round((float) $items->avg('delivery_hours'), 2);
                $minHours = round((float) $items->min('delivery_hours'), 2);
                $maxHours = round((float) $items->max('delivery_hours'), 2);

                return [
                    'servicio' => $servicio,
                    'total' => $items->count(),
                    'promedio_horas' => $avgHours,
                    'promedio' => $this->formatDurationHours($avgHours),
                    'mejor_tiempo' => $this->formatDurationHours($minHours),
                    'mayor_tiempo' => $this->formatDurationHours($maxHours),
                    'peso' => round((float) $items->sum('peso'), 3),
                    'costo' => round((float) $items->sum('precio'), 2),
                ];
            })
            ->sort(function (array $a, array $b) {
                $avgCompare = ($a['promedio_horas'] ?? 0) <=> ($b['promedio_horas'] ?? 0);
                if ($avgCompare !== 0) {
                    return $avgCompare;
                }

                return ($b['total'] ?? 0) <=> ($a['total'] ?? 0);
            })
            ->values();
    }

    private function formatDurationHours(float $hours): string
    {
        if ($hours <= 0) {
            return '-';
        }

        if ($hours < 24) {
            return number_format($hours, 1) . ' h';
        }

        $days = floor($hours / 24);
        $remainingHours = round($hours - ($days * 24), 1);

        return number_format($days, 0) . ' d ' . number_format($remainingHours, 1) . ' h';
    }

    private function roleNamesSubquery(): Builder
    {
        $roleNamesAggregate = DB::connection()->getDriverName() === 'pgsql'
            ? "STRING_AGG(LOWER(r.name), ',') as role_names"
            : "GROUP_CONCAT(LOWER(r.name)) as role_names";

        return DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->select('mhr.model_id', DB::raw($roleNamesAggregate))
            ->where('mhr.model_type', 'App\\Models\\User')
            ->groupBy('mhr.model_id');
    }

    private function regionalesValueExpression(string $alias): string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            ? "coalesce({$alias}.regionales::text, '')"
            : "coalesce({$alias}.regionales, '')";
    }

    private function buildAdministrativeLocationSummary(Collection $rows, string $key, string $fallback): ?array
    {
        $ranking = $rows
            ->map(fn (array $row) => strtoupper(trim((string) ($row[$key] ?? ''))))
            ->filter(fn ($value) => $value !== '' && $value !== '-')
            ->countBy()
            ->sortDesc();

        if ($ranking->isEmpty()) {
            return [
                'nombre' => $fallback,
                'total' => 0,
            ];
        }

        return [
            'nombre' => (string) $ranking->keys()->first(),
            'total' => (int) $ranking->first(),
        ];
    }

    private function buildAdministrativeLocationRanking(Collection $rows, string $key, string $fallback, bool $splitComma = false): Collection
    {
        $values = $rows->flatMap(function (array $row) use ($key, $splitComma) {
            $value = (string) ($row[$key] ?? '');
            $items = $splitComma ? explode(',', $value) : [$value];

            return collect($items)
                ->map(fn ($item) => $this->canonicalDepartamentoName((string) $item))
                ->filter(fn ($item) => $item !== '' && $item !== '-');
        });

        $ranking = $values
            ->countBy()
            ->sortDesc()
            ->map(fn ($cantidad, $nombre) => [
                'nombre' => (string) $nombre,
                'total' => (int) $cantidad,
            ])
            ->values();

        if ($ranking->isEmpty()) {
            return collect([[
                'nombre' => $fallback,
                'total' => 0,
            ]]);
        }

        return $ranking;
    }

    private function canonicalDepartamentoName(string $value): string
    {
        $value = $this->normalizeDestino($value);
        if ($this->isInvalidLocationValue($value)) {
            return '';
        }

        foreach ($this->departamentoAliasMap() as $departamento => $aliases) {
            $normalizedAliases = collect($aliases)
                ->push($departamento)
                ->map(fn ($alias) => $this->normalizeDestino((string) $alias));

            if ($normalizedAliases->contains($value)) {
                return $departamento;
            }

            if ($normalizedAliases->contains(fn ($alias) => $alias !== '' && str_contains($value, $alias))) {
                return $departamento;
            }
        }

        foreach ($this->localidadDepartamentoAliasMap() as $departamento => $aliases) {
            foreach ($aliases as $alias) {
                $alias = $this->normalizeDestino((string) $alias);
                if ($alias !== '' && str_contains($value, $alias)) {
                    return $departamento;
                }
            }
        }

        return '';
    }

    private function isInvalidLocationValue(string $value): bool
    {
        return in_array($value, ['', '-', '.', 'SIN DESTINO', 'SIN ORIGEN', 'ENTREGA LOCAL'], true);
    }

    private function localidadDepartamentoAliasMap(): array
    {
        return [
            'LA PAZ' => [
                'EL ALTO',
                'VIACHA',
                'ACHOCALLA',
                'CARANAVI',
                'COPACABANA',
            ],
            'COCHABAMBA' => [
                'QUILLACOLLO',
                'SACABA',
                'TIQUIPAYA',
                'VINTO',
                'COLCAPIRHUA',
                'CLIZA',
            ],
            'BENI' => [
                'RIBERALTA',
                'RURRENABAQUE',
                'MAGDALENA',
                'SANTA ANA',
                'SAN BORJA',
                'GUAYARAMERIN',
                'REYES',
                'SAN IGNACIO DE MOXOS',
            ],
            'SANTA CRUZ' => [
                'MONTERO',
                'ANDRES IBANEZ',
                'ANDRÉS IBÁÑEZ',
                'ANDRÉS IBAÑEZ',
                'ANDRES IBÁÑEZ',
                'WARNES',
                'COTOCA',
                'LA GUARDIA',
                'EL TORNO',
                'YAPACANI',
                'CAMIRI',
                'VALLEGRANDE',
            ],
            'ORURO' => [
                'HUANUNI',
                'CHALLAPATA',
            ],
            'POTOSI' => [
                'POTOSÍ',
                'UYUNI',
                'VILLAZON',
                'VILLAZÓN',
                'TUPIZA',
                'LLALLAGUA',
            ],
            'TARIJA' => [
                'YACUIBA',
                'BERMEJO',
                'VILLA MONTES',
                'VILLAMONTES',
            ],
            'CHUQUISACA' => [
                'MONTEAGUDO',
                'CAMARGO',
                'VILLA SERRANO',
            ],
            'PANDO' => [
                'PORVENIR',
                'PUERTO RICO',
            ],
        ];
    }

    private function firstDepartamentoFromList(string $value): string
    {
        return collect(explode(',', $value))
            ->map(fn ($item) => $this->canonicalDepartamentoName((string) $item))
            ->filter(fn ($item) => $item !== '' && $item !== '-')
            ->first() ?? '';
    }

    private function isAdmissionRoleNames(string $roleNames): bool
    {
        $normalized = mb_strtolower(trim($roleNames));
        if ($normalized === '') {
            return false;
        }

        foreach (['admision', 'admisiones'] as $admissionRole) {
            if (str_contains($normalized, $admissionRole)) {
                return true;
            }
        }

        return false;
    }

    private function isEmpresaRoleNames(string $roleNames): bool
    {
        return str_contains(mb_strtolower(trim($roleNames)), 'empresa');
    }

    private function resolveRegionalText(mixed $ciudad, mixed $regionales): string
    {
        $items = [];

        if (is_string($regionales) && trim($regionales) !== '') {
            $decoded = json_decode($regionales, true);
            if (is_array($decoded)) {
                $items = array_merge($items, $decoded);
            }
        } elseif (is_array($regionales)) {
            $items = array_merge($items, $regionales);
        }

        if ($items === [] && trim((string) $ciudad) !== '') {
            $items[] = (string) $ciudad;
        }

        $items = collect($items)
            ->map(fn ($regional) => strtoupper(trim((string) $regional)))
            ->filter(fn ($regional) => $regional !== '' && $regional !== '-')
            ->unique()
            ->values();

        return $items->isNotEmpty() ? $items->implode(', ') : 'SIN REGIONAL';
    }

    private function paginateCollection(Collection $rows, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $items = $rows->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            ['path' => url()->current(), 'query' => $request->query()]
        );
    }

    private function resolveSelectedModules(string $scope, Request $request): array
    {
        if ($scope !== 'general') {
            return [$scope];
        }

        $requested = $request->query('modules', array_keys(self::MODULES));
        $requested = is_array($requested) ? $requested : [$requested];
        $valid = array_values(array_filter(array_map('strtolower', $requested), fn ($k) => isset(self::MODULES[$k])));

        return empty($valid) ? array_keys(self::MODULES) : $valid;
    }

    private function filterRowsWithoutCanceled(Collection $rows): Collection
    {
        return $rows
            ->reject(fn (array $row) => (bool) ($row['is_cancelado'] ?? false))
            ->values();
    }

    private function filterRowsByState(
        Collection $rows,
        array $statuses
    ): Collection {
        $normalizedStatuses = array_values(array_unique(array_filter(array_map('strtolower', $statuses))));
        if (empty($normalizedStatuses)) {
            $normalizedStatuses = ['entregado', 'pendiente', 'rezago'];
        }

        $selectedMap = array_fill_keys($normalizedStatuses, true);

        return $rows->filter(function (array $row) use ($selectedMap) {
            $isEntregado = (bool) ($row['is_entregado'] ?? false);
            $bucket = (string) ($row['situacion_bucket'] ?? '');

            if (isset($selectedMap['entregado']) && $isEntregado) {
                return true;
            }

            $isCancelado = (bool) ($row['is_cancelado'] ?? false);

            if (isset($selectedMap['pendiente']) && !$isEntregado && !$isCancelado) {
                return true;
            }

            if (isset($selectedMap['rezago']) && $bucket === 'rezago') {
                return true;
            }

            return false;
        })->values();
    }

    private function filterRowsByDepartamentoOrigen(Collection $rows, string $departamentoOrigen): Collection
    {
        if ($departamentoOrigen === '') {
            return $rows->values();
        }

        $aliases = $this->departamentoAliasMap()[$departamentoOrigen] ?? [$departamentoOrigen];
        $aliases = collect($aliases)
            ->push($departamentoOrigen)
            ->map(fn ($value) => $this->normalizeDestino((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($aliases->isEmpty()) {
            return $rows->values();
        }

        return $rows->filter(function (array $row) use ($aliases) {
            $origenes = collect(explode(',', (string) ($row['origen_registro'] ?? '')))
                ->map(fn ($value) => $this->normalizeDestino($value))
                ->filter(fn ($value) => $value !== '' && $value !== '-');

            return $origenes->contains(fn ($origen) => $aliases->contains($this->canonicalDepartamentoName($origen)));
        })->values();
    }

    private function resolveDateRange(Request $request): array
    {
        $range = strtolower(trim((string) $request->query('range', 'all')));
        if ($range === 'all') {
            return [null, null, 'all'];
        }

        $from = $this->safeCarbon((string) $request->query('from', ''))?->startOfDay();
        $to = $this->safeCarbon((string) $request->query('to', ''))?->endOfDay();

        if ($from && !$to) {
            $to = $from->copy()->endOfDay();
        } elseif ($to && !$from) {
            $from = $to->copy()->startOfDay();
        }

        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        if ($from || $to) {
            return [$from, $to, 'custom'];
        }

        $now = now();
        if ($range === 'today') {
            return [$now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today'];
        }
        if ($range === '7d') {
            return [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), '7d'];
        }
        if ($range === '30d') {
            return [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), '30d'];
        }
        if ($range === 'month') {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'month'];
        }

        return [null, null, 'all'];
    }

    private function resolveSelectedMonthFilters(Request $request): array
    {
        $months = $request->query('months', []);
        $months = is_array($months) ? $months : [$months];
        $currentYear = now()->year;

        return collect($months)
            ->map(function ($value) use ($currentYear) {
                $value = trim((string) $value);
                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
                    return $value;
                }

                if (preg_match('/^(0?[1-9]|1[0-2])$/', $value)) {
                    return sprintf('%04d-%02d', $currentYear, (int) $value);
                }

                return null;
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function buildMonthDateRanges(array $selectedMonths): array
    {
        return collect($selectedMonths)
            ->map(function (string $month) {
                try {
                    $start = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();

                    return [
                        'from' => $start->copy()->startOfDay(),
                        'to' => $start->copy()->endOfMonth()->endOfDay(),
                    ];
                } catch (\Throwable $e) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    private function monthFilterOptions(): array
    {
        $year = now()->year;

        return collect(range(1, 12))
            ->map(fn (int $month) => [
                'value' => sprintf('%04d-%02d', $year, $month),
                'label' => $this->monthFilterLabel(sprintf('%04d-%02d', $year, $month)),
            ])
            ->all();
    }

    private function monthFilterLabel(string $month): string
    {
        $labels = [
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

        if (!preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month, $matches)) {
            return $month;
        }

        return ($labels[(int) $matches[2]] ?? $month) . ' ' . $matches[1];
    }

    private function resolveStatusFilters(Request $request): array
    {
        $requested = $request->query('statuses');
        if ($requested === null) {
            $legacy = strtolower(trim((string) $request->query('status', '')));
            $legacy = $legacy === 'pendientes' ? 'pendiente' : $legacy;
            if ($legacy !== '') {
                $requested = [$legacy];
            }
        }

        if ($requested === null) {
            return ['entregado', 'pendiente', 'rezago'];
        }

        $requested = is_array($requested) ? $requested : [$requested];
        $requested = array_values(array_unique(array_filter(array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $requested
        ))));

        $allowed = ['entregado', 'pendiente', 'no_entregado', 'rezago'];
        $valid = array_values(array_filter($requested, static fn ($status) => in_array($status, $allowed, true)));
        $valid = array_map(static fn ($status) => $status === 'no_entregado' ? 'pendiente' : $status, $valid);
        $valid = array_values(array_unique($valid));

        if (empty($valid)) {
            return ['entregado', 'pendiente', 'rezago'];
        }

        return $valid;
    }

    private function resolveServiceFilters(Request $request): array
    {
        $requested = $request->query('servicios', []);
        $requested = is_array($requested) ? $requested : [$requested];

        return collect($requested)
            ->map(fn ($value) => $this->normalizeServiceName((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function serviceOptionsFromRows(Collection $rows): array
    {
        return $rows
            ->map(fn (array $row) => $this->normalizeServiceName((string) ($row['servicio'] ?? '')))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function filterRowsByService(Collection $rows, array $selectedServices): Collection
    {
        if (empty($selectedServices)) {
            return $rows->values();
        }

        $selectedMap = array_fill_keys($selectedServices, true);

        return $rows
            ->filter(fn (array $row) => isset($selectedMap[$this->normalizeServiceName((string) ($row['servicio'] ?? ''))]))
            ->values();
    }

    private function normalizeServiceName(string $value): string
    {
        $value = strtoupper(preg_replace('/\s+/', ' ', trim($value)) ?: '');

        if ($value === '') {
            return '';
        }

        if (str_contains($value, 'ORDINARI')) {
            return 'ORDINARIOS';
        }

        if (str_contains($value, 'CERTIFIC')) {
            return 'CERTIFICADOS';
        }

        return $value;
    }

    private function resolveEstadoIds(Request $request): array
    {
        $ids = $request->query('estado_ids', []);
        $ids = is_array($ids) ? $ids : [$ids];
        return array_values(array_filter(array_map('intval', $ids), fn ($v) => $v > 0));
    }

    private function resolveLimit(Request $request): ?int
    {
        $value = strtolower((string) $request->query('limit', '200'));
        if ($value === 'all') {
            return null;
        }
        $allowed = [50, 100, 200, 500, 1000];
        $intValue = (int) $value;
        return in_array($intValue, $allowed, true) ? $intValue : 200;
    }

    private function resolvePerPage(Request $request): int
    {
        $allowed = [25, 50, 100];
        $value = (int) $request->query('per_page', 50);
        return in_array($value, $allowed, true) ? $value : 50;
    }

    private function applyStatusFilter(Builder $query, string $stateColumn, string $status, ?int $estadoEntregadoId): void
    {
        if (!$estadoEntregadoId || $status === 'all') {
            return;
        }

        if ($status === 'entregado') {
            $query->where($stateColumn, $estadoEntregadoId);
            return;
        }

        $query->where(function (Builder $sub) use ($stateColumn, $estadoEntregadoId) {
            $sub->whereNull($stateColumn)->orWhere($stateColumn, '<>', $estadoEntregadoId);
        });
    }

    private function applyDateFilter(Builder $query, string $column, ?Carbon $from, ?Carbon $to, array $monthDateRanges = []): void
    {
        if (!empty($monthDateRanges)) {
            $query->where(function (Builder $sub) use ($column, $monthDateRanges) {
                foreach ($monthDateRanges as $range) {
                    if (($range['from'] ?? null) instanceof Carbon && ($range['to'] ?? null) instanceof Carbon) {
                        $sub->orWhereBetween($column, [$range['from'], $range['to']]);
                    }
                }
            });
            return;
        }

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

    private function applyDepartamentoFilter(Builder $query, string $moduleKey, string $departamento, string $tipo): void
    {
        if ($departamento === '') {
            return;
        }

        $columnKey = $tipo === 'origen' ? 'origen_col' : 'destino_col';
        $column = self::MODULES[$moduleKey][$columnKey] ?? null;
        if (!$column) {
            $query->whereRaw('1 = 0');
            return;
        }

        $aliases = $this->departamentoAliasMap()[$departamento] ?? [$departamento];
        $aliases = collect($aliases)
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($aliases)) {
            return;
        }

        $query->whereIn(DB::raw('trim(upper(t.' . $column . '))'), $aliases);
    }

    private function applySearchFilter(Builder $query, string $moduleKey, string $search): void
    {
        $search = trim($search);
        if ($search === '') {
            return;
        }

        $like = '%' . strtolower($search) . '%';
        $columns = $this->searchColumnsForModule($moduleKey);

        $query->where(function (Builder $sub) use ($columns, $like) {
            foreach ($columns as $column) {
                $sub->orWhereRaw("LOWER(COALESCE(CAST($column AS TEXT), '')) LIKE ?", [$like]);
            }
        });
    }

    private function searchColumnsForModule(string $moduleKey): array
    {
        return match ($moduleKey) {
            'contrato' => [
                't.codigo',
                'e.nombre_estado',
                't.origen',
                't.destino',
                't.nombre_r',
                't.nombre_d',
                'emp.nombre',
                'u.name',
            ],
            'ems' => [
                't.codigo',
                'e.nombre_estado',
                't.origen',
                't.ciudad',
                't.nombre_remitente',
                't.nombre_destinatario',
                'u.name',
            ],
            'certi' => [
                't.codigo',
                'e.nombre_estado',
                't.cuidad',
                't.destinatario',
            ],
            default => [
                't.codigo',
                'e.nombre_estado',
                't.ciudad',
                't.destinatario',
            ],
        };
    }

    private function resolveEstadoEntregadoId(): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function resolveEstadoCanceladoId(): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO'])
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function resolveDepartamentoFiltro(Request $request, string $queryKey = 'departamento'): string
    {
        $value = strtoupper(trim((string) $request->query($queryKey, '')));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $aliases = $this->departamentoAliasMap();

        if (isset($aliases[$value])) {
            return $value;
        }

        foreach ($aliases as $departamento => $departamentoAliases) {
            $normalizedAliases = array_map(
                fn ($alias) => strtoupper(trim((string) $alias)),
                $departamentoAliases
            );

            if (in_array($value, $normalizedAliases, true)) {
                return $departamento;
            }
        }

        return '';
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

    private function resolveSituacionBucket(?Carbon $inicio, Carbon $fin, int $greenDays, int $yellowDays): string
    {
        if (!$inicio || $fin->lessThan($inicio)) {
            return 'sin_datos';
        }

        $hours = $inicio->diffInHours($fin);
        if ($hours <= ($greenDays * 24)) {
            return 'correcto';
        }
        if ($hours <= ($yellowDays * 24)) {
            return 'retraso';
        }

        return 'rezago';
    }

    private function resolveEmsThresholdDays(string $destino, bool $esProvincia): array
    {
        $baseDestino = $this->resolveEmsBaseDestino($destino);
        $green = in_array($baseDestino, self::DESTINOS_LARGA_DISTANCIA, true) ? 2 : 1;
        $yellow = $green + 1;
        if ($esProvincia) {
            $green++;
            $yellow++;
        }

        return ['green' => $green, 'yellow' => $yellow];
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
                str_starts_with($normalized, $base . ' ')
                || str_starts_with($normalized, $base . '-')
                || str_starts_with($normalized, $base . ',')
                || str_starts_with($normalized, $base . '/')
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

    private function normalizeScope(string $scope): string
    {
        $scope = strtolower(trim($scope));
        return in_array($scope, self::SCOPES, true) ? $scope : 'general';
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'contrato' => 'Reporte Contratos',
            'ems' => 'Reporte EMS',
            'certi' => 'Reporte Certificados',
            'ordi' => 'Reporte Ordinarios',
            default => 'Reporte General',
        };
    }

    private function safeCarbon(?string $value): ?Carbon
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
}


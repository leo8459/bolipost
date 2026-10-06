<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportesController extends Controller
{
    private array $estadoIdCache = [];

    private const EVENTO_ENTREGADO_ID = 316;
    private const EVENTO_EMS_SOLICITUD_ID = 295;
    private const EVENTO_CONTRATO_RECOGIDO_ID = 295;
    private const CERTI_ORDI_GREEN_DAYS = 7;
    private const CERTI_ORDI_YELLOW_DAYS = 15;
    private const DESTINOS_LARGA_DISTANCIA = ['SANTA CRUZ', 'TRINIDAD', 'TARIJA'];
    private const DESTINOS_BASE = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
    private const DESTINOS_CAPITALES = ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
    private const COMMERCIAL_LINES = [
        'PAQUETES EMS',
        'PAQUETES CONTRATOS',
        'PAQUETES CERTI',
        'PAQUETES ORDI',
        'DELIVERY EXPRESS',
    ];

    private const MODULES = [
        'contrato' => ['label' => 'CONTRATOS', 'table' => 'paquetes_contrato', 'state_col' => 'estados_id', 'origen_col' => 'origen', 'destino_col' => 'destino'],
        'ems' => ['label' => 'EMS', 'table' => 'paquetes_ems', 'state_col' => 'estado_id', 'origen_col' => 'origen', 'destino_col' => 'ciudad'],
        'certi' => ['label' => 'CERTIFICADOS', 'table' => 'paquetes_certi', 'state_col' => 'fk_estado', 'origen_col' => null, 'destino_col' => 'cuidad'],
        'ordi' => ['label' => 'ORDINARIOS', 'table' => 'paquetes_ordi', 'state_col' => 'fk_estado', 'origen_col' => null, 'destino_col' => 'ciudad'],
    ];

    public function lifetimeMovements(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'service' => ['nullable', 'in:all,contrato,ems'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $search = trim((string) ($filters['q'] ?? ''));
        $service = $filters['service'] ?? 'all';
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;
        $queries = [];

        if (in_array($service, ['all', 'contrato'], true)) {
            $queries[] = $this->lifetimeMovementQuery(
                'Contratos', 'eventos_contrato', 'paquetes_contrato', 'estados_id', 'destino', $search, $from, $to
            );
        }
        if (in_array($service, ['all', 'ems'], true)) {
            $queries[] = $this->lifetimeMovementQuery(
                'EMS', 'eventos_ems', 'paquetes_ems', 'estado_id', 'ciudad', $search, $from, $to
            );
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $base = DB::query()->fromSub($union, 'movement_history');
        $summary = (clone $base)
            ->select('service')
            ->selectRaw('COUNT(*) as movements')
            ->selectRaw('COUNT(DISTINCT code) as packages')
            ->groupBy('service')
            ->get()
            ->keyBy('service');
        $movements = (clone $base)
            ->orderByDesc('moved_at')
            ->orderByDesc('movement_id')
            ->paginate(100)
            ->withQueryString();

        return view('reportes.lifetime-movements', [
            'movements' => $movements,
            'summary' => $summary,
            'search' => $search,
            'service' => $service,
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function enviosOficiales(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $origen = trim((string) $request->query('origen', ''));
        $destino = trim((string) $request->query('destino', ''));

        $baseQuery = DB::table('paquetes_ems as ems')
            ->leftJoin('paquetes_ems_formulario as formulario', 'formulario.paquete_ems_id', '=', 'ems.id')
            ->leftJoin('estados as estado', 'estado.id', '=', 'ems.estado_id')
            ->leftJoin('users as usuario', 'usuario.id', '=', 'ems.user_id')
            ->whereRaw("trim(upper(coalesce(formulario.tipo_correspondencia, ems.tipo_correspondencia, ''))) = 'OFICIAL'");

        $origenOptions = (clone $baseQuery)
            ->selectRaw("distinct trim(coalesce(ems.origen, '')) as origen")
            ->whereRaw("trim(coalesce(ems.origen, '')) <> ''")
            ->orderBy('origen')
            ->pluck('origen');

        $destinoOptions = (clone $baseQuery)
            ->selectRaw("distinct trim(coalesce(ems.ciudad, '')) as destino")
            ->whereRaw("trim(coalesce(ems.ciudad, '')) <> ''")
            ->orderBy('destino')
            ->pluck('destino');

        $query = (clone $baseQuery)
            ->when($search !== '', function (Builder $query) use ($search) {
                $like = '%' . strtolower($search) . '%';
                $query->where(function (Builder $sub) use ($like) {
                    foreach ([
                        'ems.codigo',
                        'ems.cod_especial',
                        'ems.origen',
                        'ems.ciudad',
                        'ems.nombre_remitente',
                        'ems.nombre_destinatario',
                        'ems.direccion',
                        'estado.nombre_estado',
                        'usuario.name',
                    ] as $column) {
                        $sub->orWhereRaw("LOWER(COALESCE(CAST($column AS TEXT), '')) LIKE ?", [$like]);
                    }
                });
            })
            ->when($from !== '', function (Builder $query) use ($from) {
                $query->whereDate('ems.created_at', '>=', $from);
            })
            ->when($to !== '', function (Builder $query) use ($to) {
                $query->whereDate('ems.created_at', '<=', $to);
            })
            ->when($origen !== '', function (Builder $query) use ($origen) {
                $query->whereRaw('trim(upper(coalesce(ems.origen, ?))) = ?', ['', strtoupper($origen)]);
            })
            ->when($destino !== '', function (Builder $query) use ($destino) {
                $query->whereRaw('trim(upper(coalesce(ems.ciudad, ?))) = ?', ['', strtoupper($destino)]);
            })
            ->select([
                'ems.id',
                'ems.codigo',
                'ems.cod_especial',
                'ems.origen',
                DB::raw("coalesce(ems.ciudad, '-') as destino"),
                DB::raw("coalesce(ems.nombre_remitente, '-') as remitente"),
                DB::raw("coalesce(ems.nombre_destinatario, '-') as destinatario"),
                DB::raw("coalesce(ems.direccion, '-') as direccion"),
                DB::raw("coalesce(estado.nombre_estado, '-') as estado"),
                DB::raw("coalesce(usuario.name, '-') as usuario"),
                DB::raw('coalesce(ems.peso, 0) as peso'),
                DB::raw('coalesce(ems.precio, 0) as precio'),
                'ems.created_at',
                'ems.updated_at',
            ]);

        $summaryRows = (clone $query)->get();
        $envios = (clone $query)
            ->orderByDesc('ems.created_at')
            ->orderByDesc('ems.id')
            ->paginate(25)
            ->withQueryString();

        return view('reportes.envios-oficiales', [
            'envios' => $envios,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'origen' => $origen,
            'destino' => $destino,
            'origenOptions' => $origenOptions,
            'destinoOptions' => $destinoOptions,
            'totalOficiales' => $summaryRows->count(),
            'pesoTotal' => (float) $summaryRows->sum('peso'),
            'precioTotal' => (float) $summaryRows->sum('precio'),
        ]);
    }

    public function commercialPerformance(Request $request)
    {
        $data = $this->buildCommercialPerformanceData($request);

        return view('reportes.commercial-performance', $data);
    }

    public function exportCommercialPerformanceExcel(Request $request)
    {
        @set_time_limit(300);

        $data = $this->buildCommercialPerformanceData($request);
        $filename = 'rendimiento-servicios-productos-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new \App\Exports\CommercialPerformanceExport($data), $filename);
    }

    public function exportCommercialPerformancePdf(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '1024M');

        $data = $this->buildCommercialPerformanceData($request);
        $pdf = Pdf::loadView('reportes.commercial-performance-pdf', $data)->setPaper('A4', 'landscape');
        $filename = 'rendimiento-servicios-productos-' . now()->format('Ymd-His') . '.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    private function buildCommercialBaseRows(Request $request): array
    {
        $selectedModules = $this->resolveSelectedModules('general', $request);
        [$from, $to] = $this->resolveDateRange($request);
        $selectedMonths = $this->resolveSelectedMonthFilters($request);
        $monthDateRanges = $this->buildMonthDateRanges($selectedMonths);
        if (!empty($monthDateRanges)) {
            $from = null;
            $to = null;
        }
        $search = trim((string) $request->query('q', ''));
        $departamentoOrigen = $this->resolveDepartamentoFiltro($request, 'departamento_origen');
        $departamentoDestino = $this->resolveDepartamentoFiltro($request, 'departamento_destino');
        if ($departamentoDestino === '') {
            $departamentoDestino = $this->resolveDepartamentoFiltro($request, 'departamento');
        }
        $statuses = $this->resolveStatusFilters($request);
        $selectedServices = $this->resolveServiceFilters($request);
        $limit = $this->resolveLimit($request);
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
        $rows = $this->filterRowsByState($rows, $statuses);
        $rows = $this->filterRowsByService($rows, $selectedServices);

        $rows = $rows->sortByDesc(fn ($row) => $row['created_at_ts'] ?? 0)->values();
        if ($limit !== null) {
            $rows = $rows->take($limit)->values();
        }

        return [
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
            'rows' => $rows,
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
                DB::raw("coalesce(srv.nombre_servicio, 'EMS') as servicio_nombre"),
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

    private function lifetimeMovementQuery(
        string $label,
        string $eventsTable,
        string $packagesTable,
        string $stateColumn,
        string $destinationColumn,
        string $search,
        ?string $from,
        ?string $to
    ): Builder {
        $query = DB::table($eventsTable.' as movement')
            ->leftJoin($packagesTable.' as package', 'package.codigo', '=', 'movement.codigo')
            ->leftJoin('eventos as event', 'event.id', '=', 'movement.evento_id')
            ->leftJoin('users as actor', 'actor.id', '=', 'movement.user_id')
            ->leftJoin('estados as state', 'state.id', '=', 'package.'.$stateColumn)
            ->selectRaw('? as service', [$label])
            ->addSelect([
                'movement.id as movement_id',
                'movement.codigo as code',
                'movement.evento_id as event_id',
                'event.nombre_evento as event_name',
                'actor.name as user_name',
                'package.origen as origin',
                'package.'.$destinationColumn.' as destination',
                'state.nombre_estado as current_state',
                'movement.created_at as moved_at',
            ]);

        if ($from) {
            $query->where('movement.created_at', '>=', Carbon::parse($from, 'America/La_Paz')->startOfDay()->setTimezone(config('app.timezone')));
        }
        if ($to) {
            $query->where('movement.created_at', '<=', Carbon::parse($to, 'America/La_Paz')->endOfDay()->setTimezone(config('app.timezone')));
        }
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $sub) use ($like) {
                $sub->whereRaw("LOWER(COALESCE(movement.codigo, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(event.nombre_evento, '')) LIKE ?", [$like])
                    ->orWhereRaw("LOWER(COALESCE(actor.name, '')) LIKE ?", [$like]);
            });
        }

        return $query;
    }

    private function buildCommercialPerformanceData(Request $request): array
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');
        $request->query->set('limit', 'all');

        $baseRequest = $request->duplicate();
        $baseRequest->query->remove('lineas');
        $baseRequest->query->set('limit', 'all');

        $data = $this->buildCommercialBaseRows($baseRequest);
        $selectedLines = collect((array) $request->query('lineas', []))
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $rows = $this->filterRowsWithoutCanceled(
            collect($data['rows'] ?? [])->concat($this->fetchCommercialSolicitudRows($request))
        )
            ->map(function (array $row) {
                $line = $this->resolveCommercialLine((string) ($row['servicio'] ?? ''), (string) ($row['modulo_key'] ?? ''));
                $serviceName = $this->resolveCommercialServiceName($line, (string) ($row['servicio'] ?? ''), (string) ($row['modulo_key'] ?? ''));
                $row['linea_negocio'] = $line;
                $row['servicio_comercial'] = $serviceName;
                unset($row['precio']);

                return $row;
            });

        if (!empty($selectedLines)) {
            $selectedLineMap = array_fill_keys($selectedLines, true);
            $rows = $rows
                ->filter(fn (array $row) => isset($selectedLineMap[(string) ($row['linea_negocio'] ?? '')]))
                ->values();
        }

        $lineRows = $rows
            ->groupBy(fn (array $row) => (string) ($row['linea_negocio'] ?? 'OTRAS LINEAS'))
            ->map(function (Collection $items, string $line) {
                $ordered = $items->sortByDesc(fn (array $row) => $row['created_at_ts'] ?? 0)->values();
                $topService = $items
                    ->groupBy(fn (array $row) => trim((string) ($row['servicio_comercial'] ?? 'SIN SERVICIO')) ?: 'SIN SERVICIO')
                    ->map(fn (Collection $serviceItems, string $service) => [
                        'servicio' => $service,
                        'cantidad' => $serviceItems->count(),
                    ])
                    ->sortByDesc('cantidad')
                    ->values()
                    ->first();

                return [
                    'linea' => $line,
                    'cantidad' => $items->count(),
                    'entregados' => $items->where('is_entregado', true)->count(),
                    'no_entregados' => $items->where('is_entregado', false)->where('is_cancelado', false)->count(),
                    'peso' => round((float) $items->sum('peso'), 3),
                    'top_servicio' => (string) ($topService['servicio'] ?? 'SIN SERVICIO'),
                    'top_servicio_cantidad' => (int) ($topService['cantidad'] ?? 0),
                    'ultimo_registro' => (string) ($ordered->first()['created_at'] ?? '-'),
                ];
            })
            ->sortByDesc('cantidad')
            ->values();

        $serviceRows = $rows
            ->groupBy(function (array $row) {
                $line = (string) ($row['linea_negocio'] ?? 'OTRAS LINEAS');
                $service = trim((string) ($row['servicio_comercial'] ?? 'SIN SERVICIO')) ?: 'SIN SERVICIO';

                return $line . '||' . $service;
            })
            ->map(function (Collection $items, string $groupKey) {
                [$line, $service] = array_pad(explode('||', $groupKey, 2), 2, 'SIN SERVICIO');
                $ordered = $items->sortByDesc(fn (array $row) => $row['created_at_ts'] ?? 0)->values();

                return [
                    'linea' => $line,
                    'servicio' => $service,
                    'cantidad' => $items->count(),
                    'entregados' => $items->where('is_entregado', true)->count(),
                    'no_entregados' => $items->where('is_entregado', false)->where('is_cancelado', false)->count(),
                    'peso' => round((float) $items->sum('peso'), 3),
                    'ultimo_registro' => (string) ($ordered->first()['created_at'] ?? '-'),
                ];
            })
            ->sortByDesc('cantidad')
            ->values();

        $commercialTotals = [
            'lineas' => $lineRows->count(),
            'registros' => $rows->count(),
            'entregados' => $rows->where('is_entregado', true)->count(),
            'no_entregados' => $rows->where('is_entregado', false)->where('is_cancelado', false)->count(),
            'peso_total' => round((float) $rows->sum('peso'), 3),
            'top_linea' => (string) ($lineRows->first()['linea'] ?? '-'),
            'top_linea_cantidad' => (int) ($lineRows->first()['cantidad'] ?? 0),
        ];
        $commercialKpis = [
            'effectiveness' => $this->buildCommercialEffectivenessSummary($rows),
            'sla' => $this->buildCommercialSlaSummary($rows),
            'heatmap' => $this->buildCommercialHeatMapSummary($rows),
        ];

        return [
            'scopeLabel' => 'Rendimiento de servicios y productos',
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
            'lineOptions' => self::COMMERCIAL_LINES,
            'selectedLines' => $selectedLines,
            'lineRows' => $lineRows,
            'serviceRows' => $serviceRows,
            'commercialTotals' => $commercialTotals,
            'commercialKpis' => $commercialKpis,
        ];
    }

    private function resolveCommercialLine(string $service, string $moduleKey = ''): string
    {
        $moduleKey = strtolower(trim($moduleKey));

        return match ($moduleKey) {
            'ems' => 'PAQUETES EMS',
            'contrato' => 'PAQUETES CONTRATOS',
            'certi' => 'PAQUETES CERTI',
            'ordi' => 'PAQUETES ORDI',
            'tiktoker' => 'DELIVERY EXPRESS',
            default => 'PAQUETES EMS',
        };
    }

    private function resolveCommercialServiceName(string $line, string $service, string $moduleKey = ''): string
    {
        $normalizedService = strtoupper(trim($service));
        $moduleKey = strtolower(trim($moduleKey));

        if ($moduleKey === 'ems' || $line === 'PAQUETES EMS') {
            return 'PAQUETES EMS';
        }

        return match ($line) {
            'PAQUETES CONTRATOS' => 'PAQUETES CONTRATOS',
            'PAQUETES CERTI' => 'PAQUETES CERTI',
            'PAQUETES ORDI' => 'PAQUETES ORDI',
            'DELIVERY EXPRESS' => 'DELIVERY EXPRESS',
            default => $normalizedService !== '' ? $normalizedService : 'SIN SERVICIO',
        };
    }

    private function fetchCommercialSolicitudRows(Request $request): Collection
    {
        [$from, $to, $range] = $this->resolveDateRange($request);
        $selectedMonths = $this->resolveSelectedMonthFilters($request);
        $monthDateRanges = $this->buildMonthDateRanges($selectedMonths);
        if (!empty($monthDateRanges)) {
            $from = null;
            $to = null;
            $range = 'months';
        }

        $codigoSql = "coalesce(nullif(trim(t.codigo_solicitud), ''), nullif(trim(t.barcode), ''), 'SIN CODIGO')";

        $query = DB::table('solicitud_clientes as t')
            ->leftJoin('estados as e', 'e.id', '=', 't.estado_id')
            ->select([
                DB::raw("'tiktoker' as modulo_key"),
                DB::raw("'DELIVERY EXPRESS' as modulo_label"),
                DB::raw("$codigoSql as codigo"),
                DB::raw('t.estado_id as estado_id'),
                DB::raw("coalesce(e.nombre_estado, '-') as estado_nombre"),
                DB::raw("coalesce(t.origen, '-') as origen"),
                DB::raw("coalesce(t.ciudad, '-') as destino"),
                DB::raw("coalesce(t.nombre_remitente, '-') as remitente"),
                DB::raw("coalesce(t.nombre_destinatario, '-') as destinatario"),
                DB::raw("'-' as empresa"),
                DB::raw('0 as usuario_id'),
                DB::raw("'-' as usuario"),
                DB::raw("'' as usuario_roles"),
                DB::raw("'-' as usuario_regional"),
                DB::raw("'' as usuario_regionales"),
                DB::raw("'DELIVERY EXPRESS' as servicio_nombre"),
                DB::raw('0 as entregado_por_id'),
                DB::raw("coalesce((select u.name from eventos_tiktoker ev left join users u on u.id = ev.user_id where ev.codigo = $codigoSql and ev.evento_id = " . self::EVENTO_ENTREGADO_ID . " order by ev.created_at desc, ev.id desc limit 1), 'Sin entrega registrada') as entregado_por"),
                DB::raw("'' as entregado_por_roles"),
                DB::raw("(select max(created_at) from eventos_tiktoker ev where ev.codigo = $codigoSql and ev.evento_id = " . self::EVENTO_ENTREGADO_ID . ") as delivered_at"),
                DB::raw('1 as usuario_activo'),
                DB::raw('0 as usuario_empresa_gestora'),
                DB::raw('coalesce(t.peso, 0) as peso'),
                DB::raw('coalesce(t.precio, 0) as precio'),
                't.created_at',
                't.updated_at',
                DB::raw('t.created_at as primer_evento_at'),
            ]);

        if (!empty($monthDateRanges)) {
            $query->where(function ($sub) use ($monthDateRanges) {
                foreach ($monthDateRanges as [$monthFrom, $monthTo]) {
                    $sub->orWhereBetween('t.created_at', [$monthFrom->copy()->startOfDay(), $monthTo->copy()->endOfDay()]);
                }
            });
        } elseif ($range !== 'all' && $from && $to) {
            $query->whereBetween('t.created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        }

        return $query
            ->get()
            ->map(fn ($row) => $this->decorateRow('tiktoker', $row, $this->resolveEstadoEntregadoId(), $this->resolveEstadoCanceladoId()));
    }

    private function buildCommercialEffectivenessSummary(Collection $rows): array
    {
        $operativos = $rows
            ->reject(fn (array $row) => (bool) ($row['is_cancelado'] ?? false))
            ->values();

        $entregados = $operativos->where('is_entregado', true)->count();
        $devoluciones = $operativos
            ->filter(fn (array $row) => str_contains($this->normalizeDestino((string) ($row['estado'] ?? '')), 'DEVOL'))
            ->count();
        $rezago = $operativos->where('situacion_bucket', 'rezago')->count();
        $pendientes = max(0, $operativos->count() - $entregados - $devoluciones);

        $byLine = $operativos
            ->groupBy(fn (array $row) => (string) ($row['linea_negocio'] ?? 'OTRAS LINEAS'))
            ->map(function (Collection $items, string $line) {
                $total = $items->count();
                $entregados = $items->where('is_entregado', true)->count();
                $devoluciones = $items
                    ->filter(fn (array $row) => str_contains($this->normalizeDestino((string) ($row['estado'] ?? '')), 'DEVOL'))
                    ->count();
                $rezago = $items->where('situacion_bucket', 'rezago')->count();

                return [
                    'linea' => $line,
                    'total' => $total,
                    'entregados' => $entregados,
                    'devoluciones' => $devoluciones,
                    'rezago' => $rezago,
                    'pendientes' => max(0, $total - $entregados - $devoluciones),
                    'efectividad_pct' => $total > 0 ? round(($entregados / $total) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('efectividad_pct')
            ->values();

        return [
            'metodologia' => 'Entrega exitosa = evento entregado confirmado. Devolucion y rezago se calculan por estado/situacion operativa.',
            'total' => $operativos->count(),
            'entregados' => $entregados,
            'devoluciones' => $devoluciones,
            'rezago' => $rezago,
            'pendientes' => $pendientes,
            'efectividad_pct' => $operativos->count() > 0 ? round(($entregados / $operativos->count()) * 100, 2) : 0,
            'incidencia_pct' => $operativos->count() > 0 ? round((($devoluciones + $rezago) / $operativos->count()) * 100, 2) : 0,
            'rows' => $byLine,
        ];
    }

    private function buildCommercialSlaSummary(Collection $rows): array
    {
        $deliveredRows = $rows
            ->filter(fn (array $row) => (bool) ($row['is_entregado'] ?? false))
            ->filter(fn (array $row) => (float) ($row['delivery_hours'] ?? 0) > 0)
            ->values();

        $byLine = $deliveredRows
            ->groupBy(fn (array $row) => (string) ($row['linea_negocio'] ?? 'OTRAS LINEAS'))
            ->map(function (Collection $items, string $line) {
                $avgHours = round((float) $items->avg('delivery_hours'), 2);
                $minHours = round((float) $items->min('delivery_hours'), 2);
                $maxHours = round((float) $items->max('delivery_hours'), 2);

                return [
                    'linea' => $line,
                    'entregados' => $items->count(),
                    'promedio_horas' => $avgHours,
                    'promedio' => $this->formatDurationHours($avgHours),
                    'minimo' => $this->formatDurationHours($minHours),
                    'maximo' => $this->formatDurationHours($maxHours),
                    'peso' => round((float) $items->sum('peso'), 3),
                ];
            })
            ->sort(function (array $a, array $b) {
                $compare = ($a['promedio_horas'] ?? 0) <=> ($b['promedio_horas'] ?? 0);

                return $compare !== 0 ? $compare : (($b['entregados'] ?? 0) <=> ($a['entregados'] ?? 0));
            })
            ->values();

        $avgHours = round((float) $deliveredRows->avg('delivery_hours'), 2);
        $minHours = round((float) $deliveredRows->min('delivery_hours'), 2);
        $maxHours = round((float) $deliveredRows->max('delivery_hours'), 2);

        return [
            'metodologia' => 'SLA promedio calculado desde la emision/registro de la guia hasta la entrega final confirmada.',
            'entregados' => $deliveredRows->count(),
            'promedio_horas' => $avgHours,
            'promedio' => $this->formatDurationHours($avgHours),
            'minimo' => $this->formatDurationHours($minHours),
            'maximo' => $this->formatDurationHours($maxHours),
            'mejor_linea' => (string) ($byLine->first()['linea'] ?? '-'),
            'mejor_linea_promedio' => (string) ($byLine->first()['promedio'] ?? '-'),
            'rows' => $byLine,
        ];
    }

    private function buildCommercialHeatMapSummary(Collection $rows): array
    {
        $originRows = $rows
            ->map(function (array $row) {
                return [
                    'origen' => $this->normalizeCommercialLocationValue((string) ($row['origen_registro'] ?? $row['origen'] ?? '')),
                    'peso' => (float) ($row['peso'] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['origen'] !== '')
            ->groupBy('origen')
            ->map(fn (Collection $items, string $origen) => [
                'ubicacion' => $origen,
                'cantidad' => $items->count(),
                'peso' => round((float) $items->sum('peso'), 3),
            ])
            ->sortByDesc('cantidad')
            ->values();

        $destinationRows = $rows
            ->map(function (array $row) {
                return [
                    'destino' => $this->normalizeCommercialLocationValue((string) ($row['destino'] ?? '')),
                    'peso' => (float) ($row['peso'] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['destino'] !== '')
            ->groupBy('destino')
            ->map(fn (Collection $items, string $destino) => [
                'ubicacion' => $destino,
                'cantidad' => $items->count(),
                'peso' => round((float) $items->sum('peso'), 3),
            ])
            ->sortByDesc('cantidad')
            ->values();

        $routeRows = $rows
            ->map(function (array $row) {
                $origen = $this->normalizeCommercialLocationValue((string) ($row['origen_registro'] ?? $row['origen'] ?? ''));
                $destino = $this->normalizeCommercialLocationValue((string) ($row['destino'] ?? ''));

                return [
                    'ruta' => $origen !== '' && $destino !== '' ? $origen . ' -> ' . $destino : '',
                    'peso' => (float) ($row['peso'] ?? 0),
                ];
            })
            ->filter(fn (array $row) => $row['ruta'] !== '')
            ->groupBy('ruta')
            ->map(fn (Collection $items, string $ruta) => [
                'ruta' => $ruta,
                'cantidad' => $items->count(),
                'peso' => round((float) $items->sum('peso'), 3),
            ])
            ->sortByDesc('cantidad')
            ->values();

        return [
            'metodologia' => 'Mapa de calor comercial construido con ranking de origenes, destinos y rutas mas frecuentes del periodo.',
            'top_origen' => (string) ($originRows->first()['ubicacion'] ?? '-'),
            'top_destino' => (string) ($destinationRows->first()['ubicacion'] ?? '-'),
            'top_ruta' => (string) ($routeRows->first()['ruta'] ?? '-'),
            'origenes' => $originRows->take(15)->values(),
            'destinos' => $destinationRows->take(15)->values(),
            'rutas' => $routeRows->take(15)->values(),
        ];
    }

    private function normalizeCommercialLocationValue(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return in_array($normalized, ['', '-', '.', 'SIN DESTINO', 'SIN ORIGEN'], true) ? '' : $normalized;
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

        $expression = $this->departamentoFilterExpression($moduleKey, $tipo);
        if ($expression === '') {
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

        $query->whereIn(DB::raw('trim(upper(' . $expression . '))'), $aliases);
    }

    private function departamentoFilterExpression(string $moduleKey, string $tipo): string
    {
        $columnKey = $tipo === 'origen' ? 'origen_col' : 'destino_col';
        $column = self::MODULES[$moduleKey][$columnKey] ?? null;
        if (!$column) {
            return '';
        }

        if ($tipo === 'origen') {
            return 'coalesce(t.' . $column . ", '')";
        }

        $destinoExpression = 'coalesce(t.' . $column . ", '')";
        $origenColumn = self::MODULES[$moduleKey]['origen_col'] ?? null;
        $stateColumn = self::MODULES[$moduleKey]['state_col'] ?? null;
        $estadoAlmacenId = $this->resolveEstadoIdByName('ALMACEN');

        if (!$origenColumn || !$stateColumn || !$estadoAlmacenId) {
            return $destinoExpression;
        }

        return 'case when t.' . $stateColumn . ' = ' . (int) $estadoAlmacenId
            . " then coalesce(nullif(trim(t." . $origenColumn . "), ''), t." . $column . ')'
            . ' else ' . $destinoExpression . ' end';
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
        return $this->resolveEstadoIdByName('ENTREGADO');
    }

    private function resolveEstadoCanceladoId(): ?int
    {
        return $this->resolveEstadoIdByName('CANCELADO');
    }

    private function resolveEstadoIdByName(string $estadoNombre): ?int
    {
        $cacheKey = strtoupper(trim($estadoNombre));
        if (array_key_exists($cacheKey, $this->estadoIdCache)) {
            return $this->estadoIdCache[$cacheKey];
        }

        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [$cacheKey])
            ->value('id');

        $resolvedId = $id ? (int) $id : null;
        $this->estadoIdCache[$cacheKey] = $resolvedId;

        return $resolvedId;
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


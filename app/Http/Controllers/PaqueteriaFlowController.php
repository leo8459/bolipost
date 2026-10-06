<?php

namespace App\Http\Controllers;

use App\Exports\PaqueteriaFlowExport;
use App\Support\CarteroEvent;
use App\Support\EncargadoEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class PaqueteriaFlowController extends Controller
{
    private const EVENTO_ALMACEN_ID = 295;
    private const EVENTO_DESPACHO_ID = 240;
    private const EVENTO_RECIBIDO_TRANSITO_ID = 297;
    private const EVENTO_ASIGNACION_CARTERO_ID = 184;
    private const EVENTO_INTENTO_DEVOLUCION_ID = 315;
    private const EVENTO_ENTREGADO_ID = 316;

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

    private const DEPARTMENTS = [
        'CHUQUISACA' => 'Chuquisaca',
        'LA PAZ' => 'La Paz',
        'COCHABAMBA' => 'Cochabamba',
        'ORURO' => 'Oruro',
        'POTOSI' => 'Potosí',
        'TARIJA' => 'Tarija',
        'SANTA CRUZ' => 'Santa Cruz',
        'BENI' => 'Beni',
        'PANDO' => 'Pando',
    ];

    private const DEPARTMENT_ALIASES = [
        'CHUQUISACA' => ['CHUQUISACA', 'SUCRE', 'MONTEAGUDO', 'CAMARGO', 'VILLA SERRANO'],
        'LA PAZ' => ['LA PAZ', 'EL ALTO', 'VIACHA', 'ACHOCALLA', 'CARANAVI', 'COPACABANA'],
        'COCHABAMBA' => ['COCHABAMBA', 'QUILLACOLLO', 'SACABA', 'TIQUIPAYA', 'VINTO', 'COLCAPIRHUA', 'CLIZA'],
        'ORURO' => ['ORURO', 'HUANUNI', 'CHALLAPATA'],
        'POTOSI' => ['POTOSI', 'POTOSÍ', 'UYUNI', 'VILLAZON', 'VILLAZÓN', 'TUPIZA', 'LLALLAGUA'],
        'TARIJA' => ['TARIJA', 'YACUIBA', 'BERMEJO', 'VILLA MONTES', 'VILLAMONTES'],
        'SANTA CRUZ' => ['SANTA CRUZ', 'MONTERO', 'WARNES', 'COTOCA', 'LA GUARDIA', 'EL TORNO', 'YAPACANI', 'CAMIRI', 'VALLEGRANDE'],
        'BENI' => ['BENI', 'TRINIDAD', 'RIBERALTA', 'RURRENABAQUE', 'MAGDALENA', 'SANTA ANA', 'SAN BORJA', 'GUAYARAMERIN', 'REYES'],
        'PANDO' => ['PANDO', 'COBIJA', 'PORVENIR', 'PUERTO RICO'],
    ];

    public function index(Request $request)
    {
        [$year, $selectedMonths, $selectedDepartments] = $this->validatedFilters($request);

        return view('reportes.flujo-paqueteria', $this->buildReportData($year, $selectedMonths, $selectedDepartments));
    }

    public function exportExcel(Request $request)
    {
        [$year, $selectedMonths, $selectedDepartments] = $this->validatedFilters($request);
        $data = $this->buildReportData($year, $selectedMonths, $selectedDepartments);

        return Excel::download(
            new PaqueteriaFlowExport($data),
            'flujo-paqueteria-'.$year.'.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        [$year, $selectedMonths, $selectedDepartments] = $this->validatedFilters($request);
        $data = $this->buildReportData($year, $selectedMonths, $selectedDepartments);
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
            'departamentos' => ['sometimes', 'array', 'max:'.count(self::DEPARTMENTS)],
            'departamentos.*' => ['string', Rule::in(array_keys(self::DEPARTMENTS)), 'distinct'],
        ]);

        $year = (int) ($validated['anio'] ?? now()->year);
        $selectedMonths = isset($validated['meses'])
            ? array_values(array_unique(array_map('intval', $validated['meses'])))
            : [7, 8, 9];
        sort($selectedMonths);
        $requestedDepartments = array_map(
            fn ($department) => strtoupper(trim((string) $department)),
            $validated['departamentos'] ?? []
        );
        $selectedDepartments = array_values(array_intersect(array_keys(self::DEPARTMENTS), $requestedDepartments));

        return [$year, $selectedMonths, $selectedDepartments];
    }

    private function buildReportData(int $year, array $selectedMonths, array $selectedDepartments = []): array
    {
        $months = [];
        $companiesById = [];
        $departmentPackageCodes = $selectedDepartments === []
            ? null
            : $this->departmentPackageCodesQuery($selectedDepartments);
        $selectedDepartmentNames = array_map(
            fn (string $department) => self::DEPARTMENTS[$department],
            $selectedDepartments
        );
        $departmentLabel = $selectedDepartmentNames === []
            ? 'Todos los departamentos'
            : implode(', ', $selectedDepartmentNames);
        $excludedTestCompanyIds = DB::table('empresa')
            ->where(function ($query): void {
                $query->whereRaw("LOWER(TRIM(COALESCE(nombre, ''))) LIKE ?", ['%prueba%'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(nombre, ''))) = ?", ['EMPRESA']);
            })
            ->pluck('id')
            ->all();
        $cancelledStateId = DB::table('estados')
            ->whereRaw('TRIM(UPPER(nombre_estado)) = ?', ['CANCELADO'])
            ->value('id');
        $cancelledStateId = $cancelledStateId !== null ? (int) $cancelledStateId : null;
        $companyIdExpression = 'COALESCE(pc.empresa_id, usuario.empresa_id)';
        // Usa el último registro por CN-33, igual que el listado de bitácoras.
        $latestBitacoraIds = DB::table('bitacoras')
            ->selectRaw('MAX(id)')
            ->whereNotNull('cod_especial')
            ->whereRaw("TRIM(COALESCE(cod_especial, '')) <> ''")
            ->groupByRaw('UPPER(TRIM(cod_especial))');
        $deliveredContractEvents = DB::table('eventos_contrato')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MIN(created_at) as entregado_at')
            ->where('evento_id', self::EVENTO_ENTREGADO_ID)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $deliveredEmsEvents = DB::table('eventos_ems')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MIN(created_at) as entregado_at')
            ->where('evento_id', self::EVENTO_ENTREGADO_ID)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $returnEventId = DB::table('eventos')
            ->whereRaw('TRIM(UPPER(nombre_evento)) = ?', ['PAQUETE DEVUELVO'])
            ->value('id');
        $returnedContractEvents = DB::table('eventos_contrato')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MIN(created_at) as devuelto_at')
            ->when($returnEventId !== null, fn ($query) => $query->where('evento_id', (int) $returnEventId))
            ->when($returnEventId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->groupByRaw('TRIM(UPPER(codigo))');
        $returnedEmsEvents = DB::table('eventos_ems')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MIN(created_at) as devuelto_at')
            ->when($returnEventId !== null, fn ($query) => $query->where('evento_id', (int) $returnEventId))
            ->when($returnEventId === null, fn ($query) => $query->whereRaw('1 = 0'))
            ->groupByRaw('TRIM(UPPER(codigo))');
        $assignmentEventIds = DB::table('eventos')
            ->where(function ($query): void {
                $query->whereRaw('TRIM(UPPER(nombre_evento)) IN (?, ?, ?)', [
                    mb_strtoupper(CarteroEvent::ASIGNADO),
                    mb_strtoupper(CarteroEvent::CAMBIADO),
                    mb_strtoupper(EncargadoEvent::CARTERO_CAMBIADO),
                ])
                    ->orWhereRaw('UPPER(nombre_evento) LIKE ?', ['%ASIGNADO A CARTERO%'])
                    ->orWhere('id', self::EVENTO_ASIGNACION_CARTERO_ID);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $courierReturnEventIds = [self::EVENTO_INTENTO_DEVOLUCION_ID];
        if ($returnEventId !== null && ! in_array((int) $returnEventId, $courierReturnEventIds, true)) {
            $courierReturnEventIds[] = (int) $returnEventId;
        }
        $assignmentContractEvents = DB::table('eventos_contrato')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MAX(created_at) as asignado_at')
            ->whereIn('evento_id', $assignmentEventIds)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $assignmentEmsEvents = DB::table('eventos_ems')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MAX(created_at) as asignado_at')
            ->whereIn('evento_id', $assignmentEventIds)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $courierDeliveredContractEvents = DB::table('eventos_contrato')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MAX(created_at) as entregado_at')
            ->where('evento_id', self::EVENTO_ENTREGADO_ID)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $courierDeliveredEmsEvents = DB::table('eventos_ems')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MAX(created_at) as entregado_at')
            ->where('evento_id', self::EVENTO_ENTREGADO_ID)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $courierReturnedContractEvents = DB::table('eventos_contrato')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MAX(created_at) as devuelto_at')
            ->whereIn('evento_id', $courierReturnEventIds)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $courierReturnedEmsEvents = DB::table('eventos_ems')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MAX(created_at) as devuelto_at')
            ->whereIn('evento_id', $courierReturnEventIds)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $dispatchEventIds = DB::table('eventos')
            ->whereRaw('TRIM(UPPER(nombre_evento)) IN (?, ?)', [
                'SACA INTERNA CREADA (SALIDA).',
                'DELIVERY EXPRESS ENVIADO EN SACA INTERNA.',
            ])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if (! in_array(self::EVENTO_DESPACHO_ID, $dispatchEventIds, true)) {
            $dispatchEventIds[] = self::EVENTO_DESPACHO_ID;
        }
        $warehouseContractEvents = DB::table('eventos_contrato')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MIN(created_at) as almacen_at')
            ->where('evento_id', self::EVENTO_ALMACEN_ID)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $warehouseEmsEvents = DB::table('eventos_ems')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MIN(created_at) as almacen_at')
            ->where('evento_id', self::EVENTO_ALMACEN_ID)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $dispatchContractEvents = DB::table('eventos_contrato')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MIN(created_at) as despacho_at')
            ->whereIn('evento_id', $dispatchEventIds)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $dispatchEmsEvents = DB::table('eventos_ems')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('MIN(created_at) as despacho_at')
            ->whereIn('evento_id', $dispatchEventIds)
            ->groupByRaw('TRIM(UPPER(codigo))');
        $transitReceiptContractEvents = DB::table('eventos_contrato')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('created_at as recibido_at')
            ->where('evento_id', self::EVENTO_RECIBIDO_TRANSITO_ID);
        $transitReceiptEmsEvents = DB::table('eventos_ems')
            ->selectRaw('TRIM(UPPER(codigo)) as codigo_normalizado')
            ->selectRaw('created_at as recibido_at')
            ->where('evento_id', self::EVENTO_RECIBIDO_TRANSITO_ID);
        $terminalStateIds = DB::table('estados')
            ->whereRaw('TRIM(UPPER(nombre_estado)) IN (?, ?, ?)', ['ENTREGADO', 'DEVOLUCION', 'DEVOLUCIÓN'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $resolutionStats = [
            'ems' => [
                'entregados' => ['total_seconds' => 0, 'count' => 0],
                'devueltos' => ['total_seconds' => 0, 'count' => 0],
            ],
            'contrato' => [
                'entregados' => ['total_seconds' => 0, 'count' => 0],
                'devueltos' => ['total_seconds' => 0, 'count' => 0],
            ],
        ];
        $dispatchStats = [
            'ems' => ['total_seconds' => 0, 'count' => 0],
            'contrato' => ['total_seconds' => 0, 'count' => 0],
        ];
        $transitReceiptStats = [
            'ems' => ['total_seconds' => 0, 'count' => 0],
            'contrato' => ['total_seconds' => 0, 'count' => 0],
        ];
        $courierResolutionStats = [
            'ems' => [
                'entregados' => ['total_seconds' => 0, 'count' => 0],
                'devueltos' => ['total_seconds' => 0, 'count' => 0],
            ],
            'contrato' => [
                'entregados' => ['total_seconds' => 0, 'count' => 0],
                'devueltos' => ['total_seconds' => 0, 'count' => 0],
            ],
        ];

        foreach ($selectedMonths as $monthNumber) {
            $monthName = self::MONTHS[$monthNumber];
            $start = Carbon::create($year, $monthNumber, 1)->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $dateRange = [$start->toDateTimeString(), $end->format('Y-m-d H:i:s.u')];

            $contractQuery = DB::table('paquetes_contrato as pc')
                ->leftJoin('users as usuario', 'usuario.id', '=', 'pc.user_id')
                ->whereBetween('pc.created_at', $dateRange);
            $this->applyDepartmentFilter($contractQuery, 'pc.origen', $selectedDepartments);
            if ($excludedTestCompanyIds !== []) {
                $contractQuery->where(function ($query) use ($companyIdExpression, $excludedTestCompanyIds): void {
                    $query->whereRaw($companyIdExpression.' IS NULL')
                        ->orWhereNotIn(DB::raw($companyIdExpression), $excludedTestCompanyIds);
                });
            }
            $contractDispatchQuery = clone $contractQuery;
            $this->excludeCancelledState($contractQuery, 'pc.estados_id', $cancelledStateId);
            $emsQuery = DB::table('paquetes_ems')
                ->whereBetween('paquetes_ems.created_at', $dateRange);
            $this->applyDepartmentFilter($emsQuery, 'origen', $selectedDepartments);
            $emsDispatchQuery = clone $emsQuery;
            $this->excludeCancelledState($emsQuery, 'paquetes_ems.estado_id', $cancelledStateId);

            $contractDispatchRows = (clone $contractDispatchQuery)
                ->leftJoinSub($warehouseContractEvents, 'evento_almacen', function ($join): void {
                    $join->on('evento_almacen.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                })
                ->leftJoinSub($dispatchContractEvents, 'evento_despacho', function ($join): void {
                    $join->on('evento_despacho.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                })
                ->where(function ($query): void {
                    $query->whereNotNull('evento_despacho.despacho_at')
                        ->orWhereNotNull('pc.envio_cn33');
                })
                ->get([
                    'pc.created_at as created_at',
                    'pc.envio_cn33 as envio_cn33',
                    'evento_almacen.almacen_at as almacen_at',
                    'evento_despacho.despacho_at as despacho_at',
                ]);
            $emsDispatchRows = (clone $emsDispatchQuery)
                ->leftJoinSub($warehouseEmsEvents, 'evento_almacen', function ($join): void {
                    $join->on('evento_almacen.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                })
                ->leftJoinSub($dispatchEmsEvents, 'evento_despacho', function ($join): void {
                    $join->on('evento_despacho.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                })
                ->where(function ($query): void {
                    $query->whereNotNull('evento_despacho.despacho_at')
                        ->orWhereNotNull('paquetes_ems.envio_cn33');
                })
                ->get([
                    'paquetes_ems.created_at as created_at',
                    'paquetes_ems.envio_cn33 as envio_cn33',
                    'evento_almacen.almacen_at as almacen_at',
                    'evento_despacho.despacho_at as despacho_at',
                ]);
            $this->addDispatchDurations($dispatchStats['contrato'], $contractDispatchRows);
            $this->addDispatchDurations($dispatchStats['ems'], $emsDispatchRows);

            $contractTransitReceiptRows = (clone $contractDispatchQuery)
                ->leftJoinSub($dispatchContractEvents, 'evento_despacho', function ($join): void {
                    $join->on('evento_despacho.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                })
                ->joinSub($transitReceiptContractEvents, 'evento_recibido', function ($join): void {
                    $join->on('evento_recibido.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                })
                ->whereRaw('evento_recibido.recibido_at >= COALESCE(pc.envio_cn33, evento_despacho.despacho_at)')
                ->get([
                    DB::raw('TRIM(UPPER(pc.codigo)) as codigo_normalizado'),
                    DB::raw('COALESCE(pc.envio_cn33, evento_despacho.despacho_at) as despacho_at'),
                    'evento_recibido.recibido_at',
                ]);
            $emsTransitReceiptRows = (clone $emsDispatchQuery)
                ->leftJoinSub($dispatchEmsEvents, 'evento_despacho', function ($join): void {
                    $join->on('evento_despacho.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                })
                ->joinSub($transitReceiptEmsEvents, 'evento_recibido', function ($join): void {
                    $join->on('evento_recibido.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                })
                ->whereRaw('evento_recibido.recibido_at >= COALESCE(paquetes_ems.envio_cn33, evento_despacho.despacho_at)')
                ->get([
                    DB::raw('TRIM(UPPER(paquetes_ems.codigo)) as codigo_normalizado'),
                    DB::raw('COALESCE(paquetes_ems.envio_cn33, evento_despacho.despacho_at) as despacho_at'),
                    'evento_recibido.recibido_at',
                ]);
            $this->addTransitReceiptDurations($transitReceiptStats['contrato'], $contractTransitReceiptRows);
            $this->addTransitReceiptDurations($transitReceiptStats['ems'], $emsTransitReceiptRows);

            if ($terminalStateIds !== []) {
                $contractResolutionRows = (clone $contractQuery)
                    ->leftJoin('estados as estado_final', 'estado_final.id', '=', 'pc.estados_id')
                    ->leftJoinSub($deliveredContractEvents, 'evento_entrega', function ($join): void {
                        $join->on('evento_entrega.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                    })
                    ->leftJoinSub($returnedContractEvents, 'evento_devolucion', function ($join): void {
                        $join->on('evento_devolucion.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                    })
                    ->whereIn('pc.estados_id', $terminalStateIds)
                    ->get([
                        'pc.created_at as created_at',
                        'pc.updated_at as updated_at',
                        'estado_final.nombre_estado as estado_final',
                        'evento_entrega.entregado_at',
                        'evento_devolucion.devuelto_at',
                    ]);

                $emsResolutionRows = (clone $emsQuery)
                    ->leftJoin('estados as estado_final', 'estado_final.id', '=', 'paquetes_ems.estado_id')
                    ->leftJoinSub($deliveredEmsEvents, 'evento_entrega', function ($join): void {
                        $join->on('evento_entrega.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                    })
                    ->leftJoinSub($returnedEmsEvents, 'evento_devolucion', function ($join): void {
                        $join->on('evento_devolucion.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                    })
                    ->whereIn('paquetes_ems.estado_id', $terminalStateIds)
                    ->get([
                        'paquetes_ems.created_at',
                        'paquetes_ems.updated_at',
                        'estado_final.nombre_estado as estado_final',
                        'evento_entrega.entregado_at',
                        'evento_devolucion.devuelto_at',
                    ]);

                $contractCourierResolutionRows = (clone $contractQuery)
                    ->join('cartero as asignacion_cartero', 'asignacion_cartero.id_paquetes_contrato', '=', 'pc.id')
                    ->leftJoin('estados as estado_final', 'estado_final.id', '=', 'pc.estados_id')
                    ->leftJoinSub($assignmentContractEvents, 'evento_asignacion', function ($join): void {
                        $join->on('evento_asignacion.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                    })
                    ->leftJoinSub($courierDeliveredContractEvents, 'evento_entrega_cartero', function ($join): void {
                        $join->on('evento_entrega_cartero.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                    })
                    ->leftJoinSub($courierReturnedContractEvents, 'evento_devolucion_cartero', function ($join): void {
                        $join->on('evento_devolucion_cartero.codigo_normalizado', '=', DB::raw('TRIM(UPPER(pc.codigo))'));
                    })
                    ->whereIn('pc.estados_id', $terminalStateIds)
                    ->get([
                        DB::raw('TRIM(UPPER(pc.codigo)) as codigo_normalizado'),
                        'asignacion_cartero.created_at as asignacion_fallback_at',
                        'asignacion_cartero.updated_at as cierre_fallback_at',
                        'estado_final.nombre_estado as estado_final',
                        'evento_asignacion.asignado_at',
                        'evento_entrega_cartero.entregado_at',
                        'evento_devolucion_cartero.devuelto_at',
                    ]);

                $emsCourierResolutionRows = (clone $emsQuery)
                    ->join('cartero as asignacion_cartero', 'asignacion_cartero.id_paquetes_ems', '=', 'paquetes_ems.id')
                    ->leftJoin('estados as estado_final', 'estado_final.id', '=', 'paquetes_ems.estado_id')
                    ->leftJoinSub($assignmentEmsEvents, 'evento_asignacion', function ($join): void {
                        $join->on('evento_asignacion.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                    })
                    ->leftJoinSub($courierDeliveredEmsEvents, 'evento_entrega_cartero', function ($join): void {
                        $join->on('evento_entrega_cartero.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                    })
                    ->leftJoinSub($courierReturnedEmsEvents, 'evento_devolucion_cartero', function ($join): void {
                        $join->on('evento_devolucion_cartero.codigo_normalizado', '=', DB::raw('TRIM(UPPER(paquetes_ems.codigo))'));
                    })
                    ->whereIn('paquetes_ems.estado_id', $terminalStateIds)
                    ->get([
                        DB::raw('TRIM(UPPER(paquetes_ems.codigo)) as codigo_normalizado'),
                        'asignacion_cartero.created_at as asignacion_fallback_at',
                        'asignacion_cartero.updated_at as cierre_fallback_at',
                        'estado_final.nombre_estado as estado_final',
                        'evento_asignacion.asignado_at',
                        'evento_entrega_cartero.entregado_at',
                        'evento_devolucion_cartero.devuelto_at',
                    ]);

                $this->addResolutionDurations($resolutionStats['contrato'], $contractResolutionRows);
                $this->addResolutionDurations($resolutionStats['ems'], $emsResolutionRows);
                $this->addCourierResolutionDurations($courierResolutionStats['contrato'], $contractCourierResolutionRows);
                $this->addCourierResolutionDurations($courierResolutionStats['ems'], $emsCourierResolutionRows);
            }

            $contractGuides = (int) (clone $contractQuery)->distinct('pc.codigo')->count('pc.codigo');
            $contractWeight = (float) (clone $contractQuery)->sum('pc.peso');
            $emsGuides = (int) (clone $emsQuery)->distinct('codigo')->count('codigo');

            $bitacoraQuery = DB::table('bitacoras as b')
                ->whereIn('b.id', clone $latestBitacoraIds)
                ->whereBetween('b.created_at', $dateRange);
            if ($departmentPackageCodes !== null) {
                $this->applyBitacoraDepartmentFilter($bitacoraQuery, $selectedDepartments, $departmentPackageCodes);
            }
            $bitacorasCn33 = $bitacoraQuery->get(['b.cod_especial', 'b.transportadora', 'b.peso']);

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
                ->when($selectedDepartments !== [], fn ($query) => $this->applyDepartmentFilter($query, 'pc.origen', $selectedDepartments))
                ->when($excludedTestCompanyIds !== [], fn ($query) => $query->whereNotIn(DB::raw($companyIdExpression), $excludedTestCompanyIds))
                ->when($cancelledStateId !== null, function ($query) use ($cancelledStateId): void {
                    $this->excludeCancelledState($query, 'pc.estados_id', $cancelledStateId);
                })
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
        $combinedResolutionStats = [
            'entregados' => [
                'total_seconds' => $resolutionStats['ems']['entregados']['total_seconds'] + $resolutionStats['contrato']['entregados']['total_seconds'],
                'count' => $resolutionStats['ems']['entregados']['count'] + $resolutionStats['contrato']['entregados']['count'],
            ],
            'devueltos' => [
                'total_seconds' => $resolutionStats['ems']['devueltos']['total_seconds'] + $resolutionStats['contrato']['devueltos']['total_seconds'],
                'count' => $resolutionStats['ems']['devueltos']['count'] + $resolutionStats['contrato']['devueltos']['count'],
            ],
        ];
        $resolutionTime = [
            'ems' => $this->summarizeResolutionStats($resolutionStats['ems']),
            'contrato' => $this->summarizeResolutionStats($resolutionStats['contrato']),
            'total' => $this->summarizeResolutionStats($combinedResolutionStats),
        ];
        $combinedDispatchStats = [
            'total_seconds' => $dispatchStats['ems']['total_seconds'] + $dispatchStats['contrato']['total_seconds'],
            'count' => $dispatchStats['ems']['count'] + $dispatchStats['contrato']['count'],
        ];
        $dispatchTime = [
            'ems' => $this->summarizeDispatchStats($dispatchStats['ems']),
            'contrato' => $this->summarizeDispatchStats($dispatchStats['contrato']),
            'total' => $this->summarizeDispatchStats($combinedDispatchStats),
        ];
        $combinedTransitReceiptStats = [
            'total_seconds' => $transitReceiptStats['ems']['total_seconds'] + $transitReceiptStats['contrato']['total_seconds'],
            'count' => $transitReceiptStats['ems']['count'] + $transitReceiptStats['contrato']['count'],
        ];
        $transitReceiptTime = [
            'ems' => $this->summarizeTransitReceiptStats($transitReceiptStats['ems']),
            'contrato' => $this->summarizeTransitReceiptStats($transitReceiptStats['contrato']),
            'total' => $this->summarizeTransitReceiptStats($combinedTransitReceiptStats),
        ];
        $combinedCourierResolutionStats = [
            'entregados' => [
                'total_seconds' => $courierResolutionStats['ems']['entregados']['total_seconds'] + $courierResolutionStats['contrato']['entregados']['total_seconds'],
                'count' => $courierResolutionStats['ems']['entregados']['count'] + $courierResolutionStats['contrato']['entregados']['count'],
            ],
            'devueltos' => [
                'total_seconds' => $courierResolutionStats['ems']['devueltos']['total_seconds'] + $courierResolutionStats['contrato']['devueltos']['total_seconds'],
                'count' => $courierResolutionStats['ems']['devueltos']['count'] + $courierResolutionStats['contrato']['devueltos']['count'],
            ],
        ];
        $courierResolutionTime = [
            'ems' => $this->summarizeResolutionStats($courierResolutionStats['ems']),
            'contrato' => $this->summarizeResolutionStats($courierResolutionStats['contrato']),
            'total' => $this->summarizeResolutionStats($combinedCourierResolutionStats),
        ];

        return [
            'anio' => $year,
            'yearOptions' => $yearOptions,
            'monthOptions' => self::MONTHS,
            'selectedMonths' => $selectedMonths,
            'departmentOptions' => self::DEPARTMENTS,
            'selectedDepartments' => $selectedDepartments,
            'departmentLabel' => $departmentLabel,
            'periodLabel' => $periodLabel,
            'months' => $months,
            'totals' => $totals,
            'resolutionTime' => $resolutionTime,
            'dispatchTime' => $dispatchTime,
            'transitReceiptTime' => $transitReceiptTime,
            'courierResolutionTime' => $courierResolutionTime,
            'companyRows' => $companyRows->all(),
            'topByGuides' => $topByGuides,
            'topByWeight' => $topByWeight,
        ];
    }

    private function addResolutionDurations(array &$stats, $rows): void
    {
        foreach ($rows as $row) {
            $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;
            $isReturned = Str::ascii(Str::upper(trim((string) $row->estado_final))) === 'DEVOLUCION';
            $completedAtValue = $isReturned
                ? ($row->devuelto_at ?: $row->updated_at)
                : ($row->entregado_at ?: $row->updated_at);
            $completedAt = $completedAtValue ? Carbon::parse($completedAtValue) : null;

            if (! $createdAt || ! $completedAt) {
                continue;
            }

            $elapsedSeconds = $completedAt->getTimestamp() - $createdAt->getTimestamp();
            if ($elapsedSeconds < 0) {
                continue;
            }

            $result = $isReturned ? 'devueltos' : 'entregados';
            $stats[$result]['total_seconds'] += $elapsedSeconds;
            $stats[$result]['count']++;
        }
    }

    private function addCourierResolutionDurations(array &$stats, $rows): void
    {
        foreach ($rows as $row) {
            $assignedAtValue = $row->asignado_at ?: $row->asignacion_fallback_at;
            $isReturned = Str::ascii(Str::upper(trim((string) $row->estado_final))) === 'DEVOLUCION';
            $completedAtValue = $isReturned
                ? ($row->devuelto_at ?: $row->cierre_fallback_at)
                : ($row->entregado_at ?: $row->cierre_fallback_at);

            if (! $assignedAtValue || ! $completedAtValue) {
                continue;
            }

            $assignedAt = Carbon::parse($assignedAtValue);
            $completedAt = Carbon::parse($completedAtValue);
            $elapsedSeconds = $completedAt->getTimestamp() - $assignedAt->getTimestamp();
            if ($elapsedSeconds < 0) {
                continue;
            }

            $result = $isReturned ? 'devueltos' : 'entregados';
            $stats[$result]['total_seconds'] += $elapsedSeconds;
            $stats[$result]['count']++;
        }
    }

    private function addDispatchDurations(array &$stats, $rows): void
    {
        foreach ($rows as $row) {
            $warehouseAtValue = $row->almacen_at ?: $row->created_at;
            $dispatchAtValue = $row->envio_cn33 ?: $row->despacho_at;
            if (! $warehouseAtValue || ! $dispatchAtValue) {
                continue;
            }

            $warehouseAt = Carbon::parse($warehouseAtValue);
            $dispatchAt = Carbon::parse($dispatchAtValue);
            $elapsedSeconds = $dispatchAt->getTimestamp() - $warehouseAt->getTimestamp();
            if ($elapsedSeconds < 0) {
                continue;
            }

            $stats['total_seconds'] += $elapsedSeconds;
            $stats['count']++;
        }
    }

    private function addTransitReceiptDurations(array &$stats, $rows): void
    {
        $firstReceiptByCode = [];

        foreach ($rows as $row) {
            $code = trim((string) $row->codigo_normalizado);
            $dispatchAt = $row->despacho_at ? Carbon::parse($row->despacho_at) : null;
            $receivedAt = $row->recibido_at ? Carbon::parse($row->recibido_at) : null;

            if ($code === '' || ! $dispatchAt || ! $receivedAt) {
                continue;
            }

            $elapsedSeconds = $receivedAt->getTimestamp() - $dispatchAt->getTimestamp();
            if ($elapsedSeconds < 0) {
                continue;
            }

            if (! isset($firstReceiptByCode[$code]) || $receivedAt->lt($firstReceiptByCode[$code]['received_at'])) {
                $firstReceiptByCode[$code] = [
                    'elapsed_seconds' => $elapsedSeconds,
                    'received_at' => $receivedAt,
                ];
            }
        }

        foreach ($firstReceiptByCode as $receipt) {
            $stats['total_seconds'] += $receipt['elapsed_seconds'];
            $stats['count']++;
        }
    }

    private function summarizeResolutionStats(array $stats): array
    {
        $delivered = $stats['entregados'];
        $returned = $stats['devueltos'];
        $totalSeconds = $delivered['total_seconds'] + $returned['total_seconds'];
        $totalCount = $delivered['count'] + $returned['count'];
        $averageDeliveryMinutes = $this->averageDurationMinutes($delivered);
        $averageReturnMinutes = $this->averageDurationMinutes($returned);
        $averageTotalMinutes = $this->averageDurationMinutes([
            'total_seconds' => $totalSeconds,
            'count' => $totalCount,
        ]);

        return [
            'entregados' => $delivered['count'],
            'promedio_entrega_texto' => $this->formatAverageDuration($averageDeliveryMinutes),
            'devueltos' => $returned['count'],
            'promedio_devolucion_texto' => $this->formatAverageDuration($averageReturnMinutes),
            'finalizados' => $totalCount,
            'promedio_total_texto' => $this->formatAverageDuration($averageTotalMinutes),
        ];
    }

    private function averageDurationMinutes(array $stats): ?int
    {
        return $stats['count'] > 0
            ? (int) round($stats['total_seconds'] / $stats['count'] / 60)
            : null;
    }

    private function summarizeDispatchStats(array $stats): array
    {
        $averageMinutes = $this->averageDurationMinutes($stats);

        return [
            'despachados' => $stats['count'],
            'promedio_minutos' => $averageMinutes,
            'promedio_texto' => $this->formatAverageDuration($averageMinutes),
        ];
    }

    private function summarizeTransitReceiptStats(array $stats): array
    {
        $averageMinutes = $this->averageDurationMinutes($stats);

        return [
            'recibidos' => $stats['count'],
            'promedio_minutos' => $averageMinutes,
            'promedio_texto' => $this->formatAverageDuration($averageMinutes),
        ];
    }

    private function formatAverageDuration(?int $minutes): string
    {
        if ($minutes === null) {
            return 'Sin datos';
        }

        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $remainingMinutes = $minutes % 60;
        $parts = [];

        if ($days > 0) {
            $parts[] = $days.' '.($days === 1 ? 'día' : 'días');
        }
        if ($hours > 0) {
            $parts[] = $hours.' '.($hours === 1 ? 'hora' : 'horas');
        }
        if ($remainingMinutes > 0 || $parts === []) {
            $parts[] = $remainingMinutes.' '.($remainingMinutes === 1 ? 'minuto' : 'minutos');
        }

        return implode(', ', $parts);
    }

    private function departmentAliases(array $selectedDepartments): array
    {
        return collect($selectedDepartments)
            ->flatMap(fn (string $department) => self::DEPARTMENT_ALIASES[$department] ?? [$department])
            ->map(fn ($alias) => strtoupper(trim((string) $alias)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function applyDepartmentFilter($query, string $locationExpression, array $selectedDepartments): void
    {
        if ($selectedDepartments === []) {
            return;
        }

        $aliases = $this->departmentAliases($selectedDepartments);
        $normalizedLocation = "TRIM(UPPER(COALESCE({$locationExpression}, '')))";

        $query->where(function ($departmentQuery) use ($aliases, $normalizedLocation): void {
            $departmentQuery->whereIn(DB::raw($normalizedLocation), $aliases);
            foreach ($aliases as $alias) {
                $departmentQuery->orWhereRaw($normalizedLocation.' LIKE ?', ['%'.$alias.'%']);
            }
        });
    }

    private function excludeCancelledState($query, string $stateColumn, ?int $cancelledStateId): void
    {
        if (!$cancelledStateId) {
            return;
        }

        $query->where(function ($stateQuery) use ($stateColumn, $cancelledStateId): void {
            $stateQuery->whereNull($stateColumn)
                ->orWhere($stateColumn, '<>', $cancelledStateId);
        });
    }

    private function applyBitacoraDepartmentFilter($query, array $selectedDepartments, $departmentPackageCodes): void
    {
        $aliases = $this->departmentAliases($selectedDepartments);

        $query->where(function ($departmentQuery) use ($aliases, $departmentPackageCodes): void {
            $departmentQuery->whereIn(
                DB::raw("TRIM(UPPER(COALESCE(b.cod_especial, '')))"),
                clone $departmentPackageCodes
            );

            foreach ([
                ['paquetes_ems as dept_ems', 'b.paquetes_ems_id', 'origen'],
                ['paquetes_contrato as dept_contrato', 'b.paquetes_contrato_id', 'origen'],
            ] as [$table, $bitacoraColumn, $originColumn]) {
                [$tableName, $alias] = explode(' as ', $table, 2);
                $departmentQuery->orWhereExists(function ($packageQuery) use ($tableName, $alias, $bitacoraColumn, $originColumn, $aliases): void {
                    $packageQuery->selectRaw('1')
                        ->from($tableName.' as '.$alias)
                        ->whereColumn($alias.'.id', $bitacoraColumn)
                        ->where(function ($locationQuery) use ($aliases, $alias, $originColumn): void {
                            $this->applyDepartmentAliasFilter($locationQuery, $alias.'.'.$originColumn, $aliases);
                        });
                });
            }
        });
    }

    private function departmentPackageCodesQuery(array $selectedDepartments)
    {
        $aliases = $this->departmentAliases($selectedDepartments);
        $queries = [
            DB::table('paquetes_ems')
                ->selectRaw("TRIM(UPPER(cod_especial)) as cod_especial")
                ->whereNotNull('cod_especial')
                ->whereRaw("TRIM(COALESCE(cod_especial, '')) <> ''")
                ->where(function ($query) use ($aliases): void {
                    $this->applyDepartmentAliasFilter($query, 'origen', $aliases);
                }),
            DB::table('paquetes_contrato')
                ->selectRaw("TRIM(UPPER(cod_especial)) as cod_especial")
                ->whereNotNull('cod_especial')
                ->whereRaw("TRIM(COALESCE(cod_especial, '')) <> ''")
                ->where(function ($query) use ($aliases): void {
                    $this->applyDepartmentAliasFilter($query, 'origen', $aliases);
                }),
            DB::table('paquetes_int')
                ->selectRaw("TRIM(UPPER(cod_especial)) as cod_especial")
                ->whereNotNull('cod_especial')
                ->whereRaw("TRIM(COALESCE(cod_especial, '')) <> ''")
                ->where(function ($query) use ($aliases): void {
                    $this->applyDepartmentAliasFilter($query, 'origen', $aliases);
                }),
        ];

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->union($query);
        }

        return DB::query()
            ->fromSub($union, 'department_cn33_codes')
            ->select('cod_especial')
            ->whereNotNull('cod_especial');
    }

    private function applyDepartmentAliasFilter($query, string $locationExpression, array $aliases): void
    {
        $normalizedLocation = "TRIM(UPPER(COALESCE({$locationExpression}, '')))";
        $query->whereIn(DB::raw($normalizedLocation), $aliases);

        foreach ($aliases as $alias) {
            $query->orWhereRaw($normalizedLocation.' LIKE ?', ['%'.$alias.'%']);
        }
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

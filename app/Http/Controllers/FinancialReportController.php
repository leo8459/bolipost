<?php

namespace App\Http\Controllers;

use App\Models\CashierFlowReceivableCollection;
use App\Models\CashierFlowReceivableMovement;
use App\Models\ConciliacionEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Services\FacturacionReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FinancialReportController extends Controller
{
    private const MAX_SERVICE_FILTER_ITEMS = 200;

    private const CASHIER_FLOW_EXCLUDED_CASHIERS = [];

    private const DEPARTMENT_BY_BRANCH_CODE = [
        '0' => 'LA PAZ',
        '1' => 'SANTA CRUZ',
        '2' => 'COCHABAMBA',
        '3' => 'ORURO',
        '4' => 'POTOSI',
        '5' => 'SUCRE',
        '6' => 'TARIJA',
        '7' => 'COBIJA',
        '8' => 'TRINIDAD',
    ];

    private const DEPARTMENT_ALIASES = [
        'SANTA CRUZ DE LA SIERRA' => 'SANTA CRUZ',
        'CHUQUISACA' => 'SUCRE',
        'PANDO' => 'COBIJA',
        'BENI' => 'TRINIDAD',
        'COCHABMABA' => 'COCHABAMBA',
    ];

    private const DASHBOARD_DEPARTMENTS = [
        'LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI',
        'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA',
    ];

    private const SERVICE_GROUPS = [
        'Servicio EMS Nacional' => [
            'Servicio EMS Nacional',
            'Servicio EMS Local Cobertura 1',
            'Servicio EMS Local Cobertura 4',
            'Servicio Ciudades Intermedias',
            'Servicio Ciudades Intermedias Trinidad Cobija',
            'Servicio Trinidad Cobija',
            'Servicio Delivery Express',
        ],
        'Servicio Contratos' => [
            'Servicio Contratos por concepto de pago de servicios de courier correspondiente',
        ],
        'Servicio Certificado' => [
            'Servicio Certificado',
            'Servicio Certificado Internacional',
        ],
        'Servicio Certificadas' => [
            'Servicio Certificadas',
        ],
        'Servicio Internacional' => [
            'Servicio Internacional',
            'Servicio EMS Internacional',
            'Servicio Encomienda Internacional',
            'Servicio Ordinaria Internacional',
            'Servicio Ordinarias',
            'Servicio Aerolinea',
            'Servicio Venta de Estampillas',
            'Servicio Venta de Tarjeta Postal',
        ],
        'Servicio Casilla' => [
            'Servicio Casilla',
        ],
    ];

    public function __construct(private readonly FacturacionReportService $reports) {}

    public function cashierFlow(Request $request)
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');

        return view('financial-reports.cashier-flow', $this->buildCashierFlowData($request));
    }

    private function buildCashierFlowData(Request $request, bool $includeCashierBreakdown = true): array
    {
        $data = $this->buildServicesReportData(
            $request,
            forceOnlyContracts: false,
            reconcileContracts: false,
            excludeContracts: true,
            excludedCashierNames: self::CASHIER_FLOW_EXCLUDED_CASHIERS,
            enableDepartmentFilter: true,
            cacheReports: true,
            showReceivablesSeparately: true,
            includeAnnulledDetails: true,
            loadAllReceivablesOutsideServiceFilter: true
        );
        $receivableRows = collect($data['receivableServices']);
        $invoicePeriodMovements = collect();
        if ($includeCashierBreakdown && Schema::hasTable('cashier_flow_receivable_movements') && $data['selectedDepartment'] === '') {
            $invoicePeriodMovements = CashierFlowReceivableMovement::query()
                ->where('cobro_activo', true)
                ->where('anio', $data['anio'])
                ->whereIn('mes', $data['selectedMonths'])
                ->whereIn('servicio', $receivableRows->pluck('servicio')->all())
                ->get();
            $invoicePeriodMovements = $this->enrichCashierFlowCollectedMovements($invoicePeriodMovements);
        }
        $cancellationServices = collect($data['services'])->concat($data['receivableServices']);
        $cancelledInvoices = $this->cashierFlowCancelledInvoicesFromServices(
            $cancellationServices,
            $data['anio'],
            self::CASHIER_FLOW_EXCLUDED_CASHIERS,
            $data['selectedDepartment']
        );
        $cancelledMovementKeys = $cancelledInvoices->pluck('_movementKey')->filter()->flip();
        if ($cancelledMovementKeys->isNotEmpty()) {
            $invoicePeriodMovements = $invoicePeriodMovements
                ->reject(fn (CashierFlowReceivableMovement $movement): bool => $cancelledMovementKeys->has($movement->movement_key))
                ->values();
        }
        $data['cashierFlowCancelledInvoices'] = $cancelledInvoices
            ->unique(fn (array $invoice): string => filled($invoice['_movementKey'] ?? null)
                ? (string) $invoice['_movementKey']
                : (string) $invoice['_dedupeKey'])
            ->sortByDesc('fecha')
            ->values();
        $data['summary']['totalMontoAnulado'] = (float) $cancelledInvoices
            ->reject(fn (array $invoice): bool => $this->isCashierFlowReceivableService((string) ($invoice['_servicio'] ?? '')))
            ->sum('monto');
        $data['cashierFlowCancellationLookupErrors'] = collect($data['annulledDetailErrors'] ?? [])
            ->unique()
            ->values();
        $cancelledByService = $cancelledInvoices
            ->reject(fn (array $invoice): bool => $this->isCashierFlowReceivableService((string) ($invoice['_servicio'] ?? '')))
            ->groupBy('_servicio')
            ->map(fn (Collection $invoices): float => (float) $invoices->sum('monto'));
        $data['services'] = collect($data['services'])->map(function (array $service) use ($cancelledByService): array {
            $annulledAmount = (float) $cancelledByService->get((string) ($service['servicio'] ?? ''), 0);
            $service['totalMontoAnulado'] = $annulledAmount;
            $service['totalMontoNoIncluidoEnTotalVendido'] = (float) ($service['totalMontoNoIncluidoEnTotalVendido'] ?? 0)
                + $annulledAmount;

            return $service;
        })->values();
        $data['summary']['totalMontoNoIncluidoEnTotalVendido'] = (float) collect($data['services'])->sum('totalMontoNoIncluidoEnTotalVendido');
        $data['summary']['totalMontoAnulado'] = (float) $cancelledByService->sum();
        $invoiceCollections = $invoicePeriodMovements->groupBy('servicio');
        $data['receivableServices'] = $receivableRows
            ->map(function (array $service) use ($invoiceCollections): array {
                $serviceCollections = $invoiceCollections->get((string) ($service['servicio'] ?? ''), collect());
                $service['_montoCobrado'] = (float) $serviceCollections->sum('monto');
                $service['_montoPendiente'] = max(0, (float) ($service['totalMonto'] ?? 0) - $service['_montoCobrado']);
                $service['_cobroRealizado'] = $service['_montoCobrado'] > 0;

                return $service;
            })
            ->values();
        $receivableDepartmentBreakdown = null;
        if ($includeCashierBreakdown) {
            $receivableDepartmentBreakdown = $this->cashierFlowReceivableDepartmentBreakdown(
                $data['receivableServices'],
                $data['selectedMonths'],
                $data['anio'],
                $invoicePeriodMovements,
                $data['selectedDepartment']
            );
            $data['cashierFlowReceivableDepartmentErrors'] = $receivableDepartmentBreakdown['errors'];
            $data['cashierFlowReceivableDepartmentAmounts'] = $receivableDepartmentBreakdown['pendingByDepartment'];
            $data['cashierFlowReceivableInvoicers'] = $receivableDepartmentBreakdown['invoicers'];
            if ($receivableDepartmentBreakdown['complete']) {
                $data['receivableServices'] = $data['receivableServices']
                    ->map(function (array $service) use ($receivableDepartmentBreakdown): array {
                        $serviceName = (string) ($service['servicio'] ?? '');
                        $service['totalMonto'] = (float) $receivableDepartmentBreakdown['billedByService']->get($serviceName, $service['totalMonto'] ?? 0);
                        $service['_montoCobrado'] = (float) $receivableDepartmentBreakdown['collectedByService']->get($serviceName, 0);
                        $service['_montoPendiente'] = (float) $receivableDepartmentBreakdown['pendingByService']->get($serviceName, 0);
                        $service['_cobroRealizado'] = $service['_montoCobrado'] > 0;

                        return $service;
                    })
                    ->values();
            }
            $data['cashierFlowReceivablePendingAmount'] = (float) $data['receivableServices']->sum('_montoPendiente');
        }
        $collectedServiceRows = $this->buildCollectedReceivableServiceRows($invoicePeriodMovements->groupBy('servicio'));
        $data['serviceGroups'] = $this->buildCashierFlowServiceGroups(
            collect($data['services']),
            collect($data['receivableServices']),
            $collectedServiceRows,
            collect($receivableDepartmentBreakdown['collectedQrByService'] ?? [])
        );
        $data['serviceGroups'] = $this->alignCashierFlowVentanillasSalesCount(
            $data['serviceGroups'],
            (float) ($data['summary']['cantidadVentas'] ?? 0)
        );
        $data['summary']['cantidadServicios'] = $data['serviceGroups']->count();
        $data['cashierFlowCollectedAmount'] = (float) $invoicePeriodMovements->sum('monto');
        $data['summary']['totalRecaudado'] = (float) $data['serviceGroups']->sum(
            fn (array $group): float => (float) ($group['totalMontoRecaudado'] ?? 0)
        );
        $ventanillasGroup = $data['serviceGroups']->first(
            fn (array $group): bool => ($group['servicio'] ?? '') === 'VENTANILLAS'
        );
        $data['summary']['totalSinContratosEca'] = (float) ($ventanillasGroup['totalMontoRecaudado']
            ?? $data['summary']['totalMontoVendido']
            ?? $data['summary']['totalMonto']
            ?? 0);
        $data['totalReportAmountIncludingReceivables'] = (float) $data['summary']['totalRecaudado']
            + (float) ($data['cashierFlowReceivablePendingAmount'] ?? $data['receivableServices']->sum('_montoPendiente'));
        if ($includeCashierBreakdown) {
            $data['cashierRows'] = $this->addCashierReceivableIncome(
                $this->buildCashierBreakdown($data['services']),
                $invoicePeriodMovements
            );
            $data['cashierRows'] = $this->addCashierFlowReceivableInvoicers(
                $data['cashierRows'],
                $data['cashierFlowReceivableInvoicers'] ?? collect()
            );
            $data['cashierRows'] = $this->addCashierPeriodAverages(
                $data['cashierRows'],
                $data['selectedMonths'],
                $data['anio']
            );
            $data['cashierDepartmentGroups'] = $this->addReceivablesToCashierDepartmentGroups(
                $this->buildCashierDepartmentGroups($data['cashierRows']),
                $data['cashierFlowReceivableDepartmentAmounts'] ?? collect(),
                $this->countReportDaysExcludingSundays($data['selectedMonths'], $data['anio'])
            );
            $departmentSalesAlignment = $this->alignCashierFlowDepartmentSalesCounts(
                $data['cashierDepartmentGroups'],
                $data
            );
            $data['cashierDepartmentGroups'] = $departmentSalesAlignment['groups'];
            $data['cashierFlowDepartmentSalesCountWarning'] = $departmentSalesAlignment['warning'];
        }

        return $data;
    }

    public function cashierFlowDashboardAmounts(Request $request): JsonResponse
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');

        $validated = $request->validate([
            'mes' => ['required_without:meses', 'nullable', 'integer', 'between:1,12'],
            'meses' => ['required_without:mes', 'nullable', 'array', 'min:1', 'max:12'],
            'meses.*' => ['required', 'integer', 'distinct', 'between:1,12'],
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 3)],
            'departamentos' => ['nullable', 'array', 'min:1', 'max:9'],
            'departamentos.*' => ['required', 'string', 'distinct', Rule::in(self::DASHBOARD_DEPARTMENTS)],
        ]);

        $months = collect($validated['meses'] ?? [$validated['mes']])->map(fn ($month): int => (int) $month)->sort()->values()->all();
        $departments = collect($validated['departamentos'] ?? self::DASHBOARD_DEPARTMENTS)->sort()->values()->all();
        $allDepartments = count($departments) === count(self::DASHBOARD_DEPARTMENTS);
        $groupAmounts = collect();
        $departmentAmounts = collect($departments)->mapWithKeys(fn (string $department): array => [$department => 0.0]);
        $total = 0.0;

        foreach ($allDepartments ? [''] : $departments as $department) {
            $reportRequest = $request->duplicate([
                'meses' => $months,
                'anio' => (int) $validated['anio'],
                'departamento' => $department,
            ]);
            $data = $this->buildCashierFlowData($reportRequest, false);
            if (collect($data['errors'] ?? [])->isNotEmpty()) {
                return response()->json(['message' => 'No se pudo consultar el importe ingresado para los filtros elegidos.'], 503);
            }

            $reportAmount = (float) ($data['summary']['totalRecaudado'] ?? 0);
            $total += $reportAmount;
            if ($allDepartments) {
                foreach ($data['services'] as $service) {
                    foreach ($service['_porRegionales'] ?? [] as $regionalRow) {
                        $regionalRow = (array) $regionalRow;
                        $regionalDepartment = $this->departmentNameFromRegionalRow($regionalRow);
                        if ($departmentAmounts->has($regionalDepartment)) {
                            $departmentAmounts->put(
                                $regionalDepartment,
                                (float) $departmentAmounts->get($regionalDepartment)
                                    + (float) ($regionalRow['totalMontoVendido'] ?? $regionalRow['totalMonto'] ?? 0)
                            );
                        }
                    }
                }
            } else {
                $departmentAmounts->put($department, $reportAmount);
            }
            foreach ($data['serviceGroups'] as $group) {
                $name = (string) $group['servicio'];
                $groupAmounts->put($name,
                    (float) $groupAmounts->get($name, 0)
                    + (float) ($group['totalMontoVendido'] ?? $group['totalMonto'] ?? 0)
                );
            }
        }

        if ($allDepartments) {
            $unassignedAmount = round($total - (float) $departmentAmounts->sum(), 2);
            if ($unassignedAmount < -0.01) {
                foreach ($departments as $department) {
                    $regionalRequest = $request->duplicate([
                        'meses' => $months,
                        'anio' => (int) $validated['anio'],
                        'departamento' => $department,
                    ]);
                    $regionalData = $this->buildCashierFlowData($regionalRequest, false);
                    if (collect($regionalData['errors'] ?? [])->isNotEmpty()) {
                        return response()->json(['message' => 'No se pudo consultar el desglose regional del importe.'], 503);
                    }
                    $departmentAmounts->put($department, (float) ($regionalData['summary']['totalRecaudado'] ?? 0));
                }
                $unassignedAmount = round($total - (float) $departmentAmounts->sum(), 2);
                if ($unassignedAmount < -0.01) {
                    return response()->json(['message' => 'El desglose regional no concilia con el total del reporte financiero.'], 503);
                }
            }
            if ($unassignedAmount > 0) {
                $departmentAmounts->put('SIN REGIONAL ASIGNADA', $unassignedAmount);
            }
        }

        return response()->json([
            'meses' => $months,
            'anio' => (int) $validated['anio'],
            'departamentos' => $departments,
            'importe_total' => round($total, 2),
            'desglose_departamentos' => $departmentAmounts
                ->map(fn (float $amount, string $department): array => [
                    'departamento' => $department,
                    'importe' => round($amount, 2),
                ])
                ->values()
                ->all(),
            'grupos' => $groupAmounts
                ->map(fn (float $amount, string $name): array => [
                    'servicio' => $name,
                    'importe' => round($amount, 2),
                ])
                ->filter(fn (array $group): bool => $group['importe'] > 0)
                ->sortByDesc('importe')
                ->values()
                ->all(),
        ]);
    }

    public function cashierFlowReport(Request $request)
    {
        if ($request->boolean('stream')) {
            return $this->streamCashierFlowReport($request);
        }

        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '1024M');

        $progressCallback = $request->attributes->get('_cashier_flow_progress_callback');
        $progressCallback = $progressCallback instanceof Closure ? $progressCallback : null;
        $reportProgress = function (int $percent, string $stage, int $completed = 0, int $total = 0) use ($progressCallback): void {
            if ($progressCallback === null) {
                return;
            }

            $progressCallback([
                'type' => 'progress',
                'percent' => min(94, max(0, $percent)),
                'stage' => $stage,
                'completed' => max(0, $completed),
                'total' => max(0, $total),
            ]);
        };

        $reportProgress(3, 'Validando filtros del reporte…');

        $data = $this->buildServicesReportData(
            $request,
            forceOnlyContracts: false,
            reconcileContracts: false,
            excludeContracts: true,
            excludedCashierNames: self::CASHIER_FLOW_EXCLUDED_CASHIERS,
            enableDepartmentFilter: true,
            cacheReports: true,
            showReceivablesSeparately: true,
            includeAnnulledDetails: true,
            loadAllReceivablesOutsideServiceFilter: true,
            progressCallback: function (int $percent, string $stage, int $completed, int $total) use ($reportProgress): void {
                $reportProgress($percent, $stage, $completed, $total);
            }
        );
        $reportProgress(82, 'Revisando facturas anuladas y pagos…');
        $auditServices = collect($data['services'])->concat($data['receivableServices']);
        $hasCompleteOptimizedAudit = collect($data['annulledDetailErrors'] ?? [])->isEmpty();
        if ($hasCompleteOptimizedAudit) {
            $invoiceAudit = $this->cashierFlowAuditFromServices(
                $auditServices,
                $data['anio'],
                self::CASHIER_FLOW_EXCLUDED_CASHIERS,
                $data['selectedDepartment']
            );
        } else {
            $invoiceAudit = $this->loadCashierFlowCancelledInvoices(
                $this->cashierFlowInvoiceDetailFilters($auditServices, $data['selectedMonths'], $data['anio']),
                $auditServices,
                $data['selectedDepartment']
            );
        }
        $reportProgress(87, 'Cuadrando los importes de las facturas…');
        $periodCancelledInvoices = $invoiceAudit['invoices'];
        $summaryCancelledInvoices = $periodCancelledInvoices
            ->reject(fn (array $invoice): bool => $this->isCashierFlowReceivableService((string) $invoice['_servicio']));
        $data['services'] = $this->subtractCancelledInvoicesFromServices(
            collect($data['services']),
            $summaryCancelledInvoices,
            $invoiceAudit['nonCancelledAmounts'],
            $invoiceAudit['completeServices']
        );
        $data['summary']['totalMontoAnulado'] = (float) $summaryCancelledInvoices->sum('monto');
        $cancelledByService = $summaryCancelledInvoices
            ->groupBy('_servicio')
            ->map(fn (Collection $invoices): float => (float) $invoices->sum('monto'));
        $data['services'] = collect($data['services'])->map(function (array $service) use ($cancelledByService): array {
            $annulledAmount = (float) $cancelledByService->get((string) ($service['servicio'] ?? ''), 0);
            $service['totalMontoAnulado'] = $annulledAmount;
            $service['totalMontoNoIncluidoEnTotalVendido'] = (float) ($service['totalMontoNoIncluidoEnTotalVendido'] ?? 0)
                + $annulledAmount;

            return $service;
        })->values();
        $data['summary']['totalMonto'] = (float) $data['services']->sum('totalMonto');
        // El conteo financiero ya excluye facturas y pagos anulados.
        if (($data['uniqueSalesCountFromApi'] ?? false) && ! ($data['includedSalesCountFromApi'] ?? false)) {
            $cancelledPaidSaleIds = $summaryCancelledInvoices
                ->filter(function (array $invoice): bool {
                    $fiscalStatus = $this->normalizePersonName((string) ($invoice['estadoFiscal'] ?? ''));
                    $paymentStatus = $this->normalizePersonName((string) ($invoice['estadoPago'] ?? ''));
                    $isFiscalAnnulled = in_array($fiscalStatus, ['ANULADA', 'ANULADO'], true);
                    $isPaymentCancelled = in_array($paymentStatus, ['ANULADA', 'ANULADO', 'CANCELADA', 'CANCELADO'], true);

                    return ! $isFiscalAnnulled && $isPaymentCancelled;
                })
                ->pluck('_ventaId')
                ->filter()
                ->unique()
                ->count();
            $data['summary']['cantidadVentas'] = max(
                0,
                (float) $data['summary']['cantidadVentas'] - $cancelledPaidSaleIds
            );
        } elseif (! ($data['uniqueSalesCountFromApi'] ?? false)) {
            $data['summary']['cantidadVentas'] = (float) $data['services']->sum('cantidadVentas');
        }
        $data['summary']['cantidadDetalles'] = (float) $data['services']->sum('cantidadDetalles');
        $data['summary']['totalCantidad'] = (float) $data['services']->sum('totalCantidad');
        $data['summary']['totalMontoVendido'] = (float) $data['services']->sum('totalMontoVendido');
        $data['summary']['totalMontoNoIncluidoEnTotalVendido'] = (float) $data['services']->sum('totalMontoNoIncluidoEnTotalVendido');
        $data['summary']['totalMontoAnulado'] = (float) $data['services']->sum('totalMontoAnulado');
        $allCollectedMovements = Schema::hasTable('cashier_flow_receivable_movements')
            ? $this->cashierFlowCollectedMovementsForPeriod($data['selectedMonths'], $data['anio'])
            : collect();
        $collectedMovements = $data['selectedDepartment'] === '' ? $allCollectedMovements : collect();
        $reportProgress(89, 'Validando cobros registrados…');
        $collectionAudit = $this->loadCashierFlowCancelledInvoices(
            $this->cashierFlowMovementDetailFilters($allCollectedMovements)
        );
        $cancelledMovementKeys = $collectionAudit['invoices']->pluck('_movementKey')->filter()->flip();
        if ($cancelledMovementKeys->isNotEmpty()) {
            $allCollectedMovements = $allCollectedMovements
                ->reject(fn (CashierFlowReceivableMovement $movement): bool => $cancelledMovementKeys->has($movement->movement_key))
                ->values();
        }
        $collectedMovements = $data['selectedDepartment'] === '' ? $allCollectedMovements : collect();
        $data['cashierFlowCancelledInvoices'] = $periodCancelledInvoices
            ->concat($collectionAudit['invoices'])
            ->unique(fn (array $invoice): string => filled($invoice['_movementKey'] ?? null)
                ? (string) $invoice['_movementKey']
                : (string) $invoice['_dedupeKey'])
            ->sortByDesc('fecha')
            ->values();
        $data['cashierFlowCancellationLookupErrors'] = $invoiceAudit['errors']
            ->concat($collectionAudit['errors'])
            ->unique()
            ->values();
        $reportProgress(91, 'Separando el saldo pendiente por departamento…');
        $receivableDepartmentBreakdown = $this->cashierFlowReceivableDepartmentBreakdown(
            collect($data['receivableServices'] ?? []),
            $data['selectedMonths'],
            $data['anio'],
            $data['selectedDepartment'] === '' ? $allCollectedMovements : collect(),
            $data['selectedDepartment']
        );
        $data['cashierFlowReceivableDepartmentErrors'] = $receivableDepartmentBreakdown['errors'];
        $data['cashierFlowReceivableDepartmentAmounts'] = $receivableDepartmentBreakdown['pendingByDepartment'];
        $data['cashierFlowReceivableInvoicers'] = $receivableDepartmentBreakdown['invoicers'];
        $data['cashierFlowCollectedMovements'] = $collectedMovements;
        $data['cashierFlowCollectedAmount'] = (float) $collectedMovements->sum('monto');
        $receivableCollections = $collectedMovements->groupBy('servicio');
        $data['receivableServices'] = collect($data['receivableServices'] ?? [])
            ->map(function (array $service) use ($receivableCollections, $receivableDepartmentBreakdown): array {
                $serviceCollections = $receivableCollections->get((string) ($service['servicio'] ?? ''), collect());
                $serviceName = (string) ($service['servicio'] ?? '');
                if ($receivableDepartmentBreakdown['complete']) {
                    $service['totalMonto'] = (float) $receivableDepartmentBreakdown['billedByService']->get($serviceName, $service['totalMonto'] ?? 0);
                }
                $service['_montoCobrado'] = $receivableDepartmentBreakdown['complete']
                    ? (float) $receivableDepartmentBreakdown['collectedByService']->get($serviceName, 0)
                    : (float) $serviceCollections->sum('monto');
                $service['_montoPendiente'] = $receivableDepartmentBreakdown['complete']
                    ? (float) $receivableDepartmentBreakdown['pendingByService']->get($serviceName, 0)
                    : max(0, (float) ($service['totalMonto'] ?? 0) - $service['_montoCobrado']);
                $service['_cobroRealizado'] = $service['_montoCobrado'] > 0;

                return $service;
            })
            ->values();
        $collectedServiceRows = $this->buildCollectedReceivableServiceRows(
            $collectedMovements->groupBy('servicio')
        );
        $data['serviceGroups'] = $this->buildCashierFlowServiceGroups(
            collect($data['services']),
            collect($data['receivableServices']),
            $collectedServiceRows,
            collect($receivableDepartmentBreakdown['collectedQrByService'] ?? [])
        );
        $data['serviceGroups'] = $this->alignCashierFlowVentanillasSalesCount(
            $data['serviceGroups'],
            (float) ($data['summary']['cantidadVentas'] ?? 0)
        );
        $data['summary']['cantidadServicios'] = $data['serviceGroups']->count();
        $data['cashierFlowReceivablePendingAmount'] = (float) $data['receivableServices']->sum('_montoPendiente');
        $data['summary']['totalRecaudado'] = (float) $data['serviceGroups']->sum(
            fn (array $group): float => (float) ($group['totalMontoRecaudado'] ?? 0)
        );
        $ventanillasGroup = $data['serviceGroups']->first(
            fn (array $group): bool => ($group['servicio'] ?? '') === 'VENTANILLAS'
        );
        $data['summary']['totalSinContratosEca'] = (float) ($ventanillasGroup['totalMontoRecaudado']
            ?? $data['summary']['totalMontoVendido']
            ?? $data['summary']['totalMonto']
            ?? 0);
        $data['totalReportAmountIncludingReceivables'] = $data['summary']['totalRecaudado']
            + $data['cashierFlowReceivablePendingAmount'];
        $data['totalReportIncome'] = $data['summary']['totalRecaudado'];
        $data['cashierFlowCollectionsOmittedByDepartment'] = $data['selectedDepartment'] !== '';
        $data['cashierRows'] = $this->addCashierReceivableIncome(
            $this->buildCashierBreakdown($data['services']),
            $collectedMovements
        );
        $data['cashierRows'] = $this->addCashierFlowReceivableInvoicers(
            $data['cashierRows'],
            $data['cashierFlowReceivableInvoicers'] ?? collect()
        );
        $data['cashierRows'] = $this->addCashierPeriodAverages(
            $data['cashierRows'],
            $data['selectedMonths'],
            $data['anio']
        );
        $data['cashierRows'] = $this->addCashierFlowPaymentBreakdown(
            $data['cashierRows'],
            $invoiceAudit['paymentBreakdown'],
            $collectedMovements,
            $invoiceAudit['movementPaymentMethods']->merge($collectionAudit['movementPaymentMethods'])
        );
        $data['cashierDepartmentGroups'] = $this->addReceivablesToCashierDepartmentGroups(
            $this->buildCashierDepartmentGroups($data['cashierRows']),
            $data['cashierFlowReceivableDepartmentAmounts'],
            $this->countReportDaysExcludingSundays($data['selectedMonths'], $data['anio'])
        );
        $departmentSalesAlignment = $this->alignCashierFlowDepartmentSalesCounts(
            $data['cashierDepartmentGroups'],
            $data
        );
        $data['cashierDepartmentGroups'] = $departmentSalesAlignment['groups'];
        $data['cashierFlowDepartmentSalesCountWarning'] = $departmentSalesAlignment['warning'];
        $data['generatedAt'] = now();
        $data['periodLabel'] = collect($data['selectedMonths'])
            ->map(fn (int $month) => [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ][$month] ?? (string) $month)
            ->implode(', ').' de '.$data['anio'];

        $reportDays = $this->countReportDaysExcludingSundays($data['selectedMonths'], $data['anio']);
        $data['averageDailyIncome'] = $reportDays > 0 ? $data['totalReportAmountIncludingReceivables'] / $reportDays : 0.0;
        $data['topCashier'] = $data['cashierRows']->first();
        $data['topService'] = collect($data['serviceGroups'])
            ->flatMap(fn (array $group) => ($group['servicio'] ?? '') === 'VENTANILLAS'
                ? collect($group['_children'] ?? [])
                : collect([$group]))
            ->sortByDesc(fn (array $group) => $group['totalMontoVendido'] ?? $group['totalMonto'] ?? 0)
            ->first();

        $reportProgress(94, 'Preparando y dibujando el PDF…');

        return Pdf::loadView('financial-reports.cashier-flow-pdf', $data)
            ->setPaper('A4', 'portrait')
            ->download('reporte-flujo-cajero-'.$data['anio'].'-'.now()->format('Ymd_His').'.pdf');
    }

    private function streamCashierFlowReport(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '1024M');
        @ini_set('zlib.output_compression', '0');

        return response()->stream(function () use ($request): void {
            $send = function (array $message): void {
                echo json_encode($message, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $send([
                'type' => 'progress',
                'percent' => 2,
                'stage' => 'Iniciando las consultas del reporte…',
                'completed' => 0,
                'total' => 0,
            ]);

            $auditConnection = null;
            $auditContext = [];
            try {
                try {
                    if (DB::getDriverName() === 'pgsql') {
                        $user = $request->user();
                        $auditConnection = DB::connection();
                        $auditContext = [
                            'app.audit_user_id' => $user?->getAuthIdentifier(),
                            'app.audit_user_name' => $user?->name,
                            'app.audit_user_alias' => $user?->alias,
                            'app.audit_request_ip' => $request->ip(),
                            'app.audit_user_agent' => $request->userAgent(),
                        ];
                        $assignments = [];
                        $bindings = [];
                        foreach ($auditContext as $key => $value) {
                            $assignments[] = "set_config('{$key}', ?, false)";
                            $bindings[] = (string) ($value ?? '');
                        }
                        $auditConnection->select('SELECT '.implode(', ', $assignments), $bindings);
                    }
                } catch (\Throwable) {
                    $auditConnection = null;
                    $auditContext = [];
                }

                $reportRequest = clone $request;
                $reportRequest->query->remove('stream');
                $reportRequest->attributes->set('_cashier_flow_progress_callback', $send);

                $pdfResponse = $this->cashierFlowReport($reportRequest);
                if (! method_exists($pdfResponse, 'getContent')) {
                    throw new \RuntimeException('La respuesta PDF no contiene datos descargables.');
                }

                $pdfContent = $pdfResponse->getContent();
                if (! is_string($pdfContent) || $pdfContent === '') {
                    throw new \RuntimeException('No se pudo leer el PDF generado.');
                }

                $disposition = (string) $pdfResponse->headers->get('Content-Disposition', '');
                $fileName = preg_match('/filename\s*=\s*"?([^";]+)"?/i', $disposition, $matches)
                    ? trim($matches[1])
                    : 'reporte-flujo-cajero.pdf';
                $encodedPdf = base64_encode($pdfContent);
                $encodedLength = strlen($encodedPdf);
                $chunkLength = 65536;

                $send([
                    'type' => 'download-start',
                    'percent' => 94,
                    'fileName' => $fileName,
                    'total' => $encodedLength,
                ]);

                for ($offset = 0; $offset < $encodedLength; $offset += $chunkLength) {
                    $chunk = substr($encodedPdf, $offset, $chunkLength);
                    $send([
                        'type' => 'pdf-chunk',
                        'data' => $chunk,
                        'loaded' => min($encodedLength, $offset + strlen($chunk)),
                        'total' => $encodedLength,
                    ]);
                }

                $send(['type' => 'complete', 'percent' => 100]);
            } catch (\Throwable $exception) {
                report($exception);
                $send([
                    'type' => 'error',
                    'message' => 'No se pudo completar el PDF. Revise los filtros e inténtelo nuevamente.',
                ]);
            } finally {
                if ($auditConnection !== null && $auditContext !== []) {
                    try {
                        $clear = array_map(fn (string $key): string => "set_config('{$key}', '', false)", array_keys($auditContext));
                        $auditConnection->select('SELECT '.implode(', ', $clear));
                    } catch (\Throwable) {
                        // El flujo del reporte no debe fallar por la limpieza del contexto de auditoria.
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'application/x-ndjson; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function cashierFlowInvoiceDetailFilters(Collection $services, array $months, int $year): array
    {
        return $services
            ->pluck('servicio')
            ->map(fn ($service): string => trim((string) $service))
            ->filter()
            ->unique()
            ->flatMap(fn (string $service) => collect($months)
                ->map(fn ($month): array => [
                    'servicio' => $service,
                    'mes' => (int) $month,
                    'anio' => $year,
                ]))
            ->values()
            ->all();
    }

    private function cashierFlowMovementDetailFilters(Collection $movements): array
    {
        return $movements
            ->map(fn (CashierFlowReceivableMovement $movement): array => [
                'servicio' => (string) $movement->servicio,
                'mes' => (int) $movement->mes,
                'anio' => (int) $movement->anio,
            ])
            ->unique(fn (array $filter): string => $filter['servicio'].'|'.$filter['mes'].'|'.$filter['anio'])
            ->values()
            ->all();
    }

    private function cashierFlowCancelledInvoicesFromServices(
        Collection $services,
        int $year,
        array $excludedCashierNames = self::CASHIER_FLOW_EXCLUDED_CASHIERS,
        string $selectedDepartment = ''
    ): Collection {
        $rows = $services
            ->flatMap(function (array $service): Collection {
                $serviceName = trim((string) ($service['servicio'] ?? ''));

                return collect($service['_annulledRows'] ?? [])->map(fn ($row): array => [
                    ...(array) $row,
                    '_servicio' => $serviceName,
                    '_mes' => (int) ($row['_mes'] ?? 0),
                ]);
            })
            ->values();

        return $this->attachCashierFlowMovementKeys($rows, $year)
            ->filter(function (array $row) use ($excludedCashierNames, $selectedDepartment, $services): bool {
                if (! $this->isCancelledCashierFlowInvoice($row)) {
                    return false;
                }
                if ($excludedCashierNames !== [] && $this->isCashierFlowExcludedInvoice($row, $excludedCashierNames)) {
                    return false;
                }

                return $selectedDepartment === '' || $this->cashierFlowDetailRowMatchesDepartment(
                    $row,
                    (string) ($row['_servicio'] ?? ''),
                    $services,
                    $selectedDepartment
                );
            })
            ->map(function (array $row) use ($year): array {
                $invoiceUser = (array) ($row['usuario'] ?? []);
                $userId = trim((string) ($invoiceUser['id'] ?? $row['usuarioId'] ?? ''));
                $userName = trim((string) ($invoiceUser['nombre'] ?? $invoiceUser['name'] ?? $row['usuarioNombre'] ?? ''));
                $userEmail = trim((string) ($invoiceUser['email'] ?? $row['usuarioEmail'] ?? ''));
                $userAlias = trim((string) ($invoiceUser['alias'] ?? $row['usuarioAlias'] ?? ''));
                $saleId = trim((string) ($row['ventaId'] ?? ''));
                $detailId = trim((string) ($row['detalleId'] ?? ''));
                $amount = round((float) ($row['totalLinea'] ?? $row['monto'] ?? 0), 2);
                $serviceName = (string) ($row['_servicio'] ?? '');
                $month = (int) ($row['_mes'] ?? 0);

                return [
                    '_servicio' => $serviceName,
                    '_movementKey' => trim((string) ($row['_movementKey'] ?? '')),
                    '_dedupeKey' => hash('sha256', implode('|', [$serviceName, $month, $year, $saleId, $detailId, $amount])),
                    '_cashierIdentity' => $this->cashierIdentity($userId, $userEmail, $userAlias, $userName),
                    '_usuarioId' => $userId,
                    '_usuarioEmail' => $userEmail,
                    '_usuarioAlias' => $userAlias,
                    '_departamento' => $this->firstReportTextValue($row['regional'] ?? null),
                    '_ventaId' => $saleId,
                    '_detalleId' => $detailId,
                    '_cantidad' => round((float) ($row['cantidad'] ?? 0), 2),
                    'servicio' => $serviceName,
                    'fecha' => trim((string) ($row['fecha'] ?? '')),
                    'venta' => $saleId !== '' ? $saleId : '-',
                    'detalle' => $detailId !== '' ? $detailId : '-',
                    'facturadoPor' => $userName !== '' ? $userName : ($userAlias !== '' ? $userAlias : ($userId !== '' ? $userId : 'Sin dato')),
                    'medioPago' => trim((string) ($row['medioPago'] ?? $row['medio_pago'] ?? '')),
                    'estadoFiscal' => trim((string) ($row['estadoFiscal'] ?? $row['estado_fiscal'] ?? '')),
                    'estadoPago' => trim((string) ($row['estadoPago'] ?? $row['estado_pago'] ?? '')),
                    'monto' => $amount,
                ];
            })
            ->values();
    }

    private function cashierFlowAuditFromServices(
        Collection $services,
        int $year,
        array $excludedCashierNames = self::CASHIER_FLOW_EXCLUDED_CASHIERS,
        string $selectedDepartment = ''
    ): array {
        $invoices = $this->cashierFlowCancelledInvoicesFromServices(
            $services,
            $year,
            $excludedCashierNames,
            $selectedDepartment
        );
        $nonCancelledAmounts = collect();
        $paymentBreakdown = collect();
        $completeServices = collect();

        foreach ($services as $service) {
            $serviceName = trim((string) ($service['servicio'] ?? ''));
            if ($serviceName === '') {
                continue;
            }

            $serviceAmount = 0.0;
            foreach ((array) ($service['_paymentAuditRows'] ?? []) as $auditRow) {
                $auditRow = (array) $auditRow;
                $user = [
                    'id' => $auditRow['usuarioId'] ?? '',
                    'nombre' => $auditRow['usuarioNombre'] ?? '',
                    'email' => $auditRow['usuarioEmail'] ?? '',
                    'alias' => $auditRow['usuarioAlias'] ?? '',
                ];
                if ($excludedCashierNames !== [] && $this->isCashierFlowExcludedInvoice(
                    ['usuario' => $user],
                    $excludedCashierNames
                )) {
                    continue;
                }

                $rowAmount = (float) ($auditRow['totalMonto'] ?? 0);
                $serviceAmount += $rowAmount;
                if ($this->isCashierFlowReceivableService($serviceName)) {
                    continue;
                }

                $identity = $this->cashierIdentity(
                    (string) ($user['id'] ?? ''),
                    (string) ($user['email'] ?? ''),
                    (string) ($user['alias'] ?? ''),
                    (string) ($user['nombre'] ?? '')
                );
                $cashierMethods = $paymentBreakdown->get($identity, []);
                foreach (['qr', 'efectivo', 'otros'] as $method) {
                    $amount = (float) data_get($auditRow, "porMedioPago.{$method}", 0);
                    if ($amount === 0.0) {
                        continue;
                    }

                    $methodStats = $cashierMethods[$method] ?? ['monto' => 0.0];
                    $methodStats['monto'] += $amount;
                    $cashierMethods[$method] = $methodStats;
                }
                $paymentBreakdown->put($identity, $cashierMethods);
            }

            $nonCancelledAmounts->put($serviceName, $serviceAmount);
            $completeServices->put($serviceName, true);
        }

        return [
            'invoices' => $invoices,
            'errors' => collect(),
            'nonCancelledAmounts' => $nonCancelledAmounts,
            'completeServices' => $completeServices,
            'paymentBreakdown' => $paymentBreakdown,
            'movementPaymentMethods' => collect(),
        ];
    }

    private function loadCashierFlowCancelledInvoices(
        array $filters,
        ?Collection $departmentServices = null,
        string $selectedDepartment = '',
        array $excludedCashierNames = self::CASHIER_FLOW_EXCLUDED_CASHIERS,
        bool $refresh = false
    ): array
    {
        if ($filters === []) {
            return [
                'invoices' => collect(),
                'errors' => collect(),
                'nonCancelledAmounts' => collect(),
                'completeServices' => collect(),
                'paymentBreakdown' => collect(),
                'movementPaymentMethods' => collect(),
            ];
        }

        try {
            $results = $this->reports->serviceDetailsBatch($filters, true, $refresh);
        } catch (\Throwable $exception) {
            return [
                'invoices' => collect(),
                'errors' => collect([$exception->getMessage()]),
                'nonCancelledAmounts' => collect(),
                'completeServices' => collect(),
                'paymentBreakdown' => collect(),
                'movementPaymentMethods' => collect(),
            ];
        }

        $invoices = collect();
        $errors = collect();
        $nonCancelledAmounts = collect();
        $paymentBreakdown = collect();
        $movementPaymentMethods = collect();
        $successfulServiceMonths = collect();
        foreach ($results as $result) {
            $filter = (array) ($result['filter'] ?? []);
            if (($result['error'] ?? null) !== null || ! is_array($result['report'] ?? null)) {
                $errorMessage = (string) ($result['error'] ?? 'No se pudo consultar el detalle del servicio.');
                $serviceName = trim((string) ($filter['servicio'] ?? 'Servicio sin nombre'));
                $month = (int) ($filter['mes'] ?? 0);
                $year = (int) ($filter['anio'] ?? 0);
                $errors->push("{$serviceName} / {$month}-{$year}: {$errorMessage}");
                Log::warning('Falló la auditoría detallada de facturación para el flujo de caja.', [
                    'servicio' => $serviceName,
                    'mes' => $month,
                    'anio' => $year,
                    'error' => $errorMessage,
                ]);

                continue;
            }

            $serviceName = trim((string) ($filter['servicio'] ?? ''));
            $month = (int) ($filter['mes'] ?? 0);
            $year = (int) ($filter['anio'] ?? 0);
            $successfulServiceMonths->push($serviceName.'|'.$month);
            $rows = $this->attachCashierFlowMovementKeys(
                collect(data_get($result, 'report.servicio.rows', []))->map(fn ($row): array => [
                    ...(array) $row,
                    '_servicio' => $serviceName,
                    '_mes' => $month,
                ]),
                $year
            );

            foreach ($rows as $row) {
                if ($excludedCashierNames !== [] && $this->isCashierFlowExcludedInvoice($row, $excludedCashierNames)) {
                    continue;
                }
                if ($selectedDepartment !== ''
                    && ! $this->cashierFlowDetailRowMatchesDepartment(
                        $row,
                        $serviceName,
                        $departmentServices ?? collect(),
                        $selectedDepartment
                    )) {
                    continue;
                }

                $invoiceUser = (array) ($row['usuario'] ?? []);
                $userId = trim((string) ($invoiceUser['id'] ?? $row['usuarioId'] ?? ''));
                $userName = trim((string) ($invoiceUser['nombre'] ?? $invoiceUser['name'] ?? $row['usuarioNombre'] ?? ''));
                $userEmail = trim((string) ($invoiceUser['email'] ?? $row['usuarioEmail'] ?? ''));
                $userAlias = trim((string) ($invoiceUser['alias'] ?? $row['usuarioAlias'] ?? ''));
                $saleId = trim((string) ($row['ventaId'] ?? ''));
                $detailId = trim((string) ($row['detalleId'] ?? ''));
                $rowAmount = (float) ($row['totalLinea'] ?? $row['monto'] ?? 0);
                $amount = round($rowAmount, 2);
                $movementKey = trim((string) ($row['_movementKey'] ?? ''));

                if (! $this->isCancelledCashierFlowInvoice($row)) {
                    $nonCancelledAmounts->put(
                        $serviceName,
                        (float) $nonCancelledAmounts->get($serviceName, 0)
                            + $rowAmount
                    );

                    $paymentMethod = $this->cashierFlowPaymentMethod($row);
                    if ($this->isCashierFlowReceivableService($serviceName)) {
                        if ($movementKey !== '') {
                            $movementPaymentMethods->put($movementKey, $paymentMethod);
                        }
                    } else {
                        $cashierIdentity = $this->cashierIdentity($userId, $userEmail, $userAlias, $userName);
                        $cashierMethods = $paymentBreakdown->get($cashierIdentity, []);
                        $methodStats = $cashierMethods[$paymentMethod] ?? ['monto' => 0.0];
                        $methodStats['monto'] += $amount;
                        $cashierMethods[$paymentMethod] = $methodStats;
                        $paymentBreakdown->put($cashierIdentity, $cashierMethods);
                    }

                    continue;
                }

                $invoices->push([
                    '_servicio' => $serviceName,
                    '_movementKey' => $movementKey,
                    '_dedupeKey' => hash('sha256', implode('|', [$serviceName, $month, $year, $saleId, $detailId, $amount])),
                    '_cashierIdentity' => $this->cashierIdentity($userId, $userEmail, $userAlias, $userName),
                    '_usuarioId' => $userId,
                    '_usuarioEmail' => $userEmail,
                    '_usuarioAlias' => $userAlias,
                    '_departamento' => $this->firstReportTextValue(
                        $row['departamento'] ?? null,
                        $row['regional'] ?? null,
                        $invoiceUser['departamento'] ?? null,
                        $invoiceUser['regional'] ?? null
                    ),
                    '_ventaId' => $saleId,
                    '_detalleId' => $detailId,
                    '_cantidad' => round((float) ($row['cantidad'] ?? 0), 2),
                    'servicio' => $serviceName,
                    'fecha' => trim((string) ($row['fecha'] ?? '')),
                    'venta' => $saleId !== '' ? $saleId : '-',
                    'detalle' => $detailId !== '' ? $detailId : '-',
                    'facturadoPor' => $userName !== '' ? $userName : ($userAlias !== '' ? $userAlias : ($userId !== '' ? $userId : 'Sin dato')),
                    'medioPago' => trim((string) ($row['medioPago'] ?? $row['medio_pago'] ?? '')),
                    'estadoFiscal' => trim((string) ($row['estadoFiscal'] ?? $row['estado_fiscal'] ?? '')),
                    'estadoPago' => trim((string) ($row['estadoPago'] ?? $row['estado_pago'] ?? '')),
                    'monto' => $amount,
                ]);
            }
        }

        $expectedMonthsByService = collect($filters)
            ->groupBy(fn (array $filter): string => trim((string) ($filter['servicio'] ?? '')))
            ->map(fn (Collection $serviceFilters): int => $serviceFilters->pluck('mes')->map(fn ($month): int => (int) $month)->unique()->count());
        $completeServices = $expectedMonthsByService->map(
            fn (int $expectedMonths, string $serviceName): bool => $successfulServiceMonths
                ->filter(fn (string $key): bool => str_starts_with($key, $serviceName.'|'))
                ->unique()
                ->count() === $expectedMonths
        );

        return [
            'invoices' => $invoices,
            'errors' => $errors,
            'nonCancelledAmounts' => $nonCancelledAmounts,
            'completeServices' => $completeServices,
            'paymentBreakdown' => $paymentBreakdown,
            'movementPaymentMethods' => $movementPaymentMethods,
        ];
    }

    private function cashierFlowPaymentMethod(array $row): string
    {
        $method = $this->normalizePersonName($this->firstReportTextValue(
            $row['medioPago'] ?? null,
            $row['medio_pago'] ?? null,
            $row['metodoPago'] ?? null,
            $row['metodo_pago'] ?? null
        ));

        if (str_contains($method, 'QR')) {
            return 'qr';
        }
        if (str_contains($method, 'EFECTIVO')) {
            return 'efectivo';
        }

        return 'otros';
    }

    private function isCancelledCashierFlowInvoice(array $row): bool
    {
        $fiscalStatus = $this->normalizePersonName((string) ($row['estadoFiscal'] ?? $row['estado_fiscal'] ?? ''));
        $paymentStatus = $this->normalizePersonName((string) ($row['estadoPago'] ?? $row['estado_pago'] ?? ''));
        $cancelledStatuses = ['ANULADA', 'ANULADO'];

        return in_array($fiscalStatus, $cancelledStatuses, true)
            || in_array($paymentStatus, [...$cancelledStatuses, 'CANCELADA', 'CANCELADO'], true);
    }

    private function isCashierFlowExcludedInvoice(array $row, array $excludedCashierNames): bool
    {
        $invoiceUser = (array) ($row['usuario'] ?? []);
        $name = $this->normalizePersonName((string) (
            $invoiceUser['nombre'] ?? $invoiceUser['name'] ?? $row['usuarioNombre'] ?? ''
        ));

        return collect($excludedCashierNames)
            ->map(fn (string $excludedName): string => $this->normalizePersonName($excludedName))
            ->contains(function (string $excludedName) use ($name): bool {
                $tokens = array_filter(explode(' ', $excludedName));

                return $name !== ''
                    && $tokens !== []
                    && collect($tokens)->every(fn (string $token): bool => str_contains($name, $token));
            });
    }

    private function cashierFlowDetailRowMatchesDepartment(
        array $row,
        string $serviceName,
        Collection $services,
        string $department
    ): bool
    {
        $rowDepartment = $this->firstReportTextValue(
            $row['departamento'] ?? null,
            $row['regional'] ?? null,
            $row['regionalNombre'] ?? null
        );
        if ($rowDepartment !== '') {
            return $this->sameNormalizedText($this->canonicalDepartmentName($rowDepartment), $department);
        }
        if (isset($row['codigosSucursal']) && is_array($row['codigosSucursal'])) {
            return $this->sameNormalizedText($this->departmentNameFromRegionalRow($row), $department);
        }

        $service = $services->first(
            fn (array $candidate): bool => (string) ($candidate['servicio'] ?? '') === $serviceName
        );
        if (! $service) {
            return false;
        }

        $invoiceUser = (array) ($row['usuario'] ?? []);
        $invoicePerson = [
            'usuarioId' => $invoiceUser['id'] ?? $row['usuarioId'] ?? '',
            'usuarioNombre' => $invoiceUser['nombre'] ?? $invoiceUser['name'] ?? $row['usuarioNombre'] ?? '',
            'usuarioEmail' => $invoiceUser['email'] ?? $row['usuarioEmail'] ?? '',
            'usuarioAlias' => $invoiceUser['alias'] ?? $row['usuarioAlias'] ?? '',
        ];
        $people = collect($service['_porPersonas'] ?? [])
            ->map(fn ($person): array => (array) $person)
            ->keyBy(fn (array $person): string => $this->cashierIdentity(
                (string) ($person['usuarioId'] ?? ''),
                (string) ($person['usuarioEmail'] ?? ''),
                (string) ($person['usuarioAlias'] ?? ''),
                (string) ($person['usuarioNombre'] ?? '')
            ));
        $personKey = $people->has($this->cashierIdentity(
            (string) $invoicePerson['usuarioId'],
            (string) $invoicePerson['usuarioEmail'],
            (string) $invoicePerson['usuarioAlias'],
            (string) $invoicePerson['usuarioNombre']
        ))
            ? $this->cashierIdentity(
                (string) $invoicePerson['usuarioId'],
                (string) $invoicePerson['usuarioEmail'],
                (string) $invoicePerson['usuarioAlias'],
                (string) $invoicePerson['usuarioNombre']
            )
            : $this->matchingCashierRowKey($people, $invoicePerson);
        if ($personKey === null) {
            return false;
        }

        $person = $people->get($personKey);
        $departments = collect($person['_departamentos'] ?? $person['departamentos'] ?? []);
        if ($departments->isEmpty() && filled($person['departamento'] ?? null)) {
            $departments = collect(explode(',', (string) $person['departamento']));
        }

        return $departments->contains(
            fn ($personDepartment): bool => $this->sameNormalizedText(
                $this->canonicalDepartmentName((string) $personDepartment),
                $department
            )
        );
    }

    private function subtractCancelledInvoicesFromServices(
        Collection $services,
        Collection $cancelledInvoices,
        Collection $nonCancelledAmounts,
        Collection $completeServices
    ): Collection
    {
        $cancelledByService = $cancelledInvoices->groupBy('_servicio');

        return $services->map(function (array $service) use ($cancelledByService, $nonCancelledAmounts, $completeServices): array {
            $cancelled = $cancelledByService->get((string) ($service['servicio'] ?? ''), collect());
            $serviceName = (string) ($service['servicio'] ?? '');
            if ($cancelled->isEmpty() || ! $completeServices->get($serviceName, false)) {
                return $service;
            }

            $reportedAmount = (float) ($service['totalMonto'] ?? 0);
            $nonCancelledAmount = (float) $nonCancelledAmounts->get($serviceName, 0);
            $cancelledAmount = (float) $cancelled->sum('monto');
            $amountToRemove = min($cancelledAmount, max(0, $reportedAmount - $nonCancelledAmount));
            if ($amountToRemove <= 0) {
                return $service;
            }
            $includedShare = $cancelledAmount > 0 ? min(1, $amountToRemove / $cancelledAmount) : 0;
            $includedCancelled = $cancelled->map(function (array $invoice) use ($includedShare): array {
                $invoice['monto'] = (float) $invoice['monto'] * $includedShare;
                $invoice['_cantidad'] = (float) $invoice['_cantidad'] * $includedShare;

                return $invoice;
            });

            $service['totalMonto'] = max(0, $reportedAmount - $amountToRemove);
            $service['totalCantidad'] = max(0, (float) ($service['totalCantidad'] ?? 0) - (float) $includedCancelled->sum('_cantidad'));
            $service['cantidadDetalles'] = max(0, (float) ($service['cantidadDetalles'] ?? 0) - ($includedShare >= 1 ? $cancelled->count() : 0));
            $cancelledSaleCount = $includedShare >= 1
                ? max(1, $cancelled->pluck('_ventaId')->filter()->unique()->count())
                : 0;
            $service['cantidadVentas'] = max(0, (float) ($service['cantidadVentas'] ?? 0) - $cancelledSaleCount);

            $cancelledByCashier = $includedCancelled
                ->groupBy('_cashierIdentity')
                ->map(function (Collection $cashierInvoices): array {
                    $first = $cashierInvoices->first();

                    return [
                        'usuarioId' => $first['_usuarioId'],
                        'usuarioNombre' => $first['facturadoPor'],
                        'usuarioEmail' => $first['_usuarioEmail'],
                        'usuarioAlias' => $first['_usuarioAlias'],
                        'totalMonto' => (float) $cashierInvoices->sum('monto'),
                        'totalCantidad' => (float) $cashierInvoices->sum('_cantidad'),
                        'cantidadDetalles' => (float) ($cashierInvoices->first()['monto'] > 0 ? $cashierInvoices->count() : 0),
                        'cantidadVentas' => (float) ($cashierInvoices->first()['monto'] > 0
                            ? max(1, $cashierInvoices->pluck('_ventaId')->filter()->unique()->count())
                            : 0),
                    ];
                });

            $service['_porPersonas'] = collect($service['_porPersonas'] ?? [])
                ->map(function ($person) use ($cancelledByCashier): array {
                    $person = (array) $person;
                    $personIdentity = $this->cashierIdentity(
                        (string) ($person['usuarioId'] ?? ''),
                        (string) ($person['usuarioEmail'] ?? ''),
                        (string) ($person['usuarioAlias'] ?? ''),
                        (string) ($person['usuarioNombre'] ?? '')
                    );
                    $adjustmentKey = $cancelledByCashier->has($personIdentity)
                        ? $personIdentity
                        : $this->matchingCashierRowKey($cancelledByCashier, $person);
                    $adjustment = $adjustmentKey !== null ? $cancelledByCashier->get($adjustmentKey) : null;
                    if ($adjustment === null) {
                        return $person;
                    }

                    foreach (['totalMonto', 'totalCantidad', 'cantidadDetalles', 'cantidadVentas'] as $field) {
                        $person[$field] = max(0, (float) ($person[$field] ?? 0) - (float) ($adjustment[$field] ?? 0));
                    }

                    return $person;
                })
                ->values()
                ->all();

            return $service;
        });
    }

    private function cashierFlowCollectedMovementsForPeriod(array $months, int $year): Collection
    {
        $months = collect($months)
            ->map(fn ($month): int => (int) $month)
            ->filter(fn (int $month): bool => $month >= 1 && $month <= 12)
            ->unique()
            ->values()
            ->all();
        if ($months === [] || ! Schema::hasTable('cashier_flow_receivable_movements')) {
            return collect();
        }

        $movements = CashierFlowReceivableMovement::query()
            ->where('cobro_activo', true)
            ->whereYear('cobrado_at', $year)
            ->where(function ($query) use ($months): void {
                $query->whereMonth('cobrado_at', $months[0]);
                foreach (array_slice($months, 1) as $month) {
                    $query->orWhereMonth('cobrado_at', $month);
                }
            })
            ->orderBy('cobrado_at')
            ->get();

        return $this->enrichCashierFlowCollectedMovements($movements);
    }

    private function buildCollectedReceivableServiceRows(Collection $collectionsByService): Collection
    {
        return $collectionsByService
            ->map(function (Collection $serviceMovements, string $serviceName): array {
                if ($serviceMovements->isEmpty()) {
                    return [];
                }

                return [
                    'servicio' => $serviceName,
                    'cantidadVentas' => $serviceMovements->pluck('venta_id')->filter()->unique()->count(),
                    'cantidadDetalles' => $serviceMovements->count(),
                    'totalCantidad' => (float) $serviceMovements->sum('cantidad_paquetes'),
                    'totalMonto' => (float) $serviceMovements->sum('monto'),
                    'ultimaFecha' => $serviceMovements
                        ->sortBy('cobrado_at')
                        ->last()?->cobrado_at?->timezone(config('app.timezone'))
                        ->format('Y-m-d H:i:s'),
                    '_meses' => $serviceMovements->pluck('mes')->map(fn ($month): int => (int) $month)->unique()->sort()->values()->all(),
                    '_esCobroReceivable' => true,
                    '_porRegionales' => [],
                    '_porPersonas' => [],
                ];
            })
            ->values();
    }

    private function enrichCashierFlowCollectedMovements(Collection $movements): Collection
    {
        foreach ($movements
            ->filter(fn (CashierFlowReceivableMovement $movement): bool =>
                blank($movement->facturado_por_id)
                || blank($movement->facturado_por_nombre)
                || blank($movement->facturado_por_email)
                || blank($movement->facturado_por_alias)
                || (float) $movement->cantidad_paquetes === 0.0
            )
            ->groupBy(fn (CashierFlowReceivableMovement $movement): string => $movement->servicio.'|'.$movement->mes.'|'.$movement->anio) as $monthMovements) {
            /** @var CashierFlowReceivableMovement $first */
            $first = $monthMovements->first();
            $serviceName = (string) $first->servicio;
            $month = (int) $first->mes;
            $year = (int) $first->anio;

            try {
                $report = $this->reports->serviceDetail($serviceName, $month, $year, true, false);
                $rows = $this->attachCashierFlowMovementKeys(
                    collect(($report['servicio']['rows'] ?? []))->map(fn ($row): array => [
                        ...(array) $row,
                        '_servicio' => $serviceName,
                        '_mes' => $month,
                    ]),
                    $year
                )->keyBy('_movementKey');

                foreach ($monthMovements as $movement) {
                    $row = $rows->get($movement->movement_key);
                    if (! $row) {
                        continue;
                    }
                    $invoiceUser = (array) ($row['usuario'] ?? []);
                    $attributes = [];
                    foreach ([
                        'id' => ['facturado_por_id', 80],
                        'nombre' => ['facturado_por_nombre', 180],
                        'email' => ['facturado_por_email', 180],
                        'alias' => ['facturado_por_alias', 180],
                    ] as $sourceKey => [$column, $maxLength]) {
                        if (blank($movement->{$column}) && filled($invoiceUser[$sourceKey] ?? null)) {
                            $attributes[$column] = mb_substr(trim((string) $invoiceUser[$sourceKey]), 0, $maxLength);
                        }
                    }
                    if ((float) $movement->cantidad_paquetes === 0.0 && isset($row['cantidad'])) {
                        $attributes['cantidad_paquetes'] = round((float) $row['cantidad'], 2);
                    }
                    if ($attributes !== []) {
                        $movement->forceFill($attributes)->save();
                    }
                }
            } catch (\Throwable $exception) {
                $this->logDetailError($exception, $serviceName, $month, $year);
            }
        }

        return $movements->values();
    }

    public function markCashierFlowReceivableCollected(Request $request)
    {
        $validated = $request->validate([
            'servicio_cobro' => ['required', 'string', 'max:180'],
            'meses' => ['required', 'array', 'min:1', 'max:12'],
            'meses.*' => ['required', 'integer', 'distinct', 'between:1,12'],
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 3)],
            'limite' => ['nullable', 'integer', 'between:1,200'],
            'departamento' => ['nullable', 'string', 'max:120'],
            'filtro_servicios' => ['nullable', 'array', 'max:'.self::MAX_SERVICE_FILTER_ITEMS],
            'filtro_servicios.*' => ['string', 'distinct', 'max:180'],
        ]);

        $serviceName = trim((string) $validated['servicio_cobro']);
        abort_unless(
            $this->serviceGroupName($serviceName) === 'Servicio Contratos' || $this->isEcaInternationalService($serviceName),
            404
        );

        $redirectFilters = [
            'servicios' => $validated['filtro_servicios'] ?? [],
            'meses' => collect($validated['meses'])->map(fn ($month): int => (int) $month)->unique()->sort()->values()->all(),
            'anio' => (int) $validated['anio'],
            'limite' => (int) ($validated['limite'] ?? 200),
            'departamento' => $this->canonicalDepartmentName((string) ($validated['departamento'] ?? '')),
        ];

        $backToFlow = fn () => redirect()->route('dashboard.financiera.flujo-cajero', $redirectFilters);
        if (! Schema::hasTable('cashier_flow_receivable_collections')) {
            return $backToFlow()->with('cashierFlowError', 'Falta preparar el registro de cobros. Ejecuta las migraciones de la aplicacion.');
        }

        $months = $redirectFilters['meses'];
        $limit = $redirectFilters['limite'];
        $department = $redirectFilters['departamento'];
        $reportRequest = Request::create('/dir-financiera/flujo-cajero', 'GET', [
            'servicios' => [$serviceName],
            'meses' => $months,
            'anio' => $redirectFilters['anio'],
            'limite' => $limit,
            'departamento' => $department,
        ]);
        $data = $this->buildServicesReportData(
            $reportRequest,
            forceOnlyContracts: false,
            reconcileContracts: false,
            excludeContracts: true,
            excludedCashierNames: [],
            enableDepartmentFilter: true,
            cacheReports: true,
            showReceivablesSeparately: true
        );

        if ($data['errors']->isNotEmpty()) {
            return $backToFlow()->with('cashierFlowError', $data['errors']->first());
        }

        $receivable = $data['receivableServices']->first(
            fn (array $service): bool => (string) ($service['servicio'] ?? '') === $serviceName
        );
        if (! $receivable) {
            return $backToFlow()->with('cashierFlowError', 'No se encontro el servicio por cobrar en el periodo seleccionado. Actualiza los datos e intentalo nuevamente.');
        }

        $amount = round((float) ($receivable['totalMonto'] ?? 0), 2);
        if ($amount <= 0) {
            return $backToFlow()->with('cashierFlowError', 'El servicio no tiene un importe pendiente para registrar como cobrado.');
        }

        $scopeHash = $this->cashierFlowReceivableScopeHash(
            $data['selectedMonths'],
            $data['anio'],
            $data['limite'],
            $data['selectedDepartment'],
            $serviceName
        );
        try {
            $collection = CashierFlowReceivableCollection::query()->firstOrCreate(
                ['scope_hash' => $scopeHash],
                [
                    'servicio' => $serviceName,
                    'anio' => $data['anio'],
                    'meses' => $data['selectedMonths'],
                    'limite' => $data['limite'],
                    'departamento' => $data['selectedDepartment'] !== '' ? $data['selectedDepartment'] : null,
                    'monto_cobrado' => $amount,
                    'cobrado_por' => $request->user()?->id,
                    'cobrado_at' => now(),
                ]
            );
        } catch (QueryException $exception) {
            $collection = CashierFlowReceivableCollection::query()->where('scope_hash', $scopeHash)->first();
            if (! $collection) {
                throw $exception;
            }
        }

        $reactivated = false;
        if (! $collection->wasRecentlyCreated && ! $collection->cobro_activo) {
            $collection->forceFill([
                'monto_cobrado' => $amount,
                'cobrado_por' => $request->user()?->id,
                'cobrado_at' => now(),
                'cobro_activo' => true,
            ])->save();
            $reactivated = true;
        }

        $message = $collection->wasRecentlyCreated || $reactivated
            ? 'Cobro realizado por Bs '.\App\Support\BolivianNumber::format($amount, 2).' y agregado al total recaudado.'
            : 'Este servicio ya estaba marcado como cobrado y permanece en el total recaudado.';

        return $backToFlow()->with('cashierFlowSuccess', $message);
    }

    public function returnCashierFlowReceivableToPending(Request $request)
    {
        $validated = $request->validate([
            'servicio_cobro' => ['required', 'string', 'max:180'],
            'meses' => ['required', 'array', 'min:1', 'max:12'],
            'meses.*' => ['required', 'integer', 'distinct', 'between:1,12'],
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 3)],
            'limite' => ['nullable', 'integer', 'between:1,200'],
            'departamento' => ['nullable', 'string', 'max:120'],
            'filtro_servicios' => ['nullable', 'array', 'max:'.self::MAX_SERVICE_FILTER_ITEMS],
            'filtro_servicios.*' => ['string', 'distinct', 'max:180'],
        ]);

        $serviceName = trim((string) $validated['servicio_cobro']);
        abort_unless(
            $this->serviceGroupName($serviceName) === 'Servicio Contratos' || $this->isEcaInternationalService($serviceName),
            404
        );

        $months = collect($validated['meses'])->map(fn ($month): int => (int) $month)->unique()->sort()->values()->all();
        $limit = (int) ($validated['limite'] ?? 200);
        $department = $this->canonicalDepartmentName((string) ($validated['departamento'] ?? ''));
        $redirectFilters = [
            'servicios' => $validated['filtro_servicios'] ?? [],
            'meses' => $months,
            'anio' => (int) $validated['anio'],
            'limite' => $limit,
            'departamento' => $department,
        ];
        $backToFlow = fn () => redirect()->route('dashboard.financiera.flujo-cajero', $redirectFilters);

        $scopeHash = $this->cashierFlowReceivableScopeHash(
            $months,
            (int) $validated['anio'],
            $limit,
            $department,
            $serviceName
        );
        $collection = Schema::hasTable('cashier_flow_receivable_collections')
            ? CashierFlowReceivableCollection::query()->where('scope_hash', $scopeHash)->first()
            : null;

        if (! $collection || ! $collection->cobro_activo) {
            return $backToFlow()->with('cashierFlowError', 'Este servicio ya esta por cobrar o no tiene un cobro registrado.');
        }

        $collection->forceFill([
            'cobro_activo' => false,
            'devuelto_at' => now(),
            'devuelto_por' => $request->user()?->id,
        ])->save();

        return $backToFlow()->with('cashierFlowSuccess', 'El servicio fue devuelto a Por cobrar y se resto de Total recaudado.');
    }

    public function markCashierFlowMovementCollected(Request $request)
    {
        return $this->updateCashierFlowMovementStatus($request, true);
    }

    public function returnCashierFlowMovementToPending(Request $request)
    {
        return $this->updateCashierFlowMovementStatus($request, false);
    }

    private function updateCashierFlowMovementStatus(Request $request, bool $collected)
    {
        $validated = $request->validate([
            'movement_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'servicio_cobro' => ['required', 'string', 'max:180'],
            'mes_cobro' => ['required', 'integer', 'between:1,12'],
            'anio' => ['required', 'integer', 'between:2000,'.(now()->year + 3)],
            'filtro_meses' => ['nullable', 'array', 'min:1', 'max:12'],
            'filtro_meses.*' => ['required', 'integer', 'distinct', 'between:1,12'],
            'filtro_anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 3)],
            'filtro_limite' => ['nullable', 'integer', 'between:1,200'],
            'filtro_departamento' => ['nullable', 'string', 'max:120'],
            'filtro_servicios' => ['nullable', 'array', 'max:'.self::MAX_SERVICE_FILTER_ITEMS],
            'filtro_servicios.*' => ['string', 'distinct', 'max:180'],
        ]);

        $serviceName = trim((string) $validated['servicio_cobro']);
        abort_unless($this->isCashierFlowReceivableService($serviceName), 404);

        $months = collect($validated['filtro_meses'] ?? [$validated['mes_cobro']])
            ->map(fn ($month): int => (int) $month)->unique()->sort()->values()->all();
        $filterYear = (int) ($validated['filtro_anio'] ?? $validated['anio']);
        $redirectFilters = [
            'servicios' => $validated['filtro_servicios'] ?? [],
            'meses' => $months,
            'anio' => $filterYear,
            'limite' => (int) ($validated['filtro_limite'] ?? 200),
            'departamento' => $this->canonicalDepartmentName((string) ($validated['filtro_departamento'] ?? '')),
        ];
        $backToFlow = fn () => redirect()->route('dashboard.financiera.flujo-cajero', $redirectFilters);

        if ($redirectFilters['departamento'] !== '') {
            return $backToFlow()->with('cashierFlowError', 'Quita el filtro de departamento antes de registrar cobros desde el detalle.');
        }
        if (! Schema::hasTable('cashier_flow_receivable_movements')) {
            return $backToFlow()->with('cashierFlowError', 'Falta preparar el registro individual de cobros. Ejecuta las migraciones de la aplicacion.');
        }

        $month = (int) $validated['mes_cobro'];
        $year = (int) $validated['anio'];
        try {
            $report = $this->reports->serviceDetail($serviceName, $month, $year, true, false);
        } catch (\Throwable $exception) {
            $this->logDetailError($exception, $serviceName, $month, $year);

            return $backToFlow()->with('cashierFlowError', 'No se pudo actualizar el movimiento. Vuelve a abrir el detalle e intentalo nuevamente.');
        }

        $movementRows = $this->attachCashierFlowMovementKeys(
            collect(($report['servicio']['rows'] ?? []))->map(fn ($row): array => [
                ...(array) $row,
                '_servicio' => $serviceName,
                '_mes' => $month,
            ]),
            $year
        );
        $row = $movementRows->first(fn (array $movement): bool => hash_equals(
            (string) ($movement['_movementKey'] ?? ''),
            (string) $validated['movement_key']
        ));
        if (! $row) {
            return $backToFlow()->with('cashierFlowError', 'El movimiento ya no aparece en el reporte actualizado. Vuelve a abrir el detalle antes de cambiar su estado.');
        }

        $collection = CashierFlowReceivableMovement::query()
            ->where('movement_key', $validated['movement_key'])
            ->first();
        if ($collected) {
            $movementAmount = round((float) ($row['totalLinea'] ?? 0), 2);
            if ($movementAmount <= 0) {
                return $backToFlow()->with('cashierFlowError', 'El movimiento no tiene un importe positivo para registrar como cobrado.');
            }
            if ($collection?->cobro_activo) {
                return $backToFlow()->with('cashierFlowSuccess', 'Este movimiento ya estaba marcado como cobrado.');
            }

            $invoiceUser = (array) ($row['usuario'] ?? []);

            $attributes = [
                'servicio' => $serviceName,
                'anio' => $year,
                'mes' => $month,
                'venta_id' => isset($row['ventaId']) ? mb_substr((string) $row['ventaId'], 0, 180) : null,
                'detalle_id' => isset($row['detalleId']) ? mb_substr((string) $row['detalleId'], 0, 180) : null,
                'facturado_por_id' => isset($invoiceUser['id']) ? mb_substr((string) $invoiceUser['id'], 0, 80) : null,
                'facturado_por_nombre' => isset($invoiceUser['nombre']) ? mb_substr(trim((string) $invoiceUser['nombre']), 0, 180) : null,
                'facturado_por_email' => isset($invoiceUser['email']) ? mb_substr(trim((string) $invoiceUser['email']), 0, 180) : null,
                'facturado_por_alias' => isset($invoiceUser['alias']) ? mb_substr(trim((string) $invoiceUser['alias']), 0, 180) : null,
                'codigo_orden' => isset($row['codigoOrden']) ? mb_substr((string) $row['codigoOrden'], 0, 180) : null,
                'codigo_seguimiento' => isset($row['codigoSeguimiento']) ? mb_substr((string) $row['codigoSeguimiento'], 0, 180) : null,
                'fecha' => isset($row['fecha']) ? mb_substr((string) $row['fecha'], 0, 80) : null,
                'descripcion' => isset($row['descripcion']) ? (string) $row['descripcion'] : null,
                'monto' => $movementAmount,
                'cantidad_paquetes' => round((float) ($row['cantidad'] ?? 0), 2),
                'cobro_activo' => true,
                'cobrado_por' => $request->user()?->id,
                'cobrado_at' => now(),
            ];

            try {
                if ($collection) {
                    $collection->forceFill($attributes)->save();
                } else {
                    CashierFlowReceivableMovement::query()->create([
                        'movement_key' => $validated['movement_key'],
                        ...$attributes,
                    ]);
                }
            } catch (QueryException $exception) {
                $collection = CashierFlowReceivableMovement::query()
                    ->where('movement_key', $validated['movement_key'])
                    ->first();
                if (! $collection) {
                    throw $exception;
                }
                $collection->forceFill($attributes)->save();
            }

            return $backToFlow()->with('cashierFlowSuccess', 'Cobro individual realizado por Bs '.\App\Support\BolivianNumber::format((float) $attributes['monto'], 2).'.');
        }

        if (! $collection || ! $collection->cobro_activo) {
            return $backToFlow()->with('cashierFlowError', 'Este movimiento ya estaba por cobrar o no tiene un cobro activo.');
        }

        $collection->forceFill([
            'cobro_activo' => false,
            'devuelto_at' => now(),
            'devuelto_por' => $request->user()?->id,
        ])->save();

        return $backToFlow()->with('cashierFlowSuccess', 'El movimiento fue devuelto a Por cobrar.');
    }

    public function invoicedContracts(Request $request)
    {
        $data = $this->buildServicesReportData($request, true);

        $data['rows'] = $this->buildInvoiceRows(
            $request,
            collect($data['selectedServices']),
            collect($data['selectedMonths']),
            $data['anio'],
            $data['errors']
        );
        $ventaIds = $data['rows']->getCollection()->pluck('ventaId')->filter()->values();
        $data['empresas'] = Schema::hasTable('empresa')
            ? Empresa::query()->orderBy('nombre')->get(['id', 'nombre', 'sigla', 'codigo_cliente'])
            : collect();
        $data['facturasAsociadas'] = Schema::hasColumn('conciliaciones_empresa', 'factura_venta_id')
            ? ConciliacionEmpresa::query()
                ->with('empresa:id,nombre')
                ->whereIn('factura_venta_id', $ventaIds)
                ->get()
                ->keyBy('factura_venta_id')
            : collect();

        return view('financial-reports.invoiced-contracts', $data);
    }

    private function normalizeServiceFilterInput(Request $request, string $field): void
    {
        $services = $request->input($field);
        if (! is_array($services)) {
            return;
        }

        $services = collect($services);
        if (! $services->every(fn ($service): bool => is_string($service))) {
            return;
        }

        $request->merge([
            $field => $services
                ->map(fn (string $service): string => trim($service))
                ->filter()
                ->unique(fn (string $service): string => mb_strtoupper($service, 'UTF-8'))
                ->values()
                ->all(),
        ]);
    }

    private function buildServicesReportData(
        Request $request,
        ?bool $forceOnlyContracts = null,
        bool $reconcileContracts = true,
        bool $excludeContracts = false,
        array $excludedCashierNames = [],
        bool $enableDepartmentFilter = false,
        bool $cacheReports = false,
        bool $showReceivablesSeparately = false,
        bool $includeAnnulledDetails = false,
        bool $loadAllReceivablesOutsideServiceFilter = false,
        ?Closure $progressCallback = null
    ): array {
        $this->normalizeServiceFilterInput($request, 'servicios');

        $validated = $request->validate([
            'servicio' => ['nullable', 'string', 'max:180'],
            'servicios' => ['nullable', 'array', 'max:'.self::MAX_SERVICE_FILTER_ITEMS],
            'servicios.*' => ['string', 'distinct', 'max:180'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'meses' => ['nullable', 'array', 'min:1', 'max:12'],
            'meses.*' => ['integer', 'distinct', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 3)],
            'limite' => ['nullable', 'integer', 'between:1,200'],
            'solo_contratos' => ['nullable', 'boolean'],
            'departamento' => ['nullable', 'string', 'max:120'],
            'actualizar' => ['nullable', 'boolean'],
        ]);

        $year = (int) ($validated['anio'] ?? now()->year);
        $limit = (int) ($validated['limite'] ?? 200);
        $refresh = (bool) ($validated['actualizar'] ?? false);
        $onlyContracts = $forceOnlyContracts ?? (bool) ($validated['solo_contratos'] ?? false);
        $selectedMonths = collect($validated['meses'] ?? [$validated['mes'] ?? now()->month])
            ->map(fn ($month) => (int) $month)
            ->unique()
            ->sort()
            ->values();
        $legacyService = trim((string) ($validated['servicio'] ?? ''));
        $requestedServices = collect($validated['servicios'] ?? ($legacyService !== '' ? [$legacyService] : []))
            ->map(fn ($service) => trim((string) $service))
            ->filter()
            ->unique()
            ->values();
        $hasServiceFilter = array_key_exists('servicios', $validated) || $legacyService !== '';
        $loadCatalogForProgress = $hasServiceFilter;
        $loadReceivablesForProgress = $loadAllReceivablesOutsideServiceFilter
            && $showReceivablesSeparately
            && $hasServiceFilter
            && $requestedServices->isNotEmpty();
        $progressWorkTotal = $selectedMonths->count()
            + ($loadCatalogForProgress ? $selectedMonths->count() : 0)
            + ($loadReceivablesForProgress ? $selectedMonths->count() : 0);
        $progressWorkCompleted = 0;
        $advanceReportProgress = function (string $stage) use (&$progressWorkCompleted, $progressWorkTotal, $progressCallback): void {
            if ($progressCallback === null) {
                return;
            }

            $progressWorkCompleted++;
            $percent = 5 + (int) round(72 * $progressWorkCompleted / max(1, $progressWorkTotal));
            $progressCallback(
                $percent,
                $stage.' ('.$progressWorkCompleted.' de '.$progressWorkTotal.')',
                $progressWorkCompleted,
                $progressWorkTotal
            );
        };
        $aggregated = collect();
        $serviceOptions = $requestedServices->keyBy(fn ($service) => mb_strtoupper($service, 'UTF-8'));
        $errors = collect();
        $annulledDetailErrors = collect();
        $apiUniqueSales = 0.0;
        $apiIncludedSales = 0.0;
        $apiExcludedSales = 0.0;
        $hasCompleteApiIncludedSales = true;
        $hasCompleteApiUniqueSales = true;
        $reportFilters = [];
        if ($hasServiceFilter) {
            $reportFilters['servicios'] = $requestedServices->all();
        }
        $excludedCountGroups = [];
        if ($excludeContracts) {
            $excludedCountGroups[] = 'contratos';
        }
        if ($showReceivablesSeparately) {
            $excludedCountGroups[] = 'eca_internacional';
        }
        if ($excludedCountGroups !== []) {
            $reportFilters['excluirGruposConteo'] = array_values(array_unique($excludedCountGroups));
        }
        if ($onlyContracts) {
            $reportFilters['incluirGruposConteo'] = ['contratos'];
        }
        if ($enableDepartmentFilter && filled($validated['departamento'] ?? null)) {
            $reportFilters['regionalConteo'] = $this->canonicalDepartmentName((string) $validated['departamento']);
        }
        if ($includeAnnulledDetails) {
            $reportFilters['incluirDetalleAnuladas'] = true;
            if ($enableDepartmentFilter && filled($validated['departamento'] ?? null)) {
                $reportFilters['regionalDetalle'] = $this->canonicalDepartmentName((string) $validated['departamento']);
            }
        }
        if ($excludedCashierNames !== []) {
            $reportFilters['excluirUsuariosConteo'] = array_values($excludedCashierNames);
        }
        $summaryReportFilters = $reportFilters;
        unset($summaryReportFilters['incluirDetalleAnuladas'], $summaryReportFilters['regionalDetalle']);
        $batchReports = null;
        if ($cacheReports && $selectedMonths->count() > 1 && $progressCallback === null) {
            try {
                $filters = $selectedMonths->map(fn (int $month): array => [
                    'mes' => $month,
                    'anio' => $year,
                    'limite' => $limit,
                    ...$reportFilters,
                ])->all();
                $batchReports = collect($this->reports->servicesBatch($filters, $refresh))
                    ->keyBy(fn (array $result): int => (int) ($result['filter']['mes'] ?? 0));
            } catch (\Throwable) {
                // La consulta individual conserva el manejo de errores por mes.
            }
        }

        $loadMonthlyReport = function (int $month) use (
            $batchReports,
            $includeAnnulledDetails,
            $annulledDetailErrors,
            $reportFilters,
            $summaryReportFilters,
            $year,
            $limit,
            $cacheReports,
            $refresh
        ): array {
            $detailError = null;

            if ($batchReports !== null) {
                $result = $batchReports->get($month);
                if ($result !== null && ($result['error'] ?? null) === null) {
                    return (array) ($result['report'] ?? []);
                }

                $detailError = (string) ($result['error'] ?? 'No se recibio el resumen del mes.');
                if (! $includeAnnulledDetails) {
                    throw new \RuntimeException($detailError);
                }
            } else {
                try {
                    return $this->reports->services($month, $year, $limit, $cacheReports, $refresh, $reportFilters);
                } catch (\Throwable $exception) {
                    if (! $includeAnnulledDetails) {
                        throw $exception;
                    }
                    $detailError = $exception->getMessage();
                }
            }

            $annulledDetailErrors->push("No se pudo obtener el detalle optimizado de anulaciones del mes {$month}: {$detailError}. Se conserva el resumen financiero.");
            $this->logDetailError(new \RuntimeException((string) $detailError), null, $month, $year);

            return $this->reports->services($month, $year, $limit, $cacheReports, $refresh, $summaryReportFilters);
        };

        foreach ($selectedMonths as $month) {
            try {
                $monthlyReport = $loadMonthlyReport((int) $month);
                if ($includeAnnulledDetails && (
                    ! (bool) data_get($monthlyReport, 'meta.detalleAnulacionesIncluido', false)
                    || ! (bool) data_get($monthlyReport, 'meta.auditoriaMediosPagoIncluida', false)
                )) {
                    $annulledDetailErrors->push("La API no devolvio el detalle de auditoria completo del mes {$month}; el resumen financiero se conserva.");
                }
                if ((bool) data_get($monthlyReport, 'meta.conteoVentasUnicas', false)) {
                    $apiUniqueSales += (float) data_get($monthlyReport, 'resumen.cantidadVentas', 0);
                    if (isset($monthlyReport['resumen']['cantidadVentasIncluidasEnTotalVendido'], $monthlyReport['resumen']['cantidadVentasNoIncluidasEnTotalVendido'])) {
                        $apiIncludedSales += (float) $monthlyReport['resumen']['cantidadVentasIncluidasEnTotalVendido'];
                        $apiExcludedSales += (float) $monthlyReport['resumen']['cantidadVentasNoIncluidasEnTotalVendido'];
                    } else {
                        $hasCompleteApiIncludedSales = false;
                    }
                } else {
                    $hasCompleteApiUniqueSales = false;
                }

                foreach ((array) ($monthlyReport['servicios'] ?? []) as $row) {
                    $name = trim((string) ($row['servicio'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $serviceKey = mb_strtoupper($name, 'UTF-8');
                    $serviceOptions->put($serviceKey, $name);
                    $current = $aggregated->get($serviceKey, [
                        'servicio' => $name,
                        'cantidadVentas' => 0,
                        'cantidadDetalles' => 0,
                        'totalCantidad' => 0,
                        'totalMonto' => 0,
                        'totalMontoVendido' => 0,
                        'totalMontoNoIncluidoEnTotalVendido' => 0,
                        'totalMontoAnulado' => 0,
                        'ultimaFecha' => null,
                        'descripcionMuestra' => null,
                        '_meses' => [],
                        '_porRegionales' => [],
                        '_porPersonas' => [],
                        '_annulledRows' => [],
                        '_paymentAuditRows' => [],
                    ]);

                    foreach (['cantidadVentas', 'cantidadDetalles', 'totalCantidad', 'totalMonto'] as $totalKey) {
                        $current[$totalKey] += (float) ($row[$totalKey] ?? 0);
                    }
                    $rowTotalMonto = (float) ($row['totalMonto'] ?? 0);
                    $current['totalMontoVendido'] += array_key_exists('totalMontoVendido', $row)
                        ? (float) $row['totalMontoVendido']
                        : $rowTotalMonto;
                    $current['totalMontoNoIncluidoEnTotalVendido'] += (float) ($row['totalMontoNoIncluidoEnTotalVendido'] ?? 0);
                    $current['totalMontoAnulado'] += (float) ($row['totalMontoAnulado'] ?? 0);

                    $date = trim((string) ($row['ultimaFecha'] ?? ''));
                    if ($date !== '' && ($current['ultimaFecha'] === null || $date > $current['ultimaFecha'])) {
                        $current['ultimaFecha'] = $date;
                    }
                    if (blank($current['descripcionMuestra']) && filled($row['descripcionMuestra'] ?? null)) {
                        $current['descripcionMuestra'] = $row['descripcionMuestra'];
                    }
                    $current['_porRegionales'] = [
                        ...($current['_porRegionales'] ?? []),
                        ...collect($row['porRegionales'] ?? [])->map(fn ($item) => (array) $item)->all(),
                    ];
                    $current['_porPersonas'] = [
                        ...($current['_porPersonas'] ?? []),
                        ...collect($row['porPersonas'] ?? [])->map(fn ($item) => (array) $item)->all(),
                    ];
                    $current['_annulledRows'] = [
                        ...($current['_annulledRows'] ?? []),
                        ...collect($row['rows'] ?? [])->map(fn ($item): array => [
                            ...(array) $item,
                            '_servicio' => $name,
                            '_mes' => (int) $month,
                        ])->all(),
                    ];
                    $current['_paymentAuditRows'] = [
                        ...($current['_paymentAuditRows'] ?? []),
                        ...collect($row['auditoriaMediosPago'] ?? [])->map(fn ($item): array => [
                            ...(array) $item,
                            '_servicio' => $name,
                            '_mes' => (int) $month,
                        ])->all(),
                    ];
                    $current['_meses'][] = $month;
                    $current['_meses'] = array_values(array_unique($current['_meses']));
                    $aggregated->put($serviceKey, $current);
                }
            } catch (\Throwable $exception) {
                $hasCompleteApiUniqueSales = false;
                $errors->push("No se pudo cargar el resumen del mes {$month}: {$exception->getMessage()}");
                $this->logDetailError($exception, null, $month, $year);
            }
            $advanceReportProgress("Consultando ventas del mes {$month}");
        }

        // Las opciones deben incluir servicios ausentes de la selección actual.
        // La consulta filtrada sigue siendo la fuente de los importes y conteos.
        if ($hasServiceFilter) {
            foreach ($selectedMonths as $month) {
                try {
                    $catalog = $this->reports->services($month, $year, 200, true, $refresh);
                    foreach ($catalog['servicios'] ?? [] as $catalogRow) {
                        $name = trim((string) ($catalogRow['servicio'] ?? ''));
                        if ($name !== '') {
                            $serviceOptions->put(mb_strtoupper($name, 'UTF-8'), $name);
                        }
                    }
                } catch (\Throwable $exception) {
                    $errors->push("No se pudo completar la lista de servicios del mes {$month}. Actualice el reporte antes de seleccionar todos.");
                    $this->logDetailError($exception, null, $month, $year);
                }
                $advanceReportProgress("Completando catálogo de servicios del mes {$month}");
            }
        }

        $receivableSource = $aggregated;
        if ($loadAllReceivablesOutsideServiceFilter && $showReceivablesSeparately && $hasServiceFilter && $requestedServices->isNotEmpty()) {
            $receivableReportFilters = $reportFilters;
            unset($receivableReportFilters['servicios']);
            unset($receivableReportFilters['excluirGruposConteo']);
            $receivableAggregated = collect();

            foreach ($selectedMonths as $month) {
                try {
                    $receivableReport = $this->reports->services(
                        (int) $month,
                        $year,
                        $limit,
                        $cacheReports,
                        $refresh,
                        $receivableReportFilters
                    );
                } catch (\Throwable $exception) {
                    if ($includeAnnulledDetails) {
                        $annulledDetailErrors->push("No se pudo obtener el detalle optimizado de anulaciones de Contratos y ECA del mes {$month}: {$exception->getMessage()}. Se conserva el resumen financiero.");
                        $this->logDetailError($exception, null, (int) $month, $year);

                        $receivableSummaryFilters = $summaryReportFilters;
                        unset($receivableSummaryFilters['servicios']);
                        unset($receivableSummaryFilters['excluirGruposConteo']);
                        try {
                            $receivableReport = $this->reports->services(
                                (int) $month,
                                $year,
                                $limit,
                                $cacheReports,
                                $refresh,
                                $receivableSummaryFilters
                            );
                        } catch (\Throwable $summaryException) {
                            $errors->push("No se pudieron cargar Contratos y ECA del mes {$month}: {$summaryException->getMessage()}");
                            $this->logDetailError($summaryException, null, (int) $month, $year);
                            $advanceReportProgress("Consultando Contratos y ECA del mes {$month}");

                            continue;
                        }
                    } else {
                        $errors->push("No se pudieron cargar Contratos y ECA del mes {$month}: {$exception->getMessage()}");
                        $this->logDetailError($exception, null, (int) $month, $year);
                        $advanceReportProgress("Consultando Contratos y ECA del mes {$month}");

                        continue;
                    }
                }

                if ($includeAnnulledDetails && (
                    ! (bool) data_get($receivableReport, 'meta.detalleAnulacionesIncluido', false)
                    || ! (bool) data_get($receivableReport, 'meta.auditoriaMediosPagoIncluida', false)
                )) {
                    $annulledDetailErrors->push("La API no devolvio el detalle de auditoria completo de Contratos y ECA del mes {$month}; los importes por cobrar se conservan.");
                }

                foreach ((array) ($receivableReport['servicios'] ?? []) as $row) {
                    $name = trim((string) ($row['servicio'] ?? ''));
                    if ($name === '' || ! $this->isCashierFlowReceivableService($name)) {
                        continue;
                    }

                    $serviceKey = mb_strtoupper($name, 'UTF-8');
                    $current = $receivableAggregated->get($serviceKey, [
                        'servicio' => $name,
                        'cantidadVentas' => 0,
                        'cantidadDetalles' => 0,
                        'totalCantidad' => 0,
                        'totalMonto' => 0,
                        'totalMontoVendido' => 0,
                        'totalMontoNoIncluidoEnTotalVendido' => 0,
                        'totalMontoAnulado' => 0,
                        'ultimaFecha' => null,
                        'descripcionMuestra' => null,
                        '_meses' => [],
                        '_porRegionales' => [],
                        '_porPersonas' => [],
                        '_annulledRows' => [],
                        '_paymentAuditRows' => [],
                    ]);

                    foreach (['cantidadVentas', 'cantidadDetalles', 'totalCantidad', 'totalMonto'] as $totalKey) {
                        $current[$totalKey] += (float) ($row[$totalKey] ?? 0);
                    }
                    $rowTotalMonto = (float) ($row['totalMonto'] ?? 0);
                    $current['totalMontoVendido'] += array_key_exists('totalMontoVendido', $row)
                        ? (float) $row['totalMontoVendido']
                        : $rowTotalMonto;
                    $current['totalMontoNoIncluidoEnTotalVendido'] += (float) ($row['totalMontoNoIncluidoEnTotalVendido'] ?? 0);
                    $current['totalMontoAnulado'] += (float) ($row['totalMontoAnulado'] ?? 0);

                    $date = trim((string) ($row['ultimaFecha'] ?? ''));
                    if ($date !== '' && ($current['ultimaFecha'] === null || $date > $current['ultimaFecha'])) {
                        $current['ultimaFecha'] = $date;
                    }
                    if (blank($current['descripcionMuestra']) && filled($row['descripcionMuestra'] ?? null)) {
                        $current['descripcionMuestra'] = $row['descripcionMuestra'];
                    }
                    $current['_porRegionales'] = [
                        ...($current['_porRegionales'] ?? []),
                        ...collect($row['porRegionales'] ?? [])->map(fn ($item) => (array) $item)->all(),
                    ];
                    $current['_porPersonas'] = [
                        ...($current['_porPersonas'] ?? []),
                        ...collect($row['porPersonas'] ?? [])->map(fn ($item) => (array) $item)->all(),
                    ];
                    $current['_annulledRows'] = [
                        ...($current['_annulledRows'] ?? []),
                        ...collect($row['rows'] ?? [])->map(fn ($item): array => [
                            ...(array) $item,
                            '_servicio' => $name,
                            '_mes' => (int) $month,
                        ])->all(),
                    ];
                    $current['_paymentAuditRows'] = [
                        ...($current['_paymentAuditRows'] ?? []),
                        ...collect($row['auditoriaMediosPago'] ?? [])->map(fn ($item): array => [
                            ...(array) $item,
                            '_servicio' => $name,
                            '_mes' => (int) $month,
                        ])->all(),
                    ];
                    $current['_meses'][] = (int) $month;
                    $current['_meses'] = array_values(array_unique($current['_meses']));
                    $receivableAggregated->put($serviceKey, $current);
                }
                $advanceReportProgress("Consultando Contratos y ECA del mes {$month}");
            }

            $receivableSource = $aggregated
                ->filter(fn (array $service): bool => $this->isCashierFlowReceivableService((string) ($service['servicio'] ?? '')))
                ->keyBy(fn (array $service): string => mb_strtoupper((string) ($service['servicio'] ?? ''), 'UTF-8'))
                ->merge($receivableAggregated);
        }

        $receivableServices = collect();
        if ($showReceivablesSeparately) {
            $receivableServices = $receivableSource
                ->filter(function (array $service): bool {
                    $name = (string) ($service['servicio'] ?? '');

                    return $this->serviceGroupName($name) === 'Servicio Contratos'
                        || $this->isEcaInternationalService($name);
                })
                ->values();
        }

        if ($excludeContracts) {
            $isContractService = fn (string $service): bool => $this->serviceGroupName($service) === 'Servicio Contratos';
            $aggregated = $aggregated->reject(
                fn (array $service): bool => $isContractService((string) ($service['servicio'] ?? ''))
            );
            $serviceOptions = $serviceOptions->reject(
                fn (string $service): bool => $isContractService($service)
            );
            $requestedServices = $requestedServices->reject(
                fn (string $service): bool => $isContractService($service)
            )->values();
        }

        if ($showReceivablesSeparately) {
            $aggregated = $aggregated->reject(
                fn (array $service): bool => $this->isEcaInternationalService((string) ($service['servicio'] ?? ''))
            );
            $serviceOptions = $serviceOptions->reject(fn (string $service): bool => $this->isEcaInternationalService($service));
            $requestedServices = $requestedServices
                ->reject(fn (string $service): bool => $this->isEcaInternationalService($service))
                ->values();
        }

        $contractServices = $aggregated
            ->filter(fn (array $service) => $this->serviceGroupName((string) ($service['servicio'] ?? '')) === 'Servicio Contratos')
            ->pluck('servicio')
            ->values();
        $selectedServices = $onlyContracts
            ? $contractServices
            : ($hasServiceFilter ? $requestedServices : $serviceOptions->sortKeys()->values());
        $selectedServices = $selectedServices
            ->map(fn (string $name): string => $serviceOptions->get(mb_strtoupper($name, 'UTF-8'), $name))
            ->unique(fn (string $name): string => mb_strtoupper($name, 'UTF-8'))
            ->values();
        $services = $aggregated
            ->only($selectedServices->map(fn (string $name): string => mb_strtoupper($name, 'UTF-8'))->all())
            ->values()
            ->sortByDesc('totalMontoVendido')
            ->values();
        $selectedDepartment = $enableDepartmentFilter
            ? $this->canonicalDepartmentName((string) ($validated['departamento'] ?? ''))
            : '';
        $departmentOptions = $enableDepartmentFilter
            ? $this->departmentOptions($services, $selectedDepartment)
            : collect();
        if ($selectedDepartment !== '') {
            $selectedDepartment = (string) ($departmentOptions->first(
                fn (string $department): bool => $this->sameNormalizedText($department, $selectedDepartment)
            ) ?? $selectedDepartment);
        }
        if ($enableDepartmentFilter) {
            $services = $this->attachDepartmentsToPeople($services);
        }
        $contractReceivables = $this->contractReceivables($services, $selectedMonths, $year);
        if ($reconcileContracts && ! $onlyContracts && $contractReceivables['has_contracts']) {
            $validatedSales = $contractReceivables['validated_sales'];
            $validatedAmount = $contractReceivables['validated_amount'];

            $services = $services->map(function (array $service) use (&$validatedSales, &$validatedAmount): array {
                if ($this->serviceGroupName((string) ($service['servicio'] ?? '')) !== 'Servicio Contratos') {
                    return $service;
                }

                $service['cantidadVentas'] = $validatedSales;
                $service['totalMonto'] = $validatedAmount;
                $service['totalMontoVendido'] = $validatedAmount;
                $service['totalMontoNoIncluidoEnTotalVendido'] = 0.0;
                $validatedSales = 0;
                $validatedAmount = 0;

                return $service;
            })->sortByDesc('totalMontoVendido')->values();
        }
        if ($enableDepartmentFilter && $selectedDepartment !== '') {
            $services = $this->filterServicesByDepartment($services, $selectedDepartment);
            $receivableServices = $this->filterServicesByDepartment(
                $this->attachDepartmentsToPeople($receivableServices),
                $selectedDepartment
            );
        } elseif ($enableDepartmentFilter) {
            $receivableServices = $this->attachDepartmentsToPeople($receivableServices);
        }
        if ($excludedCashierNames !== []) {
            $services = $this->excludeCashiersFromServices($services, $excludedCashierNames);
        }
        if ($showReceivablesSeparately) {
            $reportDays = $this->countReportDaysExcludingSundays($selectedMonths->all(), $year);
            $receivableServices = $receivableServices
                ->map(function (array $service) use ($reportDays): array {
                    $service['promedioPaquetesDiario'] = $reportDays > 0
                        ? (float) ($service['totalCantidad'] ?? 0) / $reportDays
                        : 0.0;

                    return $service;
                })
                ->sortByDesc('totalMonto')
                ->values();
        }
        $summary = [
            'cantidadServicios' => $services->count(),
            'cantidadVentas' => $services->sum('cantidadVentas'),
            'cantidadDetalles' => $services->sum('cantidadDetalles'),
            'totalCantidad' => $services->sum('totalCantidad'),
            'totalMonto' => $services->sum('totalMonto'),
            'totalMontoVendido' => $services->sum('totalMontoVendido'),
            'totalMontoNoIncluidoEnTotalVendido' => $services->sum('totalMontoNoIncluidoEnTotalVendido'),
            'totalMontoAnulado' => $services->sum('totalMontoAnulado'),
            'totalMontoBruto' => $services->sum('totalMonto') + $services->sum('totalMontoAnulado'),
            'contratosFacturadosVentas' => $contractReceivables['invoiced_sales'],
            'contratosFacturadosMonto' => $contractReceivables['invoiced_amount'],
            'contratosValidadosVentas' => $contractReceivables['validated_sales'],
            'contratosValidadosMonto' => $contractReceivables['validated_amount'],
            'contratosPorCobrarVentas' => $contractReceivables['receivable_sales'],
            'contratosPorCobrarMonto' => $contractReceivables['receivable_amount'],
        ];
        if ($hasCompleteApiUniqueSales && $errors->isEmpty()) {
            $summary['cantidadVentas'] = $apiUniqueSales;
            $summary['cantidadOperacionesRegistradas'] = $apiUniqueSales;
            if ($hasCompleteApiIncludedSales) {
                $summary['cantidadVentasIncluidasEnTotalVendido'] = $apiIncludedSales;
                $summary['cantidadVentasNoIncluidasEnTotalVendido'] = $apiExcludedSales;
                if ($showReceivablesSeparately) {
                    $summary['cantidadVentas'] = $apiIncludedSales;
                }
            }
        }
        $serviceGroups = $this->buildServiceGroups($services);
        $summary['cantidadServicios'] = $serviceGroups->count();

        if ($progressCallback !== null) {
            $progressCallback(80, 'Consolidando cajeros, servicios y montos…', $progressWorkCompleted, $progressWorkTotal);
        }

        return [
            'mes' => (int) $selectedMonths->first(),
            'anio' => $year,
            'limite' => $limit,
            'soloContratos' => $onlyContracts,
            'contractsExcluded' => $excludeContracts,
            'maxSelectedServices' => self::MAX_SERVICE_FILTER_ITEMS,
            'refreshReports' => $refresh,
            'selectedDepartment' => $selectedDepartment,
            'departmentOptions' => $departmentOptions,
            'selectedMonths' => $selectedMonths->all(),
            'selectedServices' => $selectedServices->all(),
            'serviceOptions' => $onlyContracts
                ? $contractServices
                : $serviceOptions->sortKeys()->values(),
            'summary' => $summary,
            'uniqueSalesCountFromApi' => $hasCompleteApiUniqueSales && $errors->isEmpty(),
            'includedSalesCountFromApi' => $showReceivablesSeparately && $hasCompleteApiUniqueSales && $hasCompleteApiIncludedSales && $errors->isEmpty(),
            'services' => $services,
            'receivableServices' => $receivableServices,
            'serviceGroups' => $serviceGroups,
            'meta' => [],
            'errors' => $errors,
            'annulledDetailErrors' => $annulledDetailErrors,
            'error' => $errors->first(),
        ];
    }

    /**
     * Separa las facturas de contratos pendientes de aquellas que ya fueron
     * validadas y asociadas desde Conciliaciones.
     */
    private function contractReceivables(Collection $services, Collection $months, int $year): array
    {
        $contracts = $services->filter(
            fn (array $service): bool => $this->serviceGroupName((string) ($service['servicio'] ?? '')) === 'Servicio Contratos'
        );
        $invoicedSales = (float) $contracts->sum('cantidadVentas');
        $invoicedAmount = (float) $contracts->sum('totalMonto');
        $validatedSales = 0.0;
        $validatedAmount = 0.0;

        if (
            $contracts->isNotEmpty()
            && Schema::hasTable('conciliaciones_empresa')
            && Schema::hasColumn('conciliaciones_empresa', 'factura_venta_id')
            && Schema::hasColumn('conciliaciones_empresa', 'factura_monto')
            && Schema::hasColumn('conciliaciones_empresa', 'conciliado_at')
        ) {
            $validatedInvoices = ConciliacionEmpresa::query()
                ->where('facturado_anio', $year)
                ->whereIn('facturado_mes', $months->all())
                ->whereNotNull('factura_venta_id')
                ->whereNotNull('conciliado_at')
                ->get(['factura_venta_id', 'factura_monto']);

            $validatedSales = min($invoicedSales, (float) $validatedInvoices->count());
            $validatedAmount = min($invoicedAmount, (float) $validatedInvoices->sum('factura_monto'));
        }

        return [
            'has_contracts' => $contracts->isNotEmpty(),
            'invoiced_sales' => $invoicedSales,
            'invoiced_amount' => $invoicedAmount,
            'validated_sales' => $validatedSales,
            'validated_amount' => $validatedAmount,
            'receivable_sales' => max(0, $invoicedSales - $validatedSales),
            'receivable_amount' => max(0, $invoicedAmount - $validatedAmount),
        ];
    }

    public function serviceDetail(Request $request)
    {
        abort_unless($request->boolean('modal'), 404);

        $this->normalizeServiceFilterInput($request, 'servicios');

        $validated = $request->validate([
            'servicio' => ['nullable', 'string', 'max:180'],
            'servicios' => ['nullable', 'array', 'max:'.self::MAX_SERVICE_FILTER_ITEMS],
            'servicios.*' => ['string', 'distinct', 'max:180'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'meses' => ['nullable', 'array', 'min:1', 'max:12'],
            'meses.*' => ['integer', 'distinct', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 3)],
            'page' => ['nullable', 'integer', 'min:1'],
            'actualizar' => ['nullable', 'boolean'],
            'buscar' => ['nullable', 'string', 'max:180'],
            'solo_cobrados' => ['nullable', 'boolean'],
            'clasificacion' => ['nullable', Rule::in(['todos', 'incluido', 'no_incluido', 'qr_anulado', 'anulado', 'sin_clasificar'])],
        ]);

        $year = (int) ($validated['anio'] ?? now()->year);
        $selectedMonths = collect($validated['meses'] ?? [$validated['mes'] ?? now()->month])
            ->map(fn ($month) => (int) $month)
            ->unique()
            ->sort()
            ->values();
        $legacyService = trim((string) ($validated['servicio'] ?? ''));
        $selectedServices = collect($validated['servicios'] ?? ($legacyService !== '' ? [$legacyService] : ['Servicio Internacional']))
            ->map(fn ($service) => trim((string) $service))
            ->filter()
            ->unique()
            ->values();
        $isModal = true;
        $refresh = (bool) ($validated['actualizar'] ?? false);
        $showCollectedOnly = (bool) ($validated['solo_cobrados'] ?? false);
        $classification = (string) ($validated['clasificacion'] ?? 'todos');
        $serviceOptions = $selectedServices->keyBy(fn ($service) => mb_strtoupper($service, 'UTF-8'));
        $errors = collect();

        $selectedServices = $selectedServices
            ->map(fn (string $name): string => $serviceOptions->get(mb_strtoupper($name, 'UTF-8'), $name))
            ->unique(fn (string $name): string => mb_strtoupper($name, 'UTF-8'))
            ->values();
        $rows = collect();
        $service = [
            'servicio' => $selectedServices->count() === 1 ? $selectedServices->first() : $selectedServices->count().' servicios seleccionados',
            'cantidadVentas' => 0,
            'cantidadDetalles' => 0,
            'totalCantidad' => 0,
            'totalMonto' => 0,
            'totalMontoVendido' => 0,
            'totalMontoNoIncluidoEnTotalVendido' => 0,
            'totalMontoAnulado' => 0,
        ];

        $detailFilters = $selectedServices->flatMap(fn (string $serviceName) => $selectedMonths->map(
            fn (int $month): array => ['servicio' => $serviceName, 'mes' => $month, 'anio' => $year]
        ))->values()->all();
        if (count($detailFilters) === 1) {
            $filter = $detailFilters[0];
            try {
                $detailResults = [[
                    'filter' => $filter,
                    'report' => $this->reports->serviceDetail($filter['servicio'], $filter['mes'], $year, $isModal, $refresh),
                    'error' => null,
                ]];
            } catch (\Throwable $exception) {
                $detailResults = [['filter' => $filter, 'report' => null, 'error' => $exception->getMessage()]];
            }
        } else {
            try {
                $detailResults = $this->reports->serviceDetailsBatch($detailFilters, $isModal, $refresh);
            } catch (\Throwable $exception) {
                $detailResults = array_map(fn (array $filter): array => [
                    'filter' => $filter,
                    'report' => null,
                    'error' => $exception->getMessage(),
                ], $detailFilters);
            }
        }

        foreach ($detailResults as $result) {
            $filter = (array) ($result['filter'] ?? []);
            $serviceName = (string) ($filter['servicio'] ?? '');
            $month = (int) ($filter['mes'] ?? 0);
            if ($result['error'] !== null) {
                $exception = new \RuntimeException((string) $result['error']);
                $errors->push("No se pudo cargar {$serviceName} para el mes {$month}: {$exception->getMessage()}");
                $this->logDetailError($exception, $serviceName, $month, $year);

                continue;
            }

            $detail = (array) (($result['report'] ?? [])['servicio'] ?? []);
            foreach (['cantidadVentas', 'cantidadDetalles', 'totalCantidad', 'totalMonto'] as $totalKey) {
                $service[$totalKey] += (float) ($detail[$totalKey] ?? 0);
            }
            $service['totalMontoVendido'] += array_key_exists('totalMontoVendido', $detail)
                ? (float) $detail['totalMontoVendido']
                : (float) ($detail['totalMonto'] ?? 0);
            $service['totalMontoNoIncluidoEnTotalVendido'] += (float) ($detail['totalMontoNoIncluidoEnTotalVendido'] ?? 0);
            $service['totalMontoAnulado'] += (float) ($detail['totalMontoAnulado'] ?? 0);
            $rows->push(...collect($detail['rows'] ?? [])->map(fn ($row) => [
                ...(array) $row,
                '_servicio' => $serviceName,
                '_mes' => $month,
            ])->all());
        }

        $isCashierFlowReceivableDetail = $request->boolean('flujo_cajero')
            && $selectedServices->isNotEmpty()
            && $selectedServices->every(fn (string $serviceName): bool => $this->isCashierFlowReceivableService($serviceName));
        if ($isCashierFlowReceivableDetail) {
            $rows = $rows->map(function (array $row): array {
                $row['_facturaAnulada'] = $this->isCancelledCashierFlowInvoice($row);
                $row['_estadoFiscal'] = trim((string) ($row['estadoFiscal'] ?? $row['estado_fiscal'] ?? ''));
                $row['_estadoPago'] = trim((string) ($row['estadoPago'] ?? $row['estado_pago'] ?? ''));
                $row['_mesServicio'] = $this->monthFromDescription((string) ($row['descripcion'] ?? ''));

                return $row;
            });
            $annulledRows = $rows->filter(fn (array $row): bool => (bool) ($row['_facturaAnulada'] ?? false));
            $validRows = $rows->reject(fn (array $row): bool => (bool) ($row['_facturaAnulada'] ?? false));
            $lineAmount = fn (array $row): float => (float) ($row['totalLinea'] ?? $row['monto'] ?? 0);
            $countSales = function (Collection $invoiceRows): int {
                $saleIds = $invoiceRows->pluck('ventaId')->map(fn ($id): string => trim((string) $id))->filter()->unique();

                return $saleIds->isNotEmpty() ? $saleIds->count() : $invoiceRows->count();
            };
            $validAmount = (float) $validRows->sum($lineAmount);
            $annulledAmount = (float) $annulledRows->sum($lineAmount);

            $service['detallePorFacturaCompleto'] = $errors->isEmpty();
            if ($service['detallePorFacturaCompleto']) {
                $service['cantidadVentas'] = $countSales($validRows);
                $service['cantidadDetalles'] = $validRows->count();
                $service['totalCantidad'] = (float) $validRows->sum(fn (array $row): float => (float) ($row['cantidad'] ?? 0));
                $service['totalMonto'] = $validAmount;
                $service['totalMontoVendido'] = $validAmount;
            }
            $service['totalMontoAnulado'] = $annulledAmount;
            $service['cantidadMovimientosDetalle'] = $rows->count();
            $service['cantidadLineasAnuladas'] = $annulledRows->count();
            $service['cantidadFacturasAnuladas'] = $countSales($annulledRows);
            $service['totalMontoAnuladoDetalle'] = $annulledAmount;
            $service['totalMontoBrutoDetalle'] = $validAmount + $annulledAmount;
        }

        $rows = $rows->map(function (array $row) use ($isCashierFlowReceivableDetail): array {
            if ((bool) ($row['_facturaAnulada'] ?? false)) {
                $row['_clasificacionTotalVendido'] = $this->cashierFlowPaymentMethod($row) === 'qr'
                    ? 'qr_anulado'
                    : 'anulado';
            } elseif ($isCashierFlowReceivableDetail) {
                $row['_clasificacionTotalVendido'] = 'incluido';
            } else {
                $row['_clasificacionTotalVendido'] = $this->serviceDetailRowClassification($row);
            }

            return $row;
        });

        $canManageReceivables = $isModal
            && $request->boolean('flujo_cajero')
            && $selectedServices->isNotEmpty()
            && $selectedServices->every(fn (string $serviceName): bool => $this->isCashierFlowReceivableService($serviceName))
            && trim((string) $request->query('filtro_departamento', '')) === ''
            && Schema::hasTable('cashier_flow_receivable_movements');
        $cashierFlowContext = [
            'services' => collect($request->query('filtro_servicios', []))->filter(fn ($value) => is_string($value))
                ->map(fn ($value) => trim($value))->filter()->unique()->values()->all(),
            'months' => collect($request->query('filtro_meses', []))->filter(fn ($value) => is_numeric($value))
                ->map(fn ($value): int => (int) $value)->filter(fn (int $value): bool => $value >= 1 && $value <= 12)->unique()->sort()->values()->all(),
            'year' => (int) $request->query('filtro_anio', $year),
            'limit' => max(1, min(200, (int) $request->query('filtro_limite', 200))),
            'department' => trim((string) $request->query('filtro_departamento', '')),
        ];

        if ($canManageReceivables) {
            $rows = $this->attachCashierFlowMovementKeys($rows, $year);
            $movementKeys = $rows->pluck('_movementKey')->filter()->values();
            $movementStatuses = $movementKeys->isNotEmpty()
                ? CashierFlowReceivableMovement::query()->whereIn('movement_key', $movementKeys)->get()->keyBy('movement_key')
                : collect();
            $rows = $rows->map(function (array $row) use ($movementStatuses): array {
                $movement = $movementStatuses->get($row['_movementKey']);
                $invoiceUser = (array) ($row['usuario'] ?? []);
                $row['_cobroRealizado'] = (bool) ($movement?->cobro_activo ?? false);
                $row['_montoCobrado'] = $row['_cobroRealizado'] ? (float) $movement->monto : 0.0;
                $row['_fechaCobro'] = $row['_cobroRealizado'] && $movement?->cobrado_at
                    ? $movement->cobrado_at->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
                    : null;
                $row['_facturadoPor'] = trim((string) (
                    $movement?->facturado_por_nombre
                    ?: ($invoiceUser['nombre'] ?? $invoiceUser['alias'] ?? '')
                ));
                $row['_cantidadPaquetesCobrados'] = $row['_cobroRealizado']
                    ? (float) ($movement->cantidad_paquetes ?: ($row['cantidad'] ?? 0))
                    : 0.0;

                return $row;
            });
            $paidRows = $rows->filter(fn (array $row): bool => ! ($row['_facturaAnulada'] ?? false)
                && (bool) ($row['_cobroRealizado'] ?? false));
            $service['totalMontoCobrado'] = (float) $paidRows->sum('_montoCobrado');
            $service['cantidadMovimientosCobrados'] = $paidRows->count();
            $service['cantidadVentasCobradas'] = $paidRows->pluck('ventaId')->filter()->unique()->count();
            $service['totalMontoPendiente'] = max(0, (float) $service['totalMonto'] - $service['totalMontoCobrado']);
            if ($showCollectedOnly) {
                $rows = $rows->filter(fn (array $row): bool => ! ($row['_facturaAnulada'] ?? false)
                    && (bool) ($row['_cobroRealizado'] ?? false))->values();
            }
        }

        $rows = $rows->sortByDesc('fecha')->values();
        $reportDays = $this->countReportDaysExcludingSundays($selectedMonths->all(), $year);
        $service['promedioDiario'] = $reportDays > 0
            ? (float) $service['totalMontoVendido'] / $reportDays
            : 0;

        $searchTerm = trim((string) ($validated['buscar'] ?? ''));
        if ($searchTerm !== '') {
            $normalizedSearch = mb_strtolower(Str::ascii($searchTerm));
            $rows = $rows->filter(function (array $row) use ($normalizedSearch): bool {
                $searchableValues = [
                    $row['_servicio'] ?? '',
                    $row['ventaId'] ?? '',
                    $row['detalleId'] ?? '',
                    $row['descripcion'] ?? '',
                    $row['codigoOrden'] ?? '',
                    $row['codigoSeguimiento'] ?? '',
                    $row['fecha'] ?? '',
                    $row['_estadoFiscal'] ?? '',
                    $row['_estadoPago'] ?? '',
                    $row['totalLinea'] ?? $row['monto'] ?? '',
                    \App\Support\BolivianNumber::format((float) ($row['totalLinea'] ?? $row['monto'] ?? 0), 2),
                ];
                $haystack = collect($searchableValues)
                    ->filter(fn ($value): bool => is_scalar($value))
                    ->map(fn ($value): string => (string) $value)
                    ->implode(' ');

                return str_contains(mb_strtolower(Str::ascii($haystack)), $normalizedSearch);
            })->values();
        }

        $classificationLabels = [
            'incluido' => 'Incluido en total vendido',
            'no_incluido' => 'Fuera del total vendido (incluye anulados)',
            'qr_anulado' => 'QR ANULADOS',
            'anulado' => 'Otros anulados/cancelados',
            'sin_clasificar' => 'Sin clasificar',
        ];
        $classificationSummary = [
            'todos' => [
                'label' => 'Todos los movimientos',
                'count' => $rows->count(),
                'amount' => (float) $rows->sum(fn (array $row): float => (float) ($row['totalLinea'] ?? $row['monto'] ?? 0)),
            ],
        ];
        foreach ($classificationLabels as $key => $label) {
            $matchingRows = $key === 'no_incluido'
                ? $rows->whereIn('_clasificacionTotalVendido', ['no_incluido', 'qr_anulado', 'anulado'])
                : $rows->where('_clasificacionTotalVendido', $key);
            $classificationSummary[$key] = [
                'label' => $label,
                'count' => $matchingRows->count(),
                'amount' => (float) $matchingRows->sum(fn (array $row): float => (float) ($row['totalLinea'] ?? $row['monto'] ?? 0)),
            ];
        }
        if ($classification !== 'todos') {
            $rows = $classification === 'no_incluido'
                ? $rows->whereIn('_clasificacionTotalVendido', ['no_incluido', 'qr_anulado', 'anulado'])->values()
                : $rows->where('_clasificacionTotalVendido', $classification)->values();
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = $isModal ? 20 : 50;
        $paginatedRows = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page', 'actualizar')]
        );

        $viewData = [
            'mes' => (int) $selectedMonths->first(),
            'anio' => $year,
            'selectedMonths' => $selectedMonths->all(),
            'selectedServices' => $selectedServices->all(),
            'serviceOptions' => $serviceOptions->sortKeys()->values(),
            'serviceName' => $selectedServices->implode(', '),
            'service' => $service,
            'isCashierFlowReceivableDetail' => $isCashierFlowReceivableDetail,
            'rows' => $paginatedRows,
            'errors' => $errors,
            'error' => $errors->first(),
            'searchTerm' => $searchTerm,
            'classification' => $classification,
            'classificationSummary' => $classificationSummary,
            'canManageReceivables' => $canManageReceivables,
            'cashierFlowContext' => $cashierFlowContext,
            'showCollectedOnly' => $showCollectedOnly && $canManageReceivables,
        ];

        return response()
            ->view('financial-reports.partials.service-detail-modal-content', $viewData)
            ->header('X-Flow-Detail-Fragment', '1');
    }

    private function serviceDetailRowClassification(array $row): string
    {
        $annulled = filter_var($row['anulada'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $fiscalStatus = $this->normalizePersonName((string) ($row['estadoFiscal'] ?? $row['estado_fiscal'] ?? ''));
        $paymentStatus = $this->normalizePersonName((string) ($row['estadoPago'] ?? $row['estado_pago'] ?? ''));
        $isPaymentCancelled = in_array($paymentStatus, ['ANULADA', 'ANULADO', 'CANCELADA', 'CANCELADO'], true);
        $isInvoiceAnnulled = $annulled || in_array($fiscalStatus, ['ANULADA', 'ANULADO'], true);
        if ($this->cashierFlowPaymentMethod($row) === 'qr' && ($isInvoiceAnnulled || $isPaymentCancelled)) {
            return 'qr_anulado';
        }
        if ($isInvoiceAnnulled || $isPaymentCancelled) {
            return 'anulado';
        }

        $included = $row['incluidaEnTotalVendido'] ?? null;
        if (is_bool($included)) {
            return $included ? 'incluido' : 'no_incluido';
        }
        if (is_numeric($included)) {
            return (bool) $included ? 'incluido' : 'no_incluido';
        }
        if (is_string($included)) {
            $normalized = mb_strtoupper(trim($included), 'UTF-8');
            if (in_array($normalized, ['SI', 'SÍ', 'TRUE', '1'], true)) {
                return 'incluido';
            }
            if (in_array($normalized, ['NO', 'FALSE', '0'], true)) {
                return 'no_incluido';
            }
        }

        return 'sin_clasificar';
    }

    private function logDetailError(\Throwable $exception, ?string $service, int $month, int $year): void
    {
        Log::warning('No se pudo cargar información del reporte financiero por servicio.', [
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'servicio' => $service,
            'mes' => $month,
            'anio' => $year,
        ]);
    }

    private function buildInvoiceRows(
        Request $request,
        Collection $services,
        Collection $months,
        int $year,
        Collection $errors
    ): LengthAwarePaginator {
        $rows = collect();

        foreach ($services as $serviceName) {
            foreach ($months as $month) {
                try {
                    $report = $this->reports->serviceDetail((string) $serviceName, (int) $month, $year);
                    $detail = (array) ($report['servicio'] ?? []);
                    $rows->push(...collect($detail['rows'] ?? [])->map(fn ($row) => [
                        ...(array) $row,
                        '_servicio' => $serviceName,
                        '_mes' => (int) $month,
                        '_mes_servicio' => $this->monthFromDescription((string) ($row['descripcion'] ?? '')) ?? (int) $month,
                    ])->all());
                } catch (\Throwable $exception) {
                    $errors->push("No se pudo cargar {$serviceName} para el mes {$month}: {$exception->getMessage()}");
                    $this->logDetailError($exception, (string) $serviceName, (int) $month, $year);
                }
            }
        }

        $rows = $rows->sortByDesc('fecha')->values();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 200;

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );
    }

    private function monthFromDescription(string $description): ?int
    {
        $normalized = mb_strtoupper($description);
        foreach ([
            1 => 'ENERO', 2 => 'FEBRERO', 3 => 'MARZO', 4 => 'ABRIL',
            5 => 'MAYO', 6 => 'JUNIO', 7 => 'JULIO', 8 => 'AGOSTO',
            9 => 'SEPTIEMBRE', 10 => 'OCTUBRE', 11 => 'NOVIEMBRE', 12 => 'DICIEMBRE',
        ] as $month => $name) {
            if (str_contains($normalized, $name)) {
                return $month;
            }
        }

        return null;
    }

    private function buildCashierFlowServiceGroups(
        Collection $services,
        Collection $receivableServices,
        Collection $collectedServiceRows,
        ?Collection $collectedQrByService = null
    ): Collection {
        $collectedQrByService ??= collect();
        $services = $services->map(function (array $service): array {
            $soldAmount = max(0, (float) ($service['totalMontoVendido'] ?? $service['totalMonto'] ?? 0));
            $qrOutsideSold = max(
                0,
                (float) ($service['totalMontoNoIncluidoEnTotalVendido'] ?? 0)
                    - (float) ($service['totalMontoAnulado'] ?? 0)
            );
            $qrFromAudit = collect($service['_paymentAuditRows'] ?? [])
                ->sum(fn (array $row): float => max(0, (float) data_get($row, 'porMedioPago.qr', 0)));
            // The cashier-flow total must match the API's Facturado amount.
            // Values outside totalMontoVendido are shown separately and must not
            // be added to income; annulled invoices are already excluded there.
            $totalCollected = $soldAmount;
            $qrCollected = min($totalCollected, max($qrFromAudit, $qrOutsideSold));

            $service['totalMontoCobrado'] = max(0, $totalCollected - $qrCollected);
            $service['totalMontoCobradoQr'] = $qrCollected;
            $service['totalMontoRecaudado'] = $service['totalMontoCobrado'] + $service['totalMontoCobradoQr'];

            return $service;
        });
        $receivableRows = $receivableServices->map(function (array $service) use ($collectedQrByService): array {
            $serviceName = (string) ($service['servicio'] ?? '');
            $collectedAmount = max(0, (float) ($service['_montoCobrado'] ?? 0));
            $collectedQrAmount = min(
                $collectedAmount,
                max(0, (float) $collectedQrByService->get($serviceName, 0))
            );

            $service['totalMontoPorCobrar'] = (float) ($service['_montoPendiente']
                ?? max(0, (float) ($service['totalMonto'] ?? 0) - (float) ($service['_montoCobrado'] ?? 0)));
            $service['totalMontoNoIncluidoEnTotalVendido'] = 0.0;
            $service['totalMontoCobradoQr'] = $collectedQrAmount;
            $service['totalMontoCobrado'] = max(0, $collectedAmount - $collectedQrAmount);
            $service['totalMontoRecaudado'] = $service['totalMontoCobrado'] + $service['totalMontoCobradoQr'];

            return $service;
        });
        $nonReceivableCollectionRows = $collectedServiceRows->reject(
            fn (array $service): bool => $this->isCashierFlowReceivableService((string) ($service['servicio'] ?? ''))
        )->map(function (array $service): array {
            $amount = max(0, (float) ($service['totalMonto'] ?? 0));
            $service['totalMontoCobrado'] = $amount;
            $service['totalMontoCobradoQr'] = 0.0;
            $service['totalMontoRecaudado'] = $amount;

            return $service;
        });
        $serviceGroups = $this->buildServiceGroups(
            $services->concat($receivableRows)->concat($nonReceivableCollectionRows),
            mergeContractsAndEca: true,
            labelCombinedReceivablesAsContracts: true
        );
        $aggregateGroup = function (string $name, Collection $source, Collection $children): array {
            return [
                'servicio' => $name,
                'cantidadVentas' => $source->sum('cantidadVentas'),
                'cantidadDetalles' => $source->sum('cantidadDetalles'),
                'totalCantidad' => $source->sum('totalCantidad'),
                'totalMonto' => $source->sum('totalMonto'),
                'totalMontoVendido' => $source->sum(fn (array $group) => $group['totalMontoVendido'] ?? $group['totalMonto'] ?? 0),
                'totalMontoCobrado' => $source->sum('totalMontoCobrado'),
                'totalMontoCobradoQr' => $source->sum('totalMontoCobradoQr'),
                'totalMontoRecaudado' => $source->sum('totalMontoRecaudado'),
                'totalMontoPorCobrar' => $source->sum(fn (array $group) => $group['totalMontoPorCobrar'] ?? 0),
                'totalMontoNoIncluidoEnTotalVendido' => $source->sum('totalMontoNoIncluidoEnTotalVendido'),
                'totalMontoAnulado' => $source->sum('totalMontoAnulado'),
                'ultimaFecha' => $source->pluck('ultimaFecha')->filter()->max(),
                '_ultimaFechaEsCobro' => $source->contains(fn (array $group): bool => (bool) ($group['_ultimaFechaEsCobro'] ?? false)),
                '_meses' => $source->pluck('_meses')->flatten()->unique()->sort()->values()->all(),
                '_children' => $children->sortByDesc(fn (array $child) => $child['totalMontoVendido'] ?? $child['totalMonto'] ?? 0)->values(),
            ];
        };

        $ventanillaGroups = $serviceGroups->reject(fn (array $group): bool => ($group['servicio'] ?? '') === 'Contratos')->values();
        $contractGroups = $serviceGroups->filter(fn (array $group): bool => ($group['servicio'] ?? '') === 'Contratos')->values();
        $groups = collect();

        if ($ventanillaGroups->isNotEmpty()) {
            $groups->push($aggregateGroup('VENTANILLAS', $ventanillaGroups, $ventanillaGroups));
        }

        if ($contractGroups->isNotEmpty()) {
            $contractChildren = $contractGroups
                ->flatMap(fn (array $group) => collect($group['_children'] ?? []))
                ->values();
            $groups->push($aggregateGroup('Contratos', $contractGroups, $contractChildren));
        }

        return $groups->values();
    }

    private function alignCashierFlowVentanillasSalesCount(Collection $serviceGroups, float $uniqueSalesCount): Collection
    {
        // Child service counts may overlap when one sale contains multiple service types.
        return $serviceGroups
            ->map(function (array $group) use ($uniqueSalesCount): array {
                if (($group['servicio'] ?? '') === 'VENTANILLAS') {
                    $group['cantidadVentas'] = $uniqueSalesCount;
                }

                return $group;
            })
            ->values();
    }

    private function buildServiceGroups(
        Collection $services,
        bool $mergeContractsAndEca = false,
        bool $labelCombinedReceivablesAsContracts = false
    ): Collection {
        return $services
            ->map(function (array $service) use ($mergeContractsAndEca): array {
                $serviceName = (string) ($service['servicio'] ?? '');
                $service['_serviceGroupName'] = $mergeContractsAndEca && $this->isCashierFlowReceivableService($serviceName)
                    ? 'Servicio Contratos'
                    : $this->serviceGroupName($serviceName);

                return $service;
            })
            ->groupBy(fn (array $service): string => $this->normalizePersonName((string) $service['_serviceGroupName']))
            ->map(function (Collection $children) use ($labelCombinedReceivablesAsContracts): array {
                $groupName = (string) ($children->first()['_serviceGroupName'] ?? 'Otros servicios');
                $displayChildren = $children->map(function (array $service): array {
                    unset($service['_serviceGroupName']);

                    return $service;
                });

                return [
                    'servicio' => $labelCombinedReceivablesAsContracts && $groupName === 'Servicio Contratos'
                        ? 'Contratos'
                        : $groupName,
                    'cantidadVentas' => $displayChildren->sum('cantidadVentas'),
                    'cantidadDetalles' => $displayChildren->sum('cantidadDetalles'),
                    'totalCantidad' => $displayChildren->sum('totalCantidad'),
                    'totalMonto' => $displayChildren->sum('totalMonto'),
                    'totalMontoVendido' => $displayChildren->sum(fn (array $service) => $service['totalMontoVendido'] ?? $service['totalMonto'] ?? 0),
                    'totalMontoCobrado' => $displayChildren->sum('totalMontoCobrado'),
                    'totalMontoCobradoQr' => $displayChildren->sum('totalMontoCobradoQr'),
                    'totalMontoRecaudado' => $displayChildren->sum('totalMontoRecaudado'),
                    'totalMontoPorCobrar' => $displayChildren->sum(fn (array $service) => $service['totalMontoPorCobrar'] ?? $service['_montoPendiente'] ?? 0),
                    'totalMontoNoIncluidoEnTotalVendido' => $displayChildren->sum('totalMontoNoIncluidoEnTotalVendido'),
                    'totalMontoAnulado' => $displayChildren->sum('totalMontoAnulado'),
                    'ultimaFecha' => $displayChildren->pluck('ultimaFecha')->filter()->max(),
                    '_ultimaFechaEsCobro' => $displayChildren->contains(fn (array $service): bool => (bool) ($service['_esCobroReceivable'] ?? false)),
                    '_meses' => $displayChildren->pluck('_meses')->flatten()->unique()->sort()->values()->all(),
                    '_children' => $displayChildren->sortByDesc('totalMontoVendido')->values(),
                ];
            })
            ->sortByDesc('totalMontoVendido')
            ->values();
    }

    private function buildRegionalBreakdown(Collection $services): Collection
    {
        return $services
            ->flatMap(fn (array $service) => collect($service['_porRegionales'] ?? []))
            ->map(function ($row): array {
                $row = (array) $row;
                $regional = mb_strtoupper(trim((string) ($row['regional'] ?? '')));

                return [
                    'regional' => $regional !== '' ? $regional : 'SIN REGIONAL',
                    'codigosSucursal' => collect($row['codigosSucursal'] ?? [])
                        ->map(fn ($code) => trim((string) $code))
                        ->filter(fn ($code) => $code !== '')
                        ->values()
                        ->all(),
                    'cantidadVentas' => (float) ($row['cantidadVentas'] ?? 0),
                    'cantidadDetalles' => (float) ($row['cantidadDetalles'] ?? 0),
                    'totalCantidad' => (float) ($row['totalCantidad'] ?? 0),
                    'totalMonto' => (float) ($row['totalMonto'] ?? 0),
                    'totalMontoVendido' => (float) ($row['totalMontoVendido'] ?? $row['totalMonto'] ?? 0),
                ];
            })
            ->groupBy('regional')
            ->map(function (Collection $rows, string $regional): array {
                return [
                    'regional' => $regional,
                    'codigosSucursal' => $rows->pluck('codigosSucursal')->flatten()->unique()->sort()->values()->all(),
                    'cantidadVentas' => $rows->sum('cantidadVentas'),
                    'cantidadDetalles' => $rows->sum('cantidadDetalles'),
                    'totalCantidad' => $rows->sum('totalCantidad'),
                    'totalMonto' => $rows->sum('totalMonto'),
                    'totalMontoVendido' => $rows->sum('totalMontoVendido'),
                    'totalMontoNoIncluidoEnTotalVendido' => max(0, $rows->sum('totalMonto') - $rows->sum('totalMontoVendido')),
                ];
            })
            ->sortByDesc('totalMontoVendido')
            ->values();
    }

    private function buildCashierBreakdown(Collection $services): Collection
    {
        return $services
            ->flatMap(fn (array $service) => collect($service['_porPersonas'] ?? []))
            ->map(function ($row): array {
                $row = (array) $row;
                $id = trim((string) ($row['usuarioId'] ?? ''));
                $name = trim((string) ($row['usuarioNombre'] ?? ''));
                $email = trim((string) ($row['usuarioEmail'] ?? ''));
                $alias = trim((string) ($row['usuarioAlias'] ?? ''));

                return [
                    '_identity' => $this->cashierIdentity($id, $email, $alias, $name),
                    'usuarioId' => $id,
                    'usuarioNombre' => $name !== '' ? $name : 'USUARIO SIN IDENTIFICAR',
                    'usuarioEmail' => $email,
                    'usuarioAlias' => $alias,
                    'usuarioCarnet' => trim((string) ($row['usuarioCarnet'] ?? '')),
                    'departamentos' => collect($row['_departamentos'] ?? [])
                        ->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'cantidadVentas' => (float) ($row['cantidadVentas'] ?? 0),
                    'cantidadDetalles' => (float) ($row['cantidadDetalles'] ?? 0),
                    'totalCantidad' => (float) ($row['totalCantidad'] ?? 0),
                    'totalMonto' => (float) ($row['totalMonto'] ?? 0),
                    'totalMontoVendido' => (float) ($row['totalMontoVendido'] ?? $row['totalMonto'] ?? 0),
                    'totalMontoNoIncluidoEnTotalVendido' => max(0, (float) ($row['totalMonto'] ?? 0) - (float) ($row['totalMontoVendido'] ?? $row['totalMonto'] ?? 0)),
                ];
            })
            ->groupBy('_identity')
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $departments = $rows
                    ->pluck('departamentos')
                    ->flatten()
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                return [
                    'usuarioId' => $first['usuarioId'],
                    'usuarioNombre' => $first['usuarioNombre'],
                    'usuarioEmail' => $first['usuarioEmail'],
                    'usuarioAlias' => $first['usuarioAlias'],
                    'usuarioCarnet' => $first['usuarioCarnet'],
                    'departamentos' => $departments->all(),
                    'departamento' => $departments->isNotEmpty()
                        ? $departments->implode(', ')
                        : 'SIN REGIONAL ASIGNADA',
                    'cantidadVentas' => $rows->sum('cantidadVentas'),
                    'cantidadDetalles' => $rows->sum('cantidadDetalles'),
                    'totalCantidad' => $rows->sum('totalCantidad'),
                    'totalMonto' => $rows->sum('totalMonto'),
                    'totalMontoVendido' => $rows->sum('totalMontoVendido'),
                    'totalMontoNoIncluidoEnTotalVendido' => $rows->sum('totalMontoNoIncluidoEnTotalVendido'),
                ];
            })
            ->sortByDesc('totalMontoVendido')
            ->values();
    }

    private function buildCashierDepartmentGroups(Collection $cashierRows): Collection
    {
        return $cashierRows
            ->map(function (array $cashier): array {
                $departments = collect($cashier['departamentos'] ?? [])
                    ->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values();

                if ($departments->isEmpty()) {
                    $departmentLabel = 'SIN REGIONAL ASIGNADA';
                } elseif ($departments->count() === 1) {
                    $departmentLabel = (string) $departments->first();
                } else {
                    // Keep multi-regional cashiers in one group so their income is not duplicated.
                    $departmentLabel = 'VARIOS DEPARTAMENTOS: '.$departments->implode(', ');
                }

                $cashier['departamentoReporte'] = $departmentLabel;

                return $cashier;
            })
            ->groupBy('departamentoReporte')
            ->map(function (Collection $cashiers, string $department): array {
                return [
                    'departamento' => $department,
                    'cajeros' => $cashiers->sortByDesc('totalIngresosIncluyendoPorCobrar')->values(),
                    'cantidadCajeros' => $cashiers->count(),
                    'cantidadVentas' => (float) $cashiers->sum('cantidadVentas'),
                    'cantidadDetalles' => (float) $cashiers->sum('cantidadDetalles'),
                    'totalCantidad' => (float) $cashiers->sum('totalCantidad'),
                    'totalMontoVentanilla' => (float) $cashiers->sum('totalMontoVentanilla'),
                    'totalMontoCobrado' => (float) $cashiers->sum('totalMontoCobrado'),
                    'totalMontoFacturadoContratosEca' => (float) $cashiers->sum('totalMontoFacturadoContratosEca'),
                    'totalIngresos' => (float) $cashiers->sum('totalIngresos'),
                    'totalMontoNoCobrado' => (float) $cashiers->sum('totalMontoNoCobrado'),
                    'totalIngresosIncluyendoPorCobrar' => (float) $cashiers->sum('totalIngresosIncluyendoPorCobrar'),
                    'promedioDiario' => (float) $cashiers->sum('promedioDiario'),
                    'promedioGeneradoDiario' => (float) $cashiers->sum('promedioGeneradoDiario'),
                ];
            })
            ->sortByDesc('totalIngresosIncluyendoPorCobrar')
            ->values();
    }

    /**
     * Replaces department subtotals built from overlapping service rows with
     * the unique sale counts returned by the regional report query.
     * Individual cashier rows remain operation counts because one sale can
     * contain several service lines.
     *
     * @return array{groups: Collection, warning: ?string}
     */
    private function alignCashierFlowDepartmentSalesCounts(Collection $departmentGroups, array $data): array
    {
        if ($departmentGroups->isEmpty()) {
            return ['groups' => $departmentGroups, 'warning' => null];
        }

        if (! (bool) ($data['uniqueSalesCountFromApi'] ?? false)) {
            return [
                'groups' => $departmentGroups,
                'warning' => 'La API no proporcionó un conteo validado de ventas únicas; se conserva el conteo de operaciones por departamento.',
            ];
        }

        $countsByDepartment = collect();
        $selectedDepartment = $this->canonicalDepartmentName((string) ($data['selectedDepartment'] ?? ''));
        if ($selectedDepartment !== '') {
            $countsByDepartment->put($selectedDepartment, (float) ($data['summary']['cantidadVentas'] ?? 0));
        } else {
            $departments = $departmentGroups
                ->pluck('departamento')
                ->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
                ->filter(fn (string $department): bool => in_array($department, self::DASHBOARD_DEPARTMENTS, true))
                ->unique()
                ->values();
            $months = collect($data['selectedMonths'] ?? [])->map(fn ($month): int => (int) $month)->values();
            $selectedServices = collect($data['selectedServices'] ?? [])->map(fn ($service): string => trim((string) $service))->filter()->values();

            if ($departments->isNotEmpty() && $months->isNotEmpty()) {
                $filters = [];
                foreach ($departments as $department) {
                    foreach ($months as $month) {
                        $filter = [
                            'mes' => $month,
                            'anio' => (int) ($data['anio'] ?? now()->year),
                            'limite' => (int) ($data['limite'] ?? 200),
                            'excluirGruposConteo' => ['contratos', 'eca_internacional'],
                            'regionalConteo' => $department,
                        ];
                        if ($selectedServices->isNotEmpty()) {
                            $filter['servicios'] = $selectedServices->all();
                        }
                        $filters[] = $filter;
                    }
                }

                try {
                    $results = $this->reports->servicesBatch($filters, (bool) ($data['refreshReports'] ?? false));
                    $states = [];
                    foreach ($results as $result) {
                        $filter = (array) ($result['filter'] ?? []);
                        $department = $this->canonicalDepartmentName((string) ($filter['regionalConteo'] ?? ''));
                        if ($department === '') {
                            continue;
                        }

                        $state = $states[$department] ?? [
                            'unique' => 0.0,
                            'included' => 0.0,
                            'uniqueComplete' => true,
                            'includedComplete' => true,
                            'months' => 0,
                        ];
                        $report = is_array($result['report'] ?? null) ? $result['report'] : [];
                        $hasUniqueCount = ($result['error'] ?? null) === null
                            && (bool) data_get($report, 'meta.conteoVentasUnicas', false);
                        if ($hasUniqueCount) {
                            $state['unique'] += (float) data_get($report, 'resumen.cantidadVentas', 0);
                            $state['months']++;
                            if (isset($report['resumen']['cantidadVentasIncluidasEnTotalVendido'], $report['resumen']['cantidadVentasNoIncluidasEnTotalVendido'])) {
                                $state['included'] += (float) $report['resumen']['cantidadVentasIncluidasEnTotalVendido'];
                            } else {
                                $state['includedComplete'] = false;
                            }
                        } else {
                            $state['uniqueComplete'] = false;
                            $state['includedComplete'] = false;
                        }
                        $states[$department] = $state;
                    }

                    foreach ($departments as $department) {
                        $state = $states[$department] ?? null;
                        if (! is_array($state)
                            || ! $state['uniqueComplete']
                            || $state['months'] !== $months->count()) {
                            continue;
                        }

                        $countsByDepartment->put(
                            $department,
                            $state['includedComplete'] ? $state['included'] : $state['unique']
                        );
                    }
                } catch (\Throwable $exception) {
                    $this->logDetailError($exception, null, (int) ($months->first() ?? 0), (int) ($data['anio'] ?? now()->year));
                }
            }
        }

        $alignedGroups = $departmentGroups->map(function (array $group) use ($countsByDepartment): array {
            $department = $this->canonicalDepartmentName((string) ($group['departamento'] ?? ''));
            if ($countsByDepartment->has($department)) {
                $group['cantidadVentas'] = (float) $countsByDepartment->get($department);
            }

            return $group;
        })->values();
        $expectedDepartmentCount = $selectedDepartment !== ''
            ? (int) $departmentGroups->contains(fn (array $group): bool => $this->canonicalDepartmentName((string) ($group['departamento'] ?? '')) === $selectedDepartment)
            : $departmentGroups->pluck('departamento')
                ->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
                ->filter(fn (string $department): bool => in_array($department, self::DASHBOARD_DEPARTMENTS, true))
                ->unique()
                ->count();
        $alignedDepartmentCount = $countsByDepartment->keys()->intersect(
            $departmentGroups->pluck('departamento')->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
        )->count();
        $warning = $expectedDepartmentCount > $alignedDepartmentCount
            ? 'No se pudo validar el conteo único de ventas para todos los departamentos; en los departamentos pendientes se conserva el conteo de operaciones.'
            : null;

        return ['groups' => $alignedGroups, 'warning' => $warning];
    }

    private function cashierFlowReceivableDepartmentBreakdown(
        Collection $receivableServices,
        array $months,
        int $year,
        Collection $collectedMovements,
        string $selectedDepartment
    ): array {
        $pendingByDepartment = collect();
        $pendingByService = collect();
        $collectedByService = collect();
        $collectedQrByService = collect();
        $billedByService = collect();
        $invoicers = collect();
        $detailCountsByService = collect();
        $errors = collect();

        if ($receivableServices->isEmpty()) {
            return [
                'complete' => true,
                'pendingByDepartment' => $pendingByDepartment,
                'pendingByService' => $pendingByService,
                'collectedByService' => $collectedByService,
                'collectedQrByService' => $collectedQrByService,
                'billedByService' => $billedByService,
                'invoicers' => $invoicers,
                'errors' => $errors,
            ];
        }

        $filters = $this->cashierFlowInvoiceDetailFilters($receivableServices, $months, $year);
        $movementsByKey = $collectedMovements->keyBy(fn (CashierFlowReceivableMovement $movement): string => (string) $movement->movement_key);
        try {
            $results = $this->reports->serviceDetailsBatch($filters, true, false);
        } catch (\Throwable $exception) {
            $results = array_map(fn (array $filter): array => [
                'filter' => $filter,
                'report' => null,
                'error' => $exception->getMessage(),
            ], $filters);
        }

        $invoicePeople = collect($results)
            ->flatMap(fn (array $result) => collect(data_get($result, 'report.servicio.rows', []))
                ->map(function ($row): array {
                    $row = (array) $row;
                    $invoiceUser = (array) ($row['usuario'] ?? []);

                    return [
                        'usuarioId' => $invoiceUser['id'] ?? $row['usuarioId'] ?? '',
                        'usuarioNombre' => $invoiceUser['nombre'] ?? $invoiceUser['name'] ?? $row['usuarioNombre'] ?? '',
                        'usuarioEmail' => $invoiceUser['email'] ?? $row['usuarioEmail'] ?? '',
                        'usuarioAlias' => $invoiceUser['alias'] ?? $row['usuarioAlias'] ?? '',
                    ];
                }))
            ->concat($receivableServices->flatMap(fn (array $service) => collect($service['_porPersonas'] ?? [])
                ->map(fn ($person): array => (array) $person)));
        $invoiceUserDepartments = $this->cashierFlowInvoiceUserDepartments($invoicePeople);

        foreach ($results as $result) {
            $filter = (array) ($result['filter'] ?? []);
            $serviceName = trim((string) ($filter['servicio'] ?? ''));
            $month = (int) ($filter['mes'] ?? 0);
            if (($result['error'] ?? null) !== null || ! is_array($result['report'] ?? null)) {
                $errors->push("{$serviceName} / {$month}-{$year}: ".(string) ($result['error'] ?? 'No se pudo consultar el detalle.'));

                continue;
            }

            $service = $receivableServices->first(
                fn (array $candidate): bool => (string) ($candidate['servicio'] ?? '') === $serviceName
            );
            $servicePeopleByIdentity = collect($service['_porPersonas'] ?? [])
                ->map(fn ($person): array => (array) $person)
                ->groupBy(fn (array $person): string => $this->cashierIdentity(
                    (string) ($person['usuarioId'] ?? ''),
                    (string) ($person['usuarioEmail'] ?? ''),
                    (string) ($person['usuarioAlias'] ?? ''),
                    (string) ($person['usuarioNombre'] ?? '')
                ))
                ->map(function (Collection $personRows): array {
                    $person = (array) $personRows->first();
                    $person['cantidadVentas'] = (float) $personRows->sum('cantidadVentas');
                    $person['cantidadDetalles'] = (float) $personRows->sum('cantidadDetalles');
                    $person['totalCantidad'] = (float) $personRows->sum('totalCantidad');
                    $person['usuarioCarnet'] = (string) ($personRows->first(
                        fn (array $row): bool => trim((string) ($row['usuarioCarnet'] ?? '')) !== ''
                    )['usuarioCarnet'] ?? '');

                    return $person;
                });
            $rows = $this->attachCashierFlowMovementKeys(
                collect(data_get($result, 'report.servicio.rows', []))->map(fn ($row): array => [
                    ...(array) $row,
                    '_servicio' => $serviceName,
                    '_mes' => $month,
                ]),
                $year
            );
            $detailCountsByService->put($serviceName, (int) $detailCountsByService->get($serviceName, 0) + $rows->count());

            foreach ($rows as $row) {
                if ($this->isCancelledCashierFlowInvoice($row)) {
                    continue;
                }

                $department = $this->cashierFlowReceivableDetailDepartment(
                    $row,
                    $serviceName,
                    $receivableServices,
                    $invoiceUserDepartments,
                    $selectedDepartment
                );
                if ($selectedDepartment !== '' && ! $this->sameNormalizedText($department, $selectedDepartment)) {
                    continue;
                }

                $amount = max(0, round((float) ($row['totalLinea'] ?? $row['monto'] ?? 0), 2));
                if ($amount <= 0) {
                    continue;
                }

                $billedByService->put($serviceName, (float) $billedByService->get($serviceName, 0) + $amount);
                $movement = $movementsByKey->get((string) ($row['_movementKey'] ?? ''));
                $collectedAmount = $movement instanceof CashierFlowReceivableMovement
                    ? min($amount, max(0, (float) $movement->monto))
                    : 0.0;
                if ($collectedAmount > 0 && $movement instanceof CashierFlowReceivableMovement
                    && $this->cashierFlowPaymentMethod($row) === 'qr') {
                    $collectedQrByService->put(
                        $serviceName,
                        (float) $collectedQrByService->get($serviceName, 0) + $collectedAmount
                    );
                }
                $pendingAmount = max(0, $amount - $collectedAmount);
                $pendingByService->put($serviceName, (float) $pendingByService->get($serviceName, 0) + $pendingAmount);
                $collectedByService->put($serviceName, (float) $collectedByService->get($serviceName, 0) + $collectedAmount);

                $invoiceUser = (array) ($row['usuario'] ?? []);
                $invoicePerson = [
                    'usuarioId' => $invoiceUser['id'] ?? $row['usuarioId'] ?? '',
                    'usuarioNombre' => $invoiceUser['nombre'] ?? $invoiceUser['name'] ?? $row['usuarioNombre'] ?? '',
                    'usuarioEmail' => $invoiceUser['email'] ?? $row['usuarioEmail'] ?? '',
                    'usuarioAlias' => $invoiceUser['alias'] ?? $row['usuarioAlias'] ?? '',
                ];
                $invoicerKey = mb_strtoupper($serviceName, 'UTF-8').'|'.$this->cashierIdentity(
                    (string) $invoicePerson['usuarioId'],
                    (string) $invoicePerson['usuarioEmail'],
                    (string) $invoicePerson['usuarioAlias'],
                    (string) $invoicePerson['usuarioNombre']
                );
                $personMetricsKey = $this->matchingCashierRowKey($servicePeopleByIdentity, $invoicePerson);
                $personMetrics = $personMetricsKey !== null
                    ? (array) $servicePeopleByIdentity->get($personMetricsKey)
                    : [];
                $invoicer = $invoicers->get($invoicerKey, [
                    'servicio' => $serviceName,
                    'facturadoPor' => trim((string) $invoicePerson['usuarioNombre']) !== ''
                        ? trim((string) $invoicePerson['usuarioNombre'])
                        : 'USUARIO SIN IDENTIFICAR',
                    'usuarioId' => (string) $invoicePerson['usuarioId'],
                    'usuarioEmail' => (string) $invoicePerson['usuarioEmail'],
                    'usuarioAlias' => (string) $invoicePerson['usuarioAlias'],
                    'usuarioCarnet' => (string) ($personMetrics['usuarioCarnet'] ?? ''),
                    'cantidadVentas' => (float) ($personMetrics['cantidadVentas'] ?? 0),
                    'cantidadDetalles' => (float) ($personMetrics['cantidadDetalles'] ?? 0),
                    'totalCantidad' => (float) ($personMetrics['totalCantidad'] ?? 0),
                    'departamento' => $department,
                    'totalFacturado' => 0.0,
                    'totalCobrado' => 0.0,
                    'totalNoCobrado' => 0.0,
                ]);
                $invoicer['totalFacturado'] += $amount;
                $invoicer['totalCobrado'] += $collectedAmount;
                $invoicer['totalNoCobrado'] += $pendingAmount;
                $invoicers->put($invoicerKey, $invoicer);

                if ($pendingAmount > 0) {
                    $pendingByDepartment->put(
                        $department,
                        (float) $pendingByDepartment->get($department, 0) + $pendingAmount
                    );
                }
            }
        }

        foreach ($receivableServices as $service) {
            $serviceName = (string) ($service['servicio'] ?? '');
            if ((float) ($service['totalMonto'] ?? 0) > 0 && (int) $detailCountsByService->get($serviceName, 0) === 0) {
                $errors->push("{$serviceName}: el detalle no devolvio facturas para distribuir por departamento.");
            }
            $expectedAmount = (float) ($service['totalMonto'] ?? 0);
            $detailAmount = (float) $billedByService->get($serviceName, 0);
            if (abs($expectedAmount - $detailAmount) > 0.05) {
                $errors->push("{$serviceName}: el detalle por factura no coincide con el total del servicio.");
            }
        }

        $complete = $errors->isEmpty();
        if (! $complete) {
            $pendingByService = collect();
            $collectedByService = collect();
            $collectedQrByService = collect();
            $billedByService = collect();
            foreach ($receivableServices as $service) {
                $serviceName = (string) ($service['servicio'] ?? '');
                $collected = $selectedDepartment === ''
                    ? (float) $collectedMovements->where('servicio', $serviceName)->sum('monto')
                    : 0.0;
                $billed = max(0, (float) ($service['totalMonto'] ?? 0));
                $pendingByService->put($serviceName, max(0, $billed - $collected));
                $collectedByService->put($serviceName, $collected);
                $billedByService->put($serviceName, $billed);
            }
            $pendingByDepartment = $this->cashierFlowReceivableSummaryPendingByDepartment(
                $receivableServices,
                $collectedMovements,
                $invoiceUserDepartments,
                $selectedDepartment
            );
            $invoicers = $pendingByDepartment['invoicers'];
            $pendingByDepartment = $pendingByDepartment['pendingByDepartment'];
        }

        return [
            'complete' => $complete,
            'pendingByDepartment' => $pendingByDepartment,
            'pendingByService' => $pendingByService,
            'collectedByService' => $collectedByService,
            'collectedQrByService' => $collectedQrByService,
            'billedByService' => $billedByService,
            'invoicers' => $invoicers->sortByDesc('totalNoCobrado')->values(),
            'errors' => $errors->unique()->values(),
        ];
    }

    private function cashierFlowReceivableSummaryPendingByDepartment(
        Collection $receivableServices,
        Collection $collectedMovements,
        Collection $invoiceUserDepartments,
        string $selectedDepartment
    ): array {
        $pendingByDepartment = collect();
        $invoicers = collect();

        foreach ($receivableServices as $service) {
            $serviceName = (string) ($service['servicio'] ?? '');
            $serviceBilled = max(0, (float) ($service['totalMonto'] ?? 0));
            $serviceCollections = $selectedDepartment === ''
                ? $collectedMovements->where('servicio', $serviceName)
                : collect();
            $servicePending = max(0, $serviceBilled - (float) $serviceCollections->sum('monto'));
            if ($serviceBilled <= 0) {
                continue;
            }

            $people = collect($service['_porPersonas'] ?? [])
                ->map(fn ($person): array => (array) $person)
                ->filter(fn (array $person): bool => (float) ($person['totalMonto'] ?? 0) > 0)
                ->groupBy(fn (array $person): string => $this->cashierIdentity(
                    (string) ($person['usuarioId'] ?? ''),
                    (string) ($person['usuarioEmail'] ?? ''),
                    (string) ($person['usuarioAlias'] ?? ''),
                    (string) ($person['usuarioNombre'] ?? '')
                ))
                ->map(function (Collection $rows): array {
                    $person = (array) $rows->first();
                    $person['totalMonto'] = (float) $rows->sum('totalMonto');
                    $person['cantidadVentas'] = (float) $rows->sum('cantidadVentas');
                    $person['cantidadDetalles'] = (float) $rows->sum('cantidadDetalles');
                    $person['totalCantidad'] = (float) $rows->sum('totalCantidad');
                    $personWithCarnet = $rows->first(fn (array $row): bool => trim((string) ($row['usuarioCarnet'] ?? '')) !== '');
                    $person['usuarioCarnet'] = (string) ($personWithCarnet['usuarioCarnet'] ?? '');
                    $person['_departamentos'] = $rows
                        ->flatMap(fn (array $row) => collect($row['_departamentos'] ?? $row['departamentos'] ?? []))
                        ->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                    return $person;
                })
                ->values();
            $peopleBilled = (float) $people->sum('totalMonto');
            if ($people->isEmpty() || $peopleBilled <= 0) {
                $department = $selectedDepartment !== '' ? $selectedDepartment : 'SIN REGIONAL ASIGNADA';
                if ($servicePending > 0) {
                    $pendingByDepartment->put($department, (float) $pendingByDepartment->get($department, 0) + $servicePending);
                }
                $invoicers->push([
                    'servicio' => $serviceName,
                    'facturadoPor' => 'USUARIO SIN IDENTIFICAR',
                    'usuarioId' => '',
                    'usuarioEmail' => '',
                    'usuarioAlias' => '',
                    'usuarioCarnet' => '',
                    'cantidadVentas' => 0.0,
                    'cantidadDetalles' => 0.0,
                    'totalCantidad' => 0.0,
                    'departamento' => $department,
                    'totalFacturado' => $serviceBilled,
                    'totalCobrado' => (float) $serviceCollections->sum('monto'),
                    'totalNoCobrado' => $servicePending,
                ]);

                continue;
            }

            $peopleByIdentity = $people->keyBy(fn (array $person): string => $this->cashierIdentity(
                (string) ($person['usuarioId'] ?? ''),
                (string) ($person['usuarioEmail'] ?? ''),
                (string) ($person['usuarioAlias'] ?? ''),
                (string) ($person['usuarioNombre'] ?? '')
            ));
            $collectedByPerson = collect();
            foreach ($serviceCollections as $movement) {
                $movementPerson = [
                    'usuarioId' => (string) ($movement->facturado_por_id ?? ''),
                    'usuarioNombre' => (string) ($movement->facturado_por_nombre ?? ''),
                    'usuarioEmail' => (string) ($movement->facturado_por_email ?? ''),
                    'usuarioAlias' => (string) ($movement->facturado_por_alias ?? ''),
                ];
                $personKey = $this->matchingCashierRowKey($peopleByIdentity, $movementPerson);
                if ($personKey !== null) {
                    $collectedByPerson->put(
                        $personKey,
                        (float) $collectedByPerson->get($personKey, 0) + (float) $movement->monto
                    );
                }
            }

            $personWeights = $people->mapWithKeys(function (array $person) use ($peopleBilled, $serviceBilled, $collectedByPerson): array {
                $personKey = $this->cashierIdentity(
                    (string) ($person['usuarioId'] ?? ''),
                    (string) ($person['usuarioEmail'] ?? ''),
                    (string) ($person['usuarioAlias'] ?? ''),
                    (string) ($person['usuarioNombre'] ?? '')
                );
                $personBilled = $serviceBilled * ((float) ($person['totalMonto'] ?? 0) / $peopleBilled);

                return [$personKey => max(0, $personBilled - (float) $collectedByPerson->get($personKey, 0))];
            });
            $weightTotal = (float) $personWeights->sum();
            if ($weightTotal <= 0) {
                $personWeights = $people->mapWithKeys(fn (array $person): array => [
                    $this->cashierIdentity(
                        (string) ($person['usuarioId'] ?? ''),
                        (string) ($person['usuarioEmail'] ?? ''),
                        (string) ($person['usuarioAlias'] ?? ''),
                        (string) ($person['usuarioNombre'] ?? '')
                    ) => (float) ($person['totalMonto'] ?? 0),
                ]);
                $weightTotal = (float) $personWeights->sum();
            }

            foreach ($people as $person) {
                $personKey = $this->cashierIdentity(
                    (string) ($person['usuarioId'] ?? ''),
                    (string) ($person['usuarioEmail'] ?? ''),
                    (string) ($person['usuarioAlias'] ?? ''),
                    (string) ($person['usuarioNombre'] ?? '')
                );
                $department = $this->cashierFlowReceivableDetailDepartment(
                    [
                        'usuario' => [
                            'id' => $person['usuarioId'] ?? '',
                            'nombre' => $person['usuarioNombre'] ?? '',
                            'email' => $person['usuarioEmail'] ?? '',
                            'alias' => $person['usuarioAlias'] ?? '',
                        ],
                    ],
                    $serviceName,
                    $receivableServices,
                    $invoiceUserDepartments,
                    $selectedDepartment
                );
                if ($selectedDepartment !== '' && ! $this->sameNormalizedText($department, $selectedDepartment)) {
                    continue;
                }

                $share = $weightTotal > 0
                    ? $servicePending * ((float) $personWeights->get($personKey, 0) / $weightTotal)
                    : 0.0;
                $personBilled = $serviceBilled * ((float) ($person['totalMonto'] ?? 0) / $peopleBilled);
                $personCollected = max(0, $personBilled - $share);
                if ($share > 0) {
                    $pendingByDepartment->put(
                        $department,
                        (float) $pendingByDepartment->get($department, 0) + $share
                    );
                }
                $invoicers->push([
                    'servicio' => $serviceName,
                    'facturadoPor' => trim((string) ($person['usuarioNombre'] ?? '')) !== ''
                        ? trim((string) $person['usuarioNombre'])
                        : 'USUARIO SIN IDENTIFICAR',
                    'usuarioId' => (string) ($person['usuarioId'] ?? ''),
                    'usuarioEmail' => (string) ($person['usuarioEmail'] ?? ''),
                    'usuarioAlias' => (string) ($person['usuarioAlias'] ?? ''),
                    'usuarioCarnet' => (string) ($person['usuarioCarnet'] ?? ''),
                    'cantidadVentas' => (float) ($person['cantidadVentas'] ?? 0),
                    'cantidadDetalles' => (float) ($person['cantidadDetalles'] ?? 0),
                    'totalCantidad' => (float) ($person['totalCantidad'] ?? 0),
                    'departamento' => $department,
                    'totalFacturado' => $personBilled,
                    'totalCobrado' => $personCollected,
                    'totalNoCobrado' => $share,
                ]);
            }
        }

        return [
            'pendingByDepartment' => $pendingByDepartment,
            'invoicers' => $invoicers->sortByDesc('totalNoCobrado')->values(),
        ];
    }

    private function cashierFlowReceivableDetailDepartment(
        array $row,
        string $serviceName,
        Collection $services,
        Collection $invoiceUserDepartments,
        string $selectedDepartment = ''
    ): string {
        $service = $services->first(
            fn (array $candidate): bool => (string) ($candidate['servicio'] ?? '') === $serviceName
        );
        $invoiceUser = (array) ($row['usuario'] ?? []);
        $invoicePerson = [
            'usuarioId' => $invoiceUser['id'] ?? $row['usuarioId'] ?? '',
            'usuarioNombre' => $invoiceUser['nombre'] ?? $invoiceUser['name'] ?? $row['usuarioNombre'] ?? '',
            'usuarioEmail' => $invoiceUser['email'] ?? $row['usuarioEmail'] ?? '',
            'usuarioAlias' => $invoiceUser['alias'] ?? $row['usuarioAlias'] ?? '',
        ];
        $invoiceProfileDepartmentKeys = collect([
            filled($invoicePerson['usuarioId']) ? 'id:'.trim((string) $invoicePerson['usuarioId']) : null,
            filled($invoicePerson['usuarioEmail']) ? 'email:'.mb_strtolower(trim((string) $invoicePerson['usuarioEmail'])) : null,
            filled($invoicePerson['usuarioAlias']) ? 'user:'.mb_strtolower(trim((string) $invoicePerson['usuarioAlias'])) : null,
        ])->filter();
        $invoiceProfileDepartmentKey = $invoiceProfileDepartmentKeys
            ->first(fn (string $key): bool => $invoiceUserDepartments->has($key));
        $departments = $invoiceProfileDepartmentKey !== null
            ? collect($invoiceUserDepartments->get($invoiceProfileDepartmentKey, []))
            : collect();

        $people = collect($service['_porPersonas'] ?? [])->map(fn ($person): array => (array) $person);
        $peopleByIdentity = $people->keyBy(fn (array $person): string => $this->cashierIdentity(
            (string) ($person['usuarioId'] ?? ''),
            (string) ($person['usuarioEmail'] ?? ''),
            (string) ($person['usuarioAlias'] ?? ''),
            (string) ($person['usuarioNombre'] ?? '')
        ));
        $personKey = $this->matchingCashierRowKey($peopleByIdentity, $invoicePerson);
        $person = $personKey !== null ? $peopleByIdentity->get($personKey) : null;
        if ($departments->isEmpty()) {
            $departments = collect($person['_departamentos'] ?? $person['departamentos'] ?? [])
                ->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
                ->filter()
                ->unique()
                ->sort()
                ->values();
        }
        if ($departments->isEmpty() && filled($person['departamento'] ?? null)) {
            $departments = collect(explode(',', (string) $person['departamento']))
                ->map(fn ($department): string => $this->canonicalDepartmentName($department))
                ->filter()
                ->unique()
                ->sort()
                ->values();
        }
        if ($selectedDepartment !== '' && $departments->contains(
            fn (string $department): bool => $this->sameNormalizedText($department, $selectedDepartment)
        )) {
            return $selectedDepartment;
        }
        if ($departments->isNotEmpty()) {
            return $departments->count() === 1
                ? (string) $departments->first()
                : 'VARIOS DEPARTAMENTOS: '.$departments->implode(', ');
        }

        $rowDepartment = $this->firstReportTextValue(
            $row['departamento'] ?? null,
            $row['regional'] ?? null,
            $row['regionalNombre'] ?? null
        );
        if ($rowDepartment !== '') {
            return $this->canonicalDepartmentName($rowDepartment);
        }
        if (isset($row['codigosSucursal']) && is_array($row['codigosSucursal'])) {
            return $this->departmentNameFromRegionalRow($row);
        }

        return 'SIN REGIONAL ASIGNADA';
    }

    private function cashierFlowInvoiceUserDepartments(Collection $invoicePeople): Collection
    {
        if ($invoicePeople->isEmpty() || ! Schema::hasTable('users')) {
            return collect();
        }

        $ids = $invoicePeople->pluck('usuarioId')
            ->filter(fn ($id): bool => is_numeric($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $emails = $invoicePeople->pluck('usuarioEmail')
            ->map(fn ($email): string => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
        $aliases = $invoicePeople->pluck('usuarioAlias')
            ->map(fn ($alias): string => mb_strtolower(trim((string) $alias)))
            ->filter()
            ->unique()
            ->values();
        $hasAlias = Schema::hasColumn('users', 'alias');

        if ($ids->isEmpty() && $emails->isEmpty() && ($aliases->isEmpty() || ! $hasAlias)) {
            return collect();
        }

        $users = User::withTrashed()
            ->where(function ($query) use ($ids, $emails, $aliases, $hasAlias): void {
                if ($ids->isNotEmpty()) {
                    $query->whereIn('id', $ids->all());
                }
                if ($emails->isNotEmpty()) {
                    $method = $ids->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('email', $emails->all());
                }
                if ($aliases->isNotEmpty() && $hasAlias) {
                    $method = $ids->isNotEmpty() || $emails->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('alias', $aliases->all());
                }
            })
            ->get();
        $departmentsByIdentity = collect();

        foreach ($users as $user) {
            $departments = collect($user->regionalesLista())
                ->map(fn (string $department): string => $this->canonicalDepartmentName($department))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();

            $id = trim((string) $user->id);
            $email = mb_strtolower(trim((string) $user->email));
            $alias = mb_strtolower(trim((string) $user->alias));
            if ($id !== '') {
                $departmentsByIdentity->put('id:'.$id, $departments);
            }
            if ($email !== '') {
                $departmentsByIdentity->put('email:'.$email, $departments);
            }
            if ($alias !== '') {
                $departmentsByIdentity->put('user:'.$alias, $departments);
            }
        }

        return $departmentsByIdentity;
    }

    private function addReceivablesToCashierDepartmentGroups(
        Collection $departmentGroups,
        Collection $pendingByDepartment,
        int $reportDays
    ): Collection {
        $groupsByName = $departmentGroups->keyBy(fn (array $group): string => $this->normalizePersonName((string) $group['departamento']));
        foreach ($groupsByName as $key => $group) {
            $group['totalMontoNoCobrado'] = (float) ($group['totalMontoNoCobrado'] ?? 0);
            $group['totalIngresosIncluyendoPorCobrar'] = (float) ($group['totalIngresosIncluyendoPorCobrar'] ?? $group['totalIngresos'] ?? 0);
            $group['promedioGeneradoDiario'] = $reportDays > 0
                ? $group['totalIngresosIncluyendoPorCobrar'] / $reportDays
                : 0.0;
            $groupsByName->put($key, $group);
        }

        foreach ($pendingByDepartment as $department => $pendingAmount) {
            $department = trim((string) $department) !== '' ? trim((string) $department) : 'SIN REGIONAL ASIGNADA';
            $key = $this->normalizePersonName($department);
            $group = $groupsByName->get($key, [
                'departamento' => $department,
                'cajeros' => collect(),
                'cantidadCajeros' => 0,
                'cantidadVentas' => 0.0,
                'cantidadDetalles' => 0.0,
                'totalCantidad' => 0.0,
                'totalMontoVentanilla' => 0.0,
                'totalMontoCobrado' => 0.0,
                'totalMontoFacturadoContratosEca' => 0.0,
                'totalIngresos' => 0.0,
                'promedioDiario' => 0.0,
                'promedioGeneradoDiario' => 0.0,
                'totalMontoNoCobrado' => 0.0,
                'totalIngresosIncluyendoPorCobrar' => 0.0,
            ]);
            $alreadyIncludedPending = (float) collect($group['cajeros'] ?? [])->sum('totalMontoNoCobrado');
            $pendingToInclude = max(0, (float) $pendingAmount - $alreadyIncludedPending);
            $group['totalMontoNoCobrado'] += $pendingToInclude;
            $group['totalIngresos'] = (float) ($group['totalIngresos'] ?? 0) + $pendingToInclude;
            $group['totalIngresosIncluyendoPorCobrar'] = (float) $group['totalIngresos'];
            $group['promedioGeneradoDiario'] = $reportDays > 0
                ? $group['totalIngresosIncluyendoPorCobrar'] / $reportDays
                : 0.0;
            $groupsByName->put($key, $group);
        }

        return $groupsByName
            ->sortByDesc('totalIngresosIncluyendoPorCobrar')
            ->values();
    }

    private function addCashierReceivableIncome(Collection $cashierRows, Collection $movements): Collection
    {
        $rows = $cashierRows
            ->map(function (array $cashier): array {
                $cashier['totalMontoVentanilla'] = (float) ($cashier['totalMontoVendido'] ?? $cashier['totalMonto'] ?? 0);
                $cashier['totalMontoCobrado'] = (float) ($cashier['totalMontoCobrado'] ?? 0);
                $cashier['totalIngresos'] = $cashier['totalMontoVentanilla'] + $cashier['totalMontoCobrado'];

                return $cashier;
            })
            ->keyBy(fn (array $cashier): string => $this->cashierIdentity(
                (string) ($cashier['usuarioId'] ?? ''),
                (string) ($cashier['usuarioEmail'] ?? ''),
                (string) ($cashier['usuarioAlias'] ?? ''),
                (string) ($cashier['usuarioNombre'] ?? '')
            ));

        $acceptedByCashier = $movements
            ->groupBy(function (CashierFlowReceivableMovement $movement): string {
                return $this->cashierIdentity(
                    (string) $movement->facturado_por_id,
                    (string) $movement->facturado_por_email,
                    (string) $movement->facturado_por_alias,
                    (string) $movement->facturado_por_nombre
                );
            })
            ->map(function (Collection $cashierMovements): array {
                /** @var CashierFlowReceivableMovement $first */
                $first = $cashierMovements->first();
                $id = trim((string) $first->facturado_por_id);
                $email = trim((string) $first->facturado_por_email);
                $alias = trim((string) $first->facturado_por_alias);
                $name = trim((string) $first->facturado_por_nombre);

                return [
                    'usuarioId' => $id,
                    'usuarioNombre' => $name !== '' ? $name : ($alias !== '' ? $alias : ($id !== '' ? $id : 'USUARIO SIN IDENTIFICAR')),
                    'usuarioEmail' => $email,
                    'usuarioAlias' => $alias,
                    'usuarioCarnet' => '',
                    'departamentos' => [],
                    'departamento' => 'SIN REGIONAL ASIGNADA',
                    'cantidadVentas' => 0.0,
                    'cantidadDetalles' => 0.0,
                    'totalCantidad' => 0.0,
                    'totalMonto' => 0.0,
                    'totalMontoVentanilla' => 0.0,
                    'totalMontoCobrado' => (float) $cashierMovements->sum('monto'),
                    'totalIngresos' => (float) $cashierMovements->sum('monto'),
                ];
            });

        foreach ($acceptedByCashier as $identity => $accepted) {
            $cashierKey = $rows->has($identity)
                ? $identity
                : $this->matchingCashierRowKey($rows, $accepted);
            $cashier = $cashierKey !== null ? $rows->get($cashierKey) : null;
            if ($cashier !== null) {
                $cashier['totalMontoCobrado'] += (float) $accepted['totalMontoCobrado'];
                $cashier['totalIngresos'] = $cashier['totalMontoVentanilla'] + $cashier['totalMontoCobrado'];
                $rows->put($cashierKey, $cashier);

                continue;
            }

            $rows->put($identity, $accepted);
        }

        return $rows->sortByDesc('totalIngresos')->values();
    }

    private function addCashierFlowReceivableInvoicers(Collection $cashierRows, Collection $invoicers): Collection
    {
        $rows = $cashierRows
            ->map(function (array $cashier): array {
                $cashier['totalIngresos'] = (float) ($cashier['totalIngresos'] ?? $cashier['totalMontoVentanilla'] ?? 0);
                $cashier['totalMontoFacturadoContratosEca'] = (float) ($cashier['totalMontoFacturadoContratosEca'] ?? 0);
                $cashier['totalMontoNoCobrado'] = (float) ($cashier['totalMontoNoCobrado'] ?? 0);
                $cashier['cantidadVentas'] = (float) ($cashier['cantidadVentas'] ?? 0);
                $cashier['cantidadDetalles'] = (float) ($cashier['cantidadDetalles'] ?? 0);
                $cashier['totalCantidad'] = (float) ($cashier['totalCantidad'] ?? 0);
                $cashier['totalIngresosIncluyendoPorCobrar'] = $cashier['totalIngresos'] + $cashier['totalMontoNoCobrado'];

                return $cashier;
            })
            ->keyBy(fn (array $cashier): string => $this->cashierIdentity(
                (string) ($cashier['usuarioId'] ?? ''),
                (string) ($cashier['usuarioEmail'] ?? ''),
                (string) ($cashier['usuarioAlias'] ?? ''),
                (string) ($cashier['usuarioNombre'] ?? '')
            ));

        $invoicersByIdentity = $invoicers->groupBy(fn (array $invoicer): string => $this->cashierIdentity(
            (string) ($invoicer['usuarioId'] ?? ''),
            (string) ($invoicer['usuarioEmail'] ?? ''),
            (string) ($invoicer['usuarioAlias'] ?? ''),
            (string) ($invoicer['facturadoPor'] ?? '')
        ));

        foreach ($invoicersByIdentity as $identity => $personInvoices) {
            $firstInvoice = (array) $personInvoices->first();
            $person = [
                'usuarioId' => (string) ($firstInvoice['usuarioId'] ?? ''),
                'usuarioNombre' => trim((string) ($firstInvoice['facturadoPor'] ?? '')) ?: 'USUARIO SIN IDENTIFICAR',
                'usuarioEmail' => (string) ($firstInvoice['usuarioEmail'] ?? ''),
                'usuarioAlias' => (string) ($firstInvoice['usuarioAlias'] ?? ''),
            ];
            $cashierKey = $rows->has($identity)
                ? (string) $identity
                : $this->matchingCashierRowKey($rows, $person);
            $cashier = $cashierKey !== null ? $rows->get($cashierKey) : null;
            $departments = $personInvoices
                ->pluck('departamento')
                ->flatMap(function ($department): array {
                    $department = trim((string) $department);
                    if (str_starts_with(mb_strtoupper($department, 'UTF-8'), 'VARIOS DEPARTAMENTOS:')) {
                        $department = trim(substr($department, strlen('VARIOS DEPARTAMENTOS:')));
                    }

                    return $department !== '' ? explode(',', $department) : [];
                })
                ->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
                ->filter(fn (string $department): bool => $department !== '' && ! $this->sameNormalizedText($department, 'SIN REGIONAL ASIGNADA'))
                ->unique()
                ->sort()
                ->values();

            if ($cashier === null) {
                $cashier = [
                    'usuarioId' => $person['usuarioId'],
                    'usuarioNombre' => $person['usuarioNombre'],
                    'usuarioEmail' => $person['usuarioEmail'],
                    'usuarioAlias' => $person['usuarioAlias'],
                    'usuarioCarnet' => '',
                    'departamentos' => [],
                    'departamento' => 'SIN REGIONAL ASIGNADA',
                    'cantidadVentas' => 0.0,
                    'cantidadDetalles' => 0.0,
                    'totalCantidad' => 0.0,
                    'totalMonto' => 0.0,
                    'totalMontoVendido' => 0.0,
                    'totalMontoNoIncluidoEnTotalVendido' => 0.0,
                    'totalMontoVentanilla' => 0.0,
                    'totalMontoCobrado' => 0.0,
                    'totalIngresos' => 0.0,
                    'totalMontoFacturadoContratosEca' => 0.0,
                    'totalMontoNoCobrado' => 0.0,
                    'totalIngresosIncluyendoPorCobrar' => 0.0,
                ];
                $cashierKey = (string) $identity;
            }

            $cashier['departamentos'] = collect($cashier['departamentos'] ?? [])
                ->concat($departments)
                ->map(fn ($department): string => $this->canonicalDepartmentName((string) $department))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
            $cashier['departamento'] = $cashier['departamentos'] !== []
                ? implode(', ', $cashier['departamentos'])
                : (string) ($cashier['departamento'] ?? 'SIN REGIONAL ASIGNADA');
            $cashier['cantidadVentas'] = (float) ($cashier['cantidadVentas'] ?? 0) + (float) $personInvoices->sum('cantidadVentas');
            $cashier['cantidadDetalles'] = (float) ($cashier['cantidadDetalles'] ?? 0) + (float) $personInvoices->sum('cantidadDetalles');
            $cashier['totalCantidad'] = (float) ($cashier['totalCantidad'] ?? 0) + (float) $personInvoices->sum('totalCantidad');
            if (trim((string) ($cashier['usuarioCarnet'] ?? '')) === '') {
                $cashier['usuarioCarnet'] = (string) ($firstInvoice['usuarioCarnet'] ?? '');
            }
            $cashier['totalMontoFacturadoContratosEca'] += (float) $personInvoices->sum('totalFacturado');
            $cashier['totalMontoNoCobrado'] += (float) $personInvoices->sum('totalNoCobrado');
            $cashier['totalIngresos'] += (float) $personInvoices->sum('totalNoCobrado');
            $cashier['totalIngresosIncluyendoPorCobrar'] = (float) $cashier['totalIngresos'];
            $rows->put((string) $cashierKey, $cashier);
        }

        return $rows->sortByDesc('totalIngresosIncluyendoPorCobrar')->values();
    }

    private function matchingCashierRowKey(Collection $cashierRows, array $person): ?string
    {
        $personId = trim((string) ($person['usuarioId'] ?? ''));
        $personEmail = mb_strtolower(trim((string) ($person['usuarioEmail'] ?? '')));
        $personAlias = mb_strtolower(trim((string) ($person['usuarioAlias'] ?? '')));
        $personName = $this->normalizePersonName((string) ($person['usuarioNombre'] ?? ''));

        foreach ($cashierRows as $key => $cashier) {
            $cashierId = trim((string) ($cashier['usuarioId'] ?? ''));
            $cashierEmail = mb_strtolower(trim((string) ($cashier['usuarioEmail'] ?? '')));
            $cashierAlias = mb_strtolower(trim((string) ($cashier['usuarioAlias'] ?? '')));
            $cashierName = $this->normalizePersonName((string) ($cashier['usuarioNombre'] ?? ''));
            $sameName = $personName !== '' && $personName === $cashierName;

            // The report's visible "Facturó" name is the source of truth for assigning these collections.
            if ($sameName) {
                return (string) $key;
            }

            if ($personId !== '' && $cashierId !== '' && $personId !== $cashierId) {
                continue;
            }

            $sameEmail = $personEmail !== '' && $personEmail === $cashierEmail;
            $sameAlias = $personAlias !== '' && $personAlias === $cashierAlias;
            if ($sameEmail || $sameAlias) {
                return (string) $key;
            }
        }

        return null;
    }

    private function addCashierFlowPaymentBreakdown(
        Collection $cashierRows,
        Collection $detailPaymentBreakdown,
        Collection $collectedMovements,
        Collection $movementPaymentMethods
    ): Collection {
        $paymentBreakdown = $detailPaymentBreakdown;
        $cashierRowsByIdentity = $cashierRows->keyBy(fn (array $cashier): string => $this->cashierIdentity(
            (string) ($cashier['usuarioId'] ?? ''),
            (string) ($cashier['usuarioEmail'] ?? ''),
            (string) ($cashier['usuarioAlias'] ?? ''),
            (string) ($cashier['usuarioNombre'] ?? '')
        ));

        foreach ($collectedMovements as $movement) {
            $movementCashier = [
                'usuarioId' => (string) $movement->facturado_por_id,
                'usuarioEmail' => (string) $movement->facturado_por_email,
                'usuarioAlias' => (string) $movement->facturado_por_alias,
                'usuarioNombre' => (string) $movement->facturado_por_nombre,
            ];
            $movementCashierIdentity = $this->cashierIdentity(
                (string) $movement->facturado_por_id,
                (string) $movement->facturado_por_email,
                (string) $movement->facturado_por_alias,
                (string) $movement->facturado_por_nombre
            );
            $cashierIdentity = $cashierRowsByIdentity->has($movementCashierIdentity)
                ? $movementCashierIdentity
                : ($this->matchingCashierRowKey($cashierRowsByIdentity, $movementCashier) ?? $movementCashierIdentity);
            $paymentMethod = (string) $movementPaymentMethods->get((string) $movement->movement_key, 'otros');
            if (! in_array($paymentMethod, ['qr', 'efectivo', 'otros'], true)) {
                $paymentMethod = 'otros';
            }

            $cashierMethods = $paymentBreakdown->get($cashierIdentity, []);
            $methodStats = $cashierMethods[$paymentMethod] ?? ['monto' => 0.0];
            $methodStats['monto'] += (float) $movement->monto;
            $cashierMethods[$paymentMethod] = $methodStats;
            $paymentBreakdown->put($cashierIdentity, $cashierMethods);
        }

        return $cashierRows->map(function (array $cashier) use ($paymentBreakdown): array {
            $cashierIdentity = $this->cashierIdentity(
                (string) ($cashier['usuarioId'] ?? ''),
                (string) ($cashier['usuarioEmail'] ?? ''),
                (string) ($cashier['usuarioAlias'] ?? ''),
                (string) ($cashier['usuarioNombre'] ?? '')
            );
            $cashierMethods = $paymentBreakdown->get($cashierIdentity, []);
            $cashier['paymentMethods'] = [];

            foreach (['qr', 'efectivo', 'otros'] as $method) {
                $methodStats = (array) ($cashierMethods[$method] ?? []);
                $cashier['paymentMethods'][$method] = [
                    'totalMonto' => (float) ($methodStats['monto'] ?? 0),
                ];
            }

            $knownAmount = (float) collect($cashier['paymentMethods'])->sum('totalMonto');
            $collectedIncome = max(0, (float) ($cashier['totalIngresos'] ?? $cashier['totalMonto'] ?? 0)
                - (float) ($cashier['totalMontoNoCobrado'] ?? 0));
            $unclassifiedAmount = max(0, round(
                $collectedIncome - $knownAmount,
                2
            ));
            $cashier['paymentMethods']['otros']['totalMonto'] += $unclassifiedAmount;

            return $cashier;
        });
    }

    private function cashierIdentity(string $id, string $email, string $alias, string $name): string
    {
        $id = trim($id);
        $email = trim($email);
        $alias = trim($alias);
        $name = trim($name);

        if ($id !== '') {
            return 'id:'.$id;
        }
        if ($email !== '') {
            return 'email:'.mb_strtolower($email);
        }

        $user = mb_strtolower($alias !== '' ? $alias : $name);

        return $user !== '' ? 'user:'.$user : 'user:sin-identificar';
    }

    private function addCashierPeriodAverages(Collection $cashierRows, array $months, int $year): Collection
    {
        $reportDays = $this->countReportDaysExcludingSundays($months, $year);

        return $cashierRows->map(function (array $cashier) use ($reportDays): array {
            $cashier['promedioPaquetesDiario'] = $reportDays > 0
                ? (float) ($cashier['totalCantidad'] ?? 0) / $reportDays
                : 0.0;
            $cashier['promedioDiario'] = $reportDays > 0
                ? (float) ($cashier['totalIngresos'] ?? $cashier['totalMonto'] ?? 0) / $reportDays
                : 0.0;
            $cashier['promedioGeneradoDiario'] = $reportDays > 0
                ? (float) ($cashier['totalIngresosIncluyendoPorCobrar'] ?? $cashier['totalIngresos'] ?? 0) / $reportDays
                : 0.0;

            return $cashier;
        });
    }

    private function countReportDaysExcludingSundays(array $months, int $year): int
    {
        $reportDays = 0;

        foreach (collect($months)->map(fn ($month): int => (int) $month)->unique() as $month) {
            if ($month < 1 || $month > 12) {
                continue;
            }

            $date = Carbon::create($year, $month, 1);
            $daysInMonth = $date->daysInMonth;

            for ($day = 1; $day <= $daysInMonth; $day++) {
                if (! $date->copy()->day($day)->isSunday()) {
                    $reportDays++;
                }
            }
        }

        return $reportDays;
    }

    private function cashierFlowReceivableScopeHash(
        array $months,
        int $year,
        int $limit,
        string $department,
        string $service
    ): string {
        $scope = [
            'anio' => $year,
            'meses' => collect($months)->map(fn ($month): int => (int) $month)->unique()->sort()->values()->all(),
            'limite' => $limit,
            'departamento' => $this->normalizePersonName($department),
            'servicio' => trim($service),
        ];

        return hash('sha256', json_encode($scope, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function isCashierFlowReceivableService(string $service): bool
    {
        return $this->serviceGroupName($service) === 'Servicio Contratos'
            || $this->isEcaInternationalService($service);
    }

    private function isEcaInternationalService(string $service): bool
    {
        $normalizedService = $this->normalizePersonName($service);

        return str_contains($normalizedService, 'ECA')
            && str_contains($normalizedService, 'INTERNACIONAL');
    }

    private function attachCashierFlowMovementKeys(Collection $rows, int $year): Collection
    {
        $occurrences = [];

        return $rows->map(function ($row) use (&$occurrences, $year): array {
            $row = (array) $row;
            $identity = [
                'servicio' => (string) ($row['_servicio'] ?? ''),
                'anio' => $year,
                'mes' => (int) ($row['_mes'] ?? 0),
                'venta' => (string) ($row['ventaId'] ?? ''),
                'detalle' => (string) ($row['detalleId'] ?? ''),
                'orden' => (string) ($row['codigoOrden'] ?? ''),
                'seguimiento' => (string) ($row['codigoSeguimiento'] ?? ''),
                'fecha' => (string) ($row['fecha'] ?? ''),
                'monto' => number_format((float) ($row['totalLinea'] ?? 0), 2, '.', ''),
                'descripcion' => (string) ($row['descripcion'] ?? ''),
            ];
            $identityHash = hash('sha256', json_encode($identity, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $occurrence = $occurrences[$identityHash] ?? 0;
            $occurrences[$identityHash] = $occurrence + 1;
            $row['_movementKey'] = hash('sha256', $identityHash.':'.$occurrence);

            return $row;
        });
    }

    private function excludeCashiersFromServices(Collection $services, array $excludedNames): Collection
    {
        $normalizedExcludedNames = collect($excludedNames)
            ->map(fn (string $name): string => $this->normalizePersonName($name))
            ->filter()
            ->values();

        return $services
            ->map(function (array $service) use ($normalizedExcludedNames): array {
                $people = collect($service['_porPersonas'] ?? [])->map(fn ($row): array => (array) $row);
                $excludedPeople = $people->filter(function (array $person) use ($normalizedExcludedNames): bool {
                    $personName = $this->normalizePersonName((string) ($person['usuarioNombre'] ?? ''));

                    return $normalizedExcludedNames->contains(function (string $excludedName) use ($personName): bool {
                        $tokens = array_filter(explode(' ', $excludedName));

                        return $personName !== ''
                            && $tokens !== []
                            && collect($tokens)->every(fn (string $token): bool => str_contains($personName, $token));
                    });
                });

                $service['_porPersonas'] = $people->diffKeys($excludedPeople)->values()->all();
                foreach (['cantidadVentas', 'cantidadDetalles', 'totalCantidad', 'totalMonto', 'totalMontoVendido', 'totalMontoNoIncluidoEnTotalVendido'] as $totalKey) {
                    $service[$totalKey] = max(
                        0,
                        (float) ($service[$totalKey] ?? 0) - (float) $excludedPeople->sum($totalKey)
                    );
                }

                return $service;
            })
            ->sortByDesc('totalMontoVendido')
            ->values();
    }

    private function departmentOptions(Collection $services, string $selectedDepartment): Collection
    {
        $options = $services
            ->flatMap(fn (array $service) => collect($service['_porRegionales'] ?? []))
            ->map(fn ($row): string => $this->departmentNameFromRegionalRow((array) $row))
            ->filter()
            ->unique(fn (string $department): string => $this->normalizePersonName($department))
            ->sort()
            ->values();

        if (
            $selectedDepartment !== ''
            && ! $options->contains(fn (string $department): bool => $this->sameNormalizedText($department, $selectedDepartment))
        ) {
            $options->push($selectedDepartment);
        }

        return $options;
    }

    private function filterServicesByDepartment(Collection $services, string $selectedDepartment): Collection
    {
        return $services
            ->map(function (array $service) use ($selectedDepartment): array {
                $regionalRows = collect($service['_porRegionales'] ?? [])
                    ->map(fn ($row): array => (array) $row)
                    ->filter(fn (array $row): bool => $this->sameNormalizedText(
                        $this->departmentNameFromRegionalRow($row),
                        $selectedDepartment
                    ));

                $service['_porRegionales'] = $regionalRows->values()->all();
                $service['_porPersonas'] = collect($service['_porPersonas'] ?? [])
                    ->map(fn ($row): array => (array) $row)
                    ->filter(fn (array $person): bool => collect($person['_departamentos'] ?? [])
                        ->contains(fn ($department): bool => $this->sameNormalizedText(
                            (string) $department,
                            $selectedDepartment
                        )))
                    ->map(function (array $person) use ($selectedDepartment): array {
                        $person['_departamentos'] = collect($person['_departamentos'] ?? [])
                            ->filter(fn ($department): bool => $this->sameNormalizedText(
                                (string) $department,
                                $selectedDepartment
                            ))
                            ->values()
                            ->all();

                        return $person;
                    })
                    ->values()
                    ->all();

                foreach (['cantidadVentas', 'cantidadDetalles', 'totalCantidad', 'totalMonto'] as $totalKey) {
                    $service[$totalKey] = (float) $regionalRows->sum($totalKey);
                }
                $service['totalMontoVendido'] = (float) $regionalRows->sum(fn (array $row) => $row['totalMontoVendido'] ?? $row['totalMonto'] ?? 0);
                $service['totalMontoNoIncluidoEnTotalVendido'] = max(0, $service['totalMonto'] - $service['totalMontoVendido']);
                // La API aún no expone anuladas dentro de la dimensión regional.
                $service['totalMontoAnulado'] = null;

                return $service;
            })
            ->filter(fn (array $service): bool => collect($service['_porRegionales'] ?? [])->isNotEmpty())
            ->sortByDesc('totalMontoVendido')
            ->values();
    }

    private function attachDepartmentsToPeople(Collection $services): Collection
    {
        $reportUsers = $this->reportUsersForPeople($services);

        return $services->map(function (array $service) use ($reportUsers): array {
            $service['_porPersonas'] = collect($service['_porPersonas'] ?? [])
                ->map(function ($row) use ($reportUsers): array {
                    $person = (array) $row;
                    $person['_departamentos'] = $this->personDepartments($person, $reportUsers);

                    return $person;
                })
                ->values()
                ->all();

            return $service;
        })->values();
    }

    private function reportUsersForPeople(Collection $services): Collection
    {
        if (! Schema::hasTable('users')) {
            return collect();
        }

        $people = $services
            ->flatMap(fn (array $service) => collect($service['_porPersonas'] ?? []))
            ->map(fn ($row): array => (array) $row);
        $ids = $people->pluck('usuarioId')->filter(fn ($id): bool => is_numeric($id))->map(fn ($id): int => (int) $id)->unique();
        $emails = $people->pluck('usuarioEmail')->map(fn ($email): string => mb_strtolower(trim((string) $email)))->filter()->unique();
        $aliases = $people->pluck('usuarioAlias')->map(fn ($alias): string => mb_strtolower(trim((string) $alias)))->filter()->unique();

        if ($ids->isEmpty() && $emails->isEmpty() && $aliases->isEmpty()) {
            return collect();
        }

        return User::query()
            ->where(function ($query) use ($ids, $emails, $aliases): void {
                if ($ids->isNotEmpty()) {
                    $query->whereIn('id', $ids->all());
                }
                if ($emails->isNotEmpty()) {
                    $method = $ids->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('email', $emails->all());
                }
                if ($aliases->isNotEmpty() && Schema::hasColumn('users', 'alias')) {
                    $method = $ids->isNotEmpty() || $emails->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('alias', $aliases->all());
                }
            })
            ->get();
    }

    private function personDepartments(array $person, Collection $reportUsers): array
    {
        foreach (['regional', 'departamento', 'ciudad'] as $departmentKey) {
            $personDepartment = trim((string) ($person[$departmentKey] ?? ''));
            if ($personDepartment !== '') {
                return [$this->canonicalDepartmentName($personDepartment)];
            }
        }

        $personId = trim((string) ($person['usuarioId'] ?? ''));
        $personEmail = mb_strtolower(trim((string) ($person['usuarioEmail'] ?? '')));
        $personAlias = mb_strtolower(trim((string) ($person['usuarioAlias'] ?? '')));
        $user = $reportUsers->first(function (User $candidate) use ($personId, $personEmail, $personAlias): bool {
            return ($personId !== '' && (string) $candidate->id === $personId)
                || ($personEmail !== '' && mb_strtolower(trim((string) $candidate->email)) === $personEmail)
                || ($personAlias !== '' && mb_strtolower(trim((string) $candidate->alias)) === $personAlias);
        });

        if ($user === null) {
            return [];
        }

        return collect($user->regionalesLista())
            ->map(fn (string $department): string => $this->canonicalDepartmentName($department))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function sameNormalizedText(string $left, string $right): bool
    {
        return $this->canonicalDepartmentName($left) === $this->canonicalDepartmentName($right);
    }

    private function departmentNameFromRegionalRow(array $row): string
    {
        foreach ((array) ($row['codigosSucursal'] ?? []) as $branchCode) {
            $code = trim((string) $branchCode);
            if (isset(self::DEPARTMENT_BY_BRANCH_CODE[$code])) {
                return self::DEPARTMENT_BY_BRANCH_CODE[$code];
            }
        }

        return $this->canonicalDepartmentName($this->reportTextValue($row['regional'] ?? null));
    }

    private function canonicalDepartmentName(string $department): string
    {
        $normalized = $this->normalizePersonName($department);
        if (isset(self::DEPARTMENT_BY_BRANCH_CODE[$normalized])) {
            return self::DEPARTMENT_BY_BRANCH_CODE[$normalized];
        }

        return self::DEPARTMENT_ALIASES[$normalized] ?? $normalized;
    }

    private function firstReportTextValue(mixed ...$values): string
    {
        foreach ($values as $value) {
            $text = $this->reportTextValue($value);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function reportTextValue(mixed $value): string
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            return trim((string) $value);
        }

        if (! is_array($value)) {
            return '';
        }

        foreach (['nombre', 'name', 'descripcion', 'description', 'label', 'valor', 'value', 'departamento', 'regional', 'codigo', 'id'] as $key) {
            if (array_key_exists($key, $value)) {
                $text = $this->reportTextValue($value[$key]);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return collect($value)
            ->map(fn ($item): string => $this->reportTextValue($item))
            ->filter()
            ->unique()
            ->implode(', ');
    }

    private function normalizePersonName(string $name): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', Str::ascii($name))));
    }

    private function serviceGroupName(string $service): string
    {
        $normalizedService = $this->normalizePersonName($service);

        if ($this->isEcaInternationalService($service)) {
            return 'Servicio ECA Internacional';
        }

        if (str_contains($normalizedService, 'CERTIFICADA')) {
            return 'Servicio Certificadas';
        }

        if (str_contains($normalizedService, 'CERTIFICADO')) {
            return 'Servicio Certificado';
        }

        foreach (self::SERVICE_GROUPS as $groupName => $subservices) {
            if (in_array($service, $subservices, true)
                || in_array($normalizedService, array_map(fn (string $name): string => $this->normalizePersonName($name), $subservices), true)) {
                return $groupName;
            }
        }

        if (str_contains($normalizedService, 'CONTRATO')) {
            return 'Servicio Contratos';
        }

        return $service !== '' ? $service : 'Otros servicios';
    }

    private function dateFilters(Request $request, bool $withLimit = false): array
    {
        $rules = [
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 3)],
        ];

        if ($withLimit) {
            $rules['limite'] = ['nullable', 'integer', 'between:1,200'];
        }

        $validated = $request->validate($rules);

        return [
            'mes' => (int) ($validated['mes'] ?? now()->month),
            'anio' => (int) ($validated['anio'] ?? now()->year),
            ...($withLimit ? ['limite' => (int) ($validated['limite'] ?? 200)] : []),
        ];
    }
}

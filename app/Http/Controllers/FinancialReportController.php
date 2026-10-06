<?php

namespace App\Http\Controllers;

use App\Models\CashierFlowReceivableCollection;
use App\Models\CashierFlowReceivableMovement;
use App\Models\ConciliacionEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Services\FacturacionReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FinancialReportController extends Controller
{
    private const MAX_SERVICE_FILTER_ITEMS = 200;

    private const CASHIER_FLOW_EXCLUDED_CASHIERS = [
        'EDGAR JAVIER GIRONDA CHIRI',
    ];

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
        'Servicio Internacional' => [
            'Servicio Internacional',
            'Servicio EMS Internacional',
            'Servicio Encomienda Internacional',
            'Servicio Ordinaria Internacional',
            'Servicio Certificadas',
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
        $data = $this->buildServicesReportData(
            $request,
            forceOnlyContracts: false,
            reconcileContracts: false,
            excludeContracts: true,
            excludedCashierNames: self::CASHIER_FLOW_EXCLUDED_CASHIERS,
            enableDepartmentFilter: true,
            cacheReports: true,
            showReceivablesSeparately: true,
            includeAnnulledDetails: true
        );
        $receivableRows = collect($data['receivableServices']);
        $invoicePeriodMovements = collect();
        if (Schema::hasTable('cashier_flow_receivable_movements') && $data['selectedDepartment'] === '') {
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
            $service['totalMontoAnulado'] = (float) $cancelledByService->get((string) ($service['servicio'] ?? ''), 0);

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
        $collectedServiceRows = $this->buildCollectedReceivableServiceRows($invoicePeriodMovements->groupBy('servicio'));
        $data['serviceGroups'] = $this->buildServiceGroups(
            collect($data['services'])->concat($collectedServiceRows)
        );
        $data['summary']['cantidadServicios'] = $data['serviceGroups']->count();
        $data['cashierFlowCollectedAmount'] = (float) $invoicePeriodMovements->sum('monto');
        $data['summary']['totalRecaudado'] = (float) ($data['summary']['totalMontoVendido'] ?? $data['summary']['totalMonto'] ?? 0)
            + $data['cashierFlowCollectedAmount'];
        $data['summary']['totalSinContratosEca'] = (float) ($data['summary']['totalMontoVendido'] ?? $data['summary']['totalMonto'] ?? 0);
        $data['cashierRows'] = $this->addCashierReceivableIncome(
            $this->buildCashierBreakdown($data['services']),
            $invoicePeriodMovements
        );
        $data['cashierRows'] = $this->addCashierPeriodAverages(
            $data['cashierRows'],
            $data['selectedMonths'],
            $data['anio']
        );
        $data['cashierDepartmentGroups'] = $this->buildCashierDepartmentGroups($data['cashierRows']);

        return view('financial-reports.cashier-flow', $data);
    }

    public function cashierFlowReport(Request $request)
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');
        @ini_set('memory_limit', '1024M');

        $data = $this->buildServicesReportData(
            $request,
            forceOnlyContracts: false,
            reconcileContracts: false,
            excludeContracts: true,
            excludedCashierNames: self::CASHIER_FLOW_EXCLUDED_CASHIERS,
            enableDepartmentFilter: true,
            cacheReports: true,
            showReceivablesSeparately: true
        );
        $invoiceDetailFilters = $this->cashierFlowInvoiceDetailFilters(
            collect($data['services'])->concat($data['receivableServices']),
            $data['selectedMonths'],
            $data['anio']
        );
        $invoiceAudit = $this->loadCashierFlowCancelledInvoices(
            $invoiceDetailFilters,
            collect($data['services'])->concat($data['receivableServices']),
            $data['selectedDepartment']
        );
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
            $service['totalMontoAnulado'] = (float) $cancelledByService->get((string) ($service['servicio'] ?? ''), 0);

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
        $collectedMovements = Schema::hasTable('cashier_flow_receivable_movements') && $data['selectedDepartment'] === ''
            ? $this->cashierFlowCollectedMovementsForPeriod($data['selectedMonths'], $data['anio'])
            : collect();
        $collectionAudit = $this->loadCashierFlowCancelledInvoices(
            $this->cashierFlowMovementDetailFilters($collectedMovements)
        );
        $cancelledMovementKeys = $collectionAudit['invoices']->pluck('_movementKey')->filter()->flip();
        if ($cancelledMovementKeys->isNotEmpty()) {
            $collectedMovements = $collectedMovements
                ->reject(fn (CashierFlowReceivableMovement $movement): bool => $cancelledMovementKeys->has($movement->movement_key))
                ->values();
        }
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
        $collectedServiceRows = $this->buildCollectedReceivableServiceRows(
            $collectedMovements->groupBy('servicio')
        );
        $data['serviceGroups'] = $this->buildServiceGroups(
            collect($data['services'])->concat($collectedServiceRows)
        );
        $data['summary']['cantidadServicios'] = $data['serviceGroups']->count();
        $data['cashierFlowCollectedMovements'] = $collectedMovements;
        $data['cashierFlowCollectedAmount'] = (float) $collectedMovements->sum('monto');
        $data['summary']['totalRecaudado'] = (float) ($data['summary']['totalMontoVendido'] ?? $data['summary']['totalMonto'] ?? 0)
            + $data['cashierFlowCollectedAmount'];
        $data['summary']['totalSinContratosEca'] = (float) ($data['summary']['totalMontoVendido'] ?? $data['summary']['totalMonto'] ?? 0);
        $data['totalReportIncome'] = $data['summary']['totalRecaudado'];
        $data['cashierFlowCollectionsOmittedByDepartment'] = $data['selectedDepartment'] !== '';
        $data['cashierRows'] = $this->addCashierReceivableIncome(
            $this->buildCashierBreakdown($data['services']),
            $collectedMovements
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
        $data['cashierDepartmentGroups'] = $this->buildCashierDepartmentGroups($data['cashierRows']);
        $data['generatedAt'] = now();
        $data['periodLabel'] = collect($data['selectedMonths'])
            ->map(fn (int $month) => [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ][$month] ?? (string) $month)
            ->implode(', ').' de '.$data['anio'];

        $reportDays = $this->countReportDaysExcludingSundays($data['selectedMonths'], $data['anio']);
        $data['averageDailyIncome'] = $reportDays > 0 ? $data['totalReportIncome'] / $reportDays : 0.0;
        $data['topCashier'] = $data['cashierRows']->first();
        $data['topService'] = $data['serviceGroups']->first();

        return Pdf::loadView('financial-reports.cashier-flow-pdf', $data)
            ->setPaper('A4', 'portrait')
            ->download('reporte-flujo-cajero-'.$data['anio'].'-'.now()->format('Ymd_His').'.pdf');
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
                $errors->push((string) ($result['error'] ?? 'No se pudo consultar el detalle del servicio.'));

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
        bool $includeAnnulledDetails = false
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
        if ($cacheReports && $selectedMonths->count() > 1) {
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
                if ($includeAnnulledDetails && ! (bool) data_get($monthlyReport, 'meta.detalleAnulacionesIncluido', false)) {
                    $annulledDetailErrors->push("La API no devolvio el detalle optimizado de anulaciones del mes {$month}; el resumen financiero se conserva.");
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
                    $current['_meses'][] = $month;
                    $current['_meses'] = array_values(array_unique($current['_meses']));
                    $aggregated->put($serviceKey, $current);
                }
            } catch (\Throwable $exception) {
                $hasCompleteApiUniqueSales = false;
                $errors->push("No se pudo cargar el resumen del mes {$month}: {$exception->getMessage()}");
                $this->logDetailError($exception, null, $month, $year);
            }
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
            }
        }

        $receivableServices = collect();
        if ($showReceivablesSeparately) {
            $receivableServices = $aggregated
                ->filter(function (array $service) use ($hasServiceFilter, $requestedServices): bool {
                    $name = (string) ($service['servicio'] ?? '');

                    return $this->serviceGroupName($name) === 'Servicio Contratos'
                        || ($this->isEcaInternationalService($name)
                            && (! $hasServiceFilter || $requestedServices->contains(
                                fn (string $requestedService): bool => $this->sameNormalizedText($requestedService, $name)
                            )));
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
            $receivableServices = $this->excludeCashiersFromServices($receivableServices, $excludedCashierNames);
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

        return [
            'mes' => (int) $selectedMonths->first(),
            'anio' => $year,
            'limite' => $limit,
            'soloContratos' => $onlyContracts,
            'contractsExcluded' => $excludeContracts,
            'maxSelectedServices' => self::MAX_SERVICE_FILTER_ITEMS,
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
            $paidRows = $rows->filter(fn (array $row): bool => (bool) ($row['_cobroRealizado'] ?? false));
            $service['totalMontoCobrado'] = (float) $paidRows->sum('_montoCobrado');
            $service['cantidadMovimientosCobrados'] = $paidRows->count();
            $service['cantidadVentasCobradas'] = $paidRows->pluck('ventaId')->filter()->unique()->count();
            $service['totalMontoPendiente'] = max(0, (float) $service['totalMonto'] - $service['totalMontoCobrado']);
            if ($showCollectedOnly) {
                $rows = $rows->filter(fn (array $row): bool => (bool) ($row['_cobroRealizado'] ?? false))->values();
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
                    $row['totalLinea'] ?? '',
                    \App\Support\BolivianNumber::format((float) ($row['totalLinea'] ?? 0), 2),
                ];
                $haystack = collect($searchableValues)
                    ->filter(fn ($value): bool => is_scalar($value))
                    ->map(fn ($value): string => (string) $value)
                    ->implode(' ');

                return str_contains(mb_strtolower(Str::ascii($haystack)), $normalizedSearch);
            })->values();
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
            'rows' => $paginatedRows,
            'errors' => $errors,
            'error' => $errors->first(),
            'searchTerm' => $searchTerm,
            'canManageReceivables' => $canManageReceivables,
            'cashierFlowContext' => $cashierFlowContext,
            'showCollectedOnly' => $showCollectedOnly && $canManageReceivables,
        ];

        return response()
            ->view('financial-reports.partials.service-detail-modal-content', $viewData)
            ->header('X-Flow-Detail-Fragment', '1');
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

    private function buildServiceGroups(Collection $services): Collection
    {
        return $services
            ->groupBy(fn (array $service) => $this->serviceGroupName((string) ($service['servicio'] ?? '')))
            ->map(function (Collection $children, string $groupName) {
                return [
                    'servicio' => $groupName,
                    'cantidadVentas' => $children->sum('cantidadVentas'),
                    'cantidadDetalles' => $children->sum('cantidadDetalles'),
                    'totalCantidad' => $children->sum('totalCantidad'),
                    'totalMonto' => $children->sum('totalMonto'),
                    'totalMontoVendido' => $children->sum(fn (array $service) => $service['totalMontoVendido'] ?? $service['totalMonto'] ?? 0),
                    'totalMontoNoIncluidoEnTotalVendido' => $children->sum('totalMontoNoIncluidoEnTotalVendido'),
                    'totalMontoAnulado' => $children->sum('totalMontoAnulado'),
                    'ultimaFecha' => $children->pluck('ultimaFecha')->filter()->max(),
                    '_ultimaFechaEsCobro' => $children->contains(fn (array $service): bool => (bool) ($service['_esCobroReceivable'] ?? false)),
                    '_meses' => $children->pluck('_meses')->flatten()->unique()->sort()->values()->all(),
                    '_children' => $children->sortByDesc('totalMontoVendido')->values(),
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
                    'cajeros' => $cashiers->sortByDesc('totalIngresos')->values(),
                    'cantidadCajeros' => $cashiers->count(),
                    'cantidadVentas' => (float) $cashiers->sum('cantidadVentas'),
                    'cantidadDetalles' => (float) $cashiers->sum('cantidadDetalles'),
                    'totalCantidad' => (float) $cashiers->sum('totalCantidad'),
                    'totalMontoVentanilla' => (float) $cashiers->sum('totalMontoVentanilla'),
                    'totalMontoCobrado' => (float) $cashiers->sum('totalMontoCobrado'),
                    'totalIngresos' => (float) $cashiers->sum('totalIngresos'),
                    'promedioDiario' => (float) $cashiers->sum('promedioDiario'),
                ];
            })
            ->sortByDesc('totalIngresos')
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
            $unclassifiedAmount = max(0, round(
                (float) ($cashier['totalIngresos'] ?? $cashier['totalMonto'] ?? 0) - $knownAmount,
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

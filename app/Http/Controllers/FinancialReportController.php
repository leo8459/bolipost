<?php

namespace App\Http\Controllers;

use App\Models\ConciliacionEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Services\FacturacionReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FinancialReportController extends Controller
{
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

    public function services(Request $request)
    {
        return view('financial-reports.services', $this->buildServicesReportData($request, false));
    }

    public function cashierFlow(Request $request)
    {
        $data = $this->buildServicesReportData(
            $request,
            forceOnlyContracts: false,
            reconcileContracts: false,
            excludeContracts: true,
            excludedCashierNames: self::CASHIER_FLOW_EXCLUDED_CASHIERS,
            enableDepartmentFilter: true
        );
        $data['cashierRows'] = $this->buildCashierBreakdown($data['services']);

        return view('financial-reports.cashier-flow', $data);
    }

    public function cashierFlowReport(Request $request)
    {
        $data = $this->buildServicesReportData(
            $request,
            forceOnlyContracts: false,
            reconcileContracts: false,
            excludeContracts: true,
            excludedCashierNames: self::CASHIER_FLOW_EXCLUDED_CASHIERS,
            enableDepartmentFilter: true
        );
        $data['cashierRows'] = $this->buildCashierBreakdown($data['services']);
        $data['cashierRows'] = $this->addCashierWorkMetrics(
            $data['cashierRows'],
            $data['services'],
            $data['selectedMonths'],
            $data['anio'],
            $data['selectedDepartment'],
            self::CASHIER_FLOW_EXCLUDED_CASHIERS
        );
        $data['generatedAt'] = now();
        $data['periodLabel'] = collect($data['selectedMonths'])
            ->map(fn (int $month) => [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ][$month] ?? (string) $month)
            ->implode(', ').' de '.$data['anio'];

        $totalAmount = (float) ($data['summary']['totalMonto'] ?? 0);
        $totalSales = (float) ($data['summary']['cantidadVentas'] ?? 0);
        $data['averageTicket'] = $totalSales > 0 ? $totalAmount / $totalSales : 0;
        $data['topCashier'] = $data['cashierRows']->first();
        $data['topService'] = $data['serviceGroups']->first();

        return Pdf::loadView('financial-reports.cashier-flow-pdf', $data)
            ->setPaper('A4', 'portrait')
            ->download('reporte-flujo-cajero-sin-contratos-'.$data['anio'].'-'.now()->format('Ymd_His').'.pdf');
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

        return view('financial-reports.services', $data);
    }

    public function executiveReport(Request $request)
    {
        $data = $this->buildServicesReportData($request);
        $groups = $data['serviceGroups'];
        $totalAmount = (float) ($data['summary']['totalMonto'] ?? 0);
        $totalSales = (float) ($data['summary']['cantidadVentas'] ?? 0);
        $topGroup = $groups->first();
        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $data['generatedAt'] = now();
        $data['periodLabel'] = collect($data['selectedMonths'])
            ->map(fn (int $month) => $monthNames[$month] ?? (string) $month)
            ->implode(', ').' de '.$data['anio'];
        $data['averageTicket'] = $totalSales > 0 ? $totalAmount / $totalSales : 0;
        $data['topGroup'] = $topGroup;
        $data['topGroupShare'] = $totalAmount > 0
            ? ((float) ($topGroup['totalMonto'] ?? 0) / $totalAmount) * 100
            : 0;

        $pdf = Pdf::loadView('financial-reports.executive-report-pdf', $data)
            ->setPaper('A4', 'portrait');

        return $pdf->download('reporte-ejecutivo-ventas-servicios-'.$data['anio'].'-'.now()->format('Ymd_His').'.pdf');
    }

    private function buildServicesReportData(
        Request $request,
        ?bool $forceOnlyContracts = null,
        bool $reconcileContracts = true,
        bool $excludeContracts = false,
        array $excludedCashierNames = [],
        bool $enableDepartmentFilter = false
    ): array {
        $validated = $request->validate([
            'servicio' => ['nullable', 'string', 'max:180'],
            'servicios' => ['nullable', 'array', 'max:50'],
            'servicios.*' => ['string', 'distinct', 'max:180'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'meses' => ['nullable', 'array', 'min:1', 'max:12'],
            'meses.*' => ['integer', 'distinct', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 1)],
            'limite' => ['nullable', 'integer', 'between:1,200'],
            'solo_contratos' => ['nullable', 'boolean'],
            'departamento' => ['nullable', 'string', 'max:120'],
        ]);

        $year = (int) ($validated['anio'] ?? now()->year);
        $limit = (int) ($validated['limite'] ?? 200);
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
        $serviceOptions = $requestedServices->keyBy(fn ($service) => $service);
        $errors = collect();

        foreach ($selectedMonths as $month) {
            try {
                $monthlyReport = $this->reports->services($month, $year, $limit);

                foreach ((array) ($monthlyReport['servicios'] ?? []) as $row) {
                    $name = trim((string) ($row['servicio'] ?? ''));
                    if ($name === '') {
                        continue;
                    }

                    $serviceOptions->put($name, $name);
                    $current = $aggregated->get($name, [
                        'servicio' => $name,
                        'cantidadVentas' => 0,
                        'cantidadDetalles' => 0,
                        'totalCantidad' => 0,
                        'totalMonto' => 0,
                        'ultimaFecha' => null,
                        'descripcionMuestra' => null,
                        '_meses' => [],
                        '_porRegionales' => [],
                        '_porPersonas' => [],
                    ]);

                    foreach (['cantidadVentas', 'cantidadDetalles', 'totalCantidad', 'totalMonto'] as $totalKey) {
                        $current[$totalKey] += (float) ($row[$totalKey] ?? 0);
                    }

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
                    $current['_meses'][] = $month;
                    $current['_meses'] = array_values(array_unique($current['_meses']));
                    $aggregated->put($name, $current);
                }
            } catch (\Throwable $exception) {
                $errors->push("No se pudo cargar el resumen del mes {$month}: {$exception->getMessage()}");
                $this->logDetailError($exception, null, $month, $year);
            }
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

        $contractServices = $aggregated
            ->filter(fn (array $service) => $this->serviceGroupName((string) ($service['servicio'] ?? '')) === 'Servicio Contratos')
            ->keys()
            ->values();
        $selectedServices = $onlyContracts
            ? $contractServices
            : ($hasServiceFilter ? $requestedServices : $serviceOptions->sortKeys()->values());
        $services = $aggregated
            ->only($selectedServices->all())
            ->values()
            ->sortByDesc('totalMonto')
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
                $validatedSales = 0;
                $validatedAmount = 0;

                return $service;
            })->sortByDesc('totalMonto')->values();
        }
        if ($enableDepartmentFilter && $selectedDepartment !== '') {
            $services = $this->filterServicesByDepartment($services, $selectedDepartment);
        }
        if ($excludedCashierNames !== []) {
            $services = $this->excludeCashiersFromServices($services, $excludedCashierNames);
        }
        $summary = [
            'cantidadServicios' => $services->count(),
            'cantidadVentas' => $services->sum('cantidadVentas'),
            'cantidadDetalles' => $services->sum('cantidadDetalles'),
            'totalCantidad' => $services->sum('totalCantidad'),
            'totalMonto' => $services->sum('totalMonto'),
            'contratosFacturadosVentas' => $contractReceivables['invoiced_sales'],
            'contratosFacturadosMonto' => $contractReceivables['invoiced_amount'],
            'contratosValidadosVentas' => $contractReceivables['validated_sales'],
            'contratosValidadosMonto' => $contractReceivables['validated_amount'],
            'contratosPorCobrarVentas' => $contractReceivables['receivable_sales'],
            'contratosPorCobrarMonto' => $contractReceivables['receivable_amount'],
        ];
        $serviceGroups = $this->buildServiceGroups($services);
        $summary['cantidadServicios'] = $serviceGroups->count();

        return [
            'mes' => (int) $selectedMonths->first(),
            'anio' => $year,
            'limite' => $limit,
            'soloContratos' => $onlyContracts,
            'contractsExcluded' => $excludeContracts,
            'selectedDepartment' => $selectedDepartment,
            'departmentOptions' => $departmentOptions,
            'selectedMonths' => $selectedMonths->all(),
            'selectedServices' => $selectedServices->all(),
            'serviceOptions' => $onlyContracts
                ? $contractServices
                : $serviceOptions->sortKeys()->values(),
            'summary' => $summary,
            'services' => $services,
            'serviceGroups' => $serviceGroups,
            'meta' => [],
            'errors' => $errors,
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
        $validated = $request->validate([
            'servicio' => ['nullable', 'string', 'max:180'],
            'servicios' => ['nullable', 'array', 'max:50'],
            'servicios.*' => ['string', 'distinct', 'max:180'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'meses' => ['nullable', 'array', 'min:1', 'max:12'],
            'meses.*' => ['integer', 'distinct', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 1)],
            'page' => ['nullable', 'integer', 'min:1'],
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
        $serviceOptions = $selectedServices->keyBy(fn ($service) => $service);
        $errors = collect();

        foreach ($selectedMonths as $month) {
            try {
                $monthlyReport = $this->reports->services($month, $year, 200);
                foreach ((array) ($monthlyReport['servicios'] ?? []) as $serviceRow) {
                    $name = trim((string) ($serviceRow['servicio'] ?? ''));
                    if ($name !== '') {
                        $serviceOptions->put($name, $name);
                    }
                }
            } catch (\Throwable $exception) {
                $errors->push("No se pudo obtener la lista de servicios del mes {$month}: {$exception->getMessage()}");
                $this->logDetailError($exception, null, $month, $year);
            }
        }

        $rows = collect();
        $service = [
            'servicio' => $selectedServices->count() === 1 ? $selectedServices->first() : $selectedServices->count().' servicios seleccionados',
            'cantidadVentas' => 0,
            'cantidadDetalles' => 0,
            'totalCantidad' => 0,
            'totalMonto' => 0,
        ];

        foreach ($selectedServices as $serviceName) {
            foreach ($selectedMonths as $month) {
                try {
                    $report = $this->reports->serviceDetail($serviceName, $month, $year);
                    $detail = (array) ($report['servicio'] ?? []);
                    foreach (['cantidadVentas', 'cantidadDetalles', 'totalCantidad', 'totalMonto'] as $totalKey) {
                        $service[$totalKey] += (float) ($detail[$totalKey] ?? 0);
                    }
                    $rows->push(...collect($detail['rows'] ?? [])->map(fn ($row) => [
                        ...(array) $row,
                        '_servicio' => $serviceName,
                        '_mes' => $month,
                    ])->all());
                } catch (\Throwable $exception) {
                    $errors->push("No se pudo cargar {$serviceName} para el mes {$month}: {$exception->getMessage()}");
                    $this->logDetailError($exception, $serviceName, $month, $year);
                }
            }
        }

        $rows = $rows->sortByDesc('fecha')->values();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $paginatedRows = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->except('page')]
        );

        return view('financial-reports.service-detail', [
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
        ]);
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
                    'ultimaFecha' => $children->pluck('ultimaFecha')->filter()->max(),
                    '_meses' => $children->pluck('_meses')->flatten()->unique()->sort()->values()->all(),
                    '_children' => $children->sortByDesc('totalMonto')->values(),
                ];
            })
            ->sortByDesc('totalMonto')
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
                ];
            })
            ->sortByDesc('totalMonto')
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
                $identity = $id !== ''
                    ? 'id:'.$id
                    : ($email !== '' ? 'email:'.mb_strtolower($email) : 'user:'.mb_strtolower($alias !== '' ? $alias : $name));

                return [
                    '_identity' => $identity !== 'user:' ? $identity : 'user:sin-identificar',
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
                ];
            })
            ->sortByDesc('totalMonto')
            ->values();
    }

    private function addCashierWorkMetrics(
        Collection $cashierRows,
        Collection $services,
        array $months,
        int $year,
        string $selectedDepartment,
        array $excludedNames
    ): Collection {
        $filters = $services
            ->pluck('servicio')
            ->filter()
            ->unique()
            ->flatMap(fn (string $service): Collection => collect($months)->map(fn ($month): array => [
                'servicio' => $service,
                'mes' => (int) $month,
                'anio' => $year,
            ]))
            ->values()
            ->all();
        $workedDates = [];
        $normalizedExcludedNames = collect($excludedNames)
            ->map(fn (string $name): string => $this->normalizePersonName($name))
            ->filter()
            ->values();

        if ($filters !== []) {
            foreach ($this->reports->serviceDetailsBatch($filters) as $result) {
                if (($result['error'] ?? null) !== null) {
                    Log::warning('No se pudo calcular los días trabajados del flujo de cajero.', [
                        'message' => $result['error'],
                        'servicio' => data_get($result, 'filter.servicio'),
                        'mes' => data_get($result, 'filter.mes'),
                        'anio' => data_get($result, 'filter.anio'),
                    ]);

                    continue;
                }

                foreach ((array) data_get($result, 'report.servicio.rows', []) as $detailRow) {
                    $detailRow = (array) $detailRow;
                    $person = (array) ($detailRow['usuario'] ?? []);
                    $personName = trim((string) ($person['nombre'] ?? ''));
                    $normalizedPersonName = $this->normalizePersonName($personName);
                    $isExcluded = $normalizedExcludedNames->contains(function (string $excludedName) use ($normalizedPersonName): bool {
                        $tokens = array_filter(explode(' ', $excludedName));

                        return $normalizedPersonName !== ''
                            && $tokens !== []
                            && collect($tokens)->every(fn (string $token): bool => str_contains($normalizedPersonName, $token));
                    });
                    if ($isExcluded || ($selectedDepartment !== '' && ! $this->sameNormalizedText(
                        $this->detailRowDepartment($detailRow),
                        $selectedDepartment
                    ))) {
                        continue;
                    }

                    $date = substr(trim((string) ($detailRow['fecha'] ?? '')), 0, 10);
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                        continue;
                    }

                    $identity = $this->cashierIdentity(
                        (string) ($person['id'] ?? ''),
                        (string) ($person['email'] ?? ''),
                        (string) ($person['alias'] ?? ''),
                        $personName
                    );
                    $workedDates[$identity][$date] = true;
                }
            }
        }

        return $cashierRows->map(function (array $cashier) use ($workedDates): array {
            $identity = $this->cashierIdentity(
                (string) ($cashier['usuarioId'] ?? ''),
                (string) ($cashier['usuarioEmail'] ?? ''),
                (string) ($cashier['usuarioAlias'] ?? ''),
                (string) ($cashier['usuarioNombre'] ?? '')
            );
            $workedDays = count($workedDates[$identity] ?? []);
            $cashier['diasTrabajados'] = $workedDays;
            $cashier['promedioDiario'] = $workedDays > 0
                ? (float) ($cashier['totalMonto'] ?? 0) / $workedDays
                : 0.0;

            return $cashier;
        });
    }

    private function cashierIdentity(string $id, string $email, string $alias, string $name): string
    {
        $id = trim($id);
        $email = mb_strtolower(trim($email));
        $alias = mb_strtolower(trim($alias));
        $name = mb_strtolower(trim($name));

        return $id !== ''
            ? 'id:'.$id
            : ($email !== '' ? 'email:'.$email : 'user:'.($alias !== '' ? $alias : ($name !== '' ? $name : 'sin-identificar')));
    }

    private function detailRowDepartment(array $row): string
    {
        $regional = $row['regional'] ?? [];
        $regionalName = is_array($regional)
            ? trim((string) ($regional['nombre'] ?? ''))
            : trim((string) $regional);
        if ($regionalName !== '') {
            return $this->canonicalDepartmentName($regionalName);
        }

        $branch = (array) ($row['sucursal'] ?? []);
        $department = trim((string) ($branch['departamento'] ?? $branch['nombre'] ?? ''));
        if ($department !== '') {
            return $this->canonicalDepartmentName($department);
        }

        $branchCode = trim((string) ($branch['codigoSucursal'] ?? ''));

        return self::DEPARTMENT_BY_BRANCH_CODE[$branchCode] ?? '';
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
                foreach (['cantidadVentas', 'cantidadDetalles', 'totalCantidad', 'totalMonto'] as $totalKey) {
                    $service[$totalKey] = max(
                        0,
                        (float) ($service[$totalKey] ?? 0) - (float) $excludedPeople->sum($totalKey)
                    );
                }

                return $service;
            })
            ->sortByDesc('totalMonto')
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

                return $service;
            })
            ->filter(fn (array $service): bool => collect($service['_porRegionales'] ?? [])->isNotEmpty())
            ->sortByDesc('totalMonto')
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

        return $this->canonicalDepartmentName((string) ($row['regional'] ?? ''));
    }

    private function canonicalDepartmentName(string $department): string
    {
        $normalized = $this->normalizePersonName($department);
        if (isset(self::DEPARTMENT_BY_BRANCH_CODE[$normalized])) {
            return self::DEPARTMENT_BY_BRANCH_CODE[$normalized];
        }

        return self::DEPARTMENT_ALIASES[$normalized] ?? $normalized;
    }

    private function normalizePersonName(string $name): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', Str::ascii($name))));
    }

    private function serviceGroupName(string $service): string
    {
        foreach (self::SERVICE_GROUPS as $groupName => $subservices) {
            if (in_array($service, $subservices, true)) {
                return $groupName;
            }
        }

        return $service !== '' ? $service : 'Otros servicios';
    }

    private function dateFilters(Request $request, bool $withLimit = false): array
    {
        $rules = [
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 1)],
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

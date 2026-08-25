<?php

namespace App\Http\Controllers;

use App\Services\FacturacionReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FinancialReportController extends Controller
{
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

    private function buildServicesReportData(Request $request, ?bool $forceOnlyContracts = null): array
    {
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
                    $current['_meses'][] = $month;
                    $current['_meses'] = array_values(array_unique($current['_meses']));
                    $aggregated->put($name, $current);
                }
            } catch (\Throwable $exception) {
                $errors->push("No se pudo cargar el resumen del mes {$month}: {$exception->getMessage()}");
                $this->logDetailError($exception, null, $month, $year);
            }
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
        $summary = [
            'cantidadServicios' => $services->count(),
            'cantidadVentas' => $services->sum('cantidadVentas'),
            'cantidadDetalles' => $services->sum('cantidadDetalles'),
            'totalCantidad' => $services->sum('totalCantidad'),
            'totalMonto' => $services->sum('totalMonto'),
        ];
        $serviceGroups = $this->buildServiceGroups($services);
        $summary['cantidadServicios'] = $serviceGroups->count();

        return [
            'mes' => (int) $selectedMonths->first(),
            'anio' => $year,
            'limite' => $limit,
            'soloContratos' => $onlyContracts,
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

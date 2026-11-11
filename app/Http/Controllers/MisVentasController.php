<?php

namespace App\Http\Controllers;

use App\Models\FacturaTicketSnapshot;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteInt;
use App\Models\PaqueteOrdi;
use App\Models\User;
use App\Services\FacturacionCartService;
use App\Services\SitraIpsClient;
use App\Services\SitraTrackingDestinationClient;
use App\Services\SitraTrackingEventsClient;
use App\Services\TrackingProgressService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Milon\Barcode\DNS2D;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MisVentasController extends Controller
{
    private const KARDEX_EVENT_DELIVERED = 316;
    private const KARDEX_EVENT_SENT_TO_WINDOW_EMS = 312;
    private const KARDEX_EVENT_SENT_TO_WINDOW_CERTI = 172;
    private const KARDEX_EVENT_ASSIGNED_TO_COURIER = 184;
    private const KARDEX_EVENT_ADMISSION = 295;
    private const KARDEX_EVENT_CERTI_ADMISSION = 168;
    private const KARDEX_EVENT_COUNTER_DELIVERY = [183, 185, 4478];
    private const KARDEX_EVENT_COURIER_DELIVERY = [165, 315, 4477];
    private const KARDEX_EVENT_TIKTOKER_ADMISSION = 2239;
    private const KARDEX_EVENT_TIKTOKER_ARRIVED_AT_EMS_WINDOW = 2238;

    public function index(Request $request, FacturacionCartService $service): View
    {
        $effectiveScope = $this->resolveEffectiveOwnScope($request);
        return $this->renderVentasPage($request, $service, $effectiveScope, 'own');
    }

    public function branchIndex(Request $request, FacturacionCartService $service): View
    {
        return $this->renderVentasPage($request, $service, 'branch', 'branch');
    }

    private function resolveCajaContext(FacturacionCartService $service, $user): array
    {
        try {
            return $service->fetchCajaEstado($user);
        } catch (\Throwable) {
            return [
                'estado' => 'SIN_APERTURA',
                'mensaje' => 'No se pudo consultar el estado de caja.',
                'caja' => [],
            ];
        }
    }

    public function exportPdf(Request $request, FacturacionCartService $service): Response
    {
        $effectiveScope = $this->resolveEffectiveOwnScope($request);
        return $this->exportVentasPdf($request, $service, $effectiveScope, 'own');
    }

    public function branchExportPdf(Request $request, FacturacionCartService $service): Response
    {
        return $this->exportVentasPdf($request, $service, 'branch', 'branch');
    }

    public function kardex(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): View
    {
        $report = $this->buildKardexReport($request, $service, $ipsClient, $destinationClient, $eventsClient);

        return view('facturacion.kardex', array_merge($report, [
            'scope' => 'own',
            'canViewBranchVentas' => false,
            'branchCashierSummary' => collect(),
        ]));
    }

    public function exportKardexPdf(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): Response
    {
        $report = $this->buildKardexReport($request, $service, $ipsClient, $destinationClient, $eventsClient);
        $pdf = Pdf::loadView('facturacion.kardex-pdf', array_merge($report, [
            'generatedAt' => now(),
        ]))->setPaper('A4', 'landscape');

        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportKardexExcel(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): StreamedResponse
    {
        $report = $this->buildKardexReport($request, $service, $ipsClient, $destinationClient, $eventsClient);
        $spreadsheet = $this->buildKardexSpreadsheet($report);
        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function kardexRegional(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): View
    {
        return view('facturacion.kardex', array_merge(
            $this->buildKardexRegionalReport($request, $service, $ipsClient, $destinationClient, $eventsClient),
            ['isRegionalKardex' => true]
        ));
    }

    public function exportKardexRegionalPdf(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): Response
    {
        $report = $this->buildKardexRegionalReport($request, $service, $ipsClient, $destinationClient, $eventsClient);
        $pdf = Pdf::loadView('facturacion.kardex-pdf', array_merge($report, [
            'generatedAt' => now(),
        ]))->setPaper('A4', 'landscape');

        $filename = 'kardex-sucursal-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportKardexRegionalExcel(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): StreamedResponse
    {
        $report = $this->buildKardexRegionalReport($request, $service, $ipsClient, $destinationClient, $eventsClient);
        $spreadsheet = $this->buildKardexSpreadsheet($report);
        $filename = 'kardex-sucursal-' . now()->format('Ymd-His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildKardexReport(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): array
    {
        [$user, $filters] = $this->resolveRequestContext($request, 'own');
        $kardexError = null;

        try {
            // Carga los registros del cajero en formato de detalle Kardex y complementa sus destinos mediante tracking.
            [$sales, , $scopeMetadata] = $this->loadScopedVentas($service, $user, array_merge($filters, ['q' => '']), 'own');
            $rows = $this->buildKardexViewRows($sales, $ipsClient, $destinationClient, $eventsClient);
            $summary = array_merge($this->emptySummary(), [
                'totalVentas' => $rows->unique('venta_group_key')->count(),
                'montoTotal' => round((float) $rows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
            ]);
            $kardexPartialWarning = !empty($scopeMetadata['kardex_source_errors'])
                ? 'El Kardex se cargó parcialmente porque falló una consulta de facturación. Actualiza la página para volver a consultar y completar los datos.'
                : null;
        } catch (\Throwable $e) {
            report($e);
            $rows = collect();
            $summary = $this->emptySummary();
            $kardexError = 'No se pudieron cargar las ventas del cajero para el Kardex: ' . $e->getMessage();
            $kardexPartialWarning = null;
        }

        $visibleRows = $this->applyKardexSearchFilter(
                $rows,
                (string) $request->input('q', '')
            )
            ->sortBy(fn (array $row): string => sprintf(
                '%02d|%012d',
                $this->kardexOperationGroupRank((string) data_get($row, 'operacion_grupo', '')),
                max(0, 9999999999999 - (int) data_get($row, 'fecha_sort', 0))
            ))
            ->values()
            ->map(function (array $row, int $index): array {
                $row['nro'] = $index + 1;
                return $row;
            });
        $paymentMetrics = $this->summarizeKardexPaymentMetrics($visibleRows);

        return [
            'user' => $user,
            'filters' => $filters,
            'summary' => $summary,
            'rows' => $rows,
            'visibleRows' => $visibleRows,
            'operationGroups' => $this->buildKardexOperationGroups($visibleRows, true),
            'totalVentas' => $paymentMetrics['totalVentas'],
            'ventasEfectivo' => $paymentMetrics['ventasEfectivo'],
            'montoEfectivo' => $paymentMetrics['montoEfectivo'],
            'ventasQrPagadas' => $paymentMetrics['ventasQrPagadas'],
            'montoQr' => $paymentMetrics['montoQr'],
            'totalImporte' => round((float) $visibleRows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
            'totalPeso' => round((float) $visibleRows->sum(fn ($row) => (float) data_get($row, 'peso', 0)), 3),
            'totalAnuladas' => $visibleRows->filter(fn ($row) => (bool) data_get($row, 'es_anulada'))->count(),
            'kardexError' => $kardexError,
            'kardexPartialWarning' => $kardexPartialWarning,
            'officePostal' => $this->resolveKardexUserOffice($user),
            'responsableName' => strtoupper(trim((string) ($user->name ?? ''))),
            'ventanillaName' => (string) data_get($user, 'ventanilla', data_get($user, 'id', '')),
        ];
    }

    private function buildKardexRegionalReport(Request $request, FacturacionCartService $service, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient): array
    {
        [$user, $filters] = $this->resolveRequestContext($request, 'own');
        $sucursalId = (int) ($user->sucursal_id ?? 0);
        abort_if($sucursalId <= 0, 403, 'Tu cuenta no tiene una sucursal asignada para consultar el Kardex por Sucursal.');

        $sucursalLabel = collect([
            $user->sucursal?->nombre,
            $user->sucursal?->descripcion,
            $user->sucursal?->municipio,
            $user->sucursal?->departamento,
        ])
            ->map(fn ($value) => trim((string) $value))
            ->first(fn (string $value) => $value !== '', '');
        if ($sucursalLabel === '') {
            $sucursalLabel = 'SUCURSAL ' . $sucursalId;
        }
        $kardexError = null;
        $kardexPartialWarning = false;
        $sucursalCashierCount = 0;
        $rows = collect();

        try {
            // Conserva la misma normalización del Kardex personal, consultando
            // únicamente las cuentas de facturación asignadas a esta sucursal.
            [$sales, , $scopeMetadata] = $this->loadScopedVentas(
                $service,
                $user,
                array_merge($filters, ['q' => '']),
                'branch_kardex'
            );
            $failedCashiers = (int) ($scopeMetadata['sucursal_failed_cashier_count'] ?? 0);
            $cashierCount = (int) ($scopeMetadata['sucursal_cashier_count'] ?? 0);
            $sucursalCashierCount = $cashierCount;

            if ($cashierCount > 0 && $failedCashiers >= $cashierCount) {
                throw new \RuntimeException('No se pudo consultar las ventas de los cajeros asignados a esta sucursal.');
            }

            $kardexPartialWarning = $failedCashiers > 0;
            // Mantiene exactamente el enriquecimiento del Kardex personal:
            // clasificación del servicio, códigos, destino, operación y etapas.
            $rows = $this->buildKardexViewRows($sales, $ipsClient, $destinationClient, $eventsClient, true);
            $rows = $rows->reject(fn (array $row) => $this->isExcludedFromKardexRegionalGeneral($row))->values();
        } catch (\Throwable $e) {
            report($e);
            $rows = collect();
            $kardexError = 'No se pudo cargar el Kardex por Sucursal: ' . $e->getMessage();
        }

        $visibleRows = $this->applyKardexSearchFilter($rows, (string) ($filters['q'] ?? ''))
            ->sort(function (array $left, array $right): int {
                $leftInvoice = trim((string) data_get($left, 'numero_factura', ''));
                $rightInvoice = trim((string) data_get($right, 'numero_factura', ''));
                $leftIsNumeric = preg_match('/^\d+$/', $leftInvoice) === 1;
                $rightIsNumeric = preg_match('/^\d+$/', $rightInvoice) === 1;

                if ($leftIsNumeric && $rightIsNumeric) {
                    $invoiceOrder = (int) $rightInvoice <=> (int) $leftInvoice;
                    if ($invoiceOrder !== 0) {
                        return $invoiceOrder;
                    }
                } elseif ($leftIsNumeric !== $rightIsNumeric) {
                    return $leftIsNumeric ? -1 : 1;
                } else {
                    $invoiceOrder = strnatcasecmp($rightInvoice, $leftInvoice);
                    if ($invoiceOrder !== 0) {
                        return $invoiceOrder;
                    }
                }

                return (int) data_get($right, 'fecha_sort', 0) <=> (int) data_get($left, 'fecha_sort', 0);
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['nro'] = $index + 1;

                return $row;
            });
        $cashierSections = $this->buildKardexRegionalCashierSections($visibleRows);

        return [
            'user' => $user,
            'filters' => $filters,
            'summary' => array_merge($this->emptySummary(), [
                'totalVentas' => $rows->count(),
                'montoTotal' => round((float) $rows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
            ]),
            'rows' => $rows,
            'visibleRows' => $visibleRows,
            'cashierSections' => $cashierSections,
            'operationGroups' => $this->buildKardexOperationGroups($visibleRows, true),
            'totalVentas' => $visibleRows->count(),
            'ventasEfectivo' => 0,
            'montoEfectivo' => 0,
            'ventasQrPagadas' => 0,
            'montoQr' => 0,
            'totalImporte' => round((float) $visibleRows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
            'totalPeso' => round((float) $visibleRows->sum(fn ($row) => (float) data_get($row, 'peso', 0)), 3),
            'totalAnuladas' => $visibleRows->filter(fn ($row) => (bool) data_get($row, 'es_anulada'))->count(),
            'kardexError' => $kardexError,
            'kardexPartialWarning' => $kardexPartialWarning,
            'sucursalCashierCount' => $sucursalCashierCount,
            'isRegionalKardex' => true,
            'officePostal' => $sucursalLabel,
            'responsableName' => 'KARDEX POR SUCURSAL',
            'ventanillaName' => 'TODAS LAS VENTANILLAS DE LA SUCURSAL',
        ];
    }

    private function isExcludedFromKardexRegionalGeneral(array $row): bool
    {
        $service = $this->normalizeKardexText(collect([
            data_get($row, 'tipo_correspondencia'),
            data_get($row, 'tipo_servicio'),
            data_get($row, 'detalle_items'),
        ])->filter()->implode(' '));

        return str_contains($service, 'CONTRATO')
            || preg_match('/(^|[^A-Z])ECA([^A-Z]|$)/', $service) === 1;
    }

    private function kardexOperationGroupLabel(array $row): string
    {
        return match ($this->normalizeKardexText((string) data_get($row, 'modalidad_entrega', ''))) {
            'ADMISION' => 'ADMISIONES',
            'ENTREGA', 'DISTRIBUCION DOMICILIARIA', 'DISTRIBUCION NO DOMICILIARIA' => 'ENTREGAS',
            default => 'ADMISIONES',
        };
    }

    private function kardexOperationGroupRank(string $group): int
    {
        return match ($this->normalizeKardexText($group)) {
            'ADMISIONES' => 1,
            'ENTREGAS' => 2,
            default => 3,
        };
    }

    private function kardexDeliverySubgroupRank(string $subgroup): int
    {
        return match ($this->normalizeKardexText($subgroup)) {
            'ENTREGA DISTRIBUCION NO DOMICILIARIA' => 1,
            'ENTREGA DISTRIBUCION DOMICILIARIA' => 2,
            default => 3,
        };
    }

    private function buildKardexOperationGroups(Collection $rows, bool $groupServices = true): Collection
    {
        $nextRowNumber = 0;

        return $rows
            ->groupBy(fn (array $row): string => (string) data_get($row, 'operacion_grupo', $this->kardexOperationGroupLabel($row)))
            ->sortBy(fn (Collection $groupRows, string $label): int => $this->kardexOperationGroupRank($label))
            ->map(function (Collection $operationRows, string $label) use ($groupServices, &$nextRowNumber): array {
                $isDeliveryGroup = $this->normalizeKardexText($label) === 'ENTREGAS';
                $subgroupRows = $isDeliveryGroup
                    ? $operationRows
                        ->groupBy(fn (array $row): string => trim((string) data_get($row, 'operacion_subgrupo', '')))
                        ->sortBy(fn (Collection $rows, string $subgroup): int => $this->kardexDeliverySubgroupRank($subgroup))
                    : collect(['' => $operationRows->values()]);

                $deliverySubgroups = $subgroupRows->map(function (Collection $rows, string $subgroup) use ($groupServices, &$nextRowNumber): array {
                    $serviceGroups = $groupServices
                        ? $rows
                            ->groupBy(fn (array $row): string => $this->normalizeKardexText(trim((string) data_get($row, 'tipo_correspondencia', ''))) ?: 'SIN DETALLE')
                            ->sortKeysUsing(fn ($left, $right) => strnatcasecmp((string) $left, (string) $right))
                        : collect(['' => $rows->values()]);

                    $serviceGroups = $serviceGroups->map(fn (Collection $serviceRows) => $serviceRows
                        ->values()
                        ->map(function (array $row) use (&$nextRowNumber): array {
                            $row['nro'] = ++$nextRowNumber;

                            return $row;
                        })
                        ->values());
                    $subgroupRows = $serviceGroups->flatten(1)->values();

                    return [
                        'label' => $subgroup,
                        'rows' => $subgroupRows,
                        'service_groups' => $serviceGroups,
                        'total_importe' => round((float) $subgroupRows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
                    ];
                });
                $operationRows = $deliverySubgroups->flatMap(fn (array $subgroup) => $subgroup['rows'])->values();
                $serviceGroups = $groupServices
                    ? $operationRows
                        ->groupBy(fn (array $row): string => $this->normalizeKardexText(trim((string) data_get($row, 'tipo_correspondencia', ''))) ?: 'SIN DETALLE')
                        ->sortKeysUsing(fn ($left, $right) => strnatcasecmp((string) $left, (string) $right))
                    : collect(['' => $operationRows]);

                return [
                    'label' => $label,
                    'rows' => $operationRows->values(),
                    'service_groups' => $serviceGroups,
                    'delivery_subgroups' => $isDeliveryGroup ? $deliverySubgroups->values() : collect(),
                    'total_importe' => round((float) $operationRows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
                ];
            })
            ->values();
    }

    private function buildKardexRegionalCashierSections(Collection $rows): Collection
    {
        return $rows
            ->groupBy(function (array $row): string {
                $userId = trim((string) data_get($row, 'origen_usuario_id', ''));
                $name = $this->normalizeKardexText((string) data_get($row, 'origen_usuario_nombre', ''));

                return $userId !== '' ? 'user:' . $userId : 'name:' . ($name !== '' ? $name : 'SIN CAJERO');
            })
            ->map(function (Collection $cashierRows): array {
                $first = $cashierRows->first();
                $serviceGroups = $cashierRows
                    ->groupBy(function (array $row): string {
                        $service = trim((string) data_get($row, 'tipo_correspondencia', ''));

                        return $service !== '' ? $service : 'SIN DETALLE';
                    })
                    ->sortKeysUsing(fn ($left, $right) => strnatcasecmp((string) $left, (string) $right))
                    ->map(fn (Collection $serviceRows) => $serviceRows
                        ->sortBy(fn (array $row) => sprintf('%012d|%s', (int) data_get($row, 'fecha_sort', 0), (string) data_get($row, 'numero_factura', '')))
                        ->values());
                $detailRows = $serviceGroups
                    ->flatten(1)
                    ->values()
                    ->map(function (array $row, int $index): array {
                        $row['nro'] = $index + 1;

                        return $row;
                    });
                $serviceGroups = $detailRows->groupBy(fn (array $row) => trim((string) data_get($row, 'tipo_correspondencia', '')) ?: 'SIN DETALLE');
                $responsableName = trim((string) data_get($first, 'origen_usuario_nombre', ''));
                $cashierId = trim((string) data_get($first, 'origen_usuario_id', ''));

                return [
                    'key' => $cashierId !== '' ? $cashierId : $this->normalizeKardexText($responsableName),
                    'sheet_name' => $responsableName !== '' ? $responsableName : ($cashierId !== '' ? 'Cajero ' . $cashierId : 'Sin cajero'),
                    'responsable_name' => $responsableName !== '' ? $responsableName : 'SIN RESPONSABLE',
                    'ventanilla_name' => $cashierId !== '' ? $cashierId : '-',
                    'rows' => $detailRows,
                    'service_groups' => $serviceGroups,
                    'operation_groups' => $this->buildKardexOperationGroups($detailRows, true),
                    'total_importe' => round((float) $detailRows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2),
                    'total_peso' => round((float) $detailRows->sum(fn ($row) => (float) data_get($row, 'peso', 0)), 3),
                ];
            })
            ->sortBy(fn (array $section) => $this->normalizeKardexText((string) $section['sheet_name']))
            ->values();
    }

    private function resolveSucursalCashiers(User $viewer): Collection
    {
        $sucursalId = (int) ($viewer->sucursal_id ?? 0);

        return User::query()
            ->where('sucursal_id', $sucursalId)
            ->permission('feature.dashboard.facturacion')
            ->with(['sucursal', 'roles.permissions', 'permissions'])
            ->orderBy('name')
            ->get()
            ->values();
    }

    private function fetchSucursalRawRows(FacturacionCartService $service, Collection $cashiers, array $filters): array
    {
        $rows = collect();
        $batch = $service->fetchSucursalKardexBatch($cashiers->all(), $filters);
        $failedUsers = (array) ($batch['errors'] ?? []);

        foreach ($cashiers as $cashier) {
            $cashierId = trim((string) $cashier->id);
            if (isset($failedUsers[$cashierId])) {
                \Illuminate\Support\Facades\Log::warning('No se pudieron cargar ventas para un cajero del Kardex por Sucursal.', [
                    'user_id' => $cashierId,
                    'reason' => $failedUsers[$cashierId],
                ]);
                continue;
            }

            $cashierRows = collect($batch['rows'][$cashierId] ?? [])
                ->map(function ($row) use ($cashier) {
                    $sucursal = $cashier->sucursal;
                    $fields = [
                        ['origenUsuarioId', 'origen_usuario_id', (string) $cashier->id],
                        ['origenUsuarioNombre', 'origen_usuario_nombre', (string) $cashier->name],
                        ['origenUsuarioEmail', 'origen_usuario_email', (string) $cashier->email],
                        ['origenUsuarioAlias', 'origen_usuario_alias', (string) ($cashier->alias ?? '')],
                        ['origenUsuarioCarnet', 'origen_usuario_carnet', (string) ($cashier->ci ?? '')],
                        ['origenUsuarioCiudad', 'origen_usuario_ciudad', (string) ($cashier->ciudad ?? '')],
                        ['origenSucursalNombre', 'origen_sucursal_nombre', (string) ($sucursal->nombre ?? $sucursal->descripcion ?? '')],
                        ['origenSucursalCodigo', 'origen_sucursal_codigo', (string) ($sucursal->codigo ?? '')],
                        ['origenSucursalMunicipio', 'origen_sucursal_municipio', (string) ($sucursal->municipio ?? '')],
                        ['origenSucursalDepartamento', 'origen_sucursal_departamento', (string) ($sucursal->departamento ?? '')],
                    ];

                    foreach ($fields as [$camelKey, $snakeKey, $fallback]) {
                        $current = trim((string) (data_get($row, $camelKey) ?? data_get($row, $snakeKey) ?? ''));
                        if ($current === '') {
                            $row->{$camelKey} = $fallback;
                        }
                    }

                    return $row;
                });
            $rows = $rows->concat($cashierRows);
        }

        return [$rows->values(), count($failedUsers), $cashiers->count()];
    }

    private function summarizeKardexPaymentMetrics(Collection $rows): array
    {
        $sales = $rows->groupBy(function ($row, $index): string {
            $key = trim((string) data_get($row, 'venta_group_key', ''));

            return $key !== '' ? 'sale:' . $key : 'row:' . $index;
        });

        $cashSales = $sales->filter(function (Collection $details): bool {
            $sale = $details->first();

            return strtolower(trim((string) data_get($sale, 'estado_venta', ''))) === 'emitido'
                && strtolower(trim((string) data_get($sale, 'estado_pago', ''))) === 'pagado'
                && strtoupper(trim((string) data_get($sale, 'estado_emision', ''))) === 'FACTURADA'
                && strtolower(trim((string) data_get($sale, 'metodo_pago', ''))) !== 'qr'
                && ! $this->isQrPaymentRow($sale)
                && (bool) data_get($sale, 'contabiliza_en_caja', true)
                && ! (bool) data_get($sale, 'es_cuenta_por_cobrar', false)
                && strtolower(trim((string) data_get($sale, 'canal_emision', ''))) !== 'oficial'
                && ! (bool) data_get($sale, 'es_anulada', false);
        });
        $qrPaidSales = $sales->filter(function (Collection $details): bool {
            $sale = $details->first();

            return $this->isQrPaymentRow($sale)
                && strtolower(trim((string) data_get($sale, 'estado_venta', ''))) === 'emitido'
                && strtolower(trim((string) data_get($sale, 'estado_pago', ''))) === 'pagado'
                && ! (bool) data_get($sale, 'es_anulada', false);
        });
        $sumSaleDetails = static fn (Collection $groups): float => round((float) $groups->sum(
            fn (Collection $details): float => (float) $details->sum(
                fn ($detail): float => (float) data_get($detail, 'importe', 0)
            )
        ), 2);

        return [
            'totalVentas' => $sales->count(),
            'ventasEfectivo' => $cashSales->count(),
            'montoEfectivo' => $sumSaleDetails($cashSales),
            'ventasQrPagadas' => $qrPaidSales->count(),
            'montoQr' => $sumSaleDetails($qrPaidSales),
        ];
    }

    private function resolveKardexUserOffice(User $user): string
    {
        $office = method_exists($user, 'regionalesTexto') ? $user->regionalesTexto() : '';
        $office = trim((string) ($office ?: $user->ciudad ?: data_get($user, 'sucursal.departamento', '')));

        return strtoupper($office !== '' ? $office : '-');
    }
    private function isKardexCasillaService(string $tipoServicio, string $guiaCasilla = ''): bool
    {
        $normalized = $this->normalizeKardexText($tipoServicio . ' ' . $guiaCasilla);
        if (str_contains($normalized, 'ESTAMPILLA')) {
            return false;
        }

        return str_contains($normalized, 'CASILLA');
    }

    private function applyKardexSearchFilter(Collection $rows, string $query): Collection
    {
        $query = $this->normalizeKardexText($query);
        if ($query === '') {
            return $rows;
        }

        return $rows->filter(function ($row) use ($query): bool {
            $haystack = collect([
                data_get($row, 'fecha'),
                data_get($row, 'origen'),
                data_get($row, 'regional_registro'),
                data_get($row, 'venta_referencia'),
                data_get($row, 'metodo_pago_label'),
                data_get($row, 'estado_venta_label'),
                data_get($row, 'estado_label'),
                data_get($row, 'tipo_correspondencia'),
                data_get($row, 'tipo_servicio'),
                data_get($row, 'cliente'),
                data_get($row, 'guia_casilla'),
                data_get($row, 'pais_ciudad'),
                data_get($row, 'numero_factura'),
                data_get($row, 'importe'),
                data_get($row, 'modalidad_entrega'),
            ])->map(fn ($value) => $this->normalizeKardexText((string) $value))->implode(' ');

            return str_contains($haystack, $query);
        });
    }
    private function buildKardexSpreadsheet(array $report): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getDefaultStyle()->getFont()->setName('Arial')->setSize(9);
        $isRegional = (bool) data_get($report, 'isRegionalKardex', false);
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($isRegional ? 'Sucursal' : 'Kardex');
        // Personal and regional exports use the same operation → service order.
        // Keep service labels in the detail rows without adding service subtitles.
        $this->populateKardexWorksheet($sheet, $report, true, false);

        if ($isRegional) {
            foreach (collect(data_get($report, 'cashierSections', [])) as $section) {
                $cashierSheet = $spreadsheet->createSheet();
                $cashierSheet->setTitle($this->uniqueKardexWorksheetTitle($spreadsheet, (string) data_get($section, 'sheet_name', 'Cajero')));
                $cashierReport = array_merge($report, [
                    'visibleRows' => data_get($section, 'rows', collect()),
                    'responsableName' => (string) data_get($section, 'responsable_name', 'SIN RESPONSABLE'),
                    'ventanillaName' => (string) data_get($section, 'ventanilla_name', '-'),
                    'totalImporte' => (float) data_get($section, 'total_importe', 0),
                ]);
                $this->populateKardexWorksheet($cashierSheet, $cashierReport, true, false);
            }
        }

        return $spreadsheet;
    }

    private function populateKardexWorksheet(Worksheet $sheet, array $report, bool $groupServices, bool $showServiceSubtitles = false): void
    {

        foreach (['A' => 6, 'B' => 13, 'C' => 11, 'D' => 10, 'E' => 42, 'F' => 31, 'G' => 10, 'H' => 24, 'I' => 26, 'J' => 13, 'K' => 15] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        foreach ([1 => 24, 2 => 24, 3 => 18, 4 => 18, 5 => 18, 6 => 23, 7 => 23, 8 => 8, 9 => 30] as $row => $height) {
            $sheet->getRowDimension($row)->setRowHeight($height);
        }

        $sheet->mergeCells('A1:B2');
        $sheet->mergeCells('C1:E2');
        $sheet->mergeCells('F1:K2');
        $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $this->addKardexLogo($sheet, public_path('images/LOGO 19-2-26.png'), 'A1', 118, 46);
        $this->addKardexLogo($sheet, public_path('images/ministerio-obras-publicas.png'), 'F1', 250, 46);

        $sheet->mergeCells('B3:E5');
        $sheet->setCellValue('B3', "KARDEX DIARIO DE RENDICIÓN\nAGENCIA BOLIVIANA DE CORREOS\nEXPRESADO EN BS.");
        $sheet->getStyle('B3:E5')->getAlignment()->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('B3:E5')->getFont()->setBold(true)->setSize(10);
        $sheet->getStyle('B3:E5')->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);

        $sheet->mergeCells('H3:K3');
        $sheet->mergeCells('H4:K5');
        $sheet->setCellValue('H3', 'Dirección de Operaciones');
        $sheet->setCellValue('H4', 'ADMISIONES / ENTREGAS');
        $sheet->getStyle('H3:K5')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('H3:K5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        $sheet->mergeCells('A6:C6');
        $sheet->setCellValue('A6', 'Oficina Postal:');
        $sheet->mergeCells('D6:E6');
        $sheet->setCellValue('D6', strtoupper((string) data_get($report, 'officePostal', '')));
        $sheet->mergeCells('F6:G6');
        $sheet->setCellValue('F6', 'Fecha de recaudación:');
        $sheet->mergeCells('H6:K6');
        $sheet->setCellValue('H6', $this->formatKardexExportDate((string) data_get($report, 'filters.to')));
        $sheet->mergeCells('A7:C7');
        $sheet->setCellValue('A7', 'Nombre Responsable:');
        $sheet->mergeCells('D7:K7');
        $sheet->setCellValue('D7', strtoupper((string) data_get($report, 'responsableName', '')));
        $sheet->getStyle('A6:K7')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A6:K7')->getFont()->setBold(true);
        $sheet->getStyle('A6:K7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A6:C7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle('D6:E6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H6:K6')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D7:K7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = ['N°', 'FECHA', 'CANTIDAD', 'REGIONAL', 'TIPO DE CORRESPONDENCIA', 'CÓDIGO DE ENVÍO', 'PESO', 'PAÍS/CIUDAD DE DESTINO / CIUDAD DE ORIGEN', 'OPERACIÓN', 'N° FACTURA', 'IMPORTE'];
        $sheet->fromArray($headers, null, 'A9');
        $sheet->getStyle('A9:K9')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        $sheet->getStyle('A9:K9')->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle('A9:K9')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $sheet->getStyle('A9:K9')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $rowNumber = 10;
        $visibleRows = collect($report['visibleRows'] ?? [])->values();
        $operationGroups = $this->buildKardexOperationGroups($visibleRows, $groupServices);
        foreach ($operationGroups as $operationGroup) {
            $operationRows = collect(data_get($operationGroup, 'rows', []))->values();
            $sheet->mergeCells('A' . $rowNumber . ':J' . $rowNumber);
            $sheet->setCellValue('A' . $rowNumber, mb_strtoupper((string) data_get($operationGroup, 'label', 'ADMISIONES')) . ' · ' . number_format($operationRows->count()) . ' registros');
            $sheet->setCellValue('K' . $rowNumber, 'Bs ' . number_format((float) data_get($operationGroup, 'total_importe', 0), 2, ',', '.'));
            $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1D4F7A');
            $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle('K' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $rowNumber++;

            $deliverySubgroups = collect(data_get($operationGroup, 'delivery_subgroups', []));
            if ($deliverySubgroups->isEmpty()) {
                $deliverySubgroups = collect([[
                    'label' => '',
                    'rows' => $operationRows,
                    'service_groups' => data_get($operationGroup, 'service_groups', []),
                    'total_importe' => data_get($operationGroup, 'total_importe', 0),
                ]]);
            }

            foreach ($deliverySubgroups as $deliverySubgroup) {
                $subgroupLabel = trim((string) data_get($deliverySubgroup, 'label', ''));
                $subgroupRows = collect(data_get($deliverySubgroup, 'rows', []));
                if ($subgroupLabel !== '') {
                    $sheet->mergeCells('A' . $rowNumber . ':J' . $rowNumber);
                    $sheet->setCellValue('A' . $rowNumber, $subgroupLabel . ' · ' . number_format($subgroupRows->count()) . ' registros');
                    $sheet->setCellValue('K' . $rowNumber, 'Bs ' . number_format((float) data_get($deliverySubgroup, 'total_importe', 0), 2, ',', '.'));
                    $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C9DEEF');
                    $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getFont()->setBold(true)->getColor()->setRGB('18324B');
                    $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle('A' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('K' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $rowNumber++;
                }

            foreach (collect(data_get($deliverySubgroup, 'service_groups', [])) as $serviceName => $serviceRows) {
                $serviceRows = collect($serviceRows)->values();
                if ($groupServices && $showServiceSubtitles) {
                    $sheet->mergeCells('A' . $rowNumber . ':J' . $rowNumber);
                    $sheet->setCellValue('A' . $rowNumber, mb_strtoupper((string) $serviceName) . ' · ' . number_format($serviceRows->count()) . ' registros');
                    $sheet->setCellValue('K' . $rowNumber, 'Bs ' . number_format((float) $serviceRows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2, ',', '.'));
                    $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9EAF7');
                    $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getFont()->setBold(true)->getColor()->setRGB('18324B');
                    $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                    $sheet->getStyle('A' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    $sheet->getStyle('K' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $rowNumber++;
                }

                foreach ($serviceRows as $row) {
            $tipoCorrespondencia = (string) data_get($row, 'tipo_correspondencia');
            $detalleCasilla = trim((string) data_get($row, 'detalle_casilla', ''));
            if ((bool) data_get($row, 'es_casilla') && $detalleCasilla !== '') {
                $tipoCorrespondencia .= ' - ' . $detalleCasilla;
            }
            $sheet->fromArray([
                data_get($row, 'nro'),
                data_get($row, 'fecha'),
                data_get($row, 'cantidad'),
                data_get($row, 'origen'),
                $tipoCorrespondencia,
                data_get($row, 'guia_casilla'),
                data_get($row, 'peso') !== null && data_get($row, 'peso') !== '' ? number_format((float) data_get($row, 'peso'), 3, ',', '.') : '',
                (bool) data_get($row, 'es_casilla')
                    ? ''
                    : ((string) data_get($row, 'pais_ciudad', '-') === '-'
                        ? '-'
                        : strtoupper((string) data_get($row, 'ubicacion_tipo', 'DESTINO')).': '.data_get($row, 'pais_ciudad')),
                trim((string) data_get($row, 'modalidad_entrega', '-'))
                    .(trim((string) data_get($row, 'operacion_detalle', '')) !== ''
                        ? ' · '.trim((string) data_get($row, 'operacion_detalle'))
                        : ''),
                data_get($row, 'numero_factura'),
                'Bs ' . number_format((float) data_get($row, 'importe', 0), 2, ',', '.'),
            ], null, 'A' . $rowNumber);
            $hasDeliveryMode = trim((string) data_get($row, 'modalidad_entrega', '-')) !== '-';
            $hasOperationDetail = trim((string) data_get($row, 'operacion_detalle', '')) !== '';
            $sheet->getRowDimension($rowNumber)->setRowHeight(((bool) data_get($row, 'es_casilla') && $detalleCasilla !== '') || $hasDeliveryMode || $hasOperationDetail ? 30 : 18);
            $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle('A' . $rowNumber . ':K' . $rowNumber)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
            $sheet->getStyle('A' . $rowNumber . ':D' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G' . $rowNumber . ':J' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('K' . $rowNumber)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            if ((bool) data_get($row, 'es_anulada')) {
                $sheet->getStyle('J' . $rowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
                $sheet->getStyle('J' . $rowNumber)->getFont()->getColor()->setRGB('C00000');
                $sheet->getStyle('J' . $rowNumber)->getFont()->setBold(true);
            } else {
                $sheet->getStyle('J' . $rowNumber)->getFont()->getColor()->setRGB('198754');
                $sheet->getStyle('J' . $rowNumber)->getFont()->setBold(true);
            }
            $rowNumber++;
                }
            }
            }
        }

        $lastDataRow = max(10, $rowNumber - 1);
        $totalRow = $rowNumber;
        $sheet->mergeCells('A' . $totalRow . ':J' . $totalRow);
        $sheet->setCellValue('A' . $totalRow, 'TOTAL PARCIAL');
        $sheet->setCellValue('K' . $totalRow, 'Bs ' . number_format((float) $report['totalImporte'], 2, ',', '.'));
        $sheet->mergeCells('A' . ($totalRow + 1) . ':J' . ($totalRow + 1));
        $sheet->setCellValue('A' . ($totalRow + 1), 'TOTAL GENERAL');
        $sheet->setCellValue('K' . ($totalRow + 1), 'Bs ' . number_format((float) $report['totalImporte'], 2, ',', '.'));
        $sheet->getStyle('A' . $totalRow . ':K' . ($totalRow + 1))->getFont()->setBold(true);
        $sheet->getStyle('A' . $totalRow . ':K' . ($totalRow + 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $totalRow . ':A' . ($totalRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('K' . $totalRow . ':K' . ($totalRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $obsRow = $totalRow + 3;
        $sheet->mergeCells('A' . $obsRow . ':E' . ($obsRow + 5));
        $sheet->setCellValue('A' . $obsRow, 'Observaciones');
        $sheet->mergeCells('F' . $obsRow . ':G' . ($obsRow + 5));
        $sheet->setCellValue('F' . ($obsRow + 5), "SELLO / FIRMA DE CONFORMIDAD\nRECAUDADOR");
        $sheet->mergeCells('H' . $obsRow . ':I' . ($obsRow + 5));
        $sheet->setCellValue('H' . ($obsRow + 5), "SELLO / FIRMA DE CONFORMIDAD\nREVISOR");
        $sheet->mergeCells('J' . $obsRow . ':K' . ($obsRow + 5));
        $sheet->setCellValue('J' . ($obsRow + 5), "SELLO RECEPCIÓN\nTESORERÍA");
        foreach (range($obsRow, $obsRow + 5) as $footerRow) {
            $sheet->getRowDimension($footerRow)->setRowHeight(22);
        }
        $sheet->getStyle('A' . $obsRow . ':K' . ($obsRow + 5))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('F' . $obsRow . ':K' . ($obsRow + 5))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_BOTTOM)->setWrapText(true);
        $sheet->getStyle('A' . $obsRow)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        $sheet->freezePane('A10');
        $sheet->setAutoFilter('A9:K' . $lastDataRow);
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0)->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageMargins()->setTop(0.35)->setRight(0.25)->setLeft(0.25)->setBottom(0.35);
        $sheet->getPageSetup()->setPrintArea('A1:K' . ($obsRow + 5));
        $sheet->getStyle('A1:K' . ($obsRow + 5))->getAlignment()->setShrinkToFit(false);

    }

    private function uniqueKardexWorksheetTitle(Spreadsheet $spreadsheet, string $title): string
    {
        $base = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $title));
        $base = mb_substr($base !== '' ? $base : 'Cajero', 0, 31);
        $candidate = $base;
        $suffixNumber = 2;
        $existingTitles = collect($spreadsheet->getSheetNames())->map(fn ($name) => mb_strtolower((string) $name));

        while ($existingTitles->contains(mb_strtolower($candidate))) {
            $suffix = ' (' . $suffixNumber . ')';
            $candidate = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
            $suffixNumber++;
        }

        return $candidate;
    }

    private function addKardexLogo($sheet, string $path, string $cell, int $width, int $height): void
    {
        if (! is_file($path)) {
            return;
        }

        $drawing = new Drawing();
        $drawing->setPath($path);
        $drawing->setCoordinates($cell);
        $drawing->setWidth($width);
        $drawing->setHeight($height);
        $drawing->setWorksheet($sheet);
    }

    private function formatKardexExportDate(?string $date): string
    {
        if (! $date) {
            return now()->format('d/m/Y');
        }

        try {
            return \Carbon\Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }
    private function renderVentasPage(Request $request, FacturacionCartService $service, string $scope, string $routeScope = 'own'): View
    {
        [$user, $filters] = $this->resolveRequestContext($request, $scope);
        $cajaContext = $this->resolveCajaContext($service, $user);
        [$rows, $summary, $extra] = $this->loadScopedVentas($service, $user, $filters, $scope);

        $pageRows = $rows
            ->forPage((int) $filters['page'], (int) $filters['per_page'])
            ->values();

        $carts = new LengthAwarePaginator(
            $pageRows,
            $rows->count(),
            (int) $filters['per_page'],
            (int) $filters['page'],
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('facturacion.mis-ventas', [
            'carts' => $carts,
            'summary' => $summary,
            'filters' => $filters,
            'cajaContext' => $cajaContext,
            'pageContext' => $this->pageContextForScope($scope, $routeScope),
            'branchCashierSummary' => $extra['branch_cashiers'] ?? collect(),
        ]);
    }

    private function exportVentasPdf(Request $request, FacturacionCartService $service, string $scope, string $routeScope = 'own'): Response
    {
        [$user, $filters] = $this->resolveRequestContext($request, $scope);
        $exportFilters = $filters;
        $exportFilters['per_page'] = 100;
        [$carts] = $this->loadScopedVentas($service, $user, $exportFilters, $scope);
        $rows = $this->buildPdfRows($carts);

        $totals = [
            'parcial' => round((float) $rows
                ->filter(fn ($row) => (bool) data_get($row, 'contabiliza_en_caja', true))
                ->sum(fn ($row) => (float) data_get($row, 'importe_parcial', 0)), 2),
            'general' => round((float) $rows
                ->filter(fn ($row) => (bool) data_get($row, 'contabiliza_en_caja', true))
                ->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
            'qr' => round((float) $rows
                ->filter(fn ($row) => strtolower((string) data_get($row, 'metodo_pago', '')) === 'qr')
                ->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
        ];

        $pdf = Pdf::loadView('facturacion.mis-ventas-kardex-pdf', [
            'user' => $user,
            'filters' => $filters,
            'generatedAt' => now(),
            'carts' => $carts,
            'rows' => $rows,
            'totals' => $totals,
            'scope' => $scope,
            'routeScope' => $routeScope,
        ])->setPaper('a4', 'portrait');

        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function ticket(Request $request, int $cart, FacturacionCartService $service, SitraIpsClient $ips): StreamedResponse
    {
        $user = $this->authorizeVentasViewer($request->user(), $request->query('scope') === 'branch' ? 'branch' : 'own');
        $sourceUser = $this->resolveSourceUserForRequest($request, $user);
        $venta = $this->resolveVentaForDetail($service, $sourceUser, $cart);

        abort_unless($venta, 404, 'No se encontro la venta solicitada.');

        $snapshot = $this->ticketSnapshot($venta, $ips);
        abort_unless($this->ticketSnapshotHasEligiblePackage($snapshot), 409, 'El paquete todavía no está listo para entrega.');
        $ticket = $this->buildTicketData($venta, $user, $snapshot);
        $pdf = Pdf::loadView('facturacion.mis-ventas-ticket', ['cart' => $venta, 'ticket' => $ticket])->setPaper([0, 0, 226.77, 680], 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), 'ticket-' . ($venta->codigo_orden ?: ('venta-' . $venta->id)) . '.pdf');
    }

    private function ticketSnapshotHasEligiblePackage(array $snapshot): bool
    {
        $progressService = app(TrackingProgressService::class);

        foreach ((array) data_get($snapshot, 'items', []) as $item) {
            $eventText = trim((string) (($item['estado'] ?? '') . ' ' . ($item['evento'] ?? '')));
            $normalizedEventText = mb_strtolower($eventText);
            $progress = $progressService->resolve([[
                'codigo_evento' => $item['evento_cd'] ?? null,
                'nombre_evento' => $eventText,
            ]], 'TRACKING');
            $steps = array_values((array) ($progress['steps'] ?? []));
            $currentIndex = (int) ($progress['current_index'] ?? -1);
            $expeditionIndex = collect($steps)
                ->search(fn ($step) => strcasecmp((string) $step, 'Expedicion') === 0);

            if ($expeditionIndex !== false && $currentIndex >= (int) $expeditionIndex) {
                return true;
            }

            // El API puede devolver solo la etiqueta de etapa; estos estados
            // también confirman que el paquete ya pasó a la fase de entrega.
            if (str_contains($normalizedEventText, 'aduana')
                || str_contains($normalizedEventText, 'reparto')) {
                return true;
            }
        }

        return false;
    }

    private function ticketSnapshot(object $venta, SitraIpsClient $ips): array
    {
        $cartId = (int) ($venta->id ?? 0);
        if ($cartId <= 0) return [];
        $items = [];
        foreach ($this->normalizeItems($venta->items ?? []) as $item) {
            $code = strtoupper(trim((string) data_get($item, 'resumen_origen.codigo_paquete', data_get($item, 'codigo_paquete', ''))));
            if ($code === '') continue;
            try {
                $package = $ips->package($code)['package'] ?? [];
                $items[] = ['codigo' => $code, 'evento_cd' => $package['operational_event_cd'] ?? null, 'evento' => $package['event_name'] ?? null, 'estado' => $package['stage']['label'] ?? null, 'oficina' => $package['office_name'] ?? null];
            } catch (\Throwable) {
                $items[] = ['codigo' => $code, 'evento_cd' => null, 'evento' => null, 'estado' => null, 'oficina' => null];
            }
        }
        $data = ['captured_at' => now()->toIso8601String(), 'items' => $items];
        FacturaTicketSnapshot::updateOrCreate(
            ['cart_id' => $cartId],
            ['data' => $data, 'captured_at' => now()]
        );
        return $data;
    }

    public function detail(Request $request, int $cart, FacturacionCartService $service): JsonResponse
    {
        $user = $this->authorizeVentasViewer($request->user(), $request->query('scope') === 'branch' ? 'branch' : 'own');
        $sourceUser = $this->resolveSourceUserForRequest($request, $user);
        $venta = $this->resolveVentaForDetail($service, $sourceUser, $cart);
        abort_unless($venta, 404, 'No se encontro la venta solicitada.');

        $items = $this->normalizeItems(data_get($venta, 'items', []))
            ->map(function ($item) {
                $item = is_array($item) ? (object) $item : $item;
                $codigoReferencia = collect([
                    trim((string) data_get($item, 'resumen_origen.codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_item', '')),
                    trim((string) data_get($item, 'codigo', '')),
                    trim((string) data_get($item, 'codigo_item', '')),
                ])->first(fn ($code) => $code !== '' && ! $this->isServiceReferenceCode((string) $code));

                return [
                    'id' => (int) data_get($item, 'id', 0),
                    'codigo' => trim((string) data_get($item, 'codigo', '')),
                    'codigo_detalle_enviado' => trim((string) data_get($item, 'codigo_detalle_enviado', data_get($item, 'resumen_origen.codigo_detalle_enviado', ''))),
                    'codigo_paquete' => trim((string) data_get($item, 'codigo_paquete', data_get($item, 'resumen_origen.codigo_paquete', ''))),
                    'codigo_item' => trim((string) data_get($item, 'codigo_item', '')),
                    'codigo_referencia' => is_string($codigoReferencia) ? $codigoReferencia : '',
                    'origen_tipo' => trim((string) data_get($item, 'origen_tipo', '')),
                    'titulo' => trim((string) data_get($item, 'titulo', '')),
                    'nombre_servicio' => trim((string) data_get($item, 'nombre_servicio', '')),
                    'nombre_destinatario' => trim((string) data_get($item, 'nombre_destinatario', '')),
                    'resumen_origen' => (array) data_get($item, 'resumen_origen', []),
                    'tarifario_manual_referencia' => (array) data_get($item, 'resumen_origen.tarifario_manual_referencia', []),
                    'cantidad' => (int) data_get($item, 'cantidad', 0),
                    'monto_base' => round((float) data_get($item, 'monto_base', 0), 2),
                    'monto_extras' => round((float) data_get($item, 'monto_extras', 0), 2),
                    'total_linea' => round((float) data_get($item, 'total_linea', 0), 2),
                ];
            })
            ->values();

        return response()->json([
            'cart' => [
                'id' => (int) data_get($venta, 'id', $cart),
                'codigo_orden' => trim((string) data_get($venta, 'codigo_orden', '')),
                'total' => round((float) data_get($venta, 'total', 0), 2),
                'items_count' => max((int) data_get($venta, 'items_count', 0), $items->count()),
            ],
            'items' => $items,
        ]);
    }

    private function resolveRequestContext(Request $request, string $scope = 'own'): array
    {
        $user = $this->authorizeVentasViewer($request->user(), $scope);
        $today = now()->toDateString();

        $validated = $request->validate([
            'estado' => ['nullable', 'in:all,borrador,pendiente_pago,emitido'],
            'estado_emision' => ['nullable', 'in:all,FACTURADA,PENDIENTE,RECHAZADA,ERROR,NO_APLICA,ANULADA,PENDIENTE_ANULACION,ANULACION_OBSERVADA'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        return [$user, [
            'estado' => (string) (($validated['estado'] ?? 'all') === 'borrador' ? 'all' : ($validated['estado'] ?? 'all')),
            'estado_emision' => (string) ($validated['estado_emision'] ?? 'all'),
            'from' => $validated['from'] ?? $today,
            'to' => $validated['to'] ?? $today,
            'q' => trim((string) ($validated['q'] ?? '')),
            'per_page' => (int) ($validated['per_page'] ?? 20),
            'page' => (int) ($validated['page'] ?? 1),
        ]];
    }

    private function resolveEffectiveOwnScope(Request $request): string
    {
        $requestedScope = strtolower(trim((string) $request->query('scope', '')));
        if ($requestedScope === 'own') {
            return 'own';
        }

        $user = $request->user();
        if ($requestedScope === 'branch' && $this->canAccessBranchVentas($user)) {
            return 'branch';
        }

        return $this->canAccessBranchVentas($user) ? 'branch' : 'own';
    }

    private function canAccessBranchVentas(?User $user): bool
    {
        if (!$user || (int) ($user->sucursal_id ?? 0) <= 0) {
            return false;
        }

        return $user->can('ventas-sucursal.export.pdf')
            || $user->can('ventas-sucursal.index')
            || $user->can('mis-ventas.kardex-regional');
    }

    private function authorizeVentasViewer(?User $user, string $scope = 'own'): User
    {
        abort_unless($user, 403, 'No tienes permiso para ver ventas.');

        if ($scope === 'branch') {
            abort_unless($this->canAccessBranchVentas($user), 403, 'No tienes permiso para ver ventas de sucursal.');
            abort_unless((int) ($user->sucursal_id ?? 0) > 0, 403, 'Tu cuenta no tiene una sucursal de facturacion asignada.');

            return $user;
        }

        abort_unless($user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para ver tus ventas.');

        return $user;
    }

    private function pageContextForScope(string $scope, string $routeScope = 'own'): array
    {
        if ($scope === 'branch') {
            return [
                'page_title' => 'Ventas sucursal',
                'panel_title' => 'Ventas de sucursal',
                'panel_subtitle' => 'Ventas emitidas por los cajeros de tu sucursal de facturacion.',
                'filter_route' => $routeScope === 'branch' ? 'ventas-sucursal.index' : 'mis-ventas.index',
                'export_route' => $routeScope === 'branch' ? 'ventas-sucursal.export.pdf' : 'mis-ventas.export.pdf',
                'show_cashier_column' => true,
                'scope' => 'branch',
                'route_scope' => $routeScope,
            ];
        }

        return [
            'page_title' => 'Mis ventas',
            'panel_title' => 'Historial de ventas',
            'panel_subtitle' => 'Detalle de emisiones registradas para tu cuenta.',
            'filter_route' => 'mis-ventas.index',
            'export_route' => 'mis-ventas.export.pdf',
            'show_cashier_column' => false,
            'scope' => 'own',
            'route_scope' => $routeScope,
        ];
    }

    private function loadScopedVentas(FacturacionCartService $service, User $user, array $filters, string $scope): array
    {
        $scopeMetadata = [
            'branch_cashiers' => collect(),
            'sucursal_cashier_count' => 0,
            'sucursal_failed_cashier_count' => 0,
            'kardex_source_errors' => [],
        ];
        if ($scope === 'branch') {
            $rawRows = $this->fetchBranchRawRows($service, $user, $filters);
        } elseif ($scope === 'branch_kardex') {
            [$rawRows, $failedCashiers, $cashierCount] = $this->fetchSucursalRawRows(
                $service,
                $this->resolveSucursalCashiers($user),
                $filters
            );
            $scopeMetadata['sucursal_cashier_count'] = $cashierCount;
            $scopeMetadata['sucursal_failed_cashier_count'] = $failedCashiers;
        } else {
            $rawRows = $this->fetchOwnRawRows($service, $user, $filters, $scopeMetadata['kardex_source_errors']);
        }

        $rawRows = $rawRows->reject(fn ($row) => $this->isUnconsolidatedManualRelease($row));

        if ($rawRows->isEmpty()) {
            return [collect(), $this->emptySummary(), $scopeMetadata];
        }

        $normalizedRows = $this->normalizeKardexRows($rawRows)
            ->reject(fn ($row) => $this->isUnconsolidatedManualRelease($row));
        $dedupeKey = fn ($row) => (in_array($scope, ['regional', 'branch_kardex'], true)
            ? trim((string) data_get($row, 'origen_usuario_id', '')) . ':'
            : '') . $this->normalizedVentaRowKey($row);
        $rows = $normalizedRows
            // The cart endpoint is concatenated before the Kardex endpoint. Prefer
            // the copy carrying the official invoice number before removing duplicates.
            ->sortByDesc(fn ($row) => $this->kardexRowCompletenessScore($row))
            ->unique($dedupeKey)
            ->sortByDesc(fn ($row) => strtotime((string) (data_get($row, 'emitido_en') ?: data_get($row, 'created_at', '1970-01-01 00:00:00'))))
            ->values();
        $rows = $this->applyLocalFilters($rows, $filters);
        $this->logKardexInvoiceNumberAudit($user, $filters, $scope, $rawRows, $normalizedRows, $rows, $dedupeKey);

        return [
            $rows->values(),
            $this->summaryFromRows($rows),
            array_merge($scopeMetadata, [
                'branch_cashiers' => $scope === 'branch' ? $this->buildBranchCashierSummary($rows) : collect(),
            ]),
        ];
    }

    private function fetchOwnRawRows(FacturacionCartService $service, User $user, array $filters, ?array &$sourceErrors = null): Collection
    {
        $sourceErrors = [];
        $fullRangeFilters = $filters;
        $fullRangeFilters['page'] = 1;
        $fullRangeFilters['per_page'] = 100;
        $fullRangeFilters['limite'] = 500;

        // Use the one-pass Kardex endpoint for a single cashier too. It returns
        // invoice and cart detail together, avoiding the slow summary endpoint
        // plus a second cart query used by the legacy path.
        $userId = trim((string) $user->id);
        $optimized = $service->fetchSucursalKardexBatch([$user], $fullRangeFilters);
        $optimizedError = (string) (
            data_get($optimized, 'errors.' . $userId)
            ?? data_get($optimized, 'errors.__unmatched')
            ?? ''
        );
        if ($optimizedError === '') {
            return collect(data_get($optimized, 'rows.' . $userId, []))
                ->map(fn ($row) => is_array($row) ? (object) $row : $row)
                ->filter(fn ($row) => is_object($row) && $this->kardexRowBelongsToUser($row, $user))
                ->values();
        }

        $isMissingFastEndpoint = preg_match('/\b404\b/', $optimizedError) === 1;
        Log::warning('No se pudo usar el endpoint optimizado del Kardex personal.', [
            'user_id' => $user->id,
            'from' => $fullRangeFilters['from'] ?? null,
            'to' => $fullRangeFilters['to'] ?? null,
            'reason' => $optimizedError,
            'fallback' => $isMissingFastEndpoint ? 'legacy' : 'carts_only',
        ]);

        $kardex = ['detalle' => []];
        if ($isMissingFastEndpoint) {
            try {
                $kardex = $service->fetchKardexVentas($user, $fullRangeFilters);
            } catch (\Throwable $exception) {
                $sourceErrors['detalle'] = $exception->getMessage();
                Log::warning('No se pudo cargar el detalle fiscal del Kardex personal.', [
                    'user_id' => $user->id,
                    'from' => $fullRangeFilters['from'] ?? null,
                    'to' => $fullRangeFilters['to'] ?? null,
                    'reason' => $exception->getMessage(),
                ]);
            }
        } else {
            $sourceErrors['detalle'] = $optimizedError;
        }

        $ventasPages = collect();
        $currentPage = 1;
        $lastPage = 1;

        try {
            do {
                $ventasFilters = $fullRangeFilters;
                $ventasFilters['page'] = $currentPage;

                $ventasPage = $service->fetchVentas($user, $ventasFilters);
                $ventasPages = $ventasPages->concat(collect($ventasPage['carts'] ?? []));

                $pagination = (array) ($ventasPage['pagination'] ?? []);
                $lastPage = max(
                    (int) ($pagination['last_page'] ?? 0),
                    (int) ($pagination['lastPage'] ?? 0),
                    (int) ($pagination['total_pages'] ?? 0),
                    (int) ($pagination['pages'] ?? 0),
                    1
                );

                $currentPage++;
            } while ($currentPage <= $lastPage);
        } catch (\Throwable $exception) {
            $sourceErrors['carritos'] = $exception->getMessage();
            Log::warning('No se pudieron cargar los carritos del Kardex personal.', [
                'user_id' => $user->id,
                'from' => $fullRangeFilters['from'] ?? null,
                'to' => $fullRangeFilters['to'] ?? null,
                'page' => $currentPage,
                'reason' => $exception->getMessage(),
            ]);
        }

        if (isset($sourceErrors['detalle'], $sourceErrors['carritos'])) {
            throw new \RuntimeException('Fallaron las consultas de detalle fiscal y carritos. Detalle: '
                . $sourceErrors['detalle'] . ' Carritos: ' . $sourceErrors['carritos']);
        }

        return $ventasPages
            ->concat(collect($kardex['detalle'] ?? []))
            ->map(fn ($row) => is_array($row) ? (object) $row : $row)
            ->filter(fn ($row) => is_object($row) && $this->kardexRowBelongsToUser($row, $user))
            ->values();
    }

    private function kardexRowBelongsToUser(object $row, User $user): bool
    {
        $sourceId = trim((string) (
            data_get($row, 'origen_usuario_id')
            ?: data_get($row, 'origenUsuarioId')
            ?: data_get($row, 'usuario.id')
            ?: ''
        ));
        if ($sourceId !== '') {
            return $sourceId === trim((string) $user->id);
        }

        foreach ([
            [
                data_get($row, 'origen_usuario_email') ?: data_get($row, 'origenUsuarioEmail') ?: data_get($row, 'usuario.email'),
                $user->email,
                static fn (string $value): string => mb_strtolower(trim($value)),
            ],
            [
                data_get($row, 'origen_usuario_alias') ?: data_get($row, 'origenUsuarioAlias') ?: data_get($row, 'usuario.alias'),
                $user->alias,
                static fn (string $value): string => mb_strtolower(trim($value)),
            ],
            [
                data_get($row, 'origen_usuario_carnet') ?: data_get($row, 'origenUsuarioCarnet') ?: data_get($row, 'usuario.carnet'),
                $user->ci,
                static fn (string $value): string => strtoupper(preg_replace('/\\s+/', '', trim($value))),
            ],
        ] as [$sourceValue, $expectedValue, $normalize]) {
            $sourceValue = trim((string) $sourceValue);
            $expectedValue = trim((string) $expectedValue);
            if ($sourceValue === '' || $expectedValue === '') {
                continue;
            }

            return $normalize($sourceValue) === $normalize($expectedValue);
        }

        $sourceName = trim((string) (
            data_get($row, 'origen_usuario_nombre')
            ?: data_get($row, 'origenUsuarioNombre')
            ?: data_get($row, 'usuario.nombre')
            ?: data_get($row, 'usuario.name')
            ?: ''
        ));

        return $sourceName === ''
            || $this->normalizeKardexText($sourceName) === $this->normalizeKardexText((string) $user->name);
    }

    private function fetchBranchRawRows(FacturacionCartService $service, User $viewer, array $filters): Collection
    {
        return $this->resolveBranchCashiers($viewer)
            ->flatMap(function (User $cashier) use ($service, $filters) {
                try {
                    return $this->fetchOwnRawRows($service, $cashier, $filters)
                        ->map(function ($row) use ($cashier) {
                            $sucursal = $cashier->sucursal;
                            if (!isset($row->origenUsuarioId) || trim((string) ($row->origenUsuarioId ?? '')) === '') {
                                $row->origenUsuarioId = (string) $cashier->id;
                            }
                            if (!isset($row->origenUsuarioNombre) || trim((string) ($row->origenUsuarioNombre ?? '')) === '') {
                                $row->origenUsuarioNombre = (string) $cashier->name;
                            }
                            if (!isset($row->origenUsuarioEmail) || trim((string) ($row->origenUsuarioEmail ?? '')) === '') {
                                $row->origenUsuarioEmail = (string) $cashier->email;
                            }
                            if (!isset($row->origenUsuarioAlias) || trim((string) ($row->origenUsuarioAlias ?? '')) === '') {
                                $row->origenUsuarioAlias = (string) ($cashier->alias ?? '');
                            }
                            if (!isset($row->origenUsuarioCarnet) || trim((string) ($row->origenUsuarioCarnet ?? '')) === '') {
                                $row->origenUsuarioCarnet = (string) ($cashier->ci ?? '');
                            }
                            if (!isset($row->origenUsuarioCiudad) || trim((string) ($row->origenUsuarioCiudad ?? '')) === '') {
                                $row->origenUsuarioCiudad = (string) ($cashier->ciudad ?? '');
                            }
                            if (!isset($row->origenSucursalNombre) || trim((string) ($row->origenSucursalNombre ?? '')) === '') {
                                $row->origenSucursalNombre = (string) ($sucursal->nombre ?? $sucursal->descripcion ?? '');
                            }
                            if (!isset($row->origenSucursalMunicipio) || trim((string) ($row->origenSucursalMunicipio ?? '')) === '') {
                                $row->origenSucursalMunicipio = (string) ($sucursal->municipio ?? '');
                            }
                            if (!isset($row->origenSucursalDepartamento) || trim((string) ($row->origenSucursalDepartamento ?? '')) === '') {
                                $row->origenSucursalDepartamento = (string) ($sucursal->departamento ?? '');
                            }

                            return $row;
                        });
                } catch (\Throwable) {
                    return collect();
                }
            })
            ->sortByDesc(fn ($row) => strtotime((string) data_get($row, 'fecha', '1970-01-01 00:00:00')))
            ->values();
    }

    private function resolveBranchCashiers(User $viewer): Collection
    {
        return User::query()
            ->where('sucursal_id', (int) $viewer->sucursal_id)
            ->orderBy('name')
            ->get()
            ->filter(fn (User $candidate) => $candidate->can('feature.dashboard.facturacion'))
            ->values();
    }

    private function buildBranchCashierSummary(Collection $rows): Collection
    {
        return $rows
            ->groupBy(function ($row) {
                return trim((string) data_get($row, 'origen_usuario_id', data_get($row, 'origen_usuario_email', 'sin-usuario')));
            })
            ->map(function (Collection $cashierRows) {
                $first = $cashierRows->first();

                return [
                    'usuario_id' => trim((string) data_get($first, 'origen_usuario_id', '')),
                    'nombre' => trim((string) data_get($first, 'origen_usuario_nombre', 'Sin usuario')),
                    'email' => trim((string) data_get($first, 'origen_usuario_email', '')),
                    'cantidad_ventas' => $cashierRows->count(),
                    'total_vendido' => round((float) $cashierRows->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
                    'total_caja' => round((float) $cashierRows
                        ->filter(fn ($row) => $this->contabilizaEnCaja($row)
                            && !$this->isQrPaymentRow($row)
                            && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado')
                        ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
                ];
            })
            ->sortByDesc('total_vendido')
            ->values();
    }

    private function resolveSourceUserForRequest(Request $request, User $viewer): User
    {
        $sourceUserId = (int) $request->query('source_user_id', 0);
        if ($sourceUserId <= 0 || ! $this->canAccessBranchVentas($viewer)) {
            return $viewer;
        }

        $sourceUser = User::query()
            ->whereKey($sourceUserId)
            ->where('sucursal_id', (int) $viewer->sucursal_id)
            ->first();

        return $sourceUser ?: $viewer;
    }

    private function summaryFromKardex(array $resumen): array
    {
        $pendientes = (int) ($resumen['pendientes'] ?? 0);
        $qrPendientes = (int) ($resumen['ventasQrPendientes'] ?? 0);
        $qrNoFacturados = (int) ($resumen['ventasQrNoFacturadas'] ?? 0);

        return [
            'totalVentas' => (int) ($resumen['ventas'] ?? 0),
            'totalBorradores' => 0,
            'facturadas' => (int) ($resumen['facturadas'] ?? 0),
            'efectivoCount' => (int) ($resumen['ventasEfectivo'] ?? 0),
            'pendientes' => $pendientes,
            'anuladas' => (int) ($resumen['anuladas'] ?? 0),
            'rechazadas' => (int) ($resumen['observadas'] ?? 0),
            'qrPagados' => (int) ($resumen['ventasQr'] ?? 0),
            'qrPendientes' => $qrPendientes,
            'qrNoFacturados' => $qrNoFacturados,
            'pendientesOperativas' => $pendientes + $qrPendientes + $qrNoFacturados,
            'montoQr' => (float) ($resumen['totalQr'] ?? 0),
            'montoAnulado' => (float) ($resumen['totalAnulado'] ?? 0),
            'montoEfectivo' => (float) ($resumen['totalVendido'] ?? 0),
            'montoTotal' => (float) ($resumen['totalVendido'] ?? 0),
        ];
    }

    private function summaryFromRows(Collection $rows): array
    {
        $effectiveRows = $rows->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === 'emitido'
            && $this->contabilizaEnCaja($row)
            && !$this->isQrPaymentRow($row)
            && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado'
            && strtoupper((string) data_get($row, 'estado_emision', '')) === 'FACTURADA'
            && $this->hasFiscalInvoiceRecord($row));
        $qrPaidRows = $rows->filter(fn ($row) => $this->isQrPaymentRow($row)
            && strtolower((string) data_get($row, 'estado', '')) === 'emitido'
            && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado');
        $pendientes = $rows->filter(fn ($row) => strtoupper((string) data_get($row, 'estado_emision', '')) === 'PENDIENTE')->count();
        $qrPendientes = $rows->filter(fn ($row) => $this->isQrPaymentRow($row)
            && strtolower((string) data_get($row, 'estado', '')) === 'pendiente_pago'
            && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pendiente')->count();
        $qrNoFacturados = $qrPaidRows->filter(fn ($row) => !$this->hasFiscalInvoiceRecord($row))->count();

        return [
            'totalVentas' => $rows->filter(fn ($row) => (string) data_get($row, 'estado', '') === 'emitido')->count(),
            'totalBorradores' => $rows->filter(fn ($row) => (string) data_get($row, 'estado', '') === 'borrador')->count(),
            'facturadas' => $rows->filter(fn ($row) => strtoupper((string) data_get($row, 'estado_emision', '')) === 'FACTURADA'
                && $this->hasFiscalInvoiceRecord($row))->count(),
            'efectivoCount' => $effectiveRows->count(),
            'pendientes' => $pendientes,
            'anuladas' => $rows->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === 'anulado'
                || in_array(strtoupper((string) data_get($row, 'estado_emision', '')), ['ANULADA', 'ANULADO'], true))->count(),
            'rechazadas' => $rows->filter(function ($row) {
                $estadoEmision = strtoupper((string) data_get($row, 'estado_emision', ''));
                $isQrPayment = $this->isQrPaymentRow($row);
                $estadoPago = strtolower((string) data_get($row, 'estado_pago', 'pendiente'));

                return $estadoEmision === 'RECHAZADA'
                    || ($isQrPayment && $estadoPago === 'cancelado');
            })->count(),
            'qrPagados' => $qrPaidRows->count(),
            'qrNoFacturados' => $qrNoFacturados,
            'qrFacturados' => $qrPaidRows->filter(fn ($row) => $this->hasFiscalInvoiceRecord($row))->count(),
            'qrPendientes' => $qrPendientes,
            'pendientesOperativas' => $pendientes + $qrPendientes + $qrNoFacturados,
            'montoQr' => round((float) $qrPaidRows
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
            'montoAnulado' => round((float) $rows
                ->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === 'anulado'
                    || in_array(strtoupper((string) data_get($row, 'estado_emision', '')), ['ANULADA', 'ANULADO'], true))
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
            'montoEfectivo' => round((float) $effectiveRows
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
            'montoTotal' => round((float) $effectiveRows
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
        ];
    }

    private function hasFiscalInvoiceRecord(object|array $row): bool
    {
        $response = (array) data_get($row, 'respuesta_emision', []);
        $numeroFactura = trim((string) (
            data_get($row, 'numero_factura')
            ?: data_get($row, 'numeroFactura')
            ?: data_get($row, 'nroFactura')
            ?: data_get($response, 'factura.nroFactura')
            ?: data_get($response, 'factura.numeroFactura')
            ?: data_get($response, 'nroFactura')
            ?: data_get($response, 'numeroFactura')
            ?: ''
        ));
        $cuf = trim((string) (
            data_get($row, 'cuf')
            ?: data_get($response, 'factura.cuf')
            ?: data_get($response, 'cuf')
            ?: ''
        ));
        $pdfUrl = trim((string) (
            data_get($row, 'pdf_url')
            ?: data_get($row, 'pdfUrl')
            ?: data_get($response, 'factura.pdfUrl')
            ?: data_get($response, 'factura.pdf_url')
            ?: ''
        ));
        $estadoSufe = strtoupper(trim((string) (
            data_get($row, 'estado_sufe')
            ?: data_get($row, 'estadoSufe')
            ?: data_get($response, 'estadoSufe')
            ?: ''
        )));

        return $numeroFactura !== ''
            || $cuf !== ''
            || $pdfUrl !== ''
            || in_array($estadoSufe, ['FACTURADA', 'PROCESADA'], true);
    }

    private function isUnconsolidatedManualRelease(object|array $row): bool
    {
        $mensaje = strtoupper(trim((string) data_get($row, 'mensaje_emision', '')));
        $qrReference = trim((string) (
            data_get($row, 'qr_transaction_id')
            ?: data_get($row, 'codigo_seguimiento')
            ?: data_get($row, 'respuesta_emision.transaction_id')
            ?: ''
        ));

        $marcaNoConsolidada = str_contains($mensaje, 'LIBERACION_MANUAL_FACTURADA')
            || str_contains($mensaje, 'NO_CONSOLIDADA');

        return $marcaNoConsolidada
            && $this->isQrPaymentRow($row)
            && $qrReference === ''
            && !$this->hasFiscalInvoiceRecord($row);
    }

    private function contabilizaEnCaja(object|array $row): bool
    {
        return (bool) data_get($row, 'contabiliza_en_caja', true);
    }

    private function emptySummary(): array
    {
        return [
            'totalVentas' => 0,
            'totalBorradores' => 0,
            'facturadas' => 0,
            'efectivoCount' => 0,
            'pendientes' => 0,
            'anuladas' => 0,
            'rechazadas' => 0,
            'qrPagados' => 0,
            'qrNoFacturados' => 0,
            'qrFacturados' => 0,
            'qrPendientes' => 0,
            'pendientesOperativas' => 0,
            'montoQr' => 0.0,
            'montoAnulado' => 0.0,
            'montoEfectivo' => 0.0,
            'montoTotal' => 0.0,
        ];
    }

    private function applyLocalFilters(Collection $rows, array $filters): Collection
    {
        $filtered = $rows;

        $filtered = $filtered->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) !== 'borrador');

        if (($filters['estado'] ?? 'all') !== 'all') {
            $estado = strtolower((string) $filters['estado']);
            $filtered = $filtered->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === $estado);
        }

        if (($filters['estado_emision'] ?? 'all') !== 'all') {
            $estadoEmision = strtoupper((string) $filters['estado_emision']);
            $filtered = $filtered->filter(fn ($row) => strtoupper((string) data_get($row, 'estado_emision', '')) === $estadoEmision);
        }

        if (!empty($filters['from'])) {
            $fromTs = strtotime((string) $filters['from'] . ' 00:00:00');
            $filtered = $filtered->filter(function ($row) use ($fromTs) {
                $date = data_get($row, 'emitido_en') ?: data_get($row, 'created_at');
                return $date ? strtotime((string) $date) >= $fromTs : false;
            });
        }

        if (!empty($filters['to'])) {
            $toTs = strtotime((string) $filters['to'] . ' 23:59:59');
            $filtered = $filtered->filter(function ($row) use ($toTs) {
                $date = data_get($row, 'emitido_en') ?: data_get($row, 'created_at');
                return $date ? strtotime((string) $date) <= $toTs : false;
            });
        }

        if (($filters['q'] ?? '') !== '') {
            $needle = mb_strtolower((string) $filters['q']);
            $filtered = $filtered->filter(function ($row) use ($needle) {
                $fields = [
                    data_get($row, 'codigo_orden'),
                    data_get($row, 'codigo_seguimiento'),
                    data_get($row, 'codigo_seguimiento_fiscal'),
                    data_get($row, 'qr_transaction_id'),
                    data_get($row, 'numero_documento'),
                    data_get($row, 'razon_social'),
                    data_get($row, 'origen_usuario_nombre'),
                    data_get($row, 'origen_usuario_email'),
                    data_get($row, 'mensaje_emision'),
                ];

                foreach ($fields as $field) {
                    if ($field !== null && str_contains(mb_strtolower((string) $field), $needle)) {
                        return true;
                    }
                }

                return false;
            });
        }

        return $filtered->values();
    }

    private function normalizeKardexRows(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            $ventaId = (int) data_get($row, 'id', 0);
            $origenVentaId = $this->extractOrigenVentaId($row);
            $items = $this->normalizeItems(data_get($row, 'items', data_get($row, 'detalle', [])));

            $codigoOrden = trim((string) (
                data_get($row, 'codigoOrden')
                ?? data_get($row, 'codigo_orden')
                ?? ''
            ));
            $codigoSeguimiento = trim((string) (
                data_get($row, 'codigoSeguimiento')
                ?? data_get($row, 'codigo_seguimiento')
                ?? ''
            ));
            $numeroFactura = '';
            $numeroFacturaFuente = '';
            foreach ([
                'numero_factura' => data_get($row, 'numero_factura'),
                'numeroFactura' => data_get($row, 'numeroFactura'),
                'nroFactura' => data_get($row, 'nroFactura'),
                'respuesta_emision.factura.nroFactura' => data_get($row, 'respuesta_emision.factura.nroFactura'),
                'respuesta_emision.factura.numeroFactura' => data_get($row, 'respuesta_emision.factura.numeroFactura'),
                'respuesta_emision.consultaSefe.nroFactura' => data_get($row, 'respuesta_emision.consultaSefe.nroFactura'),
                'seguimiento.detalle.nroFactura' => data_get($row, 'seguimiento.detalle.nroFactura'),
                'seguimiento.detalle.numeroFactura' => data_get($row, 'seguimiento.detalle.numeroFactura'),
            ] as $source => $candidate) {
                $candidate = trim((string) ($candidate ?? ''));
                if ($candidate !== '' && ! in_array($candidate, ['-', '—'], true)) {
                    $numeroFactura = $candidate;
                    $numeroFacturaFuente = $source;
                    break;
                }
            }
            $estadoEmisionPayload = strtoupper(trim((string) data_get($row, 'estado_emision', '')));
            $estadoSufe = '';
            foreach ([
                data_get($row, 'estadoSufe'),
                data_get($row, 'estado_sufe'),
                data_get($row, 'respuesta_emision.estadoSufe'),
                data_get($row, 'respuesta_emision.estado_sufe'),
                data_get($row, 'respuesta_emision.consultaSefe.estadoSufe'),
                data_get($row, 'respuesta_emision.consultaSefe.estado_sufe'),
                $estadoEmisionPayload,
            ] as $estadoCandidato) {
                $estadoCandidato = strtoupper(trim((string) ($estadoCandidato ?? '')));
                if ($estadoCandidato !== '') {
                    $estadoSufe = $estadoCandidato;
                    break;
                }
            }
            $cuf = trim((string) (
                data_get($row, 'cuf')
                ?? data_get($row, 'respuesta_emision.factura.cuf')
            ));

            $pdfUrl = trim((string) (
                data_get($row, 'pdfUrl')
                ?? data_get($row, 'urlPdf')
                ?? data_get($row, 'respuesta_emision.factura.pdfUrl')
            ));
            $pdfUrl = $this->normalizeSefePublicUrl($pdfUrl);
            if ($pdfUrl === '' && $cuf !== '' && $estadoSufe === 'PROCESADA') {
                $pdfUrl = $this->sefePublicPdfUrl($cuf);
            }

            $createdAt = (string) (
                data_get($row, 'fecha')
                ?? data_get($row, 'emitido_en')
                ?? data_get($row, 'created_at')
                ?? ''
            );
            $isOficial = $this->isOfficialVentaPayload($row);
            $canalEmision = $this->resolveCanalEmisionVentaPayload($row, null, null, $isOficial);
            $metodoPago = $this->resolveMetodoPagoVentaPayload($row, null, null, $canalEmision);
            $estadoCart = '';
            foreach ([
                data_get($row, 'estado'),
                data_get($row, 'estado_venta'),
                data_get($row, 'estadoVenta'),
            ] as $estadoCandidato) {
                $estadoCandidato = strtolower(trim((string) ($estadoCandidato ?? '')));
                if ($estadoCandidato !== '') {
                    $estadoCart = $estadoCandidato;
                    break;
                }
            }
            if ($estadoCart === '') {
                $estadoCart = 'emitido';
            }
            $estadosAnulacion = [
                'ANULADA',
                'ANULADO',
                'ANULACION_SOLICITADA',
                'PENDIENTE_ANULACION',
                'ANULACION_OBSERVADA',
            ];
            $esAnulacionFiscal = in_array($estadoSufe, $estadosAnulacion, true)
                || in_array($estadoEmisionPayload, $estadosAnulacion, true);
            $esVentaAnulada = in_array($estadoSufe, ['ANULADA', 'ANULADO'], true)
                || in_array($estadoEmisionPayload, ['ANULADA', 'ANULADO'], true)
                || in_array($estadoCart, ['anulado', 'descartado'], true);
            $emision = $this->mapEstadoSufeToBridge($estadoSufe);
            if (
                ! $esAnulacionFiscal
                && (
                    in_array($estadoSufe, ['FACTURADA', 'PROCESADA'], true)
                    || ($emision['estado'] === 'SIN ESTADO' && ($numeroFactura !== '' || $pdfUrl !== ''))
                )
            ) {
                $emision = [
                    'estado' => 'FACTURADA',
                    'mensaje' => 'Factura emitida correctamente.',
                ];
            }
            $estadoPago = strtolower(trim((string) data_get($row, 'estado_pago', '')));
            if ($estadoPago === '') {
                $estadoPago = $metodoPago === 'qr' ? 'pendiente' : 'pagado';
            }
            $codigoSeguimientoFiscal = trim((string) data_get($row, 'codigo_seguimiento_fiscal', $codigoSeguimiento));
            $qrTransactionId = trim((string) data_get($row, 'qr_transaction_id', ''));
            $pesoTotal = round((float) (
                data_get($row, 'peso_total')
                ?? data_get($row, 'pesoTotal')
                ?? data_get($row, 'peso')
                ?? 0
            ), 3);
            if ($metodoPago === 'qr') {
                $qrEstadoEmision = strtoupper(trim((string) data_get($row, 'estado_emision', 'NO_APLICA')));
                if ($estadoSufe !== '' && $emision['estado'] !== 'SIN ESTADO') {
                    $qrEstadoEmision = $emision['estado'];
                }

                if (
                    ! $esAnulacionFiscal
                    && (
                        in_array($estadoSufe, ['FACTURADA', 'PROCESADA'], true)
                        || ($emision['estado'] === 'FACTURADA' && ($numeroFactura !== '' || $pdfUrl !== ''))
                    )
                ) {
                    $qrEstadoEmision = 'FACTURADA';
                }
                if ($esVentaAnulada) {
                    $qrEstadoEmision = 'ANULADA';
                }

                $emision = [
                    'estado' => $qrEstadoEmision,
                    'mensaje' => $this->buildQrStatusMessage($estadoPago, trim((string) data_get($row, 'mensaje_emision', ''))),
                ];
            }

            if ($estadoCart === 'descartado') {
                $estadoCart = 'anulado';
            }
            if ($estadoCart === 'anulado' || in_array($emision['estado'], ['ANULADA', 'ANULADO'], true)) {
                $estadoCart = 'anulado';
                $emision = [
                    'estado' => 'ANULADA',
                    'mensaje' => trim((string) data_get($row, 'mensaje_emision', '')) ?: 'Factura anulada correctamente.',
                ];
            }

            $resolvedCartId = $origenVentaId > 0 ? $origenVentaId : $ventaId;

            return (object) [
                'id' => $resolvedCartId,
                'origen_venta_id' => $origenVentaId > 0 ? $origenVentaId : null,
                'venta_id' => $ventaId,
                'origen_usuario_id' => trim((string) data_get($row, 'origenUsuarioId', data_get($row, 'origen_usuario_id', ''))),
                'origen_usuario_nombre' => trim((string) data_get($row, 'origenUsuarioNombre', data_get($row, 'origen_usuario_nombre', ''))),
                'origen_usuario_email' => trim((string) data_get($row, 'origenUsuarioEmail', data_get($row, 'origen_usuario_email', ''))),
                'origen_usuario_ciudad' => trim((string) data_get($row, 'origenUsuarioCiudad', data_get($row, 'origen_usuario_ciudad', ''))),
                'origen_sucursal_nombre' => trim((string) data_get($row, 'origenSucursalNombre', data_get($row, 'origen_sucursal_nombre', ''))),
                'origen_sucursal_codigo' => trim((string) data_get($row, 'origenSucursalCodigo', data_get($row, 'origen_sucursal_codigo', ''))),
                'origen_sucursal_municipio' => trim((string) data_get($row, 'origenSucursalMunicipio', data_get($row, 'origen_sucursal_municipio', ''))),
                'origen_sucursal_departamento' => trim((string) data_get($row, 'origenSucursalDepartamento', data_get($row, 'origen_sucursal_departamento', ''))),
                'created_at' => $createdAt,
                'emitido_en' => $createdAt,
                'codigo_orden' => $codigoOrden,
                'numero_factura' => $numeroFactura,
                'numero_factura_fuente' => $numeroFacturaFuente,
                'numero_documento' => $isOficial ? '' : trim((string) data_get($row, 'documentoIdentidad', data_get($row, 'numero_documento', ''))),
                'razon_social' => trim((string) data_get($row, 'razonSocial', data_get($row, 'razon_social', ''))),
                'modalidad_facturacion' => $isOficial
                    ? 'registro_interno'
                    : (trim((string) data_get($row, 'codigoCliente', data_get($row, 'codigo_cliente', ''))) !== '' ? 'con_datos' : 'sin_cliente'),
                'canal_emision' => $canalEmision,
                'metodo_pago' => $metodoPago,
                'es_oficial' => $isOficial,
                'estado' => $estadoCart,
                'estado_pago' => $estadoPago,
                'estado_emision' => $emision['estado'],
                'mensaje_emision' => $emision['mensaje'],
                'codigo_seguimiento' => $codigoSeguimiento,
                'codigo_seguimiento_fiscal' => $codigoSeguimientoFiscal !== '' ? $codigoSeguimientoFiscal : $codigoSeguimiento,
                'qr_transaction_id' => $qrTransactionId !== '' ? $qrTransactionId : null,
                'total' => (float) data_get($row, 'total', 0),
                'peso_total' => $pesoTotal,
                'items_count' => (int) data_get($row, 'itemsCount', data_get($row, 'cantidadItems', data_get($row, 'cantidad_items', $items->count()))),
                'items' => $items,
                'respuesta_emision' => [
                    'factura' => [
                        'nroFactura' => $numeroFactura,
                        'numeroFactura' => $numeroFactura,
                        'cuf' => $cuf,
                        'pdfUrl' => $pdfUrl,
                    ],
                    'estadoSufe' => $estadoSufe,
                ],
            ];
        })->values();
    }

    private function normalizedVentaRowKey(object $row): string
    {
        $id = (int) data_get($row, 'id', 0);
        if ($id > 0) {
            return 'id:' . $id;
        }

        $codigoOrden = trim((string) data_get($row, 'codigo_orden', ''));
        if ($codigoOrden !== '') {
            return 'codigo:' . $codigoOrden;
        }

        $codigoSeguimiento = trim((string) data_get($row, 'codigo_seguimiento', ''));
        if ($codigoSeguimiento !== '') {
            return 'seguimiento:' . $codigoSeguimiento;
        }

        return md5(json_encode($row));
    }

    private function kardexRowCompletenessScore(object $row): int
    {
        $estadoEmision = strtoupper(trim((string) data_get($row, 'estado_emision', '')));
        $estadoVenta = strtolower(trim((string) data_get($row, 'estado', '')));
        $isAnnulled = in_array($estadoEmision, ['ANULADA', 'ANULADO'], true)
            || in_array($estadoVenta, ['anulado', 'descartado'], true);
        $numeroFactura = trim((string) data_get($row, 'numero_factura', ''));
        $cuf = trim((string) data_get($row, 'respuesta_emision.factura.cuf', data_get($row, 'cuf', '')));
        $pdfUrl = trim((string) data_get($row, 'respuesta_emision.factura.pdfUrl', data_get($row, 'pdf_url', '')));
        $items = data_get($row, 'items', []);
        $itemCount = is_countable($items) ? count($items) : 0;

        return ($isAnnulled ? 1000000000 : 0)
            + ($numeroFactura !== '' && ! in_array($numeroFactura, ['-', '—'], true) ? 100000000 : 0)
            + ($cuf !== '' ? 1000000 : 0)
            + ($pdfUrl !== '' ? 10000 : 0)
            + min(999, $itemCount);
    }

    private function logKardexInvoiceNumberAudit(
        User $user,
        array $filters,
        string $scope,
        Collection $rawRows,
        Collection $normalizedRows,
        Collection $visibleRows,
        callable $dedupeKey
    ): void {
        $facturadas = $visibleRows->filter(function ($row): bool {
            $estadoEmision = strtoupper(trim((string) data_get($row, 'estado_emision', '')));
            $estadoSufe = strtoupper(trim((string) data_get($row, 'respuesta_emision.estadoSufe', '')));
            $estado = strtolower(trim((string) data_get($row, 'estado', '')));

            return $estado !== 'anulado'
                && ! in_array($estadoEmision, ['ANULADA', 'ANULADO'], true)
                && ($estadoEmision === 'FACTURADA'
                    || in_array($estadoSufe, ['FACTURADA', 'PROCESADA'], true));
        });
        $missingInvoice = $facturadas->filter(fn ($row): bool => trim((string) data_get($row, 'numero_factura', '')) === '');
        $duplicateGroupsWithNumber = $normalizedRows
            ->groupBy($dedupeKey)
            ->filter(function (Collection $group): bool {
                return $group->count() > 1
                    && $group->contains(fn ($row): bool => trim((string) data_get($row, 'numero_factura', '')) !== '');
            })
            ->count();
        $numberSources = $normalizedRows
            ->filter(fn ($row): bool => trim((string) data_get($row, 'numero_factura', '')) !== '')
            ->countBy(fn ($row): string => (string) data_get($row, 'numero_factura_fuente', 'desconocida'))
            ->all();
        $examples = $missingInvoice->take(10)->map(fn ($row): array => [
            'venta_id' => data_get($row, 'venta_id') ?: data_get($row, 'id'),
            'cart_id' => data_get($row, 'origen_venta_id') ?: data_get($row, 'id'),
            'codigo_orden' => data_get($row, 'codigo_orden'),
            'codigo_seguimiento_final' => substr(trim((string) data_get($row, 'codigo_seguimiento', '')), -5),
            'estado_emision' => data_get($row, 'estado_emision'),
            'estado_sufe' => data_get($row, 'respuesta_emision.estadoSufe'),
            'tiene_cuf' => trim((string) data_get($row, 'respuesta_emision.factura.cuf', '')) !== '',
            'tiene_pdf' => trim((string) data_get($row, 'respuesta_emision.factura.pdfUrl', '')) !== '',
        ])->values()->all();

        $context = [
            'scope' => $scope,
            'user_id' => (int) $user->id,
            'fecha_desde' => $filters['from'] ?? null,
            'fecha_hasta' => $filters['to'] ?? null,
            'filas_api' => $rawRows->count(),
            'filas_normalizadas' => $normalizedRows->count(),
            'filas_visibles' => $visibleRows->count(),
            'filas_facturadas' => $facturadas->count(),
            'filas_facturadas_con_numero' => $facturadas->count() - $missingInvoice->count(),
            'filas_facturadas_sin_numero' => $missingInvoice->count(),
            'grupos_duplicados_con_numero' => $duplicateGroupsWithNumber,
            'fuentes_numero_factura' => $numberSources,
            'ejemplos_sin_numero' => $examples,
        ];

        Log::debug('mis-ventas.kardex.invoice-number-audit', $context);

        if ($missingInvoice->isNotEmpty()) {
            Log::warning('mis-ventas.kardex.facturada-sin-numero-factura', $context);
        }
    }

    private function extractOrigenVentaId(object|array|null $payload): int
    {
        if ($payload === null) {
            return 0;
        }

        $candidates = [
            data_get($payload, 'origenVentaId'),
            data_get($payload, 'origen_venta_id'),
            data_get($payload, 'origenVenta.id'),
            data_get($payload, 'origen_venta.id'),
            data_get($payload, 'venta.origenVentaId'),
            data_get($payload, 'venta.origen_venta_id'),
            data_get($payload, 'venta.origenVenta.id'),
            data_get($payload, 'venta.origen_venta.id'),
        ];

        foreach ($candidates as $candidate) {
            $value = (int) $candidate;
            if ($value > 0) {
                return $value;
            }
        }

        return 0;
    }

    private function resolveVentaMergeCartId(object|array $row): int
    {
        $origenVentaId = $this->extractOrigenVentaId($row);
        if ($origenVentaId > 0) {
            return $origenVentaId;
        }

        return (int) data_get($row, 'id', 0);
    }

    private function resolveVentaForDetail(FacturacionCartService $service, object $user, int $cart): ?object
    {
        $venta = $service->fetchVentaById($user, $cart);
        if (!$venta) {
            return null;
        }

        $cartItems = $this->normalizeItems(data_get($venta, 'items', []));
        if ($cartItems->isNotEmpty()) {
            return $venta;
        }

        $ventaId = (int) data_get($venta, 'venta_id', 0);
        if ($ventaId <= 0) {
            $ventaId = $this->extractOrigenVentaId($venta);
        }
        if ($ventaId <= 0) {
            $ventaId = $cart;
        }

        $ventaDetalle = $service->fetchVentaDetalleByVentaId($user, $ventaId);
        if ($ventaDetalle) {
            return $this->mapVentaDetailToCart($ventaDetalle);
        }

        return $venta;
    }

    private function mapVentaDetailToCart(object $venta): object
    {
        $detalle = collect((array) data_get($venta, 'detalle', []))
            ->map(fn ($item) => $this->mapVentaDetalleItemToCartItem($item))
            ->filter()
            ->values();

        $codigoOrden = trim((string) data_get($venta, 'codigoOrden', data_get($venta, 'codigo_orden', '')));
        $numeroFactura = $this->extractNumeroFacturaFromVentaDetail($venta);
        $cuf = trim((string) (
            data_get($venta, 'seguimiento.cuf')
            ?? data_get($venta, 'cuf')
        ));
        $pdfUrl = trim((string) data_get($venta, 'seguimiento.urlPdf', ''));
        $pdfUrl = $this->normalizeSefePublicUrl($pdfUrl);
        if ($pdfUrl === '' && $cuf !== '') {
            $pdfUrl = $this->sefePublicPdfUrl($cuf);
        }

        $isOficial = $this->isOfficialVentaPayload($venta);
        $canalEmision = $this->resolveCanalEmisionVentaPayload($venta, null, $venta, $isOficial);
        $metodoPago = $this->resolveMetodoPagoVentaPayload($venta, null, $venta, $canalEmision);

        return (object) [
            'id' => (int) data_get($venta, 'id', 0),
            'codigo_orden' => $codigoOrden,
            'numero_documento' => $isOficial
                ? ''
                : trim((string) data_get($venta, 'cliente.documentoIdentidad', data_get($venta, 'documentoIdentidad', ''))),
            'razon_social' => trim((string) data_get($venta, 'cliente.razonSocial', data_get($venta, 'razonSocial', ''))),
            'emitido_en' => (string) data_get($venta, 'created_at', data_get($venta, 'fecha', '')),
            'total' => (float) data_get($venta, 'montoTotal', data_get($venta, 'total', 0)),
            'modalidad_facturacion' => $isOficial ? 'registro_interno' : 'con_datos',
            'canal_emision' => $canalEmision,
            'metodo_pago' => $metodoPago,
            'canal_operativo' => (string) data_get($venta, 'canal_operativo', 'normal'),
            'contabiliza_en_caja' => (bool) data_get($venta, 'contabiliza_en_caja', true),
            'es_cuenta_por_cobrar' => (bool) data_get($venta, 'es_cuenta_por_cobrar', false),
            'empresa_nombre' => trim((string) data_get($venta, 'empresa_nombre', '')),
            'empresa_codigo_cliente' => trim((string) data_get($venta, 'empresa_codigo_cliente', '')),
            'es_oficial' => $isOficial,
            'items' => $detalle,
            'respuesta_emision' => [
                'factura' => [
                    'nroFactura' => $numeroFactura,
                    'numeroFactura' => $numeroFactura,
                    'cuf' => $cuf,
                    'pdfUrl' => $pdfUrl,
                ],
            ],
        ];
    }

    private function extractNumeroFacturaFromVentaDetail(object $venta): string
    {
        return trim((string) (
            data_get($venta, 'numero_factura')
            ?? data_get($venta, 'numeroFactura')
            ?? data_get($venta, 'seguimiento.detalle.nroFactura')
            ?? data_get($venta, 'seguimiento.detalle.numeroFactura')
        ));
    }

    private function mapVentaDetalleItemToCartItem(mixed $item): ?object
    {
        if (is_object($item)) {
            $item = (array) $item;
        }
        if (!is_array($item)) {
            return null;
        }

        $cantidad = (int) max(1, (float) ($item['cantidad'] ?? 1));
        $base = (float) ($item['monto_base'] ?? $item['precio'] ?? 0);
        $extras = (float) ($item['monto_extras'] ?? 0);
        $totalLinea = (float) ($item['total_linea'] ?? (($base + $extras) * $cantidad));

        return (object) [
            'id' => (int) ($item['id'] ?? 0),
            'codigo' => (string) ($item['codigo'] ?? ''),
            'codigo_paquete' => (string) ($item['codigo_paquete'] ?? data_get($item, 'resumen_origen.codigo_paquete', '')),
            'codigo_item' => (string) ($item['codigo_item'] ?? data_get($item, 'resumen_origen.codigo_item', '')),
            'codigo_detalle_enviado' => (string) ($item['codigo_detalle_enviado'] ?? data_get($item, 'resumen_origen.codigo_detalle_enviado', '')),
            'origen_tipo' => (string) ($item['origen_tipo'] ?? ''),
            'titulo' => (string) ($item['titulo'] ?? $item['descripcion'] ?? 'Sin detalle'),
            'nombre_servicio' => (string) ($item['descripcion'] ?? $item['titulo'] ?? ''),
            'nombre_destinatario' => (string) ($item['subtitulo'] ?? ''),
            'resumen_origen' => (array) ($item['resumen_origen'] ?? []),
            'cantidad' => $cantidad,
            'monto_base' => round($base, 2),
            'monto_extras' => round($extras, 2),
            'total_linea' => round($totalLinea, 2),
        ];
    }

    private function mapEstadoSufeToBridge(string $estadoSufe): array
    {
        return match ($estadoSufe) {
            'FACTURADA' => ['estado' => 'FACTURADA', 'mensaje' => 'Factura emitida correctamente.'],
            'PROCESADA' => ['estado' => 'FACTURADA', 'mensaje' => 'Factura emitida correctamente.'],
            'ANULADA', 'ANULADO' => ['estado' => 'ANULADA', 'mensaje' => 'Factura anulada correctamente.'],
            'ANULACION_SOLICITADA', 'PENDIENTE_ANULACION' => ['estado' => 'PENDIENTE_ANULACION', 'mensaje' => 'La anulacion fue enviada y espera confirmacion final.'],
            'ANULACION_OBSERVADA' => ['estado' => 'ANULACION_OBSERVADA', 'mensaje' => 'La anulacion fue observada y requiere revision.'],
            'PENDIENTE' => ['estado' => 'PENDIENTE', 'mensaje' => 'Emision en proceso de confirmacion.'],
            'RECEPCIONADA', 'CONTINGENCIA_CREADA' => ['estado' => 'PENDIENTE', 'mensaje' => 'Emision en proceso de confirmacion.'],
            'REGISTRADA_OFICIAL' => ['estado' => 'SIN ESTADO', 'mensaje' => 'Registro oficial almacenado sin emision electronica.'],
            'RECHAZADA' => ['estado' => 'RECHAZADA', 'mensaje' => 'Requiere revision antes de reenviar.'],
            'OBSERVADA' => ['estado' => 'RECHAZADA', 'mensaje' => 'Requiere revision antes de reenviar.'],
            'ERROR' => ['estado' => 'ERROR', 'mensaje' => 'Se registro un error en la emision.'],
            default => ['estado' => 'SIN ESTADO', 'mensaje' => 'Sin observaciones registradas.'],
        };
    }

    private function buildQrStatusMessage(string $estadoPago, string $fallback = ''): string
    {
        $estadoPago = strtolower(trim($estadoPago));
        $fallbackNormalized = strtolower(trim($fallback));

        if ($fallback !== '' && (
            str_contains($fallbackNormalized, 'factura')
            || str_contains($fallbackNormalized, 'sefe')
            || str_contains($fallbackNormalized, 'fiscal')
        )) {
            return $fallback;
        }

        return match ($estadoPago) {
            'pagado' => $fallback !== '' ? $fallback : 'Pago QR confirmado. La factura electronica se emitira automaticamente.',
            'cancelado' => $fallback !== '' ? $fallback : 'Pago QR cancelado o rechazado.',
            default => $fallback !== '' ? $fallback : 'QR generado. Si el cliente no completo el pago, la venta queda pendiente hasta actualizar su estado.',
        };
    }

    private function isOfficialVentaPayload(object|array $venta): bool
    {
        $codigoOrden = strtoupper(trim((string) data_get($venta, 'codigoOrden', data_get($venta, 'codigo_orden', ''))));
        $razonSocial = strtoupper(trim((string) data_get($venta, 'cliente.razonSocial', data_get($venta, 'razonSocial', ''))));
        $estadoSufe = strtoupper(trim((string) data_get($venta, 'estadoSufe', data_get($venta, 'estado_sufe', ''))));
        $tipoOrigen = strtoupper(trim((string) data_get($venta, 'origenVenta.tipo', '')));

        return str_starts_with($codigoOrden, 'OFI-')
            || $razonSocial === 'ENVIO OFICIAL'
            || $estadoSufe === 'REGISTRADA_OFICIAL'
            || $tipoOrigen === 'OFICIAL';
    }

    private function resolveCanalEmisionVentaPayload(
        object|array $venta,
        object|array|null $bridgeCart = null,
        object|array|null $ventaDetalle = null,
        ?bool $isOficial = null
    ): string {
        if ($isOficial ?? $this->isOfficialVentaPayload($venta)) {
            return 'oficial';
        }

        $candidates = [
            data_get($venta, 'canal_emision'),
            data_get($venta, 'canalEmision'),
            data_get($bridgeCart, 'canal_emision'),
            data_get($bridgeCart, 'canalEmision'),
            data_get($ventaDetalle, 'canal_emision'),
            data_get($ventaDetalle, 'canalEmision'),
        ];

        foreach ($candidates as $candidate) {
            $normalized = strtolower(trim((string) $candidate));
            if (in_array($normalized, ['factura_electronica', 'qr'], true)) {
                return $normalized;
            }
        }

        $codigoOrden = strtoupper(trim((string) (
            data_get($venta, 'codigoOrden')
            ?? data_get($venta, 'codigo_orden')
            ?? data_get($bridgeCart, 'codigo_orden')
            ?? data_get($ventaDetalle, 'codigoOrden')
            ?? ''
        )));
        if ($this->hasQrOrderCodePrefix($codigoOrden)) {
            return 'qr';
        }

        $paymentHints = [
            data_get($venta, 'metodo_pago'),
            data_get($venta, 'metodoPago'),
            data_get($bridgeCart, 'metodo_pago'),
            data_get($bridgeCart, 'metodoPago'),
            data_get($ventaDetalle, 'metodo_pago'),
            data_get($ventaDetalle, 'metodoPago'),
        ];

        foreach ($paymentHints as $hint) {
            if (strtolower(trim((string) $hint)) === 'qr') {
                return 'qr';
            }
        }

        return 'factura_electronica';
    }

    private function resolveMetodoPagoVentaPayload(
        object|array $venta,
        object|array|null $bridgeCart = null,
        object|array|null $ventaDetalle = null,
        ?string $canalEmision = null
    ): string {
        $paymentHints = [
            data_get($venta, 'metodo_pago'),
            data_get($venta, 'metodoPago'),
            data_get($bridgeCart, 'metodo_pago'),
            data_get($bridgeCart, 'metodoPago'),
            data_get($ventaDetalle, 'metodo_pago'),
            data_get($ventaDetalle, 'metodoPago'),
        ];

        foreach ($paymentHints as $hint) {
            $normalized = strtolower(trim((string) $hint));
            if (in_array($normalized, ['efectivo', 'qr'], true)) {
                return $normalized;
            }
        }

        $qrTransactionId = trim((string) (
            data_get($venta, 'qr_transaction_id')
            ?? data_get($bridgeCart, 'qr_transaction_id')
            ?? data_get($ventaDetalle, 'qr_transaction_id')
            ?? ''
        ));
        if ($qrTransactionId !== '') {
            return 'qr';
        }

        return strtolower(trim((string) ($canalEmision ?? 'factura_electronica'))) === 'qr' ? 'qr' : 'efectivo';
    }

    private function isQrPaymentRow(object|array $row): bool
    {
        $codigoOrden = strtoupper(trim((string) data_get($row, 'codigo_orden', data_get($row, 'codigoOrden', ''))));

        return strtolower(trim((string) data_get($row, 'metodo_pago', ''))) === 'qr'
            || trim((string) data_get($row, 'qr_transaction_id', '')) !== ''
            || strtolower(trim((string) data_get($row, 'canal_emision', ''))) === 'qr'
            || $this->hasQrOrderCodePrefix($codigoOrden);
    }

    private function hasQrOrderCodePrefix(string $codigoOrden): bool
    {
        $codigoOrden = strtoupper(trim($codigoOrden));

        return str_starts_with($codigoOrden, 'VQ-')
            || str_starts_with($codigoOrden, 'VQC-');
    }

    private function labelCanalEmision(string $canalEmision): string
    {
        return match (strtolower(trim($canalEmision))) {
            'qr' => 'QR',
            'oficial' => 'Envio oficial',
            default => 'Factura electronica',
        };
    }

    private function resolvePdfSectionKey(object|array $cart): string
    {
        $estadoCart = strtolower(trim((string) data_get($cart, 'estado', '')));
        $estadoEmision = strtoupper(trim((string) data_get($cart, 'estado_emision', 'NO_APLICA')));

        if ($estadoCart === 'anulado' || in_array($estadoEmision, ['ANULADA', 'ANULADO'], true)) {
            return 'factura_anulada';
        }

        if ($this->isQrPaymentRow($cart)) {
            $estadoPago = strtolower(trim((string) data_get($cart, 'estado_pago', 'pendiente')));

            if ($estadoPago === 'cancelado') {
                return 'qr_cancelado';
            }

            if ($estadoPago !== 'pagado') {
                return 'qr_pendiente';
            }

            if ($estadoEmision === 'FACTURADA' && $this->hasFiscalInvoiceRecord($cart)) {
                return 'qr_facturado';
            }

            return 'qr_pagado_pendiente_factura';
        }

        return strtolower(trim((string) data_get($cart, 'canal_emision', 'factura_electronica')));
    }

    private function resolvePdfEstadoColumn(string $sectionKey, string $estadoEmision, string $estadoPago, string $mensajeEmision): array
    {
        $normalizedMessage = trim($mensajeEmision);

        return match ($sectionKey) {
            'factura_anulada' => [
                'label' => 'VENTA ANULADA',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Venta anulada localmente y excluida de caja.',
            ],
            'qr_facturado' => [
                'label' => 'FACTURADA',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Cobro QR convertido en factura electronica.',
            ],
            'qr_pagado_pendiente_factura' => [
                'label' => 'QR PAGADO',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Cobro QR confirmado. Factura pendiente o en otro estado.',
            ],
            'qr_cancelado' => [
                'label' => 'QR ANULADO',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'El intento de cobro QR no se concreto.',
            ],
            'qr_pendiente' => [
                'label' => 'QR PENDIENTE',
                'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Pendiente de pago QR.',
            ],
            default => match ($estadoEmision) {
                'FACTURADA' => [
                    'label' => 'EMITIDO',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Factura emitida correctamente.',
                ],
                'PENDIENTE' => [
                    'label' => 'PENDIENTE',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Emision en proceso de confirmacion.',
                ],
                'RECHAZADA' => [
                    'label' => 'RECHAZADA',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'La factura requiere revision.',
                ],
                'PENDIENTE_ANULACION' => [
                    'label' => 'PENDIENTE ANULACION',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'La anulacion fue enviada y espera confirmacion final.',
                ],
                'ANULACION_OBSERVADA' => [
                    'label' => 'ANULACION OBSERVADA',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'La anulacion fue observada y requiere revision.',
                ],
                'ERROR' => [
                    'label' => 'ERROR',
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Se registro un error en la emision.',
                ],
                default => [
                    'label' => strtoupper($estadoPago === 'cancelado' ? 'ANULADO' : ($estadoEmision !== '' ? $estadoEmision : 'SIN ESTADO')),
                    'detalle' => $normalizedMessage !== '' ? $normalizedMessage : 'Sin observaciones registradas.',
                ],
            },
        };
    }

    private function buildPdfRows(Collection $carts, bool $includeKardexSourceItems = false): Collection
    {
        return $carts->values()->map(function ($cart, int $cartIndex) use ($includeKardexSourceItems) {
            $cart = is_array($cart) ? (object) $cart : $cart;
            $items = $this->normalizeItems(data_get($cart, 'items', []));

            $respuesta = (array) data_get($cart, 'respuesta_emision', []);
            $numeroFactura = trim((string) (
                data_get($cart, 'numero_factura')
                ?: data_get($respuesta, 'factura.nroFactura')
                ?: data_get($respuesta, 'factura.numeroFactura')
                ?: data_get($respuesta, 'consultaSefe.nroFactura')
                ?: ''
            ));
            $cuf = trim((string) (
                data_get($cart, 'cuf')
                ?: data_get($respuesta, 'factura.cuf')
                ?: data_get($respuesta, 'cuf')
                ?: ''
            ));
            $ventaId = (int) data_get($cart, 'id', data_get($cart, 'venta_id', 0));
            $codigoOrdenOriginal = trim((string) data_get($cart, 'codigo_orden', data_get($cart, 'codigoOrden', '')));
            $pdfUrl = trim((string) (
                data_get($respuesta, 'factura.pdfUrl')
                ?? data_get($respuesta, 'factura.pdf_url')
                ?? data_get($cart, 'pdf_url')
                ?? data_get($cart, 'pdfUrl')
                ?? ''
            ));
            $pdfUrl = $this->normalizeSefePublicUrl($pdfUrl);
            if ($pdfUrl === '' && $cuf !== '') {
                $pdfUrl = $this->sefePublicPdfUrl($cuf);
            }
            $saleGroupKey = $ventaId > 0
                ? 'venta:' . $ventaId
                : ($codigoOrdenOriginal !== ''
                    ? 'codigo:' . mb_strtoupper($codigoOrdenOriginal)
                    : ($numeroFactura !== '' ? 'factura:' . $numeroFactura : 'cart:' . $cartIndex));
            $fecha = $cart->emitido_en ?? $cart->created_at;
            $canalEmision = strtolower(trim((string) data_get($cart, 'canal_emision', 'factura_electronica')));
            $metodoPago = strtolower(trim((string) data_get($cart, 'metodo_pago', $canalEmision === 'qr' ? 'qr' : 'efectivo')));
            $estadoPago = strtolower(trim((string) data_get($cart, 'estado_pago', 'pendiente')));
            $estadoEmision = strtoupper(trim((string) data_get($cart, 'estado_emision', '')));
            $mensajeEmision = trim((string) data_get($cart, 'mensaje_emision', ''));
            $esCuentaPorCobrar = (bool) data_get($cart, 'es_cuenta_por_cobrar', false);
            $empresaContrato = trim((string) data_get($cart, 'empresa_nombre', ''));
            $contabilizaEnCaja = (bool) data_get($cart, 'contabiliza_en_caja', $metodoPago !== 'qr');
            $sectionKey = $this->resolvePdfSectionKey($cart);
            $estadoColumn = $this->resolvePdfEstadoColumn($sectionKey, $estadoEmision, $estadoPago, $mensajeEmision);
            $isAnnulled = $sectionKey === 'factura_anulada';
            $contabilizaEnCaja = $contabilizaEnCaja && ! $isAnnulled;
            if ($esCuentaPorCobrar) {
                $estadoColumn = [
                    'label' => 'FACTURADA',
                    'detalle' => 'Cuenta por cobrar'
                        . ($empresaContrato !== '' ? ' a ' . $empresaContrato : '')
                        . '. No suma a caja.',
                ];
            }

            $emisionLabel = match ($sectionKey) {
                'qr_facturado' => 'QR pagado + facturado',
                'qr_pagado_pendiente_factura' => 'QR pagado',
                'qr_cancelado' => 'QR cancelado',
                'qr_pendiente' => 'QR pendiente',
                default => $this->labelCanalEmision($canalEmision),
            };

            $tipoEnvio = $items
                ->map(fn ($item) => $this->resolveKardexItemServiceName($item))
                ->filter()
                ->unique()
                ->implode(' / ');
            $detalleItems = $items
                ->map(fn ($item) => trim((string) data_get($item, 'titulo', data_get($item, 'nombre_servicio', ''))))
                ->filter()
                ->unique()
                ->implode(' / ');
            $codigoOrden = $codigoOrdenOriginal !== ''
                ? $codigoOrdenOriginal
                : (trim((string) data_get($cart, 'codigo_seguimiento', '')) ?: '-');
            $detalleCodigos = $this->buildDetailCodeEntriesFromItems($items);
            $codigosPaquete = $detalleCodigos
                ->pluck('codigo')
                ->filter()
                ->values();
            $pesoTotal = (float) $items->sum(fn ($item) => (float) data_get($item, 'resumen_origen.peso', 0));
            if ($pesoTotal <= 0) {
                $pesoTotal = round((float) (
                    data_get($cart, 'peso_total')
                    ?? data_get($cart, 'pesoTotal')
                    ?? data_get($cart, 'peso')
                    ?? 0
                ), 3);
            }
            $cantidadTotal = (int) data_get($cart, 'items_count', 0);
            if ($cantidadTotal <= 0) {
                $cantidadTotal = max(1, $items->count());
            }
            $packageItemsCount = $items
                ->filter(fn ($item) => $this->isPackageCartItem($item))
                ->count();
            $serviceItemsCount = max(0, $cantidadTotal - $packageItemsCount);
            $detalleResumen = collect([
                $packageItemsCount > 0 ? $packageItemsCount . ' paquete' . ($packageItemsCount === 1 ? '' : 's') : null,
                $serviceItemsCount > 0 ? $serviceItemsCount . ' servicio' . ($serviceItemsCount === 1 ? '' : 's') : null,
            ])->filter()->implode(' + ');
            $clienteLabel = trim((string) data_get($cart, 'razon_social', ''));
            if ($clienteLabel === '') {
                $clienteLabel = (bool) data_get($cart, 'es_oficial', false) ? 'ENVIO OFICIAL' : 'Sin cliente';
            }

            $row = [
                'venta_id' => $ventaId,
                'venta_group_key' => $saleGroupKey,
                'venta_referencia' => $codigoOrden !== '-' ? $codigoOrden : 'VENTA-' . ($ventaId > 0 ? $ventaId : ($cartIndex + 1)),
                'numero_documento' => trim((string) data_get($cart, 'numero_documento', data_get($cart, 'documentoIdentidad', ''))),
                'origen_usuario_id' => trim((string) data_get($cart, 'origen_usuario_id', '')),
                'origen_usuario_nombre' => trim((string) data_get($cart, 'origen_usuario_nombre', '')),
                'origen_usuario_email' => trim((string) data_get($cart, 'origen_usuario_email', '')),
                'origen_usuario_ciudad' => trim((string) data_get($cart, 'origen_usuario_ciudad', '')),
                'origen_sucursal_nombre' => trim((string) data_get($cart, 'origen_sucursal_nombre', '')),
                'origen_sucursal_codigo' => trim((string) data_get($cart, 'origen_sucursal_codigo', '')),
                'origen_sucursal_municipio' => trim((string) data_get($cart, 'origen_sucursal_municipio', '')),
                'origen_sucursal_departamento' => trim((string) data_get($cart, 'origen_sucursal_departamento', '')),
                'fecha' => $fecha ? date('d/m/Y', strtotime((string) $fecha)) : '-',
                'fecha_hora' => $fecha ? date('d/m/Y H:i', strtotime((string) $fecha)) : '-',
                'fecha_sort' => $fecha ? strtotime((string) $fecha) : 0,
                'cliente' => $clienteLabel,
                'estado_venta' => strtolower(trim((string) data_get($cart, 'estado', ''))),
                'codigo_seguimiento' => trim((string) data_get($cart, 'codigo_seguimiento', '')),
                'codigo_seguimiento_fiscal' => trim((string) data_get($cart, 'codigo_seguimiento_fiscal', data_get($cart, 'codigo_seguimiento', ''))),
                'qr_transaction_id' => trim((string) data_get($cart, 'qr_transaction_id', '')),
                'mensaje_emision' => trim((string) data_get($cart, 'mensaje_emision', '')),
                'tipo_envio' => $tipoEnvio !== '' ? $tipoEnvio : 'SIN DETALLE REAL',
                'detalle_items' => $detalleItems !== '' ? $detalleItems : 'Sin detalle real',
                'detalle_resumen' => $detalleResumen,
                'detalle_codigos' => $detalleCodigos,
                'codigo_item' => $codigoOrden,
                'codigo_paquetes' => $codigosPaquete,
                'codigo_paquetes_texto' => $codigosPaquete->implode(', '),
                'codigo_referencia' => $codigosPaquete->isNotEmpty()
                    ? $codigosPaquete->implode(', ')
                    : $codigoOrden,
                'peso' => $pesoTotal,
                'cantidad' => $cantidadTotal,
                'canal_emision' => $canalEmision,
                'metodo_pago' => $metodoPago,
                'estado_pago' => $estadoPago,
                'estado_emision' => $estadoEmision,
                'section_key' => $sectionKey,
                'emision_label' => $emisionLabel,
                'estado_label' => $estadoColumn['label'],
                'estado_detalle' => $estadoColumn['detalle'],
                'contabiliza_en_caja' => $contabilizaEnCaja,
                'es_cuenta_por_cobrar' => $esCuentaPorCobrar,
                'empresa_nombre' => $empresaContrato,
                'cobrada' => ! $isAnnulled && in_array($sectionKey, ['factura_electronica', 'qr_facturado', 'qr_pagado_pendiente_factura', 'oficial'], true),
                'numero_factura' => $numeroFactura !== '' ? $numeroFactura : '-',
                'pdf_url' => $pdfUrl,
                'cuf' => $cuf,
                'importe_parcial' => round((float) data_get($cart, 'total', 0), 2),
                'importe_general' => round((float) data_get($cart, 'total', 0), 2),
                'importe_venta' => round((float) data_get($cart, 'total', 0), 2),
            ];

            if ($includeKardexSourceItems) {
                $row['kardex_source_items'] = $items;
            }

            return $row;
        })->values();
    }

    private function buildKardexViewRows(Collection $carts, SitraIpsClient $ipsClient, SitraTrackingDestinationClient $destinationClient, SitraTrackingEventsClient $eventsClient, bool $fastRegionalMode = false): Collection
    {
        $pdfRows = $this->buildPdfRows($carts, true);
        $packageCodes = $pdfRows
            ->flatMap(fn (array $row) => collect($row['detalle_codigos'] ?? [])
                ->filter(fn (array $entry): bool => $this->isKardexPackageEntry($entry))
                ->pluck('codigo'))
            ->map(fn ($code) => $this->normalizeKardexPackageLookupCode((string) $code))
            ->filter()
            ->unique()
            ->values();
        $localPackageServices = $this->lookupLocalKardexPackageServices($pdfRows);
        $ipsServiceCodes = $pdfRows
            ->flatMap(fn (array $row) => collect($row['detalle_codigos'] ?? [])
                ->filter(function (array $entry) use ($localPackageServices): bool {
                    $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                    if ($code === '') {
                        return false;
                    }

                    $source = $entry['source'] ?? null;
                    $origin = is_object($source)
                        ? ltrim(trim((string) data_get($source, 'origen_tipo', '')), '\\')
                        : '';
                    $localOrigin = (string) ($localPackageServices['ORIGIN#'.$code] ?? '');
                    $isDomesticEms = $origin === ltrim(PaqueteEms::class, '\\')
                        || $localOrigin === ltrim(PaqueteEms::class, '\\');
                    if ($isDomesticEms) {
                        return false;
                    }

                    $service = trim((string) ($entry['servicio'] ?? ''));

                    return $origin === ltrim(PaqueteInt::class, '\\')
                        || $this->isKardexInternationalService($service)
                        || $this->isKardexUpuTrackingCode($code);
                })
                ->map(fn (array $entry) => $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''))))
            ->filter()
            ->unique()
            ->values();
        // Both Kardex views use IPS's mail class for international items.
        // Domestic EMS keeps the locally billed tariff; only international
        // codes are looked up here, and the client caches those results.
        $ipsServices = $ipsClient->packageServices($ipsServiceCodes->all());
        $pdfRows = $pdfRows->map(function (array $row) use ($ipsServices, $localPackageServices): array {
            $row['detalle_codigos'] = collect($row['detalle_codigos'] ?? [])
                ->map(function (array $entry) use ($ipsServices, $localPackageServices, $row): array {
                    $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                    $source = $entry['source'] ?? null;
                    $origin = is_object($source) ? ltrim(trim((string) data_get($source, 'origen_tipo', '')), '\\') : '';
                    $originId = is_object($source) ? (int) data_get($source, 'origen_id', 0) : 0;
                    $localKey = $origin !== '' && $originId > 0 ? $origin.'#'.$originId : '';
                    $codeKey = $code !== '' ? 'CODE#'.$code : '';
                    $localCodeOrigin = $codeKey !== '' ? (string) ($localPackageServices['ORIGIN#'.$code] ?? '') : '';
                    $localService = ($codeKey !== '' ? ($localPackageServices[$codeKey] ?? null) : null)
                        ?: ($localKey !== '' ? ($localPackageServices[$localKey] ?? null) : null);
                    $isLocalPackage = $localCodeOrigin !== ''
                        || in_array($origin, [
                            ltrim(PaqueteEms::class, '\\'),
                            ltrim(PaqueteInt::class, '\\'),
                            ltrim(PaqueteOrdi::class, '\\'),
                            ltrim(PaqueteCerti::class, '\\'),
                        ], true)
                        || $this->isKardexPackageEntry($entry);
                    $sourceService = trim((string) ($entry['servicio'] ?? ''));
                    $isDomesticEmsPackage = $origin === ltrim(PaqueteEms::class, '\\')
                        || $localCodeOrigin === ltrim(PaqueteEms::class, '\\');
                    $isInternational = ! $isDomesticEmsPackage && (
                        $origin === ltrim(PaqueteInt::class, '\\')
                        || $this->isKardexInternationalService($sourceService)
                        || $this->isKardexUpuTrackingCode($code)
                    );
                    if (! $isLocalPackage && ! $isInternational) {
                        return $entry;
                    }

                    $saleService = trim((string) ($row['tipo_envio'] ?? ''));

                    $entry['servicio'] = $isDomesticEmsPackage
                        ? ($localService ?: ($saleService !== '' ? $saleService : null) ?: ($ipsServices[$code] ?? null) ?: ($sourceService !== '' ? $sourceService : null))
                        : (($isInternational ? ($ipsServices[$code] ?? null) : null) ?: $localService ?: ($sourceService !== '' ? $sourceService : null) ?: ($saleService !== '' ? $saleService : null));
                    $entry['servicio'] = $entry['servicio'] ?: 'NO VERIFICADO EN IPS';

                    return $entry;
                });

            return $row;
        });

        $destinationEntries = $pdfRows->flatMap(function (array $row): Collection {
            $detalleCodigos = collect($row['detalle_codigos'] ?? []);
            $esCasilla = $this->isKardexCasillaService(
                (string) ($row['tipo_envio'] ?? ''),
                $detalleCodigos->pluck('codigo')->filter()->implode(', ')
            );
            if ($esCasilla) {
                return collect();
            }

            return $detalleCodigos->map(function (array $entry) use ($row): ?array {
                $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                if ($code === '') {
                    return null;
                }

                return [
                    'codigo' => $code,
                    'servicio' => trim((string) ($entry['servicio'] ?? '')) ?: (string) ($row['tipo_envio'] ?? ''),
                ];
            })->filter();
        })->unique(fn (array $entry) => $entry['codigo'].'|'.$this->normalizeKardexText($entry['servicio']))->values();

        $allCodes = $destinationEntries->pluck('codigo')->unique()->values();
        $trackingCodes = $destinationEntries
            ->filter(fn (array $entry) => $this->isKardexInternationalService($entry['servicio'])
                || $this->isKardexUpuTrackingCode($entry['codigo']))
            ->pluck('codigo')
            ->unique()
            ->values();
        $localDestinations = $this->lookupKardexDestinationsInBatch($allCodes);
        $trackingDestinations = $destinationClient->destinations($trackingCodes->all());
        $trackingEvents = $eventsClient->events($trackingCodes->all(), ! $fastRegionalMode);
        foreach ($this->lookupKardexDestinationsFromTrackingEvents($trackingEvents) as $code => $destination) {
            if ($this->cleanKardexReportText((string) ($trackingDestinations[$code] ?? '')) === '') {
                $trackingDestinations[$code] = $destination;
            }
        }
        $trackingOrigins = $this->lookupKardexOriginsFromTrackingEvents($trackingEvents);

        $splitRows = $pdfRows
            ->flatMap(fn (array $row) => $this->splitKardexPackageRows($row))
            ->values();
        $operationalStages = $this->lookupKardexOperationalStages($splitRows, $trackingEvents);
        $deliveryExpressPayments = $this->lookupKardexDeliveryExpressPaymentModes($splitRows);
        $viewRows = $splitRows
            ->map(function (array $splitRow) use ($localDestinations, $trackingDestinations, $trackingOrigins, $trackingEvents, $operationalStages, $deliveryExpressPayments): array {
                $codes = collect($splitRow['detalle_codigos'] ?? [])
                    ->pluck('codigo')
                    ->map(fn ($code) => $this->normalizeKardexPackageLookupCode((string) $code))
                    ->filter()
                    ->unique()
                    ->values();
                $stages = $codes
                    ->map(fn (string $code) => $operationalStages[$code] ?? null)
                    ->filter()
                    ->unique()
                    ->values();
                $splitRow['modalidad_entrega'] = $this->resolveKardexOperation($splitRow, $codes, $stages, $trackingEvents);
                $isDeliveryExpress = $this->isKardexDeliveryExpressRow($splitRow);
                $paymentLabel = $isDeliveryExpress
                    ? $this->resolveKardexDeliveryExpressPaymentMode($splitRow, $deliveryExpressPayments)
                    : '';
                $rawDistributionLabel = $stages
                    ->first(fn (string $stage) => in_array(
                        $this->normalizeKardexText($stage),
                        [
                            'DISTRIBUCION DOMICILIARIA',
                            'DISTRIBUCION NO DOMICILIARIA',
                            'ENTREGADO DOMICILIARIA',
                            'ENTREGADO NO DOMICILIARIA',
                            'ENTREGADO MODALIDAD NO REGISTRADA',
                        ],
                        true
                    ));
                $officeArrivalLabel = $stages
                    ->first(fn (string $stage) => $this->normalizeKardexText($stage) === 'RECIBIDO EN OFICINA DE ENTREGA');
                $distributionLabel = $this->kardexDeliveryModeLabel($rawDistributionLabel ?? $officeArrivalLabel);

                if ($isDeliveryExpress) {
                    $splitRow['forma_cobro_delivery_express'] = $paymentLabel;
                    $splitRow['estado_distribucion_delivery_express'] = $distributionLabel ?? '';
                    $splitRow['estado_operativo_delivery_express'] = $distributionLabel ?? '';
                    $details = collect([
                        $paymentLabel !== '' ? 'COBRO: ' . $paymentLabel : null,
                        $distributionLabel,
                    ])->filter()->values();
                    $splitRow['operacion_detalle'] = $details->implode(' · ');
                } else {
                    $splitRow['operacion_detalle'] = $this->normalizeKardexText($splitRow['modalidad_entrega']) === 'ENTREGA'
                        ? ($distributionLabel ?? '')
                        : '';
                }

                return $this->formatKardexViewRow($splitRow, $localDestinations, $trackingDestinations, $trackingOrigins);
            })
            ->values();

        return $viewRows
            ->groupBy('venta_group_key')
            ->flatMap(function (Collection $saleRows): Collection {
                $detailCount = $saleRows->count();

                return $saleRows->values()->map(function (array $row, int $index) use ($detailCount): array {
                    $row['venta_detalle_orden'] = $index + 1;
                    $row['venta_detalles_total'] = $detailCount;

                    return $row;
                });
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['nro'] = $index + 1;

                return $row;
            });
    }

    private function kardexDeliveryModeLabel(?string $stage): ?string
    {
        return match ($this->normalizeKardexText((string) $stage)) {
            'DISTRIBUCION DOMICILIARIA', 'ENTREGADO DOMICILIARIA' => 'DISTRIBUCIÓN DOMICILIARIA',
            'DISTRIBUCION NO DOMICILIARIA',
            'ENTREGADO NO DOMICILIARIA',
            'RECIBIDO EN OFICINA DE ENTREGA' => 'DISTRIBUCIÓN NO DOMICILIARIA',
            default => null,
        };
    }

    private function kardexDeliverySubgroupLabel(string $operation, string $deliveryMode): ?string
    {
        if ($this->normalizeKardexText($operation) !== 'ENTREGA') {
            return null;
        }

        return match ($this->normalizeKardexText($deliveryMode)) {
            'DISTRIBUCION NO DOMICILIARIA' => 'ENTREGA · DISTRIBUCIÓN NO DOMICILIARIA',
            'DISTRIBUCION DOMICILIARIA' => 'ENTREGA · DISTRIBUCIÓN DOMICILIARIA',
            default => null,
        };
    }

    private function isKardexDeliveryExpressRow(array $row): bool
    {
        $values = collect([
            data_get($row, 'tipo_envio'),
            data_get($row, 'tipo_correspondencia'),
            data_get($row, 'detalle_items'),
            data_get($row, 'kardex_servicio_familia'),
        ])->merge(collect($row['detalle_codigos'] ?? [])->flatMap(function (array $entry): array {
            $source = $entry['source'] ?? null;

            return [
                data_get($entry, 'servicio'),
                data_get($source, 'titulo'),
                data_get($source, 'nombre_servicio'),
                data_get($source, 'resumen_origen.servicio_familia'),
                data_get($source, 'resumen_origen.servicio_nombre'),
            ];
        }))->filter(fn ($value) => trim((string) $value) !== '');

        return $values->contains(function ($value): bool {
            $normalized = $this->normalizeKardexText(str_replace('_', ' ', (string) $value));

            return str_contains($normalized, 'DELIVERY EXPRESS');
        });
    }

    private function lookupKardexDeliveryExpressPaymentModes(Collection $rows): array
    {
        if (! Schema::hasTable('solicitud_clientes')) {
            return [];
        }

        $requestIds = collect();
        $requestCodes = collect();
        foreach ($rows as $row) {
            if (! $this->isKardexDeliveryExpressRow($row)) {
                continue;
            }

            $rowRequestId = (int) data_get($row, 'kardex_origen_id', 0);
            if ($rowRequestId > 0) {
                $requestIds->push($rowRequestId);
            }
            $rowCode = $this->normalizeKardexPackageLookupCode((string) data_get($row, 'kardex_codigo_solicitud', ''));
            if ($rowCode !== '') {
                $requestCodes->push($rowCode);
            }

            foreach (collect($row['detalle_codigos'] ?? []) as $entry) {
                $source = $entry['source'] ?? null;
                if (! is_object($source)) {
                    continue;
                }

                $origin = ltrim(trim((string) data_get($source, 'origen_tipo', '')), '\\');
                $serviceFamily = $this->normalizeKardexText((string) data_get($source, 'resumen_origen.servicio_familia', ''));
                if ($origin !== 'App\\Models\\SolicitudCliente'
                    && $serviceFamily !== 'DELIVERY EXPRESS'
                    && $serviceFamily !== 'DELIVERY_EXPRESS') {
                    continue;
                }

                $requestId = (int) data_get($source, 'origen_id', 0);
                if ($requestId > 0) {
                    $requestIds->push($requestId);
                }

                foreach ([
                    data_get($source, 'resumen_origen.codigo_paquete'),
                    data_get($source, 'resumen_origen.codigo'),
                    data_get($source, 'codigo_paquete'),
                    data_get($source, 'codigo'),
                    data_get($entry, 'codigo'),
                ] as $code) {
                    $normalizedCode = $this->normalizeKardexPackageLookupCode((string) $code);
                    if ($normalizedCode !== '') {
                        $requestCodes->push($normalizedCode);
                    }
                }
            }
        }

        $requestIds = $requestIds->unique()->values();
        $requestCodes = $requestCodes->unique()->values();
        if ($requestIds->isEmpty() && $requestCodes->isEmpty()) {
            return [];
        }

        $columns = ['id', 'codigo_solicitud', 'barcode', 'forma_pago', 'pago_destinatario'];
        $paymentRows = collect();
        foreach ($requestIds->chunk(1000) as $idChunk) {
            $paymentRows = $paymentRows->concat(DB::table('solicitud_clientes')
                ->select($columns)
                ->whereIn('id', $idChunk->all())
                ->get());
        }
        foreach ($requestCodes->chunk(1000) as $codeChunk) {
            $paymentRows = $paymentRows->concat(DB::table('solicitud_clientes')
                ->select($columns)
                ->whereIn(DB::raw('TRIM(UPPER(codigo_solicitud))'), $codeChunk->all())
                ->orWhereIn(DB::raw('TRIM(UPPER(barcode))'), $codeChunk->all())
                ->get());
        }

        $paymentModes = [];
        foreach ($paymentRows->unique('id') as $paymentRow) {
            $label = $this->resolveKardexDeliveryExpressPaymentMode([
                'forma_pago' => $paymentRow->forma_pago ?? null,
                'pago_destinatario' => $paymentRow->pago_destinatario ?? false,
            ]);
            $paymentModes['ID#' . (int) $paymentRow->id] = $label;
            foreach ([$paymentRow->codigo_solicitud ?? '', $paymentRow->barcode ?? ''] as $code) {
                $normalizedCode = $this->normalizeKardexPackageLookupCode((string) $code);
                if ($normalizedCode !== '') {
                    $paymentModes['CODE#' . $normalizedCode] = $label;
                }
            }
        }

        return $paymentModes;
    }

    private function resolveKardexDeliveryExpressPaymentMode(array $row, array $paymentModes = []): string
    {
        $directFormaPago = strtolower(trim((string) data_get(
            $row,
            'forma_pago',
            data_get($row, 'kardex_forma_pago', data_get($row, 'resumen_origen.forma_pago', ''))
        )));
        $directoPorCobrar = filter_var(data_get(
            $row,
            'pago_destinatario',
            data_get($row, 'kardex_pago_destinatario', data_get($row, 'resumen_origen.pago_destinatario', false))
        ), FILTER_VALIDATE_BOOLEAN);
        if (in_array($directFormaPago, ['destino', 'por cobrar', 'por_cobrar'], true) || $directoPorCobrar) {
            return 'POR COBRAR';
        }
        if (in_array($directFormaPago, ['pagado', 'origen'], true)) {
            return 'PAGADO';
        }

        $rowRequestId = (int) data_get($row, 'kardex_origen_id', 0);
        if ($rowRequestId > 0 && isset($paymentModes['ID#' . $rowRequestId])) {
            return (string) $paymentModes['ID#' . $rowRequestId];
        }
        $rowCode = $this->normalizeKardexPackageLookupCode((string) data_get($row, 'kardex_codigo_solicitud', ''));
        if ($rowCode !== '' && isset($paymentModes['CODE#' . $rowCode])) {
            return (string) $paymentModes['CODE#' . $rowCode];
        }

        foreach (collect($row['detalle_codigos'] ?? []) as $entry) {
            $source = $entry['source'] ?? null;
            $requestId = (int) data_get($source, 'origen_id', 0);
            if ($requestId > 0 && isset($paymentModes['ID#' . $requestId])) {
                return (string) $paymentModes['ID#' . $requestId];
            }

            foreach ([
                data_get($source, 'resumen_origen.codigo_paquete'),
                data_get($source, 'resumen_origen.codigo'),
                data_get($source, 'codigo_paquete'),
                data_get($source, 'codigo'),
                data_get($entry, 'codigo'),
            ] as $code) {
                $normalizedCode = $this->normalizeKardexPackageLookupCode((string) $code);
                if ($normalizedCode !== '' && isset($paymentModes['CODE#' . $normalizedCode])) {
                    return (string) $paymentModes['CODE#' . $normalizedCode];
                }
            }
        }

        // SolicitudCliente usa este mismo valor predeterminado: si no hay
        // forma_pago y pago_destinatario es falso, el envío figura como pagado.
        return $directoPorCobrar ? 'POR COBRAR' : 'PAGADO';
    }

    private function lookupKardexOperationalStages(Collection $rows, array $trackingEventsByCode = []): array
    {
        $codes = $rows
            ->flatMap(fn (array $row) => collect($row['detalle_codigos'] ?? [])->pluck('codigo'))
            ->map(fn ($code) => $this->normalizeKardexPackageLookupCode((string) $code))
            ->filter()
            ->unique()
            ->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $eventTypesByTable = [
            'eventos_ems' => [
                'admission' => [self::KARDEX_EVENT_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'arrival' => [self::KARDEX_EVENT_SENT_TO_WINDOW_EMS],
                'non_domiciliary' => self::KARDEX_EVENT_COUNTER_DELIVERY,
            ],
            'eventos_contrato' => [
                'admission' => [self::KARDEX_EVENT_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'arrival' => [self::KARDEX_EVENT_SENT_TO_WINDOW_EMS],
                'non_domiciliary' => self::KARDEX_EVENT_COUNTER_DELIVERY,
            ],
            'eventos_certi' => [
                'admission' => [self::KARDEX_EVENT_CERTI_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'arrival' => [self::KARDEX_EVENT_SENT_TO_WINDOW_CERTI],
                'non_domiciliary' => self::KARDEX_EVENT_COUNTER_DELIVERY,
            ],
            'eventos_ordi' => [
                'admission' => [self::KARDEX_EVENT_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'non_domiciliary' => self::KARDEX_EVENT_COUNTER_DELIVERY,
            ],
            'eventos_tiktoker' => [
                'admission' => [self::KARDEX_EVENT_TIKTOKER_ADMISSION],
                'domiciliary' => array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY),
                'arrival' => [self::KARDEX_EVENT_TIKTOKER_ARRIVED_AT_EMS_WINDOW],
                'non_domiciliary' => self::KARDEX_EVENT_COUNTER_DELIVERY,
            ],
        ];
        // El evento 316 es entrega para EMS, ORDI, contratos y Delivery Express;
        // en CERTI significa clasificación procesada, no entrega.
        $deliveredEventIdsByTable = [
            'eventos_ems' => [self::KARDEX_EVENT_DELIVERED],
            'eventos_contrato' => [self::KARDEX_EVENT_DELIVERED],
            'eventos_certi' => [],
            'eventos_ordi' => [self::KARDEX_EVENT_DELIVERED],
            'eventos_tiktoker' => [self::KARDEX_EVENT_DELIVERED],
        ];
        $eventsByCode = [];
        foreach ($eventTypesByTable as $table => $eventTypes) {
            if (! Schema::hasTable($table)
                || ! Schema::hasColumn($table, 'codigo')
                || ! Schema::hasColumn($table, 'evento_id')
                || ! Schema::hasColumn($table, 'created_at')) {
                continue;
            }

            $eventIds = array_values(array_unique(array_merge(
                array_merge(...array_values($eventTypes)),
                $deliveredEventIdsByTable[$table] ?? []
            )));

            foreach ($codes->chunk(1000) as $codeChunk) {
                $events = DB::table($table)
                    ->select(['codigo', 'evento_id', 'created_at'])
                    ->whereIn(DB::raw('TRIM(UPPER(codigo))'), $codeChunk->all())
                    ->whereIn('evento_id', $eventIds)
                    ->orderBy('created_at')
                    ->get();

                foreach ($events as $event) {
                    $code = $this->normalizeKardexPackageLookupCode((string) $event->codigo);
                    if ($code !== '') {
                        $eventsByCode[$code][$table][] = $event;
                    }
                }
            }
        }

        $stages = [];
        $stageTimestamps = [];
        foreach ($eventsByCode as $code => $eventsByTable) {
            $history = collect();
            foreach ($eventsByTable as $table => $events) {
                foreach ($events as $event) {
                    $timestamp = strtotime((string) $event->created_at);
                    if ($timestamp === false) {
                        continue;
                    }
                    $eventId = (int) $event->evento_id;
                    $category = null;
                    foreach ($eventTypesByTable[$table] as $type => $ids) {
                        if (in_array($eventId, $ids, true)) {
                            $category = $type;
                            break;
                        }
                    }
                    $isDeliveredEvent = in_array($eventId, $deliveredEventIdsByTable[$table] ?? [], true);
                    if ($category !== null || $isDeliveredEvent) {
                        $history->push([
                            'timestamp' => $timestamp,
                            'event_id' => $eventId,
                            'category' => $category,
                            'delivered' => $isDeliveredEvent,
                        ]);
                    }
                }
            }

            if ($history->isEmpty()) {
                continue;
            }

            $history = $history->sortBy('timestamp')->values();
            $latest = $history->last();
            if ((bool) ($latest['delivered'] ?? false)) {
                $route = $history->filter(fn (array $event) => $event['category'] !== null)->last();
                $category = match ($route['category'] ?? null) {
                    'arrival', 'non_domiciliary' => 'delivered_non_domiciliary',
                    'domiciliary' => 'delivered_domiciliary',
                    default => 'delivered_unknown',
                };
            } else {
                $category = $latest['category'];
            }

            $stages[$code] = match ($category) {
                'admission' => 'ADMISIÓN',
                'arrival' => 'RECIBIDO EN OFICINA DE ENTREGA',
                'domiciliary' => 'DISTRIBUCIÓN DOMICILIARIA',
                'non_domiciliary' => 'DISTRIBUCIÓN NO DOMICILIARIA',
                'delivered_domiciliary' => 'ENTREGADO · DOMICILIARIA',
                'delivered_non_domiciliary' => 'ENTREGADO · NO DOMICILIARIA',
                'delivered_unknown' => 'ENTREGADO · MODALIDAD NO REGISTRADA',
                default => '-',
            };
            $stageTimestamps[$code] = (int) ($latest['timestamp'] ?? 0);
        }

        foreach ($trackingEventsByCode as $rawCode => $events) {
            $code = $this->normalizeKardexPackageLookupCode((string) $rawCode);
            if ($code === '') {
                continue;
            }

            $latestTrackingTimestamp = collect((array) $events)
                ->map(fn ($event) => strtotime((string) (data_get($event, 'timeline_sort_at') ?? data_get($event, 'created_at', ''))) ?: 0)
                ->filter(fn (int $timestamp) => $timestamp > 0)
                ->max();
            $explicitDeliveryModeEvent = $this->latestKardexIpsDeliveryModeEvent(collect((array) $events));
            $explicitDeliveryModeTimestamp = $explicitDeliveryModeEvent !== null
                ? (strtotime((string) (data_get($explicitDeliveryModeEvent, 'timeline_sort_at') ?? data_get($explicitDeliveryModeEvent, 'created_at', ''))) ?: null)
                : null;
            $explicitDeliveryModeIsCurrent = $explicitDeliveryModeEvent !== null
                && (! isset($stages[$code])
                    || ($explicitDeliveryModeTimestamp !== null
                        && $explicitDeliveryModeTimestamp >= (int) ($stageTimestamps[$code] ?? 0)));
            if (isset($stages[$code])
                && ($latestTrackingTimestamp === null
                    || $latestTrackingTimestamp <= (int) ($stageTimestamps[$code] ?? 0))
                && ! $explicitDeliveryModeIsCurrent) {
                continue;
            }

            $stage = $this->resolveKardexSitraOperationalStage((array) $events, $code);
            if ($stage !== null) {
                $stages[$code] = $stage;
                $stageTimestamps[$code] = (int) ($latestTrackingTimestamp ?? 0);
            }
        }

        return $stages;
    }

    private function latestKardexIpsDeliveryModeEvent(Collection $events): ?object
    {
        return $events
            ->map(fn ($event) => (object) (is_array($event) ? $event : (array) $event))
            ->filter(fn (object $event): bool => (int) ($event->codigo_evento ?? 0) === 37
                && $this->normalizeKardexDeliveryMode($event->delivery_mode ?? null) !== null)
            ->sortByDesc(fn (object $event): int => strtotime((string) ($event->timeline_sort_at ?? $event->created_at ?? '')) ?: 0)
            ->first();
    }

    private function normalizeKardexDeliveryMode(mixed $value): ?string
    {
        return match (strtoupper(trim((string) $value))) {
            'DOMICILIARIA' => 'DOMICILIARIA',
            'NO_DOMICILIARIA' => 'NO_DOMICILIARIA',
            default => null,
        };
    }

    private function resolveKardexSitraOperationalStage(array $events, string $code): ?string
    {
        $events = collect($events)
            ->map(fn ($event) => (object) (is_array($event) ? $event : (array) $event))
            ->sortByDesc(fn ($event) => strtotime((string) ($event->timeline_sort_at ?? $event->created_at ?? '')) ?: 0)
            ->values();
        if ($events->isEmpty()) {
            return null;
        }

        $service = preg_match('/^R[A-Z]\d{9}[A-Z]{2}$/', $code) === 1 ? 'CERTI' : 'EMS';
        $progress = app(\App\Services\TrackingProgressService::class)->resolve($events, $service);
        $steps = collect($progress['steps'] ?? [])->map(fn ($step) => $this->normalizeKardexText((string) $step));
        $currentStep = $this->normalizeKardexText((string) data_get($progress, 'steps.'.(int) data_get($progress, 'current_index', 0), ''));
        $progressStatus = $this->normalizeKardexText((string) data_get($progress, 'status', ''));
        $latestEvent = (object) $events->first();
        $explicitDeliveryModeEvent = $this->latestKardexIpsDeliveryModeEvent($events);
        $latestEventCode = is_numeric($latestEvent->codigo_evento ?? null)
            ? (int) $latestEvent->codigo_evento
            : null;
        $latestLocalEventId = is_numeric($latestEvent->evento_id ?? null)
            ? (int) $latestEvent->evento_id
            : null;
        $latestEventText = $this->normalizeKardexText(implode(' ', array_filter([
            (string) ($latestEvent->nombre_evento ?? ''),
            (string) ($latestEvent->eventType ?? ''),
            (string) ($latestEvent->evento ?? ''),
            (string) ($latestEvent->descripcion ?? ''),
        ])));
        $arrivalEventCodes = [32];
        $arrivalEventIds = [self::KARDEX_EVENT_SENT_TO_WINDOW_EMS, self::KARDEX_EVENT_SENT_TO_WINDOW_CERTI];
        $counterEventCodes = [73, 75];
        $counterEventIds = array_merge($counterEventCodes, self::KARDEX_EVENT_COUNTER_DELIVERY);
        $courierEventCodes = [36, 39, 67, 74];
        $courierEventIds = array_merge([self::KARDEX_EVENT_ASSIGNED_TO_COURIER], self::KARDEX_EVENT_COURIER_DELIVERY);
        $routeEventIds = array_merge($counterEventIds, $arrivalEventIds);
        $routeEventCodes = array_merge($counterEventCodes, $arrivalEventCodes);
        $latestRouteEvent = $events->first(function ($event) use ($routeEventIds, $routeEventCodes, $courierEventCodes, $courierEventIds): bool {
            $event = (object) $event;
            $eventCode = is_numeric($event->codigo_evento ?? null) ? (int) $event->codigo_evento : null;
            $eventId = is_numeric($event->evento_id ?? null) ? (int) $event->evento_id : null;

            return in_array($eventCode, $courierEventCodes, true)
                || in_array($eventCode, $routeEventCodes, true)
                || in_array($eventId, $courierEventIds, true)
                || in_array($eventId, $routeEventIds, true);
        });
        $latestRouteEvent = $latestRouteEvent !== null ? (object) $latestRouteEvent : null;
        $latestRouteEventCode = is_numeric($latestRouteEvent?->codigo_evento ?? null)
            ? (int) $latestRouteEvent->codigo_evento
            : null;
        $latestRouteLocalEventId = is_numeric($latestRouteEvent?->evento_id ?? null)
            ? (int) $latestRouteEvent->evento_id
            : null;
        $latestRouteIsDomiciliary = in_array($latestRouteEventCode, $courierEventCodes, true)
            || in_array($latestRouteLocalEventId, $courierEventIds, true);

        if (in_array($progressStatus, ['ENVIO CANCELADO', 'DEVOLUCION EN CURSO', 'DEVUELTO AL REMITENTE'], true)) {
            return null;
        }

        // Los eventos 32, 312 y 172 señalan llegada a la oficina de entrega.
        // No prueban que el destinatario ya lo recogió por ventanilla.
        if (in_array($latestEventCode, $arrivalEventCodes, true)
            || in_array($latestLocalEventId, $arrivalEventIds, true)) {
            return 'RECIBIDO EN OFICINA DE ENTREGA';
        }

        // El evento actual manda sobre los eventos históricos. Por ejemplo,
        // un envío entregado puede conservar eventos anteriores de Aduana.
        if ($progressStatus === 'ENTREGADO' || in_array($latestEventCode, [37, 1250], true)) {
            $deliveryMode = $this->normalizeKardexDeliveryMode($latestEvent->delivery_mode ?? null)
                ?? $this->normalizeKardexDeliveryMode($explicitDeliveryModeEvent?->delivery_mode ?? null);
            if ($deliveryMode === 'DOMICILIARIA') {
                return 'ENTREGADO · DOMICILIARIA';
            }
            if ($deliveryMode === 'NO_DOMICILIARIA') {
                return 'ENTREGADO · NO DOMICILIARIA';
            }
            if ($latestRouteIsDomiciliary) {
                return 'ENTREGADO · DOMICILIARIA';
            }
            $hasCounterDeliveryEvidence = in_array($latestRouteLocalEventId, self::KARDEX_EVENT_COUNTER_DELIVERY, true)
                || $latestRouteEventCode === 75
                || $latestRouteEventCode === 32
                || in_array($latestRouteLocalEventId, $arrivalEventIds, true)
                || ($latestEventCode === 37 && $events->contains(fn ($event) => (int) ($event->codigo_evento ?? 0) === 38));

            return $hasCounterDeliveryEvidence
                ? 'ENTREGADO · NO DOMICILIARIA'
                : 'ENTREGADO · MODALIDAD NO REGISTRADA';
        }

        if (str_contains($currentStep, 'cartero')
            || in_array($latestEventCode, $courierEventCodes, true)
            || in_array($latestLocalEventId, $courierEventIds, true)) {
            return 'DISTRIBUCIÓN DOMICILIARIA';
        }
        if ((bool) data_get($progress, 'is_pickup_available')
            || (bool) data_get($progress, 'pickup_ready_at_destination_customs')
            || in_array($latestEventCode, $counterEventCodes, true)
            || in_array($latestLocalEventId, $counterEventIds, true)
            || $currentStep === 'VENTANILLA'
            // El evento 38 libera el envío de Aduana hacia la ventanilla postal.
            || ($currentStep === 'VENTANILLA = ADUANA' && $latestEventCode === 38)) {
            return 'DISTRIBUCIÓN NO DOMICILIARIA';
        }

        $isAdmissionEvent = in_array($latestEventCode, [1, 45, 68, 78], true)
            || in_array($latestLocalEventId, [self::KARDEX_EVENT_ADMISSION, self::KARDEX_EVENT_CERTI_ADMISSION, 2239], true)
            || str_contains($latestEventText, 'ADMISION')
            || str_contains($latestEventText, 'RECIBIR ENVIO DEL CLIENTE')
            || str_contains($latestEventText, 'PAQUETE RECIBIDO DEL CLIENTE');
        if ($isAdmissionEvent) {
            return 'ADMISIÓN';
        }

        return null;
    }

    private function splitKardexPackageRows(array $row): Collection
    {
        $detalleCodigos = collect($row['detalle_codigos'] ?? []);
        $packageEntries = $detalleCodigos
            ->filter(fn (array $entry): bool => $this->isKardexPackageEntry($entry))
            ->filter(function (array $entry): bool {
                $source = $entry['source'] ?? null;

                return is_object($source) && max(1, (int) data_get($source, 'cantidad', 1)) === 1;
            })
            ->unique(fn (array $entry) => spl_object_id($entry['source']))
            ->values();

        if ($packageEntries->isEmpty()) {
            $sourceItems = collect($row['kardex_source_items'] ?? [])->filter(fn ($item) => is_object($item));
            $hasPackageItems = $sourceItems->contains(fn ($item) => $this->isPackageCartItem($item))
                || $detalleCodigos->contains(fn (array $entry) => $this->isKardexPackageEntry($entry));

            // Una venta puede incluir varios servicios sin código de paquete.
            // Mantenerlos en la misma fila mezcla productos distintos (por
            // ejemplo, Aerolínea y Tarjeta Postal) en el Kardex personal y el
            // regional, porque ambos usan este mismo desglose.
            if ($sourceItems->isNotEmpty() && (! $hasPackageItems || $sourceItems->count() > 1)) {
                return $this->splitKardexNonPackageItems(
                    $row,
                    $sourceItems,
                    $detalleCodigos,
                    (float) ($row['importe_general'] ?? 0)
                );
            }

            $sourceItemDetails = $sourceItems
                ->map(fn ($item) => $this->resolveKardexItemDisplayDetail($item))
                ->filter()
                ->unique()
                ->implode(' / ');
            if ($sourceItemDetails !== '') {
                $row['detalle_items'] = $sourceItemDetails;
            }
            if ($hasPackageItems) {
                $packageCodes = $detalleCodigos->pluck('codigo')->filter()->values();
                $row['codigo_referencia'] = $packageCodes->isNotEmpty() ? $packageCodes->implode(', ') : '-';
                $serviceNames = $detalleCodigos
                    ->pluck('servicio')
                    ->map(fn ($service) => trim((string) $service))
                    ->filter()
                    ->unique()
                    ->values();
                if ($serviceNames->isNotEmpty()) {
                    $row['tipo_envio'] = $serviceNames->implode(' / ');
                }
            } else {
                $row['codigo_referencia'] = '-';
                $row['kardex_non_package_service'] = true;
            }
            unset($row['kardex_source_items']);

            return collect([$row]);
        }

        $sourceItems = collect($row['kardex_source_items'] ?? [])->filter(fn ($item) => is_object($item));
        $packageSourceIds = $packageEntries
            ->map(fn (array $entry) => spl_object_id($entry['source']))
            ->flip();
        $remainingItems = $sourceItems
            ->reject(fn ($item) => $packageSourceIds->has(spl_object_id($item)))
            ->values();
        $packageAmounts = $packageEntries
            ->map(fn (array $entry) => $this->resolveKardexItemAmount($entry['source']))
            ->all();
        $invoiceAmount = round((float) ($row['importe_general'] ?? 0), 2);
        $packageAmountTotal = round(array_sum($packageAmounts), 2);

        if ($remainingItems->isEmpty() && $packageAmountTotal !== $invoiceAmount) {
            $packageAmounts = $this->reconcileKardexLineAmounts($invoiceAmount, $packageAmounts);
            $packageAmountTotal = round(array_sum($packageAmounts), 2);
        } elseif ($packageAmountTotal > $invoiceAmount) {
            $packageAmounts = $this->reconcileKardexLineAmounts($invoiceAmount, $packageAmounts);
            $packageAmountTotal = round(array_sum($packageAmounts), 2);
        }

        $splitRows = $packageEntries
            ->values()
            ->map(function (array $entry, int $index) use ($row, $packageAmounts): array {
                $source = $entry['source'];
                $code = trim((string) ($entry['codigo'] ?? ''));
                $service = trim((string) ($entry['servicio'] ?? ''));
                $entryCollection = collect([$entry]);
                $amount = (float) ($packageAmounts[$index] ?? 0);

                return array_merge($row, [
                    'tipo_envio' => $service !== '' ? $service : (string) ($row['tipo_envio'] ?? ''),
                    'detalle_items' => trim((string) (
                        data_get($source, 'titulo')
                        ?: data_get($source, 'nombre_servicio')
                        ?: $service
                    )),
                    'detalle_codigos' => $entryCollection,
                    'codigo_paquetes' => collect([$code])->filter()->values(),
                    'codigo_paquetes_texto' => $code,
                    'codigo_referencia' => $code,
                    'cantidad' => 1,
                    'peso' => (float) data_get($source, 'resumen_origen.peso', data_get($source, 'peso', 0)),
                    'importe_parcial' => $amount,
                    'importe_general' => $amount,
                ]);
            });

        if ($remainingItems->isNotEmpty()) {
            $remainingEntries = $detalleCodigos
                ->reject(function (array $entry) use ($packageSourceIds): bool {
                    $source = $entry['source'] ?? null;

                    return is_object($source) && $packageSourceIds->has(spl_object_id($source));
                })
                ->values();
            $residualAmount = max(0, round($invoiceAmount - $packageAmountTotal, 2));
            $splitRows = $splitRows->concat($this->splitKardexNonPackageItems(
                $row,
                $remainingItems,
                $remainingEntries,
                $residualAmount
            ));
        }

        unset($row['kardex_source_items']);

        return $splitRows
            ->map(function (array $splitRow): array {
                unset($splitRow['kardex_source_items']);

                return $splitRow;
            })
            ->values();
    }

    private function splitKardexNonPackageItems(array $row, Collection $items, Collection $detailCodes, float $targetAmount): Collection
    {
        $items = $items->filter(fn ($item) => is_object($item))->values();
        if ($items->isEmpty()) {
            return collect();
        }

        $amounts = $items
            ->map(fn ($item) => $this->resolveKardexItemAmount($item))
            ->all();
        if (round(array_sum($amounts), 2) !== round($targetAmount, 2)) {
            $amounts = $this->reconcileKardexLineAmounts($targetAmount, $amounts);
        }

        return $items->values()->map(function ($item, int $index) use ($row, $detailCodes, $amounts): array {
            $sourceId = spl_object_id($item);
            $entries = $detailCodes
                ->filter(function (array $entry) use ($sourceId): bool {
                    $source = $entry['source'] ?? null;

                    return is_object($source) && spl_object_id($source) === $sourceId;
                })
                ->values();
            $codes = $entries->pluck('codigo')->filter()->values();
            $service = trim((string) $entries->pluck('servicio')->filter()->first())
                ?: $this->resolveKardexItemServiceName($item);
            $detail = $this->resolveKardexItemDisplayDetail($item);
            $amount = (float) ($amounts[$index] ?? 0);
            $isPackage = $this->isPackageCartItem($item);

            return array_merge($row, [
                'tipo_envio' => $service !== '' ? $service : 'OTROS SERVICIOS',
                'detalle_items' => $detail !== '' ? $detail : ($service !== '' ? $service : 'Otros servicios'),
                'kardex_servicio_familia' => trim((string) data_get($item, 'resumen_origen.servicio_familia', '')),
                'kardex_origen_tipo' => ltrim(trim((string) data_get($item, 'origen_tipo', '')), '\\'),
                'kardex_origen_id' => (int) data_get($item, 'origen_id', 0),
                'kardex_codigo_solicitud' => trim((string) (
                    data_get($item, 'resumen_origen.codigo_paquete')
                    ?: data_get($item, 'resumen_origen.codigo')
                    ?: data_get($item, 'codigo_paquete')
                    ?: data_get($item, 'codigo')
                    ?: ''
                )),
                'kardex_forma_pago' => trim((string) data_get($item, 'resumen_origen.forma_pago', '')),
                'kardex_pago_destinatario' => filter_var(data_get($item, 'resumen_origen.pago_destinatario', false), FILTER_VALIDATE_BOOLEAN),
                'detalle_codigos' => $entries,
                'codigo_paquetes' => $codes,
                'codigo_paquetes_texto' => $codes->implode(', '),
                'codigo_referencia' => $codes->isNotEmpty() ? $codes->implode(', ') : '-',
                'kardex_non_package_service' => ! $isPackage,
                'cantidad' => max(1, (int) data_get($item, 'cantidad', 1)),
                'peso' => round((float) data_get($item, 'resumen_origen.peso', data_get($item, 'peso', 0)), 3),
                'importe_parcial' => $amount,
                'importe_general' => $amount,
                'importe_venta' => $amount,
            ]);
        });
    }

    private function resolveKardexItemDisplayDetail(mixed $item): string
    {
        $title = trim((string) (data_get($item, 'titulo') ?: data_get($item, 'nombre_servicio', '')));
        $serviceDescription = trim((string) (
            data_get($item, 'resumen_origen.descripcion_servicio')
            ?: data_get($item, 'descripcion_servicio')
            ?: ''
        ));
        $itemService = trim((string) (data_get($item, 'nombre_servicio') ?: $title));
        $isCasilla = str_contains($this->normalizeKardexText($title . ' ' . $itemService . ' ' . $serviceDescription), 'CASILLA');

        return $isCasilla && $serviceDescription !== '' ? $serviceDescription : $title;
    }

    private function resolveKardexItemServiceName(mixed $item): string
    {
        $candidates = collect([
            data_get($item, 'resumen_origen.tarifario_snapshot.servicio.nombre_servicio'),
            data_get($item, 'resumen_origen.tarifario_snapshot.nombre_servicio'),
            data_get($item, 'resumen_origen.servicio_nombre'),
            data_get($item, 'resumen_origen.nombre_servicio'),
            data_get($item, 'titulo'),
            data_get($item, 'descripcion'),
            data_get($item, 'resumen_origen.descripcion_servicio'),
            data_get($item, 'nombre_servicio'),
        ])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->map(fn (string $value) => $this->resolveKardexServiceDisplayName($value))
            ->filter(fn (string $value) => $value !== '' && $value !== 'SIN DETALLE')
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return '';
        }

        // Prefer a billed tariff variant such as EMS Nacional (Eje Troncal)
        // or an explicit Contratos/ECA detail over a generic service name.
        $specific = $candidates->first(function (string $value): bool {
            $normalized = $this->normalizeKardexText($value);

            return str_contains($normalized, 'EJE TRONCAL')
                || str_contains($normalized, 'COBERTURA')
                || str_contains($normalized, 'INTERNACIONAL')
                || ((str_contains($normalized, 'CONTRATO') || str_contains($normalized, 'ECA'))
                    && $this->kardexServiceDetailSuffix($value) !== '');
        });

        return $specific ?: (string) $candidates->first();
    }

    private function isKardexPackageEntry(array $entry): bool
    {
        $source = $entry['source'] ?? null;
        $code = trim((string) ($entry['codigo'] ?? ''));
        if (! is_object($source) || $code === '') {
            return false;
        }

        $origin = ltrim(trim((string) data_get($source, 'origen_tipo', '')), '\\');
        $packageOrigins = [PaqueteEms::class, PaqueteInt::class, PaqueteOrdi::class, PaqueteCerti::class];
        if (in_array($origin, array_map(fn ($type) => ltrim($type, '\\'), $packageOrigins), true)) {
            return true;
        }

        if ($origin !== 'App\\Models\\ConceptoFacturacion') {
            return false;
        }

        $references = [
            data_get($source, 'codigo'),
            data_get($source, 'codigo_paquete'),
            data_get($source, 'resumen_origen.codigo_paquete'),
        ];

        foreach ($references as $reference) {
            if (preg_match('/^SRVE-(?:2|3|4|11|12)\\s*-/i', trim((string) $reference)) === 1) {
                return true;
            }
        }

        return false;
    }

    private function resolveKardexItemAmount(object $item): float
    {
        $base = (float) data_get($item, 'monto_base', data_get($item, 'precio', 0));
        $extras = (float) data_get($item, 'monto_extras', 0);
        $quantity = max(1, (int) data_get($item, 'cantidad', 1));
        $lineTotal = data_get($item, 'total_linea');

        if (is_numeric($lineTotal) && ((float) $lineTotal > 0 || ($base + $extras) <= 0)) {
            return round((float) $lineTotal, 2);
        }

        return round(($base + $extras) * $quantity, 2);
    }

    private function reconcileKardexLineAmounts(float $targetAmount, array $amounts): array
    {
        if ($amounts === []) {
            return [];
        }

        $weights = array_map(fn ($amount) => max(0, (float) $amount), $amounts);
        $weightTotal = array_sum($weights);
        if ($weightTotal <= 0) {
            $weights = array_fill(0, count($amounts), 1.0);
            $weightTotal = count($weights);
        }

        $remainingCents = max(0, (int) round($targetAmount * 100));
        $allocated = [];
        foreach ($weights as $index => $weight) {
            if ($index === array_key_last($weights)) {
                $allocated[] = $remainingCents / 100;
                break;
            }

            $cents = (int) floor(($targetAmount * 100) * ($weight / $weightTotal));
            $cents = max(0, min($remainingCents, $cents));
            $allocated[] = $cents / 100;
            $remainingCents -= $cents;
        }

        return $allocated;
    }

    private function formatKardexViewRow(array $row, array $localDestinations, array $trackingDestinations, array $trackingOrigins = []): array
    {
        $detalleCodigos = collect($row['detalle_codigos'] ?? []);
        $tipoServicio = trim((string) ($row['tipo_envio'] ?? ''));
        $isNonPackageService = (bool) ($row['kardex_non_package_service'] ?? false);
        $guiaCasilla = $isNonPackageService
            ? '-'
            : $detalleCodigos->pluck('codigo')->filter()->implode(', ');
        $hasDestinationHint = $detalleCodigos
            ->pluck('destino_hint')
            ->contains(fn ($hint) => trim((string) $hint) !== '');
        if (! $isNonPackageService && $guiaCasilla === '' && ! $hasDestinationHint) {
            $guiaCasilla = trim((string) ($row['codigo_referencia'] ?? ''));
        }
        $regional = $this->resolveKardexRegional($row);
        $esCasilla = $this->isKardexCasillaService($tipoServicio, $guiaCasilla);
        $tipoCorrespondencia = $esCasilla
            ? $this->formatKardexCasillaLabel($tipoServicio, $guiaCasilla)
            : $this->resolveKardexCorrespondenceType(
                $tipoServicio,
                $guiaCasilla,
                (string) ($row['detalle_items'] ?? '')
            );
        $estadoEmision = strtoupper(trim((string) ($row['estado_emision'] ?? '')));
        $estadoVenta = strtolower(trim((string) ($row['estado_venta'] ?? '')));
        $esAnulada = in_array($estadoEmision, ['ANULADA', 'ANULADO'], true)
            || in_array($estadoVenta, ['anulado', 'descartado'], true)
            || ($row['section_key'] ?? '') === 'factura_anulada';
        $metodoPago = strtolower(trim((string) ($row['metodo_pago'] ?? '')));
        $estadoPago = strtolower(trim((string) ($row['estado_pago'] ?? '')));
        $metodoPagoLabel = (bool) ($row['es_cuenta_por_cobrar'] ?? false)
            ? 'CUENTA POR COBRAR'
            : (strtolower(trim((string) ($row['canal_emision'] ?? ''))) === 'oficial'
                ? 'REGISTRO OFICIAL'
                : ($metodoPago === 'qr'
                    ? 'QR · ' . strtoupper($estadoPago !== '' ? $estadoPago : 'PENDIENTE')
                    : 'EFECTIVO'));
        $estadoVentaLabel = match ($estadoVenta) {
            'emitido' => 'EMITIDO',
            'pendiente_pago' => 'PENDIENTE DE PAGO',
            'borrador' => 'BORRADOR',
            'anulado', 'descartado' => 'ANULADO',
            default => $estadoEmision !== '' ? $estadoEmision : 'SIN ESTADO',
        };
        $operacion = trim((string) ($row['modalidad_entrega'] ?? '-')) ?: '-';
        $operacionSubgrupo = $this->kardexDeliverySubgroupLabel($operacion, (string) ($row['operacion_detalle'] ?? ''));
        $esEntrega = $this->normalizeKardexText($operacion) === 'ENTREGA';
        $destino = $esCasilla
            ? ''
            : $this->resolveKardexDestinationFromBatch($row, $localDestinations, $trackingDestinations);
        $origen = $esCasilla
            ? ''
            : $this->resolveKardexOriginFromBatch($row, $trackingOrigins);
        $ubicacion = $esEntrega ? $origen : $destino;
        $detalleCasilla = '';
        if ($esCasilla) {
            $detalleCasilla = trim((string) ($row['detalle_items'] ?? ''));
            if ($detalleCasilla === '' || in_array($this->normalizeKardexText($detalleCasilla), ['SIN DETALLE REAL', 'OTROS SERVICIOS'], true)) {
                $detalleCasilla = $tipoServicio;
            }

            $detalleCasilla = collect(preg_split('/\s+\/\s+/', $detalleCasilla) ?: [$detalleCasilla])
                ->map(fn ($detail) => trim((string) (preg_replace('/^\s*(?:SERVICIO\s+)?CASILLA\s*[-:\x{2013}\x{2014}]?\s*/iu', '', (string) $detail) ?? $detail)))
                ->filter(fn ($detail) => $detail !== '' && ! in_array($this->normalizeKardexText($detail), ['CASILLA', 'SERVICIO CASILLA'], true))
                ->implode(' | ');
        }

        return array_merge($row, [
            'fecha' => $row['fecha'] ?? '-',
            'cantidad' => (int) ($row['cantidad'] ?? 0),
            'regional_registro' => $regional,
            'origen' => $this->resolveKardexOriginCode($regional, $row['origen_sucursal_codigo'] ?? null),
            'tipo_servicio' => $tipoServicio !== '' ? $tipoServicio : 'SIN DETALLE',
            'tipo_correspondencia' => $tipoCorrespondencia,
            'detalle_casilla' => $detalleCasilla,
            'guia_casilla' => $esCasilla ? '' : ($guiaCasilla !== '' ? $guiaCasilla : '-'),
            'peso' => $esCasilla ? null : (float) ($row['peso'] ?? 0),
            'pais_ciudad' => $ubicacion !== '' ? $ubicacion : '-',
            'ubicacion_tipo' => $esEntrega ? 'ORIGEN' : 'DESTINO',
            'modalidad_entrega' => $operacion,
            'operacion_subgrupo' => $operacionSubgrupo,
            'numero_factura' => trim((string) ($row['numero_factura'] ?? '-')) ?: '-',
            'importe' => (float) ($row['importe_general'] ?? 0),
            'importe_venta' => (float) ($row['importe_venta'] ?? $row['importe_general'] ?? 0),
            'operacion_grupo' => $this->kardexOperationGroupLabel([
                'modalidad_entrega' => $operacion,
                'operacion_detalle' => (string) ($row['operacion_detalle'] ?? ''),
            ]),
            'metodo_pago_label' => $metodoPagoLabel,
            'facturacion_label' => trim((string) ($row['estado_label'] ?? '')) ?: 'SIN ESTADO',
            'estado_venta_label' => $estadoVentaLabel,
            'estado_emision' => $estadoEmision,
            'es_anulada' => $esAnulada,
            'es_casilla' => $esCasilla,
        ]);
    }

    private function resolveKardexOperation(array $row, Collection $codes, Collection $stages, array $trackingEventsByCode): string
    {
        $serviceNames = collect($row['detalle_codigos'] ?? [])
            ->pluck('servicio')
            ->prepend((string) ($row['tipo_envio'] ?? ''))
            ->implode(' ');
        $normalizedServiceNames = $this->normalizeKardexText($serviceNames);
        if (str_contains($normalizedServiceNames, 'CASILLA')
            || str_contains($normalizedServiceNames, 'ESTAMPILLA')
            || str_contains($normalizedServiceNames, 'TARJETA')) {
            return 'ADMISIÓN';
        }

        $upuOriginCountries = $codes
            ->map(fn (string $code) => $this->kardexUpuOriginCountryCode($code))
            ->filter()
            ->unique()
            ->values();
        if ($upuOriginCountries->isNotEmpty()) {
            return $upuOriginCountries->contains(fn (string $country) => $country !== 'BO')
                ? 'ENTREGA'
                : 'ADMISIÓN';
        }

        $isInternationalService = $this->isKardexInternationalService($serviceNames);

        // Some postal products (for example ordinary international mail)
        // have no tracking code, but the Kardex row still records their
        // admission at the counter.
        if ($codes->isEmpty() && $this->isKardexPostalAdmissionService($serviceNames)) {
            return 'ADMISIÓN';
        }

        // A domestic item in Kardex represents its counter admission. Its
        // operation must not change later when tracking reaches distribution
        // or delivery events.
        if ($codes->isNotEmpty() && ! $isInternationalService) {
            return 'ADMISIÓN';
        }

        $sourceHints = collect($row['detalle_codigos'] ?? [])
            ->flatMap(function (array $entry): array {
                $source = $entry['source'] ?? null;

                return collect([
                    data_get($source, 'operacion'),
                    data_get($source, 'tipo_operacion'),
                    data_get($source, 'operation'),
                    data_get($source, 'operation_type'),
                    data_get($source, 'movimiento'),
                    data_get($source, 'tipo_movimiento'),
                    data_get($source, 'resumen_origen.operacion'),
                    data_get($source, 'resumen_origen.tipo_operacion'),
                ])->filter(fn ($value) => trim((string) $value) !== '')->all();
            })
            ->implode(' ');
        $sourceHint = $this->normalizeKardexText($sourceHints);
        if (str_contains($sourceHint, 'ADMISION') || str_contains($sourceHint, 'OUTBOUND')) {
            return 'ADMISIÓN';
        }
        if (str_contains($sourceHint, 'ENTREGA')
            || str_contains($sourceHint, 'DELIVERY')
            || str_contains($sourceHint, 'INBOUND')
            || str_contains($sourceHint, 'RECEPCION')) {
            return 'ENTREGA';
        }

        foreach ($codes as $code) {
            foreach ((array) ($trackingEventsByCode[$code] ?? []) as $event) {
                $event = (array) $event;
                $originCountry = strtoupper(trim((string) (
                    $event['origin_country_code']
                    ?? $event['pais_origen_iso2']
                    ?? ''
                )));
                $destinationCountry = strtoupper(trim((string) (
                    $event['destination_country_code']
                    ?? $event['pais_destino_iso2']
                    ?? ''
                )));
                if ($originCountry !== '' && $destinationCountry !== '') {
                    if ($originCountry !== 'BO' && $destinationCountry === 'BO') {
                        return 'ENTREGA';
                    }
                    if ($originCountry === 'BO' && $destinationCountry !== 'BO') {
                        return 'ADMISIÓN';
                    }
                }
            }
        }

        $normalizedStages = $stages->map(fn (string $stage) => $this->normalizeKardexText($stage));
        if ($normalizedStages->contains('ADMISION')) {
            return 'ADMISIÓN';
        }
        if ($normalizedStages->contains(fn (string $stage) => str_contains($stage, 'DISTRIBUCION') || str_contains($stage, 'ENTREGA'))) {
            return 'ENTREGA';
        }

        // Unknown package direction in a sale defaults to its admission.
        if ($codes->isNotEmpty()) {
            return 'ADMISIÓN';
        }

        return 'ADMISIÓN';
    }

    private function resolveKardexOriginFromBatch(array $row, array $trackingOrigins): string
    {
        $origins = collect($row['detalle_codigos'] ?? [])
            ->map(function (array $entry) use ($trackingOrigins): string {
                $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                $trackingOrigin = $this->cleanKardexReportText((string) ($trackingOrigins[$code] ?? ''));
                $source = $entry['source'] ?? null;
                $city = $this->cleanKardexReportText((string) (
                    data_get($source, 'resumen_origen.ciudad_origen')
                    ?? data_get($source, 'resumen_origen.origin_city')
                    ?? data_get($source, 'ciudad_origen')
                    ?? data_get($source, 'origin_city')
                    ?? data_get($source, 'origen_ciudad')
                    ?? ''
                ));
                $country = $this->cleanKardexReportText((string) (
                    data_get($source, 'resumen_origen.pais_origen_nombre')
                    ?? data_get($source, 'resumen_origen.pais_origen')
                    ?? data_get($source, 'resumen_origen.origin_country_name')
                    ?? data_get($source, 'pais_origen_nombre')
                    ?? data_get($source, 'pais_origen')
                    ?? data_get($source, 'origin_country_name')
                    ?? data_get($source, 'origin_country')
                    ?? ''
                ));
                $countryCode = strtoupper(trim((string) (
                    data_get($source, 'resumen_origen.pais_origen_iso2')
                    ?? data_get($source, 'resumen_origen.origin_country_code')
                    ?? data_get($source, 'pais_origen_iso2')
                    ?? data_get($source, 'origin_country_code')
                    ?? $this->kardexUpuOriginCountryCode($code)
                    ?? ''
                )));
                $genericOrigin = $this->cleanKardexReportText((string) (
                    data_get($source, 'resumen_origen.origen')
                    ?? data_get($source, 'origen')
                    ?? data_get($source, 'origin')
                    ?? ''
                ));
                if ($genericOrigin !== '' && $city === '' && $country === '') {
                    [$genericCity, $genericCountry] = $this->parseKardexOriginHint($genericOrigin);
                    $city = $genericCity;
                    $country = $genericCountry;
                }

                if ($trackingOrigin !== '' && ($city === '' || $country === '')) {
                    [$trackingCity, $trackingCountry] = $this->parseKardexOriginHint($trackingOrigin);
                    $city = $city !== '' ? $city : $trackingCity;
                    $country = $country !== '' ? $country : $trackingCountry;
                }

                if ($country === '' && $countryCode !== '') {
                    $country = $this->kardexCountryDestinationHint($countryCode) ?: $countryCode;
                } elseif ($country !== '') {
                    $country = $this->kardexCountryDestinationHint($country) ?: $country;
                }

                $origin = $this->formatKardexOriginLocation($city, $country);

                return $origin !== '' ? $origin : $trackingOrigin;
            })
            ->filter()
            ->unique()
            ->values();

        return $origins->isNotEmpty() ? $origins->implode(', ') : '-';
    }

    private function lookupKardexOriginsFromTrackingEvents(array $trackingEventsByCode): array
    {
        $origins = [];

        foreach ($trackingEventsByCode as $rawCode => $rawEvents) {
            $code = $this->normalizeKardexPackageLookupCode((string) $rawCode);
            if ($code === '') {
                continue;
            }

            $events = collect((array) $rawEvents)
                ->map(fn ($event) => (array) $event)
                ->sortByDesc(fn (array $event) => strtotime((string) ($event['timeline_sort_at'] ?? $event['created_at'] ?? '')) ?: 0)
                ->values();
            $bestCity = '';
            $bestCountry = '';

            foreach ($events as $event) {
                $city = $this->cleanKardexReportText((string) (
                    $event['ciudad_origen']
                    ?? $event['origin_city']
                    ?? $event['city_origin']
                    ?? ''
                ));
                $country = $this->cleanKardexReportText((string) (
                    $event['origin_country_name']
                    ?? $event['pais_origen_nombre']
                    ?? $event['pais_origen']
                    ?? $event['country_origin']
                    ?? ''
                ));
                $countryCode = strtoupper(trim((string) (
                    $event['origin_country_code']
                    ?? $event['pais_origen_iso2']
                    ?? ''
                )));
                $genericOrigin = $this->cleanKardexReportText((string) ($event['origen'] ?? $event['origin'] ?? ''));
                if ($genericOrigin !== '' && $city === '' && $country === '') {
                    [$city, $country] = $this->parseKardexOriginHint($genericOrigin);
                }

                if ($country === '' && $countryCode !== '') {
                    $country = $this->kardexCountryDestinationHint($countryCode) ?: $countryCode;
                } elseif ($country !== '') {
                    $country = $this->kardexCountryDestinationHint($country) ?: $country;
                }

                $bestCity = $bestCity !== '' ? $bestCity : $city;
                $bestCountry = $bestCountry !== '' ? $bestCountry : $country;
                if ($bestCity !== '' && $bestCountry !== '') {
                    break;
                }
            }

            if ($bestCountry === '') {
                $countryCode = $this->kardexUpuOriginCountryCode($code);
                $bestCountry = $countryCode !== ''
                    ? ($this->kardexCountryDestinationHint($countryCode) ?: $countryCode)
                    : '';
            }

            $origin = $this->formatKardexOriginLocation($bestCity, $bestCountry);
            if ($origin !== '') {
                $origins[$code] = $origin;
            }
        }

        return $origins;
    }

    private function parseKardexOriginHint(string $origin): array
    {
        $origin = $this->cleanKardexReportText($origin);
        if ($origin === '') {
            return ['', ''];
        }

        $parts = array_values(array_filter(array_map(
            fn ($part) => $this->cleanKardexReportText((string) $part),
            explode('/', $origin, 2)
        )));
        if (count($parts) >= 2) {
            $firstCountry = $this->kardexCountryDestinationHint($parts[0]);
            $secondCountry = $this->kardexCountryDestinationHint($parts[1]);
            if ($firstCountry !== '') {
                return [$parts[1], $firstCountry];
            }
            if ($secondCountry !== '') {
                return [$parts[0], $secondCountry];
            }

            return [$parts[0], $parts[1]];
        }

        $country = $this->kardexCountryDestinationHint($origin);

        return $country !== '' ? ['', $country] : [$origin, ''];
    }

    private function formatKardexOriginLocation(string $city, string $country): string
    {
        $city = $this->cleanKardexReportText($city);
        $country = $this->cleanKardexReportText($country);
        $cityAsCountry = $this->kardexCountryDestinationHint($city);
        if ($cityAsCountry !== ''
            && ($country === '' || $this->normalizeKardexText($cityAsCountry) === $this->normalizeKardexText($country))) {
            return $cityAsCountry;
        }
        if ($city !== '' && $country !== '' && $this->normalizeKardexText($city) !== $this->normalizeKardexText($country)) {
            return $city.' / '.$country;
        }

        return $city !== '' ? $city : $country;
    }

    private function formatKardexDestinationLocation(string $destination): string
    {
        $destination = $this->cleanKardexReportText($destination);
        if ($destination === '') {
            return '';
        }

        $parts = array_values(array_filter(array_map(
            fn ($part) => $this->cleanKardexReportText((string) $part),
            explode('/', $destination, 2)
        )));
        if (count($parts) >= 2) {
            $firstCountry = $this->kardexCountryDestinationHint($parts[0]);
            $secondCountry = $this->kardexCountryDestinationHint($parts[1]);
            if ($firstCountry !== '') {
                return $firstCountry.' / '.$parts[1];
            }
            if ($secondCountry !== '') {
                return $secondCountry.' / '.$parts[0];
            }

            return implode(' / ', $parts);
        }

        return $this->kardexCountryDestinationHint($destination)
            ?: $this->normalizeKardexInternationalCountry($destination);
    }

    private function isKardexUpuTrackingCode(string $code): bool
    {
        return $this->kardexUpuOriginCountryCode($code) !== '';
    }

    private function isKardexS10TypeCode(string $code): bool
    {
        return preg_match('/^[ECLRU][A-Z]\d{9}[A-Z]{2}$/i', strtoupper(trim($code))) === 1;
    }

    private function kardexUpuOriginCountryCode(string $code): string
    {
        $code = strtoupper(trim($code));

        return preg_match('/^[A-Z]{2}\d{9}([A-Z]{2})$/', $code, $matches) === 1
            ? $matches[1]
            : '';
    }

    private function resolveKardexDestinationFromBatch(array $row, array $localDestinations, array $trackingDestinations): string
    {
        $destinations = collect($row['detalle_codigos'] ?? [])
            ->map(function (array $entry) use ($row, $localDestinations, $trackingDestinations): string {
                $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                $destinationHint = $this->cleanKardexReportText((string) ($entry['destino_hint'] ?? ''));
                $service = trim((string) ($entry['servicio'] ?? '')) ?: (string) ($row['tipo_envio'] ?? '');
                $isInternational = $this->isKardexInternationalService($service) || $this->isKardexUpuTrackingCode($code);
                if ($code === '') {
                    return $isInternational ? $this->formatKardexDestinationLocation($destinationHint) : $destinationHint;
                }

                if ($isInternational) {
                    $trackingDestination = $this->cleanKardexReportText((string) ($trackingDestinations[$code] ?? ''));
                    if ($trackingDestination !== '') {
                        return $this->formatKardexDestinationLocation($trackingDestination);
                    }
                }

                foreach ($this->kardexDestinationLookupOrder($code, $service) as $lookup) {
                    $destination = $this->cleanKardexReportText((string) data_get($localDestinations, $code.'.'.$lookup, ''));
                    if ($destination !== '') {
                        return $isInternational ? $this->formatKardexDestinationLocation($destination) : $destination;
                    }
                }

                return $isInternational ? $this->formatKardexDestinationLocation($destinationHint) : $destinationHint;
            })
            ->filter()
            ->unique()
            ->values();

        if ($destinations->isNotEmpty()) {
            return $destinations->implode(', ');
        }

        $fallback = $this->cleanKardexReportText($this->resolveKardexDestination($row, 'place'));
        $isInternationalRow = $this->isKardexInternationalService((string) ($row['tipo_envio'] ?? ''))
            || collect($row['detalle_codigos'] ?? [])->contains(fn (array $entry) => $this->isKardexUpuTrackingCode((string) ($entry['codigo'] ?? '')));
        if ($fallback !== '' && $isInternationalRow) {
            $fallback = $this->formatKardexDestinationLocation($fallback);
        }

        return $fallback !== '' ? $fallback : '-';
    }

    private function lookupKardexDestinationsFromTrackingEvents(array $trackingEventsByCode): array
    {
        $destinations = [];

        foreach ($trackingEventsByCode as $rawCode => $rawEvents) {
            $code = $this->normalizeKardexPackageLookupCode((string) $rawCode);
            if ($code === '') {
                continue;
            }

            $events = collect((array) $rawEvents)
                ->map(fn ($event) => (array) $event)
                ->sortByDesc(fn (array $event) => strtotime((string) ($event['created_at'] ?? '')) ?: 0)
                ->values();

            foreach ($events as $event) {
                $countryName = trim((string) (
                    $event['destination_country_name']
                    ?? $event['pais_destino_nombre']
                    ?? $event['pais_destino']
                    ?? $event['country_destino']
                    ?? ''
                ));
                $countryCode = strtoupper(trim((string) (
                    $event['destination_country_code']
                    ?? $event['pais_destino_iso2']
                    ?? ''
                )));
                if ($countryName === '' && $countryCode !== '') {
                    $countryName = $countryCode;
                }

                $country = $this->normalizeKardexInternationalCountry($countryName);
                if ($country !== '') {
                    $city = $this->cleanKardexReportText((string) (
                        $event['ciudad_destino']
                        ?? $event['destination_city']
                        ?? ''
                    ));
                    $destinations[$code] = $city !== '' ? $city.' / '.$country : $country;
                    break;
                }
            }

            if (isset($destinations[$code])) {
                continue;
            }

            $enteredBolivia = $events->contains(function (array $event): bool {
                $eventCode = $event['codigo_evento'] ?? $event['eventCode'] ?? $event['event_type_cd'] ?? null;
                if (! is_numeric($eventCode) || ! in_array((int) $eventCode, [31, 34, 38, 76], true)) {
                    return false;
                }

                $offices = [
                    $event['office_code'] ?? null,
                    $event['officeCode'] ?? null,
                    $event['office'] ?? null,
                    $event['oficina'] ?? null,
                ];

                foreach ($offices as $office) {
                    if (preg_match('/^\s*BO[A-Z0-9]{2,}/i', (string) $office) === 1) {
                        return true;
                    }
                }

                return false;
            });

            if ($enteredBolivia) {
                $destinations[$code] = 'BOLIVIA (PLURINATIONAL STATE OF)';
            }
        }

        return $destinations;
    }

    private function normalizeKardexInternationalCountry(string $country): string
    {
        $country = $this->kardexInternationalCountry($this->cleanKardexReportText($country));
        $normalized = $this->normalizeKardexText($country);

        if (in_array($normalized, [
            'BO',
            'BOL',
            'BOLIVIA',
            'BOLIVIA PLURINATIONAL STATE OF',
            'BOLIVIA (PLURINATIONAL STATE OF)',
            'PLURINATIONAL STATE OF BOLIVIA',
        ], true)) {
            return 'BOLIVIA (PLURINATIONAL STATE OF)';
        }

        return $country;
    }

    private function kardexInternationalCountry(string $destination): string
    {
        $parts = explode('/', $destination, 2);
        $country = trim((string) ($parts[1] ?? $parts[0]));

        return $country !== '' ? $country : trim($destination);
    }

    private function lookupKardexDestinationsInBatch(Collection $codes): array
    {
        $codes = $codes
            ->map(fn ($code) => $this->normalizeKardexPackageLookupCode((string) $code))
            ->filter()
            ->unique()
            ->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $destinations = [];
        $models = [
            'ems' => [PaqueteEms::class, ['ciudad']],
            'int' => [PaqueteInt::class, ['destino']],
            'ordi' => [PaqueteOrdi::class, ['pais', 'ciudad']],
            'certi' => [PaqueteCerti::class, ['cuidad']],
        ];

        foreach ($models as $type => [$model, $columns]) {
            foreach ($codes->chunk(400) as $chunk) {
                $values = $chunk->all();
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $rows = $model::query()
                    ->where(function ($query) use ($placeholders, $values): void {
                        $query->whereRaw('trim(upper(codigo)) IN ('.$placeholders.')', $values)
                            ->orWhereRaw("trim(upper(coalesce(cod_especial, ''))) IN (".$placeholders.')', $values);
                    })
                    ->get(array_merge(['codigo', 'cod_especial'], $columns));

                foreach ($rows as $package) {
                    $code = $this->normalizeKardexPackageLookupCode((string) data_get($package, 'codigo', ''));
                    $specialCode = $this->normalizeKardexPackageLookupCode((string) data_get($package, 'cod_especial', ''));
                    $country = $this->cleanKardexReportText((string) data_get($package, 'pais', ''));
                    $city = $this->cleanKardexReportText((string) (
                        data_get($package, 'ciudad')
                        ?? data_get($package, 'cuidad')
                        ?? data_get($package, 'destino')
                        ?? ''
                    ));
                    $destination = $type === 'ordi' && $country !== '' ? $country : $city;

                    foreach ([$code, $specialCode] as $alias) {
                        if ($alias !== '' && $chunk->contains($alias) && $destination !== '') {
                            $destinations[$alias][$type] ??= $destination;
                        }
                    }
                }
            }
        }

        return $destinations;
    }

    private function isKardexInternationalService(string $service): bool
    {
        $service = $this->normalizeKardexText($service);

        return str_contains($service, 'INTERNACIONAL') || str_contains($service, 'ENCOMIENDA RETOUR');
    }

    private function isKardexPostalAdmissionService(string $service): bool
    {
        $service = $this->normalizeKardexText($service);
        if ($service === ''
            || str_contains($service, 'CASILLA')
            || str_contains($service, 'ESTAMPILLA')
            || str_contains($service, 'TARJETA')) {
            return false;
        }

        foreach ([
            'ORDINARIA',
            'ENCOMIENDA',
            'CERTIFICAD',
            'CARTA',
            'LETTER',
            'LC/AO',
            'PAQUETE',
            'EXPRESS',
            'CORRESPONDENCIA',
        ] as $marker) {
            if (str_contains($service, $marker)) {
                return true;
            }
        }

        return preg_match('/(^|[^A-Z])EMS([^A-Z]|$)/', $service) === 1;
    }

    private function kardexDestinationLookupOrder(string $code, string $service = ''): array
    {
        $service = $this->normalizeKardexText($service);

        if (str_contains($service, 'EMS') && ! str_contains($service, 'INTERNACIONAL')) {
            return ['ems', 'int', 'ordi', 'certi'];
        }
        if (str_contains($service, 'ORDINARIA') || str_contains($service, 'ORDINARIAS')) {
            return str_contains($service, 'INTERNACIONAL') ? ['int', 'ordi', 'ems', 'certi'] : ['ordi', 'ems', 'int', 'certi'];
        }
        if (str_contains($service, 'CERTIFICADO') || str_contains($service, 'CERTIFICADAS')) {
            return str_contains($service, 'INTERNACIONAL') ? ['int', 'certi', 'ems', 'ordi'] : ['certi', 'int', 'ems', 'ordi'];
        }
        if (str_contains($service, 'ENCOMIENDA') || str_contains($service, 'INTERNACIONAL')) {
            return ['int', 'ems', 'ordi', 'certi'];
        }

        return $this->looksLikeInternationalBolivianTracking($code)
            ? ['int', 'ems', 'ordi', 'certi']
            : ['ems', 'ordi', 'certi', 'int'];
    }

    private function looksLikeInternationalBolivianTracking(string $code): bool
    {
        return (bool) preg_match('/^(EE|CP|RR)[A-Z0-9]+BO$/i', $code);
    }

    private function normalizeKardexPackageLookupCode(string $code): string
    {
        $code = strtoupper(trim($code));
        $code = preg_replace('/^VFC-\d+\s+PAQUETES:\s*/i', '', $code) ?? $code;
        $code = preg_replace('/^VQC-\d+\s+PAQUETES:\s*/i', '', $code) ?? $code;
        $code = preg_replace('/^SRVE-\d+\s*-\s*/i', '', $code) ?? $code;
        $code = trim($code);

        return $this->isServiceReferenceCode($code) ? '' : $code;
    }

    private function cleanKardexReportText(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?: '';
        if ($value === '' || $value === '-') {
            return '';
        }

        return mb_strtoupper($value);
    }
    private function resolveKardexServiceDisplayName(string $service): string
    {
        $service = str_replace('_', ' ', $service);
        $service = strtoupper(trim(preg_replace('/\s+/', ' ', $service) ?? $service));
        if ($service === '') {
            return 'SIN DETALLE';
        }

        if (str_contains($this->normalizeKardexText($service), 'PAGO DE CASILLA')) {
            return $service;
        }

        $beforeDash = strtoupper(trim((string) (preg_split('/\s+-\s*/', $service, 2)[0] ?? $service)));
        $beforeDashNormalized = $this->normalizeKardexText($beforeDash);
        $normalized = $this->normalizeKardexText($service);

        $map = [
            'SERVICIO AEROLINEA' => 'AEROLINEA',
            'AEROLINEA' => 'AEROLINEA',
            'SERVICIO CASILLA' => 'CASILLA',
            'CASILLA' => 'CASILLA',
            'SERVICIO CERTIFICADO INTERNACIONAL' => 'CERTIFICADO INTERNACIONAL',
            'CERTIFICADO INTERNACIONAL' => 'CERTIFICADO INTERNACIONAL',
            'SERVICIO CERTIFICADO' => 'CERTIFICADO',
            'CERTIFICADO' => 'CERTIFICADO',
            'SERVICIO CERTIFICADAS' => 'CERTIFICADAS',
            'CERTIFICADAS' => 'CERTIFICADAS',
            'SERVICIO CONTRATOS' => 'CONTRATOS',
            'CONTRATOS' => 'CONTRATOS',
            'SERVICIO ECA INTERNACIONAL' => 'ECA INTERNACIONAL',
            'ECA INTERNACIONAL' => 'ECA INTERNACIONAL',
            'SERVICIO ECA' => 'ECA',
            'ECA' => 'ECA',
            'SERVICIO EMS INTERNACIONAL' => 'EMS INTERNACIONAL',
            'EMS INTERNACIONAL' => 'EMS INTERNACIONAL',
            'SERVICIO EMS LOCAL COBERTURA 1' => 'EMS LOCAL COBERTURA 1',
            'EMS LOCAL COBERTURA 1' => 'EMS LOCAL COBERTURA 1',
            'SERVICIO EMS LOCAL COBERTURA 2' => 'EMS LOCAL COBERTURA 2',
            'EMS LOCAL COBERTURA 2' => 'EMS LOCAL COBERTURA 2',
            'SERVICIO EMS LOCAL COBERTURA 3' => 'EMS LOCAL COBERTURA 3',
            'EMS LOCAL COBERTURA 3' => 'EMS LOCAL COBERTURA 3',
            'SERVICIO EMS LOCAL COBERTURA 4' => 'EMS LOCAL COBERTURA 4',
            'EMS LOCAL COBERTURA 4' => 'EMS LOCAL COBERTURA 4',
            'SERVICIO EMS NACIONAL' => 'EMS NACIONAL',
            'EMS NACIONAL' => 'EMS NACIONAL',
            'SERVICIO EMS' => 'EMS',
            'EMS' => 'EMS',
            'SERVICIO ENCOMIENDA INTERNACIONAL' => 'ENCOMIENDA INTERNACIONAL',
            'ENCOMIENDA INTERNACIONAL' => 'ENCOMIENDA INTERNACIONAL',
            'SERVICIO ENCOMIENDA RETOUR' => 'ENCOMIENDA RETOUR',
            'ENCOMIENDA RETOUR' => 'ENCOMIENDA RETOUR',
            'SERVICIO ENCOMIENDA' => 'ENCOMIENDA',
            'ENCOMIENDA' => 'ENCOMIENDA',
            'SERVICIO VENTA DE ESTAMPILLAS' => 'ESTAMPILLAS',
            'VENTA DE ESTAMPILLAS' => 'ESTAMPILLAS',
            'ESTAMPILLAS' => 'ESTAMPILLAS',
            'SERVICIO ORDINARIA INTERNACIONAL' => 'ORDINARIAS INTERNACIONAL',
            'SERVICIO ORDINARIAS INTERNACIONAL' => 'ORDINARIAS INTERNACIONAL',
            'ORDINARIA INTERNACIONAL' => 'ORDINARIAS INTERNACIONAL',
            'ORDINARIAS INTERNACIONAL' => 'ORDINARIAS INTERNACIONAL',
            'SERVICIO ORDINARIAS' => 'ORDINARIAS',
            'SERVICIO ORDINARIA' => 'ORDINARIAS',
            'ORDINARIAS' => 'ORDINARIAS',
            'SERVICIO CIUDADES INTERMEDIAS TRINIDAD COBIJA' => 'CIUDADES INTERMEDIAS TRINIDAD COBIJA',
            'CIUDADES INTERMEDIAS TRINIDAD COBIJA' => 'CIUDADES INTERMEDIAS TRINIDAD COBIJA',
            'SERVICIO CIUDADES INTERMEDIAS' => 'CIUDADES INTERMEDIAS',
            'CIUDADES INTERMEDIAS' => 'CIUDADES INTERMEDIAS',
            'SERVICIO SUPER EXPRESS NACIONAL' => 'SUPER EXPRESS NACIONAL',
            'SUPER EXPRESS NACIONAL' => 'SUPER EXPRESS NACIONAL',
            'SERVICIO TRINIDAD COBIJA' => 'TRINIDAD COBIJA',
            'TRINIDAD COBIJA' => 'TRINIDAD COBIJA',
            'SERVICIO INTERNACIONAL' => 'INTERNACIONAL',
            'INTERNACIONAL' => 'INTERNACIONAL',
            'SERVICIO VENTA DE TARJETA POSTAL' => 'TARJETA POSTAL',
            'VENTA DE TARJETA POSTAL' => 'TARJETA POSTAL',
            'TARJETA POSTAL' => 'TARJETA POSTAL',
            'VENTANILLA' => 'VENTANILLA',
        ];

        $displayName = $map[$beforeDashNormalized] ?? $map[$normalized] ?? $beforeDash;
        if (str_contains($beforeDashNormalized, 'CONTRATO') || str_contains($beforeDashNormalized, 'ECA')) {
            return $this->appendKardexServiceDetail($displayName, $service);
        }

        return $displayName;
    }
    private function resolveKardexOriginCode(string $regional, mixed $codigoSucursal = null): string
    {
        $normalized = $this->normalizeKardexText($regional);
        $map = [
            'LA PAZ' => 'LPB',
            'COCHABAMBA' => 'CBB',
            'SANTA CRUZ' => 'SCZ',
            'SANTA CRUZ DE LA SIERRA' => 'SCZ',
            'ORURO' => 'ORU',
            'POTOSI' => 'POI',
            'CHUQUISACA' => 'SRE',
            'SUCRE' => 'SRE',
            'TARIJA' => 'TJA',
            'BENI' => 'TDD',
            'TRINIDAD' => 'TDD',
            'PANDO' => 'CIJ',
            'COBIJA' => 'CIJ',
        ];

        return $map[$normalized] ?? (trim((string) $codigoSucursal) !== '' ? trim((string) $codigoSucursal) : '-');
    }

    private function resolveKardexCorrespondenceType(string $tipoServicio, string $guiaCasilla, string $detalle = ''): string
    {
        $normalized = $this->normalizeKardexText(str_replace('_', ' ', $tipoServicio . ' ' . $detalle . ' ' . $guiaCasilla));
        $code = strtoupper(trim((string) (explode(',', $guiaCasilla, 2)[0] ?? '')));

        // Aerolínea is its own billed product; do not classify a mixed service
        // description as Tarjeta Postal just because both words are present.
        if (str_contains($normalized, 'AEROLINEA')) {
            return 'AEROLINEA';
        }

        if (str_contains($normalized, 'CASILLA')) {
            return $this->formatKardexCasillaLabel($tipoServicio, $guiaCasilla);
        }

        if (str_contains($this->normalizeKardexText($tipoServicio), 'CONTRATO')) {
            return $this->appendKardexServiceDetail('SERVICIO DE PAGO DE CONTRATOS', $tipoServicio, $detalle);
        }

        $serviceName = $this->normalizeKardexText($tipoServicio);
        if (str_contains($serviceName, 'ECA')) {
            $label = str_contains($normalized, 'INTERNACIONAL')
                ? 'SERVICIO ECA INTERNACIONAL'
                : 'SERVICIO ECA';

            return $this->appendKardexServiceDetail($label, $tipoServicio, $detalle);
        }

        // Keep domestic EMS admissions classified by the billed product.
        // Domestic identifiers do not use the UPU S10 format handled below.
        if (str_contains($normalized, 'EMS')) {
            if (preg_match('/EMS\s+LOCAL\s+COBERTURA\s+([1-4])/', $normalized, $matches) === 1) {
                return 'EMS LOCAL COBERTURA ' . $matches[1];
            }

            if (str_contains($normalized, 'EJE TRONCAL')) {
                return 'EMS NACIONAL (EJE TRONCAL)';
            }

            if (preg_match('/(^|[^A-Z])NACIONAL([^A-Z]|$)/', $normalized) === 1) {
                return 'EMS NACIONAL';
            }
        }

        // Apply the international service mapping defined for Kardex.
        if ($this->isKardexS10TypeCode($code)) {
            $prefix = substr($code, 0, 2);
            if ($prefix[0] === 'E') {
                return 'EMS INTERNACIONAL';
            }

            if ($prefix[0] === 'C') {
                return 'ENCOMIENDA';
            }

            if ($prefix[0] === 'R'
                || in_array($prefix, ['LP', 'LX', 'LT'], true)) {
                return 'CERTIFICADO';
            }

            if ($prefix[0] === 'U') {
                return 'ORDINARIA';
            }
        }

        // En el correo internacional, Letters (LC/AO) se registra como ordinaria.
        if (str_contains($normalized, 'LETTERS') || str_contains($normalized, 'LC/AO')) {
            return 'ORDINARIA';
        }

        // Fallback por nombre de producto cuando no hay un código S10 clasificable.
        if (str_contains($normalized, 'PARCEL') && str_starts_with($code, 'C')) {
            return 'ENCOMIENDA';
        }

        if (str_contains($normalized, 'CERTIFICADO')) {
            return 'CERTIFICADO';
        }

        if (str_contains($normalized, 'ENCOMIENDA')) {
            return 'ENCOMIENDA';
        }

        if (str_contains($normalized, 'EMS')) {
            // "INTERNACIONAL" contains the substring "NACIONAL"; national
            // labels were handled above with a whole-word check.
            if (str_contains($normalized, 'INTERNACIONAL')) {
                return 'EMS INTERNACIONAL';
            }

            return 'EMS';
        }

        return strtoupper($tipoServicio !== '' ? $tipoServicio : 'SIN DETALLE');
    }

    private function appendKardexServiceDetail(string $label, string ...$sources): string
    {
        $normalizedLabel = $this->normalizeKardexText($label);

        foreach ($sources as $source) {
            $detail = $this->kardexServiceDetailSuffix($source);
            if ($detail === '') {
                continue;
            }

            $normalizedDetail = $this->normalizeKardexText($detail);
            if ($normalizedDetail !== '' && str_contains($normalizedLabel, $normalizedDetail)) {
                continue;
            }

            return $label . ' - ' . mb_strtoupper($detail);
        }

        return $label;
    }

    private function kardexServiceDetailSuffix(string $value): string
    {
        $parts = preg_split('/\s+[-\x{2013}\x{2014}]\s*/u', trim($value), 2) ?: [];

        return trim((string) ($parts[1] ?? ''));
    }

    private function formatKardexCasillaLabel(string $tipoServicio, string $guiaCasilla): string
    {
        $tipoServicio = trim($tipoServicio);
        $guiaCasilla = trim($guiaCasilla);
        $candidate = $guiaCasilla !== '' && $guiaCasilla !== '-' && str_contains($this->normalizeKardexText($guiaCasilla), 'PAGO DE CASILLA')
            ? $guiaCasilla
            : $tipoServicio;

        if ($candidate === '' || ! str_contains($this->normalizeKardexText($candidate), 'PAGO DE CASILLA')) {
            $candidate = trim($tipoServicio . ' ' . ($guiaCasilla !== '-' ? $guiaCasilla : ''));
        }

        $candidate = preg_replace('/\s+/', ' ', $candidate) ?: 'PAGO DE CASILLA';

        return mb_strtoupper(trim($candidate));
    }

    private function normalizeKardexText(string $value): string
    {
        // Str::ascii removes accents consistently. iconv's TRANSLIT can turn
        // Spanish accented vowels into apostrophes on Windows (e.g. ADMISIÓN
        // -> ADMISI'ON), which breaks Kardex operation grouping.
        $normalized = Str::ascii($value);

        return strtoupper(trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized));
    }

    private function resolveKardexRegional(array $row): string
    {
        $regional = trim((string) (
            data_get($row, 'origen_sucursal_municipio')
            ?: data_get($row, 'origen_sucursal_departamento')
            ?: data_get($row, 'origen_sucursal_nombre')
            ?: data_get($row, 'origen_usuario_ciudad')
        ));

        return $regional !== '' ? $regional : '-';
    }

    private function resolveKardexDestination(array $row, string $mode): string
    {
        $values = collect($row['detalle_codigos'] ?? [])
            ->map(function ($entry) use ($mode) {
                $source = data_get($entry, 'source');
                $country = collect([
                    data_get($source, 'resumen_origen.pais_destino'),
                    data_get($source, 'resumen_origen.pais'),
                    data_get($source, 'pais_destino'),
                    data_get($source, 'pais'),
                    data_get($source, 'destino_pais'),
                ])->first(fn ($value) => trim((string) $value) !== '');
                $city = collect([
                    data_get($source, 'resumen_origen.ciudad_destino'),
                    data_get($source, 'resumen_origen.ciudad'),
                    data_get($source, 'ciudad_destino'),
                    data_get($source, 'ciudad'),
                    data_get($source, 'destino_ciudad'),
                ])->first(fn ($value) => trim((string) $value) !== '');

                return $mode === 'region'
                    ? trim((string) ($country ?: $city))
                    : trim(implode(' / ', array_filter([trim((string) $city), trim((string) $country)])));
            })
            ->filter()
            ->unique()
            ->values();

        return $values->isNotEmpty() ? $values->implode(', ') : '-';
    }

    private function extractDetailCodesFromItems(Collection $items): Collection
    {
        return $items
            ->flatMap(function ($item) {
                $candidates = [
                    trim((string) data_get($item, 'resumen_origen.codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_item', '')),
                    trim((string) data_get($item, 'codigo', '')),
                    trim((string) data_get($item, 'codigo_item', '')),
                ];

                return collect($candidates)
                    ->filter(fn ($code) => $code !== '' && ! $this->isServiceReferenceCode((string) $code))
                    ->take(1)
                    ->all();
            })
            ->filter()
            ->unique()
            ->values();
    }

    private function buildDetailCodeEntriesFromItems(Collection $items): Collection
    {
        return $items
            ->map(function ($item) {
                $candidates = collect([
                    trim((string) data_get($item, 'resumen_origen.codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_detalle_enviado', '')),
                    trim((string) data_get($item, 'codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_item', '')),
                    trim((string) data_get($item, 'codigo', '')),
                    trim((string) data_get($item, 'codigo_item', '')),
                ])->filter();

                $codigo = '';
                $destinoHint = '';
                foreach ($candidates as $candidate) {
                    $candidate = $this->normalizeKardexPackageLookupCode((string) $candidate);
                    if ($candidate === '') {
                        continue;
                    }

                    if (preg_match('/^(?:VFC|VQC)-\d+/i', $candidate) === 1) {
                        continue;
                    }

                    $countryHint = $this->kardexCountryDestinationHint($candidate);
                    if ($countryHint !== '') {
                        $destinoHint = $destinoHint !== '' ? $destinoHint : $countryHint;
                        continue;
                    }

                    // A tracking code must contain a digit or a separator.
                    // This prevents a destination label such as FRANCIA from
                    // being shown or sent to SITRA as the shipment identifier.
                    if (preg_match('/[0-9-]/', $candidate) !== 1) {
                        continue;
                    }

                    $codigo = $candidate;
                    break;
                }

                $servicio = $this->resolveKardexItemServiceName($item);
                $ciudadDestino = collect([
                    data_get($item, 'resumen_origen.ciudad_destino'),
                    data_get($item, 'ciudad_destino'),
                    data_get($item, 'resumen_origen.ciudad'),
                    data_get($item, 'ciudad'),
                    data_get($item, 'resumen_origen.destino_ciudad'),
                    data_get($item, 'destino_ciudad'),
                ])->map(fn ($value) => $this->cleanKardexReportText((string) $value))
                    ->first(fn (string $value) => $value !== '');
                $paisDestino = collect([
                    data_get($item, 'resumen_origen.pais_destino'),
                    data_get($item, 'pais_destino'),
                    data_get($item, 'resumen_origen.pais'),
                    data_get($item, 'pais'),
                    data_get($item, 'destino_pais'),
                ])->map(function ($value): string {
                    $value = $this->cleanKardexReportText((string) $value);

                    return $value !== '' ? ($this->kardexCountryDestinationHint($value) ?: $value) : '';
                })->first(fn (string $value) => $value !== '');
                $isInternational = $this->isKardexInternationalService($servicio)
                    || $this->isKardexUpuTrackingCode($codigo);
                if ($ciudadDestino !== '') {
                    $paisDestino = $paisDestino ?: ($isInternational ? $destinoHint : '');
                    $destinoHint = $isInternational && $paisDestino !== ''
                        ? $ciudadDestino.' / '.$paisDestino
                        : $ciudadDestino;
                } elseif ($paisDestino !== '') {
                    $destinoHint = $paisDestino;
                }

                if ($codigo === '' && $destinoHint === '' && ! $this->isPackageCartItem($item)) {
                    return null;
                }

                return [
                    'servicio' => $servicio,
                    'codigo' => $codigo,
                    'destino_hint' => $destinoHint,
                    'source' => $item,
                ];
            })
            ->filter()
            ->unique(function ($entry): string {
                $code = mb_strtoupper(trim((string) data_get($entry, 'codigo', '')));
                $source = data_get($entry, 'source');
                $identity = $code !== '' ? $code : (is_object($source) ? (string) spl_object_id($source) : uniqid('', true));

                return mb_strtoupper(trim((string) data_get($entry, 'servicio', ''))).'|'.$identity;
            })
            ->values();
    }

    private function kardexCountryDestinationHint(string $value): string
    {
        $value = $this->normalizeKardexText($value);
        $countries = [
            'AR' => 'ARGENTINA', 'ARGENTINA' => 'ARGENTINA',
            'AT' => 'AUSTRIA', 'AUSTRIA' => 'AUSTRIA',
            'AU' => 'AUSTRALIA', 'AUSTRALIA' => 'AUSTRALIA',
            'BE' => 'BELGICA', 'BELGICA' => 'BELGICA', 'BELGIUM' => 'BELGICA',
            'BO' => 'BOLIVIA', 'BOLIVIA' => 'BOLIVIA',
            'BG' => 'BULGARIA', 'BULGARIA' => 'BULGARIA',
            'BR' => 'BRASIL', 'BRASIL' => 'BRASIL', 'BRAZIL' => 'BRASIL',
            'CA' => 'CANADA', 'CANADA' => 'CANADA',
            'CH' => 'SUIZA', 'SUIZA' => 'SUIZA', 'SWITZERLAND' => 'SUIZA',
            'CL' => 'CHILE', 'CHILE' => 'CHILE',
            'CN' => 'CHINA', 'CHINA' => 'CHINA',
            'CO' => 'COLOMBIA', 'COLOMBIA' => 'COLOMBIA',
            'CZ' => 'REPUBLICA CHECA', 'REPUBLICA CHECA' => 'REPUBLICA CHECA', 'CZECH REPUBLIC' => 'REPUBLICA CHECA',
            'DK' => 'DINAMARCA', 'DINAMARCA' => 'DINAMARCA', 'DENMARK' => 'DINAMARCA',
            'DE' => 'ALEMANIA', 'ALEMANIA' => 'ALEMANIA', 'GERMANY' => 'ALEMANIA',
            'DO' => 'REPUBLICA DOMINICANA', 'REPUBLICA DOMINICANA' => 'REPUBLICA DOMINICANA',
            'EC' => 'ECUADOR', 'ECUADOR' => 'ECUADOR',
            'ES' => 'ESPANA', 'ESPANA' => 'ESPANA', 'SPAIN' => 'ESPANA',
            'FI' => 'FINLANDIA', 'FINLANDIA' => 'FINLANDIA', 'FINLAND' => 'FINLANDIA',
            'FR' => 'FRANCIA', 'FRANCIA' => 'FRANCIA', 'FRANCE' => 'FRANCIA',
            'GR' => 'GRECIA', 'GRECIA' => 'GRECIA', 'GREECE' => 'GRECIA',
            'GB' => 'REINO UNIDO', 'REINO UNIDO' => 'REINO UNIDO', 'UNITED KINGDOM' => 'REINO UNIDO',
            'HK' => 'HONG KONG', 'HONG KONG' => 'HONG KONG',
            'HU' => 'HUNGRIA', 'HUNGRIA' => 'HUNGRIA', 'HUNGARY' => 'HUNGRIA',
            'ID' => 'INDONESIA', 'INDONESIA' => 'INDONESIA',
            'IE' => 'IRLANDA', 'IRLANDA' => 'IRLANDA', 'IRELAND' => 'IRLANDA',
            'IL' => 'ISRAEL', 'ISRAEL' => 'ISRAEL',
            'IN' => 'INDIA', 'INDIA' => 'INDIA',
            'IT' => 'ITALIA', 'ITALIA' => 'ITALIA',
            'JP' => 'JAPON', 'JAPON' => 'JAPON', 'JAPAN' => 'JAPON',
            'KR' => 'COREA DEL SUR', 'COREA DEL SUR' => 'COREA DEL SUR', 'SOUTH KOREA' => 'COREA DEL SUR',
            'LU' => 'LUXEMBURGO', 'LUXEMBURGO' => 'LUXEMBURGO', 'LUXEMBOURG' => 'LUXEMBURGO',
            'MX' => 'MEXICO', 'MEXICO' => 'MEXICO',
            'MY' => 'MALASIA', 'MALASIA' => 'MALASIA', 'MALAYSIA' => 'MALASIA',
            'NL' => 'PAISES BAJOS', 'NLD' => 'PAISES BAJOS', 'PAISES BAJOS' => 'PAISES BAJOS', 'NETHERLANDS' => 'PAISES BAJOS',
            'NZ' => 'NUEVA ZELANDA', 'NUEVA ZELANDA' => 'NUEVA ZELANDA', 'NEW ZEALAND' => 'NUEVA ZELANDA',
            'PE' => 'PERU', 'PERU' => 'PERU',
            'PL' => 'POLONIA', 'POLONIA' => 'POLONIA', 'POLAND' => 'POLONIA',
            'PT' => 'PORTUGAL', 'PORTUGAL' => 'PORTUGAL',
            'PY' => 'PARAGUAY', 'PARAGUAY' => 'PARAGUAY',
            'RO' => 'RUMANIA', 'RUMANIA' => 'RUMANIA', 'ROMANIA' => 'RUMANIA',
            'RU' => 'RUSIA', 'RUSIA' => 'RUSIA', 'RUSSIA' => 'RUSIA',
            'SE' => 'SUECIA', 'SUECIA' => 'SUECIA', 'SWEDEN' => 'SUECIA',
            'SG' => 'SINGAPUR', 'SINGAPUR' => 'SINGAPUR', 'SINGAPORE' => 'SINGAPUR',
            'TH' => 'TAILANDIA', 'TAILANDIA' => 'TAILANDIA', 'THAILAND' => 'TAILANDIA',
            'TR' => 'TURQUIA', 'TURQUIA' => 'TURQUIA', 'TURKEY' => 'TURQUIA',
            'TW' => 'TAIWAN', 'TAIWAN' => 'TAIWAN',
            'UA' => 'UCRANIA', 'UCRANIA' => 'UCRANIA', 'UKRAINE' => 'UCRANIA',
            'US' => 'ESTADOS UNIDOS', 'ESTADOS UNIDOS' => 'ESTADOS UNIDOS', 'UNITED STATES' => 'ESTADOS UNIDOS',
            'UY' => 'URUGUAY', 'URUGUAY' => 'URUGUAY',
            'VE' => 'VENEZUELA', 'VENEZUELA' => 'VENEZUELA',
            'VN' => 'VIETNAM', 'VIETNAM' => 'VIETNAM',
            'ZA' => 'SUDAFRICA', 'SUDAFRICA' => 'SUDAFRICA', 'SOUTH AFRICA' => 'SUDAFRICA',
        ];

        return $countries[$value] ?? '';
    }

    private function isPackageCartItem(object|array $item): bool
    {
        $origin = ltrim(trim((string) data_get($item, 'origen_tipo', '')), '\\');
        $packageOrigins = [PaqueteEms::class, PaqueteInt::class, PaqueteOrdi::class, PaqueteCerti::class];
        if (in_array($origin, array_map(fn ($type) => ltrim($type, '\\'), $packageOrigins), true)) {
            return true;
        }

        $codigo = strtoupper(trim((string) data_get($item, 'codigo', '')));
        $codigoItem = strtoupper(trim((string) data_get($item, 'codigo_item', '')));
        $codigoPaquete = strtoupper(trim((string) data_get($item, 'codigo_paquete', '')));
        $resumenCodigo = strtoupper(trim((string) data_get($item, 'resumen_origen.codigo', '')));
        $resumenCodigoItem = strtoupper(trim((string) data_get($item, 'resumen_origen.codigo_item', '')));
        $resumenCodigoPaquete = strtoupper(trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')));

        $references = collect([
            $codigoPaquete,
            $resumenCodigoPaquete,
            $codigo,
            $codigoItem,
            $resumenCodigo,
            $resumenCodigoItem,
        ])->filter();

        if ($references->isEmpty()) {
            return false;
        }

        $originHints = strtoupper(trim(implode(' ', array_filter([
            (string) data_get($item, 'origen_tipo', ''),
            (string) data_get($item, 'titulo', ''),
            (string) data_get($item, 'nombre_servicio', ''),
            (string) data_get($item, 'resumen_origen.descripcion_servicio', ''),
            (string) data_get($item, 'resumen_origen.servicio_nombre', ''),
        ]))));

        $hasPackageReference = $references->contains(function (string $reference): bool {
            return ! $this->isServiceReferenceCode($reference);
        });

        if (! $hasPackageReference) {
            return false;
        }

        return ! str_contains($originHints, 'SERVICIO')
            && ! str_contains($originHints, 'ADMISION')
            && ! str_contains($originHints, 'EXTRA');
    }

    private function isServiceReferenceCode(string $reference): bool
    {
        $reference = strtoupper(trim($reference));

        if ($reference === '') {
            return false;
        }

        return str_starts_with($reference, 'SRVE-')
            || str_starts_with($reference, 'SERV-')
            || str_starts_with($reference, 'SERVICIO-')
            // The reports API uses ITEM-{id} as an internal row key when an
            // invoice item has no postal tracking code. It is not a shipment.
            || preg_match('/^ITEM-\d+$/', $reference) === 1;
    }

    private function normalizeItems(mixed $items): Collection
    {
        $rows = $items instanceof Collection
            ? $items
            : (is_array($items) ? collect($items) : collect());

        return $rows
            ->map(fn ($item) => is_array($item) ? (object) $item : $item)
            ->filter(fn ($item) => is_object($item))
            ->values();
    }

    private function lookupLocalKardexPackageServices(Collection $pdfRows): array
    {
        $packageModels = [
            ltrim(PaqueteEms::class, '\\') => ['model' => PaqueteEms::class, 'relation' => 'tarifario.servicio'],
            ltrim(PaqueteInt::class, '\\') => ['model' => PaqueteInt::class, 'relation' => 'servicio'],
            ltrim(PaqueteOrdi::class, '\\') => ['model' => PaqueteOrdi::class, 'relation' => 'servicio'],
            ltrim(PaqueteCerti::class, '\\') => ['model' => PaqueteCerti::class, 'relation' => 'servicio'],
        ];
        $idsByOrigin = [];
        $codes = [];

        foreach ($pdfRows as $row) {
            foreach ($row['detalle_codigos'] ?? [] as $entry) {
                $source = $entry['source'] ?? null;
                $code = $this->normalizeKardexPackageLookupCode((string) ($entry['codigo'] ?? ''));
                $origin = is_object($source) ? ltrim(trim((string) data_get($source, 'origen_tipo', '')), '\\') : '';
                $originId = is_object($source) ? (int) data_get($source, 'origen_id', 0) : 0;
                $hasLocalSource = $originId > 0
                    && isset($packageModels[$origin]);

                if ($hasLocalSource) {
                    $idsByOrigin[$origin][] = $originId;
                } elseif ($code !== '') {
                    // Some Kardex rows only carry the tracking code. Resolve
                    // those against local package tables so IPS's generic
                    // "EMS" label cannot hide the admitted tariff service.
                    $codes[] = $code;
                }
            }
        }

        $services = [];
        $codes = array_values(array_unique($codes));
        foreach ($packageModels as $origin => $definition) {
            $ids = array_values(array_unique($idsByOrigin[$origin] ?? []));
            if ($ids === [] && $codes === []) {
                continue;
            }

            $packages = $definition['model']::query()
                ->with($definition['relation'])
                ->where(function ($query) use ($ids, $codes): void {
                    if ($ids !== []) {
                        $query->whereIn('id', $ids);
                    }
                    if ($codes !== []) {
                        $ids !== [] ? $query->orWhereIn('codigo', $codes) : $query->whereIn('codigo', $codes);
                    }
                })
                ->get();

            foreach ($packages as $package) {
                $name = $origin === ltrim(PaqueteEms::class, '\\')
                    ? trim((string) ($package->tarifario?->servicio?->nombre_servicio ?? ''))
                    : trim((string) ($package->servicio?->nombre_servicio ?? ''));
                if ($name !== '') {
                    $services[$origin.'#'.$package->id] = $name;
                    $code = $this->normalizeKardexPackageLookupCode((string) ($package->codigo ?? ''));
                    if ($code !== '') {
                        $services['CODE#'.$code] ??= $name;
                        $services['ORIGIN#'.$code] ??= $origin;
                    }
                }
            }
        }

        return $services;
    }

    private function buildTicketData(object $cart, $user, array $snapshot = []): array
    {
        $respuesta = (array) ($cart->respuesta_emision ?? []);
        $numeroFactura = trim((string) (data_get($respuesta, 'factura.nroFactura') ?? data_get($respuesta, 'factura.numeroFactura') ?? data_get($respuesta, 'numeroFactura') ?? ''));
        $cuf = trim((string) (data_get($respuesta, 'factura.cuf') ?? data_get($respuesta, 'cuf') ?? ''));
        $nit = preg_replace('/\D+/', '', (string) (data_get($respuesta, 'factura.nitEmisor') ?? data_get($respuesta, 'nitEmisor') ?? config('services.facturacion_bridge.nit_emisor') ?? '')) ?: 'S/N';
        $qrPayload = trim((string) (data_get($respuesta, 'factura.qrCode') ?? data_get($respuesta, 'factura.qrUrl') ?? data_get($respuesta, 'consultaSefe.qrUrl') ?? ''));
        if ($qrPayload === '' && $nit !== '' && $cuf !== '' && $numeroFactura !== '') {
            $qrPayload = 'https://pilotosiat.impuestos.gob.bo/consulta/QR?nit=' . urlencode($nit) . '&cuf=' . urlencode($cuf) . '&numero=' . urlencode($numeroFactura) . '&t=1';
        }
        $qrImage = null;
        if ($qrPayload !== '') {
            try {
                $qrImage = (new DNS2D())->getBarcodePNG($qrPayload, 'QRCODE,H', 5, 5);
            } catch (\Throwable) {
                $qrImage = null;
            }
        }
        $sucursal = $user?->sucursal;

        return [
            'empresa' => config('app.name') ?: 'Agencia Boliviana de Correos',
            'sucursal' => trim((string) ($sucursal->nombre ?? $sucursal->descripcion ?? 'Sucursal')),
            'direccion' => trim((string) (data_get($respuesta, 'factura.direccion') ?? '')),
            'telefono' => trim((string) (data_get($respuesta, 'factura.telefono') ?? ($sucursal->telefono ?? ''))),
            'nit' => $nit,
            'orden' => trim((string) ($cart->codigo_orden ?? ('VENT-' . $cart->id))),
            'nombre' => trim((string) ($cart->razon_social ?: 'SIN NOMBRE')),
            'documento' => trim((string) ($cart->numero_documento ?: '99003')),
            'numero_factura' => $numeroFactura !== '' ? $numeroFactura : 'S/N',
            'comprobante_label' => strtolower(trim((string) ($cart->metodo_pago ?? 'efectivo'))) === 'qr' ? 'Referencia QR' : 'Factura N°',
            'comprobante_valor' => strtolower(trim((string) ($cart->metodo_pago ?? 'efectivo'))) === 'qr'
                ? trim((string) ($cart->qr_transaction_id ?? $cart->codigo_orden ?? 'S/N'))
                : ($numeroFactura !== '' ? $numeroFactura : 'S/N'),
            'fecha' => !empty($cart->emitido_en) ? date('d/m/Y H:i:s', strtotime((string) $cart->emitido_en)) : '-',
            'importe' => round((float) $cart->total, 2),
            'metodo_pago' => match (strtolower(trim((string) ($cart->metodo_pago ?? 'efectivo')))) {
                'qr' => 'Pago QR',
                default => 'Pago de contado',
            },
            'qr_payload' => $qrPayload,
            'qr_image' => $qrImage,
            'cuf' => $cuf,
            'pdf_url' => $this->normalizeSefePublicUrl(trim((string) data_get($respuesta, 'factura.pdfUrl', ''))),
            'ips_snapshot' => $snapshot,
        ];
    }

    private function sefePublicBaseUrl(): string
    {
        return rtrim((string) config('services.facturacion_bridge.sefe_public_base_url', 'https://sefe.agetic.gob.bo'), '/');
    }

    private function sefePublicPdfUrl(string $cuf): string
    {
        return $this->sefePublicBaseUrl() . '/public/facturas_pdf/' . ltrim($cuf, '/') . '.pdf';
    }

    private function normalizeSefePublicUrl(?string $url): string
    {
        $resolvedUrl = trim((string) $url);
        if ($resolvedUrl === '') {
            return '';
        }

        $path = (string) parse_url($resolvedUrl, PHP_URL_PATH);
        if ($path === '' || !str_starts_with($path, '/public/')) {
            return $resolvedUrl;
        }

        return $this->sefePublicBaseUrl() . $path;
    }
}

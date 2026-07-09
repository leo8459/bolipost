<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FacturacionCartService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Milon\Barcode\DNS2D;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MisVentasController extends Controller
{
    public function index(Request $request, FacturacionCartService $service): View
    {
        return $this->renderVentasPage($request, $service, 'own');
    }

    public function branchIndex(Request $request, FacturacionCartService $service): View
    {
        return $this->renderVentasPage($request, $service, 'branch');
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
        return $this->exportVentasPdf($request, $service, 'own');
    }

    public function branchExportPdf(Request $request, FacturacionCartService $service): Response
    {
        return $this->exportVentasPdf($request, $service, 'branch');
    }

    private function renderVentasPage(Request $request, FacturacionCartService $service, string $scope): View
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
            'pageContext' => $this->pageContextForScope($scope),
            'branchCashierSummary' => $extra['branch_cashiers'] ?? collect(),
        ]);
    }

    private function exportVentasPdf(Request $request, FacturacionCartService $service, string $scope): Response
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
        ])->setPaper('a4', 'portrait');

        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function ticket(Request $request, int $cart, FacturacionCartService $service): StreamedResponse
    {
        $user = $this->authorizeVentasViewer($request->user(), $request->query('scope') === 'branch' ? 'branch' : 'own');
        $sourceUser = $this->resolveSourceUserForRequest($request, $user);
        $venta = $this->resolveVentaForDetail($service, $sourceUser, $cart);

        abort_unless($venta, 404, 'No se encontro la venta solicitada.');

        $ticket = $this->buildTicketData($venta, $user);
        $pdf = Pdf::loadView('facturacion.mis-ventas-ticket', ['cart' => $venta, 'ticket' => $ticket])->setPaper([0, 0, 226.77, 680], 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), 'ticket-' . ($venta->codigo_orden ?: ('venta-' . $venta->id)) . '.pdf');
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

                return [
                    'id' => (int) data_get($item, 'id', 0),
                    'codigo' => trim((string) data_get($item, 'codigo', '')),
                    'origen_tipo' => trim((string) data_get($item, 'origen_tipo', '')),
                    'titulo' => trim((string) data_get($item, 'titulo', '')),
                    'nombre_servicio' => trim((string) data_get($item, 'nombre_servicio', '')),
                    'nombre_destinatario' => trim((string) data_get($item, 'nombre_destinatario', '')),
                    'resumen_origen' => (array) data_get($item, 'resumen_origen', []),
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
            'estado_emision' => ['nullable', 'in:all,FACTURADA,PENDIENTE,RECHAZADA,ERROR,NO_APLICA'],
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

    private function authorizeVentasViewer(?User $user, string $scope = 'own'): User
    {
        abort_unless($user, 403, 'No tienes permiso para ver ventas.');

        if ($scope === 'branch') {
            abort_unless($user->can('ventas-sucursal.index'), 403, 'No tienes permiso para ver ventas de sucursal.');
            abort_unless((int) ($user->sucursal_id ?? 0) > 0, 403, 'Tu cuenta no tiene una sucursal de facturacion asignada.');

            return $user;
        }

        abort_unless($user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para ver tus ventas.');

        return $user;
    }

    private function pageContextForScope(string $scope): array
    {
        if ($scope === 'branch') {
            return [
                'page_title' => 'Ventas sucursal',
                'panel_title' => 'Ventas de sucursal',
                'panel_subtitle' => 'Ventas emitidas por los cajeros de tu sucursal de facturacion.',
                'filter_route' => 'ventas-sucursal.index',
                'export_route' => 'ventas-sucursal.export.pdf',
                'show_cashier_column' => true,
                'scope' => 'branch',
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
        ];
    }

    private function loadScopedVentas(FacturacionCartService $service, User $user, array $filters, string $scope): array
    {
        $rawRows = $scope === 'branch'
            ? $this->fetchBranchRawRows($service, $user, $filters)
            : $this->fetchOwnRawRows($service, $user, $filters);

        if ($rawRows->isEmpty()) {
            return [collect(), $this->emptySummary(), [
                'branch_cashiers' => collect(),
            ]];
        }

        $rows = $this->normalizeKardexRows($rawRows)
            ->unique(fn ($row) => $this->normalizedVentaRowKey($row))
            ->sortByDesc(fn ($row) => strtotime((string) (data_get($row, 'emitido_en') ?: data_get($row, 'created_at', '1970-01-01 00:00:00'))))
            ->values();
        $rows = $this->applyLocalFilters($rows, $filters);

        return [
            $rows->values(),
            $this->summaryFromRows($rows),
            [
                'branch_cashiers' => $scope === 'branch' ? $this->buildBranchCashierSummary($rows) : collect(),
            ],
        ];
    }

    private function fetchOwnRawRows(FacturacionCartService $service, User $user, array $filters): Collection
    {
        $kardex = $service->fetchKardexVentas($user, $filters);
        $ventas = $service->fetchVentas($user, $filters);

        return collect($ventas['carts'] ?? [])
            ->concat(collect($kardex['detalle'] ?? []))
            ->map(fn ($row) => is_array($row) ? (object) $row : $row)
            ->filter(fn ($row) => is_object($row))
            ->values();
    }

    private function fetchBranchRawRows(FacturacionCartService $service, User $viewer, array $filters): Collection
    {
        return $this->resolveBranchCashiers($viewer)
            ->flatMap(function (User $cashier) use ($service, $filters) {
                try {
                    return $this->fetchOwnRawRows($service, $cashier, $filters)
                        ->map(function ($row) use ($cashier) {
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
                        ->filter(fn ($row) => !$this->isQrPaymentRow($row)
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
        if ($sourceUserId <= 0 || ! $viewer->can('ventas-sucursal.index')) {
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
        return [
            'totalVentas' => (int) ($resumen['ventas'] ?? 0),
            'totalBorradores' => 0,
            'facturadas' => (int) ($resumen['facturadas'] ?? 0),
            'pendientes' => (int) ($resumen['pendientes'] ?? 0),
            'rechazadas' => (int) ($resumen['observadas'] ?? 0),
            'montoTotal' => (float) ($resumen['totalVendido'] ?? 0),
        ];
    }

    private function summaryFromRows(Collection $rows): array
    {
        $paidStatuses = ['FACTURADA'];

        return [
            'totalVentas' => $rows->filter(fn ($row) => (string) data_get($row, 'estado', '') === 'emitido')->count(),
            'totalBorradores' => $rows->filter(fn ($row) => (string) data_get($row, 'estado', '') === 'borrador')->count(),
            'facturadas' => $rows->filter(fn ($row) => in_array(strtoupper((string) data_get($row, 'estado_emision', '')), $paidStatuses, true))->count(),
            'pendientes' => $rows->filter(fn ($row) => strtoupper((string) data_get($row, 'estado_emision', '')) === 'PENDIENTE')->count(),
            'rechazadas' => $rows->filter(function ($row) {
                $estadoEmision = strtoupper((string) data_get($row, 'estado_emision', ''));
                $isQrPayment = $this->isQrPaymentRow($row);
                $estadoPago = strtolower((string) data_get($row, 'estado_pago', 'pendiente'));

                return $estadoEmision === 'RECHAZADA'
                    || ($isQrPayment && $estadoPago === 'cancelado');
            })->count(),
            'qrPagados' => $rows->filter(fn ($row) => $this->isQrPaymentRow($row)
                && strtolower((string) data_get($row, 'estado', '')) === 'emitido'
                && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado')->count(),
            'qrFacturados' => $rows->filter(fn ($row) => $this->isQrPaymentRow($row)
                && strtolower((string) data_get($row, 'estado', '')) === 'emitido'
                && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado'
                && strtoupper((string) data_get($row, 'estado_emision', '')) === 'FACTURADA')->count(),
            'qrPendientes' => $rows->filter(fn ($row) => $this->isQrPaymentRow($row)
                && strtolower((string) data_get($row, 'estado', '')) === 'pendiente_pago'
                && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pendiente')->count(),
            'montoQr' => round((float) $rows
                ->filter(fn ($row) => $this->isQrPaymentRow($row)
                    && strtolower((string) data_get($row, 'estado', '')) === 'emitido'
                    && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado')
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
            'montoTotal' => round((float) $rows
                ->filter(fn ($row) => strtolower((string) data_get($row, 'estado', '')) === 'emitido'
                    && !$this->isQrPaymentRow($row)
                    && strtolower((string) data_get($row, 'estado_pago', 'pendiente')) === 'pagado')
                ->sum(fn ($row) => (float) data_get($row, 'total', 0)), 2),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'totalVentas' => 0,
            'totalBorradores' => 0,
            'facturadas' => 0,
            'pendientes' => 0,
            'rechazadas' => 0,
            'qrPagados' => 0,
            'qrFacturados' => 0,
            'qrPendientes' => 0,
            'montoQr' => 0.0,
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
            $numeroFactura = trim((string) (
                data_get($row, 'numeroFactura')
                ?? data_get($row, 'nroFactura')
                ?? data_get($row, 'respuesta_emision.factura.nroFactura')
                ?? data_get($row, 'respuesta_emision.factura.numeroFactura')
            ));
            $estadoSufe = strtoupper(trim((string) (
                data_get($row, 'estadoSufe')
                ?? data_get($row, 'estado_emision')
                ?? data_get($row, 'respuesta_emision.estadoSufe')
                ?? ''
            )));
            $cuf = trim((string) (
                data_get($row, 'cuf')
                ?? data_get($row, 'respuesta_emision.factura.cuf')
            ));

            $pdfUrl = trim((string) (
                data_get($row, 'pdfUrl')
                ?? data_get($row, 'urlPdf')
                ?? data_get($row, 'respuesta_emision.factura.pdfUrl')
            ));
            if ($pdfUrl === '' && $cuf !== '' && $estadoSufe === 'PROCESADA') {
                $pdfUrl = 'https://sefe.demo.agetic.gob.bo/public/facturas_pdf/' . $cuf . '.pdf';
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
            $emision = $this->mapEstadoSufeToBridge($estadoSufe);
            $estadoCart = strtolower(trim((string) data_get($row, 'estado', '')));
            if ($estadoCart === '') {
                $estadoCart = 'emitido';
            }
            $estadoPago = strtolower(trim((string) data_get($row, 'estado_pago', '')));
            if ($estadoPago === '') {
                $estadoPago = $metodoPago === 'qr' ? 'pendiente' : 'pagado';
            }
            $codigoSeguimientoFiscal = trim((string) data_get($row, 'codigo_seguimiento_fiscal', $codigoSeguimiento));
            $qrTransactionId = trim((string) data_get($row, 'qr_transaction_id', ''));
            if ($metodoPago === 'qr') {
                $emision = [
                    'estado' => strtoupper(trim((string) data_get($row, 'estado_emision', 'NO_APLICA'))),
                    'mensaje' => $this->buildQrStatusMessage($estadoPago, trim((string) data_get($row, 'mensaje_emision', ''))),
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
                'created_at' => $createdAt,
                'emitido_en' => $createdAt,
                'codigo_orden' => $codigoOrden,
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
        if ($venta && $this->normalizeItems(data_get($venta, 'items', []))->isNotEmpty()) {
            return $venta;
        }

        $ventaDetalle = $service->fetchVentaDetalleByVentaId($user, $cart);
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
        if ($pdfUrl === '' && $cuf !== '') {
            $pdfUrl = 'https://sefe.demo.agetic.gob.bo/public/facturas_pdf/' . $cuf . '.pdf';
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
        if ($this->isQrPaymentRow($cart)) {
            $estadoPago = strtolower(trim((string) data_get($cart, 'estado_pago', 'pendiente')));
            $estadoEmision = strtoupper(trim((string) data_get($cart, 'estado_emision', 'NO_APLICA')));

            if ($estadoPago === 'cancelado') {
                return 'qr_cancelado';
            }

            if ($estadoPago !== 'pagado') {
                return 'qr_pendiente';
            }

            if ($estadoEmision === 'FACTURADA') {
                return 'qr_facturado';
            }

            return 'qr_pagado_pendiente_factura';
        }

        return strtolower(trim((string) data_get($cart, 'canal_emision', 'factura_electronica')));
    }

    private function buildPdfRows(Collection $carts): Collection
    {
        return $carts->map(function ($cart) {
            $cart = is_array($cart) ? (object) $cart : $cart;
            $items = $this->normalizeItems(data_get($cart, 'items', []));

            $respuesta = (array) data_get($cart, 'respuesta_emision', []);
            $numeroFactura = trim((string) (
                data_get($respuesta, 'factura.nroFactura')
                ?? data_get($respuesta, 'factura.numeroFactura')
                ?? data_get($respuesta, 'consultaSefe.nroFactura')
                ?? $cart->id
            ));
            $fecha = $cart->emitido_en ?? $cart->created_at;
            $canalEmision = strtolower(trim((string) data_get($cart, 'canal_emision', 'factura_electronica')));
            $metodoPago = strtolower(trim((string) data_get($cart, 'metodo_pago', $canalEmision === 'qr' ? 'qr' : 'efectivo')));
            $estadoPago = strtolower(trim((string) data_get($cart, 'estado_pago', 'pendiente')));
            $estadoEmision = strtoupper(trim((string) data_get($cart, 'estado_emision', '')));
            $contabilizaEnCaja = $metodoPago !== 'qr';
            $sectionKey = $this->resolvePdfSectionKey($cart);

            $emisionLabel = match ($sectionKey) {
                'qr_facturado' => 'QR pagado + facturado',
                'qr_pagado_pendiente_factura' => 'QR pagado',
                'qr_cancelado' => 'QR cancelado',
                'qr_pendiente' => 'QR pendiente',
                default => $this->labelCanalEmision($canalEmision),
            };

            $tipoEnvio = $items
                ->map(fn ($item) => trim((string) data_get($item, 'nombre_servicio', data_get($item, 'titulo', ''))))
                ->filter()
                ->unique()
                ->implode(' / ');
            $detalleItems = $items
                ->map(fn ($item) => trim((string) data_get($item, 'titulo', data_get($item, 'nombre_servicio', ''))))
                ->filter()
                ->unique()
                ->implode(' / ');
            $codigoOrden = trim((string) data_get($cart, 'codigo_orden', data_get($cart, 'codigo_seguimiento', ''))) ?: '-';
            $codigosPaquete = $this->extractPackageCodesFromItems($items);
            $pesoTotal = (float) $items->sum(fn ($item) => (float) data_get($item, 'resumen_origen.peso', 0));
            $cantidadTotal = (int) data_get($cart, 'items_count', 0);
            if ($cantidadTotal <= 0) {
                $cantidadTotal = max(1, $items->count());
            }
            $packageItemsCount = $codigosPaquete->count();
            $serviceItemsCount = max(0, $cantidadTotal - $packageItemsCount);
            $detalleResumen = collect([
                $packageItemsCount > 0 ? $packageItemsCount . ' paquete' . ($packageItemsCount === 1 ? '' : 's') : null,
                $serviceItemsCount > 0 ? $serviceItemsCount . ' servicio' . ($serviceItemsCount === 1 ? '' : 's') : null,
            ])->filter()->implode(' + ');
            $clienteLabel = trim((string) data_get($cart, 'razon_social', ''));
            if ($clienteLabel === '') {
                $clienteLabel = (bool) data_get($cart, 'es_oficial', false) ? 'ENVIO OFICIAL' : 'Sin cliente';
            }

            return [
                'origen_usuario_id' => trim((string) data_get($cart, 'origen_usuario_id', '')),
                'origen_usuario_nombre' => trim((string) data_get($cart, 'origen_usuario_nombre', '')),
                'origen_usuario_email' => trim((string) data_get($cart, 'origen_usuario_email', '')),
                'fecha' => $fecha ? date('d/m/Y', strtotime((string) $fecha)) : '-',
                'fecha_hora' => $fecha ? date('d/m/Y H:i', strtotime((string) $fecha)) : '-',
                'fecha_sort' => $fecha ? strtotime((string) $fecha) : 0,
                'cliente' => $clienteLabel,
                'tipo_envio' => $tipoEnvio !== '' ? $tipoEnvio : 'SIN DETALLE REAL',
                'detalle_items' => $detalleItems !== '' ? $detalleItems : 'Sin detalle real',
                'detalle_resumen' => $detalleResumen,
                'codigo_item' => $codigoOrden,
                'codigo_paquetes' => $codigosPaquete,
                'codigo_referencia' => $codigosPaquete->isNotEmpty()
                    ? $codigoOrden . "\nPaquetes: " . $codigosPaquete->implode(', ')
                    : $codigoOrden,
                'peso' => $pesoTotal,
                'cantidad' => $cantidadTotal,
                'canal_emision' => $canalEmision,
                'metodo_pago' => $metodoPago,
                'estado_pago' => $estadoPago,
                'estado_emision' => $estadoEmision,
                'section_key' => $sectionKey,
                'emision_label' => $emisionLabel,
                'contabiliza_en_caja' => $contabilizaEnCaja,
                'cobrada' => in_array($sectionKey, ['factura_electronica', 'qr_facturado', 'qr_pagado_pendiente_factura', 'oficial'], true),
                'numero_factura' => $numeroFactura !== '' ? $numeroFactura : '-',
                'importe_parcial' => round((float) data_get($cart, 'total', 0), 2),
                'importe_general' => round((float) data_get($cart, 'total', 0), 2),
            ];
        })->values();
    }

    private function extractPackageCodesFromItems(Collection $items): Collection
    {
        return $items
            ->flatMap(function ($item) {
                return [
                    trim((string) data_get($item, 'codigo', '')),
                    trim((string) data_get($item, 'codigo_item', '')),
                    trim((string) data_get($item, 'codigo_paquete', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_item', '')),
                    trim((string) data_get($item, 'resumen_origen.codigo_paquete', '')),
                ];
            })
            ->filter()
            ->unique()
            ->values();
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

    private function buildTicketData(object $cart, $user): array
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
            'pdf_url' => trim((string) data_get($respuesta, 'factura.pdfUrl', '')),
        ];
    }
}

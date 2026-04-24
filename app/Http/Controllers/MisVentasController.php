<?php

namespace App\Http\Controllers;

use App\Services\FacturacionCartService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
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
        [$user, $filters] = $this->resolveRequestContext($request);
        $cajaContext = $this->resolveCajaContext($service, $user);
        $arqueosContext = $this->resolveArqueosContext($service, $user, $filters['arqueo_mes']);

        if ($filters['estado'] === 'borrador') {
            $empty = collect();
            $carts = new LengthAwarePaginator(
                $empty,
                0,
                (int) $filters['per_page'],
                (int) $filters['page'],
                ['path' => $request->url(), 'query' => $request->query()]
            );

            return view('facturacion.mis-ventas', [
                'carts' => $carts,
                'summary' => $this->emptySummary(),
                'filters' => $filters,
                'cajaContext' => $cajaContext,
                'arqueosContext' => $arqueosContext,
            ]);
        }

        $remote = $service->fetchKardexVentas($user, $filters);
        $rawRows = collect($remote['detalle'] ?? [])
            ->map(fn ($row) => is_array($row) ? (object) $row : $row)
            ->filter(fn ($row) => is_object($row))
            ->values();

        $pageRows = $rawRows
            ->forPage((int) $filters['page'], (int) $filters['per_page'])
            ->values();

        $rows = $this->normalizeKardexRows($pageRows, $service, $user);
        $summary = $this->summaryFromKardex($remote['resumen'] ?? []);

        $carts = new LengthAwarePaginator(
            $rows,
            $rawRows->count(),
            (int) $filters['per_page'],
            (int) $filters['page'],
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('facturacion.mis-ventas', [
            'carts' => $carts,
            'summary' => $summary,
            'filters' => $filters,
            'cajaContext' => $cajaContext,
            'arqueosContext' => $arqueosContext,
        ]);
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

    private function resolveArqueosContext(FacturacionCartService $service, $user, string $mes): array
    {
        try {
            $data = $service->fetchCajaArqueos($user, $mes);
            return [
                'mes' => (string) ($data['mes'] ?? $mes),
                'resumen' => (array) ($data['resumen'] ?? []),
                'dias' => $data['dias'] ?? collect(),
                'error' => null,
            ];
        } catch (\Throwable) {
            return [
                'mes' => $mes,
                'resumen' => [],
                'dias' => collect(),
                'error' => 'No se pudo cargar el arqueo mensual.',
            ];
        }
    }

    public function exportPdf(Request $request, FacturacionCartService $service): Response
    {
        [$user, $filters] = $this->resolveRequestContext($request);

        if ($filters['estado'] === 'borrador') {
            $rows = collect();
            $carts = collect();
        } else {
            $exportFilters = $filters;
            $exportFilters['per_page'] = 500;
            $remote = $service->fetchKardexVentas($user, $exportFilters);
            $rawRows = collect($remote['detalle'] ?? [])
                ->map(fn ($row) => is_array($row) ? (object) $row : $row)
                ->filter(fn ($row) => is_object($row))
                ->values();
            $carts = $this->normalizeKardexRows($rawRows, $service, $user);
            $rows = $this->buildPdfRows($carts);
        }

        $totals = [
            'parcial' => round((float) $rows->sum(fn ($row) => (float) data_get($row, 'importe_parcial', 0)), 2),
            'general' => round((float) $rows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
        ];

        $pdf = Pdf::loadView('facturacion.mis-ventas-kardex-pdf', [
            'user' => $user,
            'filters' => $filters,
            'generatedAt' => now(),
            'carts' => $carts,
            'rows' => $rows,
            'totals' => $totals,
        ])->setPaper('a4', 'portrait');

        $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function ticket(Request $request, int $cart, FacturacionCartService $service): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para ver tus ventas.');

        $venta = $service->fetchVentaById($user, $cart);
        if (!$venta) {
            $ventaDetalle = $service->fetchVentaDetalleByVentaId($user, $cart);
            $venta = $ventaDetalle ? $this->mapVentaDetailToCart($ventaDetalle) : null;
        }

        abort_unless($venta, 404, 'No se encontro la venta solicitada.');

        $ticket = $this->buildTicketData($venta, $user);
        $pdf = Pdf::loadView('facturacion.mis-ventas-ticket', ['cart' => $venta, 'ticket' => $ticket])->setPaper([0, 0, 226.77, 680], 'portrait');

        return response()->streamDownload(fn () => print($pdf->output()), 'ticket-' . ($venta->codigo_orden ?: ('venta-' . $venta->id)) . '.pdf');
    }

    private function resolveRequestContext(Request $request): array
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para ver tus ventas.');
        $today = now()->toDateString();

        $validated = $request->validate([
            'estado' => ['nullable', 'in:all,borrador,emitido'],
            'estado_emision' => ['nullable', 'in:all,FACTURADA,PENDIENTE,RECHAZADA,ERROR'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'arqueo_mes' => ['nullable', 'regex:/^\d{4}\-\d{2}$/'],
        ]);

        return [$user, [
            'estado' => (string) ($validated['estado'] ?? 'all'),
            'estado_emision' => (string) ($validated['estado_emision'] ?? 'all'),
            'from' => $validated['from'] ?? $today,
            'to' => $validated['to'] ?? $today,
            'q' => trim((string) ($validated['q'] ?? '')),
            'per_page' => (int) ($validated['per_page'] ?? 20),
            'page' => (int) ($validated['page'] ?? 1),
            'arqueo_mes' => (string) ($validated['arqueo_mes'] ?? now()->format('Y-m')),
        ]];
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

    private function emptySummary(): array
    {
        return [
            'totalVentas' => 0,
            'totalBorradores' => 0,
            'facturadas' => 0,
            'pendientes' => 0,
            'rechazadas' => 0,
            'montoTotal' => 0.0,
        ];
    }

    private function normalizeKardexRows(Collection $rows, FacturacionCartService $service, $user): Collection
    {
        return $rows->map(function ($row) use ($service, $user) {
            $ventaId = (int) data_get($row, 'id', 0);
            $origenVentaId = (int) data_get($row, 'origenVentaId', 0);

            $bridgeCart = $origenVentaId > 0 ? $service->fetchVentaById($user, $origenVentaId) : null;
            $ventaDetalle = null;

            $items = $this->normalizeItems(data_get($bridgeCart, 'items', []));
            if ($items->isEmpty() && $ventaId > 0) {
                $ventaDetalle = $service->fetchVentaDetalleByVentaId($user, $ventaId);
                $items = collect((array) data_get($ventaDetalle, 'detalle', []))
                    ->map(fn ($item) => $this->mapVentaDetalleItemToCartItem($item))
                    ->filter()
                    ->values();
            }

            $codigoOrden = trim((string) data_get($row, 'codigoOrden', ''));
            $codigoSeguimiento = trim((string) data_get($row, 'codigoSeguimiento', ''));
            $numeroFactura = trim((string) (
                data_get($row, 'numeroFactura')
                ?? data_get($bridgeCart, 'respuesta_emision.factura.nroFactura')
                ?? data_get($bridgeCart, 'respuesta_emision.factura.numeroFactura')
                ?? data_get($ventaDetalle, 'seguimiento.detalle.nroFactura')
                ?? data_get($ventaDetalle, 'seguimiento.detalle.numeroFactura')
            ));
            $estadoSufe = strtoupper(trim((string) data_get($row, 'estadoSufe', '')));
            $cuf = trim((string) (
                data_get($row, 'cuf')
                ?? data_get($bridgeCart, 'respuesta_emision.factura.cuf')
                ?? data_get($ventaDetalle, 'seguimiento.cuf')
            ));

            $pdfUrl = trim((string) (
                data_get($bridgeCart, 'respuesta_emision.factura.pdfUrl')
                ?? data_get($ventaDetalle, 'seguimiento.urlPdf')
            ));
            if ($pdfUrl === '' && $cuf !== '' && $estadoSufe === 'PROCESADA') {
                $pdfUrl = 'https://sefe.demo.agetic.gob.bo/public/facturas_pdf/' . $cuf . '.pdf';
            }

            $createdAt = (string) data_get($row, 'fecha', '');
            $emision = $this->mapEstadoSufeToBridge($estadoSufe);

            return (object) [
                'id' => $origenVentaId > 0 ? $origenVentaId : $ventaId,
                'venta_id' => $ventaId,
                'created_at' => $createdAt,
                'emitido_en' => $createdAt,
                'codigo_orden' => $codigoOrden,
                'numero_documento' => trim((string) data_get($row, 'documentoIdentidad', '')),
                'razon_social' => trim((string) data_get($row, 'razonSocial', '')),
                'modalidad_facturacion' => trim((string) data_get($row, 'codigoCliente', '')) !== '' ? 'con_datos' : 'sin_cliente',
                'estado' => 'emitido',
                'estado_emision' => $emision['estado'],
                'mensaje_emision' => $emision['mensaje'],
                'codigo_seguimiento' => $codigoSeguimiento,
                'total' => (float) data_get($row, 'total', 0),
                'items_count' => (int) data_get($row, 'itemsCount', $items->count()),
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

        return (object) [
            'id' => (int) data_get($venta, 'id', 0),
            'codigo_orden' => $codigoOrden,
            'numero_documento' => trim((string) data_get($venta, 'cliente.documentoIdentidad', data_get($venta, 'documentoIdentidad', ''))),
            'razon_social' => trim((string) data_get($venta, 'cliente.razonSocial', data_get($venta, 'razonSocial', ''))),
            'emitido_en' => (string) data_get($venta, 'created_at', data_get($venta, 'fecha', '')),
            'total' => (float) data_get($venta, 'montoTotal', data_get($venta, 'total', 0)),
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
            'PROCESADA' => ['estado' => 'FACTURADA', 'mensaje' => 'Factura emitida correctamente.'],
            'RECEPCIONADA', 'CONTINGENCIA_CREADA' => ['estado' => 'PENDIENTE', 'mensaje' => 'Emision en proceso de confirmacion.'],
            'OBSERVADA' => ['estado' => 'RECHAZADA', 'mensaje' => 'Requiere revision antes de reenviar.'],
            'ERROR' => ['estado' => 'ERROR', 'mensaje' => 'Se registro un error en la emision.'],
            default => ['estado' => 'SIN ESTADO', 'mensaje' => 'Sin observaciones registradas.'],
        };
    }

    private function buildPdfRows(Collection $carts): Collection
    {
        return $carts->flatMap(function ($cart) {
            $cart = is_array($cart) ? (object) $cart : $cart;
            $items = $this->normalizeItems(data_get($cart, 'items', []));

            $respuesta = (array) data_get($cart, 'respuesta_emision', []);
            $numeroFactura = trim((string) (
                data_get($respuesta, 'factura.nroFactura')
                ?? data_get($respuesta, 'factura.numeroFactura')
                ?? data_get($respuesta, 'consultaSefe.nroFactura')
                ?? $cart->id
            ));

            return $items->map(function ($item) use ($cart, $numeroFactura) {
                $resumen = (array) data_get($item, 'resumen_origen', []);
                $fecha = $cart->emitido_en ?? $cart->created_at;
                $origenTipo = trim((string) data_get($item, 'origen_tipo', ''));
                $nombreServicio = trim((string) data_get($item, 'nombre_servicio', ''));
                $titulo = trim((string) data_get($item, 'titulo', ''));
                $codigo = trim((string) data_get($item, 'codigo', ''));
                $itemId = (int) data_get($item, 'id', 0);

                return [
                    'fecha' => $fecha ? date('d/m/Y', strtotime((string) $fecha)) : '-',
                    'origen' => trim((string) ($resumen['ciudad'] ?? $resumen['origen'] ?? $origenTipo)) ?: '-',
                    'tipo_envio' => $nombreServicio !== '' ? $nombreServicio : ($titulo !== '' ? $titulo : 'SIN SERVICIO'),
                    'codigo_item' => trim((string) (($resumen['codigo'] ?? null) ?: $codigo ?: ('ITEM-' . $itemId))),
                    'peso' => (float) ($resumen['peso'] ?? 0),
                    'cantidad' => max(1, (int) data_get($item, 'cantidad', 1)),
                    'numero_factura' => $numeroFactura !== '' ? $numeroFactura : '-',
                    'importe_parcial' => round((float) data_get($item, 'monto_base', 0), 2),
                    'importe_general' => round((float) data_get($item, 'total_linea', 0), 2),
                ];
            });
        })->values();
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
            'fecha' => !empty($cart->emitido_en) ? date('d/m/Y H:i:s', strtotime((string) $cart->emitido_en)) : '-',
            'importe' => round((float) $cart->total, 2),
            'metodo_pago' => 'Pago de contado',
            'qr_payload' => $qrPayload,
            'qr_image' => $qrImage,
            'cuf' => $cuf,
            'pdf_url' => trim((string) data_get($respuesta, 'factura.pdfUrl', '')),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Estado;
use App\Models\SolicitudCliente;
use App\Services\ClienteSolicitudFacturacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DeliveryExpressControlController extends Controller
{
    public function index(Request $request, ClienteSolicitudFacturacionService $facturacion): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'pago' => ['nullable', 'in:todos,qr,destino'],
            'estado' => ['nullable', 'string', 'max:100'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $filters = [
            'q' => trim((string) ($validated['q'] ?? '')),
            'pago' => (string) ($validated['pago'] ?? 'todos'),
            'estado' => trim((string) ($validated['estado'] ?? '')),
            'fecha_desde' => trim((string) ($validated['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($validated['fecha_hasta'] ?? '')),
        ];

        $baseQuery = SolicitudCliente::query()
            ->with([
                'cliente:id,codigo_cliente,name,email',
                'estadoRegistro:id,nombre_estado',
                'servicioExtra:id,nombre,descripcion',
                'destino:id,nombre_destino',
            ])
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $search = $filters['q'];
                $query->where(function ($inner) use ($search): void {
                    $inner->where('codigo_solicitud', 'ILIKE', "%{$search}%")
                        ->orWhere('barcode', 'ILIKE', "%{$search}%")
                        ->orWhere('nombre_remitente', 'ILIKE', "%{$search}%")
                        ->orWhere('nombre_destinatario', 'ILIKE', "%{$search}%")
                        ->orWhere('origen', 'ILIKE', "%{$search}%")
                        ->orWhere('ciudad', 'ILIKE', "%{$search}%")
                        ->orWhereHas('cliente', function ($clienteQuery) use ($search): void {
                            $clienteQuery->where('name', 'ILIKE', "%{$search}%")
                                ->orWhere('codigo_cliente', 'ILIKE', "%{$search}%")
                                ->orWhere('email', 'ILIKE', "%{$search}%");
                        });
                });
            })
            ->when($filters['pago'] === 'qr', fn ($query) => $query->where('forma_pago', 'pagado'))
            ->when($filters['pago'] === 'destino', function ($query): void {
                $query->where(function ($inner): void {
                    $inner->where('forma_pago', 'destino')
                        ->orWhere('pago_destinatario', true);
                });
            })
            ->when($filters['estado'] !== '', function ($query) use ($filters): void {
                $query->whereHas('estadoRegistro', fn ($estadoQuery) =>
                    $estadoQuery->whereRaw('trim(upper(nombre_estado)) = ?', [mb_strtoupper($filters['estado'])])
                );
            })
            ->when($filters['fecha_desde'] !== '', fn ($query) => $query->whereDate('created_at', '>=', $filters['fecha_desde']))
            ->when($filters['fecha_hasta'] !== '', fn ($query) => $query->whereDate('created_at', '<=', $filters['fecha_hasta']));

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'qr' => (clone $baseQuery)->where('forma_pago', 'pagado')->count(),
            'destino' => (clone $baseQuery)->where(function ($query): void {
                $query->where('forma_pago', 'destino')->orWhere('pago_destinatario', true);
            })->count(),
        ];

        $solicitudes = (clone $baseQuery)
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        [$qrSinSolicitud, $pendingQrLookupError] = $this->loadOutstandingQrAttempts($facturacion, $filters);
        $invoiceBySolicitud = $this->loadQrInvoiceStatuses($solicitudes->getCollection(), $facturacion);
        $hasInvoiceLookupError = $pendingQrLookupError;

        foreach ($solicitudes->getCollection() as $solicitud) {
            if ($solicitud->forma_pago !== 'pagado') {
                $solicitud->setAttribute('_control_invoice', [
                    'status' => 'NO_VERIFICADA',
                    'number' => '',
                    'message' => 'Sin factura QR vinculada; pago previsto en destino',
                ]);
                continue;
            }

            $invoice = $invoiceBySolicitud[$solicitud->id] ?? null;
            if ($invoice === null) {
                $hasInvoiceLookupError = true;
                $invoice = ['status' => 'SIN_DATO', 'number' => '', 'message' => 'Sin dato de Facturación'];
            }
            $solicitud->setAttribute('_control_invoice', $invoice);
        }

        $estados = Estado::query()
            ->whereIn('id', SolicitudCliente::query()->whereNotNull('estado_id')->select('estado_id')->distinct())
            ->orderBy('nombre_estado')
            ->pluck('nombre_estado')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return view('delivery-express.paquetes-faro', [
            'solicitudes' => $solicitudes,
            'filters' => $filters,
            'summary' => $summary,
            'estados' => $estados,
            'hasInvoiceLookupError' => $hasInvoiceLookupError,
            'qrSinSolicitud' => $qrSinSolicitud,
        ]);
    }

    /**
     * Keep QR attempts visible when the client history has a payment attempt
     * that does not yet have a linked local delivery request.
     *
     * @return array{0:array<int, array<string, mixed>>, 1:bool}
     */
    private function loadOutstandingQrAttempts(ClienteSolicitudFacturacionService $facturacion, array $filters): array
    {
        if ($filters['pago'] === 'destino' || $filters['estado'] !== '') {
            return [[], false];
        }

        $candidates = [];
        $afterId = 0;
        $lookupError = false;

        try {
            do {
                $response = Cache::remember(
                    'delivery-express-control:outstanding-qr:'.$afterId,
                    now()->addMinute(),
                    fn () => $facturacion->listarQrClienteOnlinePendientes($afterId, 100)
                );
                $carts = (array) data_get($response, 'data.carts', []);

                foreach ($carts as $cart) {
                    if (!is_array($cart)) {
                        continue;
                    }

                    $originUserId = trim((string) ($cart['origen_usuario_id'] ?? ''));
                    if (!preg_match('/^cliente_online_(\d+)$/', $originUserId, $matches)) {
                        continue;
                    }

                    $summary = $this->qrSummaryFromCart($cart);
                    if ($summary === []) {
                        continue;
                    }

                    $paymentStatus = $this->normalizeQrPaymentStatus((string) ($cart['estado_pago'] ?? ''));
                    if (!in_array($paymentStatus, ['pagado', 'pendiente'], true)) {
                        continue;
                    }
                    if ($filters['pago'] === 'qr' && $paymentStatus !== 'pagado') {
                        continue;
                    }

                    try {
                        $createdAt = !empty($cart['created_at'])
                            ? \Illuminate\Support\Carbon::parse($cart['created_at'])
                            : null;
                    } catch (\Throwable) {
                        $createdAt = null;
                    }
                    if ($paymentStatus === 'pendiente'
                        && $createdAt
                        && $createdAt->lte(now()->subHours(48))) {
                        continue;
                    }

                    $item = collect((array) ($cart['items'] ?? []))
                        ->first(fn ($candidate) => strtolower((string) data_get($candidate, 'origen_tipo', '')) === 'cliente_online');
                    $candidates[] = [
                        'cart' => $cart,
                        'summary' => $summary,
                        'client_id' => (int) $matches[1],
                        'solicitud_id' => (int) ($summary['solicitud_cliente_id'] ?? 0),
                        'payment_status' => $paymentStatus,
                        'created_at' => $createdAt,
                        'code' => trim((string) ($cart['codigo_orden'] ?? data_get($item, 'codigo', ''))) ?: 'Pago QR',
                        'service' => trim((string) ($summary['servicio'] ?? data_get($item, 'nombre_servicio', 'Delivery Express'))),
                        'origin' => trim((string) ($summary['origen'] ?? '')),
                        'destination' => trim((string) ($summary['destino'] ?? '')),
                        'quantity' => max(1, (int) ($summary['cantidad_paquetes'] ?? 1)),
                        'amount' => (float) ($cart['total'] ?? 0),
                    ];
                }

                $pagination = (array) data_get($response, 'data.pagination', []);
                $nextAfterId = (int) ($pagination['next_after_id'] ?? $afterId);
                if ($nextAfterId <= $afterId && $carts !== []) {
                    $nextAfterId = max(array_map(fn ($cart) => (int) data_get($cart, 'id', 0), $carts));
                }
                if ($nextAfterId <= $afterId) {
                    break;
                }
                $afterId = $nextAfterId;
            } while (true);
        } catch (\Throwable $exception) {
            $lookupError = true;
            Log::warning('No se pudieron consultar los QR globales pendientes de Delivery Express.', [
                'after_id' => $afterId,
                'error' => $exception->getMessage(),
            ]);
        }

        $linkedIds = collect($candidates)
            ->pluck('solicitud_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values();
        $existingLinkedIds = $linkedIds->isEmpty()
            ? collect()
            : SolicitudCliente::query()->whereIn('id', $linkedIds)->pluck('id')->map(fn ($id) => (int) $id);
        $requestsByClient = [];

        foreach ($candidates as $index => $candidate) {
            if ($candidate['solicitud_id'] > 0 && $existingLinkedIds->contains($candidate['solicitud_id'])) {
                unset($candidates[$index]);
                continue;
            }

            if ($candidate['payment_status'] !== 'pagado' || $candidate['solicitud_id'] > 0) {
                continue;
            }

            $clientId = $candidate['client_id'];
            if (!array_key_exists($clientId, $requestsByClient)) {
                $requestsByClient[$clientId] = SolicitudCliente::query()
                    ->where('cliente_id', $clientId)
                    ->get();
            }

            if ($this->matchLegacySolicitud(
                $requestsByClient[$clientId],
                $candidate['summary'],
                $candidate['cart']
            )) {
                unset($candidates[$index]);
            }
        }

        $clientIds = collect($candidates)->pluck('client_id')->unique()->values();
        $clients = $clientIds->isEmpty()
            ? collect()
            : Cliente::query()->whereIn('id', $clientIds)->get(['id', 'name', 'codigo_cliente'])->keyBy('id');
        $search = mb_strtolower($filters['q']);
        $rows = [];

        foreach ($candidates as $candidate) {
            $client = $clients->get($candidate['client_id']);
            $rowSearchText = mb_strtolower(implode(' ', [
                $candidate['code'], $client?->name, $client?->codigo_cliente,
                $candidate['service'], $candidate['origin'], $candidate['destination'],
                $candidate['payment_status'],
            ]));

            if ($search !== '' && !str_contains($rowSearchText, $search)) {
                continue;
            }
            if ($filters['fecha_desde'] !== '' && (!$candidate['created_at'] || $candidate['created_at']->toDateString() < $filters['fecha_desde'])) {
                continue;
            }
            if ($filters['fecha_hasta'] !== '' && (!$candidate['created_at'] || $candidate['created_at']->toDateString() > $filters['fecha_hasta'])) {
                continue;
            }

            $invoice = $candidate['payment_status'] === 'pagado'
                ? $this->invoiceFromCart($candidate['cart'])
                : ['status' => 'NO_APLICA', 'number' => '', 'message' => 'Pendiente de pago'];
            if ($invoice['status'] === 'SIN_DATO') {
                $lookupError = true;
            }

            $rows[] = [
                'code' => $candidate['code'],
                'client_name' => $client?->name ?: 'Cliente no identificado',
                'client_code' => $client?->codigo_cliente ?: '—',
                'service' => $candidate['service'] !== '' ? $candidate['service'] : 'Delivery Express',
                'origin' => $candidate['origin'] !== '' ? $candidate['origin'] : '—',
                'destination' => $candidate['destination'] !== '' ? $candidate['destination'] : '—',
                'quantity' => $candidate['quantity'],
                'amount' => $candidate['amount'],
                'payment_status' => $candidate['payment_status'],
                'invoice' => $invoice,
                'created_at' => $candidate['created_at'],
            ];
        }

        usort($rows, fn (array $left, array $right): int =>
            ($right['created_at']?->getTimestamp() ?? 0) <=> ($left['created_at']?->getTimestamp() ?? 0)
        );

        return [$rows, $lookupError];
    }

    private function normalizeQrPaymentStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['pagado', 'paid', 'success', 'successful', 'succeeded', 'completed', 'approved', 'confirmed'], true)) {
            return 'pagado';
        }
        if (in_array($status, ['cancelado', 'cancelled', 'canceled', 'rejected', 'failed', 'failure', 'expired', 'error'], true)) {
            return 'cancelado';
        }

        return 'pendiente';
    }

    /**
     * Read the QR carts for only the customers on the visible page and map the
     * billing status back to their locally registered delivery requests.
     *
     * @param  Collection<int, SolicitudCliente>  $solicitudes
     * @return array<int, array{status:string, number:string, message:string}>
     */
    private function loadQrInvoiceStatuses(Collection $solicitudes, ClienteSolicitudFacturacionService $facturacion): array
    {
        $paidRequests = $solicitudes
            ->filter(fn (SolicitudCliente $solicitud): bool => $solicitud->forma_pago === 'pagado' && $solicitud->cliente)
            ->groupBy('cliente_id');
        $statuses = [];

        foreach ($paidRequests as $requests) {
            $cliente = $requests->first()->cliente;
            if (!$cliente) {
                continue;
            }

            $wanted = $requests->keyBy(fn (SolicitudCliente $solicitud): int => (int) $solicitud->id);
            $found = [];

            try {
                $page = 1;
                $lastPage = null;

                do {
                    $response = Cache::remember(
                        'delivery-express-control:qr-history:'.$cliente->id.':'.$page,
                        now()->addMinute(),
                        fn () => $facturacion->listarSolicitudesQr($cliente, $page, 100)
                    );
                    $carts = (array) data_get($response, 'data.carts', []);

                    foreach ($carts as $cart) {
                        if (!is_array($cart)) {
                            continue;
                        }

                        $summary = $this->qrSummaryFromCart($cart);
                        $solicitudId = (int) ($summary['solicitud_cliente_id'] ?? 0);
                        if ($solicitudId > 0 && $wanted->has($solicitudId)) {
                            $found[$solicitudId] = $this->invoiceFromCart($cart);
                            continue;
                        }

                        $match = $this->matchLegacySolicitud($requests, $summary, $cart);
                        if ($match) {
                            $found[(int) $match->id] = $this->invoiceFromCart($cart);
                        }
                    }

                    $pagination = (array) data_get($response, 'data.pagination', []);
                    $reportedLastPage = max(
                        (int) ($pagination['last_page'] ?? 0),
                        (int) ($pagination['lastPage'] ?? 0),
                        (int) ($pagination['total_pages'] ?? 0),
                        (int) ($pagination['pages'] ?? 0)
                    );
                    $lastPage = $reportedLastPage > 0 ? $reportedLastPage : null;
                    $page++;
                } while (
                    $wanted->keys()->diff(array_keys($found))->isNotEmpty()
                    && $carts !== []
                    && ($lastPage === null ? count($carts) >= 100 : $page <= $lastPage)
                    && $page <= 100
                );
            } catch (\Throwable $exception) {
                Log::warning('No se pudo consultar la facturacion QR para el control Delivery Express.', [
                    'cliente_id' => $cliente->id,
                    'solicitudes_ids' => $wanted->keys()->all(),
                    'error' => $exception->getMessage(),
                ]);
            }

            $statuses = $statuses + $found;
        }

        return $statuses;
    }

    private function qrSummaryFromCart(array $cart): array
    {
        $item = collect((array) ($cart['items'] ?? []))
            ->first(fn ($item) => strtolower((string) data_get($item, 'origen_tipo', '')) === 'cliente_online');

        return (array) data_get($item, 'resumen_origen', []);
    }

    private function invoiceFromCart(array $cart): array
    {
        $storedResponse = $cart['respuesta_emision'] ?? [];
        if (is_string($storedResponse)) {
            $storedResponse = json_decode($storedResponse, true) ?: [];
        }
        $storedResponse = is_array($storedResponse) ? $storedResponse : [];

        $status = strtoupper(trim((string) (
            data_get($cart, 'invoice_status')
            ?? data_get($cart, 'invoiceStatus')
            ?? data_get($cart, 'estado_emision')
            ?? data_get($storedResponse, 'estado')
            ?? data_get($storedResponse, 'estadoSufe')
            ?? data_get($cart, 'respuesta.estado')
            ?? ''
        )));
        $number = trim((string) (
            data_get($cart, 'invoice_number')
            ?? data_get($cart, 'invoiceNumber')
            ?? data_get($cart, 'numero_factura')
            ?? data_get($storedResponse, 'factura.nroFactura')
            ?? data_get($storedResponse, 'factura.numeroFactura')
            ?? data_get($cart, 'respuesta.factura.nroFactura')
            ?? data_get($cart, 'respuesta.factura.numeroFactura')
            ?? ''
        ));

        if ($number !== '' || in_array($status, ['FACTURADA', 'PROCESADA'], true)) {
            $status = 'FACTURADA';
        } elseif ($status === '') {
            $status = 'SIN_DATO';
        }

        return [
            'status' => $status,
            'number' => $number,
            'message' => $status === 'SIN_DATO' ? 'Sin dato de Facturación' : '',
        ];
    }

    private function matchLegacySolicitud(Collection $requests, array $summary, array $cart): ?SolicitudCliente
    {
        if ($summary === [] || empty($cart['created_at'])) {
            return null;
        }

        try {
            $qrCreatedAt = \Illuminate\Support\Carbon::parse($cart['created_at'])->getTimestamp();
        } catch (\Throwable) {
            return null;
        }

        $normalize = static fn ($value): string => mb_strtoupper(trim((string) $value));
        $candidates = $requests->filter(function (SolicitudCliente $solicitud) use ($summary, $cart, $qrCreatedAt, $normalize): bool {
            if ((int) $solicitud->servicio_extra_id !== (int) ($summary['servicio_extra_id'] ?? 0)
                || (int) $solicitud->destino_id !== (int) ($summary['destino_id'] ?? 0)
                || (int) $solicitud->cantidad !== (int) ($summary['cantidad_paquetes'] ?? 1)
                || abs((float) $solicitud->precio - (float) ($cart['total'] ?? 0)) >= 0.01) {
                return false;
            }

            $solicitudCreatedAt = $solicitud->created_at?->getTimestamp();
            if (!$solicitudCreatedAt || abs($solicitudCreatedAt - $qrCreatedAt) > 172800) {
                return false;
            }

            foreach ([
                'origen' => 'origen',
                'contenido' => 'contenido',
                'nombre_remitente' => 'remitente',
                'carnet' => 'carnet_remitente',
                'telefono_remitente' => 'telefono_remitente',
                'nombre_destinatario' => 'destinatario',
                'telefono_destinatario' => 'telefono_destinatario',
                'direccion_recojo' => 'direccion_recojo',
            ] as $solicitudField => $summaryField) {
                if ($normalize($solicitud->{$solicitudField}) !== $normalize($summary[$summaryField] ?? '')) {
                    return false;
                }
            }

            return true;
        })->values();

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}

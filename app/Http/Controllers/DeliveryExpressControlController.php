<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Estado;
use App\Models\SolicitudCliente;
use App\Models\User;
use App\Services\ClienteSolicitudFacturacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeliveryExpressControlController extends Controller
{
    public function index(Request $request, ClienteSolicitudFacturacionService $facturacion): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:180'],
            'pago' => ['nullable', 'in:todos,qr,pendiente,destino'],
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
        $departmentScope = $this->resolveDepartmentScope($request->user());

        $baseQuery = SolicitudCliente::query()
            ->with([
                'cliente:id,codigo_cliente,name,email',
                'estadoRegistro:id,nombre_estado',
                'servicioExtra:id,nombre,descripcion',
                'destino:id,nombre_destino',
            ]);

        if ($departmentScope !== null) {
            if ($departmentScope === []) {
                $baseQuery->whereRaw('1 = 0');
            } else {
                $baseQuery->where(function ($departmentQuery) use ($departmentScope): void {
                    foreach ($departmentScope as $department) {
                        $departmentQuery
                            ->orWhereRaw("trim(upper(coalesce(origen, ''))) = ?", [$department])
                            ->orWhereRaw("trim(upper(coalesce(ciudad, ''))) = ?", [$department])
                            ->orWhereHas('destino', fn ($destinationQuery) =>
                                $destinationQuery->whereRaw("trim(upper(coalesce(nombre_destino, ''))) = ?", [$department])
                            );
                    }
                });
            }
        }

        $baseQuery = $baseQuery
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
            ->when($filters['pago'] === 'pendiente', fn ($query) => $query->where('forma_pago', 'origen'))
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
            'pending' => (clone $baseQuery)->where('forma_pago', 'origen')->count(),
            'destino' => (clone $baseQuery)->where(function ($query): void {
                $query->where('forma_pago', 'destino')->orWhere('pago_destinatario', true);
            })->count(),
        ];

        $solicitudes = (clone $baseQuery)
            ->latest('created_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        [$qrSinSolicitud, $pendingQrLookupError] = $this->loadOutstandingQrAttempts($facturacion, $filters, $departmentScope);
        $invoiceBySolicitud = $this->loadQrInvoiceStatuses($solicitudes->getCollection(), $facturacion);
        $hasInvoiceLookupError = $pendingQrLookupError;

        foreach ($solicitudes->getCollection() as $solicitud) {
            if ($solicitud->forma_pago !== 'pagado') {
                $solicitud->setAttribute('_control_invoice', [
                    'status' => 'NO_VERIFICADA',
                    'number' => '',
                    'message' => 'Sin factura QR vinculada; envío por cobrar',
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
            ->whereIn('id', (clone $baseQuery)->whereNotNull('estado_id')->select('estado_id')->distinct())
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
            'departmentScope' => $departmentScope,
        ]);
    }

    public function downloadInvoice(Request $request, int $cliente, int $cart, ClienteSolicitudFacturacionService $facturacion)
    {
        $customer = Cliente::query()->findOrFail($cliente);

        try {
            $payment = $facturacion->consultarPagoQrPorUsuario('cliente_online_'.$customer->id, $cart);
            $remoteCart = (array) ($payment['cart'] ?? []);
            abort_unless((int) ($remoteCart['id'] ?? 0) === $cart, 404);
            $remoteOriginUserId = trim((string) ($remoteCart['origen_usuario_id'] ?? ''));
            abort_unless($remoteOriginUserId === '' || $remoteOriginUserId === 'cliente_online_'.$customer->id, 404);

            $isClientOnlineCart = collect((array) ($remoteCart['items'] ?? []))
                ->contains(fn ($item) => strtolower((string) data_get($item, 'origen_tipo', '')) === 'cliente_online');
            abort_unless($isClientOnlineCart, 404);
            $departmentScope = $this->resolveDepartmentScope($request->user());
            $qrSummary = $this->qrSummaryFromCart($remoteCart);
            abort_unless($this->matchesDepartmentScope($departmentScope, [
                $qrSummary['origen'] ?? '',
                $qrSummary['destino'] ?? '',
            ]), 404);
            abort_unless($this->normalizeQrPaymentStatus((string) ($payment['payment_status'] ?? '')) === 'pagado', 409, 'El pago todavía no está confirmado.');
            abort_unless(strtoupper((string) ($payment['invoice_status'] ?? '')) === 'FACTURADA', 409, 'La factura todavía no está emitida.');

            $url = trim((string) ($payment['invoice_pdf_url'] ?? ''));
            abort_unless($this->isAllowedInvoicePdfUrl($url), 404, 'El PDF de esta factura todavía no está disponible.');

            $response = Http::connectTimeout(10)->timeout(45)
                ->withoutRedirecting()->accept('application/pdf')->get($url);
            if (!$response->successful() || !str_starts_with($response->body(), '%PDF-')) {
                throw new \RuntimeException('Facturación no devolvió un PDF válido para la factura QR.');
            }

            $invoiceNumber = trim((string) ($payment['invoice_number'] ?? ''));
            $safeNumber = preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceNumber) ?: (string) $cart;

            return response($response->body(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="factura-'.$safeNumber.'.pdf"',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo reimprimir la factura QR desde el control Delivery Express.', [
                'cliente_id' => $customer->id,
                'cart_id' => $cart,
                'error' => $exception->getMessage(),
            ]);

            abort(502, 'No se pudo descargar la factura. Inténtalo nuevamente en unos minutos.');
        }
    }

    /**
     * Keep QR attempts visible when the client history has a payment attempt
     * that does not yet have a linked local delivery request.
     *
     * @return array{0:array<int, array<string, mixed>>, 1:bool}
     */
    private function loadOutstandingQrAttempts(ClienteSolicitudFacturacionService $facturacion, array $filters, ?array $departmentScope): array
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
                    if (!$this->matchesDepartmentScope($departmentScope, [
                        $summary['origen'] ?? '',
                        $summary['destino'] ?? '',
                    ])) {
                        continue;
                    }

                    $paymentStatus = $this->normalizeQrPaymentStatus((string) ($cart['estado_pago'] ?? ''));
                    if (!in_array($paymentStatus, ['pagado', 'pendiente'], true)) {
                        continue;
                    }
                    if ($filters['pago'] === 'qr' && $paymentStatus !== 'pagado') {
                        continue;
                    }
                    if ($filters['pago'] === 'pendiente' && $paymentStatus !== 'pendiente') {
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
                'client_id' => $candidate['client_id'],
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
            'cart_id' => (int) ($cart['id'] ?? 0),
            'message' => $status === 'SIN_DATO' ? 'Sin dato de Facturación' : '',
        ];
    }

    private function isAllowedInvoicePdfUrl(string $url): bool
    {
        $base = rtrim((string) config('services.facturacion_bridge.sefe_public_base_url', 'https://sefe.agetic.gob.bo'), '/');
        $baseParts = parse_url($base);
        $urlParts = parse_url($url);
        if (!is_array($baseParts) || !is_array($urlParts)) {
            return false;
        }

        $sameOrigin = strtolower((string) ($urlParts['scheme'] ?? '')) === strtolower((string) ($baseParts['scheme'] ?? ''))
            && strtolower((string) ($urlParts['host'] ?? '')) === strtolower((string) ($baseParts['host'] ?? ''))
            && (int) ($urlParts['port'] ?? 0) === (int) ($baseParts['port'] ?? 0)
            && !isset($urlParts['user'])
            && !isset($urlParts['pass']);
        $path = (string) ($urlParts['path'] ?? '');

        return $sameOrigin
            && !isset($urlParts['query'])
            && !isset($urlParts['fragment'])
            && preg_match('#^/public/facturas_pdf/[A-Za-z0-9_-]+\.pdf$#i', $path) === 1;
    }

    private function resolveDepartmentScope(?User $user): ?array
    {
        if (!$user) {
            return [];
        }

        $roles = $user->getRoleNames()->map(
            fn ($role) => str_replace(
                [' ', '-'],
                '_',
                mb_strtolower(Str::ascii(trim((string) $role)))
            )
        );

        $hasGlobalAccess = $user->hasGlobalDepartmentAccess()
            || $roles->contains(fn ($role) => in_array($role, [
                'admin',
                'administrador',
                'super_admin',
                'superadmin',
                'super_administrador',
                'superadministrador',
                'gestor',
                'administrador_operaciones',
                'administrador_de_operaciones',
            ], true));

        if ($hasGlobalAccess) {
            return null;
        }

        return $user->regionalesLista();
    }

    private function matchesDepartmentScope(?array $departmentScope, array $locations): bool
    {
        if ($departmentScope === null) {
            return true;
        }

        if ($departmentScope === []) {
            return false;
        }

        $departments = collect($departmentScope)
            ->map(fn ($department) => mb_strtoupper(trim((string) $department)))
            ->filter()
            ->all();

        return collect($locations)
            ->map(fn ($location) => mb_strtoupper(trim((string) $location)))
            ->contains(fn ($location) => $location !== '' && in_array($location, $departments, true));
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

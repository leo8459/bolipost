<?php

namespace App\Http\Controllers;

use App\Mail\SolicitudClienteCreadaMail;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\Estado;
use App\Models\Origen;
use App\Models\ServicioExtra;
use App\Models\SolicitudCliente;
use App\Models\TarifarioTiktoker;
use App\Services\ClienteSolicitudFacturacionService;
use App\Support\SolicitudCode;
use App\Support\ServiceAvailability;
use App\Support\TiktokerEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ClienteSolicitudController extends Controller
{
    private const DELIVERY_EXPRESS_DISABLED_MESSAGE = 'La creación de solicitudes Delivery Express está deshabilitada temporalmente.';

    private const DIRECCION_DESTINATARIO_VENTANILLA = 'CORREOS DE BOLIVIA';

    private const CIUDADES_BOLIVIA = [
        'LA PAZ',
        'SANTA CRUZ',
        'COBIJA',
        'TRINIDAD',
        'TARIJA',
        'SUCRE',
        'ORURO',
        'COCHABAMBA',
        'POTOSI',
    ];

    public function create(): View|RedirectResponse
    {
        if (! ServiceAvailability::isEnabled('solicitud')) {
            return redirect()->route('clientes.dashboard')->with('error', self::DELIVERY_EXPRESS_DISABLED_MESSAGE);
        }

        $cliente = Auth::guard('cliente')->user();
        $servicioExtraIds = TarifarioTiktoker::query()
            ->whereNotNull('servicio_extra_id')
            ->distinct()
            ->pluck('servicio_extra_id');

        return view('clientes.solicitudes', [
            'cliente' => $cliente,
            'tiposDocumento' => Cliente::tiposDocumentoIdentidad(),
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(),
            'servicioExtras' => ServicioExtra::query()
                ->whereIn('id', $servicioExtraIds)
                ->orderBy('id')
                ->get(['id', 'nombre', 'descripcion']),
            'ciudades' => self::CIUDADES_BOLIVIA,
        ]);
    }

    public function history(ClienteSolicitudFacturacionService $facturacion): View
    {
        $cliente = Auth::guard('cliente')->user();

        $solicitudes = SolicitudCliente::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'servicioExtra:id,nombre,descripcion',
                'destino:id,nombre_destino',
                'tarifarioTiktoker:id,origen_id,destino_id,servicio_extra_id,peso1,peso2,peso_extra,tiempo_entrega',
                'tarifarioTiktoker.origen:id,nombre_origen',
                'tarifarioTiktoker.destino:id,nombre_destino',
                'tarifarioTiktoker.servicioExtra:id,nombre',
            ])
            ->where('cliente_id', (int) $cliente->id)
            ->latest()
            ->get();

        $pagosQrSinSolicitud = collect();
        $qrAttemptsBySolicitudId = [];
        $qrPaymentsBySolicitudId = [];
        $qrHistoryWarning = null;
        try {
            $carts = $facturacion->listarSolicitudesQr($cliente, 1, 100);
            foreach ((array) data_get($carts, 'data.carts', []) as $cart) {
                if (!is_array($cart) || !$this->qrCartWasIssued($cart)) {
                    continue;
                }
                $attemptId = $this->qrAttemptIdFromCart($cart);
                if (!$attemptId) {
                    continue;
                }

                $summary = $this->qrSummaryFromCart($cart);
                $solicitudId = (int) ($summary['solicitud_cliente_id'] ?? 0);
                $paymentStatus = $this->estadoPagoQrDesdeCarrito($cart);
                if ($solicitudId <= 0 && $paymentStatus === 'pagado') {
                    // Recupera el vínculo en carritos antiguos si la API no
                    // guardó solicitud_cliente_id después de registrar el envío.
                    $solicitudVinculada = $this->buscarSolicitudQrVinculada($solicitudes, $summary, $cart);
                    $solicitudId = (int) ($solicitudVinculada?->id ?? 0);
                }
                $payment = (object) [
                    'qr_attempt_id' => $attemptId,
                    'internal_code' => (string) ($cart['codigo_orden'] ?? ''),
                    'importe' => (float) ($cart['total'] ?? 0),
                    'estado_pago' => $paymentStatus,
                    'created_at' => !empty($cart['created_at'])
                        ? \Illuminate\Support\Carbon::parse($cart['created_at'])
                        : null,
                ];
                if ($solicitudId > 0) {
                    $qrAttemptsBySolicitudId[$solicitudId] = $attemptId;
                    $qrPaymentsBySolicitudId[$solicitudId] = $payment;
                    continue;
                }

                // Mantener visibles pagos QR pendientes y confirmados sin envío vinculado.
                // El cliente puede consultar y terminar el registro desde Mis solicitudes.
                if (!in_array($paymentStatus, ['pendiente', 'pagado'], true)) {
                    continue;
                }

                $pagosQrSinSolicitud->push((object) [
                    'qr_attempt_id' => $payment->qr_attempt_id,
                    'internal_code' => $payment->internal_code,
                    'importe' => $payment->importe,
                    'estado_pago' => $payment->estado_pago,
                    'created_at' => $payment->created_at,
                    'servicio' => (string) ($summary['servicio'] ?? 'Delivery Express'),
                    'origen' => (string) ($summary['origen'] ?? ''),
                    'destino' => (string) ($summary['destino'] ?? ''),
                    'cantidad' => (int) ($summary['cantidad_paquetes'] ?? 1),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('No se pudo cargar el historial QR desde Facturacion.', [
                'cliente_id' => $cliente->id,
                'error' => $exception->getMessage(),
            ]);
            $qrHistoryWarning = 'No se pudo consultar el historial de pagos QR en este momento.';
        }

        return view('clientes.mis-solicitudes', [
            'cliente' => $cliente,
            'solicitudes' => $solicitudes,
            'pagosQrSinSolicitud' => $pagosQrSinSolicitud,
            'qrAttemptsBySolicitudId' => $qrAttemptsBySolicitudId,
            'qrPaymentsBySolicitudId' => $qrPaymentsBySolicitudId,
            'qrHistoryWarning' => $qrHistoryWarning,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse|\Illuminate\Http\Response
    {
        if (! ServiceAvailability::isEnabled('solicitud')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => self::DELIVERY_EXPRESS_DISABLED_MESSAGE], 503);
            }

            return redirect()->route('clientes.dashboard')->with('error', self::DELIVERY_EXPRESS_DISABLED_MESSAGE);
        }

        $cliente = Auth::guard('cliente')->user();
        $servicioExtraIds = TarifarioTiktoker::query()
            ->whereNotNull('servicio_extra_id')
            ->distinct()
            ->pluck('servicio_extra_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'servicio_extra_id' => ['required', 'integer', 'in:'.implode(',', $servicioExtraIds)],
            'origen' => ['required', 'string'],
            'destino_id' => ['required', 'integer', 'exists:destino,id'],
            'cantidad' => ['required', 'integer', 'in:1'],
            'contenido' => ['required', 'string'],
            'nombre_remitente' => ['required', 'string', 'max:255'],
            'carnet' => ['required', 'string', 'max:255'],
            'telefono_remitente' => ['required', 'string', 'max:50'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['required', 'string', 'max:50'],
            'direccion_recojo' => ['required', 'string', 'max:255'],
            'direccion_entrega' => ['required', 'string', 'max:255'],
            'forma_pago' => ['required', 'in:origen,destino'],
            'tipo_pago' => ['nullable', 'required_if:forma_pago,origen', 'prohibited_unless:forma_pago,origen', 'in:qr'],
            'recargo_volumen' => ['nullable', 'boolean'],
        ]);

        $data['recargo_volumen'] = (bool) ($data['recargo_volumen'] ?? false);

        if (($data['tipo_pago'] ?? null) === 'qr') {
            $message = 'Para pagar con QR, confirma la solicitud desde el formulario para generar el codigo y esperar la confirmacion del pago.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->back()->withInput()->withErrors(['tipo_pago' => $message]);
        }

        try {
            $solicitud = $this->persistirSolicitud($cliente, $data);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['estado' => $exception->getMessage()]);
        }

        $message = 'Solicitud registrada correctamente con codigo '.$solicitud->codigo_solicitud.'.';

        try {
            Mail::to($cliente->email)->send(new SolicitudClienteCreadaMail($solicitud, $cliente));
        } catch (\Throwable $exception) {
            Log::error('No se pudo enviar el correo de solicitud al cliente.', [
                'solicitud_id' => $solicitud->id,
                'cliente_id' => $cliente->id,
                'cliente_email' => $cliente->email,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'warning' => 'La solicitud se registro, pero no se pudo enviar el correo automatico.',
                    'codigo_solicitud' => $solicitud->codigo_solicitud,
                ], 201);
            }
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'codigo_solicitud' => $solicitud->codigo_solicitud,
            ], 201);
        }

        return $this->downloadTicket($solicitud);
    }

    private function persistirSolicitud(Cliente $cliente, array $data): SolicitudCliente
    {
        $destino = Destino::query()->findOrFail((int) $data['destino_id']);
        $servicioExtra = ServicioExtra::query()->findOrFail((int) $data['servicio_extra_id']);
        $tarifarioTiktoker = $this->resolveTarifarioTiktoker(
            (int) $data['servicio_extra_id'],
            (string) $data['origen'],
            (int) $data['destino_id']
        );
        $estadoSolicitudId = $this->resolveSolicitudEstadoId();
        $direccionEntrega = $this->isPuertaAVentanillaService($servicioExtra)
            ? self::DIRECCION_DESTINATARIO_VENTANILLA
            : trim((string) $data['direccion_entrega']);
        $recargoPagoDestino = ! array_key_exists('precio_pagado', $data)
            && ($data['forma_pago'] ?? 'destino') === 'destino'
                ? SolicitudCliente::RECARGO_PAGO_DESTINO
                : 0;

        $solicitud = SolicitudCliente::query()->create([
            'cliente_id' => (int) $cliente->id,
            'estado_id' => $estadoSolicitudId,
            'servicio_extra_id' => (int) $data['servicio_extra_id'],
            'origen' => $this->upper((string) $data['origen']),
            'tipo_correspondencia' => null,
            'servicio_especial' => null,
            'contenido' => trim((string) $data['contenido']),
            'cantidad' => (int) $data['cantidad'],
            'peso' => null,
            'precio' => array_key_exists('precio_pagado', $data)
                ? round((float) $data['precio_pagado'], 2)
                : round((float) $tarifarioTiktoker->peso1 + (!empty($data['recargo_volumen']) ? 10 : 0) + $recargoPagoDestino, 2),
            'recargo_pago_destino' => $recargoPagoDestino,
            'forma_pago' => (string) ($data['forma_pago'] ?? 'destino'),
            'tipo_pago' => $data['tipo_pago'] ?? null,
            'pago_destinatario' => ($data['forma_pago'] ?? 'destino') === 'destino',
            'nombre_remitente' => $this->upper((string) $data['nombre_remitente']),
            'nombre_envia' => null,
            'carnet' => trim((string) $data['carnet']),
            'telefono_remitente' => $this->nullableTrim($data['telefono_remitente'] ?? null),
            'nombre_destinatario' => $this->upper((string) $data['nombre_destinatario']),
            'telefono_destinatario' => $this->nullableTrim($data['telefono_destinatario'] ?? null),
            'direccion_recojo' => trim((string) $data['direccion_recojo']),
            'direccion' => $direccionEntrega,
            'ciudad' => $this->upper((string) $destino->nombre_destino),
            'servicio_id' => null,
            'destino_id' => (int) $data['destino_id'],
            'tarifario_tiktoker_id' => (int) $tarifarioTiktoker->id,
        ]);

        $codigoSolicitud = $this->generateSolicitudCode((int) $solicitud->id, $solicitud->origen);
        $solicitud->update([
            'codigo_solicitud' => $codigoSolicitud,
            'barcode' => $codigoSolicitud,
        ]);

        $this->registrarEventoTiktokerCreacionCliente($solicitud, (int) $cliente->id);

        return $solicitud->loadMissing(['destino:id,nombre_destino', 'estadoRegistro:id,nombre_estado']);
    }

    private function generateSolicitudCode(int $id, ?string $origin): string
    {
        return SolicitudCode::make($id, $origin);
    }

    private function resolveSolicitudEstadoId(): int
    {
        $estadoId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id') ?? 0);

        if ($estadoId <= 0) {
            throw new \RuntimeException('No existe el estado SOLICITUD en la tabla estados.');
        }

        return $estadoId;
    }

    private function resolveTarifarioTiktoker(int $servicioExtraId, string $origen, int $destinoId): TarifarioTiktoker
    {
        $origenId = (int) (Origen::query()
            ->whereRaw('trim(upper(nombre_origen)) = ?', [$this->upper($origen)])
            ->value('id') ?? 0);

        if ($origenId <= 0) {
            throw new \RuntimeException('No existe el origen seleccionado en la tabla origen.');
        }

        $tarifario = TarifarioTiktoker::query()
            ->where('origen_id', $origenId)
            ->where('destino_id', $destinoId)
            ->where('servicio_extra_id', $servicioExtraId)
            ->first();

        if (! $tarifario) {
            throw new \RuntimeException('No existe un tarifario Delivery Express para el servicio, origen y destino seleccionados.');
        }

        return $tarifario;
    }

    private function upper(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function nullableUpper(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : strtoupper($text);
    }

    private function nullableTrim(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function isPuertaAVentanillaService(ServicioExtra $servicioExtra): bool
    {
        $text = $this->normalizeServiceText(
            (string) $servicioExtra->nombre.' '.(string) $servicioExtra->descripcion
        );

        return str_contains($text, 'puerta a ventanilla');
    }

    private function normalizeServiceText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $value
        );

        return preg_replace('/\s+/', ' ', $value) ?: '';
    }

    private function registrarEventoTiktokerCreacionCliente(SolicitudCliente $solicitud, int $clienteId): void
    {
        $codigo = trim((string) ($solicitud->codigo_solicitud ?: $solicitud->barcode));
        $eventoId = TiktokerEvent::resolveId(TiktokerEvent::SOLICITUD_REGISTRADA);

        if ($codigo === '' || $clienteId <= 0 || $eventoId <= 0) {
            return;
        }

        DB::table('eventos_tiktoker')->insert([
            'codigo' => $codigo,
            'evento_id' => $eventoId,
            'user_id' => null,
            'cliente_id' => $clienteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function downloadTicket(SolicitudCliente $solicitud)
    {
        $cliente = Auth::guard('cliente')->user();
        abort_unless($cliente && (int) $solicitud->cliente_id === (int) $cliente->id, 404);

        $solicitud->loadMissing([
            'estadoRegistro:id,nombre_estado',
            'servicioExtra:id,nombre,descripcion',
            'destino:id,nombre_destino',
        ]);

        $pdf = Pdf::loadView('paquetes_ems.solicitud-ticket', [
            'solicitud' => $solicitud,
            'isPdf' => true,
        ])->setPaper([0, 0, 226.77, 950]);

        return $pdf->download('solicitud-'.$solicitud->codigo_solicitud.'.pdf');
    }

    public function quote(Request $request): JsonResponse
    {
        if (! ServiceAvailability::isEnabled('solicitud')) {
            return response()->json(['message' => self::DELIVERY_EXPRESS_DISABLED_MESSAGE], 503);
        }

        $data = $request->validate([
            'servicio_extra_id' => ['required', 'integer', 'exists:servicio_extras,id'],
            'origen' => ['required', 'string'],
            'destino_id' => ['required', 'integer', 'exists:destino,id'],
        ]);

        try {
            $tarifario = $this->resolveTarifarioTiktoker(
                (int) $data['servicio_extra_id'],
                (string) $data['origen'],
                (int) $data['destino_id']
            );
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'precio' => number_format((float) $tarifario->peso1, 2, '.', ''),
        ]);
    }

    public function checkQrStatus(Request $request, ClienteSolicitudFacturacionService $facturacion): JsonResponse
    {
        $data = $request->validate([
            'qr_attempt_id' => ['required', 'uuid'],
        ]);
        $cliente = Auth::guard('cliente')->user();
        return $this->checkQrStatusForCustomer($cliente, (string) $data['qr_attempt_id'], $facturacion);
    }

    public function checkQrStatusForCustomer(
        Cliente $cliente,
        string $qrAttemptId,
        ClienteSolicitudFacturacionService $facturacion
    ): JsonResponse {
        $attemptId = strtolower(trim($qrAttemptId));
        if (!\Illuminate\Support\Str::isUuid($attemptId)) {
            return response()->json(['message' => 'El intento QR no es válido.'], 422);
        }
        try {
            $lookup = $facturacion->buscarSolicitudQr($cliente, $attemptId);
            $cart = $lookup['cart'] ?? null;
            if (!is_array($cart)) {
                return response()->json(['message' => 'No se encontro el pago QR de esta solicitud.'], 404);
            }
            $cartId = (int) ($cart['id'] ?? 0);
            if ($cartId <= 0) {
                throw new \RuntimeException('Facturacion devolvio un carrito QR invalido.');
            }
            $payment = $facturacion->consultarPagoQr($cliente, $cartId);
            $cart = is_array($payment['cart'] ?? null) ? $payment['cart'] : $cart;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo consultar el pago QR del cliente online.', [
                'cliente_id' => $cliente->id,
                'qr_attempt_id' => $attemptId,
                'error' => $exception->getMessage(),
            ]);

            if (str_contains($exception->getMessage(), '[QR_AMOUNT_MISMATCH]')) {
                $cart = is_array($cart ?? null) ? $cart : [];
                $paid = $this->normalizarEstadoPagoQr((string) ($cart['estado_pago'] ?? '')) === 'pagado';
                $qr = $cart !== [] ? $this->qrPayloadParaEstado($facturacion, $cart, $attemptId, $paid ? 'pagado' : 'pendiente') : null;

                return response()->json([
                    'ok' => false,
                    'code' => 'qr_amount_mismatch',
                    'paid' => $paid,
                    'payment_status' => $paid ? 'pagado' : 'pendiente',
                    'invoice_status' => strtoupper(trim((string) ($cart['estado_emision'] ?? 'NO_APLICA'))),
                    'qr' => $qr,
                    'terminal' => true,
                    'retry' => false,
                    'registration_error' => 'qr_amount_mismatch',
                    'message' => $paid
                        ? 'Facturación reportó una diferencia de monto para un pago confirmado. No vuelvas a pagar; el envío y la factura requieren revisión manual.'
                        : 'Facturación reportó una diferencia de monto y bloqueó este QR. No lo pagues; consulta soporte antes de iniciar otro intento.',
                ], 409);
            }

            return response()->json([
                'message' => 'No se pudo consultar el pago. Volveremos a intentarlo.',
                'retry' => true,
            ], 503);
        }

        $paymentStatus = $this->estadoPagoQrDesdeCarrito(
            $cart,
            (string) ($payment['payment_status'] ?? $cart['estado_pago'] ?? 'pendiente')
        );
        $invoiceStatus = strtoupper(trim((string) ($payment['invoice_status'] ?? $cart['estado_emision'] ?? 'NO_APLICA')));
        $invoiceNumber = trim((string) ($payment['invoice_number'] ?? $cart['numero_factura'] ?? ''));
        $invoiceError = null;
        $qrStatusPayload = $this->qrPayloadParaEstado($facturacion, $cart, $attemptId, $paymentStatus);

        if ($paymentStatus === 'pagado' && $this->qrSolicitudId($cart) === null) {
            $summary = $this->qrSummaryFromCart($cart);
            $requiredFields = [
                'servicio_extra_id', 'origen', 'destino_id', 'cantidad_paquetes', 'contenido',
                'remitente', 'carnet_remitente', 'direccion_recojo', 'destinatario', 'direccion_entrega',
            ];
            $missingFields = array_values(array_filter($requiredFields, fn ($field) =>
                blank($summary[$field] ?? null)
                || (in_array($field, ['servicio_extra_id', 'destino_id', 'cantidad_paquetes'], true) && (int) ($summary[$field] ?? 0) <= 0)
            ));
            if ($missingFields !== []) {
                $incidentSentKey = 'cliente-online-qr-shipment-incident-sent:'.$cartId;
                if (!Cache::get($incidentSentKey) && Cache::add('cliente-online-qr-shipment-incident-lock:'.$cartId, true, now()->addMinutes(5))) {
                    try {
                        $facturacion->reportarIncidenciaQrClienteOnline($cliente, $cartId, $attemptId, $missingFields);
                        Cache::forever($incidentSentKey, true);
                        Log::critical('Pago QR confirmado sin datos completos para reconstruir el envío; incidencia enviada a Facturación.', [
                            'cliente_id' => $cliente->id,
                            'facturacion_cart_id' => $cartId,
                            'missing_fields' => $missingFields,
                        ]);
                    } catch (\Throwable $exception) {
                        Log::critical('Pago QR confirmado sin datos completos y no se pudo registrar la incidencia en Facturación.', [
                            'cliente_id' => $cliente->id,
                            'facturacion_cart_id' => $cartId,
                            'missing_fields' => $missingFields,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                }

                return response()->json([
                    'ok' => false,
                    'paid' => true,
                    'payment_status' => 'pagado',
                    'invoice_status' => $invoiceStatus,
                    'invoice_number' => $invoiceNumber,
                    'invoice_download_url' => $invoiceStatus === 'FACTURADA' && filled($payment['invoice_pdf_url'] ?? null)
                        ? route('clientes.solicitudes.qr.invoice.download', ['qr_attempt_id' => $attemptId])
                        : null,
                    'qr' => $qrStatusPayload,
                    'terminal' => true,
                    'retry' => false,
                    'registration_error' => 'missing_shipment_data',
                    'message' => 'Pago confirmado. No se pudieron recuperar los datos del envío y la solicitud requiere revisión. Conserva el código de pago; no vuelvas a pagar.',
                ], 409);
            }
        }

        $invoiceAlreadyProcessing = in_array($invoiceStatus, [
            'FACTURADA', 'PENDIENTE', 'PROCESADA', 'EMITIENDO', 'EMISION_INCIERTA',
        ], true);
        $invoiceCanBeStarted = in_array($invoiceStatus, [
            '', 'NO_APLICA', 'ERROR', 'RECHAZADA', 'ANULADA', 'ANULACION_OBSERVADA',
        ], true);
        $invoiceRetryKey = 'cliente-online-qr-invoice-retry:'.$cartId;
        $retryDeferred = $paymentStatus === 'pagado'
            && in_array($invoiceStatus, ['ERROR', 'RECHAZADA'], true)
            && !Cache::add($invoiceRetryKey, true, now()->addMinutes(5));

        if ($paymentStatus === 'pagado' && !$invoiceAlreadyProcessing && $invoiceCanBeStarted && !$retryDeferred) {
            $invoiceLock = Cache::lock('cliente-online-qr-invoice:'.$cartId, 120);
            if (!$invoiceLock->get()) {
                $invoiceStatus = 'EMITIENDO';
                $invoiceError = 'La factura está siendo procesada. Se actualizará automáticamente.';
            } else {
                try {
                    $invoice = $facturacion->emitirFacturaQrPagada($cliente, $cartId);
                    $payment = $invoice;
                    $cart = is_array($invoice['cart'] ?? null) ? $invoice['cart'] : $cart;
                    $invoiceStatus = strtoupper(trim((string) ($invoice['invoice_status'] ?? $cart['estado_emision'] ?? 'PENDIENTE')));
                    $invoiceNumber = trim((string) ($invoice['invoice_number'] ?? $cart['numero_factura'] ?? '')) ?: $invoiceNumber;
                    if (in_array($invoiceStatus, ['ERROR', 'RECHAZADA'], true)) {
                        Cache::put($invoiceRetryKey, true, now()->addMinutes(5));
                    } else {
                        Cache::forget($invoiceRetryKey);
                    }
                } catch (\Throwable $exception) {
                        $invoiceError = 'La factura requiere revisión.';
                    Log::error('Pago QR confirmado, pero fallo la solicitud de factura.', [
                        'cliente_id' => $cliente->id,
                        'facturacion_cart_id' => $cartId,
                        'error' => $exception->getMessage(),
                    ]);
                    $invoiceStatus = 'ERROR';
                    Cache::put($invoiceRetryKey, true, now()->addMinutes(5));
                } finally {
                    $invoiceLock->release();
                }
            }
        } elseif ($retryDeferred) {
            $invoiceError = 'La factura sigue en revisión. Se volverá a intentar automáticamente en unos minutos.';
        }

        $solicitud = null;
        $mailWarning = null;
        if ($paymentStatus === 'pagado') {
            try {
                $summary = $this->qrSummaryFromCart($cart);
                $solicitudId = (int) ($summary['solicitud_cliente_id'] ?? 0);
                if ($solicitudId <= 0 && ! ServiceAvailability::isEnabled('solicitud')) {
                    $qrPayload = $facturacion->qrPayloadFromCart($cart, $attemptId);
                    $qrPayload['amount'] = number_format((float) $qrPayload['amount'], 2, '.', '');
                    $qrPayload['currency'] = 'BOB';
                    $qrPayload['qr_image'] = '';

                    return response()->json([
                        'ok' => true,
                        'payment_status' => 'pagado',
                        'paid' => true,
                        'terminal' => true,
                        'invoice_status' => $invoiceStatus,
                        'invoice_number' => $invoiceNumber,
                        'invoice_download_url' => $invoiceStatus === 'FACTURADA' && filled($payment['invoice_pdf_url'] ?? null)
                            ? route('clientes.solicitudes.qr.invoice.download', ['qr_attempt_id' => $attemptId])
                            : null,
                        'invoice_error' => $invoiceError,
                        'solicitud_codigo' => null,
                        'ticket_url' => null,
                        'qr' => $qrPayload,
                        'mail_warning' => null,
                        'shipment_registration_blocked' => true,
                        'message' => 'El pago fue confirmado. El registro del envío está deshabilitado temporalmente.',
                    ]);
                }

                if ($solicitudId > 0) {
                    $solicitud = SolicitudCliente::query()
                        ->where('cliente_id', (int) $cliente->id)
                        ->find($solicitudId);
                    $created = false;
                } else {
                    $lock = Cache::lock('cliente-online-qr-register:'.$cliente->id.':'.$attemptId, 90);
                    if (!$lock->get()) {
                        return response()->json([
                            'message' => 'El pago fue confirmado y el envio se esta registrando. Consulta nuevamente en unos segundos.',
                            'retry' => true,
                            'payment_status' => 'pagado',
                            'paid' => true,
                            'invoice_status' => $invoiceStatus,
                            'invoice_number' => $invoiceNumber,
                            'invoice_download_url' => $invoiceStatus === 'FACTURADA' && filled($payment['invoice_pdf_url'] ?? null)
                                ? route('clientes.solicitudes.qr.invoice.download', ['qr_attempt_id' => $attemptId])
                                : null,
                            'qr' => $qrStatusPayload,
                        ], 503);
                    }

                    try {
                        // Another status request may have linked the shipment while this one waited.
                        $latest = $facturacion->buscarSolicitudQr($cliente, $attemptId)['cart'] ?? $cart;
                        $latestSummary = is_array($latest) ? $this->qrSummaryFromCart($latest) : [];
                        $latestSolicitudId = (int) ($latestSummary['solicitud_cliente_id'] ?? 0);
                        $localRequestCacheKey = 'cliente-online-qr-local-solicitud:'.$cliente->id.':'.$attemptId;
                        if ($latestSolicitudId > 0) {
                            $solicitud = SolicitudCliente::query()
                                ->where('cliente_id', (int) $cliente->id)
                                ->find($latestSolicitudId);
                            $created = false;
                            if ($solicitud) {
                                Cache::forget($localRequestCacheKey);
                            }
                        } elseif ((int) Cache::get($localRequestCacheKey, 0) > 0
                            && ($cachedSolicitud = SolicitudCliente::query()
                                ->where('cliente_id', (int) $cliente->id)
                                ->find((int) Cache::get($localRequestCacheKey, 0)))) {
                            $solicitud = $cachedSolicitud;
                            $facturacion->vincularSolicitudQr($cliente, $attemptId, $solicitud);
                            Cache::forget($localRequestCacheKey);
                            $created = false;
                        } else {
                            $solicitud = $this->registrarSolicitudQrPagada($cliente, $cart);
                            Cache::forever($localRequestCacheKey, (int) $solicitud->id);
                            $facturacion->vincularSolicitudQr($cliente, $attemptId, $solicitud);
                            Cache::forget($localRequestCacheKey);
                            $created = true;
                        }
                    } finally {
                        $lock->release();
                    }
                }

                if (!$solicitud) {
                    throw new \RuntimeException('No se pudo recuperar la solicitud vinculada al pago QR.');
                }
                if ($created) {
                    try {
                        Mail::to($cliente->email)->send(new SolicitudClienteCreadaMail($solicitud, $cliente));
                    } catch (\Throwable $exception) {
                        Log::warning('El envío QR se registró, pero falló el correo de confirmación.', [
                            'solicitud_id' => $solicitud->id,
                            'cliente_id' => $cliente->id,
                            'error' => $exception->getMessage(),
                        ]);
                                $mailWarning = 'El envio quedo registrado, pero no se pudo enviar el correo de confirmacion.';
                    }
                }
            } catch (\Throwable $exception) {
                Log::error('Pago QR confirmado, pero no se pudo completar el registro del envío.', [
                    'cliente_id' => $cliente->id,
                    'facturacion_cart_id' => $cartId,
                    'qr_attempt_id' => $attemptId,
                    'error' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'El pago fue confirmado, pero no se pudo registrar el envio. Estamos reintentando.',
                    'retry' => true,
                    'payment_status' => 'pagado',
                    'paid' => true,
                    'invoice_status' => $invoiceStatus,
                    'invoice_number' => $invoiceNumber,
                    'invoice_download_url' => $invoiceStatus === 'FACTURADA' && filled($payment['invoice_pdf_url'] ?? null)
                        ? route('clientes.solicitudes.qr.invoice.download', ['qr_attempt_id' => $attemptId])
                        : null,
                    'invoice_error' => $invoiceError,
                    'qr' => $qrStatusPayload,
                ], 503);
            }
        }

        if ($paymentStatus === 'pagado'
            && $solicitud
            && ($solicitud->forma_pago === 'origen' || $solicitud->tipo_pago === 'qr')) {
            $estadoActual = strtoupper(trim((string) Estado::query()->whereKey($solicitud->estado_id)->value('nombre_estado')));
            $justificacion = trim((string) $solicitud->justificacion);
            if ($estadoActual === 'CANCELADO' && str_contains(mb_strtolower($justificacion), 'qr sin pago confirmado')) {
                $solicitud->update([
                    'estado_id' => $this->resolveSolicitudEstadoId(),
                    'justificacion' => trim($justificacion."\nReactivada automáticamente al confirmarse el pago QR tardío."),
                ]);
                $solicitud->refresh();
                Log::warning('Se reactivó una solicitud cancelada por vencimiento al confirmarse el pago QR tardío.', [
                    'solicitud_id' => $solicitud->id,
                    'cliente_id' => $cliente->id,
                    'facturacion_cart_id' => $cartId,
                ]);
            }
            $solicitud->update([
                'forma_pago' => 'pagado',
                'tipo_pago' => 'qr',
                'pago_destinatario' => false,
            ]);
        }

        if ($invoiceError === null) {
            $invoiceStatus = strtoupper(trim((string) ($cart['estado_emision'] ?? $invoiceStatus)));
        }
        $invoiceNumber = trim((string) ($cart['numero_factura'] ?? $invoiceNumber)) ?: $invoiceNumber;
        $invoiceIsFinal = in_array($invoiceStatus, ['FACTURADA', 'RECHAZADA', 'ERROR', 'ANULADA'], true);
        $terminal = $paymentStatus === 'cancelado'
            || ($paymentStatus === 'pagado' && $solicitud && $invoiceIsFinal);
        $qrPayload = $this->qrPayloadParaEstado($facturacion, $cart, $attemptId, $paymentStatus);

        return response()->json([
            'ok' => true,
            'payment_status' => $paymentStatus,
            'paid' => $paymentStatus === 'pagado',
            'terminal' => $terminal,
            'invoice_status' => $invoiceStatus,
            'invoice_number' => $invoiceNumber,
            'invoice_download_url' => $invoiceStatus === 'FACTURADA' && filled($payment['invoice_pdf_url'] ?? null)
                ? route('clientes.solicitudes.qr.invoice.download', ['qr_attempt_id' => $attemptId])
                : null,
            'invoice_error' => $invoiceError,
            'invoice_retry_allowed' => $paymentStatus === 'pagado'
                && in_array($invoiceStatus, ['ERROR', 'RECHAZADA'], true),
            'billing_data' => [
                'tipo_documento' => (string) ($cart['tipo_documento'] ?? ''),
                'numero_documento' => (string) ($cart['numero_documento'] ?? ''),
                'complemento_documento' => (string) ($cart['complemento_documento'] ?? ''),
                'razon_social' => (string) ($cart['razon_social'] ?? ''),
                'correo_facturacion' => (string) ($cart['correo_facturacion'] ?? ''),
            ],
            'solicitud_codigo' => $solicitud?->codigo_solicitud,
            'ticket_url' => $solicitud ? route('clientes.solicitudes.ticket', ['solicitud' => $solicitud->id]) : null,
            'qr' => $qrPayload,
            'mail_warning' => $mailWarning,
            'message' => $this->mensajeEstadoPagoQr($paymentStatus, $invoiceStatus, $solicitud?->codigo_solicitud),
        ]);
    }

    public function retryQrInvoiceWithBillingData(Request $request, ClienteSolicitudFacturacionService $facturacion): JsonResponse
    {
        $data = $request->validate([
            'qr_attempt_id' => ['required', 'uuid'],
            'tipo_documento' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::in(array_keys(Cliente::tiposDocumentoIdentidad()))],
            'numero_documento' => ['required', 'string', 'max:80'],
            'complemento_documento' => ['nullable', 'string', 'max:30'],
            'razon_social' => ['required', 'string', 'max:255'],
            'correo_facturacion' => ['required', 'email', 'max:50'],
        ]);
        $cliente = Auth::guard('cliente')->user();
        $attemptId = strtolower(trim((string) $data['qr_attempt_id']));

        try {
            $lookup = $facturacion->buscarSolicitudQr($cliente, $attemptId);
            $cart = $lookup['cart'] ?? null;
            if (!is_array($cart) || (int) ($cart['id'] ?? 0) <= 0) {
                return response()->json(['message' => 'No se encontró el pago QR de esta cuenta.'], 404);
            }
            $isClienteOnlineCart = collect((array) ($cart['items'] ?? []))
                ->contains(fn ($item) => strtolower((string) data_get($item, 'origen_tipo', '')) === 'cliente_online');
            if (!$isClienteOnlineCart) {
                return response()->json(['message' => 'Este pago no corresponde a una solicitud online.'], 404);
            }
            $cartId = (int) $cart['id'];
            $payment = $facturacion->consultarPagoQr($cliente, $cartId);
            $cart = is_array($payment['cart'] ?? null) ? $payment['cart'] : $cart;
            if ($this->normalizarEstadoPagoQr((string) ($payment['payment_status'] ?? $cart['estado_pago'] ?? '')) !== 'pagado') {
                return response()->json(['message' => 'Solo puedes corregir la factura de un pago confirmado.'], 409);
            }

            $invoiceStatus = strtoupper(trim((string) ($payment['invoice_status'] ?? $cart['estado_emision'] ?? '')));
            if (!in_array($invoiceStatus, ['ERROR', 'RECHAZADA'], true)) {
                return response()->json([
                    'message' => 'La factura no está en un estado que permita reintentarla. Consulta su estado antes de hacer cambios.',
                    'invoice_status' => $invoiceStatus,
                ], 409);
            }

            $invoiceLock = Cache::lock('cliente-online-qr-invoice:'.$cartId, 120);
            if (!$invoiceLock->get()) {
                return response()->json(['message' => 'La factura se está procesando. Espera un momento y consulta nuevamente.'], 409);
            }

            try {
                $result = $facturacion->corregirDatosYReintentarFacturaQrPagada($cliente, $cart, $data);
            } finally {
                $invoiceLock->release();
            }

            $retryKey = 'cliente-online-qr-invoice-retry:'.$cartId;
            if (in_array(strtoupper((string) ($result['invoice_status'] ?? '')), ['ERROR', 'RECHAZADA'], true)) {
                Cache::put($retryKey, true, now()->addMinutes(5));
            } else {
                Cache::forget($retryKey);
            }

            return $this->checkQrStatusForCustomer($cliente, $attemptId, $facturacion);
        } catch (\Throwable $exception) {
            Log::error('El cliente online no pudo corregir/reintentar la factura de un QR pagado.', [
                'cliente_id' => $cliente->id,
                'qr_attempt_id' => $attemptId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo reintentar la factura. El pago sigue confirmado; no vuelvas a pagar. Intenta nuevamente desde Mis solicitudes.',
                'paid' => true,
                'retry' => true,
            ], 502);
        }
    }

    public function downloadQrInvoice(
        Request $request,
        string $qr_attempt_id,
        ClienteSolicitudFacturacionService $facturacion
    ) {
        $cliente = Auth::guard('cliente')->user();
        $attemptId = strtolower(trim($qr_attempt_id));
        abort_unless(\Illuminate\Support\Str::isUuid($attemptId), 404);

        try {
            $lookup = $facturacion->buscarSolicitudQr($cliente, $attemptId);
            $cart = $lookup['cart'] ?? null;
            abort_unless(is_array($cart) && (int) ($cart['id'] ?? 0) > 0, 404);

            $isClienteOnlineCart = collect((array) ($cart['items'] ?? []))
                ->contains(fn ($item) => strtolower((string) data_get($item, 'origen_tipo', '')) === 'cliente_online');
            abort_unless($isClienteOnlineCart, 404);

            $payment = $facturacion->consultarPagoQr($cliente, (int) $cart['id']);
            abort_unless($this->normalizarEstadoPagoQr((string) ($payment['payment_status'] ?? '')) === 'pagado', 409, 'El pago todavía no está confirmado.');
            abort_unless(strtoupper((string) ($payment['invoice_status'] ?? '')) === 'FACTURADA', 409, 'La factura todavía no está emitida.');

            $url = trim((string) ($payment['invoice_pdf_url'] ?? ''));
            abort_unless($this->isAllowedQrInvoicePdfUrl($url), 404, 'El PDF de esta factura todavía no está disponible.');

            $response = Http::connectTimeout(10)->timeout(45)
                ->withoutRedirecting()->accept('application/pdf')->get($url);
            if (!$response->successful() || !str_starts_with($response->body(), '%PDF-')) {
                throw new \RuntimeException('Facturación no devolvió un PDF válido para la factura QR.');
            }

            $invoiceNumber = trim((string) ($payment['invoice_number'] ?? ''));
            $safeNumber = preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceNumber) ?: (string) $cart['id'];

            return response($response->body(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="factura-'.$safeNumber.'.pdf"',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo descargar la factura de un pago QR del cliente online.', [
                'cliente_id' => $cliente?->id,
                'qr_attempt_id' => $attemptId,
                'error' => $exception->getMessage(),
            ]);

            abort(502, 'No se pudo descargar la factura. Inténtalo nuevamente en unos minutos.');
        }
    }

    private function isAllowedQrInvoicePdfUrl(string $url): bool
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

    private function registrarSolicitudQrPagada(Cliente $cliente, array $cart): SolicitudCliente
    {
        $item = collect((array) ($cart['items'] ?? []))
            ->first(fn ($item) => strtolower((string) data_get($item, 'origen_tipo', '')) === 'cliente_online');
        $summary = (array) data_get($item, 'resumen_origen', []);
        if ($summary === []) {
            throw new \RuntimeException('La API no devolvio los datos del envio QR.');
        }

        $data = [
            'servicio_extra_id' => (int) ($summary['servicio_extra_id'] ?? 0),
            'origen' => (string) ($summary['origen'] ?? ''),
            'destino_id' => (int) ($summary['destino_id'] ?? 0),
            'cantidad' => (int) ($summary['cantidad_paquetes'] ?? 1),
            'recargo_volumen' => (bool) ($summary['recargo_volumen'] ?? false),
            'contenido' => (string) ($summary['contenido'] ?? ''),
            'nombre_remitente' => (string) ($summary['remitente'] ?? ''),
            'carnet' => (string) ($summary['carnet_remitente'] ?? ''),
            'telefono_remitente' => (string) ($summary['telefono_remitente'] ?? ''),
            'direccion_recojo' => (string) ($summary['direccion_recojo'] ?? ''),
            'nombre_destinatario' => (string) ($summary['destinatario'] ?? ''),
            'telefono_destinatario' => (string) ($summary['telefono_destinatario'] ?? ''),
            'direccion_entrega' => (string) ($summary['direccion_entrega'] ?? ''),
            'forma_pago' => 'pagado',
            'tipo_pago' => 'qr',
            'precio_pagado' => round((float) ($cart['total'] ?? data_get($item, 'total_linea', 0)), 2),
        ];

        return DB::transaction(fn () => $this->persistirSolicitud($cliente, $data));
    }

    private function qrPayloadParaEstado(
        ClienteSolicitudFacturacionService $facturacion,
        array $cart,
        string $attemptId,
        string $paymentStatus
    ): array {
        $qr = $facturacion->qrPayloadFromCart($cart, $attemptId);
        $qr['amount'] = number_format((float) $qr['amount'], 2, '.', '');
        $qr['currency'] = 'BOB';
        if ($paymentStatus !== 'pendiente') {
            $qr['qr_image'] = '';
        }

        return $qr;
    }

    private function normalizarEstadoPagoQr(string $status): string
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

    private function mensajeEstadoPagoQr(string $paymentStatus, string $invoiceStatus, ?string $solicitudCodigo): string
    {
        if ($paymentStatus === 'cancelado') {
            return 'Este intento QR vencio o fue cancelado. Puedes iniciar un nuevo intento de pago.';
        }
        if ($paymentStatus !== 'pagado') {
            return 'Esperando la confirmacion del pago QR.';
        }
        if ($invoiceStatus === 'FACTURADA') {
            return 'Pago confirmado, factura emitida y envio registrado'.($solicitudCodigo ? ' con codigo '.$solicitudCodigo : '').'.';
        }
        if (in_array($invoiceStatus, ['ERROR', 'RECHAZADA', 'ANULADA'], true)) {
            return 'Pago confirmado y envio registrado. La factura requiere revision.';
        }

        return 'Pago confirmado y envio registrado. La factura se esta procesando.';
    }

    private function qrSummaryFromCart(array $cart): array
    {
        $item = collect((array) ($cart['items'] ?? []))
            ->first(fn ($item) => strtolower((string) data_get($item, 'origen_tipo', '')) === 'cliente_online');

        return (array) data_get($item, 'resumen_origen', []);
    }

    private function buscarSolicitudQrVinculada(iterable $solicitudes, array $summary, array $cart): ?SolicitudCliente
    {
        $servicioExtraId = (int) ($summary['servicio_extra_id'] ?? 0);
        $destinoId = (int) ($summary['destino_id'] ?? 0);
        if ($servicioExtraId <= 0 || $destinoId <= 0 || empty($cart['created_at'])) {
            return null;
        }

        try {
            $qrCreatedAt = \Illuminate\Support\Carbon::parse($cart['created_at'])->getTimestamp();
        } catch (\Throwable $exception) {
            return null;
        }

        $normalize = static fn ($value): string => mb_strtoupper(trim((string) $value));
        $candidates = collect($solicitudes)->filter(function (SolicitudCliente $solicitud) use (
            $summary,
            $servicioExtraId,
            $destinoId,
            $cart,
            $qrCreatedAt,
            $normalize
        ): bool {
            if ($solicitud->forma_pago !== 'pagado'
                || (int) $solicitud->servicio_extra_id !== $servicioExtraId
                || (int) $solicitud->destino_id !== $destinoId
                || (int) $solicitud->cantidad !== (int) ($summary['cantidad_paquetes'] ?? 1)
                || abs((float) $solicitud->precio - (float) ($cart['total'] ?? 0)) >= 0.01) {
                return false;
            }

            $solicitudCreatedAt = $solicitud->created_at?->getTimestamp();
            if (!$solicitudCreatedAt || abs($solicitudCreatedAt - $qrCreatedAt) > 172800) {
                return false;
            }

            $fields = [
                'origen' => 'origen',
                'contenido' => 'contenido',
                'nombre_remitente' => 'remitente',
                'carnet' => 'carnet_remitente',
                'telefono_remitente' => 'telefono_remitente',
                'nombre_destinatario' => 'destinatario',
                'telefono_destinatario' => 'telefono_destinatario',
                'direccion_recojo' => 'direccion_recojo',
            ];
            foreach ($fields as $solicitudField => $summaryField) {
                if ($normalize($solicitud->{$solicitudField}) !== $normalize($summary[$summaryField] ?? '')) {
                    return false;
                }
            }

            return true;
        })->values();

        // Solo enlaza cuando los datos identifican un único envío.
        return $candidates->count() === 1 ? $candidates->first() : null;
    }

    private function qrAttemptIdFromCart(array $cart): ?string
    {
        $summary = $this->qrSummaryFromCart($cart);
        $attemptId = strtolower(trim((string) ($summary['qr_attempt_id'] ?? '')));
        if ($attemptId !== '' && \Illuminate\Support\Str::isUuid($attemptId)) {
            return $attemptId;
        }

        $item = collect((array) ($cart['items'] ?? []))
            ->first(fn ($item) => strtolower((string) data_get($item, 'origen_tipo', '')) === 'cliente_online');
        $code = strtoupper(trim((string) data_get($item, 'codigo', '')));
        if (!preg_match('/^SOLQR-([A-F0-9]{32})$/', $code, $matches)) {
            return null;
        }

        $hex = strtolower($matches[1]);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    private function qrSolicitudId(array $cart): ?int
    {
        $id = (int) ($this->qrSummaryFromCart($cart)['solicitud_cliente_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    private function qrCartWasIssued(array $cart): bool
    {
        $cartState = strtolower(trim((string) ($cart['estado'] ?? '')));
        $paymentState = $this->normalizarEstadoPagoQr((string) ($cart['estado_pago'] ?? ''));

        return trim((string) ($cart['qr_transaction_id'] ?? '')) !== ''
            || in_array($cartState, ['pendiente_pago', 'emitido'], true)
            || $paymentState === 'pagado';
    }

    private function estadoPagoQrDesdeCarrito(array $cart, ?string $status = null): string
    {
        $paymentStatus = $this->normalizarEstadoPagoQr(
            $status ?? (string) ($cart['estado_pago'] ?? 'pendiente')
        );
        if ($paymentStatus !== 'pendiente' || empty($cart['created_at'])) {
            return $paymentStatus;
        }

        try {
            if (\Illuminate\Support\Carbon::parse($cart['created_at'])->lte(now()->subHours(48))) {
                return 'cancelado';
            }
        } catch (\Throwable $exception) {
            Log::warning('No se pudo interpretar la fecha de creación del QR de cliente online.', [
                'cart_id' => $cart['id'] ?? null,
                'created_at' => $cart['created_at'],
                'error' => $exception->getMessage(),
            ]);
        }

        return $paymentStatus;
    }

    public function generateQr(Request $request, ClienteSolicitudFacturacionService $facturacion): JsonResponse
    {
        if (! ServiceAvailability::isEnabled('solicitud')) {
            return response()->json(['message' => self::DELIVERY_EXPRESS_DISABLED_MESSAGE], 503);
        }

        $servicioExtraIds = TarifarioTiktoker::query()
            ->whereNotNull('servicio_extra_id')
            ->distinct()
            ->pluck('servicio_extra_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'qr_attempt_id' => ['required', 'uuid'],
            'precio_cotizado' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'forma_pago' => ['required', 'in:origen,destino'],
            'tipo_pago' => ['required', 'in:qr'],
            'tipo_documento' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::in(array_keys(Cliente::tiposDocumentoIdentidad()))],
            'numero_documento' => ['required', 'string', 'max:80'],
            'complemento_documento' => ['nullable', 'string', 'max:30'],
            'razon_social' => ['required', 'string', 'max:255'],
            'correo_facturacion' => ['required', 'email', 'max:50'],
            'servicio_extra_id' => ['required', 'integer', 'in:'.implode(',', $servicioExtraIds)],
            'origen' => ['required', 'string'],
            'destino_id' => ['required', 'integer', 'exists:destino,id'],
            'cantidad' => ['required', 'integer', 'in:1'],
            'contenido' => ['required', 'string'],
            'nombre_remitente' => ['required', 'string', 'max:255'],
            'carnet' => ['required', 'string', 'max:255'],
            'telefono_remitente' => ['required', 'string', 'max:50'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['required', 'string', 'max:50'],
            'direccion_recojo' => ['required', 'string', 'max:255'],
            'direccion_entrega' => ['required', 'string', 'max:255'],
            'recargo_volumen' => ['nullable', 'boolean'],
        ]);

        $cliente = Auth::guard('cliente')->user();
        $attemptId = strtolower((string) $data['qr_attempt_id']);

        try {
            $destino = Destino::query()->find((int) $data['destino_id']);
            $servicioExtra = ServicioExtra::query()->find((int) $data['servicio_extra_id']);
            if (! $destino || ! $servicioExtra) {
                return response()->json(['message' => 'El servicio o destino seleccionado ya no está disponible.'], 422);
            }

            $tarifario = $this->resolveTarifarioTiktoker(
                (int) $data['servicio_extra_id'],
                (string) $data['origen'],
                (int) $data['destino_id']
            );
            $recargoVolumen = (bool) ($data['recargo_volumen'] ?? false);
            $amount = round((float) $tarifario->peso1 + ($recargoVolumen ? 10 : 0), 2);
            if ($amount <= 0) {
                return response()->json(['message' => 'La tarifa calculada no permite generar un QR.'], 422);
            }
            $quotedAmount = round((float) $data['precio_cotizado'], 2);
            if (abs($quotedAmount - $amount) >= 0.01) {
                return response()->json([
                    'message' => 'La tarifa cambió desde la última cotización. Se actualizó el precio; revísalo y confirma nuevamente.',
                    'precio_actual' => number_format($amount, 2, '.', ''),
                ], 409);
            }

            Log::info('Se validó el precio cotizado para QR de cliente online.', [
                'cliente_id' => $cliente->id,
                'qr_attempt_id' => $attemptId,
                'tarifario_id' => $tarifario->id,
                'precio_cotizado' => $quotedAmount,
                'precio_a_cobrar' => $amount,
                'recargo_volumen' => $recargoVolumen,
            ]);

            $qr = $facturacion->generarQr($cliente, $data, $servicioExtra, $destino, $amount);

            return response()->json([
                'amount' => number_format((float) $qr['amount'], 2, '.', ''),
                'currency' => 'BOB',
                'internal_code' => $qr['internal_code'],
                'qr_image' => $qr['qr_image'],
                'qr_attempt_id' => $attemptId,
                'payment_status' => $this->normalizarEstadoPagoQr((string) ($qr['payment_status'] ?? 'pendiente')),
            ]);
        } catch (\RuntimeException $exception) {
            Log::warning('No se pudo preparar la facturacion del QR para el cliente online.', [
                'cliente_id' => Auth::guard('cliente')->id(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => (
                    str_starts_with($exception->getMessage(), 'El total del carrito de Facturacion')
                    || str_starts_with($exception->getMessage(), 'El QR de este intento venció')
                    || str_starts_with($exception->getMessage(), 'El QR anterior fue cancelado')
                    || str_starts_with($exception->getMessage(), 'Este intento QR ya fue pagado')
                    || str_starts_with($exception->getMessage(), 'No se pudo generar el QR en Qhantuy.')
                )
                    ? $exception->getMessage()
                    : 'No se pudo generar el QR en este momento. Intenta nuevamente.',
            ], 502);
        } catch (\Throwable $exception) {
            Log::error('No se pudo generar el QR para el cliente online.', [
                'cliente_id' => Auth::guard('cliente')->id(),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No se pudo generar el QR en Facturación. Intenta nuevamente.',
            ], 502);
        }
    }
}

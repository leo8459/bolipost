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
use App\Support\TiktokerEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ClienteSolicitudController extends Controller
{
    private const DIRECCION_DESTINATARIO_VENTANILLA = 'CORREOS DE BOLIVIA';

    private const PICKUP_CONFIRMATION_MESSAGE = 'Se comunicará un operario dentro de las próximas 24 horas para confirmar el recojo. Si nadie se comunica, por favor contáctese al 71522163.';

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

    public function create(ClienteSolicitudFacturacionService $facturacion): View
    {
        $cliente = Auth::guard('cliente')->user();
        $pendingQr = null;
        try {
            $carts = $facturacion->listarSolicitudesQr($cliente, 1, 20);
            foreach ((array) data_get($carts, 'data.carts', []) as $cart) {
                if (!is_array($cart) || $this->qrSolicitudId($cart) !== null) {
                    continue;
                }
                if ($this->normalizarEstadoPagoQr((string) ($cart['estado_pago'] ?? 'pendiente')) !== 'pendiente') {
                    continue;
                }

                $attemptId = $this->qrAttemptIdFromCart($cart);
                if (!$attemptId) {
                    continue;
                }
                $fullCart = $facturacion->buscarSolicitudQr($cliente, $attemptId)['cart'] ?? null;
                if (!is_array($fullCart)) {
                    continue;
                }
                $qr = $facturacion->qrPayloadFromCart($fullCart, $attemptId);
                $pendingQr = (object) [
                    'importe' => $qr['amount'],
                    'internal_code' => $qr['internal_code'],
                    'qr_image' => $qr['qr_image'],
                    'qr_attempt_id' => $attemptId,
                ];
                break;
            }
        } catch (\Throwable $exception) {
            Log::warning('No se pudieron recuperar los pagos QR del cliente al abrir la nueva solicitud.', [
                'cliente_id' => $cliente->id,
                'error' => $exception->getMessage(),
            ]);
        }
        $servicioExtraIds = TarifarioTiktoker::query()
            ->whereNotNull('servicio_extra_id')
            ->distinct()
            ->pluck('servicio_extra_id');

        return view('clientes.solicitudes', [
            'cliente' => $cliente,
            'pendingQr' => $pendingQr,
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
        $qrHistoryWarning = null;
        try {
            $carts = $facturacion->listarSolicitudesQr($cliente, 1, 100);
            foreach ((array) data_get($carts, 'data.carts', []) as $cart) {
                if (!is_array($cart)) {
                    continue;
                }
                $attemptId = $this->qrAttemptIdFromCart($cart);
                if (!$attemptId) {
                    continue;
                }

                $summary = $this->qrSummaryFromCart($cart);
                $solicitudId = (int) ($summary['solicitud_cliente_id'] ?? 0);
                if ($solicitudId > 0) {
                    $qrAttemptsBySolicitudId[$solicitudId] = $attemptId;
                    continue;
                }

                $pagosQrSinSolicitud->push((object) [
                    'qr_attempt_id' => $attemptId,
                    'internal_code' => (string) ($cart['codigo_orden'] ?? ''),
                    'importe' => (float) ($cart['total'] ?? 0),
                    'estado_pago' => (string) ($cart['estado_pago'] ?? 'pendiente'),
                    'created_at' => !empty($cart['created_at'])
                        ? \Illuminate\Support\Carbon::parse($cart['created_at'])
                        : null,
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
            'qrHistoryWarning' => $qrHistoryWarning,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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
            'cantidad' => ['required', 'integer', 'min:1'],
            'contenido' => ['required', 'string'],
            'nombre_remitente' => ['required', 'string', 'max:255'],
            'carnet' => ['required', 'string', 'max:255'],
            'telefono_remitente' => ['nullable', 'string', 'max:50'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['nullable', 'string', 'max:50'],
            'direccion_recojo' => ['required', 'string', 'max:255'],
            'direccion_entrega' => ['required', 'string', 'max:255'],
            'forma_pago' => ['required', 'in:origen,destino'],
        ]);

        if ($data['forma_pago'] === 'origen') {
            $message = 'Para pagar en origen, genera el QR y espera la confirmacion del pago para registrar el envio.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()->back()->withInput()->withErrors(['forma_pago' => $message]);
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

            return redirect()
                ->route('clientes.solicitudes.history')
                ->with('success', $message)
                ->with('pickup_notice', self::PICKUP_CONFIRMATION_MESSAGE)
                ->with('warning', 'La solicitud se registro, pero no se pudo enviar el correo automatico. Revisa la configuracion SMTP.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'codigo_solicitud' => $solicitud->codigo_solicitud,
            ], 201);
        }

        return redirect()
            ->route('clientes.solicitudes.history')
            ->with('success', $message)
            ->with('pickup_notice', self::PICKUP_CONFIRMATION_MESSAGE);
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
                : (float) $tarifarioTiktoker->peso1,
            'forma_pago' => (string) ($data['forma_pago'] ?? 'destino'),
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
        $attemptId = strtolower((string) $data['qr_attempt_id']);
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

            return response()->json([
                'message' => 'No se pudo consultar el pago. Volveremos a intentarlo.',
                'retry' => true,
            ], 503);
        }

        $paymentStatus = $this->normalizarEstadoPagoQr((string) ($payment['payment_status'] ?? $cart['estado_pago'] ?? 'pendiente'));
        $invoiceStatus = strtoupper(trim((string) ($payment['invoice_status'] ?? $cart['estado_emision'] ?? 'NO_APLICA')));
        $invoiceNumber = trim((string) ($payment['invoice_number'] ?? $cart['numero_factura'] ?? ''));
        $invoiceError = null;

        $invoiceAlreadyProcessing = in_array($invoiceStatus, [
            'FACTURADA', 'PENDIENTE', 'PROCESADA', 'EMITIENDO', 'EMISION_INCIERTA',
        ], true);
        $invoiceCanBeStarted = in_array($invoiceStatus, [
            '', 'NO_APLICA', 'ERROR', 'RECHAZADA', 'ANULADA', 'ANULACION_OBSERVADA',
        ], true);

        if ($paymentStatus === 'pagado' && !$invoiceAlreadyProcessing && $invoiceCanBeStarted) {
            try {
                $invoice = $facturacion->emitirFacturaQrPagada($cliente, $cartId);
                $payment = $invoice;
                $cart = is_array($invoice['cart'] ?? null) ? $invoice['cart'] : $cart;
                $invoiceStatus = strtoupper(trim((string) ($invoice['invoice_status'] ?? $cart['estado_emision'] ?? 'PENDIENTE')));
                $invoiceNumber = trim((string) ($invoice['invoice_number'] ?? $cart['numero_factura'] ?? '')) ?: $invoiceNumber;
            } catch (\Throwable $exception) {
                Log::error('El pago QR fue confirmado, pero no se pudo solicitar la factura online.', [
                    'cliente_id' => $cliente->id,
                    'facturacion_cart_id' => $cartId,
                    'error' => $exception->getMessage(),
                ]);
                $invoiceError = 'La factura requiere revision.';
                $invoiceStatus = 'ERROR';
            }
        }

        $solicitud = null;
        $mailWarning = null;
        if ($paymentStatus === 'pagado') {
            try {
                $summary = $this->qrSummaryFromCart($cart);
                $solicitudId = (int) ($summary['solicitud_cliente_id'] ?? 0);
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
                        ], 503);
                    }

                    try {
                        // Another status request may have linked the shipment while this one waited.
                        $latest = $facturacion->buscarSolicitudQr($cliente, $attemptId)['cart'] ?? $cart;
                        $latestSummary = is_array($latest) ? $this->qrSummaryFromCart($latest) : [];
                        $latestSolicitudId = (int) ($latestSummary['solicitud_cliente_id'] ?? 0);
                        if ($latestSolicitudId > 0) {
                            $solicitud = SolicitudCliente::query()
                                ->where('cliente_id', (int) $cliente->id)
                                ->find($latestSolicitudId);
                            $created = false;
                        } else {
                            $solicitud = $this->registrarSolicitudQrPagada($cliente, $cart);
                            $facturacion->vincularSolicitudQr($cliente, $attemptId, $solicitud);
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
                        Log::error('No se pudo enviar el correo de solicitud pagada al cliente.', [
                            'solicitud_id' => $solicitud->id,
                            'cliente_id' => $cliente->id,
                            'error' => $exception->getMessage(),
                        ]);
                        $mailWarning = 'El envio quedo registrado, pero no se pudo enviar el correo de confirmacion.';
                    }
                }
            } catch (\Throwable $exception) {
                Log::error('El pago QR se confirmo, pero no se pudo registrar la solicitud de envio.', [
                    'cliente_id' => $cliente->id,
                    'facturacion_cart_id' => $cartId,
                    'error' => $exception->getMessage(),
                ]);

                return response()->json([
                    'message' => 'El pago fue confirmado, pero no se pudo registrar el envio. Estamos reintentando.',
                    'retry' => true,
                    'payment_status' => 'pagado',
                    'invoice_status' => $invoiceStatus,
                ], 503);
            }
        }

        $invoiceStatus = strtoupper(trim((string) ($cart['estado_emision'] ?? $invoiceStatus)));
        $invoiceNumber = trim((string) ($cart['numero_factura'] ?? $invoiceNumber)) ?: $invoiceNumber;
        $invoiceIsFinal = in_array($invoiceStatus, ['FACTURADA', 'RECHAZADA', 'ERROR', 'ANULADA'], true);
        $terminal = $paymentStatus === 'cancelado'
            || ($paymentStatus === 'pagado' && $solicitud && $invoiceIsFinal);
        $qrPayload = $facturacion->qrPayloadFromCart($cart, $attemptId);
        $qrPayload['amount'] = number_format((float) $qrPayload['amount'], 2, '.', '');
        $qrPayload['currency'] = 'BOB';
        if ($paymentStatus !== 'pendiente') {
            $qrPayload['qr_image'] = '';
        }

        return response()->json([
            'ok' => true,
            'payment_status' => $paymentStatus,
            'paid' => $paymentStatus === 'pagado',
            'terminal' => $terminal,
            'invoice_status' => $invoiceStatus,
            'invoice_number' => $invoiceNumber,
            'invoice_error' => $invoiceError,
            'solicitud_codigo' => $solicitud?->codigo_solicitud,
            'ticket_url' => $solicitud ? route('clientes.solicitudes.ticket', ['solicitud' => $solicitud->id]) : null,
            'qr' => $qrPayload,
            'mail_warning' => $mailWarning,
            'message' => $this->mensajeEstadoPagoQr($paymentStatus, $invoiceStatus, $solicitud?->codigo_solicitud),
        ]);
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
            'contenido' => (string) ($summary['contenido'] ?? ''),
            'nombre_remitente' => (string) ($summary['remitente'] ?? ''),
            'carnet' => (string) ($summary['carnet_remitente'] ?? ''),
            'telefono_remitente' => (string) ($summary['telefono_remitente'] ?? ''),
            'direccion_recojo' => (string) ($summary['direccion_recojo'] ?? ''),
            'nombre_destinatario' => (string) ($summary['destinatario'] ?? ''),
            'telefono_destinatario' => (string) ($summary['telefono_destinatario'] ?? ''),
            'direccion_entrega' => (string) ($summary['direccion_entrega'] ?? ''),
            'forma_pago' => 'origen',
            'precio_pagado' => round((float) ($cart['total'] ?? data_get($item, 'total_linea', 0)), 2),
        ];

        return DB::transaction(fn () => $this->persistirSolicitud($cliente, $data));
    }

    private function normalizarEstadoPagoQr(string $status): string
    {
        $status = strtolower(trim($status));
        if (in_array($status, ['pagado', 'paid', 'success', 'completed', 'approved', 'confirmed'], true)) {
            return 'pagado';
        }
        if (in_array($status, ['cancelado', 'cancelled', 'rejected', 'failed', 'expired'], true)) {
            return 'cancelado';
        }

        return 'pendiente';
    }

    private function mensajeEstadoPagoQr(string $paymentStatus, string $invoiceStatus, ?string $solicitudCodigo): string
    {
        if ($paymentStatus === 'cancelado') {
            return 'El pago QR fue cancelado o vencio. Puedes generar un nuevo QR.';
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

    public function generateQr(Request $request, ClienteSolicitudFacturacionService $facturacion): JsonResponse
    {
        $servicioExtraIds = TarifarioTiktoker::query()
            ->whereNotNull('servicio_extra_id')
            ->distinct()
            ->pluck('servicio_extra_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'qr_attempt_id' => ['required', 'uuid'],
            'forma_pago' => ['required', 'in:origen'],
            'tipo_documento' => ['required', 'string', 'max:20', \Illuminate\Validation\Rule::in(array_keys(Cliente::tiposDocumentoIdentidad()))],
            'numero_documento' => ['required', 'string', 'max:80'],
            'complemento_documento' => ['nullable', 'string', 'max:30'],
            'razon_social' => ['required', 'string', 'max:255'],
            'correo_facturacion' => ['required', 'email', 'max:50'],
            'servicio_extra_id' => ['required', 'integer', 'in:'.implode(',', $servicioExtraIds)],
            'origen' => ['required', 'string'],
            'destino_id' => ['required', 'integer', 'exists:destino,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'contenido' => ['required', 'string'],
            'nombre_remitente' => ['required', 'string', 'max:255'],
            'carnet' => ['required', 'string', 'max:255'],
            'telefono_remitente' => ['nullable', 'string', 'max:50'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['nullable', 'string', 'max:50'],
            'direccion_recojo' => ['required', 'string', 'max:255'],
            'direccion_entrega' => ['required', 'string', 'max:255'],
        ]);

        $cliente = Auth::guard('cliente')->user();
        $attemptId = strtolower((string) $data['qr_attempt_id']);

        try {
            $carts = $facturacion->listarSolicitudesQr($cliente, 1, 20);
            foreach ((array) data_get($carts, 'data.carts', []) as $cart) {
                if (!is_array($cart) || $this->qrSolicitudId($cart) !== null) {
                    continue;
                }
                $otherAttemptId = $this->qrAttemptIdFromCart($cart);
                $otherStatus = $this->normalizarEstadoPagoQr((string) ($cart['estado_pago'] ?? 'pendiente'));
                if ($otherAttemptId && $otherAttemptId !== $attemptId && in_array($otherStatus, ['pendiente', 'pagado'], true)) {
                    return response()->json([
                        'message' => $otherStatus === 'pagado'
                            ? 'Ya tienes un pago confirmado que se esta registrando. Consulta su estado en Mis solicitudes.'
                            : 'Ya tienes un pago QR pendiente. Completa o cancela ese pago antes de generar otro.',
                    ], 409);
                }
            }

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
            $amount = round((float) $tarifario->peso1, 2);
            if ($amount <= 0) {
                return response()->json(['message' => 'La tarifa calculada no permite generar un QR.'], 422);
            }

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
                'message' => 'No se pudo generar el QR en este momento. Intenta nuevamente.',
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

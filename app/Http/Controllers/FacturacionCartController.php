<?php

namespace App\Http\Controllers;

use App\Services\FacturacionCartService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class FacturacionCartController extends Controller
{
    public function updateBillingData(Request $request, FacturacionCartService $service)
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        $validated = $request->validate([
            'modalidad_facturacion' => ['nullable', 'in:con_datos,sin_cliente'],
            'canal_emision' => ['nullable', 'in:factura_electronica,qr'],
            'tipo_documento' => ['nullable', 'string', 'max:20', Rule::in(array_keys(\App\Models\Cliente::tiposDocumentoIdentidad()))],
            'numero_documento' => ['nullable', 'string', 'max:80'],
            'complemento_documento' => ['nullable', 'string', 'max:30'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'correo_facturacion' => ['nullable', 'email', 'max:50'],
        ]);

        $cart = $service->updateDraftBillingData($user, $validated);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'cart_id' => $cart->id,
            ]);
        }

        return back()->with('success', 'Datos de facturacion actualizados.');
    }

    public function removeItem(Request $request, int $itemId, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        try {
            $service->removeItem($user, $itemId);

            return back()->with('success', 'Item eliminado del borrador de facturacion.');
        } catch (ModelNotFoundException) {
            return back()->with('error', 'No se encontro el item que querias quitar.');
        }
    }

    public function updateItem(Request $request, int $itemId, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:120'],
            'titulo' => ['required', 'string', 'max:255'],
            'nombre_servicio' => ['nullable', 'string', 'max:255'],
            'nombre_destinatario' => ['nullable', 'string', 'max:255'],
            'contenido' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:255'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'actividad_economica' => ['nullable', 'string', 'max:6'],
            'codigo_sin' => ['nullable', 'string', 'max:50'],
            'codigo_producto' => ['nullable', 'string', 'max:50'],
            'descripcion_servicio' => ['nullable', 'string', 'max:255'],
            'unidad_medida' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $service->updateDraftItem($user, $itemId, $validated);

            return back()->with('facturacion_feedback', [
                'type' => 'success',
                'title' => 'Item actualizado',
                'message' => 'El item del borrador fue corregido.',
                'detail' => 'Ya puedes revisar los cambios y reintentar la emision.',
            ]);
        } catch (ModelNotFoundException) {
            return back()->with('facturacion_feedback', [
                'type' => 'error',
                'title' => 'No se pudo actualizar el item',
                'message' => 'No se encontro el item que querias corregir.',
                'detail' => 'Vuelve a abrir el acceso rapido y revisa el borrador.',
            ]);
        }
    }

    public function clear(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        $cart = $service->clearDraftCart($user);

        return back()->with(
            $cart ? 'success' : 'info',
            $cart ? 'Carrito borrador vaciado correctamente.' : 'No habia items en el carrito borrador.'
        );
    }

    public function emitir(Request $request, FacturacionCartService $service): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        $billingSnapshot = $request->validate([
            'modalidad_facturacion' => ['nullable', 'in:con_datos,sin_cliente'],
            'canal_emision' => ['nullable', 'in:factura_electronica,qr'],
            'tipo_documento' => ['nullable', 'string', 'max:20', Rule::in(array_keys(\App\Models\Cliente::tiposDocumentoIdentidad()))],
            'numero_documento' => ['nullable', 'string', 'max:80'],
            'complemento_documento' => ['nullable', 'string', 'max:30'],
            'razon_social' => ['nullable', 'string', 'max:255'],
            'correo_facturacion' => ['nullable', 'email', 'max:50'],
        ]);

        $billingSnapshot = array_filter($billingSnapshot, static fn ($value) => $value !== null);
        if ($billingSnapshot !== []) {
            try {
                $service->updateDraftBillingData($user, $billingSnapshot);
                Log::info('Snapshot de facturacion sincronizado antes de emitir.', [
                    'user_id' => $user?->id,
                    'canal_emision' => $billingSnapshot['canal_emision'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo sincronizar snapshot de facturacion antes de emitir.', [
                    'user_id' => $user?->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        try {
            $ctx = $service->getRemoteContextForUser($user);
            $draft = $ctx['draft'] ?? null;
            Log::info('Inicio de emision de facturacion.', [
                'user_id' => $user?->id,
                'cart_id' => data_get($draft, 'id'),
                'canal_emision' => data_get($draft, 'canal_emision'),
                'items_count' => is_countable(data_get($draft, 'items', [])) ? count(data_get($draft, 'items', [])) : 0,
            ]);
            $draftItems = collect($draft?->items ?? []);
            if ($draftItems->isEmpty()) {
                return back()->with('facturacion_feedback', [
                    'type' => 'warning',
                    'title' => 'Carrito vacío',
                    'message' => 'Agrega al menos un Ítem antes de emitir la factura.',
                    'detail' => 'No se envió ninguna solicitud de emisión porque el borrador no tiene Ã­tems.',
                    'action' => 'emitir',
                ]);
            }
        } catch (\Throwable) {
            // Si falla la consulta previa, continÃºa con el flujo existente.
        }

        try {
            $resultado = $service->emitirBorrador($user, $billingSnapshot);
            $respuesta = (array) ($resultado['respuesta'] ?? []);
            $feedback = $this->buildBridgeFeedback($respuesta, 'emitir');
            $redirect = back()->with('facturacion_feedback', $feedback);

            $qrPayload = $this->extractQrSessionData(
                $respuesta,
                (string) data_get($resultado, 'carrito.codigo_orden', ''),
                strtolower(trim((string) data_get($resultado, 'carrito.canal_emision', ''))) === 'qr'
            );
            if ($qrPayload !== null) {
                Log::info('Facturacion QR detectado en respuesta de emision.', [
                    'user_id' => $user->id,
                    'cart_id' => data_get($resultado, 'carrito.id'),
                    'transaction_id' => $qrPayload['transaction_id'] ?? '',
                    'payment_status' => $qrPayload['payment_status'] ?? '',
                    'has_image_data' => !empty($qrPayload['image_data']),
                ]);

                $redirect->with('facturacion_qr_data', $qrPayload);
            }

            $pdfUrl = trim((string) data_get($respuesta, 'factura.pdfUrl', ''));
            $downloadPdf = null;
            if ($pdfUrl !== '' && strtoupper((string) ($respuesta['estado'] ?? '')) === 'FACTURADA') {
                $downloadPdf = [
                    'url' => $pdfUrl,
                    'key' => (string) ($respuesta['codigoOrden'] ?? $resultado['carrito']->codigo_orden ?? now()->timestamp),
                ];
                $redirect->with('facturacion_download_pdf', $downloadPdf);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'feedback' => $feedback,
                    'qr_data' => $qrPayload,
                    'download_pdf' => $downloadPdf,
                    'cart' => [
                        'id' => data_get($resultado, 'carrito.id'),
                        'estado' => data_get($resultado, 'carrito.estado'),
                        'estado_pago' => data_get($resultado, 'carrito.estado_pago'),
                        'estado_emision' => data_get($resultado, 'carrito.estado_emision'),
                        'codigo_orden' => data_get($resultado, 'carrito.codigo_orden'),
                        'qr_transaction_id' => data_get($resultado, 'carrito.qr_transaction_id'),
                        'canal_emision' => data_get($resultado, 'carrito.canal_emision'),
                    ],
                ]);
            }

            return $redirect;
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $this->buildRuntimeFeedback($e->getMessage(), 'emitir'),
                ], 422);
            }

            return back()->with('facturacion_feedback', $this->buildRuntimeFeedback($e->getMessage(), 'emitir'));
        }
    }

    public function consultar(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        try {
            $codigoSeguimiento = trim((string) $request->input('codigo_seguimiento', ''));
            $cartId = $request->integer('cart_id') ?: null;
            if ($cartId !== null) {
                $resultado = $service->consultarEstadoEmision($user, $cartId);
                $respuesta = (array) ($resultado['respuesta'] ?? []);
            } elseif ($codigoSeguimiento !== '') {
                $respuesta = (array) $service->consultarVentaSeguimiento($user, $codigoSeguimiento);
                $resultado = ['carrito' => null, 'respuesta' => $respuesta];
            } else {
                $resultado = $service->consultarEstadoEmision($user, null);
                $respuesta = (array) ($resultado['respuesta'] ?? []);
            }

            $redirect = back()->with('facturacion_feedback', $this->buildBridgeFeedback($respuesta, 'consultar'));

            $qrPayload = $this->extractQrSessionData(
                $respuesta,
                (string) data_get($resultado, 'carrito.codigo_orden', $codigoSeguimiento),
                strtolower(trim((string) data_get($resultado, 'carrito.canal_emision', ''))) === 'qr'
            );
            if ($this->shouldShowQrSessionData($qrPayload)) {
                Log::info('Facturacion QR detectado en consulta de estado.', [
                    'user_id' => $user->id,
                    'cart_id' => data_get($resultado, 'carrito.id'),
                    'codigo_seguimiento' => $codigoSeguimiento,
                    'transaction_id' => $qrPayload['transaction_id'] ?? '',
                    'payment_status' => $qrPayload['payment_status'] ?? '',
                    'has_image_data' => !empty($qrPayload['image_data']),
                ]);

                $redirect->with('facturacion_qr_data', $qrPayload);
            }

            $pdfUrl = trim((string) data_get($respuesta, 'factura.pdfUrl', ''));
            if ($pdfUrl !== '' && strtoupper((string) ($respuesta['estado'] ?? '')) === 'FACTURADA') {
                $redirect->with('facturacion_download_pdf', [
                    'url' => $pdfUrl,
                    'key' => (string) ($respuesta['codigoOrden'] ?? data_get($resultado, 'carrito.codigo_orden', now()->timestamp)),
                ]);
            }

            return $redirect;
        } catch (\RuntimeException $e) {
            return back()->with('facturacion_feedback', $this->buildRuntimeFeedback($e->getMessage(), 'consultar'));
        }
    }

    public function abrirCaja(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        try {
            $resultado = $service->abrirCaja($user);

            return back()->with('facturacion_feedback', [
                'type' => 'success',
                'title' => 'Caja abierta',
                'message' => $resultado['mensaje'] ?: 'Ya puedes emitir facturas desde el acceso rapido.',
                'detail' => 'Estado actual: ' . ($resultado['estado'] ?: 'ABIERTA'),
                'action' => 'caja_abrir',
            ]);
        } catch (\RuntimeException $e) {
            return back()->with('facturacion_feedback', [
                'type' => 'error',
                'title' => 'No se pudo abrir caja',
                'message' => 'Revisa la configuracion de sucursal/punto de venta y vuelve a intentar.',
                'detail' => trim($e->getMessage()),
                'action' => 'caja_abrir',
            ]);
        }
    }

    public function cerrarCaja(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        try {
            $resultado = $service->cerrarCaja($user);

            return back()->with('facturacion_feedback', [
                'type' => 'success',
                'title' => 'Caja cerrada',
                'message' => $resultado['mensaje'] ?: 'La caja diaria se cerro correctamente.',
                'detail' => 'Estado actual: ' . ($resultado['estado'] ?: 'CERRADA'),
                'action' => 'caja_cerrar',
            ]);
        } catch (\RuntimeException $e) {
            return back()->with('facturacion_feedback', [
                'type' => 'error',
                'title' => 'No se pudo cerrar caja',
                'message' => 'No fue posible completar el cierre diario.',
                'detail' => trim($e->getMessage()),
                'action' => 'caja_cerrar',
            ]);
        }
    }

    private function buildBridgeFeedback(array $respuesta, string $action): array
    {
        $estado = strtoupper(trim((string) ($respuesta['estado'] ?? '')));
        $estadoPago = strtolower(trim((string) (
            data_get($respuesta, 'estado_pago')
            ?? data_get($respuesta, 'payment_status')
            ?? ''
        )));
        $mensaje = trim((string) ($respuesta['mensaje'] ?? ''));
        $razon = trim((string) ($respuesta['razon'] ?? ''));
        $numeroFactura = trim((string) (
            data_get($respuesta, 'factura.nroFactura')
            ?? data_get($respuesta, 'factura.numeroFactura')
            ?? data_get($respuesta, 'nroFactura')
            ?? data_get($respuesta, 'numeroFactura')
            ?? data_get($respuesta, 'consultaSefe.nroFactura')
            ?? ''
        ));
        $codigoOrden = trim((string) (
            data_get($respuesta, 'codigoOrden')
            ?? data_get($respuesta, 'internal_code')
            ?? data_get($respuesta, 'codigo_orden')
            ?? ''
        ));
        $codigoSeguimiento = trim((string) (
            data_get($respuesta, 'codigoSeguimiento')
            ?? data_get($respuesta, 'transaction_id')
            ?? data_get($respuesta, 'id')
            ?? ''
        ));
        $pdfUrl = trim((string) (
            data_get($respuesta, 'factura.pdfUrl')
            ?? data_get($respuesta, 'urlPdf')
            ?? ''
        ));
        $feedbackMeta = $this->buildFeedbackMeta($estado, $numeroFactura, $codigoOrden, $codigoSeguimiento, $pdfUrl);

        $isQrFlow = $estado === 'NO_APLICA'
            || trim((string) (
                data_get($respuesta, 'transaction_id')
                ?? data_get($respuesta, 'payment_status')
                ?? data_get($respuesta, 'qr_url')
                ?? data_get($respuesta, 'image_data')
                ?? ''
            )) !== '';

        if ($isQrFlow) {
            if (in_array($estadoPago, ['pagado', 'success', 'paid', 'completed'], true)) {
                return [
                    'type' => 'success',
                    'title' => 'Pago QR confirmado',
                    'message' => 'El cobro por QR fue confirmado correctamente.',
                    'detail' => $mensaje !== '' ? $mensaje : 'La operacion QR ya puede considerarse cobrada.',
                    'action' => $action,
                    'meta' => $feedbackMeta,
                ];
            }

            if (in_array($estadoPago, ['cancelado', 'rejected', 'failed', 'expired'], true)) {
                return [
                    'type' => 'warning',
                    'title' => 'Pago QR no concretado',
                    'message' => 'El cobro QR fue cancelado o rechazado.',
                    'detail' => $mensaje !== '' ? $mensaje : 'Puedes generar un nuevo QR si el cliente desea reintentar.',
                    'action' => $action,
                    'meta' => $feedbackMeta,
                ];
            }

            return [
                'type' => 'info',
                'title' => 'QR generado',
                'message' => 'El cobro QR esta pendiente de confirmacion.',
                'detail' => $mensaje !== '' ? $mensaje : 'Espera el callback del proveedor o actualiza el pago en unos segundos.',
                'action' => $action,
                'meta' => $feedbackMeta,
            ];
        }

        if ($estado === 'FACTURADA') {
            return [
                'type' => 'success',
                'title' => 'Factura emitida correctamente',
                'message' => 'La factura ya fue procesada. Puedes entregar el comprobante al cliente.',
                'detail' => $mensaje !== '' ? $mensaje : 'El sistema genero la factura sin observaciones.',
                'action' => $action,
                'meta' => $feedbackMeta,
            ];
        }

        if (in_array($estado, ['PENDIENTE', 'PROCESADA'], true)) {
            return [
                'type' => 'info',
                'title' => 'Emision en proceso',
                'message' => 'La venta fue recibida por facturacion. Consulta el estado en unos segundos.',
                'detail' => $mensaje !== '' ? $mensaje : 'La API confirmo la recepcion y aun esta procesando.',
                'action' => $action,
                'meta' => $feedbackMeta,
            ];
        }

        if ($estado === 'RECHAZADA') {
            return [
                'type' => 'warning',
                'title' => 'La factura fue observada',
                'message' => 'Revisa los datos de la venta antes de reenviarla.',
                'detail' => $razon !== '' ? $razon : ($mensaje !== '' ? $mensaje : 'La API rechazo la venta por validacion.'),
                'action' => $action,
                'meta' => $feedbackMeta,
            ];
        }

        return [
            'type' => 'info',
            'title' => $action === 'consultar' ? 'Estado actualizado' : 'Respuesta de facturacion',
            'message' => $mensaje !== '' ? $mensaje : 'Se recibio respuesta del sistema de facturacion.',
            'detail' => $razon,
            'action' => $action,
            'meta' => $feedbackMeta,
        ];
    }

    private function buildRuntimeFeedback(string $message, string $action): array
    {
        return [
            'type' => 'error',
            'title' => $action === 'consultar' ? 'No se pudo consultar la factura' : 'No se pudo emitir la factura',
            'message' => 'Caja puede corregir los datos y volver a intentar.',
            'detail' => trim($message),
            'action' => $action,
        ];
    }

    private function buildFeedbackMeta(string $estado, string $numeroFactura, string $codigoOrden, string $codigoSeguimiento, string $pdfUrl): array
    {
        $meta = [];

        if ($estado !== '') {
            $meta[] = ['label' => 'Estado', 'value' => $estado];
        }
        if ($numeroFactura !== '') {
            $meta[] = ['label' => 'Factura', 'value' => $numeroFactura];
        }
        if ($codigoOrden !== '') {
            $meta[] = ['label' => 'Orden', 'value' => $codigoOrden];
        }
        if ($codigoSeguimiento !== '') {
            $meta[] = ['label' => 'Seguimiento', 'value' => $codigoSeguimiento];
        }
        if ($pdfUrl !== '') {
            $meta[] = ['label' => 'PDF', 'value' => $pdfUrl, 'type' => 'link'];
        }

        return $meta;
    }

    private function extractQrSessionData(array $respuesta, string $defaultInternalCode = '', bool $forceQr = false): ?array
    {
        $imageKeys = [
            'image_data',
            'qr_url',
            'qrUrl',
            'image_url',
            'imageUrl',
            'payment_image',
            'paymentImage',
            'qr_image',
            'qrImage',
            'embed_image',
            'embedImage',
        ];
        $transactionKeys = [
            'transaction_id',
            'transactionId',
            'payment_id',
            'paymentId',
            'items.0.id',
        ];
        $paymentStatusKeys = [
            'payment_status',
            'paymentStatus',
            'payment_state',
            'paymentState',
            'estado_pago',
            'estadoPago',
            'items.0.payment_status',
            'items.0.payment_status ',
        ];

        $imageData = $this->findFirstResponseValue($respuesta, $imageKeys);
        $hasQrMarkers = $forceQr
            || $this->hasAnyResponseValue($respuesta, $imageKeys)
            || $this->hasAnyResponseValue($respuesta, $transactionKeys)
            || $this->hasAnyResponseValue($respuesta, $paymentStatusKeys);

        if (!$hasQrMarkers) {
            return null;
        }

        $transactionId = $this->findFirstResponseValue($respuesta, $transactionKeys);

        $paymentStatus = strtoupper(trim($this->findFirstResponseValue($respuesta, $paymentStatusKeys)));

        $internalCode = trim($this->findFirstResponseValue($respuesta, [
            'internal_code',
            'internalCode',
            'codigoOrden',
            'codigo_orden',
        ]));

        $message = trim($this->findFirstResponseValue($respuesta, [
            'message',
            'mensaje',
            'detail',
            'descripcion',
        ]));

        $imageData = $this->normalizeQrImageData($imageData);

        if ($imageData === '' && $transactionId === '') {
            return null;
        }

        return [
            'image_data' => $imageData,
            'payment_status' => $paymentStatus !== '' ? $paymentStatus : 'HOLDING',
            'transaction_id' => $transactionId,
            'internal_code' => $internalCode !== '' ? $internalCode : $defaultInternalCode,
            'message' => $message !== '' ? $message : 'QR generado.',
        ];
    }

    private function shouldShowQrSessionData(?array $qrPayload): bool
    {
        if (!is_array($qrPayload) || $qrPayload === []) {
            return false;
        }

        $status = strtolower(trim((string) ($qrPayload['payment_status'] ?? 'holding')));

        return !in_array($status, [
            'pagado',
            'success',
            'paid',
            'completed',
            'approved',
            'confirmed',
            'cancelado',
            'cancelled',
            'rejected',
            'failed',
            'expired',
        ], true);
    }

    private function findFirstResponseValue(array $payload, array $keys): string
    {
        foreach ($keys as $key) {
            $direct = trim((string) data_get($payload, $key, ''));
            if ($direct !== '') {
                return $direct;
            }
        }

        $flattened = Arr::dot($payload);
        foreach ($flattened as $path => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            foreach ($keys as $key) {
                if ($path === $key || str_ends_with($path, '.' . $key)) {
                    $text = trim((string) $value);
                    if ($text !== '') {
                        return $text;
                    }
                }
            }
        }

        return '';
    }

    private function hasAnyResponseValue(array $payload, array $keys): bool
    {
        return $this->findFirstResponseValue($payload, $keys) !== '';
    }

    private function normalizeQrImageData(string $imageData): string
    {
        $imageData = trim($imageData);
        if ($imageData === '') {
            return '';
        }

        if (str_starts_with($imageData, 'data:image')) {
            return $imageData;
        }

        if (!preg_match('/^https?:\/\//i', $imageData)) {
            return $imageData;
        }

        try {
            $response = Http::timeout(20)->get($imageData);
            if (!$response->successful()) {
                Log::warning('No se pudo descargar la imagen QR remota.', [
                    'url' => $imageData,
                    'status' => $response->status(),
                ]);

                return $imageData;
            }

            $contentType = trim((string) ($response->header('Content-Type') ?? 'image/png'));
            $body = $response->body();
            if ($body === '') {
                return $imageData;
            }

            return 'data:' . $contentType . ';base64,' . base64_encode($body);
        } catch (\Throwable $e) {
            Log::warning('Fallo al normalizar imagen QR remota.', [
                'url' => $imageData,
                'message' => $e->getMessage(),
            ]);

            return $imageData;
        }
    }

    private function authorizeFacturacionAccess($user): void
    {
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para gestionar facturacion.');
    }

}

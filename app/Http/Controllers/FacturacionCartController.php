<?php

namespace App\Http\Controllers;

use App\Models\ConceptoFacturacion;
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
                'cart_id' => $cart?->id,
                'draft_missing' => $cart === null,
            ]);
        }

        return back()->with(
            'success',
            $cart
                ? 'Datos de facturacion actualizados.'
                : 'No habia un borrador activo. Agrega un item para iniciar una nueva venta.'
        );
    }

    public function removeItem(Request $request, int $itemId, FacturacionCartService $service): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        try {
            $service->removeItem($user, $itemId);

            $feedback = [
                'type' => 'success',
                'title' => 'Item quitado del carrito',
                'message' => 'El item fue retirado correctamente.',
                'detail' => 'Puedes seguir agregando items o emitir la factura.',
                'action' => 'remove_item',
            ];

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'feedback' => $feedback,
                ]);
            }

            return back()->with('success', 'Item eliminado del borrador de facturacion.');
        } catch (ModelNotFoundException) {
            $feedback = [
                'type' => 'warning',
                'title' => 'No se pudo quitar el item',
                'message' => 'No se encontro el item que querias quitar.',
                'detail' => 'Actualiza el carrito e intenta nuevamente.',
                'action' => 'remove_item',
            ];

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                ], 404);
            }

            return back()->with('error', 'No se encontro el item que querias quitar.');
        }
    }

    public function updateItem(Request $request, int $itemId, FacturacionCartService $service): RedirectResponse|JsonResponse
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
            'precio' => ['required', 'numeric', 'min:0'],
            'actividad_economica' => ['nullable', 'string', 'max:6'],
            'codigo_sin' => ['nullable', 'string', 'max:50'],
            'codigo_producto' => ['nullable', 'string', 'max:50'],
            'descripcion_servicio' => ['nullable', 'string', 'max:255'],
            'unidad_medida' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $payload = $validated;
            $payload['monto_base'] = (float) $payload['precio'];
            $payload['total_linea'] = (float) $payload['precio'];
            $payload['precio'] = (float) $payload['precio'];

            $service->updateDraftItem($user, $itemId, $payload);

            $feedback = [
                'type' => 'success',
                'title' => 'Item actualizado',
                'message' => 'El item del borrador fue corregido.',
                'detail' => 'Ya puedes revisar los cambios y reintentar la emision.',
            ];

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'feedback' => $feedback,
                ]);
            }

            return back()->with('facturacion_feedback', $feedback);
        } catch (ModelNotFoundException) {
            $feedback = [
                'type' => 'error',
                'title' => 'No se pudo actualizar el item',
                'message' => 'No se encontro el item que querias corregir.',
                'detail' => 'Vuelve a abrir el acceso rapido y revisa el borrador.',
            ];

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                ], 404);
            }

            return back()->with('facturacion_feedback', $feedback);
        }
    }

    public function clear(Request $request, FacturacionCartService $service): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        $cart = $service->clearDraftCart($user);

        $feedback = [
            'type' => $cart ? 'success' : 'info',
            'title' => $cart ? 'Carrito vaciado' : 'Carrito ya vacio',
            'message' => $cart ? 'Se eliminaron todos los items del borrador.' : 'No habia items en el carrito borrador.',
            'detail' => $cart
                ? 'Puedes volver a agregar items cuando lo necesites.'
                : 'Agrega un item para iniciar una nueva facturacion.',
            'action' => 'clear_cart',
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'feedback' => $feedback,
            ]);
        }

        return back()->with(
            $cart ? 'success' : 'info',
            $cart ? 'Carrito borrador vaciado correctamente.' : 'No habia items en el carrito borrador.'
        );
    }

    public function scanAdd(Request $request, FacturacionCartService $service): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);
        $expectsJson = $request->expectsJson() || $request->wantsJson() || $request->ajax();

        try {
            $validated = $request->validate([
                'scan_code' => ['required', 'string', 'max:120'],
            ]);

            $resultado = $service->addScannedItemByCode($user, (string) $validated['scan_code']);
            $item = (array) ($resultado['item'] ?? []);
            $cart = $resultado['cart'] ?? null;

            $feedback = [
                'type' => 'success',
                'title' => 'Item agregado al carrito',
                'message' => trim((string) ($item['label'] ?? 'Paquete')) . ' agregado correctamente.',
                'detail' => 'Codigo detectado: ' . trim((string) ($item['code'] ?? 'SIN CODIGO')) . '. Ya puedes seguir escaneando o emitir la factura.',
                'action' => 'scan_add',
            ];

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'feedback' => $feedback,
                    'item' => $item,
                    'cart' => $this->buildCartPayload($cart),
                ]);
            }

            return back()
                ->with('facturacion_feedback', $feedback)
                ->with('facturacion_scanner_open', true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $feedback = [
                'type' => 'warning',
                'title' => 'Codigo invalido',
                'message' => 'No se recibio un codigo valido para agregar al carrito.',
                'detail' => $e->validator->errors()->first('scan_code') ?: 'La solicitud no contiene un codigo escaneado valido.',
                'action' => 'scan_add',
            ];

            if ($expectsJson) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        } catch (\RuntimeException $e) {
            Log::warning('No se pudo agregar codigo al carrito de facturacion.', [
                'user_id' => $user?->id,
                'scan_code' => (string) $validated['scan_code'],
                'expects_json' => $expectsJson,
                'error' => $e->getMessage(),
            ]);

            $feedback = [
                'type' => 'warning',
                'title' => 'No se pudo agregar el codigo',
                'message' => 'El escaneo no pudo agregarse al carrito de Facturacion.',
                'detail' => trim($e->getMessage()),
                'action' => 'scan_add',
            ];

            if ($expectsJson) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                ], 422);
            }

            return back()
                ->withInput()
                ->with('facturacion_feedback', $feedback)
                ->with('facturacion_scanner_open', true);
        } catch (\Throwable $e) {
            Log::error('Error inesperado al agregar codigo al carrito de facturacion.', [
                'user_id' => $user?->id,
                'scan_code' => (string) $validated['scan_code'],
                'expects_json' => $expectsJson,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            $feedback = [
                'type' => 'error',
                'title' => 'No se pudo agregar el codigo',
                'message' => 'Ocurrio un error inesperado al agregar el paquete al carrito de Facturacion.',
                'detail' => 'Revisa storage/logs/laravel.log para ver el motivo exacto del rechazo.',
                'action' => 'scan_add',
            ];

            if ($expectsJson) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                ], 500);
            }

            return back()
                ->withInput()
                ->with('facturacion_feedback', $feedback)
                ->with('facturacion_scanner_open', true);
        }
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
        $autoEmitInvoice = $request->has('auto_emit_invoice')
            ? $request->boolean('auto_emit_invoice')
            : strtolower((string) ($billingSnapshot['canal_emision'] ?? 'factura_electronica')) !== 'qr';

        $billingSnapshot = array_filter($billingSnapshot, static fn ($value) => $value !== null);
        if ($billingSnapshot !== []) {
            try {
                $service->updateDraftBillingData($user, $billingSnapshot);
                Log::debug('Snapshot de facturacion sincronizado antes de emitir.', [
                    'user_id' => $user?->id,
                    'canal_emision' => $billingSnapshot['canal_emision'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo sincronizar snapshot de facturacion antes de emitir.', [
                    'user_id' => $user?->id,
                    'message' => $e->getMessage(),
                ]);

                $feedback = $this->buildRuntimeFeedback(
                    'No se pudo sincronizar los datos fiscales antes de emitir. Corrige el formulario y vuelve a intentar.',
                    'emitir'
                );

                if ($request->expectsJson()) {
                    return response()->json([
                        'ok' => false,
                        'feedback' => $feedback,
                    ], 422);
                }

                return back()->with('facturacion_feedback', $feedback);
            }
        }

        try {
            $ctx = $service->getRemoteContextForUser($user);
            $draft = $ctx['draft'] ?? null;
            Log::debug('Inicio de emision de facturacion.', [
                'user_id' => $user?->id,
                'cart_id' => data_get($draft, 'id'),
                'canal_emision' => data_get($draft, 'canal_emision'),
                'items_count' => is_countable(data_get($draft, 'items', [])) ? count(data_get($draft, 'items', [])) : 0,
            ]);
            $draftItems = collect($draft?->items ?? []);
            if ($draftItems->isEmpty()) {
                $feedback = [
                    'type' => 'warning',
                    'title' => 'Carrito vacío',
                    'message' => 'Agrega al menos un ítem antes de emitir la factura.',
                    'detail' => 'No se envió ninguna solicitud de emisión porque el borrador no tiene ítems.',
                    'action' => 'emitir',
                ];

                if ($request->expectsJson()) {
                    return response()->json([
                        'ok' => false,
                        'feedback' => $feedback,
                    ], 422);
                }

                return back()->with('facturacion_feedback', $feedback);
            }
        } catch (\Throwable) {
            // Si falla la consulta previa, continúa con el flujo existente.
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
                Log::debug('Facturacion QR detectado en respuesta de emision.', [
                    'user_id' => $user->id,
                    'cart_id' => data_get($resultado, 'carrito.id'),
                    'transaction_id' => $qrPayload['transaction_id'] ?? '',
                    'payment_status' => $qrPayload['payment_status'] ?? '',
                    'has_image_data' => !empty($qrPayload['image_data']),
                ]);

                $redirect
                    ->with('facturacion_qr_data', $qrPayload)
                    ->with('facturacion_qr_auto_emit_invoice', $autoEmitInvoice ? '1' : '0');
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
                    'auto_emit_invoice' => $autoEmitInvoice ? '1' : '0',
                    'cart' => [
                        'id' => data_get($resultado, 'carrito.id'),
                        'estado' => data_get($resultado, 'carrito.estado'),
                        'estado_pago' => data_get($resultado, 'carrito.estado_pago'),
                        'estado_emision' => data_get($resultado, 'carrito.estado_emision'),
                        'codigo_orden' => data_get($resultado, 'carrito.codigo_orden'),
                        'qr_transaction_id' => data_get($resultado, 'carrito.qr_transaction_id'),
                        'canal_emision' => data_get($resultado, 'carrito.canal_emision'),
                        'auto_emit_invoice' => $autoEmitInvoice ? '1' : '0',
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

    public function consultar(Request $request, FacturacionCartService $service): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        try {
            $codigoSeguimiento = trim((string) $request->input('codigo_seguimiento', ''));
            $cartId = $request->integer('cart_id') ?: null;
            $autoEmitInvoice = $request->boolean('auto_emit_invoice');
            $allowPendingRetry = !$request->expectsJson();
            if ($cartId !== null) {
                $resultado = $service->consultarEstadoEmision($user, $cartId, $autoEmitInvoice, $allowPendingRetry);
                $respuesta = (array) ($resultado['respuesta'] ?? []);
            } elseif ($codigoSeguimiento !== '') {
                $respuesta = (array) $service->consultarVentaSeguimiento($user, $codigoSeguimiento);
                $resultado = ['carrito' => null, 'respuesta' => $respuesta];
            } else {
                $resultado = $service->consultarEstadoEmision($user, null, $autoEmitInvoice, $allowPendingRetry);
                $respuesta = (array) ($resultado['respuesta'] ?? []);
            }

            $feedback = $this->buildBridgeFeedback($respuesta, 'consultar');
            $redirect = back()->with('facturacion_feedback', $feedback);

            $qrPayload = $this->extractQrSessionData(
                $respuesta,
                (string) data_get($resultado, 'carrito.codigo_orden', $codigoSeguimiento),
                strtolower(trim((string) data_get($resultado, 'carrito.canal_emision', ''))) === 'qr'
            );
            if ($this->shouldShowQrSessionData($qrPayload)) {
                Log::debug('Facturacion QR detectado en consulta de estado.', [
                    'user_id' => $user->id,
                    'cart_id' => data_get($resultado, 'carrito.id'),
                    'codigo_seguimiento' => $codigoSeguimiento,
                    'transaction_id' => $qrPayload['transaction_id'] ?? '',
                    'payment_status' => $qrPayload['payment_status'] ?? '',
                    'has_image_data' => !empty($qrPayload['image_data']),
                ]);

                $redirect
                    ->with('facturacion_qr_data', $qrPayload)
                    ->with('facturacion_qr_auto_emit_invoice', $autoEmitInvoice ? '1' : '0');
            }

            $pdfUrl = trim((string) data_get($respuesta, 'factura.pdfUrl', ''));
            $downloadPdf = null;
            if ($pdfUrl !== '' && strtoupper((string) ($respuesta['estado'] ?? '')) === 'FACTURADA') {
                $downloadPdf = [
                    'url' => $pdfUrl,
                    'key' => (string) ($respuesta['codigoOrden'] ?? data_get($resultado, 'carrito.codigo_orden', now()->timestamp)),
                ];
                $redirect->with('facturacion_download_pdf', $downloadPdf);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'feedback' => $feedback,
                    'qr_data' => $qrPayload,
                    'download_pdf' => $downloadPdf,
                    'auto_emit_invoice' => $autoEmitInvoice ? '1' : '0',
                    'cart' => [
                        'id' => data_get($resultado, 'carrito.id'),
                        'estado' => data_get($resultado, 'carrito.estado'),
                        'estado_pago' => data_get($resultado, 'carrito.estado_pago'),
                        'estado_emision' => data_get($resultado, 'carrito.estado_emision'),
                        'codigo_orden' => data_get($resultado, 'carrito.codigo_orden'),
                        'qr_transaction_id' => data_get($resultado, 'carrito.qr_transaction_id'),
                        'canal_emision' => data_get($resultado, 'carrito.canal_emision'),
                        'auto_emit_invoice' => $autoEmitInvoice ? '1' : '0',
                    ],
                ]);
            }

            return $redirect;
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $this->buildRuntimeFeedback($e->getMessage(), 'consultar'),
                ], 422);
            }

            return back()->with('facturacion_feedback', $this->buildRuntimeFeedback($e->getMessage(), 'consultar'));
        }
    }

    public function abrirCaja(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);
        Log::info('FacturacionCartController abrirCaja start', [
            'user_id' => $user->id,
            'sucursal_id' => $user->sucursal_id,
        ]);

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
            Log::warning('FacturacionCartController abrirCaja failed', [
                'user_id' => $user->id,
                'sucursal_id' => $user->sucursal_id,
                'error' => $e->getMessage(),
            ]);
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
        $validated = $request->validate([
            'monto_cierre_declarado' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            try {
                $service->clearDraftCart($user);
            } catch (\Throwable $draftError) {
                Log::info('No se pudo limpiar el borrador antes del cierre de caja.', [
                    'user_id' => $user->id,
                    'error' => $draftError->getMessage(),
                ]);
            }

            $montoCierreDeclarado = array_key_exists('monto_cierre_declarado', $validated)
                && $validated['monto_cierre_declarado'] !== null
                ? (float) $validated['monto_cierre_declarado']
                : 0.0;

            $resultado = $service->cerrarCaja($user, $montoCierreDeclarado);

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
        $autoFacturaError = trim((string) data_get($respuesta, 'auto_factura_error', ''));
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
            if (in_array($estadoPago, ['pagado', 'success', 'paid', 'completed', 'approved', 'confirmed'], true)) {
                if ((bool) data_get($respuesta, 'auto_factura_pending', false)) {
                    return [
                        'type' => 'info',
                        'title' => 'Pago QR confirmado',
                        'message' => 'El pago fue confirmado y la factura se esta procesando.',
                        'detail' => $mensaje !== '' ? $mensaje : 'Evita reenviar la venta; consulta nuevamente en unos segundos.',
                        'action' => $action,
                        'meta' => $feedbackMeta,
                    ];
                }

                if ($autoFacturaError !== '') {
                    return [
                        'type' => 'warning',
                        'title' => 'Pago QR confirmado',
                        'message' => 'El cobro por QR fue confirmado, pero la factura no se emitio automaticamente.',
                        'detail' => $autoFacturaError,
                        'action' => $action,
                        'meta' => $feedbackMeta,
                    ];
                }

                return [
                    'type' => 'success',
                    'title' => 'Pago QR confirmado',
                    'message' => 'El cobro por QR fue confirmado correctamente.',
                    'detail' => $mensaje !== '' ? $mensaje : 'La operacion QR ya puede considerarse cobrada.',
                    'action' => $action,
                    'meta' => $feedbackMeta,
                ];
            }

            if (in_array($estadoPago, ['cancelado', 'cancelled', 'rejected', 'failed', 'expired'], true)) {
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
            $message = $pdfUrl !== ''
                ? 'La factura fue emitida y el PDF se descargo automaticamente.'
                : 'La factura ya fue procesada. Puedes entregar el comprobante al cliente.';
            $detail = $mensaje !== ''
                ? $mensaje
                : ($pdfUrl !== ''
                    ? 'Verifica la descarga del comprobante y continua con la siguiente venta cuando estes listo.'
                    : 'El sistema genero la factura sin observaciones.');

            return [
                'type' => 'success',
                'title' => $pdfUrl !== '' ? 'Factura emitida y descargada' : 'Factura emitida correctamente',
                'message' => $message,
                'detail' => $detail,
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

    private function buildCartPayload(?object $cart): ?array
    {
        if (!$cart) {
            return null;
        }

        $rawItems = $cart->items ?? [];
        $itemsCollection = $rawItems instanceof \Illuminate\Support\Collection
            ? $rawItems
            : collect($rawItems);

        $items = $itemsCollection
            ->map(function ($item) {
                return [
                    'id' => data_get($item, 'id'),
                    'codigo' => (string) data_get($item, 'codigo', ''),
                    'titulo' => (string) data_get($item, 'titulo', ''),
                    'nombre_servicio' => (string) data_get($item, 'nombre_servicio', ''),
                    'nombre_destinatario' => (string) data_get($item, 'nombre_destinatario', ''),
                    'resumen_origen' => (array) data_get($item, 'resumen_origen', []),
                    'cantidad' => max(1, (int) data_get($item, 'cantidad', 1)),
                    'monto_base' => (float) data_get($item, 'monto_base', data_get($item, 'precio', data_get($item, 'total_linea', 0))),
                    'monto_extras' => (float) data_get($item, 'monto_extras', 0),
                    'total_linea' => (float) data_get($item, 'total_linea', 0),
                    'servicios_extra' => array_values((array) data_get($item, 'servicios_extra', [])),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => data_get($cart, 'id'),
            'estado' => (string) data_get($cart, 'estado', ''),
            'estado_pago' => (string) data_get($cart, 'estado_pago', ''),
            'estado_emision' => (string) data_get($cart, 'estado_emision', ''),
            'codigo_orden' => (string) data_get($cart, 'codigo_orden', ''),
            'canal_emision' => (string) data_get($cart, 'canal_emision', ''),
            'cantidad_items' => (int) collect($items)->sum(fn ($item) => max(1, (int) ($item['cantidad'] ?? 1))),
            'total' => (float) data_get($cart, 'total', 0),
            'items' => $items,
        ];
    }

    public function addConcepto(Request $request, FacturacionCartService $service): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);
        $expectsJson = $request->expectsJson() || $request->wantsJson() || $request->ajax();

        try {
            $validated = $request->validate([
                'concepto_facturacion_id' => ['required', 'integer', 'exists:conceptos_facturacion,id'],
            ]);

            $concepto = ConceptoFacturacion::query()
                ->where('activo', true)
                ->findOrFail((int) $validated['concepto_facturacion_id']);

            $cart = $service->addConceptoFacturacion($user, $concepto);
            $montoBase = round((float) ($concepto->precio_base ?? 0), 2);

            $feedback = [
                'type' => 'success',
                'title' => 'Cobro agregado al carrito',
                'message' => trim((string) $concepto->nombre) . ' agregado correctamente.',
                'detail' => 'Precio base aplicado: Bs ' . number_format($montoBase, 2) . '. Si hace falta, puedes editarlo dentro del carrito.',
                'action' => 'concepto_add',
            ];

            Log::debug('Concepto facturable agregado al carrito.', [
                'user_id' => $user?->id,
                'concepto_id' => $concepto->id,
                'cart_id' => $cart->id ?? null,
                'expects_json' => $expectsJson,
            ]);

            if ($expectsJson) {
                return response()->json([
                    'ok' => true,
                    'feedback' => $feedback,
                    'cart' => $this->buildCartPayload($cart),
                ]);
            }

            return back()->with('facturacion_feedback', $feedback);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $feedback = [
                'type' => 'warning',
                'title' => 'Concepto invalido',
                'message' => 'Debes seleccionar un concepto facturable valido.',
                'detail' => $e->validator->errors()->first('concepto_facturacion_id') ?: 'La solicitud no contiene un concepto valido.',
                'action' => 'concepto_add',
            ];

            Log::warning('Validacion fallida al agregar concepto facturable.', [
                'user_id' => $user?->id,
                'payload' => $request->all(),
                'expects_json' => $expectsJson,
                'errors' => $e->errors(),
            ]);

            if ($expectsJson) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        } catch (ModelNotFoundException $e) {
            $feedback = [
                'type' => 'warning',
                'title' => 'Concepto no disponible',
                'message' => 'El concepto facturable ya no esta disponible.',
                'detail' => 'Actualiza la pantalla y vuelve a intentarlo.',
                'action' => 'concepto_add',
            ];

            Log::warning('Concepto facturable no encontrado o inactivo al agregar.', [
                'user_id' => $user?->id,
                'payload' => $request->all(),
                'expects_json' => $expectsJson,
            ]);

            if ($expectsJson) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                ], 404);
            }

            return back()->withInput()->with('facturacion_feedback', $feedback);
        } catch (\RuntimeException $e) {
            $feedback = [
                'type' => 'warning',
                'title' => 'No se pudo agregar el cobro',
                'message' => 'El concepto no pudo agregarse al carrito de Facturacion.',
                'detail' => trim($e->getMessage()),
                'action' => 'concepto_add',
            ];

            Log::warning('Fallo de negocio al agregar concepto facturable.', [
                'user_id' => $user?->id,
                'payload' => $request->all(),
                'expects_json' => $expectsJson,
                'message' => $e->getMessage(),
            ]);

            if ($expectsJson) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                ], 422);
            }

            return back()->withInput()->with('facturacion_feedback', $feedback);
        } catch (\Throwable $e) {
            $feedback = [
                'type' => 'error',
                'title' => 'No se pudo agregar el cobro',
                'message' => 'La solicitud fallo antes de completar el agregado al carrito.',
                'detail' => 'Revisa el log del servidor para mas detalle tecnico.',
                'action' => 'concepto_add',
            ];

            Log::error('Error inesperado al agregar concepto facturable.', [
                'user_id' => $user?->id,
                'payload' => $request->all(),
                'expects_json' => $expectsJson,
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            if ($expectsJson) {
                return response()->json([
                    'ok' => false,
                    'feedback' => $feedback,
                ], 500);
            }

            throw $e;
        }
    }

    private function authorizeFacturacionAccess($user): void
    {
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para gestionar facturacion.');
    }

}

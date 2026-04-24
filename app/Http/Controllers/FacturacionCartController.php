<?php

namespace App\Http\Controllers;

use App\Services\FacturacionCartService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacturacionCartController extends Controller
{
    public function updateBillingData(Request $request, FacturacionCartService $service)
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        $validated = $request->validate([
            'modalidad_facturacion' => ['nullable', 'in:con_datos,sin_cliente'],
            'canal_emision' => ['nullable', 'in:qr,factura_electronica'],
            'tipo_documento' => ['nullable', 'string', 'max:20', Rule::in(array_keys(\App\Models\Cliente::tiposDocumentoIdentidad()))],
            'numero_documento' => ['nullable', 'string', 'max:80'],
            'complemento_documento' => ['nullable', 'string', 'max:30'],
            'razon_social' => ['nullable', 'string', 'max:255'],
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

    public function emitir(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        try {
            $ctx = $service->getRemoteContextForUser($user);
            $draft = $ctx['draft'] ?? null;
            $draftItems = collect($draft?->items ?? []);
            if ($draftItems->isEmpty()) {
                return back()->with('facturacion_feedback', [
                    'type' => 'warning',
                    'title' => 'Carrito vacío',
                    'message' => 'Agrega al menos un ítem antes de emitir la factura.',
                    'detail' => 'No se envió ninguna solicitud de emisión porque el borrador no tiene ítems.',
                    'action' => 'emitir',
                ]);
            }
        } catch (\Throwable) {
            // Si falla la consulta previa, continúa con el flujo existente.
        }

        try {
            $resultado = $service->emitirBorrador($user);
            $respuesta = (array) ($resultado['respuesta'] ?? []);
            $redirect = back()->with('facturacion_feedback', $this->buildBridgeFeedback($respuesta, 'emitir'));

            $pdfUrl = trim((string) data_get($respuesta, 'factura.pdfUrl', ''));
            if ($pdfUrl !== '' && strtoupper((string) ($respuesta['estado'] ?? '')) === 'FACTURADA') {
                $redirect->with('facturacion_download_pdf', [
                    'url' => $pdfUrl,
                    'key' => (string) ($respuesta['codigoOrden'] ?? $resultado['carrito']->codigo_orden ?? now()->timestamp),
                ]);
            }

            return $redirect;
        } catch (\RuntimeException $e) {
            return back()->with('facturacion_feedback', $this->buildRuntimeFeedback($e->getMessage(), 'emitir'));
        }
    }

    public function consultar(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        try {
            $codigoSeguimiento = trim((string) $request->input('codigo_seguimiento', ''));

            if ($codigoSeguimiento !== '') {
                $respuesta = (array) $service->consultarVentaSeguimiento($user, $codigoSeguimiento);
                $resultado = ['carrito' => null, 'respuesta' => $respuesta];
            } else {
                $resultado = $service->consultarEstadoEmision($user, $request->integer('cart_id') ?: null);
                $respuesta = (array) ($resultado['respuesta'] ?? []);
            }

            $redirect = back()->with('facturacion_feedback', $this->buildBridgeFeedback($respuesta, 'consultar'));

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
        $mensaje = trim((string) ($respuesta['mensaje'] ?? ''));
        $razon = trim((string) ($respuesta['razon'] ?? ''));

        if ($estado === 'FACTURADA') {
            return [
                'type' => 'success',
                'title' => 'Factura emitida correctamente',
                'message' => 'La factura ya fue procesada. Puedes entregar el comprobante al cliente.',
                'detail' => $mensaje !== '' ? $mensaje : 'El sistema genero la factura sin observaciones.',
                'action' => $action,
            ];
        }

        if (in_array($estado, ['PENDIENTE', 'PROCESADA'], true)) {
            return [
                'type' => 'info',
                'title' => 'Emision en proceso',
                'message' => 'La venta fue recibida por facturacion. Consulta el estado en unos segundos.',
                'detail' => $mensaje !== '' ? $mensaje : 'La API confirmo la recepcion y aun esta procesando.',
                'action' => $action,
            ];
        }

        if ($estado === 'RECHAZADA') {
            return [
                'type' => 'warning',
                'title' => 'La factura fue observada',
                'message' => 'Revisa los datos de la venta antes de reenviarla.',
                'detail' => $razon !== '' ? $razon : ($mensaje !== '' ? $mensaje : 'La API rechazo la venta por validacion.'),
                'action' => $action,
            ];
        }

        return [
            'type' => 'info',
            'title' => $action === 'consultar' ? 'Estado actualizado' : 'Respuesta de facturacion',
            'message' => $mensaje !== '' ? $mensaje : 'Se recibio respuesta del sistema de facturacion.',
            'detail' => $razon,
            'action' => $action,
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

    private function authorizeFacturacionAccess($user): void
    {
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para gestionar facturacion.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\FacturacionCartService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FacturacionCartController extends Controller
{
    public function updateBillingData(Request $request, FacturacionCartService $service)
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para gestionar facturacion.');

        $validated = $request->validate([
            'modalidad_facturacion' => ['nullable', 'in:con_datos,sin_cliente'],
            'canal_emision' => ['nullable', 'in:qr,factura_electronica'],
            'tipo_documento' => ['nullable', 'string', 'max:20'],
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
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para gestionar facturacion.');

        try {
            $service->removeItem($user, $itemId);

            return back()->with('success', 'Item eliminado del borrador de facturacion.');
        } catch (ModelNotFoundException) {
            return back()->with('error', 'No se encontro el item que querias quitar.');
        }
    }

    public function clear(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para gestionar facturacion.');

        $cart = $service->clearDraftCart($user);

        return back()->with(
            $cart ? 'success' : 'info',
            $cart ? 'Carrito borrador vaciado correctamente.' : 'No habia items en el carrito borrador.'
        );
    }

    public function emitir(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para gestionar facturacion.');

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
        abort_unless($user && $user->can('feature.dashboard.facturacion'), 403, 'No tienes permiso para gestionar facturacion.');

        try {
            $resultado = $service->consultarEstadoEmision($user, $request->integer('cart_id') ?: null);
            $respuesta = (array) ($resultado['respuesta'] ?? []);
            $redirect = back()->with('facturacion_feedback', $this->buildBridgeFeedback($respuesta, 'consultar'));

            $pdfUrl = trim((string) data_get($respuesta, 'factura.pdfUrl', ''));
            if ($pdfUrl !== '' && strtoupper((string) ($respuesta['estado'] ?? '')) === 'FACTURADA') {
                $redirect->with('facturacion_download_pdf', [
                    'url' => $pdfUrl,
                    'key' => (string) ($respuesta['codigoOrden'] ?? $resultado['carrito']->codigo_orden ?? now()->timestamp),
                ]);
            }

            return $redirect;
        } catch (\RuntimeException $e) {
            return back()->with('facturacion_feedback', $this->buildRuntimeFeedback($e->getMessage(), 'consultar'));
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
}

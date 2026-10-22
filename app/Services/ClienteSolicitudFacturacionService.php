<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Destino;
use App\Models\ServicioExtra;
use App\Models\Sucursal;
use App\Models\SolicitudCliente;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ClienteSolicitudFacturacionService
{
    private const ONLINE_CUSTOMER_NAME = 'CLIENTE ONLINE';

    public function generarQr(
        Cliente $cliente,
        array $solicitud,
        ServicioExtra $servicio,
        Destino $destino,
        float $amount
    ): array {
        $remoteUserId = 'cliente_online_'.$cliente->id;
        $sucursal = $this->resolveSucursal((string) $solicitud['origen']);
        $attemptId = Str::lower((string) $solicitud['qr_attempt_id']);
        $code = 'SOLQR-'.Str::upper(str_replace('-', '', $attemptId));
        $originId = $this->qrOriginId($attemptId);

        $existingCart = $this->buscarSolicitudQr($cliente, $attemptId)['cart'] ?? null;
        if (is_array($existingCart)) {
            return $this->qrPayloadFromCart($existingCart, $attemptId);
        }

        $originCity = $this->upper((string) $solicitud['origen']);
        $destinationCity = $this->upper((string) $destino->nombre_destino);
        $serviceName = trim((string) ($servicio->descripcion ?: $servicio->nombre ?: 'Delivery Express'));
        $normalizedService = Str::of((string) $servicio->nombre.' '.(string) $servicio->descripcion)
            ->ascii()->upper()->squish()->toString();
        $productCode = str_contains($normalizedService, 'VENTANILLA A VENTANILLA') ? 'SRVE-02' : 'SRVE-01';
        $fiscalDescription = $this->fiscalDescription($servicio);
        $title = Str::limit('Delivery Express: '.$originCity.' a '.$destinationCity, 255, '');
        $identity = [
            'origen_usuario_id' => $remoteUserId,
            'origen_usuario_nombre' => self::ONLINE_CUSTOMER_NAME,
            'origen_usuario_email' => $this->onlineCustomerEmail(),
            'origen_usuario_alias' => self::ONLINE_CUSTOMER_NAME,
            'origen_sucursal_id' => (string) $sucursal->puntoVenta,
            'origen_sucursal_codigo' => (string) $sucursal->codigoSucursal,
            'origen_sucursal_nombre' => Str::limit($originCity, 255, ''),
        ];

        $itemResponse = $this->request('POST', '/cart/items/upsert', array_merge($identity, [
            'origen_tipo' => 'cliente_online',
            'origen_id' => $originId,
            'codigo' => $code,
            'titulo' => $title,
            'nombre_servicio' => Str::limit($serviceName, 255, ''),
            'nombre_destinatario' => Str::limit(trim((string) $solicitud['nombre_destinatario']), 255, ''),
            'servicios_extra' => [],
            'resumen_origen' => [
                'tipo' => 'cliente_online',
                'qr_attempt_id' => $attemptId,
                'codigo' => $code,
                'codigo_paquete' => $code,
                'codigo_detalle_enviado' => $code,
                'servicio' => $serviceName,
                'actividad_economica' => '841001',
                'codigo_sin' => '99100',
                'codigo_producto' => $productCode,
                'codigo_producto_fiscal' => $productCode,
                'descripcion_servicio' => $fiscalDescription,
                'unidad_medida' => 58,
                'servicio_extra_id' => (int) $servicio->id,
                'destino_id' => (int) $destino->id,
                'origen' => $originCity,
                'destino' => $destinationCity,
                'cantidad_paquetes' => (int) $solicitud['cantidad'],
                'contenido' => trim((string) $solicitud['contenido']),
                'remitente' => trim((string) $solicitud['nombre_remitente']),
                'carnet_remitente' => trim((string) $solicitud['carnet']),
                'telefono_remitente' => trim((string) ($solicitud['telefono_remitente'] ?? '')),
                'direccion_recojo' => trim((string) $solicitud['direccion_recojo']),
                'destinatario' => trim((string) $solicitud['nombre_destinatario']),
                'telefono_destinatario' => trim((string) ($solicitud['telefono_destinatario'] ?? '')),
                'direccion_entrega' => trim((string) $solicitud['direccion_entrega']),
            ],
            // La cotización del formulario ya es el total del servicio solicitado.
            'cantidad' => 1,
            'monto_base' => round($amount, 2),
            'monto_extras' => 0,
            'total_linea' => round($amount, 2),
        ]));

        $cartId = (int) data_get($itemResponse, 'cart.id', 0);
        if ($cartId <= 0) {
            throw new RuntimeException('Facturación no devolvió el identificador del carrito.');
        }

        $this->request('PUT', '/cart/billing', array_merge($identity, [
            'cart_id' => $cartId,
            'modalidad_facturacion' => 'con_datos',
            'canal_emision' => 'qr',
            'tipo_documento' => trim((string) $solicitud['tipo_documento']),
            'numero_documento' => trim((string) $solicitud['numero_documento']),
            'complemento_documento' => trim((string) ($solicitud['complemento_documento'] ?? '')),
            'razon_social' => $this->upper((string) $solicitud['razon_social']),
            'correo_facturacion' => strtolower(trim((string) $solicitud['correo_facturacion'])),
        ]));

        $emissionResponse = $this->request('POST', '/cart/emitir', [
            'origen_usuario_id' => $remoteUserId,
            'cart_id' => $cartId,
            'modalidad_facturacion' => 'con_datos',
            'canal_emision' => 'qr',
        ]);

        $qrData = (array) (data_get($emissionResponse, 'respuesta.respuesta')
            ?? data_get($emissionResponse, 'respuesta')
            ?? $emissionResponse);
        $qrImage = trim((string) ($qrData['image_data']
            ?? data_get($qrData, 'qr_data.image_data')
            ?? $qrData['qr_url']
            ?? data_get($qrData, 'qr_data.qr_url')
            ?? $qrData['image_url']
            ?? data_get($qrData, 'items.0.image_data')
            ?? data_get($qrData, 'items.0.qr_url')
            ?? data_get($emissionResponse, 'image_data')
            ?? data_get($emissionResponse, 'qr_url')
            ?? ''));

        if ($qrImage === '') {
            throw new RuntimeException('Facturación no devolvió la imagen del QR.');
        }

        return [
            'cart_id' => $cartId,
            'transaction_id' => trim((string) ($qrData['transaction_id']
                ?? $qrData['transactionId']
                ?? data_get($emissionResponse, 'cart.qr_transaction_id')
                ?? '')),
            'payment_status' => trim((string) ($qrData['payment_status']
                ?? $qrData['paymentStatus']
                ?? data_get($emissionResponse, 'cart.estado_pago')
                ?? 'pendiente')),
            'amount' => round($amount, 2),
            'internal_code' => trim((string) ($qrData['internal_code']
                ?? data_get($emissionResponse, 'cart.codigo_orden')
                ?? $code)),
            'qr_image' => $qrImage,
        ];
    }

    public function consultarPagoQr(Cliente $cliente, int $cartId): array
    {
        $body = $this->request('POST', '/cart/consultar', [
            'origen_usuario_id' => 'cliente_online_'.$cliente->id,
            'cart_id' => $cartId,
            'auto_emit_invoice' => false,
        ]);

        return $this->normalizarEstadoQr($body);
    }

    public function buscarSolicitudQr(Cliente $cliente, string $attemptId): array
    {
        return $this->request('POST', '/cart/cliente-online', [
            'origen_usuario_id' => 'cliente_online_'.$cliente->id,
            'qr_attempt_id' => Str::lower(trim($attemptId)),
            'origen_id' => $this->qrOriginId(Str::lower(trim($attemptId))),
        ]);
    }

    public function listarSolicitudesQr(Cliente $cliente, int $page = 1, int $perPage = 20): array
    {
        return $this->request('POST', '/cart/cliente-online', [
            'origen_usuario_id' => 'cliente_online_'.$cliente->id,
            'page' => max(1, $page),
            'per_page' => min(100, max(1, $perPage)),
        ]);
    }

    public function vincularSolicitudQr(Cliente $cliente, string $attemptId, SolicitudCliente $solicitud): array
    {
        return $this->request('POST', '/cart/cliente-online/vincular', [
            'origen_usuario_id' => 'cliente_online_'.$cliente->id,
            'qr_attempt_id' => Str::lower(trim($attemptId)),
            'origen_id' => $this->qrOriginId(Str::lower(trim($attemptId))),
            'solicitud_cliente_id' => (int) $solicitud->id,
            'codigo_solicitud' => (string) $solicitud->codigo_solicitud,
        ]);
    }

    public function qrPayloadFromCart(array $cart, string $attemptId): array
    {
        $response = (array) data_get($cart, 'respuesta_emision', []);
        $qrImage = trim((string) (
            data_get($response, 'image_data')
            ?? data_get($response, 'qr_url')
            ?? data_get($response, 'qr_data.image_data')
            ?? data_get($response, 'qr_data.qr_url')
            ?? data_get($response, 'image_url')
            ?? data_get($response, 'items.0.image_data')
            ?? data_get($response, 'items.0.qr_url')
            ?? ''
        ));
        $amount = (float) data_get($cart, 'total', data_get($cart, 'items.0.total_linea', 0));

        return [
            'cart_id' => (int) ($cart['id'] ?? 0),
            'transaction_id' => trim((string) ($cart['qr_transaction_id'] ?? data_get($response, 'transaction_id', ''))),
            'payment_status' => trim((string) ($cart['estado_pago'] ?? data_get($response, 'payment_status', 'pendiente'))),
            'amount' => round($amount, 2),
            'internal_code' => trim((string) ($cart['codigo_orden'] ?? data_get($response, 'internal_code', ''))),
            'qr_image' => $qrImage,
            'qr_attempt_id' => Str::lower(trim($attemptId)),
        ];
    }

    public function emitirFacturaQrPagada(Cliente $cliente, int $cartId): array
    {
        $body = $this->request('POST', '/cart/emitir', [
            'origen_usuario_id' => 'cliente_online_'.$cliente->id,
            'cart_id' => $cartId,
            'modalidad_facturacion' => 'con_datos',
            'canal_emision' => 'factura_electronica',
        ]);

        return $this->normalizarEstadoQr($body);
    }

    private function normalizarEstadoQr(array $body): array
    {
        $cart = (array) data_get($body, 'cart', []);
        $respuesta = (array) data_get($body, 'respuesta', []);
        $respuestaGuardada = data_get($cart, 'respuesta_emision', []);
        if (is_string($respuestaGuardada)) {
            $respuestaGuardada = json_decode($respuestaGuardada, true) ?: [];
        }
        $respuestaGuardada = is_array($respuestaGuardada) ? $respuestaGuardada : [];

        $paymentStatus = strtolower(trim((string) (
            data_get($cart, 'estado_pago')
            ?? data_get($respuesta, 'estado_pago')
            ?? data_get($respuesta, 'payment_status')
            ?? data_get($respuesta, 'paymentStatus')
            ?? 'pendiente'
        )));
        $invoiceStatus = strtoupper(trim((string) (
            data_get($cart, 'estado_emision')
            ?? data_get($respuesta, 'estado')
            ?? data_get($respuestaGuardada, 'estado')
            ?? 'NO_APLICA'
        )));
        $invoiceNumber = trim((string) (
            data_get($respuesta, 'factura.nroFactura')
            ?? data_get($respuesta, 'factura.numeroFactura')
            ?? data_get($respuesta, 'nroFactura')
            ?? data_get($respuestaGuardada, 'factura.nroFactura')
            ?? data_get($respuestaGuardada, 'factura.numeroFactura')
            ?? ''
        ));

        return [
            'cart' => $cart,
            'respuesta' => $respuesta,
            'payment_status' => $paymentStatus,
            'invoice_status' => $invoiceStatus,
            'invoice_number' => $invoiceNumber,
        ];
    }

    private function request(string $method, string $path, array $payload): array
    {
        $baseUrl = rtrim((string) config('services.facturacion_bridge.base_url'), '/');
        $token = trim((string) config('services.facturacion_bridge.token'));
        if ($baseUrl === '' || $token === '') {
            throw new RuntimeException('El servicio de Facturación no está configurado.');
        }

        $client = Http::withToken($token)
            ->acceptJson()
            ->timeout((int) config('services.facturacion_bridge.timeout', 30))
            ->connectTimeout((int) config('services.facturacion_bridge.connect_timeout', 5))
            ->withOptions(['verify' => config('services.facturacion_bridge.ssl_verify', true)]);

        $response = match (strtoupper($method)) {
            'PUT' => $client->put($baseUrl.$path, $payload),
            default => $client->post($baseUrl.$path, $payload),
        };

        return $this->assertSuccessfulResponse($response, $path);
    }

    private function assertSuccessfulResponse(Response $response, string $path): array
    {
        $body = $this->decodeJsonResponse($response);
        if ($response->successful() && is_array($body) && ($body['ok'] ?? false)) {
            return $body;
        }

        Log::warning('La API de Facturacion devolvio una respuesta inesperada al generar QR.', [
            'path' => $path,
            'http_status' => $response->status(),
            'content_type' => $response->header('Content-Type'),
            'body_type' => get_debug_type($body),
            'body_keys' => is_array($body) ? array_slice(array_keys($body), 0, 20) : [],
            'ok_present' => is_array($body) && array_key_exists('ok', $body),
            'ok_value' => is_array($body) ? ($body['ok'] ?? null) : null,
            'success_value' => is_array($body) ? ($body['success'] ?? null) : null,
            'cart_id_present' => data_get($body, 'cart.id') !== null,
        ]);

        $message = trim((string) (data_get($body, 'message')
            ?? data_get($body, 'mensaje')
            ?? data_get($body, 'errors.0')
            ?? ''));
        if ($message === '') {
            $message = 'La API de Facturación rechazó la operación (HTTP '.$response->status().'): '.$path;
        }

        throw new RuntimeException($message);
    }

    private function decodeJsonResponse(Response $response): ?array
    {
        $body = $response->json();
        if (is_array($body)) {
            return $body;
        }

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', (string) $response->body());
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveSucursal(string $city): Sucursal
    {
        $normalizedCity = $this->upper($city);
        $municipioSucursal = match ($normalizedCity) {
            'SANTA CRUZ' => 'SANTA CRUZ DE LA SIERRA',
            'CHUQUISACA' => 'SUCRE',
            'BENI' => 'TRINIDAD',
            'PANDO' => 'COBIJA',
            default => $normalizedCity,
        };
        $sucursal = Sucursal::query()
            ->whereRaw('trim(upper(municipio)) = ?', [$municipioSucursal])
            ->orderBy('codigoSucursal')
            ->orderBy('puntoVenta')
            ->first();

        if (! $sucursal || ! is_numeric($sucursal->codigoSucursal) || ! is_numeric($sucursal->puntoVenta)) {
            throw new RuntimeException('No hay una sucursal de Facturación configurada para '.$normalizedCity.'.');
        }

        return $sucursal;
    }

    private function qrOriginId(string $attemptId): int
    {
        return (int) (hexdec(substr(hash('sha256', $attemptId), 0, 8)) % 1_900_000_000) + 100_000_000;
    }

    private function onlineCustomerEmail(): string
    {
        return (string) config('services.facturacion_bridge.fallback_email', 'sincorreo@agbc.bo');
    }

    private function fiscalDescription(ServicioExtra $servicio): string
    {
        $description = trim((string) $servicio->descripcion);
        if ($description !== '') {
            return $description;
        }

        $name = Str::of((string) $servicio->nombre)->ascii()->upper()->squish()->toString();

        return match (true) {
            str_contains($name, 'PUERTA A PUERTA') => 'Servicio de puerta a puerta - Envio paqueteria',
            str_contains($name, 'PUERTA A VENTANILLA') => 'Servicio de puerta a ventanilla - Envio paqueteria',
            str_contains($name, 'VENTANILLA A VENTANILLA') => 'Servicio de ventanilla a ventanilla - Envio paqueteria',
            default => 'Delivery Express - Envio paqueteria',
        };
    }

    private function upper(string $value): string
    {
        return Str::upper(trim($value));
    }
}

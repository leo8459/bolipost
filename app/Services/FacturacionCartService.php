<?php

namespace App\Services;

use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FacturacionCartService
{
    public function fetchCajaEstado(User $user): array
    {
        $body = $this->request('GET', '/caja/estado', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        ));

        return [
            'estado' => strtoupper(trim((string) (data_get($body, 'estado') ?? data_get($body, 'data.estado') ?? 'SIN_APERTURA'))),
            'mensaje' => trim((string) (data_get($body, 'mensaje') ?? data_get($body, 'message') ?? data_get($body, 'data.mensaje') ?? '')),
            'caja' => (array) (data_get($body, 'caja') ?? data_get($body, 'data.caja') ?? []),
        ];
    }

    public function fetchCajaArqueos(User $user, ?string $mes = null): array
    {
        $payload = array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        );

        if ($mes !== null && trim($mes) !== '') {
            $payload['mes'] = trim($mes);
        }

        $body = $this->request('GET', '/caja/arqueos', $payload);

        return [
            'mes' => (string) (data_get($body, 'mes') ?? ($mes ?: now()->format('Y-m'))),
            'rango' => (array) (data_get($body, 'rango') ?? []),
            'resumen' => (array) (data_get($body, 'resumen') ?? []),
            'dias' => collect((array) data_get($body, 'dias', []))
                ->map(fn ($row) => is_array($row) ? (object) $row : null)
                ->filter()
                ->values(),
        ];
    }

    public function abrirCaja(User $user): array
    {
        $body = $this->request('POST', '/caja/abrir', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        ));

        return [
            'estado' => strtoupper(trim((string) (data_get($body, 'estado') ?? data_get($body, 'data.estado') ?? 'ABIERTA'))),
            'mensaje' => trim((string) (data_get($body, 'mensaje') ?? data_get($body, 'message') ?? data_get($body, 'data.mensaje') ?? 'Caja abierta correctamente.')),
            'caja' => (array) (data_get($body, 'caja') ?? data_get($body, 'data.caja') ?? []),
        ];
    }

    public function cerrarCaja(User $user): array
    {
        $body = $this->request('POST', '/caja/cerrar', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        ));

        return [
            'estado' => strtoupper(trim((string) (data_get($body, 'estado') ?? data_get($body, 'data.estado') ?? 'CERRADA'))),
            'mensaje' => trim((string) (data_get($body, 'mensaje') ?? data_get($body, 'message') ?? data_get($body, 'data.mensaje') ?? 'Caja cerrada correctamente.')),
            'caja' => (array) (data_get($body, 'caja') ?? data_get($body, 'data.caja') ?? []),
        ];
    }

    public function getRemoteContextForUser(User $user): array
    {
        $body = $this->request('GET', '/cart/context', [
            'origen_usuario_id' => (string) $user->id,
        ]);

        $draft = $this->toCart(data_get($body, 'draft'));
        $changed = $this->ensureDraftItemsFiscalDataSynced($user, $draft);
        if ($changed) {
            $body = $this->request('GET', '/cart/context', [
                'origen_usuario_id' => (string) $user->id,
            ]);
            $draft = $this->toCart(data_get($body, 'draft'));
        }

        return [
            'draft' => $draft,
            'last' => $this->toCart(data_get($body, 'last')),
        ];
    }

    public function updateDraftBillingData(User $user, array $payload): object
    {
        $body = $this->request('PUT', '/cart/billing', array_merge(
            $payload,
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        ));

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo actualizar datos de facturacion remotos.');
        }
        return $cart;
    }

    public function addPaqueteEms(User $user, PaqueteEms $paquete): object
    {
        $this->assertFacturacionPermission($user);

        $paquete->loadMissing(['tarifario.servicio']);
        $servicioEms = optional(optional($paquete->tarifario)->servicio);
        $servicio = $this->resolveFiscalServicio(
            $servicioEms instanceof Servicio ? $servicioEms : null,
            null
        );
        $montoBase = round((float) ($paquete->precio ?? 0), 2);

        $body = $this->request('POST', '/cart/items/upsert', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user),
            [
            'origen_tipo' => PaqueteEms::class,
            'origen_id' => (int) $paquete->id,
            'codigo' => (string) ($paquete->codigo ?? ''),
            'titulo' => 'Admision EMS',
            'nombre_servicio' => (string) ($servicio->nombre_servicio ?? ''),
            'nombre_destinatario' => (string) ($paquete->nombre_destinatario ?? ''),
            'servicios_extra' => [],
            'resumen_origen' => [
                'codigo' => (string) ($paquete->codigo ?? ''),
                'contenido' => (string) ($paquete->contenido ?? ''),
                'peso' => (float) ($paquete->peso ?? 0),
                'destinatario' => (string) ($paquete->nombre_destinatario ?? ''),
                'direccion' => (string) ($paquete->direccion ?? ''),
                'ciudad' => (string) ($paquete->ciudad ?? ''),
                'actividad_economica' => (string) ($servicio->actividadEconomica ?? ''),
                'codigo_sin' => (string) ($servicio->codigoSin ?? ''),
                'codigo_producto' => (string) ($servicio->codigo ?? ''),
                'descripcion_servicio' => (string) ($servicio->descripcion ?? ''),
                'unidad_medida' => $servicio->unidadMedida,
            ],
            'cantidad' => 1,
            'monto_base' => $montoBase,
            'monto_extras' => 0,
            'total_linea' => $montoBase,
        ]));

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo guardar item remoto.');
        }
        return $cart;
    }

    public function addPaqueteCerti(User $user, PaqueteCerti $paquete): object
    {
        $this->assertFacturacionPermission($user);

        $paquete->loadMissing('servicio');
        $servicio = $this->resolveFiscalServicio($paquete->servicio, $this->resolveModuloServicio('CERTIFICADAS'))
            ?: $paquete->servicio
            ?: $this->resolveModuloServicio('CERTIFICADAS');
        $peso = (float) ($paquete->peso ?? 0);
        $montoBase = $this->resolveCertiMontoBase($paquete);

        $body = $this->request('POST', '/cart/items/upsert', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user),
            [
            'origen_tipo' => PaqueteCerti::class,
            'origen_id' => (int) $paquete->id,
            'codigo' => (string) ($paquete->codigo ?? ''),
            'titulo' => (string) ($servicio->nombre_servicio ?? 'Envio correspondencia'),
            'nombre_servicio' => (string) ($servicio->nombre_servicio ?? 'ENVIO CORRESPONDENCIA'),
            'nombre_destinatario' => (string) ($paquete->destinatario ?? ''),
            'servicios_extra' => [],
            'resumen_origen' => [
                'codigo' => (string) ($paquete->codigo ?? ''),
                'contenido' => (string) ($paquete->tipo ?? 'CERTIFICADO'),
                'peso' => $peso,
                'destinatario' => (string) ($paquete->destinatario ?? ''),
                'direccion' => (string) ($paquete->zona ?? ''),
                'ciudad' => (string) ($paquete->cuidad ?? ''),
                'actividad_economica' => (string) ($servicio->actividadEconomica ?? ''),
                'codigo_sin' => (string) ($servicio->codigoSin ?? ''),
                'codigo_producto' => (string) ($servicio->codigo ?? ($paquete->codigo ?? '')),
                'descripcion_servicio' => (string) ($servicio->descripcion ?? $servicio->nombre_servicio ?? 'ENVIO CORRESPONDENCIA'),
                'unidad_medida' => $servicio->unidadMedida ?? 58,
            ],
            'cantidad' => 1,
            'monto_base' => $montoBase,
            'monto_extras' => 0,
            'total_linea' => $montoBase,
        ]));

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo guardar item remoto.');
        }
        return $cart;
    }

    public function addPaqueteOrdi(User $user, PaqueteOrdi $paquete): object
    {
        $this->assertFacturacionPermission($user);

        $paquete->loadMissing('servicio');
        $servicio = $this->resolveFiscalServicio($paquete->servicio, $this->resolveModuloServicio('ORDINARIAS'))
            ?: $paquete->servicio
            ?: $this->resolveModuloServicio('ORDINARIAS');
        $peso = $this->toFloatNumber($paquete->peso ?? 0);
        $montoBase = $this->resolveOrdiMontoBase($paquete);

        $body = $this->request('POST', '/cart/items/upsert', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user),
            [
            'origen_tipo' => PaqueteOrdi::class,
            'origen_id' => (int) $paquete->id,
            'codigo' => (string) ($paquete->codigo ?? ''),
            'titulo' => (string) ($servicio->nombre_servicio ?? 'Envio correspondencia'),
            'nombre_servicio' => (string) ($servicio->nombre_servicio ?? 'ENVIO CORRESPONDENCIA'),
            'nombre_destinatario' => (string) ($paquete->destinatario ?? ''),
            'servicios_extra' => [],
            'resumen_origen' => [
                'codigo' => (string) ($paquete->codigo ?? ''),
                'contenido' => (string) ($paquete->observaciones ?? 'ORDINARIO'),
                'peso' => $peso,
                'destinatario' => (string) ($paquete->destinatario ?? ''),
                'direccion' => (string) ($paquete->zona ?? ''),
                'ciudad' => (string) ($paquete->ciudad ?? ''),
                'actividad_economica' => (string) ($servicio->actividadEconomica ?? ''),
                'codigo_sin' => (string) ($servicio->codigoSin ?? ''),
                'codigo_producto' => (string) ($servicio->codigo ?? ($paquete->codigo ?? '')),
                'descripcion_servicio' => (string) ($servicio->descripcion ?? $servicio->nombre_servicio ?? 'ENVIO CORRESPONDENCIA'),
                'unidad_medida' => $servicio->unidadMedida ?? 58,
            ],
            'cantidad' => 1,
            'monto_base' => $montoBase,
            'monto_extras' => 0,
            'total_linea' => $montoBase,
        ]));

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo guardar item remoto.');
        }
        return $cart;
    }

    public function removeItem(User $user, int $itemId): ?object
    {
        try {
            $body = $this->request('DELETE', '/cart/items/' . $itemId, ['origen_usuario_id' => (string) $user->id]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                throw new ModelNotFoundException('Item de facturacion no encontrado.');
            }
            throw $e;
        }
        return $this->toCart(data_get($body, 'cart'));
    }

    public function updateDraftItem(User $user, int $itemId, array $payload): object
    {
        try {
            $body = $this->request('PUT', '/cart/items/' . $itemId, array_merge($payload, [
                'origen_usuario_id' => (string) $user->id,
            ]));
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                throw new ModelNotFoundException('Item de facturacion no encontrado.');
            }
            throw $e;
        }
        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo actualizar item remoto.');
        }
        return $cart;
    }

    public function clearDraftCart(User $user): ?object
    {
        $body = $this->request('POST', '/cart/clear', ['origen_usuario_id' => (string) $user->id]);
        return $this->toCart(data_get($body, 'cart'));
    }

    public function emitirBorrador(User $user): array
    {
        $ctx = $this->getRemoteContextForUser($user);
        $this->ensureDraftItemsFiscalDataSynced($user, $ctx['draft'] ?? null);
        $this->ensureDraftSucursalSynced($user);

        $body = $this->request('POST', '/cart/emitir', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        ));
        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException((string) data_get($body, 'respuesta.mensaje', 'No se pudo emitir.'));
        }
        return ['carrito' => $cart, 'payload' => [], 'respuesta' => (array) data_get($body, 'respuesta', [])];
    }

    public function consultarEstadoEmision(User $user, ?int $cartId = null): array
    {
        $body = $this->request('POST', '/cart/consultar', [
            'origen_usuario_id' => (string) $user->id,
            'cart_id' => $cartId,
        ]);
        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException((string) data_get($body, 'respuesta.mensaje', 'No se pudo consultar.'));
        }
        return ['carrito' => $cart, 'respuesta' => (array) data_get($body, 'respuesta', [])];
    }

    public function fetchVentas(User $user, array $filters): array
    {
        $body = $this->request('GET', '/cart/ventas', array_merge($filters, [
            'origen_usuario_id' => (string) $user->id,
        ]));
        $data = (array) data_get($body, 'data', []);
        $carts = collect((array) data_get($data, 'carts', []))
            ->map(fn ($c) => $this->toCart($c))
            ->filter()
            ->values();

        return [
            'carts' => $carts,
            'pagination' => (array) data_get($data, 'pagination', []),
            'summary' => (array) data_get($data, 'summary', []),
            'filters' => (array) data_get($data, 'filters', []),
        ];
    }

    public function fetchKardexVentas(User $user, array $filters): array
    {
        $estadoSufe = $this->mapEstadoEmisionToSufe((string) ($filters['estado_emision'] ?? 'all'));
        $requestedLimit = (int) ($filters['limite'] ?? (($filters['per_page'] ?? 20) * 10));
        $requestedLimit = max(1, $requestedLimit);
        $limite = min(500, max(50, $requestedLimit));
        $payload = array_filter([
            'origen_usuario_email' => trim((string) ($user->email ?? '')) ?: null,
            'origen_usuario_alias' => trim((string) ($user->alias ?? '')) ?: null,
            'origen_usuario_carnet' => strtoupper(trim((string) ($user->ci ?? ''))) ?: null,
            'fechaInicio' => $filters['from'] ?? null,
            'fechaFin' => $filters['to'] ?? null,
            'q' => trim((string) ($filters['q'] ?? '')) ?: null,
            'estado_sufe' => $estadoSufe,
            'limite' => $limite,
        ], fn ($value) => $value !== null && $value !== '');

        $body = $this->request('GET', '/ventas/reportes/kardex-usuarios', $payload);

        return [
            'detalle' => collect((array) data_get($body, 'detalle', []))
                ->map(fn ($row) => is_array($row) ? (object) $row : null)
                ->filter()
                ->values(),
            'resumen' => (array) data_get($body, 'resumen', []),
            'filters' => $payload,
        ];
    }

    public function fetchVentaById(User $user, int $cartId): ?object
    {
        try {
            $body = $this->request('GET', '/cart/ventas/' . $cartId, [
                'origen_usuario_id' => (string) $user->id,
            ]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }
        return $this->toCart(data_get($body, 'cart'));
    }

    public function fetchVentaDetalleByVentaId(User $user, int $ventaId): ?object
    {
        $payload = array_filter([
            'origen_usuario_email' => trim((string) ($user->email ?? '')) ?: null,
            'origen_usuario_alias' => trim((string) ($user->alias ?? '')) ?: null,
            'origen_usuario_carnet' => strtoupper(trim((string) ($user->ci ?? ''))) ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $body = $this->request('GET', '/ventas/' . $ventaId, $payload);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }

        if (!is_array($body)) {
            return null;
        }

        return (object) $body;
    }

    public function consultarVentaSeguimiento(User $user, string $codigoSeguimiento): array
    {
        $codigoSeguimiento = trim($codigoSeguimiento);
        if ($codigoSeguimiento === '') {
            throw new \RuntimeException('Codigo de seguimiento vacio.');
        }

        $payload = array_filter([
            'origen_usuario_email' => trim((string) ($user->email ?? '')) ?: null,
            'origen_usuario_alias' => trim((string) ($user->alias ?? '')) ?: null,
            'origen_usuario_carnet' => strtoupper(trim((string) ($user->ci ?? ''))) ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        $response = $this->request('GET', '/ventas/consultar/' . rawurlencode($codigoSeguimiento), $payload);

        $estadoSufe = strtoupper(trim((string) (
            data_get($response, 'estadoSufe')
            ?? data_get($response, 'data.estadoSufe')
            ?? data_get($response, 'estado')
            ?? ''
        )));

        $estadoBridge = match ($estadoSufe) {
            'PROCESADA' => 'FACTURADA',
            'RECEPCIONADA', 'CONTINGENCIA_CREADA' => 'PENDIENTE',
            'OBSERVADA' => 'RECHAZADA',
            'ERROR' => 'ERROR',
            default => $estadoSufe !== '' ? $estadoSufe : 'PENDIENTE',
        };

        $mensaje = trim((string) (
            data_get($response, 'mensaje')
            ?? data_get($response, 'message')
            ?? data_get($response, 'data.mensaje')
            ?? data_get($response, 'data.message')
            ?? 'Consulta realizada correctamente.'
        ));

        $pdfUrl = trim((string) (
            data_get($response, 'factura.pdfUrl')
            ?? data_get($response, 'data.factura.pdfUrl')
            ?? data_get($response, 'urlPdf')
            ?? data_get($response, 'data.urlPdf')
        ));

        return [
            'estado' => $estadoBridge,
            'mensaje' => $mensaje,
            'codigoOrden' => (string) (
                data_get($response, 'codigoOrden')
                ?? data_get($response, 'data.codigoOrden')
                ?? ''
            ),
            'factura' => [
                'pdfUrl' => $pdfUrl,
            ],
            'raw' => $response,
        ];
    }

    public function fetchVentasPdf(User $user, array $filters): array
    {
        $sucursal = $user->sucursal;
        $payload = array_merge($filters, [
            'origen_usuario_id' => (string) $user->id,
            'responsable_nombre' => (string) ($user->name ?? ''),
            'oficina_postal' => (string) ($sucursal->nombre ?? $sucursal->descripcion ?? $sucursal->municipio ?? ''),
            'ventanilla' => $sucursal ? ('Punto ' . (string) ($sucursal->puntoVenta ?? '')) : '',
        ]);

        $client = Http::baseUrl($this->resolveBaseUrl())
            ->withToken((string) config('services.facturacion_bridge.token'))
            ->accept('application/pdf')
            ->timeout((int) config('services.facturacion_bridge.timeout', 30));

        $response = $client->get('/cart/ventas/pdf', $payload);

        if (!$response->successful()) {
            $body = $response->json();
            $msg = is_array($body)
                ? (string) ($body['message'] ?? $body['mensaje'] ?? 'Error remoto')
                : (string) $response->body();
            throw new \RuntimeException($response->status() . ' ' . trim($msg));
        }

        $disposition = (string) ($response->header('Content-Disposition') ?? '');
        preg_match('/filename=\"?([^\";]+)\"?/i', $disposition, $matches);
        $filename = trim((string) ($matches[1] ?? ''));
        if ($filename === '') {
            $filename = 'kardex-facturacion-' . now()->format('Ymd-His') . '.pdf';
        }

        return [
            'content' => $response->body(),
            'content_type' => (string) ($response->header('Content-Type') ?? 'application/pdf'),
            'filename' => $filename,
        ];
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $client = Http::baseUrl($this->resolveBaseUrl())
            ->withToken((string) config('services.facturacion_bridge.token'))
            ->acceptJson()
            ->timeout((int) config('services.facturacion_bridge.timeout', 30));

        $response = match (strtoupper($method)) {
            'GET' => $client->get($path, $payload),
            'PUT' => $client->put($path, $payload),
            'DELETE' => $client->send('DELETE', $path, ['json' => $payload]),
            default => $client->post($path, $payload),
        };

        try {
            $response->throw();
        } catch (RequestException $e) {
            $body = $response->json();
            $msg = is_array($body)
                ? (string) ($body['message'] ?? $body['mensaje'] ?? 'Error remoto')
                : (string) $e->getMessage();
            throw new \RuntimeException($response->status() . ' ' . $msg, 0, $e);
        }

        $body = $response->json();
        if (!is_array($body)) {
            throw new \RuntimeException('Respuesta no valida de API facturacion.');
        }
        return $body;
    }

    private function resolveBaseUrl(): string
    {
        $base = rtrim((string) config('services.facturacion_bridge.base_url'), '/');
        if ($base === '') {
            throw new \RuntimeException('No se configuro FACTURACION_BRIDGE_BASE_URL.');
        }
        if (str_ends_with($base, '/emitir')) {
            $base = substr($base, 0, -7);
        }
        return rtrim($base, '/');
    }

    private function toCart($data): ?object
    {
        if (!is_array($data)) {
            return null;
        }
        $data['respuesta_emision'] = (array) ($data['respuesta_emision'] ?? []);
        $data['items'] = collect((array) ($data['items'] ?? []))
            ->map(fn ($i) => is_array($i) ? (object) $i : null)
            ->filter()
            ->values();
        return (object) $data;
    }

    private function originUserPayload(User $user): array
    {
        $alias = trim((string) ($user->alias ?? ''));
        $carnet = strtoupper(trim((string) ($user->ci ?? '')));

        return [
            'origen_usuario_id' => (string) $user->id,
            'origen_usuario_nombre' => (string) ($user->name ?? ''),
            'origen_usuario_email' => (string) ($user->email ?? ''),
            'origen_usuario_alias' => $alias !== '' ? $alias : null,
            'origen_usuario_carnet' => $carnet !== '' ? $carnet : null,
        ];
    }

    private function originSucursalPayload(User $user): array
    {
        $user->loadMissing('sucursal');
        $sucursal = $user->sucursal;

        if (!$sucursal) {
            throw new \RuntimeException('El usuario no tiene sucursal asignada para facturacion.');
        }

        if ($sucursal->codigoSucursal === null || $sucursal->puntoVenta === null) {
            throw new \RuntimeException('La sucursal asignada no tiene codigoSucursal/puntoVenta configurados.');
        }

        $codigoSucursal = trim((string) $sucursal->codigoSucursal);
        $puntoVenta = trim((string) $sucursal->puntoVenta);
        $nombreSucursal = trim((string) ($sucursal->nombre ?? $sucursal->descripcion ?? ''));

        if ($codigoSucursal === '' || $puntoVenta === '') {
            throw new \RuntimeException('La sucursal asignada no tiene codigoSucursal/puntoVenta validos.');
        }

        return [
            // Claves usadas por el bridge actual
            'origen_sucursal_id' => $puntoVenta,
            'origen_sucursal_codigo' => $codigoSucursal,
            'origen_sucursal_nombre' => $nombreSucursal,
            // Claves requeridas por endpoints de caja en API facturacion
            'codigo_sucursal' => $codigoSucursal,
            'punto_venta' => $puntoVenta,
            // Compatibilidad adicional por si el backend valida en camelCase
            'codigoSucursal' => $codigoSucursal,
            'puntoVenta' => $puntoVenta,
        ];
    }

    private function mapEstadoEmisionToSufe(string $estado): ?string
    {
        return match (strtoupper(trim($estado))) {
            'FACTURADA' => 'PROCESADA',
            'PENDIENTE' => 'RECEPCIONADA',
            'RECHAZADA' => 'OBSERVADA',
            'ERROR' => 'ERROR',
            default => null,
        };
    }

    private function ensureDraftSucursalSynced(User $user): void
    {
        $ctx = $this->getRemoteContextForUser($user);
        $draft = $ctx['draft'] ?? null;
        if (!$draft) {
            return;
        }

        $codigoSucursal = trim((string) ($draft->origen_sucursal_codigo ?? $draft->codigoSucursal ?? ''));
        $puntoVenta = trim((string) ($draft->origen_sucursal_id ?? $draft->puntoVenta ?? ''));
        if ($codigoSucursal !== '' && $puntoVenta !== '') {
            return;
        }

        $payload = array_filter([
            'modalidad_facturacion' => (string) ($draft->modalidad_facturacion ?? 'con_datos'),
            'canal_emision' => (string) ($draft->canal_emision ?? 'factura_electronica'),
            'tipo_documento' => (string) ($draft->tipo_documento ?? ''),
            'razon_social' => (string) ($draft->razon_social ?? ''),
            'numero_documento' => (string) ($draft->numero_documento ?? ''),
            'complemento_documento' => (string) ($draft->complemento_documento ?? ''),
        ], fn ($v) => $v !== null);

        $this->request('PUT', '/cart/billing', array_merge(
            $payload,
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        ));
    }

    private function resolveCertiMontoBase(PaqueteCerti $paquete): float
    {
        $precio = round($this->toFloatNumber($paquete->precio ?? 0), 2);
        if ($precio > 0) {
            return $precio;
        }

        $peso = $this->toFloatNumber($paquete->peso ?? 0);
        if ($peso <= 0) {
            return 0.00;
        }

        // Compatibilidad: pesos historicos en gramos vs kg.
        $kg = $peso > 10 ? ($peso / 1000) : $peso;
        if ($kg >= 0.001 && $kg <= 0.500) {
            return 5.00;
        }
        if ($kg > 0.500 && $kg <= 2.000) {
            return 10.00;
        }

        return 0.00;
    }

    private function resolveOrdiMontoBase(PaqueteOrdi $paquete): float
    {
        $precio = round($this->toFloatNumber($paquete->precio ?? 0), 2);
        if ($precio > 0) {
            return $precio;
        }

        $peso = $this->toFloatNumber($paquete->peso ?? 0);
        if ($peso <= 0) {
            return 0.00;
        }

        // Compatibilidad: pesos historicos en gramos vs kg.
        $kg = $peso > 10 ? ($peso / 1000) : $peso;
        if ($kg >= 0.001 && $kg <= 0.500) {
            return 5.00;
        }
        if ($kg > 0.500 && $kg <= 2.000) {
            return 10.00;
        }

        return 0.00;
    }

    private function toFloatNumber(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }

        $normalized = str_replace(',', '.', $raw);
        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }

    private function resolveModuloServicio(string $nombre): ?Servicio
    {
        $servicio = Servicio::query()
            ->whereRaw('trim(upper(nombre_servicio)) = trim(upper(?))', [$nombre])
            ->first();

        if ($this->hasServicioFiscalData($servicio)) {
            return $servicio;
        }

        $fallback = $this->resolveAnyServicioWithFiscalData();

        return $fallback ?: $servicio;
    }

    private function resolveAnyServicioWithFiscalData(): ?Servicio
    {
        return Servicio::query()
            ->whereNotNull('actividadEconomica')
            ->whereRaw("trim(\"actividadEconomica\") <> ''")
            ->whereNotNull('codigoSin')
            ->whereRaw("trim(\"codigoSin\") <> ''")
            ->whereNotNull('codigo')
            ->whereRaw("trim(\"codigo\") <> ''")
            ->whereNotNull('unidadMedida')
            ->where('unidadMedida', '>', 0)
            ->first();
    }

    private function resolveFiscalServicio(?Servicio ...$candidatos): ?Servicio
    {
        foreach ($candidatos as $servicio) {
            if ($this->hasServicioFiscalData($servicio)) {
                return $servicio;
            }
        }

        return $this->resolveAnyServicioWithFiscalData();
    }

    private function hasServicioFiscalData(?Servicio $servicio): bool
    {
        if (!$servicio) {
            return false;
        }

        return trim((string) ($servicio->actividadEconomica ?? '')) !== ''
            && trim((string) ($servicio->codigoSin ?? '')) !== ''
            && trim((string) ($servicio->codigo ?? '')) !== ''
            && (int) ($servicio->unidadMedida ?? 0) > 0;
    }

    private function ensureDraftItemsFiscalDataSynced(User $user, ?object $draft): bool
    {
        if (!$draft || !isset($draft->items)) {
            return false;
        }

        $changed = false;
        foreach ((array) $draft->items as $item) {
            if (!$item || !isset($item->id)) {
                continue;
            }

            $resumen = (array) ($item->resumen_origen ?? []);
            $needSync = trim((string) ($resumen['actividad_economica'] ?? '')) === ''
                || trim((string) ($resumen['codigo_sin'] ?? '')) === ''
                || trim((string) ($resumen['codigo_producto'] ?? '')) === ''
                || trim((string) ($resumen['descripcion_servicio'] ?? '')) === ''
                || (int) ($resumen['unidad_medida'] ?? 0) <= 0;

            if (!$needSync) {
                continue;
            }

            $servicio = $this->resolveServicioForDraftItem($item);
            if (!$servicio) {
                continue;
            }

            $payload = [
                'codigo' => (string) ($item->codigo ?? ''),
                'titulo' => (string) ($item->titulo ?? ''),
                'nombre_servicio' => (string) ($servicio->nombre_servicio ?? $item->nombre_servicio ?? ''),
                'nombre_destinatario' => (string) ($item->nombre_destinatario ?? ''),
                'contenido' => (string) ($resumen['contenido'] ?? ''),
                'direccion' => (string) ($resumen['direccion'] ?? ''),
                'ciudad' => (string) ($resumen['ciudad'] ?? ''),
                'peso' => is_numeric($resumen['peso'] ?? null) ? (float) $resumen['peso'] : null,
                'actividad_economica' => (string) ($servicio->actividadEconomica ?? $resumen['actividad_economica'] ?? ''),
                'codigo_sin' => (string) ($servicio->codigoSin ?? $resumen['codigo_sin'] ?? ''),
                'codigo_producto' => (string) ($servicio->codigo ?? $resumen['codigo_producto'] ?? ($item->codigo ?? '')),
                'descripcion_servicio' => (string) ($servicio->descripcion ?? $servicio->nombre_servicio ?? $resumen['descripcion_servicio'] ?? ''),
                'unidad_medida' => (int) ($servicio->unidadMedida ?? 0) > 0 ? (int) $servicio->unidadMedida : 58,
            ];

            try {
                $this->updateDraftItem($user, (int) $item->id, $payload);
                $changed = true;
            } catch (\Throwable $e) {
                // keep flow resilient; failed items can still be edited manually in UI
            }
        }

        return $changed;
    }

    private function resolveServicioForDraftItem(object $item): ?Servicio
    {
        $origenTipo = ltrim((string) ($item->origen_tipo ?? ''), '\\');
        $origenId = (int) ($item->origen_id ?? 0);

        if ($origenId <= 0) {
            return null;
        }

        if ($origenTipo === ltrim(PaqueteCerti::class, '\\')) {
            $paquete = PaqueteCerti::query()->with('servicio')->find($origenId);
            return $this->resolveFiscalServicio(
                $paquete?->servicio,
                $this->resolveModuloServicio('CERTIFICADAS')
            );
        }

        if ($origenTipo === ltrim(PaqueteOrdi::class, '\\')) {
            $paquete = PaqueteOrdi::query()->with('servicio')->find($origenId);
            return $this->resolveFiscalServicio(
                $paquete?->servicio,
                $this->resolveModuloServicio('ORDINARIAS')
            );
        }

        if ($origenTipo === ltrim(PaqueteEms::class, '\\')) {
            $paquete = PaqueteEms::query()->with('tarifario.servicio')->find($origenId);
            $servicio = optional(optional($paquete)->tarifario)->servicio;
            if ($servicio instanceof Servicio) {
                return $this->resolveFiscalServicio($servicio);
            }
        }

        return null;
    }

    private function assertFacturacionPermission(User $user): void
    {
        if (!method_exists($user, 'can') || !$user->can('feature.dashboard.facturacion')) {
            throw new \RuntimeException('El usuario no tiene permiso de facturacion para agregar items al carrito.');
        }
    }
}

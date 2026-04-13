<?php

namespace App\Services;

use App\Models\FacturacionCart;
use App\Models\FacturacionCartItem;
use App\Models\PaqueteEms;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FacturacionCartService
{
    public function getActiveCartForUser(User $user): FacturacionCart
    {
        return FacturacionCart::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'estado' => 'borrador',
            ],
            [
                'abierto_en' => now(),
            ]
        );
    }

    public function addPaqueteEms(User $user, PaqueteEms $paquete): FacturacionCartItem
    {
        return DB::transaction(function () use ($user, $paquete) {
            $cart = $this->getActiveCartForUser($user);

            $paquete->loadMissing(['tarifario.servicio', 'tarifario.destino']);

            $montoBase = round((float) ($paquete->precio ?? 0), 2);
            $serviciosExtra = $this->resolveExtraServicesForPaqueteEms($paquete);
            $montoExtras = round((float) collect($serviciosExtra)->sum('amount'), 2);
            $totalLinea = round($montoBase + $montoExtras, 2);

            $item = FacturacionCartItem::query()->updateOrCreate(
                [
                    'cart_id' => $cart->id,
                    'origen_tipo' => PaqueteEms::class,
                    'origen_id' => $paquete->id,
                ],
                [
                    'codigo' => (string) ($paquete->codigo ?? ''),
                    'titulo' => 'Admision EMS',
                    'nombre_servicio' => (string) optional(optional($paquete->tarifario)->servicio)->nombre_servicio,
                    'nombre_destinatario' => (string) ($paquete->nombre_destinatario ?? ''),
                    'servicios_extra' => $serviciosExtra,
                    'resumen_origen' => [
                        'codigo' => (string) ($paquete->codigo ?? ''),
                        'tipo_correspondencia' => (string) ($paquete->tipo_correspondencia ?? ''),
                        'servicio_especial' => (string) ($paquete->servicio_especial ?? ''),
                        'contenido' => (string) ($paquete->contenido ?? ''),
                        'cantidad' => (int) ($paquete->cantidad ?? 1),
                        'peso' => (float) ($paquete->peso ?? 0),
                        'destinatario' => (string) ($paquete->nombre_destinatario ?? ''),
                        'direccion' => (string) ($paquete->direccion ?? ''),
                        'ciudad' => (string) ($paquete->ciudad ?? ''),
                        'precio' => $montoBase,
                        'actividad_economica' => (string) (optional(optional($paquete->tarifario)->servicio)->actividadEconomica ?? ''),
                        'codigo_sin' => (string) (optional(optional($paquete->tarifario)->servicio)->codigoSin ?? ''),
                        'codigo_producto' => (string) (optional(optional($paquete->tarifario)->servicio)->codigo ?? ''),
                        'descripcion_servicio' => (string) (optional(optional($paquete->tarifario)->servicio)->descripcion ?? ''),
                        'unidad_medida' => optional(optional($paquete->tarifario)->servicio)->unidadMedida,
                    ],
                    'cantidad' => 1,
                    'monto_base' => $montoBase,
                    'monto_extras' => $montoExtras,
                    'total_linea' => $totalLinea,
                ]
            );

            $this->refreshCartTotals($cart);

            return $item;
        });
    }

    public function refreshCartTotals(FacturacionCart $cart): FacturacionCart
    {
        $cart->loadMissing('items');

        $cart->forceFill([
            'cantidad_items' => $cart->items->count(),
            'subtotal' => round((float) $cart->items->sum(fn (FacturacionCartItem $item) => (float) $item->monto_base), 2),
            'total_extras' => round((float) $cart->items->sum(fn (FacturacionCartItem $item) => (float) $item->monto_extras), 2),
            'total' => round((float) $cart->items->sum(fn (FacturacionCartItem $item) => (float) $item->total_linea), 2),
        ])->save();

        return $cart->fresh('items');
    }

    public function removeItem(User $user, int $itemId): ?FacturacionCart
    {
        return DB::transaction(function () use ($user, $itemId) {
            $item = FacturacionCartItem::query()
                ->whereKey($itemId)
                ->whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->where('estado', 'borrador');
                })
                ->first();

            if (!$item) {
                throw new ModelNotFoundException('Item de facturacion no encontrado.');
            }

            $cart = $item->cart;
            $item->delete();

            return $this->refreshCartTotals($cart);
        });
    }

    public function clearDraftCart(User $user): ?FacturacionCart
    {
        return DB::transaction(function () use ($user) {
            $cart = FacturacionCart::query()
                ->where('user_id', $user->id)
                ->where('estado', 'borrador')
                ->with('items')
                ->latest('id')
                ->first();

            if (!$cart) {
                return null;
            }

            $cart->items()->delete();

            return $this->refreshCartTotals($cart);
        });
    }

    public function updateDraftBillingData(User $user, array $payload): FacturacionCart
    {
        return DB::transaction(function () use ($user, $payload) {
            $cart = $this->getActiveCartForUser($user);
            $modalidadFacturacion = $this->normalizeBillingMode($payload['modalidad_facturacion'] ?? null);
            $canalEmision = $this->normalizeInvoiceChannel($payload['canal_emision'] ?? null);

            $tipoDocumento = $modalidadFacturacion === 'sin_cliente'
                ? null
                : $this->normalizeNullableString($payload['tipo_documento'] ?? null);
            $numeroDocumento = $modalidadFacturacion === 'sin_cliente'
                ? null
                : $this->normalizeNullableString($payload['numero_documento'] ?? null);
            $complementoDocumento = $modalidadFacturacion === 'sin_cliente' || ! $this->documentTypeUsesComplement($tipoDocumento)
                ? null
                : Str::upper((string) $this->normalizeNullableString($payload['complemento_documento'] ?? null));
            $razonSocial = $modalidadFacturacion === 'sin_cliente'
                ? null
                : Str::upper((string) $this->normalizeNullableString($payload['razon_social'] ?? null));

            $cart->forceFill([
                'modalidad_facturacion' => $modalidadFacturacion,
                'canal_emision' => $canalEmision,
                'tipo_documento' => $tipoDocumento,
                'numero_documento' => $numeroDocumento,
                'complemento_documento' => $complementoDocumento,
                'razon_social' => $razonSocial,
            ])->save();

            return $cart->fresh('items');
        });
    }

    public function emitirBorrador(User $user): array
    {
        $cart = FacturacionCart::query()
            ->where('user_id', $user->id)
            ->where('estado', 'borrador')
            ->with('items')
            ->latest('id')
            ->first();

        if (! $cart) {
            throw new \RuntimeException('No se encontro un borrador de facturacion activo.');
        }

        if ($cart->items->isEmpty()) {
            throw new \RuntimeException('El borrador no tiene items para emitir.');
        }

        $payload = $this->buildBridgePayload($user, $cart);
        $attemptNumber = $this->nextAttemptNumber($cart);

        try {
            $response = Http::baseUrl($this->resolveBridgeBaseUrl())
                ->withToken((string) config('services.facturacion_bridge.token'))
                ->withHeader('X-Bridge-Debug', 'true')
                ->acceptJson()
                ->timeout((int) config('services.facturacion_bridge.timeout', 30))
                ->post('/emitir', $payload);

            $body = $response->json();

            if (! is_array($body)) {
                $body = [
                    'ok' => false,
                    'estado' => 'ERROR',
                    'mensaje' => 'La API externa devolvio una respuesta no valida.',
                ];
            }

            $body = $this->attachAttemptMetadata($body, $attemptNumber);

            if ($response->failed() || !($body['ok'] ?? false)) {
                $this->registrarResultadoEmision($cart, $payload, $body, false);

                throw new \RuntimeException((string) ($body['mensaje'] ?? $body['message'] ?? 'No se pudo emitir la factura.'));
            }

            $cart = DB::transaction(function () use ($cart, $payload, $body) {
                $cart->forceFill([
                    'estado' => 'emitido',
                    'codigo_orden' => (string) ($body['codigoOrden'] ?? $payload['codigoOrden']),
                    'codigo_seguimiento' => (string) ($body['codigoSeguimiento'] ?? data_get($body, 'sefe.datos.codigoSeguimiento', '')),
                    'estado_emision' => (string) ($body['estado'] ?? 'PENDIENTE'),
                    'mensaje_emision' => (string) ($body['mensaje'] ?? 'Factura emitida correctamente.'),
                    'respuesta_emision' => $body,
                    'cerrado_en' => now(),
                    'emitido_en' => now(),
                ])->save();

                return $cart->fresh('items');
            });

            return [
                'carrito' => $cart,
                'payload' => $payload,
                'respuesta' => $body,
            ];
        } catch (ConnectionException $e) {
            throw new \RuntimeException('No se pudo conectar con la API de facturacion: ' . $e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new \RuntimeException('La API de facturacion rechazo la solicitud: ' . $e->getMessage(), 0, $e);
        }
    }

    public function consultarEstadoEmision(User $user, ?int $cartId = null): array
    {
        $query = FacturacionCart::query()
            ->where('user_id', $user->id)
            ->whereNotNull('codigo_seguimiento');

        if ($cartId) {
            $query->whereKey($cartId);
        }

        $cart = $query->latest('emitido_en')->latest('id')->first();

        if (! $cart) {
            throw new \RuntimeException('No existe una emision previa para consultar.');
        }

        $codigoSeguimiento = trim((string) $cart->codigo_seguimiento);
        if ($codigoSeguimiento === '') {
            throw new \RuntimeException('La emision seleccionada no tiene codigo de seguimiento.');
        }

        try {
            $response = Http::baseUrl($this->resolveBridgeBaseUrl())
                ->withToken((string) config('services.facturacion_bridge.token'))
                ->withHeader('X-Bridge-Debug', 'true')
                ->acceptJson()
                ->timeout((int) config('services.facturacion_bridge.timeout', 30))
                ->get('/consultar/' . urlencode($codigoSeguimiento));

            $body = $response->json();

            if (! is_array($body)) {
                $body = [
                    'ok' => false,
                    'estado' => 'ERROR',
                    'mensaje' => 'La API externa devolvio una respuesta no valida en la consulta.',
                ];
            }

            if ($response->failed() || !($body['ok'] ?? false)) {
                $this->registrarResultadoConsulta($cart, $body, false);

                throw new \RuntimeException((string) ($body['mensaje'] ?? $body['message'] ?? 'No se pudo consultar el estado de la factura.'));
            }

            $cart->forceFill([
                'estado_emision' => (string) ($body['estado'] ?? $cart->estado_emision),
                'mensaje_emision' => (string) ($body['mensaje'] ?? $cart->mensaje_emision),
                'respuesta_emision' => $body,
            ])->save();

            return [
                'carrito' => $cart->fresh(),
                'respuesta' => $body,
            ];
        } catch (ConnectionException $e) {
            throw new \RuntimeException('No se pudo consultar la API de facturacion: ' . $e->getMessage(), 0, $e);
        } catch (RequestException $e) {
            throw new \RuntimeException('La API de facturacion rechazo la consulta: ' . $e->getMessage(), 0, $e);
        }
    }

    private function buildBridgePayload(User $user, FacturacionCart $cart): array
    {
        $sucursal = $user->sucursal;

        if (! $sucursal) {
            throw new \RuntimeException('El usuario no tiene una sucursal configurada para facturacion.');
        }

        $modalidad = (string) $cart->modalidad_facturacion;
        $sinCliente = $modalidad === 'sin_cliente';
        $codigoOrden = $this->generateCodigoOrden($cart);
        $tipoDocumento = $sinCliente ? 5 : (int) ($cart->tipo_documento ?: 1);
        $numeroDocumento = $sinCliente ? '99003' : (string) ($cart->numero_documento ?: '');
        $razonSocial = $sinCliente ? 'SIN NOMBRE' : (string) ($cart->razon_social ?: '');
        $complemento = $tipoDocumento === 1
            ? $this->sanitizeComplemento($cart->complemento_documento)
            : null;

        if (! $sinCliente) {
            if ($numeroDocumento === '' || $razonSocial === '' || blank($cart->tipo_documento)) {
                throw new \RuntimeException('Completa tipo de documento, numero de documento y razon social antes de emitir.');
            }
        }

        $detalle = $cart->items->map(function (FacturacionCartItem $item) {
            $resumen = (array) ($item->resumen_origen ?? []);
            $cantidad = max(1, (int) ($item->cantidad ?? 1));
            $precioUnitario = round((float) ($item->monto_base ?? 0), 2);
            $actividadEconomica = trim((string) ($resumen['actividad_economica'] ?? ''));
            $codigoSin = trim((string) ($resumen['codigo_sin'] ?? ''));
            $codigoProducto = trim((string) ($resumen['codigo_producto'] ?? ''));
            $descripcion = trim((string) ($resumen['descripcion_servicio'] ?? ''));
            $unidadMedidaRaw = $resumen['unidad_medida'] ?? null;
            $unidadMedida = is_numeric($unidadMedidaRaw) ? (int) $unidadMedidaRaw : 0;

            $itemLabel = trim((string) ($item->nombre_servicio ?: $item->titulo ?: $item->codigo ?: 'item'));
            if ($actividadEconomica === '') {
                throw new \RuntimeException("El servicio {$itemLabel} no tiene actividad economica configurada.");
            }
            if ($codigoSin === '') {
                throw new \RuntimeException("El servicio {$itemLabel} no tiene codigo SIN configurado.");
            }
            if ($codigoProducto === '') {
                throw new \RuntimeException("El servicio {$itemLabel} no tiene codigo configurado.");
            }
            if (mb_strlen($codigoProducto) < 3) {
                throw new \RuntimeException("El servicio {$itemLabel} tiene un codigo demasiado corto. Debe tener al menos 3 caracteres.");
            }
            if ($descripcion === '') {
                throw new \RuntimeException("El servicio {$itemLabel} no tiene descripcion configurada.");
            }
            if ($unidadMedida <= 0) {
                throw new \RuntimeException("El servicio {$itemLabel} no tiene unidad de medida configurada.");
            }

            return [
                'actividadEconomica' => $actividadEconomica,
                'codigoSin' => $codigoSin,
                'codigo' => $codigoProducto,
                'descripcion' => Str::limit(Str::upper($descripcion), 250, ''),
                'unidadMedida' => $unidadMedida,
                'precioUnitario' => $precioUnitario,
                'cantidad' => $cantidad,
            ];
        })->values()->all();

        $codigoSucursalRaw = $sucursal->codigoSucursal ?? null;
        $puntoVentaRaw = $sucursal->puntoVenta ?? null;
        $municipioRaw = trim((string) ($sucursal->municipio ?? ''));
        $departamentoRaw = trim((string) ($sucursal->departamento ?? ''));
        $telefonoRaw = preg_replace('/[^0-9]/', '', (string) ($sucursal->telefono ?? '')) ?: '';

        if (! is_numeric($codigoSucursalRaw)) {
            throw new \RuntimeException('La sucursal del usuario no tiene codigo de sucursal configurado.');
        }
        if (! is_numeric($puntoVentaRaw)) {
            throw new \RuntimeException('La sucursal del usuario no tiene punto de venta configurado.');
        }
        if ($municipioRaw === '') {
            throw new \RuntimeException('La sucursal del usuario no tiene municipio configurado.');
        }
        if ($departamentoRaw === '') {
            throw new \RuntimeException('La sucursal del usuario no tiene departamento configurado.');
        }
        if ($telefonoRaw === '') {
            throw new \RuntimeException('La sucursal del usuario no tiene telefono configurado.');
        }

        $codigoSucursal = (int) $codigoSucursalRaw;
        $puntoVenta = (int) $puntoVentaRaw;
        $municipio = Str::upper($municipioRaw);
        $departamento = Str::upper($departamentoRaw);
        $telefono = $telefonoRaw;
        $correo = $this->resolveBillingEmail($user);

        $payload = [
            'codigoOrden' => $codigoOrden,
            'origenUsuario' => [
                'nombre' => (string) $user->name,
                'email' => $correo,
            ],
            'codigoSucursal' => $codigoSucursal,
            'puntoVenta' => $puntoVenta,
            'documentoSector' => (int) config('services.facturacion_bridge.documento_sector', 1),
            'municipio' => $municipio,
            'departamento' => $departamento,
            'telefono' => $telefono,
            'codigoCliente' => $this->resolveCodigoClientePuente($cart, $numeroDocumento, $sinCliente),
            'razonSocial' => Str::upper($razonSocial),
            'documentoIdentidad' => $numeroDocumento,
            'tipoDocumentoIdentidad' => $tipoDocumento,
            'complemento' => $complemento,
            'correo' => $correo,
            'metodoPago' => (int) config('services.facturacion_bridge.metodo_pago', 1),
            'formatoFactura' => (string) config('services.facturacion_bridge.formato_factura', 'rollo'),
            'montoTotal' => round((float) $cart->total, 2),
            'detalle' => $detalle,
        ];

        Log::info('Facturacion bridge payload prepared', [
            'cart_id' => $cart->id,
            'user_id' => $user->id,
            'bridge_url' => $this->resolveBridgeBaseUrl() . '/emitir',
            'codigoOrden' => $payload['codigoOrden'],
            'codigoSucursal' => $payload['codigoSucursal'],
            'puntoVenta' => $payload['puntoVenta'],
            'documentoIdentidad' => $payload['documentoIdentidad'],
            'tipoDocumentoIdentidad' => $payload['tipoDocumentoIdentidad'],
            'montoTotal' => $payload['montoTotal'],
            'detalle_count' => count($payload['detalle']),
            'using_fallback_sucursal' => ! $sucursal,
        ]);

        return $payload;
    }

    private function resolveCodigoClientePuente(FacturacionCart $cart, string $numeroDocumento, bool $sinCliente): string
    {
        if ($sinCliente) {
            return 'SN-' . str_pad((string) $cart->id, 8, '0', STR_PAD_LEFT);
        }

        $base = preg_replace('/[^A-Za-z0-9\-_]/', '', strtoupper($numeroDocumento)) ?: 'CLI';

        return Str::limit('CLI-' . $base, 35, '');
    }

    private function sanitizeComplemento(?string $complemento): ?string
    {
        $clean = strtoupper(trim((string) $complemento));
        if ($clean === '') {
            return null;
        }

        $clean = preg_replace('/[^A-Z0-9]/', '', $clean) ?: '';

        return $clean === '' ? null : Str::limit($clean, 2, '');
    }

    private function generateCodigoOrden(FacturacionCart $cart): string
    {
        return 'BOLI-' . now()->format('YmdHis') . '-' . str_pad((string) $cart->id, 6, '0', STR_PAD_LEFT);
    }

    private function registrarResultadoEmision(FacturacionCart $cart, array $payload, array $body, bool $exitoso): void
    {
        $cart->forceFill([
            'codigo_orden' => (string) ($body['codigoOrden'] ?? $payload['codigoOrden'] ?? ''),
            'codigo_seguimiento' => (string) ($body['codigoSeguimiento'] ?? data_get($body, 'sefe.datos.codigoSeguimiento', '')),
            'estado_emision' => (string) ($body['estado'] ?? ($exitoso ? 'PENDIENTE' : 'RECHAZADA')),
            'mensaje_emision' => (string) ($body['mensaje'] ?? $body['message'] ?? ''),
            'respuesta_emision' => $body,
        ])->save();
    }

    private function registrarResultadoConsulta(FacturacionCart $cart, array $body, bool $exitoso): void
    {
        $cart->forceFill([
            'estado_emision' => (string) ($body['estado'] ?? ($exitoso ? $cart->estado_emision : 'ERROR')),
            'mensaje_emision' => (string) ($body['mensaje'] ?? $body['message'] ?? $cart->mensaje_emision),
            'respuesta_emision' => $body,
        ])->save();
    }

    private function resolveExtraServicesForPaqueteEms(PaqueteEms $paquete): array
    {
        return [];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeBillingMode(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['con_datos', 'sin_cliente'], true) ? $value : 'con_datos';
    }

    private function normalizeInvoiceChannel(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['qr', 'factura_electronica'], true) ? $value : 'qr';
    }

    private function documentTypeUsesComplement(?string $documentType): bool
    {
        return in_array(trim((string) $documentType), ['1', '2'], true);
    }

    private function resolveBillingEmail(User $user): string
    {
        $email = trim((string) ($user->email ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }

        return (string) config('services.facturacion_bridge.fallback_email', 'sincorreo@agbc.bo');
    }

    private function nextAttemptNumber(FacturacionCart $cart): int
    {
        $current = (int) data_get($cart->respuesta_emision, '_meta.intentos', 0);

        return max(1, $current + 1);
    }

    private function attachAttemptMetadata(array $body, int $attemptNumber): array
    {
        $meta = (array) ($body['_meta'] ?? []);
        $meta['intentos'] = $attemptNumber;
        $meta['reintentable'] = (($body['estado'] ?? null) === 'RECHAZADA');
        $meta['registrado_en'] = now()->toIso8601String();
        $body['_meta'] = $meta;

        return $body;
    }

    private function resolveBridgeBaseUrl(): string
    {
        $baseUrl = trim((string) config('services.facturacion_bridge.base_url'));
        $baseUrl = rtrim($baseUrl, '/');

        if ($baseUrl === '') {
            throw new \RuntimeException('No se configuro FACTURACION_BRIDGE_BASE_URL.');
        }

        if (str_ends_with($baseUrl, '/emitir')) {
            $baseUrl = substr($baseUrl, 0, -strlen('/emitir'));
        }

        return rtrim($baseUrl, '/');
    }
}

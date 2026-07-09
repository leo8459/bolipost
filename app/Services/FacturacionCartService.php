<?php

namespace App\Services;

use App\Models\ConceptoFacturacion;
use App\Models\PaqueteCerti;
use App\Models\PaqueteInt;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use App\Models\Servicio;
use App\Models\SolicitudCliente;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacturacionCartService
{
    private const EMS_SOLICITUD_FISCAL_DATA = [
        'actividad_economica' => '841001',
        'codigo_sin' => '99100',
        'unidad_medida' => 58,
        'descripcion_servicio' => 'Envios de paqueteria',
    ];

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
        $payload = array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        );
        Log::info('FacturacionCartService abrirCaja request', [
            'user_id' => $user->id,
            'payload' => $payload,
        ]);
        $body = $this->request('POST', '/caja/abrir', $payload);
        Log::info('FacturacionCartService abrirCaja response', [
            'user_id' => $user->id,
            'body' => $body,
        ]);

        return [
            'estado' => strtoupper(trim((string) (data_get($body, 'estado') ?? data_get($body, 'data.estado') ?? 'ABIERTA'))),
            'mensaje' => trim((string) (data_get($body, 'mensaje') ?? data_get($body, 'message') ?? data_get($body, 'data.mensaje') ?? 'Caja abierta correctamente.')),
            'caja' => (array) (data_get($body, 'caja') ?? data_get($body, 'data.caja') ?? []),
        ];
    }

    public function cerrarCaja(User $user, float $montoCierreDeclarado): array
    {
        $body = $this->request('POST', '/caja/cerrar', array_merge(
            [
                'monto_cierre_declarado' => round($montoCierreDeclarado, 2),
            ],
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

    public function updateDraftBillingData(User $user, array $payload, ?int $cartId = null): ?object
    {
        $payload = $this->withMotivoFromCanalEmision($payload);
        if ($cartId !== null && $cartId > 0) {
            $payload['cart_id'] = $cartId;
        }

        $body = $this->request('PUT', '/cart/billing', array_merge(
            $payload,
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        ));

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart && !data_get($body, 'draft_missing', false)) {
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
        $resumenOrigen = $this->buildPaqueteEmsResumenOrigen($paquete, $servicio);
        $payload = array_merge(
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
            'resumen_origen' => $resumenOrigen,
            'cantidad' => 1,
            'monto_base' => $montoBase,
            'monto_extras' => 0,
            'total_linea' => $montoBase,
        ]);

        $body = $this->request('POST', '/cart/items/upsert', $payload);

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo guardar item remoto.');
        }
        return $cart;
    }

    public function registerPaqueteEmsOficial(User $user, PaqueteEms $paquete): array
    {
        $this->assertFacturacionPermission($user);

        $user->loadMissing('sucursal');
        $paquete->loadMissing(['tarifario.servicio']);
        $servicioEms = optional(optional($paquete->tarifario)->servicio);
        $servicio = $this->resolveFiscalServicio(
            $servicioEms instanceof Servicio ? $servicioEms : null,
            $this->resolveModuloServicio('EMS')
        );
        if (!$servicio) {
            throw new \RuntimeException('No se encontro un servicio fiscal para registrar la venta OFICIAL.');
        }

        $resumenOrigen = $this->buildPaqueteEmsResumenOrigen($paquete, $servicio);
        $fallbackEmail = trim((string) config('services.facturacion_bridge.fallback_email', 'sincorreo@agbc.bo'));
        if ($fallbackEmail === '' || !filter_var($fallbackEmail, FILTER_VALIDATE_EMAIL)) {
            $fallbackEmail = 'sincorreo@agbc.bo';
        }
        $telefonoSucursal = preg_replace('/\D+/', '', (string) ($user->sucursal?->telefono ?? '')) ?? '';
        if (strlen($telefonoSucursal) > 8) {
            $telefonoSucursal = substr($telefonoSucursal, 0, 8);
        }
        if (strlen($telefonoSucursal) < 7) {
            $telefonoSucursal = '2222222';
        }

        $payload = array_merge(
            [
                'origenVenta' => [
                    'id' => (string) $paquete->id,
                    'tipo' => PaqueteEms::class,
                ],
                'origenUsuario' => [
                    'id' => (string) $user->id,
                    'nombre' => (string) ($user->name ?? ''),
                    'email' => (string) ($user->email ?? ''),
                    'alias' => (string) ($user->alias ?? ''),
                    'carnet' => (string) ($user->ci ?? ''),
                ],
                'origenSucursal' => [
                    'id' => (string) $user->sucursal?->puntoVenta,
                    'codigo' => (string) $user->sucursal?->codigoSucursal,
                    'nombre' => (string) ($user->sucursal?->nombre ?? $user->sucursal?->descripcion ?? $user->sucursal?->municipio ?? ''),
                ],
                'municipio' => (string) ($user->sucursal?->municipio ?? 'LA PAZ'),
                'telefono' => $telefonoSucursal,
                'documentoSector' => (int) config('services.facturacion_bridge.documento_sector', 1),
                'codigoCliente' => null,
                'razonSocial' => 'ENVIO OFICIAL',
                'documentoIdentidad' => null,
                'tipoDocumentoIdentidad' => null,
                'correo' => null,
                'metodoPago' => null,
                'formatoFactura' => null,
                'montoTotal' => 0,
                'detalle' => [[
                    'actividadEconomica' => (string) ($resumenOrigen['actividad_economica'] ?? ''),
                    'codigoSin' => (string) ($resumenOrigen['codigo_sin'] ?? ''),
                    'codigo' => (string) ($resumenOrigen['codigo'] ?? ($paquete->codigo ?? '')),
                    'descripcion' => (string) ($resumenOrigen['descripcion_servicio'] ?? 'Envio oficial'),
                    'unidadMedida' => (int) ($resumenOrigen['unidad_medida'] ?? 58),
                    'precioUnitario' => 0,
                    'cantidad' => 1,
                ]],
            ],
            $this->originSucursalPayload($user)
        );

        $this->assertOfficialRegistrationPayload($payload);

        $body = $this->request('POST', '/registrar-oficial', $payload);

        if (!(bool) data_get($body, 'ok')) {
            throw new \RuntimeException((string) (data_get($body, 'message') ?: 'No se pudo registrar la venta OFICIAL remota.'));
        }

        return $body;
    }

    private function buildPaqueteEmsResumenOrigen(PaqueteEms $paquete, ?Servicio $servicio): array
    {
        return [
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
            'unidad_medida' => (int) ($servicio->unidadMedida ?? 0),
        ];
    }

    private function assertOfficialRegistrationPayload(array $payload): void
    {
        $required = [
            'origenVenta.id',
            'origenVenta.tipo',
            'origenUsuario.id',
            'origenSucursal.id',
            'origenSucursal.codigo',
            'codigoSucursal',
            'puntoVenta',
            'documentoSector',
        ];

        foreach ($required as $key) {
            $value = data_get($payload, $key);
            if ($value === null || (is_string($value) && trim($value) === '')) {
                throw new \RuntimeException('Falta el dato obligatorio para venta OFICIAL: ' . $key . '.');
            }
        }

        $detalle = data_get($payload, 'detalle.0');
        if (!is_array($detalle)) {
            throw new \RuntimeException('La venta OFICIAL requiere al menos una linea de detalle.');
        }

        $cantidad = $detalle['cantidad'] ?? null;
        if ($cantidad === null || !is_numeric($cantidad) || (float) $cantidad <= 0) {
            throw new \RuntimeException('La venta OFICIAL requiere una cantidad valida en el detalle.');
        }
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

    public function addPaqueteInt(User $user, PaqueteInt $paquete): object
    {
        $this->assertFacturacionPermission($user);

        $paquete->loadMissing('servicio');
        $servicioInternacional = $this->resolveServicioInternacional();
        $servicioFiscal = $this->resolveFiscalServicio($servicioInternacional, $paquete->servicio, $this->resolveAnyServicioWithFiscalData());
        $servicioPresentacion = $servicioInternacional ?: $paquete->servicio ?: $servicioFiscal;
        $peso = $this->toFloatNumber($paquete->peso ?? 0);
        $montoBase = round($this->toFloatNumber($paquete->precio ?? 0), 2);
        $codigo = (string) ($paquete->codigo ?? '');
        if (trim($codigo) === '') {
            $codigo = (string) ($paquete->cod_especial ?? '');
        }
        $tituloServicio = (string) (
            $servicioPresentacion->nombre_servicio
            ?? $servicioFiscal->nombre_servicio
            ?? 'INTERNACIONAL'
        );
        $descripcionServicio = (string) (
            $servicioPresentacion->descripcion
            ?? $servicioFiscal->descripcion
            ?? $servicioPresentacion->nombre_servicio
            ?? $servicioFiscal->nombre_servicio
            ?? 'ENVIOS DE PAQUETERIA INTERNACIONAL'
        );

        $payload = array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user),
            [
                'origen_tipo' => PaqueteInt::class,
                'origen_id' => (int) $paquete->id,
                'codigo' => $codigo,
                'titulo' => $tituloServicio,
                'nombre_servicio' => $tituloServicio,
                'nombre_destinatario' => (string) ($paquete->destino ?? ''),
                'servicios_extra' => [],
                'resumen_origen' => [
                    'codigo' => $codigo,
                    'contenido' => 'INTERNO',
                    'peso' => $peso,
                    'destinatario' => (string) ($paquete->destino ?? ''),
                    'direccion' => (string) ($paquete->destino ?? ''),
                    'ciudad' => (string) ($paquete->destino ?? ''),
                    'actividad_economica' => (string) ($servicioFiscal->actividadEconomica ?? $servicioPresentacion->actividadEconomica ?? ''),
                    'codigo_sin' => (string) ($servicioFiscal->codigoSin ?? $servicioPresentacion->codigoSin ?? ''),
                    'codigo_producto' => (string) ($servicioFiscal->codigo ?? $servicioPresentacion->codigo ?? $codigo),
                    'descripcion_servicio' => $descripcionServicio,
                    'unidad_medida' => $servicioFiscal->unidadMedida ?? $servicioPresentacion->unidadMedida ?? 58,
                ],
                'cantidad' => 1,
                'monto_base' => $montoBase,
                'monto_extras' => 0,
                'total_linea' => $montoBase,
            ]
        );

        $body = $this->request('POST', '/cart/items/upsert', $payload);

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo guardar item remoto.');
        }

        return $cart;
    }

    public function addPaqueteContrato(User $user, Recojo $paquete): object
    {
        $this->assertFacturacionPermission($user);

        $servicio = $this->resolveFiscalServicio(
            $this->resolveModuloServicio('CONTRATOS'),
            $this->resolveModuloServicio('ORDINARIAS'),
            $this->resolveModuloServicio('CERTIFICADAS')
        );
        $montoBase = round($this->toFloatNumber($paquete->precio ?? 0), 2);
        $peso = $this->toFloatNumber($paquete->peso ?? 0);

        $body = $this->request('POST', '/cart/items/upsert', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user),
            [
                'origen_tipo' => Recojo::class,
                'origen_id' => (int) $paquete->id,
                'codigo' => (string) ($paquete->codigo ?? ''),
                'titulo' => (string) ($servicio->nombre_servicio ?? 'Paquete contrato'),
                'nombre_servicio' => (string) ($servicio->nombre_servicio ?? 'PAQUETE CONTRATO'),
                'nombre_destinatario' => (string) ($paquete->nombre_d ?? ''),
                'servicios_extra' => [],
                'resumen_origen' => [
                    'codigo' => (string) ($paquete->codigo ?? ''),
                    'contenido' => (string) ($paquete->contenido ?? 'CONTRATO'),
                    'peso' => $peso,
                    'destinatario' => (string) ($paquete->nombre_d ?? ''),
                    'direccion' => (string) ($paquete->direccion_d ?? ''),
                    'ciudad' => (string) ($paquete->destino ?? ''),
                    'actividad_economica' => (string) ($servicio->actividadEconomica ?? ''),
                    'codigo_sin' => (string) ($servicio->codigoSin ?? ''),
                    'codigo_producto' => (string) ($servicio->codigo ?? ($paquete->codigo ?? '')),
                    'descripcion_servicio' => (string) ($servicio->descripcion ?? $servicio->nombre_servicio ?? 'PAQUETE CONTRATO'),
                    'unidad_medida' => $servicio->unidadMedida ?? 58,
                ],
                'cantidad' => max(1, (int) ($paquete->cantidad ?? 1)),
                'monto_base' => $montoBase,
                'monto_extras' => 0,
                'total_linea' => $montoBase,
            ]
        ));

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo guardar item remoto.');
        }

        return $cart;
    }

    public function addConceptoFacturacion(User $user, ConceptoFacturacion $concepto): object
    {
        $this->assertFacturacionPermission($user);

        $ctx = $this->getRemoteContextForUser($user);
        $draft = $ctx['draft'] ?? null;
        $existingItem = $this->findEquivalentConceptoDraftItem($draft, $concepto);

        if ($existingItem) {
            $cantidadActual = max(1, (int) data_get($existingItem, 'cantidad', 1));
            $montoBase = round((float) data_get($existingItem, 'monto_base', $concepto->precio_base ?? 0), 2);
            $montoExtras = round((float) data_get($existingItem, 'monto_extras', 0), 2);
            $nuevaCantidad = $cantidadActual + 1;

            return $this->updateDraftItem(
                $user,
                (int) data_get($existingItem, 'id'),
                $this->buildDraftItemUpdatePayload(
                    $existingItem,
                    [
                        'cantidad' => $nuevaCantidad,
                        'monto_base' => $montoBase,
                        'monto_extras' => $montoExtras,
                        'total_linea' => round(($montoBase + $montoExtras) * $nuevaCantidad, 2),
                    ]
                )
            );
        }

        $body = $this->request('POST', '/cart/items/upsert', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user),
            $this->buildConceptoDraftPayload(
                $concepto,
                $this->resolveConceptoDraftOriginId($draft, $concepto)
            )
        ));

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo guardar el concepto facturable en el carrito.');
        }

        return $cart;
    }

    public function addScannedItemByCode(User $user, string $codigo): array
    {
        $this->assertFacturacionPermission($user);

        $codigoNormalizado = strtoupper(trim($codigo));
        if ($codigoNormalizado === '') {
            throw new \RuntimeException('Ingresa un codigo valido para escanear.');
        }

        $matches = collect();

        $contrato = Recojo::query()
            ->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigoNormalizado])
            ->first();
        if ($contrato) {
            $matches->push([
                'type' => 'contrato',
                'label' => 'Paquete Contrato',
                'record' => $contrato,
            ]);
        }

        $ordinario = PaqueteOrdi::query()
            ->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigoNormalizado])
            ->first();
        if ($ordinario) {
            $matches->push([
                'type' => 'ordinario',
                'label' => 'Paquete Ordinario',
                'record' => $ordinario,
            ]);
        }

        $certificado = PaqueteCerti::query()
            ->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigoNormalizado])
            ->first();
        if ($certificado) {
            $matches->push([
                'type' => 'certificado',
                'label' => 'Paquete Certificado',
                'record' => $certificado,
            ]);
        }

        $interno = PaqueteInt::query()
            ->where(function ($query) use ($codigoNormalizado) {
                $query->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigoNormalizado])
                    ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigoNormalizado]);
            })
            ->first();
        if ($interno) {
            $matches->push([
                'type' => 'interno',
                'label' => 'Paquete Interno',
                'record' => $interno,
            ]);
        }

        $ems = PaqueteEms::query()
            ->where(function ($query) use ($codigoNormalizado) {
                $query->whereRaw('trim(upper(codigo)) = trim(upper(?))', [$codigoNormalizado])
                    ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigoNormalizado]);
            })
            ->first();
        if ($ems) {
            $matches->push([
                'type' => 'ems',
                'label' => 'Paquete EMS',
                'record' => $ems,
            ]);
        }

        $solicitudEms = SolicitudCliente::query()
            ->where(function ($query) use ($codigoNormalizado) {
                $query->whereRaw('trim(upper(COALESCE(codigo_solicitud, \'\'))) = trim(upper(?))', [$codigoNormalizado])
                    ->orWhereRaw('trim(upper(COALESCE(barcode, \'\'))) = trim(upper(?))', [$codigoNormalizado])
                    ->orWhereRaw('trim(upper(COALESCE(cod_especial, \'\'))) = trim(upper(?))', [$codigoNormalizado]);
            })
            ->first();
        if ($solicitudEms) {
            $matches->push([
                'type' => 'solicitud_ems',
                'label' => 'Solicitud EMS',
                'record' => $solicitudEms,
            ]);
        }

        if ($matches->isEmpty()) {
            throw new \RuntimeException('No se encontro ningun paquete de Contratos, Ordinarios, Certificados, Internos, EMS o Solicitudes EMS con ese codigo.');
        }

        if ($matches->count() > 1) {
            $labels = $matches->pluck('label')->implode(', ');
            throw new \RuntimeException('El codigo existe en varios modulos (' . $labels . '). Revisa el registro antes de agregarlo al carrito.');
        }

        $match = $matches->first();
        $record = $match['record'];

        $cart = match ($match['type']) {
            'contrato' => $this->addPaqueteContrato($user, $record),
            'ordinario' => $this->addPaqueteOrdi($user, $record),
            'certificado' => $this->addPaqueteCerti($user, $record),
            'interno' => $this->addPaqueteInt($user, $record),
            'ems' => $this->addPaqueteEms($user, $record),
            'solicitud_ems' => $this->addSolicitudEms($user, $record),
            default => throw new \RuntimeException('El codigo escaneado no tiene un modulo compatible con Facturacion.'),
        };

        return [
            'cart' => $cart,
            'item' => [
                'type' => (string) $match['type'],
                'label' => (string) $match['label'],
                'code' => (string) ($record->codigo ?? $codigoNormalizado),
            ],
        ];
    }

    public function addSolicitudEms(User $user, SolicitudCliente $solicitud): object
    {
        $this->assertFacturacionPermission($user);

        $solicitud->loadMissing(['servicioExtra', 'tarifarioTiktoker.servicioExtra']);
        $montoBase = round((float) ($solicitud->precio ?? 0), 2);
        $nombreServicio = trim((string) (
            $solicitud->servicioExtra->nombre
            ?? optional($solicitud->tarifarioTiktoker)->servicioExtra->nombre
            ?? 'EMS'
        ));
        $fiscalData = $this->resolveSolicitudEmsFiscalData($nombreServicio);

        $body = $this->request('POST', '/cart/items/upsert', array_merge(
            $this->originUserPayload($user),
            $this->originSucursalPayload($user),
            [
                'origen_tipo' => SolicitudCliente::class,
                'origen_id' => (int) $solicitud->id,
                'codigo' => (string) ($solicitud->codigo_solicitud ?? ''),
                'titulo' => 'Solicitud EMS',
                'nombre_servicio' => $nombreServicio,
                'nombre_destinatario' => (string) ($solicitud->nombre_destinatario ?? ''),
                'servicios_extra' => [],
                'resumen_origen' => [
                    'codigo' => (string) ($solicitud->codigo_solicitud ?? ''),
                    'contenido' => (string) ($solicitud->contenido ?? ''),
                    'peso' => (float) ($solicitud->peso ?? 0),
                    'destinatario' => (string) ($solicitud->nombre_destinatario ?? ''),
                    'direccion' => (string) ($solicitud->direccion ?? ''),
                    'ciudad' => (string) ($solicitud->ciudad ?? ''),
                    'actividad_economica' => $fiscalData['actividad_economica'],
                    'codigo_sin' => $fiscalData['codigo_sin'],
                    'codigo_producto' => $fiscalData['codigo_producto'],
                    'descripcion_servicio' => $fiscalData['descripcion_servicio'],
                    'unidad_medida' => $fiscalData['unidad_medida'],
                ],
                'cantidad' => max(1, (int) ($solicitud->cantidad ?? 1)),
                'monto_base' => $montoBase,
                'monto_extras' => 0,
                'total_linea' => $montoBase,
            ]
        ));

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException('No se pudo guardar la solicitud EMS en facturacion.');
        }

        return $cart;
    }

    private function resolveSolicitudEmsFiscalData(?string $nombreServicioExtra): array
    {
        $nombre = strtoupper(trim((string) $nombreServicioExtra));
        $codigoProducto = 'SRVE-01';

        if (str_contains($nombre, 'VENTANILLA A VENTANILLA')) {
            $codigoProducto = 'SRVE-02';
        }

        return array_merge(self::EMS_SOLICITUD_FISCAL_DATA, [
            'codigo_producto' => $codigoProducto,
        ]);
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

    public function emitirBorrador(User $user, array $overrides = [], ?int $cartId = null): array
    {
        if ($cartId !== null && $cartId > 0) {
            $targetCart = $this->fetchVentaById($user, $cartId);
            $this->ensureDraftItemsFiscalDataSynced($user, $targetCart);
            $this->ensureDraftItemCodesUnique($user, $targetCart);
        } else {
            $ctx = $this->getRemoteContextForUser($user);
            $this->ensureDraftItemsFiscalDataSynced($user, $ctx['draft'] ?? null);
            $this->ensureDraftItemCodesUnique($user, $ctx['draft'] ?? null);
            $this->ensureDraftSucursalSynced($user);
        }

        if ($cartId !== null && $cartId > 0) {
            $overrides['cart_id'] = $cartId;
        }

        $body = $this->request('POST', '/cart/emitir', array_merge(
            $overrides,
            $this->originUserPayload($user),
            $this->originSucursalPayload($user)
        ));
        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$cart) {
            throw new \RuntimeException((string) data_get($body, 'respuesta.mensaje', 'No se pudo emitir.'));
        }
        if ($cartId !== null && $cartId > 0 && (int) ($cart->id ?? 0) !== $cartId) {
            throw new \RuntimeException('La API de facturacion devolvio una venta distinta a la solicitada para emitir.');
        }
        return ['carrito' => $cart, 'payload' => [], 'respuesta' => (array) data_get($body, 'respuesta', [])];
    }

    public function consultarEstadoEmision(
        User $user,
        ?int $cartId = null,
        bool $autoEmitInvoice = false,
        bool $allowPendingRetry = true
    ): array
    {
        $payload = [
            'origen_usuario_id' => (string) $user->id,
            'cart_id' => $cartId,
            'auto_emit_invoice' => $autoEmitInvoice,
        ];

        $body = $this->request('POST', '/cart/consultar', $payload);
        [$body, $cart] = $this->retryPendingQrConsultIfNeeded($payload, $body, $allowPendingRetry);

        if (!$cart) {
            $cart = $this->toCart(data_get($body, 'cart'));
        }

        if (!$cart) {
            throw new \RuntimeException((string) data_get($body, 'respuesta.mensaje', 'No se pudo consultar.'));
        }

        $respuesta = (array) data_get($body, 'respuesta', []);

        if ($this->shouldAutoEmitPaidQrInvoice($cart, $respuesta, $autoEmitInvoice)) {
            $attemptStatus = $this->resolveAutoEmitAttemptStatus($cart);
            if ($attemptStatus === 'cooldown' || $attemptStatus === 'locked') {
                $respuesta['auto_factura_pending'] = true;

                return ['carrito' => $cart, 'respuesta' => $respuesta];
            }

            try {
                $this->markAutoEmitAttemptCooldown($cart, 120);
                $invoiceResult = $this->emitFacturaForPaidQrCart($user, $cart);
                $this->markAutoEmitAttemptCooldown($invoiceResult['carrito'] ?? $cart, 300);

                return [
                    'carrito' => $invoiceResult['carrito'],
                    'respuesta' => (array) ($invoiceResult['respuesta'] ?? []),
                ];
            } catch (\Throwable $e) {
                Log::warning('No se pudo emitir automaticamente la factura despues del pago QR.', [
                    'user_id' => $user->id,
                    'cart_id' => $cart->id ?? null,
                    'codigo_orden' => $cart->codigo_orden ?? null,
                    'message' => $e->getMessage(),
                ]);

                $respuesta['auto_factura_error'] = trim($e->getMessage());
            } finally {
                $this->releaseAutoEmitLock($cart);
            }
        }

        return ['carrito' => $cart, 'respuesta' => $respuesta];
    }

    private function retryPendingQrConsultIfNeeded(array $payload, array $body, bool $allowPendingRetry = true): array
    {
        if (!$allowPendingRetry) {
            return [$body, $this->toCart(data_get($body, 'cart'))];
        }

        $cart = $this->toCart(data_get($body, 'cart'));
        if (!$this->shouldRetryPendingQrConsult($cart, (array) data_get($body, 'respuesta', []))) {
            return [$body, $cart];
        }

        $delaySeconds = $this->pendingQrConsultDelaySeconds($cart);
        if ($delaySeconds <= 0) {
            return [$body, $cart];
        }

        sleep($delaySeconds);

        $retriedBody = $this->request('POST', '/cart/consultar', $payload);
        return [$retriedBody, $this->toCart(data_get($retriedBody, 'cart'))];
    }

    private function shouldRetryPendingQrConsult(?object $cart, array $respuesta): bool
    {
        if (!$cart) {
            return false;
        }

        $canalEmision = strtolower(trim((string) ($cart->canal_emision ?? '')));
        $estadoCart = strtolower(trim((string) ($cart->estado ?? '')));
        $estadoPago = strtolower(trim((string) ($cart->estado_pago ?? data_get($respuesta, 'estado_pago', ''))));
        $paymentStatus = strtolower(trim((string) (
            data_get($respuesta, 'payment_status')
            ?? data_get($respuesta, 'items.0.payment_status')
            ?? ''
        )));
        $transactionId = trim((string) ($cart->qr_transaction_id ?? ''));

        if ($canalEmision !== 'qr' || $transactionId === '') {
            return false;
        }

        if (in_array($estadoPago, ['pagado', 'cancelado'], true)) {
            return false;
        }

        if ($estadoCart !== 'pendiente_pago') {
            return false;
        }

        return in_array($paymentStatus, ['', 'holding', 'pending', 'pendiente'], true);
    }

    private function shouldAutoEmitPaidQrInvoice(?object $cart, array $respuesta, bool $autoEmitInvoice): bool
    {
        if (!$autoEmitInvoice || !$cart) {
            return false;
        }

        $canalEmision = strtolower(trim((string) ($cart->canal_emision ?? '')));
        $estadoEmision = strtoupper(trim((string) ($cart->estado_emision ?? '')));
        $estadoPago = strtolower(trim((string) (
            $cart->estado_pago
            ?? data_get($respuesta, 'estado_pago')
            ?? data_get($respuesta, 'payment_status')
            ?? data_get($respuesta, 'items.0.payment_status')
            ?? ''
        )));

        if ($canalEmision !== 'qr') {
            return false;
        }

        if (!in_array($estadoPago, ['pagado', 'success', 'paid', 'completed', 'approved', 'confirmed'], true)) {
            return false;
        }

        $codigoSeguimientoFiscal = trim((string) (
            $cart->codigo_seguimiento_fiscal
            ?? $cart->codigo_seguimiento
            ?? data_get($respuesta, 'codigoSeguimiento')
            ?? ''
        ));
        $numeroFactura = trim((string) (
            data_get($cart, 'respuesta_emision.factura.nroFactura')
            ?? data_get($cart, 'respuesta_emision.factura.numeroFactura')
            ?? data_get($respuesta, 'factura.nroFactura')
            ?? data_get($respuesta, 'factura.numeroFactura')
            ?? data_get($respuesta, 'nroFactura')
            ?? data_get($respuesta, 'numeroFactura')
            ?? ''
        ));

        if ($codigoSeguimientoFiscal !== '' || $numeroFactura !== '') {
            return false;
        }

        return in_array($estadoEmision, ['', 'NO_APLICA'], true);
    }

    private function resolveAutoEmitAttemptStatus(object $cart): string
    {
        $cartId = (int) ($cart->id ?? 0);
        if ($cartId <= 0) {
            return 'invalid';
        }

        if (Cache::has($this->autoEmitCooldownKey($cartId))) {
            return 'cooldown';
        }

        $lock = Cache::lock($this->autoEmitLockKey($cartId), 30);
        if (!$lock->get()) {
            return 'locked';
        }

        return 'acquired';
    }

    private function releaseAutoEmitLock(object $cart): void
    {
        $cartId = (int) ($cart->id ?? 0);
        if ($cartId <= 0) {
            return;
        }

        try {
            Cache::lock($this->autoEmitLockKey($cartId), 30)->forceRelease();
        } catch (\Throwable) {
            // Si el driver no soporta locks distribuidos, no bloqueamos el flujo.
        }
    }

    private function markAutoEmitAttemptCooldown(object $cart, int $seconds): void
    {
        $cartId = (int) ($cart->id ?? 0);
        if ($cartId <= 0 || $seconds <= 0) {
            return;
        }

        Cache::put($this->autoEmitCooldownKey($cartId), now()->timestamp, now()->addSeconds($seconds));
    }

    private function autoEmitLockKey(int $cartId): string
    {
        return 'facturacion:qr:auto-emit:lock:' . $cartId;
    }

    private function autoEmitCooldownKey(int $cartId): string
    {
        return 'facturacion:qr:auto-emit:cooldown:' . $cartId;
    }

    private function emitFacturaForPaidQrCart(User $user, object $cart): array
    {
        $targetCartId = (int) ($cart->id ?? 0);
        if ($targetCartId <= 0) {
            throw new \RuntimeException('La venta QR pagada no tiene cart_id valido para facturar automaticamente.');
        }

        $targetCart = $this->fetchVentaById($user, $targetCartId);
        if (!$targetCart) {
            throw new \RuntimeException('La venta QR pagada ya no se encontro en el bridge de facturacion.');
        }

        if (strtolower(trim((string) ($targetCart->metodo_pago ?? ''))) !== 'qr') {
            throw new \RuntimeException('La venta pagada ya no figura como cobro QR en el bridge.');
        }

        $billingSnapshot = $this->buildFacturaElectronicaBillingSnapshot($targetCart);

        Log::debug('Pago QR confirmado; iniciando emision automatica de factura electronica.', [
            'user_id' => $user->id,
            'cart_id' => $targetCart->id ?? null,
            'codigo_orden' => $targetCart->codigo_orden ?? null,
        ]);

        return $this->emitirBorrador($user, $billingSnapshot, $targetCartId);
    }

    private function buildFacturaElectronicaBillingSnapshot(object $cart): array
    {
        return array_filter([
            'modalidad_facturacion' => (string) ($cart->modalidad_facturacion ?? 'con_datos'),
            'canal_emision' => 'factura_electronica',
            'tipo_documento' => $this->nullableString($cart->tipo_documento ?? null),
            'numero_documento' => $this->nullableString($cart->numero_documento ?? null),
            'complemento_documento' => $this->nullableString($cart->complemento_documento ?? null),
            'razon_social' => $this->nullableString($cart->razon_social ?? null),
            'correo_facturacion' => $this->nullableString($cart->correo_facturacion ?? ($cart->correo ?? null)),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function pendingQrConsultDelaySeconds(object $cart): int
    {
        $updatedAtRaw = trim((string) ($cart->updated_at ?? ''));
        if ($updatedAtRaw === '') {
            return 0;
        }

        try {
            $updatedAt = Carbon::parse($updatedAtRaw);
        } catch (\Throwable) {
            return 0;
        }

        $ageSeconds = $updatedAt->diffInSeconds(now());
        if ($ageSeconds >= 15) {
            return 0;
        }

        return max(1, 16 - $ageSeconds);
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

        try {
            $body = $this->request('GET', '/ventas/reportes/kardex-usuarios', $payload);
        } catch (\RuntimeException $e) {
            if (!str_starts_with($e->getMessage(), '404')) {
                throw $e;
            }

            // Compatibilidad con APIs que exponen el reporte sin el prefijo /ventas.
            $body = $this->request('GET', '/reportes/kardex-usuarios', $payload);
        }

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
            ->timeout((int) config('services.facturacion_bridge.timeout', 30))
            ->withOptions(['verify' => config('services.facturacion_bridge.ssl_verify', true)]);

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
        $timeout = (int) config('services.facturacion_bridge.timeout', 30);
        $connectTimeout = (int) config('services.facturacion_bridge.connect_timeout', min(10, max(3, $timeout)));

        $client = Http::baseUrl($this->resolveBaseUrl())
            ->withToken((string) config('services.facturacion_bridge.token'))
            ->acceptJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->withOptions(['verify' => config('services.facturacion_bridge.ssl_verify', true)]);

        try {
            $response = match (strtoupper($method)) {
                'GET' => $client->get($path, $payload),
                'PUT' => $client->put($path, $payload),
                'DELETE' => $client->send('DELETE', $path, ['json' => $payload]),
                default => $client->post($path, $payload),
            };
        } catch (ConnectionException $e) {
            throw new \RuntimeException($this->connectionFailureMessage($e, $path, $timeout), 0, $e);
        }

        try {
            $response->throw();
        } catch (RequestException $e) {
            $body = $this->decodeJsonBody($response);
            Log::warning('FacturacionCartService request failed', [
                'method' => strtoupper($method),
                'path' => $path,
                'payload' => $payload,
                'status' => $response->status(),
                'body' => $body ?: trim((string) $response->body()),
            ]);
            $msg = is_array($body)
                ? (string) ($body['message'] ?? $body['mensaje'] ?? $this->firstValidationError($body) ?? 'Error remoto')
                : trim((string) $response->body());
            if ($msg === '') {
                $msg = (string) $e->getMessage();
            }
            throw new \RuntimeException($response->status() . ' ' . $msg, 0, $e);
        }

        if ($response->status() === 204) {
            return [];
        }

        $body = $this->decodeJsonBody($response);
        if (is_array($body)) {
            return $body;
        }

        $rawBody = trim((string) $response->body());
        if ($rawBody === '') {
            return [];
        }

        $contentType = (string) ($response->header('Content-Type') ?? 'desconocido');
        $snippet = mb_substr($rawBody, 0, 240);
        throw new \RuntimeException('Respuesta no valida de API facturacion. status=' . $response->status() . ' content_type=' . $contentType . ' body=' . $snippet);
    }

    private function connectionFailureMessage(ConnectionException $e, string $path, int $timeout): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'cURL error 28') || str_contains(strtolower($message), 'timed out')) {
            if ($path === '/cart/emitir') {
                return 'La API de facturacion no respondio en ' . $timeout . ' segundos. La emision pudo haber quedado en proceso; espera un momento y usa Consultar estado antes de volver a emitir.';
            }

            return 'La API de facturacion no respondio en ' . $timeout . ' segundos. Intenta nuevamente en unos momentos.';
        }

        if (str_contains($message, 'cURL error 7')) {
            return 'No se pudo conectar con la API de facturacion. Verifica que el servicio local este levantado en FACTURACION_BRIDGE_BASE_URL.';
        }

        if (str_contains($message, 'cURL error 35') || str_contains(strtolower($message), 'connection was reset')) {
            return 'La conexion con la API de facturacion fue cerrada antes de responder. Intenta consultar el estado de la factura o reintenta cuando el servicio este estable.';
        }

        return 'No se pudo conectar con la API de facturacion: ' . $message;
    }

    private function decodeJsonBody($response): ?array
    {
        $raw = (string) $response->body();
        if (trim($raw) === '') {
            return null;
        }

        $rawWithoutBom = preg_replace('/^\xEF\xBB\xBF/', '', ltrim($raw)) ?? $raw;
        $decoded = json_decode($rawWithoutBom, true);
        return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : null;
    }

    private function firstValidationError(array $body): ?string
    {
        $errors = $body['errors'] ?? null;
        if (!is_array($errors)) {
            return null;
        }

        foreach ($errors as $messages) {
            if (is_array($messages) && isset($messages[0]) && is_string($messages[0])) {
                return $messages[0];
            }
        }

        return null;
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

    private function buildConceptoDraftPayload(ConceptoFacturacion $concepto, ?int $originId = null): array
    {
        $montoBase = round((float) ($concepto->precio_base ?? 0), 2);
        $resolvedOriginId = $originId !== null && $originId > 0
            ? $originId
            : (int) $concepto->id;

        return [
            'origen_tipo' => ConceptoFacturacion::class,
            'origen_id' => $resolvedOriginId,
            'codigo' => (string) ($concepto->codigo ?? ''),
            'titulo' => (string) ($concepto->nombre ?? 'Cobro adicional'),
            'nombre_servicio' => (string) ($concepto->nombre ?? 'COBRO ADICIONAL'),
            'nombre_destinatario' => '',
            'servicios_extra' => [],
            'resumen_origen' => [
                'codigo' => (string) ($concepto->codigo ?? ''),
                'contenido' => 'COBRO ADICIONAL',
                'peso' => 0,
                'destinatario' => '',
                'direccion' => '',
                'ciudad' => '',
                'actividad_economica' => (string) ($concepto->actividad_economica ?? ''),
                'codigo_sin' => (string) ($concepto->codigo_sin ?? ''),
                'codigo_producto' => (string) ($concepto->codigo ?? ''),
                'descripcion_servicio' => (string) ($concepto->descripcion ?? $concepto->nombre ?? 'COBRO ADICIONAL'),
                'unidad_medida' => (int) ($concepto->unidad_medida ?? 58),
                'concepto_facturacion_id' => (int) $concepto->id,
            ],
            'cantidad' => 1,
            'monto_base' => $montoBase,
            'monto_extras' => 0,
            'total_linea' => $montoBase,
        ];
    }

    private function findEquivalentConceptoDraftItem(?object $draft, ConceptoFacturacion $concepto): ?object
    {
        if (!$draft) {
            return null;
        }

        $expectedPayload = $this->buildConceptoDraftPayload($concepto);
        $expectedResumen = $this->normalizeFacturacionResumenOrigenForMatch($expectedPayload['resumen_origen'] ?? []);
        $expectedMontoBase = round((float) ($expectedPayload['monto_base'] ?? 0), 2);

        return collect($draft->items ?? [])
            ->first(function ($item) use ($concepto, $expectedPayload, $expectedResumen, $expectedMontoBase) {
                $itemConceptoId = (int) data_get(
                    $item,
                    'resumen_origen.concepto_facturacion_id',
                    data_get($item, 'origen_id', 0)
                );

                return ltrim((string) data_get($item, 'origen_tipo', ''), '\\') === ltrim(ConceptoFacturacion::class, '\\')
                    && $itemConceptoId === (int) $concepto->id
                    && trim((string) data_get($item, 'codigo', '')) === trim((string) ($expectedPayload['codigo'] ?? ''))
                    && trim((string) data_get($item, 'titulo', '')) === trim((string) ($expectedPayload['titulo'] ?? ''))
                    && trim((string) data_get($item, 'nombre_servicio', '')) === trim((string) ($expectedPayload['nombre_servicio'] ?? ''))
                    && round((float) data_get($item, 'monto_base', 0), 2) === $expectedMontoBase
                    && $this->normalizeFacturacionResumenOrigenForMatch((array) data_get($item, 'resumen_origen', [])) === $expectedResumen;
            });
    }

    private function resolveConceptoDraftOriginId(?object $draft, ConceptoFacturacion $concepto): int
    {
        if (!$draft) {
            return (int) $concepto->id;
        }

        $sameConceptItems = collect($draft->items ?? [])
            ->filter(function ($item) use ($concepto) {
                if (ltrim((string) data_get($item, 'origen_tipo', ''), '\\') !== ltrim(ConceptoFacturacion::class, '\\')) {
                    return false;
                }

                $itemConceptoId = (int) data_get(
                    $item,
                    'resumen_origen.concepto_facturacion_id',
                    data_get($item, 'origen_id', 0)
                );

                return $itemConceptoId === (int) $concepto->id;
            })
            ->values();

        if ($sameConceptItems->isEmpty()) {
            return (int) $concepto->id;
        }

        $maxOriginId = $sameConceptItems
            ->map(fn ($item) => (int) data_get($item, 'origen_id', 0))
            ->max();

        return max((int) $concepto->id, $maxOriginId) + 1;
    }

    private function buildDraftItemUpdatePayload(object $item, array $overrides = []): array
    {
        $resumen = (array) data_get($item, 'resumen_origen', []);
        $base = round((float) ($overrides['monto_base'] ?? data_get($item, 'monto_base', data_get($item, 'precio', 0))), 2);
        $extras = round((float) ($overrides['monto_extras'] ?? data_get($item, 'monto_extras', 0)), 2);
        $cantidad = max(1, (int) ($overrides['cantidad'] ?? data_get($item, 'cantidad', 1)));

        return array_merge([
            'codigo' => trim((string) data_get($item, 'codigo', '')),
            'titulo' => trim((string) data_get($item, 'titulo', '')),
            'nombre_servicio' => trim((string) data_get($item, 'nombre_servicio', '')),
            'nombre_destinatario' => trim((string) data_get($item, 'nombre_destinatario', '')),
            'contenido' => trim((string) ($resumen['contenido'] ?? '')),
            'direccion' => trim((string) ($resumen['direccion'] ?? '')),
            'ciudad' => trim((string) ($resumen['ciudad'] ?? '')),
            'peso' => (float) ($resumen['peso'] ?? 0),
            'precio' => $base,
            'monto_base' => $base,
            'monto_extras' => $extras,
            'cantidad' => $cantidad,
            'total_linea' => round(($base + $extras) * $cantidad, 2),
            'actividad_economica' => trim((string) ($resumen['actividad_economica'] ?? '')),
            'codigo_sin' => trim((string) ($resumen['codigo_sin'] ?? '')),
            'codigo_producto' => trim((string) ($resumen['codigo_producto'] ?? '')),
            'descripcion_servicio' => trim((string) ($resumen['descripcion_servicio'] ?? '')),
            'unidad_medida' => (int) ($resumen['unidad_medida'] ?? 58),
        ], $overrides);
    }

    private function normalizeFacturacionResumenOrigen(array $resumen): array
    {
        $normalized = $resumen;
        ksort($normalized);

        return array_map(function ($value) {
            if (is_numeric($value)) {
                return round((float) $value, 2);
            }

            return is_string($value) ? trim($value) : $value;
        }, $normalized);
    }

    private function normalizeFacturacionResumenOrigenForMatch(array $resumen): array
    {
        $normalized = $this->normalizeFacturacionResumenOrigen($resumen);

        return [
            'codigo' => (string) ($normalized['codigo'] ?? ''),
            'contenido' => (string) ($normalized['contenido'] ?? ''),
            'actividad_economica' => (string) ($normalized['actividad_economica'] ?? ''),
            'codigo_sin' => (string) ($normalized['codigo_sin'] ?? ''),
            'codigo_producto' => (string) ($normalized['codigo_producto'] ?? ''),
            'descripcion_servicio' => (string) ($normalized['descripcion_servicio'] ?? ''),
            'unidad_medida' => (int) ($normalized['unidad_medida'] ?? 0),
        ];
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
        $nombreSucursal = trim((string) ($sucursal->nombre ?? $sucursal->descripcion ?? $sucursal->municipio ?? ''));
        $municipio = trim((string) ($sucursal->municipio ?? ''));
        $departamento = trim((string) ($sucursal->departamento ?? $municipio));

        if ($codigoSucursal === '' || $puntoVenta === '') {
            throw new \RuntimeException('La sucursal asignada no tiene codigoSucursal/puntoVenta validos.');
        }

        return [
            // Claves usadas por el bridge actual
            'origen_sucursal_id' => $puntoVenta,
            'origen_sucursal_codigo' => $codigoSucursal,
            'origen_sucursal_nombre' => $nombreSucursal,
            'origen_sucursal_municipio' => $municipio !== '' ? $municipio : null,
            // Claves requeridas por endpoints de caja en API facturacion
            'codigo_sucursal' => $codigoSucursal,
            'punto_venta' => $puntoVenta,
            'municipio' => $municipio !== '' ? $municipio : null,
            // Compatibilidad adicional por si el backend valida en camelCase
            'codigoSucursal' => $codigoSucursal,
            'puntoVenta' => $puntoVenta,
            'municipioSucursal' => $municipio !== '' ? $municipio : null,
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
        $payload = $this->withMotivoFromCanalEmision($payload);

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

    private function withMotivoFromCanalEmision(array $payload): array
    {
        $canalEmision = strtolower(trim((string) ($payload['canal_emision'] ?? '')));
        if ($canalEmision === '') {
            return $payload;
        }

        if (!in_array($canalEmision, ['factura_electronica', 'qr'], true)) {
            $canalEmision = 'factura_electronica';
        }
        $payload['canal_emision'] = $canalEmision;
        $payload['motivo'] = $canalEmision === 'qr' ? 'qr' : 'factura electronica';

        return $payload;
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

    private function resolveServicioInternacional(): ?Servicio
    {
        return Servicio::query()
            ->where(function ($query) {
                $query->whereRaw('trim(upper(nombre_servicio)) = trim(upper(?))', ['INTERNACIONAL'])
                    ->orWhereRaw('trim(upper(codigo)) = trim(upper(?))', ['SRVI-001']);
            })
            ->first();
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

    private function ensureDraftItemCodesUnique(User $user, ?object $draft): bool
    {
        if (!$draft || !isset($draft->items)) {
            return false;
        }

        $items = collect((array) $draft->items)
            ->filter(fn ($item) => $item && isset($item->id))
            ->values();

        if ($items->count() <= 1) {
            return false;
        }

        $changed = false;

        $items
            ->groupBy(function ($item) {
                $codigo = trim((string) data_get($item, 'codigo', ''));

                return $codigo !== '' ? mb_strtolower($codigo) : '__empty__';
            })
            ->each(function ($group, $normalizedCode) use ($user, &$changed) {
                if ($normalizedCode === '__empty__' || $group->count() <= 1) {
                    return;
                }

                $orderedGroup = $group
                    ->sortBy(fn ($item) => (int) data_get($item, 'id', 0))
                    ->values();

                $baseCode = trim((string) data_get($orderedGroup->first(), 'codigo', ''));
                if ($baseCode === '') {
                    return;
                }

                foreach ($orderedGroup as $index => $item) {
                    $expectedCode = $index === 0
                        ? $baseCode
                        : $this->buildAlternateDraftItemCode($baseCode, $index + 1);
                    $currentCode = trim((string) data_get($item, 'codigo', ''));

                    if ($currentCode === $expectedCode) {
                        continue;
                    }

                    try {
                        $this->updateDraftItem(
                            $user,
                            (int) data_get($item, 'id'),
                            $this->buildDraftItemUpdatePayload($item, [
                                'codigo' => $expectedCode,
                            ])
                        );
                        $changed = true;
                    } catch (\Throwable) {
                        // keep flow resilient; item can still be edited manually in UI
                    }
                }
            });

        return $changed;
    }

    private function buildAlternateDraftItemCode(string $baseCode, int $position): string
    {
        $baseCode = trim($baseCode);
        if ($baseCode === '') {
            return '';
        }

        $suffix = '-' . str_pad((string) max(2, $position), 2, '0', STR_PAD_LEFT);
        $maxBaseLength = max(1, 120 - strlen($suffix));
        $trimmedBase = substr($baseCode, 0, $maxBaseLength);

        return $trimmedBase . $suffix;
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

        if ($origenTipo === ltrim(PaqueteInt::class, '\\')) {
            $paquete = PaqueteInt::query()->with('servicio')->find($origenId);
            return $this->resolveFiscalServicio(
                $paquete?->servicio,
                $this->resolveAnyServicioWithFiscalData()
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

        if ($origenTipo === ltrim(Recojo::class, '\\')) {
            return $this->resolveFiscalServicio(
                $this->resolveModuloServicio('CONTRATOS'),
                $this->resolveModuloServicio('ORDINARIAS'),
                $this->resolveModuloServicio('CERTIFICADAS')
            );
        }

        if ($origenTipo === ltrim(SolicitudCliente::class, '\\')) {
            return $this->resolveFiscalServicio($this->resolveModuloServicio('EMS'));
        }

        if ($origenTipo === ltrim(ConceptoFacturacion::class, '\\')) {
            $conceptoId = (int) data_get($item, 'resumen_origen.concepto_facturacion_id', $origenId);
            $concepto = ConceptoFacturacion::query()->find($conceptoId);
            if (!$concepto) {
                return null;
            }

            return new Servicio([
                'nombre_servicio' => (string) ($concepto->nombre ?? ''),
                'actividadEconomica' => (string) ($concepto->actividad_economica ?? ''),
                'codigoSin' => (string) ($concepto->codigo_sin ?? ''),
                'codigo' => (string) ($concepto->codigo ?? ''),
                'descripcion' => (string) ($concepto->descripcion ?? $concepto->nombre ?? ''),
                'unidadMedida' => (int) ($concepto->unidad_medida ?? 58),
            ]);
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

<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ConceptoFacturacion;
use App\Models\Empresa;
use App\Services\FacturacionCartService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FacturacionServicioController extends Controller
{
    public function index(Request $request, FacturacionCartService $service): View
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        $context = $this->safeRemoteContext($service, $user);
        $caja = $this->safeCajaContext($service, $user);
        $conceptos = ConceptoFacturacion::query()
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereRaw("UPPER(TRIM(COALESCE(nombre, ''))) = ?", ['CONTRATOS'])
                    ->orWhereRaw("UPPER(TRIM(COALESCE(codigo, ''))) = ?", ['SRVE-7']);
            })
            ->orderBy('nombre')
            ->get();
        $empresas = Empresa::query()
            ->leftJoin('clientes as c', function ($join) {
                $join->on(
                    DB::raw("REPLACE(TRIM(UPPER(COALESCE(c.codigo_cliente, ''))), ' ', '')"),
                    '=',
                    DB::raw("REPLACE(TRIM(UPPER(COALESCE(empresa.codigo_cliente, ''))), ' ', '')")
                );
            })
            ->whereRaw("TRIM(COALESCE(empresa.codigo_cliente, '')) <> ?", ['9999'])
            ->orderBy('empresa.codigo_cliente')
            ->orderBy('empresa.nombre')
            ->get([
                'empresa.id',
                'empresa.nombre',
                'empresa.sigla',
                'empresa.codigo_cliente',
                DB::raw('c.id as cliente_id'),
                DB::raw('c.razon_social as cliente_razon_social'),
                DB::raw('c.tipodocumentoidentidad as cliente_tipo_documento'),
                DB::raw('c.numero_carnet as cliente_numero_documento'),
                DB::raw('c.complemento as cliente_complemento'),
                DB::raw('c.email as cliente_email'),
            ]);

        $activeDraft = $context['draft'] ?? null;
        $activeDraftItems = collect($activeDraft?->items ?? []);
        $hasBlockingDraft = $activeDraftItems->isNotEmpty();
        $pendingConceptos = collect(old('conceptos', []))
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) use ($conceptos) {
                $concepto = $conceptos->firstWhere('id', (int) ($item['concepto_facturacion_id'] ?? 0));
                $cantidad = max(1, (int) ($item['cantidad'] ?? 1));
                $precioBase = round((float) ($item['precio'] ?? $concepto?->precio_base ?? 0), 2);
                $descripcion = trim((string) ($item['descripcion'] ?? data_get(
                    $concepto,
                    'descripcion',
                    $concepto?->nombre ?? ''
                )));

                return [
                    'concepto_facturacion_id' => (int) ($item['concepto_facturacion_id'] ?? 0),
                    'cantidad' => $cantidad,
                    'nombre' => $concepto?->nombre ?? 'Concepto no disponible',
                    'codigo' => $concepto?->codigo ?? '',
                    'descripcion' => $descripcion,
                    'precio_base' => $precioBase,
                    'total' => round($precioBase * $cantidad, 2),
                ];
            })
            ->values();

        return view('facturacion.servicio-directo', [
            'conceptos' => $conceptos,
            'empresas' => $empresas,
            'billingDocumentTypes' => Cliente::tiposDocumentoIdentidad(),
            'cajaContext' => $caja,
            'activeDraft' => $activeDraft,
            'hasBlockingDraft' => $hasBlockingDraft,
            'result' => session('facturacion_servicio_result'),
            'pendingConceptos' => $pendingConceptos,
        ]);
    }

    public function store(Request $request, FacturacionCartService $service): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeFacturacionAccess($user);

        $validated = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'conceptos' => ['required', 'array', 'min:1'],
            'conceptos.*.concepto_facturacion_id' => ['required', 'integer', 'exists:conceptos_facturacion,id'],
            'conceptos.*.cantidad' => ['required', 'integer', 'min:1', 'max:999'],
            'conceptos.*.codigo' => ['nullable', 'string', 'max:120'],
            'conceptos.*.descripcion' => ['nullable', 'string', 'max:255'],
            'conceptos.*.precio' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'tipo_documento' => ['required', 'string', 'max:20', Rule::in(array_keys(Cliente::tiposDocumentoIdentidad()))],
            'numero_documento' => ['required', 'string', 'max:80'],
            'complemento_documento' => ['nullable', 'string', 'max:30'],
            'razon_social' => ['required', 'string', 'max:255'],
            'correo_facturacion' => ['nullable', 'email', 'max:50'],
        ]);

        $empresa = Empresa::query()
            ->whereKey((int) $validated['empresa_id'])
            ->firstOrFail();

        $context = $this->safeRemoteContext($service, $user);
        $activeDraftItems = collect(($context['draft'] ?? null)?->items ?? []);
        if ($activeDraftItems->isNotEmpty()) {
            return back()
                ->withInput()
                ->with('facturacion_servicio_result', [
                    'type' => 'warning',
                    'title' => 'Borrador activo detectado',
                    'message' => 'La facturacion por servicio no puede continuar mientras exista un borrador activo en la facturacion principal.',
                    'detail' => 'Emite o limpia primero el borrador de la facturacion actual para evitar mezclar operaciones.',
                ]);
        }

        $conceptosSolicitados = collect($validated['conceptos'] ?? [])
            ->map(function (array $item): array {
                return [
                    'concepto_facturacion_id' => (int) $item['concepto_facturacion_id'],
                    'cantidad' => max(1, (int) $item['cantidad']),
                    'codigo' => trim((string) ($item['codigo'] ?? '')),
                    'descripcion' => trim((string) ($item['descripcion'] ?? '')),
                    'precio' => round((float) ($item['precio'] ?? 0), 2),
                ];
            })
            ->values();

        $conceptos = ConceptoFacturacion::query()
            ->where('activo', true)
            ->whereIn('id', $conceptosSolicitados->pluck('concepto_facturacion_id')->all())
            ->get()
            ->keyBy('id');

        $billingPayload = [
            'modalidad_facturacion' => 'con_datos',
            'canal_emision' => 'factura_electronica',
            'canal_operativo' => 'contrato',
            'contabiliza_en_caja' => false,
            'es_cuenta_por_cobrar' => true,
            'empresa_id' => (int) $empresa->id,
            'empresa_codigo_cliente' => trim((string) ($empresa->codigo_cliente ?? '')),
            'empresa_nombre' => trim((string) ($empresa->nombre ?? '')),
            'empresa_sigla' => trim((string) ($empresa->sigla ?? '')),
            'tipo_documento' => (string) $validated['tipo_documento'],
            'numero_documento' => trim((string) $validated['numero_documento']),
            'complemento_documento' => trim((string) ($validated['complemento_documento'] ?? '')),
            'razon_social' => trim((string) $validated['razon_social']),
            'correo_facturacion' => trim((string) ($validated['correo_facturacion'] ?? '')),
        ];

        try {
            $service->clearDraftCart($user);
            $conceptosAgrupados = $conceptosSolicitados
                ->groupBy('concepto_facturacion_id');

            foreach ($conceptosAgrupados as $conceptoId => $lineasConcepto) {
                $concepto = $conceptos->get((int) $conceptoId);
                if (!$concepto) {
                    throw new \RuntimeException('Uno de los conceptos seleccionados ya no esta disponible.');
                }

                $precioBaseConcepto = round((float) ($concepto->precio_base ?? 0), 2);
                $cantidadTotal = (int) collect($lineasConcepto)->sum(fn ($linea) => max(1, (int) ($linea['cantidad'] ?? 1)));
                $cart = $service->addConceptoFacturacion($user, $concepto, $cantidadTotal, $precioBaseConcepto);

                if ($cantidadTotal <= 0) {
                    continue;
                }

                $draftItem = $this->findConceptDraftItemFromCart($cart, (int) $conceptoId, $precioBaseConcepto);
                if (!$draftItem) {
                    throw new \RuntimeException('No se pudo localizar el item base del concepto en el borrador remoto.');
                }

                $entries = collect($lineasConcepto)
                    ->flatMap(function (array $linea) use ($concepto, $precioBaseConcepto, $service) {
                        $cantidad = max(1, (int) ($linea['cantidad'] ?? 1));
                        $precio = round((float) ($linea['precio'] ?? $precioBaseConcepto), 2);
                        $codigo = trim((string) ($linea['codigo'] ?? $concepto->codigo ?? ''));
                        $descripcion = trim((string) ($linea['descripcion'] ?? ''));
                        if ($descripcion === '') {
                            $descripcion = trim((string) data_get(
                                $service->normalizeConceptoFacturacionFiscalData($concepto),
                                'descripcion_servicio',
                                $concepto->descripcion ?? $concepto->nombre ?? 'COBRO ADICIONAL'
                            ));
                        }

                        return collect(range(1, $cantidad))
                            ->map(fn () => [
                                'codigo' => $codigo !== '' ? $codigo : (string) ($concepto->codigo ?? ''),
                                'descripcion_servicio' => $descripcion,
                                'precio' => $precio,
                            ]);
                    })
                    ->values()
                    ->all();

                $baseDescription = trim((string) data_get(
                    $service->normalizeConceptoFacturacionFiscalData($concepto),
                    'descripcion_servicio',
                    $concepto->descripcion ?? $concepto->nombre ?? 'COBRO ADICIONAL'
                ));

                if ($this->requiresGroupedCustomization($entries, $concepto, $precioBaseConcepto, $baseDescription)) {
                    $service->customizeGroupedDraftItemUnits($user, (int) data_get($draftItem, 'id', 0), $entries);
                }
            }
            $service->updateDraftBillingData($user, $billingPayload);
            $resultado = $service->emitirBorrador($user, $billingPayload);

            $respuesta = (array) ($resultado['respuesta'] ?? []);
            $facturaNumero = trim((string) (
                data_get($respuesta, 'factura.nroFactura')
                ?? data_get($respuesta, 'factura.numeroFactura')
                ?? data_get($respuesta, 'nroFactura')
                ?? data_get($respuesta, 'numeroFactura')
            ));
            $pdfUrl = trim((string) data_get($respuesta, 'factura.pdfUrl', ''));
            $resumenConceptos = $conceptosSolicitados
                ->map(function (array $item) use ($conceptos): string {
                    $concepto = $conceptos->get($item['concepto_facturacion_id']);
                    $nombre = trim((string) ($concepto?->nombre ?? 'Concepto'));

                    return $nombre . ' x' . $item['cantidad'] . ' @ Bs ' . number_format((float) ($item['precio'] ?? 0), 2);
                })
                ->implode(' | ');

            return redirect()
                ->route('facturacion-servicio.index')
                ->with('facturacion_servicio_result', [
                    'type' => strtoupper((string) ($respuesta['estado'] ?? '')) === 'FACTURADA' ? 'success' : 'warning',
                    'title' => strtoupper((string) ($respuesta['estado'] ?? '')) === 'FACTURADA'
                        ? 'Factura emitida'
                        : 'Respuesta de facturacion',
                    'message' => trim((string) ($respuesta['mensaje'] ?? 'La operacion fue procesada.')),
                    'detail' => 'Empresa: ' . trim((string) ($empresa->nombre ?? 'SIN EMPRESA'))
                        . ' | Modalidad: Cuenta por cobrar'
                        . ' | Conceptos: ' . $resumenConceptos
                        . ' | Orden: ' . trim((string) data_get($resultado, 'carrito.codigo_orden', '-'))
                        . ' | Factura: ' . ($facturaNumero !== '' ? $facturaNumero : 'S/N'),
                    'pdf_url' => $pdfUrl,
                ]);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('facturacion_servicio_result', [
                    'type' => 'error',
                    'title' => 'No se pudo emitir la factura',
                    'message' => 'La facturacion por servicio no pudo completarse.',
                    'detail' => trim($e->getMessage()) !== '' ? trim($e->getMessage()) : 'Revisa el log del servidor para mayor detalle.',
                ]);
        }
    }

    private function authorizeFacturacionAccess($user): void
    {
        abort_unless($user && $user->can('facturacion-servicio.index'), 403, 'No tienes permiso para acceder a Facturacion por servicio.');
    }

    private function safeRemoteContext(FacturacionCartService $service, $user): array
    {
        try {
            return $service->getRemoteContextForUser($user);
        } catch (\Throwable) {
            return ['draft' => null, 'last' => null];
        }
    }

    private function safeCajaContext(FacturacionCartService $service, $user): array
    {
        try {
            return $service->fetchCajaEstado($user);
        } catch (\Throwable) {
            return [
                'estado' => 'SIN_APERTURA',
                'mensaje' => 'No se pudo consultar el estado de caja.',
                'caja' => [],
            ];
        }
    }

    private function findConceptDraftItemFromCart(object $cart, int $conceptoId, float $precioBase): ?object
    {
        return collect($cart->items ?? [])
            ->first(function ($item) use ($conceptoId, $precioBase) {
                return ltrim((string) data_get($item, 'origen_tipo', ''), '\\') === ltrim(ConceptoFacturacion::class, '\\')
                    && (int) data_get($item, 'resumen_origen.concepto_facturacion_id', data_get($item, 'origen_id', 0)) === $conceptoId
                    && round((float) data_get($item, 'monto_base', data_get($item, 'precio', 0)), 2) === round($precioBase, 2);
            });
    }

    private function requiresGroupedCustomization(
        array $entries,
        ConceptoFacturacion $concepto,
        float $precioBase,
        string $baseDescription
    ): bool
    {
        $baseCode = trim((string) ($concepto->codigo ?? ''));

        return collect($entries)
            ->contains(function (array $entry) use ($precioBase, $baseCode, $baseDescription) {
                return round((float) ($entry['precio'] ?? $precioBase), 2) !== round($precioBase, 2)
                    || trim((string) ($entry['codigo'] ?? $baseCode)) !== $baseCode
                    || trim((string) ($entry['descripcion_servicio'] ?? $baseDescription)) !== $baseDescription;
            });
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FacturaFirmaPdfService;
use App\Services\FacturacionCartService;
use App\Services\TrackingProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class FacturaFirmaPdfController extends Controller
{
    private const TRACKING_EVENT_SOURCES = [
        ['table' => 'eventos_ems', 'service' => 'EMS'],
        ['table' => 'eventos_certi', 'service' => 'CERTI'],
        ['table' => 'eventos_contrato', 'service' => 'CONTRATO'],
        ['table' => 'eventos_ordi', 'service' => 'ORDI'],
        ['table' => 'eventos_tiktoker', 'service' => 'SOLICITUD'],
    ];

    public function __invoke(Request $request, FacturaFirmaPdfService $pdfService, FacturacionCartService $cartService)
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'cart_id' => ['nullable', 'integer', 'min:1'],
            'source_user_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $url = $data['url'];
        $base = rtrim((string) config('services.facturacion_bridge.sefe_public_base_url'), '/');
        abort_unless(str_starts_with($url, $base . '/public/facturas_pdf/'), 403);

        try {
            $response = Http::connectTimeout(10)->timeout(45)
                ->withoutRedirecting()->accept('application/pdf')->get($url);
            if (!$response->successful() || !str_starts_with($response->body(), '%PDF-')) {
                throw new \RuntimeException('No se recibio el PDF de la factura.');
            }

            $content = $pdfService->appendSignatureFields(
                $response->body(),
                $this->buildDeliveryData($request, $cartService)
            );
        } catch (\Throwable $exception) {
            report($exception);
            abort(502, 'No se pudo preparar la factura con los campos de firma. Intenta descargarla nuevamente.');
        }

        $filename = basename((string) parse_url($url, PHP_URL_PATH));

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $filename) . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function buildDeliveryData(Request $request, FacturacionCartService $cartService): array
    {
        $viewer = $request->user();
        $sourceUser = $this->resolveSourceUser($request, $viewer);
        $data = $this->fallbackDeliveryData($viewer);
        $cartId = $request->integer('cart_id');

        if ($cartId <= 0 || !$sourceUser) {
            return $data;
        }

        try {
            $cart = $cartService->fetchVentaById($sourceUser, $cartId);
            if (!$cart) {
                return $data;
            }

            $items = $this->normalizeItems(data_get($cart, 'items', []));
            $ventaId = (int) (
                data_get($cart, 'venta_id')
                ?: data_get($cart, 'origen_venta_id')
                ?: data_get($cart, 'id')
                ?: $cartId
            );
            if ($items->isEmpty() && $ventaId > 0) {
                $detalle = $cartService->fetchVentaDetalleByVentaId($sourceUser, $ventaId);
                if ($detalle) {
                    $items = $this->normalizeItems(data_get($detalle, 'detalle', []));
                }
            }

            return array_merge($data, $this->deliveryDataFromCart($cart, $items, $sourceUser));
        } catch (\Throwable $exception) {
            report($exception);

            return $data;
        }
    }

    private function resolveSourceUser(Request $request, ?User $viewer): ?User
    {
        if (!$viewer) {
            return null;
        }

        $sourceUserId = $request->integer('source_user_id');
        if ($sourceUserId <= 0 || $sourceUserId === (int) $viewer->id) {
            return $viewer;
        }

        if ($viewer->can('ventas-sucursal.index') || $viewer->can('ventas-sucursal.export.pdf')) {
            return User::query()->find($sourceUserId) ?: $viewer;
        }

        return $viewer;
    }

    private function fallbackDeliveryData(?User $user): array
    {
        $user?->loadMissing('sucursal');
        $sucursal = $user?->sucursal;

        return [
            'codigo_rastreo' => '-',
            'barcode' => '',
            'destinatario' => '-',
            'ciudad' => trim((string) ($user?->ciudad ?? $sucursal?->municipio ?? '')),
            'ventanilla' => trim((string) ($sucursal?->puntoVenta ?? $sucursal?->codigoSucursal ?? '')),
            'aduana' => 'S/I',
            'numero_factura' => '-',
            'usuario' => trim((string) ($user?->name ?? '')),
            'tipo' => '-',
            'peso' => '-',
            'precio' => '-',
            'estado_entrega' => 'PENDIENTE DE FIRMA',
            'fecha_entrega' => '-',
            'packages' => [],
        ];
    }

    private function deliveryDataFromCart(object $cart, \Illuminate\Support\Collection $items, User $sourceUser): array
    {
        $sourceUser->loadMissing('sucursal');
        $primary = $items->first();
        $codes = $items
            ->map(fn ($item) => $this->resolveTrackingCode($item))
            ->filter()
            ->unique()
            ->values();

        $trackingCode = trim((string) ($codes->first() ?: data_get($cart, 'codigo_orden', data_get($cart, 'codigo_seguimiento', '-'))));
        $invoiceNumber = trim((string) (
            data_get($cart, 'respuesta_emision.factura.nroFactura')
            ?? data_get($cart, 'respuesta_emision.factura.numeroFactura')
            ?? data_get($cart, 'numero_factura')
            ?? '-'
        ));
        $weight = $this->resolveTotalWeight($items, $cart);
        $sucursal = $sourceUser->sucursal;

        return [
            'codigo_rastreo' => $trackingCode !== '' ? $trackingCode : '-',
            'barcode' => $trackingCode !== '' && $trackingCode !== '-' ? $trackingCode : '',
            'destinatario' => $this->firstValue([
                $primary,
                data_get($primary, 'resumen_origen', []),
                $cart,
            ], ['nombre_destinatario', 'destinatario', 'subtitulo', 'razon_social', 'cliente.razonSocial'], '-'),
            'ciudad' => $this->firstValue([
                $primary,
                data_get($primary, 'resumen_origen', []),
                $cart,
            ], ['ciudad', 'cuidad', 'destino', 'ciudad_destino', 'pais_destino', 'pais', 'origen_usuario_ciudad'], '-'),
            'ventanilla' => $this->firstValue([
                $cart,
                $sucursal,
                $sourceUser,
            ], ['origen_sucursal_codigo', 'puntoVenta', 'codigoSucursal', 'ciudad'], '-'),
            'aduana' => $this->firstValue([
                $primary,
                data_get($primary, 'resumen_origen', []),
            ], ['aduana'], 'S/I'),
            'numero_factura' => $invoiceNumber !== '' ? $invoiceNumber : '-',
            'usuario' => trim((string) data_get($cart, 'origen_usuario_nombre', $sourceUser->name ?? '-')) ?: '-',
            'tipo' => $this->firstValue([
                $primary,
                data_get($primary, 'resumen_origen', []),
            ], ['tipo', 'titulo', 'nombre_servicio', 'descripcion', 'descripcion_servicio', 'servicio_nombre'], '-'),
            'peso' => $weight > 0 ? number_format($weight, 3, '.', '') . ' gr.' : '-',
            'precio' => 'Bs ' . number_format((float) data_get($cart, 'total', 0), 2, '.', ''),
            'fecha_entrega' => $this->resolveInvoiceDate($cart),
            'packages' => $this->deliveryPackagesFromItems($items),
        ];
    }

    private function resolveInvoiceDate(object $cart): string
    {
        $date = $this->firstValue([$cart, data_get($cart, 'respuesta_emision', [])], [
            'emitido_en',
            'fecha_emision',
            'fechaEmision',
            'fecha',
            'factura.fecha_emision',
            'factura.fechaEmision',
            'factura.fecha',
            'consultaSefe.fechaEmision',
        ], '');

        if ($date === '') {
            return '-';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }

        $months = [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ];

        $day = date('d', $timestamp);
        $month = $months[(int) date('n', $timestamp)] ?? date('m', $timestamp);
        $year = date('Y', $timestamp);
        $time = date('H:i:s', $timestamp);

        return "{$day} de {$month} de {$year} {$time}";
    }

    private function deliveryPackagesFromItems(\Illuminate\Support\Collection $items): array
    {
        return $items
            ->map(function ($item): ?array {
                $code = $this->resolveTrackingCode($item);
                if ($code === '') {
                    return null;
                }

                $service = $this->firstValue([
                    $item,
                    data_get($item, 'resumen_origen', []),
                ], ['tipo', 'titulo', 'nombre_servicio', 'descripcion', 'descripcion_servicio', 'servicio_nombre'], '');

                return [
                    'codigo' => $code,
                    'servicio' => $service,
                ];
            })
            ->filter()
            ->unique(fn (array $package) => strtoupper(trim(($package['codigo'] ?? '') . '|' . ($package['servicio'] ?? ''))))
            ->filter(fn (array $package) => $this->shouldIncludePackageInDeliveryForm(
                (string) ($package['codigo'] ?? ''),
                (string) ($package['servicio'] ?? '')
            ))
            ->values()
            ->all();
    }

    private function shouldIncludePackageInDeliveryForm(string $code, string $service): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        /** @var TrackingProgressService $progressService */
        $progressService = app(TrackingProgressService::class);

        foreach ($this->trackingEventSourcesForService($service) as $source) {
            $events = $this->trackingEventsForPackage($source['table'], $source['service'], $code);
            if ($events->isEmpty()) {
                continue;
            }

            $progress = $progressService->resolve($events, (string) $source['service']);
            $steps = array_values((array) ($progress['steps'] ?? []));
            $currentIndex = (int) ($progress['current_index'] ?? -1);
            $expeditionIndex = collect($steps)
                ->search(fn ($step) => strcasecmp((string) $step, 'Expedicion') === 0);

            if ($expeditionIndex !== false && $currentIndex >= (int) $expeditionIndex) {
                return true;
            }
        }

        return false;
    }

    private function trackingEventSourcesForService(string $service): array
    {
        $service = mb_strtoupper(trim($service));
        $preferredTable = '';

        if (str_contains($service, 'EMS')) {
            $preferredTable = 'eventos_ems';
        } elseif (str_contains($service, 'CERTI') || str_contains($service, 'CERTIFICADO')) {
            $preferredTable = 'eventos_certi';
        } elseif (str_contains($service, 'CONTRATO')) {
            $preferredTable = 'eventos_contrato';
        } elseif (str_contains($service, 'ORDI') || str_contains($service, 'ORDINARIA')) {
            $preferredTable = 'eventos_ordi';
        } elseif (str_contains($service, 'SOLICITUD') || str_contains($service, 'TIKTOKER')) {
            $preferredTable = 'eventos_tiktoker';
        }

        return collect(self::TRACKING_EVENT_SOURCES)
            ->sortBy(fn (array $source) => $source['table'] === $preferredTable ? 0 : 1)
            ->values()
            ->all();
    }

    private function trackingEventsForPackage(string $table, string $service, string $code): \Illuminate\Support\Collection
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'codigo')) {
            return collect();
        }

        $query = DB::table($table . ' as ee')
            ->whereRaw('TRIM(UPPER(ee.codigo)) = TRIM(UPPER(?))', [$code]);

        $hasEventCatalog = Schema::hasTable('eventos')
            && Schema::hasColumn($table, 'evento_id')
            && Schema::hasColumn('eventos', 'id')
            && Schema::hasColumn('eventos', 'nombre_evento');

        if ($hasEventCatalog) {
            $query->leftJoin('eventos as e', 'e.id', '=', 'ee.evento_id');
        }

        $select = [
            'ee.codigo',
            DB::raw("'" . $service . "' as servicio"),
            $hasEventCatalog ? 'e.nombre_evento' : DB::raw('NULL as nombre_evento'),
        ];

        foreach (['id', 'evento_id', 'codigo_evento', 'user_id', 'created_at', 'updated_at'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $select[] = 'ee.' . $column;
            }
        }

        if (Schema::hasColumn($table, 'created_at')) {
            $query->orderByDesc('ee.created_at');
        }

        if (Schema::hasColumn($table, 'id')) {
            $query->orderByDesc('ee.id');
        }

        return $query->get($select);
    }

    private function normalizeItems(mixed $items): \Illuminate\Support\Collection
    {
        $rows = $items instanceof \Illuminate\Support\Collection
            ? $items
            : (is_array($items) ? collect($items) : collect());

        return $rows
            ->map(fn ($item) => is_array($item) ? (object) $item : $item)
            ->filter(fn ($item) => is_object($item))
            ->values();
    }

    private function resolveTrackingCode(mixed $item): string
    {
        $candidates = [
            data_get($item, 'resumen_origen.codigo_detalle_enviado'),
            data_get($item, 'codigo_detalle_enviado'),
            data_get($item, 'codigo_paquete'),
            data_get($item, 'resumen_origen.codigo_paquete'),
            data_get($item, 'resumen_origen.codigo'),
            data_get($item, 'codigo'),
            data_get($item, 'codigo_item'),
        ];

        foreach ($candidates as $candidate) {
            $code = strtoupper(trim((string) $candidate));
            if ($code === '') {
                continue;
            }

            if (preg_match('/^SRVE-\d+\s*-\s*(.+)$/', $code, $matches)) {
                $code = strtoupper(trim((string) $matches[1]));
            }

            if (!$this->isServiceReferenceCode($code)) {
                return $code;
            }
        }

        return '';
    }

    private function isServiceReferenceCode(string $reference): bool
    {
        $reference = strtoupper(trim($reference));

        return $reference !== ''
            && (str_starts_with($reference, 'SRVE-')
                || str_starts_with($reference, 'SERV-')
                || str_starts_with($reference, 'SERVICIO-'));
    }

    private function resolveTotalWeight(\Illuminate\Support\Collection $items, object $cart): float
    {
        $weight = (float) $items->sum(fn ($item) => (float) (
            data_get($item, 'resumen_origen.peso')
            ?? data_get($item, 'peso')
            ?? 0
        ));

        if ($weight <= 0) {
            $weight = (float) (
                data_get($cart, 'peso_total')
                ?? data_get($cart, 'pesoTotal')
                ?? data_get($cart, 'peso')
                ?? 0
            );
        }

        return round($weight, 3);
    }

    private function firstValue(array $sources, array $keys, string $fallback): string
    {
        foreach ($sources as $source) {
            if ($source === null) {
                continue;
            }

            foreach ($keys as $key) {
                $value = trim((string) data_get($source, $key, ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return $fallback;
    }
}

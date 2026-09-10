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

    private array $unavailableTrackingApiUrls = [];

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
        $packages = $items
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
                    'peso' => $this->resolveDeliveryPackageWeight($item),
                    'monto' => $this->resolveDeliveryPackageAmount($item),
                ];
            })
            ->filter()
            ->unique(fn (array $package) => strtoupper(trim(($package['codigo'] ?? '') . '|' . ($package['servicio'] ?? ''))))
            ->values();

        return $this->filterDeliveryPackagesByTracking($packages)->values()->all();
    }

    private function resolveDeliveryPackageAmount(object $item): string
    {
        $amount = collect([
            data_get($item, 'monto_base'),
            data_get($item, 'precio'),
            data_get($item, 'resumen_origen.monto_base'),
            data_get($item, 'resumen_origen.precio'),
        ])->first(fn ($value) => $value !== null && trim((string) $value) !== '');

        if ($amount === null || trim((string) $amount) === '') {
            $amount = data_get($item, 'total_linea');
        }

        $amount = round(max(0, (float) $amount), 2);

        return $amount > 0 ? 'Bs ' . number_format($amount, 2, '.', '') : '';
    }

    private function resolveDeliveryPackageWeight(object $item): string
    {
        $weight = collect([
            data_get($item, 'resumen_origen.peso'),
            data_get($item, 'peso'),
            data_get($item, 'resumen_origen.peso_kg'),
            data_get($item, 'peso_kg'),
            data_get($item, 'weight'),
        ])->first(fn ($value) => $value !== null && trim((string) $value) !== '');

        if ($weight === null || trim((string) $weight) === '') {
            return '';
        }

        $weight = round(max(0, (float) str_replace(',', '.', (string) $weight)), 3);

        return $weight > 0 ? number_format($weight, 3, '.', '') . ' kg' : '';
    }

    private function shouldIncludePackageInDeliveryForm(string $code, string $service): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        /** @var TrackingProgressService $progressService */
        $progressService = app(TrackingProgressService::class);

        if ($this->localTrackingAllowsDeliveryForm($code, $service, $progressService)) {
            return true;
        }

        $events = $this->trackingEventsFromExternalApi($code);
        if ($events->isEmpty()) {
            return false;
        }

        $progress = $progressService->resolve(
            $events,
            (string) (($events->first()->servicio ?? $service) ?: 'TRACKING')
        );

        return $this->trackingProgressAllowsDeliveryForm($progress);
    }

    private function filterDeliveryPackagesByTracking(\Illuminate\Support\Collection $packages): \Illuminate\Support\Collection
    {
        /** @var TrackingProgressService $progressService */
        $progressService = app(TrackingProgressService::class);
        $included = collect();
        $pending = collect();

        foreach ($packages as $package) {
            $code = strtoupper(trim((string) ($package['codigo'] ?? '')));
            $service = (string) ($package['servicio'] ?? '');

            if ($this->localTrackingAllowsDeliveryForm($code, $service, $progressService)) {
                $included->push($package);
            } else {
                $pending->push($package);
            }
        }

        $externalEventsByCode = $this->trackingEventsFromExternalApiByCode(
            $pending->pluck('codigo')->all()
        );

        return $included->merge($pending->filter(function (array $package) use ($externalEventsByCode, $progressService): bool {
            $code = strtoupper(trim((string) ($package['codigo'] ?? '')));
            $events = $externalEventsByCode[$code] ?? collect();
            if ($events->isEmpty()) {
                return false;
            }

            $progress = $progressService->resolve(
                $events,
                (string) (($events->first()->servicio ?? $package['servicio'] ?? '') ?: 'TRACKING')
            );

            return $this->trackingProgressAllowsDeliveryForm($progress);
        }));
    }

    private function localTrackingAllowsDeliveryForm(string $code, string $service, TrackingProgressService $progressService): bool
    {
        foreach ($this->trackingEventSourcesForService($service) as $source) {
            $events = $this->trackingEventsForPackage($source['table'], $source['service'], $code);
            if ($events->isEmpty()) {
                continue;
            }

            $progress = $progressService->resolve($events, (string) $source['service']);
            if ($this->trackingProgressAllowsDeliveryForm($progress)) {
                return true;
            }
        }

        return false;
    }

    private function trackingProgressAllowsDeliveryForm(array $progress): bool
    {
        $steps = array_values((array) ($progress['steps'] ?? []));
        $currentIndex = (int) ($progress['current_index'] ?? -1);
        $expeditionIndex = collect($steps)
            ->search(fn ($step) => strcasecmp((string) $step, 'Expedicion') === 0);
        $currentStep = (string) ($steps[$currentIndex] ?? '');

        return ($expeditionIndex !== false && $currentIndex >= (int) $expeditionIndex)
            || in_array($this->normalizeTrackingStep($currentStep), ['expedicion', 'aduana', 'ventanilla', 'entregado'], true);
    }

    private function trackingEventsFromExternalApi(string $code): \Illuminate\Support\Collection
    {
        $eventsByCode = $this->trackingEventsFromExternalApiByCode([$code]);

        return $eventsByCode[strtoupper(trim($code))] ?? collect();
    }

    private function trackingEventsFromExternalApiByCode(array $codes): array
    {
        $codes = collect($codes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();
        $eventsByCode = [];

        if ($codes->isEmpty()) {
            return $eventsByCode;
        }

        foreach ($this->trackingApiConfigs() as $apiConfig) {
            if (isset($this->unavailableTrackingApiUrls[$apiConfig['url']])) {
                continue;
            }

            $pendingCodes = $codes
                ->reject(fn (string $code) => isset($eventsByCode[$code]))
                ->values();

            if ($pendingCodes->isEmpty()) {
                break;
            }

            $responses = $this->fetchExternalTrackingResponses($apiConfig, $pendingCodes);
            if ($responses === null) {
                continue;
            }

            foreach ($responses as $code => $response) {
                if ($response instanceof \Illuminate\Http\Client\Response) {
                    if ($response->serverError()) {
                        $this->unavailableTrackingApiUrls[$apiConfig['url']] = true;
                    }

                    if ($response->status() === 422 || ! $response->ok()) {
                        continue;
                    }

                    $payload = (array) $response->json();
                } elseif (is_array($response)) {
                    $payload = $response;
                } else {
                    continue;
                }

                $events = $this->normalizeExternalTrackingEvents($payload, (string) $code);
                if ($events->isNotEmpty()) {
                    $eventsByCode[strtoupper(trim((string) $code))] = $events;
                }
            }
        }

        return $eventsByCode;
    }

    private function fetchExternalTrackingResponses(array $apiConfig, \Illuminate\Support\Collection $codes): ?array
    {
        $responses = [];

        try {
            if (! empty($apiConfig['batch'])) {
                $request = Http::connectTimeout(2)
                    ->timeout((int) config('services.tracking_sqlserver.timeout', 15))
                    ->acceptJson()
                    ->withOptions(['verify' => (bool) config('services.tracking_sqlserver.ssl_verify', false)]);

                if ($apiConfig['token'] !== '') {
                    $request = $request->withToken($apiConfig['token']);
                }

                $response = $request->post($apiConfig['url'], ['codigos' => $codes->values()->all()]);

                if ($response->serverError()) {
                    $this->unavailableTrackingApiUrls[$apiConfig['url']] = true;

                    return null;
                }

                if ($response->status() === 422 || ! $response->ok()) {
                    return [];
                }

                return $this->trackingResponsesFromBatchPayload((array) $response->json(), $codes);
            }

            foreach ($codes as $code) {
                $request = Http::connectTimeout(2)
                    ->timeout((int) config('services.tracking_sqlserver.timeout', 15))
                    ->acceptJson()
                    ->withOptions(['verify' => (bool) config('services.tracking_sqlserver.ssl_verify', false)]);

                if ($apiConfig['token'] !== '') {
                    $request = $request->withToken($apiConfig['token']);
                }

                $responses[$code] = $request->get($apiConfig['url'], ['codigo' => $code]);
            }
        } catch (\Throwable) {
            $this->unavailableTrackingApiUrls[$apiConfig['url']] = true;

            return null;
        }

        return $responses;
    }

    private function trackingResponsesFromBatchPayload(array $payload, \Illuminate\Support\Collection $codes): array
    {
        $requested = array_fill_keys($codes->values()->all(), true);
        $responses = [];
        $groups = data_get($payload, 'resultado', data_get($payload, 'resultados', []));

        foreach ((array) $groups as $key => $group) {
            $group = is_array($group) ? $group : (array) $group;
            if ($group === []) {
                continue;
            }

            $code = strtoupper(trim((string) ($group['codigo'] ?? (is_string($key) ? $key : ''))));
            if ($code === '' || ! isset($requested[$code])) {
                continue;
            }

            $responses[$code] = $group;
        }

        return $responses;
    }

    private function trackingApiConfigs(): array
    {
        $baseUrl = $this->normalizeTrackingApiUrl(trim((string) config('services.tracking_sqlserver.base_url', '')));
        $batchUrl = $this->normalizeTrackingBatchApiUrl(trim((string) config('services.tracking_sqlserver.eventos_batch_url', '')), $baseUrl);

        return collect([
            [
                'url' => $batchUrl,
                'token' => $this->normalizeBearerToken(trim((string) config('services.tracking_sqlserver.token', ''))),
                'batch' => true,
            ],
            [
                'url' => $baseUrl,
                'token' => $this->normalizeBearerToken(trim((string) config('services.tracking_sqlserver.token', ''))),
                'batch' => false,
            ],
            [
                'url' => $this->normalizeTrackingApiUrl(trim((string) config('services.tracking_sqlserver.fallback_base_url', ''))),
                'token' => $this->normalizeBearerToken(trim((string) config('services.tracking_sqlserver.fallback_token', ''))),
                'batch' => false,
            ],
        ])
            ->filter(fn (array $config) => $config['url'] !== '')
            ->unique(fn (array $config) => $config['url'])
            ->values()
            ->all();
    }

    private function normalizeExternalTrackingEvents(array $payload, string $code): \Illuminate\Support\Collection
    {
        $events = collect(data_get($payload, 'eventos_locales', []))
            ->merge(data_get($payload, 'eventos_externos', []));

        if ($events->isEmpty()) {
            $events = collect(data_get($payload, 'resultado', []))
                ->flatMap(fn ($group) => (array) data_get($group, 'eventos', []));
        }

        return $events
            ->map(fn ($event) => $this->normalizeExternalTrackingEvent($event, $payload, $code))
            ->filter()
            ->sortByDesc(fn ($event) => strtotime((string) ($event->created_at ?? '')) ?: 0)
            ->values();
    }

    private function normalizeExternalTrackingEvent(mixed $event, array $payload, string $code): ?object
    {
        $event = is_array($event) ? $event : (array) $event;
        if ($event === []) {
            return null;
        }

        $createdAt = (string) (
            $event['created_at']
            ?? $event['eventDate']
            ?? $event['fecha_hora']
            ?? $event['fecha_registro']
            ?? $event['fecha']
            ?? now()->toDateTimeString()
        );

        return (object) [
            'id' => $event['id'] ?? null,
            'codigo' => $event['codigo'] ?? $event['mailitM_FID'] ?? data_get($payload, 'codigo', $code),
            'evento_id' => $event['evento_id'] ?? $event['id_evento'] ?? null,
            'codigo_evento' => $event['codigo_evento'] ?? $event['eventCode'] ?? $event['event_type_cd'] ?? null,
            'created_at' => $createdAt,
            'updated_at' => $event['updated_at'] ?? $createdAt,
            'nombre_evento' => $event['nombre_evento']
                ?? $event['eventType']
                ?? $event['evento']
                ?? $event['descripcion_evento']
                ?? $event['descripcion']
                ?? 'Evento de seguimiento',
            'servicio' => $this->resolveExternalTrackingService($event, $payload, $code),
        ];
    }

    private function resolveExternalTrackingService(array $event, array $payload, string $code): string
    {
        $service = strtoupper(trim((string) (
            data_get($payload, 'servicio')
            ?? data_get($payload, 'tipo_servicio')
            ?? data_get($payload, 'service')
            ?? $event['servicio']
            ?? $event['tipo_servicio']
            ?? $event['service']
            ?? ''
        )));

        if ($service !== '') {
            return $service;
        }

        $code = strtoupper(trim((string) ($event['mailitM_FID'] ?? data_get($payload, 'codigo', $code))));

        if (preg_match('/^R[A-Z]\d{9}[A-Z]{2}$/', $code) === 1) {
            return 'CERTI';
        }

        if (preg_match('/^E[A-Z]\d{9}[A-Z]{2}$/', $code) === 1) {
            return 'EMS';
        }

        return 'TRACKING';
    }

    private function normalizeTrackingApiUrl(string $configuredUrl): string
    {
        if ($configuredUrl === '') {
            return '';
        }

        $parts = parse_url($configuredUrl);
        $path = trim((string) ($parts['path'] ?? ''));

        if ($path === '' || $path === '/') {
            return rtrim($configuredUrl, '/') . '/api/tracking/eventos';
        }

        if ($path === '/api/sqlserver/busqueda') {
            return preg_replace('#/api/sqlserver/busqueda$#', '/api/tracking/eventos', $configuredUrl) ?: $configuredUrl;
        }

        return $configuredUrl;
    }

    private function normalizeTrackingBatchApiUrl(string $configuredUrl, string $baseUrl): string
    {
        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/');
        }

        if ($baseUrl === '') {
            return '';
        }

        return rtrim($baseUrl, '/') . '/batch';
    }

    private function normalizeBearerToken(string $token): string
    {
        if (str_starts_with(strtolower($token), 'bearer ')) {
            return trim(substr($token, 7));
        }

        return $token;
    }

    private function normalizeTrackingStep(string $step): string
    {
        $step = mb_strtolower(trim($step));

        if (class_exists(\Normalizer::class)) {
            $step = \Normalizer::normalize($step, \Normalizer::FORM_D) ?: $step;
            $step = preg_replace('/\p{Mn}+/u', '', $step) ?: $step;
        }

        return $step;
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
            data_get($item, 'resumen_origen.codigo_item'),
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

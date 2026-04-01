<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cartero;
use App\Models\Driver;
use App\Models\Estado;
use App\Models\FuelInvoice;
use App\Models\ActivityLog;
use App\Models\Vehicle;
use App\Models\VehicleLogInvestigationTicket;
use App\Models\VehicleLogSession;
use App\Services\MaintenanceAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MobileUtilityController extends Controller
{
    private const HEARTBEAT_TTL_HOURS = 12;
    private const HEARTBEAT_DUPLICATE_WINDOW_SECONDS = 3;
    private const HEARTBEAT_ROUTE_HISTORY_LIMIT = 180;
    private const BITACORA_SESSION_TTL_HOURS = 24;
    private const HEARTBEAT_SUSPEND_MINUTES = 5;

    public function locationHeartbeat(Request $request)
    {
        $payload = $request->validate([
            'user_id' => 'nullable|integer|min:0',
            'driver_id' => 'nullable|integer|min:0',
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'latitude' => 'nullable|numeric|between:-90,90',
            'lat' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'lng' => 'nullable|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|min:0|max:300',
            'point_timestamp' => 'nullable|numeric|min:0',
            'timestamp' => 'nullable',
            'waiting_stop' => 'nullable|boolean',
            'estado' => 'nullable|string|max:40',
            'gps_enabled' => 'nullable|boolean',
            'gps_mocked' => 'nullable|boolean',
            'sent_at' => 'nullable|date',
        ]);

        $lat = $this->asFloat($payload['latitude'] ?? $payload['lat'] ?? null);
        $lng = $this->asFloat($payload['longitude'] ?? $payload['lng'] ?? null);
        if (is_null($lat) || is_null($lng)) {
            return response()->json([
                'message' => 'Latitude/longitude requeridos.',
            ], 422);
        }

        $vehicleId = (int) $payload['vehicle_id'];
        $cacheKey = "mobile:heartbeat:vehicle:{$vehicleId}";
        $nowIso = now()->toIso8601String();
        $incomingTs = $payload['point_timestamp'] ?? $payload['timestamp'] ?? null;
        $speedKmh = $this->asFloat($payload['speed_kmh'] ?? null);
        $status = $this->normalizeHeartbeatStatus($payload['estado'] ?? null, (bool) ($payload['waiting_stop'] ?? false));
        $last = Cache::get($cacheKey);

        // Drop near-duplicate writes to avoid unnecessary cache churn.
        if (is_array($last)) {
            $sameLat = abs(((float) ($last['latitude'] ?? 0)) - $lat) < 0.00001;
            $sameLng = abs(((float) ($last['longitude'] ?? 0)) - $lng) < 0.00001;
            $sameStatus = ((string) ($last['estado'] ?? '')) === $status;
            $receivedAt = isset($last['received_at']) ? strtotime((string) $last['received_at']) : 0;
            $freshEnough = $receivedAt > 0 && (time() - $receivedAt) <= self::HEARTBEAT_DUPLICATE_WINDOW_SECONDS;

            if ($sameLat && $sameLng && $sameStatus && $freshEnough) {
                return response()->json([
                    'message' => 'Heartbeat ignorado por duplicado reciente.',
                    'vehicle_id' => $vehicleId,
                    'received_at' => $nowIso,
                ]);
            }
        }

        $heartbeatEvent = [
            'user_id' => (int) ($payload['user_id'] ?? ($request->user()?->id ?? 0)),
            'driver_id' => (int) ($payload['driver_id'] ?? 0) ?: null,
            'vehicle_id' => $vehicleId,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed_kmh' => $speedKmh,
            'point_timestamp' => $incomingTs,
            'waiting_stop' => $status === 'ESPERA',
            'estado' => $status,
            'gps_enabled' => array_key_exists('gps_enabled', $payload)
                ? (bool) $payload['gps_enabled']
                : true,
            'gps_mocked' => array_key_exists('gps_mocked', $payload)
                ? (bool) $payload['gps_mocked']
                : false,
            'sent_at' => (string) ($payload['sent_at'] ?? $nowIso),
            'received_at' => $nowIso,
        ];

        Cache::put($cacheKey, $heartbeatEvent, now()->addHours(self::HEARTBEAT_TTL_HOURS));
        $this->appendHeartbeatRoutePoint($vehicleId, $heartbeatEvent);

        $this->touchHeartbeatIndex($vehicleId);
        $this->publishHeartbeatEvent($heartbeatEvent);

        return response()->json([
            'message' => 'Heartbeat recibido.',
            'vehicle_id' => $vehicleId,
            'received_at' => $nowIso,
        ]);
    }

    public function bitacoraLoad(Request $request)
    {
        $payload = $request->validate([
            'user_id' => 'nullable|integer|min:0',
            'driver_id' => 'nullable|integer|min:0',
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'session_id' => 'nullable|integer|min:0',
            'session_reference' => 'nullable|string|max:120',
            'event' => 'required|string|in:START,FINALIZE,POINT_TO_POINT,CARGA',
            'responsible_driver_id' => 'nullable|integer|min:0',
            'current_driver_id' => 'nullable|integer|min:0',
            'timeline' => 'nullable|array|max:500',
            'points' => 'nullable|array|max:500',
            'point_to_point_segments' => 'nullable|array|max:500',
            'distance_km' => 'nullable|numeric|min:0',
            'distanceKm' => 'nullable|numeric|min:0',
            'sent_at' => 'nullable|date',
            'qr_data' => 'nullable',
            'qrData' => 'nullable',
            'qr_urls' => 'nullable',
        ]);

        $resolvedUserId = (int) ($request->user()?->id ?? 0);
        if ($resolvedUserId <= 0 && !empty($payload['user_id'])) {
            $resolvedUserId = (int) $payload['user_id'];
        }

        $event = strtoupper((string) $payload['event']);
        if ($event === 'START') {
            $vehicle = Vehicle::query()->find((int) $payload['vehicle_id']);
            $blockReason = MaintenanceAlertService::resolveVehicleLogBlockReason($vehicle);
            if ($blockReason !== null) {
                return response()->json([
                    'message' => $blockReason,
                ], 422);
            }
        }

        $action = match ($event) {
            'START' => 'BITACORA_LOAD_START',
            'FINALIZE' => 'BITACORA_LOAD_FINALIZE',
            'POINT_TO_POINT' => 'BITACORA_LOAD_POINT_TO_POINT',
            'CARGA' => 'BITACORA_LOAD_CARGA',
            default => 'BITACORA_LOAD',
        };
        $sessionId = (int) ($payload['session_id'] ?? 0);
        $timeline = $this->normalizeBitacoraTimeline(
            $payload['timeline'] ?? null,
            $payload['points'] ?? null,
            $payload['point_to_point_segments'] ?? null
        );
        $distanceKm = $this->asFloat($payload['distance_km'] ?? $payload['distanceKm'] ?? null);
        $sessionKey = $this->bitacoraSessionCacheKey($resolvedUserId, (int) $payload['vehicle_id'], $sessionId);
        $timelineSanitized = is_array($timeline) ? array_values($timeline) : [];
        $segments = is_array($payload['point_to_point_segments'] ?? null) ? array_values($payload['point_to_point_segments']) : [];
        $createdCount = 0;
        $createdIds = [];
        $fuelRouteContext = $this->resolveFuelRouteContextFromTimeline($timelineSanitized);
        $qrDataPayload = $payload['qr_data'] ?? $payload['qrData'] ?? $payload['qr_urls'] ?? null;
        $qrUrls = $this->extractQrUrls($qrDataPayload);
        $structuredFuelEntries = $this->extractStructuredFuelEntries($qrDataPayload);
        $fuelSync = [
            'requested' => count($qrUrls) + count($structuredFuelEntries),
            'scraped_ok' => 0,
            'saved_ok' => 0,
            'saved_ids' => [],
            'errors' => [],
        ];

        if ($event === 'START') {
            Cache::put($sessionKey, [
                'user_id' => $resolvedUserId > 0 ? $resolvedUserId : null,
                'driver_id' => (int) ($payload['driver_id'] ?? 0) ?: null,
                'vehicle_id' => (int) $payload['vehicle_id'],
                'session_id' => $sessionId ?: null,
                'session_reference' => $payload['session_reference'] ?? null,
                'responsible_driver_id' => (int) ($payload['responsible_driver_id'] ?? 0) ?: null,
                'current_driver_id' => (int) ($payload['current_driver_id'] ?? 0) ?: null,
                'sent_at' => (string) ($payload['sent_at'] ?? now()->toIso8601String()),
                'timeline' => $timelineSanitized,
                'point_to_point_segments' => $segments,
            ], now()->addHours(self::BITACORA_SESSION_TTL_HOURS));
        }

        $timelineCount = is_array($timeline) ? count($timeline) : 0;
        if (in_array($event, ['POINT_TO_POINT', 'CARGA', 'FINALIZE'], true) && ($timelineCount >= 2 || count($segments) >= 1)) {
            // En cierre normal de bitacora (START -> FINALIZE) se mantiene un solo tramo,
            // pero conservando todos los puntos intermedios para graficar la trayectoria real.
            $timelineForPointToPoint = $timeline;
            $segmentsForPointToPoint = $segments;
            if ($event === 'FINALIZE' && is_array($timelineForPointToPoint) && count($timelineForPointToPoint) >= 2) {
                $fromPoint = $timelineForPointToPoint[0];
                $toPoint = $timelineForPointToPoint[count($timelineForPointToPoint) - 1];
                $segmentsForPointToPoint = [[
                    'from' => $fromPoint,
                    'to' => $toPoint,
                    'intermediate_points' => array_values(array_filter(
                        array_slice($timelineForPointToPoint, 1, -1),
                        fn ($point) => is_array($point)
                    )),
                ]];
            }

            $pointToPointRequest = Request::create('/api/vehicle-logs/point-to-point', 'POST', [
                'user_id' => $resolvedUserId > 0 ? $resolvedUserId : null,
                'driver_id' => (int) ($payload['driver_id'] ?? 0) ?: null,
                'vehicle_id' => (int) $payload['vehicle_id'],
                'session_id' => $sessionId ?: null,
                'session_reference' => $payload['session_reference'] ?? null,
                'responsible_driver_id' => (int) ($payload['responsible_driver_id'] ?? 0) ?: null,
                'current_driver_id' => (int) ($payload['current_driver_id'] ?? 0) ?: null,
                'timeline' => $timelineForPointToPoint,
                'point_to_point_segments' => $segmentsForPointToPoint,
                'distance_km' => $distanceKm,
                'sent_at' => $payload['sent_at'] ?? now()->toIso8601String(),
            ]);
            $pointToPointRequest->setUserResolver(fn () => $request->user());

            /** @var JsonResponse $ptpResponse */
            $ptpResponse = app(VehicleLogApiController::class)->pointToPoint($pointToPointRequest);
            $ptpStatus = $ptpResponse->getStatusCode();
            if ($ptpStatus >= 400) {
                $ptpData = $ptpResponse->getData(true);

                return response()->json([
                    'message' => 'No se pudo cerrar la bitacora: fallo registro de tramos punto a punto.',
                    'event' => $event,
                    'session_id' => $sessionId ?: null,
                    'details' => is_array($ptpData) ? $ptpData : null,
                ], $ptpStatus);
            }

            $ptpData = $ptpResponse->getData(true);
            $createdCount = (int) ($ptpData['created_count'] ?? 0);
            $createdIds = is_array($ptpData['vehicle_log_ids'] ?? null) ? $ptpData['vehicle_log_ids'] : [];
        }

        $canSyncFuel = $event === 'FINALIZE' && $this->isVehicleInRoute((int) $payload['vehicle_id']);

        if ($event !== 'FINALIZE' && ($fuelSync['requested'] ?? 0) > 0) {
            $fuelSync['errors'][] = [
                'stage' => 'rules',
                'message' => 'El vale de combustible solo se registra al finalizar la bitacora.',
            ];
        } elseif (!$canSyncFuel && ($fuelSync['requested'] ?? 0) > 0) {
            $fuelSync['errors'][] = [
                'stage' => 'rules',
                'message' => 'No se puede registrar vale: el vehiculo no esta EN_RUTA al finalizar la bitacora.',
            ];
        } else {
            if (!empty($qrUrls)) {
                $urlSync = $this->syncMobileQrDataAsFuel(
                    $request,
                    $qrUrls,
                    (int) $payload['vehicle_id'],
                    (int) ($payload['driver_id'] ?? 0),
                    $fuelRouteContext
                );
                $fuelSync = $this->mergeFuelSyncResults($fuelSync, $urlSync);
            }

            if (!empty($structuredFuelEntries)) {
                $entrySync = $this->syncMobileStructuredFuelEntries(
                    $request,
                    $structuredFuelEntries,
                    (int) $payload['vehicle_id'],
                    (int) ($payload['driver_id'] ?? 0),
                    $fuelRouteContext
                );
                $fuelSync = $this->mergeFuelSyncResults($fuelSync, $entrySync);
            }
        }

        if ($event === 'FINALIZE') {
            Cache::forget($sessionKey);
        }

        $log = ActivityLog::create($this->buildActivityLogPayload($request, [
            'user_id' => $resolvedUserId > 0 ? $resolvedUserId : null,
            'action' => $action,
            'model' => 'vehicle_log',
            'record_id' => (int) (($payload['session_id'] ?? 0) ?: $payload['vehicle_id']),
            'changes_json' => [
                'driver_id' => (int) ($payload['driver_id'] ?? 0) ?: null,
                'vehicle_id' => (int) $payload['vehicle_id'],
                'session_id' => $sessionId ?: null,
                'session_reference' => $payload['session_reference'] ?? null,
                'responsible_driver_id' => (int) ($payload['responsible_driver_id'] ?? 0) ?: null,
                'current_driver_id' => (int) ($payload['current_driver_id'] ?? 0) ?: null,
                'distance_km' => $distanceKm,
                'timeline_count' => count($timelineSanitized),
                'timeline' => $timelineSanitized,
                'point_to_point_segments_count' => count($segments),
                'point_to_point_segments' => $segments,
                'created_count' => $createdCount,
                'vehicle_log_ids' => $createdIds,
                'qr_urls_count' => count($qrUrls),
                'fuel_sync' => $fuelSync,
                'sent_at' => (string) ($payload['sent_at'] ?? now()->toIso8601String()),
            ],
        ]));

        return response()->json([
            'message' => 'Carga de bitacora recibida.',
            'activity_log_id' => $log->id,
            'event' => $event,
            'created_count' => $createdCount,
            'vehicle_log_ids' => $createdIds,
            'point_to_point_segments_count' => count($segments),
            'fuel_sync' => $fuelSync,
        ], 201);
    }

    public function emergencyAlert(Request $request)
    {
        $payload = $request->validate([
            'type' => 'nullable|string|max:40',
            'message' => 'required|string|max:1000',
            'record_id' => 'nullable|integer|min:0',
            'model' => 'nullable|string|max:120',
            'changes_json' => 'nullable',
        ]);

        $log = ActivityLog::create($this->buildActivityLogPayload($request, [
            'user_id' => (int) ($request->user()?->id ?? 0) ?: null,
            'action' => 'SOS',
            'model' => (string) ($payload['model'] ?? 'vehicle_assignments'),
            'record_id' => (int) ($payload['record_id'] ?? 0),
            'changes_json' => is_string($payload['changes_json'] ?? null)
                ? json_decode((string) $payload['changes_json'], true)
                : ($payload['changes_json'] ?? [
                    'type' => (string) ($payload['type'] ?? 'SOS'),
                    'message' => (string) $payload['message'],
                ]),
        ]));

        return response()->json([
            'message' => 'Alerta de emergencia registrada.',
            'activity_log_id' => $log->id,
        ], 201);
    }

    public function activityIndex(Request $request)
    {
        $user = $request->user();
        $query = ActivityLog::query()->with('user');
        if ($this->activityLogsHasColumn('fecha')) {
            $query->latest('fecha');
        } else {
            $query->latest();
        }

        if (!in_array($user?->role, ['admin', 'recepcion'], true)) {
            $query->where('user_id', (int) $user?->id);
        }

        if ($request->filled('model') && $this->activityLogsHasColumn('model')) {
            $query->where('model', (string) $request->query('model'));
        }
        if ($request->filled('action')) {
            $query->where('action', (string) $request->query('action'));
        }

        return response()->json($query->paginate(20));
    }

    public function activityStore(Request $request)
    {
        $payload = $request->validate([
            'user_id' => 'nullable|integer|min:0',
            'action' => 'required|string|max:120',
            'model' => 'required|string|max:120',
            'record_id' => 'nullable|integer|min:0',
            'changes_json' => 'nullable',
        ]);

        $resolvedUserId = (int) ($request->user()?->id ?? 0);
        if ($resolvedUserId <= 0 && !empty($payload['user_id'])) {
            $resolvedUserId = (int) $payload['user_id'];
        }

        $changes = $payload['changes_json'] ?? null;
        if (is_string($changes)) {
            $decoded = json_decode($changes, true);
            $changes = is_array($decoded) ? $decoded : ['raw' => $changes];
        }

        $log = ActivityLog::create($this->buildActivityLogPayload($request, [
            'user_id' => $resolvedUserId > 0 ? $resolvedUserId : null,
            'action' => (string) $payload['action'],
            'model' => (string) $payload['model'],
            'record_id' => (int) ($payload['record_id'] ?? 0),
            'changes_json' => $changes,
        ]));

        return response()->json([
            'message' => 'Actividad registrada.',
            'activity_log_id' => $log->id,
        ], 201);
    }

    public function siatConsultaFactura(Request $request)
    {
        $payload = $request->validate([
            'nit_emisor' => 'required|string|max:30',
            'cuf' => 'required|string|max:255',
            'numero_factura' => 'required|string|max:60',
        ]);

        $nit = preg_replace('/\D+/', '', (string) $payload['nit_emisor']);
        $numero = preg_replace('/\D+/', '', (string) $payload['numero_factura']);
        $cuf = trim((string) $payload['cuf']);

        $body = [
            'nitEmisor' => $nit,
            'cuf' => $cuf,
            'numeroFactura' => $numero,
        ];

        try {
            $verifySsl = filter_var(env('SIAT_VERIFY_SSL', false), FILTER_VALIDATE_BOOL);
            $response = Http::timeout(20)
                ->retry(1, 350)
                ->withOptions(['verify' => $verifySsl])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/plain, */*',
                    'Origin' => 'https://siat.impuestos.gob.bo',
                    'Referer' => 'https://siat.impuestos.gob.bo/',
                    'User-Agent' => 'Mozilla/5.0',
                ])
                ->put('https://siatrest.impuestos.gob.bo/sre-sfe-shared-v2-rest/consulta/factura', $body);

            if (!$response->ok()) {
                return response()->json([
                    'message' => 'SIAT no devolvio respuesta exitosa.',
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 800),
                ], 422);
            }

            return response()->json($response->json());
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error consultando SIAT.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function asFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function buildActivityLogPayload(Request $request, array $input): array
    {
        $payload = [
            'user_id' => $input['user_id'] ?? null,
            'action' => (string) ($input['action'] ?? 'UNKNOWN'),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
        ];

        $changes = $input['changes_json'] ?? null;
        $model = (string) ($input['model'] ?? 'unknown');
        $recordId = isset($input['record_id']) ? (int) $input['record_id'] : 0;

        if ($this->activityLogsHasColumn('model')) {
            $payload['model'] = $model;
        }
        if ($this->activityLogsHasColumn('record_id')) {
            $payload['record_id'] = $recordId;
        }
        if ($this->activityLogsHasColumn('changes_json')) {
            $payload['changes_json'] = $changes;
        }
        if ($this->activityLogsHasColumn('fecha')) {
            $payload['fecha'] = now();
        }

        // Compatibilidad con esquema legado de vitacora: module/details.
        if ($this->activityLogsHasColumn('module')) {
            $payload['module'] = $model;
        }
        if ($this->activityLogsHasColumn('details')) {
            $legacyDetails = [
                'record_id' => $recordId > 0 ? $recordId : null,
                'changes_json' => $changes,
            ];
            $payload['details'] = json_encode($legacyDetails, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $payload;
    }

    private function activityLogsHasColumn(string $column): bool
    {
        static $cache = [];
        if (array_key_exists($column, $cache)) {
            return $cache[$column];
        }

        return $cache[$column] = Schema::hasColumn('activity_logs', $column);
    }

    private function normalizeHeartbeatStatus(?string $status, bool $waitingStop): string
    {
        $normalized = mb_strtoupper(trim((string) $status));
        if ($normalized !== '') {
            return Str::limit($normalized, 40, '');
        }

        return $waitingStop ? 'ESPERA' : 'EN_RUTA';
    }

    private function touchHeartbeatIndex(int $vehicleId): void
    {
        $indexKey = 'mobile:heartbeat:index';
        $ids = Cache::get($indexKey, []);
        if (!is_array($ids)) {
            $ids = [];
        }

        if (!in_array($vehicleId, $ids, true)) {
            $ids[] = $vehicleId;
            Cache::put($indexKey, array_values(array_unique(array_map('intval', $ids))), now()->addHours(self::HEARTBEAT_TTL_HOURS));
        }
    }

    private function appendHeartbeatRoutePoint(int $vehicleId, array $heartbeatEvent): void
    {
        $routeKey = "mobile:heartbeat:route:{$vehicleId}";
        $history = Cache::get($routeKey, []);
        if (!is_array($history)) {
            $history = [];
        }

        $point = [
            'lat' => $this->asFloat($heartbeatEvent['latitude'] ?? null),
            'lng' => $this->asFloat($heartbeatEvent['longitude'] ?? null),
            't' => (string) ($heartbeatEvent['point_timestamp'] ?? $heartbeatEvent['sent_at'] ?? $heartbeatEvent['received_at'] ?? now()->toIso8601String()),
            'is_marked' => false,
            'address' => '',
            'point_label' => (string) ($heartbeatEvent['estado'] ?? 'EN_RUTA'),
            'speed_kmh' => $this->asFloat($heartbeatEvent['speed_kmh'] ?? null),
        ];

        if (is_null($point['lat']) || is_null($point['lng'])) {
            return;
        }

        $last = !empty($history) ? $history[array_key_last($history)] : null;
        $sameAsLast = is_array($last)
            && ((float) ($last['lat'] ?? 0) === (float) $point['lat'])
            && ((float) ($last['lng'] ?? 0) === (float) $point['lng'])
            && ((string) ($last['t'] ?? '') === (string) $point['t']);

        if (!$sameAsLast) {
            $history[] = $point;
        }

        if (count($history) > self::HEARTBEAT_ROUTE_HISTORY_LIMIT) {
            $history = array_slice($history, -self::HEARTBEAT_ROUTE_HISTORY_LIMIT);
        }

        Cache::put($routeKey, array_values($history), now()->addHours(self::HEARTBEAT_TTL_HOURS));
    }

    /**
     * Publica heartbeat en Redis para consumo en tiempo real sin escritura frecuente en DB.
     * Canales:
     * - mobile.location.heartbeat
     * - mobile.location.heartbeat.{vehicle_id}
     *
     * @param array<string, mixed> $payload
     */
    private function publishHeartbeatEvent(array $payload): void
    {
        $channelBase = trim((string) env('MOBILE_HEARTBEAT_CHANNEL', 'mobile.location.heartbeat'));
        if ($channelBase === '') {
            $channelBase = 'mobile.location.heartbeat';
        }

        $vehicleId = (int) ($payload['vehicle_id'] ?? 0);
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            return;
        }

        try {
            Redis::publish($channelBase, $encoded);
            if ($vehicleId > 0) {
                Redis::publish("{$channelBase}.{$vehicleId}", $encoded);
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo publicar heartbeat en Redis.', [
                'channel' => $channelBase,
                'vehicle_id' => $vehicleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function bitacoraSessionCacheKey(int $userId, int $vehicleId, int $sessionId): string
    {
        if ($sessionId > 0) {
            return "mobile:bitacora:session:{$sessionId}";
        }

        return "mobile:bitacora:user:{$userId}:vehicle:{$vehicleId}";
    }

    private function isVehicleInRoute(int $vehicleId): bool
    {
        if ($vehicleId <= 0) {
            return false;
        }

        $heartbeat = Cache::get("mobile:heartbeat:vehicle:{$vehicleId}");
        if (!is_array($heartbeat)) {
            return false;
        }

        $status = strtoupper(trim((string) ($heartbeat['estado'] ?? '')));
        return $status === 'EN_RUTA';
    }

    private function resolveTrackedSession(?string $sessionReference, int $vehicleId, int $driverId): ?VehicleLogSession
    {
        $query = VehicleLogSession::query()
            ->with(['vehicle:id,placa', 'responsibleDriver:id,nombre,user_id', 'currentDriver:id,nombre,user_id'])
            ->whereNull('ended_at')
            ->orderByDesc('id');

        $sessionReference = trim((string) $sessionReference);
        if ($sessionReference !== '') {
            return $query->where('session_reference', $sessionReference)->first();
        }

        if ($vehicleId > 0) {
            $session = (clone $query)->where('vehicle_id', $vehicleId)->first();
            if ($session) {
                return $session;
            }
        }

        if ($driverId > 0) {
            return $query
                ->where(function ($q) use ($driverId) {
                    $q->where('current_driver_id', $driverId)
                        ->orWhere('responsible_driver_id', $driverId);
                })
                ->first();
        }

        return null;
    }

    private function evaluateSessionSuspension(VehicleLogSession $session, ?int $relatedUserId): array
    {
        $heartbeat = Cache::get("mobile:heartbeat:vehicle:{$session->vehicle_id}");
        $receivedAt = is_array($heartbeat) ? $this->parseHeartbeatTime($heartbeat['received_at'] ?? null) : null;
        $lastSeenAt = $receivedAt ?? $session->updated_at ?? $session->started_at;
        $minutesWithoutHeartbeat = $lastSeenAt ? $lastSeenAt->diffInMinutes(now()) : null;
        $isSuspended = $minutesWithoutHeartbeat !== null && $minutesWithoutHeartbeat >= self::HEARTBEAT_SUSPEND_MINUTES;

        $ticket = null;
        $requiresForcedClose = $isSuspended;
        if ($isSuspended) {
            if ($session->status !== 'En Suspenso') {
                $session->update([
                    'status' => 'En Suspenso',
                    'meta_json' => array_merge((array) ($session->meta_json ?? []), [
                        'heartbeat_missing_since' => optional($lastSeenAt)->toIso8601String(),
                        'heartbeat_missing_minutes' => $minutesWithoutHeartbeat,
                    ]),
                ]);

                VehicleLogStageEvent::query()->create([
                    'vehicle_log_session_id' => $session->id,
                    'session_reference' => $session->session_reference,
                    'vehicle_id' => $session->vehicle_id,
                    'responsible_driver_id' => $session->responsible_driver_id,
                    'acting_driver_id' => $session->current_driver_id,
                    'stage_name' => 'SUSPENSION',
                    'event_kind' => 'forced_shutdown_detected',
                    'event_at' => now(),
                    'notes' => 'Bitacora marcada en suspenso por ausencia de heartbeat.',
                    'payload_json' => [
                        'minutes_without_heartbeat' => $minutesWithoutHeartbeat,
                        'last_heartbeat_at' => optional($lastSeenAt)->toIso8601String(),
                    ],
                ]);
            }

            $ticket = $this->openInvestigationTicket($session, $relatedUserId, $minutesWithoutHeartbeat, $lastSeenAt);
            if ($ticket && $relatedUserId) {
                $acknowledgedBy = collect((array) (($ticket->meta_json ?? [])['mobile_acknowledged_by_users'] ?? []))
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->values()
                    ->all();

                if (in_array((int) $relatedUserId, $acknowledgedBy, true)) {
                    $requiresForcedClose = false;
                }
            }
        }

        return [
            'id' => $session->id,
            'session_reference' => $session->session_reference,
            'status' => $isSuspended ? 'En Suspenso' : ((string) ($session->status ?: 'Activa')),
            'vehicle_id' => (int) $session->vehicle_id,
            'plate' => (string) ($session->vehicle?->placa ?? ''),
            'responsible_driver_id' => $session->responsible_driver_id ? (int) $session->responsible_driver_id : null,
            'current_driver_id' => $session->current_driver_id ? (int) $session->current_driver_id : null,
            'last_heartbeat_at' => optional($lastSeenAt)->toIso8601String(),
            'minutes_without_heartbeat' => $minutesWithoutHeartbeat,
            'requires_forced_close' => $requiresForcedClose,
            'ticket' => $ticket ? [
                'id' => (int) $ticket->id,
                'ticket_code' => (string) $ticket->ticket_code,
                'status' => (string) $ticket->status,
                'message' => (string) ($ticket->message ?? ''),
                'packages_total' => (int) ($ticket->packages_total ?? 0),
                'packages_open' => (int) ($ticket->packages_open ?? 0),
                'packages_delivered' => (int) ($ticket->packages_delivered ?? 0),
            ] : null,
        ];
    }

    private function openInvestigationTicket(
        VehicleLogSession $session,
        ?int $relatedUserId,
        ?int $minutesWithoutHeartbeat,
        ?\Illuminate\Support\Carbon $lastSeenAt
    ): VehicleLogInvestigationTicket {
        $existing = VehicleLogInvestigationTicket::query()
            ->where('session_reference', $session->session_reference)
            ->where('reason_type', 'CIERRE_FORZADO')
            ->where('status', '!=', VehicleLogInvestigationTicket::STATUS_CLOSED)
            ->latest('id')
            ->first();

        $packageStats = $this->resolvePackageStatsForSession($session);
        $message = sprintf(
            'Se detecto cierre forzado o abandono de bitacora. Ultimo heartbeat: %s. Tiempo sin latido: %s minuto(s). Revisar paquetes transportados y estado de entrega.',
            optional($lastSeenAt)->format('d/m/Y H:i') ?: 'Sin dato',
            $minutesWithoutHeartbeat ?? 0
        );

        if ($existing) {
            $existing->update([
                'message' => $message,
                'packages_total' => $packageStats['total'],
                'packages_open' => $packageStats['open'],
                'packages_delivered' => $packageStats['delivered'],
                'meta_json' => $packageStats['meta'],
            ]);

            return $existing->fresh();
        }

        $ticket = VehicleLogInvestigationTicket::query()->create([
            'ticket_code' => 'BIT-' . now()->format('YmdHis') . '-' . str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
            'session_reference' => (string) $session->session_reference,
            'vehicle_id' => $session->vehicle_id,
            'responsible_driver_id' => $session->responsible_driver_id,
            'current_driver_id' => $session->current_driver_id,
            'related_user_id' => $relatedUserId,
            'reason_type' => 'CIERRE_FORZADO',
            'status' => VehicleLogInvestigationTicket::STATUS_OPEN,
            'message' => $message,
            'packages_total' => $packageStats['total'],
            'packages_open' => $packageStats['open'],
            'packages_delivered' => $packageStats['delivered'],
            'meta_json' => $packageStats['meta'],
            'opened_at' => now(),
        ]);

        ActivityLog::create([
            'user_id' => $relatedUserId,
            'action' => 'BITACORA_INVESTIGATION_TICKET_OPENED',
            'model' => 'vehicle_log_session',
            'record_id' => $session->id,
            'changes_json' => [
                'ticket_code' => $ticket->ticket_code,
                'session_reference' => $session->session_reference,
                'vehicle_id' => $session->vehicle_id,
                'packages_total' => $packageStats['total'],
                'packages_open' => $packageStats['open'],
                'packages_delivered' => $packageStats['delivered'],
            ],
        ]);

        return $ticket;
    }

    private function resolvePackageStatsForSession(VehicleLogSession $session): array
    {
        $driverUserIds = Driver::query()
            ->whereIn('id', array_values(array_filter([
                (int) ($session->responsible_driver_id ?? 0),
                (int) ($session->current_driver_id ?? 0),
            ])))
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($driverUserIds)) {
            return [
                'total' => 0,
                'open' => 0,
                'delivered' => 0,
                'meta' => [],
            ];
        }

        $deliveredStateIds = Estado::query()
            ->whereRaw('UPPER(nombre_estado) LIKE ?', ['%ENTREGADO%'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $total = Cartero::query()
            ->whereIn('id_user', $driverUserIds)
            ->count();

        $delivered = empty($deliveredStateIds)
            ? 0
            : Cartero::query()->whereIn('id_user', $driverUserIds)->whereIn('id_estados', $deliveredStateIds)->count();

        return [
            'total' => (int) $total,
            'open' => max((int) $total - (int) $delivered, 0),
            'delivered' => (int) $delivered,
            'meta' => [
                'driver_user_ids' => $driverUserIds,
            ],
        ];
    }

    private function parseHeartbeatTime(mixed $value): ?\Illuminate\Support\Carbon
    {
        try {
            return filled($value) ? now()->parse((string) $value) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractQrUrls(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $urls = [];
        $stack = [$raw];
        while (!empty($stack)) {
            $current = array_pop($stack);
            if (is_string($current)) {
                $url = trim($current);
                if (filter_var($url, FILTER_VALIDATE_URL) && Str::startsWith($url, ['http://', 'https://'])) {
                    $urls[] = $url;
                }
                continue;
            }

            if (!is_array($current)) {
                continue;
            }

            foreach (['url', 'qr_url', 'qrUrl', 'invoice_url', 'qr_payload', 'qr'] as $key) {
                if (!array_key_exists($key, $current)) {
                    continue;
                }
                $url = trim((string) $current[$key]);
                if (filter_var($url, FILTER_VALIDATE_URL) && Str::startsWith($url, ['http://', 'https://'])) {
                    $urls[] = $url;
                }
            }

            foreach ($current as $value) {
                if (is_array($value) || is_string($value)) {
                    $stack[] = $value;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    private function normalizeBitacoraTimeline(mixed $timelineRaw, mixed $pointsRaw, mixed $segmentsRaw): array
    {
        $timeline = is_array($timelineRaw) ? $timelineRaw : (is_array($pointsRaw) ? $pointsRaw : []);
        if (!empty($timeline)) {
            return $timeline;
        }

        if (!is_array($segmentsRaw) || empty($segmentsRaw)) {
            return [];
        }

        $normalized = [];
        foreach ($segmentsRaw as $segment) {
            if (!is_array($segment)) {
                continue;
            }

            $from = is_array($segment['from'] ?? null) ? $segment['from'] : null;
            $to = is_array($segment['to'] ?? null) ? $segment['to'] : null;
            $middle = is_array($segment['intermediate_points'] ?? null)
                ? $segment['intermediate_points']
                : (is_array($segment['intermediatePoints'] ?? null) ? $segment['intermediatePoints'] : []);

            if ($from) {
                $point = $this->normalizeSegmentPoint($from);
                if ($point) {
                    $normalized[] = $point;
                }
            }

            foreach ($middle as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $point = $this->normalizeSegmentPoint($item);
                if ($point) {
                    $normalized[] = $point;
                }
            }

            if ($to) {
                $point = $this->normalizeSegmentPoint($to);
                if ($point) {
                    $normalized[] = $point;
                }
            }
        }

        return array_values($normalized);
    }

    private function collapseTimelineToFirstLast(array $timeline): array
    {
        if (count($timeline) < 2) {
            return $timeline;
        }

        $first = $timeline[0];
        $last = $timeline[array_key_last($timeline)];

        return [$first, $last];
    }

    private function mergeFuelSyncResults(array $base, array $extra): array
    {
        return [
            'requested' => (int) ($base['requested'] ?? 0),
            'scraped_ok' => (int) ($base['scraped_ok'] ?? 0) + (int) ($extra['scraped_ok'] ?? 0),
            'saved_ok' => (int) ($base['saved_ok'] ?? 0) + (int) ($extra['saved_ok'] ?? 0),
            'saved_ids' => array_values(array_unique(array_merge(
                is_array($base['saved_ids'] ?? null) ? $base['saved_ids'] : [],
                is_array($extra['saved_ids'] ?? null) ? $extra['saved_ids'] : []
            ))),
            'errors' => array_values(array_merge(
                is_array($base['errors'] ?? null) ? $base['errors'] : [],
                is_array($extra['errors'] ?? null) ? $extra['errors'] : []
            )),
        ];
    }

    private function extractStructuredFuelEntries(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                return [];
            }
        }

        if (!is_array($raw)) {
            return [];
        }

        $entries = [];
        $stack = [$raw];

        while (!empty($stack)) {
            $current = array_pop($stack);
            if (!is_array($current)) {
                continue;
            }

            $hasFuelShape = isset(
                $current['numero_factura'],
                $current['monto_total'],
                $current['cantidad']
            ) || isset(
                $current['invoice_number'],
                $current['total_amount'],
                $current['liters']
            ) || isset(
                $current['station'],
                $current['liters'],
                $current['total_amount']
            ) || isset(
                $current['estacion'],
                $current['litros'],
                $current['monto']
            );

            if ($hasFuelShape) {
                $entries[] = $current;
            }

            foreach ($current as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return $entries;
    }

    private function syncMobileStructuredFuelEntries(Request $request, array $entries, int $vehicleId, int $driverId, array $fuelRouteContext = []): array
    {
        $result = [
            'requested' => count($entries),
            'scraped_ok' => count($entries),
            'saved_ok' => 0,
            'saved_ids' => [],
            'errors' => [],
        ];

        foreach ($entries as $entry) {
            try {
                if (!is_array($entry)) {
                    continue;
                }

                $station = trim((string) ($entry['station'] ?? $entry['estacion'] ?? $entry['razon_social_emisor'] ?? $entry['razonSocialEmisor'] ?? 'SIN ESTACION'));
                $liters = $this->asFloat($entry['liters'] ?? $entry['litros'] ?? $entry['cantidad'] ?? null) ?? 0.0;
                $total = $this->asFloat($entry['total_amount'] ?? $entry['monto_total'] ?? $entry['monto'] ?? $entry['total'] ?? null) ?? 0.0;
                $dateTime = trim((string) ($entry['date_time'] ?? $entry['fecha_emision'] ?? $entry['fecha'] ?? now()->toDateTimeString()));
                $invoiceNumber = trim((string) ($entry['invoice_number'] ?? $entry['numero_factura'] ?? ''));
                $qrPayload = trim((string) ($entry['qr_payload'] ?? $entry['qr'] ?? $entry['qr_url'] ?? $entry['url'] ?? ''));

                if ($liters <= 0 || $total <= 0) {
                    $result['errors'][] = [
                        'stage' => 'normalize',
                        'message' => 'Entrada qrData sin litros/total validos.',
                    ];
                    continue;
                }

                if ($invoiceNumber !== '' && FuelInvoice::query()->where('numero_factura', $invoiceNumber)->exists()) {
                    continue;
                }

                $storeRequest = Request::create('/api/fuel-logs', 'POST', [
                    'station' => $station !== '' ? $station : 'SIN ESTACION',
                    'liters' => $liters,
                    'total_amount' => $total,
                    'date_time' => $dateTime,
                    'invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
                    'customer_name' => (string) ($entry['customer_name'] ?? $entry['nombre_cliente'] ?? 'CONSUMO MOVIL'),
                    'qr_payload' => $qrPayload !== '' ? $qrPayload : null,
                    'vehicle_id' => $vehicleId > 0 ? $vehicleId : null,
                    'driver_id' => $driverId > 0 ? $driverId : null,
                ] + $fuelRouteContext);
                $storeRequest->setUserResolver(fn () => $request->user());

                $storeResponse = app(FuelLogApiController::class)->store($storeRequest);
                $storeStatus = $storeResponse->getStatusCode();
                $storePayload = $storeResponse->getData(true);

                if ($storeStatus >= 400) {
                    $result['errors'][] = [
                        'stage' => 'store',
                        'message' => (string) ($storePayload['message'] ?? 'No se pudo guardar el combustible desde qrData.'),
                    ];
                    continue;
                }

                $result['saved_ok']++;
                $savedId = (int) ($storePayload['id'] ?? 0);
                if ($savedId > 0) {
                    $result['saved_ids'][] = $savedId;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = [
                    'stage' => 'exception',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function normalizeSegmentPoint(array $source): ?array
    {
        $lat = $this->asFloat($source['latitude'] ?? $source['lat'] ?? null);
        $lng = $this->asFloat($source['longitude'] ?? $source['lng'] ?? null);
        if (is_null($lat) || is_null($lng)) {
            return null;
        }

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'lat' => $lat,
            'lng' => $lng,
            'timestamp' => $source['timestamp'] ?? $source['t'] ?? now()->toIso8601String(),
            't' => $source['timestamp'] ?? $source['t'] ?? now()->toIso8601String(),
            'label' => (string) ($source['label'] ?? ''),
            'address' => (string) ($source['address'] ?? ''),
        ];
    }

    public function sessionHealth(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'session_reference' => 'nullable|string|max:120',
            'vehicle_id' => 'nullable|integer|min:1',
        ]);

        $authUser = $request->user();
        $driver = $authUser?->resolvedDriver();
        $session = $this->resolveTrackedSession(
            $payload['session_reference'] ?? null,
            (int) ($payload['vehicle_id'] ?? 0),
            (int) ($driver?->id ?? 0)
        );

        if (!$session) {
            return response()->json([
                'ok' => true,
                'has_issue' => false,
                'session' => null,
                'ticket' => null,
            ]);
        }

        $suspension = $this->evaluateSessionSuspension($session, $authUser?->id);

        return response()->json([
            'ok' => true,
            'has_issue' => $suspension['status'] === 'En Suspenso',
            'session' => $suspension,
            'ticket' => $suspension['ticket'] ?? null,
        ]);
    }

    public function confirmInvestigationTicket(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ticket_id' => 'required|integer|min:1',
        ]);

        $authUser = $request->user();
        $ticket = VehicleLogInvestigationTicket::query()->findOrFail((int) $payload['ticket_id']);
        $meta = (array) ($ticket->meta_json ?? []);
        $acknowledgedBy = (array) ($meta['mobile_acknowledged_by_users'] ?? []);
        $userId = (int) ($authUser?->id ?? 0);

        if ($userId > 0 && !in_array($userId, $acknowledgedBy, true)) {
            $acknowledgedBy[] = $userId;
        }

        $meta['mobile_acknowledged_by_users'] = array_values(array_unique(array_filter(array_map('intval', $acknowledgedBy))));
        $meta['mobile_acknowledged_at'] = now()->toIso8601String();

        $ticket->update([
            'status' => VehicleLogInvestigationTicket::STATUS_REVIEWING,
            'meta_json' => $meta,
        ]);

        ActivityLog::create([
            'user_id' => $userId > 0 ? $userId : null,
            'action' => 'BITACORA_INVESTIGATION_TICKET_CONFIRMED_MOBILE',
            'model' => 'vehicle_log_investigation_ticket',
            'record_id' => $ticket->id,
            'changes_json' => [
                'ticket_code' => $ticket->ticket_code,
                'status' => $ticket->status,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Ticket confirmado correctamente.',
            'ticket_id' => (int) $ticket->id,
            'ticket_code' => (string) $ticket->ticket_code,
            'status' => (string) $ticket->status,
        ]);
    }

    private function resolveFuelRouteContextFromTimeline(array $timeline): array
    {
        if (empty($timeline)) {
            return [];
        }

        $first = is_array($timeline[0] ?? null) ? $timeline[0] : null;
        $last = is_array($timeline[array_key_last($timeline)] ?? null) ? $timeline[array_key_last($timeline)] : null;

        if (!$first && !$last) {
            return [];
        }

        $startLabel = $this->resolveTimelinePointLabel($first, 'Punto de inicio');
        $endLabel = $this->resolveTimelinePointLabel($last, 'Punto final');
        $startLat = $this->asFloat($first['latitude'] ?? $first['lat'] ?? null);
        $startLng = $this->asFloat($first['longitude'] ?? $first['lng'] ?? null);
        $endLat = $this->asFloat($last['latitude'] ?? $last['lat'] ?? null);
        $endLng = $this->asFloat($last['longitude'] ?? $last['lng'] ?? null);

        return array_filter([
            'location_label' => $endLabel,
            'route_start_label' => $startLabel,
            'route_end_label' => $endLabel,
            'route_start_latitude' => $startLat,
            'route_start_longitude' => $startLng,
            'route_end_latitude' => $endLat,
            'route_end_longitude' => $endLng,
            'latitude' => $endLat,
            'longitude' => $endLng,
        ], fn ($value) => !is_null($value) && $value !== '');
    }

    private function resolveTimelinePointLabel(?array $point, string $fallback): string
    {
        if (!$point) {
            return $fallback;
        }

        $address = trim((string) ($point['address'] ?? ''));
        if ($address !== '') {
            return $address;
        }

        $label = trim((string) ($point['label'] ?? ''));
        if ($label !== '') {
            return $label;
        }

        $lat = $this->asFloat($point['latitude'] ?? $point['lat'] ?? null);
        $lng = $this->asFloat($point['longitude'] ?? $point['lng'] ?? null);
        if (!is_null($lat) && !is_null($lng)) {
            return sprintf('%.6f, %.6f', $lat, $lng);
        }

        return $fallback;
    }

    private function syncMobileQrDataAsFuel(Request $request, array $urls, int $vehicleId, int $driverId, array $fuelRouteContext = []): array
    {
        $result = [
            'requested' => count($urls),
            'scraped_ok' => 0,
            'saved_ok' => 0,
            'saved_ids' => [],
            'errors' => [],
        ];

        foreach ($urls as $url) {
            try {
                $scrapeRequest = Request::create('/api/fuel-logs/scrape-from-qr', 'POST', ['url' => $url]);
                $scrapeRequest->setUserResolver(fn () => $request->user());
                $scrapeResponse = app(FuelScrapeApiController::class)->scrapeFromQr($scrapeRequest);
                $scrapeStatus = $scrapeResponse->getStatusCode();
                $scrapePayload = $scrapeResponse->getData(true);

                if ($scrapeStatus >= 400 || !is_array($scrapePayload) || empty($scrapePayload['success'])) {
                    $result['errors'][] = [
                        'url' => $url,
                        'stage' => 'scrape',
                        'message' => (string) ($scrapePayload['message'] ?? 'No se pudo extraer la factura desde QR.'),
                    ];
                    continue;
                }

                $result['scraped_ok']++;
                $scrapedData = is_array($scrapePayload['data'] ?? null) ? $scrapePayload['data'] : [];

                $invoiceNumber = trim((string) ($scrapedData['numero_factura'] ?? ''));
                if ($invoiceNumber !== '' && FuelInvoice::query()->where('numero_factura', $invoiceNumber)->exists()) {
                    continue;
                }

                $liters = $this->extractLitersFromScrapedData($scrapedData);
                $total = $this->extractTotalFromScrapedData($scrapedData, $liters);
                $station = trim((string) ($scrapedData['razonSocialEmisor'] ?? '')) ?: 'SIN ESTACION';
                $dateTime = trim((string) ($scrapedData['fecha_emision'] ?? '')) ?: now()->toDateTimeString();

                if ($liters <= 0 || $total <= 0) {
                    $result['errors'][] = [
                        'url' => $url,
                        'stage' => 'normalize',
                        'message' => 'Factura sin litros/total validos para registrar combustible.',
                    ];
                    continue;
                }

                $storeRequest = Request::create('/api/fuel-logs', 'POST', [
                    'station' => $station,
                    'liters' => $liters,
                    'total_amount' => $total,
                    'date_time' => $dateTime,
                    'invoice_number' => $invoiceNumber !== '' ? $invoiceNumber : null,
                    'customer_name' => (string) ($scrapedData['nombre_cliente'] ?? 'CONSUMO MOVIL'),
                    'qr_payload' => $url,
                    'vehicle_id' => $vehicleId > 0 ? $vehicleId : null,
                    'driver_id' => $driverId > 0 ? $driverId : null,
                ] + $fuelRouteContext);
                $storeRequest->setUserResolver(fn () => $request->user());

                $storeResponse = app(FuelLogApiController::class)->store($storeRequest);
                $storeStatus = $storeResponse->getStatusCode();
                $storePayload = $storeResponse->getData(true);

                if ($storeStatus >= 400) {
                    $result['errors'][] = [
                        'url' => $url,
                        'stage' => 'store',
                        'message' => (string) ($storePayload['message'] ?? 'No se pudo guardar el combustible.'),
                    ];
                    continue;
                }

                $result['saved_ok']++;
                $savedId = (int) ($storePayload['id'] ?? 0);
                if ($savedId > 0) {
                    $result['saved_ids'][] = $savedId;
                }
            } catch (\Throwable $e) {
                Log::warning('Error procesando qr_data en bitacoraLoad', [
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                $result['errors'][] = [
                    'url' => $url,
                    'stage' => 'exception',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $result;
    }

    private function extractLitersFromScrapedData(array $data): float
    {
        $liters = $this->asFloat($data['cantidad'] ?? $data['galones'] ?? null);
        if (!is_null($liters) && $liters > 0) {
            return $liters;
        }

        $details = is_array($data['details'] ?? null) ? $data['details'] : [];
        $sum = 0.0;
        foreach ($details as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sum += (float) ($this->asFloat($row['cantidad'] ?? null) ?? 0.0);
        }

        return $sum > 0 ? $sum : 0.0;
    }

    private function extractTotalFromScrapedData(array $data, float $liters): float
    {
        $total = $this->asFloat($data['monto_total'] ?? $data['total_calculado'] ?? null);
        if (!is_null($total) && $total > 0) {
            return $total;
        }

        $details = is_array($data['details'] ?? null) ? $data['details'] : [];
        $sum = 0.0;
        foreach ($details as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowTotal = $this->asFloat($row['subtotal'] ?? null);
            if (!is_null($rowTotal)) {
                $sum += $rowTotal;
            }
        }

        if ($sum > 0) {
            return $sum;
        }

        $unitPrice = $this->asFloat($data['precio_unitario'] ?? $data['precio_galon'] ?? null);
        if (!is_null($unitPrice) && $unitPrice > 0 && $liters > 0) {
            return round($unitPrice * $liters, 2);
        }

        return 0.0;
    }
}

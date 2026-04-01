<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MobileDbSnapshot;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLog;
use App\Models\VehicleLogSession;
use App\Models\VehicleOperationAlert;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MapController extends Controller
{
    private const LIVE_STALE_SECONDS = 20;

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (!in_array($user->role, ['admin', 'recepcion', 'conductor'], true)) {
            abort(403);
        }

        return view('map.index');
    }

    public function data(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 403);

        $mode = mb_strtolower(trim((string) $request->query('mode', 'online')));
        if (!in_array($mode, ['online', 'offline'], true)) {
            $mode = 'online';
        }

        $selectedDate = $this->resolveSelectedDate((string) $request->query('date', ''));

        $onlineVehicles = $this->annotateStaleness($this->mergeHeartbeatVehicles([], $user));
        $alerts = $this->syncAndBuildOperationalAlerts($onlineVehicles);

        if ($mode === 'offline') {
            $vehicles = $this->annotateStaleness($this->buildOfflineVehicles($user, $selectedDate));

            return response()->json([
                'mode' => 'offline',
                'selected_date' => $selectedDate->toDateString(),
                'updated_at' => now()->toIso8601String(),
                'vehicles' => $vehicles,
                'alerts' => $alerts,
            ]);
        }

        $vehicles = $onlineVehicles;

        return response()->json([
            'mode' => 'online',
            'selected_date' => null,
            'updated_at' => now()->toIso8601String(),
            'vehicles' => $vehicles,
            'alerts' => $alerts,
        ]);
    }

    /**
     * Build "offline" route view from latest mobile snapshots (LOCAL_DB payloads).
     * @return array<int, array<string, mixed>>
     */
    private function buildOfflineVehicles($user, Carbon $selectedDate): array
    {
        $logsQuery = VehicleLog::query()
            ->with(['vehicle', 'driver'])
            ->whereDate('fecha', $selectedDate->toDateString())
            ->orderBy('fecha')
            ->orderBy('created_at')
            ->orderBy('id');

        if ($user->role === 'conductor') {
            $driverId = (int) ($user?->resolvedDriver()?->id ?? 0);
            if ($driverId > 0) {
                $logsQuery->where('drivers_id', $driverId);
            } else {
                return [];
            }
        }

        $logs = $logsQuery->get();
        if ($logs->isNotEmpty()) {
            return $logs
                ->groupBy(fn (VehicleLog $log) => (int) ($log->vehicles_id ?? 0))
                ->map(fn (Collection $group) => $this->buildOfflineVehicleFromLogs($group, $selectedDate))
                ->filter()
                ->values()
                ->all();
        }

        return $this->buildOfflineVehiclesFromSnapshots($user, $selectedDate);
    }

    /**
     * Fallback historico usando snapshots locales si no existen bitacoras del dia.
     * @return array<int, array<string, mixed>>
     */
    private function buildOfflineVehiclesFromSnapshots($user, Carbon $selectedDate): array
    {
        $snapshotsQuery = MobileDbSnapshot::query()
            ->orderByDesc('id')
            ->limit(500);

        if ($user->role === 'conductor') {
            $snapshotsQuery->where('user_id', (int) $user->id);
        }

        $snapshots = $snapshotsQuery->get();
        if ($snapshots->isEmpty()) {
            return [];
        }

        $userIds = $snapshots->pluck('user_id')->filter()->unique()->values();
        $driversByUser = Driver::query()
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');

        $driverIds = $driversByUser->pluck('id')->values();
        $assignmentByDriver = VehicleAssignment::query()
            ->whereIn('driver_id', $driverIds)
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->groupBy('driver_id')
            ->map(fn (Collection $rows) => $rows->first());

        $vehicleIds = $assignmentByDriver->pluck('vehicle_id')->filter()->unique()->values();
        $vehiclesById = Vehicle::query()->whereIn('id', $vehicleIds)->get()->keyBy('id');

        $acc = [];

        foreach ($snapshots as $snapshot) {
            $snapshotDate = $snapshot->sent_at instanceof Carbon
                ? $snapshot->sent_at
                : ($snapshot->created_at instanceof Carbon ? $snapshot->created_at : null);
            if ($snapshotDate && !$snapshotDate->copy()->setTimezone(config('app.timezone'))->isSameDay($selectedDate)) {
                continue;
            }

            $payload = $this->decodeSnapshotPayload($snapshot->payload_json);
            if (empty($payload)) {
                continue;
            }

            $points = $this->extractSnapshotRoutePoints($payload);
            $points = array_values(array_filter($points, fn (array $p) => $this->isPointFromDate($p, $selectedDate)));
            if (empty($points)) {
                continue;
            }

            $snapshotUserId = (int) ($snapshot->user_id ?? $payload['user_id'] ?? 0);
            if ($snapshotUserId <= 0) {
                continue;
            }

            $driver = $driversByUser->get($snapshotUserId);
            if (!$driver) {
                continue;
            }

            $assignment = $assignmentByDriver->get($driver->id);
            $vehicleId = (int) ($assignment->vehicle_id ?? 0);
            $vehicle = $vehiclesById->get($vehicleId);
            $key = $driver->id . ':' . $vehicleId;

            if (!isset($acc[$key])) {
                $acc[$key] = [
                    'vehicle_id' => $vehicleId,
                    'placa' => (string) ($vehicle->placa ?? 'SIN PLACA'),
                    'marca' => (string) ($vehicle->marca ?? ''),
                    'modelo' => (string) ($vehicle->modelo ?? ''),
                    'driver_id' => (int) $driver->id,
                    'driver_name' => (string) ($driver->nombre ?? 'SIN CONDUCTOR'),
                    'log_id' => null,
                    'fecha' => optional($snapshot->sent_at)->toDateString(),
                    'recorrido_inicio' => 'Offline snapshot',
                    'recorrido_destino' => 'Offline snapshot',
                    'current_address' => 'Sin direccion',
                    'last_point' => null,
                    'points' => [],
                    'points_count' => 0,
                    'marked_points' => [],
                    'offline_segments' => [],
                    'heartbeat_received_at' => null,
                    'current_speed_kmh' => null,
                ];
            }

            $existing = collect($acc[$key]['points'])->keyBy(function ($p) {
                return implode('|', [(string) ($p['lat'] ?? ''), (string) ($p['lng'] ?? ''), (string) ($p['t'] ?? '')]);
            });

            foreach ($points as $p) {
                $k = implode('|', [(string) $p['lat'], (string) $p['lng'], (string) ($p['t'] ?? '')]);
                $prev = $existing->get($k);
                if (!$prev || (!empty($p['is_marked']) && empty($prev['is_marked']))) {
                    $existing->put($k, $p);
                }
            }

            $merged = $existing->values()->all();
            usort($merged, function ($a, $b) {
                return strcmp((string) ($a['t'] ?? ''), (string) ($b['t'] ?? ''));
            });

            $acc[$key]['points'] = $merged;
            $acc[$key]['last_point'] = !empty($merged) ? $merged[array_key_last($merged)] : null;
            $acc[$key]['marked_points'] = array_values(array_filter($merged, fn ($p) => !empty($p['is_marked'])));
            $acc[$key]['offline_segments'] = $this->buildOfflineSegments($merged);
            $acc[$key]['points_count'] = count($merged);
            $acc[$key]['current_address'] = (string) (($acc[$key]['last_point']['address'] ?? '') ?: 'Sin direccion');
        }

        return array_values(array_filter($acc, fn ($v) => !empty($v['last_point'])));
    }

    private function buildOfflineVehicleFromLogs(Collection $group, Carbon $selectedDate): ?array
    {
        if ($group->isEmpty()) {
            return null;
        }

        $sortedLogs = $group->sort(function (VehicleLog $a, VehicleLog $b) {
            $aTime = optional($a->created_at)->getTimestamp() ?? 0;
            $bTime = optional($b->created_at)->getTimestamp() ?? 0;
            if ($aTime === $bTime) {
                return (int) $a->id <=> (int) $b->id;
            }

            return $aTime <=> $bTime;
        })->values();

        /** @var VehicleLog $firstLog */
        $firstLog = $sortedLogs->first();
        /** @var VehicleLog $lastLog */
        $lastLog = $sortedLogs->last();

        $mergedPoints = [];
        $pointIndex = [];
        $markedPoints = [];
        $offlineSegments = [];
        $tripSummaries = [];

        foreach ($sortedLogs as $log) {
            $logPoints = $this->normalizePointsWithFallback($log);
            if (empty($logPoints)) {
                continue;
            }

            foreach ($logPoints as $point) {
                $pointKey = implode('|', [
                    (string) ($point['lat'] ?? ''),
                    (string) ($point['lng'] ?? ''),
                    (string) ($point['t'] ?? ''),
                ]);
                if (!isset($pointIndex[$pointKey])) {
                    $pointIndex[$pointKey] = true;
                    $mergedPoints[] = $point;
                } elseif (!empty($point['is_marked'])) {
                    foreach ($mergedPoints as $idx => $existingPoint) {
                        $existingKey = implode('|', [
                            (string) ($existingPoint['lat'] ?? ''),
                            (string) ($existingPoint['lng'] ?? ''),
                            (string) ($existingPoint['t'] ?? ''),
                        ]);
                        if ($existingKey === $pointKey) {
                            $mergedPoints[$idx]['is_marked'] = true;
                            $mergedPoints[$idx]['point_label'] = (string) ($point['point_label'] ?? $mergedPoints[$idx]['point_label'] ?? '');
                            $mergedPoints[$idx]['address'] = (string) ($point['address'] ?? $mergedPoints[$idx]['address'] ?? '');
                            break;
                        }
                    }
                }
            }

            $segmentPoints = array_values($logPoints);
            $segmentStart = $segmentPoints[0];
            $segmentEnd = $segmentPoints[array_key_last($segmentPoints)];
            $offlineSegments[] = [
                'log_id' => (int) $log->id,
                'from' => $segmentStart,
                'to' => $segmentEnd,
                'points' => $segmentPoints,
                'recorrido_inicio' => (string) ($log->recorrido_inicio ?? ''),
                'recorrido_destino' => (string) ($log->recorrido_destino ?? ''),
                'driver_name' => (string) ($log->driver?->nombre ?? 'SIN CONDUCTOR'),
                'created_at' => optional($log->created_at)->toIso8601String(),
            ];

            $tripSummaries[] = [
                'log_id' => (int) $log->id,
                'recorrido_inicio' => (string) ($log->recorrido_inicio ?? 'Sin origen'),
                'recorrido_destino' => (string) ($log->recorrido_destino ?? 'Sin destino'),
                'driver_name' => (string) ($log->driver?->nombre ?? 'SIN CONDUCTOR'),
                'points_count' => count($segmentPoints),
                'created_at' => optional($log->created_at)->toIso8601String(),
            ];
        }

        if (empty($mergedPoints)) {
            return null;
        }

        usort($mergedPoints, function (array $a, array $b) {
            return strcmp((string) ($a['t'] ?? ''), (string) ($b['t'] ?? ''));
        });

        $markedPoints = array_values(array_filter($mergedPoints, fn (array $point) => !empty($point['is_marked'])));
        $lastPoint = $mergedPoints[array_key_last($mergedPoints)];

        return [
            'vehicle_id' => (int) ($lastLog->vehicles_id ?? 0),
            'placa' => (string) ($lastLog->vehicle?->placa ?? 'SIN PLACA'),
            'marca' => (string) ($lastLog->vehicle?->marca ?? ''),
            'modelo' => (string) ($lastLog->vehicle?->modelo ?? ''),
            'driver_id' => (int) ($lastLog->drivers_id ?? 0),
            'driver_name' => (string) ($lastLog->driver?->nombre ?? 'SIN CONDUCTOR'),
            'log_id' => (int) $lastLog->id,
            'fecha' => $selectedDate->toDateString(),
            'recorrido_inicio' => (string) ($firstLog->recorrido_inicio ?? 'Sin origen'),
            'recorrido_destino' => (string) ($lastLog->recorrido_destino ?? 'Sin destino'),
            'current_address' => (string) (($lastPoint['address'] ?? '') ?: ($lastLog->recorrido_destino ?? 'Sin direccion')),
            'last_point' => $lastPoint,
            'points' => $mergedPoints,
            'points_count' => count($mergedPoints),
            'marked_points' => $markedPoints,
            'offline_segments' => $offlineSegments,
            'heartbeat_received_at' => null,
            'current_speed_kmh' => null,
            'trip_count' => count($tripSummaries),
            'trip_summaries' => $tripSummaries,
        ];
    }

    private function buildVehiclePayload(Collection $group): ?array
    {
        if ($group->isEmpty()) {
            return null;
        }

        $latest = $group->first();
        $withRoute = $group->first(function (VehicleLog $log) {
            return is_array($log->ruta_json) && count($log->ruta_json) > 0;
        });
        $sourceLog = $withRoute ?: $latest;

        $points = $this->normalizePoints($sourceLog);
        $lastPoint = !empty($points) ? $points[array_key_last($points)] : null;

        if (!$lastPoint) {
            $fallbackLat = $this->asFloat($sourceLog->latitud_destino) ?? $this->asFloat($sourceLog->latitud_inicio);
            $fallbackLng = $this->asFloat($sourceLog->logitud_destino) ?? $this->asFloat($sourceLog->logitud_inicio);
            if (!is_null($fallbackLat) && !is_null($fallbackLng)) {
                $lastPoint = ['lat' => $fallbackLat, 'lng' => $fallbackLng, 't' => null, 'is_marked' => false];
                if (empty($points)) {
                    $points[] = $lastPoint;
                }
            }
        }

        if (!$lastPoint) {
            return null;
        }

        if (empty($lastPoint['t'])) {
            $lastPoint['t'] = optional($latest->fecha)->toIso8601String()
                ?? optional($latest->updated_at)->toIso8601String()
                ?? optional($latest->created_at)->toIso8601String()
                ?? now()->toIso8601String();
        }

        return [
            'vehicle_id' => (int) ($latest->vehicles_id ?? 0),
            'placa' => (string) ($latest->vehicle?->placa ?? 'SIN PLACA'),
            'marca' => (string) ($latest->vehicle?->marca ?? ''),
            'modelo' => (string) ($latest->vehicle?->modelo ?? ''),
            'driver_id' => (int) ($latest->drivers_id ?? 0),
            'driver_name' => (string) ($latest->driver?->nombre ?? 'SIN CONDUCTOR'),
            'log_id' => (int) $latest->id,
            'fecha' => optional($latest->fecha)->toDateString(),
            'recorrido_inicio' => (string) ($latest->recorrido_inicio ?? ''),
            'recorrido_destino' => (string) ($latest->recorrido_destino ?? ''),
            'current_address' => (string) (
                ($lastPoint['address'] ?? '')
                ?: ($latest->recorrido_destino ?? '')
                ?: 'Sin direccion'
            ),
            'last_point' => $lastPoint,
            'points' => $points,
            'points_count' => count($points),
            'marked_points' => array_values(array_filter($points, fn (array $p) => !empty($p['is_marked']))),
            'heartbeat_received_at' => null,
            'current_speed_kmh' => null,
        ];
    }

    private function normalizePoints(VehicleLog $log): array
    {
        $raw = is_array($log->ruta_json) ? $log->ruta_json : [];
        $points = [];

        foreach ($raw as $point) {
            if (!is_array($point)) {
                continue;
            }

            $lat = $this->asFloat($point['lat'] ?? $point['latitude'] ?? null);
            $lng = $this->asFloat($point['lng'] ?? $point['longitude'] ?? null);
            if (is_null($lat) || is_null($lng)) {
                continue;
            }

            $t = $point['t'] ?? $point['timestamp'] ?? null;
            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
                't' => is_scalar($t) ? (string) $t : null,
                'is_marked' => !empty($point['is_marked']) || !empty($point['marked']) || !empty($point['isMarked']),
                'point_label' => (string) ($point['point_label'] ?? $point['label'] ?? ''),
                'address' => (string) ($point['address'] ?? ''),
            ];
        }

        return $points;
    }

    private function normalizePointsWithFallback(VehicleLog $log): array
    {
        $points = $this->normalizePoints($log);
        if (!empty($points)) {
            return $points;
        }

        $startLat = $this->asFloat($log->latitud_inicio);
        $startLng = $this->asFloat($log->logitud_inicio);
        $endLat = $this->asFloat($log->latitud_destino);
        $endLng = $this->asFloat($log->logitud_destino);
        $baseTime = optional($log->created_at)->toIso8601String()
            ?? optional($log->updated_at)->toIso8601String()
            ?? optional($log->fecha)->toIso8601String()
            ?? now()->toIso8601String();

        $fallback = [];
        if (!is_null($startLat) && !is_null($startLng)) {
            $fallback[] = [
                'lat' => $startLat,
                'lng' => $startLng,
                't' => $baseTime,
                'is_marked' => true,
                'point_label' => 'Inicio',
                'address' => (string) ($log->recorrido_inicio ?? ''),
            ];
        }

        if (!is_null($endLat) && !is_null($endLng)) {
            $fallback[] = [
                'lat' => $endLat,
                'lng' => $endLng,
                't' => optional($log->updated_at)->toIso8601String() ?? $baseTime,
                'is_marked' => true,
                'point_label' => 'Destino',
                'address' => (string) ($log->recorrido_destino ?? ''),
            ];
        }

        return $fallback;
    }

    private function decodeSnapshotPayload(?string $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return [];
        }

        if (isset($payload['changes_json']) && is_string($payload['changes_json'])) {
            $decoded = json_decode($payload['changes_json'], true);
            if (is_array($decoded)) {
                $payload['changes_json'] = $decoded;
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function extractSnapshotRoutePoints(array $payload): array
    {
        $rowsCandidates = [
            $payload['tables']['route_points']['rows'] ?? null,
            $payload['changes_json']['tables']['route_points']['rows'] ?? null,
            $payload['route_points']['rows'] ?? null,
            $payload['local_sqlite']['full_data']['route_points']['rows'] ?? null,
            $payload['local_sqlite']['data']['points'] ?? null,
        ];

        $rows = [];
        foreach ($rowsCandidates as $candidate) {
            if (is_array($candidate)) {
                $rows = $candidate;
                break;
            }
        }

        if (empty($rows)) {
            return [];
        }

        $points = [];
        foreach ($rows as $item) {
            if (!is_array($item)) {
                continue;
            }

            $lat = $this->asFloat($item['latitude'] ?? $item['lat'] ?? null);
            $lng = $this->asFloat($item['longitude'] ?? $item['lng'] ?? null);
            if (is_null($lat) || is_null($lng)) {
                continue;
            }

            $timestamp = $item['timestamp'] ?? $item['t'] ?? $item['created_at'] ?? now()->toIso8601String();
            $points[] = [
                'lat' => $lat,
                'lng' => $lng,
                't' => is_scalar($timestamp) ? (string) $timestamp : now()->toIso8601String(),
                'is_marked' => !empty($item['is_marked']) || !empty($item['marked']) || !empty($item['isMarked']),
                'point_label' => (string) ($item['point_label'] ?? $item['label'] ?? ''),
                'address' => (string) ($item['address'] ?? ''),
            ];
        }

        return $points;
    }

    private function asFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param array<string, mixed> $point
     */
    private function isPointFromDate(array $point, Carbon $selectedDate): bool
    {
        $raw = $point['t'] ?? null;
        if (is_null($raw) || $raw === '') {
            return $selectedDate->isSameDay(now(config('app.timezone')));
        }

        $time = null;
        if (is_numeric($raw)) {
            $numeric = (float) $raw;
            $time = $numeric > 1000000000000
                ? Carbon::createFromTimestampMs((int) $numeric)
                : Carbon::createFromTimestamp((int) $numeric);
        } elseif (is_string($raw)) {
            try {
                $time = Carbon::parse($raw);
            } catch (\Throwable) {
                $time = null;
            }
        }

        if (!$time) {
            return $selectedDate->isSameDay(now(config('app.timezone')));
        }

        return $time->setTimezone(config('app.timezone'))->isSameDay($selectedDate);
    }

    private function resolveSelectedDate(string $raw): Carbon
    {
        $value = trim($raw);
        if ($value !== '') {
            try {
                return Carbon::parse($value, config('app.timezone'))->startOfDay();
            } catch (\Throwable) {
                // Fallback a hoy si el formato no es valido.
            }
        }

        return now(config('app.timezone'))->startOfDay();
    }

    /**
     * @param array<int, array<string, mixed>> $points
     * @return array<int, array<string, mixed>>
     */
    private function buildOfflineSegments(array $points): array
    {
        if (count($points) < 2) {
            return [];
        }

        $anchors = array_values(array_filter($points, fn (array $point) => !empty($point['is_marked'])));
        if (count($anchors) < 2) {
            return [[
                'from' => $points[0],
                'to' => $points[array_key_last($points)],
                'points' => $points,
            ]];
        }

        $segments = [];
        for ($i = 0; $i < count($anchors) - 1; $i++) {
            $from = $anchors[$i];
            $to = $anchors[$i + 1];
            $fromTime = (string) ($from['t'] ?? '');
            $toTime = (string) ($to['t'] ?? '');

            $segmentPoints = array_values(array_filter($points, function (array $point) use ($fromTime, $toTime) {
                $pointTime = (string) ($point['t'] ?? '');
                if ($pointTime === '') {
                    return false;
                }

                return $pointTime >= $fromTime && $pointTime <= $toTime;
            }));

            if (count($segmentPoints) < 2) {
                $segmentPoints = [$from, $to];
            }

            $segments[] = [
                'from' => $from,
                'to' => $to,
                'points' => $segmentPoints,
            ];
        }

        return $segments;
    }

    /**
     * @param array<int, array<string, mixed>> $vehicles
     * @return array<int, array<string, mixed>>
     */
    private function mergeHeartbeatVehicles(array $vehicles, $user): array
    {
        $index = Cache::get('mobile:heartbeat:index', []);
        $vehicleIds = is_array($index)
            ? array_values(array_unique(array_filter(array_map('intval', $index), fn ($id) => $id > 0)))
            : [];

        if (Schema::hasTable('vehicle_log_sessions')) {
            $openSessionVehicleIds = VehicleLogSession::query()
                ->whereNull('ended_at')
                ->pluck('vehicle_id')
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $vehicleIds = array_values(array_unique(array_merge($vehicleIds, $openSessionVehicleIds)));
        }

        if (empty($vehicleIds)) {
            return $vehicles;
        }

        $vehiclesById = collect($vehicles)->keyBy(fn ($v) => (int) ($v['vehicle_id'] ?? 0));
        $vehicleModels = Vehicle::query()->whereIn('id', $vehicleIds)->get()->keyBy('id');
        $driverByVehicle = VehicleAssignment::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get()
            ->groupBy('vehicle_id')
            ->map(fn (Collection $rows) => $rows->first());

        $heartbeatByVehicle = [];
        $heartbeatDriverIds = [];
        $heartbeatUserIds = [];
        foreach ($vehicleIds as $vehicleId) {
            $hb = Cache::get("mobile:heartbeat:vehicle:{$vehicleId}");
            if (!is_array($hb)) {
                continue;
            }
            $heartbeatByVehicle[$vehicleId] = $hb;
            $hbDriverId = (int) ($hb['driver_id'] ?? 0);
            $hbUserId = (int) ($hb['user_id'] ?? 0);
            if ($hbDriverId > 0) {
                $heartbeatDriverIds[] = $hbDriverId;
            }
            if ($hbUserId > 0) {
                $heartbeatUserIds[] = $hbUserId;
            }
        }

        $assignmentDriverIds = $driverByVehicle->pluck('driver_id')->filter()->map(fn ($v) => (int) $v)->values()->all();
        $driverIds = array_values(array_unique(array_merge($assignmentDriverIds, $heartbeatDriverIds)));
        $drivers = empty($driverIds) ? collect() : Driver::query()->whereIn('id', $driverIds)->get()->keyBy('id');
        $driversByUser = empty($heartbeatUserIds)
            ? collect()
            : Driver::query()->whereIn('user_id', array_values(array_unique($heartbeatUserIds)))->get()->keyBy('user_id');
        $currentDriverId = (int) ($user?->resolvedDriver()?->id ?? 0);

        foreach ($vehicleIds as $vehicleId) {
            $hb = $heartbeatByVehicle[$vehicleId] ?? null;
            if (!is_array($hb)) {
                continue;
            }

            $lat = $this->asFloat($hb['latitude'] ?? $hb['lat'] ?? null);
            $lng = $this->asFloat($hb['longitude'] ?? $hb['lng'] ?? null);
            if (is_null($lat) || is_null($lng)) {
                continue;
            }

            $assignment = $driverByVehicle->get($vehicleId);
            $driverId = (int) ($hb['driver_id'] ?? ($assignment->driver_id ?? 0));
            if ($driverId <= 0) {
                $hbUserId = (int) ($hb['user_id'] ?? 0);
                if ($hbUserId > 0) {
                    $driverId = (int) ($driversByUser->get($hbUserId)?->id ?? 0);
                }
            }
            if ($user?->role === 'conductor' && $currentDriverId > 0) {
                if ($driverId <= 0 || $driverId !== $currentDriverId) {
                    continue;
                }
            }

            $driver = $drivers->get($driverId);
            if (!$driver && $driverId > 0) {
                $driver = Driver::query()->find($driverId);
            }
            $vehicle = $vehicleModels->get($vehicleId);
            $lastPoint = [
                'lat' => $lat,
                'lng' => $lng,
                't' => (string) ($hb['point_timestamp'] ?? $hb['sent_at'] ?? $hb['received_at'] ?? now()->toIso8601String()),
                'is_marked' => false,
                'address' => '',
                'point_label' => (string) ($hb['estado'] ?? ($hb['waiting_stop'] ?? false ? 'ESPERA' : 'EN_RUTA')),
                'speed_kmh' => $this->asFloat($hb['speed_kmh'] ?? null),
            ];
            $currentStatus = strtoupper(trim((string) ($lastPoint['point_label'] ?? 'EN_RUTA')));
            $gpsEnabled = array_key_exists('gps_enabled', $hb) ? (bool) $hb['gps_enabled'] : true;
            $gpsMocked = array_key_exists('gps_mocked', $hb) ? (bool) $hb['gps_mocked'] : false;
            $heartbeatRoute = Cache::get("mobile:heartbeat:route:{$vehicleId}", []);
            $heartbeatPoints = collect(is_array($heartbeatRoute) ? $heartbeatRoute : [])
                ->map(function ($point) {
                    if (!is_array($point)) {
                        return null;
                    }

                    $lat = $this->asFloat($point['lat'] ?? $point['latitude'] ?? null);
                    $lng = $this->asFloat($point['lng'] ?? $point['longitude'] ?? null);
                    if (is_null($lat) || is_null($lng)) {
                        return null;
                    }

                    return [
                        'lat' => $lat,
                        'lng' => $lng,
                        't' => (string) ($point['t'] ?? $point['timestamp'] ?? ''),
                        'is_marked' => false,
                        'address' => (string) ($point['address'] ?? ''),
                        'point_label' => (string) ($point['point_label'] ?? $point['label'] ?? ''),
                        'speed_kmh' => $this->asFloat($point['speed_kmh'] ?? null),
                    ];
                })
                ->filter()
                ->values()
                ->all();

            $existing = $vehiclesById->get($vehicleId);
            if (is_array($existing)) {
                $existing['last_point'] = $lastPoint;
                $existing['current_address'] = $lastPoint['point_label'];
                $existing['current_status'] = $currentStatus;
                $existing['heartbeat_received_at'] = (string) ($hb['received_at'] ?? '');
                $existing['current_speed_kmh'] = $this->asFloat($hb['speed_kmh'] ?? null);
                $existing['gps_enabled'] = $gpsEnabled;
                $existing['gps_mocked'] = $gpsMocked;
                if (($existing['driver_id'] ?? 0) <= 0 && $driverId > 0) {
                    $existing['driver_id'] = $driverId;
                }
                if (($existing['driver_name'] ?? '') === 'SIN CONDUCTOR' && $driver) {
                    $existing['driver_name'] = (string) ($driver->nombre ?? 'SIN CONDUCTOR');
                }
                $existingPoints = !empty($heartbeatPoints)
                    ? $heartbeatPoints
                    : (is_array($existing['points'] ?? null) ? $existing['points'] : []);
                $lastExistingPoint = !empty($existingPoints) ? $existingPoints[array_key_last($existingPoints)] : null;
                $sameAsLast = is_array($lastExistingPoint)
                    && ((float) ($lastExistingPoint['lat'] ?? 0) === (float) $lastPoint['lat'])
                    && ((float) ($lastExistingPoint['lng'] ?? 0) === (float) $lastPoint['lng'])
                    && ((string) ($lastExistingPoint['t'] ?? '') === (string) $lastPoint['t']);
                if (!$sameAsLast) {
                    $existingPoints[] = $lastPoint;
                }
                $existing['points'] = array_slice($existingPoints, -150);
                $existing['points_count'] = count($existing['points']);
                $vehiclesById->put($vehicleId, $existing);
                continue;
            }

                $vehiclesById->put($vehicleId, [
                'vehicle_id' => $vehicleId,
                'placa' => (string) ($vehicle->placa ?? 'SIN PLACA'),
                'marca' => (string) ($vehicle->marca ?? ''),
                'modelo' => (string) ($vehicle->modelo ?? ''),
                'driver_id' => $driverId,
                'driver_name' => (string) ($driver->nombre ?? 'SIN CONDUCTOR'),
                'log_id' => null,
                'fecha' => now()->toDateString(),
                'recorrido_inicio' => 'Heartbeat',
                'recorrido_destino' => 'Heartbeat',
                'current_address' => $lastPoint['point_label'],
                'current_status' => $currentStatus,
                'last_point' => $lastPoint,
                'points' => !empty($heartbeatPoints) ? $heartbeatPoints : [$lastPoint],
                'points_count' => !empty($heartbeatPoints) ? count($heartbeatPoints) : 1,
                'marked_points' => [],
                'heartbeat_received_at' => (string) ($hb['received_at'] ?? ''),
                'current_speed_kmh' => $this->asFloat($hb['speed_kmh'] ?? null),
                'gps_enabled' => $gpsEnabled,
                'gps_mocked' => $gpsMocked,
            ]);
        }

        return $vehiclesById->values()->all();
    }

    /**
     * @param array<int, array<string, mixed>> $vehicles
     * @return array<int, array<string, mixed>>
     */
    private function syncAndBuildOperationalAlerts(array $vehicles): array
    {
        $vehicleIds = collect($vehicles)
            ->pluck('vehicle_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($vehicleIds)) {
            if (Schema::hasTable('vehicle_operation_alerts')) {
                VehicleOperationAlert::query()
                    ->where('status', VehicleOperationAlert::STATUS_ACTIVE)
                    ->update([
                        'status' => VehicleOperationAlert::STATUS_RESOLVED,
                        'resolved_at' => now(),
                    ]);
            }

            return [];
        }

        $openSessions = Schema::hasTable('vehicle_log_sessions')
            ? VehicleLogSession::query()
                ->whereIn('vehicle_id', $vehicleIds)
                ->whereNull('ended_at')
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy('vehicle_id')
                ->map(fn (Collection $rows) => $rows->first())
            : collect();

        $desiredAlerts = [];
        foreach ($vehicles as $vehicle) {
            $vehicleId = (int) ($vehicle['vehicle_id'] ?? 0);
            if ($vehicleId <= 0) {
                continue;
            }

            $plate = trim((string) ($vehicle['placa'] ?? 'SIN PLACA'));
            $driverName = trim((string) ($vehicle['driver_name'] ?? 'Sin conductor'));
            $currentStatus = strtoupper(trim((string) ($vehicle['current_status'] ?? $vehicle['last_point']['point_label'] ?? 'EN_RUTA')));
            $secondsSince = (int) ($vehicle['seconds_since_update'] ?? 0);
            $isStale = (bool) ($vehicle['is_stale'] ?? false);
            $session = $openSessions->get($vehicleId);
            $sessionStatus = trim((string) ($session?->status ?? ''));
            $gpsEnabled = array_key_exists('gps_enabled', $vehicle) ? (bool) $vehicle['gps_enabled'] : true;
            $gpsMocked = array_key_exists('gps_mocked', $vehicle) ? (bool) $vehicle['gps_mocked'] : false;
            $heartbeatAt = null;
            $heartbeatRaw = $vehicle['heartbeat_received_at'] ?? null;
            if (is_string($heartbeatRaw) && $heartbeatRaw !== '') {
                try {
                    $heartbeatAt = Carbon::parse($heartbeatRaw);
                } catch (\Throwable) {
                    $heartbeatAt = null;
                }
            }

            $statusTitle = match ($currentStatus) {
                'CARGA' => 'Vehiculo cargando',
                'ENTREGA' => 'Vehiculo entregando',
                'ESPERA' => 'Vehiculo en espera',
                default => 'Vehiculo en ruta',
            };
            $statusSeverity = match ($currentStatus) {
                'CARGA' => 'info',
                'ENTREGA' => 'warning',
                'ESPERA' => 'secondary',
                default => 'success',
            };

            $desiredAlerts[] = [
                'vehicle_id' => $vehicleId,
                'vehicle_log_session_id' => $session?->id,
                'alert_type' => VehicleOperationAlert::TYPE_ROUTE_STATUS,
                'severity' => $statusSeverity,
                'status' => VehicleOperationAlert::STATUS_ACTIVE,
                'title' => $statusTitle,
                'message' => sprintf('%s se encuentra en estado %s.', $plate, $currentStatus),
                'current_stage' => $currentStatus,
                'last_heartbeat_at' => $heartbeatAt,
                'detected_at' => now(),
                'resolved_at' => null,
                'meta_json' => [
                    'seconds_since_update' => $secondsSince,
                    'driver_name' => $vehicle['driver_name'] ?? null,
                ],
            ];

            if ($isStale) {
                $desiredAlerts[] = [
                    'vehicle_id' => $vehicleId,
                    'vehicle_log_session_id' => $session?->id,
                    'alert_type' => VehicleOperationAlert::TYPE_PHONE_OFF,
                    'severity' => 'danger',
                    'status' => VehicleOperationAlert::STATUS_ACTIVE,
                    'title' => 'Celular apagado o sin señal',
                    'message' => sprintf('%s no reporta ubicacion reciente. Ultima señal hace %s.', $plate, $this->formatSecondsAsHuman($secondsSince)),
                    'current_stage' => $currentStatus,
                    'last_heartbeat_at' => $heartbeatAt,
                    'detected_at' => now(),
                    'resolved_at' => null,
                    'meta_json' => [
                        'seconds_since_update' => $secondsSince,
                        'driver_name' => $vehicle['driver_name'] ?? null,
                    ],
                ];
            }

            if (!$gpsEnabled) {
                $desiredAlerts[] = [
                    'vehicle_id' => $vehicleId,
                    'vehicle_log_session_id' => $session?->id,
                    'alert_type' => VehicleOperationAlert::TYPE_GPS_OFF,
                    'severity' => 'danger',
                    'status' => VehicleOperationAlert::STATUS_ACTIVE,
                    'title' => 'GPS apagado',
                    'message' => sprintf(
                        '%s con el vehiculo %s apago el GPS o deshabilito los servicios de ubicacion.',
                        $driverName,
                        $plate
                    ),
                    'current_stage' => $currentStatus,
                    'last_heartbeat_at' => $heartbeatAt,
                    'detected_at' => now(),
                    'resolved_at' => null,
                    'meta_json' => [
                        'driver_name' => $driverName,
                        'seconds_since_update' => $secondsSince,
                    ],
                ];
            }

            if ($gpsMocked) {
                $desiredAlerts[] = [
                    'vehicle_id' => $vehicleId,
                    'vehicle_log_session_id' => $session?->id,
                    'alert_type' => VehicleOperationAlert::TYPE_GPS_MOCKED,
                    'severity' => 'danger',
                    'status' => VehicleOperationAlert::STATUS_ACTIVE,
                    'title' => 'GPS manipulado',
                    'message' => sprintf(
                        '%s con el vehiculo %s intento falsificar su ubicacion usando GPS manipulado o ubicacion simulada.',
                        $driverName,
                        $plate
                    ),
                    'current_stage' => $currentStatus,
                    'last_heartbeat_at' => $heartbeatAt,
                    'detected_at' => now(),
                    'resolved_at' => null,
                    'meta_json' => [
                        'driver_name' => $driverName,
                        'seconds_since_update' => $secondsSince,
                    ],
                ];
            }

            if ($session && ($sessionStatus === 'En Suspenso' || $isStale)) {
                $desiredAlerts[] = [
                    'vehicle_id' => $vehicleId,
                    'vehicle_log_session_id' => $session->id,
                    'alert_type' => VehicleOperationAlert::TYPE_ROUTE_INCOMPLETE,
                    'severity' => 'danger',
                    'status' => VehicleOperationAlert::STATUS_ACTIVE,
                    'title' => 'Ruta no completada',
                    'message' => sprintf('%s tiene una bitacora abierta sin completar. Estado de sesion: %s.', $plate, $sessionStatus !== '' ? $sessionStatus : 'Activa'),
                    'current_stage' => $currentStatus,
                    'last_heartbeat_at' => $heartbeatAt,
                    'detected_at' => now(),
                    'resolved_at' => null,
                    'meta_json' => [
                        'session_reference' => $session->session_reference,
                        'seconds_since_update' => $secondsSince,
                    ],
                ];
            }
        }

        if (Schema::hasTable('vehicle_operation_alerts')) {
            $existingAlerts = VehicleOperationAlert::query()
                ->whereIn('vehicle_id', $vehicleIds)
                ->where('status', VehicleOperationAlert::STATUS_ACTIVE)
                ->get()
                ->keyBy(fn (VehicleOperationAlert $alert) => $alert->vehicle_id . ':' . $alert->alert_type);

            $activeKeys = [];
            foreach ($desiredAlerts as $payload) {
                $key = $payload['vehicle_id'] . ':' . $payload['alert_type'];
                $activeKeys[] = $key;
                $existing = $existingAlerts->get($key);

                if (!$existing) {
                    VehicleOperationAlert::query()->create($payload);
                    continue;
                }

                $shouldUpdate =
                    $existing->vehicle_log_session_id !== ($payload['vehicle_log_session_id'] ?? null) ||
                    $existing->severity !== $payload['severity'] ||
                    $existing->title !== $payload['title'] ||
                    $existing->message !== $payload['message'] ||
                    $existing->current_stage !== $payload['current_stage'] ||
                    json_encode($existing->meta_json) !== json_encode($payload['meta_json']);

                if ($shouldUpdate) {
                    $existing->update($payload);
                }
            }

            $keysFlipped = collect($activeKeys)->flip();
            $existingAlerts
                ->filter(fn (VehicleOperationAlert $alert, string $key) => !$keysFlipped->has($key))
                ->each(function (VehicleOperationAlert $alert) {
                    $alert->update([
                        'status' => VehicleOperationAlert::STATUS_RESOLVED,
                        'resolved_at' => now(),
                    ]);
                });
        }

        usort($desiredAlerts, function (array $a, array $b) {
            $priority = ['danger' => 0, 'warning' => 1, 'info' => 2, 'success' => 3, 'secondary' => 4];
            $aScore = $priority[$a['severity'] ?? 'secondary'] ?? 99;
            $bScore = $priority[$b['severity'] ?? 'secondary'] ?? 99;
            if ($aScore === $bScore) {
                return strcmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
            }
            return $aScore <=> $bScore;
        });

        return $desiredAlerts;
    }

    private function formatSecondsAsHuman(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' segundo(s)';
        }

        $minutes = (int) floor($seconds / 60);
        $remaining = $seconds % 60;
        return $minutes . ' minuto(s) ' . $remaining . ' segundo(s)';
    }

    /**
     * @param array<int, array<string, mixed>> $vehicles
     * @return array<int, array<string, mixed>>
     */
    private function annotateStaleness(array $vehicles): array
    {
        return array_map(function (array $vehicle) {
            $reference = $vehicle['heartbeat_received_at'] ?? null;
            if (!is_string($reference) || trim($reference) === '') {
                $reference = (string) data_get($vehicle, 'last_point.t', '');
            }

            $seconds = $this->secondsSince($reference);
            $isStale = $seconds === null ? true : $seconds > self::LIVE_STALE_SECONDS;

            $vehicle['seconds_since_update'] = $seconds;
            $vehicle['is_stale'] = $isStale;
            $vehicle['stale_after_seconds'] = self::LIVE_STALE_SECONDS;

            return $vehicle;
        }, $vehicles);
    }

    private function secondsSince(?string $value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $numeric = (float) $value;
                $time = $numeric > 1000000000000
                    ? Carbon::createFromTimestampMs((int) $numeric)
                    : Carbon::createFromTimestamp((int) $numeric);
            } else {
                $time = Carbon::parse($value);
            }
        } catch (\Throwable) {
            return null;
        }

        $seconds = $time->diffInSeconds(now(), false);
        return $seconds < 0 ? 0 : $seconds;
    }
}

<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\MobileDbSnapshot;
use App\Models\VehicleAssignment;
use App\Models\Vehicle;
use App\Models\VehicleLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MobileSyncProcessor
{
    public function processSnapshot(string $key): void
    {
        $rows = MobileDbSnapshot::query()
            ->where('snapshot_key', $key)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            Log::warning("Snapshot {$key} no encontrado.");
            return;
        }

        $userId = (int) ($rows->pluck('user_id')->filter()->first() ?? 0);
        $driver = Driver::query()->where('user_id', $userId)->first();

        if (!$driver) {
            Log::warning("Snapshot {$key} sin conductor asociado para user_id={$userId}.");
            return;
        }

        $segments = $this->extractRouteSegments($rows);
        if (empty($segments)) {
            Log::warning("Snapshot {$key} sin puntos de ruta procesables.");
            return;
        }

        $vehicleId = $this->resolveVehicleId((int) $driver->id);
        $vehicleStartKm = $this->resolveVehicleStartKm($vehicleId);
        $currentVehicleKm = $vehicleStartKm;
        $processedSegments = 0;
        $totalPoints = 0;

        foreach ($segments as $segment) {
            $points = $segment['points'] ?? [];
            if (empty($points)) {
                continue;
            }

            $distanceKm = $this->asFloat($segment['distance_km'] ?? null);
            $fecha = $segment['fecha'] instanceof Carbon
                ? $segment['fecha']
                : $this->resolveSnapshotDate($rows);

            $startPoint = $points[0];
            $endPoint = $points[array_key_last($points)];
            $recorridoInicio = $this->resolveRouteLabel($startPoint, 'Punto de salida');
            $recorridoDestino = $this->resolveRouteLabel($endPoint, 'Punto de llegada');

            $log = $this->findExistingSessionLog(
                (int) $driver->id,
                $vehicleId,
                $fecha->toDateString(),
                $points
            );

            $defaults = [
                'drivers_id' => (int) $driver->id,
                'vehicles_id' => $vehicleId,
                'fecha' => $fecha->toDateString(),
                'kilometraje_salida' => $currentVehicleKm,
                'kilometraje_llegada' => (!is_null($currentVehicleKm) && !is_null($distanceKm))
                    ? ($currentVehicleKm + $distanceKm)
                    : null,
                'recorrido_inicio' => $recorridoInicio,
                'recorrido_destino' => $recorridoDestino,
                'abastecimiento_combustible' => false,
                'ruta_json' => [],
            ];
            if (Schema::hasColumn('vehicle_log', 'activo')) {
                $defaults['activo'] = true;
            }

            if (Schema::hasColumn('vehicle_log', 'firma_digital')) {
                $defaults['firma_digital'] = null;
            }

            if (Schema::hasColumn('vehicle_log', 'latitud_inicio')) {
                $defaults['latitud_inicio'] = $startPoint['lat'];
            }
            if (Schema::hasColumn('vehicle_log', 'logitud_inicio')) {
                $defaults['logitud_inicio'] = $startPoint['lng'];
            }
            if (Schema::hasColumn('vehicle_log', 'latitud_destino')) {
                $defaults['latitud_destino'] = $endPoint['lat'];
            }
            if (Schema::hasColumn('vehicle_log', 'logitud_destino')) {
                $defaults['logitud_destino'] = $endPoint['lng'];
            }

            if (!$log) {
                $log = VehicleLog::query()->create($defaults);
            } elseif (!$log->vehicles_id && $vehicleId) {
                $log->vehicles_id = $vehicleId;
            }

            if (!is_null($currentVehicleKm) && (float) ($log->kilometraje_salida ?? 0) <= 0) {
                $log->kilometraje_salida = $currentVehicleKm;
            }
            if (!is_null($distanceKm) && !is_null($log->kilometraje_salida)) {
                $candidateArrival = (float) $log->kilometraje_salida + (float) $distanceKm;
                $currentArrival = $this->asFloat($log->kilometraje_llegada);
                if (is_null($currentArrival) || $candidateArrival > $currentArrival) {
                    $log->kilometraje_llegada = $candidateArrival;
                }
            }

            $existingPoints = is_array($log->ruta_json) ? $log->ruta_json : [];
            $mergedByKey = collect($existingPoints)
                ->merge($points)
                ->reduce(function (array $carry, array $point) {
                    $key = implode('|', [
                        (string) ($point['lat'] ?? ''),
                        (string) ($point['lng'] ?? ''),
                        (string) ($point['t'] ?? ''),
                    ]);

                    $incomingMarked = !empty($point['is_marked']);
                    $currentMarked = !empty($carry[$key]['is_marked'] ?? false);

                    if (!isset($carry[$key]) || ($incomingMarked && !$currentMarked)) {
                        $carry[$key] = $point;
                    }

                    return $carry;
                }, []);

            $log->ruta_json = array_values($mergedByKey);
            if ($this->shouldReplaceRouteLabel((string) ($log->recorrido_inicio ?? ''))) {
                $log->recorrido_inicio = $recorridoInicio;
            }
            $log->recorrido_destino = $recorridoDestino;

            if (Schema::hasColumn('vehicle_log', 'latitud_inicio') && is_null($log->latitud_inicio ?? null)) {
                $log->latitud_inicio = $startPoint['lat'];
            }
            if (Schema::hasColumn('vehicle_log', 'logitud_inicio') && is_null($log->logitud_inicio ?? null)) {
                $log->logitud_inicio = $startPoint['lng'];
            }
            if (Schema::hasColumn('vehicle_log', 'latitud_destino')) {
                $log->latitud_destino = $endPoint['lat'];
            }
            if (Schema::hasColumn('vehicle_log', 'logitud_destino')) {
                $log->logitud_destino = $endPoint['lng'];
            }
            $log->save();

            $this->updateVehicleCurrentKilometraje($vehicleId, $log);
            $currentVehicleKm = $this->asFloat($log->kilometraje_llegada) ?? $currentVehicleKm;
            $processedSegments++;
            $totalPoints += count($points);
        }

        Log::info("Sincronizacion exitosa de snapshot {$key}: driver {$driver->id}, bitacoras {$processedSegments}, puntos {$totalPoints}");
    }

    /**
     * @param Collection<int, MobileDbSnapshot> $rows
     * @return array<int, array{lat: float, lng: float, t: string}>
     */
    private function extractRoutePoints(Collection $rows): array
    {
        $points = [];

        foreach ($rows as $row) {
            $payload = $this->decodePayload($row->payload_json);
            if (empty($payload)) {
                continue;
            }

            $tableName = mb_strtolower(trim((string) (
                $row->table_name
                ?? ($payload['table_name'] ?? $payload['table'] ?? '')
            )));

            if ($tableName === '' && str_starts_with((string) ($row->model ?? ''), 'mobile_sqlite.')) {
                $tableName = mb_strtolower((string) substr((string) $row->model, strlen('mobile_sqlite.')));
            }

            $rowsToProcess = [];

            if ($tableName === 'route_points') {
                $rowsToProcess = $this->extractRowsFromPayload($payload);
            }

            if (empty($rowsToProcess) && isset($payload['local_sqlite']['full_data']['route_points']['rows'])) {
                $candidate = $payload['local_sqlite']['full_data']['route_points']['rows'];
                if (is_array($candidate)) {
                    $rowsToProcess = $candidate;
                }
            }

            if (empty($rowsToProcess) && isset($payload['route_points']['rows']) && is_array($payload['route_points']['rows'])) {
                $rowsToProcess = $payload['route_points']['rows'];
            }

            if (empty($rowsToProcess) && isset($payload['tables']['route_points']['rows']) && is_array($payload['tables']['route_points']['rows'])) {
                $rowsToProcess = $payload['tables']['route_points']['rows'];
            }

            foreach ($rowsToProcess as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $lat = $this->asFloat($item['latitude'] ?? $item['lat'] ?? $item['latitud'] ?? null);
                $lng = $this->asFloat($item['longitude'] ?? $item['lng'] ?? $item['longitud'] ?? null);
                if (is_null($lat) || is_null($lng)) {
                    continue;
                }

                $timestamp = $item['timestamp'] ?? $item['t'] ?? $item['created_at'] ?? now()->toIso8601String();
                $points[] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    't' => is_scalar($timestamp) ? (string) $timestamp : now()->toIso8601String(),
                    'session_id' => $item['session_id'] ?? $item['sessionId'] ?? null,
                    'is_marked' => !empty($item['is_marked']) || !empty($item['marked']) || !empty($item['isMarked']),
                    'point_label' => (string) ($item['point_label'] ?? $item['label'] ?? ''),
                    'address' => (string) ($item['address'] ?? ''),
                ];
            }
        }

        return $points;
    }

    private function decodePayload(?string $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return [];
        }

        if (isset($payload['changes_json'])) {
            $changes = $payload['changes_json'];
            if (is_string($changes)) {
                $decoded = json_decode($changes, true);
                if (is_array($decoded)) {
                    $payload['changes_json'] = $decoded;
                }
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, mixed>
     */
    private function extractRowsFromPayload(array $payload): array
    {
        $candidateRows = [
            $payload['rows'] ?? null,
            $payload['data'] ?? null,
            $payload['tables']['route_points']['rows'] ?? null,
            $payload['tables']['route_points']['data'] ?? null,
            $payload['changes_json']['rows'] ?? null,
            $payload['changes_json']['data'] ?? null,
            $payload['changes_json']['tables']['route_points']['rows'] ?? null,
            $payload['changes_json']['tables']['route_points']['data'] ?? null,
            $payload['payload']['rows'] ?? null,
        ];

        foreach ($candidateRows as $rows) {
            if (is_array($rows)) {
                return $rows;
            }
        }

        return [];
    }

    /**
     * @param Collection<int, MobileDbSnapshot> $rows
     * @return array<int, array{session_id: int|null, points: array<int, array<string, mixed>>, distance_km: float|null, fecha: Carbon}>
     */
    private function extractRouteSegments(Collection $rows): array
    {
        $points = $this->extractRoutePoints($rows);
        if (empty($points)) {
            return [];
        }

        $distancesBySession = $this->extractRouteDistancesBySession($rows);
        $grouped = [];

        foreach ($points as $point) {
            $sessionIdRaw = $point['session_id'] ?? null;
            $sessionId = (is_numeric($sessionIdRaw) && (int) $sessionIdRaw > 0) ? (int) $sessionIdRaw : null;
            $groupKey = $sessionId ? ('s:' . $sessionId) : 's:0';
            $grouped[$groupKey]['session_id'] = $sessionId;
            $grouped[$groupKey]['points'][] = $point;
        }

        $segments = [];
        foreach ($grouped as $segment) {
            $segmentPoints = $segment['points'] ?? [];
            if (empty($segmentPoints)) {
                continue;
            }

            usort($segmentPoints, fn (array $a, array $b) => $this->comparePointTime($a, $b));
            $sessionId = $segment['session_id'] ?? null;
            $firstTime = $this->parsePointTime($segmentPoints[0]['t'] ?? null) ?? $this->resolveSnapshotDate($rows);

            $segments[] = [
                'session_id' => $sessionId,
                'points' => $segmentPoints,
                'distance_km' => $sessionId ? ($distancesBySession[$sessionId] ?? null) : null,
                'fecha' => $firstTime,
            ];
        }

        usort($segments, function (array $a, array $b) {
            /** @var Carbon $at */
            $at = $a['fecha'];
            /** @var Carbon $bt */
            $bt = $b['fecha'];
            return $at->lessThan($bt) ? -1 : ($at->equalTo($bt) ? 0 : 1);
        });

        return $segments;
    }

    /**
     * @param Collection<int, MobileDbSnapshot> $rows
     */
    private function resolveSnapshotDate(Collection $rows): Carbon
    {
        $sentAt = $rows->pluck('sent_at')->filter()->last();
        if ($sentAt instanceof Carbon) {
            return $sentAt;
        }

        if (is_string($sentAt) && trim($sentAt) !== '') {
            return Carbon::parse($sentAt);
        }

        return now();
    }

    private function resolveVehicleId(int $driverId): ?int
    {
        $assignment = VehicleAssignment::query()
            ->where('driver_id', $driverId)
            ->where(function ($q) {
                $q->where('activo', true)->orWhereNull('activo');
            })
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->first();

        if (!$assignment || (int) $assignment->vehicle_id <= 0) {
            return null;
        }

        return (int) $assignment->vehicle_id;
    }

    /**
     * @param Collection<int, MobileDbSnapshot> $rows
     */
    private function extractRouteDistancesBySession(Collection $rows): array
    {
        $distances = [];

        foreach ($rows as $row) {
            $payload = $this->decodePayload($row->payload_json);
            if (empty($payload)) {
                continue;
            }

            $candidates = [
                $payload['tables']['route_sessions']['rows'] ?? null,
                $payload['changes_json']['tables']['route_sessions']['rows'] ?? null,
                $payload['route_sessions']['rows'] ?? null,
                $payload['local_sqlite']['full_data']['route_sessions']['rows'] ?? null,
                $payload['local_sqlite']['data']['sessions'] ?? null,
            ];

            foreach ($candidates as $rowsSet) {
                if (!is_array($rowsSet)) {
                    continue;
                }
                foreach ($rowsSet as $session) {
                    if (!is_array($session)) {
                        continue;
                    }
                    $sessionId = is_numeric($session['id'] ?? null) ? (int) $session['id'] : null;
                    $km = $this->asFloat($session['distance_km'] ?? $session['distanceKm'] ?? null);
                    if (!is_null($km) && !is_null($sessionId) && $sessionId > 0) {
                        $current = $distances[$sessionId] ?? null;
                        $distances[$sessionId] = is_null($current) ? $km : max($current, $km);
                    }
                }
            }
        }

        return $distances;
    }

    private function resolveVehicleStartKm(?int $vehicleId): ?float
    {
        if (($vehicleId ?? 0) <= 0) {
            return null;
        }

        $vehicle = Vehicle::query()->find((int) $vehicleId);
        if (!$vehicle) {
            return null;
        }

        $current = $this->asFloat($vehicle->kilometraje_actual ?? null);
        if (!is_null($current)) {
            return $current;
        }

        $initial = $this->asFloat($vehicle->kilometraje_inicial ?? null);
        if (!is_null($initial)) {
            return $initial;
        }

        return $this->asFloat($vehicle->kilometraje ?? null);
    }

    private function updateVehicleCurrentKilometraje(?int $vehicleId, VehicleLog $log): void
    {
        if (($vehicleId ?? 0) <= 0) {
            return;
        }

        $arrival = $this->asFloat($log->kilometraje_llegada);
        if (is_null($arrival)) {
            return;
        }

        $vehicle = Vehicle::query()->find((int) $vehicleId);
        if (!$vehicle) {
            return;
        }

        $current = $this->asFloat($vehicle->kilometraje_actual ?? null);
        if (!is_null($current) && $arrival <= $current) {
            return;
        }

        if (Schema::hasColumn('vehicles', 'kilometraje_actual')) {
            $vehicle->kilometraje_actual = $arrival;
        }
        if (Schema::hasColumn('vehicles', 'kilometraje')) {
            $vehicle->kilometraje = $arrival;
        }
        $vehicle->save();
    }

    private function asFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * Busca una bitacora equivalente (misma sesion) para evitar duplicados exactos,
     * pero permite multiples bitacoras en la misma fecha.
     *
     * @param array<int, array<string, mixed>> $points
     */
    private function findExistingSessionLog(int $driverId, ?int $vehicleId, string $date, array $points): ?VehicleLog
    {
        $incomingSignature = $this->buildRouteSignature($points);
        if ($incomingSignature === '') {
            return null;
        }

        $query = VehicleLog::query()
            ->active()
            ->where('drivers_id', $driverId)
            ->whereDate('fecha', $date)
            ->orderByDesc('id')
            ->limit(50);

        if (($vehicleId ?? 0) > 0) {
            $query->where('vehicles_id', (int) $vehicleId);
        }

        $candidates = $query->get();
        foreach ($candidates as $candidate) {
            $candidatePoints = is_array($candidate->ruta_json) ? $candidate->ruta_json : [];
            $candidateSignature = $this->buildRouteSignature($candidatePoints);
            if ($candidateSignature !== '' && $candidateSignature === $incomingSignature) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Firma compacta para diferenciar sesiones por puntos (inicio, fin, cantidad, tiempo).
     *
     * @param array<int, array<string, mixed>> $points
     */
    private function buildRouteSignature(array $points): string
    {
        if (empty($points)) {
            return '';
        }

        $first = $points[0] ?? [];
        $last = $points[array_key_last($points)] ?? [];

        $parts = [
            (string) count($points),
            (string) ($first['t'] ?? ''),
            (string) ($last['t'] ?? ''),
            (string) ($first['lat'] ?? ''),
            (string) ($first['lng'] ?? ''),
            (string) ($last['lat'] ?? ''),
            (string) ($last['lng'] ?? ''),
        ];

        return sha1(implode('|', $parts));
    }

    private function comparePointTime(array $a, array $b): int
    {
        $at = $this->parsePointTime($a['t'] ?? null);
        $bt = $this->parsePointTime($b['t'] ?? null);
        if ($at && $bt) {
            return $at->lessThan($bt) ? -1 : ($at->equalTo($bt) ? 0 : 1);
        }
        if ($at && !$bt) {
            return -1;
        }
        if (!$at && $bt) {
            return 1;
        }

        return strcmp((string) ($a['t'] ?? ''), (string) ($b['t'] ?? ''));
    }

    private function parsePointTime(mixed $raw): ?Carbon
    {
        if (is_null($raw) || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            $numeric = (float) $raw;
            if ($numeric > 1000000000000) {
                return Carbon::createFromTimestampMs((int) $numeric);
            }

            return Carbon::createFromTimestamp((int) $numeric);
        }

        if (is_string($raw)) {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $point
     */
    private function resolveRouteLabel(array $point, string $fallback): string
    {
        $address = trim((string) ($point['address'] ?? ''));
        if ($address !== '') {
            return $address;
        }

        $lat = $this->asFloat($point['lat'] ?? null);
        $lng = $this->asFloat($point['lng'] ?? null);
        if (!is_null($lat) && !is_null($lng)) {
            return sprintf('%.6f, %.6f', $lat, $lng);
        }

        return $fallback;
    }

    private function shouldReplaceRouteLabel(string $value): bool
    {
        $clean = mb_strtolower(trim($value));
        if ($clean === '') {
            return true;
        }

        if (in_array($clean, ['sincronizacion app', 'en ruta', 'no definido'], true)) {
            return true;
        }

        return preg_match('/^\s*-?\d+(?:\.\d+)?\s*,\s*-?\d+(?:\.\d+)?\s*$/', $clean) === 1;
    }
}

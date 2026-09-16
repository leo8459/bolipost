<?php

namespace App\Services;

use App\Models\ChasquiLocationPoint;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ChasquiLocationService
{
    public const LIVE_AFTER_SECONDS = 30;

    private const CACHE_TTL_HOURS = 12;

    private const INDEX_KEY = 'chasqui:location:index';

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function record(User $user, array $payload): array
    {
        $deviceId = trim((string) ($payload['device_id'] ?? ''));
        if ($deviceId === '') {
            $deviceId = 'user-'.$user->id;
        }

        $locationId = $user->id.':'.substr(hash('sha256', $deviceId), 0, 20);
        $cacheKey = $this->cacheKey($locationId);
        $now = now();
        $sentAt = isset($payload['sent_at'])
            ? Carbon::parse((string) $payload['sent_at'])
            : $now->copy();
        $speedKmh = $this->nullableFloat($payload['speed_kmh'] ?? null);

        $location = [
            'location_id' => $locationId,
            'user_id' => (int) $user->id,
            'user_name' => (string) $user->name,
            'alias' => (string) $user->alias,
            'city' => (string) ($user->ciudad ?? ''),
            'device_id' => $deviceId,
            'device_name' => trim((string) ($payload['device_name'] ?? 'ChasquiApp Android')),
            'latitude' => (float) $payload['latitude'],
            'longitude' => (float) $payload['longitude'],
            'accuracy_m' => $this->nullableFloat($payload['accuracy_m'] ?? null),
            'speed_kmh' => $speedKmh,
            'heading' => $this->nullableFloat($payload['heading'] ?? null),
            'battery_percent' => $this->nullableFloat($payload['battery_percent'] ?? null),
            'is_moving' => array_key_exists('is_moving', $payload)
                ? (bool) $payload['is_moving']
                : ($speedKmh !== null && $speedKmh >= 2),
            'gps_enabled' => array_key_exists('gps_enabled', $payload) ? (bool) $payload['gps_enabled'] : true,
            'gps_mocked' => array_key_exists('gps_mocked', $payload) ? (bool) $payload['gps_mocked'] : false,
            'sent_at' => $sentAt->toIso8601String(),
            'received_at' => $now->toIso8601String(),
        ];

        ChasquiLocationPoint::query()->updateOrCreate(
            [
                'location_id' => $locationId,
                'sent_at' => $sentAt,
            ],
            [
                'user_id' => (int) $user->id,
                'device_id' => $deviceId,
                'device_name' => $location['device_name'],
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'accuracy_m' => $location['accuracy_m'],
                'speed_kmh' => $location['speed_kmh'],
                'heading' => $location['heading'],
                'battery_percent' => $location['battery_percent'],
                'is_moving' => $location['is_moving'],
                'gps_enabled' => $location['gps_enabled'],
                'gps_mocked' => $location['gps_mocked'],
                'received_at' => $now,
            ]
        );

        Cache::put($cacheKey, $location, $now->copy()->addHours(self::CACHE_TTL_HOURS));
        $this->touchIndex($locationId, $now);

        return $this->annotate($location, $now);
    }

    /** @return array<int, array<string, mixed>> */
    public function locations(): array
    {
        $ids = Cache::get(self::INDEX_KEY, []);
        if (! is_array($ids)) {
            return [];
        }

        $now = now();
        $validIds = [];
        $locations = [];

        foreach (array_values(array_unique(array_map('strval', $ids))) as $locationId) {
            $location = Cache::get($this->cacheKey($locationId));
            if (! is_array($location)) {
                continue;
            }

            $validIds[] = $locationId;
            $locations[] = $this->annotate($location, $now);
        }

        if ($validIds !== $ids) {
            Cache::put(self::INDEX_KEY, $validIds, $now->copy()->addHours(self::CACHE_TTL_HOURS));
        }

        usort($locations, fn (array $left, array $right): int => strcmp(
            (string) ($right['received_at'] ?? ''),
            (string) ($left['received_at'] ?? '')
        ));

        return $locations;
    }

    /**
     * Devuelve un payload liviano para el mapa en vivo. Cada dispositivo incluye
     * solamente su posicion mas reciente; el navegador conserva el trazo del
     * cartero seleccionado y agrega los puntos nuevos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function liveLocations(?int $userId = null): array
    {
        return collect($this->locations())
            ->when(
                $userId !== null,
                fn ($items) => $items->where('user_id', $userId)
            )
            ->map(function (array $location): array {
                $point = [
                    'lat' => (float) ($location['latitude'] ?? 0),
                    'lng' => (float) ($location['longitude'] ?? 0),
                    't' => (string) ($location['sent_at'] ?? ''),
                    'speed_kmh' => $location['speed_kmh'] ?? null,
                    'accuracy_m' => $location['accuracy_m'] ?? null,
                ];

                return array_merge($location, [
                    'points' => [$point],
                    'points_count' => 1,
                    'distance_km' => 0,
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * Devuelve el ultimo estado y el recorrido completo de cada dispositivo para un dia.
     *
     * @return array<int, array<string, mixed>>
     */
    public function dailyLocations(Carbon $date, ?int $userId = null): array
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $start = $date->copy()->setTimezone($timezone)->startOfDay()->utc();
        $end = $date->copy()->setTimezone($timezone)->endOfDay()->utc();
        $isToday = $date->copy()->setTimezone($timezone)->isToday();

        if ($isToday) {
            $this->persistCachedLocations($userId);
        }

        $rows = ChasquiLocationPoint::query()
            ->with('user:id,name,alias,ciudad')
            ->whereBetween('sent_at', [$start, $end])
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get();

        $now = now();

        $dailyLocations = $rows
            ->groupBy('location_id')
            ->map(function ($points) use ($now): array {
                /** @var ChasquiLocationPoint $latest */
                $latest = $points->last();
                $route = $points->map(fn (ChasquiLocationPoint $point): array => [
                    'lat' => (float) $point->latitude,
                    'lng' => (float) $point->longitude,
                    't' => $point->sent_at?->toIso8601String(),
                    'speed_kmh' => $point->speed_kmh,
                    'accuracy_m' => $point->accuracy_m,
                ])->values()->all();

                return $this->annotate([
                    'location_id' => (string) $latest->location_id,
                    'user_id' => (int) $latest->user_id,
                    'user_name' => (string) ($latest->user?->name ?? ''),
                    'alias' => (string) ($latest->user?->alias ?? ''),
                    'city' => (string) ($latest->user?->ciudad ?? ''),
                    'device_id' => (string) $latest->device_id,
                    'device_name' => (string) ($latest->device_name ?: 'ChasquiApp Android'),
                    'latitude' => (float) $latest->latitude,
                    'longitude' => (float) $latest->longitude,
                    'accuracy_m' => $latest->accuracy_m,
                    'speed_kmh' => $latest->speed_kmh,
                    'heading' => $latest->heading,
                    'battery_percent' => $latest->battery_percent,
                    'is_moving' => (bool) $latest->is_moving,
                    'gps_enabled' => (bool) $latest->gps_enabled,
                    'gps_mocked' => (bool) $latest->gps_mocked,
                    'is_simulated' => (bool) $latest->is_simulated,
                    'route_label' => (string) ($latest->route_label ?? ''),
                    'sent_at' => $latest->sent_at?->toIso8601String(),
                    'received_at' => $latest->received_at?->toIso8601String(),
                    'points' => $route,
                    'points_count' => count($route),
                    'distance_km' => $this->routeDistanceKm($route),
                ], $now);
            })
            ->keyBy('location_id');

        if ($isToday) {
            foreach ($this->locations() as $current) {
                if ($userId !== null && (int) ($current['user_id'] ?? 0) !== $userId) {
                    continue;
                }

                try {
                    $sentAt = Carbon::parse((string) ($current['sent_at'] ?? ''))->setTimezone($timezone);
                } catch (\Throwable) {
                    continue;
                }

                if (! $sentAt->isSameDay($date->copy()->setTimezone($timezone))) {
                    continue;
                }

                $locationId = (string) ($current['location_id'] ?? '');
                if ($locationId === '') {
                    continue;
                }

                $existing = $dailyLocations->get($locationId, []);
                $route = is_array($existing['points'] ?? null) ? $existing['points'] : [];
                $currentPoint = [
                    'lat' => (float) $current['latitude'],
                    'lng' => (float) $current['longitude'],
                    't' => (string) $current['sent_at'],
                    'speed_kmh' => $current['speed_kmh'] ?? null,
                    'accuracy_m' => $current['accuracy_m'] ?? null,
                ];
                $lastPoint = ! empty($route) ? $route[array_key_last($route)] : null;
                $currentKey = implode('|', [$currentPoint['lat'], $currentPoint['lng'], $currentPoint['t']]);
                $lastKey = is_array($lastPoint)
                    ? implode('|', [$lastPoint['lat'] ?? '', $lastPoint['lng'] ?? '', $lastPoint['t'] ?? ''])
                    : '';
                if ($currentKey !== $lastKey) {
                    $route[] = $currentPoint;
                }

                $dailyLocations->put($locationId, array_merge($existing, $current, [
                    'points' => $route,
                    'points_count' => count($route),
                    'distance_km' => $this->routeDistanceKm($route),
                ]));
            }
        }

        return $dailyLocations->values()->all();
    }

    private function touchIndex(string $locationId, Carbon $now): void
    {
        $ids = Cache::get(self::INDEX_KEY, []);
        if (! is_array($ids)) {
            $ids = [];
        }

        if (! in_array($locationId, $ids, true)) {
            $ids[] = $locationId;
        }

        Cache::put(
            self::INDEX_KEY,
            array_values(array_unique(array_map('strval', $ids))),
            $now->copy()->addHours(self::CACHE_TTL_HOURS)
        );
    }

    /**
     * @param  array<string, mixed>  $location
     * @return array<string, mixed>
     */
    private function annotate(array $location, Carbon $now): array
    {
        try {
            $receivedAt = Carbon::parse((string) ($location['received_at'] ?? ''));
            $seconds = max(0, $receivedAt->diffInSeconds($now));
        } catch (\Throwable) {
            $seconds = null;
        }

        $location['seconds_since_update'] = $seconds;
        $location['is_stale'] = $seconds === null || $seconds > self::LIVE_AFTER_SECONDS;
        $location['stale_after_seconds'] = self::LIVE_AFTER_SECONDS;

        return $location;
    }

    private function cacheKey(string $locationId): string
    {
        return 'chasqui:location:device:'.$locationId;
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function persistCachedLocations(?int $userId = null): void
    {
        foreach ($this->locations() as $location) {
            $locationUserId = (int) ($location['user_id'] ?? 0);
            if ($locationUserId <= 0 || ($userId !== null && $locationUserId !== $userId)) {
                continue;
            }

            try {
                $sentAt = Carbon::parse((string) ($location['sent_at'] ?? ''));
                $receivedAt = Carbon::parse((string) ($location['received_at'] ?? $location['sent_at'] ?? ''));
            } catch (\Throwable) {
                continue;
            }

            ChasquiLocationPoint::query()->updateOrCreate(
                [
                    'location_id' => (string) $location['location_id'],
                    'sent_at' => $sentAt,
                ],
                [
                    'user_id' => $locationUserId,
                    'device_id' => (string) ($location['device_id'] ?? 'user-'.$locationUserId),
                    'device_name' => (string) ($location['device_name'] ?? 'ChasquiApp Android'),
                    'latitude' => (float) $location['latitude'],
                    'longitude' => (float) $location['longitude'],
                    'accuracy_m' => $this->nullableFloat($location['accuracy_m'] ?? null),
                    'speed_kmh' => $this->nullableFloat($location['speed_kmh'] ?? null),
                    'heading' => $this->nullableFloat($location['heading'] ?? null),
                    'battery_percent' => $this->nullableFloat($location['battery_percent'] ?? null),
                    'is_moving' => (bool) ($location['is_moving'] ?? false),
                    'gps_enabled' => (bool) ($location['gps_enabled'] ?? true),
                    'gps_mocked' => (bool) ($location['gps_mocked'] ?? false),
                    'received_at' => $receivedAt,
                ]
            );
        }
    }

    /** @param array<int, array<string, mixed>> $points */
    private function routeDistanceKm(array $points): float
    {
        $distance = 0.0;

        for ($index = 1, $count = count($points); $index < $count; $index++) {
            $previous = $points[$index - 1];
            $current = $points[$index];
            $lat1 = deg2rad((float) $previous['lat']);
            $lat2 = deg2rad((float) $current['lat']);
            $deltaLat = $lat2 - $lat1;
            $deltaLng = deg2rad((float) $current['lng'] - (float) $previous['lng']);
            $a = sin($deltaLat / 2) ** 2
                + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
            $distance += 6371 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
        }

        return round($distance, 3);
    }
}

<?php

namespace App\Services;

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
            'sent_at' => (string) ($payload['sent_at'] ?? $now->toIso8601String()),
            'received_at' => $now->toIso8601String(),
        ];

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
}

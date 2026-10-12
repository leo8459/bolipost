<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SitraTrackingEventsClient
{
    private const BATCH_SIZE = 1000;
    private const SINGLE_FALLBACK_CHUNK = 40;
    private const CACHE_MINUTES = 5;

    /**
     * @param  array<int, string>  $codes
     * @return array<string, array<int, array<string, mixed>>> Events keyed by normalized tracking code.
     */
    public function events(array $codes): array
    {
        $codes = collect($codes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn (string $code) => $code !== '' && strlen($code) <= 40 && preg_match('/^[A-Z0-9-]+$/', $code) === 1)
            ->unique()
            ->sort()
            ->values();

        if ($codes->isEmpty()) {
            return [];
        }

        $result = [];
        $pending = [];
        $cacheKeys = [];
        foreach ($codes as $code) {
            $cacheKeys[$code] = $this->cacheKey($code);
        }
        $codesByCacheKey = array_flip($cacheKeys);

        foreach (Cache::many(array_values($cacheKeys)) as $cacheKey => $cachedEvents) {
            if ($cachedEvents === null) {
                continue;
            }

            $code = $codesByCacheKey[$cacheKey] ?? null;
            if ($code !== null) {
                $result[$code] = (array) $cachedEvents;
            }
        }

        foreach ($codes as $code) {
            if (! array_key_exists($code, $result)) {
                $pending[] = $code;
            }
        }

        if ($pending === []) {
            return $result;
        }

        $configs = $this->trackingApiConfigs();
        $batchConfig = collect($configs)->first(fn (array $config) => ($config['batch'] ?? false) === true);
        if ($batchConfig !== null) {
            foreach (array_chunk($pending, self::BATCH_SIZE) as $batch) {
                $result = array_merge($result, $this->fetchBatch($batchConfig, $batch));
            }
        }

        $unresolved = array_values(array_diff($pending, array_keys($result)));
        foreach (collect($configs)->reject(fn (array $config) => ($config['batch'] ?? false) === true) as $config) {
            if ($unresolved === []) {
                break;
            }

            foreach (array_chunk($unresolved, self::SINGLE_FALLBACK_CHUNK) as $chunk) {
                $fetched = $this->fetchIndividually($config, $chunk);
                $result = array_merge($result, $fetched);
                $unresolved = array_values(array_diff($unresolved, array_keys($fetched)));
            }
        }

        $cacheValues = [];
        foreach ($pending as $code) {
            $events = $result[$code] ?? [];
            $result[$code] = $events;
            $cacheValues[$this->cacheKey($code)] = $events;
        }
        foreach (array_chunk($cacheValues, 250, true) as $cacheChunk) {
            Cache::putMany($cacheChunk, now()->addMinutes(self::CACHE_MINUTES));
        }

        return $result;
    }

    public function forget(string $code): void
    {
        Cache::forget($this->cacheKey(strtoupper(trim($code))));
    }

    private function fetchBatch(array $config, array $codes): array
    {
        try {
            $response = $this->request($config['token'])
                ->post($config['url'], ['codigos' => $codes]);
        } catch (\Throwable) {
            return [];
        }

        if (! $response->successful()) {
            return [];
        }

        return $this->eventsFromBatchPayload((array) $response->json(), $codes);
    }

    private function fetchIndividually(array $config, array $codes): array
    {
        $responses = [];
        try {
            $responses = Http::pool(function (Pool $pool) use ($config, $codes): array {
                $requests = [];
                foreach ($codes as $code) {
                    $request = $pool
                        ->as($code)
                        ->acceptJson()
                        ->connectTimeout(2)
                        ->timeout($this->timeout())
                        ->withOptions(['verify' => $this->sslVerify()]);
                    if ($config['token'] !== '') {
                        $request = $request->withToken($config['token']);
                    }
                    $requests[] = $request->get($config['url'], ['codigo' => $code]);
                }

                return $requests;
            });
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($responses as $code => $response) {
            if (! $response->successful()) {
                continue;
            }

            $events = $this->eventsFromPayload((array) $response->json(), (string) $code);
            if ($events !== []) {
                $result[strtoupper(trim((string) $code))] = $events;
            }
        }

        return $result;
    }

    private function eventsFromBatchPayload(array $payload, array $requestedCodes): array
    {
        $requested = array_fill_keys($requestedCodes, true);
        $groups = data_get($payload, 'resultado', data_get($payload, 'resultados', []));
        $result = [];

        foreach ((array) $groups as $key => $group) {
            $group = is_array($group) ? $group : (array) $group;
            $code = strtoupper(trim((string) (
                $group['codigo']
                ?? $group['code']
                ?? (is_string($key) ? $key : '')
            )));

            if ($code === '' || ! isset($requested[$code])) {
                continue;
            }

            $events = $this->eventsFromPayload($group, $code);
            if ($events !== []) {
                $result[$code] = $events;
            }
        }

        return $result;
    }

    private function eventsFromPayload(array $payload, string $code): array
    {
        $events = collect(data_get($payload, 'eventos_locales', []))
            ->merge(data_get($payload, 'eventos_externos', []));

        if ($events->isEmpty()) {
            $events = collect(data_get($payload, 'eventos', data_get($payload, 'events', [])));
        }
        if ($events->isEmpty()) {
            $groups = data_get($payload, 'resultado', data_get($payload, 'resultados', []));
            $events = collect((array) $groups)->flatMap(function ($group) {
                return collect(data_get($group, 'eventos_locales', []))
                    ->merge(data_get($group, 'eventos_externos', []))
                    ->merge(data_get($group, 'eventos', data_get($group, 'events', [])));
            });
        }

        return $events
            ->map(fn ($event) => $this->normalizeEvent($event, $payload, $code))
            ->filter()
            ->sortByDesc(fn (array $event) => strtotime((string) ($event['timeline_sort_at'] ?? $event['created_at'] ?? '')) ?: 0)
            ->values()
            ->all();
    }

    private function normalizeEvent(mixed $event, array $payload, string $code): ?array
    {
        $event = is_array($event) ? $event : (array) $event;
        if ($event === []) {
            return null;
        }

        $createdAt = $event['created_at']
            ?? $event['eventDateLocal']
            ?? $event['eventDate']
            ?? $event['fecha_hora']
            ?? $event['fecha_registro']
            ?? $event['fecha']
            ?? null;

        return array_merge($event, [
            'codigo' => $event['codigo'] ?? $event['mailitM_FID'] ?? data_get($payload, 'codigo', $code),
            'evento_id' => $event['evento_id'] ?? $event['id_evento'] ?? null,
            'codigo_evento' => $event['codigo_evento'] ?? $event['eventCode'] ?? $event['event_type_cd'] ?? null,
            'destination_country_name' => $event['destination_country_name']
                ?? data_get($payload, 'meta.destination_country_name')
                ?? data_get($payload, 'destino')
                ?? data_get($payload, 'pais_destino')
                ?? data_get($payload, 'country_destino'),
            'destination_country_code' => $event['destination_country_code']
                ?? data_get($payload, 'meta.destination_country_code'),
            'ciudad_destino' => $event['ciudad_destino']
                ?? $event['destination_city']
                ?? data_get($payload, 'meta.destination_city'),
            'created_at' => $createdAt,
            'timeline_sort_at' => $event['eventDateUtc']
                ?? $event['captureDateUtc']
                ?? $createdAt,
            'nombre_evento' => $event['nombre_evento']
                ?? $event['eventType']
                ?? $event['evento']
                ?? $event['descripcion_evento']
                ?? $event['descripcion']
                ?? 'Evento de seguimiento',
            'postal_status' => $event['postal_status'] ?? $event['estado_postal'] ?? null,
            'office' => $event['office'] ?? $event['oficina'] ?? null,
        ]);
    }

    private function trackingApiConfigs(): array
    {
        $baseUrl = $this->normalizeEventsUrl(trim((string) config('services.tracking_sqlserver.base_url', '')));
        $configuredBatchUrl = trim((string) config('services.tracking_sqlserver.eventos_batch_url', ''));
        $batchUrl = $configuredBatchUrl !== ''
            ? rtrim($configuredBatchUrl, '/')
            : ($baseUrl !== '' ? rtrim($baseUrl, '/').'/batch' : '');
        $fallbackUrl = $this->normalizeEventsUrl(trim((string) config('services.tracking_sqlserver.fallback_base_url', '')));
        $primaryToken = $this->normalizeBearerToken((string) config('services.tracking_sqlserver.token', ''));
        $fallbackToken = $this->normalizeBearerToken((string) config('services.tracking_sqlserver.fallback_token', ''));

        return collect([
            ['url' => $batchUrl, 'token' => $primaryToken, 'batch' => true],
            ['url' => $baseUrl, 'token' => $primaryToken, 'batch' => false],
            ['url' => $fallbackUrl, 'token' => $fallbackToken, 'batch' => false],
        ])
            ->filter(fn (array $config) => $config['url'] !== '')
            ->unique(fn (array $config) => $config['url'])
            ->values()
            ->all();
    }

    private function request(string $token)
    {
        $request = Http::acceptJson()
            ->connectTimeout(2)
            ->timeout($this->timeout())
            ->withOptions(['verify' => $this->sslVerify()]);

        return $token !== '' ? $request->withToken($token) : $request;
    }

    private function normalizeEventsUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?? ''));
        if ($path === '' || $path === '/') {
            return rtrim($url, '/').'/api/tracking/eventos';
        }
        if ($path === '/api/sqlserver/busqueda') {
            return preg_replace('#/api/sqlserver/busqueda$#', '/api/tracking/eventos', $url) ?: $url;
        }

        return rtrim($url, '/');
    }

    private function normalizeBearerToken(string $token): string
    {
        $token = trim($token);

        return str_starts_with(strtolower($token), 'bearer ')
            ? trim(substr($token, 7))
            : $token;
    }

    private function timeout(): int
    {
        return max(1, (int) config('services.tracking_sqlserver.timeout', 15));
    }

    private function sslVerify(): bool
    {
        return (bool) config('services.tracking_sqlserver.ssl_verify', false);
    }

    private function cacheKey(string $code): string
    {
        return 'kardex:sitra:events:v2:'.sha1($code);
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SitraTrackingDestinationClient
{
    // SQL Server allows 2,100 parameters per statement. SITRA binds each code
    // against both its S10 and local ID, so 1,000 codes stay below that limit.
    private const BATCH_SIZE = 1000;
    private const CACHE_READ_CHUNK = 400;
    private const CACHE_WRITE_CHUNK = 250;
    private const CACHE_HOURS = 24;
    private const EMPTY_CACHE_MINUTES = 5;

    /**
     * @param  array<int, string>  $codes
     * @return array<string, string> Normalized tracking code => destination label
     */
    public function destinations(array $codes): array
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

        $destinations = [];
        $pending = [];
        $cacheKeys = [];
        foreach ($codes as $code) {
            $cacheKey = $this->cacheKey($code);
            $cacheKeys[$code] = $cacheKey;
        }
        $codesByCacheKey = array_flip($cacheKeys);

        foreach ($this->readCacheMany(array_values($cacheKeys)) as $cacheKey => $cachedDestination) {
            if ($cachedDestination !== null) {
                $code = $codesByCacheKey[$cacheKey] ?? null;
                if ($code !== null) {
                    $destinations[$code] = (string) $cachedDestination;
                }
            }
        }

        foreach ($codes as $code) {
            if (array_key_exists($code, $destinations)) {
                continue;
            }

            $pending[] = $code;
        }

        $baseUrl = rtrim(trim((string) config('services.sitra_ips.base_url', '')), '/');
        $token = $this->normalizeBearerToken((string) (
            config('services.tracking_sqlserver.token')
            ?: config('services.sitra_ips.token', '')
        ));
        if ($pending === [] || $baseUrl === '' || $token === '') {
            return $destinations;
        }

        foreach (array_chunk($pending, self::BATCH_SIZE) as $batch) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->connectTimeout(2)
                    ->timeout(max(1, (int) config('services.sitra_ips.timeout', 15)))
                    ->withOptions(['verify' => (bool) config('services.sitra_ips.ssl_verify', false)])
                    ->post($baseUrl.'/api/tracking/destinos/batch', ['codigos' => $batch]);
            } catch (\Throwable) {
                continue;
            }

            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            $batchCache = [];
            $emptyBatchCache = [];
            $items = collect((array) $response->json('resultado', []));
            foreach ($items as $item) {
                $item = (array) $item;
                $code = strtoupper(trim((string) ($item['codigo'] ?? '')));
                if ($code === '' || ! in_array($code, $batch, true)) {
                    continue;
                }

                $city = $this->cleanLabel((string) ($item['ciudad'] ?? data_get($item, 'meta.destination_city', '')));
                $country = $this->cleanLabel((string) (
                    $item['pais']
                    ?? $item['pais_destino']
                    ?? data_get($item, 'meta.destination_country_name', '')
                ));
                $destination = $this->cleanLabel((string) ($item['destino'] ?? ''));
                if ($destination === '') {
                    $destination = implode(' / ', array_filter([$city, $country]));
                }

                $destinations[$code] = $destination;
                if ($destination !== '') {
                    $batchCache[$this->cacheKey($code)] = $destination;
                } else {
                    // SITRA sometimes returns the code but no destination yet.
                    // Keep that negative result briefly so it can be retried soon.
                    $emptyBatchCache[$this->cacheKey($code)] = '';
                }
            }

            if ($batchCache !== []) {
                foreach (array_chunk($batchCache, self::CACHE_WRITE_CHUNK, true) as $cacheChunk) {
                    Cache::putMany($cacheChunk, now()->addHours(self::CACHE_HOURS));
                }
            }

            if ($emptyBatchCache !== []) {
                foreach (array_chunk($emptyBatchCache, self::CACHE_WRITE_CHUNK, true) as $cacheChunk) {
                    Cache::putMany($cacheChunk, now()->addMinutes(self::EMPTY_CACHE_MINUTES));
                }
            }
        }

        return $destinations;
    }

    private function cacheKey(string $code): string
    {
        return 'kardex:sitra:destination:v2:'.sha1($code);
    }

    private function readCacheMany(array $keys): array
    {
        $cached = [];
        foreach (array_chunk($keys, self::CACHE_READ_CHUNK) as $keyChunk) {
            $cached = array_merge($cached, Cache::many($keyChunk));
        }

        return $cached;
    }

    private function normalizeBearerToken(string $token): string
    {
        $token = trim($token);

        return str_starts_with(strtolower($token), 'bearer ')
            ? trim(substr($token, 7))
            : $token;
    }

    private function cleanLabel(string $value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        return $value !== '' && $value !== '-' ? mb_strtoupper($value) : '';
    }
}

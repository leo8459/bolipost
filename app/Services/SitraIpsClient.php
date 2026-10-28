<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SitraIpsClient
{
    private const PACKAGE_SERVICE_CACHE_MINUTES = 360;
    private const PACKAGE_SERVICE_CONCURRENCY = 6;

    public function users(array $filters = []): array
    {
        return $this->client()
            ->get($this->url('/api/v1/ips/usuarios'), array_filter($filters, fn ($value) => $value !== null && $value !== ''))
            ->throw()
            ->json();
    }

    public function user(int $pid): array
    {
        return $this->client()
            ->get($this->url('/api/v1/ips/usuarios/'.$pid))
            ->throw()
            ->json('data');
    }

    public function createUser(array $data): array
    {
        return $this->client()
            ->post($this->url('/api/v1/ips/usuarios'), $data)
            ->throw()
            ->json('data');
    }

    public function packages(array $filters = []): array
    {
        return $this->client()
            // LocalDB can need extra time to load its data after startup.
            ->timeout(max(1, (int) config('services.sitra_ips.read_timeout', 60)))
            ->get($this->url('/api/v1/ips/paquetes'), array_filter($filters, fn ($value) => $value !== null && $value !== ''))
            ->throw()->json();
    }

    public function deliveryCatalog(int $office): array
    {
        return $this->client()
            ->get($this->url('/api/v1/ips/catalogos/entrega'), ['office_cd' => $office])
            ->throw()
            ->json('data');
    }

    public function catalog(?int $office = null): array
    {
        return $this->client()
            ->get($this->url('/api/v1/ips/catalogos'), array_filter(['office_cd' => $office]))
            ->throw()
            ->json('data');
    }

    public function createPackage(array $data): array
    {
        return $this->client()
            ->withHeaders(['Idempotency-Key' => $data['idempotency_key']])
            ->post($this->url('/api/v1/ips/paquetes'), $data)
            ->throw()
            ->json();
    }

    public function package(string $codigo, ?int $office = null): array
    {
        return $this->client()->get($this->url('/api/v1/ips/paquetes/'.rawurlencode(strtoupper($codigo))), array_filter(['office_cd' => $office]))
            ->throw()->json('data');
    }

    /**
     * Resolve the postal class for each tracking code from IPS.
     *
     * @param  array<int, string>  $codes
     * @return array<string, string> Service labels keyed by normalized tracking code.
     */
    public function packageServices(array $codes): array
    {
        $codes = collect($codes)
            ->map(fn ($code) => strtoupper(trim((string) $code)))
            ->filter(fn (string $code) => $code !== '' && strlen($code) <= 40 && preg_match('/^[A-Z0-9-]+$/', $code) === 1)
            ->unique()
            ->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $classifications = [];
        $pending = [];
        foreach ($codes as $code) {
            $cached = Cache::get($this->packageServiceCacheKey($code));
            if (is_array($cached) && trim((string) ($cached['mail_class'] ?? '')) !== '') {
                $classifications[$code] = $cached;
            } else {
                $pending[] = $code;
            }
        }

        $token = $this->normalizeBearerToken((string) config('services.sitra_ips.token'));
        $baseUrl = rtrim((string) config('services.sitra_ips.base_url'), '/');
        if ($pending !== [] && $token !== '' && $baseUrl !== '') {
            $timeout = max(1, min(15, (int) config('services.sitra_ips.classification_timeout', config('services.sitra_ips.timeout', 15))));
            $connectTimeout = max(1, min(5, (int) config('services.sitra_ips.connect_timeout', 3)));
            $verify = (bool) config('services.sitra_ips.ssl_verify', false);

            foreach (array_chunk($pending, self::PACKAGE_SERVICE_CONCURRENCY) as $chunk) {
                try {
                    $responses = Http::pool(function (Pool $pool) use ($chunk, $token, $timeout, $connectTimeout, $verify): array {
                        $requests = [];
                        foreach ($chunk as $code) {
                            $requests[] = $pool->as($code)
                                ->acceptJson()
                                ->withToken($token)
                                ->connectTimeout($connectTimeout)
                                ->timeout($timeout)
                                ->withOptions(['verify' => $verify])
                                ->get($this->url('/api/v1/ips/paquetes/'.rawurlencode($code)));
                        }

                        return $requests;
                    });
                } catch (\Throwable) {
                    continue;
                }

                foreach ($chunk as $code) {
                    $response = $responses[$code] ?? null;
                    if (! $response instanceof Response || ! $response->successful()) {
                        continue;
                    }

                    $package = (array) $response->json('data.package', []);
                    $mailClass = trim((string) ($package['mail_class'] ?? ''));
                    if ($mailClass === '') {
                        continue;
                    }

                    $classifications[$code] = [
                        'mail_class' => $mailClass,
                        'product_type' => trim((string) ($package['product_type'] ?? '')),
                    ];
                    Cache::put(
                        $this->packageServiceCacheKey($code),
                        $classifications[$code],
                        now()->addMinutes(self::PACKAGE_SERVICE_CACHE_MINUTES)
                    );
                }
            }
        }

        $classNames = [];
        if ($classifications !== []) {
            try {
                $classNames = Cache::remember('sitra_ips.mail_class_names.v1', now()->addHours(12), function (): array {
                    $catalog = $this->catalog();

                    return collect(data_get($catalog, 'mail_classes', []))
                        ->mapWithKeys(function ($item): array {
                            $code = strtoupper(trim((string) data_get($item, 'MAIL_CLASS_CD', '')));
                            $name = trim((string) data_get($item, 'MAIL_CLASS_NM', ''));

                            return $code !== '' && $name !== '' ? [$code => $name] : [];
                        })
                        ->all();
                });
            } catch (\Throwable) {
                $classNames = [];
            }
        }

        $services = [];
        foreach ($classifications as $code => $classification) {
            $mailClass = trim((string) ($classification['mail_class'] ?? ''));
            $services[$code] = $classNames[strtoupper($mailClass)] ?? $mailClass;
        }

        return $services;
    }

    public function receptacle(string $codigo, int $office): array
    {
        return $this->client()->get(
            $this->url('/api/v1/ips/envases/'.rawurlencode(strtoupper($codigo))),
            ['office_cd' => $office]
        )->throw()->json('data');
    }

    public function expectedInboundReceptacles(int $office, int $page = 1, int $perPage = 25): array
    {
        return $this->client()
            ->timeout(max(1, (int) config('services.sitra_ips.read_timeout', 60)))
            ->get($this->url('/api/v1/ips/envases-pendientes-recepcion'), [
                'office_cd' => $office,
                'page' => $page,
                'per_page' => $perPage,
            ])
            ->throw()
            ->json();
    }

    public function pendingReceptacleOpenings(int $office, int $page = 1, int $perPage = 25): array
    {
        return $this->client()
            ->timeout(max(1, (int) config('services.sitra_ips.read_timeout', 60)))
            ->get($this->url('/api/v1/ips/envases-pendientes-apertura'), [
                'office_cd' => $office,
                'page' => $page,
                'per_page' => $perPage,
            ])
            ->throw()
            ->json();
    }

    public function openedReceptaclesForPackageRegistration(int $office, int $page = 1, int $perPage = 25): array
    {
        return $this->client()
            ->timeout(max(1, (int) config('services.sitra_ips.read_timeout', 60)))
            ->get($this->url('/api/v1/ips/envases-abiertos-registro-paquetes'), [
                'office_cd' => $office,
                'page' => $page,
                'per_page' => $perPage,
            ])
            ->throw()
            ->json();
    }

    public function deliver(string $codigo, array $data): array
    {
        return $this->client()
            ->withHeaders(['Idempotency-Key' => $data['idempotency_key']])
            ->post($this->url('/api/v1/ips/paquetes/'.rawurlencode(strtoupper($codigo)).'/entrega'), $data)
            ->throw()->json();
    }

    public function event(string $codigo, array $data): array
    {
        return $this->client()
            ->withHeaders(['Idempotency-Key' => $data['idempotency_key']])
            ->post($this->url('/api/v1/ips/paquetes/'.rawurlencode(strtoupper($codigo)).'/eventos'), $data)
            ->throw()->json();
    }

    public function receptacleEvent(string $codigo, array $data): array
    {
        return $this->client()
            ->withHeaders(['Idempotency-Key' => $data['idempotency_key']])
            ->post($this->url('/api/v1/ips/envases/'.rawurlencode(strtoupper($codigo)).'/eventos'), $data)
            ->throw()->json();
    }

    private function client(): PendingRequest
    {
        $token = $this->normalizeBearerToken((string) config('services.sitra_ips.token'));
        if ($token === '') {
            throw new \RuntimeException('El token SITRA IPS no esta configurado.');
        }

        return Http::acceptJson()
            ->withToken($token)
            ->timeout(max(1, (int) config('services.sitra_ips.timeout', 15)))
            ->withOptions(['verify' => (bool) config('services.sitra_ips.ssl_verify', false)]);
    }

    private function url(string $path): string
    {
        $baseUrl = rtrim((string) config('services.sitra_ips.base_url'), '/');
        if ($baseUrl === '') {
            throw new \RuntimeException('La URL SITRA IPS no esta configurada.');
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }

    private function normalizeBearerToken(string $token): string
    {
        $token = trim($token);

        return str_starts_with(strtolower($token), 'bearer ')
            ? trim(substr($token, 7))
            : $token;
    }

    private function packageServiceCacheKey(string $code): string
    {
        return 'sitra_ips.package_service.v1.'.strtoupper(trim($code));
    }
}

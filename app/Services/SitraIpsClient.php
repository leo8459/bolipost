<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class SitraIpsClient
{
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

    public function package(string $codigo, ?int $office = null): array
    {
        return $this->client()->get($this->url('/api/v1/ips/paquetes/'.rawurlencode(strtoupper($codigo))), array_filter(['office_cd' => $office]))
            ->throw()->json('data');
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
}

<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FacturacionReportService
{
    public function services(int $month, int $year, int $limit = 200): array
    {
        return $this->get('/ventas/reportes/servicios', [
            'mes' => $month,
            'anio' => $year,
            'limite' => $limit,
        ]);
    }

    public function serviceDetail(string $service, int $month, int $year): array
    {
        return $this->get('/ventas/reportes/servicios/detalle', [
            'servicio' => $service,
            'mes' => $month,
            'anio' => $year,
        ]);
    }

    private function get(string $path, array $query): array
    {
        $baseUrl = rtrim((string) config('services.facturacion_reports.base_url'), '/');
        $token = trim((string) config('services.facturacion_reports.token'));

        if ($baseUrl === '') {
            throw new \RuntimeException('No se configuró FACTURACION_REPORTS_BASE_URL.');
        }

        if ($token === '') {
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_TOKEN.');
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.facturacion_reports.timeout', 30))
                ->connectTimeout((int) config('services.facturacion_reports.connect_timeout', 5))
                ->withOptions(['verify' => (bool) config('services.facturacion_reports.ssl_verify', true)])
                ->get($path, $query);
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('No se pudo conectar con el servicio de reportes de facturación.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new \RuntimeException("El servicio de reportes respondió con el código {$response->status()}.");
        }

        // El servicio remoto actualmente antepone un BOM UTF-8 a la respuesta JSON.
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? $response->body();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('El servicio de reportes devolvió una respuesta inválida.');
        }

        return $decoded;
    }
}

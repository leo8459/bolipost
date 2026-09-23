<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Consulta varios detalles de servicio en paralelo para evitar que los
     * reportes consolidados realicen una petición remota detrás de otra.
     *
     * @param  array<int, array{servicio: string, mes: int, anio: int}>  $filters
     * @return array<int, array{filter: array, report: ?array, error: ?string}>
     */
    public function serviceDetailsBatch(array $filters): array
    {
        $baseUrl = rtrim((string) config('services.facturacion_reports.base_url'), '/');
        $token = trim((string) config('services.facturacion_reports.token'));

        if ($baseUrl === '') {
            throw new \RuntimeException('No se configuró FACTURACION_REPORTS_BASE_URL.');
        }

        if ($token === '') {
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_TOKEN.');
        }

        $results = [];
        foreach (array_chunk(array_values($filters), 10, true) as $chunk) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $baseUrl, $token): array {
                    $requests = [];
                    foreach ($chunk as $index => $filter) {
                        $requests[] = $pool->as((string) $index)
                            ->withToken($token)
                            ->acceptJson()
                            ->timeout((int) config('services.facturacion_reports.timeout', 30))
                            ->connectTimeout((int) config('services.facturacion_reports.connect_timeout', 5))
                            ->withOptions(['verify' => (bool) config('services.facturacion_reports.ssl_verify', true)])
                            ->get($baseUrl.'/ventas/reportes/servicios/detalle', [
                                'servicio' => (string) ($filter['servicio'] ?? ''),
                                'mes' => (int) ($filter['mes'] ?? 0),
                                'anio' => (int) ($filter['anio'] ?? 0),
                            ]);
                    }

                    return $requests;
                });
            } catch (\Throwable $exception) {
                foreach ($chunk as $index => $filter) {
                    $results[$index] = [
                        'filter' => $filter,
                        'report' => null,
                        'error' => $exception->getMessage(),
                    ];
                }

                continue;
            }

            foreach ($chunk as $index => $filter) {
                $response = $responses[(string) $index] ?? null;
                if (! $response instanceof Response || ! $response->successful()) {
                    $results[$index] = [
                        'filter' => $filter,
                        'report' => null,
                        'error' => $response instanceof Response
                            ? "El servicio de reportes respondió con el código {$response->status()}."
                            : 'No se pudo conectar con el servicio de reportes de facturación.',
                    ];

                    continue;
                }

                $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? $response->body();
                $decoded = json_decode($body, true);
                $results[$index] = [
                    'filter' => $filter,
                    'report' => is_array($decoded) ? $decoded : null,
                    'error' => is_array($decoded) ? null : 'El servicio de reportes devolvió una respuesta inválida.',
                ];
            }
        }

        ksort($results);

        return array_values($results);
    }

    public function invoicePdf(string $trackingCode): array
    {
        $trackingCode = trim($trackingCode);
        if ($trackingCode === '') {
            throw new \RuntimeException('La factura no tiene código de seguimiento para consultar su PDF.');
        }

        $invoice = $this->get('/ventas/consultar/'.rawurlencode($trackingCode), []);
        $cuf = trim((string) ($invoice['cuf'] ?? ''));
        $number = trim((string) ($invoice['nroFactura'] ?? $invoice['numeroFactura'] ?? ''));
        $header = (array) data_get($invoice, 'detalleFactura.cabecera', []);
        $pdfUrl = trim((string) ($invoice['pdfUrl'] ?? $invoice['urlPdf'] ?? ''));
        if ($pdfUrl === '' && $cuf !== '') {
            $pdfUrl = rtrim((string) config('services.facturacion_bridge.sefe_public_base_url', 'https://sefe.agetic.gob.bo'), '/')
                .'/public/facturas_pdf/'.rawurlencode($cuf).'.pdf';
        }
        if ($pdfUrl === '') {
            throw new \RuntimeException('La API no devolvió el CUF ni el enlace del PDF de la factura.');
        }

        try {
            $response = Http::accept('application/pdf')
                ->timeout((int) config('services.facturacion_reports.timeout', 30))
                ->connectTimeout((int) config('services.facturacion_reports.connect_timeout', 5))
                ->withOptions(['verify' => (bool) config('services.facturacion_reports.ssl_verify', true)])
                ->get($pdfUrl);
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('No se pudo descargar el PDF oficial de la factura.', 0, $exception);
        }

        $content = $response->body();
        if (! $response->successful() || ! str_starts_with($content, '%PDF')) {
            throw new \RuntimeException('El servicio fiscal no devolvió un PDF válido para esta factura.');
        }

        return [
            'content' => $content,
            'cuf' => $cuf,
            'numero' => $number,
            'razon_social' => trim((string) ($header['nombreRazonSocial'] ?? '')),
            'codigo_cliente' => trim((string) ($header['codigoCliente'] ?? '')),
            'numero_documento' => trim((string) ($header['numeroDocumento'] ?? '')),
            'tipo_documento' => trim((string) ($header['codigoTipoDocumentoIdentidad'] ?? '')),
        ];
    }

    public function invoiceFiscalDataBatch(array $trackingCodes): array
    {
        $codes = collect($trackingCodes)
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values();
        $result = [];
        $missing = [];

        foreach ($codes as $code) {
            $cached = Cache::get('facturacion:fiscal:'.sha1($code));
            if (is_array($cached)) {
                $result[$code] = $cached;
            } else {
                $missing[] = $code;
            }
        }

        $baseUrl = rtrim((string) config('services.facturacion_reports.base_url'), '/');
        $token = trim((string) config('services.facturacion_reports.token'));
        foreach (array_chunk($missing, 10) as $chunk) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($chunk, $baseUrl, $token): array {
                    $requests = [];
                    foreach ($chunk as $code) {
                        $requests[] = $pool->as($code)
                            ->withToken($token)
                            ->acceptJson()
                            ->timeout((int) config('services.facturacion_reports.timeout', 30))
                            ->withOptions(['verify' => (bool) config('services.facturacion_reports.ssl_verify', true)])
                            ->get($baseUrl.'/ventas/consultar/'.rawurlencode($code));
                    }

                    return $requests;
                });
            } catch (\Throwable) {
                continue;
            }

            foreach ($chunk as $code) {
                $response = $responses[$code] ?? null;
                if (! $response || ! $response->successful()) {
                    continue;
                }
                $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? $response->body();
                $invoice = json_decode($body, true);
                if (! is_array($invoice)) {
                    continue;
                }
                $fiscal = $this->fiscalDataFromInvoice($invoice);
                $result[$code] = $fiscal;
                Cache::put('facturacion:fiscal:'.sha1($code), $fiscal, now()->addHours(12));
            }
        }

        return $result;
    }

    private function fiscalDataFromInvoice(array $invoice): array
    {
        $header = (array) data_get($invoice, 'detalleFactura.cabecera', []);

        return [
            'razon_social' => trim((string) ($header['nombreRazonSocial'] ?? '')),
            'codigo_cliente' => trim((string) ($header['codigoCliente'] ?? '')),
            'numero_documento' => trim((string) ($header['numeroDocumento'] ?? '')),
            'tipo_documento' => trim((string) ($header['codigoTipoDocumentoIdentidad'] ?? '')),
        ];
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

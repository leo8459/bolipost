<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacturacionReportService
{
    public function services(
        int $month,
        int $year,
        int $limit = 200,
        bool $useCache = false,
        bool $refresh = false,
        array $reportFilters = []
    ): array
    {
        $query = $this->serviceQuery($month, $year, $limit, $reportFilters);
        if (! $useCache) {
            return $this->getAllServicePages($query);
        }

        $key = $this->summaryCacheKey($month, $year, $limit, $reportFilters);

        return $this->cachedReport($key, $month, $year, $refresh, fn (): array => $this->getAllServicePages($query));
    }

    public function serviceDetail(string $service, int $month, int $year, bool $useCache = false, bool $refresh = false): array
    {
        $query = ['servicio' => $service, 'mes' => $month, 'anio' => $year];
        if (! $useCache) {
            return $this->get('/ventas/reportes/servicios/detalle', $query);
        }

        $key = $this->detailCacheKey($service, $month, $year);

        return $this->cachedReport($key, $month, $year, $refresh, fn (): array => $this->get(
            '/ventas/reportes/servicios/detalle',
            $query
        ));
    }

    /**
     * Consulta mes por mes los reportes que todavía no están en caché.
     *
     * @param  array<int, array{mes: int, anio: int, limite: int}>  $filters
     * @return array<int, array{filter: array, report: ?array, error: ?string}>
     */
    public function servicesBatch(array $filters, bool $refresh = false): array
    {
        $baseUrl = rtrim((string) config('services.facturacion_reports.base_url'), '/');
        $token = trim((string) config('services.facturacion_reports.token'));

        if ($baseUrl === '') {
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_BASE_URL.');
        }
        if ($token === '') {
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_TOKEN.');
        }

        $results = [];
        $uncached = [];
        foreach (array_values($filters) as $index => $filter) {
            $cached = $refresh ? null : $this->readCachedReport($this->summaryCacheKey(
                (int) ($filter['mes'] ?? 0),
                (int) ($filter['anio'] ?? 0),
                (int) ($filter['limite'] ?? 0),
                $filter
            ));
            if ($cached !== null) {
                $results[$index] = ['filter' => $filter, 'report' => $cached, 'error' => null];
            } else {
                $uncached[$index] = $filter;
            }
        }

        // Reportes pesados: consultar mes por mes evita saturar la memoria de la API remota.
        foreach ($uncached as $index => $filter) {
            try {
                $query = $this->serviceQuery(
                    (int) ($filter['mes'] ?? 0),
                    (int) ($filter['anio'] ?? 0),
                    (int) ($filter['limite'] ?? 0),
                    $filter
                );
                $client = Http::withToken($token)
                    ->acceptJson()
                    ->timeout((int) config('services.facturacion_reports.timeout', 30))
                    ->connectTimeout((int) config('services.facturacion_reports.connect_timeout', 5))
                    ->withOptions(['verify' => (bool) config('services.facturacion_reports.ssl_verify', true)]);
                $serviceReportUrl = $baseUrl.'/ventas/reportes/servicios';
                $response = ! empty($query['servicios'])
                    ? $client->post($serviceReportUrl, $query)
                    : $client->get($serviceReportUrl, $query);
            } catch (\Throwable $exception) {
                $results[$index] = ['filter' => $filter, 'report' => null, 'error' => $exception->getMessage()];
                continue;
            }

            if (! $response->successful()) {
                $results[$index] = [
                    'filter' => $filter,
                    'report' => null,
                    'error' => $this->reportHttpError($response),
                ];
                continue;
            }

            $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? $response->body();
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                try {
                    $decoded = $this->completeServicePages($decoded, $query);
                } catch (\Throwable $exception) {
                    $results[$index] = ['filter' => $filter, 'report' => null, 'error' => $exception->getMessage()];
                    continue;
                }
                $this->storeCachedReport(
                    $this->summaryCacheKey(
                        (int) ($filter['mes'] ?? 0),
                        (int) ($filter['anio'] ?? 0),
                        (int) ($filter['limite'] ?? 0),
                        $filter
                    ),
                    $decoded,
                    (int) ($filter['mes'] ?? 0),
                    (int) ($filter['anio'] ?? 0)
                );
            }
            $results[$index] = [
                'filter' => $filter,
                'report' => is_array($decoded) ? $decoded : null,
                'error' => is_array($decoded) ? null : 'El servicio de reportes devolvió una respuesta inválida.',
            ];
        }
        ksort($results);

        return array_values($results);
    }

    /**
     * Consulta varios detalles de servicio en paralelo para evitar que los
     * reportes consolidados realicen una petición remota detrás de otra.
     *
     * @param  array<int, array{servicio: string, mes: int, anio: int}>  $filters
     * @return array<int, array{filter: array, report: ?array, error: ?string}>
     */
    public function serviceDetailsBatch(array $filters, bool $useCache = false, bool $refresh = false): array
    {
        $baseUrl = rtrim((string) config('services.facturacion_reports.base_url'), '/');
        $token = trim((string) config('services.facturacion_reports.token'));

        if ($baseUrl === '') {
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_BASE_URL.');
        }

        if ($token === '') {
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_TOKEN.');
        }

        $results = [];
        $uncached = [];
        foreach (array_values($filters) as $index => $filter) {
            $key = $this->detailCacheKey(
                (string) ($filter['servicio'] ?? ''),
                (int) ($filter['mes'] ?? 0),
                (int) ($filter['anio'] ?? 0)
            );
            $cached = $useCache && ! $refresh ? $this->readCachedReport($key) : null;
            if ($cached !== null) {
                $results[$index] = ['filter' => $filter, 'report' => $cached, 'error' => null];
            } else {
                $uncached[$index] = $filter;
            }
        }

        foreach (array_chunk($uncached, 10, true) as $chunk) {
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
                if ($useCache && is_array($decoded)) {
                    $this->storeCachedReport(
                        $this->detailCacheKey(
                            (string) ($filter['servicio'] ?? ''),
                            (int) ($filter['mes'] ?? 0),
                            (int) ($filter['anio'] ?? 0)
                        ),
                        $decoded,
                        (int) ($filter['mes'] ?? 0),
                        (int) ($filter['anio'] ?? 0)
                    );
                }
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

    private function detailCacheKey(string $service, int $month, int $year): string
    {
        return 'facturacion:reportes:detalle:v3:'.$this->cacheNamespace().':'.sha1($service.'|'.$year.'|'.$month);
    }

    private function summaryCacheKey(int $month, int $year, int $limit, array $filters = []): string
    {
        $filterHash = sha1(json_encode($this->normalizedServiceFilters($filters), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

        return 'facturacion:reportes:servicios:v3:'.$this->cacheNamespace().':'.$year.':'.$month.':'.$limit.':'.$filterHash;
    }

    private function serviceQuery(int $month, int $year, int $limit, array $filters = []): array
    {
        return array_merge([
            'mes' => $month,
            'anio' => $year,
            'limite' => $limit,
            'pagina' => 1,
        ], $this->normalizedServiceFilters($filters));
    }

    private function normalizedServiceFilters(array $filters): array
    {
        $normalized = [];
        foreach (['servicios', 'excluirGruposConteo', 'incluirGruposConteo', 'excluirUsuariosConteo'] as $field) {
            if (! array_key_exists($field, $filters) || ! is_array($filters[$field])) {
                continue;
            }
            $normalized[$field] = collect($filters[$field])
                ->map(fn ($value): string => trim((string) $value))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        }
        if (array_key_exists('regionalConteo', $filters)) {
            $normalized['regionalConteo'] = trim((string) $filters['regionalConteo']);
        }

        return $normalized;
    }

    private function getAllServicePages(array $query): array
    {
        $query['pagina'] = 1;

        return $this->completeServicePages($this->getServiceReportPage($query), $query);
    }

    private function completeServicePages(array $report, array $query): array
    {
        $pagination = (array) data_get($report, 'meta.paginacionServicios', []);
        $totalPages = max(1, (int) ($pagination['totalPaginas'] ?? 1));
        if ($totalPages === 1) {
            return $report;
        }

        $services = collect($report['servicios'] ?? []);
        $seenNames = $services
            ->map(fn (array $service): string => mb_strtoupper(trim((string) ($service['servicio'] ?? ''))))
            ->filter()
            ->flip();
        for ($page = 2; $page <= $totalPages; $page++) {
            $pageReport = $this->getServiceReportPage(array_merge($query, ['pagina' => $page]));
            $reportedPage = (int) data_get($pageReport, 'meta.paginacionServicios.pagina', 0);
            if ($reportedPage !== $page) {
                throw new \RuntimeException('La API de reportes devolvió una página distinta a la solicitada.');
            }
            foreach ((array) ($pageReport['servicios'] ?? []) as $service) {
                $name = mb_strtoupper(trim((string) ($service['servicio'] ?? '')));
                if ($name === '' || $seenNames->has($name)) {
                    throw new \RuntimeException('La paginación de servicios repitió un servicio y no se consolidó para evitar duplicar montos.');
                }
                $seenNames->put($name, true);
                $services->push($service);
            }
        }
        $expectedTotal = (int) ($pagination['total'] ?? $seenNames->count());
        if ($seenNames->count() !== $expectedTotal) {
            throw new \RuntimeException('La paginación de servicios no devolvió todos los registros informados por la API.');
        }

        $report['servicios'] = $services->values()->all();
        $report['meta']['paginacionServicios']['pagina'] = 1;
        $report['meta']['paginacionServicios']['totalPaginas'] = $totalPages;

        return $report;
    }

    private function getServiceReportPage(array $query): array
    {
        if (empty($query['servicios'])) {
            return $this->get('/ventas/reportes/servicios', $query);
        }

        $baseUrl = rtrim((string) config('services.facturacion_reports.base_url'), '/');
        $token = trim((string) config('services.facturacion_reports.token'));
        if ($baseUrl === '') {
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_BASE_URL.');
        }
        if ($token === '') {
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_TOKEN.');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout((int) config('services.facturacion_reports.timeout', 30))
                ->connectTimeout((int) config('services.facturacion_reports.connect_timeout', 5))
                ->withOptions(['verify' => (bool) config('services.facturacion_reports.ssl_verify', true)])
                ->post($baseUrl.'/ventas/reportes/servicios', $query);
        } catch (ConnectionException $exception) {
            throw new \RuntimeException('No se pudo conectar con el servicio de reportes de facturación.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new \RuntimeException($this->reportHttpError($response));
        }

        $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? $response->body();
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('El servicio de reportes devolvió una respuesta inválida.');
        }

        return $decoded;
    }

    private function cacheNamespace(): string
    {
        return substr(sha1(rtrim((string) config('services.facturacion_reports.base_url'), '/')), 0, 12);
    }

    private function cachedReport(string $key, int $month, int $year, bool $refresh, callable $fetch): array
    {
        if (! $refresh) {
            $cached = $this->readCachedReport($key);
            if ($cached !== null) {
                return $cached;
            }
        }

        $report = $fetch();
        $this->storeCachedReport($key, $report, $month, $year);

        return $report;
    }

    private function readCachedReport(string $key): ?array
    {
        try {
            $cached = Cache::get($key);

            return is_array($cached) ? $cached : null;
        } catch (\Throwable $exception) {
            Log::warning('No se pudo leer la caché del reporte de facturación.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function storeCachedReport(string $key, array $report, int $month, int $year): void
    {
        $isCurrentMonth = $year === now()->year && $month === now()->month;
        $ttl = (int) config(
            $isCurrentMonth
                ? 'services.facturacion_reports.cache_active_seconds'
                : 'services.facturacion_reports.cache_closed_seconds',
            $isCurrentMonth ? 60 : 900
        );

        try {
            Cache::put($key, $report, max(1, $ttl));
        } catch (\Throwable $exception) {
            Log::warning('No se pudo guardar la caché del reporte de facturación.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
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
            throw new \RuntimeException('No se configuró FACTURACION_BRIDGE_BASE_URL.');
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
            throw new \RuntimeException($this->reportHttpError($response));
        }

        // El servicio remoto actualmente antepone un BOM UTF-8 a la respuesta JSON.
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? $response->body();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('El servicio de reportes devolvió una respuesta inválida.');
        }

        return $decoded;
    }

    private function reportHttpError(Response $response): string
    {
        $body = $response->json();
        $message = is_array($body)
            ? mb_strtolower(trim((string) ($body['message'] ?? $body['error'] ?? '')))
            : '';

        if (str_contains($message, 'allowed memory size')) {
            return 'El servicio de reportes agotó la memoria al procesar este periodo. La API de facturación debe optimizar esta consulta o ampliar su memoria.';
        }

        return "El servicio de reportes respondió con el código {$response->status()}.";
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PaqueteEmsFacturacionService
{
    private const BATCH_SIZE = 50;

    private const CACHE_SECONDS = 60;

    /**
     * Consulta las facturas de los paquetes visibles en la admision EMS.
     *
     * @param  array<int, array|object>  $packages
     * @return array<string, string> Estado indexado por el codigo normalizado.
     */
    public function statuses(array $packages, bool $refresh = false): array
    {
        $statuses = [];
        $codes = [];

        foreach ($packages as $package) {
            $code = $this->normalizeCode(data_get($package, 'codigo', ''));
            if ($code === '') {
                continue;
            }

            if (mb_strtoupper(trim((string) data_get($package, 'tipo_correspondencia', '')), 'UTF-8') === 'OFICIAL') {
                $statuses[$code] = 'oficial';
                continue;
            }

            if (mb_strtoupper(trim((string) data_get($package, 'servicio_especial', '')), 'UTF-8') === 'POR COBRAR') {
                $statuses[$code] = 'por_cobrar';
                continue;
            }

            $statuses[$code] = 'sin_verificar';
            $codes[$code] = $code;
        }

        if ($codes === []) {
            return $statuses;
        }

        $baseUrl = rtrim((string) config('services.facturacion_reports.base_url'), '/');
        $token = trim((string) config('services.facturacion_reports.token'));
        if ($baseUrl === '' || $token === '') {
            return $statuses;
        }

        $namespace = substr(sha1($baseUrl), 0, 12);
        $pending = [];
        foreach ($codes as $code) {
            $cacheKey = $this->cacheKey($namespace, $code);
            try {
                $cached = $refresh ? null : Cache::get($cacheKey);
            } catch (Throwable) {
                $cached = null;
            }

            if (in_array($cached, ['facturada', 'no_facturada'], true)) {
                $statuses[$code] = $cached;
            } else {
                $pending[] = $code;
            }
        }

        foreach (array_chunk($pending, self::BATCH_SIZE) as $batch) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->asJson()
                    ->timeout((int) config('services.facturacion_reports.timeout', 30))
                    ->connectTimeout((int) config('services.facturacion_reports.connect_timeout', 5))
                    ->withOptions(['verify' => (bool) config('services.facturacion_reports.ssl_verify', true)])
                    ->post($baseUrl.'/paquetes/consultar-facturas', [
                        'codigos_paquete' => $batch,
                    ]);
            } catch (Throwable) {
                continue;
            }

            if (! $response->successful()) {
                continue;
            }

            $body = preg_replace('/^\xEF\xBB\xBF/', '', $response->body()) ?? $response->body();
            $payload = json_decode($body, true);
            if (! is_array($payload) || ($payload['ok'] ?? true) !== true) {
                continue;
            }

            foreach ((array) ($payload['resultados'] ?? []) as $result) {
                if (! is_array($result)) {
                    continue;
                }

                $code = $this->normalizeCode($result['codigo_paquete'] ?? '');
                if ($code === '' || ! in_array($code, $batch, true)) {
                    continue;
                }

                $invoiced = $this->hasMatchingInvoice($result);
                if ($invoiced === null) {
                    continue;
                }

                $status = $invoiced ? 'facturada' : 'no_facturada';
                $statuses[$code] = $status;

                try {
                    Cache::put(
                        $this->cacheKey($namespace, $code),
                        $status,
                        now()->addSeconds(self::CACHE_SECONDS)
                    );
                } catch (Throwable) {
                    // La respuesta de la API se usa aunque no se pueda guardar en caché.
                }
            }
        }

        return $statuses;
    }

    private function hasMatchingInvoice(array $result): ?bool
    {
        // El resultado ya se asocio al codigo solicitado. Se compara la
        // existencia de facturas, independientemente de su vigencia fiscal.
        if (isset($result['coincidencias']) && is_array($result['coincidencias'])) {
            return $result['coincidencias'] !== [];
        }

        if (array_key_exists('facturado', $result)) {
            return $this->toBoolean($result['facturado']);
        }

        return null;
    }

    private function toBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value !== 0.0;
        }

        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'yes', 'si', 'sí' => true,
                '0', 'false', 'no' => false,
                default => null,
            };
        }

        return null;
    }

    private function normalizeCode(mixed $value): string
    {
        return mb_strtoupper(trim((string) $value), 'UTF-8');
    }

    private function cacheKey(string $namespace, string $code): string
    {
        return 'facturacion:paquetes-ems:consulta:v2:'.$namespace.':'.sha1($code);
    }
}

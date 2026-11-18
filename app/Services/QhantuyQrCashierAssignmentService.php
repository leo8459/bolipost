<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QhantuyQrCashierAssignmentService
{
    private const PATH = '/qr/cashier-assignment';

    /** @return array{cashier_user_id: string, branch_code: string, point_sale_id: string}|null */
    public function cashierContext(User $user): ?array
    {
        $sucursal = $user->sucursal;
        $branchCode = trim((string) $sucursal?->codigoSucursal);
        $pointSaleId = trim((string) $sucursal?->puntoVenta);

        if ($branchCode === '' || $pointSaleId === '') {
            return null;
        }

        return [
            'cashier_user_id' => (string) $user->getKey(),
            'branch_code' => $branchCode,
            'point_sale_id' => $pointSaleId,
        ];
    }

    public function configured(array $cashier): bool
    {
        return $this->request('GET', $cashier);
    }

    public function save(array $cashier, #[\SensitiveParameter] string $appkey): void
    {
        if (! $this->request('PUT', $cashier + ['appkey' => $appkey])) {
            throw new RuntimeException('El API no confirmó la asignación de la caja QR.');
        }
    }

    public function clear(array $cashier): void
    {
        if ($this->request('DELETE', $cashier)) {
            throw new RuntimeException('El API no confirmó el cambio a la caja general.');
        }
    }

    private function request(string $method, #[\SensitiveParameter] array $payload): bool
    {
        $baseUrl = rtrim(trim((string) config('services.facturacion_bridge.base_url')), '/');
        $token = trim((string) config('services.facturacion_bridge.qr_assignments_token'));

        if ($baseUrl === '') {
            throw new RuntimeException('Falta configurar la URL del API de facturación en Bolipost.');
        }

        if ($token === '') {
            throw new RuntimeException('Falta configurar FACTURACION_QR_ASSIGNMENTS_TOKEN en Bolipost.');
        }

        if (str_ends_with($baseUrl, '/emitir')) {
            $baseUrl = substr($baseUrl, 0, -strlen('/emitir'));
        }

        $urlParts = parse_url($baseUrl);
        if ($urlParts === false || ! in_array($urlParts['scheme'] ?? '', ['http', 'https'], true)
            || empty($urlParts['host']) || array_intersect_key($urlParts, array_flip(['user', 'pass', 'query', 'fragment']))) {
            throw new RuntimeException('Revisa la URL configurada para el API de facturación.');
        }

        $client = Http::baseUrl($baseUrl)
            ->withToken($token)
            ->acceptJson()
            ->withoutRedirecting()
            ->connectTimeout(3)
            ->timeout(6);

        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            // XAMPP may ship an older CA bundle than the Windows trust store.
            $client->withOptions(['curl' => [CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA]]);
        }

        try {
            $response = match ($method) {
                'GET' => $client->get(self::PATH, $payload),
                'PUT' => $client->put(self::PATH, $payload),
                'DELETE' => $client->send('DELETE', self::PATH, ['json' => $payload]),
            };
        } catch (ConnectionException) {
            throw new RuntimeException('No se pudo conectar con el API de facturación. Revisa el estado de la caja antes de volver a intentar.');
        }

        if ($response->status() === 401) {
            throw new RuntimeException('El API rechazó la conexión. Verifica que el token de administración de cajas QR coincida en ambos proyectos.');
        }

        if ($response->status() === 422) {
            throw new RuntimeException('El API rechazó los datos de la caja QR. Revisa la sucursal, el punto de venta y el appkey.');
        }

        $raw = ltrim($response->body());
        // The billing API can prepend a UTF-8 BOM before the JSON response.
        $body = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw, true);
        $configured = data_get($body, 'data.configured');
        if (! $response->successful() || data_get($body, 'ok') !== true || ! is_bool($configured)) {
            throw new RuntimeException('El API de facturación no pudo confirmar el estado de la caja QR.');
        }

        return $configured;
    }
}

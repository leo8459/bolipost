<?php

namespace App\Http\Middleware;

use App\Models\ExternalApiToken;
use App\Models\ExternalApiUsage;
use App\Support\ExternalApiJwt;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureExternalApiJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        // Chasqui reserva Authorization para el token personal del cartero.
        $jwt = trim((string) $request->header('X-API-Token', ''));

        if ($jwt === '') {
            $jwt = trim((string) $request->bearerToken());
        }

        $payload = $jwt !== '' ? ExternalApiJwt::decode($jwt) : null;
        $jti = is_array($payload) ? trim((string) ($payload['jti'] ?? '')) : '';

        $apiToken = $jti !== ''
            ? ExternalApiToken::query()->where('jti', $jti)->first()
            : null;

        if (! $apiToken || ! $apiToken->isUsable() || ! hash_equals($apiToken->token_hash, hash('sha256', $jwt))) {
            return response()->json([
                'message' => 'Token de acceso invalido, vencido o dado de baja.',
            ], 401);
        }

        $request->attributes->set('external_api_token', $apiToken);
        $response = $next($request);

        if ($response->isSuccessful() || $response->getStatusCode() === Response::HTTP_NOT_MODIFIED) {
            try {
                $this->recordUsage($request, $apiToken);
            } catch (QueryException $exception) {
                report($exception);
            }
        }

        return $response;
    }

    private function recordUsage(Request $request, ExternalApiToken $token): void
    {
        $lastUsedAt = now();
        $token->forceFill(['last_used_at' => $lastUsedAt])->save();

        if (! Schema::hasTable('external_api_usages')) {
            return;
        }

        $method = $request->method();
        $url = $request->getUriForPath('/'.ltrim($request->route()->uri(), '/'));
        $client = strtolower(trim((string) $request->header('X-API-Client')));
        $app = preg_match('/\A[a-z0-9][a-z0-9._-]{0,63}\z/', $client) ? $client : null;
        $origin = $this->httpOrigin($request->headers->get('Origin'))
            ?? $this->httpOrigin($request->headers->get('Referer'));
        $ip = $app === null && $origin === null ? $request->ip() : null;
        $usage = ExternalApiUsage::query()->getQuery();
        $countColumn = $usage->getGrammar()->wrap('external_api_usages.request_count');
        $usage->upsert([
            'external_api_token_id' => $token->id,
            'fingerprint' => hash('sha256', implode("\n", [$method, $url, $app, $origin, $ip])),
            'method' => $method,
            'endpoint_url' => $url,
            'source_app' => $app,
            'source_origin' => $origin,
            'source_ip' => $ip,
            'request_count' => 1,
            'last_used_at' => $lastUsedAt,
        ], ['external_api_token_id', 'fingerprint'], [
            'request_count' => $usage->raw($countColumn.' + 1'),
            'last_used_at' => $lastUsedAt,
        ]);
    }

    private function httpOrigin(?string $url): ?string
    {
        $parts = parse_url($url ?? '');
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');

        if (! in_array($scheme, ['http', 'https'], true)
            || isset($parts['user'])
            || (! filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
                && ! filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))) {
            return null;
        }

        $port = $parts['port'] ?? null;
        $origin = $scheme.'://'.$host;

        if ($port !== null && $port !== ($scheme === 'https' ? 443 : 80)) {
            $origin .= ':'.$port;
        }

        return strlen($origin) <= 255 ? $origin : null;
    }
}

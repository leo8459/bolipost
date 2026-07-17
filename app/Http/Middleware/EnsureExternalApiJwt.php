<?php

namespace App\Http\Middleware;

use App\Models\ExternalApiToken;
use App\Support\ExternalApiJwt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExternalApiJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $jwt = trim((string) $request->bearerToken());

        if ($jwt === '') {
            $jwt = trim((string) $request->header('X-API-Token', ''));
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

        $apiToken->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('external_api_token', $apiToken);

        return $next($request);
    }
}

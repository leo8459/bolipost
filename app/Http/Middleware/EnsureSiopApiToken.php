<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSiopApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = trim((string) env('EVENTOS_SIOP_API_TOKEN', ''));

        if ($configuredToken === '') {
            return response()->json([
                'message' => 'El token de la API SIOP no esta configurado.',
            ], 503);
        }

        $providedToken = $this->extractToken($request);

        if ($providedToken === '' || ! hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'message' => 'Token de acceso invalido o ausente.',
            ], 401);
        }

        return $next($request);
    }

    private function extractToken(Request $request): string
    {
        $bearerToken = trim((string) $request->bearerToken());

        if ($bearerToken !== '') {
            return $bearerToken;
        }

        return trim((string) $request->header('X-API-Token', ''));
    }
}

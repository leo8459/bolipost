<?php

namespace App\Http\Middleware;

use App\Models\ExternalApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExternalApiAbility
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        /** @var ExternalApiToken|null $token */
        $token = $request->attributes->get('external_api_token');
        $abilities = is_array($token?->abilities) ? $token->abilities : [];

        if (! in_array($ability, $abilities, true)) {
            return response()->json([
                'message' => 'El token no tiene permiso para utilizar esta API.',
                'permiso_requerido' => $ability,
            ], 403);
        }

        return $next($request);
    }
}

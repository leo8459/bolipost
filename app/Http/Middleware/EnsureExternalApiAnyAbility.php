<?php

namespace App\Http\Middleware;

use App\Models\ExternalApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExternalApiAnyAbility
{
    public function handle(Request $request, Closure $next, string ...$requiredAbilities): Response
    {
        /** @var ExternalApiToken|null $token */
        $token = $request->attributes->get('external_api_token');
        $abilities = is_array($token?->abilities) ? $token->abilities : [];

        foreach ($requiredAbilities as $ability) {
            if (in_array($ability, $abilities, true)) {
                return $next($request);
            }
        }

        return response()->json([
            'message' => 'El token no tiene permiso para utilizar esta API.',
            'permisos_requeridos' => $requiredAbilities,
        ], 403);
    }
}

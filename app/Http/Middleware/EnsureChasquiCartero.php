<?php

namespace App\Http\Middleware;

use App\Support\ChasquiCartero;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChasquiCartero
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ChasquiCartero::isAllowed($request->user())) {
            return response()->json([
                'message' => 'El usuario autenticado no tiene un rol de cartero habilitado para ChasquiApp.',
            ], 403);
        }

        return $next($request);
    }
}

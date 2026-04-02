<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalWebAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('cliente')->check() && ! Auth::guard('web')->check()) {
            return redirect()
                ->route('clientes.dashboard')
                ->with('warning', 'El panel interno es exclusivo para personal autorizado.');
        }

        Auth::shouldUse('web');

        return $next($request);
    }
}

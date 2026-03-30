<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureClienteProfileComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $cliente = Auth::guard('cliente')->user();

        if ($cliente && ! $cliente->perfilCompleto() && ! $request->routeIs('clientes.profile.complete', 'clientes.profile.complete.store', 'clientes.logout')) {
            return redirect()
                ->route('clientes.profile.complete')
                ->with('warning', 'Completa tus datos antes de continuar.');
        }

        return $next($request);
    }
}

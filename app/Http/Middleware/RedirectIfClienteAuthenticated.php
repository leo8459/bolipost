<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfClienteAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('cliente')->check()) {
            return redirect()->route('clientes.dashboard');
        }

        return $next($request);
    }
}

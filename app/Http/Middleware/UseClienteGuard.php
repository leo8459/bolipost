<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UseClienteGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            // Client pages should not share a browser session with the internal panel.
            Auth::guard('web')->logout();
            $request->session()->forget('url.intended');
        }

        Auth::shouldUse('cliente');

        return $next($request);
    }
}

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
            $impersonatorId = (int) $request->session()->get('impersonator_cliente_admin_id', 0);
            $webUser = Auth::guard('web')->user();
            $isValidClientImpersonation = $impersonatorId > 0
                && (int) $webUser?->id === $impersonatorId
                && $webUser?->isSuperAdmin();

            if (! $isValidClientImpersonation) {
                // Client pages stay separate from the internal panel unless a
                // super administrator explicitly started client impersonation.
                $request->session()->forget('impersonator_cliente_admin_id');
                Auth::guard('web')->logout();
                $request->session()->forget('url.intended');
            }
        }

        Auth::shouldUse('cliente');

        return $next($request);
    }
}

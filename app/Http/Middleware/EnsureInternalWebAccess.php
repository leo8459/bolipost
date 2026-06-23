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
        if (Auth::guard('cliente')->check()) {
            // Keep internal and client sessions isolated even if both cookies survive.
            Auth::guard('cliente')->logout();
            $request->session()->forget('url.intended');
        }

        if (! Auth::guard('web')->check()) {
            return redirect()->route('login');
        }

        $user = Auth::guard('web')->user();
        $role = mb_strtolower(trim((string) ($user?->role ?? '')));
        if ($role === 'conductor' && ! $this->isPrivilegedAdminUser($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Esta cuenta esta habilitada solo para la aplicacion movil.',
            ]);
        }

        Auth::shouldUse('web');

        return $next($request);
    }

    private function isPrivilegedAdminUser(mixed $user): bool
    {
        if (! $user) {
            return false;
        }

        $primaryRole = mb_strtolower(trim((string) ($user->role ?? '')));
        if ($primaryRole === 'admin' || str_contains($primaryRole, 'admin')) {
            return true;
        }

        if (method_exists($user, 'getRoleNames')) {
            foreach ((array) $user->getRoleNames()->toArray() as $roleName) {
                $normalized = mb_strtolower(trim((string) $roleName));
                if ($normalized !== '' && ($normalized === 'admin' || str_contains($normalized, 'admin'))) {
                    return true;
                }
            }
        }

        return false;
    }
}

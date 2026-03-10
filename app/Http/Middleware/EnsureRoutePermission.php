<?php

namespace App\Http\Middleware;

use App\Support\AclPermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoutePermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('acl.route_permission.enabled', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return $next($request);
        }

        $permissionName = $request->route()?->getName();

        if (! is_string($permissionName) || $permissionName === '') {
            return $next($request);
        }

        if (in_array($permissionName, (array) config('acl.excluded_route_permissions', []), true)) {
            return $next($request);
        }

        // If permission wasn't created yet, do not block here.
        if (! AclPermissionRegistry::permissionExists($permissionName)) {
            return $next($request);
        }

        if (! $user->can($permissionName)) {
            abort(403, 'No tienes permiso para acceder a esta ventana o accion.');
        }

        return $next($request);
    }
}

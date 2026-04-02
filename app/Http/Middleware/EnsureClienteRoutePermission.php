<?php

namespace App\Http\Middleware;

use App\Support\ClienteAclPermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureClienteRoutePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('acl_cliente.route_permission.enabled', true)) {
            return $next($request);
        }

        $cliente = Auth::guard('cliente')->user();

        if (! $cliente) {
            return $next($request);
        }

        $permissionName = $request->route()?->getName();

        if (! is_string($permissionName) || $permissionName === '') {
            return $next($request);
        }

        if (in_array($permissionName, (array) config('acl_cliente.excluded_route_permissions', []), true)) {
            return $next($request);
        }

        $permissionsToCheck = ClienteAclPermissionRegistry::authorizationPermissionsForRouteAccess($permissionName);

        foreach ($permissionsToCheck as $permission) {
            if ($cliente->can($permission)) {
                return $next($request);
            }
        }

        abort(403, 'No tienes permiso para acceder a esta vista de cliente.');
    }
}

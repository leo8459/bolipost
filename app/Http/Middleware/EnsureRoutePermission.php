<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AclPermissionRegistry;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
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
        $denialReason = $this->denialReason($request, $request->route()?->getName());
        if ($denialReason !== null) {
            abort(403, $denialReason);
        }

        return $next($request);
    }

    public function denialReason(Request $request, ?string $permissionName): ?string
    {
        if (! (bool) config('acl.route_permission.enabled', true)) {
            return null;
        }

        $user = $request->user();

        if (! $user) {
            return null;
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return null;
        }

        // Compatibilidad con instalaciones que usan variantes de rol admin
        // (por ejemplo: "Super Admin", "Super Admin Nuevo", etc.).
        if ($this->isPrivilegedAdminUser($user)) {
            return null;
        }

        if ($permissionName === null || $permissionName === '') {
            return null;
        }

        if (in_array($permissionName, (array) config('acl.excluded_route_permissions', []), true)) {
            return null;
        }

        $permissionsToCheck = AclPermissionRegistry::authorizationPermissionsForRouteAccess($permissionName);

        $existingPermissions = AclPermissionRegistry::existingPermissionsFrom($permissionsToCheck);

        if ($existingPermissions === []) {
            if ((bool) config('acl.route_permission.allow_when_permission_missing', true)) {
                return null;
            }

            return 'No se encontro la configuracion de permisos para esta ventana.';
        }

        foreach ($existingPermissions as $permission) {
            if ($user->can($permission)) {
                return null;
            }
        }

        // A role may have been assigned or edited after this worker loaded ACL.
        // Retry with committed assignments before rejecting a persisted user.
        if ($user instanceof User && $user->exists) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $user->unsetRelation('roles')->unsetRelation('permissions');

            foreach ($existingPermissions as $permission) {
                if ($user->can($permission)) {
                    return null;
                }
            }
        }

        return 'No tienes permiso para acceder a esta ventana o accion.';
    }

    private function isPrivilegedAdminUser(mixed $user): bool
    {
        $role = mb_strtolower(trim((string) ($user->role ?? '')));
        if ($role === '') {
            return false;
        }

        return $role === 'admin' || str_contains($role, 'admin');
    }
}

<?php

namespace App\Livewire\Hooks;

use App\Support\AclPermissionRegistry;
use Livewire\ComponentHook;

class EnsureLivewireActionPermission extends ComponentHook
{
    public function call($method, $params, $returnEarly): void
    {
        if (! (bool) config('acl.route_permission.enabled', true)) {
            return;
        }

        $componentClass = $this->component::class;

        if (! str_starts_with($componentClass, 'App\\Livewire\\')) {
            return;
        }

        $methodName = (string) $method;

        if ($methodName === '' || str_starts_with($methodName, '__')) {
            return;
        }

        $user = auth()->user();

        if (! $user) {
            abort(403, 'No tienes permiso para ejecutar esta accion.');
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        // Mantener la misma compatibilidad del middleware de rutas:
        // usuarios con variantes de rol "admin" deben poder operar.
        if ($this->isPrivilegedAdminUser($user)) {
            return;
        }

        $permissionsToCheck = AclPermissionRegistry::authorizationPermissionsForLivewireAction(
            $componentClass,
            $methodName,
            $this->component
        );

        if ($permissionsToCheck === []) {
            return;
        }

        $existingPermissions = AclPermissionRegistry::existingPermissionsFrom($permissionsToCheck);

        if ($existingPermissions === []) {
            if ((bool) config('acl.route_permission.allow_when_permission_missing', true)) {
                return;
            }

            abort(403, 'No se encontro la configuracion de permisos para esta accion.');
        }

        foreach ($existingPermissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para ejecutar esta funcionalidad.');
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

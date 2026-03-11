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
}

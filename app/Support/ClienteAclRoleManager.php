<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class ClienteAclRoleManager
{
    public static function sync(): array
    {
        $permissionNames = ClienteAclPermissionRegistry::syncPermissions('cliente');
        $defaultRoles = collect((array) config('acl_cliente.default_roles', []))
            ->filter(fn (mixed $roleName): bool => is_string($roleName) && trim($roleName) !== '')
            ->map(fn (string $roleName): string => trim($roleName))
            ->unique()
            ->values()
            ->all();

        $templates = (array) config('acl_cliente.role_templates', []);

        foreach ($defaultRoles as $roleName) {
            $role = Role::findOrCreate($roleName, 'cliente');
            $patterns = (array) ($templates[$roleName] ?? []);

            if ($patterns === [] || $role->permissions()->exists()) {
                continue;
            }

            $resolvedPermissions = collect($permissionNames)
                ->filter(function (string $permissionName) use ($patterns): bool {
                    foreach ($patterns as $pattern) {
                        if (Str::is($pattern, $permissionName)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->values()
                ->all();

            $role->syncPermissions($resolvedPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissionNames;
    }
}

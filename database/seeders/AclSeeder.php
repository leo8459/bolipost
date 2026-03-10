<?php

namespace Database\Seeders;

use App\Support\AclPermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AclSeeder extends Seeder
{
    public function run(): void
    {
        $permissionNames = AclPermissionRegistry::syncPermissions();
        $defaultRoles = (array) config('acl.default_roles', []);
        $templates = (array) config('acl.role_templates', []);
        $guardName = (string) config('auth.defaults.guard', 'web');
        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        foreach ($defaultRoles as $roleName) {
            $role = Role::findOrCreate($roleName, $guardName);
            $patterns = (array) ($templates[$roleName] ?? []);

            if ($patterns === []) {
                continue;
            }

            $resolvedPermissions = $this->resolvePermissionsFromPatterns($permissionNames, $patterns);

            if ($roleName === $superAdminRole) {
                $role->syncPermissions($resolvedPermissions);
                continue;
            }

            // Preserve existing custom setups; only seed templates when empty.
            if (! $role->permissions()->exists()) {
                $role->syncPermissions($resolvedPermissions);
            }
        }
    }

    /**
     * @param  array<int, string>  $permissionNames
     * @param  array<int, string>  $patterns
     * @return array<int, string>
     */
    private function resolvePermissionsFromPatterns(array $permissionNames, array $patterns): array
    {
        if (in_array('*', $patterns, true)) {
            return $permissionNames;
        }

        return collect($permissionNames)
            ->filter(function (string $permission) use ($patterns): bool {
                foreach ($patterns as $pattern) {
                    if (Str::is($pattern, $permission)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }
}

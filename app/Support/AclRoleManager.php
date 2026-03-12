<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AclRoleManager
{
    public static function sync(): array
    {
        $permissionNames = AclPermissionRegistry::syncPermissions();
        $guardName = (string) config('auth.defaults.guard', 'web');
        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');
        $defaultRoles = self::canonicalRoleNames();
        $templates = (array) config('acl.role_templates', []);

        foreach ($defaultRoles as $roleName) {
            Role::findOrCreate($roleName, $guardName);
        }

        self::migrateLegacyRoles($guardName, $defaultRoles);

        foreach ($defaultRoles as $roleName) {
            $role = Role::findOrCreate($roleName, $guardName);
            $patterns = (array) ($templates[$roleName] ?? []);

            if ($patterns === []) {
                continue;
            }

            $resolvedPermissions = self::resolvePermissionsFromPatterns($permissionNames, $patterns);

            if ($roleName === $superAdminRole) {
                $role->syncPermissions($resolvedPermissions);
                continue;
            }

            if (! $role->permissions()->exists()) {
                $role->syncPermissions($resolvedPermissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissionNames;
    }

    public static function activeRoles()
    {
        $roleNames = self::canonicalRoleNames();

        return Role::query()
            ->whereIn('name', $roleNames)
            ->orderBy('name')
            ->get();
    }

    public static function canonicalRoleNames(): array
    {
        return collect((array) config('acl.default_roles', []))
            ->filter(fn (mixed $roleName): bool => is_string($roleName) && trim($roleName) !== '')
            ->map(fn (string $roleName): string => trim($roleName))
            ->unique()
            ->values()
            ->all();
    }

    private static function migrateLegacyRoles(string $guardName, array $defaultRoles): void
    {
        $aliases = collect((array) config('acl.legacy_role_aliases', []))
            ->filter(fn (mixed $canonicalRole, mixed $legacyRole): bool => is_string($legacyRole) && trim($legacyRole) !== '' && is_string($canonicalRole) && trim($canonicalRole) !== '');

        foreach ($defaultRoles as $canonicalRole) {
            $aliases->put($canonicalRole, $canonicalRole);
        }

        foreach ($defaultRoles as $canonicalRole) {
            $canonical = Role::findOrCreate($canonicalRole, $guardName);

            $caseOnlyDuplicates = Role::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower($canonicalRole)])
                ->where('guard_name', $guardName)
                ->where('id', '!=', $canonical->id)
                ->get();

            foreach ($caseOnlyDuplicates as $duplicateRole) {
                self::mergeRoleInto($duplicateRole, $canonical);
            }
        }

        foreach ($aliases as $legacyRole => $canonicalRole) {
            if ($legacyRole === $canonicalRole) {
                continue;
            }

            $legacy = Role::query()
                ->where('name', $legacyRole)
                ->where('guard_name', $guardName)
                ->first();

            if (! $legacy) {
                continue;
            }

            $canonical = Role::findOrCreate($canonicalRole, $guardName);
            self::mergeRoleInto($legacy, $canonical);
        }
    }

    private static function mergeRoleInto(Role $legacyRole, Role $canonicalRole): void
    {
        if ($legacyRole->id === $canonicalRole->id) {
            return;
        }

        $permissionNames = $legacyRole->permissions()->pluck('name')->all();

        if ($permissionNames !== []) {
            $canonicalRole->givePermissionTo($permissionNames);
        }

        $roleAssignments = DB::table('model_has_roles')
            ->where('role_id', $legacyRole->id)
            ->get();

        foreach ($roleAssignments as $assignment) {
            $exists = DB::table('model_has_roles')
                ->where('role_id', $canonicalRole->id)
                ->where('model_type', $assignment->model_type)
                ->where('model_id', $assignment->model_id)
                ->exists();

            if (! $exists) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $canonicalRole->id,
                    'model_type' => $assignment->model_type,
                    'model_id' => $assignment->model_id,
                ]);
            }
        }

        DB::table('model_has_roles')->where('role_id', $legacyRole->id)->delete();
        $legacyRole->delete();
    }

    private static function resolvePermissionsFromPatterns(array $permissionNames, array $patterns): array
    {
        if (in_array('*', $patterns, true)) {
            return $permissionNames;
        }

        return collect($permissionNames)
            ->filter(function (string $permission) use ($patterns): bool {
                $normalizedPermission = str_starts_with($permission, 'feature.')
                    ? (string) Str::after($permission, 'feature.')
                    : $permission;

                foreach ($patterns as $pattern) {
                    if (Str::is($pattern, $permission) || Str::is($pattern, $normalizedPermission)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }
}

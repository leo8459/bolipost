<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ClienteAclPermissionRegistry
{
    public static function syncPermissions(?string $guardName = null): array
    {
        $guardName ??= 'cliente';
        $permissionNames = self::allPermissionNames();

        $rows = collect($permissionNames)
            ->map(fn (string $permissionName): array => [
                'name' => $permissionName,
                'guard_name' => $guardName,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        if ($rows !== []) {
            Permission::query()->upsert($rows, ['name', 'guard_name'], ['updated_at']);
        }

        Permission::query()
            ->where('guard_name', $guardName)
            ->whereNotIn('name', $permissionNames)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissionNames;
    }

    public static function allPermissionNames(): array
    {
        $routePermissions = array_keys((array) config('acl_cliente.route_permissions', []));
        $featurePermissions = array_keys((array) config('acl_cliente.feature_permissions', []));

        return array_values(array_unique(array_merge($routePermissions, $featurePermissions)));
    }

    public static function groupedPermissionsForMatrix(?string $guardName = null): array
    {
        $guardName ??= 'cliente';
        $configuredPermissions = self::configuredPermissionDefinitions();

        $existing = Permission::query()
            ->where('guard_name', $guardName)
            ->whereIn('name', array_keys($configuredPermissions))
            ->pluck('name')
            ->flip();

        if ($existing->isEmpty()) {
            self::syncPermissions($guardName);
        }

        $groups = [];

        foreach ($configuredPermissions as $permissionName => $definition) {
            $moduleKey = (string) ($definition['module'] ?? self::moduleKeyFromPermission($permissionName));

            if (! isset($groups[$moduleKey])) {
                $groups[$moduleKey] = [
                    'module_key' => $moduleKey,
                    'module_label' => self::moduleLabel($moduleKey),
                    'permissions' => [],
                ];
            }

            $groups[$moduleKey]['permissions'][] = [
                'name' => $permissionName,
                'action_label' => (string) ($definition['label'] ?? self::humanize($permissionName)),
                'hint' => $definition['hint'] ?? null,
                'type_label' => str_starts_with($permissionName, 'feature.') ? 'Boton' : 'Vista',
            ];
        }

        return array_values($groups);
    }

    public static function authorizationPermissionsForRouteAccess(string $routePermission): array
    {
        $aliases = (array) config('acl_cliente.route_permission_aliases', []);
        $canonical = (string) ($aliases[$routePermission] ?? $routePermission);

        return [$canonical];
    }

    public static function permissionExists(string $permissionName): bool
    {
        return in_array($permissionName, self::allPermissionNames(), true);
    }

    private static function configuredPermissionDefinitions(): array
    {
        return array_merge(
            (array) config('acl_cliente.route_permissions', []),
            (array) config('acl_cliente.feature_permissions', [])
        );
    }

    private static function moduleKeyFromPermission(string $permissionName): string
    {
        if (str_starts_with($permissionName, 'feature.')) {
            $parts = explode('.', $permissionName);

            return implode('.', array_slice($parts, 1, 2));
        }

        $parts = explode('.', $permissionName);

        return implode('.', array_slice($parts, 0, 2));
    }

    private static function moduleLabel(string $moduleKey): string
    {
        $labels = (array) config('acl_cliente.module_labels', []);

        return (string) ($labels[$moduleKey] ?? self::humanize($moduleKey));
    }

    private static function humanize(string $value): string
    {
        return Str::headline(str_replace(['.', '-', '_'], ' ', $value));
    }
}

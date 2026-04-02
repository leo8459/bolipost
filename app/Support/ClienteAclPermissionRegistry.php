<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissionNames;
    }

    public static function allPermissionNames(): array
    {
        $excluded = collect((array) config('acl_cliente.excluded_route_permissions', []));

        return collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): ?string => $route->getName())
            ->filter(fn (?string $name): bool => is_string($name) && str_starts_with($name, 'clientes.'))
            ->reject(fn (string $name): bool => $excluded->contains($name))
            ->unique()
            ->values()
            ->all();
    }

    public static function groupedPermissionsForMatrix(?string $guardName = null): array
    {
        $guardName ??= 'cliente';
        $permissionNames = Permission::query()
            ->where('guard_name', $guardName)
            ->orderBy('name')
            ->pluck('name');

        if ($permissionNames->isEmpty()) {
            $permissionNames = collect(self::syncPermissions($guardName));
        }

        $groups = [];

        foreach ($permissionNames as $permissionName) {
            [$moduleKey, $actionKey] = self::splitPermissionName($permissionName);

            if (! isset($groups[$moduleKey])) {
                $groups[$moduleKey] = [
                    'module_key' => $moduleKey,
                    'module_label' => self::moduleLabel($moduleKey),
                    'permissions' => [],
                ];
            }

            $groups[$moduleKey]['permissions'][] = [
                'name' => $permissionName,
                'action_label' => self::actionLabel($actionKey),
                'hint' => 'Controla el acceso a la vista o accion de cliente.',
                'type_label' => 'Ruta cliente',
            ];
        }

        return array_values($groups);
    }

    public static function authorizationPermissionsForRouteAccess(string $routePermission): array
    {
        return [$routePermission];
    }

    public static function permissionExists(string $permissionName): bool
    {
        return Permission::query()
            ->where('guard_name', 'cliente')
            ->where('name', $permissionName)
            ->exists();
    }

    public static function resolveRouteNameFromMenuUrl(string $url): ?string
    {
        if (
            $url === ''
            || str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, '#')
        ) {
            return null;
        }

        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        if ($path === '') {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create('/'.$path, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        $name = $route->getName();

        return is_string($name) && $name !== '' ? $name : null;
    }

    private static function splitPermissionName(string $permissionName): array
    {
        $segments = explode('.', $permissionName);
        $first = $segments[0] ?? $permissionName;
        $second = $segments[1] ?? 'general';
        $action = $segments[2] ?? $second;

        $moduleKey = $first === 'clientes' && isset($segments[1])
            ? $first.'.'.$segments[1]
            : $permissionName;

        return [$moduleKey, $action];
    }

    private static function moduleLabel(string $moduleKey): string
    {
        return (string) ((array) config('acl_cliente.module_labels', []))[$moduleKey]
            ?? Str::headline(str_replace(['.', '-', '_'], ' ', $moduleKey));
    }

    private static function actionLabel(string $actionKey): string
    {
        return match ($actionKey) {
            'dashboard' => 'Acceso al panel',
            'index' => 'Ver listado',
            'create' => 'Abrir formulario',
            'store' => 'Guardar',
            'history' => 'Ver historial',
            default => Str::headline(str_replace(['-', '_'], ' ', $actionKey)),
        };
    }
}

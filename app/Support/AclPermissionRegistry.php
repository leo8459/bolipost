<?php

namespace App\Support;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AclPermissionRegistry
{
    /**
     * Actions most commonly found in route names.
     *
     * @var array<string, string>
     */
    private const ACTION_LABELS = [
        'index' => 'Ver listado',
        'show' => 'Ver detalle',
        'create' => 'Abrir formulario',
        'store' => 'Guardar nuevo',
        'edit' => 'Abrir edicion',
        'update' => 'Actualizar',
        'destroy' => 'Eliminar',
        'delete' => 'Eliminar',
        'restore' => 'Restaurar',
        'restoring' => 'Restaurar',
        'import' => 'Importar',
        'export' => 'Exportar',
        'excel' => 'Exportar Excel',
        'pdf' => 'Exportar PDF',
        'download' => 'Descargar',
        'search' => 'Buscar',
        'entrega' => 'Registrar entrega',
        'boleta' => 'Ver boleta',
        'access' => 'Acceder',
    ];

    /**
     * Sync discovered permissions into database.
     *
     * @return array<int, string>
     */
    public static function syncPermissions(): array
    {
        $guardName = (string) config('auth.defaults.guard', 'web');
        $permissionNames = self::allPermissionNames();

        foreach ($permissionNames as $permissionName) {
            Permission::findOrCreate($permissionName, $guardName);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissionNames;
    }

    /**
     * List all permission names discovered from auth routes + custom entries.
     *
     * @return array<int, string>
     */
    public static function allPermissionNames(): array
    {
        $routePermissions = collect(Route::getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => self::isProtectedRoute($route))
            ->map(fn (IlluminateRoute $route): string => (string) $route->getName());

        $customPermissions = collect(config('acl.custom_permissions', []))
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '');

        return $routePermissions
            ->merge($customPermissions)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Group permissions by module for role checkbox matrix.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function groupedPermissionsForMatrix(): array
    {
        $excludedPermissions = collect(config('acl.excluded_route_permissions', []));

        $permissionNames = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->reject(fn (string $permissionName): bool => $excludedPermissions->contains($permissionName))
            ->values();

        if ($permissionNames->isEmpty()) {
            $permissionNames = collect(self::syncPermissions())
                ->reject(fn (string $permissionName): bool => $excludedPermissions->contains($permissionName))
                ->values();
        }

        $moduleLabels = (array) config('acl.module_labels', []);
        $groups = [];

        foreach ($permissionNames as $permissionName) {
            [$moduleKey, $actionKey] = self::splitPermissionName($permissionName);

            if (! isset($groups[$moduleKey])) {
                $groups[$moduleKey] = [
                    'module_key' => $moduleKey,
                    'module_label' => $moduleLabels[$moduleKey] ?? self::humanize($moduleKey),
                    'permissions' => [],
                ];
            }

            $groups[$moduleKey]['permissions'][] = [
                'name' => $permissionName,
                'action_key' => $actionKey,
                'action_label' => self::actionLabel($permissionName, $actionKey),
            ];
        }

        ksort($groups);

        return array_values($groups);
    }

    /**
     * Check whether a permission exists in cached Spatie registry.
     */
    public static function permissionExists(string $permissionName): bool
    {
        return app(PermissionRegistrar::class)
            ->getPermissions()
            ->contains('name', $permissionName);
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function splitPermissionName(string $permissionName): array
    {
        $segments = explode('.', $permissionName);
        $moduleKey = $segments[0] ?? $permissionName;

        if ($moduleKey === 'api' && isset($segments[1])) {
            $moduleKey = 'api.'.$segments[1];
        }

        $actionKey = count($segments) > 1 ? (string) end($segments) : 'access';

        return [$moduleKey, $actionKey];
    }

    private static function isProtectedRoute(IlluminateRoute $route): bool
    {
        $name = $route->getName();

        if (! is_string($name) || $name === '') {
            return false;
        }

        if (in_array($name, (array) config('acl.excluded_route_permissions', []), true)) {
            return false;
        }

        $middlewares = $route->gatherMiddleware();

        return collect($middlewares)->contains(function (string $middleware): bool {
            return $middleware === 'auth' || str_starts_with($middleware, 'auth:');
        });
    }

    private static function actionLabel(string $permissionName, string $actionKey): string
    {
        if (isset(self::ACTION_LABELS[$actionKey])) {
            return self::ACTION_LABELS[$actionKey];
        }

        if (str_ends_with($permissionName, '.pdf')) {
            return 'Exportar PDF';
        }

        if (str_ends_with($permissionName, '.excel')) {
            return 'Exportar Excel';
        }

        return 'Acceso: '.self::humanize($actionKey);
    }

    private static function humanize(string $value): string
    {
        return Str::headline(str_replace(['.', '-', '_'], ' ', $value));
    }
}

<?php

namespace App\Support;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AclPermissionRegistry
{
    /**
     * Route action labels.
     *
     * @var array<string, string>
     */
    private const ACTION_LABELS = [
        'index' => 'Acceso:Clasificacion',
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
        'sync' => 'Sincronizar permisos',
        'entrega' => 'Registrar entrega',
        'boleta' => 'Ver boleta',
        'access' => 'Acceder',
    ];

    /**
     * Functional permission labels (button/action level).
     *
     * @var array<string, string>
     */
    private const FEATURE_ACTION_LABELS = [
        'view' => 'Botones de visualizacion',
        'create' => 'Botones de registro',
        'edit' => 'Botones de edicion',
        'delete' => 'Botones de eliminacion/baja',
        'restore' => 'Botones de restauracion',
        'export' => 'Botones de exportacion/descarga',
        'import' => 'Botones de importacion',
        'assign' => 'Botones de Recibir paquetes',
        'confirm' => 'Botones de confirmacion',
        'deliver' => 'Botones de entrega',
        'report' => 'Botones de reporte',
        'print' => 'Botones de impresion/boleta',
        'manage' => 'Boton de alta de paquetes',
    ];

    /**
     * Route suffix to feature action map.
     *
     * @var array<string, string>
     */
    private const FEATURE_ACTION_FROM_ROUTE = [
        'index' => 'view',
        'show' => 'view',
        'search' => 'view',
        'create' => 'create',
        'store' => 'create',
        'edit' => 'edit',
        'update' => 'edit',
        'destroy' => 'delete',
        'delete' => 'delete',
        'restore' => 'restore',
        'restoring' => 'restore',
        'export' => 'export',
        'excel' => 'export',
        'pdf' => 'export',
        'download' => 'export',
        'import' => 'import',
        'asignar' => 'assign',
        'assign' => 'assign',
        'confirmar' => 'confirm',
        'confirm' => 'confirm',
        'entrega' => 'deliver',
        'entregar' => 'deliver',
        'reporte' => 'report',
        'report' => 'report',
        'boleta' => 'print',
        'imprimir' => 'print',
        'print' => 'print',
    ];

    /**
     * Livewire component -> ACL module map.
     *
     * @var array<string, string>
     */
    private const LIVEWIRE_COMPONENT_MODULES = [
        'Auditoria' => 'auditoria',
        'CodigoEmpresa' => 'codigo-empresa',
        'Despacho' => 'despachos',
        'DespachoAdmitido' => 'despachos',
        'DespachoExpedicion' => 'despachos',
        'DespachoTodos' => 'despachos',
        'Destino' => 'destinos',
        'Empresa' => 'empresas',
        'Estado' => 'estados',
        'Evento' => 'eventos',
        'EventosAuditoria' => 'eventos-auditoria',
        'EventosTabla' => 'eventos',
        'Origen' => 'origenes',
        'PaqueteCerti' => 'paquetes-certificados',
        'PaquetesEms' => 'paquetes-ems',
        'PaquetesOrdi' => 'paquetes-ordinarios',
        'Peso' => 'pesos',
        'Plantilla' => 'paquetes-ems',
        'Recojo' => 'paquetes-contrato',
        'RecojoCartero' => 'paquetes-contrato',
        'RecojoRecogerEnvios' => 'paquetes-contrato',
        'Saca' => 'sacas',
        'Servicio' => 'servicios',
        'Tarifario' => 'tarifario',
        'Users' => 'users',
        'Ventanilla' => 'ventanillas',
    ];

    /**
     * Dynamic module map for EventosTabla by tipo.
     *
     * @var array<string, string>
     */
    private const EVENTOS_TABLA_TIPO_MODULES = [
        'ems' => 'eventos-ems',
        'certi' => 'eventos-certi',
        'ordi' => 'eventos-ordi',
        'despacho' => 'eventos-despacho',
        'contrato' => 'eventos-contrato',
    ];

    /**
     * Method overrides for Livewire action inference.
     *
     * @var array<string, string>
     */
    private const LIVEWIRE_METHOD_ACTION_OVERRIDES = [
        'admitirdespachos' => 'confirm',
        'altaaalmacen' => 'restore',
        'bajamasiva' => 'delete',
        'cerrardespacho' => 'confirm',
        'confirmarmandargeneradoshoy' => 'confirm',
        'confirmarrecibir' => 'assign',
        'devolveraadmisiones' => 'restore',
        'devolveraclasificacion' => 'restore',
        'devolverrezagoaalmacen' => 'restore',
        'despacharseleccionados' => 'assign',
        'ejecutaroperacion' => 'manage',
        'enqueuereceptaculo' => 'assign',
        'guardarpesocontratoporcodigo' => 'edit',
        'intervenirdespacho' => 'confirm',
        'mandarseleccionadosalmacen' => 'assign',
        'mandarseleccionadoscontratosregional' => 'assign',
        'mandarseleccionadosgeneradoshoy' => 'assign',
        'mandarseleccionadosregional' => 'assign',
        'mandarseleccionadossinfiltrofecha' => 'assign',
        'mandarseleccionadosventanillaems' => 'assign',
        'marcarinventario' => 'edit',
        'marcarventanilla' => 'assign',
        'openadmitirmodal' => 'confirm',
        'opencontratopesomodal' => 'edit',
        'opencontratoregistrarmodal' => 'create',
        'openentregaventanillamodal' => 'deliver',
        'openintervencionmodal' => 'confirm',
        'openreencaminarmodal' => 'edit',
        'openrecibirmodal' => 'assign',
        'openrecibirregionalmodal' => 'assign',
        'openregionalcontratomodal' => 'assign',
        'openregionalmodal' => 'assign',
        'openpasswordmodal' => 'edit',
        'previewadmitir' => 'confirm',
        'reaperturasaca' => 'restore',
        'recibirseleccionadosregional' => 'assign',
        'registrarcontratorapido' => 'create',
        'registrarintervencion' => 'confirm',
        'reimprimircn33' => 'print',
        'reimprimirformularioentrega' => 'print',
        'reimprimirmanifiesto' => 'print',
        'removebatchrow' => 'assign',
        'removescanned' => 'assign',
        'rezagomasivo' => 'delete',
        'saveconfirmed' => 'confirm',
        'savereencaminar' => 'edit',
        'scanandsearch' => 'assign',
        'togglecn33reprint' => 'print',
        'updatepassword' => 'edit',
        'volverapertura' => 'restore',
    ];

    /**
     * @var array<int, string>|null
     */
    private static ?array $cachedLivewireFeaturePermissions = null;

    /**
     * @var array<int, string>|null
     */
    private static ?array $cachedRoutePermissions = null;

    /**
     * @var array<string, bool>|null
     */
    private static ?array $cachedRouteLookup = null;

    /**
     * @var array<string, bool>|null
     */
    private static ?array $cachedExistingPermissionLookup = null;

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
        self::$cachedExistingPermissionLookup = null;

        return $permissionNames;
    }

    /**
     * List all permission names discovered from routes + feature actions + custom entries.
     *
     * @return array<int, string>
     */
    public static function allPermissionNames(): array
    {
        $routePermissions = self::routePermissionNames();
        $featurePermissions = self::featurePermissionNamesFromRoutes($routePermissions);
        $livewireFeaturePermissions = self::featurePermissionNamesFromLivewireComponents();

        $customPermissions = collect(config('acl.custom_permissions', []))
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '');

        return collect($routePermissions)
            ->merge($featurePermissions)
            ->merge($livewireFeaturePermissions)
            ->merge($customPermissions)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function routePermissionNames(): array
    {
        if (self::$cachedRoutePermissions !== null) {
            return self::$cachedRoutePermissions;
        }

        self::$cachedRoutePermissions = collect(Route::getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => self::isProtectedRoute($route))
            ->map(fn (IlluminateRoute $route): string => (string) $route->getName())
            ->unique()
            ->sort()
            ->values()
            ->all();

        self::$cachedRouteLookup = collect(self::$cachedRoutePermissions)
            ->mapWithKeys(fn (string $permission): array => [$permission => true])
            ->all();

        return self::$cachedRoutePermissions;
    }

    /**
     * Functional permissions that can also authorize route actions.
     *
     * @return array<int, string>
     */
    public static function featurePermissionsForRoute(string $routePermission): array
    {
        if ($routePermission === '') {
            return [];
        }

        [$moduleKey, $actionKey] = self::splitPermissionName($routePermission);
        $featureAction = self::canonicalFeatureAction($routePermission, $actionKey);

        return array_values(array_unique([
            self::featurePermissionName($moduleKey, $featureAction),
            self::featurePermissionName($moduleKey, 'manage'),
        ]));
    }

    /**
     * Livewire action permissions used in middleware/hooks.
     *
     * @return array<int, string>
     */
    public static function authorizationPermissionsForLivewireAction(
        string $componentClass,
        string $methodName,
        ?object $component = null
    ): array {
        $targets = self::livewireFeatureTargets($componentClass, $methodName, $component, false);

        if ($targets === []) {
            return [];
        }

        $permissions = [];

        foreach ($targets as $target) {
            foreach (self::authorizationPermissionsForModuleAction(
                $target['module'],
                $target['action'],
                false
            ) as $permission) {
                $permissions[] = $permission;
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return array<int, string>
     */
    public static function featurePermissionsForLivewireAction(
        string $componentClass,
        string $methodName,
        ?object $component = null,
        bool $includeAmbiguous = false
    ): array {
        $targets = self::livewireFeatureTargets($componentClass, $methodName, $component, $includeAmbiguous);

        if ($targets === []) {
            return [];
        }

        $permissions = [];

        foreach ($targets as $target) {
            $permissions[] = self::featurePermissionName($target['module'], $target['action']);
            $permissions[] = self::featurePermissionName($target['module'], 'manage');
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Resolve ACL module keys from a Livewire component class/instance.
     *
     * @return array<int, string>
     */
    public static function moduleKeysForLivewireComponent(string $componentClass, ?object $component = null): array
    {
        return self::livewireModulesForComponent($componentClass, $component);
    }

    /**
     * Permission candidates that can authorize a module feature action.
     *
     * @return array<int, string>
     */
    public static function authorizationPermissionsForModuleAction(
        string $moduleKey,
        string $featureAction,
        bool $includeManage = true
    ): array
    {
        if ($moduleKey === '' || $featureAction === '') {
            return [];
        }

        $permissions = [self::featurePermissionName($moduleKey, $featureAction)];

        if ($includeManage) {
            $permissions[] = self::featurePermissionName($moduleKey, 'manage');
        }

        foreach (self::routePermissionsForModuleAction($moduleKey, $featureAction) as $routePermission) {
            $permissions[] = $routePermission;
        }

        return array_values(array_unique($permissions));
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
                'type' => self::permissionType($permissionName),
                'type_label' => self::permissionTypeLabel($permissionName),
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
        if ($permissionName === '') {
            return false;
        }

        $lookup = self::existingPermissionLookup();

        return isset($lookup[$permissionName]);
    }

    /**
     * @return array<string, bool>
     */
    public static function existingPermissionLookup(): array
    {
        if (self::$cachedExistingPermissionLookup !== null) {
            return self::$cachedExistingPermissionLookup;
        }

        self::$cachedExistingPermissionLookup = app(PermissionRegistrar::class)
            ->getPermissions()
            ->pluck('name')
            ->mapWithKeys(fn (string $permissionName): array => [$permissionName => true])
            ->all();

        return self::$cachedExistingPermissionLookup;
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    public static function existingPermissionsFrom(array $permissions): array
    {
        if ($permissions === []) {
            return [];
        }

        $lookup = self::existingPermissionLookup();

        return array_values(array_filter(
            array_unique($permissions),
            fn (string $permissionName): bool => isset($lookup[$permissionName])
        ));
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function splitPermissionName(string $permissionName): array
    {
        $segments = explode('.', $permissionName);

        if (($segments[0] ?? '') === 'feature' && count($segments) >= 3) {
            $actionKey = (string) array_pop($segments);
            array_shift($segments);
            $moduleKey = implode('.', $segments);

            return [$moduleKey, $actionKey];
        }

        $moduleKey = $segments[0] ?? $permissionName;

        if ($moduleKey === 'api' && isset($segments[1])) {
            $moduleKey = 'api.'.$segments[1];
        }

        $actionKey = count($segments) > 1 ? (string) end($segments) : 'access';

        return [$moduleKey, $actionKey];
    }

    public static function permissionType(string $permissionName): string
    {
        if (str_starts_with($permissionName, 'feature.')) {
            return 'feature';
        }

        if (self::isRoutePermission($permissionName)) {
            return 'route';
        }

        return 'custom';
    }

    public static function permissionTypeLabel(string $permissionName): string
    {
        return match (self::permissionType($permissionName)) {
            'feature' => 'Boton/Funcionalidad',
            'route' => 'Ruta/Ventana',
            default => 'Personalizado',
        };
    }

    /**
     * @param  array<int, string>  $routePermissions
     * @return array<int, string>
     */
    private static function featurePermissionNamesFromRoutes(array $routePermissions): array
    {
        $featurePermissions = [];

        foreach ($routePermissions as $routePermission) {
            foreach (self::featurePermissionsForRoute($routePermission) as $featurePermission) {
                $featurePermissions[] = $featurePermission;
            }
        }

        return collect($featurePermissions)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function featurePermissionNamesFromLivewireComponents(): array
    {
        if (self::$cachedLivewireFeaturePermissions !== null) {
            return self::$cachedLivewireFeaturePermissions;
        }

        $permissions = [];

        foreach (self::livewireComponentClasses() as $componentClass) {
            $reflection = new ReflectionClass($componentClass);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $reflection->getName()) {
                    continue;
                }

                foreach (self::featurePermissionsForLivewireAction(
                    $componentClass,
                    $method->getName(),
                    null,
                    true
                ) as $permissionName) {
                    $permissions[] = $permissionName;
                }
            }
        }

        self::$cachedLivewireFeaturePermissions = collect($permissions)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return self::$cachedLivewireFeaturePermissions;
    }

    /**
     * @return array<int, class-string>
     */
    private static function livewireComponentClasses(): array
    {
        $files = glob(app_path('Livewire/*.php')) ?: [];

        return collect($files)
            ->map(fn (string $file): string => 'App\\Livewire\\'.pathinfo($file, PATHINFO_FILENAME))
            ->filter(fn (string $class): bool => class_exists($class))
            ->filter(fn (string $class): bool => is_subclass_of($class, LivewireComponent::class))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{module:string,action:string}>
     */
    private static function livewireFeatureTargets(
        string $componentClass,
        string $methodName,
        ?object $component = null,
        bool $includeAmbiguous = false
    ): array {
        $actions = self::livewireFeatureActionsForMethod($methodName, $component, $includeAmbiguous);

        if ($actions === []) {
            return [];
        }

        $modules = self::livewireModulesForComponent($componentClass, $component);

        if ($modules === []) {
            return [];
        }

        $targets = [];

        foreach ($modules as $moduleKey) {
            foreach ($actions as $action) {
                $targets[] = [
                    'module' => $moduleKey,
                    'action' => $action,
                ];
            }
        }

        return $targets;
    }

    /**
     * @return array<int, string>
     */
    private static function livewireModulesForComponent(string $componentClass, ?object $component = null): array
    {
        $baseName = class_basename($componentClass);

        if ($baseName === 'EventosTabla') {
            return self::eventosTablaModules($component);
        }

        if (isset(self::LIVEWIRE_COMPONENT_MODULES[$baseName])) {
            return [self::LIVEWIRE_COMPONENT_MODULES[$baseName]];
        }

        $fallbackModule = Str::kebab(Str::pluralStudly($baseName));

        return [$fallbackModule];
    }

    /**
     * @return array<int, string>
     */
    private static function eventosTablaModules(?object $component = null): array
    {
        if ($component && property_exists($component, 'tipo')) {
            $tipo = strtolower(trim((string) $component->tipo));

            if (isset(self::EVENTOS_TABLA_TIPO_MODULES[$tipo])) {
                return [self::EVENTOS_TABLA_TIPO_MODULES[$tipo]];
            }
        }

        return collect(self::EVENTOS_TABLA_TIPO_MODULES)
            ->values()
            ->push('eventos')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function livewireFeatureActionsForMethod(
        string $methodName,
        ?object $component = null,
        bool $includeAmbiguous = false
    ): array {
        $method = strtolower(trim($methodName));
        $normalized = (string) Str::of($method)->replaceMatches('/[^a-z0-9]/', '');

        if ($normalized === '' || str_starts_with($normalized, '__')) {
            return [];
        }

        if (in_array($normalized, ['render', 'rendered', 'mount', 'hydrate', 'boot', 'destroy'], true)) {
            return [];
        }

        if (
            str_starts_with($normalized, 'updated')
            || str_starts_with($normalized, 'updating')
            || str_starts_with($normalized, 'reset')
            || str_starts_with($normalized, 'search')
            || str_starts_with($normalized, 'get')
            || str_starts_with($normalized, 'set')
            || str_starts_with($normalized, 'toggle')
            || str_starts_with($normalized, 'change')
        ) {
            return [];
        }

        if ($normalized === 'applystatusaction') {
            if ($includeAmbiguous || ! $component || ! property_exists($component, 'statusAction')) {
                return ['delete', 'restore'];
            }

            $statusAction = strtolower(trim((string) $component->statusAction));

            return match ($statusAction) {
                'delete' => ['delete'],
                'restore' => ['restore'],
                default => ['manage'],
            };
        }

        if (isset(self::LIVEWIRE_METHOD_ACTION_OVERRIDES[$normalized])) {
            return [self::LIVEWIRE_METHOD_ACTION_OVERRIDES[$normalized]];
        }

        if (str_starts_with($normalized, 'opencreate') || str_starts_with($normalized, 'create')) {
            return ['create'];
        }

        if (
            str_starts_with($normalized, 'openedit')
            || str_starts_with($normalized, 'edit')
            || str_contains($normalized, 'reencaminar')
            || str_contains($normalized, 'password')
        ) {
            return ['edit'];
        }

        if (self::containsAny($normalized, ['delete', 'destroy', 'baja'])) {
            return ['delete'];
        }

        if (self::containsAny($normalized, ['restore', 'restaur', 'alta', 'reapertura', 'volverapertura'])) {
            return ['restore'];
        }

        if (self::containsAny($normalized, ['excel', 'pdf', 'export', 'download'])) {
            return ['export'];
        }

        if (str_contains($normalized, 'import')) {
            return ['import'];
        }

        if (self::containsAny($normalized, ['boleta', 'imprimir', 'reimprimir', 'manifiesto', 'print'])) {
            return ['print'];
        }

        if (self::containsAny($normalized, ['reporte', 'report'])) {
            return ['report'];
        }

        if (self::containsAny($normalized, ['entrega', 'deliver'])) {
            return ['deliver'];
        }

        if (self::containsAny($normalized, ['confirm', 'admit', 'intervencion', 'intervenir'])) {
            return ['confirm'];
        }

        if (self::containsAny($normalized, ['asign', 'assign', 'mandar', 'despach', 'recibir', 'batch', 'enqueue', 'devolver'])) {
            return ['assign'];
        }

        if (
            str_starts_with($normalized, 'save')
            || str_starts_with($normalized, 'guardar')
            || str_starts_with($normalized, 'registrar')
            || str_starts_with($normalized, 'ejecutar')
        ) {
            if (self::containsAny($normalized, ['confirm'])) {
                return ['confirm'];
            }

            if (self::containsAny($normalized, ['peso', 'reencaminar'])) {
                return ['edit'];
            }

            if ($includeAmbiguous) {
                return ['create', 'edit'];
            }

            if ($component && property_exists($component, 'editingId') && ! empty($component->editingId)) {
                return ['edit'];
            }

            return ['create'];
        }

        if (str_starts_with($normalized, 'open')) {
            if (str_contains($normalized, 'entrega')) {
                return ['deliver'];
            }

            if (self::containsAny($normalized, ['admit', 'intervencion'])) {
                return ['confirm'];
            }

            if (self::containsAny($normalized, ['regional', 'recibir', 'contrato', 'peso'])) {
                return ['assign'];
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private static function routePermissionsForModuleAction(string $moduleKey, string $featureAction): array
    {
        $moduleRoutes = collect(self::routePermissionNames())
            ->filter(fn (string $routePermission): bool => $routePermission === $moduleKey || str_starts_with($routePermission, $moduleKey.'.'))
            ->values();

        if ($moduleRoutes->isEmpty()) {
            return [];
        }

        if ($featureAction === 'manage') {
            return $moduleRoutes->all();
        }

        return $moduleRoutes
            ->filter(function (string $routePermission) use ($featureAction): bool {
                [, $actionKey] = self::splitPermissionName($routePermission);

                return self::canonicalFeatureAction($routePermission, $actionKey) === $featureAction;
            })
            ->values()
            ->all();
    }

    private static function featurePermissionName(string $moduleKey, string $featureAction): string
    {
        return 'feature.'.$moduleKey.'.'.$featureAction;
    }

    private static function isRoutePermission(string $permissionName): bool
    {
        if (self::$cachedRouteLookup === null) {
            self::routePermissionNames();
        }

        return isset(self::$cachedRouteLookup[$permissionName]);
    }

    private static function canonicalFeatureAction(string $routePermission, string $actionKey): string
    {
        if (isset(self::FEATURE_ACTION_FROM_ROUTE[$actionKey])) {
            return self::FEATURE_ACTION_FROM_ROUTE[$actionKey];
        }

        if (str_contains($routePermission, '.')) {
            return 'manage';
        }

        return 'view';
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
        $permissionType = self::permissionType($permissionName);

        if ($permissionType === 'feature') {
            return self::FEATURE_ACTION_LABELS[$actionKey] ?? ('Boton: '.self::humanize($actionKey));
        }

        if ($permissionType === 'custom') {
            return 'Funcionalidad: '.self::humanize($actionKey);
        }

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

    /**
     * @param  array<int, string>  $needles
     */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

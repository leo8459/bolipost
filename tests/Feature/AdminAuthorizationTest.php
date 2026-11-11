<?php

namespace Tests\Feature;

use App\Http\Controllers\RoleController;
use App\Http\Middleware\EnsureRoutePermission;
use App\Menu\Filters\RoutePermissionFilter;
use App\Models\Cliente;
use App\Models\User;
use App\Support\AclPermissionRegistry;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ViewErrorBag;
use JeroenNoten\LaravelAdminLte\Menu\Builder;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        AclPermissionRegistry::syncPermissions('web');
    }

    public function test_admin_without_permission_grants_can_open_reception_and_authorize_operations(): void
    {
        $admin = $this->adminWithoutGrants();
        $this->actingAs($admin);
        $request = Request::create('/internacional/recepcion-oficina');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
        $request->setUserResolver(fn () => $admin);

        $response = app(EnsureRoutePermission::class)->handle($request, fn () => app(Authorize::class)->handle($request, fn () => response('Recepcion disponible'), 'internacional.section')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($admin->can('internacional.workflow.operate'));
        $this->assertTrue($admin->can('feature.users.create'));
        $this->assertSame(0, $admin->roles->first()->permissions()->count());
    }

    public function test_admin_menu_keeps_nested_windows_visible_without_permission_grants(): void
    {
        $this->actingAs($this->adminWithoutGrants());
        $menu = new Builder([new RoutePermissionFilter, new GateFilter]);
        $menu->add(...config('adminlte.menu'));
        $urls = [];
        $collectUrls = function (array $items) use (&$collectUrls, &$urls): void {
            foreach ($items as $item) {
                if (isset($item['url'])) {
                    $urls[] = $item['url'];
                }
                $collectUrls($item['submenu'] ?? []);
            }
        };
        $collectUrls($menu->menu());

        foreach (['internacional/recepcion-oficina', 'internacional/almacen/por-entregar', 'users', 'roles', 'ips'] as $path) {
            $this->assertContains($path, $urls);
        }
        $this->assertFalse(Gate::allows('cliente-panel'));
    }

    public function test_operator_permissions_and_unrelated_gates_remain_restricted(): void
    {
        $operator = new User(['name' => 'Operador']);
        $operator->setRelation('roles', new Collection);
        $operator->setRelation('permissions', new Collection);
        $this->assertFalse(Gate::forUser($operator)->allows('internacional.section'));
        $this->assertFalse(Gate::forUser($operator)->allows('internacional.workflow.operate'));

        Gate::define('restricted-business-rule', fn () => false);
        $this->assertFalse(Gate::forUser($this->adminWithoutGrants())->allows('restricted-business-rule'));
    }

    public function test_package_control_menu_and_routes_respect_each_service_permission(): void
    {
        $views = [
            'control-paquetes/contratos' => 'control-paquetes.contratos.index',
            'control-paquetes/ems' => 'control-paquetes.ems.index',
            'delivery-express/paquetes-faro' => 'delivery-express.paquetes-faro.index',
        ];
        foreach ($views as $path => $permission) {
            $operator = new User(['name' => 'Operador']);
            $operator->setRelation('roles', new Collection);
            $operator->setRelation('permissions', new Collection([Permission::findByName($permission, 'web')]));
            $this->actingAs($operator);

            $controlMenu = collect(config('adminlte.menu'))->firstWhere('text', 'Control de paquetes');
            $menu = new Builder([new RoutePermissionFilter, new GateFilter]);
            $menu->add($controlMenu);
            $items = $menu->menu();
            $this->assertCount(1, $items);
            $this->assertSame([$path], array_column($items[0]['submenu'], 'url'));

            $request = Request::create('/'.$path);
            $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
            $request->setUserResolver(fn () => $operator);
            $response = app(EnsureRoutePermission::class)->handle($request, fn () => response('Disponible'));
            $this->assertSame(200, $response->getStatusCode());

            foreach (array_diff(array_keys($views), [$path]) as $otherPath) {
                $denied = Request::create('/'.$otherPath);
                $denied->setRouteResolver(fn () => Route::getRoutes()->match($denied));
                $denied->setUserResolver(fn () => $operator);
                try {
                    app(EnsureRoutePermission::class)->handle($denied, fn () => response('No debe acceder'));
                    $this->fail('El permiso de un servicio no debe autorizar otro servicio.');
                } catch (HttpExceptionInterface $exception) {
                    $this->assertSame(403, $exception->getStatusCode());
                }
            }
        }
    }

    public function test_role_editor_lists_and_saves_all_three_package_control_permissions_independently(): void
    {
        $role = Role::findOrCreate('Consulta de paquetes', 'web');
        $permissions = [
            'delivery-express.paquetes-faro.index',
            'control-paquetes.contratos.index',
            'control-paquetes.ems.index',
        ];
        $controller = app(RoleController::class);
        $data = $controller->edit($role->id)->getData();
        $menu = collect($data['menuPermissionSummary'])->firstWhere('label', 'Control de paquetes');
        $this->assertNotNull($menu);
        $this->assertEqualsCanonicalizing($permissions, array_column(array_column($menu['children'], 'route'), 'name'));
        $data['errors'] = new ViewErrorBag;
        $html = view('role.form', $data)->render();
        foreach ($permissions as $permission) {
            $this->assertStringContainsString('data-target-permission="'.$permission.'"', $html);
        }

        $request = Request::create('/role/'.$role->id, 'PUT', [
            'name' => $role->name,
            'permissions' => $permissions,
        ]);
        $controller->update($request, $role);
        $this->assertEqualsCanonicalizing($permissions, $role->fresh()->permissions->pluck('name')->all());

        $request = Request::create('/role/'.$role->id, 'PUT', [
            'name' => $role->name,
            'permissions' => ['control-paquetes.ems.index'],
        ]);
        $controller->update($request, $role);
        $this->assertSame(['control-paquetes.ems.index'], $role->fresh()->permissions->pluck('name')->all());
        $data = $controller->edit($role->id)->getData();
        $this->assertSame(['control-paquetes.ems.index'], $data['selectedPermissions']);
    }

    public function test_faro_invoice_uses_the_faro_control_permission(): void
    {
        $operator = new User(['name' => 'Consulta Faro']);
        $operator->setRelation('roles', new Collection);
        $operator->setRelation('permissions', new Collection([
            Permission::findByName('delivery-express.paquetes-faro.index', 'web'),
        ]));
        $request = Request::create('/delivery-express/paquetes-faro/factura/1/1');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
        $request->setUserResolver(fn () => $operator);
        $response = app(EnsureRoutePermission::class)->handle($request, fn () => response('Factura disponible'));
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_client_admin_role_does_not_grant_internal_permissions(): void
    {
        $client = new Cliente;
        $client->setRelation('roles', new Collection([Role::findOrCreate('administrador', 'cliente')]));
        $client->setRelation('permissions', new Collection);

        $this->assertFalse(Gate::forUser($client)->allows('internacional.section'));
        $this->assertFalse(Gate::forUser($client)->allows('internacional.workflow.operate'));
    }

    private function adminWithoutGrants(): User
    {
        $admin = new User(['name' => 'Administrador']);
        $admin->setRelation('roles', new Collection([Role::findOrCreate('administrador', 'web')]));
        $admin->setRelation('permissions', new Collection);

        return $admin;
    }
}

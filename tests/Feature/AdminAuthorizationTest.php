<?php

namespace Tests\Feature;

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
use JeroenNoten\LaravelAdminLte\Menu\Builder;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use Spatie\Permission\Models\Role;
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

<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureRoutePermission;
use App\Menu\Filters\RoutePermissionFilter;
use App\Models\User;
use App\Support\AclPermissionRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IpsAclAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        AclPermissionRegistry::syncPermissions();
    }

    public function test_ips_menu_is_hidden_without_acl_permission(): void
    {
        $this->actingAs($this->user());

        $this->assertTrue($this->ipsMenuItem()['restricted'] ?? false);
    }

    public function test_ips_menu_is_visible_with_explicit_acl_permission(): void
    {
        $this->actingAs($this->user(['ips.index']));

        $this->assertFalse($this->ipsMenuItem()['restricted'] ?? false);
    }

    public function test_ips_menu_is_visible_with_permission_inherited_from_admin_role(): void
    {
        $role = Role::findOrCreate('administrador', 'web');
        $role->givePermissionTo('ips.index');
        $user = $this->user();
        $user->setRelation('roles', new Collection([$role]));
        $this->actingAs($user);

        $this->assertFalse($this->ipsMenuItem()['restricted'] ?? false);
    }

    public function test_ips_routes_reject_users_without_acl_permissions(): void
    {
        $routes = [
            ['GET', '/ips'],
            ['POST', '/ips/seleccionados'],
            ['POST', '/ips/operar'],
            ['DELETE', '/ips/seleccionados/RR123456789BO'],
            ['GET', '/paquetes-ips'],
            ['GET', '/recepcion-ips'],
            ['POST', '/recepcion-ips/RR123456789BO'],
            ['POST', '/paquetes-ips/RR123456789BO/entrega'],
        ];

        foreach ($routes as [$method, $path]) {
            $request = $this->requestForUser($method, $path, $this->user());
            $this->assertContains('route.permission', $request->route()->gatherMiddleware());

            try {
                (new EnsureRoutePermission())->handle($request, fn () => response('allowed'));
                $this->fail("La ruta {$method} {$path} permitio acceso sin ACL.");
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }

    public function test_ips_route_accepts_user_with_its_acl_permission(): void
    {
        $request = $this->requestForUser('GET', '/ips', $this->user(['ips.index']));
        $response = (new EnsureRoutePermission())->handle($request, fn () => response('allowed'));

        $this->assertSame(200, $response->getStatusCode());
    }

    private function user(array $permissions = []): User
    {
        $user = new User(['name' => 'Operador']);
        $user->id = 1;
        $user->setRelation('roles', new Collection());
        $user->setRelation('permissions', Permission::whereIn('name', $permissions)->get());

        return $user;
    }

    private function ipsMenuItem(): array
    {
        $item = collect(config('adminlte.menu'))->firstWhere('text', 'IPS');
        $this->assertIsArray($item);
        $item = (new RoutePermissionFilter())->transform($item);

        return (new GateFilter())->transform($item);
    }

    private function requestForUser(string $method, string $path, User $user): Request
    {
        $request = Request::create($path, $method);
        $route = Route::getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        return $request;
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesIpsController;
use App\Http\Middleware\EnsureRoutePermission;
use App\Menu\Filters\RoutePermissionFilter;
use App\Models\User;
use App\Models\UserIpsLink;
use App\Services\SitraIpsClient;
use App\Services\SitraTrackingEventsClient;
use App\Support\AclPermissionRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use JeroenNoten\LaravelAdminLte\Menu\Builder;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function internationalRoleAccess(): array
    {
        $sections = [
            'internacional/recepcion-oficina',
            'internacional/sacas/recibir',
            'internacional/despacho-nacional',
            'internacional/rezago',
            'internacional/todos',
        ];
        $warehouse = [
            'internacional/almacen/por-entregar',
            'internacional/almacen/asignados-a-mi',
            'internacional/almacen/entregados-por-mi',
            'internacional/almacen/entregados',
        ];

        return [
            'without access' => [[], []],
            'sections only' => [['internacional.section'], $sections],
            'warehouse only' => [['internacional.warehouse'], $warehouse],
            'all windows' => [['internacional.section', 'internacional.warehouse'], [...$sections, ...$warehouse]],
            'operation without window access' => [['internacional.workflow.operate'], []],
        ];
    }

    #[DataProvider('internationalRoleAccess')]
    public function test_international_menu_and_windows_follow_non_admin_role_permissions(array $permissions, array $expectedUrls): void
    {
        $role = Role::findOrCreate('operador_internacional', 'web');
        $role->syncPermissions($permissions);
        $user = $this->user();
        $user->setRelation('roles', new Collection([$role]));
        $this->actingAs($user);
        $this->assertFalse($user->isSuperAdmin());

        $menu = new Builder([new RoutePermissionFilter, new GateFilter]);
        $menu->add(...array_filter(config('adminlte.menu'), fn (array $item): bool => ($item['text'] ?? null) === 'Internacional'));
        $items = $menu->menu();
        $this->assertCount($expectedUrls === [] ? 0 : 1, $items);

        $urls = [];
        $collectUrls = function (array $items) use (&$collectUrls, &$urls): void {
            foreach ($items as $item) {
                if (isset($item['url'])) {
                    $urls[] = $item['url'];
                }
                $collectUrls($item['submenu'] ?? []);
            }
        };
        $collectUrls($items);
        $this->assertEqualsCanonicalizing($expectedUrls, $urls);

        foreach ($expectedUrls as $url) {
            $request = $this->requestForUser('GET', '/'.$url, $user);
            $permission = str_contains($url, '/almacen/') ? 'internacional.warehouse' : 'internacional.section';
            $response = app(EnsureRoutePermission::class)->handle($request, fn () => app(Authorize::class)->handle($request, fn () => response('allowed'), $permission));
            $this->assertSame(200, $response->getStatusCode());
        }
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

    public function test_office_receipt_requires_operation_permission_even_with_window_access(): void
    {
        $this->actingAs($this->user(['internacional.section']));
        foreach ([17, 18] as $office) {
            $request = $this->requestForUser('POST', '/internacional/recepcion-oficina/operar?office_cd='.$office, auth()->user());
            $this->assertContains('can:internacional.workflow.operate', $request->route()->gatherMiddleware());
            try {
                app(Authorize::class)->handle($request, fn () => response('allowed'), 'internacional.workflow.operate');
                $this->fail('Acceder a la ventana no debe permitir registrar una recepcion.');
            } catch (AuthorizationException $exception) {
                $this->assertTrue($exception->response()->denied());
            }
        }
    }

    public function test_reception_refreshes_assignments_and_permission_cache_before_denying_access(): void
    {
        $role = Role::findOrCreate('internacional', 'web');
        $user = $this->user();
        $user->exists = true;
        $this->actingAs($user);
        $this->assertFalse($user->can('internacional.section'));

        // Another request commits a new role and grant after this worker cached them.
        DB::table('role_has_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => Permission::findByName('internacional.section', 'web')->id,
        ]);
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->id,
        ]);
        $this->assertFalse($user->can('internacional.section'));
        $request = $this->requestForUser('GET', '/internacional/recepcion-oficina', $user);

        $response = app(EnsureRoutePermission::class)->handle($request, fn () => app(Authorize::class)->handle(
            $request, fn () => response('allowed'), 'internacional.section'
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($user->hasRole('internacional'));
        $this->assertFalse($user->can('internacional.workflow.operate'));
    }

    public function test_reception_still_rejects_a_linked_user_without_the_window_permission(): void
    {
        $user = $this->user();
        $user->exists = true;
        $user->setRelation('ipsLink', new \App\Models\UserIpsLink(['active' => true, 'ips_user_pid' => 73]));
        $this->actingAs($user);
        $request = $this->requestForUser('GET', '/internacional/recepcion-oficina', $user);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('No tienes permiso para acceder a esta ventana o accion.');

        app(EnsureRoutePermission::class)->handle($request, fn () => response('allowed'));
    }

    public function test_office_receipt_accepts_operation_permission_from_the_users_role(): void
    {
        $role = Role::findOrCreate('operador_recepcion', 'web');
        $role->givePermissionTo('internacional.workflow.operate');
        $user = $this->user();
        $user->setRelation('roles', new Collection([$role]));
        $this->actingAs($user);
        foreach ([17, 18] as $office) {
            $request = $this->requestForUser('POST', '/internacional/recepcion-oficina/operar?office_cd='.$office, $user);
            $response = app(Authorize::class)->handle($request, fn () => response('allowed'), 'internacional.workflow.operate');
            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_internacional_role_migration_grants_actions_to_current_and_future_users(): void
    {
        $role = Role::findOrCreate('internacional', 'web');
        $role->givePermissionTo(['internacional.section', 'internacional.warehouse', 'users.index']);
        $readerRole = Role::findOrCreate('consulta_internacional', 'web');
        $readerRole->givePermissionTo('internacional.section');
        $migration = require database_path('migrations/2026_11_10_150000_complete_internacional_role_permissions.php');
        $migration->up();
        $migration->up();

        foreach ([1, 2] as $userId) {
            $user = $this->user();
            $user->id = $userId;
            $user->setRelation('roles', new Collection([$role->fresh()]));
            $this->actingAs($user);
            $this->assertFalse($user->isSuperAdmin());

            foreach ([
                ['POST', '/internacional/recepcion-oficina/seleccionados', 'internacional.workflow.selection.add'],
                ['DELETE', '/internacional/recepcion-oficina/seleccionados/PACKAGE01', 'internacional.workflow.selection.remove'],
                ['POST', '/internacional/recepcion-oficina/operar', 'internacional.workflow.operate'],
                ['POST', '/internacional/almacen/mi-reparto/seleccionados', 'internacional.selection.add'],
                ['POST', '/internacional/almacen/mi-reparto/operar', 'internacional.operate'],
                ['POST', '/internacional/sacas/recibir/operar', 'internacional.workflow.operate'],
            ] as [$method, $path, $permission]) {
                $request = $this->requestForUser($method, $path, $user);
                $response = app(EnsureRoutePermission::class)->handle($request, fn () => app(Authorize::class)->handle(
                    $request, fn () => response('allowed'), $permission
                ));
                $this->assertSame(200, $response->getStatusCode());
            }
        }

        $this->assertTrue($role->fresh()->hasPermissionTo('users.index'));
        $this->assertFalse($readerRole->fresh()->hasPermissionTo('internacional.workflow.operate'));
        $this->assertFalse($role->fresh()->hasPermissionTo('ips.index'));
    }

    public function test_internacional_operator_can_add_remove_and_confirm_a_reception(): void
    {
        (require database_path('migrations/2026_11_10_150000_complete_internacional_role_permissions.php'))->up();
        $user = $this->user();
        $user->setRelation('roles', new Collection([Role::findByName('internacional', 'web')]));
        $user->setRelation('ipsLink', new UserIpsLink([
            'active' => true, 'ips_user_pid' => 73, 'ips_office_cd' => 17,
            'last_snapshot' => ['office_cd' => 17],
        ]));
        $this->actingAs($user);
        $package = ['codigo' => 'PACKAGE01', 'event_cd' => 35, 'event_at' => '2026-11-10T12:00:00Z', 'allowed_actions' => ['EMG']];
        $ips = Mockery::mock(SitraIpsClient::class);
        $ips->shouldReceive('package')->times(3)->with('PACKAGE01', 17)->andReturn(['package' => $package]);
        $ips->shouldReceive('user')->once()->with(73)->andReturn(['user_pid' => 73, 'office_cd' => 17]);
        $ips->shouldReceive('event')->once()->with('PACKAGE01', Mockery::on(fn (array $payload): bool =>
            $payload['event'] === 'EMG' && $payload['office_cd'] === 17
            && $payload['actor_user_pid'] === 73 && $payload['external_actor_id'] === 'bolipost:1'
            && $payload['physical_receipt_confirmed'] === true
        ))->andReturn(['status' => 'succeeded']);
        $tracking = Mockery::mock(SitraTrackingEventsClient::class);
        $tracking->shouldReceive('forget')->once()->with('PACKAGE01');
        $controller = app(PaquetesIpsController::class);

        foreach ([
            ['POST', '/internacional/recepcion-oficina/seleccionados', 'internacional.workflow.selection.add', 1],
            ['DELETE', '/internacional/recepcion-oficina/seleccionados/PACKAGE01', 'internacional.workflow.selection.remove', 0],
            ['POST', '/internacional/recepcion-oficina/seleccionados', 'internacional.workflow.selection.add', 1],
        ] as [$method, $path, $permission, $count]) {
            $request = $this->requestForUser($method, $path, $user);
            $request->merge(['codigo' => 'PACKAGE01']);
            $request->setLaravelSession(app('session')->driver());
            $request->headers->set('Accept', 'application/json');
            $response = app(EnsureRoutePermission::class)->handle($request, fn () => app(Authorize::class)->handle(
                $request, fn () => $method === 'DELETE'
                    ? $controller->removeInternationalSelection($request, 'recepcion-oficina', 'PACKAGE01')
                    : $controller->addInternationalSelection($request, $ips, 'recepcion-oficina'), $permission
            ));
            $this->assertSame(200, $response->getStatusCode());
            $this->assertSame($count, $response->getData(true)['count']);
        }

        $request = $this->requestForUser('POST', '/internacional/recepcion-oficina/operar', $user);
        $request->merge(['codigo' => 'PACKAGE01', 'action' => 'EMG', 'confirmed' => '1', 'physical_receipt_confirmed' => '1']);
        $request->setLaravelSession(app('session')->driver());
        $request->headers->set('Accept', 'application/json');
        $response = app(EnsureRoutePermission::class)->handle($request, fn () => app(Authorize::class)->handle(
            $request, fn () => $controller->operateInternational($request, $ips, $tracking, 'recepcion-oficina'), 'internacional.workflow.operate'
        ));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('succeeded', $response->getData(true)['status']);
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
        $findIps = function (array $items) use (&$findIps): ?array {
            foreach ($items as $item) {
                if (($item['text'] ?? null) === 'IPS') {
                    return $item;
                }

                if ($found = $findIps($item['submenu'] ?? [])) {
                    return $found;
                }
            }

            return null;
        };
        $item = $findIps(config('adminlte.menu'));
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

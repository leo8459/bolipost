<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\RoleController;
use App\Http\Middleware\EnsureAclPermissionsSynced;
use App\Http\Requests\Auth\LoginRequest;
use App\Menu\Filters\RoutePermissionFilter;
use App\Models\User;
use App\Support\AclRoleManager;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AclMenuConsistencyTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Independent connections reproduce a menu request during a role update.
        $this->databasePath = tempnam(sys_get_temp_dir(), 'bolipost-acl-');
        config()->set('database.connections.sqlite.database', $this->databasePath);
        config()->set('database.connections.acl_menu_reader', config('database.connections.sqlite'));
        DB::purge('sqlite');
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();

        config()->set('acl.default_roles', ['administrador']);
        config()->set('acl.legacy_role_aliases', ['ADMINISTRADOR' => 'administrador']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        DB::purge('acl_menu_reader');
        DB::purge('sqlite');
        unlink($this->databasePath);

        parent::tearDown();
    }

    public function test_menu_request_keeps_committed_permissions_during_automatic_sync(): void
    {
        $role = $this->roleWithIpsPermission('administrador');
        $observedDuringUpdate = false;
        DB::listen(function (QueryExecuted $query) use ($role, &$observedDuringUpdate): void {
            if (! $this->isPermissionDetach($query)) {
                return;
            }

            $observedDuringUpdate = true;
            $this->assertTrue($this->readerHasIpsPermission($role));
        });

        AclRoleManager::sync();

        $this->assertTrue($observedDuringUpdate);
        $this->assertTrue($this->readerHasIpsPermission($role));
        $this->assertTrue($role->fresh()->hasPermissionTo('users.index'));
    }

    public function test_role_edit_keeps_old_permissions_visible_until_commit_and_applies_revocations(): void
    {
        $role = $this->roleWithIpsPermission('operador');
        $observedDuringUpdate = false;
        DB::listen(function (QueryExecuted $query) use ($role, &$observedDuringUpdate): void {
            if (! $this->isPermissionDetach($query)) {
                return;
            }

            $observedDuringUpdate = true;
            $this->assertTrue($this->readerHasIpsPermission($role));
        });

        $request = Request::create('/roles/'.$role->id, 'PUT', [
            'name' => 'operador actualizado',
            'permissions' => ['users.index'],
        ]);
        (new RoleController())->update($request, $role);

        $this->assertTrue($observedDuringUpdate);
        $this->assertFalse($this->readerHasIpsPermission($role));
        $this->assertSame(['users.index'], $role->fresh()->permissions()->pluck('name')->all());
        $this->assertSame('operador actualizado', $role->fresh()->name);
    }

    public function test_failed_sync_rolls_back_instead_of_leaving_the_menu_without_permissions(): void
    {
        $role = $this->roleWithIpsPermission('administrador');
        DB::listen(function (QueryExecuted $query): void {
            if ($this->isPermissionDetach($query)) {
                throw new RuntimeException('Fallo simulado durante la sincronizacion.');
            }
        });

        try {
            AclRoleManager::sync();
            $this->fail('La sincronizacion debio fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado durante la sincronizacion.', $exception->getMessage());
        }

        $this->assertTrue($this->readerHasIpsPermission($role));
        $this->assertSame(['ips.index'], $role->fresh()->permissions()->pluck('name')->all());
        $this->assertTrue($role->fresh()->hasPermissionTo('ips.index'));
    }

    public function test_sync_reloads_user_roles_after_migrating_a_legacy_assignment(): void
    {
        $role = $this->roleWithIpsPermission('ADMINISTRADOR');
        $user = new User(['name' => 'Administrador']);
        $user->id = 1;
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->id,
        ]);
        $this->assertTrue($user->hasRole('ADMINISTRADOR'));
        $this->assertFalse($user->hasRole('administrador'));
        $this->actingAs($user);
        $request = Request::create('/inicio');
        $request->setUserResolver(fn () => $user);

        (new EnsureAclPermissionsSynced())->handle($request, function () use ($user) {
            $this->assertTrue($user->hasRole('administrador'));
            $this->assertTrue($user->can('ips.index'));

            return response('Menu disponible');
        });

        $this->assertTrue(Cache::has('acl:permissions:sync:at'));
    }

    public function test_parallel_request_does_not_start_a_second_automatic_sync(): void
    {
        $user = new User(['name' => 'Operador']);
        $request = Request::create('/inicio');
        $request->setUserResolver(fn () => $user);
        $lock = Cache::lock('acl:permissions:sync:lock', 120);
        $this->assertTrue($lock->get());

        try {
            $response = (new EnsureAclPermissionsSynced())->handle($request, fn () => response('Menu disponible'));
            $this->assertSame('Menu disponible', $response->getContent());
            $this->assertSame(0, Role::count());
            $this->assertFalse(Cache::has('acl:permissions:sync:at'));
        } finally {
            $lock->release();
        }
    }

    public function test_login_discards_incomplete_permission_cache_and_loaded_assignments(): void
    {
        Permission::findOrCreate('ips.index', 'web');
        $role = Role::findOrCreate('operador', 'web');
        $user = new User(['name' => 'Operador']);
        $user->id = 1;
        $this->actingAs($user);
        $this->assertFalse($user->can('ips.index'));

        $request = Mockery::mock(LoginRequest::class)->makePartial();
        $request->initialize();
        $request->setLaravelSession(app('session')->driver());
        $request->setUserResolver(fn () => $user);
        $request->shouldReceive('authenticate')->once()->andReturnUsing(function () use ($role, $user): void {
            // Represent assignments committed by another request after the cache was loaded.
            DB::table('role_has_permissions')->insert([
                'role_id' => $role->id,
                'permission_id' => Permission::where('name', 'ips.index')->value('id'),
            ]);
            DB::table('model_has_roles')->insert([
                'role_id' => $role->id,
                'model_type' => $user->getMorphClass(),
                'model_id' => $user->id,
            ]);
        });

        $response = (new AuthenticatedSessionController())->store($request);

        $this->assertSame(route('home.welcome'), $response->getTargetUrl());
        $this->assertTrue($user->can('ips.index'));
    }

    public function test_menu_shows_assigned_access_and_hides_unassigned_access(): void
    {
        $role = $this->roleWithIpsPermission('operador');
        $user = new User(['name' => 'Operador']);
        $user->id = 1;
        DB::table('model_has_roles')->insert([
            'role_id' => $role->id,
            'model_type' => $user->getMorphClass(),
            'model_id' => $user->id,
        ]);
        Permission::findOrCreate('users.index', 'web');
        $this->actingAs($user);

        $routeFilter = new RoutePermissionFilter();
        $gateFilter = new GateFilter();
        $allowed = $gateFilter->transform($routeFilter->transform(['text' => 'IPS', 'url' => 'ips']));
        $denied = $gateFilter->transform($routeFilter->transform(['text' => 'Personal', 'url' => 'users']));

        $this->assertFalse($allowed['restricted'] ?? false);
        $this->assertTrue($denied['restricted'] ?? false);
    }

    private function roleWithIpsPermission(string $name): Role
    {
        Permission::findOrCreate('ips.index', 'web');
        $role = Role::findOrCreate($name, 'web');
        $role->givePermissionTo('ips.index');

        return $role;
    }

    private function isPermissionDetach(QueryExecuted $query): bool
    {
        return $query->connectionName === 'sqlite'
            && str_starts_with(strtolower($query->sql), 'delete from "role_has_permissions"');
    }

    private function readerHasIpsPermission(Role $role): bool
    {
        return DB::connection('acl_menu_reader')->table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('role_id', $role->id)
            ->where('permissions.name', 'ips.index')
            ->exists();
    }
}

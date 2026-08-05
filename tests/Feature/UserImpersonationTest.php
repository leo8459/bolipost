<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\EnsureAclPermissionsSynced;
use App\Http\Middleware\EnsureEmpresaContractUsersActive;
use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\RegistrarAuditoria;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserImpersonationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureAclPermissionsSynced::class,
            EnsureEmpresaContractUsersActive::class,
            EnsureRoutePermission::class,
            RegistrarAuditoria::class,
        ]);

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('alias')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->string('ciudad')->nullable();
            $table->json('regionales')->nullable();
            $table->string('provincia_origen')->nullable();
            $table->string('ci')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('sucursal_id')->nullable();
            $table->timestamp('auto_baja_empresa_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_administrator_can_enter_as_an_active_user_and_return(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::create([
            'name' => config('acl.super_admin_role', 'administrador'),
            'guard_name' => 'web',
        ]));
        $target = User::factory()->create();

        $response = $this
            ->actingAs($administrator)
            ->post(route('users.impersonate', $target));

        $response
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('impersonator_id', $administrator->id);
        $this->assertAuthenticatedAs($target);

        $returnResponse = $this->post(route('users.impersonate.destroy'));

        $returnResponse
            ->assertRedirect(route('users.index'))
            ->assertSessionMissing('impersonator_id');
        $this->assertAuthenticatedAs($administrator);
    }

    public function test_non_administrator_cannot_enter_as_another_user(): void
    {
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this
            ->actingAs($user)
            ->post(route('users.impersonate', $target))
            ->assertForbidden();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('impersonator_id'));
    }

    public function test_administrator_cannot_enter_as_an_inactive_user(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(Role::create([
            'name' => config('acl.super_admin_role', 'administrador'),
            'guard_name' => 'web',
        ]));
        $target = User::factory()->create();
        $target->delete();

        $this
            ->actingAs($administrator)
            ->post('/users/'.$target->id.'/ingresar')
            ->assertNotFound();

        $this->assertAuthenticatedAs($administrator);
        $this->assertFalse(session()->has('impersonator_id'));
    }
}

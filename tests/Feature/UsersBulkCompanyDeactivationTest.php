<?php

namespace Tests\Feature;

use App\Livewire\Users;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UsersBulkCompanyDeactivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente');
            $table->timestamps();
        });

        Schema::create('sucursales', function (Blueprint $table): void {
            $table->id();
            $table->string('codigoSucursal')->nullable();
            $table->string('puntoVenta')->nullable();
            $table->timestamps();
        });

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

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('sucursales');
        Schema::dropIfExists('empresa');

        parent::tearDown();
    }

    public function test_it_filters_explicitly_and_changes_status_only_for_the_selected_dependent_company(): void
    {
        $administrator = User::factory()->create();
        $administrator->givePermissionTo([
            Permission::create([
                'name' => 'feature.users.empresas.delete',
                'guard_name' => 'web',
            ]),
            Permission::create([
                'name' => 'feature.users.empresas.restore',
                'guard_name' => 'web',
            ]),
        ]);

        $firstCompany = Empresa::query()->create([
            'nombre' => 'Empresa principal',
            'sigla' => 'EP',
            'codigo_cliente' => 'CLI-100',
        ]);
        $dependentCompany = Empresa::query()->create([
            'nombre' => 'Empresa dependiente',
            'sigla' => 'ED',
            'codigo_cliente' => 'cli-100 ',
        ]);
        $otherCompany = Empresa::query()->create([
            'nombre' => 'Otra empresa',
            'sigla' => 'OE',
            'codigo_cliente' => 'CLI-200',
        ]);

        $firstUser = User::factory()->create(['empresa_id' => $firstCompany->id]);
        $dependentUser = User::factory()->create(['empresa_id' => $dependentCompany->id]);
        $alreadyInactiveUser = User::factory()->create(['empresa_id' => $dependentCompany->id]);
        $alreadyInactiveUser->delete();
        $otherUser = User::factory()->create(['empresa_id' => $otherCompany->id]);

        $component = Livewire::actingAs($administrator)
            ->test(Users::class, ['empresaMode' => true]);

        $component
            ->assertSee('CLI-100 - 2 empresa(s)')
            ->set('filterCodigoCliente', 'cli-100')
            ->assertSet('filterCodigoCliente', 'CLI-100')
            ->assertSet('appliedFilterCodigoCliente', '')
            ->assertSee('Todas las empresas del codigo CLI-100')
            ->call('applyFilters')
            ->assertSet('appliedFilterCodigoCliente', 'CLI-100')
            ->set('bulkCodigoCliente', ' cli-100 ')
            ->assertSee('Empresa principal')
            ->assertSee('Empresa dependiente')
            ->set('bulkEmpresaIds', [(string) $dependentCompany->id])
            ->assertSee('Usuarios activos afectados:')
            ->call('applyBulkStatusAction')
            ->assertHasNoErrors()
            ->assertDispatched('closeBulkStatusModal')
            ->assertSet('bulkCodigoCliente', '');

        $this->assertDatabaseHas('users', ['id' => $firstUser->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('users', ['id' => $dependentUser->id]);
        $this->assertSoftDeleted('users', ['id' => $alreadyInactiveUser->id]);
        $this->assertDatabaseHas('users', ['id' => $otherUser->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $administrator->id, 'deleted_at' => null]);

        $component
            ->call('openBulkStatusModal', 'restore')
            ->assertSet('bulkStatusAction', 'restore')
            ->set('bulkEmpresaIds', [(string) $dependentCompany->id])
            ->assertSee('Usuarios inactivos afectados:')
            ->call('applyBulkStatusAction')
            ->assertHasNoErrors()
            ->assertDispatched('closeBulkStatusModal');

        $this->assertDatabaseHas('users', ['id' => $dependentUser->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('users', ['id' => $alreadyInactiveUser->id, 'deleted_at' => null]);
    }
}

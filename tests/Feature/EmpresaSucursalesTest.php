<?php

namespace Tests\Feature;

use App\Http\Controllers\UserController;
use App\Livewire\Empresa as Empresas;
use App\Livewire\Users;
use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class EmpresaSucursalesTest extends TestCase
{
    private const MIGRATION = '2026_11_18_100000_create_empresa_sucursales_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2025_06_11_195557_create_permission_tables.php',
            '2026_02_26_212136_create_empresa_table.php',
            '2026_02_26_215923_add_empresa_id_to_users_table.php',
            '2026_03_10_000010_add_alias_to_users_table.php',
            '2026_03_19_100000_create_sucursales_table.php',
            '2026_03_19_100100_add_sucursal_id_to_users_table.php',
            '2026_06_08_104500_add_regionales_to_users_table.php',
            '2026_07_22_000001_add_provincia_origen_to_users_table.php',
            self::MIGRATION,
        ] as $filename) {
            (require database_path('migrations/'.$filename))->up();
        }
    }

    public function test_empresa_manages_its_own_branches_and_cannot_delete_an_assigned_branch(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::create(['name' => 'feature.empresas.edit']));
        Permission::create(['name' => 'feature.empresas.create']);
        Permission::create(['name' => 'feature.empresas.delete']);
        $company = $this->createEmpresa('Andina');
        $otherCompany = $this->createEmpresa('Oriental');
        $otherBranch = $otherCompany->sucursales()->create(['nombre' => 'Norte']);

        $component = Livewire::actingAs($admin)->test(Empresas::class)
            ->call('openSucursalesModal', $company->id)->assertDispatched('openSucursalesModal')
            ->assertSee(['La Paz', 'Cochabamba', 'Santa Cruz', 'Oruro', 'Potosí', 'Tarija', 'Sucre (Chuquisaca)', 'Trinidad (Beni)', 'Cobija (Pando)'])
            ->set('sucursalFilas.0.nombre', ' Central ')
            ->set('sucursalFilas.0.ciudad', 'LA PAZ')
            ->call('saveSucursal')->assertHasNoErrors();

        $branch = $company->sucursales()->sole();
        $this->assertSame('Central - LA PAZ', $branch->etiqueta);
        $this->assertDatabaseCount('sucursales', 0);
        $component->call('editSucursal', $branch->id)->set('sucursalFilas.0.nombre', 'Centro')
            ->call('saveSucursal')->assertHasNoErrors();
        $this->assertSame('Centro', $branch->refresh()->nombre);

        $component->set('sucursalFilas.0.nombre', ' Centro ')->call('saveSucursal')
            ->assertHasErrors(['sucursalFilas.0.nombre' => 'unique']);
        $user = User::factory()->create(['empresa_id' => $company->id, 'empresa_sucursal_id' => $branch->id]);
        $user->delete();
        $component->call('deleteSucursal', $branch->id)->assertHasErrors('sucursalEliminar');
        $this->expectException(ModelNotFoundException::class);
        $component->call('editSucursal', $otherBranch->id);
    }

    public function test_empresa_can_add_multiple_branches_with_departments_in_one_save(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::create(['name' => 'feature.empresas.edit']));
        $company = $this->createEmpresa('Andina');

        $component = Livewire::actingAs($admin)->test(Empresas::class)
            ->call('openSucursalesModal', $company->id)
            ->set('sucursalFilas.0.nombre', 'Norte')
            ->set('sucursalFilas.0.ciudad', 'TRINIDAD')
            ->call('addSucursalFila')
            ->set('sucursalFilas.1.nombre', ' Norte ')
            ->set('sucursalFilas.1.ciudad', 'Otro lugar')
            ->call('saveSucursal')
            ->assertHasErrors(['sucursalFilas.1.nombre' => 'distinct', 'sucursalFilas.1.ciudad' => 'in']);
        $this->assertDatabaseCount('empresa_sucursales', 0);

        $component->call('removeSucursalFila', 0)
            ->call('addSucursalFila')->assertSee('Crear sucursales')
            ->set('sucursalFilas.1.nombre', 'Centro')
            ->set('sucursalFilas.1.ciudad', 'SUCRE')
            ->set('sucursalFilas.2.nombre', 'Sur')
            ->set('sucursalFilas.2.ciudad', 'TARIJA')
            ->call('saveSucursal')->assertHasNoErrors()
            ->assertSet('sucursalFilas', [['nombre' => '', 'ciudad' => '']]);
        $this->assertSame(['Centro' => 'SUCRE', 'Sur' => 'TARIJA'], $company->sucursales()->orderBy('nombre')->pluck('ciudad', 'nombre')->all());
    }

    public function test_user_form_requires_matching_company_and_branch_and_preserves_billing_assignment(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo(Permission::create(['name' => 'feature.users.empresas.edit']));
        $company = $this->createEmpresa('Andina');
        $branch = $company->sucursales()->create(['nombre' => 'Sucursal Centro']);
        $otherCompany = $this->createEmpresa('Oriental');
        $otherBranch = $otherCompany->sucursales()->create(['nombre' => 'Sucursal Norte']);
        $billingBranch = Sucursal::create(['codigoSucursal' => 1, 'puntoVenta' => 0, 'municipio' => 'LA PAZ', 'telefono' => '2220000']);
        $user = User::factory()->create([
            'ciudad' => 'LA PAZ',
            'empresa_id' => $company->id,
            'sucursal_id' => $billingBranch->id,
        ]);

        Livewire::actingAs($admin)->test(Users::class, ['empresaMode' => true])
            ->call('openEditModal', $user->id)->assertStatus(200)
            ->assertSee('Sucursal Centro')->assertDontSee('Sucursal Norte')
            ->call('saveUser')->assertHasErrors(['empresa_sucursal_id' => 'required'])
            ->set('empresa_sucursal_id', (string) $branch->id)
            ->set('empresa_id', (string) $otherCompany->id)->assertSet('empresa_sucursal_id', '')
            ->assertSee('Sucursal Norte')->assertDontSee('Sucursal Centro')
            ->set('empresa_sucursal_id', (string) $branch->id)
            ->call('saveUser')->assertHasErrors(['empresa_sucursal_id' => 'exists'])
            ->set('empresa_sucursal_id', (string) $otherBranch->id)
            ->call('saveUser')->assertHasNoErrors();

        $this->assertTrue($user->refresh()->empresaSucursal->is($otherBranch));
        $this->assertTrue($user->empresa->is($otherCompany));
        $this->assertTrue($user->sucursal->is($billingBranch));
    }

    public function test_standard_user_controller_and_import_validate_company_branch(): void
    {
        $company = $this->createEmpresa('Andina');
        $otherCompany = $this->createEmpresa('Oriental');
        $branch = $otherCompany->sucursales()->create(['nombre' => 'Sucursal Norte']);
        $controller = app(UserController::class);
        $payload = [
            'name' => 'Nuevo usuario', 'alias' => 'nuevo', 'email' => 'nuevo@example.com',
            'password' => 'password', 'ciudad' => 'LA PAZ',
            'empresa_id' => $company->id, 'empresa_sucursal_id' => $branch->id,
        ];

        try {
            $controller->store(Request::create('/users', 'POST', [...$payload, 'empresa_id' => null, 'empresa_sucursal_id' => null]));
            $this->fail('A user without company and branch was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('empresa_id', $exception->errors());
            $this->assertArrayHasKey('empresa_sucursal_id', $exception->errors());
        }

        try {
            $controller->store(Request::create('/users', 'POST', $payload));
            $this->fail('A branch from a different company was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('empresa_sucursal_id', $exception->errors());
        }

        $controller->store(Request::create('/users', 'POST', [...$payload, 'empresa_id' => $otherCompany->id]));
        $this->assertDatabaseHas('users', ['alias' => 'nuevo', 'empresa_id' => $otherCompany->id, 'empresa_sucursal_id' => $branch->id]);

        Role::create(['name' => 'empresa']);
        $file = UploadedFile::fake()->createWithContent('usuarios.csv', implode("\n", [
            'name,alias,email,password,ciudad,ci,rol,empresa_codigo,sucursal_id,empresa_sucursal_id',
            "Fila válida,importado,importado@example.com,password,LA PAZ,,empresa,{$otherCompany->codigo_cliente},,{$branch->id}",
            "Fila inválida,invalido,invalido@example.com,password,LA PAZ,,empresa,{$company->codigo_cliente},,{$branch->id}",
        ]));
        $controller->import(Request::create('/users/import', 'POST', files: ['archivo' => $file]));

        $this->assertDatabaseHas('users', ['alias' => 'importado', 'empresa_id' => $otherCompany->id, 'empresa_sucursal_id' => $branch->id]);
        $this->assertDatabaseMissing('users', ['alias' => 'invalido']);
    }

    public function test_database_rejects_mismatched_pairs(): void
    {
        $company = $this->createEmpresa('Andina');
        $branch = $this->createEmpresa('Oriental')->sucursales()->create(['nombre' => 'Sucursal Norte']);

        $this->expectException(QueryException::class);
        User::factory()->create(['empresa_id' => $company->id, 'empresa_sucursal_id' => $branch->id]);
    }

    public function test_migration_can_be_rolled_back_without_removing_billing_branches(): void
    {
        (require database_path('migrations/'.self::MIGRATION))->down();

        $this->assertFalse(Schema::hasTable('empresa_sucursales'));
        $this->assertFalse(Schema::hasColumn('users', 'empresa_sucursal_id'));
        $this->assertTrue(Schema::hasColumn('users', 'sucursal_id'));
        $this->assertTrue(Schema::hasTable('sucursales'));
    }

    private function createEmpresa(string $name): Empresa
    {
        return Empresa::create(['nombre' => $name, 'sigla' => $name, 'codigo_cliente' => strtoupper($name)]);
    }
}

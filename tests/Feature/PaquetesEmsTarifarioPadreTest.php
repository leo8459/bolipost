<?php

namespace Tests\Feature;

use App\Livewire\PaquetesEms;
use App\Models\AppSetting;
use App\Models\Servicio;
use App\Models\TarifarioPadre;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PaquetesEmsTarifarioPadreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('servicio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_servicio');
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_09_120000_create_tarifario_padre_table.php'))->up();
        (require database_path('migrations/2026_05_07_090000_create_app_settings_table.php'))->up();
        Schema::create('paquetes_ems', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('observacion')->nullable();
        });
    }

    public function test_only_administrator_can_change_the_persistent_configuration(): void
    {
        $this->loginWithRole('administrador');
        $padre = TarifarioPadre::create(['nombre' => 'Tarifario 2']);
        $component = $this->makeEmsComponent();
        $this->assertTrue($component->puedeConfigurarTarifario);
        $component->abrirConfiguracionTarifario();
        $component->tarifarioPadreSeleccionado = $padre->id;
        $component->guardarConfiguracionTarifario();
        $this->assertSame((string) $padre->id, AppSetting::getValue('ems.tarifario_padre_id'));
        $this->assertSame('', $component->servicio_id);
        $this->assertFalse($component->mostrarConfiguracionTarifario);
    }

    public function test_nonadministrator_cannot_invoke_configuration_action(): void
    {
        $this->loginWithRole('operador');
        $component = $this->makeEmsComponent();
        $this->assertFalse($component->puedeConfigurarTarifario);
        $this->expectException(HttpException::class);
        $component->guardarConfiguracionTarifario();
    }

    public function test_administrator_cannot_select_nonexistent_parent(): void
    {
        $this->loginWithRole('administrador');
        $component = $this->makeEmsComponent();
        $component->tarifarioPadreSeleccionado = 999;
        $this->expectException(ValidationException::class);
        $component->guardarConfiguracionTarifario();
    }

    public function test_services_are_filtered_for_every_user_and_other_screens_are_unchanged(): void
    {
        $uno = TarifarioPadre::create(['nombre' => 'Uno']);
        $dos = TarifarioPadre::create(['nombre' => 'Dos']);
        Servicio::create(['nombre_servicio' => 'EMS_NACIONAL', 'tarifario_padre_id' => $uno->id]);
        $servicio = Servicio::create(['nombre_servicio' => 'EMS nuevo', 'tarifario_padre_id' => $dos->id]);
        AppSetting::setValue('ems.tarifario_padre_id', (string) $dos->id);
        $this->loginWithRole('operador');
        $component = $this->makeEmsComponent();
        $this->assertSame([$servicio->id], $component->availableIds());
        $component = $this->makeEmsComponent();
        $component->mode = 'admision';
        $this->assertCount(2, $component->availableIds());
    }

    public function test_service_from_a_previous_configuration_is_rejected_on_save(): void
    {
        $uno = TarifarioPadre::create(['nombre' => 'Uno']);
        $dos = TarifarioPadre::create(['nombre' => 'Dos']);
        $servicio = Servicio::create(['nombre_servicio' => 'EMS', 'tarifario_padre_id' => $uno->id]);
        $component = $this->makeEmsComponent();
        $component->servicio_id = $servicio->id;
        AppSetting::setValue('ems.tarifario_padre_id', (string) $uno->id);
        $component->checkService();
        AppSetting::setValue('ems.tarifario_padre_id', (string) $dos->id);
        $this->expectException(ValidationException::class);
        $component->checkService();
    }

    public function test_different_services_continue_the_existing_en_sequence(): void
    {
        DB::table('paquetes_ems')->insert(['codigo' => 'EN000005123LPZ']);
        $component = $this->makeEmsComponent();
        $component->origen = 'LA PAZ';
        foreach (['EMS_NACIONAL', 'Tarifa nueva', 'ENCOMIENDA'] as $nombre) {
            $servicio = Servicio::create(['nombre_servicio' => $nombre]);
            $component->servicio_id = $servicio->id;
            $codigo = $component->nextCode();
            $expected = 5124 + $servicio->id - 1;
            $this->assertSame('EN'.str_pad((string) $expected, 9, '0', STR_PAD_LEFT).'LPZ', $codigo);
            DB::table('paquetes_ems')->insert(['codigo' => $codigo]);
        }
    }

    private function loginWithRole(string $name): void
    {
        $user = new User;
        $user->id = 1;
        $user->setRelation('roles', collect([new Role(['name' => $name, 'guard_name' => 'web'])]));
        $this->actingAs($user);
    }

    private function makeEmsComponent(): PaquetesEms
    {
        $component = new class extends PaquetesEms
        {
            public function availableIds(): array
            {
                return $this->serviciosDisponiblesQuery()->pluck('id')->all();
            }

            public function checkService(): void
            {
                $this->validarServicioDisponible();
            }

            public function nextCode(): string
            {
                return $this->generateCodigo();
            }
        };
        $component->mode = 'create_ems';

        return $component;
    }
}

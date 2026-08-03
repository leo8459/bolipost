<?php

namespace Tests\Feature;

use App\Livewire\PaquetesEms;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaquetesEmsCn33DestinationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['paquetes_ems', 'paquetes_contrato', 'solicitud_clientes', 'paquetes_int'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('paquetes_ems', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('ciudad')->nullable();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('destino')->nullable();
        });

        Schema::create('solicitud_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('ciudad')->nullable();
        });

        Schema::create('paquetes_int', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('destino')->nullable();
        });
    }

    public function test_resolves_the_existing_cn33_destination_case_insensitively(): void
    {
        DB::table('paquetes_ems')->insert([
            'cod_especial' => 'srz00001',
            'ciudad' => ' Cochabamba ',
        ]);

        $this->assertSame('COCHABAMBA', $this->makePaquetesEmsComponent()->cn33Destino(' SRZ00001 '));
    }

    public function test_resolves_destination_from_every_supported_cn33_package_type(): void
    {
        $component = $this->makePaquetesEmsComponent();

        DB::table('paquetes_contrato')->insert(['cod_especial' => 'LPZ00001', 'destino' => 'Tarija']);
        $this->assertSame('TARIJA', $component->cn33Destino('LPZ00001'));

        DB::table('solicitud_clientes')->insert(['cod_especial' => 'LPZ00002', 'ciudad' => 'Sucre']);
        $this->assertSame('SUCRE', $component->cn33Destino('LPZ00002'));

        DB::table('paquetes_int')->insert(['cod_especial' => 'LPZ00003', 'destino' => 'Oruro']);
        $this->assertSame('ORURO', $component->cn33Destino('LPZ00003'));

        $this->assertNull($component->cn33Destino('NO-EXISTE'));
    }

    public function test_only_accepts_an_existing_cn33_code_and_rejects_package_codes(): void
    {
        DB::table('paquetes_ems')->insert([
            'cod_especial' => 'LPZ00001',
            'ciudad' => 'Santa Cruz',
        ]);

        $component = $this->makePaquetesEmsComponent();

        $this->assertTrue($component->isCn33Code(' lpz00001 '));
        $this->assertFalse($component->isCn33Code('SL00000015LP'));
        $this->assertFalse($component->isCn33Code('LPZ99999'));
        $this->assertFalse($component->isCn33Code('cualquier texto'));
    }

    public function test_confirmation_modal_displays_the_resolved_department_instead_of_a_fixed_value(): void
    {
        $template = file_get_contents(resource_path('views/livewire/paquetes-ems.blade.php'));

        $this->assertStringContainsString('$cn33DestinoConfirmacion', $template);
        $this->assertStringNotContainsString('<strong>RAFOVAR</strong>', $template);
    }

    private function makePaquetesEmsComponent(): PaquetesEms
    {
        return new class extends PaquetesEms
        {
            public function cn33Destino(string $codEspecial): ?string
            {
                return $this->resolveCn33Destino($codEspecial);
            }

            public function isCn33Code(string $codigo): bool
            {
                return $this->isExistingCn33Code($codigo);
            }
        };
    }
}

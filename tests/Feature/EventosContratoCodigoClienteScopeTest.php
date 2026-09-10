<?php

namespace Tests\Feature;

use App\Livewire\EventosTabla;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventosContratoCodigoClienteScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_cliente')->nullable();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->string('codigo');
        });

        Schema::create('eventos_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('eventos_contrato');
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('empresa');

        parent::tearDown();
    }

    public function test_usuario_ve_eventos_de_empresas_con_el_mismo_codigo_cliente(): void
    {
        DB::table('empresa')->insert([
            ['id' => 1, 'codigo_cliente' => ' CLI 001 '],
            ['id' => 2, 'codigo_cliente' => 'cli001'],
            ['id' => 3, 'codigo_cliente' => 'CLI002'],
        ]);
        DB::table('paquetes_contrato')->insert([
            ['empresa_id' => 1, 'codigo' => 'CONT-1'],
            ['empresa_id' => 2, 'codigo' => 'CONT-2'],
            ['empresa_id' => 3, 'codigo' => 'CONT-3'],
        ]);
        DB::table('eventos_contrato')->insert([
            ['codigo' => 'CONT-1'],
            ['codigo' => 'CONT-2'],
            ['codigo' => 'CONT-3'],
        ]);

        $this->actingAs($this->userForEmpresa(1));

        $this->assertSame(['CONT-1', 'CONT-2'], $this->scopedCodes());
    }

    public function test_empresa_sin_codigo_cliente_conserva_el_alcance_por_empresa(): void
    {
        DB::table('empresa')->insert([
            ['id' => 1, 'codigo_cliente' => null],
            ['id' => 2, 'codigo_cliente' => null],
        ]);
        DB::table('paquetes_contrato')->insert([
            ['empresa_id' => 1, 'codigo' => 'CONT-1'],
            ['empresa_id' => 2, 'codigo' => 'CONT-2'],
        ]);
        DB::table('eventos_contrato')->insert([
            ['codigo' => 'CONT-1'],
            ['codigo' => 'CONT-2'],
        ]);

        $this->actingAs($this->userForEmpresa(1));

        $this->assertSame(['CONT-1'], $this->scopedCodes());
    }

    public function test_usuario_del_evento_se_muestra_a_quien_no_tiene_rol_empresa(): void
    {
        $user = $this->userForEmpresa(0);
        $user->setRelation('roles', collect());
        $this->actingAs($user);

        $this->assertTrue($this->shouldShowEventUser());
    }

    public function test_usuario_del_evento_se_oculta_al_rol_empresa(): void
    {
        $user = $this->userForEmpresa(1);
        $user->setRelation('roles', collect([
            new Role(['name' => 'empresa', 'guard_name' => 'web']),
        ]));
        $this->actingAs($user);

        $this->assertFalse($this->shouldShowEventUser());
    }

    private function userForEmpresa(int $empresaId): User
    {
        return (new User)->forceFill([
            'id' => 99,
            'empresa_id' => $empresaId,
        ]);
    }

    private function scopedCodes(): array
    {
        $component = new EventosTabla;
        $component->tipo = 'contrato';

        $method = new ReflectionMethod($component, 'scopedTableQuery');
        $query = $method->invoke($component);

        return $query->orderBy('codigo')->pluck('codigo')->all();
    }

    private function shouldShowEventUser(): bool
    {
        $component = new EventosTabla;
        $component->tipo = 'contrato';

        $method = new ReflectionMethod($component, 'shouldShowEventUser');

        return $method->invoke($component);
    }
}

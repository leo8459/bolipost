<?php

namespace Tests\Feature;

use App\Services\ContratoCodigoService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContratoCodigoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->timestamps();
        });

        Schema::create('codigo_empresa', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->string('barcode');
            $table->unsignedBigInteger('empresa_id');
            $table->timestamps();
        });

        $migration = require database_path('migrations/2026_07_31_120000_create_correlativos_contrato_and_unique_codigo.php');
        $migration->up();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('correlativos_contrato');
        Schema::dropIfExists('codigo_empresa');
        Schema::dropIfExists('paquetes_contrato');

        parent::tearDown();
    }

    public function test_comparte_un_correlativo_por_prefijo_y_continua_desde_el_maximo_existente(): void
    {
        DB::table('codigo_empresa')->insert([
            'codigo' => 'C0001A89000BO',
            'barcode' => 'C0001A89000BO',
            'empresa_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('paquetes_contrato')->insert([
            [
                'codigo' => 'C0001A88990BO',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'C0002A00015BO',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $service = app(ContratoCodigoService::class);

        $this->assertSame(89001, $service->reservarSiguiente('0001'));
        $this->assertSame(89002, $service->reservarSiguiente('0001'));
        $this->assertSame(16, $service->reservarSiguiente('0002'));
    }

    public function test_un_codigo_manual_mayor_adelanta_el_contador_de_su_empresa(): void
    {
        $service = app(ContratoCodigoService::class);

        $this->assertSame(1, $service->reservarSiguiente('0003'));

        DB::table('paquetes_contrato')->insert([
            'codigo' => 'C0003A90500BO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $service->sincronizarDesdeCodigo('C0003A90500BO');

        $this->assertSame(90501, $service->reservarSiguiente('0003'));
    }

    public function test_la_base_de_datos_rechaza_codigos_de_contrato_duplicados(): void
    {
        DB::table('paquetes_contrato')->insert([
            'codigo' => 'C0001A89001BO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('paquetes_contrato')->insert([
            'codigo' => 'C0001A89001BO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_devuelve_el_mensaje_solicitado_cuando_el_codigo_esta_duplicado(): void
    {
        DB::table('paquetes_contrato')->insert([
            'codigo' => 'C0001A89001BO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Route::post('/_test/codigo-contrato-duplicado', function () {
            DB::table('paquetes_contrato')->insert([
                'codigo' => 'C0001A89001BO',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson('/_test/codigo-contrato-duplicado')
            ->assertStatus(409)
            ->assertExactJson([
                'message' => ContratoCodigoService::MENSAJE_CODIGO_DUPLICADO,
            ]);
    }
}

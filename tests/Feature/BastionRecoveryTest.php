<?php

namespace Tests\Feature;

use App\Http\Controllers\BastionController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BastionRecoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->crearTablaBastion('bastion_ems', 'nombre_destinatario', 'ciudad', true);
        $this->crearTablaBastion('bastion_contratos', 'nombre_d', 'destino', true);
        $this->crearTablaBastion('bastion_certi', 'destinatario', 'cuidad');
        $this->crearTablaBastion('bastion_ordi', 'destinatario', 'ciudad');

        foreach (['paquetes_ems', 'paquetes_contrato', 'paquetes_certi', 'paquetes_ordi'] as $tabla) {
            Schema::create($tabla, function (Blueprint $table): void {
                $table->id();
                $table->string('codigo')->nullable();
                $table->string('cod_especial')->nullable();
                $table->string('nombre_destinatario')->nullable();
                $table->string('nombre_d')->nullable();
                $table->string('destinatario')->nullable();
                $table->string('origen')->nullable();
                $table->string('ciudad')->nullable();
                $table->string('destino')->nullable();
                $table->string('cuidad')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_busca_paquetes_de_todos_los_bastiones(): void
    {
        DB::table('bastion_ems')->insert([
            'codigo' => 'EMS-001',
            'cod_especial' => 'LPZ-999',
            'nombre_destinatario' => 'DESTINATARIO PRUEBA',
            'created_at' => now(),
        ]);
        DB::table('bastion_ordi')->insert([
            'codigo' => 'ORD-001',
            'destinatario' => 'OTRA PERSONA',
            'created_at' => now(),
        ]);

        $view = app(BastionController::class)->index(
            Request::create('/bastiones/paquetes', 'GET', ['buscar' => 'lpz-999'])
        );

        $this->assertSame('bastiones.index', $view->getName());
        $this->assertSame(1, $view->getData()['paquetes']->total());
        $this->assertSame('EMS-001', $view->getData()['paquetes']->first()->codigo);
    }

    public function test_recupera_el_paquete_en_su_tabla_y_lo_retira_del_bastion(): void
    {
        $id = DB::table('bastion_ems')->insertGetId([
            'id_origen' => 87,
            'codigo' => 'EMS-RECUPERAR',
            'cod_especial' => 'REC-01',
            'nombre_destinatario' => 'MARIA PRUEBA',
            'origen' => 'LA PAZ',
            'ciudad' => 'ORURO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = app(BastionController::class)->recuperar('ems', $id);

        $this->assertTrue($response->getSession()->has('success'));
        $this->assertDatabaseHas('paquetes_ems', [
            'id' => 87,
            'codigo' => 'EMS-RECUPERAR',
            'nombre_destinatario' => 'MARIA PRUEBA',
        ]);
        $this->assertDatabaseMissing('bastion_ems', ['id' => $id]);
    }

    public function test_no_recupera_un_codigo_que_ya_existe(): void
    {
        DB::table('paquetes_contrato')->insert(['codigo' => 'CON-001']);
        $id = DB::table('bastion_contratos')->insertGetId([
            'codigo' => 'CON-001',
            'nombre_d' => 'DUPLICADO',
            'created_at' => now(),
        ]);

        $response = app(BastionController::class)->recuperar('contratos', $id);

        $this->assertTrue($response->getSession()->has('errors'));
        $this->assertSame(1, DB::table('paquetes_contrato')->where('codigo', 'CON-001')->count());
        $this->assertDatabaseHas('bastion_contratos', ['id' => $id]);
    }

    private function crearTablaBastion(string $tabla, string $destinatario, string $destino, bool $conOrigen = false): void
    {
        Schema::create($tabla, function (Blueprint $table) use ($destinatario, $destino, $conOrigen): void {
            $table->id();
            $table->unsignedBigInteger('id_origen')->nullable();
            $table->string('codigo')->nullable();
            $table->string('cod_especial')->nullable();
            $table->string($destinatario)->nullable();
            if ($conOrigen) {
                $table->string('origen')->nullable();
            }
            $table->string($destino)->nullable();
            $table->timestamps();
        });
    }
}

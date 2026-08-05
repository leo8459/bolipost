<?php

namespace Tests\Feature;

use App\Http\Controllers\AreaContratosController;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AreaContratosReportesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->timestamps();
        });

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
            $table->timestamps();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('estados_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('origen')->nullable();
            $table->timestamps();
        });

        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'ACTIVO'],
            ['id' => 2, 'nombre_estado' => 'CANCELADO'],
        ]);

        $now = now();
        for ($id = 1; $id <= 30; $id++) {
            $origen = $id <= 20 ? 'LA PAZ' : [null, '', '  '][$id % 3];

            DB::table('paquetes_contrato')->insert([
                'id' => $id,
                'estados_id' => $id === 30 ? 2 : 1,
                'codigo' => 'CONTRATO-'.$id,
                'origen' => $origen,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('estados');
        Schema::dropIfExists('empresa');

        parent::tearDown();
    }

    public function test_resume_en_la_base_de_datos_sin_cargar_todos_los_contratos(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        $view = app(AreaContratosController::class)->reportes(
            Request::create('/area-contratos/reportes', 'GET')
        );
        $data = $view->getData();

        $this->assertSame(29, $data['totalReportes']);
        $this->assertCount(25, $data['contratos']);
        $this->assertSame([
            ['origen' => 'LA PAZ', 'total' => 20],
            ['origen' => 'SIN ORIGEN', 'total' => 9],
        ], $data['groupedSummary']->all());

        $this->assertTrue(collect($queries)->contains(
            fn (string $sql) => str_contains($sql, 'count(*) as total')
                && str_contains($sql, 'group by "origen"')
        ));
    }
}

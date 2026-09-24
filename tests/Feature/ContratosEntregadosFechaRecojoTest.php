<?php

namespace Tests\Feature;

use App\Http\Controllers\RecojoController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class ContratosEntregadosFechaRecojoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('empresa_id')->nullable();
        });

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
        });

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('estados_id')->nullable();
            $table->string('codigo');
            $table->dateTime('fecha_recojo')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->timestamps();
        });

        DB::table('empresa')->insert([
            'id' => 21,
            'nombre' => 'INSA',
            'sigla' => 'INSA',
        ]);
        DB::table('estados')->insert([
            'id' => 27,
            'nombre_estado' => 'ENTREGADO',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('estados');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresa');

        parent::tearDown();
    }

    public function test_entregados_filtra_y_resume_por_fecha_de_recojo(): void
    {
        DB::table('paquetes_contrato')->insert([
            [
                'id' => 1,
                'empresa_id' => 21,
                'estados_id' => 27,
                'codigo' => 'C0018A04185BO',
                'fecha_recojo' => '2026-08-01 09:56:40',
                'peso' => 1.250,
                'created_at' => '2026-07-31 11:35:00',
                'updated_at' => '2026-08-04 00:31:32',
            ],
            [
                'id' => 2,
                'empresa_id' => 21,
                'estados_id' => 27,
                'codigo' => 'FUERA-DEL-RANGO',
                'fecha_recojo' => '2026-07-31 15:00:00',
                'peso' => 2.000,
                'created_at' => '2026-08-01 08:00:00',
                'updated_at' => '2026-08-02 08:00:00',
            ],
        ]);

        $controller = app(RecojoController::class);
        $queryMethod = new ReflectionMethod($controller, 'entregadosQueryForUser');
        $rows = $queryMethod->invoke(
            $controller,
            (object) ['empresa_id' => 21],
            '2026-08-01',
            '2026-08-31'
        )->get();

        $this->assertSame(['C0018A04185BO'], $rows->pluck('codigo')->all());

        $statsMethod = new ReflectionMethod($controller, 'buildEntregadosStats');
        $stats = $statsMethod->invoke(
            $controller,
            (object) ['empresa_id' => 21],
            '2026-08-01',
            '2026-08-31',
            $rows
        );

        $this->assertSame(1, $stats['total']);
        $this->assertSame('2026-08-01', $stats['por_dia']->first()['fecha']);
    }
}

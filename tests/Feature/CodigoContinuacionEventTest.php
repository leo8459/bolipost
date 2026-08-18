<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesEmsController;
use App\Support\CodigoContinuacionEvent;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

class CodigoContinuacionEventTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach ([
            'eventos_contrato',
            'eventos_ordi',
            'eventos_certi',
            'eventos_ems',
            'paquetes_contrato',
            'paquetes_ordi',
            'paquetes_certi',
            'paquetes_ems',
            'eventos',
        ] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        parent::tearDown();
    }

    public function test_reuses_two_catalog_events_and_keeps_each_related_code(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });

        foreach (['eventos_ems', 'eventos_certi', 'eventos_ordi', 'eventos_contrato'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('codigo');
                $table->unsignedBigInteger('evento_id');
                $table->string('codigo_relacionado')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        foreach (['paquetes_ems', 'paquetes_certi', 'paquetes_ordi', 'paquetes_contrato'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('codigo');
            });
        }

        DB::table('paquetes_ems')->insert([
            ['codigo' => 'MADRE001'],
            ['codigo' => 'MADRE002'],
        ]);

        $method = new ReflectionMethod(PaquetesEmsController::class, 'insertCodigoContinuacionEvents');
        $controller = app(PaquetesEmsController::class);

        $method->invoke($controller, 'MADRE001', 'HIJO001', 10);
        $method->invoke($controller, 'MADRE002', 'HIJO002', 10);

        $this->assertSame(2, DB::table('eventos')->count());
        $this->assertDatabaseHas('eventos', ['nombre_evento' => CodigoContinuacionEvent::MADRE]);
        $this->assertDatabaseHas('eventos', ['nombre_evento' => CodigoContinuacionEvent::HIJO]);
        $this->assertDatabaseHas('eventos_ems', [
            'codigo' => 'MADRE002',
            'codigo_relacionado' => 'HIJO002',
        ]);
        $this->assertDatabaseHas('eventos_contrato', [
            'codigo' => 'HIJO002',
            'codigo_relacionado' => 'MADRE002',
        ]);
        $this->assertSame(
            'Este codigo es la continuacion del codigo madre MADRE002.',
            CodigoContinuacionEvent::nombreMostrado(CodigoContinuacionEvent::HIJO, 'MADRE002')
        );
    }
}

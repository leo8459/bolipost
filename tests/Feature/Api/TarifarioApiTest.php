<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureExternalApiAbility;
use App\Http\Middleware\EnsureExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TarifarioApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureExternalApiJwt::class,
            EnsureExternalApiAbility::class,
        ]);

        foreach (['tarifario_tiktoker', 'tarifario', 'servicio_extras', 'peso', 'origen', 'destino', 'servicio'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('servicio', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_servicio');
        });
        Schema::create('destino', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_destino');
        });
        Schema::create('origen', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_origen');
        });
        Schema::create('peso', function (Blueprint $table): void {
            $table->id();
            $table->decimal('peso_inicial', 10, 2);
            $table->decimal('peso_final', 10, 2);
        });
        Schema::create('servicio_extras', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
        });
        Schema::create('tarifario', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('servicio_id');
            $table->unsignedBigInteger('destino_id');
            $table->unsignedBigInteger('peso_id');
            $table->unsignedBigInteger('origen_id');
            $table->decimal('precio', 10, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
        Schema::create('tarifario_tiktoker', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('origen_id');
            $table->unsignedBigInteger('destino_id');
            $table->unsignedBigInteger('servicio_extra_id')->nullable();
            $table->decimal('peso1', 10, 2);
            $table->decimal('peso2', 10, 2)->nullable();
            $table->decimal('peso3', 10, 2)->nullable();
            $table->decimal('peso_extra', 10, 2)->nullable();
            $table->unsignedInteger('tiempo_entrega');
            $table->timestamps();
        });
    }

    public function test_ems_nacional_devuelve_todo_el_tarifario_con_relaciones(): void
    {
        $servicio = \DB::table('servicio')->insertGetId(['nombre_servicio' => 'EMS_NACIONAL']);
        $otroServicio = \DB::table('servicio')->insertGetId(['nombre_servicio' => 'ENCOMIENDA']);
        $destino = \DB::table('destino')->insertGetId(['nombre_destino' => 'LA PAZ']);
        $origen = \DB::table('origen')->insertGetId(['nombre_origen' => 'COCHABAMBA']);
        $peso = \DB::table('peso')->insertGetId(['peso_inicial' => 0, 'peso_final' => 1]);

        \DB::table('tarifario')->insert([
            'servicio_id' => $servicio,
            'destino_id' => $destino,
            'peso_id' => $peso,
            'origen_id' => $origen,
            'precio' => 25,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('tarifario')->insert([
            'servicio_id' => $otroServicio,
            'destino_id' => $destino,
            'peso_id' => $peso,
            'origen_id' => $origen,
            'precio' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/tarifarios/ems-nacional')
            ->assertOk()
            ->assertJsonPath('nombre_api', 'TARIFARIO EMS NACIONAL')
            ->assertJsonPath('total_registros', 1)
            ->assertJsonPath('relaciones_incluidas.0', 'servicio')
            ->assertJsonPath('data.0.servicio.nombre_servicio', 'EMS_NACIONAL')
            ->assertJsonPath('data.0.peso.peso_final', 1)
            ->assertJsonPath('data.0.destino.nombre_destino', 'LA PAZ')
            ->assertJsonPath('data.0.origen.nombre_origen', 'COCHABAMBA');
    }

    public function test_delivery_express_devuelve_todo_el_tarifario_con_relaciones(): void
    {
        $destino = \DB::table('destino')->insertGetId(['nombre_destino' => 'LA PAZ']);
        $origen = \DB::table('origen')->insertGetId(['nombre_origen' => 'COCHABAMBA']);
        $servicioExtra = \DB::table('servicio_extras')->insertGetId(['nombre' => 'PUERTA A PUERTA']);

        \DB::table('tarifario_tiktoker')->insert([
            'origen_id' => $origen,
            'destino_id' => $destino,
            'servicio_extra_id' => $servicioExtra,
            'peso1' => 20,
            'peso2' => null,
            'peso3' => null,
            'peso_extra' => null,
            'tiempo_entrega' => 24,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/tarifarios/delivery-express')
            ->assertOk()
            ->assertJsonPath('nombre_api', 'TARIFARIO DELIVERY EXPRESS')
            ->assertJsonPath('total_registros', 1)
            ->assertJsonPath('data.0.origen.nombre_origen', 'COCHABAMBA')
            ->assertJsonPath('data.0.destino.nombre_destino', 'LA PAZ')
            ->assertJsonPath('data.0.servicio_extra.nombre', 'PUERTA A PUERTA');
    }
}

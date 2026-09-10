<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureExternalApiAbility;
use App\Http\Middleware\EnsureExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleLogExternalApiTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureExternalApiJwt::class,
            EnsureExternalApiAbility::class,
        ]);

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('placa');
            $table->decimal('kilometraje_inicial', 10, 2)->nullable();
            $table->decimal('kilometraje_actual', 10, 2)->nullable();
            $table->decimal('kilometraje', 10, 2)->nullable();
            $table->boolean('tacometro_danado')->default(false);
            $table->string('operational_status')->nullable();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('fuel_logs', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('vehicle_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('drivers_id');
            $table->unsignedBigInteger('vehicles_id');
            $table->unsignedBigInteger('fuel_log_id')->nullable();
            $table->date('fecha');
            $table->decimal('kilometraje_salida', 10, 2);
            $table->decimal('kilometraje_llegada', 10, 2)->nullable();
            $table->decimal('kilometraje_recorrido', 10, 2)->nullable();
            $table->unsignedInteger('cantidad_paquetes')->nullable();
            $table->string('recorrido_inicio');
            $table->decimal('latitud_inicio', 11, 8)->nullable();
            $table->decimal('logitud_inicio', 11, 8)->nullable();
            $table->string('recorrido_destino');
            $table->decimal('latitud_destino', 11, 8)->nullable();
            $table->decimal('logitud_destino', 11, 8)->nullable();
            $table->boolean('abastecimiento_combustible')->default(false);
            $table->text('firma_digital')->nullable();
            $table->string('odometro_photo_path')->nullable();
            $table->json('ruta_json')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['vehicle_log', 'vehicle_assignments', 'fuel_logs', 'drivers', 'vehicles'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_crea_y_lista_bitacoras_como_la_vista_vehicular(): void
    {
        Storage::fake('public');

        $vehicleId = Schema::getConnection()->table('vehicles')->insertGetId([
            'placa' => 'ABC-123',
            'kilometraje_actual' => 12500.50,
            'kilometraje' => 12500.50,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $driverId = Schema::getConnection()->table('drivers')->insertGetId([
            'nombre' => 'Conductor API',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::getConnection()->table('vehicle_assignments')->insert([
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'fecha_inicio' => now()->subDay()->toDateString(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->post('/api/bitacoras', [
            'vehicles_id' => $vehicleId,
            'drivers_id' => $driverId,
            'fecha' => now()->toDateString(),
            'kilometraje_salida' => 12500.50,
            'kilometraje_recorrido' => 18.75,
            'cantidad_paquetes' => 24,
            'recorrido_inicio' => 'Oficina Central',
            'latitud_inicio' => -16.495545,
            'logitud_inicio' => -68.133592,
            'recorrido_destino' => 'Sucursal Miraflores',
            'latitud_destino' => -16.503105,
            'logitud_destino' => -68.121558,
            'odometro_photo' => UploadedFile::fake()->image('odometro.jpg'),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Registro de bitacora creado correctamente.')
            ->assertJsonPath('data.cantidad_paquetes', 24)
            ->assertJsonPath('data.vehicle.placa', 'ABC-123')
            ->assertJsonPath('data.driver.nombre', 'Conductor API');

        $photoPath = (string) $response->json('data.odometro_photo_path');
        Storage::disk('public')->assertExists($photoPath);

        $this->assertDatabaseHas('vehicle_log', [
            'vehicles_id' => $vehicleId,
            'drivers_id' => $driverId,
            'kilometraje_llegada' => 12519.25,
            'kilometraje_recorrido' => 18.75,
            'cantidad_paquetes' => 24,
        ]);
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicleId,
            'kilometraje_actual' => 12519.25,
        ]);

        $this->getJson('/api/bitacoras?search=ABC-123&per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $response->json('data.id'));
    }

    public function test_el_catalogo_api_devuelve_todos_los_conductores_activos_aunque_esten_asignados(): void
    {
        $assignedDriverId = Schema::getConnection()->table('drivers')->insertGetId([
            'nombre' => 'Israel',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $freeDriverId = Schema::getConnection()->table('drivers')->insertGetId([
            'nombre' => 'Pablo',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::getConnection()->table('drivers')->insert([
            'nombre' => 'Inactivo',
            'activo' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $vehicleId = Schema::getConnection()->table('vehicles')->insertGetId([
            'placa' => 'ASG-001',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::getConnection()->table('vehicle_assignments')->insert([
            'vehicle_id' => $vehicleId,
            'driver_id' => $assignedDriverId,
            'fecha_inicio' => now()->subDay()->toDateString(),
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/api/bitacoras/conductores')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('data.0.id', $assignedDriverId)
            ->assertJsonPath('data.0.tiene_asignacion_activa', true)
            ->assertJsonPath('data.0.asignacion_activa.placa', 'ASG-001')
            ->assertJsonPath('data.1.id', $freeDriverId)
            ->assertJsonPath('data.1.tiene_asignacion_activa', false)
            ->assertJsonMissing(['nombre' => 'Inactivo']);
    }
}

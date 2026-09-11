<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureExternalApiAbility;
use App\Http\Middleware\EnsureExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceExternalApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureExternalApiJwt::class,
            EnsureExternalApiAbility::class,
        ]);

        Schema::create('vehicle_brands', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });
        Schema::create('vehicle_classes', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('maintenance_form_type')->nullable();
            $table->timestamps();
        });
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('placa');
            $table->unsignedBigInteger('marca_id')->nullable();
            $table->unsignedBigInteger('vehicle_class_id')->nullable();
            $table->string('maintenance_form_type')->nullable();
            $table->string('modelo')->nullable();
            $table->string('color')->nullable();
            $table->string('tipo_combustible')->nullable();
            $table->integer('anio')->nullable();
            $table->decimal('kilometraje_inicial', 10, 2)->nullable();
            $table->decimal('kilometraje_actual', 10, 2)->nullable();
            $table->decimal('kilometraje', 10, 2)->nullable();
            $table->string('operational_status')->nullable();
            $table->boolean('tacometro_danado')->default(false);
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('nombre');
            $table->string('licencia')->nullable();
            $table->string('tipo_licencia')->nullable();
            $table->date('fecha_vencimiento_licencia')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable();
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('vehicle_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->dateTime('fecha_inicio')->nullable();
            $table->dateTime('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('maintenance_types', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_class_id')->nullable();
            $table->string('maintenance_form_type')->nullable();
            $table->string('nombre');
            $table->string('categoria')->nullable();
            $table->boolean('es_preventivo')->default(false);
            $table->integer('cada_km')->nullable();
            $table->integer('intervalo_km')->nullable();
            $table->integer('intervalo_km_init')->nullable();
            $table->integer('intervalo_km_fh')->nullable();
            $table->integer('km_alerta_previa')->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('maintenance_type_vehicle', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maintenance_type_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->timestamps();
        });
        Schema::create('maintenance_appointments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('tipo_mantenimiento_id')->nullable();
            $table->dateTime('fecha_programada')->nullable();
            $table->dateTime('solicitud_fecha')->nullable();
            $table->string('origen_solicitud')->nullable();
            $table->boolean('es_accidente')->default(false);
            $table->string('evidencia_path')->nullable();
            $table->string('formulario_documento_path')->nullable();
            $table->string('estado')->default('Pendiente');
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        Schema::create('maintenance_alerts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('maintenance_type_id')->nullable();
            $table->unsignedBigInteger('maintenance_appointment_id')->nullable();
            $table->string('tipo');
            $table->string('mensaje');
            $table->boolean('leida')->default(false);
            $table->string('status');
            $table->dateTime('fecha_resolucion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->decimal('kilometraje_actual', 10, 2)->nullable();
            $table->decimal('kilometraje_objetivo', 10, 2)->nullable();
            $table->decimal('faltante_km', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach ([
            'maintenance_alerts',
            'maintenance_appointments',
            'maintenance_type_vehicle',
            'maintenance_types',
            'vehicle_assignments',
            'drivers',
            'vehicles',
            'vehicle_classes',
            'vehicle_brands',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_expone_catalogos_crea_y_lista_solicitudes_de_mantenimiento(): void
    {
        Storage::fake('public');
        $brandId = Schema::getConnection()->table('vehicle_brands')->insertGetId([
            'nombre' => 'Toyota', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classId = Schema::getConnection()->table('vehicle_classes')->insertGetId([
            'nombre' => 'Camioneta', 'maintenance_form_type' => 'vehiculo', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vehicleId = Schema::getConnection()->table('vehicles')->insertGetId([
            'placa' => 'MNT-001', 'marca_id' => $brandId, 'vehicle_class_id' => $classId,
            'maintenance_form_type' => 'vehiculo', 'modelo' => 'Hilux', 'kilometraje_actual' => 12500.5,
            'operational_status' => 'Disponible', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $driverId = Schema::getConnection()->table('drivers')->insertGetId([
            'nombre' => 'Conductor API', 'licencia' => 'LIC-001', 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Schema::getConnection()->table('vehicle_assignments')->insert([
            'vehicle_id' => $vehicleId, 'driver_id' => $driverId, 'fecha_inicio' => now()->subDay(),
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $typeId = Schema::getConnection()->table('maintenance_types')->insertGetId([
            'vehicle_class_id' => $classId, 'maintenance_form_type' => 'vehiculo',
            'nombre' => 'Cambio de aceite', 'categoria' => 'preventivo_km', 'es_preventivo' => true,
            'cada_km' => 5000, 'km_alerta_previa' => 500, 'activo' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/mantenimientos/vehiculos')
            ->assertOk()
            ->assertJsonPath('data.0.placa', 'MNT-001')
            ->assertJsonPath('data.0.asignacion_activa.driver_id', $driverId);
        $this->getJson('/api/mantenimientos/conductores')
            ->assertOk()
            ->assertJsonPath('data.0.nombre', 'Conductor API')
            ->assertJsonPath('data.0.asignacion_activa.vehicle_id', $vehicleId);
        $this->getJson('/api/mantenimientos/tipos?vehicle_id='.$vehicleId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $typeId)
            ->assertJsonPath('data.0.cada_km', 5000);

        $response = $this->post('/api/mantenimientos', [
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
            'maintenance_type_id' => $typeId,
            'fecha_programada' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'es_accidente' => false,
            'evidencia' => UploadedFile::fake()->image('evidencia.jpg'),
            'formulario_documento' => UploadedFile::fake()->create('formulario.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response
            ->assertCreated()
            ->assertJsonPath('data.estado', 'Pendiente')
            ->assertJsonPath('data.origen_solicitud', 'external_api')
            ->assertJsonPath('data.vehicle.placa', 'MNT-001')
            ->assertJsonPath('data.driver.nombre', 'Conductor API')
            ->assertJsonPath('data.maintenance_type.nombre', 'Cambio de aceite');

        $appointmentId = (int) $response->json('data.id');
        $this->assertDatabaseHas('maintenance_alerts', [
            'maintenance_appointment_id' => $appointmentId,
            'tipo' => 'Solicitud',
            'status' => 'Solicitada',
        ]);
        $this->getJson('/api/mantenimientos?search=MNT-001&status=Pendiente')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $appointmentId);

        $this->get('/api/mantenimientos/'.$appointmentId.'/evidencia')->assertOk();
        $this->get('/api/mantenimientos/'.$appointmentId.'/formulario')->assertOk();
    }

    public function test_rechaza_un_tipo_que_no_corresponde_al_vehiculo(): void
    {
        $vehicleId = Schema::getConnection()->table('vehicles')->insertGetId([
            'placa' => 'MNT-002', 'vehicle_class_id' => 10, 'maintenance_form_type' => 'vehiculo',
            'operational_status' => 'Disponible', 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $typeId = Schema::getConnection()->table('maintenance_types')->insertGetId([
            'vehicle_class_id' => 20, 'maintenance_form_type' => 'moto', 'nombre' => 'Servicio de moto',
            'activo' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson('/api/mantenimientos', [
            'vehicle_id' => $vehicleId,
            'maintenance_type_id' => $typeId,
            'fecha_programada' => now()->addDay()->toIso8601String(),
        ])->assertUnprocessable()->assertJsonValidationErrors('maintenance_type_id');
    }
}

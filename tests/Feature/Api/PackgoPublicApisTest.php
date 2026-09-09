<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackgoPublicApisTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareSchema();
    }

    public function test_alertas_soporte_y_sync_es_publica_y_responde_json(): void
    {
        $vehicleId = $this->createVehicle();
        $this->createMaintenanceAlert($vehicleId);

        $this->getJson('/api/mobile/maintenance_alerts')
            ->assertOk()
            ->assertJsonStructure(['current_page', 'data', 'per_page', 'total'])
            ->assertJsonFragment([
                'mensaje' => 'Alerta de prueba de mantenimiento',
            ]);

        $this->getJson('/api/mobile/maintenance_types')
            ->assertOk()
            ->assertJsonStructure(['current_page', 'data', 'per_page', 'total']);
    }

    public function test_combustible_y_qr_es_publica_y_responde_json(): void
    {
        $vehicleId = $this->createVehicle([
            'placa' => 'PLT-123',
        ]);
        $legacyFuelLogId = $this->createLegacyFuelLog();
        $fuelInvoiceId = $this->createFuelInvoice();
        $fuelLogId = $this->createFuelLogDetail($fuelInvoiceId);
        $this->createVehicleLog($vehicleId, $legacyFuelLogId);

        $this->getJson('/api/fuel-logs')
            ->assertOk()
            ->assertJsonStructure(['current_page', 'data', 'per_page', 'total']);

        $this->getJson("/api/fuel-logs/by-vehicle/{$vehicleId}")
            ->assertOk()
            ->assertJsonStructure(['current_page', 'data', 'per_page', 'total'])
            ->assertJsonFragment([
                'vehicle_id' => $vehicleId,
            ]);
    }

    public function test_session_health_responde_sin_login(): void
    {
        $this->getJson('/api/mobile/bitacora/session-health')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'session' => null,
                'ticket' => null,
            ]);
    }

    public function test_post_y_patch_publicos_de_packgo_responden_sin_login(): void
    {
        $userId = $this->createUser();
        $vehicleId = $this->createVehicle([
            'placa' => 'PUB-789',
            'maintenance_form_type' => 'mobile_driver',
        ]);
        $alertId = $this->createMaintenanceAlert($vehicleId);

        $this->postJson('/api/mobile/maintenance-requests', [
            'vehicle_id' => $vehicleId,
            'maintenance_type_name' => 'Cambio de aceite',
            'fecha_programada' => '2026-09-10',
            'es_accidente' => false,
        ])
            ->assertCreated()
            ->assertJsonFragment([
                'message' => 'Solicitud de mantenimiento registrada correctamente.',
            ]);

        $this->postJson('/api/activity-logs', [
            'user_id' => $userId,
            'action' => 'BITACORA_LOAD_START',
            'model' => 'vehicle_log',
            'record_id' => 4,
            'changes_json' => ['example' => true],
        ])
            ->assertCreated()
            ->assertJsonFragment([
                'message' => 'Actividad registrada.',
            ]);

        $this->postJson('/api/mobile/db-snapshot/chunk', [
            'user_id' => $userId,
            'action' => 'CHUNK',
            'model' => 'mobile_sqlite.vehicle_log',
            'record_id' => 1,
            'table_name' => 'vehicle_log',
            'page' => 1,
            'total_pages' => 3,
            'changes_json' => ['rows' => [['id' => 1, 'vehicle_id' => $vehicleId]]],
        ])
            ->assertCreated()
            ->assertJsonFragment([
                'message' => 'Chunk recibido.',
            ]);

        $this->postJson('/api/mobile/operational-incident', [
            'vehicle_id' => $vehicleId,
            'driver_id' => null,
            'session_reference' => 'BITACORA-20260908-001',
            'incident_type' => 'mobile_runtime_error',
            'severity' => 'danger',
            'message' => 'Error de ejemplo',
            'current_stage' => 'Salida',
            'meta_json' => ['source' => 'packgo'],
        ])
            ->assertCreated()
            ->assertJsonFragment([
                'ok' => true,
            ]);

        $this->patchJson("/api/alerts/{$alertId}/read", [
            'user_id' => $userId,
            'read' => true,
        ])
            ->assertOk()
            ->assertJsonFragment([
                'ok' => true,
                'alert_id' => $alertId,
                'read' => true,
            ]);
    }

    private function prepareSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('ci')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('placa', 20)->unique();
            $table->string('marca', 50)->nullable();
            $table->string('modelo', 50)->nullable();
            $table->string('maintenance_form_type', 50)->nullable();
            $table->string('tipo_combustible', 30)->nullable();
            $table->string('color', 30)->nullable();
            $table->integer('anio')->nullable();
            $table->decimal('capacidad_tanque', 10, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->decimal('kilometraje_inicial', 10, 2)->nullable();
            $table->decimal('kilometraje_actual', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maintenance_types', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->text('descripcion')->nullable();
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
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->unsignedBigInteger('tipo_mantenimiento_id')->nullable();
            $table->dateTime('fecha_programada')->nullable();
            $table->dateTime('solicitud_fecha')->nullable();
            $table->string('origen_solicitud')->nullable();
            $table->boolean('es_accidente')->default(false);
            $table->string('evidencia_path')->nullable();
            $table->string('formulario_documento_path')->nullable();
            $table->string('estado', 50)->default('Pendiente');
            $table->dateTime('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by_user_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_assignments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('maintenance_alerts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('maintenance_type_id')->nullable();
            $table->unsignedBigInteger('maintenance_appointment_id')->nullable();
            $table->string('tipo', 30);
            $table->string('mensaje', 255);
            $table->boolean('leida')->default(false);
            $table->string('status', 20)->default('Activa');
            $table->dateTime('fecha_resolucion')->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('ot_id')->nullable();
            $table->decimal('kilometraje_actual', 10, 2)->nullable();
            $table->decimal('kilometraje_objetivo', 10, 2)->nullable();
            $table->decimal('faltante_km', 10, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_alert_user_reads', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('maintenance_alert_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('gas_stations', function (Blueprint $table): void {
            $table->id();
            $table->string('razon_social')->nullable();
            $table->string('nombre')->nullable();
            $table->string('nit_emisor')->nullable();
            $table->string('direccion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('fuel_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('numero');
            $table->date('fecha_emision')->nullable();
            $table->foreignId('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete();
            $table->decimal('monto_total', 10, 2)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('fuel_logs', function (Blueprint $table): void {
            $table->id();
            $table->dateTime('fecha');
            $table->decimal('galones', 10, 2)->nullable();
            $table->decimal('precio_galon', 10, 2)->nullable();
            $table->decimal('total_calculado', 10, 2)->nullable();
            $table->decimal('kilometraje', 10, 2)->nullable();
            $table->string('recibo')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('fuel_invoice_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('fuel_invoice_id')->constrained('fuel_invoices')->cascadeOnDelete();
            $table->foreignId('fuel_log_id')->nullable()->constrained('fuel_logs')->nullOnDelete();
            $table->foreignId('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete();
            $table->decimal('cantidad', 10, 2)->nullable();
            $table->decimal('precio_unitario', 10, 2)->nullable();
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->string('estado', 30)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 255);
            $table->string('model', 120)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->unsignedBigInteger('vehicle_log_id')->nullable();
            $table->json('changes_json')->nullable();
            $table->string('module', 100)->nullable();
            $table->text('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->dateTime('fecha')->nullable();
            $table->timestamps();
        });

        Schema::create('mobile_db_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('snapshot_key', 120)->index();
            $table->dateTime('sent_at')->nullable();
            $table->string('action', 120)->index();
            $table->string('model', 190)->index();
            $table->string('table_name', 120)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->unsignedInteger('page')->nullable();
            $table->unsignedInteger('total_pages')->nullable();
            $table->longText('payload_json')->nullable();
            $table->unsignedInteger('payload_size')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_operation_alerts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->unsignedBigInteger('vehicle_log_session_id')->nullable()->index();
            $table->string('alert_type', 40)->index();
            $table->string('severity', 20)->default('info')->index();
            $table->string('status', 20)->default('Activa')->index();
            $table->string('title', 180);
            $table->text('message')->nullable();
            $table->string('current_stage', 40)->nullable()->index();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('detected_at')->nullable()->index();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->json('meta_json')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_log_sessions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('responsible_driver_id')->nullable();
            $table->unsignedBigInteger('current_driver_id')->nullable();
            $table->string('session_reference', 120)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_log', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('drivers_id')->nullable();
            $table->unsignedBigInteger('vehicles_id');
            $table->unsignedBigInteger('fuel_log_id')->nullable();
            $table->date('fecha');
            $table->decimal('kilometraje_salida', 10, 2);
            $table->decimal('kilometraje_llegada', 10, 2)->nullable();
            $table->string('recorrido_inicio');
            $table->string('recorrido_destino');
            $table->boolean('abastecimiento_combustible')->default(false);
            $table->text('firma_digital')->nullable();
            $table->json('ruta_json')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(): int
    {
        return (int) \DB::table('users')->insertGetId([
            'name' => 'Usuario Prueba',
            'email' => 'usuario.prueba@example.com',
            'password' => bcrypt('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createVehicle(array $overrides = []): int
    {
        return (int) \DB::table('vehicles')->insertGetId(array_merge([
            'placa' => 'PRU-001',
            'marca' => 'Toyota',
            'modelo' => 'Prueba',
            'maintenance_form_type' => 'mobile_driver',
            'tipo_combustible' => 'Gasolina',
            'color' => 'Blanco',
            'anio' => 2026,
            'capacidad_tanque' => 50,
            'activo' => true,
            'kilometraje_inicial' => 100,
            'kilometraje_actual' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createMaintenanceAlert(int $vehicleId): int
    {
        return (int) \DB::table('maintenance_alerts')->insertGetId([
            'vehicle_id' => $vehicleId,
            'maintenance_type_id' => null,
            'maintenance_appointment_id' => null,
            'tipo' => 'preventivo',
            'mensaje' => 'Alerta de prueba de mantenimiento',
            'leida' => false,
            'status' => 'Activa',
            'fecha_resolucion' => null,
            'usuario_id' => null,
            'ot_id' => null,
            'kilometraje_actual' => 100,
            'kilometraje_objetivo' => 120,
            'faltante_km' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createFuelInvoice(): int
    {
        return (int) \DB::table('fuel_invoices')->insertGetId([
            'numero' => 'F-0001',
            'fecha_emision' => now()->toDateString(),
            'monto_total' => 100,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createLegacyFuelLog(): int
    {
        return (int) \DB::table('fuel_logs')->insertGetId([
            'fecha' => now(),
            'galones' => 10,
            'precio_galon' => 10,
            'total_calculado' => 100,
            'kilometraje' => 150,
            'recibo' => 'F-0001',
            'observaciones' => 'Registro de prueba',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createFuelLogDetail(int $fuelInvoiceId): int
    {
        return (int) \DB::table('fuel_invoice_details')->insertGetId([
            'fuel_invoice_id' => $fuelInvoiceId,
            'cantidad' => 10,
            'precio_unitario' => 10,
            'subtotal' => 100,
            'estado' => 'Falta verificar',
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createVehicleLog(int $vehicleId, int $fuelLogId): int
    {
        return (int) \DB::table('vehicle_log')->insertGetId([
            'vehicles_id' => $vehicleId,
            'fuel_log_id' => $fuelLogId,
            'fecha' => now()->toDateString(),
            'kilometraje_salida' => 100,
            'kilometraje_llegada' => 150,
            'recorrido_inicio' => 'Salida de prueba',
            'recorrido_destino' => 'Llegada de prueba',
            'abastecimiento_combustible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

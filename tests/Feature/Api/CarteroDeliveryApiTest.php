<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CarteroDeliveryApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        $this->createTables();

        DB::table('users')->insert([
            'id' => 38,
            'name' => 'Cartero API',
            'email' => 'cartero.api@example.test',
            'password' => bcrypt('secret'),
            'ciudad' => 'LA PAZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('estados')->insert([
            ['id' => 13, 'nombre_estado' => 'CARTERO'],
            ['id' => 14, 'nombre_estado' => 'PROVINCIA'],
            ['id' => 27, 'nombre_estado' => 'ENTREGADO'],
        ]);
        DB::table('eventos')->insert([
            'id' => 316,
            'nombre_evento' => 'Paquete entregado exitosamente.',
        ]);
    }

    protected function tearDown(): void
    {
        foreach (['app_settings', 'eventos_ems', 'cartero', 'paquetes_ems', 'eventos', 'estados', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    public function test_cartero_entrega_su_paquete_con_fotografia_y_evento(): void
    {
        $now = Carbon::parse('2026-09-01 13:00:00');
        DB::table('paquetes_ems')->insert([
            'id' => 15,
            'codigo' => 'EE123456789BO',
            'nombre_destinatario' => 'Maria Perez',
            'ciudad' => 'LA PAZ',
            'estado_id' => 13,
            'imagen' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('cartero')->insert([
            'id' => 90,
            'id_paquetes_ems' => 15,
            'id_estados' => 13,
            'id_user' => 38,
            'intento' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('eventos_ems')->insert([
            'codigo' => 'EE123456789BO',
            'evento_id' => 184,
            'user_id' => 38,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $photo = UploadedFile::fake()->createWithContent(
            'foto-entrega.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZlS8AAAAASUVORK5CYII=')
        );

        $response = $this->actingAs(User::query()->findOrFail(38))
            ->post('/api/chasqui/paquetes/entregar', [
                'tipo_paquete' => 'EMS',
                'id' => 15,
                'recibido_por' => 'Maria Perez',
                'fecha_entrega' => '2026-09-01T14:30',
                'descripcion' => 'Entregado personalmente.',
                'foto' => $photo,
            ], ['Accept' => 'application/json']);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Correspondencia entregada correctamente.')
            ->assertJsonPath('data.codigo', 'EE123456789BO')
            ->assertJsonPath('data.estado', 'ENTREGADO')
            ->assertJsonPath('data.foto_guardada', true);

        $this->assertDatabaseHas('paquetes_ems', [
            'id' => 15,
            'estado_id' => 27,
        ]);
        $this->assertDatabaseHas('cartero', [
            'id' => 90,
            'id_estados' => 27,
            'recibido_por' => 'Maria Perez',
            'descripcion' => 'Entregado personalmente.',
        ]);
        $this->assertDatabaseHas('eventos_ems', [
            'codigo' => 'EE123456789BO',
            'evento_id' => 316,
            'user_id' => 38,
        ]);

        $this->assertStringStartsWith(
            'data:image/',
            (string) DB::table('cartero')->where('id', 90)->value('imagen')
        );
    }

    public function test_fotografia_es_obligatoria_para_confirmar_la_entrega(): void
    {
        $this->actingAs(User::query()->findOrFail(38))
            ->postJson('/api/chasqui/paquetes/entregar', [
                'tipo_paquete' => 'EMS',
                'id' => 15,
                'recibido_por' => 'Maria Perez',
                'fecha_entrega' => '2026-09-01T14:30',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('foto');
    }

    public function test_api_indica_intervalo_y_paquetes_pendientes_del_cartero(): void
    {
        DB::table('app_settings')->insert([
            ['key' => 'chasqui.notifications.enabled', 'value' => '1'],
            ['key' => 'chasqui.notifications.interval_minutes', 'value' => '30'],
            ['key' => 'chasqui.notifications.title', 'value' => 'ChasquiApp'],
            ['key' => 'chasqui.notifications.message', 'value' => 'Tienes paquetes pendientes'],
        ]);
        DB::table('paquetes_ems')->insert([
            'id' => 16,
            'codigo' => 'EE987654321BO',
            'nombre_destinatario' => 'Destinatario pendiente',
            'ciudad' => 'LA PAZ',
            'estado_id' => 13,
            'imagen' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('cartero')->insert([
            'id' => 91,
            'id_paquetes_ems' => 16,
            'id_estados' => 13,
            'id_user' => 38,
            'intento' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::query()->findOrFail(38))
            ->getJson('/api/chasqui/notificaciones/pendientes')
            ->assertOk()
            ->assertJsonPath('should_notify', true)
            ->assertJsonPath('pending_packages', 1)
            ->assertJsonPath('pending_by_type.EMS', 1)
            ->assertJsonPath('notification.message', 'Tienes paquetes pendientes')
            ->assertJsonPath('notification.interval_minutes', 30)
            ->assertJsonPath('notification.interval_seconds', 1800);
    }

    private function createTables(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('ciudad')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
        });
        Schema::create('paquetes_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->string('nombre_destinatario')->nullable();
            $table->string('ciudad')->nullable();
            $table->unsignedBigInteger('estado_id');
            $table->longText('imagen')->nullable();
            $table->timestamps();
        });
        Schema::create('cartero', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_paquetes_ems')->nullable();
            $table->unsignedBigInteger('id_paquetes_certi')->nullable();
            $table->unsignedBigInteger('id_paquetes_ordi')->nullable();
            $table->unsignedBigInteger('id_paquetes_contrato')->nullable();
            $table->unsignedBigInteger('id_solicitud_cliente')->nullable();
            $table->unsignedBigInteger('id_estados');
            $table->unsignedBigInteger('id_user')->nullable();
            $table->unsignedInteger('intento')->default(0);
            $table->string('recibido_por')->nullable();
            $table->text('descripcion')->nullable();
            $table->longText('imagen')->nullable();
            $table->longText('imagen_devolucion')->nullable();
            $table->timestamps();
        });
        Schema::create('eventos_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
}

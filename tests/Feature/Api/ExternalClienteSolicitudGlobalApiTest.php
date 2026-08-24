<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\ExternalApiToken;
use App\Support\ExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExternalClienteSolicitudGlobalApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('external_api_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('jti')->unique();
            $table->string('token_hash');
            $table->text('token_encrypted')->nullable();
            $table->text('token_plain')->nullable();
            $table->json('abilities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_cliente', 9)->nullable()->unique();
            $table->string('provider')->default('local');
            $table->string('google_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('rol')->default('tiktokero');
            $table->string('telefono', 50)->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('origen', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_origen');
            $table->timestamps();
        });

        Schema::create('destino', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_destino');
            $table->timestamps();
        });

        Schema::create('servicio_extras', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('tarifario_tiktoker', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('origen_id');
            $table->unsignedBigInteger('destino_id');
            $table->unsignedBigInteger('servicio_extra_id');
            $table->decimal('peso1', 10, 2)->nullable();
            $table->decimal('peso2', 10, 2)->nullable();
            $table->decimal('peso3', 10, 2)->nullable();
            $table->decimal('peso_extra', 10, 2)->nullable();
            $table->unsignedInteger('tiempo_entrega')->nullable();
            $table->timestamps();
        });

        Schema::create('solicitud_clientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cliente_id');
            $table->string('codigo_solicitud')->nullable();
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('estado_id')->nullable();
            $table->string('origen')->nullable();
            $table->text('contenido')->nullable();
            $table->unsignedInteger('cantidad')->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->unsignedBigInteger('servicio_extra_id')->nullable();
            $table->string('nombre_remitente')->nullable();
            $table->string('carnet')->nullable();
            $table->string('telefono_remitente', 50)->nullable();
            $table->string('nombre_destinatario')->nullable();
            $table->string('telefono_destinatario', 50)->nullable();
            $table->string('direccion_recojo')->nullable();
            $table->string('direccion')->nullable();
            $table->string('ciudad')->nullable();
            $table->unsignedBigInteger('destino_id')->nullable();
            $table->unsignedBigInteger('tarifario_tiktoker_id')->nullable();
            $table->timestamps();
        });

        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });

        Schema::create('eventos_tiktoker', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('cliente_id');
            $table->timestamps();
        });
    }

    public function test_un_token_autorizado_puede_ver_solicitudes_de_todos_los_clientes(): void
    {
        $primero = $this->crearCliente('COD000001', 'Primero', 'primero@example.com');
        $segundo = $this->crearCliente('COD000002', 'Segundo', 'segundo@example.com');

        DB::table('solicitud_clientes')->insert([
            [
                'cliente_id' => $primero->id,
                'codigo_solicitud' => 'SL00000001LP',
                'nombre_remitente' => 'REMITENTE UNO',
                'nombre_destinatario' => 'DESTINATARIO UNO',
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'cliente_id' => $segundo->id,
                'codigo_solicitud' => 'SL00000002SC',
                'nombre_remitente' => 'REMITENTE DOS',
                'nombre_destinatario' => 'DESTINATARIO DOS',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->withToken($this->issueToken(['solicitudes-clientes:read']))
            ->getJson('/api/integraciones/solicitudes-clientes?per_page=50')
            ->assertOk()
            ->assertJsonPath('message', 'Solicitudes de clientes obtenidas correctamente.')
            ->assertJsonPath('solicitudes.total', 2)
            ->assertJsonPath('solicitudes.data.0.cliente.email', 'segundo@example.com')
            ->assertJsonPath('solicitudes.data.1.cliente.email', 'primero@example.com');
    }

    public function test_un_token_autorizado_puede_crear_una_solicitud_indicando_cliente_id(): void
    {
        $cliente = $this->crearCliente('COD000003', 'Cliente API', 'cliente.api@example.com');

        $estadoId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'SOLICITUD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $origenId = DB::table('origen')->insertGetId([
            'nombre_origen' => 'LA PAZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $destinoId = DB::table('destino')->insertGetId([
            'nombre_destino' => 'COCHABAMBA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $servicioId = DB::table('servicio_extras')->insertGetId([
            'nombre' => 'PUERTA A PUERTA',
            'descripcion' => 'Entrega en domicilio',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $tarifarioId = DB::table('tarifario_tiktoker')->insertGetId([
            'origen_id' => $origenId,
            'destino_id' => $destinoId,
            'servicio_extra_id' => $servicioId,
            'peso1' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withToken($this->issueToken(['solicitudes-clientes:create']))
            ->postJson('/api/integraciones/solicitudes-clientes', [
                'cliente_id' => $cliente->id,
                'servicio_extra_id' => $servicioId,
                'origen' => 'La Paz',
                'destino_id' => $destinoId,
                'cantidad' => 2,
                'contenido' => 'Documentos',
                'nombre_remitente' => 'Maria Perez',
                'carnet' => '1234567',
                'telefono_remitente' => '70000001',
                'nombre_destinatario' => 'Juan Lopez',
                'telefono_destinatario' => '70000002',
                'direccion_recojo' => 'Av. Siempre Viva 10',
                'direccion_entrega' => 'Calle Final 20',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Solicitud registrada correctamente.')
            ->assertJsonPath('solicitud.cliente_id', $cliente->id)
            ->assertJsonPath('solicitud.estado_id', $estadoId)
            ->assertJsonPath('solicitud.tarifario_tiktoker_id', $tarifarioId)
            ->assertJsonPath('solicitud.precio', '20.00');

        $this->assertDatabaseHas('solicitud_clientes', [
            'cliente_id' => $cliente->id,
            'codigo_solicitud' => 'SL00000001LP',
            'nombre_remitente' => 'MARIA PEREZ',
            'nombre_destinatario' => 'JUAN LOPEZ',
        ]);
        $this->assertDatabaseHas('eventos_tiktoker', [
            'codigo' => 'SL00000001LP',
            'cliente_id' => $cliente->id,
        ]);
    }

    public function test_un_token_sin_el_permiso_requerido_es_rechazado(): void
    {
        $this->withToken($this->issueToken(['solicitudes-clientes:read']))
            ->postJson('/api/integraciones/solicitudes-clientes', [])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'solicitudes-clientes:create');
    }

    private function crearCliente(string $codigo, string $nombre, string $email): Cliente
    {
        return Cliente::query()->create([
            'codigo_cliente' => $codigo,
            'name' => $nombre,
            'email' => $email,
            'telefono' => '70000000',
        ]);
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function issueToken(array $abilities): string
    {
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion solicitudes globales',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => $abilities,
            'is_active' => true,
        ]);

        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        return $jwt;
    }
}

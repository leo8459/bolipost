<?php

namespace Tests\Feature\Api;

use App\Models\ExternalApiToken;
use App\Support\ExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaqueteContactoApiTest extends TestCase
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

        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
            $table->timestamps();
        });

        $this->createPackageTable('paquetes_certi', [
            'codigo', 'destinatario', 'telefono', 'zona', 'cuidad', 'fk_estado',
        ]);
        $this->createPackageTable('paquetes_contrato', [
            'codigo', 'origen', 'destino', 'estados_id', 'nombre_r', 'telefono_r', 'nombre_d', 'telefono_d', 'direccion_d',
        ]);
        $this->createPackageTable('paquetes_ems', [
            'codigo', 'origen', 'ciudad', 'estado_id', 'nombre_remitente', 'telefono_remitente', 'nombre_destinatario', 'telefono_destinatario', 'direccion',
        ]);
        $this->createPackageTable('paquetes_ordi', [
            'codigo', 'ciudad', 'fk_estado', 'destinatario', 'telefono', 'zona',
        ]);
        $this->createPackageTable('solicitud_clientes', [
            'codigo_solicitud', 'origen', 'ciudad', 'estado_id', 'nombre_remitente', 'telefono_remitente', 'nombre_destinatario', 'telefono_destinatario', 'direccion',
        ]);

        foreach (['eventos_certi', 'eventos_contrato', 'eventos_ems', 'eventos_ordi', 'eventos_tiktoker'] as $table) {
            $this->createEventTable($table);
        }
    }

    public function test_rechaza_consultas_sin_token(): void
    {
        $this->getJson('/api/paquetes-contactos')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token de acceso invalido, vencido o dado de baja.');
    }

    public function test_devuelve_los_contactos_unificados_de_todos_los_tipos(): void
    {
        $now = now();
        $estadoId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'EN TRANSITO', 'created_at' => $now, 'updated_at' => $now,
        ]);

        DB::table('paquetes_certi')->insert([
            'codigo' => 'CERTI-1', 'destinatario' => 'Destino Certi', 'telefono' => '70000001',
            'zona' => 'Zona Central', 'cuidad' => 'POTOSI', 'fk_estado' => $estadoId,
            'created_at' => $now->copy()->subMinutes(5), 'updated_at' => $now,
        ]);
        DB::table('paquetes_contrato')->insert([
            'codigo' => 'CONTRATO-1', 'nombre_r' => 'Remitente Contrato', 'telefono_r' => '70000002',
            'nombre_d' => 'Destino Contrato', 'telefono_d' => '70000003',
            'origen' => 'LA PAZ', 'destino' => 'COCHABAMBA', 'estados_id' => $estadoId,
            'direccion_d' => 'Av. Blanco Galindo 123',
            'created_at' => $now->copy()->subMinutes(4), 'updated_at' => $now,
        ]);
        DB::table('paquetes_ems')->insert([
            'codigo' => 'EMS-1', 'nombre_remitente' => 'Remitente EMS', 'telefono_remitente' => '70000004',
            'nombre_destinatario' => 'Destino EMS', 'telefono_destinatario' => '70000005',
            'origen' => 'ORURO', 'ciudad' => 'TARIJA', 'estado_id' => $estadoId,
            'direccion' => 'Calle Bolivar 456',
            'created_at' => $now->copy()->subMinutes(3), 'updated_at' => $now,
        ]);
        DB::table('paquetes_ordi')->insert([
            'codigo' => 'ORDI-1', 'destinatario' => 'Destino Ordinario', 'telefono' => '70000006',
            'zona' => 'Zona Norte', 'ciudad' => 'BENI', 'fk_estado' => $estadoId,
            'created_at' => $now->copy()->subMinutes(2), 'updated_at' => $now,
        ]);
        DB::table('solicitud_clientes')->insert([
            'codigo_solicitud' => 'SOL-1', 'nombre_remitente' => 'Remitente Solicitud',
            'telefono_remitente' => '70000007', 'nombre_destinatario' => 'Destino Solicitud',
            'telefono_destinatario' => '70000008', 'origen' => 'SUCRE', 'ciudad' => 'PANDO',
            'direccion' => 'Av. Las Americas 789',
            'estado_id' => $estadoId, 'created_at' => $now->copy()->subMinute(), 'updated_at' => $now,
        ]);

        $response = $this->withToken($this->issueToken())
            ->getJson('/api/paquetes-contactos?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('paginacion.total_registros', 5)
            ->assertJsonCount(5, 'data');

        $items = collect($response->json('data'))->keyBy('tipo');

        $this->assertSame('Remitente Contrato', $items['contrato']['remitente']['nombre']);
        $this->assertSame('70000003', $items['contrato']['destinatario']['telefono']);
        $this->assertSame('Av. Blanco Galindo 123', $items['contrato']['destinatario']['direccion']);
        $this->assertSame('LA PAZ', $items['contrato']['origen']);
        $this->assertSame('COCHABAMBA', $items['contrato']['destino']);
        $this->assertSame($estadoId, $items['contrato']['estado']['id']);
        $this->assertSame('EN TRANSITO', $items['contrato']['estado']['nombre']);
        $this->assertSame('Remitente EMS', $items['ems']['remitente']['nombre']);
        $this->assertSame('Calle Bolivar 456', $items['ems']['destinatario']['direccion']);
        $this->assertSame('ORURO', $items['ems']['origen']);
        $this->assertSame('TARIJA', $items['ems']['destino']);
        $this->assertSame('Destino Solicitud', $items['solicitud']['destinatario']['nombre']);
        $this->assertSame('Av. Las Americas 789', $items['solicitud']['destinatario']['direccion']);
        $this->assertSame('SUCRE', $items['solicitud']['origen']);
        $this->assertSame('PANDO', $items['solicitud']['destino']);
        $this->assertNull($items['certi']['remitente']['nombre']);
        $this->assertNull($items['certi']['origen']);
        $this->assertSame('POTOSI', $items['certi']['destino']);
        $this->assertSame('Destino Certi', $items['certi']['destinatario']['nombre']);
        $this->assertSame('Zona Central', $items['certi']['destinatario']['direccion']);
        $this->assertNull($items['ordinario']['remitente']['telefono']);
        $this->assertNull($items['ordinario']['origen']);
        $this->assertSame('BENI', $items['ordinario']['destino']);
        $this->assertSame('70000006', $items['ordinario']['destinatario']['telefono']);
        $this->assertSame('Zona Norte', $items['ordinario']['destinatario']['direccion']);
    }

    public function test_permite_filtrar_por_tipo_y_codigo(): void
    {
        DB::table('paquetes_ems')->insert([
            [
                'codigo' => 'EMS-BUSCADO', 'nombre_remitente' => 'Uno', 'telefono_remitente' => '1',
                'nombre_destinatario' => 'Dos', 'telefono_destinatario' => '2',
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'codigo' => 'EMS-OTRO', 'nombre_remitente' => 'Tres', 'telefono_remitente' => '3',
                'nombre_destinatario' => 'Cuatro', 'telefono_destinatario' => '4',
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        $this->withToken($this->issueToken())
            ->getJson('/api/paquetes-contactos/ems?codigo=BUSCADO')
            ->assertOk()
            ->assertJsonPath('paginacion.total_registros', 1)
            ->assertJsonPath('data.0.tipo', 'ems')
            ->assertJsonPath('data.0.codigo', 'EMS-BUSCADO');
    }

    public function test_un_token_granular_solo_ve_los_tipos_seleccionados(): void
    {
        DB::table('paquetes_ems')->insert([
            'codigo' => 'EMS-PERMITIDO', 'nombre_remitente' => 'Remitente EMS', 'telefono_remitente' => '1',
            'nombre_destinatario' => 'Destino EMS', 'telefono_destinatario' => '2',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('paquetes_contrato')->insert([
            'codigo' => 'CONTRATO-BLOQUEADO', 'nombre_r' => 'Remitente Contrato', 'telefono_r' => '3',
            'nombre_d' => 'Destino Contrato', 'telefono_d' => '4',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $jwt = $this->issueToken(['paquetes-contactos:ems:read']);

        $this->withToken($jwt)
            ->getJson('/api/paquetes-contactos')
            ->assertOk()
            ->assertJsonPath('paginacion.total_registros', 1)
            ->assertJsonPath('tipos_incluidos.0', 'ems')
            ->assertJsonPath('data.0.codigo', 'EMS-PERMITIDO');

        $this->withToken($jwt)
            ->getJson('/api/paquetes-contactos/contrato')
            ->assertForbidden()
            ->assertJsonPath('message', 'El token no tiene permiso para consultar este tipo de paquete.');
    }

    public function test_api_de_todos_los_paquetes_incluye_todo_el_historial_de_eventos(): void
    {
        $now = now();
        $eventoAdmitido = DB::table('estados')->insertGetId([
            'nombre_estado' => 'ADMITIDO', 'created_at' => $now, 'updated_at' => $now,
        ]);

        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });

        $primerEvento = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Envio admitido.',
            'created_at' => $now->copy()->subMinutes(3),
            'updated_at' => $now,
        ]);
        $segundoEvento = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Envio en transito.',
            'created_at' => $now->copy()->subMinute(),
            'updated_at' => $now,
        ]);

        DB::table('paquetes_ems')->insert([
            'codigo' => 'EMS-EVENTOS',
            'estado_id' => $eventoAdmitido,
            'nombre_destinatario' => 'Destino',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('eventos_ems')->insert([
            [
                'codigo' => 'ems-eventos',
                'evento_id' => $primerEvento,
                'created_at' => $now->copy()->subMinutes(3),
                'updated_at' => $now,
            ],
            [
                'codigo' => 'EMS-EVENTOS',
                'evento_id' => $segundoEvento,
                'created_at' => $now->copy()->subMinute(),
                'updated_at' => $now,
            ],
        ]);

        $this->withToken($this->issueToken(['paquetes-eventos:read']))
            ->getJson('/api/paquetes-eventos?per_page=10')
            ->assertOk()
            ->assertJsonPath('paginacion.total_registros', 1)
            ->assertJsonPath('tipos_incluidos', ['certi', 'contrato', 'ems', 'ordinario', 'solicitud'])
            ->assertJsonPath('data.0.tipo', 'ems')
            ->assertJsonPath('data.0.cantidad_eventos', 2)
            ->assertJsonPath('data.0.eventos.0.nombre', 'Envio admitido.')
            ->assertJsonPath('data.0.eventos.1.nombre', 'Envio en transito.');
    }

    public function test_un_token_sin_permiso_de_direcciones_es_rechazado(): void
    {
        $this->withToken($this->issueToken(['paquetes-contactos:ems:read']))
            ->getJson('/api/direcciones-destino')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'direcciones-destino:read');
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function createPackageTable(string $name, array $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns): void {
            $table->id();
            foreach ($columns as $column) {
                $table->string($column)->nullable();
            }
            $table->timestamps();
        });
    }

    private function createEventTable(string $name): void
    {
        Schema::create($name, function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->timestamps();
        });
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function issueToken(array $abilities = ['paquetes-contactos:read']): string
    {
        $token = ExternalApiToken::query()->create([
            'name' => 'Postman paquetes',
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

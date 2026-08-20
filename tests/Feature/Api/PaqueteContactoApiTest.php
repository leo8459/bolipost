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

        $this->createPackageTable('paquetes_certi', [
            'codigo', 'destinatario', 'telefono',
        ]);
        $this->createPackageTable('paquetes_contrato', [
            'codigo', 'nombre_r', 'telefono_r', 'nombre_d', 'telefono_d',
        ]);
        $this->createPackageTable('paquetes_ems', [
            'codigo', 'nombre_remitente', 'telefono_remitente', 'nombre_destinatario', 'telefono_destinatario',
        ]);
        $this->createPackageTable('paquetes_ordi', [
            'codigo', 'destinatario', 'telefono',
        ]);
        $this->createPackageTable('solicitud_clientes', [
            'codigo_solicitud', 'nombre_remitente', 'telefono_remitente', 'nombre_destinatario', 'telefono_destinatario',
        ]);
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

        DB::table('paquetes_certi')->insert([
            'codigo' => 'CERTI-1', 'destinatario' => 'Destino Certi', 'telefono' => '70000001',
            'created_at' => $now->copy()->subMinutes(5), 'updated_at' => $now,
        ]);
        DB::table('paquetes_contrato')->insert([
            'codigo' => 'CONTRATO-1', 'nombre_r' => 'Remitente Contrato', 'telefono_r' => '70000002',
            'nombre_d' => 'Destino Contrato', 'telefono_d' => '70000003',
            'created_at' => $now->copy()->subMinutes(4), 'updated_at' => $now,
        ]);
        DB::table('paquetes_ems')->insert([
            'codigo' => 'EMS-1', 'nombre_remitente' => 'Remitente EMS', 'telefono_remitente' => '70000004',
            'nombre_destinatario' => 'Destino EMS', 'telefono_destinatario' => '70000005',
            'created_at' => $now->copy()->subMinutes(3), 'updated_at' => $now,
        ]);
        DB::table('paquetes_ordi')->insert([
            'codigo' => 'ORDI-1', 'destinatario' => 'Destino Ordinario', 'telefono' => '70000006',
            'created_at' => $now->copy()->subMinutes(2), 'updated_at' => $now,
        ]);
        DB::table('solicitud_clientes')->insert([
            'codigo_solicitud' => 'SOL-1', 'nombre_remitente' => 'Remitente Solicitud',
            'telefono_remitente' => '70000007', 'nombre_destinatario' => 'Destino Solicitud',
            'telefono_destinatario' => '70000008', 'created_at' => $now->copy()->subMinute(), 'updated_at' => $now,
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
        $this->assertSame('Remitente EMS', $items['ems']['remitente']['nombre']);
        $this->assertSame('Destino Solicitud', $items['solicitud']['destinatario']['nombre']);
        $this->assertNull($items['certi']['remitente']['nombre']);
        $this->assertSame('Destino Certi', $items['certi']['destinatario']['nombre']);
        $this->assertNull($items['ordinario']['remitente']['telefono']);
        $this->assertSame('70000006', $items['ordinario']['destinatario']['telefono']);
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

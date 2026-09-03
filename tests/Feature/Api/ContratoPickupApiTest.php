<?php

namespace Tests\Feature\Api;

use App\Models\ExternalApiToken;
use App\Support\ExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContratoPickupApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('ciudad')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

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

        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('estados_id');
            $table->string('origen');
            $table->dateTime('fecha_recojo')->nullable();
            $table->timestamps();
        });

        Schema::create('eventos_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    public function test_recoge_paquetes_en_solicitud_de_la_ciudad_del_propietario_del_token(): void
    {
        $now = now();
        $solicitudId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'SOLICITUD', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $almacenId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'ALMACEN', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('eventos')->insert([
            'id' => 295,
            'nombre_evento' => 'Paquete recibido del cliente.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operador La Paz',
            'email' => 'operador@example.com',
            'password' => bcrypt('password'),
            'ciudad' => 'LA PAZ',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('paquetes_contrato')->insert([
            [
                'codigo' => 'CTO-LP-001', 'estados_id' => $solicitudId, 'origen' => 'LA PAZ',
                'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'codigo' => 'CTO-CBBA-001', 'estados_id' => $solicitudId, 'origen' => 'COCHABAMBA',
                'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        $this->withToken($this->issueToken($userId))
            ->postJson('/api/paquetes-contrato/recoger', [
                'codigos' => ['cto-lp-001', 'CTO-CBBA-001', 'NO-EXISTE'],
            ])
            ->assertOk()
            ->assertJsonPath('actualizados', 1)
            ->assertJsonPath('codigos.0', 'CTO-LP-001')
            ->assertJsonPath('no_procesados', ['CTO-CBBA-001', 'NO-EXISTE']);

        $this->assertDatabaseHas('paquetes_contrato', [
            'codigo' => 'CTO-LP-001',
            'estados_id' => $almacenId,
        ]);
        $this->assertDatabaseHas('paquetes_contrato', [
            'codigo' => 'CTO-CBBA-001',
            'estados_id' => $solicitudId,
        ]);
        $this->assertDatabaseHas('eventos_contrato', [
            'codigo' => 'CTO-LP-001',
            'evento_id' => 295,
            'user_id' => $userId,
        ]);
        $this->assertDatabaseCount('eventos_contrato', 1);
        $this->assertNotNull(DB::table('paquetes_contrato')->where('codigo', 'CTO-LP-001')->value('fecha_recojo'));
    }

    public function test_rechaza_un_token_sin_permiso_de_recojo(): void
    {
        $this->withToken($this->issueToken(null, ['paquetes-contactos:contrato:read']))
            ->postJson('/api/paquetes-contrato/recoger', ['codigos' => ['CTO-001']])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'paquetes-contrato:pickup');
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function issueToken(?int $userId, array $abilities = ['paquetes-contrato:pickup']): string
    {
        $token = ExternalApiToken::query()->create([
            'user_id' => $userId,
            'name' => 'Integracion recojos',
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

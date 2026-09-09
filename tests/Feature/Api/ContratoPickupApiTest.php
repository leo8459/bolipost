<?php

namespace Tests\Feature\Api;

use App\Models\ExternalApiToken;
use App\Models\User;
use App\Services\ContratoPickupService;
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
            $table->decimal('peso', 10, 3)->nullable();
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

        Schema::create('solicitud_clientes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->string('codigo_solicitud')->nullable();
            $table->string('barcode')->nullable();
            $table->unsignedBigInteger('estado_id');
            $table->string('origen');
            $table->decimal('peso', 10, 3)->nullable();
            $table->timestamps();
        });

        Schema::create('eventos_tiktoker', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('cliente_id')->nullable();
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
                'envios' => [
                    ['codigo' => 'cto-lp-001', 'peso' => 700],
                    ['codigo' => 'CTO-CBBA-001', 'peso' => 2.350],
                    ['codigo' => 'NO-EXISTE', 'peso' => 0.500],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('actualizados', 1)
            ->assertJsonPath('actualizados_por_tipo.contrato', 1)
            ->assertJsonPath('actualizados_por_tipo.solicitud', 0)
            ->assertJsonPath('codigos.0', 'CTO-LP-001')
            ->assertJsonPath('no_procesados', ['CTO-CBBA-001', 'NO-EXISTE']);

        $this->assertDatabaseHas('paquetes_contrato', [
            'codigo' => 'CTO-LP-001',
            'estados_id' => $almacenId,
            'peso' => 700.000,
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

    public function test_recoge_contratos_y_solicitudes_delivery_express_en_la_misma_peticion(): void
    {
        $now = now();
        $solicitudId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'SOLICITUD', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $almacenId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'ALMACEN', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('eventos')->insert([
            [
                'id' => 295,
                'nombre_evento' => 'Paquete recibido del cliente.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 296,
                'nombre_evento' => 'Delivery Express recibido en almacen.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operador La Paz',
            'email' => 'operador.mixto@example.com',
            'password' => bcrypt('password'),
            'ciudad' => 'LA PAZ',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('paquetes_contrato')->insert([
            'codigo' => 'CTO-LP-002',
            'estados_id' => $solicitudId,
            'origen' => 'LA PAZ',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('solicitud_clientes')->insert([
            [
                'codigo_solicitud' => 'SL00000001LP',
                'barcode' => 'SL00000001LP',
                'estado_id' => $solicitudId,
                'origen' => 'LA PAZ',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'codigo_solicitud' => 'SL00000002CB',
                'barcode' => 'SL00000002CB',
                'estado_id' => $solicitudId,
                'origen' => 'COCHABAMBA',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->withToken($this->issueToken($userId))
            ->postJson('/api/paquetes-contrato/recoger', [
                'envios' => [
                    ['codigo' => 'CTO-LP-002', 'peso' => 1.250],
                    ['codigo' => 'sl00000001lp'],
                    ['codigo' => 'SL00000002CB'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('actualizados', 2)
            ->assertJsonPath('actualizados_por_tipo.contrato', 1)
            ->assertJsonPath('actualizados_por_tipo.solicitud', 1)
            ->assertJsonPath('codigos', ['CTO-LP-002', 'SL00000001LP'])
            ->assertJsonPath('no_procesados', ['SL00000002CB']);

        $this->assertDatabaseHas('solicitud_clientes', [
            'codigo_solicitud' => 'SL00000001LP',
            'estado_id' => $almacenId,
            'peso' => null,
        ]);
        $this->assertDatabaseHas('solicitud_clientes', [
            'codigo_solicitud' => 'SL00000002CB',
            'estado_id' => $solicitudId,
        ]);
        $this->assertDatabaseHas('eventos_tiktoker', [
            'codigo' => 'SL00000001LP',
            'evento_id' => 296,
            'user_id' => $userId,
        ]);
    }

    public function test_rechaza_un_token_sin_permiso_de_recojo(): void
    {
        $this->withToken($this->issueToken(null, ['paquetes-contactos:contrato:read']))
            ->postJson('/api/paquetes-contrato/recoger', ['codigos' => ['CTO-001']])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'paquetes-contrato:pickup');
    }

    public function test_la_api_aplica_el_rango_valido_cuando_se_envia_un_peso(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operador La Paz',
            'email' => 'operador.validacion.peso@example.com',
            'password' => bcrypt('password'),
            'ciudad' => 'LA PAZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = $this->issueToken($userId);

        $this->withToken($token)
            ->postJson('/api/paquetes-contrato/recoger', [
                'envios' => [['codigo' => 'CTO-PESO-CERO', 'peso' => 0]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['envios.0.peso']);

        $this->withToken($token)
            ->postJson('/api/paquetes-contrato/recoger', [
                'envios' => [['codigo' => 'CTO-PESO-ALTO', 'peso' => 700.001]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['envios.0.peso']);
    }

    public function test_la_api_exige_peso_para_contrato_pero_no_para_delivery_express(): void
    {
        $now = now();
        $solicitudId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'SOLICITUD', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('estados')->insert([
            'nombre_estado' => 'ALMACEN', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operador La Paz',
            'email' => 'operador.peso.condicional@example.com',
            'password' => bcrypt('password'),
            'ciudad' => 'LA PAZ',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('paquetes_contrato')->insert([
            'codigo' => 'CTO-PESO-REQUERIDO',
            'estados_id' => $solicitudId,
            'origen' => 'LA PAZ',
            'peso' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->withToken($this->issueToken($userId))
            ->postJson('/api/paquetes-contrato/recoger', [
                'envios' => [['codigo' => 'CTO-PESO-REQUERIDO']],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Por favor ingrese un peso entre 0,001 y 700,000 kg para los paquetes: CTO-PESO-REQUERIDO.');

        $this->assertDatabaseHas('paquetes_contrato', [
            'codigo' => 'CTO-PESO-REQUERIDO',
            'estados_id' => $solicitudId,
            'peso' => null,
        ]);
    }

    public function test_el_recojo_web_guarda_el_peso_obligatorio_antes_de_mover_a_almacen(): void
    {
        $now = now();
        $solicitudId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'SOLICITUD', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $almacenId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'ALMACEN', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('eventos')->insert([
            'id' => ContratoPickupService::EVENTO_ID_CONTRATO_RECOGIDO,
            'nombre_evento' => 'Paquete recibido del cliente.',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operador La Paz',
            'email' => 'operador.peso@example.com',
            'password' => bcrypt('password'),
            'ciudad' => 'LA PAZ',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $packageId = DB::table('paquetes_contrato')->insertGetId([
            'codigo' => 'CTO-PESO-001',
            'estados_id' => $solicitudId,
            'origen' => 'LA PAZ',
            'peso' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $result = app(ContratoPickupService::class)->recogerPorIdsConPesos(
            User::query()->findOrFail($userId),
            [$packageId],
            [$packageId => '1,250']
        );

        $this->assertSame(1, $result['actualizados']);
        $this->assertDatabaseHas('paquetes_contrato', [
            'id' => $packageId,
            'estados_id' => $almacenId,
            'peso' => 1.250,
        ]);
        $this->assertDatabaseHas('eventos_contrato', [
            'codigo' => 'CTO-PESO-001',
            'evento_id' => ContratoPickupService::EVENTO_ID_CONTRATO_RECOGIDO,
            'user_id' => $userId,
        ]);
    }

    public function test_el_recojo_web_rechaza_paquetes_sin_peso_e_informa_su_codigo(): void
    {
        $now = now();
        $solicitudId = DB::table('estados')->insertGetId([
            'nombre_estado' => 'SOLICITUD', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('estados')->insert([
            'nombre_estado' => 'ALMACEN', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $userId = DB::table('users')->insertGetId([
            'name' => 'Operador La Paz',
            'email' => 'operador.sinpeso@example.com',
            'password' => bcrypt('password'),
            'ciudad' => 'LA PAZ',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $packageId = DB::table('paquetes_contrato')->insertGetId([
            'codigo' => 'CTO-SIN-PESO',
            'estados_id' => $solicitudId,
            'origen' => 'LA PAZ',
            'peso' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        try {
            app(ContratoPickupService::class)->recogerPorIdsConPesos(
                User::query()->findOrFail($userId),
                [$packageId],
                [$packageId => '']
            );
            $this->fail('El servicio debio rechazar el recojo sin peso.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Por favor ingrese un peso', $exception->getMessage());
            $this->assertStringContainsString('CTO-SIN-PESO', $exception->getMessage());
        }

        $this->assertDatabaseHas('paquetes_contrato', [
            'id' => $packageId,
            'estados_id' => $solicitudId,
            'peso' => null,
        ]);
        $this->assertDatabaseCount('eventos_contrato', 0);
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

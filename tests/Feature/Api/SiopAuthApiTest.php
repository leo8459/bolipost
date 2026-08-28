<?php

namespace Tests\Feature\Api;

use App\Models\ExternalApiToken;
use App\Models\User;
use App\Support\ExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiopAuthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table): void {
            $table->id();
            $table->date('fin_contrato')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('alias')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('ciudad')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamp('auto_baja_empresa_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
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
    }

    public function test_una_integracion_autorizada_puede_iniciar_sesion_en_siop(): void
    {
        $user = User::factory()->create([
            'alias' => 'operador.siop',
            'password' => 'ClaveSegura123',
        ]);
        $integrationToken = $this->externalToken(['siop:login']);

        $response = $this->withToken($integrationToken)
            ->postJson('/api/integraciones/siop/login', [
                'alias' => 'OPERADOR.SIOP',
                'password' => 'ClaveSegura123',
                'device_name' => 'SIOP Android',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesion SIOP correcto.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.alias', 'operador.siop')
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'SIOP Android',
        ]);

        $userToken = (string) $response->json('access_token');
        $this->withToken($userToken)
            ->getJson('/api/siop/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);

        $this->withToken($userToken)
            ->postJson('/api/siop/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Sesion SIOP cerrada correctamente.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_siop_rechaza_credenciales_incorrectas(): void
    {
        User::factory()->create([
            'alias' => 'operador.siop',
            'password' => 'ClaveCorrecta123',
        ]);

        $this->withToken($this->externalToken(['siop:login']))
            ->postJson('/api/integraciones/siop/login', [
                'alias' => 'operador.siop',
                'password' => 'ClaveIncorrecta123',
            ])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'El usuario o la contrasena son incorrectos.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_siop_exige_el_permiso_de_integracion_correcto(): void
    {
        $this->withToken($this->externalToken(['paquetes-eventos:read']))
            ->postJson('/api/integraciones/siop/login', [
                'alias' => 'operador.siop',
                'password' => 'ClaveSegura123',
            ])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'siop:login');
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function externalToken(array $abilities): string
    {
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion SIOP',
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

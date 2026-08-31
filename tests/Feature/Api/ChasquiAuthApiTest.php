<?php

namespace Tests\Feature\Api;

use App\Models\ExternalApiToken;
use App\Models\User;
use App\Support\ExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ChasquiAuthApiTest extends TestCase
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

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_cartero_habilitado_puede_iniciar_sesion_en_chasqui(): void
    {
        $user = User::factory()->create([
            'alias' => 'cartero.chasqui',
            'password' => 'ClaveSegura123',
            'ciudad' => 'LA PAZ',
        ]);
        $user->assignRole(Role::create(['name' => 'cartero_ems', 'guard_name' => 'web']));

        $response = $this->withToken($this->externalToken(['chasqui:login']))
            ->postJson('/api/integraciones/chasqui/login', [
                'alias' => 'CARTERO.CHASQUI',
                'password' => 'ClaveSegura123',
                'device_name' => 'Chasqui Android',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesion ChasquiApp correcto.')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.alias', 'cartero.chasqui')
            ->assertJsonPath('user.role', 'cartero_ems')
            ->assertJsonPath('user.roles.0', 'cartero_ems')
            ->assertJsonPath('user.abilities.0', 'chasqui')
            ->assertJsonPath('user.abilities.1', 'cartero_ems')
            ->assertJsonPath('user.role_id', fn ($roleId): bool => is_int($roleId) && $roleId > 0)
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'Chasqui Android',
        ]);
    }

    public function test_chasqui_rechaza_un_usuario_que_no_es_cartero(): void
    {
        User::factory()->create([
            'alias' => 'operador.normal',
            'password' => 'ClaveSegura123',
        ]);

        $this->withToken($this->externalToken(['chasqui:login']))
            ->postJson('/api/integraciones/chasqui/login', [
                'alias' => 'operador.normal',
                'password' => 'ClaveSegura123',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'El usuario no tiene un rol de cartero habilitado para ChasquiApp.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_asignacion_exige_credencial_de_integracion_y_token_personal(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'cartero_ems', 'guard_name' => 'web']));
        $personalToken = $user->createToken('Chasqui Android', ['chasqui'])->plainTextToken;
        $integrationToken = $this->externalToken(['chasqui:paquetes:assign']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$personalToken,
            'X-API-Token' => $integrationToken,
        ])->postJson('/api/chasqui/paquetes/asignar', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$personalToken,
            'X-API-Token' => '',
        ])
            ->postJson('/api/chasqui/paquetes/asignar', [])
            ->assertUnauthorized();
    }

    /** @param array<int, string> $abilities */
    private function externalToken(array $abilities): string
    {
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion Chasqui',
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

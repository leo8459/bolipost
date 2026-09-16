<?php

namespace Tests\Feature\Api;

use App\Models\ExternalApiToken;
use App\Models\User;
use App\Services\ChasquiLocationService;
use App\Support\ExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
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

        Schema::create('chasqui_location_points', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('location_id', 64);
            $table->string('device_id', 190);
            $table->string('device_name', 120)->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_m', 10, 2)->nullable();
            $table->decimal('speed_kmh', 8, 2)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->decimal('battery_percent', 5, 2)->nullable();
            $table->boolean('is_moving')->default(false);
            $table->boolean('gps_enabled')->default(true);
            $table->boolean('gps_mocked')->default(false);
            $table->timestamp('sent_at');
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['location_id', 'sent_at']);
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

    public function test_entrega_exige_credencial_con_permiso_y_token_personal_del_cartero(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'cartero_ems', 'guard_name' => 'web']));
        $personalToken = $user->createToken('Chasqui Android', ['chasqui'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$personalToken,
            'X-API-Token' => $this->externalToken(['chasqui:paquetes:deliver']),
        ])->postJson('/api/chasqui/paquetes/entregar', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo_paquete', 'foto']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$personalToken,
            'X-API-Token' => $this->externalToken(['chasqui:paquetes:read']),
        ])->postJson('/api/chasqui/paquetes/entregar', [])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'chasqui:paquetes:deliver');
    }

    public function test_notificaciones_exigen_su_permiso_de_integracion(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'cartero_ems', 'guard_name' => 'web']));
        $personalToken = $user->createToken('Chasqui Android', ['chasqui'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$personalToken,
            'X-API-Token' => $this->externalToken(['chasqui:paquetes:read']),
        ])->getJson('/api/chasqui/notificaciones/pendientes')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'chasqui:notificaciones:read');
    }

    public function test_chasqui_puede_reportar_la_ubicacion_de_su_celular(): void
    {
        $user = User::factory()->create([
            'name' => 'Cartero GPS',
            'alias' => 'cartero.gps',
            'ciudad' => 'LA PAZ',
        ]);
        $user->assignRole(Role::create(['name' => 'cartero_ems', 'guard_name' => 'web']));
        $personalToken = $user->createToken('Chasqui Android', ['chasqui'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$personalToken,
            'X-API-Token' => $this->externalToken(['chasqui:location:update']),
        ])->postJson('/api/chasqui/location/heartbeat', [
            'device_id' => 'android-test-123',
            'device_name' => 'Poco Chasqui',
            'latitude' => -16.4897,
            'longitude' => -68.1193,
            'accuracy_m' => 7.5,
            'speed_kmh' => 10.8,
            'gps_enabled' => true,
            'gps_mocked' => false,
        ])
            ->assertStatus(202)
            ->assertJsonPath('message', 'Ubicacion de ChasquiApp recibida.')
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.alias', 'cartero.gps')
            ->assertJsonPath('data.device_name', 'Poco Chasqui')
            ->assertJsonPath('data.is_moving', true)
            ->assertJsonPath('data.is_stale', false);

        $index = Cache::get('chasqui:location:index');
        $this->assertIsArray($index);
        $this->assertCount(1, $index);
        $this->assertNotNull(Cache::get('chasqui:location:device:'.$index[0]));
        $this->assertDatabaseHas('chasqui_location_points', [
            'user_id' => $user->id,
            'device_id' => 'android-test-123',
            'latitude' => -16.4897,
            'longitude' => -68.1193,
        ]);
    }

    public function test_historial_diario_agrupa_y_ordena_el_recorrido_del_chasqui(): void
    {
        $user = User::factory()->create([
            'name' => 'Cartero Historial',
            'alias' => 'cartero.historial',
        ]);
        $locations = app(ChasquiLocationService::class);
        $firstAt = now()->startOfDay()->addHours(8);

        $locations->record($user, [
            'device_id' => 'walking-device',
            'latitude' => -16.4897,
            'longitude' => -68.1193,
            'sent_at' => $firstAt->toIso8601String(),
        ]);
        $locations->record($user, [
            'device_id' => 'walking-device',
            'latitude' => -16.4910,
            'longitude' => -68.1210,
            'sent_at' => $firstAt->copy()->addMinutes(5)->toIso8601String(),
        ]);

        $history = $locations->dailyLocations(now(), $user->id);

        $this->assertCount(1, $history);
        $this->assertSame(2, $history[0]['points_count']);
        $this->assertSame(-16.4897, $history[0]['points'][0]['lat']);
        $this->assertSame(-16.491, $history[0]['latitude']);
        $this->assertGreaterThan(0, $history[0]['distance_km']);
    }

    public function test_mapa_en_vivo_devuelve_solo_el_ultimo_punto_y_permite_filtrar_por_cartero(): void
    {
        $carteroUno = User::factory()->create(['name' => 'Cartero Uno']);
        $carteroDos = User::factory()->create(['name' => 'Cartero Dos']);
        $locations = app(ChasquiLocationService::class);

        $locations->record($carteroUno, [
            'device_id' => 'device-live-one',
            'latitude' => -16.4897,
            'longitude' => -68.1193,
        ]);
        $locations->record($carteroUno, [
            'device_id' => 'device-live-one',
            'latitude' => -16.4900,
            'longitude' => -68.1200,
            'sent_at' => now()->addSecond()->toIso8601String(),
        ]);
        $locations->record($carteroDos, [
            'device_id' => 'device-live-two',
            'latitude' => -17.3935,
            'longitude' => -66.1570,
        ]);

        $all = $locations->liveLocations();
        $filtered = $locations->liveLocations($carteroUno->id);

        $this->assertCount(2, $all);
        $this->assertCount(1, $filtered);
        $this->assertSame($carteroUno->id, $filtered[0]['user_id']);
        $this->assertSame(-16.49, $filtered[0]['latitude']);
        $this->assertSame(1, $filtered[0]['points_count']);
        $this->assertCount(1, $filtered[0]['points']);
    }

    public function test_ubicacion_chasqui_exige_el_permiso_de_integracion_correcto(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::create(['name' => 'cartero_ems', 'guard_name' => 'web']));
        $personalToken = $user->createToken('Chasqui Android', ['chasqui'])->plainTextToken;

        $this->withHeaders([
            'Authorization' => 'Bearer '.$personalToken,
            'X-API-Token' => $this->externalToken(['chasqui:paquetes:read']),
        ])->postJson('/api/chasqui/location/heartbeat', [
            'latitude' => -16.4897,
            'longitude' => -68.1193,
        ])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'chasqui:location:update');
    }

    public function test_credencial_de_rastreo_puede_consultar_todas_las_ubicaciones(): void
    {
        $carteroUno = User::factory()->create(['name' => 'Cartero Uno', 'alias' => 'cartero.uno']);
        $carteroDos = User::factory()->create(['name' => 'Cartero Dos', 'alias' => 'cartero.dos']);

        $locations = app(ChasquiLocationService::class);
        $locations->record($carteroUno, [
            'device_id' => 'device-one',
            'latitude' => -16.4897,
            'longitude' => -68.1193,
            'speed_kmh' => 8.5,
        ]);
        $locations->record($carteroDos, [
            'device_id' => 'device-two',
            'latitude' => -17.3935,
            'longitude' => -66.1570,
            'speed_kmh' => 0,
        ]);

        $token = $this->externalToken(['chasqui:location:update']);

        $this->withToken($token)
            ->getJson('/api/chasqui/location/heartbeat?online_only=1')
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['user_name' => 'Cartero Uno'])
            ->assertJsonFragment(['user_name' => 'Cartero Dos']);

        $this->withToken($token)
            ->getJson('/api/chasqui/location/heartbeat?moving_only=1')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.alias', 'cartero.uno');
    }

    public function test_consulta_de_ubicaciones_rechaza_una_credencial_sin_permiso(): void
    {
        $this->withToken($this->externalToken(['chasqui:paquetes:read']))
            ->getJson('/api/chasqui/location/heartbeat')
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'chasqui:location:update');
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

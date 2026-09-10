<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use App\Models\ExternalApiToken;
use App\Services\GoogleIdTokenVerifier;
use App\Support\ExternalApiJwt;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClienteAuthApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('clientes', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_cliente', 9)->nullable()->unique();
            $table->string('provider')->default('local');
            $table->string('google_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->string('rol')->default('tiktokero');
            $table->text('avatar')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->string('tipodocumentoidentidad', 50)->nullable();
            $table->string('numero_carnet', 50)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('direccion')->nullable();
            $table->string('complemento', 50)->nullable();
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

    public function test_un_sistema_externo_puede_registrar_un_cliente(): void
    {
        $response = $this->postJson('/api/clientes/register', [
            'name' => 'Cliente API',
            'email' => 'cliente.api@example.com',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'ClaveSegura123',
            'device_name' => 'Postman',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Cliente registrado correctamente.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('cliente.email', 'cliente.api@example.com')
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('clientes', [
            'email' => 'cliente.api@example.com',
            'provider' => 'local',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertTrue(Hash::check('ClaveSegura123', Cliente::firstOrFail()->password));
    }

    public function test_registro_valida_correo_unico_y_confirmacion_de_contrasena(): void
    {
        Cliente::query()->create([
            'name' => 'Existente',
            'email' => 'existente@example.com',
            'password' => 'ClaveSegura123',
        ]);

        $response = $this->postJson('/api/clientes/register', [
            'name' => 'Duplicado',
            'email' => 'existente@example.com',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'OtraClave123',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_un_cliente_puede_iniciar_sesion_y_recibir_un_token(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente API',
            'email' => 'login@example.com',
            'password' => 'ClaveSegura123',
        ]);

        $response = $this->postJson('/api/clientes/login', [
            'email' => 'login@example.com',
            'password' => 'ClaveSegura123',
            'device_name' => 'Sistema companero',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesion correcto.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('cliente.id', $cliente->id)
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Cliente::class,
            'tokenable_id' => $cliente->id,
            'name' => 'Sistema companero',
        ]);
    }

    public function test_login_rechaza_credenciales_incorrectas_sin_crear_token(): void
    {
        Cliente::query()->create([
            'name' => 'Cliente API',
            'email' => 'login@example.com',
            'password' => 'ClaveCorrecta123',
        ]);

        $response = $this->postJson('/api/clientes/login', [
            'email' => 'login@example.com',
            'password' => 'ClaveIncorrecta123',
        ]);

        $response
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'El correo o la contrasena son incorrectos.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_un_cliente_puede_iniciar_sesion_con_google_y_recibir_un_token(): void
    {
        $verifier = $this->mock(GoogleIdTokenVerifier::class);
        $verifier->shouldReceive('verify')
            ->once()
            ->with('token-google-valido')
            ->andReturn([
                'sub' => 'google-123',
                'email' => 'google@example.com',
                'email_verified' => true,
                'name' => 'Cliente Google',
                'picture' => 'https://example.com/avatar.png',
            ]);

        $response = $this->postJson('/api/clientes/google-login', [
            'id_token' => 'token-google-valido',
            'device_name' => 'Delivery Express Android',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesion con Google correcto.')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('cliente.email', 'google@example.com')
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('clientes', [
            'email' => 'google@example.com',
            'google_id' => 'google-123',
            'provider' => 'google',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => Cliente::class,
            'name' => 'Delivery Express Android',
        ]);
    }

    public function test_las_solicitudes_de_clientes_requieren_un_token(): void
    {
        $this->getJson('/api/clientes/solicitudes')->assertUnauthorized();
        $this->postJson('/api/clientes/solicitudes', [])->assertUnauthorized();
    }

    public function test_una_integracion_autorizada_puede_crear_un_usuario_delivery_express(): void
    {
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion de clientes',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:create'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->postJson('/api/integraciones/clientes', [
                'name' => 'Nuevo Cliente',
                'email' => 'nuevo.cliente@example.com',
                'password' => 'ClaveSegura123',
                'password_confirmation' => 'ClaveSegura123',
                'device_name' => 'Delivery Express',
            ])
            ->assertCreated()
            ->assertJsonPath('cliente.email', 'nuevo.cliente@example.com')
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseHas('clientes', [
            'email' => 'nuevo.cliente@example.com',
            'provider' => 'local',
        ]);
    }

    public function test_una_integracion_autorizada_puede_editar_un_usuario_delivery_express(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Original',
            'email' => 'cliente.editar@example.com',
            'password' => 'ClaveSegura123',
            'numero_carnet' => '1111111',
            'telefono' => '61111111',
            'direccion' => 'Direccion anterior',
        ]);
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion para editar clientes',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:update'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->patchJson("/api/integraciones/clientes/{$cliente->id}", [
                'name' => 'Cliente Actualizado',
                'numero_carnet' => '7654321',
                'telefono' => '70000000',
                'direccion' => 'Avenida Principal 123',
                'email' => 'correo.no.permitido@example.com',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Cliente actualizado correctamente.')
            ->assertJsonPath('cliente.id', $cliente->id)
            ->assertJsonPath('cliente.name', 'Cliente Actualizado')
            ->assertJsonPath('cliente.numero_carnet', '7654321')
            ->assertJsonPath('cliente.telefono', '70000000')
            ->assertJsonPath('cliente.direccion', 'Avenida Principal 123');

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'name' => 'Cliente Actualizado',
            'email' => 'cliente.editar@example.com',
            'numero_carnet' => '7654321',
            'telefono' => '70000000',
            'direccion' => 'Avenida Principal 123',
        ]);
    }

    public function test_editar_cliente_requiere_el_permiso_correspondiente(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Protegido',
            'email' => 'cliente.protegido@example.com',
            'password' => 'ClaveSegura123',
        ]);
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion sin permiso de edicion',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:create'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->patchJson("/api/integraciones/clientes/{$cliente->id}", ['name' => 'Cambio no autorizado'])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'clientes:update');

        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'name' => 'Cliente Protegido',
        ]);
    }

    public function test_editar_cliente_exige_al_menos_un_campo_permitido(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Sin Cambios',
            'email' => 'cliente.sin.cambios@example.com',
            'password' => 'ClaveSegura123',
        ]);
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion de edicion',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:update'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->patchJson("/api/integraciones/clientes/{$cliente->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente']);
    }

    public function test_una_integracion_autorizada_puede_actualizar_la_contrasena_de_un_cliente(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Contrasena',
            'email' => 'cliente.password@example.com',
            'password' => 'ClaveAnterior123',
        ]);
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion para actualizar contrasenas',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:password:update'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->patchJson("/api/integraciones/clientes/{$cliente->id}/password", [
                'password' => 'NuevaClaveSegura123',
                'password_confirmation' => 'NuevaClaveSegura123',
            ])
            ->assertOk()
            ->assertExactJson([
                'message' => 'Contraseña del cliente actualizada correctamente.',
            ]);

        $this->assertTrue(Hash::check('NuevaClaveSegura123', $cliente->fresh()->password));
        $this->assertFalse(Hash::check('ClaveAnterior123', $cliente->fresh()->password));
    }

    public function test_actualizar_contrasena_de_cliente_valida_longitud_y_confirmacion(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Validacion',
            'email' => 'cliente.validacion.password@example.com',
            'password' => 'ClaveAnterior123',
        ]);
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion para validar contrasenas',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:password:update'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->patchJson("/api/integraciones/clientes/{$cliente->id}/password", [
                'password' => 'corta',
                'password_confirmation' => 'diferente',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(Hash::check('ClaveAnterior123', $cliente->fresh()->password));
    }

    public function test_actualizar_contrasena_de_cliente_requiere_el_permiso_correspondiente(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Protegido',
            'email' => 'cliente.password.protegido@example.com',
            'password' => 'ClaveAnterior123',
        ]);
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion sin permiso de contrasenas',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:update'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->patchJson("/api/integraciones/clientes/{$cliente->id}/password", [
                'password' => 'NuevaClaveSegura123',
                'password_confirmation' => 'NuevaClaveSegura123',
            ])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', 'clientes:password:update');

        $this->assertTrue(Hash::check('ClaveAnterior123', $cliente->fresh()->password));
    }

    public function test_una_integracion_autorizada_puede_iniciar_sesion_con_correo_y_contrasena(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Login',
            'email' => 'cliente.login@example.com',
            'password' => 'ClaveSegura123',
        ]);
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion de login',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:login'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->postJson('/api/integraciones/clientes/login', [
                'email' => 'cliente.login@example.com',
                'password' => 'ClaveSegura123',
                'device_name' => 'Postman Delivery Express',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesion correcto.')
            ->assertJsonPath('cliente.id', $cliente->id)
            ->assertJsonStructure(['access_token']);
    }

    public function test_las_integraciones_devuelven_json_aunque_postman_no_envie_accept(): void
    {
        $token = ExternalApiToken::query()->create([
            'name' => 'Integracion Google',
            'jti' => hash('sha256', Str::uuid()->toString()),
            'token_hash' => hash('sha256', Str::random(40)),
            'abilities' => ['clientes:google-login'],
            'is_active' => true,
        ]);
        $jwt = ExternalApiJwt::issue($token);
        $token->forceFill(['token_hash' => hash('sha256', $jwt)])->save();

        $this->withToken($jwt)
            ->post('/api/integraciones/clientes/google-login')
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors(['id_token']);
    }
}

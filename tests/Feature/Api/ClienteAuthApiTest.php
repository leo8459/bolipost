<?php

namespace Tests\Feature\Api;

use App\Models\Cliente;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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
}

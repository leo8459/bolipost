<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureInternalWebAccess;
use App\Http\Middleware\EnsureRoutePermission;
use App\Models\ExternalApiToken;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExternalApiTokenSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            Authenticate::class,
            EnsureInternalWebAccess::class,
            EnsureRoutePermission::class,
        ]);

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

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_la_vista_muestra_el_catalogo_con_nombres_comprensibles(): void
    {
        $this->get('/configuracion/apis')
            ->assertOk()
            ->assertSee('Nueva API')
            ->assertSee('URLs para probar')
            ->assertSee('target="_blank"', false)
            ->assertSee('/configuracion/apis?nueva=1', false)
            ->assertSee('Selecciona las APIs')
            ->assertSee('Consultar paquetes CERTI')
            ->assertSee('Consultar paquetes de contrato')
            ->assertSee('Consultar paquetes EMS')
            ->assertSee('Consultar paquetes ordinarios')
            ->assertSee('Consultar solicitudes Delivery Express')
            ->assertSee('EVENTOS SIOP')
            ->assertSee('INICIO SESION SIOP')
            ->assertSee('INICIO SESION CHASQUIAPP')
            ->assertSee('CHASQUIAPP - Paquetes asignados al cartero')
            ->assertSee('CHASQUIAPP - Asignar paquetes al cartero')
            ->assertSee('Consultar direcciones de entrega')
            ->assertSee('Actualizar direcciones de entrega')
            ->assertSee('Iniciar sesion Delivery Express con Google')
            ->assertSee('Iniciar sesion Delivery Express con usuario y contrasena')
            ->assertSee('Crear usuario Delivery Express')
            ->assertSee('Crear solicitud Delivery Express para un cliente')
            ->assertSee('Ver solicitudes Delivery Express de un cliente')
            ->assertSee('Ver todos los paquetes de solicitudes de clientes')
            ->assertSee('Crear solicitud de cliente')
            ->assertSee('seleccionadas de')
            ->assertSee('PUT / PATCH')
            ->assertSee('/api/paquetes-contactos/ems')
            ->assertSee('/api/paquetes-contactos/solicitud')
            ->assertSee('/api/paquetes-eventos')
            ->assertSee('/api/integraciones/siop/login')
            ->assertSee('/api/integraciones/chasqui/login')
            ->assertSee('/api/chasqui/paquetes-asignados')
            ->assertSee('/api/chasqui/paquetes/asignar')
            ->assertSee('/api/integraciones/clientes/google-login')
            ->assertSee('/api/integraciones/clientes/login')
            ->assertSee('/api/integraciones/clientes')
            ->assertSee('/api/integraciones/clientes/{cliente}/solicitudes')
            ->assertSee('/api/integraciones/solicitudes-clientes')
            ->assertSee('Ver ejemplo Postman')
            ->assertSee('Body &gt; raw &gt; JSON', false)
            ->assertSee('Crear credencial con APIs seleccionadas')
            ->assertDontSee('/api/mobile/login')
            ->assertDontSee('/api/carteros/asignar')
            ->assertDontSee('route:GET:/api/activity-logs', false);

        $this->get('/configuracion/apis?nueva=1')
            ->assertOk()
            ->assertSee('Cerrar ventana')
            ->assertSee('collapse show', false)
            ->assertSee('Crear nueva credencial API');
    }

    public function test_puede_crear_un_token_con_varias_apis_seleccionadas(): void
    {
        $abilities = [
            'paquetes-contactos:ems:read',
            'paquetes-contactos:solicitud:read',
            'direcciones-destino:read',
        ];

        $this->post('/configuracion/apis', [
            'name' => 'Integración operador externo',
            'abilities' => $abilities,
        ])->assertRedirect(route('configuracion.apis.index'));

        $token = ExternalApiToken::query()->firstOrFail();

        $this->assertSame('Integración operador externo', $token->name);
        $this->assertSame($abilities, $token->abilities);
        $this->assertNotEmpty($token->token_plain);
        $this->assertTrue($token->isUsable());
    }

    public function test_puede_crear_una_credencial_para_las_apis_delivery_express(): void
    {
        $abilities = [
            'clientes:create',
            'clientes:google-login',
            'clientes:login',
            'clientes:solicitudes:create',
            'clientes:solicitudes:read',
        ];

        $this->post('/configuracion/apis', [
            'name' => 'Aplicacion Delivery Express',
            'abilities' => $abilities,
        ])->assertRedirect(route('configuracion.apis.index'));

        $token = ExternalApiToken::query()->firstOrFail();

        $this->assertSame($abilities, $token->abilities);
        $this->assertNotEmpty($token->token_plain);

        $this->get(route('configuracion.apis.token-manual', $token))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_rechaza_permisos_que_no_existen_en_el_catalogo(): void
    {
        $this->post('/configuracion/apis', [
            'name' => 'Integración inválida',
            'abilities' => ['administrador-total'],
        ])->assertSessionHasErrors('abilities.0');

        $this->assertDatabaseCount('external_api_tokens', 0);
    }

    public function test_rechaza_rutas_internas_como_permisos_externos(): void
    {
        $this->post('/configuracion/apis', [
            'name' => 'Integración con ruta interna',
            'abilities' => ['route:POST:/api/mobile/login'],
        ])->assertSessionHasErrors('abilities.0');

        $this->assertDatabaseCount('external_api_tokens', 0);
    }

    public function test_puede_borrar_una_credencial_y_revocar_su_token(): void
    {
        $token = ExternalApiToken::query()->create([
            'name' => 'Credencial para borrar',
            'jti' => hash('sha256', 'credencial-para-borrar'),
            'token_hash' => hash('sha256', 'token-activo'),
            'token_plain' => 'token-activo',
            'abilities' => ['paquetes-contactos:ems:read'],
            'is_active' => true,
        ]);

        $this->delete(route('configuracion.apis.destroy', $token))
            ->assertRedirect();

        $this->assertDatabaseMissing('external_api_tokens', ['id' => $token->id]);
    }
}

<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureInternalWebAccess;
use App\Http\Middleware\EnsureRoutePermission;
use App\Models\ExternalApiToken;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
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
        $usageMigration = require database_path('migrations/2026_11_23_120000_create_external_api_usages_table.php');
        $usageMigration->up();

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
            ->assertSee('TARIFARIO EMS NACIONAL')
            ->assertSee('TARIFARIO DELIVERY EXPRESS')
            ->assertSee('EVENTOS SIOP')
            ->assertSee('INICIO SESION SIOP')
            ->assertSee('INICIO SESION CHASQUIAPP')
            ->assertSee('ENVIO DE CORREOS')
            ->assertSee('CHASQUIAPP - Paquetes asignados al cartero')
            ->assertSee('CHASQUIAPP - Asignar paquetes al cartero')
            ->assertSee('ENTREGA CARTEROS')
            ->assertSee('NOTIFICACIONES CARTEROS')
            ->assertSee('RASTREO TIEMPO REAL')
            ->assertSee('CREAR BITACORA')
            ->assertSee('VER BITACORAS')
            ->assertSee('CREAR GASOLINA')
            ->assertSee('VER GASOLINA')
            ->assertSee('SOLICITAR MANTENIMIENTOS')
            ->assertSee('VER MANTENIMIENTOS')
            ->assertSee('/api/bitacoras')
            ->assertSee('/api/gasolinas')
            ->assertSee('/api/mantenimientos')
            ->assertSee('Consultar direcciones de entrega')
            ->assertSee('Actualizar direcciones de entrega')
            ->assertSee('Iniciar sesion Delivery Express con Google')
            ->assertSee('Iniciar sesion Delivery Express con usuario y contrasena')
            ->assertSee('Crear usuario Delivery Express')
            ->assertSee('Editar usuario Delivery Express')
            ->assertSee('Actualizar contraseña de cliente')
            ->assertSee('Crear solicitud Delivery Express para un cliente')
            ->assertSee('Ver solicitudes Delivery Express de un cliente')
            ->assertSee('Ver todos los paquetes de solicitudes de clientes')
            ->assertSee('Crear solicitud de cliente')
            ->assertSee('RECOJO DE PAQUETES')
            ->assertSee('seleccionadas de')
            ->assertSee('PUT / PATCH')
            ->assertSee('/api/paquetes-contactos/ems')
            ->assertSee('/api/paquetes-contactos/solicitud')
            ->assertSee('/api/tarifarios/ems-nacional')
            ->assertSee('/api/tarifarios/delivery-express')
            ->assertSee('/api/paquetes-eventos')
            ->assertSee('/api/integraciones/siop/login')
            ->assertSee('/api/integraciones/chasqui/login')
            ->assertSee('/api/integraciones/correos/enviar')
            ->assertSee('/api/chasqui/paquetes-asignados')
            ->assertSee('/api/chasqui/paquetes/asignar')
            ->assertSee('/api/chasqui/paquetes/entregar')
            ->assertSee('/api/chasqui/notificaciones/pendientes')
            ->assertSee('/api/chasqui/location/heartbeat')
            ->assertSee('/api/integraciones/clientes/google-login')
            ->assertSee('/api/integraciones/clientes/login')
            ->assertSee('/api/integraciones/clientes')
            ->assertSee('/api/integraciones/clientes/{cliente}')
            ->assertSee('/api/integraciones/clientes/{cliente}/password')
            ->assertSee('/api/integraciones/clientes/{cliente}/solicitudes')
            ->assertSee('/api/integraciones/solicitudes-clientes')
            ->assertSee('/api/paquetes-contrato/recoger')
            ->assertSee('Ver ejemplo Postman')
            ->assertSee('Body &gt; raw &gt; JSON', false)
            ->assertSee('Crear credencial con APIs seleccionadas')
            ->assertSee('/api/mobile/login')
            ->assertDontSee('/api/carteros/asignar')
            ->assertDontSee('route:GET:/api/activity-logs', false);

        $this->get('/configuracion/apis?nueva=1')
            ->assertOk()
            ->assertSee('Cerrar ventana')
            ->assertSee('collapse show', false)
            ->assertSee('Crear nueva credencial API');
    }

    public function test_mantenimiento_aparece_una_sola_vez_con_su_metodo_principal_y_primero_por_ser_nuevo(): void
    {
        $html = $this->get('/configuracion/apis?nueva=1')
            ->assertOk()
            ->getContent();

        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $requestRows = $xpath->query('//tr[contains(@class, "js-api-route-row")][contains(., "SOLICITAR MANTENIMIENTOS")]');
        $readRows = $xpath->query('//tr[contains(@class, "js-api-route-row")][contains(., "VER MANTENIMIENTOS")]');

        $this->assertSame(1, $requestRows->length);
        $this->assertStringContainsString('POST', $requestRows->item(0)->textContent);
        $this->assertStringContainsString('/api/mantenimientos', $requestRows->item(0)->textContent);
        $this->assertSame(1, $readRows->length);
        $this->assertStringContainsString('GET', $readRows->item(0)->textContent);
        $this->assertStringContainsString('/api/mantenimientos', $readRows->item(0)->textContent);
        $this->assertStringContainsString(
            'VER MANTENIMIENTOS',
            $xpath->query('//div[@id="api-inventory-get"]//tbody/tr[1]')->item(0)->textContent
        );
        $this->assertStringContainsString(
            'SOLICITAR MANTENIMIENTOS',
            $xpath->query('//div[@id="api-inventory-post"]//tbody/tr[1]')->item(0)->textContent
        );
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

    public function test_la_credencial_del_tarifario_registra_el_endpoint_y_el_origen_de_cada_uso_exitoso(): void
    {
        $ability = 'tarifario:delivery-express:read';
        $html = $this->get(route('configuracion.apis.index', ['nueva' => 1, 'api' => $ability]))
            ->assertOk()
            ->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $checked = (new \DOMXPath($document))->query('//input[@name="abilities[]" and @checked]');
        $this->assertSame(1, $checked->length);
        $this->assertSame($ability, $checked->item(0)->getAttribute('value'));

        Schema::create('tarifario_tiktoker', function (Blueprint $table): void {
            $table->id();
        });

        $this->post(route('configuracion.apis.store'), [
            'name' => 'Integración de tarifario',
            'abilities' => [$ability],
        ])->assertRedirect(route('configuracion.apis.index'));
        $token = ExternalApiToken::query()->sole();
        $endpoint = '/api/tarifarios/delivery-express';

        $this->getJson($endpoint)->assertUnauthorized();
        $headers = ['Authorization' => 'Bearer '.$token->token_plain];
        $this->getJson('/api/tarifarios/ems-nacional', $headers)->assertForbidden();
        $this->getJson($endpoint.'?origen_id=-1', $headers)->assertUnprocessable();
        $this->assertDatabaseCount('external_api_usages', 0);
        $this->assertNull($token->fresh()->last_used_at);

        $this->getJson($endpoint.'?dato_privado=secreto-de-consulta', $headers + [
            'Origin' => 'https://portal.example',
        ])->assertOk();
        $this->getJson($endpoint, $headers + [
            'Referer' => 'https://portal.example/ruta-privada?sesion=secreto',
        ])->assertOk();
        $this->getJson($endpoint, ['X-API-Token' => $token->token_plain])->assertOk();

        $this->assertDatabaseCount('external_api_usages', 2);
        $this->assertDatabaseHas('external_api_usages', [
            'external_api_token_id' => $token->id,
            'method' => 'GET',
            'endpoint_url' => url($endpoint),
            'source_origin' => 'https://portal.example',
            'source_ip' => null,
            'request_count' => 2,
        ]);
        $this->assertDatabaseHas('external_api_usages', [
            'external_api_token_id' => $token->id,
            'source_origin' => null,
            'source_ip' => '127.0.0.1',
            'request_count' => 1,
        ]);

        $this->get(route('configuracion.apis.index'))
            ->assertOk()
            ->assertSeeText('Último uso')
            ->assertSeeText('llamadas exitosas')
            ->assertSee('<strong>3</strong>', false)
            ->assertSee('https://portal.example')
            ->assertDontSee('secreto-de-consulta')
            ->assertDontSee('ruta-privada');

        $this->delete(route('configuracion.apis.destroy', $token))->assertRedirect();
        $this->assertDatabaseCount('external_api_usages', 0);

        Schema::drop('external_api_usages');
        $this->get(route('configuracion.apis.index'))
            ->assertOk()
            ->assertSeeText('El conteo de apps se habilita al aplicar la migración');
    }

    public function test_el_panel_muestra_actividad_y_cuenta_apps_distintas_por_credencial_en_siete_dias(): void
    {
        $ability = 'tarifario:delivery-express:read';
        Route::middleware(['external.api.jwt', 'external.api.ability:'.$ability])->group(function (): void {
            Route::get('/api/uso-prueba/primera', fn () => response()->noContent());
            Route::get('/api/uso-prueba/segunda', fn () => response()->noContent());
        });

        foreach (['Integración actual', 'Integración anterior', 'Integración nueva'] as $name) {
            $this->post(route('configuracion.apis.store'), ['name' => $name, 'abilities' => [$ability]])
                ->assertRedirect();
        }

        $current = ExternalApiToken::query()->where('name', 'Integración actual')->sole();
        $previous = ExternalApiToken::query()->where('name', 'Integración anterior')->sole();
        $currentHeaders = ['Authorization' => 'Bearer '.$current->token_plain];
        $previousHeaders = ['Authorization' => 'Bearer '.$previous->token_plain];

        $this->travel(-8)->days();
        $this->getJson('/api/uso-prueba/primera', $currentHeaders + ['X-API-Client' => 'app-antigua'])->assertNoContent();
        $this->getJson('/api/uso-prueba/primera', $previousHeaders + ['Origin' => 'https://anterior.example'])->assertNoContent();
        $this->travelBack();

        $this->getJson('/api/uso-prueba/primera', $currentHeaders + ['X-API-Client' => 'PackGo'])->assertNoContent();
        $this->getJson('/api/uso-prueba/segunda', $currentHeaders + [
            'X-API-Client' => 'packgo',
            'Origin' => 'https://packgo.example',
        ])->assertNoContent();
        $this->getJson('/api/uso-prueba/primera', $currentHeaders + ['Origin' => 'https://portal.example'])->assertNoContent();
        $this->getJson('/api/uso-prueba/segunda', $currentHeaders + ['Origin' => 'https://portal.example'])->assertNoContent();
        $this->getJson('/api/uso-prueba/primera', $currentHeaders)->assertNoContent();

        $html = $this->get(route('configuracion.apis.index'))
            ->assertOk()
            ->assertSeeText('Uso y apps')
            ->assertSeeText('Ver llamadas y orígenes')
            ->assertSeeText('packgo')
            ->assertSeeText('X-API-Client: portal-clientes')
            ->getContent();

        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        foreach (['Uso reciente', 'Usada anteriormente', 'Sin uso registrado'] as $label) {
            $this->assertSame('1', $xpath->evaluate('string(//div[span="'.$label.'"]/strong)'));
        }

        $currentCell = '//tr[td/strong="Integración actual"]/td[2]';
        $previousCell = '//tr[td/strong="Integración anterior"]/td[2]';
        $newCell = '//tr[td/strong="Integración nueva"]/td[2]';
        $currentUsage = $xpath->evaluate('normalize-space('.$currentCell.')');
        $previousUsage = $xpath->evaluate('normalize-space('.$previousCell.')');
        $newUsage = $xpath->evaluate('normalize-space('.$newCell.')');

        $this->assertStringContainsString('Uso reciente 2 apps identificadas en 7 días', $currentUsage);
        $this->assertStringContainsString('1 IP sin app identificada', $currentUsage);
        $this->assertStringContainsString('6 llamadas exitosas en total', $currentUsage);
        $this->assertStringContainsString('Usada anteriormente 0 apps identificadas en 7 días', $previousUsage);
        $this->assertStringContainsString('1 llamada exitosa en total', $previousUsage);
        $this->assertStringContainsString('Sin uso registrado 0 apps identificadas en 7 días', $newUsage);

        Schema::drop('external_api_usages');
        $this->get(route('configuracion.apis.index'))
            ->assertOk()
            ->assertSeeText('Sin datos por aplicación')
            ->assertSeeText('Último uso');
    }

    public function test_puede_crear_una_credencial_con_permisos_separados_de_bitacora(): void
    {
        $abilities = ['bitacoras:create', 'bitacoras:read'];

        $this->post('/configuracion/apis', [
            'name' => 'Integracion de bitacoras',
            'abilities' => $abilities,
        ])->assertRedirect(route('configuracion.apis.index'));

        $token = ExternalApiToken::query()->firstOrFail();

        $this->assertSame($abilities, $token->abilities);
        $this->assertNotEmpty($token->token_plain);
    }

    public function test_puede_crear_una_credencial_con_permisos_separados_de_gasolina(): void
    {
        $abilities = ['gasolinas:create', 'gasolinas:read'];

        $this->post('/configuracion/apis', [
            'name' => 'Integracion de gasolina',
            'abilities' => $abilities,
        ])->assertRedirect(route('configuracion.apis.index'));

        $token = ExternalApiToken::query()->firstOrFail();

        $this->assertSame($abilities, $token->abilities);
        $this->assertNotEmpty($token->token_plain);
    }

    public function test_puede_crear_una_credencial_con_permisos_separados_de_mantenimiento(): void
    {
        $abilities = ['mantenimientos:create', 'mantenimientos:read'];

        $this->post('/configuracion/apis', [
            'name' => 'Integracion de mantenimientos',
            'abilities' => $abilities,
        ])->assertRedirect(route('configuracion.apis.index'));

        $token = ExternalApiToken::query()->firstOrFail();

        $this->assertSame($abilities, $token->abilities);
        $this->assertNotEmpty($token->token_plain);
    }

    public function test_puede_crear_una_credencial_para_las_apis_delivery_express(): void
    {
        $abilities = [
            'clientes:create',
            'clientes:update',
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

<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureInternalWebAccess;
use App\Http\Middleware\EnsureRoutePermission;
use App\Models\ExternalApiToken;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function test_mantenimiento_aparece_una_vez_por_metodo_y_antes_de_los_tarifarios_mas_antiguos(): void
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
        $getRows = $xpath->query('//div[@id="api-inventory-get"]//tbody')->item(0)->textContent;
        $this->assertLessThan(strpos($getRows, 'TARIFARIO EMS NACIONAL'), strpos($getRows, 'VER MANTENIMIENTOS'));
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

    public function test_la_credencial_del_inventario_consulta_los_mismos_contratos_y_sla_de_la_vista(): void
    {
        $ability = 'indicadores:contratos:inventario:read';
        $endpoint = '/api/indicadores/contratos/inventario';
        $this->get(route('configuracion.apis.index'))
            ->assertOk()
            ->assertSee('Credencial de INDICADORES CONTRATOS');

        $html = $this->get(route('configuracion.apis.index', ['nueva' => 1, 'api' => $ability]))
            ->assertOk()
            ->assertSee('INDICADORES CONTRATOS')
            ->assertSee($endpoint)
            ->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $checked = (new \DOMXPath($document))->query('//input[@name="abilities[]" and @checked]');
        $this->assertSame(1, $checked->length);
        $this->assertSame($ability, $checked->item(0)->getAttribute('value'));

        $this->post(route('configuracion.apis.store'), ['name' => 'Inventario', 'abilities' => [$ability]])
            ->assertRedirect(route('configuracion.apis.index'));
        $token = ExternalApiToken::query()->sole();
        $headers = ['Authorization' => 'Bearer '.$token->token_plain];
        $this->getJson($endpoint)->assertUnauthorized();
        $this->getJson($endpoint.'?per_page=101', $headers)->assertUnprocessable()->assertJsonValidationErrors('per_page');

        $this->post(route('configuracion.apis.store'), ['name' => 'Tarifario', 'abilities' => ['tarifario:ems-nacional:read']])
            ->assertRedirect(route('configuracion.apis.index'));
        $tarifario = ExternalApiToken::query()->where('name', 'Tarifario')->sole();
        $this->getJson($endpoint, ['Authorization' => 'Bearer '.$tarifario->token_plain])
            ->assertForbidden()
            ->assertJsonPath('permiso_requerido', $ability);

        foreach (['estados' => 'nombre_estado', 'empresa' => 'nombre', 'users' => 'name'] as $name => $column) {
            Schema::create($name, function (Blueprint $table) use ($column): void {
                $table->id();
                $table->string($column);
            });
        }
        Schema::create('paquetes_contrato', function (Blueprint $table): void {
            $table->id();
            foreach (['codigo', 'cod_especial', 'origen', 'destino', 'provincia', 'nombre_r', 'nombre_d'] as $column) {
                $table->string($column)->nullable();
            }
            $table->unsignedBigInteger('estados_id')->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('peso', 10, 2);
            $table->timestamp('fecha_recojo')->nullable();
            $table->timestamps();
        });
        Schema::create('eventos_contrato', function (Blueprint $table): void {
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->timestamp('created_at');
        });

        $this->travelTo(Carbon::parse('2026-11-26 12:00:00'));
        DB::table('estados')->insert([['id' => 1, 'nombre_estado' => 'ALMACEN'], ['id' => 2, 'nombre_estado' => 'ENTREGADO']]);
        DB::table('empresa')->insert(['id' => 1, 'nombre' => 'Empresa de prueba']);
        DB::table('users')->insert(['id' => 1, 'name' => 'Operador de prueba']);

        $contrato = [
            'origen' => 'LA PAZ',
            'destino' => 'COCHABAMBA',
            'nombre_r' => 'Remitente de prueba',
            'nombre_d' => 'Destinatario de prueba',
            'empresa_id' => 1,
            'user_id' => 1,
            'peso' => 1.50,
            'updated_at' => now(),
        ];
        DB::table('paquetes_contrato')->insert([
            ['codigo' => 'CONTRATO-EN-PLAZO', 'estados_id' => 1] + $contrato,
            ['codigo' => 'CONTRATO-ATRASO', 'estados_id' => 1] + $contrato,
            ['codigo' => 'CONTRATO-SIN-DATOS', 'estados_id' => null, 'destino' => 'LA PAZ'] + $contrato,
            ['codigo' => 'CONTRATO-ENTREGADO', 'estados_id' => 2, 'destino' => 'LA PAZ'] + $contrato,
        ]);
        DB::table('eventos_contrato')->insert([
            ['codigo' => 'CONTRATO-EN-PLAZO', 'evento_id' => 295, 'created_at' => now()->subHours(8)],
            ['codigo' => 'CONTRATO-ATRASO', 'evento_id' => 295, 'created_at' => now()->subDay()->subMinutes(30)],
        ]);

        $filters = '?q=CONTRATO&origen=LA%20PAZ&destino=LA%20PAZ';
        $this->get('/indicadores/contratos/inventario'.$filters.'&sla=atraso')
            ->assertOk()
            ->assertSeeText('CONTRATO-ATRASO')
            ->assertDontSeeText('CONTRATO-EN-PLAZO')
            ->assertDontSeeText('CONTRATO-SIN-DATOS')
            ->assertDontSeeText('CONTRATO-ENTREGADO');

        $firstPage = $this->getJson($endpoint.$filters.'&per_page=1', $headers)
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('last_page', 3)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.codigo', 'CONTRATO-SIN-DATOS')
            ->assertJsonPath('data.0.sla_estado', 'sin_datos')
            ->assertJsonPath('opciones_filtros.destino', ['LA PAZ']);

        $this->getJson($firstPage->json('next_page_url'), $headers)
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('data.0.codigo', 'CONTRATO-ATRASO');

        $this->getJson($endpoint.$filters.'&sla=atraso', $headers)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.codigo', 'CONTRATO-ATRASO')
            ->assertJsonPath('data.0.remitente', 'Remitente de prueba')
            ->assertJsonPath('data.0.destinatario', 'Destinatario de prueba')
            ->assertJsonPath('data.0.peso', 1.5)
            ->assertJsonPath('data.0.empresa', 'Empresa de prueba')
            ->assertJsonPath('data.0.usuario', 'Operador de prueba')
            ->assertJsonPath('data.0.sla_texto', '1d 0h')
            ->assertJsonPath('data.0.sla_estado', 'atraso')
            ->assertJsonPath('sla_resumen', ['en_plazo' => 1, 'atraso' => 1, 'rezago' => 0, 'sin_datos' => 1]);
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

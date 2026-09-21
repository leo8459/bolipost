<?php

namespace Tests\Feature;

use App\Http\Controllers\RecojoController;
use App\Support\AclPermissionRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MisPaquetesContratoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
            $table->timestamps();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('estados_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('codigo_madre')->nullable();
            $table->string('cod_especial')->nullable();
            $table->string('origen')->nullable();
            $table->string('destino')->nullable();
            $table->string('destino_registrado')->nullable();
            $table->string('nombre_r')->nullable();
            $table->string('nombre_d')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->timestamps();
        });

        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'ADMITIDO'],
            ['id' => 2, 'nombre_estado' => 'ENTREGADO'],
        ]);

        DB::table('paquetes_contrato')->insert([
            [
                'id' => 1,
                'user_id' => 10,
                'estados_id' => 1,
                'codigo' => 'MIO-001',
                'origen' => 'LA PAZ',
                'destino' => 'COCHABAMBA',
                'nombre_d' => 'Ana',
                'created_at' => '2026-09-20 10:00:00',
                'updated_at' => '2026-09-20 10:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 10,
                'estados_id' => 2,
                'codigo' => 'MIO-002',
                'origen' => 'LA PAZ',
                'destino' => 'TARIJA',
                'nombre_d' => 'Luis',
                'created_at' => '2026-09-21 11:00:00',
                'updated_at' => '2026-09-21 11:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 20,
                'estados_id' => 1,
                'codigo' => 'AJENO-001',
                'origen' => 'ORURO',
                'destino' => 'LA PAZ',
                'nombre_d' => 'Pedro',
                'created_at' => '2026-09-21 12:00:00',
                'updated_at' => '2026-09-21 12:00:00',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('estados');

        parent::tearDown();
    }

    public function test_lista_unicamente_los_paquetes_generados_por_el_usuario_autenticado(): void
    {
        $request = Request::create('/paquetes-contrato/mis-paquetes', 'GET');
        $request->setUserResolver(fn () => (object) ['id' => 10]);

        $view = app(RecojoController::class)->misPaquetes($request);

        $this->assertSame('paquetes_contrato.mis-paquetes', $view->getName());
        $this->assertSame(['MIO-002', 'MIO-001'], $view->getData()['paquetes']->pluck('codigo')->all());
        $this->assertNotContains('AJENO-001', $view->getData()['paquetes']->pluck('codigo')->all());
    }

    public function test_aplica_busqueda_estado_y_fechas_sin_exponer_paquetes_ajenos(): void
    {
        $request = Request::create('/paquetes-contrato/mis-paquetes', 'GET', [
            'q' => 'Luis',
            'estado_id' => 2,
            'fecha_desde' => '2026-09-21',
            'fecha_hasta' => '2026-09-21',
        ]);
        $request->setUserResolver(fn () => (object) ['id' => 10]);

        $paquetes = app(RecojoController::class)
            ->misPaquetes($request)
            ->getData()['paquetes'];

        $this->assertSame(['MIO-002'], $paquetes->pluck('codigo')->all());
    }

    public function test_menu_y_permisos_incluyen_la_nueva_vista(): void
    {
        $menuSolicitud = collect(config('adminlte.menu'))
            ->flatMap(fn ($item) => is_array($item) ? ($item['submenu'] ?? []) : [])
            ->first(fn ($item) => is_array($item)
                && ($item['text'] ?? null) === 'Solicitud de Correspondencia');

        $this->assertNotNull($menuSolicitud);
        $this->assertTrue(collect($menuSolicitud['submenu'] ?? [])->contains(
            fn ($item) => ($item['url'] ?? null) === 'paquetes-contrato/mis-paquetes'
                && ($item['text'] ?? null) === 'Todos mis paquetes'
        ));
        $this->assertContains(
            'paquetes-contrato.index',
            AclPermissionRegistry::authorizationPermissionsForRouteAccess('paquetes-contrato.mis-paquetes')
        );
    }
}

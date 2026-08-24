<?php

namespace Tests\Feature;

use App\Http\Controllers\AreaContratosController;
use App\Support\AclPermissionRegistry;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class EmpresaGuiasTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
            $table->timestamps();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('estados_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('codigo_madre')->nullable();
            $table->string('cod_especial')->nullable();
            $table->string('nombre_r')->nullable();
            $table->string('nombre_d')->nullable();
            $table->string('origen')->nullable();
            $table->string('destino')->nullable();
            $table->unsignedInteger('cantidad')->nullable();
            $table->decimal('peso', 12, 3)->nullable();
            $table->decimal('precio', 12, 2)->nullable();
            $table->timestamps();
        });

        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'Empresa Uno', 'sigla' => 'E1', 'codigo_cliente' => '001'],
            ['id' => 2, 'nombre' => 'Empresa Dos', 'sigla' => 'E2', 'codigo_cliente' => '002'],
        ]);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Usuario antiguo', 'empresa_id' => 1],
            ['id' => 2, 'name' => 'Usuario interno', 'empresa_id' => null],
        ]);
        DB::table('estados')->insert(['id' => 1, 'nombre_estado' => 'ADMITIDO']);

        DB::table('paquetes_contrato')->insert([
            [
                'id' => 1,
                'user_id' => 2,
                'empresa_id' => 1,
                'estados_id' => 1,
                'codigo' => 'GUIA-DIRECTA',
                'created_at' => '2026-08-10 10:00:00',
                'updated_at' => '2026-08-10 10:00:00',
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'empresa_id' => null,
                'estados_id' => 1,
                'codigo' => 'GUIA-ANTIGUA',
                'created_at' => '2026-08-15 10:00:00',
                'updated_at' => '2026-08-15 10:00:00',
            ],
            [
                'id' => 3,
                'user_id' => 2,
                'empresa_id' => null,
                'estados_id' => 1,
                'codigo' => 'GUIA-INTERNA',
                'created_at' => '2026-08-15 10:00:00',
                'updated_at' => '2026-08-15 10:00:00',
            ],
            [
                'id' => 4,
                'user_id' => 2,
                'empresa_id' => 2,
                'estados_id' => 1,
                'codigo' => 'GUIA-OTRA',
                'created_at' => '2026-08-20 10:00:00',
                'updated_at' => '2026-08-20 10:00:00',
            ],
            [
                'id' => 5,
                'user_id' => 2,
                'empresa_id' => 1,
                'estados_id' => 0,
                'codigo' => 'GUIA-ESTADO-CERO',
                'created_at' => '2026-08-22 10:00:00',
                'updated_at' => '2026-08-22 10:00:00',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('estados');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresa');

        parent::tearDown();
    }

    public function test_lista_solo_guias_empresariales_incluyendo_registros_antiguos(): void
    {
        $view = app(AreaContratosController::class)->guiasEmpresa(
            Request::create('/empresa/guias', 'GET')
        );

        $this->assertSame(
            ['GUIA-OTRA', 'GUIA-ANTIGUA', 'GUIA-DIRECTA'],
            $view->getData()['guias']->pluck('codigo')->all()
        );
    }

    public function test_filtra_por_empresa_y_rango_de_fechas_inclusivo(): void
    {
        $view = app(AreaContratosController::class)->guiasEmpresa(
            Request::create('/empresa/guias', 'GET', [
                'empresa_id' => 1,
                'fecha_desde' => '2026-08-15',
                'fecha_hasta' => '2026-08-15',
            ])
        );

        $this->assertSame(
            ['GUIA-ANTIGUA'],
            $view->getData()['guias']->pluck('codigo')->all()
        );
    }

    public function test_reporte_excel_respeta_filtros_y_excluye_estado_cero(): void
    {
        Carbon::setTestNow('2026-08-24 12:00:00');
        Excel::fake();

        try {
            app(AreaContratosController::class)->exportGuiasEmpresaExcel(
                Request::create('/empresa/guias/reporte-excel', 'GET', [
                    'empresa_id' => 1,
                    'fecha_desde' => '2026-08-01',
                    'fecha_hasta' => '2026-08-31',
                ])
            );

            Excel::assertDownloaded('reporte-guias-empresa-20260824-120000.xlsx', function ($export) {
                return $export->collection()->pluck('codigo')->all() === [
                    'GUIA-ANTIGUA',
                    'GUIA-DIRECTA',
                ];
            });
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_menu_contiene_empresa_y_guias_empresa(): void
    {
        $empresaMenu = collect(config('adminlte.menu'))
            ->first(fn ($item) => is_array($item)
                && ($item['text'] ?? null) === 'Empresa'
                && collect($item['submenu'] ?? [])->contains(
                    fn ($child) => ($child['url'] ?? null) === 'empresa/guias'
                ));

        $this->assertNotNull($empresaMenu);
        $this->assertContains(
            'area-contratos.todos',
            AclPermissionRegistry::authorizationPermissionsForRouteAccess('empresa.guias.index')
        );
        $this->assertContains(
            'feature.empresa.guias.index.export',
            AclPermissionRegistry::authorizationPermissionsForRouteAccess('empresa.guias.excel')
        );
        $this->assertContains(
            'feature.empresa.guias.index.view',
            AclPermissionRegistry::authorizationPermissionsForRouteAccess('empresa.guias.rastreo')
        );
        $this->assertContains(
            'feature.empresa.guias.index.print',
            AclPermissionRegistry::authorizationPermissionsForRouteAccess('paquetes-contrato.reporte')
        );

        foreach (['search', 'export', 'view', 'print'] as $action) {
            $this->assertContains(
                'feature.empresa.guias.index.'.$action,
                config('acl.custom_permissions')
            );
        }
    }
}

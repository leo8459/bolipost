<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class FinancialReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.facturacion_reports.base_url' => 'https://safe.example.test/api/factura-venta',
            'services.facturacion_reports.token' => 'test-token',
        ]);

        Schema::create('app_settings', function ($table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_invoiced_contract_report_shows_only_contract_services(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/detalle')) {
                return Http::response(json_encode([
                    'servicio' => [
                        'rows' => [[
                            'ventaId' => 'venta-contrato-1',
                            'detalleId' => 10,
                            'descripcion' => 'Factura de contrato',
                            'codigoOrden' => 'orden-contrato-1',
                            'codigoSeguimiento' => 'BO-CONTRATO-1',
                            'fecha' => '2026-08-19',
                            'totalLinea' => 150,
                        ]],
                    ],
                ]), 200);
            }

            return Http::response(json_encode([
                'servicios' => [
                    [
                        'servicio' => 'Servicio Internacional',
                        'cantidadVentas' => 8,
                        'cantidadDetalles' => 8,
                        'totalCantidad' => 8,
                        'totalMonto' => 800,
                        'ultimaFecha' => '2026-08-18',
                    ],
                    [
                        'servicio' => 'Servicio Contratos por concepto de pago de servicios de courier correspondiente',
                        'cantidadVentas' => 3,
                        'cantidadDetalles' => 4,
                        'totalCantidad' => 5,
                        'totalMonto' => 150,
                        'ultimaFecha' => '2026-08-19',
                    ],
                ],
            ]), 200);
        });

        $response = $this->withoutMiddleware()->get(route('dashboard.conciliacion.facturado', [
            'mes' => 8,
            'anio' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Servicio Contratos')
            ->assertSee('Facturas del servicio de contratos')
            ->assertSee('3 ventas')
            ->assertSee('BO-CONTRATO-1')
            ->assertDontSee('Servicio Internacional')
            ->assertViewHas('soloContratos', true)
            ->assertViewHas('serviceOptions', fn ($options) => $options->count() === 1
                && $options->first() === 'Servicio Contratos por concepto de pago de servicios de courier correspondiente'
            )
            ->assertViewHas('services', fn ($services) => $services->count() === 1
                && $services->first()['cantidadVentas'] === 3.0
                && $services->first()['totalMonto'] === 150.0
            )
            ->assertViewHas('serviceGroups', fn ($groups) => $groups->count() === 1
                && $groups->first()['servicio'] === 'Servicio Contratos'
            )
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 1
                && $rows->first()['ventaId'] === 'venta-contrato-1'
            )
            ->assertViewHas('summary', fn ($summary) => $summary['cantidadServicios'] === 1
                && $summary['cantidadVentas'] === 3.0
                && $summary['totalMonto'] === 150.0
            );
    }

    public function test_cashier_flow_reuses_cached_summary_and_can_refresh_it(): void
    {
        $remoteCalls = 0;
        Http::fake(function () use (&$remoteCalls) {
            $remoteCalls++;

            return Http::response(['servicios' => [[
                'servicio' => 'Servicio Internacional',
                'cantidadVentas' => $remoteCalls,
                'cantidadDetalles' => $remoteCalls,
                'totalCantidad' => $remoteCalls,
                'totalMonto' => 10 * $remoteCalls,
            ]]], 200);
        });

        $url = route('dashboard.financiera.flujo-cajero', ['mes' => 8, 'anio' => 2026]);
        $this->withoutMiddleware()->get($url)->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['cantidadVentas'] === 1.0);
        $this->withoutMiddleware()->get($url)->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['cantidadVentas'] === 1.0);
        $this->assertSame(1, $remoteCalls);

        $this->withoutMiddleware()->get(route('dashboard.financiera.flujo-cajero', [
            'mes' => 8, 'anio' => 2026, 'actualizar' => 1,
        ]))->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['cantidadVentas'] === 2.0);
        $this->assertSame(2, $remoteCalls);
    }

    public function test_dashboard_income_uses_every_cashier_flow_service_and_collected_receivables(): void
    {
        Schema::create('cashier_flow_receivable_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('movement_key');
            $table->string('servicio');
            $table->integer('anio');
            $table->integer('mes');
            $table->decimal('monto', 12, 2);
            $table->boolean('cobro_activo');
            $table->string('facturado_por_id');
            $table->string('facturado_por_nombre');
            $table->string('facturado_por_email');
            $table->string('facturado_por_alias');
            $table->decimal('cantidad_paquetes', 12, 2);
            $table->timestamp('cobrado_at');
        });
        DB::table('cashier_flow_receivable_movements')->insert([
            'movement_key' => str_repeat('a', 64),
            'servicio' => 'Servicio Contratos por concepto de pago de servicios de courier correspondiente',
            'anio' => 2026,
            'mes' => 8,
            'monto' => 25,
            'cobro_activo' => true,
            'facturado_por_id' => '1',
            'facturado_por_nombre' => 'Cajero de prueba',
            'facturado_por_email' => 'cajero@example.test',
            'facturado_por_alias' => 'cajero',
            'cantidad_paquetes' => 1,
            'cobrado_at' => '2026-08-20 10:00:00',
        ]);

        Http::fake(fn () => Http::response(['servicios' => [
            ['servicio' => 'Servicio EMS Nacional', 'totalMonto' => 100, 'totalMontoVendido' => 90, 'porRegionales' => [
                ['regional' => 'LA PAZ', 'totalMontoVendido' => 50],
                ['regional' => 'ORURO', 'totalMontoVendido' => 40],
            ]],
            ['servicio' => 'Servicio EMS Local Cobertura 1', 'totalMonto' => 30, 'totalMontoVendido' => 30, 'porRegionales' => [
                ['regional' => 'LA PAZ', 'totalMontoVendido' => 30],
            ]],
            ['servicio' => 'Servicio Contratos por concepto de pago de servicios de courier correspondiente', 'totalMonto' => 200],
            ['servicio' => 'Servicio Certificadas', 'totalMonto' => 50, 'porRegionales' => [
                ['regional' => 'LA PAZ', 'totalMonto' => 50],
            ]],
            ['servicio' => 'Servicio Ordinarias', 'totalMonto' => 40, 'porRegionales' => [
                ['regional' => 'ORURO', 'totalMonto' => 40],
            ]],
        ]], 200));

        $this->withoutMiddleware()
            ->getJson(route('dashboard.financiera.flujo-cajero.dashboard-amounts', [
                'mes' => 8,
                'anio' => 2026,
                'departamentos' => ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'],
            ]))
            ->assertOk()
            ->assertJsonPath('importe_total', 235)
            ->assertJsonFragment(['servicio' => 'Servicio EMS Nacional', 'importe' => 120])
            ->assertJsonFragment(['servicio' => 'Servicio Contratos', 'importe' => 25])
            ->assertJsonFragment(['servicio' => 'Servicio Internacional', 'importe' => 90])
            ->assertJsonFragment(['departamento' => 'LA PAZ', 'importe' => 130])
            ->assertJsonFragment(['departamento' => 'ORURO', 'importe' => 80])
            ->assertJsonFragment(['departamento' => 'SIN REGIONAL ASIGNADA', 'importe' => 25]);

        Http::assertSentCount(1);
    }

    public function test_dashboard_income_adds_selected_departments_across_selected_months(): void
    {
        Http::fake(function (Request $request) {
            $month = (int) $request['mes'];
            $department = (string) $request['regionalConteo'];
            $amount = match ($department.'|'.$month) {
                'LA PAZ|7' => 10,
                'LA PAZ|8' => 20,
                'ORURO|7' => 30,
                'ORURO|8' => 40,
                default => 0,
            };

            return Http::response(['servicios' => [[
                'servicio' => 'Servicio EMS Nacional',
                'totalMonto' => $amount,
                'totalMontoVendido' => $amount,
                'porRegionales' => [[
                    'regional' => $department,
                    'totalMonto' => $amount,
                    'totalMontoVendido' => $amount,
                ]],
            ]]], 200);
        });

        $this->withoutMiddleware()
            ->getJson(route('dashboard.financiera.flujo-cajero.dashboard-amounts', [
                'meses' => [7, 8],
                'anio' => 2026,
                'departamentos' => ['LA PAZ', 'ORURO'],
            ]))
            ->assertOk()
            ->assertJsonPath('meses', [7, 8])
            ->assertJsonPath('departamentos', ['LA PAZ', 'ORURO'])
            ->assertJsonPath('importe_total', 100)
            ->assertJsonFragment(['departamento' => 'LA PAZ', 'importe' => 30])
            ->assertJsonFragment(['departamento' => 'ORURO', 'importe' => 70])
            ->assertJsonFragment(['servicio' => 'Servicio EMS Nacional', 'importe' => 100]);

        Http::assertSentCount(4);
    }

    public function test_cashier_modal_reuses_cached_details_for_multiple_services(): void
    {
        Http::fake(fn (Request $request) => Http::response(['servicio' => [
            'cantidadVentas' => 1,
            'cantidadDetalles' => 1,
            'totalCantidad' => 1,
            'totalMonto' => 25,
            'rows' => [[
                'ventaId' => $request['servicio'],
                'descripcion' => 'Detalle de prueba',
                'fecha' => '2026-08-18',
                'totalLinea' => 25,
            ]],
        ]], 200));

        $url = route('dashboard.financiera.ventas-servicios.detalle', [
            'servicios' => ['Servicio A', 'Servicio B'],
            'meses' => [8],
            'anio' => 2026,
            'modal' => 1,
        ]);

        $this->withoutMiddleware()->get($url)->assertOk()
            ->assertHeader('X-Flow-Detail-Fragment', '1')
            ->assertSee('Movimientos individuales')
            ->assertSee('Servicio A')
            ->assertSee('Servicio B');
        $this->withoutMiddleware()->get($url)->assertOk()
            ->assertHeader('X-Flow-Detail-Fragment', '1');

        Http::assertSentCount(2);

        $this->withoutMiddleware()->get($url.'&actualizar=1')->assertOk()
            ->assertHeader('X-Flow-Detail-Fragment', '1');
        Http::assertSentCount(4);
    }

    public function test_cashier_flow_caches_multiple_months_after_parallel_fetch(): void
    {
        Http::fake(fn (Request $request) => Http::response(['servicios' => [[
            'servicio' => 'Servicio Internacional',
            'cantidadVentas' => (int) $request['mes'],
            'cantidadDetalles' => (int) $request['mes'],
            'totalCantidad' => (int) $request['mes'],
            'totalMonto' => 10 * (int) $request['mes'],
        ]]], 200));

        $url = route('dashboard.financiera.flujo-cajero', [
            'meses' => [7, 8], 'anio' => 2026,
        ]);
        $this->withoutMiddleware()->get($url)->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['cantidadVentas'] === 15.0);
        $this->withoutMiddleware()->get($url)->assertOk()
            ->assertViewHas('summary', fn (array $summary): bool => $summary['cantidadVentas'] === 15.0);

        Http::assertSentCount(2);
    }

    public function test_cashier_flow_consolidates_totals_and_excludes_contracts(): void
    {
        Http::fake(function (Request $request) {
            $isJuly = (int) $request['mes'] === 7;

            return Http::response(json_encode([
                'servicios' => [[
                    'servicio' => 'Servicio Internacional',
                    'cantidadVentas' => 4,
                    'cantidadDetalles' => 4,
                    'totalCantidad' => 4,
                    'totalMonto' => 200,
                    'ultimaFecha' => $isJuly ? '2026-07-20' : '2026-08-20',
                    'porRegionales' => [
                        [
                            'regional' => 'LA PAZ',
                            'codigosSucursal' => ['0'],
                            'cantidadVentas' => 2,
                            'cantidadDetalles' => 2,
                            'totalCantidad' => 2,
                            'totalMonto' => $isJuly ? 100 : 125,
                        ],
                        [
                            'regional' => 'COCHABAMBA',
                            'codigosSucursal' => ['2'],
                            'cantidadVentas' => 1,
                            'cantidadDetalles' => 1,
                            'totalCantidad' => 1,
                            'totalMonto' => $isJuly ? 50 : 25,
                        ],
                    ],
                    'porPersonas' => [
                        [
                            'usuarioId' => '10',
                            'usuarioNombre' => 'CAJERO UNO',
                            'usuarioEmail' => 'cajero1@example.test',
                            'usuarioAlias' => 'cajero1',
                            'usuarioCarnet' => '1000',
                            'cantidadVentas' => 2,
                            'cantidadDetalles' => 2,
                            'totalCantidad' => 2,
                            'totalMonto' => $isJuly ? 100 : 125,
                        ],
                        [
                            'usuarioId' => '20',
                            'usuarioNombre' => 'CAJERO DOS',
                            'usuarioEmail' => 'cajero2@example.test',
                            'usuarioAlias' => 'cajero2',
                            'usuarioCarnet' => '2000',
                            'cantidadVentas' => 1,
                            'cantidadDetalles' => 1,
                            'totalCantidad' => 1,
                            'totalMonto' => $isJuly ? 50 : 25,
                        ],
                        [
                            'usuarioId' => '99',
                            'usuarioNombre' => 'EDGAR JAVIER GIRONDA CHIRI',
                            'usuarioEmail' => 'edgar.girona@correos.gob.bo',
                            'usuarioAlias' => 'edgargironda',
                            'usuarioCarnet' => '4850032',
                            'cantidadVentas' => 1,
                            'cantidadDetalles' => 1,
                            'totalCantidad' => 1,
                            'totalMonto' => 50,
                        ],
                    ],
                ], [
                    'servicio' => 'Servicio Contratos por concepto de pago de servicios de courier correspondiente',
                    'cantidadVentas' => 10,
                    'cantidadDetalles' => 10,
                    'totalCantidad' => 10,
                    'totalMonto' => 1000,
                    'porRegionales' => [[
                        'regional' => 'LA PAZ',
                        'codigosSucursal' => ['0'],
                        'cantidadVentas' => 10,
                        'cantidadDetalles' => 10,
                        'totalCantidad' => 10,
                        'totalMonto' => 1000,
                    ]],
                    'porPersonas' => [[
                        'usuarioId' => '30',
                        'usuarioNombre' => 'CAJERO CONTRATOS',
                        'usuarioAlias' => 'contratos',
                        'cantidadVentas' => 10,
                        'cantidadDetalles' => 10,
                        'totalCantidad' => 10,
                        'totalMonto' => 1000,
                    ]],
                ]],
            ]), 200);
        });

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.flujo-cajero', [
            'meses' => [7, 8],
            'anio' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Flujo de cajero')
            ->assertSee('Reporte ejecutivo resumido')
            ->assertSee('Cajero con mayor ingreso')
            ->assertSee('Filtrando datos')
            ->assertSee('Ventas realizadas')
            ->assertSee('Cantidad de paquetes')
            ->assertSee('Ingresos de ventanilla')
            ->assertSee('Facturación por cajero')
            ->assertSee('Regional / departamento')
            ->assertSee('Generando reporte')
            ->assertSee('data-report-download', false)
            ->assertSee('CAJERO UNO')
            ->assertSee('Descargar reporte ejecutivo')
            ->assertSee('Los contratos se excluyen')
            ->assertDontSee('CAJERO CONTRATOS')
            ->assertDontSee('EDGAR JAVIER GIRONDA CHIRI')
            ->assertDontSee('edgargironda')
            ->assertDontSee('4850032')
            ->assertDontSee('Servicio Contratos por concepto')
            ->assertDontSee('Facturación por departamento')
            ->assertViewHas('selectedServices', ['Servicio Internacional'])
            ->assertViewHas('serviceOptions', fn ($options) => $options->all() === ['Servicio Internacional'])
            ->assertViewHas('cashierRows', fn ($rows) => $rows->count() === 2
                && $rows->firstWhere('usuarioId', '10')['cantidadVentas'] === 4.0
                && $rows->firstWhere('usuarioId', '10')['totalMonto'] === 225.0
            )
            ->assertViewHas('summary', fn ($summary) => $summary['cantidadVentas'] === 6.0
                && $summary['totalMonto'] === 300.0
            );

        Http::assertSentCount(2);
    }

    public function test_cashier_flow_filters_totals_and_cashiers_by_department(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('ciudad')->nullable();
            $table->json('regionales')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
        DB::table('users')->insert([
            ['id' => 10, 'name' => 'CAJERO LA PAZ', 'alias' => 'cajerolp', 'email' => 'lapaz@example.test', 'password' => 'secret', 'ciudad' => 'LA PAZ', 'regionales' => json_encode(['LA PAZ'])],
            ['id' => 20, 'name' => 'CAJERO SANTA CRUZ', 'alias' => 'cajeroscz', 'email' => 'santacruz@example.test', 'password' => 'secret', 'ciudad' => 'SANTA CRUZ', 'regionales' => json_encode(['SANTA CRUZ'])],
            ['id' => 99, 'name' => 'EDGAR JAVIER GIRONDA CHIRI', 'alias' => 'edgargironda', 'email' => 'edgar.girona@correos.gob.bo', 'password' => 'secret', 'ciudad' => 'LA PAZ', 'regionales' => json_encode(['LA PAZ'])],
        ]);

        Http::fake([
            'safe.example.test/*' => Http::response(json_encode([
                'servicios' => [[
                    'servicio' => 'Servicio Internacional',
                    'cantidadVentas' => 7,
                    'cantidadDetalles' => 7,
                    'totalCantidad' => 7,
                    'totalMonto' => 700,
                    'porRegionales' => [
                        ['regional' => 'LA PAZ', 'codigosSucursal' => ['0'], 'cantidadVentas' => 4, 'cantidadDetalles' => 4, 'totalCantidad' => 4, 'totalMonto' => 400],
                        ['regional' => 'SANTA CRUZ DE LA SIERRA', 'codigosSucursal' => ['1'], 'cantidadVentas' => 3, 'cantidadDetalles' => 3, 'totalCantidad' => 3, 'totalMonto' => 300],
                        ['regional' => 'COCHABAMBA', 'codigosSucursal' => ['2'], 'cantidadVentas' => 0, 'cantidadDetalles' => 0, 'totalCantidad' => 0, 'totalMonto' => 0],
                        ['regional' => 'ORURO', 'codigosSucursal' => ['3'], 'cantidadVentas' => 0, 'cantidadDetalles' => 0, 'totalCantidad' => 0, 'totalMonto' => 0],
                        ['regional' => 'POTOSI', 'codigosSucursal' => ['4'], 'cantidadVentas' => 0, 'cantidadDetalles' => 0, 'totalCantidad' => 0, 'totalMonto' => 0],
                        ['regional' => 'CHUQUISACA', 'codigosSucursal' => ['5'], 'cantidadVentas' => 0, 'cantidadDetalles' => 0, 'totalCantidad' => 0, 'totalMonto' => 0],
                        ['regional' => 'TARIJA', 'codigosSucursal' => ['6'], 'cantidadVentas' => 0, 'cantidadDetalles' => 0, 'totalCantidad' => 0, 'totalMonto' => 0],
                        ['regional' => 'PANDO', 'codigosSucursal' => ['7'], 'cantidadVentas' => 0, 'cantidadDetalles' => 0, 'totalCantidad' => 0, 'totalMonto' => 0],
                        ['regional' => 'BENI', 'codigosSucursal' => ['8'], 'cantidadVentas' => 0, 'cantidadDetalles' => 0, 'totalCantidad' => 0, 'totalMonto' => 0],
                    ],
                    'porPersonas' => [
                        ['usuarioId' => '10', 'usuarioNombre' => 'CAJERO LA PAZ', 'usuarioEmail' => 'lapaz@example.test', 'usuarioAlias' => 'cajerolp', 'cantidadVentas' => 3, 'cantidadDetalles' => 3, 'totalCantidad' => 3, 'totalMonto' => 300],
                        ['usuarioId' => '20', 'usuarioNombre' => 'CAJERO SANTA CRUZ', 'usuarioEmail' => 'santacruz@example.test', 'usuarioAlias' => 'cajeroscz', 'cantidadVentas' => 3, 'cantidadDetalles' => 3, 'totalCantidad' => 3, 'totalMonto' => 300],
                        ['usuarioId' => '99', 'usuarioNombre' => 'EDGAR JAVIER GIRONDA CHIRI', 'usuarioEmail' => 'edgar.girona@correos.gob.bo', 'usuarioAlias' => 'edgargironda', 'cantidadVentas' => 1, 'cantidadDetalles' => 1, 'totalCantidad' => 1, 'totalMonto' => 100],
                    ],
                ]],
            ]), 200),
        ]);

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.flujo-cajero', [
            'meses' => [8],
            'anio' => 2026,
            'departamento' => 'SANTA CRUZ',
        ]));

        $response->assertOk()
            ->assertSee('Seleccione el departamento')
            ->assertSee('Todos los departamentos')
            ->assertSee('Reporte filtrado exclusivamente para')
            ->assertSee('CAJERO SANTA CRUZ')
            ->assertDontSee('CAJERO LA PAZ')
            ->assertDontSee('EDGAR JAVIER GIRONDA CHIRI')
            ->assertViewHas('selectedDepartment', 'SANTA CRUZ')
            ->assertViewHas('departmentOptions', fn ($options) => $options->all() === [
                'COBIJA',
                'COCHABAMBA',
                'LA PAZ',
                'ORURO',
                'POTOSI',
                'SANTA CRUZ',
                'SUCRE',
                'TARIJA',
                'TRINIDAD',
            ])
            ->assertViewHas('cashierRows', fn ($rows) => $rows->count() === 1
                && $rows->first()['usuarioNombre'] === 'CAJERO SANTA CRUZ'
                && $rows->first()['departamento'] === 'SANTA CRUZ'
                && $rows->first()['totalMonto'] === 300.0
            )
            ->assertViewHas('summary', fn ($summary) => $summary['cantidadVentas'] === 3.0
                && $summary['totalCantidad'] === 3.0
                && $summary['totalMonto'] === 300.0
            );
    }

    public function test_cashier_flow_executive_report_downloads_pdf_without_contracts(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/ventas/reportes/servicios/detalle')) {
                return Http::response(json_encode([
                    'servicio' => [
                        'rows' => [
                            [
                                'fecha' => '2026-08-03 09:15:00',
                                'usuario' => ['id' => '10', 'nombre' => 'CAJERO UNO'],
                                'regional' => ['nombre' => 'LA PAZ'],
                            ],
                            [
                                'fecha' => '2026-08-04 14:20:00',
                                'usuario' => ['id' => '10', 'nombre' => 'CAJERO UNO'],
                                'regional' => ['nombre' => 'LA PAZ'],
                            ],
                        ],
                    ],
                ], JSON_UNESCAPED_UNICODE), 200);
            }

            return Http::response(json_encode([
                'servicios' => [
                    [
                        'servicio' => 'Servicio Internacional',
                        'cantidadVentas' => 3,
                        'cantidadDetalles' => 3,
                        'totalCantidad' => 3,
                        'totalMonto' => 300,
                        'porRegionales' => [['regional' => 'LA PAZ', 'cantidadVentas' => 3, 'cantidadDetalles' => 3, 'totalCantidad' => 3, 'totalMonto' => 300]],
                        'porPersonas' => [
                            ['usuarioId' => '10', 'usuarioNombre' => 'CAJERO UNO', 'regional' => 'LA PAZ', 'cantidadVentas' => 2, 'cantidadDetalles' => 2, 'totalCantidad' => 2, 'totalMonto' => 200],
                            ['usuarioId' => '99', 'usuarioNombre' => 'EDGAR JAVIER GIRONDA CHIRI', 'regional' => 'LA PAZ', 'cantidadVentas' => 1, 'cantidadDetalles' => 1, 'totalCantidad' => 1, 'totalMonto' => 100],
                        ],
                    ],
                    [
                        'servicio' => 'Servicio Contratos por concepto de pago de servicios de courier correspondiente',
                        'cantidadVentas' => 5,
                        'cantidadDetalles' => 5,
                        'totalCantidad' => 5,
                        'totalMonto' => 500,
                        'porRegionales' => [['regional' => 'COCHABAMBA', 'totalMonto' => 500]],
                        'porPersonas' => [['usuarioId' => '20', 'usuarioNombre' => 'CAJERO CONTRATOS', 'totalMonto' => 500]],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE), 200);
        });

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.flujo-cajero.pdf', [
            'meses' => [8],
            'anio' => 2026,
            'departamento' => 'LA PAZ',
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('INGRESOS DE VENTANILLA NACIONAL', $text);
        $this->assertStringContainsString('DÍAS', $text);
        $this->assertStringContainsString('TRABAJADOS', $text);
        $this->assertStringContainsString('PROMEDIO POR DÍA', $text);
        $this->assertStringContainsString('Bs 100,00', $text);
    }
}

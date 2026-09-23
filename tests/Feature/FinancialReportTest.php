<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public function test_services_report_renders_remote_summary_and_rows(): void
    {
        Http::fake([
            'safe.example.test/*' => Http::response("\xEF\xBB\xBF".json_encode([
                'resumen' => [
                    'cantidadServicios' => 1,
                    'cantidadVentas' => 4,
                    'cantidadDetalles' => 5,
                    'totalCantidad' => 6,
                    'totalMonto' => 120.5,
                ],
                'servicios' => [[
                    'servicio' => 'Servicio Internacional',
                    'cantidadVentas' => 4,
                    'cantidadDetalles' => 5,
                    'totalCantidad' => 6,
                    'totalMonto' => 120.5,
                    'ultimaFecha' => '2026-08-18',
                    'descripcionMuestra' => 'Envío internacional',
                ]],
                'meta' => [],
            ], JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json']),
        ]);

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.ventas-servicios', [
            'mes' => 8,
            'anio' => 2026,
            'limite' => 200,
        ]));

        $response->assertOk()
            ->assertSee('Ventas por servicio')
            ->assertSee('Reporte ejecutivo resumido')
            ->assertSee('Servicio con mayor ingreso')
            ->assertSee('Filtrando datos')
            ->assertSee('Espere por favor, estamos preparando su reporte.')
            ->assertSee('Buscar un servicio por nombre')
            ->assertSee('Filtrar')
            ->assertSee('Servicio Internacional')
            ->assertViewHas('services', fn ($services) => $services->count() === 1);

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['mes'] === 8
            && $request['anio'] === 2026
            && $request['limite'] === 200
        );
    }

    public function test_services_report_combines_months_and_filters_selected_services(): void
    {
        Http::fake(fn (Request $request) => Http::response(json_encode([
            'servicios' => [
                [
                    'servicio' => 'Servicio A',
                    'cantidadVentas' => 1,
                    'cantidadDetalles' => 2,
                    'totalCantidad' => 3,
                    'totalMonto' => 25,
                    'ultimaFecha' => "2026-{$request['mes']}-18",
                    'descripcionMuestra' => 'Servicio seleccionado',
                ],
                [
                    'servicio' => 'Servicio B',
                    'cantidadVentas' => 5,
                    'cantidadDetalles' => 5,
                    'totalCantidad' => 5,
                    'totalMonto' => 100,
                    'ultimaFecha' => "2026-{$request['mes']}-17",
                    'descripcionMuestra' => 'Servicio no seleccionado',
                ],
            ],
        ]), 200));

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.ventas-servicios', [
            'servicios' => ['Servicio A'],
            'meses' => [7, 8],
            'anio' => 2026,
            'limite' => 200,
        ]));

        $response->assertOk()
            ->assertSee('Totales consolidados')
            ->assertViewHas('selectedServices', ['Servicio A'])
            ->assertViewHas('selectedMonths', [7, 8])
            ->assertViewHas('services', fn ($services) => $services->count() === 1
                && $services->first()['servicio'] === 'Servicio A'
                && $services->first()['totalMonto'] === 50.0
            )
            ->assertViewHas('summary', fn ($summary) => $summary['cantidadVentas'] === 2.0
                && $summary['cantidadDetalles'] === 4.0
                && $summary['totalMonto'] === 50.0
            );

        Http::assertSentCount(2);
    }

    public function test_services_report_groups_and_exposes_subservices(): void
    {
        $serviceNames = [
            'Servicio EMS Nacional',
            'Servicio Delivery Express',
            'Servicio Venta de Estampillas',
            'Servicio Venta de Tarjeta Postal',
            'Servicio Contratos por concepto de pago de servicios de courier correspondiente',
            'Servicio Internacional',
            'Servicio Certificadas',
            'Servicio Aerolinea',
            'Servicio Casilla',
        ];

        Http::fake([
            'safe.example.test/*' => Http::response(json_encode([
                'servicios' => collect($serviceNames)->map(fn (string $service, int $index) => [
                    'servicio' => $service,
                    'cantidadVentas' => 1,
                    'cantidadDetalles' => 1,
                    'totalCantidad' => 1,
                    'totalMonto' => ($index + 1) * 10,
                    'ultimaFecha' => '2026-08-18',
                    'descripcionMuestra' => $service,
                ])->all(),
            ]), 200),
        ]);

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.ventas-servicios', [
            'mes' => 8,
            'anio' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Servicios agrupados')
            ->assertSee('Haga clic en un grupo')
            ->assertSee('Servicio Delivery Express')
            ->assertSee('Servicio Aerolinea')
            ->assertViewHas('serviceGroups', function ($groups) {
                $ems = $groups->firstWhere('servicio', 'Servicio EMS Nacional');
                $international = $groups->firstWhere('servicio', 'Servicio Internacional');

                return $groups->count() === 4
                    && $ems['_children']->count() === 2
                    && $international['_children']->count() === 5
                    && $international['_children']->pluck('servicio')->contains('Servicio Venta de Estampillas')
                    && $international['_children']->pluck('servicio')->contains('Servicio Venta de Tarjeta Postal');
            });
    }

    public function test_services_report_can_show_only_invoiced_contract_services(): void
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

    public function test_general_report_only_counts_contract_invoices_validated_in_reconciliation(): void
    {
        Schema::create('conciliaciones_empresa', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('facturado_anio')->nullable();
            $table->unsignedTinyInteger('facturado_mes')->nullable();
            $table->string('factura_venta_id')->nullable();
            $table->decimal('factura_monto', 15, 2)->nullable();
            $table->timestamp('conciliado_at')->nullable();
            $table->timestamps();
        });
        DB::table('conciliaciones_empresa')->insert([
            [
                'facturado_anio' => 2026,
                'facturado_mes' => 8,
                'factura_venta_id' => 'contrato-validado',
                'factura_monto' => 150,
                'conciliado_at' => now(),
            ],
            [
                'facturado_anio' => 2026,
                'facturado_mes' => 8,
                'factura_venta_id' => 'contrato-sin-validar',
                'factura_monto' => 100,
                'conciliado_at' => null,
            ],
        ]);
        Http::fake([
            'safe.example.test/*' => Http::response(json_encode([
                'servicios' => [
                    [
                        'servicio' => 'Servicio Internacional',
                        'cantidadVentas' => 2,
                        'cantidadDetalles' => 2,
                        'totalCantidad' => 2,
                        'totalMonto' => 200,
                    ],
                    [
                        'servicio' => 'Servicio Contratos por concepto de pago de servicios de courier correspondiente',
                        'cantidadVentas' => 3,
                        'cantidadDetalles' => 3,
                        'totalCantidad' => 3,
                        'totalMonto' => 450,
                    ],
                ],
            ]), 200),
        ]);

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.ventas-servicios', [
            'mes' => 8,
            'anio' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Cuentas por cobrar')
            ->assertSee('Bs 300,00')
            ->assertSee('Contratos validados')
            ->assertViewHas('summary', fn (array $summary): bool => $summary['cantidadVentas'] === 3.0
                && $summary['totalMonto'] === 350.0
                && $summary['contratosValidadosVentas'] === 1.0
                && $summary['contratosValidadosMonto'] === 150.0
                && $summary['contratosPorCobrarVentas'] === 2.0
                && $summary['contratosPorCobrarMonto'] === 300.0
            );
    }

    public function test_executive_report_downloads_a_professional_pdf_with_selected_filters(): void
    {
        Http::fake([
            'safe.example.test/*' => Http::response(json_encode([
                'servicios' => [[
                    'servicio' => 'Servicio Internacional',
                    'cantidadVentas' => 4,
                    'cantidadDetalles' => 5,
                    'totalCantidad' => 6,
                    'totalMonto' => 120.5,
                    'ultimaFecha' => '2026-08-18',
                    'descripcionMuestra' => 'Envio internacional',
                ]],
            ]), 200),
        ]);

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.ventas-servicios.pdf', [
            'servicios' => ['Servicio Internacional'],
            'meses' => [8],
            'anio' => 2026,
            'limite' => 200,
        ]));

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload();

        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_service_detail_is_rendered_and_paginated(): void
    {
        $rows = collect(range(1, 51))->map(fn (int $number) => [
            'ventaId' => "venta-{$number}",
            'detalleId' => $number,
            'descripcion' => 'Servicio de prueba',
            'codigoOrden' => "orden-{$number}",
            'codigoSeguimiento' => "BO-{$number}",
            'fecha' => '2026-08-18',
            'totalLinea' => 10,
        ])->all();

        Http::fake([
            'safe.example.test/*' => Http::response("\xEF\xBB\xBF".json_encode([
                'servicio' => [
                    'servicio' => 'Servicio Internacional',
                    'cantidadVentas' => 51,
                    'cantidadDetalles' => 51,
                    'totalCantidad' => 51,
                    'totalMonto' => 510,
                    'rows' => $rows,
                ],
            ]), 200, ['Content-Type' => 'application/json']),
        ]);

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.ventas-servicios.detalle', [
            'servicio' => 'Servicio Internacional',
            'mes' => 8,
            'anio' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Detalle de ventas por servicio')
            ->assertSee('Reporte ejecutivo resumido')
            ->assertSee('Servicios consultados')
            ->assertSee('Ventas registradas')
            ->assertSee('Cantidad total de paquetería')
            ->assertSee('Ingresos de ventanilla')
            ->assertSee('Seleccione los meses')
            ->assertSee('BO-1')
            ->assertDontSee('BO-51')
            ->assertViewHas('rows', fn ($paginator) => $paginator instanceof LengthAwarePaginator
                && $paginator->total() === 51
                && $paginator->count() === 50
            );
    }

    public function test_service_detail_combines_multiple_services_and_months(): void
    {
        Http::fake(function (Request $request) {
            if (! str_contains($request->url(), '/detalle')) {
                return Http::response(json_encode([
                    'servicios' => [
                        ['servicio' => 'Servicio A'],
                        ['servicio' => 'Servicio B'],
                    ],
                ]), 200);
            }

            return Http::response(json_encode([
                'servicio' => [
                    'servicio' => $request['servicio'],
                    'cantidadVentas' => 1,
                    'cantidadDetalles' => 1,
                    'totalCantidad' => 1,
                    'totalMonto' => 25,
                    'rows' => [[
                        'ventaId' => $request['servicio'].'-'.$request['mes'],
                        'descripcion' => 'Combinación de prueba',
                        'fecha' => "2026-{$request['mes']}-18",
                        'totalLinea' => 25,
                    ]],
                ],
            ]), 200);
        });

        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.ventas-servicios.detalle', [
            'servicios' => ['Servicio A', 'Servicio B'],
            'meses' => [7, 8],
            'anio' => 2026,
        ]));

        $response->assertOk()
            ->assertSee('Resultado combinado')
            ->assertSee('Servicio A-7')
            ->assertSee('Servicio A-8')
            ->assertSee('Servicio B-7')
            ->assertSee('Servicio B-8')
            ->assertViewHas('selectedServices', ['Servicio A', 'Servicio B'])
            ->assertViewHas('selectedMonths', [7, 8])
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 4)
            ->assertViewHas('service', fn ($service) => $service['cantidadVentas'] === 4.0
                && $service['totalMonto'] === 100.0
            );

        Http::assertSentCount(6);
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

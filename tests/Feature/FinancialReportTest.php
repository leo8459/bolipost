<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
}

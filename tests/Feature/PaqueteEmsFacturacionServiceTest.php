<?php

namespace Tests\Feature;

use App\Services\PaqueteEmsFacturacionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaqueteEmsFacturacionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'services.facturacion_reports.base_url' => 'https://facturacion.example/api/factura-venta',
            'services.facturacion_reports.token' => 'test-token',
        ]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_matches_invoices_by_code_even_when_results_are_reordered(): void
    {
        Http::fake(['facturacion.example/*' => Http::response([
            'ok' => true,
            'resultados' => [
                ['codigo_paquete' => 'EMS-B', 'coincidencias' => [], 'facturado' => false],
                ['codigo_paquete' => 'OFICIAL-C', 'coincidencias' => [['id' => 20]]],
                [
                    'codigo_paquete' => 'EMS-A',
                    'coincidencias' => [['id' => 10]],
                    'tiene_documento_fiscal_vigente' => false,
                ],
            ],
        ])]);

        $statuses = app(PaqueteEmsFacturacionService::class)->statuses([
            ['codigo' => ' ems-a '],
            ['codigo' => 'EMS-B'],
            ['codigo' => 'OFICIAL-C', 'tipo_correspondencia' => 'OFICIAL'],
        ]);

        $this->assertSame([
            'EMS-A' => 'facturada',
            'EMS-B' => 'no_facturada',
            'OFICIAL-C' => 'oficial',
        ], $statuses);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://facturacion.example/api/factura-venta/paquetes/consultar-facturas'
            && $request->data() === ['codigos_paquete' => ['EMS-A', 'EMS-B']]);
    }

    public function test_does_not_assign_another_codes_invoice_or_assume_missing_results_are_negative(): void
    {
        $body = "\xEF\xBB\xBF".json_encode([
            'ok' => true,
            'resultados' => [
                ['codigo_paquete' => 'EMS-AB', 'coincidencias' => [['id' => 30]]],
                ['codigo_paquete' => 'EMS-B', 'coincidencias' => []],
            ],
        ]);
        Http::fake(['facturacion.example/*' => Http::response($body)]);

        $this->assertSame([
            'EMS-A' => 'sin_verificar',
            'EMS-B' => 'no_facturada',
        ], app(PaqueteEmsFacturacionService::class)->statuses([
            ['codigo' => 'EMS-A'], ['codigo' => 'EMS-B'],
        ]));
    }

    public function test_api_failure_is_not_reported_as_no_invoice(): void
    {
        Http::fake(['facturacion.example/*' => Http::response([], 503)]);

        $this->assertSame(['EMS-A' => 'sin_verificar'],
            app(PaqueteEmsFacturacionService::class)->statuses([['codigo' => 'EMS-A']]));
    }

    public function test_refresh_rechecks_a_previously_unbilled_package(): void
    {
        Http::fake(['facturacion.example/*' => Http::sequence()
            ->push(['ok' => true, 'resultados' => [['codigo_paquete' => 'EMS-A', 'coincidencias' => []]]])
            ->push(['ok' => true, 'resultados' => [['codigo_paquete' => 'EMS-A', 'coincidencias' => [['id' => 1]]]]])]);

        $service = app(PaqueteEmsFacturacionService::class);
        $packages = [['codigo' => 'EMS-A']];
        $this->assertSame(['EMS-A' => 'no_facturada'], $service->statuses($packages));
        $this->assertSame(['EMS-A' => 'facturada'], $service->statuses($packages, refresh: true));
        // El servicio POR COBRAR prevalece incluso si antes se cacheo una factura.
        $this->assertSame(['EMS-A' => 'por_cobrar'], $service->statuses([
            ['codigo' => 'EMS-A', 'servicio_especial' => ' por cobrar '],
        ]));
        Http::assertSentCount(2);
    }

    public function test_warehouse_gate_blocks_unbilled_and_unverified_packages_but_allows_officials(): void
    {
        Http::fake(['facturacion.example/*' => Http::response([
            'ok' => true,
            'resultados' => [
                ['codigo_paquete' => 'EMS-A', 'coincidencias' => [['id' => 1]]],
                ['codigo_paquete' => 'EMS-B', 'coincidencias' => []],
            ],
        ])]);
        $component = new class extends \App\Livewire\PaquetesEms
        {
            public function checkWarehouse(array $packages): bool
            {
                return $this->validarFacturacionAlmacen($packages);
            }
        };

        $this->assertFalse($component->checkWarehouse([
            ['codigo' => 'EMS-A'], ['codigo' => 'EMS-B'], ['codigo' => 'EMS-C'],
            ['codigo' => 'OF-1', 'tipo_correspondencia' => ' oficial '],
            ['codigo' => 'EMS-COBRO', 'servicio_especial' => ' por cobrar '],
        ]));
        $this->assertSame([
            ['codigo' => 'EMS-B', 'estado' => 'no_facturada'],
            ['codigo' => 'EMS-C', 'estado' => 'sin_verificar'],
        ], $component->facturacionAlmacenBloqueados);
        $events = \Livewire\store($component)->get('dispatched');
        $this->assertSame('openFacturacionAlmacenModal', $events[0]->serialize()['name']);
        Http::assertSent(fn ($request) => $request->data() === ['codigos_paquete' => ['EMS-A', 'EMS-B', 'EMS-C']]);

        $this->assertTrue($component->checkWarehouse([
            ['codigo' => 'EMS-A'], ['codigo' => 'OF-1', 'tipo_correspondencia' => 'OFICIAL'],
            ['codigo' => 'EMS-COBRO', 'servicio_especial' => 'POR COBRAR'],
        ]));
        $this->assertSame([], $component->facturacionAlmacenBloqueados);

        config(['services.facturacion_reports.token' => '']);
        $this->assertTrue($component->checkWarehouse([['codigo' => 'OF-1', 'tipo_correspondencia' => 'OFICIAL']]));
        $this->assertTrue($component->checkWarehouse([['codigo' => 'EMS-COBRO', 'servicio_especial' => 'POR COBRAR']]));
        $this->assertFalse($component->checkWarehouse([['codigo' => 'EMS-IDA', 'servicio_especial' => 'IDA']]));
        $this->assertFalse($component->checkWarehouse([['codigo' => 'POR COBRAR', 'servicio_especial' => 'IDA Y VUELTA']]));
        $this->assertFalse($component->checkWarehouse([['codigo' => 'OFICIAL-FALSO', 'tipo_correspondencia' => 'EMS']]));
    }
}

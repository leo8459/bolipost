<?php

namespace Tests\Feature;

use App\Http\Controllers\FacturaFirmaPdfController;
use App\Models\User;
use App\Services\FacturaFirmaPdfService;
use App\Services\FacturacionCartService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use ReflectionMethod;
use setasign\Fpdi\Fpdi;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class FacturaFirmaPdfDeliveryFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('eventos_certi');
        Schema::dropIfExists('eventos');

        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });

        Schema::create('eventos_certi', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->index();
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('eventos_certi');
        Schema::dropIfExists('eventos');

        parent::tearDown();
    }

    public function test_delivery_form_lists_only_packages_from_expedition_forward(): void
    {
        $expeditionEvent = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Enviar envio a ubicacion nacional',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $customsEvent = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Enviado a control aduanero',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $counterEvent = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Paquete listo para entregar en oficina',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $deliveredEvent = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Paquete entregado exitosamente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('eventos_certi')->insert([
            [
                'codigo' => 'RR-EXPEDICION',
                'evento_id' => $expeditionEvent,
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'codigo' => 'RR-ADUANA',
                'evento_id' => $customsEvent,
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ],
            [
                'codigo' => 'RR-VENTANILLA',
                'evento_id' => $counterEvent,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'codigo' => 'RR-ENTREGADO',
                'evento_id' => $deliveredEvent,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $packages = $this->deliveryPackagesFromItems(collect([
            (object) ['codigo' => 'RR-EXPEDICION', 'tipo' => 'Certificado Internacional', 'peso' => 0.01],
            (object) ['codigo' => 'RR-ADUANA', 'tipo' => 'Certificado Internacional', 'peso' => 0.02],
            (object) ['codigo' => 'RR-VENTANILLA', 'tipo' => 'Certificado Internacional', 'peso' => 0.03],
            (object) ['codigo' => 'RR-ENTREGADO', 'tipo' => 'Certificado Internacional', 'peso' => 0.04],
        ]));

        $this->assertSame(['RR-EXPEDICION', 'RR-ADUANA', 'RR-VENTANILLA', 'RR-ENTREGADO'], array_column($packages, 'codigo'));
        $this->assertSame(['0.010 kg', '0.020 kg', '0.030 kg', '0.040 kg'], array_column($packages, 'peso'));
    }

    public function test_delivery_form_accepts_customs_package_code_from_resumen_codigo_item(): void
    {
        $customsEvent = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Paquete enviado a aduana.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('eventos_certi')->insert([
            'codigo' => 'RR-RESUMEN-ITEM',
            'evento_id' => $customsEvent,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $packages = $this->deliveryPackagesFromItems(collect([
            (object) [
                'tipo' => 'Certificado Internacional',
                'resumen_origen' => [
                    'codigo_item' => 'RR-RESUMEN-ITEM',
                    'peso' => 0.025,
                ],
                'precio' => 69,
            ],
        ]));

        $this->assertSame('RR-RESUMEN-ITEM', $packages[0]['codigo'] ?? null);
        $this->assertSame('0.025 kg', $packages[0]['peso'] ?? null);
        $this->assertSame('Bs 69.00', $packages[0]['monto'] ?? null);
    }

    public function test_delivery_form_uses_external_tracking_fallback_when_local_events_are_missing(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking-primary.test/api/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'primary-token');
        config()->set('services.tracking_sqlserver.eventos_batch_url', '');
        config()->set('services.tracking_sqlserver.fallback_base_url', 'https://tracking-fallback.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.fallback_token', 'fallback-token');

        Http::fake([
            'https://tracking-primary.test/*' => Http::response([], 500),
            'https://tracking-fallback.test/*' => Http::response([
                'tipo' => 'tracking_eventos',
                'existe_paquete' => true,
                'resultado' => [[
                    'codigo' => 'LX-API-ADUANA',
                    'eventos' => [[
                        'codigo' => 'LX-API-ADUANA',
                        'created_at' => '2026-09-10 14:21:16',
                        'nombre_evento' => 'Paquete enviado a aduana.',
                    ]],
                ]],
            ], 200),
        ]);

        $packages = $this->deliveryPackagesFromItems(collect([
            (object) [
                'codigo_paquete' => 'LX-API-ADUANA',
                'tipo' => 'Ordinarias Internacional',
                'peso' => 0.5,
                'precio' => 25,
            ],
        ]));

        $this->assertSame('LX-API-ADUANA', $packages[0]['codigo'] ?? null);
        $this->assertSame('0.500 kg', $packages[0]['peso'] ?? null);
        $this->assertSame('Bs 25.00', $packages[0]['monto'] ?? null);
    }

    public function test_delivery_form_uses_tracking_batch_endpoint_for_multiple_packages(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking-primary.test/api/tracking/eventos');
        config()->set('services.tracking_sqlserver.eventos_batch_url', 'https://tracking-primary.test/api/tracking/eventos/batch');
        config()->set('services.tracking_sqlserver.token', 'primary-token');
        config()->set('services.tracking_sqlserver.fallback_base_url', '');

        Http::fake([
            'https://tracking-primary.test/api/tracking/eventos/batch' => Http::response([
                'tipo' => 'tracking_eventos_batch',
                'resultado' => [
                    [
                        'codigo' => 'LX-BATCH-1',
                        'tipo_servicio' => 'ordinarias',
                        'eventos_externos' => [[
                            'mailitM_FID' => 'LX-BATCH-1',
                            'codigo_evento' => 31,
                            'eventType' => 'Enviado a control aduanero',
                            'eventDate' => '2026-09-10 14:21:16',
                        ]],
                    ],
                    [
                        'codigo' => 'LX-BATCH-2',
                        'tipo_servicio' => 'ordinarias',
                        'eventos_externos' => [[
                            'mailitM_FID' => 'LX-BATCH-2',
                            'codigo_evento' => 31,
                            'eventType' => 'Enviado a control aduanero',
                            'eventDate' => '2026-09-10 14:22:16',
                        ]],
                    ],
                ],
            ], 200),
        ]);

        $packages = $this->deliveryPackagesFromItems(collect([
            (object) [
                'codigo_paquete' => 'LX-BATCH-1',
                'tipo' => 'Ordinarias Internacional',
                'peso' => 0.5,
                'precio' => 25,
            ],
            (object) [
                'codigo_paquete' => 'LX-BATCH-2',
                'tipo' => 'Ordinarias Internacional',
                'peso' => 0.05,
                'precio' => 25,
            ],
        ]));

        $this->assertSame(['LX-BATCH-1', 'LX-BATCH-2'], array_column($packages, 'codigo'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://tracking-primary.test/api/tracking/eventos/batch'
            && $request['codigos'] === ['LX-BATCH-1', 'LX-BATCH-2']);
    }

    public function test_delivery_form_is_not_reserved_when_there_are_no_counter_packages(): void
    {
        $method = new ReflectionMethod(FacturaFirmaPdfService::class, 'deliveryBlockHeight');
        $method->setAccessible(true);

        $height = $method->invoke(new FacturaFirmaPdfService(), [
            'codigo_rastreo' => 'RR-SOLO-REFERENCIA',
            'packages' => [],
        ], 80);

        $this->assertSame(0.0, $height);
    }

    public function test_delivery_date_uses_invoice_emission_datetime(): void
    {
        $method = new ReflectionMethod(FacturaFirmaPdfController::class, 'resolveInvoiceDate');
        $method->setAccessible(true);

        $date = $method->invoke(new FacturaFirmaPdfController(), (object) [
            'emitido_en' => '2026-09-09 13:29:25',
        ]);

        $this->assertSame('09 de septiembre de 2026 13:29:25', $date);
    }

    public function test_delivery_form_is_added_as_two_separate_pdf_pages(): void
    {
        $service = new FacturaFirmaPdfService();

        $output = $service->appendSignatureFields($this->simpleInvoicePdf(), [
            'packages' => [
                ['codigo' => 'RR-VENTANILLA', 'servicio' => 'Certificadas', 'peso' => '0.015 kg'],
            ],
            'usuario' => 'Nanda Flores Yujra',
            'numero_factura' => '2597',
            'fecha_entrega' => '09 de septiembre de 2026 13:29:25',
        ]);

        $pages = (new Parser())->parseContent($output)->getPages();

        $this->assertCount(3, $pages);
        $this->assertStringContainsString('FORMULARIO DE ENTREGA', $pages[1]->getText());
        $this->assertStringContainsString('RR-VENTANILLA', $pages[1]->getText());
        $this->assertStringContainsString('0.015 kg', $pages[1]->getText());
        $this->assertStringNotContainsString('Certificadas', $pages[1]->getText());
        $this->assertStringContainsString('Conserve este comprobante como respaldo de entrega.', $pages[1]->getText());
        $this->assertStringContainsString('Copia para Correos de Bolivia.', $pages[1]->getText());
        $this->assertStringContainsString('FORMULARIO DE ENTREGA', $pages[2]->getText());
        $this->assertStringContainsString('RR-VENTANILLA', $pages[2]->getText());
        $this->assertStringContainsString('Conserve este comprobante para respaldo de entrega.', $pages[2]->getText());
        $this->assertStringContainsString('Copia para Aduana.', $pages[2]->getText());
    }

    public function test_delivery_form_page_is_not_added_without_packages(): void
    {
        $service = new FacturaFirmaPdfService();

        $output = $service->appendSignatureFields($this->simpleInvoicePdf(), [
            'codigo_rastreo' => 'RR-SOLO-REFERENCIA',
            'packages' => [],
        ]);

        $pages = (new Parser())->parseContent($output)->getPages();

        $this->assertCount(1, $pages);
    }

    public function test_invoice_download_uses_sale_detail_and_local_tracking_for_delivery_form(): void
    {
        $eventId = DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Paquete enviado a aduana.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('eventos_certi')->insert([
            'codigo' => 'RR-DETALLE',
            'evento_id' => $eventId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cartService = $this->mock(FacturacionCartService::class);
        $cartService->shouldReceive('fetchVentaById')->once()->andReturn((object) [
            'id' => 8606, 'venta_id' => 123, 'items' => [],
        ]);
        $cartService->shouldReceive('fetchVentaDetalleByVentaId')->once()
            ->withArgs(fn (User $user, int $id) => $user->id === 1 && $id === 123)
            ->andReturn((object) ['detalle' => [
                ['codigo_paquete' => 'RR-DETALLE', 'tipo' => 'Certificado Internacional'],
            ]]);

        $response = $this->downloadInvoice();
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $pages = (new Parser())->parseContent($response->getContent())->getPages();
        $this->assertCount(3, $pages);
        $this->assertStringContainsString('RR-DETALLE', $pages[1]->getText());
    }

    public function test_invoice_download_succeeds_without_delivery_form_when_tracking_is_unavailable(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/tracking/eventos');
        config()->set('services.tracking_sqlserver.eventos_batch_url', '');
        config()->set('services.tracking_sqlserver.fallback_base_url', '');
        $this->mock(FacturacionCartService::class)
            ->shouldReceive('fetchVentaById')->once()->andReturn((object) [
                'id' => 8606,
                'items' => [['codigo_paquete' => 'RR-SIN-TRACKING', 'tipo' => 'Certificado Internacional']],
            ]);

        $response = $this->downloadInvoice();
        $response->assertOk();
        $this->assertCount(1, (new Parser())->parseContent($response->getContent())->getPages());
    }

    public function test_invoice_download_succeeds_for_services_without_package_codes(): void
    {
        $this->mock(FacturacionCartService::class)
            ->shouldReceive('fetchVentaById')->once()->andReturn((object) [
                'id' => 8606,
                'items' => [['codigo' => 'SERV-123', 'titulo' => 'Servicio']],
            ]);

        $response = $this->downloadInvoice();
        $response->assertOk();
        $this->assertCount(1, (new Parser())->parseContent($response->getContent())->getPages());
    }

    public function test_invoice_download_succeeds_for_links_without_cart_id(): void
    {
        $this->mock(FacturacionCartService::class)->shouldNotReceive('fetchVentaById');

        $response = $this->downloadInvoice(['cart_id' => null]);
        $response->assertOk();
        $this->assertCount(1, (new Parser())->parseContent($response->getContent())->getPages());
    }

    private function downloadInvoice(array $parameters = []): TestResponse
    {
        config()->set('services.facturacion_bridge.sefe_public_base_url', 'https://sefe.test');
        Http::preventStrayRequests();
        Http::fake([
            'https://sefe.test/public/facturas_pdf/test.pdf' => Http::response($this->simpleInvoicePdf(), 200),
            '*' => Http::response([], 503),
        ]);
        $user = new User(['name' => 'Cajero']);
        $user->id = 1;
        $user->setRelation('sucursal', null);
        $request = Request::create('/facturacion/factura-con-firma', 'GET', array_merge([
            'url' => 'https://sefe.test/public/facturas_pdf/test.pdf',
            'cart_id' => 8606,
            'source_user_id' => 1,
        ], $parameters));
        $request->setUserResolver(fn () => $user);

        return TestResponse::fromBaseResponse(app()->call(
            [new FacturaFirmaPdfController(), '__invoke'], ['request' => $request]
        ));
    }

    private function deliveryPackagesFromItems(\Illuminate\Support\Collection $items): array
    {
        $method = new ReflectionMethod(FacturaFirmaPdfController::class, 'deliveryPackagesFromItems');
        $method->setAccessible(true);

        return $method->invoke(new FacturaFirmaPdfController(), $items);
    }

    private function simpleInvoicePdf(): string
    {
        $pdf = new Fpdi();
        $pdf->AddPage('P', [80, 120]);
        $pdf->SetFont('Courier', '', 8);
        $pdf->Text(8, 15, 'FACTURA');
        $pdf->Text(8, 25, 'FECHA: 09 de septiembre de 2026 13:29:25');

        return $pdf->Output('S');
    }
}

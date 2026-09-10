<?php

namespace Tests\Feature;

use App\Http\Controllers\FacturaFirmaPdfController;
use App\Services\FacturaFirmaPdfService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            (object) ['codigo' => 'RR-EXPEDICION', 'tipo' => 'Certificado Internacional'],
            (object) ['codigo' => 'RR-ADUANA', 'tipo' => 'Certificado Internacional'],
            (object) ['codigo' => 'RR-VENTANILLA', 'tipo' => 'Certificado Internacional'],
            (object) ['codigo' => 'RR-ENTREGADO', 'tipo' => 'Certificado Internacional'],
        ]));

        $this->assertSame(['RR-EXPEDICION', 'RR-ADUANA', 'RR-VENTANILLA', 'RR-ENTREGADO'], array_column($packages, 'codigo'));
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
                ['codigo' => 'RR-VENTANILLA', 'servicio' => 'Certificadas'],
            ],
            'usuario' => 'Nanda Flores Yujra',
            'numero_factura' => '2597',
            'fecha_entrega' => '09 de septiembre de 2026 13:29:25',
        ]);

        $pages = (new Parser())->parseContent($output)->getPages();

        $this->assertCount(3, $pages);
        $this->assertStringContainsString('FORMULARIO DE ENTREGA', $pages[1]->getText());
        $this->assertStringContainsString('RR-VENTANILLA', $pages[1]->getText());
        $this->assertStringNotContainsString('Certificadas', $pages[1]->getText());
        $this->assertStringContainsString('FORMULARIO DE ENTREGA', $pages[2]->getText());
        $this->assertStringContainsString('RR-VENTANILLA', $pages[2]->getText());
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

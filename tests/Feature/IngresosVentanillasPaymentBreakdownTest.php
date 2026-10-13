<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class IngresosVentanillasPaymentBreakdownTest extends TestCase
{
    public function test_payment_methods_reach_cashiers_department_totals_and_pdf(): void
    {
        $this->assertPaymentBreakdown(false);
    }

    public function test_payment_audit_excludes_observed_and_cancelled_invoices_using_detail(): void
    {
        $this->assertPaymentBreakdown(true);
    }

    private function assertPaymentBreakdown(bool $includesObserved): void
    {
        config([
            'services.facturacion_reports.base_url' => 'https://reports.example.test/api',
            'services.facturacion_reports.token' => 'test-token',
        ]);
        Schema::create('app_settings', function ($table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $people = [
            ['usuarioId' => '10', 'usuarioNombre' => 'CAJERO LA PAZ', 'departamento' => 'LA PAZ', 'totalMonto' => 120, 'totalMontoVendido' => 100],
            ['usuarioId' => '20', 'usuarioNombre' => 'CAJERO COCHABAMBA', 'departamento' => 'COCHABAMBA', 'totalMonto' => 200, 'totalMontoVendido' => 200],
        ];
        Http::preventStrayRequests();
        $summary = [
            'meta' => ['detalleAnulacionesIncluido' => true, 'auditoriaMediosPagoIncluida' => true],
            'servicios' => [[
                'servicio' => 'Servicio Internacional',
                'totalMonto' => 320,
                'totalMontoVendido' => 300,
                'totalMontoNoIncluidoEnTotalVendido' => 20,
                'porPersonas' => $people,
                'auditoriaMediosPago' => [
                    [...$people[0], 'totalMonto' => $includesObserved ? 120 : 100, 'porMedioPago' => ['qr' => 40, 'efectivo' => $includesObserved ? 80 : 60, 'otros' => 0]],
                    [...$people[1], 'totalMonto' => 200, 'porMedioPago' => ['qr' => 75, 'efectivo' => 125, 'otros' => 0]],
                ],
            ]],
        ];
        Http::fake(function ($request) use ($summary) {
            if (str_contains($request->url(), '/detalle')) {
                $rows = [];
                foreach ([['10', 'QR', 40, true, false], ['10', 'EFECTIVO', 60, true, false],
                    ['20', 'QR', 75, true, false], ['20', 'EFECTIVO', 125, true, false],
                    ['10', 'EFECTIVO', 20, false, false], ['10', 'QR', 5, false, true]] as $index => $entry) {
                    [$id, $method, $amount, $included, $cancelled] = $entry;
                    $rows[] = [
                        'ventaId' => 'sale-'.$index, 'detalleId' => $index,
                        'usuario' => ['id' => $id], 'medioPago' => $method,
                        'totalLinea' => $amount, 'cantidad' => 1,
                        'incluidaEnTotalVendido' => $included, 'anulada' => $cancelled,
                        'estadoFiscal' => $cancelled ? 'ANULADA' : ($included ? 'PROCESADA' : 'OBSERVADA'),
                    ];
                }

                return Http::response(['servicio' => ['rows' => $rows]]);
            }

            return Http::response($summary);
        });

        $filters = ['mes' => 7, 'anio' => 2026];
        $response = $this->withoutMiddleware()->get(route('dashboard.financiera.ingresos-ventanillas', $filters));
        $response->assertOk()->assertViewHas('cashierDepartmentGroups', function ($groups): bool {
            $this->assertCount(2, $groups);
            $this->assertEquals(300, $groups->sum('totalIngresos'));
            foreach ($groups as $group) {
                $cashiers = collect($group['cajeros']);
                $this->assertEquals(
                    $group['totalIngresos'],
                    $cashiers->sum('paymentMethods.qr.totalMonto') + $cashiers->sum('paymentMethods.efectivo.totalMonto')
                );
            }

            return true;
        });
        $response->assertViewHas('cashierRows', function ($rows): bool {
            $this->assertEquals(115, $rows->sum('paymentMethods.qr.totalMonto'));
            $this->assertEquals(185, $rows->sum('paymentMethods.efectivo.totalMonto'));

            return true;
        });

        $pdf = $this->get(route('dashboard.financiera.ingresos-ventanillas.pdf', $filters));
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $text = (new Parser)->parseContent($pdf->getContent())->getText();
        foreach (['40,00', '60,00', '75,00', '125,00'] as $amount) {
            $this->assertStringContainsString($amount, $text);
        }
    }
}

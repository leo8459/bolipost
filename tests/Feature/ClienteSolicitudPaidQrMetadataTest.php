<?php

namespace Tests\Feature;

use App\Http\Controllers\ClienteSolicitudController;
use App\Models\Cliente;
use App\Services\ClienteSolicitudFacturacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ClienteSolicitudPaidQrMetadataTest extends TestCase
{
    public function test_paid_qr_with_lost_shipment_data_keeps_amount_and_code_and_stops_fiscal_retries(): void
    {
        Http::preventStrayRequests();
        $cliente = new Cliente();
        $cliente->id = 24;
        Auth::guard('cliente')->setUser($cliente);
        $attemptId = '0ec76722-3a19-45fd-b04d-49134bf2be5e';
        $cart = [
            'id' => 13311, 'codigo_orden' => 'VQC-0000004392',
            'estado_pago' => 'pagado', 'estado_emision' => 'EMITIENDO',
            'qr_transaction_id' => '4731459', 'total' => 1,
            'created_at' => now()->toDateTimeString(),
            'items' => [[
                'origen_tipo' => 'cliente_online',
                'resumen_origen' => ['qr_attempt_id' => $attemptId],
            ]],
        ];
        $service = Mockery::mock(ClienteSolicitudFacturacionService::class)->makePartial();
        $service->shouldReceive('buscarSolicitudQr')->once()->with($cliente, $attemptId)->andReturn(['cart' => $cart]);
        $service->shouldReceive('consultarPagoQr')->once()->with($cliente, 13311)->andReturn([
            'cart' => $cart, 'payment_status' => 'pagado', 'invoice_status' => 'EMITIENDO',
        ]);
        $service->shouldNotReceive('emitirFacturaQrPagada');
        $service->shouldNotReceive('vincularSolicitudQr');

        $request = Request::create('/clientes/solicitudes/pago-qr/estado', 'POST', ['qr_attempt_id' => $attemptId]);
        $response = (new ClienteSolicitudController())->checkQrStatus($request, $service);
        $payload = $response->getData(true);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertTrue($payload['paid']);
        $this->assertSame('pagado', $payload['payment_status']);
        $this->assertSame('1.00', $payload['qr']['amount']);
        $this->assertSame('VQC-0000004392', $payload['qr']['internal_code']);
        $this->assertSame('', $payload['qr']['qr_image']);
        $this->assertTrue($payload['terminal']);
        $this->assertFalse($payload['retry']);
        $this->assertSame('missing_shipment_data', $payload['registration_error']);
        $this->assertStringContainsString('no vuelvas a pagar', $payload['message']);
    }
}

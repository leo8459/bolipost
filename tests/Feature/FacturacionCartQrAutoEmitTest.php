<?php

namespace Tests\Feature;

use App\Services\FacturacionCartService;
use ReflectionMethod;
use Tests\TestCase;

class FacturacionCartQrAutoEmitTest extends TestCase
{
    public function test_auto_emit_accepts_paid_status_from_qr_response_when_cart_is_still_pending(): void
    {
        $service = new FacturacionCartService();
        $method = new ReflectionMethod(FacturacionCartService::class, 'shouldAutoEmitPaidQrInvoice');
        $method->setAccessible(true);

        $cart = (object) [
            'canal_emision' => 'qr',
            'metodo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'estado_emision' => 'NO_APLICA',
        ];

        $this->assertTrue($method->invoke($service, $cart, [
            'payment_status' => 'PAID',
        ], true));
    }

    public function test_auto_emit_accepts_paid_status_from_nested_qr_item_response(): void
    {
        $service = new FacturacionCartService();
        $method = new ReflectionMethod(FacturacionCartService::class, 'shouldAutoEmitPaidQrInvoice');
        $method->setAccessible(true);

        $cart = (object) [
            'canal_emision' => 'qr',
            'metodo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'estado_emision' => '',
        ];

        $this->assertTrue($method->invoke($service, $cart, [
            'items' => [
                ['paymentStatus' => 'APPROVED'],
            ],
        ], true));
    }

    public function test_auto_emit_still_rejects_unpaid_qr_status(): void
    {
        $service = new FacturacionCartService();
        $method = new ReflectionMethod(FacturacionCartService::class, 'shouldAutoEmitPaidQrInvoice');
        $method->setAccessible(true);

        $cart = (object) [
            'canal_emision' => 'qr',
            'metodo_pago' => 'qr',
            'estado_pago' => 'pendiente',
            'estado_emision' => 'NO_APLICA',
        ];

        $this->assertFalse($method->invoke($service, $cart, [
            'payment_status' => 'HOLDING',
        ], true));
    }
}

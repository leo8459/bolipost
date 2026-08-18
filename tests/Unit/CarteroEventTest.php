<?php

namespace Tests\Unit;

use App\Support\CarteroEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CarteroEventTest extends TestCase
{
    #[DataProvider('carteroEvents')]
    public function test_displays_original_detail_from_tracking_record(string $eventName): void
    {
        $detail = 'Evento completo con usuario, cartero u observacion.';

        $this->assertSame($detail, CarteroEvent::nombreMostrado($eventName, $detail));
        $this->assertSame($eventName, CarteroEvent::nombreMostrado($eventName, null));
    }

    public static function carteroEvents(): array
    {
        return [
            [CarteroEvent::ASIGNADO],
            [CarteroEvent::CAMBIADO],
            [CarteroEvent::QUITADO],
            [CarteroEvent::INTENTO_VENTANILLA],
        ];
    }
}

<?php

namespace Tests\Unit;

use App\Support\EncargadoEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EncargadoEventTest extends TestCase
{
    #[DataProvider('encargadoEvents')]
    public function test_displays_tracking_detail_without_changing_the_catalog_event(string $eventName): void
    {
        $detail = 'Detalle completo con usuario y valores.';

        $this->assertSame($detail, EncargadoEvent::nombreMostrado($eventName, $detail));
        $this->assertSame($eventName, EncargadoEvent::nombreMostrado($eventName, null));
    }

    public static function encargadoEvents(): array
    {
        return [
            [EncargadoEvent::CANCELADO],
            [EncargadoEvent::PESO_ACTUALIZADO],
            [EncargadoEvent::CARTERO_CAMBIADO],
            [EncargadoEvent::CARTERO_QUITADO],
            [EncargadoEvent::DEVUELTO_ORIGEN],
            [EncargadoEvent::DEVUELTO_DESTINO],
            [EncargadoEvent::DEVUELTO_VENTANILLA],
        ];
    }
}

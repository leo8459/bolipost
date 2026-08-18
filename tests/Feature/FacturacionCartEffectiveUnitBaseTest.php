<?php

namespace Tests\Feature;

use App\Services\FacturacionCartService;
use ReflectionMethod;
use Tests\TestCase;

class FacturacionCartEffectiveUnitBaseTest extends TestCase
{
    public function test_effective_unit_base_uses_total_and_quantity_when_base_is_zero(): void
    {
        $service = new FacturacionCartService();
        $method = new ReflectionMethod($service, 'resolveEffectiveDraftItemUnitBaseAmount');
        $method->setAccessible(true);

        $item = (object) [
            'cantidad' => 2,
            'monto_base' => 0,
            'monto_extras' => 0,
            'total_linea' => 4.00,
        ];

        $resolved = $method->invoke($service, $item);

        $this->assertSame(2.0, $resolved);
    }
}

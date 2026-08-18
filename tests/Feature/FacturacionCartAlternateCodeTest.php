<?php

namespace Tests\Feature;

use App\Services\FacturacionCartService;
use ReflectionMethod;
use Tests\TestCase;

class FacturacionCartAlternateCodeTest extends TestCase
{
    public function test_alternate_code_uses_dot_suffix_sequence(): void
    {
        $service = new FacturacionCartService();
        $method = new ReflectionMethod($service, 'buildAlternateDraftItemCode');
        $method->setAccessible(true);

        $this->assertSame('SRVE-6.1', $method->invoke($service, 'SRVE-6', 2));
        $this->assertSame('SRVE-6.2', $method->invoke($service, 'SRVE-6', 3));
    }
}

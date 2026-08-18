<?php

namespace Tests\Feature;

use App\Models\ConceptoFacturacion;
use App\Services\FacturacionCartService;
use ReflectionMethod;
use Tests\TestCase;

class FacturacionCartCodeFamilyNormalizationTest extends TestCase
{
    public function test_code_family_extraction_removes_numeric_dot_suffix(): void
    {
        $service = new FacturacionCartService();
        $method = new ReflectionMethod($service, 'extractDraftItemCodeFamily');
        $method->setAccessible(true);

        $this->assertSame('SRVE-6', $method->invoke($service, 'SRVE-6.1'));
        $this->assertSame('SRVE-6', $method->invoke($service, 'SRVE-6.9'));
        $this->assertSame('SRVE-6', $method->invoke($service, 'SRVE-6'));
    }
}

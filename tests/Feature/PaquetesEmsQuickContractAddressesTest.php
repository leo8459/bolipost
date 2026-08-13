<?php

namespace Tests\Feature;

use Tests\TestCase;

class PaquetesEmsQuickContractAddressesTest extends TestCase
{
    public function test_quick_contract_accepts_and_persists_optional_addresses(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/PaquetesEmsController.php'));
        $view = file_get_contents(resource_path('views/paquetes_ems/registro-rapido-contrato.blade.php'));

        $this->assertStringContainsString("'items.*.direccion_r' => 'nullable|string|max:255'", $controller);
        $this->assertStringContainsString("'items.*.direccion_d' => 'nullable|string|max:255'", $controller);
        $this->assertStringContainsString("'direccion_r' => \$item['direccion_r'] !== '' ? \$item['direccion_r'] : 'SIN DIRECCION'", $controller);
        $this->assertStringContainsString("'direccion_d' => \$item['direccion_d'] !== '' ? \$item['direccion_d'] : 'SIN DIRECCION'", $controller);

        $this->assertStringContainsString('id="registroRapidoDireccionOrigen"', $view);
        $this->assertStringContainsString('id="registroRapidoDireccionDestinatario"', $view);
        $this->assertStringContainsString('direccion_r: item.direccion_r || null', $view);
        $this->assertStringContainsString('direccion_d: item.direccion_d || null', $view);
    }
}

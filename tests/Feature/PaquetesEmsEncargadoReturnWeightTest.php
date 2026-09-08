<?php

namespace Tests\Feature;

use Tests\TestCase;

class PaquetesEmsEncargadoReturnWeightTest extends TestCase
{
    public function test_la_vista_exige_peso_al_devolver_un_contrato_sin_peso(): void
    {
        $template = file_get_contents(resource_path('views/paquetes_ems/encargado.blade.php'));

        $this->assertStringContainsString('data-requires-contract-weight', $template);
        $this->assertStringContainsString('id="emsReturnWeightField"', $template);
        $this->assertStringContainsString('id="emsReturnWeightCode"', $template);
        $this->assertStringContainsString('id="emsReturnWeight"', $template);
        $this->assertStringContainsString('name="peso"', $template);
        $this->assertStringContainsString('weight < 0.001 || weight > 150', $template);
    }

    public function test_el_servidor_bloquea_la_devolucion_del_contrato_sin_peso(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/PaquetesEmsController.php'));

        $this->assertStringContainsString("'peso' => ['nullable', 'numeric', 'min:0.001', 'max:150']", $controller);
        $this->assertStringContainsString('RecojoContrato::query()->whereKey($id)->lockForUpdate()->first()', $controller);
        $this->assertStringContainsString('if ($contrato && (float) $contrato->peso < 0.001)', $controller);
        $this->assertStringContainsString("'peso' => 'Ingrese un peso entre 0,001 y 150,000 kg para el paquete '", $controller);
        $this->assertStringContainsString('$contrato->forceFill([\'peso\' => $pesoRetorno])->save();', $controller);
    }
}

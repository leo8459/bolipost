<?php

namespace Tests\Unit;

use App\Models\Recojo;
use PHPUnit\Framework\TestCase;

class RecojoDestinoTest extends TestCase
{
    public function test_prioriza_el_destino_registrado_para_mostrar(): void
    {
        $recojo = new Recojo([
            'destino' => 'COCHABAMBA',
            'destino_registrado' => 'SANTA CRUZ',
        ]);

        $this->assertSame('SANTA CRUZ', $recojo->destinoParaMostrar());
    }

    public function test_usa_destino_como_respaldo_si_el_registrado_esta_vacio(): void
    {
        $recojo = new Recojo([
            'destino' => 'COCHABAMBA',
            'destino_registrado' => '   ',
        ]);

        $this->assertSame('COCHABAMBA', $recojo->destinoParaMostrar());
    }
}

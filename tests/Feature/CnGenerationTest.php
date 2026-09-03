<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CnGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('app_settings');
        parent::tearDown();
    }

    public function test_generation_screen_contains_destination_controls(): void
    {
        $response = $this->withoutMiddleware()->get(route('dashboard.generacion-cn'));

        $response->assertOk()
            ->assertViewIs('cn-generation.index')
            ->assertViewHas('countryDispatchCodes', fn (array $codes): bool => $codes['MX'] === 'MEX' && $codes['PE'] === 'LIM')
            ->assertSee('Generacion de CN')
            ->assertSee('Añadir destino')
            ->assertSee('Pais de destino')
            ->assertSee('LPB-LIM completa todas las filas con origen LPB, oficina LIM y destino LIM.');
    }

    public function test_it_generates_a_cn_route_sheet_pdf_with_multiple_countries(): void
    {
        $response = $this->withoutMiddleware()->post(route('dashboard.generacion-cn.pdf'), [
            'fecha' => '2026-09-03',
            'hoja_ruta' => 'CP-87',
            'despacho' => '29',
            'administracion_expedidora' => 'BO - BOLIVIA',
            'oficina_cambio' => 'LPB - LA PAZ',
            'servicio' => 'ENDA. INT. AEREO',
            'transporte' => 'AEREO',
            'itinerario' => 'LPB - LIM',
            'boletin' => 'CN-44',
            'rows' => [
                [
                    'pais_codigo' => 'PE',
                    'oficina_destino' => 'LIM - LIMA',
                    'envio' => 'MEX 07/1F',
                    'origen' => 'LPB',
                    'destino' => 'LIM',
                    'peso' => 11.9,
                    'valor_declarado' => 0,
                ],
                [
                    'pais_codigo' => 'MX',
                    'oficina_destino' => 'MEX - MEXICO',
                    'envio' => 'BUS 04/1F',
                    'origen' => 'LPB',
                    'destino' => 'MEX',
                    'peso' => 0.3,
                    'valor_declarado' => 0,
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('hoja-ruta-cn-29.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_generation_rejects_an_unknown_country(): void
    {
        $response = $this->withoutMiddleware()->from(route('dashboard.generacion-cn'))->post(route('dashboard.generacion-cn.pdf'), [
            'fecha' => '2026-09-03',
            'hoja_ruta' => 'CP-87',
            'despacho' => '29',
            'administracion_expedidora' => 'BO - BOLIVIA',
            'oficina_cambio' => 'LPB - LA PAZ',
            'servicio' => 'ENDA. INT. AEREO',
            'rows' => [[
                'pais_codigo' => 'XX',
                'oficina_destino' => 'XXX',
                'envio' => 'TEST-1',
                'origen' => 'LPB',
                'destino' => 'XXX',
                'peso' => 1,
            ]],
        ]);

        $response->assertRedirect(route('dashboard.generacion-cn'))
            ->assertSessionHasErrors('rows.0.pais_codigo');
    }
}

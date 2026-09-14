<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MarbeteGenerationTest extends TestCase
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

    public function test_marbetes_screen_contains_upu_receptacle_fields(): void
    {
        $response = $this->withoutMiddleware()->get(route('dashboard.marbetes'));

        $response->assertOk()
            ->assertViewIs('marbetes.index')
            ->assertViewHas('destinations', fn (array $destinations): bool => $destinations['PE']['impc'] === 'PELIMA' && $destinations['FR']['impc'] === 'FRPARA')
            ->assertSee('Marbetes CN 35')
            ->assertSee('IMPC origen')
            ->assertSee('Numero de receptaculo')
            ->assertSee('Generar marbete PDF');
    }

    public function test_it_generates_a_cn35_label_with_a_29_character_upu_identifier(): void
    {
        $response = $this->withoutMiddleware()->post(route('dashboard.marbetes.pdf'), $this->validPayload());

        $expectedCode = 'BOLPBAPELIMAAUN60077001110061';
        $this->assertSame(29, strlen($expectedCode));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString($expectedCode, (string) $response->headers->get('content-disposition'));
    }

    public function test_it_rejects_an_unknown_destination_country(): void
    {
        $payload = $this->validPayload();
        $payload['pais_codigo'] = 'XX';

        $response = $this->withoutMiddleware()->from(route('dashboard.marbetes'))->post(route('dashboard.marbetes.pdf'), $payload);

        $response->assertRedirect(route('dashboard.marbetes'))
            ->assertSessionHasErrors('pais_codigo');
    }

    /** @return array<string, mixed> */
    private function validPayload(): array
    {
        return [
            'fecha' => '2026-06-10',
            'origen_impc' => 'BOLPBA',
            'pais_codigo' => 'PE',
            'subclase' => 'UN',
            'numero_despacho' => 77,
            'numero_receptaculo' => 1,
            'ultimo_receptaculo' => 1,
            'registrado_asegurado' => 1,
            'cantidad_envios' => 0,
            'peso' => 6.1,
            'tipo_correo' => 'CERTIF. INT. AEREO',
            'vuelo' => 'OB1746',
            'tren' => '',
        ];
    }
}

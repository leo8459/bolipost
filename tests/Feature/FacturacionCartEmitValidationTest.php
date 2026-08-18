<?php

namespace Tests\Feature;

use App\Http\Controllers\FacturacionCartController;
use App\Models\User;
use App\Services\FacturacionCartService;
use App\Services\FacturacionClienteFrecuenteService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class FacturacionCartEmitValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_emitir_requires_document_number_and_razon_social(): void
    {
        $request = Request::create('/facturacion/cart/emitir', 'POST', [
            'modalidad_facturacion' => 'con_datos',
            'canal_emision' => 'factura_electronica',
            'tipo_documento' => '1',
            'numero_documento' => '   ',
            'razon_social' => '',
            'correo_facturacion' => 'safe@correos.gob.bo',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('feature.dashboard.facturacion')
            ->andReturn(true);

        $request->setUserResolver(static fn () => $user);

        $service = Mockery::mock(FacturacionCartService::class);
        $frequentClients = Mockery::mock(FacturacionClienteFrecuenteService::class);

        try {
            (new FacturacionCartController())->emitir($request, $service, $frequentClients);
            $this->fail('La emision debio fallar por validacion de datos fiscales obligatorios.');
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            $this->assertArrayHasKey('numero_documento', $errors);
            $this->assertArrayHasKey('razon_social', $errors);
        }
    }
}

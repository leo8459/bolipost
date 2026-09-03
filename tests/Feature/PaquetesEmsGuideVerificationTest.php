<?php

namespace Tests\Feature;

use App\Models\PaqueteEms;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaquetesEmsGuideVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('paquetes_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('origen')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('tipo_correspondencia')->nullable();
            $table->string('nombre_remitente')->nullable();
            $table->string('nombre_destinatario')->nullable();
            $table->unsignedInteger('cantidad')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->unsignedBigInteger('tarifario_id')->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_ems_formulario', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('paquete_ems_id');
            $table->string('direccion')->nullable();
            $table->string('referencia')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_ems_formulario');
        Schema::dropIfExists('paquetes_ems');
        parent::tearDown();
    }

    public function test_qr_token_opens_the_public_ems_guide_verification(): void
    {
        $paquete = $this->createPackage();
        $token = Crypt::encryptString((string) $paquete->id);

        $response = $this->get(route('paquetes-ems.verificar-guia', ['t' => $token]));

        $response->assertOk()
            ->assertViewIs('paquetes_ems.verificacion')
            ->assertSee('Guia EMS verificada')
            ->assertSee('EE000000001BO')
            ->assertSee('LA PAZ')
            ->assertSee('COCHABAMBA');
    }

    public function test_verification_can_render_the_ems_guide_pdf_with_its_qr(): void
    {
        $paquete = $this->createPackage();
        $token = Crypt::encryptString((string) $paquete->id);

        $response = $this->get(route('paquetes-ems.verificar-guia.pdf', ['t' => $token]));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('guia-ems-verificacion-EE000000001BO.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_invalid_verification_token_is_rejected(): void
    {
        $this->get(route('paquetes-ems.verificar-guia', ['t' => 'token-invalido']))
            ->assertNotFound();
    }

    private function createPackage(): PaqueteEms
    {
        return PaqueteEms::query()->create([
            'codigo' => 'EE000000001BO',
            'origen' => 'LA PAZ',
            'ciudad' => 'COCHABAMBA',
            'tipo_correspondencia' => 'EMS',
            'nombre_remitente' => 'REMITENTE PRUEBA',
            'nombre_destinatario' => 'DESTINATARIO PRUEBA',
            'cantidad' => 1,
            'peso' => 1.25,
            'precio' => 25,
        ]);
    }
}

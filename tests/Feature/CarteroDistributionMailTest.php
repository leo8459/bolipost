<?php

namespace Tests\Feature;

use App\Http\Controllers\CarterosController;
use App\Mail\PaqueteEmsDistribucionMail;
use App\Models\PaqueteEms;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CarteroDistributionMailTest extends TestCase
{
    public function test_al_asignar_al_cartero_notifica_solo_paquetes_ems_con_correo(): void
    {
        Mail::fake();

        $cartero = new User([
            'name' => 'Cartero Demo',
            'ciudad' => 'COCHABAMBA',
        ]);
        $cartero->id = 77;

        $conCorreo = new PaqueteEms([
            'correo_electronico' => 'cliente@example.com',
            'codigo' => 'EMS-DIST-1',
            'cod_especial' => 'CBB00001',
            'nombre_remitente' => 'Cliente Demo',
            'origen' => 'LA PAZ',
            'ciudad' => 'COCHABAMBA',
        ]);
        $conCorreo->id = 701;
        $conCorreo->setRelation('formulario', null);

        $sinCorreo = new PaqueteEms([
            'correo_electronico' => null,
            'codigo' => 'EMS-DIST-2',
        ]);
        $sinCorreo->id = 702;
        $sinCorreo->setRelation('formulario', null);

        $controller = new class extends CarterosController
        {
            public function enviarCorreosDistribucionDePrueba($paquetes, User $cartero): array
            {
                return $this->sendPaquetesDistribucionEmails($paquetes, $cartero, now());
            }
        };

        $resultado = $controller->enviarCorreosDistribucionDePrueba(
            collect([$conCorreo, $sinCorreo]),
            $cartero
        );

        $this->assertSame(['sent' => 1, 'failed' => 0, 'skipped' => 1], $resultado);
        Mail::assertSent(PaqueteEmsDistribucionMail::class, 1);
        Mail::assertSent(PaqueteEmsDistribucionMail::class, function (PaqueteEmsDistribucionMail $mail): bool {
            return $mail->hasTo('cliente@example.com')
                && $mail->paquete->codigo === 'EMS-DIST-1'
                && $mail->cartero->name === 'Cartero Demo';
        });
    }

    public function test_el_correo_de_distribucion_incluye_el_logo_institucional(): void
    {
        $template = file_get_contents(resource_path('views/emails/paquetes-ems/distribucion.blade.php'));

        $this->assertFileExists(public_path('images/AGBClogo2.png'));
        $this->assertStringContainsString("public_path('images/AGBClogo2.png')", $template);
        $this->assertStringContainsString('$message->embedData(', $template);
    }
}

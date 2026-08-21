<?php

namespace Tests\Feature;

use App\Livewire\PaquetesEms;
use App\Mail\PaqueteEmsRecibidoMail;
use App\Mail\PaqueteEmsRecibidoDestinoMail;
use App\Mail\PaqueteEmsSalidaRegionalMail;
use App\Models\PaqueteEms;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaqueteEmsRecibidoMailTest extends TestCase
{
    public function test_las_tablas_ems_tienen_correo_electronico_opcional(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_21_000000_add_correo_electronico_to_paquetes_ems_tables.php'));

        $this->assertStringContainsString("Schema::table('paquetes_ems'", $migration);
        $this->assertStringContainsString("Schema::table('paquetes_ems_formulario'", $migration);
        $this->assertSame(2, substr_count($migration, "string('correo_electronico')->nullable()"));
    }

    public function test_envia_confirmacion_con_boleta_cuando_se_registra_un_correo(): void
    {
        Mail::fake();

        $paquete = new PaqueteEms([
            'correo_electronico' => 'cliente@example.com',
            'codigo' => 'EMS-123',
            'nombre_remitente' => 'Cliente Demo',
            'nombre_destinatario' => 'Destinatario Demo',
            'origen' => 'LA PAZ',
            'ciudad' => 'SANTA CRUZ',
            'contenido' => 'Documentos',
            'peso' => 1.25,
        ]);
        $paquete->id = 123;
        $paquete->setRelation('tarifario', null);
        $paquete->setRelation('formulario', null);

        $component = new class extends PaquetesEms
        {
            public function enviarCorreoDePrueba(PaqueteEms $paquete): ?bool
            {
                return $this->sendPaqueteRecibidoEmail($paquete);
            }
        };

        $this->assertTrue($component->enviarCorreoDePrueba($paquete));

        Mail::assertSent(PaqueteEmsRecibidoMail::class, function (PaqueteEmsRecibidoMail $mail): bool {
            $attachment = $mail->attachments()[0] ?? null;

            return $mail->hasTo('cliente@example.com')
                && $mail->paquete->codigo === 'EMS-123'
                && $attachment?->as === 'boleta-ems-EMS-123.pdf'
                && $attachment?->mime === 'application/pdf';
        });
    }

    public function test_no_envia_correo_si_el_campo_esta_vacio(): void
    {
        Mail::fake();

        $paquete = new PaqueteEms(['correo_electronico' => null]);
        $component = new class extends PaquetesEms
        {
            public function enviarCorreoDePrueba(PaqueteEms $paquete): ?bool
            {
                return $this->sendPaqueteRecibidoEmail($paquete);
            }
        };

        $this->assertNull($component->enviarCorreoDePrueba($paquete));
        Mail::assertNothingSent();
    }

    public function test_el_correo_incluye_el_logo_institucional_embebido(): void
    {
        $template = file_get_contents(resource_path('views/emails/paquetes-ems/recibido.blade.php'));

        $this->assertFileExists(public_path('images/AGBClogo2.png'));
        $this->assertStringContainsString("public_path('images/AGBClogo2.png')", $template);
        $this->assertStringContainsString('$message->embedData(', $template);
    }

    public function test_al_enviar_a_regional_notifica_solo_paquetes_que_tienen_correo(): void
    {
        Mail::fake();

        $conCorreo = new PaqueteEms([
            'correo_electronico' => 'cliente@example.com',
            'codigo' => 'EMS-REG-1',
            'nombre_remitente' => 'Cliente Demo',
            'origen' => 'LA PAZ',
        ]);
        $conCorreo->id = 501;
        $conCorreo->setRelation('formulario', null);

        $sinCorreo = new PaqueteEms([
            'correo_electronico' => null,
            'codigo' => 'EMS-REG-2',
        ]);
        $sinCorreo->id = 502;
        $sinCorreo->setRelation('formulario', null);

        $component = new class extends PaquetesEms
        {
            public function enviarCorreosSalidaRegionalDePrueba($paquetes): array
            {
                return $this->sendPaquetesSalidaRegionalEmails(
                    $paquetes,
                    'SANTA CRUZ',
                    'TERRESTRE',
                    'SRZ00001',
                    now()
                );
            }
        };

        $resultado = $component->enviarCorreosSalidaRegionalDePrueba(collect([$conCorreo, $sinCorreo]));

        $this->assertSame(['sent' => 1, 'failed' => 0, 'skipped' => 1], $resultado);
        Mail::assertSent(PaqueteEmsSalidaRegionalMail::class, 1);
        Mail::assertSent(PaqueteEmsSalidaRegionalMail::class, function (PaqueteEmsSalidaRegionalMail $mail): bool {
            return $mail->hasTo('cliente@example.com')
                && $mail->paquete->codigo === 'EMS-REG-1'
                && $mail->destino === 'SANTA CRUZ'
                && $mail->manifiesto === 'SRZ00001';
        });
    }

    public function test_al_recibir_en_destino_notifica_solo_paquetes_que_tienen_correo(): void
    {
        Mail::fake();

        $conCorreo = new PaqueteEms([
            'correo_electronico' => 'cliente@example.com',
            'codigo' => 'EMS-DEST-1',
            'cod_especial' => 'CBB00001',
            'nombre_remitente' => 'Cliente Demo',
            'origen' => 'LA PAZ',
            'ciudad' => 'COCHABAMBA',
        ]);
        $conCorreo->id = 601;
        $conCorreo->setRelation('formulario', null);

        $sinCorreo = new PaqueteEms([
            'correo_electronico' => null,
            'codigo' => 'EMS-DEST-2',
            'ciudad' => 'COCHABAMBA',
        ]);
        $sinCorreo->id = 602;
        $sinCorreo->setRelation('formulario', null);

        $component = new class extends PaquetesEms
        {
            public function enviarCorreosRecepcionDestinoDePrueba($paquetes): array
            {
                return $this->sendPaquetesRecibidosDestinoEmails($paquetes, now());
            }
        };

        $resultado = $component->enviarCorreosRecepcionDestinoDePrueba(collect([$conCorreo, $sinCorreo]));

        $this->assertSame(['sent' => 1, 'failed' => 0, 'skipped' => 1], $resultado);
        Mail::assertSent(PaqueteEmsRecibidoDestinoMail::class, 1);
        Mail::assertSent(PaqueteEmsRecibidoDestinoMail::class, function (PaqueteEmsRecibidoDestinoMail $mail): bool {
            return $mail->hasTo('cliente@example.com')
                && $mail->paquete->codigo === 'EMS-DEST-1'
                && $mail->destino === 'COCHABAMBA';
        });
    }
}

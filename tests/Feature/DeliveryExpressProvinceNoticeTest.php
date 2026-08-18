<?php

namespace Tests\Feature;

use App\Mail\SolicitudClienteCreadaMail;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\Estado;
use App\Models\SolicitudCliente;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeliveryExpressProvinceNoticeTest extends TestCase
{
    public function test_email_and_printable_ticket_show_province_notice(): void
    {
        $solicitud = new SolicitudCliente([
            'codigo_solicitud' => 'SL00000001LP',
            'origen' => 'LA PAZ',
            'nombre_remitente' => 'CLIENTE DEMO',
            'telefono_remitente' => '70000000',
            'direccion_recojo' => 'ZONA CENTRAL',
            'nombre_destinatario' => 'DESTINATARIO DEMO',
            'telefono_destinatario' => '71111111',
            'direccion' => 'AVENIDA PRINCIPAL',
            'contenido' => 'DOCUMENTOS',
            'cantidad' => 1,
            'peso' => 1.25,
            'precio' => 20,
        ]);
        $solicitud->created_at = Carbon::parse('2026-08-18 10:41:00');
        $solicitud->setRelation('destino', new Destino(['nombre_destino' => 'SANTA CRUZ']));
        $solicitud->setRelation('estadoRegistro', new Estado(['nombre_estado' => 'SOLICITUD']));

        $cliente = new Cliente([
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
        ]);

        $expectedNotice = 'Importante: Si registro una provincia para el envio, la solicitud no sera recogida ni validada.';
        $emailHtml = (new SolicitudClienteCreadaMail($solicitud, $cliente))->render();
        $ticketHtml = view('paquetes_ems.solicitud-ticket', ['solicitud' => $solicitud])->render();

        $this->assertStringContainsString($expectedNotice, $this->normalizedText($emailHtml));
        $this->assertStringContainsString($expectedNotice, $this->normalizedText($ticketHtml));
    }

    private function normalizedText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n', 'A', 'E', 'I', 'O', 'U', 'N'],
            $text
        );

        return preg_replace('/\s+/', ' ', $text) ?: '';
    }
}

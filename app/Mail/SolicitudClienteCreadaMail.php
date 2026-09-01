<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\SolicitudCliente;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SolicitudClienteCreadaMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SolicitudCliente $solicitud,
        public Cliente $cliente,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Solicitud registrada '.$this->solicitud->codigo_solicitud.' | Correos de Bolivia',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitudes.cliente-creada',
        );
    }

    public function attachments(): array
    {
        $codigo = trim((string) $this->solicitud->codigo_solicitud);

        return [
            Attachment::fromData(function (): string {
                $this->solicitud->loadMissing([
                    'estadoRegistro:id,nombre_estado',
                    'servicioExtra:id,nombre,descripcion',
                    'destino:id,nombre_destino',
                ]);

                return Pdf::loadView('paquetes_ems.solicitud-ticket', [
                    'solicitud' => $this->solicitud,
                    'isPdf' => true,
                ])->setPaper([0, 0, 226.77, 950])->output();
            }, 'solicitud-'.($codigo !== '' ? $codigo : $this->solicitud->id).'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}

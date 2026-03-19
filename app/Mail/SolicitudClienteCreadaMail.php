<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\SolicitudCliente;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
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
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Codigo de seguimiento de tu solicitud ' . $this->solicitud->codigo_solicitud,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.solicitudes.cliente-creada',
        );
    }
}

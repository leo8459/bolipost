<?php

namespace App\Mail;

use App\Models\PaqueteEms;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaqueteEmsRecibidoDestinoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PaqueteEms $paquete,
        public string $destino,
        public $fechaRecepcion,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Paquete en departamento de destino: ' . $this->destino . ' | Correos de Bolivia',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.paquetes-ems.recibido-destino',
        );
    }
}

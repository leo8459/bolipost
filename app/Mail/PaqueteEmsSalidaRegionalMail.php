<?php

namespace App\Mail;

use App\Models\PaqueteEms;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaqueteEmsSalidaRegionalMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PaqueteEms $paquete,
        public string $destino,
        public string $transporte,
        public string $manifiesto,
        public $fechaSalida,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu paquete EMS partió hacia ' . $this->destino . ' | Correos de Bolivia',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.paquetes-ems.salida-regional',
        );
    }
}

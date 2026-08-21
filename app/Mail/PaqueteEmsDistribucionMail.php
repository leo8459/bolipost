<?php

namespace App\Mail;

use App\Models\PaqueteEms;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaqueteEmsDistribucionMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PaqueteEms $paquete,
        public User $cartero,
        public $fechaSalida,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu paquete salió para su distribución y entrega | Correos de Bolivia',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.paquetes-ems.distribucion',
        );
    }
}

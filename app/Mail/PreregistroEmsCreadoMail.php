<?php

namespace App\Mail;

use App\Models\Preregistro;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PreregistroEmsCreadoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Preregistro $preregistro)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Preenvio EMS ' . $this->preregistro->codigo_preregistro . ' | Correos de Bolivia',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.preregistros.ems-creado',
        );
    }
}

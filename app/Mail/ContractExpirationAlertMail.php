<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ContractExpirationAlertMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Collection $alerts) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aviso de vencimiento de contratos | Correos de Bolivia',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contracts.expiration-alert');
    }
}

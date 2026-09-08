<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApiCorreoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public string $asunto,
        public string $mensaje,
        public string $formato = 'texto',
        public ?string $responderA = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto,
            replyTo: $this->responderA
                ? [new Address($this->responderA)]
                : [],
        );
    }

    public function content(): Content
    {
        $html = $this->formato === 'html'
            ? $this->mensaje
            : '<div style="white-space: pre-wrap; font-family: Arial, sans-serif;">'.e($this->mensaje).'</div>';

        return new Content(htmlString: $html);
    }
}

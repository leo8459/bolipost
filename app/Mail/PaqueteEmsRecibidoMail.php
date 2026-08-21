<?php

namespace App\Mail;

use App\Models\PaqueteEms;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaqueteEmsRecibidoMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public PaqueteEms $paquete)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Paquete EMS recibido ' . $this->paquete->codigo . ' | Correos de Bolivia',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.paquetes-ems.recibido',
        );
    }

    public function attachments(): array
    {
        $codigo = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $this->paquete->codigo) ?: (string) $this->paquete->id;

        return [
            Attachment::fromData(function (): string {
                return Pdf::loadView('paquetes_ems.boleta', [
                    'paquete' => $this->paquete,
                ])->setPaper([0, 0, 226.77, 595.28], 'portrait')->output();
            }, 'boleta-ems-' . $codigo . '.pdf')->withMime('application/pdf'),
        ];
    }
}

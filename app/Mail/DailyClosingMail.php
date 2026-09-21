<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DailyClosingMail extends Mailable
{
    use Queueable;

    public function __construct(public array $report) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cierre diario '.$this->report['date'].' | Contratos y EMS');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-closing');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(
            fn () => \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\DailyClosingExport($this->report), \Maatwebsite\Excel\Excel::XLSX),
            'cierre-diario-'.$this->report['date'].'.xlsx'
        )->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')];
    }
}
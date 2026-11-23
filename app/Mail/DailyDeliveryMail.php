<?php

namespace App\Mail;

use App\Exports\DailyDeliveryExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class DailyDeliveryMail extends Mailable
{
    public function __construct(public array $report) {}

    public function envelope(): Envelope
    {
        $scope = $this->report['regional'] ?? 'NACIONAL';

        return new Envelope(subject: 'Entregas diarias '.$this->report['date'].' | EMS y Contratos | '.$scope);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-deliveries');
    }

    public function attachments(): array
    {
        $filename = 'entregas-diarias-'.$this->report['date'];
        if (isset($this->report['regional'])) {
            $filename .= '-'.Str::slug($this->report['regional']);
        }

        return [
            Attachment::fromData($this->pdfContents(...), $filename.'.pdf')->withMime('application/pdf'),
            Attachment::fromData(
                fn () => Excel::raw(new DailyDeliveryExport($this->report), ExcelFormat::XLSX),
                $filename.'.xlsx'
            )->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ];
    }

    private function pdfContents(): string
    {
        $pdf = Pdf::loadView('emails.pdf.daily-deliveries', ['report' => $this->report])
            ->setPaper('A4', 'landscape')
            ->setOptions(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false]);
        $pdf->render();

        $document = $pdf->getDomPDF();
        $canvas = $document->getCanvas();
        $canvas->page_text(
            $canvas->get_width() - 115,
            $canvas->get_height() - 26,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $document->getFontMetrics()->getFont('DejaVu Sans'),
            8,
            [0.36, 0.42, 0.52]
        );

        return $pdf->output();
    }
}

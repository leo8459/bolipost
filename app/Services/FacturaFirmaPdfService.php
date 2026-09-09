<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Smalot\PdfParser\Parser;

class FacturaFirmaPdfService
{
    public function appendSignatureFields(string $source): string
    {
        $pdf = new class extends Fpdi
        {
            public function useClippedInvoice($template, float $width, float $height, float $top, float $visibleHeight): void
            {
                if ($visibleHeight >= $height) {
                    $this->useTemplate($template, 0, $top, $width, $height);
                    return;
                }
                $this->_out(sprintf('q 0 %.4F %.4F %.4F re W n',
                    ($this->h - $top - $visibleHeight) * $this->k,
                    $width * $this->k, $visibleHeight * $this->k));
                $this->useTemplate($template, 0, $top, $width, $height);
                $this->_out('Q');
            }

            public function useClippedInvoiceSection($template, float $width, float $height, float $sourceTop, float $destTop, float $sectionHeight): void
            {
                if ($sectionHeight <= 0) {
                    return;
                }

                $this->_out(sprintf('q 0 %.4F %.4F %.4F re W n',
                    ($this->h - $destTop - $sectionHeight) * $this->k,
                    $width * $this->k,
                    $sectionHeight * $this->k));
                $this->useTemplate($template, 0, $destTop - $sourceTop, $width, $height);
                $this->_out('Q');
            }
        };
        $pdf->SetAutoPageBreak(false);
        $pageCount = $pdf->setSourceFile(StreamReader::createByString($source));
        $logoPath = dirname(__DIR__, 2) . '/public/images/LOGO 19-2-26.png';
        [$logoWidth, $logoHeight] = getimagesize($logoPath);

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $isLastPage = $page === $pageCount;
            $footerLayout = $isLastPage ? $this->findFiscalFooterLayout($source, $size['height']) : [];
            $footerStart = $footerLayout['lawTop'] ?? null;
            $contributionStart = $footerLayout['contributionTop'] ?? null;
            $signatureInsertTop = $footerLayout['signatureInsertTop'] ?? null;
            $moveContribution = $footerStart !== null && $contributionStart !== null && $contributionStart < $footerStart;
            $insertBeforeQr = $moveContribution && $signatureInsertTop !== null && $signatureInsertTop < $contributionStart;
            $imageWidth = min(60, $size['width'] * 0.84, 24 * $logoWidth / $logoHeight);
            $imageHeight = $imageWidth * $logoHeight / $logoWidth;
            // The original invoice already includes its own top margin.
            $headerHeight = $page === 1 ? 3 + $imageHeight : 0;
            $visibleHeight = $insertBeforeQr
                ? min($size['height'], $signatureInsertTop)
                : ($moveContribution
                    ? min($size['height'], $contributionStart)
                    : ($isLastPage && $footerStart !== null
                        ? min($size['height'], $footerStart)
                        : $size['height']));
            $signatureBlockHeight = $moveContribution ? 52 : 48;
            $tailHeight = $insertBeforeQr ? max(0, $contributionStart - $signatureInsertTop - 6) : 0;
            $contributionBlockHeight = $moveContribution ? 15 : 0;
            $contentBottom = $headerHeight + $visibleHeight;
            $height = $contentBottom + ($isLastPage ? $signatureBlockHeight + $tailHeight + $contributionBlockHeight : 0);
            $pdf->AddPage($size['width'] > $height ? 'L' : 'P', [$size['width'], $height]);
            if ($page === 1) {
                $pdf->Image($logoPath, ($size['width'] - $imageWidth) / 2, 3, $imageWidth, $imageHeight);
            }
            $pdf->useClippedInvoice($template, $size['width'], $size['height'], $headerHeight, $visibleHeight);

            if ($isLastPage) {
                if ($footerStart !== null || $insertBeforeQr) {
                    $pdf->SetFillColor(255, 255, 255);
                    $pdf->Rect(0, $contentBottom, $size['width'], $signatureBlockHeight, 'F');
                }
                $margin = min(10, $size['width'] * 0.08);
                $lineEnd = $size['width'] - $margin;
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetTextColor(0);
                $pdf->SetDrawColor(0);
                $pdf->SetLineWidth(0.2);
                $pdf->Text($margin, $contentBottom + 7, 'Firma:');
                $pdf->Line($margin, $contentBottom + 15, $lineEnd, $contentBottom + 15);
                $pdf->Text($margin, $contentBottom + 23, 'Nombre completo:');
                $pdf->Line($margin, $contentBottom + 31, $lineEnd, $contentBottom + 31);
                $pdf->SetFont('Helvetica', '', 8);
                $conformidad = 'Declaro mi conformidad con la admision y/o recojo de la paqueteria registrada en este comprobante.';
                $pdf->Text($margin, $contentBottom + 41, $conformidad);
                if ($insertBeforeQr) {
                    $pdf->useClippedInvoiceSection(
                        $template,
                        $size['width'],
                        $size['height'],
                        $signatureInsertTop,
                        $contentBottom + $signatureBlockHeight,
                        $tailHeight
                    );
                    $contentBottom += $signatureBlockHeight + $tailHeight;
                } elseif ($moveContribution) {
                    $contentBottom += $signatureBlockHeight;
                }
                if ($moveContribution) {
                    $pdf->SetFont('Courier', 'B', 7);
                    foreach ([
                        'ESTA FACTURA CONTRIBUYE AL DESARROLLO DEL',
                        'PAIS, EL USO ILICITO SERA SANCIONADO',
                        'PENALMENTE DE ACUERDO A LEY',
                    ] as $index => $line) {
                    $pdf->Text(($size['width'] - $pdf->GetStringWidth($line)) / 2, $contentBottom + 7 + ($index * 3.5), $line);
                    }
                }
            }
        }

        return $pdf->Output('S');
    }

    private function findFiscalFooterStart(string $source, float $pageHeight): ?float
    {
        return $this->findFiscalFooterLayout($source, $pageHeight)['lawTop'] ?? null;
    }

    private function findFiscalFooterLayout(string $source, float $pageHeight): array
    {
        try {
            $document = (new Parser())->parseContent($source);
            $pages = $document->getPages();
            $lastPage = end($pages);
            if (!$lastPage) {
                return [];
            }
            // Parse the page's own text stream; the parser's FPDF shortcut assumes
            // imported form objects even for invoices generated directly by FPDF.
            if ($lastPage->isFpdf()) {
                $lastPage = new class($document, $lastPage->getHeader(), $lastPage->getContent(), new \Smalot\PdfParser\Config()) extends \Smalot\PdfParser\Page
                {
                    public function isFpdf(): bool
                    {
                        return false;
                    }
                };
            }
            $details = $lastPage->getDetails();
            $box = $details['CropBox'] ?? $details['MediaBox'] ?? [0, 0, 0, $pageHeight * 72 / 25.4];
            if (!is_array($box) || count($box) !== 4 || (float) $box[0] !== 0.0
                || (float) $box[1] !== 0.0 || (int) ($details['Rotate'] ?? 0) !== 0) {
                return [];
            }
            $lawTop = null;
            $contributionTop = null;
            $representationTop = null;
            $sonTop = null;
            $signatureInsertTop = null;
            $lines = [];
            foreach ($lastPage->getDataTm() as [$matrix, $text]) {
                $top = $pageHeight - (float) $matrix[5] * 25.4 / 72;
                if ($top < $pageHeight / 2 || $top > $pageHeight) {
                    continue;
                }
                // PDF generators may emit every word or glyph as a separate text
                // operation. Rebuild each printed line before matching its heading.
                $lineKey = (string) round($top, 1);
                $lines[$lineKey][] = ['x' => (float) $matrix[4], 'top' => $top, 'text' => $text];
            }
            foreach ($lines as $fragments) {
                usort($fragments, fn ($a, $b) => $a['x'] <=> $b['x']);
                $text = mb_strtolower(implode('', array_column($fragments, 'text')));
                $text = preg_replace('/[\s\x{00AD}\x{200B}]+/u', '', $text) ?? $text;
                $top = min(array_column($fragments, 'top'));
                if (preg_match('/^ley.{0,8}453\b/u', $text)) {
                    $lawTop = $top;
                }
                if (str_starts_with($text, 'son:') || ($sonTop !== null && str_contains($text, 'bolivianos'))) {
                    $sonTop = $top;
                }
                if ($sonTop !== null && $top > $sonTop && $signatureInsertTop === null
                    && preg_match('/^[\-\x{2014}\x{2013}_=]{8,}$/u', $text)) {
                    $signatureInsertTop = $top + 1.2;
                }
                if (str_starts_with($text, 'estafacturacontribuyealdesarrollo')) {
                    $contributionTop = $top;
                }
                if (str_starts_with($text, 'estedocumentoesla')) {
                    $representationTop = $top;
                }
            }

            // Only crop when both expected footer headings are identified in order.
            if ($lawTop === null || $representationTop === null || $representationTop <= $lawTop) {
                return [];
            }

            return [
                'contributionTop' => $contributionTop !== null ? max(0, $contributionTop - 2) : null,
                'lawTop' => max(0, $lawTop - 2.6),
                'signatureInsertTop' => $signatureInsertTop,
            ];
        } catch (\Throwable) {
            // An unrecognized layout must retain all invoice content.
            return [];
        }
    }
}

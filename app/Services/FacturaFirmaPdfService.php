<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Smalot\PdfParser\Parser;

class FacturaFirmaPdfService
{
    public function appendSignatureFields(string $source, array $delivery = []): string
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
        $deliveryPageWidth = 0.0;

        for ($page = 1; $page <= $pageCount; $page++) {
            $template = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($template);
            $isLastPage = $page === $pageCount;
            if ($isLastPage) {
                $deliveryPageWidth = (float) $size['width'];
            }
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
            $signatureBlockHeight = 0;
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
                    $contentBottom += $contributionBlockHeight;
                }
            }
        }

        $deliveryPageHeight = $deliveryPageWidth > 0
            ? $this->deliveryBlockHeight($delivery, $deliveryPageWidth)
            : 0;

        if ($deliveryPageHeight > 0) {
            for ($copy = 1; $copy <= 2; $copy++) {
                $pdf->AddPage('P', [$deliveryPageWidth, $deliveryPageHeight]);
                $this->drawDeliveryVoucher($pdf, $delivery, 0, $deliveryPageWidth, $copy, 2);
            }
        }

        return $pdf->Output('S');
    }

    private function deliveryBlockHeight(array $delivery, float $width): float
    {
        if ($this->deliveryPackages($delivery) === []) {
            return 0;
        }

        $height = 68 + $this->deliveryPackagesHeight($delivery, $width);

        return max($height, $width + 1);
    }

    private function drawDeliveryVoucher(Fpdi $pdf, array $delivery, float $top, float $width, int $copy = 1, int $totalCopies = 1): void
    {
        $packages = $this->deliveryPackages($delivery);
        if ($packages === []) {
            return;
        }

        $left = 5.0;
        $right = max($left + 45, $width - 5);
        $contentWidth = $right - $left;
        $y = $top + 0.8;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(40, 40, 40);
        $this->drawDashedLine($pdf, $left, $y, $right, $y);

        $y += 4.2;
        $pdf->SetFont('Courier', 'B', 9.5);
        $title = 'FORMULARIO DE ENTREGA';
        $pdf->Text(($width - $pdf->GetStringWidth($title)) / 2, $y + 4.8, $title);
        if ($totalCopies > 1) {
            $copyLabel = 'COPIA ' . $copy . '/' . $totalCopies;
            $pdf->SetFont('Courier', 'B', 5.8);
            $pdf->Text($right - $pdf->GetStringWidth($copyLabel), $y + 4.8, $copyLabel);
        }
        $y += 7.5;

        $pdf->SetFont('Courier', 'B', 8);
        $pdf->Text($left, $y, 'Paquetes:');
        $y += 4.8;
        foreach ($packages as $package) {
            $y = $this->drawDeliveryPackage($pdf, $left, $right, $y, $package);
        }
        $y += 3;

        $y = $this->drawDeliveryValueRow(
            $pdf,
            $left,
            $right,
            $y,
            'Usuario',
            $this->deliveryValue($delivery, 'usuario', '-')
        );
        $y = $this->drawDeliveryValueRow(
            $pdf,
            $left,
            $right,
            $y,
            'Nro. Factura',
            $this->deliveryValue($delivery, 'numero_factura', '-')
        );

        $y += 4;
        $y = $this->drawDeliveryDateRow(
            $pdf,
            $left,
            $right,
            $y,
            $this->deliveryValue($delivery, 'fecha_entrega', '-')
        );
        $y = $this->drawDeliveryBlankRow($pdf, $left, $right, $y + 3, 'RECIBIDO POR');
        $y = $this->drawDeliveryBlankRow($pdf, $left, $right, $y + 2, 'FIRMA');

        $y += 4;
        $pdf->SetFont('Courier', 'B', 7);
        foreach ($this->deliveryCopyFooterLines($copy) as $line) {
            $this->centerText($pdf, $line, $width, $y);
            $y += 3.6;
        }
    }

    private function deliveryCopyFooterLines(int $copy): array
    {
        if ($copy >= 2) {
            return [
                'Conserve este comprobante para respaldo de entrega.',
                'Copia para Aduana.',
            ];
        }

        return [
            'Conserve este comprobante como respaldo de entrega.',
            'Copia para Correos de Bolivia.',
        ];
    }

    private function deliveryPackagesHeight(array $delivery, float $width): float
    {
        $packages = $this->deliveryPackages($delivery);
        if ($packages === []) {
            return 0;
        }

        return 8 + (count($packages) * 14) + 3;
    }

    private function deliveryPackages(array $delivery): array
    {
        $packages = $delivery['packages'] ?? [];
        if (!is_array($packages)) {
            $packages = [];
        }

        $packages = array_values(array_filter(array_map(function ($package): array {
            if (is_array($package)) {
                return [
                    'codigo' => trim((string) ($package['codigo'] ?? '')),
                    'peso' => trim((string) ($package['peso'] ?? $package['peso_kg'] ?? $package['weight'] ?? '')),
                    'monto' => trim((string) ($package['monto'] ?? $package['precio'] ?? $package['importe'] ?? '')),
                ];
            }

            return [
                'codigo' => trim((string) $package),
                'peso' => '',
                'monto' => '',
            ];
        }, $packages), fn (array $package) => $package['codigo'] !== ''));

        return $packages;
    }

    private function drawDeliveryPackage(Fpdi $pdf, float $left, float $right, float $y, array $package): float
    {
        $code = $this->pdfText((string) ($package['codigo'] ?? ''));
        $weight = $this->pdfText((string) ($package['peso'] ?? ''));
        $amount = $this->pdfText((string) ($package['monto'] ?? ''));
        $rowHeight = 13.5;
        $barcodeWidth = min(50, $right - $left - 4);
        $barcodeHeight = 8.5;
        $barcodeX = $left + (($right - $left - $barcodeWidth) / 2);
        $barcodeY = $y + 0.8;

        $barcodePath = $this->barcodePngPath($code);

        if ($barcodePath !== null) {
            try {
                $pdf->Image($barcodePath, $barcodeX, $barcodeY, $barcodeWidth, $barcodeHeight, 'PNG');
            } finally {
                @unlink($barcodePath);
            }
        }

        $pdf->SetFont('Courier', 'B', 7.2);
        $packageText = trim($code
            . ($weight !== '' ? '     ' . $weight : '')
            . ($amount !== '' ? '     ' . $amount : ''));
        $textX = $left + ((($right - $left) - $pdf->GetStringWidth($packageText)) / 2);
        $pdf->Text(max($left, $textX), $barcodeY + $barcodeHeight + 3.2, $packageText);

        return $y + $rowHeight;
    }

    private function barcodePngPath(string $code): ?string
    {
        if ($code === '' || !class_exists(\Milon\Barcode\DNS1D::class)) {
            return null;
        }

        try {
            $barcode = (new \Milon\Barcode\DNS1D())->getBarcodePNG($code, 'C128', 1.5, 36);
            if (!is_string($barcode) || $barcode === '') {
                return null;
            }

            $path = tempnam(sys_get_temp_dir(), 'delivery-barcode-');
            if ($path === false) {
                return null;
            }

            file_put_contents($path, base64_decode($barcode, true) ?: '');

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private function drawDeliveryValueRow(Fpdi $pdf, float $left, float $right, float $y, string $label, string $value): float
    {
        $label = $this->pdfText($label . ':');
        $value = $this->pdfText($value);
        $labelWidth = 34;
        $valueWidth = max(20, $right - $left - $labelWidth);

        $pdf->SetFont('Courier', 'B', 8);
        $pdf->SetXY($left, $y);
        $pdf->Cell($labelWidth, 5, $label, 0, 0);
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->SetXY($left + $labelWidth, $y);
        $pdf->MultiCell($valueWidth, 5, $value, 0, 'L');

        return max($pdf->GetY(), $y + 5);
    }

    private function drawDeliveryDateRow(Fpdi $pdf, float $left, float $right, float $y, string $value): float
    {
        $labelWidth = 34;
        $valueWidth = max(20, $right - $left - $labelWidth);

        $pdf->SetFont('Courier', 'B', 8);
        $pdf->Text($left, $y, $this->pdfText('Fecha Entrega:'));
        $pdf->SetFont('Courier', 'B', 6.2);
        $pdf->SetXY($left + $labelWidth, $y - 3.8);
        $pdf->MultiCell($valueWidth, 4.2, $this->pdfText($value), 0, 'L');

        return max($pdf->GetY() + 2.2, $y + 6.5);
    }

    private function drawDeliveryBlankRow(Fpdi $pdf, float $left, float $right, float $y, string $label): float
    {
        $label = $this->pdfText($label . ':');
        $labelWidth = 33;

        $pdf->SetFont('Courier', 'B', 8);
        $pdf->Text($left, $y, $label);
        $pdf->Line($left + $labelWidth, $y + .6, $right, $y + .6);

        return $y + 7;
    }

    private function drawDashedLine(Fpdi $pdf, float $x1, float $y, float $x2, float $y2): void
    {
        $dash = 2.2;
        $gap = 1.4;
        for ($x = $x1; $x < $x2; $x += $dash + $gap) {
            $pdf->Line($x, $y, min($x + $dash, $x2), $y2);
        }
    }

    private function centerText(Fpdi $pdf, string $text, float $width, float $y): void
    {
        $text = $this->pdfText($text);
        $pdf->Text(max(2, ($width - $pdf->GetStringWidth($text)) / 2), $y, $text);
    }

    private function deliveryValue(array $delivery, string $key, string $fallback): string
    {
        $value = trim((string) ($delivery[$key] ?? ''));

        return $value !== '' ? $value : $fallback;
    }

    private function pdfText(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $converted !== false ? $converted : $value;
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

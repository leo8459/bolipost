<?php

namespace App\Services;

require_once dirname(__DIR__, 2) . '/vendor/setasign/fpdf/fpdf.php';

class TarifaContratoDetallePdf extends \FPDF
{
    private const MARGIN_X = 7.5;
    private const ROW_LINE_HEIGHT = 4.6;
    private const TABLE_WIDTHS = [82, 29, 29, 55, 28, 28, 20, 24, 35, 35, 40];
    private const TABLE_HEADERS = [
        'Servicio',
        'Origen',
        'Destino',
        'Dirección',
        'Zona',
        'Peso 1 a 2 kg',
        'Kilo',
        'Kilo extra',
        'Provincia',
        'Provincia origen',
        'Hora de entrega',
    ];

    private string $generatedAt;

    private string $companyName = '';

    private string $companyCode = '';

    private string $serviceTitle = '';

    private string $serviceVariants = '';

    private int $serviceCount = 0;

    private int $serviceRowsDrawn = 0;

    public function __construct(string $generatedAt)
    {
        parent::__construct('L', 'mm', 'A3');

        $this->generatedAt = $generatedAt;
        $this->SetMargins(self::MARGIN_X, 8, self::MARGIN_X);
        $this->SetAutoPageBreak(false);
        $this->AliasNbPages();
        $this->SetTitle('Detalle de Tarifario de Contratos');
        $this->SetAuthor('BOLIPOST');
        $this->SetSubject('Tarifas de contrato agrupadas por empresa y servicio');
    }

    public function beginCompany(string $name, string $code): void
    {
        $this->companyName = $name;
        $this->companyCode = $code;
        $this->serviceTitle = '';
        $this->serviceVariants = '';
        $this->serviceCount = 0;
        $this->serviceRowsDrawn = 0;

        if ($this->PageNo() === 0 || $this->GetY() + 42 > 284) {
            // Header() prints this company's heading on the new page.
            $this->AddPage('L', 'A3');

            return;
        }

        $this->drawCompanyHeading($this->GetY() + 2);
    }

    public function beginServiceGroup(string $title, array $services, int $count = 0): void
    {
        $this->serviceTitle = $title;
        $this->serviceVariants = implode(' · ', $services);
        $this->serviceCount = $count;
        $this->serviceRowsDrawn = 0;

        if ($this->GetY() + 34 > 284) {
            // Header() prints the company and this service heading on the new page.
            $this->AddPage('L', 'A3');

            return;
        }

        $this->drawServiceHeading($this->GetY() + 1);
    }

    public function addTariffRow(array $cells): void
    {
        $this->SetFont('Arial', '', 9);
        $cells = array_map(fn ($cell): string => $this->toPdfText((string) ($cell ?? '—')), $cells);
        $lineCounts = [];

        foreach (self::TABLE_WIDTHS as $index => $width) {
            $lineCounts[] = $this->lineCount($width, $cells[$index] ?? '—');
        }

        $height = max(7.2, (max($lineCounts) * self::ROW_LINE_HEIGHT) + 2);

        if ($this->GetY() + $height > 284) {
            $this->AddPage('L', 'A3');
        }

        $x = self::MARGIN_X;
        $y = $this->GetY();
        $fill = $this->serviceRowsDrawn % 2 === 1;

        $this->SetDrawColor(211, 222, 232);
        $this->SetLineWidth(0.15);
        $this->SetTextColor(39, 56, 76);
        $this->SetFillColor($fill ? 246 : 255, $fill ? 249 : 255, $fill ? 251 : 255);

        foreach (self::TABLE_WIDTHS as $index => $width) {
            $this->Rect($x, $y, $width, $height, $fill ? 'DF' : 'D');
            $this->SetXY($x, $y + 0.8);
            $this->MultiCell($width, self::ROW_LINE_HEIGHT, $cells[$index] ?? '—', 0, 'L', false);
            $x += $width;
        }

        $this->SetY($y + $height);
        $this->serviceRowsDrawn++;
    }

    public function outputPdf(): string
    {
        return $this->Output('S');
    }

    public function Header(): void
    {
        $left = self::MARGIN_X;
        $tableWidth = array_sum(self::TABLE_WIDTHS);

        $this->SetFillColor(18, 164, 154);
        $this->Rect($left, 7, $tableWidth, 1.3, 'F');

        $this->SetTextColor(23, 54, 93);
        $this->SetFont('Arial', 'B', 16);
        $this->SetXY($left, 9);
        $this->Cell(240, 7, $this->toPdfText('Detalle de Tarifario de Contratos'), 0, 0, 'L');

        $this->SetTextColor(82, 100, 123);
        $this->SetFont('Arial', '', 8);
        $this->SetXY($left + 240, 10);
        $this->Cell($tableWidth - 240, 5, $this->toPdfText('Generado: ' . $this->generatedAt), 0, 0, 'R');

        $this->SetDrawColor(18, 164, 154);
        $this->SetLineWidth(0.45);
        $this->Line($left, 18, $left + $tableWidth, 18);
        $this->SetY(20);

        if ($this->companyName !== '') {
            $this->drawCompanyHeading(21);
        }

        if ($this->serviceTitle !== '') {
            $this->drawServiceHeading($this->companyName !== '' ? 31 : 21);
        }
    }

    public function Footer(): void
    {
        $left = self::MARGIN_X;
        $tableWidth = array_sum(self::TABLE_WIDTHS);

        $this->SetDrawColor(211, 222, 232);
        $this->SetLineWidth(0.2);
        $this->Line($left, 288, $left + $tableWidth, 288);
        $this->SetY(-8);
        $this->SetTextColor(113, 128, 150);
        $this->SetFont('Arial', '', 7);
        $this->Cell($tableWidth, 4, $this->toPdfText('BOLIPOST  |  Tarifario de contratos  |  Página ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }

    private function drawCompanyHeading(float $top): void
    {
        $left = self::MARGIN_X;
        $tableWidth = array_sum(self::TABLE_WIDTHS);

        $this->SetFillColor(232, 242, 247);
        $this->Rect($left, $top, $tableWidth, 8, 'F');
        $this->SetFillColor(18, 164, 154);
        $this->Rect($left, $top, 1.6, 8, 'F');

        $this->SetTextColor(23, 54, 93);
        $this->SetFont('Arial', 'B', 12);
        $this->SetXY($left + 4, $top + 1.5);
        $this->Cell($tableWidth - 100, 5, $this->toPdfText('EMPRESA: ' . $this->companyName), 0, 0, 'L');

        $this->SetTextColor(41, 73, 105);
        $this->SetFont('Arial', 'B', 11.5);
        $this->SetXY($left + $tableWidth - 96, $top + 1.5);
        $this->Cell(92, 5, $this->toPdfText('CÓDIGO: ' . $this->companyCode), 0, 0, 'R');

        $this->SetY($top + 9);
    }

    private function drawServiceHeading(float $top): void
    {
        $left = self::MARGIN_X;
        $tableWidth = array_sum(self::TABLE_WIDTHS);

        $this->SetFillColor(244, 248, 250);
        $this->Rect($left, $top, $tableWidth, 6.5, 'F');
        $this->SetFillColor(54, 94, 132);
        $this->Rect($left, $top, 1.2, 6.5, 'F');

        $this->SetTextColor(23, 54, 93);
        $this->SetFont('Arial', 'B', 10.5);
        $this->SetXY($left + 3.5, $top + 1.1);
        $this->Cell($tableWidth - 75, 4.5, $this->toPdfText($this->serviceTitle), 0, 0, 'L');

        $this->SetTextColor(82, 100, 123);
        $this->SetFont('Arial', '', 8);
        $this->SetXY($left + $tableWidth - 70, $top + 1.1);
        $this->Cell(67, 4.5, $this->toPdfText(number_format($this->serviceCount, 0, ',', '.') . ' tarifas'), 0, 0, 'R');

        $this->SetTextColor(100, 116, 139);
        $this->SetFont('Arial', '', 7.2);
        $this->SetXY($left, $top + 6.8);
        $this->Cell($tableWidth, 3, $this->toPdfText('Incluye: ' . $this->serviceVariants), 0, 0, 'L');

        $this->drawTableHeader($left, $top + 10.2);
    }

    private function drawTableHeader(float $left, float $top): void
    {
        $this->SetFont('Arial', 'B', 8);
        $this->SetDrawColor(36, 77, 115);
        $this->SetFillColor(36, 77, 115);
        $this->SetTextColor(255, 255, 255);

        $lineHeight = 3.7;
        $lineCounts = [];

        foreach (self::TABLE_WIDTHS as $index => $width) {
            $lineCounts[] = $this->lineCount($width, $this->toPdfText(self::TABLE_HEADERS[$index]));
        }

        $height = max(10.5, max($lineCounts) * $lineHeight + 2);
        $x = $left;

        foreach (self::TABLE_WIDTHS as $index => $width) {
            $this->Rect($x, $top, $width, $height, 'DF');
            $this->SetXY($x, $top + 0.8);
            $this->MultiCell($width, $lineHeight, $this->toPdfText(self::TABLE_HEADERS[$index]), 0, 'C', false);
            $x += $width;
        }

        $this->SetY($top + $height);
    }

    private function lineCount(float $width, string $text): int
    {
        $characterWidths = $this->CurrentFont['cw'];
        $maxWidth = ($width - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $text = str_replace("\r", '', $text);
        $length = strlen($text);

        if ($length > 0 && $text[$length - 1] === "\n") {
            $length--;
        }

        $separator = -1;
        $index = 0;
        $lineStart = 0;
        $lineWidth = 0;
        $lines = 1;

        while ($index < $length) {
            $character = $text[$index];

            if ($character === "\n") {
                $index++;
                $separator = -1;
                $lineStart = $index;
                $lineWidth = 0;
                $lines++;
                continue;
            }

            if ($character === ' ') {
                $separator = $index;
            }

            $lineWidth += $characterWidths[ord($character)] ?? 500;

            if ($lineWidth > $maxWidth) {
                if ($separator === -1) {
                    if ($index === $lineStart) {
                        $index++;
                    }
                } else {
                    $index = $separator + 1;
                }

                $separator = -1;
                $lineStart = $index;
                $lineWidth = 0;
                $lines++;
            } else {
                $index++;
            }
        }

        return $lines;
    }

    private function toPdfText(string $text): string
    {
        $text = trim($text);

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

            if ($converted !== false) {
                return $converted;
            }
        }

        return utf8_decode($text);
    }
}

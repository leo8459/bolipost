<?php

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use ZipArchive;

class TodosPaquetesXlsxWriter
{
    private const MAX_DATA_ROWS_PER_SHEET = 1_048_575;

    private const HEADERS = [
        'TIPO', 'CODIGO', 'CN-33', 'ORIGEN', 'DESTINO', 'EMPRESA',
        'REMITENTE', 'DESTINATARIO', 'TELEFONO', 'PESO', 'PRECIO (BS)',
        'ESTADO', 'JUSTIFICACION', 'FECHA DE RECOJO',
    ];

    public function __construct(private readonly int $dataRowsPerSheet = self::MAX_DATA_ROWS_PER_SHEET)
    {
        if ($dataRowsPerSheet < 1 || $dataRowsPerSheet > self::MAX_DATA_ROWS_PER_SHEET) {
            throw new InvalidArgumentException('La cantidad de filas por hoja Excel no es válida.');
        }
    }

    public function dataRowsPerSheet(): int
    {
        return $this->dataRowsPerSheet;
    }

    /** @return resource */
    public function openSheet(string $sheetPath)
    {
        $handle = fopen($sheetPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('No se pudo crear la hoja del reporte.');
        }

        $this->write($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
        $this->write($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');
        $this->write($handle, '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>');
        $this->write($handle, '<sheetFormatPr defaultRowHeight="15"/><sheetData>');
        $this->writeRow($handle, self::HEADERS, 1);

        return $handle;
    }

    /** @param resource $handle
     *  @param array<int, mixed> $values
     *  @param array<int, bool> $numericColumns
     */
    public function writeRow($handle, array $values, int $rowNumber, array $numericColumns = []): void
    {
        $xml = '<row r="'.$rowNumber.'">';

        foreach (array_values($values) as $index => $value) {
            $cell = $this->columnName($index + 1).$rowNumber;
            $value = (string) ($value ?? '');

            if (($numericColumns[$index] ?? false) && $value !== '') {
                $xml .= '<c r="'.$cell.'"><v>'.$this->xml($value).'</v></c>';
            } else {
                $xml .= '<c r="'.$cell.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml($value).'</t></is></c>';
            }
        }

        $this->write($handle, $xml.'</row>');
    }

    /** @param resource $handle */
    public function finishSheet($handle, int $rowCount): void
    {
        $this->write($handle, '</sheetData><autoFilter ref="A1:N'.($rowCount + 1).'"/></worksheet>');

        if (! fclose($handle)) {
            throw new RuntimeException('No se pudo cerrar la hoja del reporte.');
        }
    }

    /** @param array<int, string> $sheetPaths */
    public function package(array $sheetPaths, string $xlsxPath): void
    {
        if ($sheetPaths === []) {
            throw new InvalidArgumentException('El reporte debe tener al menos una hoja.');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException('No se pudo crear el archivo Excel.');
        }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
                '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
                '<Default Extension="xml" ContentType="application/xml"/>'.
                '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'.
                implode('', array_map(fn (int $number): string => '<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', range(1, count($sheetPaths)))).
                '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
                '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'.
                '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'.
                '<sheets>'.implode('', array_map(function (int $number): string {
                    $name = $number === 1 ? 'Paquetes' : 'Paquetes '.$number;

                    return '<sheet name="'.$name.'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';
                }, range(1, count($sheetPaths)))).'</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'.
                '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
                implode('', array_map(fn (int $number): string => '<Relationship Id="rId'.$number.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$number.'.xml"/>', range(1, count($sheetPaths)))).
                '</Relationships>',
        ];

        foreach ($files as $name => $contents) {
            if (! $zip->addFromString($name, $contents)) {
                $zip->close();
                throw new RuntimeException('No se pudo completar el archivo Excel.');
            }
        }

        foreach (array_values($sheetPaths) as $index => $sheetPath) {
            if (! $zip->addFile($sheetPath, 'xl/worksheets/sheet'.($index + 1).'.xml')) {
                $zip->close();
                throw new RuntimeException('No se pudo agregar una hoja al archivo Excel.');
            }
        }

        if (! $zip->close()) {
            throw new RuntimeException('No se pudo cerrar el archivo Excel.');
        }
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function xml(string $value): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param resource $handle */
    private function write($handle, string $contents): void
    {
        if (fwrite($handle, $contents) !== strlen($contents)) {
            throw new RuntimeException('No se pudo escribir el archivo Excel.');
        }
    }
}

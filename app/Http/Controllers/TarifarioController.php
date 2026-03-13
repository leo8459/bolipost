<?php

namespace App\Http\Controllers;

use App\Models\Destino;
use App\Models\Origen;
use App\Models\Peso;
use App\Models\Servicio;
use App\Models\Tarifario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TarifarioController extends Controller
{
    private const IMPORT_COLUMNS = [
        'servicio',
        'origen',
        'destino',
        'peso_inicial',
        'peso_final',
        'precio',
        'observacion',
    ];

    private const TEMPLATE_WEIGHT_RANGES = [
        [0.001, 0.250],
        [0.251, 0.500],
        [0.501, 1.000],
        [1.001, 2.000],
        [2.001, 3.000],
        [3.001, 4.000],
        [4.001, 5.000],
        [5.001, 6.000],
        [6.001, 7.000],
        [7.001, 8.000],
        [8.001, 9.000],
        [9.001, 10.000],
        [10.001, 11.000],
        [11.001, 12.000],
        [12.001, 13.000],
        [13.001, 14.000],
        [14.001, 15.000],
        [15.001, 16.000],
        [16.001, 17.000],
        [17.001, 18.000],
        [18.001, 19.000],
        [19.001, 20.000],
    ];

    public function index()
    {
        return view('tarifario.index');
    }

    public function importForm()
    {
        return view('tarifario.import', [
            'columnas' => self::IMPORT_COLUMNS,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $filePath = (string) $request->file('archivo')->getRealPath();

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo Excel.'])->withInput();
        }

        $sheet = $spreadsheet->getSheetByName('Tarifario');
        if (!$sheet) {
            $sheet = $spreadsheet->getSheet(0);
        }

        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) {
            return back()->withErrors(['archivo' => 'El archivo esta vacio.'])->withInput();
        }

        $header = array_shift($rows);
        $header = array_map(fn ($value) => $this->normalizeHeader($value), $header);
        $missing = array_values(array_diff(self::IMPORT_COLUMNS, $header));

        if (!empty($missing)) {
            return back()
                ->withErrors(['archivo' => 'Columnas faltantes en Excel: ' . implode(', ', $missing)])
                ->withInput();
        }

        $servicios = Servicio::query()->orderBy('nombre_servicio')->get(['id', 'nombre_servicio']);
        $origenes = Origen::query()->orderBy('nombre_origen')->get(['id', 'nombre_origen']);
        $destinos = Destino::query()->orderBy('nombre_destino')->get(['id', 'nombre_destino']);
        $pesos = Peso::query()->orderBy('peso_inicial')->get(['id', 'peso_inicial', 'peso_final']);

        $servicioMap = [];
        foreach ($servicios as $servicio) {
            $servicioMap[$this->normalizeLookupKey((string) $servicio->nombre_servicio)] = (int) $servicio->id;
        }

        $origenMap = [];
        foreach ($origenes as $origen) {
            $origenMap[$this->normalizeLookupKey((string) $origen->nombre_origen)] = (int) $origen->id;
        }

        $destinoMap = [];
        foreach ($destinos as $destino) {
            $destinoMap[$this->normalizeLookupKey((string) $destino->nombre_destino)] = (int) $destino->id;
        }

        $pesoMap = [];
        foreach ($pesos as $peso) {
            $pesoMap[$this->buildPesoKey($peso->peso_inicial, $peso->peso_final)] = (int) $peso->id;
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;

        foreach ($rows as $row) {
            $line++;
            $hasValues = collect($row)->contains(fn ($value) => trim((string) $value) !== '');
            if (!$hasValues) {
                continue;
            }

            $data = [];
            foreach ($header as $index => $column) {
                $data[$column] = trim((string) ($row[$index] ?? ''));
            }

            $servicioTexto = (string) ($data['servicio'] ?? '');
            $origenTexto = (string) ($data['origen'] ?? '');
            $destinoTexto = (string) ($data['destino'] ?? '');
            $pesoInicial = $this->parseDecimal($data['peso_inicial'] ?? null);
            $pesoFinal = $this->parseDecimal($data['peso_final'] ?? null);
            $precio = $this->parseDecimal($data['precio'] ?? null);

            $servicioId = $servicioMap[$this->normalizeLookupKey($servicioTexto)] ?? null;
            if (!$servicioId) {
                $errors[] = "Linea {$line}: servicio '{$servicioTexto}' no existe.";
                continue;
            }

            $origenId = $origenMap[$this->normalizeLookupKey($origenTexto)] ?? null;
            if (!$origenId) {
                $errors[] = "Linea {$line}: origen '{$origenTexto}' no existe.";
                continue;
            }

            $destinoId = $destinoMap[$this->normalizeLookupKey($destinoTexto)] ?? null;
            if (!$destinoId) {
                $errors[] = "Linea {$line}: destino '{$destinoTexto}' no existe.";
                continue;
            }

            if ($pesoInicial === null || $pesoFinal === null) {
                $errors[] = "Linea {$line}: peso_inicial y peso_final son obligatorios.";
                continue;
            }

            $pesoId = $pesoMap[$this->buildPesoKey($pesoInicial, $pesoFinal)] ?? null;
            if (!$pesoId) {
                $errors[] = "Linea {$line}: no existe un peso registrado para el rango {$this->formatPeso($pesoInicial)} - {$this->formatPeso($pesoFinal)}.";
                continue;
            }

            $payload = [
                'servicio_id' => $servicioId,
                'origen_id' => $origenId,
                'destino_id' => $destinoId,
                'peso_id' => $pesoId,
                'precio' => $precio,
                'observacion' => $this->normalizeNullableText($data['observacion'] ?? null),
            ];

            $validator = Validator::make(
                $payload,
                [
                    'servicio_id' => ['required', 'integer', Rule::exists('servicio', 'id')],
                    'origen_id' => ['required', 'integer', Rule::exists('origen', 'id')],
                    'destino_id' => ['required', 'integer', Rule::exists('destino', 'id')],
                    'peso_id' => ['required', 'integer', Rule::exists('peso', 'id')],
                    'precio' => ['required', 'numeric', 'min:0'],
                    'observacion' => ['nullable', 'string'],
                ],
                [],
                [
                    'servicio_id' => 'servicio',
                    'origen_id' => 'origen',
                    'destino_id' => 'destino',
                    'peso_id' => 'peso',
                    'precio' => 'precio',
                    'observacion' => 'observacion',
                ]
            );

            if ($validator->fails()) {
                $errors[] = "Linea {$line}: " . $validator->errors()->first();
                continue;
            }

            $validated = $validator->validated();
            $tarifario = Tarifario::query()->where([
                'servicio_id' => $validated['servicio_id'],
                'origen_id' => $validated['origen_id'],
                'destino_id' => $validated['destino_id'],
                'peso_id' => $validated['peso_id'],
            ])->first();

            if ($tarifario) {
                $tarifario->update([
                    'precio' => $validated['precio'],
                    'observacion' => $validated['observacion'],
                ]);
                $updated++;
            } else {
                Tarifario::query()->create($validated);
                $created++;
            }
        }

        $message = "Importacion completada. Creados: {$created}, actualizados: {$updated}.";
        $redirect = redirect()->route('tarifario.index')->with('success', $message);

        if (!empty($errors)) {
            $redirect->with('warning', 'Se encontraron ' . count($errors) . ' fila(s) con error.');
            $redirect->with('import_errors', array_slice($errors, 0, 20));
        }

        return $redirect;
    }

    public function downloadTemplateExcel()
    {
        $filename = 'plantilla_tarifario.xlsx';
        $columns = self::IMPORT_COLUMNS;
        $servicios = Servicio::query()->orderBy('nombre_servicio')->get(['nombre_servicio']);
        $origenes = Origen::query()->orderBy('nombre_origen')->get(['nombre_origen']);
        $destinos = Destino::query()->orderBy('nombre_destino')->get(['nombre_destino']);

        return response()->streamDownload(function () use ($columns, $servicios, $origenes, $destinos) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tarifario');

            $sheet->fromArray($columns, null, 'A1');
            $sheet->getStyle('A1:G1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF20539A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF1B3E73'],
                    ],
                ],
            ]);

            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:G1');

            $columnWidths = [
                'A' => 28,
                'B' => 24,
                'C' => 24,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 36,
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $row = 2;
            foreach (self::TEMPLATE_WEIGHT_RANGES as [$pesoInicial, $pesoFinal]) {
                $sheet->setCellValue("D{$row}", $pesoInicial);
                $sheet->setCellValue("E{$row}", $pesoFinal);
                $row++;
            }

            $sheet->getStyle('D2:E5000')->getNumberFormat()->setFormatCode('0.000');
            $sheet->getStyle('F2:F5000')->getNumberFormat()->setFormatCode('#,##0.00');

            $sheetServicios = $spreadsheet->createSheet();
            $sheetServicios->setTitle('Servicios');
            $sheetServicios->setCellValue('A1', 'nombre_servicio');
            $serviceRow = 2;
            foreach ($servicios as $servicio) {
                $sheetServicios->setCellValue("A{$serviceRow}", (string) $servicio->nombre_servicio);
                $serviceRow++;
            }
            $sheetServicios->getColumnDimension('A')->setWidth(34);

            $sheetOrigenes = $spreadsheet->createSheet();
            $sheetOrigenes->setTitle('Origenes');
            $sheetOrigenes->setCellValue('A1', 'nombre_origen');
            $originRow = 2;
            foreach ($origenes as $origen) {
                $sheetOrigenes->setCellValue("A{$originRow}", (string) $origen->nombre_origen);
                $originRow++;
            }
            $sheetOrigenes->getColumnDimension('A')->setWidth(30);

            $sheetDestinos = $spreadsheet->createSheet();
            $sheetDestinos->setTitle('Destinos');
            $sheetDestinos->setCellValue('A1', 'nombre_destino');
            $destinationRow = 2;
            foreach ($destinos as $destino) {
                $sheetDestinos->setCellValue("A{$destinationRow}", (string) $destino->nombre_destino);
                $destinationRow++;
            }
            $sheetDestinos->getColumnDimension('A')->setWidth(30);

            $sheetPesos = $spreadsheet->createSheet();
            $sheetPesos->setTitle('Pesos');
            $sheetPesos->fromArray(['peso_inicial', 'peso_final', 'referencia'], null, 'A1');
            $pesoRow = 2;
            foreach (self::TEMPLATE_WEIGHT_RANGES as [$pesoInicial, $pesoFinal]) {
                $sheetPesos->setCellValue("A{$pesoRow}", $pesoInicial);
                $sheetPesos->setCellValue("B{$pesoRow}", $pesoFinal);
                $sheetPesos->setCellValue("C{$pesoRow}", $this->weightReferenceLabel($pesoInicial, $pesoFinal));
                $pesoRow++;
            }
            $sheetPesos->getStyle('A2:B5000')->getNumberFormat()->setFormatCode('0.000');
            $sheetPesos->getColumnDimension('A')->setWidth(14);
            $sheetPesos->getColumnDimension('B')->setWidth(14);
            $sheetPesos->getColumnDimension('C')->setWidth(24);

            $sheetInstrucciones = $spreadsheet->createSheet();
            $sheetInstrucciones->setTitle('Instrucciones');
            $sheetInstrucciones->setCellValue('A1', 'INSTRUCCIONES DE USO');
            $sheetInstrucciones->setCellValue('A3', '1) No cambies los nombres de columnas en la hoja Tarifario.');
            $sheetInstrucciones->setCellValue('A4', '2) Empieza a llenar datos desde la fila 2.');
            $sheetInstrucciones->setCellValue('A5', '3) Las columnas peso_inicial y peso_final ya vienen precargadas con los rangos de 0.001 a 20.000 kg.');
            $sheetInstrucciones->setCellValue('A6', '4) Si necesitas mas filas, duplica una fila existente para conservar el rango de peso.');
            $sheetInstrucciones->setCellValue('A7', '5) servicio, origen y destino deben existir en sus catalogos.');
            $sheetInstrucciones->setCellValue('A8', '6) Si una combinacion ya existe, la importacion actualiza precio y observacion.');
            $sheetInstrucciones->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'color' => ['argb' => 'FF0D47A1'],
            ]);
            $sheetInstrucciones->getColumnDimension('A')->setWidth(100);

            $lastServiceRow = max(2, $serviceRow - 1);
            $lastOriginRow = max(2, $originRow - 1);
            $lastDestinationRow = max(2, $destinationRow - 1);

            $this->applyListValidation($sheet, 'A2:A5000', "=Servicios!\$A\$2:\$A\${$lastServiceRow}");
            $this->applyListValidation($sheet, 'B2:B5000', "=Origenes!\$A\$2:\$A\${$lastOriginRow}");
            $this->applyListValidation($sheet, 'C2:C5000', "=Destinos!\$A\$2:\$A\${$lastDestinationRow}");

            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadMassTemplateExcel(Request $request)
    {
        $serviceFilter = $this->normalizeNullableText($request->query('servicio'));
        $filename = $serviceFilter
            ? 'plantilla_tarifario_masiva_' . Str::slug($serviceFilter, '_') . '.xlsx'
            : 'plantilla_tarifario_masiva.xlsx';

        $columns = self::IMPORT_COLUMNS;
        $serviciosQuery = Servicio::query()->orderBy('nombre_servicio');
        if ($serviceFilter !== null) {
            $serviciosQuery->where('nombre_servicio', $serviceFilter);
        }

        $servicios = $serviciosQuery->get(['nombre_servicio']);
        $origenes = Origen::query()->orderBy('nombre_origen')->get(['nombre_origen']);
        $destinos = Destino::query()->orderBy('nombre_destino')->get(['nombre_destino']);

        return response()->streamDownload(function () use ($columns, $servicios, $origenes, $destinos, $serviceFilter) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tarifario');

            $sheet->fromArray($columns, null, 'A1');
            $sheet->getStyle('A1:G1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF20539A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF1B3E73'],
                    ],
                ],
            ]);

            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:G1');

            foreach ([
                'A' => 28,
                'B' => 20,
                'C' => 20,
                'D' => 14,
                'E' => 14,
                'F' => 14,
                'G' => 30,
            ] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $row = 2;
            foreach ($servicios as $servicio) {
                foreach ($origenes as $origen) {
                    foreach ($destinos as $destino) {
                        foreach (self::TEMPLATE_WEIGHT_RANGES as [$pesoInicial, $pesoFinal]) {
                            $sheet->setCellValue("A{$row}", (string) $servicio->nombre_servicio);
                            $sheet->setCellValue("B{$row}", (string) $origen->nombre_origen);
                            $sheet->setCellValue("C{$row}", (string) $destino->nombre_destino);
                            $sheet->setCellValue("D{$row}", $pesoInicial);
                            $sheet->setCellValue("E{$row}", $pesoFinal);
                            $row++;
                        }
                    }
                }
            }

            $lastDataRow = max(2, $row - 1);
            $sheet->getStyle("D2:E{$lastDataRow}")->getNumberFormat()->setFormatCode('0.000');
            $sheet->getStyle("F2:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');

            $sheetServicios = $spreadsheet->createSheet();
            $sheetServicios->setTitle('Servicios');
            $sheetServicios->setCellValue('A1', 'nombre_servicio');
            $serviceRow = 2;
            foreach ($servicios as $servicio) {
                $sheetServicios->setCellValue("A{$serviceRow}", (string) $servicio->nombre_servicio);
                $serviceRow++;
            }
            $sheetServicios->getColumnDimension('A')->setWidth(34);

            $sheetOrigenes = $spreadsheet->createSheet();
            $sheetOrigenes->setTitle('Origenes');
            $sheetOrigenes->setCellValue('A1', 'nombre_origen');
            $originRow = 2;
            foreach ($origenes as $origen) {
                $sheetOrigenes->setCellValue("A{$originRow}", (string) $origen->nombre_origen);
                $originRow++;
            }
            $sheetOrigenes->getColumnDimension('A')->setWidth(24);

            $sheetDestinos = $spreadsheet->createSheet();
            $sheetDestinos->setTitle('Destinos');
            $sheetDestinos->setCellValue('A1', 'nombre_destino');
            $destinationRow = 2;
            foreach ($destinos as $destino) {
                $sheetDestinos->setCellValue("A{$destinationRow}", (string) $destino->nombre_destino);
                $destinationRow++;
            }
            $sheetDestinos->getColumnDimension('A')->setWidth(24);

            $sheetInstrucciones = $spreadsheet->createSheet();
            $sheetInstrucciones->setTitle('Instrucciones');
            $sheetInstrucciones->setCellValue('A1', 'INSTRUCCIONES DE USO');
            $sheetInstrucciones->setCellValue('A3', '1) Esta plantilla ya incluye todas las combinaciones de origen, destino y peso.');
            $sheetInstrucciones->setCellValue('A4', '2) Solo llena precio y, si necesitas, observacion.');
            $sheetInstrucciones->setCellValue('A5', '3) Puedes filtrar en Excel por origen, destino o servicio para trabajar por bloques.');
            $sheetInstrucciones->setCellValue('A6', '4) Si vuelves a importar una combinacion existente, el sistema actualiza precio y observacion.');
            $sheetInstrucciones->setCellValue('A7', $serviceFilter
                ? '5) Esta plantilla fue generada solo para el servicio: ' . $serviceFilter
                : '5) Esta plantilla fue generada para todos los servicios registrados.');
            $sheetInstrucciones->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'color' => ['argb' => 'FF0D47A1'],
            ]);
            $sheetInstrucciones->getColumnDimension('A')->setWidth(100);

            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function normalizeHeader($value): string
    {
        return strtolower(trim((string) $value));
    }

    private function normalizeLookupKey(string $value): string
    {
        $value = trim($value);
        $value = Str::ascii($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return strtoupper($value);
    }

    private function normalizeNullableText($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function applyListValidation(Worksheet $sheet, string $range, string $formula): void
    {
        [$start, $end] = explode(':', $range);
        $startCell = $sheet->getCell($start);
        $validation = $startCell->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Valor invalido');
        $validation->setError('Selecciona un valor de la lista.');
        $validation->setFormula1($formula);

        [$startCol, $startRow] = $this->splitCell($start);
        [$endCol, $endRow] = $this->splitCell($end);

        for ($row = $startRow; $row <= $endRow; $row++) {
            for ($col = $startCol; $col <= $endCol; $col++) {
                $sheet->getCell($col . $row)->setDataValidation(clone $validation);
            }
        }
    }

    private function splitCell(string $cell): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($cell), $matches);

        return [$matches[1], (int) $matches[2]];
    }

    private function parseDecimal($value): ?float
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $hasComma = str_contains($text, ',');
        $hasDot = str_contains($text, '.');

        if ($hasComma && $hasDot) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif ($hasComma) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function buildPesoKey($pesoInicial, $pesoFinal): string
    {
        return sprintf('%.3f|%.3f', (float) $pesoInicial, (float) $pesoFinal);
    }

    private function formatPeso(float $value): string
    {
        return number_format($value, 3, '.', '');
    }

    private function weightReferenceLabel(float $pesoInicial, float $pesoFinal): string
    {
        return $this->formatPeso($pesoInicial) . ' - ' . $this->formatPeso($pesoFinal) . ' kg';
    }
}

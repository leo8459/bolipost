<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Peso;
use App\Models\Servicio;
use App\Models\Tarifario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
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

    public function exportPdf()
    {
        $this->authorizeTarifarioAccess();

        $tarifarios = Tarifario::query()
            ->with(['servicio', 'peso'])
            ->orderBy('servicio_id')
            ->orderBy('peso_id')
            ->get();

        $grouped = $tarifarios
            ->groupBy(fn ($item) => optional($item->servicio)->nombre_servicio ?: 'SIN SERVICIO')
            ->sortKeys();

        $pdf = Pdf::loadView('tarifario.report-pdf', [
            'groupedTarifarios' => $grouped,
            'generatedAt' => now(),
            'totalTarifarios' => $tarifarios->count(),
        ])->setPaper('A4', 'portrait');

        return $pdf->stream('tarifario-' . now()->format('Ymd-His') . '.pdf');
    }

    public function downloadGlobalReportExcel()
    {
        $this->authorizeTarifarioAccess();

        $servicios = Servicio::query()
            ->orderBy('nombre_servicio')
            ->get(['id', 'nombre_servicio']);

        $pesos = Peso::query()
            ->orderBy('peso_inicial')
            ->get(['id', 'peso_inicial', 'peso_final']);

        $tarifarios = Tarifario::query()
            ->get(['servicio_id', 'peso_id', 'precio'])
            ->mapWithKeys(function ($item) {
                return [((int) $item->peso_id) . ':' . ((int) $item->servicio_id) => (float) $item->precio];
            });

        $filename = 'tarifario_global_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($servicios, $pesos, $tarifarios) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tarifario Global');

            $serviceCount = max(1, $servicios->count());
            $lastColumnIndex = 2 + $serviceCount;
            $lastColumn = Coordinate::stringFromColumnIndex($lastColumnIndex);

            $sheet->mergeCells("A1:{$lastColumn}1");
            $sheet->setCellValue('A1', 'TARIFARIO GLOBAL POR SERVICIO');
            $sheet->mergeCells("A2:{$lastColumn}2");
            $sheet->setCellValue('A2', 'Generado el ' . now()->format('d/m/Y H:i'));

            $sheet->setCellValue('A4', 'Peso inicial (kg)');
            $sheet->setCellValue('B4', 'Peso final (kg)');

            $columnIndex = 3;
            foreach ($servicios as $servicio) {
                $sheet->setCellValue(
                    Coordinate::stringFromColumnIndex($columnIndex) . '4',
                    (string) $servicio->nombre_servicio
                );
                $columnIndex++;
            }

            $row = 5;
            foreach ($pesos as $peso) {
                $sheet->setCellValue("A{$row}", (float) $peso->peso_inicial);
                $sheet->setCellValue("B{$row}", (float) $peso->peso_final);

                $columnIndex = 3;
                foreach ($servicios as $servicio) {
                    $key = ((int) $peso->id) . ':' . ((int) $servicio->id);
                    $price = $tarifarios->get($key);
                    if ($price !== null) {
                        $sheet->setCellValue(Coordinate::stringFromColumnIndex($columnIndex) . $row, $price);
                    }
                    $columnIndex++;
                }

                $row++;
            }

            $lastDataRow = max(5, $row - 1);

            $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
                'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF20539A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getStyle("A2:{$lastColumn}2")->applyFromArray([
                'font' => ['italic' => true, 'color' => ['argb' => 'FF4B5563']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $sheet->getStyle("A4:{$lastColumn}4")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FF1F2937']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFECF64'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFB45309'],
                    ],
                ],
            ]);

            $sheet->getStyle("A5:B{$lastDataRow}")->getNumberFormat()->setFormatCode('0.000');
            if ($lastColumnIndex >= 3) {
                $sheet->getStyle('C5:' . $lastColumn . $lastDataRow)->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $sheet->getStyle("A4:{$lastColumn}{$lastDataRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFCBD5E1'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            $sheet->getColumnDimension('A')->setWidth(16);
            $sheet->getColumnDimension('B')->setWidth(16);
            for ($i = 3; $i <= $lastColumnIndex; $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(22);
            }

            $sheet->freezePane('C5');
            $sheet->setAutoFilter("A4:{$lastColumn}{$lastDataRow}");

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
        $pesos = Peso::query()->orderBy('peso_inicial')->get(['id', 'peso_inicial', 'peso_final']);

        $servicioMap = [];
        foreach ($servicios as $servicio) {
            $servicioMap[$this->normalizeLookupKey((string) $servicio->nombre_servicio)] = (int) $servicio->id;
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
            $pesoInicial = $this->parseDecimal($data['peso_inicial'] ?? null);
            $pesoFinal = $this->parseDecimal($data['peso_final'] ?? null);
            $precio = $this->parseDecimal($data['precio'] ?? null);

            $servicioId = $servicioMap[$this->normalizeLookupKey($servicioTexto)] ?? null;
            if (!$servicioId) {
                $errors[] = "Linea {$line}: servicio '{$servicioTexto}' no existe.";
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
                'peso_id' => $pesoId,
                'precio' => $precio,
                'observacion' => $this->normalizeNullableText($data['observacion'] ?? null),
                'origen_id' => null,
                'destino_id' => null,
            ];

            $validator = Validator::make(
                $payload,
                [
                    'servicio_id' => ['required', 'integer', Rule::exists('servicio', 'id')],
                    'peso_id' => ['required', 'integer', Rule::exists('peso', 'id')],
                    'precio' => ['required', 'numeric', 'min:0'],
                    'observacion' => ['nullable', 'string'],
                ],
                [],
                [
                    'servicio_id' => 'servicio',
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

        return response()->streamDownload(function () use ($columns, $servicios) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tarifario');

            $sheet->fromArray($columns, null, 'A1');
            $sheet->getStyle('A1:E1')->applyFromArray([
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
            $sheet->setAutoFilter('A1:E1');

            $columnWidths = [
                'A' => 28,
                'B' => 14,
                'C' => 14,
                'D' => 14,
                'E' => 36,
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $row = 2;
            foreach (self::TEMPLATE_WEIGHT_RANGES as [$pesoInicial, $pesoFinal]) {
                $sheet->setCellValue("B{$row}", $pesoInicial);
                $sheet->setCellValue("C{$row}", $pesoFinal);
                $row++;
            }

            $sheet->getStyle('B2:C5000')->getNumberFormat()->setFormatCode('0.000');
            $sheet->getStyle('D2:D5000')->getNumberFormat()->setFormatCode('#,##0.00');

            $sheetServicios = $spreadsheet->createSheet();
            $sheetServicios->setTitle('Servicios');
            $sheetServicios->setCellValue('A1', 'nombre_servicio');
            $serviceRow = 2;
            foreach ($servicios as $servicio) {
                $sheetServicios->setCellValue("A{$serviceRow}", (string) $servicio->nombre_servicio);
                $serviceRow++;
            }
            $sheetServicios->getColumnDimension('A')->setWidth(34);

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
            $sheetInstrucciones->setCellValue('A5', '3) El tarifario ahora solo usa servicio, peso y precio.');
            $sheetInstrucciones->setCellValue('A6', '4) Las columnas peso_inicial y peso_final ya vienen precargadas con los rangos de 0.001 a 20.000 kg.');
            $sheetInstrucciones->setCellValue('A7', '5) Si necesitas mas filas, duplica una fila existente para conservar el rango de peso.');
            $sheetInstrucciones->setCellValue('A8', '6) Si una combinacion de servicio y peso ya existe, la importacion actualiza precio y observacion.');
            $sheetInstrucciones->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'color' => ['argb' => 'FF0D47A1'],
            ]);
            $sheetInstrucciones->getColumnDimension('A')->setWidth(100);

            $lastServiceRow = max(2, $serviceRow - 1);

            $this->applyListValidation($sheet, 'A2:A5000', "=Servicios!\$A\$2:\$A\${$lastServiceRow}");

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

        return response()->streamDownload(function () use ($columns, $servicios, $serviceFilter) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Tarifario');

            $sheet->fromArray($columns, null, 'A1');
            $sheet->getStyle('A1:E1')->applyFromArray([
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
            $sheet->setAutoFilter('A1:E1');

            foreach ([
                'A' => 28,
                'B' => 14,
                'C' => 14,
                'D' => 14,
                'E' => 30,
            ] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $row = 2;
            foreach ($servicios as $servicio) {
                foreach (self::TEMPLATE_WEIGHT_RANGES as [$pesoInicial, $pesoFinal]) {
                    $sheet->setCellValue("A{$row}", (string) $servicio->nombre_servicio);
                    $sheet->setCellValue("B{$row}", $pesoInicial);
                    $sheet->setCellValue("C{$row}", $pesoFinal);
                    $row++;
                }
            }

            $lastDataRow = max(2, $row - 1);
            $sheet->getStyle("B2:C{$lastDataRow}")->getNumberFormat()->setFormatCode('0.000');
            $sheet->getStyle("D2:D{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');

            $sheetServicios = $spreadsheet->createSheet();
            $sheetServicios->setTitle('Servicios');
            $sheetServicios->setCellValue('A1', 'nombre_servicio');
            $serviceRow = 2;
            foreach ($servicios as $servicio) {
                $sheetServicios->setCellValue("A{$serviceRow}", (string) $servicio->nombre_servicio);
                $serviceRow++;
            }
            $sheetServicios->getColumnDimension('A')->setWidth(34);

            $sheetInstrucciones = $spreadsheet->createSheet();
            $sheetInstrucciones->setTitle('Instrucciones');
            $sheetInstrucciones->setCellValue('A1', 'INSTRUCCIONES DE USO');
            $sheetInstrucciones->setCellValue('A3', '1) Esta plantilla ya incluye todas las combinaciones de servicio y peso.');
            $sheetInstrucciones->setCellValue('A4', '2) Solo llena precio y, si necesitas, observacion.');
            $sheetInstrucciones->setCellValue('A5', '3) Puedes filtrar en Excel por servicio para trabajar por bloques.');
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

    private function authorizeTarifarioAccess(): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'No tienes permiso para acceder a esta ventana o accion.');
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        if ($user->can('tarifario.index')) {
            return;
        }

        abort(403, 'No tienes permiso para acceder a esta ventana o accion.');
    }
}

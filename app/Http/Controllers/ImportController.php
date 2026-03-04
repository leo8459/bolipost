<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\PaqueteCerti;
use App\Models\Ventanilla;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportController extends Controller
{
    private const IMPORT_COLUMNS = [
        'codigo',
        'destinatario',
        'telefono',
        'peso',
        'aduana',
        'zona',
        'tipo',
    ];

    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE = 168;
    private const ESTADO_VENTANILLA = 'VENTANILLA';
    private const CIUDADES = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'SUCRE',
        'TARIJA',
        'TRINIDAD',
        'COBIJA',
    ];

    public function paquets()
    {
        return view('importar.paquets', [
            'columnas' => self::IMPORT_COLUMNS,
            'ciudades' => self::CIUDADES,
            'ventanillas' => Ventanilla::query()->orderBy('nombre_ventanilla')->get(['id', 'nombre_ventanilla']),
            'ciudadPorDefecto' => strtoupper(trim((string) optional(Auth::user())->ciudad)),
        ]);
    }

    public function importPaquets(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
            'ciudad' => ['required', 'string', Rule::in(self::CIUDADES)],
            'fk_ventanilla' => 'required|integer|exists:ventanilla,id',
        ]);

        $estadoVentanillaId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [self::ESTADO_VENTANILLA])
            ->value('id');

        if (!$estadoVentanillaId) {
            return back()->withErrors([
                'archivo' => 'No existe el estado VENTANILLA en la tabla estados.',
            ])->withInput();
        }

        $ventanilla = Ventanilla::query()->find((int) $request->integer('fk_ventanilla'));
        if (!$ventanilla) {
            return back()->withErrors([
                'fk_ventanilla' => 'La ventanilla seleccionada no existe.',
            ])->withInput();
        }

        $filePath = (string) $request->file('archivo')->getRealPath();

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'archivo' => 'No se pudo leer el archivo Excel.',
            ])->withInput();
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        if (empty($rows)) {
            return back()->withErrors([
                'archivo' => 'El archivo esta vacio.',
            ])->withInput();
        }

        $header = array_shift($rows);
        $header = array_map(fn ($value) => $this->normalizeHeader($value), $header);
        $missing = array_values(array_diff(self::IMPORT_COLUMNS, $header));

        if (!empty($missing)) {
            return back()->withErrors([
                'archivo' => 'Columnas faltantes en Excel: ' . implode(', ', $missing),
            ])->withInput();
        }

        $created = 0;
        $errors = [];
        $line = 1;
        $now = now();
        $rowsToInsert = [];
        $eventRows = [];
        $userId = (int) optional(Auth::user())->id;
        $canCreateEvento = $userId > 0 && DB::table('eventos')->where('id', self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE)->exists();
        $ciudad = $this->upper((string) $request->input('ciudad'));
        $ventanillaNombre = $this->upper((string) $ventanilla->nombre_ventanilla);

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

            $payload = [
                'codigo' => $this->upper((string) ($data['codigo'] ?? '')),
                'destinatario' => $this->upper((string) ($data['destinatario'] ?? '')),
                'telefono' => $this->parseInteger($data['telefono'] ?? null),
                'cuidad' => $ciudad,
                'zona' => $this->upper((string) ($data['zona'] ?? '')),
                'ventanilla' => $ventanillaNombre,
                'peso' => $this->parseDecimal($data['peso'] ?? null),
                'tipo' => $this->upper((string) ($data['tipo'] ?? '')),
                'aduana' => $this->upper((string) ($data['aduana'] ?? '')),
                'fk_estado' => (int) $estadoVentanillaId,
                'fk_ventanilla' => (int) $ventanilla->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $validator = Validator::make(
                $payload,
                [
                    'codigo' => 'required|string|max:255',
                    'destinatario' => 'required|string|max:255',
                    'telefono' => 'required|integer|min:0',
                    'cuidad' => 'required|string|max:255',
                    'zona' => 'required|string|max:255',
                    'ventanilla' => 'required|string|max:255',
                    'peso' => 'required|numeric|min:0',
                    'tipo' => 'required|string|max:255',
                    'aduana' => 'required|string|max:255',
                    'fk_estado' => 'required|integer|exists:estados,id',
                    'fk_ventanilla' => 'required|integer|exists:ventanilla,id',
                ]
            );

            if ($validator->fails()) {
                $errors[] = "Linea {$line}: " . $validator->errors()->first();
                continue;
            }

            $valid = $validator->validated();
            $rowsToInsert[] = $valid;
            $created++;

            if ($canCreateEvento) {
                $eventRows[] = [
                    'codigo' => $valid['codigo'],
                    'evento_id' => self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rowsToInsert)) {
            DB::transaction(function () use ($rowsToInsert, $eventRows) {
                PaqueteCerti::query()->insert($rowsToInsert);
                if (!empty($eventRows)) {
                    DB::table('eventos_certi')->insert($eventRows);
                }
            });
        }

        $message = "Importacion completada. Registros creados: {$created}.";
        $redirect = redirect()->route('importar.paquets')->with('success', $message);

        if (!empty($errors)) {
            $redirect->with('warning', 'Se encontraron ' . count($errors) . ' fila(s) con error.');
            $redirect->with('import_errors', array_slice($errors, 0, 30));
        }

        return $redirect;
    }

    public function downloadPaquetsTemplateExcel()
    {
        $filename = 'plantilla_importacion_paquetes.xlsx';
        $columns = array_map('strtoupper', self::IMPORT_COLUMNS);
        $example = [
            'RX123456789BO',
            'JUAN PEREZ',
            '71234567',
            '0.520',
            'NO',
            'CENTRAL',
            'PP',
        ];

        return response()->streamDownload(function () use ($columns, $example) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Paquetes');

            $sheet->fromArray($columns, null, 'A1');
            $sheet->fromArray([$example], null, 'A2');
            $sheet->getStyle('A1:G1')->getFont()->setBold(true);

            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function normalizeHeader($value): string
    {
        $text = trim((string) $value);
        $ascii = function_exists('iconv')
            ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text)
            : false;
        $text = strtolower($ascii !== false ? $ascii : $text);
        $text = preg_replace('/[^a-z0-9]+/', '_', $text) ?? $text;

        return trim($text, '_');
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

    private function parseInteger($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $normalized = str_replace([' ', '-', '(', ')', '+'], '', $text);
        if ($normalized === '' || !ctype_digit($normalized)) {
            return null;
        }

        if (strlen($normalized) > 10) {
            return null;
        }

        return (int) $normalized;
    }

    private function upper(string $value): string
    {
        return strtoupper(trim($value));
    }
}

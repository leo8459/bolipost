<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\PaqueteCerti;
use App\Models\PaqueteOrdi;
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
    private const DESTINO_CERTI = 'CERTI';
    private const DESTINO_ORDI = 'ORDI';

    private const IMPORT_COLUMNS = [
        'codigo',
        'destinatario',
        'telefono',
        'peso',
        'aduana',
        'zona',
        'tipo',
    ];

    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE_CERTI = 168;
    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE_ORDI = 295;
    private const ESTADO_VENTANILLA = 'VENTANILLA';
    private const ESTADO_CLASIFICACION = 'CLASIFICACION';
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
        $estados = Estado::query()
            ->orderBy('nombre_estado')
            ->get(['id', 'nombre_estado']);

        $estadoDefaultPorDestino = [
            self::DESTINO_ORDI => $this->findEstadoIdByName($estados, self::ESTADO_CLASIFICACION),
            self::DESTINO_CERTI => $this->findEstadoIdByName($estados, self::ESTADO_VENTANILLA),
        ];

        return view('importar.paquets', [
            'columnas' => self::IMPORT_COLUMNS,
            'ciudades' => self::CIUDADES,
            'tiposDestino' => $this->tiposDestino(),
            'tipoDestinoPorDefecto' => self::DESTINO_ORDI,
            'estados' => $estados,
            'estadoDefaultPorDestino' => $estadoDefaultPorDestino,
            'ventanillas' => Ventanilla::query()->orderBy('nombre_ventanilla')->get(['id', 'nombre_ventanilla']),
            'ciudadPorDefecto' => strtoupper(trim((string) optional(Auth::user())->ciudad)),
        ]);
    }

    public function importPaquets(Request $request)
    {
        $request->validate([
            'tipo_destino' => ['required', 'string', Rule::in(array_keys($this->tiposDestino()))],
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
            'ciudad' => ['required', 'string', Rule::in(self::CIUDADES)],
            'fk_estado' => 'required|integer|exists:estados,id',
            'fk_ventanilla' => 'required|integer|exists:ventanilla,id',
        ]);

        $tipoDestino = strtoupper((string) $request->input('tipo_destino'));
        $isOrdi = $tipoDestino === self::DESTINO_ORDI;
        $estadoId = (int) $request->integer('fk_estado');

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
        $eventoId = $isOrdi
            ? self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE_ORDI
            : self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE_CERTI;
        $tablaEventos = $isOrdi ? 'eventos_ordi' : 'eventos_certi';
        $canCreateEvento = $userId > 0 && DB::table('eventos')->where('id', $eventoId)->exists();
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

            $payload = $isOrdi
                ? $this->buildOrdiPayload($data, $ciudad, (int) $estadoId, (int) $ventanilla->id, $now)
                : $this->buildCertiPayload($data, $ciudad, $ventanillaNombre, (int) $estadoId, (int) $ventanilla->id, $now);

            $validator = Validator::make(
                $payload,
                $isOrdi ? $this->ordiRules() : $this->certiRules()
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
                    'evento_id' => $eventoId,
                    'user_id' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($rowsToInsert)) {
            DB::transaction(function () use ($rowsToInsert, $eventRows, $isOrdi, $tablaEventos) {
                if ($isOrdi) {
                    PaqueteOrdi::query()->insert($rowsToInsert);
                } else {
                    PaqueteCerti::query()->insert($rowsToInsert);
                }

                if (!empty($eventRows)) {
                    DB::table($tablaEventos)->insert($eventRows);
                }
            });
        }

        $destinoTexto = $this->tiposDestino()[$tipoDestino] ?? $tipoDestino;
        $message = "Importacion completada en {$destinoTexto}. Registros creados: {$created}.";
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

    private function buildCertiPayload(
        array $data,
        string $ciudad,
        string $ventanillaNombre,
        int $estadoId,
        int $ventanillaId,
        $now
    ): array {
        return [
            'codigo' => $this->upper((string) ($data['codigo'] ?? '')),
            'destinatario' => $this->upper((string) ($data['destinatario'] ?? '')),
            'telefono' => $this->parseInteger($data['telefono'] ?? null),
            'cuidad' => $ciudad,
            'zona' => $this->upper((string) ($data['zona'] ?? '')),
            'ventanilla' => $ventanillaNombre,
            'peso' => $this->parseDecimal($data['peso'] ?? null),
            'tipo' => $this->upper((string) ($data['tipo'] ?? '')),
            'aduana' => $this->upper((string) ($data['aduana'] ?? '')),
            'fk_estado' => $estadoId,
            'fk_ventanilla' => $ventanillaId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function buildOrdiPayload(
        array $data,
        string $ciudad,
        int $estadoId,
        int $ventanillaId,
        $now
    ): array {
        $tipo = $this->upper((string) ($data['tipo'] ?? ''));

        return [
            'codigo' => $this->upper((string) ($data['codigo'] ?? '')),
            'destinatario' => $this->upper((string) ($data['destinatario'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'ciudad' => $ciudad,
            'zona' => $this->upper((string) ($data['zona'] ?? '')),
            'peso' => $this->parseDecimal($data['peso'] ?? null),
            'aduana' => $this->upper((string) ($data['aduana'] ?? '')),
            'observaciones' => $tipo !== '' ? ('TIPO: ' . $tipo) : null,
            'cod_especial' => null,
            'fk_estado' => $estadoId,
            'fk_ventanilla' => $ventanillaId,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function certiRules(): array
    {
        return [
            'codigo' => 'required|string|max:255',
            'destinatario' => 'required|string|max:255',
            'telefono' => 'required|integer|min:0|max:2147483647',
            'cuidad' => 'required|string|max:255',
            'zona' => 'required|string|max:255',
            'ventanilla' => 'required|string|max:255',
            'peso' => 'required|numeric|min:0',
            'tipo' => 'required|string|max:255',
            'aduana' => 'required|string|max:255',
            'fk_estado' => 'required|integer|exists:estados,id',
            'fk_ventanilla' => 'required|integer|exists:ventanilla,id',
            'created_at' => 'required|date',
            'updated_at' => 'required|date',
        ];
    }

    private function ordiRules(): array
    {
        return [
            'codigo' => 'required|string|max:255',
            'destinatario' => 'required|string|max:255',
            'telefono' => 'required|string|max:30',
            'ciudad' => 'required|string|max:255',
            'zona' => 'required|string|max:255',
            'peso' => 'required|numeric|min:0',
            'aduana' => 'required|string|max:50',
            'observaciones' => 'nullable|string|max:1000',
            'cod_especial' => 'nullable|string|max:255',
            'fk_estado' => 'required|integer|exists:estados,id',
            'fk_ventanilla' => 'required|integer|exists:ventanilla,id',
            'created_at' => 'required|date',
            'updated_at' => 'required|date',
        ];
    }

    private function tiposDestino(): array
    {
        return [
            self::DESTINO_ORDI => 'PAQUETES ORDINARIOS',
            self::DESTINO_CERTI => 'PAQUETES CERTIFICADOS',
        ];
    }

    private function findEstadoIdByName($estados, string $nombre): ?int
    {
        $target = strtoupper(trim($nombre));
        $estado = $estados->first(function ($item) use ($target) {
            return strtoupper(trim((string) $item->nombre_estado)) === $target;
        });

        return $estado ? (int) $estado->id : null;
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

        return (int) $normalized;
    }

    private function upper(string $value): string
    {
        return strtoupper(trim($value));
    }
}

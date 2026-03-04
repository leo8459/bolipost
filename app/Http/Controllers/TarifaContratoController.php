<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\TarifaContrato;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TarifaContratoController extends Controller
{
    private const SERVICIOS = [
        'ENVIO NACIONAL (REGULAR)',
        'ENVIO NACIONAL (EXPRESS)',
        'ENVIO LOCAL(REGULAR)',
        'ENVIO LOCAL(EXPRESS)',
        'ENVIO INTERPROVINCIAL',
    ];

    private const DEPARTAMENTOS = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'CHUQUISACA',
        'BENI',
        'PANDO',
    ];

    private const IMPORT_COLUMNS = [
        'empresa_codigo',
        'origen',
        'destino',
        'servicio',
        'kilo',
        'kilo_extra',
        'provincia',
        'retencion',
        'horas_entrega',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $tarifas = TarifaContrato::query()
            ->with('empresa')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('origen', 'ILIKE', "%{$q}%")
                        ->orWhere('destino', 'ILIKE', "%{$q}%")
                        ->orWhere('servicio', 'ILIKE', "%{$q}%")
                        ->orWhere('provincia', 'ILIKE', "%{$q}%")
                        ->orWhere('horas_entrega', 'ILIKE', "%{$q}%");
                })->orWhereHas('empresa', function ($sub) use ($q) {
                    $sub->where('nombre', 'ILIKE', "%{$q}%")
                        ->orWhere('sigla', 'ILIKE', "%{$q}%")
                        ->orWhere('codigo_cliente', 'ILIKE', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('tarifa_contrato.index', [
            'tarifas' => $tarifas,
            'q' => $q,
        ]);
    }

    public function create()
    {
        return view('tarifa_contrato.create', [
            'tarifaContrato' => new TarifaContrato(),
            'empresas' => Empresa::query()->orderBy('nombre')->get(),
            'servicios' => self::SERVICIOS,
            'departamentos' => self::DEPARTAMENTOS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        TarifaContrato::query()->create($data);

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato creada correctamente.');
    }

    public function importForm()
    {
        return view('tarifa_contrato.import', [
            'servicios' => self::SERVICIOS,
            'departamentos' => self::DEPARTAMENTOS,
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

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
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

        $empresaCodigoMap = Empresa::query()
            ->get(['id', 'codigo_cliente'])
            ->mapWithKeys(function ($empresa) {
                return [strtoupper(trim((string) $empresa->codigo_cliente)) => (int) $empresa->id];
            })
            ->all();

        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1; // Cabecera

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

            $empresaCodigo = strtoupper((string) ($data['empresa_codigo'] ?? ''));
            $empresaId = $empresaCodigoMap[$empresaCodigo] ?? null;

            if (!$empresaId) {
                $errors[] = "Linea {$line}: empresa_codigo '{$empresaCodigo}' no existe.";
                continue;
            }

            $payload = [
                'empresa_id' => $empresaId,
                'origen' => strtoupper((string) ($data['origen'] ?? '')),
                'destino' => strtoupper((string) ($data['destino'] ?? '')),
                'servicio' => $this->normalizeServicio((string) ($data['servicio'] ?? '')),
                'kilo' => $this->parseDecimal($data['kilo'] ?? null),
                'kilo_extra' => $this->parseDecimal($data['kilo_extra'] ?? null),
                'provincia' => strtoupper((string) ($data['provincia'] ?? '')),
                'retencion' => $this->parseDecimal($data['retencion'] ?? null),
                'horas_entrega' => $data['horas_entrega'] ?? null,
            ];

            $validator = Validator::make(
                $payload,
                $this->validationRules(),
                [],
                [
                    'empresa_id' => 'empresa',
                    'origen' => 'origen',
                    'destino' => 'destino',
                    'servicio' => 'servicio',
                    'kilo' => 'kilo',
                    'kilo_extra' => 'kilo extra',
                    'provincia' => 'provincia',
                    'retencion' => 'retencion',
                    'horas_entrega' => 'horas de entrega',
                ]
            );

            if ($validator->fails()) {
                $errors[] = "Linea {$line}: " . $validator->errors()->first();
                continue;
            }

            $validated = $validator->validated();
            $unique = Arr::only($validated, [
                'empresa_id',
                'origen',
                'destino',
                'servicio',
                'kilo',
                'kilo_extra',
                'provincia',
            ]);

            $values = Arr::only($validated, ['retencion', 'horas_entrega']);

            $tarifa = TarifaContrato::query()->where($unique)->first();
            if ($tarifa) {
                $tarifa->update($values);
                $updated++;
            } else {
                TarifaContrato::query()->create($validated);
                $created++;
            }
        }

        $message = "Importacion completada. Creadas: {$created}, actualizadas: {$updated}.";
        $redirect = redirect()->route('tarifa-contrato.index')->with('success', $message);

        if (!empty($errors)) {
            $redirect->with('warning', 'Se encontraron ' . count($errors) . ' fila(s) con error.');
            $redirect->with('import_errors', array_slice($errors, 0, 20));
        }

        return $redirect;
    }

    public function downloadTemplateExcel()
    {
        $filename = 'plantilla_tarifa_contrato.xlsx';
        $columns = self::IMPORT_COLUMNS;
        $example = [
            'EMPRESA001',
            'LA PAZ',
            'COCHABAMBA',
            'ENVIO NACIONAL (REGULAR)',
            '10',
            '1.5',
            'QUILLACOLLO',
            '5',
            '48',
        ];

        return response()->streamDownload(function () use ($columns, $example) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('TarifaContrato');

            $sheet->fromArray($columns, null, 'A1');
            $sheet->fromArray([$example], null, 'A2');

            $sheet->getStyle('A1:I1')->getFont()->setBold(true);
            foreach (range('A', 'I') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function edit(TarifaContrato $tarifaContrato)
    {
        return view('tarifa_contrato.edit', [
            'tarifaContrato' => $tarifaContrato,
            'empresas' => Empresa::query()->orderBy('nombre')->get(),
            'servicios' => self::SERVICIOS,
            'departamentos' => self::DEPARTAMENTOS,
        ]);
    }

    public function update(Request $request, TarifaContrato $tarifaContrato)
    {
        $data = $this->validateData($request);
        $tarifaContrato->update($data);

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato actualizada correctamente.');
    }

    public function destroy(TarifaContrato $tarifaContrato)
    {
        $tarifaContrato->delete();

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato eliminada correctamente.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate($this->validationRules());

        $data['provincia'] = strtoupper(trim((string) $data['provincia']));

        return $data;
    }

    private function validationRules(): array
    {
        return [
            'empresa_id' => ['required', 'integer', Rule::exists('empresa', 'id')],
            'origen' => ['required', 'string', Rule::in(self::DEPARTAMENTOS)],
            'destino' => ['required', 'string', Rule::in(self::DEPARTAMENTOS)],
            'servicio' => ['required', 'string', Rule::in(self::SERVICIOS)],
            'kilo' => 'required|numeric|min:0',
            'kilo_extra' => 'required|numeric|min:0',
            'provincia' => 'required|string|max:255',
            'retencion' => 'required|numeric|min:0|max:100',
            'horas_entrega' => 'required|integer|min:0',
        ];
    }

    private function normalizeHeader($value): string
    {
        return strtolower(trim((string) $value));
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

    private function normalizeServicio(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = str_replace('LOCAL (REGULAR)', 'LOCAL(REGULAR)', $value);
        $value = str_replace('LOCAL (EXPRESS)', 'LOCAL(EXPRESS)', $value);

        return $value;
    }
}

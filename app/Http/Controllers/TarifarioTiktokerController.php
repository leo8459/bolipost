<?php

namespace App\Http\Controllers;

use App\Models\Destino;
use App\Models\Origen;
use App\Models\ServicioExtra;
use App\Models\TarifarioTiktoker;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TarifarioTiktokerController extends Controller
{
    private const IMPORT_COLUMNS = [
        'origen',
        'destino',
        'servicio_extra',
        'peso1',
        'peso2',
        'peso3',
        'peso_extra',
        'tiempo_entrega',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $origenId = max(0, (int) $request->query('origen_id', 0));
        $destinoId = max(0, (int) $request->query('destino_id', 0));

        $tarifas = TarifarioTiktoker::query()
            ->with([
                'origen:id,nombre_origen',
                'destino:id,nombre_destino',
                'servicioExtra:id,nombre',
            ])
            ->when($origenId > 0, fn ($query) => $query->where('origen_id', $origenId))
            ->when($destinoId > 0, fn ($query) => $query->where('destino_id', $destinoId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($search) use ($q) {
                    $search->whereRaw('CAST(peso1 AS TEXT) ILIKE ?', ["%{$q}%"])
                        ->orWhereRaw('CAST(peso2 AS TEXT) ILIKE ?', ["%{$q}%"])
                        ->orWhereRaw('CAST(peso3 AS TEXT) ILIKE ?', ["%{$q}%"])
                        ->orWhereRaw('CAST(peso_extra AS TEXT) ILIKE ?', ["%{$q}%"])
                        ->orWhereRaw('CAST(tiempo_entrega AS TEXT) ILIKE ?', ["%{$q}%"])
                        ->orWhereHas('origen', fn ($sub) => $sub->where('nombre_origen', 'ILIKE', "%{$q}%"))
                        ->orWhereHas('destino', fn ($sub) => $sub->where('nombre_destino', 'ILIKE', "%{$q}%"))
                        ->orWhereHas('servicioExtra', fn ($sub) => $sub->where('nombre', 'ILIKE', "%{$q}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('tarifario_tiktoker.index', [
            'tarifas' => $tarifas,
            'q' => $q,
            'origenId' => $origenId,
            'destinoId' => $destinoId,
            'origenes' => Origen::query()->orderBy('nombre_origen')->get(['id', 'nombre_origen']),
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(['id', 'nombre_destino']),
            'servicioExtras' => ServicioExtra::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function create()
    {
        return view('tarifario_tiktoker.create', [
            'tarifa' => new TarifarioTiktoker(),
            'origenes' => Origen::query()->orderBy('nombre_origen')->get(['id', 'nombre_origen']),
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(['id', 'nombre_destino']),
            'servicioExtras' => ServicioExtra::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request)
    {
        TarifarioTiktoker::query()->create($this->validateData($request));

        return redirect()
            ->route('tarifario-tiktoker.index')
            ->with('success', 'Tarifario tiktoker creado correctamente.');
    }

    public function edit(TarifarioTiktoker $tarifarioTiktoker)
    {
        return view('tarifario_tiktoker.edit', [
            'tarifa' => $tarifarioTiktoker,
            'origenes' => Origen::query()->orderBy('nombre_origen')->get(['id', 'nombre_origen']),
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(['id', 'nombre_destino']),
            'servicioExtras' => ServicioExtra::query()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, TarifarioTiktoker $tarifarioTiktoker)
    {
        $tarifarioTiktoker->update($this->validateData($request));

        return redirect()
            ->route('tarifario-tiktoker.index')
            ->with('success', 'Tarifario tiktoker actualizado correctamente.');
    }

    public function destroy(TarifarioTiktoker $tarifarioTiktoker)
    {
        $tarifarioTiktoker->delete();

        return redirect()
            ->route('tarifario-tiktoker.index')
            ->with('success', 'Tarifario tiktoker eliminado correctamente.');
    }

    public function importForm()
    {
        return view('tarifario_tiktoker.import', [
            'columnas' => self::IMPORT_COLUMNS,
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $origenMap = Origen::query()
            ->get(['id', 'nombre_origen'])
            ->mapWithKeys(fn ($origen) => [strtoupper(trim((string) $origen->nombre_origen)) => (int) $origen->id]);

        $destinoMap = Destino::query()
            ->get(['id', 'nombre_destino'])
            ->mapWithKeys(fn ($destino) => [strtoupper(trim((string) $destino->nombre_destino)) => (int) $destino->id]);

        $servicioExtraMap = ServicioExtra::query()
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn ($extra) => [strtoupper(trim((string) $extra->nombre)) => (int) $extra->id]);

        try {
            $spreadsheet = IOFactory::load((string) $request->file('archivo')->getRealPath());
        } catch (\Throwable) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo Excel.'])->withInput();
        }

        $sheet = $spreadsheet->getSheetByName('TarifarioTiktoker') ?: $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            return back()->withErrors(['archivo' => 'El archivo esta vacio.'])->withInput();
        }

        $header = array_map(fn ($value) => $this->normalizeHeader($value), array_shift($rows));
        $missing = array_values(array_diff(self::IMPORT_COLUMNS, $header));

        if ($missing !== []) {
            return back()
                ->withErrors(['archivo' => 'Columnas faltantes en Excel: ' . implode(', ', $missing)])
                ->withInput();
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $line = 1;

        foreach ($rows as $row) {
            $line++;

            if (! collect($row)->contains(fn ($value) => trim((string) $value) !== '')) {
                continue;
            }

            $data = [];
            foreach ($header as $index => $column) {
                $data[$column] = trim((string) ($row[$index] ?? ''));
            }

            $origenNombre = strtoupper((string) ($data['origen'] ?? ''));
            $destinoNombre = strtoupper((string) ($data['destino'] ?? ''));
            $servicioExtraNombre = strtoupper((string) ($data['servicio_extra'] ?? ''));

            $payload = [
                'origen_id' => $origenMap[$origenNombre] ?? null,
                'destino_id' => $destinoMap[$destinoNombre] ?? null,
                'servicio_extra_id' => $servicioExtraNombre === '' ? null : ($servicioExtraMap[$servicioExtraNombre] ?? null),
                'peso1' => $this->parseDecimal($data['peso1'] ?? null),
                'peso2' => $this->parseDecimal($data['peso2'] ?? null),
                'peso3' => $this->parseDecimal($data['peso3'] ?? null),
                'peso_extra' => $this->parseDecimal($data['peso_extra'] ?? null),
                'tiempo_entrega' => $this->parseInteger($data['tiempo_entrega'] ?? null),
            ];

            $validator = Validator::make($payload, $this->validationRules());

            if ($validator->fails()) {
                $errors[] = "Linea {$line}: " . $validator->errors()->first();
                continue;
            }

            $validated = $validator->validated();
            $existing = TarifarioTiktoker::query()
                ->where('origen_id', (int) $validated['origen_id'])
                ->where('destino_id', (int) $validated['destino_id'])
                ->where(function ($query) use ($validated) {
                    if (($validated['servicio_extra_id'] ?? null) === null) {
                        $query->whereNull('servicio_extra_id');
                    } else {
                        $query->where('servicio_extra_id', (int) $validated['servicio_extra_id']);
                    }
                })
                ->first();

            if ($existing) {
                $existing->update(Arr::only($validated, ['servicio_extra_id', 'peso1', 'peso2', 'peso3', 'peso_extra', 'tiempo_entrega']));
                $updated++;
                continue;
            }

            TarifarioTiktoker::query()->create($validated);
            $created++;
        }

        $message = "Importacion completada. Creadas: {$created}, actualizadas: {$updated}.";
        $redirect = redirect()->route('tarifario-tiktoker.index')->with('success', $message);

        if ($errors !== []) {
            $redirect->with('warning', 'Se encontraron ' . count($errors) . ' fila(s) con error.');
            $redirect->with('import_errors', array_slice($errors, 0, 20));
        }

        return $redirect;
    }

    public function downloadTemplateExcel()
    {
        $origenes = Origen::query()->orderBy('nombre_origen')->get(['id', 'nombre_origen']);
        $destinos = Destino::query()->orderBy('nombre_destino')->get(['id', 'nombre_destino']);
        $servicioExtras = ServicioExtra::query()->orderBy('nombre')->get(['nombre']);

        return response()->streamDownload(function () use ($origenes, $destinos, $servicioExtras) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('TarifarioTiktoker');
            $sheet->fromArray(self::IMPORT_COLUMNS, null, 'A1');

            $sheet->getStyle('A1:H1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF20539A']],
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
            $sheet->setAutoFilter('A1:H1');

            foreach (['A' => 24, 'B' => 24, 'C' => 22, 'D' => 12, 'E' => 12, 'F' => 12, 'G' => 14, 'H' => 16] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $sheet->getStyle('D2:G5000')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('H2:H5000')->getNumberFormat()->setFormatCode('0');

            $sheetOrigenes = $spreadsheet->createSheet();
            $sheetOrigenes->setTitle('Origenes');
            $sheetOrigenes->fromArray(['nombre_origen'], null, 'A1');
            $row = 2;
            foreach ($origenes as $origen) {
                $sheetOrigenes->setCellValue("A{$row}", (string) $origen->nombre_origen);
                $row++;
            }
            $sheetOrigenes->getColumnDimension('A')->setWidth(30);

            $sheetDestinos = $spreadsheet->createSheet();
            $sheetDestinos->setTitle('Destinos');
            $sheetDestinos->fromArray(['nombre_destino'], null, 'A1');
            $row = 2;
            foreach ($destinos as $destino) {
                $sheetDestinos->setCellValue("A{$row}", (string) $destino->nombre_destino);
                $row++;
            }
            $sheetDestinos->getColumnDimension('A')->setWidth(30);

            $sheetServicioExtras = $spreadsheet->createSheet();
            $sheetServicioExtras->setTitle('ServiciosExtras');
            $sheetServicioExtras->fromArray(['nombre'], null, 'A1');
            $row = 2;
            foreach ($servicioExtras as $extra) {
                $sheetServicioExtras->setCellValue("A{$row}", (string) $extra->nombre);
                $row++;
            }
            $sheetServicioExtras->getColumnDimension('A')->setWidth(28);

            $sheetInstrucciones = $spreadsheet->createSheet();
            $sheetInstrucciones->setTitle('Instrucciones');
            $sheetInstrucciones->setCellValue('A1', 'INSTRUCCIONES DE USO');
            $sheetInstrucciones->setCellValue('A3', '1) No cambies los nombres de columnas en la hoja TarifarioTiktoker.');
            $sheetInstrucciones->setCellValue('A4', '2) Usa los nombres de departamento de las hojas Origenes y Destinos.');
            $sheetInstrucciones->setCellValue('A5', '3) servicio_extra puede quedar vacio o usar un nombre de la hoja ServiciosExtras.');
            $sheetInstrucciones->setCellValue('A6', '4) origen y destino deben escribirse exactamente como en las listas.');
            $sheetInstrucciones->setCellValue('A7', '5) peso1, peso2, peso3 y peso_extra son montos numericos.');
            $sheetInstrucciones->setCellValue('A8', '6) tiempo_entrega se registra en horas.');
            $sheetInstrucciones->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'color' => ['argb' => 'FF0D47A1'],
            ]);
            $sheetInstrucciones->getColumnDimension('A')->setWidth(100);

            $this->applyListValidation($sheet, 'A2:A5000', '=Origenes!$A$2:$A$' . max(2, $origenes->count() + 1));
            $this->applyListValidation($sheet, 'B2:B5000', '=Destinos!$A$2:$A$' . max(2, $destinos->count() + 1));
            $this->applyListValidation($sheet, 'C2:C5000', '=ServiciosExtras!$A$2:$A$' . max(2, $servicioExtras->count() + 1));

            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, 'plantilla_tarifario_tiktoker.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate($this->validationRules());
    }

    private function validationRules(): array
    {
        return [
            'origen_id' => ['required', 'integer', Rule::exists('origen', 'id')],
            'destino_id' => ['required', 'integer', Rule::exists('destino', 'id')],
            'servicio_extra_id' => ['nullable', 'integer', Rule::exists('servicio_extras', 'id')],
            'peso1' => ['required', 'numeric', 'min:0'],
            'peso2' => ['required', 'numeric', 'min:0'],
            'peso3' => ['required', 'numeric', 'min:0'],
            'peso_extra' => ['required', 'numeric', 'min:0'],
            'tiempo_entrega' => ['required', 'integer', 'min:0'],
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

        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (str_contains($text, ',')) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float) $text : null;
    }

    private function parseInteger($value): ?int
    {
        $text = trim((string) $value);

        return $text === '' || ! is_numeric($text) ? null : (int) $text;
    }

    private function applyListValidation(Worksheet $sheet, string $range, string $formula): void
    {
        [$start, $end] = explode(':', $range);
        $validation = $sheet->getCell($start)->getDataValidation();
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
}

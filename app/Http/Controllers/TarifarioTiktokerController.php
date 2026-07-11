<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Destino;
use App\Models\Origen;
use App\Models\ServicioExtra;
use App\Models\TarifarioTiktoker;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

class TarifarioTiktokerController extends Controller
{
    private const REGIONAL_DISPLAY_NAMES = [
        'PANDO' => 'COBIJA',
        'BENI' => 'TRINIDAD',
        'CHUQUISACA' => 'SUCRE',
    ];

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

    private const REQUIRED_IMPORT_COLUMNS = [
        'origen',
        'destino',
        'peso1',
        'peso2',
        'peso_extra',
        'tiempo_entrega',
    ];

    public function index(Request $request)
    {
        $filters = $this->extractFilters($request);

        $tarifas = $this->buildFilteredQuery($filters)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('tarifario_tiktoker.index', [
            'tarifas' => $tarifas,
            'q' => $filters['q'],
            'origenId' => $filters['origen_id'],
            'destinoId' => $filters['destino_id'],
            'origenes' => Origen::query()->orderBy('nombre_origen')->get(['id', 'nombre_origen']),
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(['id', 'nombre_destino']),
            'servicioExtras' => ServicioExtra::query()->orderBy('nombre')->get(['id', 'nombre']),
            'regionalNameMap' => self::REGIONAL_DISPLAY_NAMES,
        ]);
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->extractFilters($request);
        $tarifas = $this->buildFilteredQuery($filters)
            ->orderBy('origen_id')
            ->orderBy('destino_id')
            ->orderBy('servicio_extra_id')
            ->get();

        $groupedTarifas = $this->groupTarifasByOrigen($tarifas);

        $pdf = Pdf::loadView('tarifario_tiktoker.report-pdf', [
            'groupedTarifas' => $groupedTarifas,
            'generatedAt' => now(),
            'totalTarifas' => $tarifas->count(),
            'filters' => $this->buildFilterSummary($filters),
        ])->setPaper('A4', 'landscape');

        return $pdf->stream('tarifario-tiktoker-' . now()->format('Ymd-His') . '.pdf');
    }

    public function downloadReportExcel(Request $request)
    {
        $filters = $this->extractFilters($request);
        $tarifas = $this->buildFilteredQuery($filters)
            ->orderBy('origen_id')
            ->orderBy('destino_id')
            ->orderBy('servicio_extra_id')
            ->get();

        $groupedTarifas = $this->groupTarifasByOrigen($tarifas);
        $filename = 'tarifario_tiktoker_' . now()->format('Ymd_His') . '.xlsx';
        $filterSummary = $this->buildFilterSummary($filters);

        return response()->streamDownload(function () use ($groupedTarifas, $tarifas, $filterSummary) {
            $spreadsheet = new Spreadsheet();
            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Resumen');

            $summarySheet->mergeCells('A1:H1');
            $summarySheet->setCellValue('A1', 'REPORTE TARIFARIO TIKTOKER');
            $summarySheet->mergeCells('A2:H2');
            $summarySheet->setCellValue('A2', 'Generado el ' . now()->format('d/m/Y H:i'));

            $summarySheet->fromArray([
                ['Filtros aplicados', $filterSummary],
                ['Total registros', $tarifas->count()],
                ['Total departamentos', $groupedTarifas->count()],
            ], null, 'A4');

            $summarySheet->fromArray([
                ['Departamento', 'Cantidad registros', 'Peso 1 promedio (hasta 2 kg)', 'Peso 2 promedio (hasta 5 kg)', 'Peso 3 promedio (opcional)', 'Peso extra promedio (+ de 5 kg)', 'Tiempo promedio (h)', 'Servicios distintos'],
            ], null, 'A8');

            $summaryRow = 9;
            foreach ($groupedTarifas as $department => $items) {
                $summarySheet->fromArray([[
                    $department,
                    $items->count(),
                    round((float) $items->avg('peso1'), 2),
                    round((float) $items->avg('peso2'), 2),
                    round((float) $items->avg('peso3'), 2),
                    round((float) $items->avg('peso_extra'), 2),
                    round((float) $items->avg('tiempo_entrega'), 0),
                    $items->map(fn ($item) => optional($item->servicioExtra)->nombre)->filter()->unique()->count(),
                ]], null, "A{$summaryRow}");
                $summaryRow++;
            }

            $this->styleExcelReportSheet($summarySheet, 'A1:H2', 'A8:H8', max(8, $summaryRow - 1));
            $summarySheet->getColumnDimension('A')->setWidth(24);
            $summarySheet->getColumnDimension('B')->setWidth(18);
            foreach (['C', 'D', 'E', 'F'] as $column) {
                $summarySheet->getColumnDimension($column)->setWidth(24);
            }
            foreach (['G', 'H'] as $column) {
                $summarySheet->getColumnDimension($column)->setWidth(18);
            }
            $summarySheet->getStyle('C9:G' . max(9, $summaryRow - 1))->getNumberFormat()->setFormatCode('#,##0.00');
            $summarySheet->getStyle('A8:H8')->getAlignment()->setWrapText(true);
            $summarySheet->freezePane('A9');

            $sheetIndex = 1;
            foreach ($groupedTarifas as $department => $items) {
                $sheet = $spreadsheet->createSheet($sheetIndex++);
                $sheet->setTitle($this->sheetTitleForDepartment($department, $sheetIndex - 1));

                $sheet->mergeCells('A1:H1');
                $sheet->setCellValue('A1', 'TARIFARIO TIKTOKER - ' . $department);
                $sheet->mergeCells('A2:H2');
                $sheet->setCellValue('A2', 'Registros: ' . $items->count());
                $sheet->fromArray([
                    ['Destino', 'Servicio extra', 'Peso 1 (hasta 2 kg)', 'Peso 2 (hasta 5 kg)', 'Peso 3 (opcional)', 'Peso extra (+ de 5 kg)', 'Tiempo entrega (h)', 'Referencia 5 kg + 1 kg extra'],
                ], null, 'A4');

                $row = 5;
                foreach ($items as $item) {
                    $sheet->fromArray([[
                        $this->regionalDisplayName((string) optional($item->destino)->nombre_destino),
                        (string) (optional($item->servicioExtra)->nombre ?? 'General'),
                        (float) $item->peso1,
                        (float) $item->peso2,
                        $item->peso3 !== null ? (float) $item->peso3 : null,
                        (float) $item->peso_extra,
                        (int) $item->tiempo_entrega,
                        (float) $item->peso2 + (float) $item->peso_extra,
                    ]], null, "A{$row}");
                    $row++;
                }

                $lastRow = max(4, $row - 1);
                $this->styleExcelReportSheet($sheet, 'A1:H2', 'A4:H4', $lastRow);
                $sheet->setAutoFilter("A4:H{$lastRow}");
                $sheet->freezePane('A5');
                foreach (['A' => 22, 'B' => 28, 'C' => 20, 'D' => 20, 'E' => 20, 'F' => 22, 'G' => 18, 'H' => 24] as $column => $width) {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
                $sheet->getStyle('A4:H4')->getAlignment()->setWrapText(true);
                $sheet->getStyle("C5:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }

            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function create()
    {
        return view('tarifario_tiktoker.create', [
            'tarifa' => new TarifarioTiktoker(),
            'origenes' => Origen::query()->orderBy('nombre_origen')->get(['id', 'nombre_origen']),
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(['id', 'nombre_destino']),
            'servicioExtras' => ServicioExtra::query()->orderBy('nombre')->get(['id', 'nombre']),
            'regionalNameMap' => self::REGIONAL_DISPLAY_NAMES,
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
            'regionalNameMap' => self::REGIONAL_DISPLAY_NAMES,
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
            ->flatMap(function ($origen) {
                $original = strtoupper(trim((string) $origen->nombre_origen));
                $display = $this->regionalDisplayName($original);

                return [
                    $original => (int) $origen->id,
                    $display => (int) $origen->id,
                ];
            });

        $destinoMap = Destino::query()
            ->get(['id', 'nombre_destino'])
            ->flatMap(function ($destino) {
                $original = strtoupper(trim((string) $destino->nombre_destino));
                $display = $this->regionalDisplayName($original);

                return [
                    $original => (int) $destino->id,
                    $display => (int) $destino->id,
                ];
            });

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
        $missing = array_values(array_diff(self::REQUIRED_IMPORT_COLUMNS, $header));

        if ($missing !== []) {
            return back()
                ->withErrors(['archivo' => 'Columnas faltantes en Excel: ' . implode(', ', $missing)])
                ->withInput();
        }

        $created = 0;
        $updated = 0;
        $errors = [];
        $validRows = [];
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
                'peso3' => array_key_exists('peso3', $data) ? $this->parseDecimal($data['peso3']) : null,
                'peso_extra' => $this->parseDecimal($data['peso_extra'] ?? null),
                'tiempo_entrega' => $this->parseInteger($data['tiempo_entrega'] ?? null),
            ];

            $validator = Validator::make($payload, $this->validationRules());

            if ($validator->fails()) {
                $errors[] = "Linea {$line}: " . $validator->errors()->first();
                continue;
            }

            $validated = $validator->validated();
            $validRows[] = $validated;
        }

        if ($errors !== []) {
            return back()
                ->withInput()
                ->with('warning', 'No se guardo ninguna fila porque el archivo tiene ' . count($errors) . ' error(es). Corrige el Excel y vuelve a importarlo.')
                ->with('import_errors', $errors);
        }

        if ($validRows === []) {
            return back()
                ->with('warning', 'No se guardo ninguna fila. Revisa que el archivo tenga datos desde la fila 2.');
        }

        DB::transaction(function () use ($validRows, &$created, &$updated) {
            foreach ($validRows as $validated) {
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
        });

        $message = "Importacion completada. Creadas: {$created}, actualizadas: {$updated}.";

        return redirect()->route('tarifario-tiktoker.index')->with('success', $message);
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

            foreach (['A' => 24, 'B' => 24, 'C' => 22, 'D' => 16, 'E' => 16, 'F' => 16, 'G' => 18, 'H' => 16] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $sheet->getStyle('D2:G5000')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('H2:H5000')->getNumberFormat()->setFormatCode('0');

            $sheetOrigenes = $spreadsheet->createSheet();
            $sheetOrigenes->setTitle('Origenes');
            $sheetOrigenes->fromArray(['nombre_origen'], null, 'A1');
            $row = 2;
            foreach ($origenes as $origen) {
                $sheetOrigenes->setCellValue("A{$row}", $this->regionalDisplayName((string) $origen->nombre_origen));
                $row++;
            }
            $sheetOrigenes->getColumnDimension('A')->setWidth(30);

            $sheetDestinos = $spreadsheet->createSheet();
            $sheetDestinos->setTitle('Destinos');
            $sheetDestinos->fromArray(['nombre_destino'], null, 'A1');
            $row = 2;
            foreach ($destinos as $destino) {
                $sheetDestinos->setCellValue("A{$row}", $this->regionalDisplayName((string) $destino->nombre_destino));
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
            $sheetInstrucciones->setCellValue('A5', '3) servicio_extra y peso3 pueden quedar vacios.');
            $sheetInstrucciones->setCellValue('A6', '4) origen y destino deben escribirse exactamente como en las listas.');
            $sheetInstrucciones->setCellValue('A7', '5) peso1 es el precio hasta 2 kg, peso2 hasta 5 kg, peso3 es opcional y peso_extra se suma por cada kg o fraccion adicional despues de 5 kg.');
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
            'peso3' => ['nullable', 'numeric', 'min:0'],
            'peso_extra' => ['required', 'numeric', 'min:0'],
            'tiempo_entrega' => ['required', 'integer', 'min:0'],
        ];
    }

    private function normalizeHeader($value): string
    {
        return strtolower(trim((string) $value));
    }

    private function regionalDisplayName(string $name): string
    {
        $normalized = strtoupper(trim($name));

        return self::REGIONAL_DISPLAY_NAMES[$normalized] ?? $normalized;
    }

    private function extractFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'origen_id' => max(0, (int) $request->query('origen_id', 0)),
            'destino_id' => max(0, (int) $request->query('destino_id', 0)),
        ];
    }

    private function buildFilteredQuery(array $filters)
    {
        $q = (string) ($filters['q'] ?? '');
        $origenId = (int) ($filters['origen_id'] ?? 0);
        $destinoId = (int) ($filters['destino_id'] ?? 0);

        return TarifarioTiktoker::query()
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
            });
    }

    private function groupTarifasByOrigen(Collection $tarifas): Collection
    {
        return $tarifas
            ->groupBy(fn ($item) => $this->regionalDisplayName((string) optional($item->origen)->nombre_origen))
            ->map(fn (Collection $items) => $items->sortBy(function ($item) {
                $destino = $this->regionalDisplayName((string) optional($item->destino)->nombre_destino);
                $servicio = (string) (optional($item->servicioExtra)->nombre ?? 'General');

                return $destino . '|' . $servicio;
            })->values())
            ->sortKeys();
    }

    private function buildFilterSummary(array $filters): string
    {
        $parts = [];

        if ((int) ($filters['origen_id'] ?? 0) > 0) {
            $origen = Origen::query()->find((int) $filters['origen_id'], ['nombre_origen']);
            if ($origen) {
                $parts[] = 'Origen: ' . $this->regionalDisplayName((string) $origen->nombre_origen);
            }
        }

        if ((int) ($filters['destino_id'] ?? 0) > 0) {
            $destino = Destino::query()->find((int) $filters['destino_id'], ['nombre_destino']);
            if ($destino) {
                $parts[] = 'Destino: ' . $this->regionalDisplayName((string) $destino->nombre_destino);
            }
        }

        if ((string) ($filters['q'] ?? '') !== '') {
            $parts[] = 'Busqueda: ' . $filters['q'];
        }

        return $parts === [] ? 'Sin filtros' : implode(' | ', $parts);
    }

    private function styleExcelReportSheet(Worksheet $sheet, string $titleRange, string $headerRange, int $lastRow): void
    {
        [$headerStart, $headerEnd] = explode(':', $headerRange);
        $headerStartRow = (int) preg_replace('/^[A-Z]+/', '', strtoupper($headerStart));
        $lastHeaderColumn = preg_replace('/\d+/', '', strtoupper($headerEnd)) ?: 'A';

        $sheet->getStyle($titleRange)->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF20539A']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FF1F2937']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFECF64']],
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

        $sheet->getStyle("A{$headerStartRow}:{$lastHeaderColumn}{$lastRow}")->applyFromArray([
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
    }

    private function sheetTitleForDepartment(string $department, int $index): string
    {
        $clean = Str::upper(Str::ascii($department));
        $clean = preg_replace('/[^A-Z0-9 ]+/', '', $clean) ?? $clean;
        $clean = trim($clean);
        $prefix = $index . '-';
        $maxLength = 31 - strlen($prefix);

        return $prefix . Str::limit($clean !== '' ? $clean : 'DEPARTAMENTO', $maxLength, '');
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

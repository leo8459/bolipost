<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\TarifaContrato;
use App\Support\AclPermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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

class TarifaContratoController extends Controller
{
    private const SERVICIOS = [
        'ENVIO NACIONAL (REGULAR)',
        'ENVIO NACIONAL (REGULAR) TERRESTRE',
        'ENVIO NACIONAL (REGULAR) AEREO',
        'ENVIO NACIONAL (EXPRESS)',
        'ENVIO NACIONAL (EXPRESS) TERRESTRE',
        'ENVIO NACIONAL (EXPRESS) AEREO',
        'ENVIO LOCAL(REGULAR)',
        'ENVIO LOCAL(EXPRESS)',
        'ENVIO INTERPROVINCIAL',
        'ENVIO INTERPROVINCIAL TERRESTRE',
        'ENVIO INTERPROVINCIAL AEREO',
    ];

    private const DEPARTAMENTOS = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'SUCRE',
        'TRINIDAD',
        'COBIJA',
    ];

    private const DEPARTAMENTO_ALIASES = [
        'CHUQUISACA' => 'SUCRE',
        'BENI' => 'TRINIDAD',
        'PANDO' => 'COBIJA',
    ];

    private const PROVINCIAS_POR_DEPARTAMENTO = [
        'LA PAZ' => [
            'MURILLO', 'OMASUYOS', 'PACAJES', 'CAMACHO', 'MUNECAS', 'LARECAJA',
            'FRANZ TAMAYO', 'INGAVI', 'LOAYZA', 'INQUISIVI', 'SUD YUNGAS',
            'LOS ANDES', 'AROMA', 'NOR YUNGAS', 'ABEL ITURRALDE',
            'BAUTISTA SAAVEDRA', 'MANCO KAPAC', 'GUALBERTO VILLARROEL',
            'JOSE MANUEL COBIJA', 'CARANAVI',
        ],
        'COCHABAMBA' => [
            'CERCADO', 'CAMPERO', 'AYOPAYA', 'ESTEBAN ARCE', 'ARANI', 'ARQUE',
            'CAPINOTA', 'GERMAN JORDAN', 'QUILLACOLLO', 'CHAPARE', 'TAPACARI',
            'CARRASCO', 'MIZQUE', 'PUNATA', 'BOLIVAR', 'TIRAQUE',
        ],
        'SANTA CRUZ' => [
            'ANDRES IBANEZ', 'WARNES', 'VALLEGRANDE', 'ICHILO', 'CHIQUITOS',
            'SARA', 'CORDILLERA', 'FLORIDA', 'MANUEL MARIA CABALLERO',
            'GUARAYOS', 'NUFLO DE CHAVEZ', 'VELASCO', 'ANGEL SANDOVAL',
            'GERMAN BUSCH',
        ],
        'ORURO' => [
            'CERCADO', 'CARANGAS', 'SAUCARI', 'SABAYA', 'LADISLAO CABRERA',
            'LITORAL', 'POOPO', 'PANTALEON DALENCE', 'SAJAMA',
            'SAN PEDRO DE TOTORA', 'SEBASTIAN PAGADOR', 'EDUARDO AVAROA',
            'NOR CARANGAS', 'SUR CARANGAS', 'TOMAS BARRON',
        ],
        'POTOSI' => [
            'TOMAS FRIAS', 'RAFAEL BUSTILLO', 'CORNELIO SAAVEDRA', 'CHAYANTA',
            'CHARCAS', 'NOR CHICHAS', 'ALONSO DE IBANEZ', 'SUD CHICHAS',
            'NOR LIPEZ', 'SUD LIPEZ', 'JOSE MARIA LINARES', 'ANTONIO QUIJARRO',
            'DANIEL CAMPOS', 'MODESTO OMISTE', 'BILBAO RIOJA', 'ENRIQUE BALDIVIESO',
        ],
        'TARIJA' => [
            'CERCADO', 'ANICETO ARCE', 'BURDETT OCONNOR', 'GRAN CHACO',
            'JOSE MARIA AVILES', 'MENDEZ',
        ],
        'SUCRE' => [
            'OROPEZA', 'AZURDUY', 'ZUDANEZ', 'TOMINA', 'HERNANDO SILES',
            'YAMPARAEZ', 'NOR CINTI', 'SUD CINTI', 'BELISARIO BOETO', 'LUIS CALVO',
        ],
        'TRINIDAD' => [
            'CERCADO', 'VACA DIEZ', 'JOSE BALLIVIAN', 'YACUMA', 'MOXOS',
            'MAMORE', 'MARBAN', 'ITENE',
        ],
        'COBIJA' => [
            'NICOLAS SUAREZ', 'MANURIPI', 'MADRE DE DIOS', 'ABUNA', 'FEDERICO ROMAN',
        ],
    ];

    private const IMPORT_COLUMNS = [
        'empresa_nombre',
        'origen',
        'destino',
        'servicio',
        'direccion',
        'zona',
        'kilo_de_1_a_2',
        'kilo',
        'kilo_extra',
        'provincia_origen',
        'provincia_destino',
        'retencion',
        'horas_entrega',
    ];

    private const REQUIRED_IMPORT_COLUMNS = [
        'empresa_nombre',
        'origen',
        'destino',
        'servicio',
        'kilo',
        'kilo_extra',
        'retencion',
        'horas_entrega',
    ];

    private const FORM_FIELDS = [
        'empresa_id',
        'origen',
        'destino',
        'servicio',
        'direccion',
        'zona',
        'peso',
        'kilo',
        'kilo_extra',
        'provincia',
        'provincia_origen',
        'retencion',
        'horas_entrega',
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $servicio = strtoupper(trim((string) $request->query('servicio', '')));
        $origen = strtoupper(trim((string) $request->query('origen', '')));
        $destino = strtoupper(trim((string) $request->query('destino', '')));
        $empresaId = (int) $request->query('empresa_id', 0);

        $empresaId = $empresaId > 0 ? $empresaId : 0;

        $serviciosFiltro = TarifaContrato::query()
            ->select('servicio')
            ->whereNotNull('servicio')
            ->whereRaw("trim(servicio) <> ''")
            ->distinct()
            ->orderBy('servicio')
            ->pluck('servicio');

        $origenesFiltro = TarifaContrato::query()
            ->select('origen')
            ->whereNotNull('origen')
            ->whereRaw("trim(origen) <> ''")
            ->distinct()
            ->orderBy('origen')
            ->pluck('origen');

        $destinosFiltro = TarifaContrato::query()
            ->select('destino')
            ->whereNotNull('destino')
            ->whereRaw("trim(destino) <> ''")
            ->distinct()
            ->orderBy('destino')
            ->pluck('destino');

        $empresasFiltro = Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla']);

        $tarifas = TarifaContrato::query()
            ->with('empresa')
            ->when($empresaId > 0, function ($query) use ($empresaId) {
                $query->where('empresa_id', $empresaId);
            })
            ->when($servicio !== '', function ($query) use ($servicio) {
                $query->whereRaw('trim(upper(servicio)) = ?', [$servicio]);
            })
            ->when($origen !== '', function ($query) use ($origen) {
                $query->whereRaw('trim(upper(origen)) = ?', [$origen]);
            })
            ->when($destino !== '', function ($query) use ($destino) {
                $query->whereRaw('trim(upper(destino)) = ?', [$destino]);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('origen', 'ILIKE', "%{$q}%")
                        ->orWhere('destino', 'ILIKE', "%{$q}%")
                        ->orWhere('servicio', 'ILIKE', "%{$q}%")
                        ->orWhere('provincia', 'ILIKE', "%{$q}%")
                        ->orWhere('provincia_origen', 'ILIKE', "%{$q}%")
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
            'servicio' => $servicio,
            'origen' => $origen,
            'destino' => $destino,
            'empresaId' => $empresaId,
            'serviciosFiltro' => $serviciosFiltro,
            'origenesFiltro' => $origenesFiltro,
            'destinosFiltro' => $destinosFiltro,
            'empresasFiltro' => $empresasFiltro,
        ]);
    }

    public function create(Request $request)
    {
        $copyId = (int) $request->query('copy_id', 0);
        $this->authorizeTarifaContratoButtonAction($copyId > 0 ? 'duplicate' : 'create');

        if ($request->boolean('reset')) {
            $request->session()->forget('tarifa_contrato_defaults');
        }

        $defaults = (array) $request->session()->get('tarifa_contrato_defaults', []);
        $copySource = null;

        if ($copyId > 0) {
            $copySource = TarifaContrato::query()->find($copyId);
            if ($copySource) {
                $defaults = Arr::only($copySource->toArray(), self::FORM_FIELDS);
            }
        }

        return view('tarifa_contrato.create', [
            'tarifaContrato' => new TarifaContrato(),
            'empresas' => Empresa::query()->orderBy('nombre')->get(),
            'servicios' => self::SERVICIOS,
            'departamentos' => self::DEPARTAMENTOS,
            'provinciasPorDepartamento' => self::PROVINCIAS_POR_DEPARTAMENTO,
            'defaults' => $defaults,
            'copySource' => $copySource,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeTarifaContratoButtonAction('save');

        $data = $this->validateData($request);
        TarifaContrato::query()->create($data);

        if ((string) $request->input('action') === 'save_and_new') {
            return redirect()
                ->route('tarifa-contrato.create')
                ->with('success', 'Tarifa creada. Puedes registrar otra rapidamente.')
                ->with('tarifa_contrato_defaults', Arr::only($data, self::FORM_FIELDS));
        }

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato creada correctamente.');
    }

    public function importForm()
    {
        $this->authorizeTarifaContratoButtonAction('import');

        return view('tarifa_contrato.import', [
            'servicios' => self::SERVICIOS,
            'departamentos' => self::DEPARTAMENTOS,
            'columnas' => self::IMPORT_COLUMNS,
        ]);
    }

    public function import(Request $request)
    {
        $this->authorizeTarifaContratoButtonAction('import');

        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        $filePath = (string) $request->file('archivo')->getRealPath();

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            return back()->withErrors(['archivo' => 'No se pudo leer el archivo Excel.'])->withInput();
        }

        $sheet = $spreadsheet->getSheetByName('TarifaContrato');
        if (!$sheet) {
            $sheet = $spreadsheet->getSheet(0);
        }

        $rows = $sheet->toArray(null, true, true, false);
        if (empty($rows)) {
            return back()->withErrors(['archivo' => 'El archivo esta vacio.'])->withInput();
        }

        $header = array_shift($rows);
        $header = array_map(fn ($value) => $this->normalizeHeader($value), $header);
        $usesEmpresaCodigo = in_array('empresa_codigo', $header, true) && !in_array('empresa_nombre', $header, true);
        $requiredColumns = $usesEmpresaCodigo
            ? array_map(fn ($col) => $col === 'empresa_nombre' ? 'empresa_codigo' : $col, self::REQUIRED_IMPORT_COLUMNS)
            : self::REQUIRED_IMPORT_COLUMNS;
        $missing = array_values(array_diff($requiredColumns, $header));

        if (!empty($missing)) {
            return back()
                ->withErrors(['archivo' => 'Columnas faltantes en Excel: ' . implode(', ', $missing)])
                ->withInput();
        }

        $empresaCodigoMap = [];
        $empresaNombreMap = [];
        $empresaNombreDuplicado = [];
        $empresas = Empresa::query()->get(['id', 'codigo_cliente', 'nombre']);

        foreach ($empresas as $empresa) {
            $codigo = strtoupper(trim((string) $empresa->codigo_cliente));
            if ($codigo !== '') {
                $empresaCodigoMap[$codigo] = (int) $empresa->id;
            }

            $nombreKey = $this->normalizeCompanyName((string) $empresa->nombre);
            if ($nombreKey === '') {
                continue;
            }

            if (isset($empresaNombreMap[$nombreKey]) && $empresaNombreMap[$nombreKey] !== (int) $empresa->id) {
                $empresaNombreDuplicado[$nombreKey] = true;
            } else {
                $empresaNombreMap[$nombreKey] = (int) $empresa->id;
            }
        }

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

            $filaResumen = $this->buildImportRowSummary($data);

            $empresaId = null;
            if ($usesEmpresaCodigo) {
                $empresaCodigo = strtoupper((string) ($data['empresa_codigo'] ?? ''));
                $empresaId = $empresaCodigoMap[$empresaCodigo] ?? null;

                if (!$empresaId) {
                    $errors[] = "Linea {$line} ({$filaResumen}): no se guardo porque empresa_codigo '{$empresaCodigo}' no existe.";
                    continue;
                }
            } else {
                $empresaNombre = (string) ($data['empresa_nombre'] ?? '');
                $empresaNombreKey = $this->normalizeCompanyName($empresaNombre);

                if ($empresaNombreKey === '') {
                    $errors[] = "Linea {$line} ({$filaResumen}): no se guardo porque empresa_nombre es obligatorio.";
                    continue;
                }

                if (isset($empresaNombreDuplicado[$empresaNombreKey])) {
                    $errors[] = "Linea {$line} ({$filaResumen}): no se guardo porque empresa_nombre '{$empresaNombre}' es ambiguo (duplicado).";
                    continue;
                }

                $empresaId = $empresaNombreMap[$empresaNombreKey] ?? null;
                if (!$empresaId) {
                    $empresaId = $empresaCodigoMap[strtoupper($empresaNombreKey)] ?? null;
                }
                if (!$empresaId) {
                    $errors[] = "Linea {$line} ({$filaResumen}): no se guardo porque empresa_nombre '{$empresaNombre}' no existe.";
                    continue;
                }
            }

            $erroresNumericos = $this->detectInvalidNumericImportValues($data);
            if (!empty($erroresNumericos)) {
                $errors[] = "Linea {$line} ({$filaResumen}): no se guardo porque " . implode('; ', $erroresNumericos) . '.';
                continue;
            }

            $payload = [
                'empresa_id' => $empresaId,
                'origen' => $this->normalizeDepartamento($data['origen'] ?? null),
                'destino' => $this->normalizeDepartamento($data['destino'] ?? null),
                'servicio' => $this->normalizeServicio((string) ($data['servicio'] ?? '')),
                'direccion' => $this->normalizeNullableUpper($data['direccion'] ?? null),
                'zona' => $this->normalizeNullableUpper($data['zona'] ?? null),
                'peso' => $this->parseDecimal($data['kilo_de_1_a_2'] ?? ($data['peso'] ?? null)),
                'kilo' => $this->parseDecimal($data['kilo'] ?? null),
                'kilo_extra' => $this->parseDecimal($data['kilo_extra'] ?? null),
                'provincia_origen' => $this->normalizeNullableUpper($data['provincia_origen'] ?? null),
                'provincia' => $this->normalizeNullableUpper($data['provincia_destino'] ?? ($data['provincia'] ?? null)),
                'retencion' => $this->parseDecimal($data['retencion'] ?? null),
                'horas_entrega' => $data['horas_entrega'] ?? null,
            ];

            $validator = Validator::make(
                $payload,
                $this->validationRules((string) ($payload['destino'] ?? ''), (string) ($payload['origen'] ?? '')),
                $this->importValidationMessages(),
                [
                    'empresa_id' => 'empresa',
                    'origen' => 'origen',
                    'destino' => 'destino',
                    'servicio' => 'servicio',
                    'direccion' => 'direccion',
                    'zona' => 'zona',
                    'peso' => 'peso',
                    'kilo' => 'kilo',
                    'kilo_extra' => 'kilo extra',
                    'provincia' => 'provincia destino',
                    'provincia_origen' => 'provincia origen',
                    'retencion' => 'retencion',
                    'horas_entrega' => 'horas de entrega',
                ]
            );

            if ($validator->fails()) {
                $errors[] = "Linea {$line} ({$filaResumen}): " . $this->buildFriendlyImportValidationError($validator, $payload);
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
                'provincia_origen',
            ]);

            $values = Arr::only($validated, ['direccion', 'zona', 'peso', 'retencion', 'horas_entrega']);

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
        $redirect = redirect()->route('tarifa-contrato.import-form');

        if ($created === 0 && $updated === 0 && empty($errors)) {
            $redirect->with('warning', 'No se guardo ninguna fila. Revisa que el archivo tenga datos desde la fila 2 y valores validos.');
            return $redirect;
        }

        $redirect->with('success', $message);

        if (!empty($errors)) {
            $redirect->with('warning', 'Se encontraron ' . count($errors) . ' fila(s) con error.');
            $redirect->with('import_errors', array_slice($errors, 0, 20));
        }

        return $redirect;
    }

    private function buildImportRowSummary(array $data): string
    {
        $empresa = trim((string) ($data['empresa_nombre'] ?? $data['empresa_codigo'] ?? 'SIN EMPRESA'));
        $origen = trim((string) ($data['origen'] ?? 'SIN ORIGEN'));
        $destino = trim((string) ($data['destino'] ?? 'SIN DESTINO'));
        $servicio = trim((string) ($data['servicio'] ?? 'SIN SERVICIO'));

        return "empresa: {$empresa}, origen: {$origen}, destino: {$destino}, servicio: {$servicio}";
    }

    private function detectInvalidNumericImportValues(array $data): array
    {
        $errores = [];

        $decimalColumns = [
            'kilo_de_1_a_2' => 'kilo_de_1_a_2 (peso)',
            'kilo' => 'kilo',
            'kilo_extra' => 'kilo_extra',
            'retencion' => 'retencion',
        ];

        foreach ($decimalColumns as $column => $label) {
            $raw = trim((string) ($data[$column] ?? ''));
            if ($raw === '') {
                continue;
            }

            if ($this->parseDecimal($raw) === null) {
                $errores[] = "el campo {$label} tiene formato numerico invalido ('{$raw}')";
            }
        }

        $horasRaw = trim((string) ($data['horas_entrega'] ?? ''));
        if ($horasRaw !== '' && filter_var($horasRaw, FILTER_VALIDATE_INT) === false) {
            $errores[] = "el campo horas_entrega debe ser un numero entero ('{$horasRaw}')";
        }

        return $errores;
    }

    private function importValidationMessages(): array
    {
        return [
            'required' => 'el campo :attribute es obligatorio.',
            'numeric' => 'el campo :attribute debe ser numerico.',
            'integer' => 'el campo :attribute debe ser un numero entero.',
            'min.numeric' => 'el campo :attribute no puede ser menor a :min.',
            'min.integer' => 'el campo :attribute no puede ser menor a :min.',
            'max.numeric' => 'el campo :attribute no puede ser mayor a :max.',
            'max.string' => 'el campo :attribute no debe exceder :max caracteres.',
            'in' => 'el valor de :attribute no es valido para el departamento/servicio seleccionado.',
            'exists' => 'el valor de :attribute no existe en el sistema.',
        ];
    }

    private function buildFriendlyImportValidationError($validator, array $payload): string
    {
        $failed = $validator->failed();
        $field = array_key_first($failed);
        $rules = is_string($field) ? array_keys((array) ($failed[$field] ?? [])) : [];
        $rule = $rules[0] ?? null;

        if ($field === 'origen' && $rule === 'In') {
            return 'no se guardo porque el origen no coincide con un departamento valido. Usa por ejemplo LA PAZ, COCHABAMBA, SANTA CRUZ, SUCRE, TRINIDAD o COBIJA.';
        }

        if ($field === 'destino' && $rule === 'In') {
            return 'no se guardo porque el destino no coincide con un departamento valido. Si escribiste una provincia como destino, pon el departamento. Ejemplo: SUCRE para CHUQUISACA, TRINIDAD para BENI y COBIJA para PANDO.';
        }

        if ($field === 'servicio' && $rule === 'In') {
            return 'no se guardo porque el servicio no coincide con uno permitido en la plantilla. Revisa la columna servicio y usa uno de los valores del archivo modelo.';
        }

        if (in_array($field, ['kilo', 'kilo_extra', 'peso', 'retencion'], true) && in_array($rule, ['Numeric', 'Min', 'Max'], true)) {
            return 'no se guardo porque uno de los importes o pesos tiene un formato invalido. Revisa que esos campos solo tengan numeros.';
        }

        if ($field === 'horas_entrega' && in_array($rule, ['Integer', 'Min'], true)) {
            return 'no se guardo porque horas_entrega debe ser un numero entero mayor o igual a 0.';
        }

        if ($field === 'empresa_id' && in_array($rule, ['Exists', 'Required'], true)) {
            return 'no se guardo porque la empresa no se pudo identificar. Revisa el nombre de la empresa en el Excel.';
        }

        return 'no se guardo porque ' . $validator->errors()->first();
    }

    public function downloadTemplateExcel()
    {
        $this->authorizeTarifaContratoButtonAction('export');

        $filename = 'plantilla_tarifa_contrato.xlsx';
        $columns = self::IMPORT_COLUMNS;
        $empresas = Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'codigo_cliente', 'nombre', 'sigla']);

        return response()->streamDownload(function () use ($columns, $empresas) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('TarifaContrato');

            $sheet->fromArray($columns, null, 'A1');

            $sheet->getStyle('A1:M1')->applyFromArray([
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
            $sheet->setAutoFilter('A1:M1');

            $columnWidths = [
                'A' => 38,
                'B' => 18,
                'C' => 18,
                'D' => 34,
                'E' => 28,
                'F' => 20,
                'G' => 12,
                'H' => 12,
                'I' => 12,
                'J' => 18,
                'K' => 20,
                'L' => 12,
                'M' => 14,
            ];
            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            $sheet->getStyle('G2:I5000')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('L2:L5000')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('M2:M5000')->getNumberFormat()->setFormatCode('0');

            $sheetEmpresas = $spreadsheet->createSheet();
            $sheetEmpresas->setTitle('Empresas');
            $sheetEmpresas->fromArray(['codigo_cliente', 'nombre', 'sigla', 'id'], null, 'A1');

            $row = 2;
            foreach ($empresas as $empresa) {
                $sheetEmpresas->setCellValue("A{$row}", (string) $empresa->codigo_cliente);
                $sheetEmpresas->setCellValue("B{$row}", (string) $empresa->nombre);
                $sheetEmpresas->setCellValue("C{$row}", (string) $empresa->sigla);
                $sheetEmpresas->setCellValue("D{$row}", (int) $empresa->id);
                $row++;
            }

            if ($row === 2) {
                $sheetEmpresas->setCellValue('B2', '');
            }

            $sheetEmpresas->getStyle('A1:D1')->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF2E7D32'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);
            $sheetEmpresas->setAutoFilter('A1:D1');
            $sheetEmpresas->freezePane('A2');
            $sheetEmpresas->getColumnDimension('A')->setWidth(20);
            $sheetEmpresas->getColumnDimension('B')->setWidth(46);
            $sheetEmpresas->getColumnDimension('C')->setWidth(16);
            $sheetEmpresas->getColumnDimension('D')->setWidth(8);

            $sheetCatalogos = $spreadsheet->createSheet();
            $sheetCatalogos->setTitle('Catalogos');
            $sheetCatalogos->setCellValue('A1', 'SERVICIOS');
            $sheetCatalogos->setCellValue('B1', 'DEPARTAMENTOS');
            $serviceRow = 2;
            foreach (self::SERVICIOS as $servicio) {
                $sheetCatalogos->setCellValue("A{$serviceRow}", $servicio);
                $serviceRow++;
            }

            $depRow = 2;
            foreach (self::DEPARTAMENTOS as $departamento) {
                $sheetCatalogos->setCellValue("B{$depRow}", $departamento);
                $depRow++;
            }

            $sheetCatalogos->getStyle('A1:B1')->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFE3F2FD'],
                ],
            ]);
            $sheetCatalogos->getColumnDimension('A')->setWidth(34);
            $sheetCatalogos->getColumnDimension('B')->setWidth(20);

            $sheetInstrucciones = $spreadsheet->createSheet();
            $sheetInstrucciones->setTitle('Instrucciones');
            $sheetInstrucciones->setCellValue('A1', 'INSTRUCCIONES DE USO');
            $sheetInstrucciones->setCellValue('A3', '1) No cambies los nombres de columnas en la hoja TarifaContrato.');
            $sheetInstrucciones->setCellValue('A4', '2) Empieza a llenar datos desde la fila 2.');
            $sheetInstrucciones->setCellValue('A5', '3) Usa empresa_nombre igual al nombre exacto en hoja Empresas.');
            $sheetInstrucciones->setCellValue('A6', '4) Columnas obligatorias: empresa_nombre, origen, destino, servicio, kilo, kilo_extra, retencion, horas_entrega.');
            $sheetInstrucciones->setCellValue('A7', '5) Columnas opcionales: direccion, zona, kilo_de_1_a_2, provincia_origen, provincia_destino.');
            $sheetInstrucciones->setCellValue('A8', '6) origen, destino y servicio tienen listas desplegables.');
            $sheetInstrucciones->setCellValue('A9', '7) provincia_origen y provincia_destino son campos libres: puedes escribir cualquier provincia.');
            $sheetInstrucciones->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 14],
                'color' => ['argb' => 'FF0D47A1'],
            ]);
            $sheetInstrucciones->getColumnDimension('A')->setWidth(95);

            $lastEmpresaRow = max(2, $row - 1);
            $lastServiceRow = max(2, $serviceRow - 1);
            $lastDepartamentoRow = max(2, $depRow - 1);

            $this->applyListValidation($sheet, 'A2:A5000', "=Empresas!\$B\$2:\$B\${$lastEmpresaRow}");
            $this->applyListValidation($sheet, 'B2:B5000', "=Catalogos!\$B\$2:\$B\${$lastDepartamentoRow}");
            $this->applyListValidation($sheet, 'C2:C5000', "=Catalogos!\$B\$2:\$B\${$lastDepartamentoRow}");
            $this->applyListValidation($sheet, 'D2:D5000', "=Catalogos!\$A\$2:\$A\${$lastServiceRow}");

            $spreadsheet->setActiveSheetIndex(0);

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function edit(TarifaContrato $tarifaContrato)
    {
        $this->authorizeTarifaContratoButtonAction('edit');

        return view('tarifa_contrato.edit', [
            'tarifaContrato' => $tarifaContrato,
            'empresas' => Empresa::query()->orderBy('nombre')->get(),
            'servicios' => self::SERVICIOS,
            'departamentos' => self::DEPARTAMENTOS,
            'provinciasPorDepartamento' => self::PROVINCIAS_POR_DEPARTAMENTO,
            'defaults' => [],
        ]);
    }

    public function update(Request $request, TarifaContrato $tarifaContrato)
    {
        $this->authorizeTarifaContratoButtonAction('save');

        $data = $this->validateData($request);
        $tarifaContrato->update($data);

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato actualizada correctamente.');
    }

    public function destroy(TarifaContrato $tarifaContrato)
    {
        $this->authorizeTarifaContratoButtonAction('delete');

        $tarifaContrato->delete();

        return redirect()
            ->route('tarifa-contrato.index')
            ->with('success', 'Tarifa de contrato eliminada correctamente.');
    }

    private function validateData(Request $request): array
    {
        $provincia = trim((string) $request->input('provincia'));

        $request->merge([
            'origen' => $this->normalizeDepartamento($request->input('origen')),
            'destino' => $this->normalizeDepartamento($request->input('destino')),
            'servicio' => $this->normalizeServicio((string) $request->input('servicio')),
            'direccion' => $this->normalizeNullableUpper($request->input('direccion')),
            'zona' => $this->normalizeNullableUpper($request->input('zona')),
            'provincia' => $provincia === '' ? null : strtoupper($provincia),
            'provincia_origen' => $this->normalizeNullableUpper($request->input('provincia_origen')),
        ]);

        $data = $request->validate($this->validationRules((string) $request->input('destino'), (string) $request->input('origen')));

        if ($request->filled('peso')) {
            $data['peso'] = $this->parseDecimal($request->input('peso'));
        }

        return $data;
    }

    private function validationRules(string $destino = '', string $origen = ''): array
    {
        return [
            'empresa_id' => ['required', 'integer', Rule::exists('empresa', 'id')],
            'origen' => ['required', 'string', Rule::in(self::DEPARTAMENTOS)],
            'destino' => ['required', 'string', Rule::in(self::DEPARTAMENTOS)],
            'servicio' => ['required', 'string', Rule::in(self::SERVICIOS)],
            'direccion' => ['nullable', 'string', 'max:255'],
            'zona' => ['nullable', 'string', 'max:255'],
            'peso' => ['nullable', 'numeric', 'min:0'],
            'kilo' => 'required|numeric|min:0',
            'kilo_extra' => 'required|numeric|min:0',
            'provincia' => ['nullable', 'string', 'max:255'],
            'provincia_origen' => ['nullable', 'string', 'max:255'],
            'retencion' => 'required|numeric|min:0|max:100',
            'horas_entrega' => 'required|integer|min:0',
        ];
    }

    private function normalizeHeader($value): string
    {
        $value = strtolower(trim((string) $value));

        return match ($value) {
            'kilo de 1 a 2', 'kilo 1 a 2', 'kilo_de_1_a_2' => 'kilo_de_1_a_2',
            'provincia origen', 'provincia_origen' => 'provincia_origen',
            'provincia destino', 'provincia_destino', 'provincia' => 'provincia_destino',
            default => $value,
        };
    }

    private function normalizeCompanyName(string $value): string
    {
        $value = trim($value);
        $value = Str::ascii($value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return strtoupper($value);
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
                $cell = $col . $row;
                $sheet->getCell($cell)->setDataValidation(clone $validation);
            }
        }
    }

    private function splitCell(string $cell): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', strtoupper($cell), $m);
        return [$m[1], (int) $m[2]];
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

    private function authorizeTarifaContratoButtonAction(string $action): void
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }

        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            return;
        }

        $permissions = AclPermissionRegistry::existingPermissionsFrom([
            'feature.tarifa-contrato.'.$action,
        ]);

        if ($permissions === []) {
            if ((bool) config('acl.route_permission.allow_when_permission_missing', true)) {
                return;
            }

            abort(403, 'No se encontro la configuracion de permisos para esta accion.');
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para realizar esta accion.');
    }

    private function normalizeServicio(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        $value = str_replace('LOCAL (REGULAR)', 'LOCAL(REGULAR)', $value);
        $value = str_replace('LOCAL (EXPRESS)', 'LOCAL(EXPRESS)', $value);

        return $value;
    }

    private function normalizeNullableUpper($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return strtoupper($text);
    }

    private function normalizeDepartamento($value): string
    {
        $text = strtoupper(trim((string) $value));

        return self::DEPARTAMENTO_ALIASES[$text] ?? $text;
    }

}


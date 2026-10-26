<?php

namespace App\Http\Controllers;

use App\Models\Cartero;
use App\Models\CarteroAssignmentReport;
use App\Models\CarteroAssignmentReportItem;
use App\Models\Estado;
use App\Models\PackageReportExport;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use App\Models\SolicitudCliente;
use App\Models\User;
use App\Jobs\GenerateTodosPaquetesExcelReport;
use App\Jobs\GenerateTodosPaquetesHistoryPdfReport;
use App\Services\TodosPaquetesReportQuery;
use App\Services\TodosPaquetesHistoryReportService;
use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TodosPaquetesController extends Controller
{
    private const DEPARTAMENTOS = [
        'TRINIDAD',
        'SUCRE',
        'COCHABAMBA',
        'LA PAZ',
        'ORURO',
        'COBIJA',
        'POTOSI',
        'SANTA CRUZ',
        'TARIJA',
    ];

    private const LOCATION_ALIASES = [
        'BENI' => 'TRINIDAD',
        'CHUQUISACA' => 'SUCRE',
        'PANDO' => 'COBIJA',
    ];

    private const DISTRIBUTION_ASSIGNEE_ROLES = [
        'auxiliar_urbano',
        'auxiliar_urbano_dnd',
        'auxiliar_7',
        'cartero_ems',
        'carteros_ems',
    ];

    private const TYPES = [
        'ems' => [
            'label' => 'EMS',
            'model' => PaqueteEms::class,
            'table' => 'paquetes_ems',
            'state_col' => 'estado_id',
            'editable' => [
                'codigo' => 'Codigo',
                'cod_especial' => 'Cod. especial',
                'origen' => 'Origen',
                'ciudad' => 'Destino',
                'nombre_remitente' => 'Remitente',
                'nombre_destinatario' => 'Destinatario',
                'telefono_destinatario' => 'Telefono destinatario',
                'direccion' => 'Direccion',
                'referencia' => 'Referencia',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'observacion' => 'Observacion',
                'justificacion' => 'Justificacion',
            ],
            'numeric' => ['peso', 'precio'],
        ],
        'contrato' => [
            'label' => 'CONTRATO',
            'model' => Recojo::class,
            'table' => 'paquetes_contrato',
            'state_col' => 'estados_id',
            'editable' => [
                'codigo' => 'Codigo',
                'cod_especial' => 'Cod. especial',
                'origen' => 'Origen',
                'destino' => 'Destino',
                'nombre_r' => 'Remitente',
                'telefono_r' => 'Telefono remitente',
                'nombre_d' => 'Destinatario',
                'telefono_d' => 'Telefono destinatario',
                'direccion_d' => 'Direccion destinatario',
                'fecha_recojo' => 'Fecha de recojo',
                'provincia' => 'Provincia',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'observacion' => 'Observacion',
                'justificacion' => 'Justificacion',
            ],
            'numeric' => ['peso', 'precio'],
        ],
        'certi' => [
            'label' => 'CERTIFICADO',
            'model' => PaqueteCerti::class,
            'table' => 'paquetes_certi',
            'state_col' => 'fk_estado',
            'editable' => [
                'codigo' => 'Codigo',
                'cod_especial' => 'Cod. especial',
                'destinatario' => 'Destinatario',
                'telefono' => 'Telefono',
                'cuidad' => 'Ciudad',
                'zona' => 'Zona',
                'ventanilla' => 'Ventanilla',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'tipo' => 'Tipo',
                'aduana' => 'Aduana',
            ],
            'numeric' => ['peso', 'precio'],
        ],
        'ordi' => [
            'label' => 'ORDINARIO',
            'model' => PaqueteOrdi::class,
            'table' => 'paquetes_ordi',
            'state_col' => 'fk_estado',
            'editable' => [
                'codigo' => 'Codigo',
                'cod_especial' => 'Cod. especial',
                'destinatario' => 'Destinatario',
                'telefono' => 'Telefono',
                'ciudad' => 'Ciudad',
                'zona' => 'Zona',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'aduana' => 'Aduana',
                'observaciones' => 'Observaciones',
            ],
            'numeric' => ['peso', 'precio'],
        ],
        'solicitud' => [
            'label' => 'SOLICITUD',
            'model' => SolicitudCliente::class,
            'table' => 'solicitud_clientes',
            'state_col' => 'estado_id',
            'editable' => [
                'codigo_solicitud' => 'Codigo solicitud',
                'barcode' => 'Barcode',
                'cod_especial' => 'Cod. especial',
                'origen' => 'Origen',
                'ciudad' => 'Destino',
                'nombre_remitente' => 'Remitente',
                'nombre_destinatario' => 'Destinatario',
                'telefono_destinatario' => 'Telefono destinatario',
                'direccion' => 'Direccion',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'observacion' => 'Observacion',
                'justificacion' => 'Justificacion',
            ],
            'numeric' => ['peso', 'precio'],
        ],
    ];

    public function index(Request $request)
    {
        $filters = $this->validatedPackageFilters($request);
        $search = $filters['search'];
        $type = $filters['type'];
        $estadoId = $filters['estado_id'];
        $pesoMin = $filters['peso_min'];
        $pesoMax = $filters['peso_max'];
        $reportQuery = app(TodosPaquetesReportQuery::class);
        $query = $reportQuery->filtered($filters);
        $paquetesEncontrados = $reportQuery->count($filters);

        $paquetes = $query->simplePaginate(25)->withQueryString();
        $paquetes->getCollection()->transform(function ($paquete) {
            $paquete->origen = $this->canonicalLocationName((string) $paquete->origen);
            $paquete->destino = $this->canonicalLocationName((string) $paquete->destino);

            return $paquete;
        });
        $this->attachSalidaReports($paquetes->getCollection());
        $estados = Estado::query()->orderBy('nombre_estado')->get(['id', 'nombre_estado']);
        $editing = $this->resolveEditing($request);
        $reportExportId = (string) $request->query('export_job', '');
        $reportExport = Str::isUuid($reportExportId)
            ? PackageReportExport::query()
                ->whereKey($reportExportId)
                ->where('user_id', $request->user()->id)
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->first()
            : null;

        return view('todos_paquetes.index', [
            'paquetes' => $paquetes,
            'paquetesEncontrados' => $paquetesEncontrados,
            'estados' => $estados,
            'types' => self::TYPES,
            'carteros' => $this->carterosDisponibles($request),
            'departamentos' => self::DEPARTAMENTOS,
            'servicios' => DB::table('servicio')->orderBy('nombre_servicio')->get(['id', 'nombre_servicio']),
            'ventanillas' => DB::table('ventanilla')->orderBy('nombre_ventanilla')->get(['id', 'nombre_ventanilla']),
            'empresas' => DB::table('empresa')->orderBy('nombre')->get(['id', 'nombre']),
            'search' => $search,
            'type' => $type,
            'estadoId' => $estadoId,
            'pesoMin' => $pesoMin,
            'pesoMax' => $pesoMax,
            'editing' => $editing,
            'reportExport' => $reportExport,
            'showCreateModal' => $request->boolean('create') || old('package_type') !== null,
        ]);
    }

    public function createExcelExport(Request $request)
    {
        $filters = $this->validatedPackageFilters($request);
        $activeReports = PackageReportExport::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($activeReports >= 2) {
            return back()->with('error', 'Ya tienes dos reportes en preparacion. Espera a que termine uno antes de solicitar otro.');
        }

        $reportId = (string) Str::uuid();
        $filename = 'reporte-paquetes-'.now()->format('Ymd-His').'-'.substr($reportId, 0, 8).'.xlsx';
        $report = PackageReportExport::query()->create([
            'id' => $reportId,
            'user_id' => $request->user()->id,
            'report_type' => 'xlsx',
            'filters' => $filters,
            'status' => 'pending',
            'file_name' => $filename,
            'row_count' => 0,
            'progress_message' => 'En espera para generar el Excel...',
        ]);

        GenerateTodosPaquetesExcelReport::dispatch($report->id)
            ->onConnection('reports')
            ->onQueue('reports');

        $query = [
            'q' => $filters['search'] !== '' ? $filters['search'] : null,
            'type' => $filters['type'] !== '' ? $filters['type'] : null,
            'estado_id' => $filters['estado_id'] ?: null,
            'peso_min' => $filters['peso_min'],
            'peso_max' => $filters['peso_max'],
            'export_job' => $report->id,
        ];

        return redirect()
            ->route('todos-paquetes.index', array_filter($query, fn ($value) => $value !== null && $value !== ''))
            ->with('success', 'El reporte se esta preparando. Puedes seguir usando la pagina mientras se genera.');
    }

    public function exportStatus(Request $request, string $export)
    {
        $report = $this->ownedPackageReport($request, $export);
        abort_if($report->expires_at && $report->expires_at->isPast(), 410, 'El reporte ya vencio.');

        return response()->json([
            'status' => $report->status,
            'report_type' => $report->report_type,
            'rows' => (int) $report->row_count,
            'total_rows' => (int) $report->total_rows,
            'progress' => (int) $report->progress,
            'progress_message' => $report->progress_message,
            'download_url' => $report->status === 'completed'
                ? route('todos-paquetes.export.download', ['export' => $report->id])
                : null,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function downloadExport(Request $request, string $export)
    {
        $report = $this->ownedPackageReport($request, $export);

        abort_unless($report->status === 'completed' && $report->file_path, 404);
        abort_if($report->expires_at && $report->expires_at->isPast(), 410, 'El reporte ya vencio.');
        abort_unless(Storage::disk('local')->exists($report->file_path), 404);

        $contentType = match ($report->report_type) {
            'history_pdf' => 'application/pdf',
            default => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        };

        return Storage::disk('local')->download($report->file_path, $report->file_name, [
            'Content-Type' => $contentType,
        ]);
    }

    private function ownedPackageReport(Request $request, string $export): PackageReportExport
    {
        abort_unless(Str::isUuid($export), 404);

        return PackageReportExport::query()
            ->whereKey($export)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function reporteHistorial(Request $request)
    {
        $data = $request->validate([
            'codigos' => ['required', 'string', 'max:12000'],
        ], [], [
            'codigos' => 'guías',
        ]);

        $historyReportService = app(TodosPaquetesHistoryReportService::class);
        $requestedCodes = $historyReportService->parseCodes((string) $data['codigos']);

        if ($requestedCodes->isEmpty()) {
            throw ValidationException::withMessages([
                'codigos' => 'Ingresa al menos un código de guía.',
            ]);
        }

        if ($requestedCodes->count() > 200) {
            throw ValidationException::withMessages([
                'codigos' => 'El reporte admite un máximo de 200 guías por vez.',
            ]);
        }

        $activeReports = PackageReportExport::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($activeReports >= 2) {
            return back()->with('error', 'Ya tienes dos reportes en preparacion. Espera a que termine uno antes de solicitar otro.');
        }

        $reportId = (string) Str::uuid();
        $filename = 'historial-paquetes-'.now()->format('Ymd-His').'-'.substr($reportId, 0, 8).'.pdf';
        $report = PackageReportExport::query()->create([
            'id' => $reportId,
            'user_id' => $request->user()->id,
            'report_type' => 'history_pdf',
            'filters' => ['codes' => $requestedCodes->all()],
            'status' => 'pending',
            'file_name' => $filename,
            'row_count' => 0,
            'total_rows' => $requestedCodes->count(),
            'progress_message' => 'En espera para generar el PDF...',
        ]);

        GenerateTodosPaquetesHistoryPdfReport::dispatch($report->id)
            ->onConnection('reports')
            ->onQueue('reports');

        return redirect()
            ->route('todos-paquetes.index', ['export_job' => $report->id])
            ->with('success', 'El reporte PDF se esta preparando. Puedes seguir usando la pagina mientras se genera.');
    }

    public function store(Request $request)
    {
        $typeData = $request->validate([
            'package_type' => ['required', Rule::in(array_keys(self::TYPES))],
        ], [], [
            'package_type' => 'tipo de servicio',
        ]);

        $type = (string) $typeData['package_type'];
        $data = $request->validate($this->createRules($type), [], $this->createAttributes());
        $modelClass = self::TYPES[$type]['model'];
        $payload = $this->createPayload($type, $data, $request);

        DB::transaction(function () use ($modelClass, $payload) {
            $modelClass::query()->create($payload);
        });

        return redirect()
            ->route('todos-paquetes.index', $request->only(['q', 'type', 'estado_id', 'peso_min', 'peso_max', 'page']))
            ->with('success', self::TYPES[$type]['label'].' creado correctamente.');
    }

    public function updateEstado(Request $request, string $type, int $id)
    {
        $config = $this->typeConfig($type);

        $data = $request->validate([
            'estado_id' => ['required', 'integer', Rule::exists('estados', 'id')],
        ]);

        $model = $this->findPackage($config, $id);
        $stateColumn = $config['state_col'];
        $oldEstado = (int) ($model->{$stateColumn} ?? 0);
        $newEstado = (int) $data['estado_id'];

        if ($oldEstado === $newEstado) {
            return back()->with('success', 'El paquete ya tenia ese estado.');
        }

        DB::transaction(function () use ($model, $stateColumn, $newEstado, $type, $id) {
            $model->{$stateColumn} = $newEstado;
            $model->save();

            $carteroColumn = $this->carteroColumnForType($type);
            if ($carteroColumn !== null) {
                Cartero::query()
                    ->where($carteroColumn, $id)
                    ->update(['id_estados' => $newEstado]);
            }
        });

        return back()->with('success', 'Estado actualizado.');
    }

    public function updateDatos(Request $request, string $type, int $id)
    {
        $config = $this->typeConfig($type);
        $model = $this->findPackage($config, $id);
        $rules = [];

        foreach ($config['numeric'] ?? [] as $numericField) {
            if ($request->filled($numericField)) {
                $request->merge([
                    $numericField => str_replace(',', '.', trim((string) $request->input($numericField))),
                ]);
            }
        }

        foreach (['origen', 'ciudad', 'cuidad', 'destino'] as $locationField) {
            if ($request->filled($locationField)) {
                $request->merge([
                    $locationField => $this->canonicalLocationName((string) $request->input($locationField)),
                ]);
            }
        }

        foreach ($config['editable'] as $field => $label) {
            if ($field === 'fecha_recojo') {
                $rules[$field] = ['nullable', 'date'];

                continue;
            }
            $rules[$field] = in_array($field, $config['numeric'] ?? [], true)
                ? ['nullable', 'numeric']
                : ['nullable', 'string', 'max:1000'];
        }

        if (array_key_exists('codigo', $config['editable'])) {
            $rules['codigo'][] = Rule::unique($config['table'], 'codigo')->ignore($model->getKey());
        }

        if ($type === 'solicitud') {
            $rules['codigo_solicitud'][] = Rule::unique($config['table'], 'codigo_solicitud')->ignore($model->getKey());
        }

        $rules['foto'] = ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'];

        $data = $request->validate($rules);
        $foto = $data['foto'] ?? null;
        unset($data['foto']);
        $imagen = $foto ? StoredImage::fromUploadedFile($foto) : null;

        DB::transaction(function () use ($model, $data, $foto, $imagen) {
            foreach ($data as $field => $value) {
                $model->{$field} = is_string($value) ? trim($value) : $value;
            }

            if ($foto && $imagen !== null) {
                $model->imagen = $imagen;
            }

            $model->save();
        });

        return redirect()
            ->route('todos-paquetes.index', $request->only(['q', 'type', 'estado_id', 'peso_min', 'peso_max', 'page']))
            ->with('success', 'Datos actualizados.');
    }

    public function reimprimirGuia(string $type, int $id)
    {
        $config = $this->typeConfig($type);
        $model = $this->findPackage($config, $id);
        $generatedAt = now();

        if ($type === 'ems') {
            $model->loadMissing(['tarifario.destino', 'tarifario.servicio', 'tarifario.origen', 'tarifario.peso', 'formulario']);

            $pdf = Pdf::loadView('paquetes_ems.boleta', [
                'paquete' => $model,
            ])->setPaper([0, 0, 226.77, 595.28], 'portrait');

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, 'boleta-ems-'.$model->codigo.'-'.$generatedAt->format('Ymd-His').'.pdf');
        }

        if ($type === 'solicitud') {
            $model->loadMissing([
                'estadoRegistro:id,nombre_estado',
                'servicioExtra:id,nombre,descripcion',
                'destino:id,nombre_destino',
            ]);

            return view('paquetes_ems.solicitud-ticket', ['solicitud' => $model]);
        }

        if ($type === 'certi') {
            $model->loadMissing(['estado', 'ventanillaRef']);

            $pdf = Pdf::loadView('paquetes_certi.reporte_baja', [
                'packages' => collect([$model]),
            ])->setPaper('A4');

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, 'formulario-entrega-'.$model->codigo.'-'.$generatedAt->format('Ymd-His').'.pdf');
        }

        if ($type === 'ordi') {
            $model->loadMissing(['estado', 'ventanillaRef']);

            $pdf = Pdf::loadView('paquetes_ordi.reporte_baja', [
                'packages' => collect([$model]),
            ])->setPaper('A4');

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->output();
            }, 'formulario-entrega-'.$model->codigo.'-'.$generatedAt->format('Ymd-His').'.pdf');
        }

        abort_unless($type === 'contrato', 404);

        $contrato = $model;
        $contrato->loadMissing(['empresa:id,nombre,sigla', 'user.empresa:id,nombre,sigla']);

        $pdf = Pdf::loadView('paquetes_contrato.reporte', [
            'contrato' => $contrato,
            'generatedAt' => $generatedAt,
            'numeroCopias' => 3,
            'verificationUrl' => route('paquetes-contrato.verificar-guia', [
                't' => Crypt::encryptString((string) $contrato->getKey()),
            ]),
        ])->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contrato-'.$contrato->codigo.'-'.$generatedAt->format('Ymd-His').'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function reporteSalida(string $codigo)
    {
        $report = CarteroAssignmentReport::query()
            ->with(['assignedUser:id,name,ciudad', 'actorUser:id,name,ciudad'])
            ->where('codigo', $codigo)
            ->firstOrFail();

        $pdf = Pdf::loadView('carteros.asignacion-reporte', [
            'rows' => $report->rows ?? [],
            'assigned_at' => $report->assigned_at,
            'assigned_user' => $report->assignedUser,
            'actor_user' => $report->actorUser,
            'regional' => $report->regional,
            'summary_by_type' => $report->summary_by_type ?? [],
            'total_assigned' => (int) $report->total_assigned,
            'codigo_reporte' => $report->codigo,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream('reporte-salida-'.$report->codigo.'.pdf');
    }

    public function cambiarCarteroReporte(Request $request, string $codigo)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ], [], [
            'user_id' => 'cartero',
        ]);

        $report = CarteroAssignmentReport::query()
            ->with('items')
            ->where('codigo', $codigo)
            ->firstOrFail();

        $newUser = User::query()->findOrFail((int) $data['user_id'], ['id', 'name', 'ciudad']);

        if (! $this->isAllowedCartero($newUser)) {
            return back()->with('error', 'El usuario seleccionado no tiene rol de cartero.');
        }

        if (! $this->isSameCity($request->user(), $newUser)) {
            return back()->with('error', 'Solo puedes cambiar a un cartero de tu mismo departamento.');
        }

        $items = $report->items
            ->map(fn ($item) => [
                'tipo' => strtoupper((string) $item->tipo_paquete),
                'id' => (int) $item->paquete_id,
            ])
            ->filter(fn ($item) => $item['id'] > 0 && $this->carteroColumnForType($item['tipo']) !== null)
            ->unique(fn ($item) => $item['tipo'].':'.$item['id'])
            ->values();

        if ($items->isEmpty()) {
            return back()->with('error', 'El reporte '.$report->codigo.' no tiene paquetes validos para cambiar de cartero.');
        }

        $updated = 0;
        $estadoCarteroId = $this->estadoIdByName('CARTERO');

        if ($estadoCarteroId <= 0) {
            return back()->with('error', 'No existe el estado CARTERO en la tabla estados.');
        }

        DB::transaction(function () use ($items, $newUser, $report, &$updated, $estadoCarteroId) {
            foreach ($items as $item) {
                $column = $this->carteroColumnForType($item['tipo']);
                if ($column === null) {
                    continue;
                }

                $affected = Cartero::query()
                    ->where($column, $item['id'])
                    ->where('id_estados', $estadoCarteroId)
                    ->update([
                        'id_user' => (int) $newUser->id,
                        'updated_at' => now(),
                    ]);

                $updated += $affected;
            }

            $report->forceFill([
                'assigned_user_id' => (int) $newUser->id,
                'actor_user_id' => (int) auth()->id(),
            ])->save();
        });

        return back()->with(
            $updated > 0 ? 'success' : 'error',
            $updated > 0
                ? 'Reporte '.$report->codigo.': '.$updated.' paquete(s) cambiados al cartero '.$newUser->name.'.'
                : 'No se encontro ninguna asignacion activa de cartero para el reporte '.$report->codigo.'.'
        );
    }

    private function attachSalidaReports($paquetes): void
    {
        $typeMap = [
            'ems' => 'EMS',
            'contrato' => 'CONTRATO',
            'certi' => 'CERTI',
            'ordi' => 'ORDI',
            'solicitud' => 'SOLICITUD',
        ];

        $pairs = collect($paquetes)
            ->map(fn ($paquete) => [
                'tipo' => $typeMap[$paquete->type_key] ?? '',
                'id' => (int) $paquete->record_id,
            ])
            ->filter(fn ($pair) => $pair['tipo'] !== '' && $pair['id'] > 0)
            ->unique(fn ($pair) => $pair['tipo'].':'.$pair['id'])
            ->values();

        if ($pairs->isEmpty()) {
            return;
        }

        $items = CarteroAssignmentReportItem::query()
            ->select([
                'cartero_assignment_report_items.tipo_paquete',
                'cartero_assignment_report_items.paquete_id',
                'cartero_assignment_reports.codigo',
                'cartero_assignment_reports.assigned_at',
            ])
            ->join('cartero_assignment_reports', 'cartero_assignment_reports.id', '=', 'cartero_assignment_report_items.cartero_assignment_report_id')
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($sub) use ($pair) {
                        $sub->where('cartero_assignment_report_items.tipo_paquete', $pair['tipo'])
                            ->where('cartero_assignment_report_items.paquete_id', $pair['id']);
                    });
                }
            })
            ->orderByDesc('cartero_assignment_reports.assigned_at')
            ->get()
            ->unique(fn ($item) => $item->tipo_paquete.':'.$item->paquete_id)
            ->keyBy(fn ($item) => $item->tipo_paquete.':'.$item->paquete_id);

        $paquetes->transform(function ($paquete) use ($typeMap, $items) {
            $key = ($typeMap[$paquete->type_key] ?? '').':'.(int) $paquete->record_id;
            $report = $items->get($key);
            $paquete->salida_report_codigo = $report?->codigo;
            $paquete->salida_report_assigned_at = $report?->assigned_at;

            return $paquete;
        });
    }

    private function carterosDisponibles(Request $request)
    {
        $hasGlobalDepartmentAccess = (bool) optional($request->user())->hasGlobalDepartmentAccess();
        $userCity = $this->normalizeCity((string) optional($request->user())->ciudad);

        if (! $hasGlobalDepartmentAccess && $userCity === '') {
            return collect();
        }

        return User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn(DB::raw('LOWER(name)'), self::DISTRIBUTION_ASSIGNEE_ROLES);
            })
            ->when(! $hasGlobalDepartmentAccess, function ($query) use ($userCity) {
                $query->whereRaw('TRIM(UPPER(ciudad)) = ?', [$userCity]);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'ciudad']);
    }

    private function isAllowedCartero(User $user): bool
    {
        return User::query()
            ->whereKey($user->id)
            ->whereHas('roles', function ($query) {
                $query->whereIn(DB::raw('LOWER(name)'), self::DISTRIBUTION_ASSIGNEE_ROLES);
            })
            ->exists();
    }

    private function isSameCity($a, $b): bool
    {
        if ((bool) optional($a)->hasGlobalDepartmentAccess()) {
            return true;
        }

        return $this->normalizeCity((string) optional($a)->ciudad) !== ''
            && $this->normalizeCity((string) optional($a)->ciudad) === $this->normalizeCity((string) optional($b)->ciudad);
    }

    private function normalizeCity(string $city): string
    {
        return strtoupper(trim($city));
    }

    private function canonicalLocationName(string $location): string
    {
        $normalized = mb_strtoupper(trim($location));

        return self::LOCATION_ALIASES[$normalized] ?? $normalized;
    }

    private function estadoIdByName(string $name): int
    {
        return (int) (Estado::query()
            ->whereRaw('TRIM(UPPER(nombre_estado)) = ?', [strtoupper(trim($name))])
            ->value('id') ?? 0);
    }

    private function carteroColumnForType(string $type): ?string
    {
        return match (strtoupper(trim($type))) {
            'EMS' => 'id_paquetes_ems',
            'CERTI' => 'id_paquetes_certi',
            'ORDI' => 'id_paquetes_ordi',
            'CONTRATO' => 'id_paquetes_contrato',
            'SOLICITUD' => 'id_solicitud_cliente',
            default => null,
        };
    }

    private function validatedPackageFilters(Request $request): array
    {
        $weightFilters = $request->validate([
            'peso_min' => ['nullable', 'numeric', 'min:0'],
            'peso_max' => ['nullable', 'numeric', 'min:0'],
        ], [], [
            'peso_min' => 'peso mínimo',
            'peso_max' => 'peso máximo',
        ]);
        $pesoMin = $request->filled('peso_min') ? (float) $weightFilters['peso_min'] : null;
        $pesoMax = $request->filled('peso_max') ? (float) $weightFilters['peso_max'] : null;

        if ($pesoMin !== null && $pesoMax !== null && $pesoMax < $pesoMin) {
            throw ValidationException::withMessages([
                'peso_max' => 'El peso máximo debe ser mayor o igual al peso mínimo.',
            ]);
        }

        return [
            'search' => trim((string) $request->input('q', '')),
            'type' => trim((string) $request->input('type', '')),
            'estado_id' => collect((array) $request->input('estado_id', []))
                ->filter(fn ($id) => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()->values()->all(),
            'peso_min' => $pesoMin,
            'peso_max' => $pesoMax,
        ];
    }

    private function resolveEditing(Request $request): ?array
    {
        $type = trim((string) $request->query('edit_type', ''));
        $id = (int) $request->query('edit_id', 0);

        if (! array_key_exists($type, self::TYPES) || $id <= 0) {
            return null;
        }

        $config = self::TYPES[$type];
        $model = $this->findPackage($config, $id);

        return [
            'type' => $type,
            'id' => $id,
            'label' => $config['label'],
            'fields' => $config['editable'],
            'numeric' => $config['numeric'] ?? [],
            'image_url' => StoredImage::url($model->imagen ?? null),
            'values' => collect(array_keys($config['editable']))
                ->mapWithKeys(function ($field) use ($model) {
                    $value = $model->{$field};
                    if ($field === 'fecha_recojo') {
                        $value = $value?->format('Y-m-d\\TH:i:s');
                    }
                    if (in_array($field, ['origen', 'ciudad', 'cuidad', 'destino'], true)) {
                        $value = $this->canonicalLocationName((string) $value);
                    }

                    return [$field => $value];
                })
                ->all(),
        ];
    }

    private function typeConfig(string $type): array
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return self::TYPES[$type];
    }

    private function findPackage(array $config, int $id): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return $modelClass::query()->findOrFail($id);
    }

    private function createRules(string $type): array
    {
        $departmentRule = ['required', 'string', Rule::in(self::DEPARTAMENTOS)];
        $nullableText = ['nullable', 'string', 'max:1000'];
        $requiredText = ['required', 'string', 'max:1000'];

        return match ($type) {
            'ems' => [
                'codigo' => ['required', 'string', 'max:255', Rule::unique('paquetes_ems', 'codigo')],
                'cod_especial' => $nullableText,
                'origen' => $departmentRule,
                'ciudad' => $departmentRule,
                'tipo_correspondencia' => $nullableText,
                'servicio_especial' => $nullableText,
                'contenido' => $nullableText,
                'cantidad' => ['nullable', 'integer', 'min:1'],
                'peso' => ['nullable', 'numeric', 'min:0'],
                'precio' => ['nullable', 'numeric', 'min:0'],
                'nombre_remitente' => $nullableText,
                'nombre_envia' => $nullableText,
                'carnet' => $nullableText,
                'telefono_remitente' => $nullableText,
                'nombre_destinatario' => $nullableText,
                'telefono_destinatario' => $nullableText,
                'direccion' => $nullableText,
                'referencia' => $nullableText,
                'observacion' => $nullableText,
                'justificacion' => $nullableText,
                'estado_id' => ['nullable', 'integer', Rule::exists('estados', 'id')],
            ],
            'contrato' => [
                'codigo' => ['required', 'string', 'max:255', Rule::unique('paquetes_contrato', 'codigo')],
                'codigo_madre' => $nullableText,
                'cod_especial' => $nullableText,
                'origen' => $departmentRule,
                'destino' => $departmentRule,
                'provincia_origen' => $nullableText,
                'provincia' => $nullableText,
                'nombre_r' => $requiredText,
                'telefono_r' => $requiredText,
                'contenido' => $requiredText,
                'cantidad' => ['nullable', 'integer', 'min:1'],
                'direccion_r' => $requiredText,
                'nombre_d' => $requiredText,
                'telefono_d' => $nullableText,
                'direccion_d' => $requiredText,
                'mapa' => $nullableText,
                'peso' => ['required', 'numeric', 'min:0'],
                'precio' => ['nullable', 'numeric', 'min:0'],
                'observacion' => $nullableText,
                'justificacion' => $nullableText,
                'estados_id' => ['nullable', 'integer', Rule::exists('estados', 'id')],
                'empresa_id' => ['nullable', 'integer', Rule::exists('empresa', 'id')],
            ],
            'certi' => [
                'codigo' => ['required', 'string', 'max:255', Rule::unique('paquetes_certi', 'codigo')],
                'cod_especial' => $nullableText,
                'destinatario' => $requiredText,
                'telefono' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
                'cuidad' => $departmentRule,
                'zona' => $nullableText,
                'peso' => ['required', 'numeric', 'min:0'],
                'precio' => ['nullable', 'numeric', 'min:0'],
                'tipo' => $requiredText,
                'aduana' => $requiredText,
                'observaciones' => $nullableText,
                'fk_estado' => ['required', 'integer', Rule::exists('estados', 'id')],
                'fk_ventanilla' => ['required', 'integer', Rule::exists('ventanilla', 'id')],
                'servicio_id' => ['nullable', 'integer', Rule::exists('servicio', 'id')],
            ],
            'ordi' => [
                'codigo' => ['required', 'string', 'max:255', Rule::unique('paquetes_ordi', 'codigo')],
                'cod_especial' => $nullableText,
                'destinatario' => $requiredText,
                'telefono' => $requiredText,
                'ciudad' => $departmentRule,
                'zona' => $requiredText,
                'peso' => ['required', 'numeric', 'min:0'],
                'precio' => ['nullable', 'numeric', 'min:0'],
                'tipo' => $nullableText,
                'pais' => $nullableText,
                'iso' => $nullableText,
                'aduana' => $requiredText,
                'observaciones' => $nullableText,
                'factura' => $nullableText,
                'manifiesto' => $nullableText,
                'fk_estado' => ['required', 'integer', Rule::exists('estados', 'id')],
                'fk_ventanilla' => ['required', 'integer', Rule::exists('ventanilla', 'id')],
                'servicio_id' => ['nullable', 'integer', Rule::exists('servicio', 'id')],
            ],
            'solicitud' => [
                'codigo_solicitud' => ['nullable', 'string', 'max:255', Rule::unique('solicitud_clientes', 'codigo_solicitud')],
                'barcode' => $nullableText,
                'cod_especial' => $nullableText,
                'origen' => $departmentRule,
                'ciudad' => $departmentRule,
                'tipo_correspondencia' => $nullableText,
                'servicio_especial' => $nullableText,
                'contenido' => $requiredText,
                'cantidad' => ['required', 'integer', 'min:1'],
                'peso' => ['nullable', 'numeric', 'min:0'],
                'precio' => ['nullable', 'numeric', 'min:0'],
                'pago_destinatario' => ['nullable', 'boolean'],
                'nombre_remitente' => $requiredText,
                'nombre_envia' => $nullableText,
                'carnet' => $requiredText,
                'telefono_remitente' => $nullableText,
                'nombre_destinatario' => $requiredText,
                'telefono_destinatario' => $nullableText,
                'direccion_recojo' => $nullableText,
                'direccion' => $requiredText,
                'observacion' => $nullableText,
                'justificacion' => $nullableText,
                'estado_id' => ['nullable', 'integer', Rule::exists('estados', 'id')],
                'servicio_id' => ['nullable', 'integer', Rule::exists('servicio', 'id')],
            ],
            default => abort(404),
        };
    }

    private function createPayload(string $type, array $data, Request $request): array
    {
        $payload = collect($data)
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();

        foreach (['origen', 'ciudad', 'cuidad', 'destino'] as $departmentField) {
            if (isset($payload[$departmentField])) {
                $payload[$departmentField] = $this->canonicalLocationName((string) $payload[$departmentField]);
            }
        }

        if ($type === 'ems') {
            $payload['user_id'] = (int) $request->user()->id;
        }

        if ($type === 'contrato') {
            $payload['user_id'] = (int) $request->user()->id;
            $payload['cantidad'] = (string) ($payload['cantidad'] ?? 1);
        }

        if ($type === 'certi') {
            $payload['ventanilla'] = (string) DB::table('ventanilla')
                ->where('id', (int) $payload['fk_ventanilla'])
                ->value('nombre_ventanilla');
        }

        if ($type === 'solicitud') {
            $destinationNames = collect(self::LOCATION_ALIASES)
                ->filter(fn ($canonical) => $canonical === $payload['ciudad'])
                ->keys()
                ->push($payload['ciudad'])
                ->unique()
                ->values()
                ->all();
            $destinoId = (int) (DB::table('destino')
                ->whereIn(DB::raw('TRIM(UPPER(nombre_destino))'), $destinationNames)
                ->value('id') ?? 0);

            abort_if($destinoId <= 0, 422, 'El departamento de destino no esta configurado.');
            $payload['destino_id'] = $destinoId;
            $payload['pago_destinatario'] = (bool) ($payload['pago_destinatario'] ?? false);
        }

        return $payload;
    }

    private function createAttributes(): array
    {
        return [
            'codigo' => 'codigo',
            'codigo_solicitud' => 'codigo de solicitud',
            'cod_especial' => 'codigo especial',
            'estado_id' => 'estado',
            'estados_id' => 'estado',
            'fk_estado' => 'estado',
            'fk_ventanilla' => 'ventanilla',
            'servicio_id' => 'servicio',
            'empresa_id' => 'empresa',
            'cuidad' => 'destino',
            'ciudad' => 'destino',
            'nombre_r' => 'remitente',
            'nombre_d' => 'destinatario',
            'direccion_r' => 'direccion del remitente',
            'direccion_d' => 'direccion del destinatario',
        ];
    }
}

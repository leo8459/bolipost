<?php

namespace App\Http\Controllers;

use App\Exports\AreaContratosEntregadosExport;
use App\Exports\EmpresaGuiasExport;
use App\Models\Empresa;
use App\Models\EmpresaSucursal;
use App\Models\Estado;
use App\Models\Recojo;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class AreaContratosController extends Controller
{
    public function empresasPaquetes(Request $request)
    {
        $filters = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,'.(now()->year + 1)],
            'meses' => ['nullable', 'array', 'min:1', 'max:12'],
            'meses.*' => ['integer', 'distinct', 'between:1,12'],
        ]);
        $anio = (int) ($filters['anio'] ?? now()->year);
        $mesesSeleccionados = collect($filters['meses'] ?? [now()->month])
            ->map(fn ($mes) => (int) $mes)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $nombresMeses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $directosQuery = Recojo::query()
            ->leftJoin('estados as estado_contrato', 'estado_contrato.id', '=', 'paquetes_contrato.estados_id')
            ->whereNotNull('paquetes_contrato.empresa_id');
        $this->applyEmpresasPaquetesPeriod($directosQuery, $anio, $mesesSeleccionados);

        $directos = $directosQuery
            ->groupBy('paquetes_contrato.empresa_id')
            ->selectRaw('paquetes_contrato.empresa_id as empresa_id, COUNT(*) as registrados')
            ->selectRaw("SUM(CASE WHEN TRIM(UPPER(COALESCE(estado_contrato.nombre_estado, ''))) = 'ENTREGADO' THEN 1 ELSE 0 END) as entregados")
            ->get()
            ->keyBy(fn ($row) => (int) $row->empresa_id);

        // Los registros anteriores a empresa_id se atribuyen a la empresa del usuario que los creó.
        $antiguosQuery = Recojo::query()
            ->join('users as usuario_registro', 'usuario_registro.id', '=', 'paquetes_contrato.user_id')
            ->leftJoin('estados as estado_contrato', 'estado_contrato.id', '=', 'paquetes_contrato.estados_id')
            ->whereNull('paquetes_contrato.empresa_id')
            ->whereNotNull('usuario_registro.empresa_id');
        $this->applyEmpresasPaquetesPeriod($antiguosQuery, $anio, $mesesSeleccionados);

        $antiguos = $antiguosQuery
            ->groupBy('usuario_registro.empresa_id')
            ->selectRaw('usuario_registro.empresa_id as empresa_id, COUNT(*) as registrados')
            ->selectRaw("SUM(CASE WHEN TRIM(UPPER(COALESCE(estado_contrato.nombre_estado, ''))) = 'ENTREGADO' THEN 1 ELSE 0 END) as entregados")
            ->get()
            ->keyBy(fn ($row) => (int) $row->empresa_id);

        $empresas = Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla', 'codigo_cliente', 'presupuesto'])
            ->map(function (Empresa $empresa) use ($directos, $antiguos) {
                $directo = $directos->get((int) $empresa->id);
                $antiguo = $antiguos->get((int) $empresa->id);

                $empresa->setAttribute('contratos_registrados',
                    (int) ($directo->registrados ?? 0) + (int) ($antiguo->registrados ?? 0)
                );
                $empresa->setAttribute('contratos_entregados',
                    (int) ($directo->entregados ?? 0) + (int) ($antiguo->entregados ?? 0)
                );

                return $empresa;
            });

        return view('conciliacion.empresas-paquetes', [
            'empresas' => $empresas,
            'totalRegistrados' => $empresas->sum('contratos_registrados'),
            'totalEntregados' => $empresas->sum('contratos_entregados'),
            'totalPresupuesto' => $empresas->sum(fn (Empresa $empresa) => (float) ($empresa->presupuesto ?? 0)),
            'anio' => $anio,
            'mesesSeleccionados' => $mesesSeleccionados,
            'nombresMeses' => $nombresMeses,
        ]);
    }

    private function applyEmpresasPaquetesPeriod($query, int $anio, array $meses): void
    {
        $query->whereYear('paquetes_contrato.created_at', $anio)
            ->where(function ($monthQuery) use ($meses) {
                foreach ($meses as $index => $mes) {
                    $method = $index === 0 ? 'whereMonth' : 'orWhereMonth';
                    $monthQuery->{$method}('paquetes_contrato.created_at', $mes);
                }
            });
    }

    public function guiasEmpresa(Request $request)
    {
        $filters = $this->validatedGuiasEmpresaFilters($request);

        $search = trim((string) ($filters['q'] ?? ''));
        $empresaId = (int) ($filters['empresa_id'] ?? 0);
        $fechaDesde = (string) ($filters['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filters['fecha_hasta'] ?? '');

        $guias = $this->buildGuiasEmpresaQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('empresa.guias', [
            'guias' => $guias,
            'empresas' => Empresa::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'sigla', 'codigo_cliente']),
            'search' => $search,
            'empresaId' => $empresaId,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
        ]);
    }

    public function exportGuiasEmpresaExcel(Request $request)
    {
        $filters = $this->validatedGuiasEmpresaFilters($request);
        $rows = $this->buildGuiasEmpresaQuery($filters)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return Excel::download(
            new EmpresaGuiasExport($rows),
            'reporte-guias-empresa-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function validatedGuiasEmpresaFilters(Request $request): array
    {
        $fechaHastaRules = ['nullable', 'date_format:Y-m-d'];
        if ($request->filled('fecha_desde')) {
            $fechaHastaRules[] = 'after_or_equal:fecha_desde';
        }

        return $request->validate([
            'q' => ['nullable', 'string', 'max:150'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresa,id'],
            'fecha_desde' => ['nullable', 'date_format:Y-m-d'],
            'fecha_hasta' => $fechaHastaRules,
        ], [
            'fecha_hasta.after_or_equal' => 'La fecha hasta debe ser igual o posterior a la fecha desde.',
        ], [
            'empresa_id' => 'empresa',
            'fecha_desde' => 'fecha desde',
            'fecha_hasta' => 'fecha hasta',
        ]);
    }

    private function buildGuiasEmpresaQuery(array $filters)
    {
        $search = trim((string) ($filters['q'] ?? ''));
        $empresaId = (int) ($filters['empresa_id'] ?? 0);
        $fechaDesde = (string) ($filters['fecha_desde'] ?? '');
        $fechaHasta = (string) ($filters['fecha_hasta'] ?? '');

        return Recojo::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla,codigo_cliente',
                'user:id,name,empresa_id',
                'user.empresa:id,nombre,sigla,codigo_cliente',
            ])
            ->whereNotNull('estados_id')
            ->where('estados_id', '!=', 0)
            ->where(function ($query) {
                $query->whereNotNull('empresa_id')
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->whereNotNull('empresa_id'));
            })
            ->when($empresaId > 0, function ($query) use ($empresaId) {
                $query->where(function ($companyQuery) use ($empresaId) {
                    $companyQuery->where('empresa_id', $empresaId)
                        ->orWhere(function ($legacyQuery) use ($empresaId) {
                            $legacyQuery->whereNull('empresa_id')
                                ->whereHas('user', fn ($userQuery) => $userQuery->where('empresa_id', $empresaId));
                        });
                });
            })
            ->when($fechaDesde !== '', fn ($query) => $query->whereDate('created_at', '>=', $fechaDesde))
            ->when($fechaHasta !== '', fn ($query) => $query->whereDate('created_at', '<=', $fechaHasta))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('codigo', 'like', '%'.$search.'%')
                        ->orWhere('codigo_madre', 'like', '%'.$search.'%')
                        ->orWhere('cod_especial', 'like', '%'.$search.'%')
                        ->orWhere('nombre_r', 'like', '%'.$search.'%')
                        ->orWhere('nombre_d', 'like', '%'.$search.'%')
                        ->orWhere('origen', 'like', '%'.$search.'%')
                        ->orWhere('destino', 'like', '%'.$search.'%')
                        ->orWhereHas('estadoRegistro', fn ($estadoQuery) => $estadoQuery
                            ->where('nombre_estado', 'like', '%'.$search.'%'))
                        ->orWhereHas('empresa', fn ($empresaQuery) => $empresaQuery
                            ->where('nombre', 'like', '%'.$search.'%')
                            ->orWhere('sigla', 'like', '%'.$search.'%')
                            ->orWhere('codigo_cliente', 'like', '%'.$search.'%'))
                        ->orWhereHas('user.empresa', fn ($empresaQuery) => $empresaQuery
                            ->where('nombre', 'like', '%'.$search.'%')
                            ->orWhere('sigla', 'like', '%'.$search.'%')
                            ->orWhere('codigo_cliente', 'like', '%'.$search.'%'));
                });
            });
    }

    public function todos(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $estadoId = (int) $request->query('estado_id', 0);
        $empresaId = (int) $request->query('empresa_id', 0);
        $estados = Estado::query()
            ->orderBy('nombre_estado')
            ->get(['id', 'nombre_estado']);
        $empresas = Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla']);

        $contratos = Recojo::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla',
            ])
            ->when($estadoId > 0, function ($query) use ($estadoId) {
                $query->where('estados_id', $estadoId);
            })
            ->when($empresaId > 0, function ($query) use ($empresaId) {
                $query->where('empresa_id', $empresaId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('codigo', 'like', '%'.$search.'%')
                        ->orWhere('cod_especial', 'like', '%'.$search.'%')
                        ->orWhere('origen', 'like', '%'.$search.'%')
                        ->orWhere('destino', 'like', '%'.$search.'%')
                        ->orWhere('nombre_r', 'like', '%'.$search.'%')
                        ->orWhere('nombre_d', 'like', '%'.$search.'%')
                        ->orWhere('direccion_r', 'like', '%'.$search.'%')
                        ->orWhere('direccion_d', 'like', '%'.$search.'%')
                        ->orWhere('telefono_r', 'like', '%'.$search.'%')
                        ->orWhere('telefono_d', 'like', '%'.$search.'%')
                        ->orWhereHas('estadoRegistro', function ($estadoQuery) use ($search) {
                            $estadoQuery->where('nombre_estado', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('empresa', function ($empresaQuery) use ($search) {
                            $empresaQuery->where('nombre', 'like', '%'.$search.'%')
                                ->orWhere('sigla', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('area_contratos.todos', [
            'contratos' => $contratos,
            'search' => $search,
            'estadoId' => $estadoId,
            'empresaId' => $empresaId,
            'estados' => $estados,
            'empresas' => $empresas,
        ]);
    }

    public function entregados(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $estadoEntregadoId = $this->resolveEstadoIdByName('ENTREGADO');

        $contratos = $this->buildEntregadosQuery($search, $estadoEntregadoId > 0 ? [$estadoEntregadoId] : [])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('area_contratos.entregados', [
            'contratos' => $contratos,
            'search' => $search,
            'estadoEntregadoDisponible' => $estadoEntregadoId > 0,
        ]);
    }

    public function reportes(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $empresas = $this->buildEmpresaGroups();
        [$empresaIds, $sucursal, $empresaIdsConsulta] = $this->resolveReportScope($request, $empresas);

        $query = $this->buildContratosReportQuery($search, $empresaIdsConsulta, $from, $to, $sucursal);

        $contratos = (clone $query)
            ->orderBy('fecha_recojo')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $summaryRows = (clone $query)
            ->toBase()
            ->select('origen')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('origen')
            ->get();

        $groupedSummary = $summaryRows
            ->groupBy(fn ($row) => $this->normalizeOrigenSheetName($row->origen))
            ->map(fn ($items, $origen) => [
                'origen' => $origen,
                'total' => (int) $items->sum('total'),
            ])
            ->sortBy('origen', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('area_contratos.reportes', [
            'contratos' => $contratos,
            'empresas' => $empresas,
            'empresaIds' => $empresaIds,
            'sucursal' => $sucursal,
            'sucursalesPorEmpresa' => $this->reportBranchOptions($empresas),
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'groupedSummary' => $groupedSummary,
            'totalReportes' => $contratos->total(),
        ]);
    }

    public function exportReportesExcel(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $empresas = $this->buildEmpresaGroups();
        [$empresaIds, $sucursal, $empresaIdsConsulta] = $this->resolveReportScope($request, $empresas);
        $seleccionadas = $empresas->flatMap(fn (array $group) => $group['empresas'])
            ->whereInStrict('id', $sucursal ? [(int) $sucursal->empresa_id] : $empresaIds);
        $codigos = $seleccionadas->pluck('codigo_cliente')->unique();
        $empresa = $seleccionadas->isNotEmpty()
            ? (object) [
                'nombre' => $seleccionadas->pluck('nombre')->unique()->implode(' / '),
                'codigo_cliente' => $codigos->count() === 1 ? $codigos->first() : '',
            ]
            : null;

        $rowsQuery = $this->buildContratosReportQuery($search, $empresaIdsConsulta, $from, $to, $sucursal, false)
            ->select([
                'paquetes_contrato.id',
                'paquetes_contrato.user_id',
                'paquetes_contrato.estados_id',
                'paquetes_contrato.codigo',
                'paquetes_contrato.origen',
                'paquetes_contrato.provincia_origen',
                'paquetes_contrato.destino',
                'paquetes_contrato.destino_registrado',
                'paquetes_contrato.provincia',
                'paquetes_contrato.peso',
                'paquetes_contrato.precio',
                'paquetes_contrato.fecha_recojo',
                'paquetes_contrato.updated_at',
                'paquetes_contrato.nombre_d',
                'paquetes_contrato.observacion',
            ])
            ->with([
                'estadoRegistro:id,nombre_estado',
                'user:id,name',
            ])
            // Solo necesitamos saber si existe una imagen para poner un enlace.
            // No cargamos las imágenes (que pueden ser base64) en cada fila del Excel.
            ->selectRaw(
                "CASE
                    WHEN EXISTS (
                        SELECT 1
                        FROM estados AS report_estado
                        WHERE report_estado.id = paquetes_contrato.estados_id
                          AND UPPER(TRIM(report_estado.nombre_estado)) IN (?, ?, ?)
                    ) THEN EXISTS (
                        SELECT 1
                        FROM cartero AS report_cartero
                        WHERE report_cartero.id_paquetes_contrato = paquetes_contrato.id
                          AND report_cartero.imagen_devolucion IS NOT NULL
                          AND report_cartero.imagen_devolucion <> ''
                    )
                    ELSE paquetes_contrato.imagen IS NOT NULL
                         AND paquetes_contrato.imagen <> ''
                END AS report_has_delivery_image",
                ['DEVOLUCION', 'DEVOLUCIÓN', 'DEVOLUCIÃ“N']
            );

        $originExpression = "COALESCE(NULLIF(TRIM(origen), ''), 'SIN ORIGEN')";
        $summaryRows = (clone $rowsQuery)
            ->toBase()
            ->select([])
            ->selectRaw("{$originExpression} as origen")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COALESCE(SUM(peso), 0) as peso')
            ->selectRaw('COALESCE(SUM(precio), 0) as subtotal')
            ->groupByRaw($originExpression)
            ->orderBy('origen')
            ->get()
            ->sortBy('origen', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $totals = [
            'count' => (int) $summaryRows->sum('total'),
            'weight' => (float) $summaryRows->sum('peso'),
            'subtotal' => (float) $summaryRows->sum('subtotal'),
        ];

        $empresaCodigo = trim((string) ($empresa->codigo_cliente ?? ''));
        $empresaNombre = $empresaCodigo !== ''
            ? $empresaCodigo
            : ($seleccionadas->count() > 1 ? 'EMPRESAS' : ($empresa->nombre ?? 'GENERAL'));
        if ($sucursal) {
            $empresaNombre .= '-'.$sucursal->nombre;
        }
        $empresaSlug = preg_replace('/[^A-Za-z0-9]+/', '-', $empresaNombre) ?? 'GENERAL';
        $empresaSlug = trim($empresaSlug, '-');
        if ($empresaSlug === '') {
            $empresaSlug = 'GENERAL';
        }

        $filename = 'PLANILLA-'.strtoupper($empresaSlug).'-'.now()->format('Ymd-His').'.xlsx';

        $export = new AreaContratosEntregadosExport($rowsQuery, $summaryRows, $totals, [
            'empresa' => $empresa,
            'sucursal' => $sucursal?->etiqueta,
            'from' => $from,
            'to' => $to,
            'search' => $search,
            'logged_user' => $request->user(),
        ]);

        return Excel::download($export, $filename);
    }

    public function downloadImagenEntrega(Recojo $contrato)
    {
        $imagePath = trim((string) $contrato->imagenParaReporte());

        abort_if($imagePath === '', 404);

        if (preg_match('/^https?:\/\//i', $imagePath) === 1) {
            return redirect()->away($imagePath);
        }

        if (preg_match('/^data:(image\/[a-z0-9.+-]+)(?:;[^,]*)?;base64,(.*)$/is', $imagePath, $matches) === 1) {
            $binary = base64_decode(preg_replace('/\s+/', '', $matches[2]) ?? '', true);
            abort_if(! is_string($binary) || $binary === '', 404);

            return $this->downloadImageBinary($contrato, $binary, strtolower($matches[1]));
        }

        $encoded = preg_replace('/\s+/', '', $imagePath) ?? '';
        if (
            strlen($encoded) >= 128
            && preg_match('/^[A-Za-z0-9+\/=]+$/', $encoded) === 1
            && ($binary = base64_decode($encoded, true)) !== false
        ) {
            return $this->downloadImageBinary($contrato, $binary);
        }

        abort_if(! Storage::disk('public')->exists($imagePath), 404);

        $extension = pathinfo($imagePath, PATHINFO_EXTENSION);
        $code = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($contrato->codigo ?: $contrato->id)) ?: (string) $contrato->id;
        $filename = ($contrato->esDevolucion() ? 'imagen-devolucion-' : 'imagen-entrega-').trim($code, '-');
        if ($extension !== '') {
            $filename .= '.'.$extension;
        }

        return Storage::disk('public')->download($imagePath, $filename);
    }

    private function downloadImageBinary(Recojo $contrato, string $binary, ?string $declaredMime = null)
    {
        $detectedMime = function_exists('getimagesizefromstring')
            ? (getimagesizefromstring($binary)['mime'] ?? null)
            : null;
        $mime = is_string($detectedMime) ? strtolower($detectedMime) : strtolower((string) $declaredMime);

        abort_if(! str_starts_with($mime, 'image/'), 404);

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            default => 'img',
        };
        $code = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($contrato->codigo ?: $contrato->id)) ?: (string) $contrato->id;
        $filename = ($contrato->esDevolucion() ? 'imagen-devolucion-' : 'imagen-entrega-').trim($code, '-').'.'.$extension;

        return response($binary, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($binary),
        ]);
    }

    private function buildEntregadosQuery(string $search, array $estadoIds)
    {
        return Recojo::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla',
                'user:id,name',
            ])
            ->when(! empty($estadoIds), function ($query) use ($estadoIds) {
                $query->whereIn('estados_id', $estadoIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('codigo', 'like', '%'.$search.'%')
                        ->orWhere('cod_especial', 'like', '%'.$search.'%')
                        ->orWhere('origen', 'like', '%'.$search.'%')
                        ->orWhere('destino', 'like', '%'.$search.'%')
                        ->orWhere('nombre_r', 'like', '%'.$search.'%')
                        ->orWhere('nombre_d', 'like', '%'.$search.'%')
                        ->orWhere('direccion_r', 'like', '%'.$search.'%')
                        ->orWhere('direccion_d', 'like', '%'.$search.'%')
                        ->orWhere('telefono_r', 'like', '%'.$search.'%')
                        ->orWhere('telefono_d', 'like', '%'.$search.'%')
                        ->orWhereHas('empresa', function ($empresaQuery) use ($search) {
                            $empresaQuery->where('nombre', 'like', '%'.$search.'%')
                                ->orWhere('sigla', 'like', '%'.$search.'%');
                        });
                });
            });
    }

    private function buildContratosReportQuery(
        string $search,
        array $empresaIds,
        string $from,
        string $to,
        ?EmpresaSucursal $sucursal,
        bool $withRelations = true
    ) {
        return Recojo::query()
            ->when($withRelations, fn ($query) => $query->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla,codigo_cliente',
                'user:id,name',
            ]))
            ->whereNotNull('fecha_recojo')
            ->whereNotNull('estados_id')
            ->where('estados_id', '!=', 0)
            ->whereDoesntHave('estadoRegistro', function ($query) {
                $query->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO']);
            })
            ->when(! empty($empresaIds), function ($query) use ($empresaIds) {
                $query->whereIn('empresa_id', $empresaIds);
            })
            ->when($sucursal, function ($query) use ($sucursal) {
                $query->whereHas('user', fn ($userQuery) => $userQuery->withTrashed()
                    ->where('users.empresa_id', $sucursal->empresa_id)
                    ->where('users.empresa_sucursal_id', $sucursal->id));
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('codigo', 'like', '%'.$search.'%')
                        ->orWhere('cod_especial', 'like', '%'.$search.'%')
                        ->orWhere('origen', 'like', '%'.$search.'%')
                        ->orWhere('destino_registrado', 'like', '%'.$search.'%')
                        ->orWhere('nombre_r', 'like', '%'.$search.'%')
                        ->orWhere('nombre_d', 'like', '%'.$search.'%')
                        ->orWhere('direccion_r', 'like', '%'.$search.'%')
                        ->orWhere('direccion_d', 'like', '%'.$search.'%')
                        ->orWhere('telefono_r', 'like', '%'.$search.'%')
                        ->orWhere('telefono_d', 'like', '%'.$search.'%')
                        ->orWhereHas('empresa', function ($empresaQuery) use ($search) {
                            $empresaQuery->where('nombre', 'like', '%'.$search.'%')
                                ->orWhere('sigla', 'like', '%'.$search.'%')
                                ->orWhere('codigo_cliente', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('estadoRegistro', function ($estadoQuery) use ($search) {
                            $estadoQuery->where('nombre_estado', 'like', '%'.$search.'%');
                        });
                });
            })
            ->when($from !== '', function ($query) use ($from) {
                $query->whereDate('fecha_recojo', '>=', $from);
            })
            ->when($to !== '', function ($query) use ($to) {
                $query->whereDate('fecha_recojo', '<=', $to);
            });
    }

    private function buildEmpresaGroups(): Collection
    {
        return Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla', 'codigo_cliente'])
            ->groupBy(function (Empresa $empresa) {
                $codigo = $this->normalizeCodigoCliente($empresa->codigo_cliente);

                return $codigo !== '' ? 'codigo:'.$codigo : 'empresa:'.$empresa->id;
            })
            ->map(function (Collection $items) {
                $codigo = $this->normalizeCodigoCliente($items->first()->codigo_cliente);
                $empresas = $items->map(function (Empresa $empresa) use ($codigo) {
                    $nombre = trim((string) $empresa->nombre);
                    $sigla = trim((string) $empresa->sigla);

                    return [
                        'id' => (int) $empresa->id,
                        'nombre' => $sigla !== '' ? "{$nombre} ({$sigla})" : $nombre,
                        'codigo_cliente' => $codigo,
                    ];
                });

                return [
                    'ids' => $empresas->pluck('id')->sort()->values()->all(),
                    'codigo_cliente' => $codigo,
                    'empresas' => $empresas,
                ];
            })
            ->sortBy(fn (array $group) => $group['codigo_cliente'] ?: $group['empresas']->first()['nombre'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function resolveReportEmpresaIds(Request $request, Collection $groups): array
    {
        if ($request->query->has('empresa_ids')) {
            $validated = $request->validate([
                'empresa_ids' => ['array'],
                'empresa_ids.*' => ['required', 'integer', 'min:1'],
            ]);

            return array_values(array_unique(array_map('intval', $validated['empresa_ids'])));
        }

        // Los enlaces anteriores seleccionan el código de cliente completo.
        $empresaId = $request->integer('empresa_id');
        if ($empresaId <= 0) {
            return [];
        }

        $group = $groups->first(
            fn (array $group) => in_array($empresaId, $group['ids'], true)
        );

        return $group['ids'] ?? [$empresaId];
    }

    private function resolveReportScope(Request $request, Collection $groups): array
    {
        $empresaIds = $this->resolveReportEmpresaIds($request, $groups);
        $validated = $request->validate([
            'empresa_sucursal_id' => ['nullable', 'integer', 'min:1'],
        ]);
        if (empty($validated['empresa_sucursal_id'])) {
            return [$empresaIds, null, $empresaIds];
        }

        $sucursal = EmpresaSucursal::query()
            ->whereIn('empresa_id', $empresaIds)
            ->find((int) $validated['empresa_sucursal_id']);

        if (! $sucursal) {
            throw ValidationException::withMessages([
                'empresa_sucursal_id' => 'Selecciona una sucursal de una de las empresas elegidas.',
            ]);
        }

        // Una guía puede tener el ID de otra empresa del mismo código de cliente.
        $group = $groups->first(fn (array $group) => in_array((int) $sucursal->empresa_id, $group['ids'], true));

        return [$empresaIds, $sucursal, $group['ids'] ?? [(int) $sucursal->empresa_id]];
    }

    private function reportBranchOptions(Collection $groups): Collection
    {
        $sucursales = EmpresaSucursal::query()->orderBy('nombre')->get(['id', 'empresa_id', 'nombre', 'ciudad'])
            ->groupBy('empresa_id');

        return $groups->flatMap(fn (array $group) => $group['empresas'])
            ->whereInStrict('id', $sucursales->keys())
            ->mapWithKeys(fn (array $empresa) => [$empresa['id'] => [
                'nombre' => $empresa['nombre'],
                'sucursales' => $sucursales->get($empresa['id'])->map(fn (EmpresaSucursal $sucursal) => [
                    'id' => (int) $sucursal->id,
                    'nombre' => $sucursal->etiqueta,
                ]),
            ]]);
    }

    private function normalizeCodigoCliente(?string $codigo): string
    {
        return preg_replace('/\s+/', '', strtoupper(trim((string) $codigo))) ?? '';
    }

    private function resolveEstadoIdByName(string $nombre): int
    {
        return (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($nombre))])
            ->value('id') ?? 0);
    }

    private function normalizeOrigenSheetName(?string $origen): string
    {
        $value = trim((string) $origen);

        return $value !== '' ? $value : 'SIN ORIGEN';
    }
}

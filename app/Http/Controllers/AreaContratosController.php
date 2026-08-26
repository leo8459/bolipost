<?php

namespace App\Http\Controllers;

use App\Exports\AreaContratosEntregadosExport;
use App\Exports\EmpresaGuiasExport;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Recojo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
        $empresaId = (int) $request->query('empresa_id', 0);
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        $empresas = $this->buildEmpresaGroups();
        $empresaGroup = $this->resolveEmpresaGroup($empresas, $empresaId);
        $empresaIds = $empresaGroup['ids'] ?? ($empresaId > 0 ? [$empresaId] : []);

        $query = $this->buildContratosReportQuery($search, $empresaIds, $from, $to);

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
            'empresaId' => $empresaId,
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
        $empresaId = (int) $request->query('empresa_id', 0);
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $empresaGroup = $this->resolveEmpresaGroup($this->buildEmpresaGroups(), $empresaId);
        $empresaIds = $empresaGroup['ids'] ?? ($empresaId > 0 ? [$empresaId] : []);
        $empresa = $empresaGroup
            ? (object) [
                'nombre' => $empresaGroup['nombres'],
                'sigla' => null,
                'codigo_cliente' => $empresaGroup['codigo_cliente'],
            ]
            : null;

        $rows = $this->buildContratosReportQuery($search, $empresaIds, $from, $to)
            ->orderBy('origen')
            ->orderBy('fecha_recojo')
            ->orderBy('id')
            ->get();

        $empresaCodigo = trim((string) ($empresa->codigo_cliente ?? ''));
        $empresaNombre = $empresaCodigo !== ''
            ? $empresaCodigo
            : trim((string) ($empresa->nombre ?? 'GENERAL'));
        $empresaSlug = preg_replace('/[^A-Za-z0-9]+/', '-', $empresaNombre) ?? 'GENERAL';
        $empresaSlug = trim($empresaSlug, '-');
        if ($empresaSlug === '') {
            $empresaSlug = 'GENERAL';
        }

        $filename = 'PLANILLA-'.strtoupper($empresaSlug).'-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(
            new AreaContratosEntregadosExport($rows, [
                'empresa' => $empresa,
                'from' => $from,
                'to' => $to,
                'search' => $search,
                'logged_user' => $request->user(),
            ]),
            $filename
        );
    }

    public function downloadImagenEntrega(Recojo $contrato)
    {
        $imagePath = trim((string) ($contrato->imagen ?? ''));

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
        $filename = 'imagen-entrega-'.trim($code, '-');
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
        $filename = 'imagen-entrega-'.trim($code, '-').'.'.$extension;

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
        string $to
    ) {
        return Recojo::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla,codigo_cliente',
                'user:id,name',
            ])
            ->whereNotNull('fecha_recojo')
            ->whereNotNull('estados_id')
            ->where('estados_id', '!=', 0)
            ->whereDoesntHave('estadoRegistro', function ($query) {
                $query->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO']);
            })
            ->when(! empty($empresaIds), function ($query) use ($empresaIds) {
                $query->whereIn('empresa_id', $empresaIds);
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

    private function buildEmpresaGroups()
    {
        return Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla', 'codigo_cliente'])
            ->groupBy(function (Empresa $empresa) {
                $codigo = $this->normalizeCodigoCliente($empresa->codigo_cliente);

                return $codigo !== '' ? 'codigo:'.$codigo : 'empresa:'.$empresa->id;
            })
            ->map(function ($items) {
                $codigo = $this->normalizeCodigoCliente($items->first()->codigo_cliente);
                $ids = $items->pluck('id')->map(fn ($id) => (int) $id)->sort()->values();
                $nombres = $items
                    ->map(function (Empresa $empresa) {
                        $nombre = trim((string) $empresa->nombre);
                        $sigla = trim((string) $empresa->sigla);

                        return $sigla !== '' ? "{$nombre} ({$sigla})" : $nombre;
                    })
                    ->filter()
                    ->unique()
                    ->implode(' / ');

                return [
                    'id' => (int) $ids->first(),
                    'ids' => $ids->all(),
                    'codigo_cliente' => $codigo,
                    'nombres' => $nombres,
                    'label' => $codigo !== '' ? "{$codigo} - {$nombres}" : $nombres,
                ];
            })
            ->sortBy(fn (array $group) => $group['label'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function resolveEmpresaGroup($groups, int $empresaId): ?array
    {
        if ($empresaId <= 0) {
            return null;
        }

        return $groups->first(
            fn (array $group) => in_array($empresaId, $group['ids'], true)
        );
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

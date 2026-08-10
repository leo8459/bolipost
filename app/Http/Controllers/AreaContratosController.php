<?php

namespace App\Http\Controllers;

use App\Exports\AreaContratosEntregadosExport;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Recojo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AreaContratosController extends Controller
{
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
            ->orderBy('created_at')
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
            ->orderBy('created_at')
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
                $query->whereDate('created_at', '>=', $from);
            })
            ->when($to !== '', function ($query) use ($to) {
                $query->whereDate('created_at', '<=', $to);
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

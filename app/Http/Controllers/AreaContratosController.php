<?php

namespace App\Http\Controllers;

use App\Exports\AreaContratosEntregadosExport;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Recojo;
use Illuminate\Http\Request;
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
                    $sub->where('codigo', 'like', '%' . $search . '%')
                        ->orWhere('cod_especial', 'like', '%' . $search . '%')
                        ->orWhere('origen', 'like', '%' . $search . '%')
                        ->orWhere('destino', 'like', '%' . $search . '%')
                        ->orWhere('nombre_r', 'like', '%' . $search . '%')
                        ->orWhere('nombre_d', 'like', '%' . $search . '%')
                        ->orWhere('direccion_r', 'like', '%' . $search . '%')
                        ->orWhere('direccion_d', 'like', '%' . $search . '%')
                        ->orWhere('telefono_r', 'like', '%' . $search . '%')
                        ->orWhere('telefono_d', 'like', '%' . $search . '%')
                        ->orWhereHas('estadoRegistro', function ($estadoQuery) use ($search) {
                            $estadoQuery->where('nombre_estado', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('empresa', function ($empresaQuery) use ($search) {
                            $empresaQuery->where('nombre', 'like', '%' . $search . '%')
                                ->orWhere('sigla', 'like', '%' . $search . '%');
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
        $empresas = Empresa::query()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'sigla']);

        $query = $this->buildContratosReportQuery($search, $empresaId, $from, $to);

        $contratos = (clone $query)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        $rows = (clone $query)
            ->orderBy('origen')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $groupedSummary = $rows
            ->groupBy(fn (Recojo $contrato) => $this->normalizeOrigenSheetName($contrato->origen))
            ->map(fn ($items, $origen) => [
                'origen' => $origen,
                'total' => $items->count(),
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
            'totalReportes' => $rows->count(),
        ]);
    }

    public function exportReportesExcel(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $empresaId = (int) $request->query('empresa_id', 0);
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $empresa = $empresaId > 0
            ? Empresa::query()->find($empresaId, ['id', 'nombre', 'sigla'])
            : null;

        $rows = $this->buildContratosReportQuery($search, $empresaId, $from, $to)
            ->orderBy('origen')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $empresaNombre = trim((string) ($empresa->nombre ?? 'GENERAL'));
        $empresaSlug = preg_replace('/[^A-Za-z0-9]+/', '-', $empresaNombre) ?? 'GENERAL';
        $empresaSlug = trim($empresaSlug, '-');
        if ($empresaSlug === '') {
            $empresaSlug = 'GENERAL';
        }

        $filename = 'PLANILLA-' . strtoupper($empresaSlug) . '-' . now()->format('Ymd-His') . '.xlsx';

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

    private function buildEntregadosQuery(string $search, array $estadoIds)
    {
        return Recojo::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla',
                'user:id,name',
            ])
            ->when(!empty($estadoIds), function ($query) use ($estadoIds) {
                $query->whereIn('estados_id', $estadoIds);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('codigo', 'like', '%' . $search . '%')
                        ->orWhere('cod_especial', 'like', '%' . $search . '%')
                        ->orWhere('origen', 'like', '%' . $search . '%')
                        ->orWhere('destino', 'like', '%' . $search . '%')
                        ->orWhere('nombre_r', 'like', '%' . $search . '%')
                        ->orWhere('nombre_d', 'like', '%' . $search . '%')
                        ->orWhere('direccion_r', 'like', '%' . $search . '%')
                        ->orWhere('direccion_d', 'like', '%' . $search . '%')
                        ->orWhere('telefono_r', 'like', '%' . $search . '%')
                        ->orWhere('telefono_d', 'like', '%' . $search . '%')
                        ->orWhereHas('empresa', function ($empresaQuery) use ($search) {
                            $empresaQuery->where('nombre', 'like', '%' . $search . '%')
                                ->orWhere('sigla', 'like', '%' . $search . '%');
                        });
                });
            });
    }

    private function buildContratosReportQuery(
        string $search,
        int $empresaId,
        string $from,
        string $to
    ) {
        return Recojo::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla',
                'user:id,name',
            ])
            ->whereNotNull('estados_id')
            ->where('estados_id', '!=', 0)
            ->whereDoesntHave('estadoRegistro', function ($query) {
                $query->whereRaw('trim(upper(nombre_estado)) = ?', ['CANCELADO']);
            })
            ->when($empresaId > 0, function ($query) use ($empresaId) {
                $query->where('empresa_id', $empresaId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('codigo', 'like', '%' . $search . '%')
                        ->orWhere('cod_especial', 'like', '%' . $search . '%')
                        ->orWhere('origen', 'like', '%' . $search . '%')
                        ->orWhere('destino', 'like', '%' . $search . '%')
                        ->orWhere('nombre_r', 'like', '%' . $search . '%')
                        ->orWhere('nombre_d', 'like', '%' . $search . '%')
                        ->orWhere('direccion_r', 'like', '%' . $search . '%')
                        ->orWhere('direccion_d', 'like', '%' . $search . '%')
                        ->orWhere('telefono_r', 'like', '%' . $search . '%')
                        ->orWhere('telefono_d', 'like', '%' . $search . '%')
                        ->orWhereHas('empresa', function ($empresaQuery) use ($search) {
                            $empresaQuery->where('nombre', 'like', '%' . $search . '%')
                                ->orWhere('sigla', 'like', '%' . $search . '%');
                        })
                        ->orWhereHas('estadoRegistro', function ($estadoQuery) use ($search) {
                            $estadoQuery->where('nombre_estado', 'like', '%' . $search . '%');
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

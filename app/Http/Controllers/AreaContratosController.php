<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\Recojo;
use Illuminate\Http\Request;

class AreaContratosController extends Controller
{
    public function todos(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $estadoId = (int) $request->query('estado_id', 0);
        $estados = Estado::query()
            ->orderBy('nombre_estado')
            ->get(['id', 'nombre_estado']);

        $contratos = Recojo::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla',
                'user:id,name',
            ])
            ->when($estadoId > 0, function ($query) use ($estadoId) {
                $query->where('estados_id', $estadoId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('codigo', 'like', '%' . $search . '%')
                        ->orWhere('cod_especial', 'like', '%' . $search . '%')
                        ->orWhere('origen', 'like', '%' . $search . '%')
                        ->orWhere('destino', 'like', '%' . $search . '%')
                        ->orWhere('nombre_r', 'like', '%' . $search . '%')
                        ->orWhere('nombre_d', 'like', '%' . $search . '%')
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
            'estados' => $estados,
        ]);
    }

    public function entregados(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $estadoEntregadoId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
            ->value('id') ?? 0);

        $contratos = Recojo::query()
            ->with([
                'estadoRegistro:id,nombre_estado',
                'empresa:id,nombre,sigla',
                'user:id,name',
            ])
            ->when($estadoEntregadoId > 0, function ($query) use ($estadoEntregadoId) {
                $query->where('estados_id', $estadoEntregadoId);
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
                        ->orWhere('telefono_r', 'like', '%' . $search . '%')
                        ->orWhere('telefono_d', 'like', '%' . $search . '%')
                        ->orWhereHas('empresa', function ($empresaQuery) use ($search) {
                            $empresaQuery->where('nombre', 'like', '%' . $search . '%')
                                ->orWhere('sigla', 'like', '%' . $search . '%');
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('area_contratos.entregados', [
            'contratos' => $contratos,
            'search' => $search,
            'estadoEntregadoDisponible' => $estadoEntregadoId > 0,
        ]);
    }
}

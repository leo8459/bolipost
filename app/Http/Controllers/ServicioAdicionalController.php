<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\Request;

class ServicioAdicionalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->can('servicios-adicionales.index'), 403);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'in:habilitado,deshabilitado'],
        ]);
        $search = trim($filters['q'] ?? '');
        $estado = $filters['estado'] ?? '';

        $empresas = Empresa::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    foreach (['nombre', 'sigla', 'codigo_cliente'] as $column) {
                        $query->orWhereRaw('LOWER('.$column.') LIKE ?', ['%'.mb_strtolower($search).'%']);
                    }
                });
            })
            ->when($estado !== '', fn ($query) => $query->where('servicio_ida_vuelta', $estado === 'habilitado'))
            ->orderBy('nombre')->orderBy('id')->paginate(100)->withQueryString();

        return view('servicios_adicionales.index', compact('empresas', 'search', 'estado'));
    }

    public function update(Request $request, Empresa $empresa)
    {
        abort_unless($request->user()?->can('feature.servicios-adicionales.edit'), 403);

        $data = $request->validate(['servicio_ida_vuelta' => ['required', 'boolean']]);
        $empresa->servicio_ida_vuelta = (bool) $data['servicio_ida_vuelta'];
        $empresa->save();

        return back()->with('success', 'Servicio de ida y vuelta '.($empresa->servicio_ida_vuelta ? 'habilitado' : 'deshabilitado').' para '.$empresa->nombre.'.');
    }
}

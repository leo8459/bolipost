<?php

namespace App\Http\Controllers;

use App\Models\ConceptoFacturacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConceptoFacturacionController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $conceptos = ConceptoFacturacion::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q, 'UTF-8') . '%';

                $query->where(function ($search) use ($like) {
                    $search->whereRaw('LOWER(nombre) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(descripcion) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(codigo) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(codigo_sin) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(actividad_economica) LIKE ?', [$like]);
                });
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('concepto_facturacion.index', [
            'conceptos' => $conceptos,
            'q' => $q,
        ]);
    }

    public function create()
    {
        return view('concepto_facturacion.create', [
            'concepto' => new ConceptoFacturacion(),
        ]);
    }

    public function store(Request $request)
    {
        ConceptoFacturacion::query()->create($this->validateData($request));

        return redirect()
            ->route('conceptos-facturacion.index')
            ->with('success', 'Concepto facturable creado correctamente.');
    }

    public function edit(ConceptoFacturacion $conceptoFacturacion)
    {
        return view('concepto_facturacion.edit', [
            'concepto' => $conceptoFacturacion,
        ]);
    }

    public function update(Request $request, ConceptoFacturacion $conceptoFacturacion)
    {
        $conceptoFacturacion->update($this->validateData($request, $conceptoFacturacion));

        return redirect()
            ->route('conceptos-facturacion.index')
            ->with('success', 'Concepto facturable actualizado correctamente.');
    }

    public function destroy(ConceptoFacturacion $conceptoFacturacion)
    {
        $conceptoFacturacion->delete();

        return redirect()
            ->route('conceptos-facturacion.index')
            ->with('success', 'Concepto facturable eliminado correctamente.');
    }

    private function validateData(Request $request, ?ConceptoFacturacion $concepto = null): array
    {
        return $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('conceptos_facturacion', 'nombre')->ignore($concepto?->id),
            ],
            'actividad_economica' => ['required', 'string', 'max:6'],
            'codigo_sin' => ['required', 'string', 'max:7'],
            'codigo' => ['required', 'string', 'max:50', Rule::unique('conceptos_facturacion', 'codigo')->ignore($concepto?->id)],
            'unidad_medida' => ['required', 'integer', 'min:1'],
            'descripcion' => ['required', 'string', 'max:500'],
            'precio_base' => ['required', 'numeric', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'actividad_economica' => 'actividad economica',
            'codigo_sin' => 'codigo SIN',
            'unidad_medida' => 'unidad de medida',
            'precio_base' => 'precio base',
        ]);
    }
}

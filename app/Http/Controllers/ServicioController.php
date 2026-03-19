<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServicioController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $servicios = Servicio::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($search) use ($q) {
                    $search->where('nombre_servicio', 'ILIKE', "%{$q}%")
                        ->orWhere('descripcion', 'ILIKE', "%{$q}%")
                        ->orWhere('codigo', 'ILIKE', "%{$q}%")
                        ->orWhere('codigoSin', 'ILIKE', "%{$q}%")
                        ->orWhere('actividadEconomica', 'ILIKE', "%{$q}%")
                        ->orWhereRaw('CAST("unidadMedida" AS TEXT) ILIKE ?', ["%{$q}%"]);
                });
            })
            ->orderBy('nombre_servicio')
            ->paginate(15)
            ->withQueryString();

        return view('servicio.index', [
            'servicios' => $servicios,
            'q' => $q,
        ]);
    }

    public function create()
    {
        return view('servicio.create', [
            'servicio' => new Servicio(),
        ]);
    }

    public function store(Request $request)
    {
        Servicio::query()->create($this->validateData($request));

        return redirect()
            ->route('servicios.index')
            ->with('success', 'Servicio creado correctamente.');
    }

    public function edit(Servicio $servicio)
    {
        return view('servicio.edit', [
            'servicio' => $servicio,
        ]);
    }

    public function update(Request $request, Servicio $servicio)
    {
        $servicio->update($this->validateData($request, $servicio));

        return redirect()
            ->route('servicios.index')
            ->with('success', 'Servicio actualizado correctamente.');
    }

    public function destroy(Servicio $servicio)
    {
        try {
            $servicio->delete();
        } catch (QueryException) {
            return redirect()
                ->route('servicios.index')
                ->with('error', 'No se pudo dar de baja el servicio porque esta siendo utilizado.');
        }

        return redirect()
            ->route('servicios.index')
            ->with('success', 'Servicio dado de baja correctamente.');
    }

    private function validateData(Request $request, ?Servicio $servicio = null): array
    {
        return $request->validate([
            'nombre_servicio' => [
                'required',
                'string',
                'max:255',
                Rule::unique('servicio', 'nombre_servicio')->ignore($servicio?->id),
            ],
            'actividadEconomica' => ['nullable', 'string', 'max:6'],
            'codigoSin' => ['nullable', 'string', 'max:7'],
            'codigo' => ['nullable', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'unidadMedida' => ['nullable', 'integer', 'min:0'],
        ]);
    }
}

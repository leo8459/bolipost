<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SucursalController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $sucursales = Sucursal::query()
            ->withCount('users')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($search) use ($q) {
                    $search->whereRaw('CAST("codigoSucursal" AS TEXT) ILIKE ?', ["%{$q}%"])
                        ->orWhereRaw('CAST("puntoVenta" AS TEXT) ILIKE ?', ["%{$q}%"])
                        ->orWhere('municipio', 'ILIKE', "%{$q}%")
                        ->orWhere('departamento', 'ILIKE', "%{$q}%")
                        ->orWhere('telefono', 'ILIKE', "%{$q}%");
                });
            })
            ->orderBy('codigoSucursal')
            ->orderBy('puntoVenta')
            ->paginate(15)
            ->withQueryString();

        return view('sucursal.index', [
            'sucursales' => $sucursales,
            'q' => $q,
        ]);
    }

    public function create()
    {
        return view('sucursal.create', [
            'sucursal' => new Sucursal(),
        ]);
    }

    public function store(Request $request)
    {
        Sucursal::query()->create($this->validateData($request));

        return redirect()
            ->route('sucursales.index')
            ->with('success', 'Sucursal creada correctamente.');
    }

    public function edit(Sucursal $sucursal)
    {
        return view('sucursal.edit', [
            'sucursal' => $sucursal,
        ]);
    }

    public function update(Request $request, Sucursal $sucursal)
    {
        $sucursal->update($this->validateData($request, $sucursal));

        return redirect()
            ->route('sucursales.index')
            ->with('success', 'Sucursal actualizada correctamente.');
    }

    public function destroy(Sucursal $sucursal)
    {
        try {
            $sucursal->delete();
        } catch (QueryException) {
            return redirect()
                ->route('sucursales.index')
                ->with('error', 'No se pudo dar de baja la sucursal. Verifica si tiene relaciones activas.');
        }

        return redirect()
            ->route('sucursales.index')
            ->with('success', 'Sucursal dada de baja correctamente.');
    }

    private function validateData(Request $request, ?Sucursal $sucursal = null): array
    {
        return $request->validate([
            'codigoSucursal' => [
                'required',
                'integer',
                'min:0',
                Rule::unique('sucursales', 'codigoSucursal')
                    ->where(fn ($query) => $query->where('puntoVenta', (int) $request->input('puntoVenta')))
                    ->ignore($sucursal?->id),
            ],
            'puntoVenta' => ['required', 'integer', 'min:0'],
            'municipio' => ['required', 'string', 'max:25'],
            'departamento' => ['nullable', 'string', 'max:15'],
            'telefono' => ['required', 'string', 'max:8'],
        ]);
    }
}

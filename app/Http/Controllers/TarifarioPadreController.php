<?php

namespace App\Http\Controllers;

use App\Models\TarifarioPadre;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TarifarioPadreController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tarifariosPadre = TarifarioPadre::query()
            ->withCount('servicios')
            ->when($q !== '', fn ($query) => $query->whereLike('nombre', "%{$q}%"))
            ->orderBy('nombre')->paginate(15)->withQueryString();

        return view('tarifario-padre.index', compact('tarifariosPadre', 'q'));
    }

    public function create()
    {
        return view('tarifario-padre.create', ['tarifarioPadre' => new TarifarioPadre]);
    }

    public function store(Request $request)
    {
        TarifarioPadre::create($this->validateData($request));

        return to_route('tarifario-padre.index')->with('success', 'Tarifario padre creado correctamente.');
    }

    public function edit(TarifarioPadre $tarifarioPadre)
    {
        return view('tarifario-padre.edit', compact('tarifarioPadre'));
    }

    public function update(Request $request, TarifarioPadre $tarifarioPadre)
    {
        $tarifarioPadre->update($this->validateData($request, $tarifarioPadre));

        return to_route('tarifario-padre.index')->with('success', 'Tarifario padre actualizado correctamente.');
    }

    public function destroy(TarifarioPadre $tarifarioPadre)
    {
        $message = 'No se puede eliminar este tarifario padre porque tiene servicios asociados. Reasigna o desvincula esos servicios primero.';

        if ($tarifarioPadre->servicios()->exists()) {
            return to_route('tarifario-padre.index')->with('error', $message);
        }

        try {
            $tarifarioPadre->delete();
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }

            return to_route('tarifario-padre.index')->with('error', $message);
        }

        return to_route('tarifario-padre.index')->with('success', 'Tarifario padre eliminado correctamente.');
    }

    private function validateData(Request $request, ?TarifarioPadre $tarifarioPadre = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('tarifario_padre', 'nombre')->ignore($tarifarioPadre?->id)],
        ]);
    }
}

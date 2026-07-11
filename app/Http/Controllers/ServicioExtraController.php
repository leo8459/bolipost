<?php

namespace App\Http\Controllers;

use App\Models\ServicioExtra;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServicioExtraController extends Controller
{
    private const PAQUETES_EMS_SERVICIOS_CACHE_KEY = 'lookup:paquetes-ems:servicio-extras';

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $servicioExtras = ServicioExtra::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where('nombre', 'ILIKE', "%{$q}%")
                    ->orWhere('descripcion', 'ILIKE', "%{$q}%");
            })
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return view('servicio_extra.index', [
            'servicioExtras' => $servicioExtras,
            'q' => $q,
        ]);
    }

    public function create()
    {
        return view('servicio_extra.create', [
            'servicioExtra' => new ServicioExtra(),
        ]);
    }

    public function store(Request $request)
    {
        ServicioExtra::query()->create($this->validateData($request));
        Cache::forget(self::PAQUETES_EMS_SERVICIOS_CACHE_KEY);

        return redirect()
            ->route('servicio-extras.index')
            ->with('success', 'Servicio extra creado correctamente.');
    }

    public function edit(ServicioExtra $servicioExtra)
    {
        return view('servicio_extra.edit', [
            'servicioExtra' => $servicioExtra,
        ]);
    }

    public function update(Request $request, ServicioExtra $servicioExtra)
    {
        $servicioExtra->update($this->validateData($request, $servicioExtra));
        Cache::forget(self::PAQUETES_EMS_SERVICIOS_CACHE_KEY);

        return redirect()
            ->route('servicio-extras.index')
            ->with('success', 'Servicio extra actualizado correctamente.');
    }

    public function destroy(ServicioExtra $servicioExtra)
    {
        $servicioExtra->delete();
        Cache::forget(self::PAQUETES_EMS_SERVICIOS_CACHE_KEY);

        return redirect()
            ->route('servicio-extras.index')
            ->with('success', 'Servicio extra eliminado correctamente.');
    }

    private function validateData(Request $request, ?ServicioExtra $servicioExtra = null): array
    {
        return $request->validate([
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('servicio_extras', 'nombre')->ignore($servicioExtra?->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);
    }
}

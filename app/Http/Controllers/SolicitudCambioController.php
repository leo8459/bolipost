<?php

namespace App\Http\Controllers;

use App\Models\SolicitudCambio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SolicitudCambioController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('solicitudes-cambio.index'), 403);
        $solicitudes = SolicitudCambio::query()
            ->when(! $request->user()->can('feature.solicitudes-cambio.manage'), fn ($query) => $query->where('user_id', $request->user()->id))
            ->latest('id')->paginate(15);

        return view('solicitudes_cambio.index', compact('solicitudes'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->can('solicitudes-cambio.index') && $request->user()->can('feature.solicitudes-cambio.create'), 403);
        $data = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('America/La_Paz')->toDateString()],
            'ciudad' => ['required', 'string', 'max:255'],
            'solicitante' => ['required', 'string', 'max:255'],
            'cargo' => ['required', 'string', 'max:255'],
            'unidad' => ['required', 'string', 'max:255'],
            'sistema' => ['required', 'string', 'max:255'],
            'modulo' => ['required', 'string', 'max:255'],
            'ambiente' => ['required', Rule::in(['Producción', 'Pruebas', 'Desarrollo'])],
            'tipo' => ['required', Rule::in(SolicitudCambio::TIPOS)],
            'otro_tipo' => ['exclude_unless:tipo,Otro', 'required', 'string', 'max:255'],
            'antecedentes' => ['required', 'string', 'max:10000'],
            'detalle' => ['required', 'string', 'max:10000'],
            'prioridad' => ['required', Rule::in(['Alta', 'Media', 'Baja'])],
        ]);
        $solicitud = SolicitudCambio::create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('solicitudes-cambio.show', $solicitud)->with('success', 'Solicitud '.$solicitud->numero.' registrada correctamente.');
    }

    public function show(Request $request, SolicitudCambio $solicitud)
    {
        abort_unless($request->user()->can('solicitudes-cambio.index'), 403);
        abort_unless((int) $solicitud->user_id === (int) $request->user()->id || $request->user()->can('feature.solicitudes-cambio.manage'), 403);

        return view('solicitudes_cambio.show', compact('solicitud'));
    }
}

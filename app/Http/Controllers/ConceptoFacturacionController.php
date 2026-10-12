<?php

namespace App\Http\Controllers;

use App\Models\ConceptoFacturacion;
use App\Models\TarifarioReferenciaManual;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'versionManualActual' => Schema::hasTable('tarifario_referencias_manuales')
                ? TarifarioReferenciaManual::actual()
                : null,
            'versionesManuales' => Schema::hasTable('tarifario_referencias_manuales')
                ? TarifarioReferenciaManual::query()->orderByDesc('vigente_desde')->orderByDesc('id')->limit(20)->get()
                : collect(),
        ]);
    }

    public function storeVersionManual(Request $request)
    {
        abort_unless(Schema::hasTable('tarifario_referencias_manuales'), 503, 'Debe aplicarse la migración de versiones manuales antes de publicar una versión.');

        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('tarifario_referencias_manuales', 'codigo')],
            'nombre' => ['required', 'string', 'max:180'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'codigo' => 'código de versión',
            'nombre' => 'nombre de versión',
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $ahora = now();

            TarifarioReferenciaManual::query()
                ->where('estado', 'VIGENTE')
                ->lockForUpdate()
                ->get()
                ->each(function (TarifarioReferenciaManual $version) use ($ahora): void {
                    $version->update([
                        'estado' => 'HISTORICA',
                        'vigente_hasta' => $ahora,
                    ]);
                });

            TarifarioReferenciaManual::query()->create([
                'codigo' => strtoupper(trim($validated['codigo'])),
                'nombre' => trim($validated['nombre']),
                'estado' => 'VIGENTE',
                'observacion' => trim((string) ($validated['observacion'] ?? '')) ?: null,
                'publicada_por' => $request->user()?->id,
                'publicada_en' => $ahora,
                'vigente_desde' => $ahora,
                'vigente_hasta' => null,
            ]);
        });

        return redirect()
            ->route('conceptos-facturacion.index')
            ->with('success', 'Versión de referencia publicada. El flujo actual de precios no se modificó.');
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

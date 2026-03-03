<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class BusquedaController extends Controller
{
    public function trackingDemo(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
        ]);

        $codigo = trim((string) $validated['codigo']);
        $rows = $this->obtenerEventosPorCodigo($codigo);

        if ($rows->isEmpty()) {
            return redirect('/')
                ->with('tracking_error', 'Paquete no encontrado')
                ->with('tracking_codigo', $codigo);
        }

        return view('tracking-demo', [
            'codigo' => strtoupper($codigo),
            'eventos' => $rows,
            'ultimoEvento' => $rows->first(),
        ]);
    }

    public function emsEventos(Request $request): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
        ]);

        $codigo = trim((string) $validated['codigo']);
        $rows = $this->obtenerEventosPorCodigo($codigo);

        if ($rows->isEmpty()) {
            return response()->json([
                'tipo' => 'tracking_eventos',
                'filtro' => ['codigo' => $codigo],
                'existe_paquete' => false,
                'total_registros' => 0,
                'resultado' => [],
                'message' => 'No existe dicho paquete',
            ], 404);
        }

        $agrupado = $rows
            ->groupBy('codigo')
            ->map(function ($eventos, $codigoAgrupado) {
                return [
                    'codigo' => $codigoAgrupado,
                    'total_eventos' => $eventos->count(),
                    'eventos' => $eventos->values(),
                ];
            })
            ->values();

        return response()->json([
            'tipo' => 'tracking_eventos',
            'filtro' => ['codigo' => $codigo],
            'existe_paquete' => true,
            'total_registros' => $rows->count(),
            'resultado' => $agrupado,
        ]);
    }

    private function obtenerEventosPorCodigo(string $codigo): Collection
    {
        $fuentes = [
            ['tabla' => 'eventos_ems', 'servicio' => 'EMS'],
            ['tabla' => 'eventos_certi', 'servicio' => 'CERTI'],
            ['tabla' => 'eventos_contrato', 'servicio' => 'CONTRATO'],
            ['tabla' => 'eventos_ordi', 'servicio' => 'ORDI'],
        ];

        $queries = collect($fuentes)
            ->filter(fn (array $fuente) => Schema::hasTable($fuente['tabla']))
            ->map(function (array $fuente) use ($codigo) {
                return DB::table($fuente['tabla'] . ' as ee')
                    ->leftJoin('eventos as e', 'e.id', '=', 'ee.evento_id')
                    ->whereRaw('TRIM(UPPER(ee.codigo)) = TRIM(UPPER(?))', [$codigo])
                    ->select([
                        'ee.id',
                        'ee.codigo',
                        'ee.evento_id',
                        'ee.user_id',
                        'ee.created_at',
                        'ee.updated_at',
                        'e.nombre_evento',
                        DB::raw("'" . $fuente['servicio'] . "' as servicio"),
                        DB::raw("'" . $fuente['tabla'] . "' as tabla_origen"),
                    ]);
            })
            ->values();

        if ($queries->isEmpty()) {
            return collect();
        }

        $union = $queries->first();
        foreach ($queries->slice(1) as $query) {
            $union->unionAll($query);
        }

        return DB::query()
            ->fromSub($union, 'tracking')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}

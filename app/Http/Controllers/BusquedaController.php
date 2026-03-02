<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                'tipo' => 'ems_eventos',
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
            'tipo' => 'ems_eventos',
            'filtro' => ['codigo' => $codigo],
            'existe_paquete' => true,
            'total_registros' => $rows->count(),
            'resultado' => $agrupado,
        ]);
    }

    private function obtenerEventosPorCodigo(string $codigo): Collection
    {
        return DB::table('eventos_ems as ee')
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
            ])
            ->orderByDesc('ee.created_at')
            ->orderByDesc('ee.id')
            ->get();
    }
}

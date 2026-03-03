<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        try {
            $eventosApi = $this->obtenerEventosDesdeApiSqlServer($codigo);
            if ($eventosApi->isNotEmpty()) {
                return $eventosApi;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->obtenerEventosPorCodigoLocal($codigo);
    }

    private function obtenerEventosDesdeApiSqlServer(string $codigo): Collection
    {
        $baseUrl = trim((string) config('services.tracking_sqlserver.base_url', ''));
        $token = trim((string) config('services.tracking_sqlserver.token', ''));

        if ($baseUrl === '' || $token === '') {
            return collect();
        }

        $response = Http::timeout(10)
            ->acceptJson()
            ->withToken($token)
            ->get($baseUrl, ['codigo' => $codigo]);

        if (!$response->ok()) {
            throw new \RuntimeException('Error consultando API externa de tracking. HTTP ' . $response->status());
        }

        $payload = $response->json();
        $eventosLocales = collect(data_get($payload, 'eventos_locales', []));
        $eventosExternos = collect(data_get($payload, 'eventos_externos', []));

        return $eventosLocales
            ->merge($eventosExternos)
            ->values()
            ->map(function ($item, $index) use ($codigo) {
                $evento = is_array($item) ? $item : (array) $item;
                $codigoExterno = trim((string) ($evento['mailitM_FID'] ?? ''));
                $createdAt = (string) (
                    $evento['created_at']
                    ?? $evento['eventDate']
                    ?? $evento['fecha_hora']
                    ?? $evento['fecha_registro']
                    ?? $evento['fecha']
                    ?? now()->toDateTimeString()
                );

                $timestamp = strtotime($createdAt);

                return (object) [
                    'id' => $evento['id'] ?? null,
                    'codigo' => (string) ($evento['codigo'] ?? ($codigoExterno !== '' ? $codigoExterno : ($payload['codigo'] ?? $codigo))),
                    'evento_id' => $evento['evento_id'] ?? $evento['id_evento'] ?? null,
                    'user_id' => $evento['user_id'] ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $evento['updated_at'] ?? $createdAt,
                    'nombre_evento' => $evento['nombre_evento']
                        ?? $evento['eventType']
                        ?? $evento['evento']
                        ?? $evento['descripcion_evento']
                        ?? $evento['descripcion']
                        ?? 'Evento de seguimiento',
                    'servicio' => strtoupper((string) ($evento['servicio'] ?? $evento['tipo_servicio'] ?? 'TRACKING')),
                    'tabla_origen' => $evento['tabla_origen'] ?? 'api_sqlserver',
                    '_sort_ts' => $timestamp !== false ? $timestamp : (PHP_INT_MAX - (int) $index),
                ];
            })
            ->sortByDesc('_sort_ts')
            ->map(function (object $evento) {
                unset($evento->_sort_ts);
                return $evento;
            })
            ->values();
    }

    private function obtenerEventosPorCodigoLocal(string $codigo): Collection
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

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
                $office = trim((string) ($evento['office'] ?? ''));
                $nextOffice = trim((string) ($evento['nextOffice'] ?? ''));
                $nombreEvento = $evento['nombre_evento']
                    ?? $evento['eventType']
                    ?? $evento['evento']
                    ?? $evento['descripcion_evento']
                    ?? $evento['descripcion']
                    ?? 'Evento de seguimiento';

                $createdAt = (string) (
                    $evento['created_at']
                    ?? $evento['eventDate']
                    ?? $evento['fecha_hora']
                    ?? $evento['fecha_registro']
                    ?? $evento['fecha']
                    ?? now()->toDateTimeString()
                );

                $timestamp = strtotime($createdAt);
                $nombreNormalizado = $this->normalizarNombreEvento((string) $nombreEvento);

                return (object) [
                    'id' => $evento['id'] ?? null,
                    'codigo' => (string) ($evento['codigo'] ?? ($codigoExterno !== '' ? $codigoExterno : ($payload['codigo'] ?? $codigo))),
                    'evento_id' => $evento['evento_id'] ?? $evento['id_evento'] ?? null,
                    'user_id' => $evento['user_id'] ?? null,
                    'created_at' => $createdAt,
                    'updated_at' => $evento['updated_at'] ?? $createdAt,
                    'nombre_evento' => $nombreNormalizado,
                    'servicio' => strtoupper((string) ($evento['servicio'] ?? $evento['tipo_servicio'] ?? 'TRACKING')),
                    'tabla_origen' => $evento['tabla_origen'] ?? 'api_sqlserver',
                    'office' => $office,
                    'next_office' => $nextOffice,
                    'pais_origen' => 'BOLIVIA',
                    'condition' => trim((string) ($evento['condition'] ?? '')),
                    '_sort_ts' => $timestamp !== false ? $timestamp : (PHP_INT_MAX - (int) $index),
                    '_sort_priority' => $this->obtenerPrioridadEvento($nombreNormalizado),
                ];
            })
            ->sort(function ($a, $b) {
                $ts = ((int) ($b->_sort_ts ?? 0)) <=> ((int) ($a->_sort_ts ?? 0));
                if ($ts !== 0) {
                    return $ts;
                }

                $priority = ((int) ($b->_sort_priority ?? 0)) <=> ((int) ($a->_sort_priority ?? 0));
                if ($priority !== 0) {
                    return $priority;
                }

                return 0;
            })
            ->map(function (object $evento) {
                unset($evento->_sort_ts);
                unset($evento->_sort_priority);
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

    private function normalizarNombreEvento(string $nombreEvento): string
    {
        $nombre = trim($nombreEvento);
        if ($nombre === '') {
            return 'Evento de seguimiento';
        }

        $reemplazos = [
            'oficina origen de tránsito' => 'oficina de tránsito',
            'oficina destino de tránsito' => 'oficina de tránsito',
        ];

        return str_ireplace(array_keys($reemplazos), array_values($reemplazos), $nombre);
    }

    private function obtenerPrioridadEvento(string $nombreEvento): int
    {
        $texto = mb_strtolower($nombreEvento);

        return match (true) {
            str_contains($texto, 'entregado exitosamente') => 100,
            str_contains($texto, 'intento fallido') => 90,
            str_contains($texto, 'listo para entregar') => 80,
            str_contains($texto, 'devoluci') => 70,
            str_contains($texto, 'camino a ubicaci') => 60,
            str_contains($texto, 'oficina de tr') => 50,
            str_contains($texto, 'aduana del paquete registrada') => 40,
            str_contains($texto, 'send item to customs') => 30,
            str_contains($texto, 'saca de env') => 20,
            str_contains($texto, 'recibido del cliente') => 10,
            default => 0,
        };
    }
}

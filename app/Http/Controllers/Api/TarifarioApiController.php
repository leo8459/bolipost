<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarifario;
use App\Models\TarifarioTiktoker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TarifarioApiController extends Controller
{
    public function emsNacional(): JsonResponse
    {
        $tarifarios = Tarifario::query()
            ->with(['servicio', 'peso', 'destino', 'origen'])
            ->whereHas('servicio', function ($query): void {
                $query->whereRaw('UPPER(nombre_servicio) = ?', ['EMS_NACIONAL']);
            })
            ->orderBy('id')
            ->get();

        return $this->response('TARIFARIO EMS NACIONAL', 'tarifario', $tarifarios);
    }

    public function deliveryExpress(): JsonResponse
    {
        $tarifarios = TarifarioTiktoker::query()
            ->with(['origen', 'destino', 'servicioExtra'])
            ->orderBy('id')
            ->get();

        return $this->response('TARIFARIO DELIVERY EXPRESS', 'tarifario_tiktoker', $tarifarios);
    }

    private function response(string $name, string $table, Collection $tarifarios): JsonResponse
    {
        return response()->json([
            'nombre_api' => $name,
            'tabla' => $table,
            'total_registros' => $tarifarios->count(),
            'relaciones_incluidas' => collect(array_keys($tarifarios->first()?->getRelations() ?? []))
                ->map(fn (string $relation): string => Str::snake($relation))
                ->values()
                ->all(),
            'data' => $tarifarios,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarifario;
use App\Models\TarifarioTiktoker;
use App\Support\TiktokerTariffFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->get()
            ->each(function (Tarifario $tarifario): void {
                $tarifario->setAttribute('origen_nombre', $tarifario->origen?->nombre_origen);
                $tarifario->setAttribute('destino_nombre', $tarifario->destino?->nombre_destino);
            });

        return $this->response('TARIFARIO EMS NACIONAL', 'tarifario', $tarifarios);
    }

    public function deliveryExpress(Request $request): JsonResponse
    {
        $priceRule = ['nullable', 'string', 'regex:/^\d{1,10}(?:[.,]\d{1,2})?$/D'];
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'origen_id' => ['nullable', 'integer', 'min:0'],
            'destino_id' => ['nullable', 'integer', 'min:0'],
            'precio_peso1' => $priceRule,
            'precio_peso2' => $priceRule,
            'precio_peso3' => $priceRule,
            'precio_peso_extra' => $priceRule,
        ]);

        $tarifarios = TiktokerTariffFilters::apply(
            TarifarioTiktoker::query()->with(['origen', 'destino', 'servicioExtra']),
            TiktokerTariffFilters::fromQuery($filters),
        )
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalApiToken;
use App\Models\User;
use App\Services\ContratoPickupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ContratoPickupApiController extends Controller
{
    public function store(Request $request, ContratoPickupService $pickupService): JsonResponse
    {
        $data = $request->validate([
            'envios' => ['required', 'array', 'min:1', 'max:100'],
            'envios.*.codigo' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'envios.*.peso' => ['nullable', 'numeric', 'min:0.001', 'max:'.ContratoPickupService::PESO_MAXIMO_KG],
        ], [
            'envios.required' => 'Debe enviar al menos un envio con su codigo.',
            'envios.*.peso.numeric' => 'El peso de cada paquete debe ser numerico.',
            'envios.*.peso.min' => 'El peso minimo permitido es 0,001 kg.',
            'envios.*.peso.max' => 'El peso maximo permitido es 150,000 kg.',
        ]);

        /** @var ExternalApiToken|null $apiToken */
        $apiToken = $request->attributes->get('external_api_token');
        $actor = $apiToken?->user_id
            ? User::query()->find($apiToken->user_id)
            : null;

        if (! $actor) {
            return response()->json([
                'message' => 'La credencial API no esta asociada a un usuario activo.',
            ], 403);
        }

        $envios = collect($data['envios']);
        $codigos = $envios
            ->pluck('codigo')
            ->map(fn ($codigo) => trim((string) $codigo))
            ->values()
            ->all();
        $pesosPorCodigo = $envios
            ->mapWithKeys(fn ($envio) => [
                strtoupper(trim((string) $envio['codigo'])) => isset($envio['peso'])
                    ? round((float) $envio['peso'], 3)
                    : null,
            ])
            ->all();

        try {
            $resultado = $pickupService->recogerPorCodigosConPesos($actor, $codigos, $pesosPorCodigo);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => $resultado['actualizados'] > 0
                ? $resultado['actualizados'].' envio(s) enviado(s) a ALMACEN.'
                : 'No se actualizo ningun envio. Verifica codigo, estado y ciudad.',
            'actualizados' => $resultado['actualizados'],
            'actualizados_por_tipo' => $resultado['actualizados_por_tipo'],
            'codigos' => $resultado['codigos'],
            'no_procesados' => $resultado['no_procesados'],
        ]);
    }
}

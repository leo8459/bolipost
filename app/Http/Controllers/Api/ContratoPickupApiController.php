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
            'codigos' => ['required', 'array', 'min:1', 'max:100'],
            'codigos.*' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
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

        try {
            $resultado = $pickupService->recogerPorCodigos($actor, $data['codigos']);
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
            'codigos' => $resultado['codigos'],
            'no_procesados' => $resultado['no_procesados'],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SolicitudCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalClienteSolicitudApiController extends Controller
{
    public function index(Request $request, Cliente $cliente): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $solicitudes = SolicitudCliente::query()
            ->with(['estadoRegistro:id,nombre_estado', 'servicioExtra:id,nombre,descripcion', 'destino:id,nombre_destino'])
            ->where('cliente_id', $cliente->id)
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 15));

        return response()->json([
            'cliente' => [
                'id' => $cliente->id,
                'codigo_cliente' => $cliente->codigo_cliente,
                'name' => $cliente->name,
                'email' => $cliente->email,
            ],
            'solicitudes' => $solicitudes,
        ]);
    }

    public function store(
        Request $request,
        Cliente $cliente,
        ClienteSolicitudApiController $controller
    ): JsonResponse {
        return $controller->storeForCliente($request, $cliente);
    }
}

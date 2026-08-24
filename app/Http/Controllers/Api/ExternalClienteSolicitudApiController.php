<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\SolicitudCliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalClienteSolicitudApiController extends Controller
{
    public function globalIndex(Request $request): JsonResponse
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'cliente_id' => ['nullable', 'integer', 'exists:clientes,id'],
            'estado_id' => ['nullable', 'integer', 'exists:estados,id'],
            'q' => ['nullable', 'string', 'max:255'],
            'fecha_desde' => ['nullable', 'date'],
            'fecha_hasta' => ['nullable', 'date', 'after_or_equal:fecha_desde'],
        ]);

        $search = trim((string) ($data['q'] ?? ''));
        $solicitudes = SolicitudCliente::query()
            ->with([
                'cliente:id,codigo_cliente,name,email,telefono',
                'estadoRegistro:id,nombre_estado',
                'servicioExtra:id,nombre,descripcion',
                'destino:id,nombre_destino',
            ])
            ->when(isset($data['cliente_id']), fn ($query) => $query->where('cliente_id', (int) $data['cliente_id']))
            ->when(isset($data['estado_id']), fn ($query) => $query->where('estado_id', (int) $data['estado_id']))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('codigo_solicitud', 'ILIKE', "%{$search}%")
                        ->orWhere('barcode', 'ILIKE', "%{$search}%")
                        ->orWhere('nombre_remitente', 'ILIKE', "%{$search}%")
                        ->orWhere('nombre_destinatario', 'ILIKE', "%{$search}%")
                        ->orWhereHas('cliente', function ($clienteQuery) use ($search): void {
                            $clienteQuery->where('name', 'ILIKE', "%{$search}%")
                                ->orWhere('email', 'ILIKE', "%{$search}%")
                                ->orWhere('codigo_cliente', 'ILIKE', "%{$search}%");
                        });
                });
            })
            ->when(isset($data['fecha_desde']), fn ($query) => $query->whereDate('created_at', '>=', $data['fecha_desde']))
            ->when(isset($data['fecha_hasta']), fn ($query) => $query->whereDate('created_at', '<=', $data['fecha_hasta']))
            ->latest()
            ->paginate((int) ($data['per_page'] ?? 25));

        return response()->json([
            'message' => 'Solicitudes de clientes obtenidas correctamente.',
            'solicitudes' => $solicitudes,
        ]);
    }

    public function globalStore(
        Request $request,
        ClienteSolicitudApiController $controller
    ): JsonResponse {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
        ]);

        $cliente = Cliente::query()->findOrFail((int) $data['cliente_id']);

        return $controller->storeForCliente($request, $cliente);
    }

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

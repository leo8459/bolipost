<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Destino;
use App\Models\Estado;
use App\Models\Origen;
use App\Models\ServicioExtra;
use App\Models\SolicitudCliente;
use App\Models\TarifarioTiktoker;
use App\Support\SolicitudCode;
use App\Support\TiktokerEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClienteSolicitudApiController extends Controller
{
    private const DIRECCION_VENTANILLA = 'CORREOS DE BOLIVIA';

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $solicitudes = SolicitudCliente::query()
            ->with(['estadoRegistro:id,nombre_estado', 'servicioExtra:id,nombre,descripcion', 'destino:id,nombre_destino'])
            ->where('cliente_id', $this->cliente($request)->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($solicitudes);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->storeForCliente($request, $this->cliente($request));
    }

    public function storeForCliente(Request $request, Cliente $cliente): JsonResponse
    {
        $servicioExtraIds = TarifarioTiktoker::query()
            ->whereNotNull('servicio_extra_id')
            ->distinct()
            ->pluck('servicio_extra_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $data = $request->validate([
            'servicio_extra_id' => ['required', 'integer', 'in:'.implode(',', $servicioExtraIds)],
            'origen' => ['required', 'string', 'max:255'],
            'destino_id' => ['required', 'integer', 'exists:destino,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'contenido' => ['required', 'string'],
            'nombre_remitente' => ['required', 'string', 'max:255'],
            'carnet' => ['required', 'string', 'max:255'],
            'telefono_remitente' => ['nullable', 'string', 'max:50'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['nullable', 'string', 'max:50'],
            'direccion_recojo' => ['required', 'string', 'max:255'],
            'direccion_entrega' => ['required', 'string', 'max:255'],
        ]);

        try {
            $solicitud = DB::transaction(function () use ($data, $cliente): SolicitudCliente {
                $destino = Destino::query()->findOrFail((int) $data['destino_id']);
                $servicioExtra = ServicioExtra::query()->findOrFail((int) $data['servicio_extra_id']);
                $origen = $this->upper($data['origen']);
                $origenId = (int) (Origen::query()
                    ->whereRaw('trim(upper(nombre_origen)) = ?', [$origen])
                    ->value('id') ?? 0);
                $tarifario = TarifarioTiktoker::query()
                    ->where('origen_id', $origenId)
                    ->where('destino_id', (int) $data['destino_id'])
                    ->where('servicio_extra_id', (int) $data['servicio_extra_id'])
                    ->first();
                $estadoId = (int) (Estado::query()
                    ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
                    ->value('id') ?? 0);

                if ($origenId <= 0 || ! $tarifario) {
                    throw new \RuntimeException('No existe un tarifario Delivery Express para el servicio, origen y destino seleccionados.');
                }

                if ($estadoId <= 0) {
                    throw new \RuntimeException('No existe el estado SOLICITUD.');
                }

                $solicitud = SolicitudCliente::query()->create([
                    'cliente_id' => $cliente->id,
                    'estado_id' => $estadoId,
                    'servicio_extra_id' => (int) $data['servicio_extra_id'],
                    'origen' => $origen,
                    'contenido' => trim((string) $data['contenido']),
                    'cantidad' => (int) $data['cantidad'],
                    'precio' => (float) $tarifario->peso1,
                    'nombre_remitente' => $this->upper($data['nombre_remitente']),
                    'carnet' => trim((string) $data['carnet']),
                    'telefono_remitente' => $this->nullableTrim($data['telefono_remitente'] ?? null),
                    'nombre_destinatario' => $this->upper($data['nombre_destinatario']),
                    'telefono_destinatario' => $this->nullableTrim($data['telefono_destinatario'] ?? null),
                    'direccion_recojo' => trim((string) $data['direccion_recojo']),
                    'direccion' => $this->isPuertaAVentanilla($servicioExtra)
                        ? self::DIRECCION_VENTANILLA
                        : trim((string) $data['direccion_entrega']),
                    'ciudad' => $this->upper((string) $destino->nombre_destino),
                    'destino_id' => (int) $data['destino_id'],
                    'tarifario_tiktoker_id' => (int) $tarifario->id,
                ]);

                $codigo = SolicitudCode::make((int) $solicitud->id, $solicitud->origen);
                $solicitud->update(['codigo_solicitud' => $codigo, 'barcode' => $codigo]);

                $eventoId = TiktokerEvent::resolveId(TiktokerEvent::SOLICITUD_REGISTRADA);
                DB::table('eventos_tiktoker')->insert([
                    'codigo' => $codigo,
                    'evento_id' => $eventoId,
                    'user_id' => null,
                    'cliente_id' => $cliente->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return $solicitud;
            });
        } catch (\RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $solicitud->load(['estadoRegistro:id,nombre_estado', 'servicioExtra:id,nombre,descripcion', 'destino:id,nombre_destino']);

        return response()->json([
            'message' => 'Solicitud registrada correctamente.',
            'solicitud' => $solicitud,
        ], 201);
    }

    private function cliente(Request $request): Cliente
    {
        $cliente = $request->user();
        abort_unless($cliente instanceof Cliente, 403, 'El token no pertenece a un cliente.');

        return $cliente;
    }

    private function upper(string $value): string
    {
        return mb_strtoupper(trim($value));
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function isPuertaAVentanilla(ServicioExtra $servicio): bool
    {
        $text = mb_strtolower(trim($servicio->nombre.' '.$servicio->descripcion));
        $text = strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return str_contains($text, 'puerta a ventanilla');
    }
}

<?php

namespace App\Http\Controllers;

use App\Mail\SolicitudClienteCreadaMail;
use App\Models\Destino;
use App\Models\ServicioExtra;
use App\Models\SolicitudCliente;
use App\Models\TarifarioTiktoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ClienteSolicitudController extends Controller
{
    private const ESTADO_PENDIENTE = 'PENDIENTE';
    private const CIUDADES_BOLIVIA = [
        'LA PAZ',
        'SANTA CRUZ',
        'PANDO',
        'BENI',
        'TARIJA',
        'CHUQUISACA',
        'ORURO',
        'COCHABAMBA',
        'POTOSI',
    ];

    public function create(): View
    {
        $cliente = Auth::guard('cliente')->user();

        return view('clientes.solicitudes', [
            'cliente' => $cliente,
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(),
            'servicioExtras' => ServicioExtra::query()
                ->whereIn('nombre', ['serviciotiktokero', 'serviciotiktokeroventanilla'])
                ->orderBy('id')
                ->get(['id', 'nombre', 'descripcion']),
            'ciudades' => self::CIUDADES_BOLIVIA,
        ]);
    }

    public function history(): View
    {
        $cliente = Auth::guard('cliente')->user();

        $solicitudes = SolicitudCliente::query()
            ->with([
                'servicioExtra:id,nombre,descripcion',
                'destino:id,nombre_destino',
                'tarifarioTiktoker:id,origen_id,destino_id,servicio_extra_id,peso1,peso2,peso3,peso_extra,tiempo_entrega',
                'tarifarioTiktoker.origen:id,nombre_origen',
                'tarifarioTiktoker.destino:id,nombre_destino',
                'tarifarioTiktoker.servicioExtra:id,nombre',
            ])
            ->where('cliente_id', (int) $cliente->id)
            ->latest()
            ->get();

        return view('clientes.mis-solicitudes', [
            'cliente' => $cliente,
            'solicitudes' => $solicitudes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cliente = Auth::guard('cliente')->user();

        $data = $request->validate([
            'servicio_extra_id' => ['required', 'integer', 'exists:servicio_extras,id'],
            'origen' => ['required', 'string'],
            'tipo_correspondencia' => ['nullable', 'string', 'max:255'],
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

        $destino = Destino::query()->findOrFail((int) $data['destino_id']);

        $solicitud = SolicitudCliente::query()->create([
            'cliente_id' => (int) $cliente->id,
            'estado' => self::ESTADO_PENDIENTE,
            'servicio_extra_id' => (int) $data['servicio_extra_id'],
            'origen' => $this->upper($data['origen']),
            'tipo_correspondencia' => $this->nullableUpper($data['tipo_correspondencia'] ?? null),
            'servicio_especial' => null,
            'contenido' => trim((string) $data['contenido']),
            'cantidad' => (int) $data['cantidad'],
            'peso' => null,
            'nombre_remitente' => $this->upper($data['nombre_remitente']),
            'nombre_envia' => null,
            'carnet' => trim((string) $data['carnet']),
            'telefono_remitente' => $this->nullableTrim($data['telefono_remitente'] ?? null),
            'nombre_destinatario' => $this->upper($data['nombre_destinatario']),
            'telefono_destinatario' => $this->nullableTrim($data['telefono_destinatario'] ?? null),
            'direccion_recojo' => trim((string) $data['direccion_recojo']),
            'direccion' => trim((string) $data['direccion_entrega']),
            'ciudad' => $this->upper((string) $destino->nombre_destino),
            'servicio_id' => null,
            'destino_id' => (int) $data['destino_id'],
            'tarifario_tiktoker_id' => null,
        ]);

        $solicitud->update([
            'codigo_solicitud' => $this->generateSolicitudCode((int) $solicitud->id),
        ]);

        $message = 'Solicitud registrada correctamente con codigo ' . $solicitud->codigo_solicitud . '.';

        try {
            Mail::to($cliente->email)->send(new SolicitudClienteCreadaMail($solicitud, $cliente));
        } catch (\Throwable $exception) {
            Log::error('No se pudo enviar el correo de solicitud al cliente.', [
                'solicitud_id' => $solicitud->id,
                'cliente_id' => $cliente->id,
                'cliente_email' => $cliente->email,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('clientes.solicitudes.index')
                ->with('success', $message)
                ->with('warning', 'La solicitud se registro, pero no se pudo enviar el correo automatico. Revisa la configuracion SMTP.');
        }

        return redirect()
            ->route('clientes.solicitudes.index')
            ->with('success', $message);
    }

    private function generateSolicitudCode(int $id): string
    {
        return 'SOL' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
    }

    private function upper(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function nullableUpper(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : strtoupper($text);
    }

    private function nullableTrim(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}

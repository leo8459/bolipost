<?php

namespace App\Http\Controllers;

use App\Mail\SolicitudClienteCreadaMail;
use App\Models\Destino;
use App\Models\Estado;
use App\Models\ServicioExtra;
use App\Models\SolicitudCliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ClienteSolicitudController extends Controller
{
    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE = 295;

    private const CIUDADES_BOLIVIA = [
        'LA PAZ',
        'SANTA CRUZ',
        'COBIJA',
        'TRINIDAD',
        'TARIJA',
        'SUCRE',
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
                'estadoRegistro:id,nombre_estado',
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
            $destino = Destino::query()->findOrFail((int) $data['destino_id']);
            $estadoSolicitudId = $this->resolveSolicitudEstadoId();
        } catch (\RuntimeException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['estado' => $exception->getMessage()]);
        }

        $solicitud = SolicitudCliente::query()->create([
            'cliente_id' => (int) $cliente->id,
            'estado_id' => $estadoSolicitudId,
            'servicio_extra_id' => (int) $data['servicio_extra_id'],
            'origen' => $this->upper($data['origen']),
            'tipo_correspondencia' => null,
            'servicio_especial' => null,
            'contenido' => trim((string) $data['contenido']),
            'cantidad' => (int) $data['cantidad'],
            'peso' => null,
            'precio' => null,
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

        $codigoSolicitud = $this->generateSolicitudCode((int) $solicitud->id);

        $solicitud->update([
            'codigo_solicitud' => $codigoSolicitud,
            'barcode' => $codigoSolicitud,
        ]);

        $this->registrarEventoTiktokerCreacionCliente($solicitud, (int) $cliente->id);

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

    private function resolveSolicitudEstadoId(): int
    {
        $estadoId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id') ?? 0);

        if ($estadoId <= 0) {
            throw new \RuntimeException('No existe el estado SOLICITUD en la tabla estados.');
        }

        return $estadoId;
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

    private function registrarEventoTiktokerCreacionCliente(SolicitudCliente $solicitud, int $clienteId): void
    {
        $codigo = trim((string) ($solicitud->codigo_solicitud ?: $solicitud->barcode));

        if ($codigo === '' || $clienteId <= 0) {
            return;
        }

        DB::table('eventos_tiktoker')->insert([
            'codigo' => $codigo,
            'evento_id' => self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE,
            'user_id' => null,
            'cliente_id' => $clienteId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}


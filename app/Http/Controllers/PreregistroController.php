<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Destino;
use App\Models\Estado;
use App\Models\PaqueteEms;
use App\Models\PaqueteEmsFormulario;
use App\Models\Preregistro;
use App\Models\RemitenteEms;
use App\Models\Servicio;
use App\Models\Tarifario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PreregistroController extends Controller
{
    private const ESTADO_PENDIENTE = 'PENDIENTE';
    private const ESTADO_VALIDADO = 'VALIDADO';
    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE = 295;
    private const TELEFONO_DESTINATARIO_RECARGO = 1.00;
    private const EMS_CODE_SERVICE_NAMES = [
        'EMS',
        'EMS_NACIONAL',
        'SUPER_EXPRESS_NACIONAL',
        'EMS_LOCAL_COBERTURA_1',
        'EMS_LOCAL_COBERTURA_2',
        'EMS_LOCAL_COBERTURA_3',
        'EMS_LOCAL_COBERTURA_4',
        'CIUDADES_INTERMEDIAS',
        'TRINIDAD_COBIJA',
        'CIUDADES_INTERMEDIAS_TRINIDAD_COBIJA',
    ];
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

    public function publicCreate(): View
    {
        return view('preregistros.public-create', [
            'servicios' => Servicio::query()->orderBy('nombre_servicio')->get(),
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(),
            'ciudades' => self::CIUDADES_BOLIVIA,
        ]);
    }

    public function publicStore(Request $request): RedirectResponse
    {
        try {
            $preregistro = $this->createPreregistroFromRequest($request);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['general' => $exception->getMessage()]);
        }

        return redirect()
            ->back()
            ->with('success', 'Preregistro enviado correctamente. Tu codigo generado es ' . $preregistro->codigo_generado . '.')
            ->with('preregistro_id', $preregistro->id)
            ->with('preregistro_codigo', $preregistro->codigo_generado)
            ->with('preregistro_codigo_numerico', $this->extractPreregistroNumber($preregistro->codigo_generado))
            ->with('preregistro_ticket_url', route('preregistros.public.ticket', $preregistro, false));
    }

    public function publicStoreApi(Request $request): JsonResponse
    {
        $request->headers->set('Accept', 'application/json');
        try {
            $preregistro = $this->createPreregistroFromRequest($request);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => 'No se pudo guardar el preregistro.',
                'errors' => [
                    'general' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'PREREGISTRO_GUARDADO',
            'data' => [
                'id' => $preregistro->id,
                'codigo_preregistro' => $preregistro->codigo_generado,
                'codigo_preregistro_numerico' => $this->extractPreregistroNumber($preregistro->codigo_generado),
                'ticket_url' => route('preregistros.public.ticket', $preregistro, false),
                'estado' => $preregistro->estado,
                'precio' => $preregistro->precio,
            ],
        ], 201);
    }

    public function ticket(Preregistro $preregistro)
    {
        $pdf = Pdf::loadView('preregistros.ticket', [
            'preregistro' => $preregistro,
        ])->setPaper('A6', 'portrait');

        return $pdf->download('preregistro-' . $preregistro->codigo_generado . '.pdf');
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $estado = strtoupper(trim((string) $request->query('estado', self::ESTADO_PENDIENTE)));

        $preregistros = Preregistro::query()
            ->with(['servicio:id,nombre_servicio', 'destino:id,nombre_destino', 'validador:id,name', 'paqueteEms:id,codigo'])
            ->when($estado !== '', function ($query) use ($estado) {
                $query->whereRaw('trim(upper(estado)) = ?', [$estado]);
            })
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->whereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$q}%"])
                        ->orWhere('nombre_remitente', 'ILIKE', "%{$q}%")
                        ->orWhere('nombre_destinatario', 'ILIKE', "%{$q}%")
                        ->orWhere('carnet', 'ILIKE', "%{$q}%")
                        ->orWhere('telefono_remitente', 'ILIKE', "%{$q}%")
                        ->orWhere('telefono_destinatario', 'ILIKE', "%{$q}%")
                        ->orWhere('ciudad', 'ILIKE', "%{$q}%")
                        ->orWhere('codigo_preregistro', 'ILIKE', "%{$q}%")
                        ->orWhere('codigo_generado', 'ILIKE', "%{$q}%");
                });
            })
            ->orderByRaw("CASE WHEN trim(upper(estado)) = '" . self::ESTADO_PENDIENTE . "' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('preregistros.index', [
            'preregistros' => $preregistros,
            'q' => $q,
            'estado' => $estado,
            'estadosDisponibles' => [self::ESTADO_PENDIENTE, self::ESTADO_VALIDADO],
        ]);
    }

    public function approve(Request $request, Preregistro $preregistro): RedirectResponse
    {
        $user = $request->user();
        abort_if(!$user, 403, 'No autenticado.');

        if (strtoupper(trim((string) $preregistro->estado)) === self::ESTADO_VALIDADO) {
            return redirect()
                ->route('preregistros.index')
                ->with('success', 'El preregistro ya fue validado anteriormente.');
        }

        try {
            $paquete = DB::transaction(function () use ($preregistro, $user) {
                $estadoAdmisionId = $this->findEstadoId('ADMISIONES');
                if (!$estadoAdmisionId) {
                    throw new \RuntimeException('No existe el estado ADMISIONES en la tabla estados.');
                }

                $servicio = Servicio::query()->find($preregistro->servicio_id);
                if (!$servicio) {
                    throw new \RuntimeException('El servicio del preregistro ya no existe.');
                }

                $destino = Destino::query()->find($preregistro->destino_id);
                if (!$destino) {
                    throw new \RuntimeException('El destino del preregistro ya no existe.');
                }

                [$tarifarioId, $precio] = $this->resolveTarifaDesdePreregistro($preregistro, $servicio);
                $codigo = $this->generateCodigo($servicio, false);

                $paquete = PaqueteEms::query()->create([
                    'origen' => $preregistro->origen,
                    'tipo_correspondencia' => $preregistro->tipo_correspondencia,
                    'servicio_especial' => $preregistro->servicio_especial,
                    'contenido' => $preregistro->contenido,
                    'cantidad' => $preregistro->cantidad,
                    'peso' => $preregistro->peso,
                    'codigo' => $codigo,
                    'cod_especial' => null,
                    'precio' => $precio,
                    'nombre_remitente' => $preregistro->nombre_remitente,
                    'nombre_envia' => $preregistro->nombre_envia,
                    'carnet' => $preregistro->carnet,
                    'telefono_remitente' => $preregistro->telefono_remitente,
                    'nombre_destinatario' => $preregistro->nombre_destinatario,
                    'telefono_destinatario' => $preregistro->telefono_destinatario,
                    'direccion' => $preregistro->direccion,
                    'ciudad' => $preregistro->ciudad,
                    'tarifario_id' => $tarifarioId,
                    'estado_id' => $estadoAdmisionId,
                    'user_id' => (int) $user->id,
                ]);

                PaqueteEmsFormulario::query()->create([
                    'paquete_ems_id' => $paquete->id,
                    'origen' => $preregistro->origen,
                    'tipo_correspondencia' => $preregistro->tipo_correspondencia,
                    'servicio_especial' => $preregistro->servicio_especial,
                    'contenido' => $preregistro->contenido,
                    'cantidad' => $preregistro->cantidad,
                    'peso' => $preregistro->peso,
                    'codigo' => $codigo,
                    'precio' => $precio,
                    'nombre_remitente' => $preregistro->nombre_remitente,
                    'nombre_envia' => $preregistro->nombre_envia,
                    'carnet' => $preregistro->carnet,
                    'telefono_remitente' => $preregistro->telefono_remitente,
                    'nombre_destinatario' => $preregistro->nombre_destinatario,
                    'telefono_destinatario' => $preregistro->telefono_destinatario,
                    'direccion' => $preregistro->direccion,
                    'ciudad' => $preregistro->ciudad,
                    'servicio_id' => $preregistro->servicio_id,
                    'destino_id' => $preregistro->destino_id,
                    'tarifario_id' => $tarifarioId,
                ]);

                RemitenteEms::query()->updateOrCreate(
                    ['carnet' => trim((string) $preregistro->carnet)],
                    [
                        'nombre_remitente' => trim((string) $preregistro->nombre_remitente),
                        'telefono_remitente' => trim((string) $preregistro->telefono_remitente),
                        'nombre_envia' => trim((string) $preregistro->nombre_envia),
                    ]
                );

                DB::table('eventos_ems')->insert([
                    'codigo' => $codigo,
                    'evento_id' => self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE,
                    'user_id' => (int) $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $preregistro->update([
                    'estado' => self::ESTADO_VALIDADO,
                    'validado_por' => (int) $user->id,
                    'validado_at' => now(),
                    'paquete_ems_id' => (int) $paquete->id,
                    'codigo_generado' => $codigo,
                    'precio' => $precio,
                ]);

                return $paquete;
            });
        } catch (\RuntimeException $exception) {
            return redirect()
                ->route('preregistros.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('preregistros.index')
            ->with('success', 'Preregistro validado y convertido a EMS con codigo ' . $paquete->codigo . '.');
    }

    private function createPreregistroFromRequest(Request $request): Preregistro
    {
        $validator = Validator::make($request->all(), $this->rules(), [], $this->attributes());
        $data = $validator->validate();

        $servicio = Servicio::query()->findOrFail((int) $data['servicio_id']);
        $destino = Destino::query()->findOrFail((int) $data['destino_id']);

        [$tarifarioId, $precio] = $this->resolveTarifa($servicio, $data);

        $preregistro = Preregistro::query()->create([
            'estado' => self::ESTADO_PENDIENTE,
            'origen' => $this->upper($data['origen']),
            'tipo_correspondencia' => $this->nullableUpper($data['tipo_correspondencia'] ?? null),
            'servicio_especial' => $this->nullableUpper($data['servicio_especial'] ?? null),
            'contenido' => trim((string) $data['contenido']),
            'cantidad' => (int) $data['cantidad'],
            'peso' => round((float) $data['peso'], 3),
            'precio' => $precio,
            'nombre_remitente' => $this->upper($data['nombre_remitente']),
            'nombre_envia' => $this->nullableUpper($data['nombre_envia'] ?? null),
            'carnet' => trim((string) $data['carnet']),
            'telefono_remitente' => $this->nullableTrim($data['telefono_remitente'] ?? null),
            'nombre_destinatario' => $this->upper($data['nombre_destinatario']),
            'telefono_destinatario' => $this->nullableTrim($data['telefono_destinatario'] ?? null),
            'direccion' => trim((string) $data['direccion']),
            'ciudad' => $this->upper((string) $destino->nombre_destino),
            'servicio_id' => (int) $servicio->id,
            'destino_id' => (int) $destino->id,
        ]);

        $codigoPreregistro = $this->generatePreregistroCode((int) $preregistro->id);

        $preregistro->update([
            'codigo_preregistro' => $codigoPreregistro,
            'codigo_generado' => $codigoPreregistro,
        ]);

        return $preregistro->fresh();
    }

    private function rules(): array
    {
        return [
            'origen' => ['required', 'string', Rule::in(self::CIUDADES_BOLIVIA)],
            'servicio_id' => ['required', 'integer', Rule::exists('servicio', 'id')],
            'destino_id' => ['required', 'integer', Rule::exists('destino', 'id')],
            'tipo_correspondencia' => ['nullable', 'string', 'max:255'],
            'servicio_especial' => ['nullable', 'string', 'max:255'],
            'contenido' => ['required', 'string'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'peso' => ['required', 'numeric', 'min:0.001'],
            'nombre_remitente' => ['required', 'string', 'max:255'],
            'nombre_envia' => ['nullable', 'string', 'max:255'],
            'carnet' => ['required', 'string', 'max:255'],
            'telefono_remitente' => ['nullable', 'string', 'max:50'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['nullable', 'string', 'max:50'],
            'direccion' => ['required', 'string', 'max:255'],
        ];
    }

    private function attributes(): array
    {
        return [
            'origen' => 'origen',
            'servicio_id' => 'servicio',
            'destino_id' => 'destino',
            'tipo_correspondencia' => 'tipo de correspondencia',
            'servicio_especial' => 'servicio especial',
            'contenido' => 'contenido',
            'cantidad' => 'cantidad',
            'peso' => 'peso',
            'nombre_remitente' => 'nombre remitente',
            'nombre_envia' => 'nombre envia',
            'carnet' => 'carnet',
            'telefono_remitente' => 'telefono remitente',
            'nombre_destinatario' => 'nombre destinatario',
            'telefono_destinatario' => 'telefono destinatario',
            'direccion' => 'direccion',
        ];
    }

    private function resolveTarifa(Servicio $servicio, array $data): array
    {
        $tipo = strtoupper(trim((string) ($data['tipo_correspondencia'] ?? '')));
        if ($this->isCertificadoShipment($tipo)) {
            return [null, null];
        }

        $peso = (float) $data['peso'];
        $tarifario = Tarifario::query()
            ->with('peso')
            ->where('servicio_id', (int) $servicio->id)
            ->whereHas('peso', function ($query) use ($peso) {
                $query->where('peso_inicial', '<=', $peso)
                    ->where('peso_final', '>=', $peso);
            })
            ->orderBy('id')
            ->first();

        if (!$tarifario) {
            throw new \RuntimeException('No existe tarifario para este servicio y peso.');
        }

        $precio = $this->calculatePrecioFinal(
            (float) $tarifario->precio,
            trim((string) ($data['telefono_destinatario'] ?? '')) !== '',
            trim((string) ($data['servicio_especial'] ?? ''))
        );

        return [(int) $tarifario->id, $precio];
    }

    private function resolveTarifaDesdePreregistro(Preregistro $preregistro, Servicio $servicio): array
    {
        return $this->resolveTarifa($servicio, [
            'tipo_correspondencia' => $preregistro->tipo_correspondencia,
            'peso' => $preregistro->peso,
            'telefono_destinatario' => $preregistro->telefono_destinatario,
            'servicio_especial' => $preregistro->servicio_especial,
        ]);
    }

    private function calculatePrecioFinal(float $basePrice, bool $hasTelefonoDestinatario, string $servicioEspecial = ''): float
    {
        $price = round($basePrice, 2);
        if (strtoupper(trim($servicioEspecial)) === 'IDA Y VUELTA') {
            $price = round($price * 2, 2);
        }

        if ($hasTelefonoDestinatario) {
            $price = round($price + self::TELEFONO_DESTINATARIO_RECARGO, 2);
        }

        return $price;
    }

    private function isCertificadoShipment(string $tipoCorrespondencia): bool
    {
        $tipo = strtoupper(trim($tipoCorrespondencia));

        return $tipo !== '' && (str_contains($tipo, 'OFICIAL') || str_contains($tipo, 'CERTIFIC'));
    }

    private function generateCodigo(Servicio $servicio, bool $isAlmacenEms): string
    {
        $prefix = $this->getCodigoPrefix($servicio, $isAlmacenEms);
        if ($prefix === null) {
            throw new \RuntimeException('No se pudo determinar el prefijo para generar el codigo.');
        }

        $suffix = $isAlmacenEms ? 'BC' : 'BO';
        $last = PaqueteEms::query()
            ->where('codigo', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('codigo');

        $nextNumber = 1;
        if ($last) {
            $num = (int) substr((string) $last, strlen($prefix), 9);
            if ($num > 0) {
                $nextNumber = $num + 1;
            }
        }

        return $prefix . str_pad((string) $nextNumber, 9, '0', STR_PAD_LEFT) . $suffix;
    }

    private function getCodigoPrefix(Servicio $servicio, bool $isAlmacenEms): ?string
    {
        if ($isAlmacenEms) {
            return 'AG';
        }

        $name = strtoupper(trim((string) $servicio->nombre_servicio));
        if (in_array($name, self::EMS_CODE_SERVICE_NAMES, true)) {
            return 'EN';
        }

        if ($name === 'ENCOMIENDA') {
            return 'CP';
        }

        if ($name === 'ECA') {
            return 'EC';
        }

        return null;
    }

    private function findEstadoId(string $nombre): ?int
    {
        $id = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [strtoupper(trim($nombre))])
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function upper(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function nullableUpper($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : strtoupper($text);
    }

    private function nullableTrim($value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function generatePreregistroCode(int $id): string
    {
        return 'PRE' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
    }

    private function extractPreregistroNumber(?string $codigo): string
    {
        $codigo = strtoupper(trim((string) $codigo));

        if (str_starts_with($codigo, 'PRE')) {
            return substr($codigo, 3);
        }

        return $codigo;
    }
}


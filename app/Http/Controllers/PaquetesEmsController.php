<?php

namespace App\Http\Controllers;

use App\Models\CodigoEmpresa;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Origen;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo as RecojoContrato;
use App\Models\Destino;
use App\Models\ServicioExtra;
use App\Models\SolicitudCliente;
use App\Models\TarifarioTiktoker;
use App\Services\FacturacionCartService;
use App\Support\SolicitudCode;
use App\Support\TiktokerEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaquetesEmsController extends Controller
{
    private const EVENTO_ID_CONTRATO_CREADO = 318;
    private const EVENTO_ID_CONTRATO_RECIBIDO = 295;
    private const EVENTO_ID_CONTRATO_RECOGIDO = 295;
    private const EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE = 316;
    private const EVENTO_ID_INTENTO_FALLIDO_ENTREGA = 315;
    private const EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE = 295;
    private const EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO = 297;
    private const EVENTO_ID_CERTI_RECIBIDO_OFICINA_ENTREGA = 172;
    private const DIRECCION_DESTINATARIO_VENTANILLA = 'CORREOS DE BOLIVIA';

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

    private const PROVINCIAS_POR_DEPARTAMENTO = [
        'LA PAZ' => [
            'MURILLO', 'OMASUYOS', 'PACAJES', 'CAMACHO', 'MUNECAS', 'LARECAJA',
            'FRANZ TAMAYO', 'INGAVI', 'LOAYZA', 'INQUISIVI', 'SUD YUNGAS',
            'LOS ANDES', 'AROMA', 'NOR YUNGAS', 'ABEL ITURRALDE',
            'BAUTISTA SAAVEDRA', 'MANCO KAPAC', 'GUALBERTO VILLARROEL',
            'JOSE MANUEL COBIJA', 'CARANAVI',
        ],
        'COCHABAMBA' => [
            'CERCADO', 'CAMPERO', 'AYOPAYA', 'ESTEBAN ARCE', 'ARANI', 'ARQUE',
            'CAPINOTA', 'GERMAN JORDAN', 'QUILLACOLLO', 'CHAPARE', 'TAPACARI',
            'CARRASCO', 'MIZQUE', 'PUNATA', 'BOLIVAR', 'TIRAQUE', 'SACABA',
        ],
        'SANTA CRUZ' => [
            'ANDRES IBANEZ', 'WARNES', 'VALLEGRANDE', 'ICHILO', 'CHIQUITOS',
            'SARA', 'CORDILLERA', 'FLORIDA', 'MANUEL MARIA CABALLERO',
            'GUARAYOS', 'NUFLO DE CHAVEZ', 'VELASCO', 'ANGEL SANDOVAL',
            'MONTERO',
            'GERMAN BUSCH',
        ],
        'ORURO' => [
            'CERCADO', 'CARANGAS', 'SAUCARI', 'SABAYA', 'LADISLAO CABRERA',
            'LITORAL', 'POOPO', 'PANTALEON DALENCE', 'SAJAMA',
            'SAN PEDRO DE TOTORA', 'SEBASTIAN PAGADOR', 'EDUARDO AVAROA',
            'NOR CARANGAS', 'SUR CARANGAS', 'TOMAS BARRON',
        ],
        'POTOSI' => [
            'TOMAS FRIAS', 'RAFAEL BUSTILLO', 'CORNELIO SAAVEDRA', 'CHAYANTA',
            'CHARCAS', 'NOR CHICHAS', 'ALONSO DE IBANEZ', 'SUD CHICHAS',
            'NOR LIPEZ', 'SUD LIPEZ', 'JOSE MARIA LINARES', 'ANTONIO QUIJARRO',
            'DANIEL CAMPOS', 'MODESTO OMISTE', 'BILBAO RIOJA', 'ENRIQUE BALDIVIESO',
            'TUPIZA', 'VILLAZON', 'UYUNI', 'LLALLAGUA',
        ],
        'TARIJA' => [
            'CERCADO', 'ANICETO ARCE', 'BURDETT OCONNOR', 'GRAN CHACO',
            'JOSE MARIA AVILES', 'MENDEZ', 'YACUIBA',
        ],
        'SUCRE' => [
            'OROPEZA', 'AZURDUY', 'ZUDANEZ', 'TOMINA', 'HERNANDO SILES',
            'YAMPARAEZ', 'NOR CINTI', 'SUD CINTI', 'BELISARIO BOETO', 'LUIS CALVO',
        ],
        'TRINIDAD' => [
            'CERCADO', 'VACA DIEZ', 'JOSE BALLIVIAN', 'YACUMA', 'MOXOS',
            'MAMORE', 'MARBAN', 'ITENE',
        ],
        'COBIJA' => [
            'NICOLAS SUAREZ', 'MANURIPI', 'MADRE DE DIOS', 'ABUNA', 'FEDERICO ROMAN',
        ],
    ];

    public function index()
    {
        return view('paquetes_ems.index');
    }

    public function create()
    {
        $this->authorizeAnyPermission(request(), [
            'feature.paquetes-ems.index.create',
            'feature.paquetes-ems.almacen.create',
            'paquetes-ems.create',
        ]);

        return view('paquetes_ems.create');
    }

    public function createSolicitud()
    {
        $this->authorizeAnyPermission(request(), [
            'feature.paquetes-ems.solicitudes.index.create',
            'paquetes-ems.solicitudes.create',
        ]);

        return view('paquetes_ems.solicitud-create', [
            'destinos' => Destino::query()->orderBy('nombre_destino')->get(),
            'servicioExtras' => ServicioExtra::query()
                ->orderBy('id')
                ->get(['id', 'nombre', 'descripcion']),
            'ciudades' => self::CIUDADES_BOLIVIA,
            'canUseFacturacionShortcut' => $this->canUseFacturacionShortcut(Auth::user()),
        ]);
    }

    public function indexSolicitudes(Request $request)
    {
        $this->authorizeAnyPermission($request, [
            'paquetes-ems.solicitudes.index',
        ]);

        $estadoSolicitudId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id') ?? 0);

        $solicitudes = SolicitudCliente::query()
            ->with([
                'cliente:id,name,email',
                'estadoRegistro:id,nombre_estado',
                'servicioExtra:id,nombre,descripcion',
                'destino:id,nombre_destino',
            ])
            ->when($estadoSolicitudId > 0, function ($query) use ($estadoSolicitudId) {
                $query->where('estado_id', $estadoSolicitudId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('paquetes_ems.solicitudes-index', [
            'solicitudes' => $solicitudes,
        ]);
    }

    public function sendSolicitudesToAlmacen(Request $request)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.solicitudes.index.assign',
            'paquetes-ems.solicitudes.send-almacen',
        ]);

        $data = $request->validate([
            'solicitud_ids' => ['required', 'array', 'min:1'],
            'solicitud_ids.*' => ['integer', 'exists:solicitud_clientes,id'],
        ], [], [
            'solicitud_ids' => 'solicitudes',
            'solicitud_ids.*' => 'solicitud',
        ]);

        $estadoSolicitudId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id') ?? 0);

        $estadoAlmacenId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ALMACEN'])
            ->value('id') ?? 0);

        if ($estadoSolicitudId <= 0 || $estadoAlmacenId <= 0) {
            return redirect()
                ->route('paquetes-ems.solicitudes.index')
                ->with('error', 'No existen los estados SOLICITUD o ALMACEN en la tabla estados.');
        }

        $ids = collect($data['solicitud_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $updated = SolicitudCliente::query()
            ->whereIn('id', $ids)
            ->where('estado_id', $estadoSolicitudId)
            ->update([
                'estado_id' => $estadoAlmacenId,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            $actorUserId = (int) optional(Auth::user())->id;

            if ($actorUserId > 0) {
                $solicitudes = SolicitudCliente::query()
                    ->whereIn('id', $ids)
                    ->where('estado_id', $estadoAlmacenId)
                    ->get(['codigo_solicitud', 'barcode']);

                $this->registrarEventosTiktoker(
                    $solicitudes,
                    $actorUserId,
                    TiktokerEvent::resolveId(TiktokerEvent::RECIBIDA_ALMACEN)
                );
            }
        }

        return redirect()
            ->route('paquetes-ems.solicitudes.index')
            ->with(
                $updated > 0 ? 'success' : 'error',
                $updated > 0
                    ? $updated . ' solicitud(es) enviada(s) a ALMACEN correctamente.'
                    : 'No se actualizo ninguna solicitud. Verifica que sigan en estado SOLICITUD.'
            );
    }

    public function findSolicitud(Request $request): JsonResponse
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.solicitudes.index.create',
            'paquetes-ems.solicitudes.find',
        ]);

        $codigoSolicitud = strtoupper(trim((string) $request->query('codigo_solicitud')));

        if ($codigoSolicitud === '') {
            return response()->json([
                'message' => 'Debes ingresar un codigo de solicitud.',
            ], 422);
        }

        $solicitud = SolicitudCliente::query()
            ->whereRaw('trim(upper(codigo_solicitud)) = ?', [$codigoSolicitud])
            ->first();

        if (!$solicitud) {
            return response()->json([
                'message' => 'No se encontro una solicitud con ese codigo.',
            ], 404);
        }

        $direccionEntrega = $this->isPuertaAVentanillaService($solicitud->servicioExtra)
            ? self::DIRECCION_DESTINATARIO_VENTANILLA
            : $solicitud->direccion;

        return response()->json([
            'id' => (int) $solicitud->id,
            'codigo_solicitud' => $solicitud->codigo_solicitud,
            'servicio_extra_id' => $solicitud->servicio_extra_id,
            'origen' => $solicitud->origen,
            'destino_id' => $solicitud->destino_id,
            'cantidad' => $solicitud->cantidad,
            'contenido' => $solicitud->contenido,
            'nombre_remitente' => $solicitud->nombre_remitente,
            'carnet' => $solicitud->carnet,
            'telefono_remitente' => $solicitud->telefono_remitente,
            'direccion_recojo' => $solicitud->direccion_recojo,
            'nombre_destinatario' => $solicitud->nombre_destinatario,
            'telefono_destinatario' => $solicitud->telefono_destinatario,
            'direccion_entrega' => $direccionEntrega,
            'pago_destinatario' => (bool) $solicitud->pago_destinatario,
        ]);
    }

    public function quoteSolicitud(Request $request): JsonResponse
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.solicitudes.index.create',
            'paquetes-ems.solicitudes.quote',
        ]);

        $request->validate([
            'servicio_extra_id' => ['required', 'integer', 'exists:servicio_extras,id'],
            'origen' => ['required', 'string'],
            'destino_id' => ['required', 'integer', 'exists:destino,id'],
            'peso' => ['required', 'numeric', 'min:0.001'],
            'pago_destinatario' => ['nullable', 'boolean'],
        ]);

        try {
            [$tarifario, $precio] = $this->resolveTarifarioYPrecio(
                (int) $request->input('servicio_extra_id'),
                (string) $request->input('origen'),
                (int) $request->input('destino_id'),
                (float) $request->input('peso'),
                (bool) $request->boolean('pago_destinatario')
            );
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'tarifario_tiktoker_id' => (int) $tarifario->id,
            'precio' => number_format($precio, 2, '.', ''),
            'tiempo_entrega' => (int) $tarifario->tiempo_entrega,
        ]);
    }

    public function storeSolicitud(Request $request)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.solicitudes.index.create',
            'paquetes-ems.solicitudes.store',
        ]);

        $data = $request->validate([
            'solicitud_id' => ['nullable', 'integer', 'exists:solicitud_clientes,id'],
            'servicio_extra_id' => ['required', 'integer', 'exists:servicio_extras,id'],
            'origen' => ['required', 'string'],
            'destino_id' => ['required', 'integer', 'exists:destino,id'],
            'cantidad' => ['required', 'integer', 'min:1'],
            'peso' => ['required', 'numeric', 'min:0.001'],
            'pago_destinatario' => ['nullable', 'boolean'],
            'contenido' => ['required', 'string'],
            'nombre_remitente' => ['required', 'string', 'max:255'],
            'carnet' => ['required', 'string', 'max:255'],
            'telefono_remitente' => ['nullable', 'string', 'max:50'],
            'nombre_destinatario' => ['required', 'string', 'max:255'],
            'telefono_destinatario' => ['nullable', 'string', 'max:50'],
            'direccion_recojo' => ['required', 'string', 'max:255'],
            'direccion_entrega' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $destino = Destino::query()->findOrFail((int) $data['destino_id']);
            [$tarifarioTiktoker, $precio] = $this->resolveTarifarioYPrecio(
                (int) $data['servicio_extra_id'],
                (string) $data['origen'],
                (int) $data['destino_id'],
                (float) $data['peso'],
                (bool) ($data['pago_destinatario'] ?? false)
            );
        } catch (\RuntimeException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['precio' => $exception->getMessage()]);
        }
        $estadoSolicitudId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id') ?? 0);

        if ($estadoSolicitudId <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['estado' => 'No existe el estado SOLICITUD en la tabla estados.']);
        }

        $isPuertaAVentanilla = $this->isPuertaAVentanillaService($tarifarioTiktoker->servicioExtra);
        $direccionEntrega = $isPuertaAVentanilla
            ? self::DIRECCION_DESTINATARIO_VENTANILLA
            : trim((string) ($data['direccion_entrega'] ?? ''));

        if (!$isPuertaAVentanilla && $direccionEntrega === '') {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['direccion_entrega' => 'La direccion de entrega es obligatoria.']);
        }

        $payload = [
            'estado_id' => $estadoSolicitudId,
            'servicio_extra_id' => (int) $data['servicio_extra_id'],
            'origen' => strtoupper(trim((string) $data['origen'])),
            'tipo_correspondencia' => null,
            'servicio_especial' => null,
            'contenido' => trim((string) $data['contenido']),
            'cantidad' => (int) $data['cantidad'],
            'peso' => (float) $data['peso'],
            'precio' => $precio,
            'pago_destinatario' => (bool) ($data['pago_destinatario'] ?? false),
            'nombre_remitente' => strtoupper(trim((string) $data['nombre_remitente'])),
            'nombre_envia' => null,
            'carnet' => trim((string) $data['carnet']),
            'telefono_remitente' => $this->nullableTrim($data['telefono_remitente'] ?? null),
            'nombre_destinatario' => strtoupper(trim((string) $data['nombre_destinatario'])),
            'telefono_destinatario' => $this->nullableTrim($data['telefono_destinatario'] ?? null),
            'direccion_recojo' => trim((string) $data['direccion_recojo']),
            'direccion' => $direccionEntrega,
            'ciudad' => strtoupper((string) $destino->nombre_destino),
            'servicio_id' => null,
            'destino_id' => (int) $data['destino_id'],
            'tarifario_tiktoker_id' => (int) $tarifarioTiktoker->id,
        ];

        $solicitudId = (int) ($data['solicitud_id'] ?? 0);
        $solicitud = null;
        if ($solicitudId > 0) {
            $solicitud = SolicitudCliente::query()->find($solicitudId);
        }

        if ($solicitud) {
            $solicitud->update($payload);
            $solicitud->refresh();
        } else {
            $solicitud = SolicitudCliente::query()->create(array_merge($payload, [
                'cliente_id' => null,
            ]));

            $codigoSolicitud = SolicitudCode::make((int) $solicitud->id, $solicitud->origen);

            $solicitud->update([
                'codigo_solicitud' => $codigoSolicitud,
                'barcode' => $codigoSolicitud,
            ]);

            $solicitud->refresh();
        }

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId > 0) {
            $this->registrarEventosTiktoker(
                [$solicitud],
                $actorUserId,
                TiktokerEvent::resolveId(TiktokerEvent::SOLICITUD_REGISTRADA)
            );
        }

        $user = Auth::user();
        if ($this->canUseFacturacionShortcut($user)) {
            try {
                app(FacturacionCartService::class)->addSolicitudEms($user, $solicitud);
            } catch (\Throwable $e) {
                report($e);

                return redirect()
                    ->route('paquetes-ems.solicitudes.index')
                    ->with('error', 'La solicitud se guardo, pero no se pudo agregar al carrito de facturacion.');
            }

            return redirect()
                ->route('paquetes-ems.solicitudes.index')
                ->with('success', 'Solicitud guardada y agregada al carrito de facturacion.')
                ->with('solicitud_ticket_url', route('paquetes-ems.solicitudes.ticket', $solicitud));
        }

        return redirect()
            ->route('paquetes-ems.solicitudes.ticket', $solicitud);
    }

    private function canUseFacturacionShortcut($user): bool
    {
        return (bool) ($user && method_exists($user, 'can') && $user->can('feature.dashboard.facturacion'));
    }

    public function ticketSolicitud(Request $request, SolicitudCliente $solicitud)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.solicitudes.index.print',
            'feature.paquetes-ems.solicitudes.index.create',
            'paquetes-ems.solicitudes.ticket',
        ]);

        $solicitud->loadMissing([
            'estadoRegistro:id,nombre_estado',
            'servicioExtra:id,nombre,descripcion',
            'destino:id,nombre_destino',
        ]);

        return view('paquetes_ems.solicitud-ticket', [
            'solicitud' => $solicitud,
        ]);
    }

    public function almacen()
    {
        return view('paquetes_ems.almacen');
    }

    public function almacenAdmisiones(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $estadoAlmacenId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ALMACEN'])
            ->value('id');

        $paquetes = PaqueteEms::query()
            ->leftJoin('paquetes_ems_formulario as formulario', 'formulario.paquete_ems_id', '=', 'paquetes_ems.id')
            ->leftJoin('users', 'users.id', '=', 'paquetes_ems.user_id')
            ->select([
                'paquetes_ems.id',
                DB::raw("coalesce(nullif(trim(formulario.codigo), ''), paquetes_ems.codigo) as codigo"),
                DB::raw("coalesce(nullif(trim(formulario.nombre_remitente), ''), paquetes_ems.nombre_remitente) as remitente"),
                DB::raw("coalesce(nullif(trim(formulario.nombre_destinatario), ''), paquetes_ems.nombre_destinatario) as destinatario"),
                DB::raw("coalesce(nullif(trim(formulario.ciudad), ''), paquetes_ems.ciudad) as destino"),
                DB::raw("coalesce(formulario.peso, paquetes_ems.peso, 0) as peso"),
                'paquetes_ems.created_at',
                'paquetes_ems.updated_at',
                'users.name as usuario',
            ])
            ->when($estadoAlmacenId, function ($query) use ($estadoAlmacenId) {
                $query->where('paquetes_ems.estado_id', (int) $estadoAlmacenId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_ems.codigo', 'like', '%' . $search . '%')
                        ->orWhere('formulario.codigo', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.nombre_remitente', 'like', '%' . $search . '%')
                        ->orWhere('formulario.nombre_remitente', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.nombre_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('formulario.nombre_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.ciudad', 'like', '%' . $search . '%')
                        ->orWhere('formulario.ciudad', 'like', '%' . $search . '%')
                        ->orWhere('users.name', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('paquetes_ems.updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('paquetes_ems.almacen-admisiones', [
            'paquetes' => $paquetes,
            'search' => $search,
            'estadoAlmacenDisponible' => (bool) $estadoAlmacenId,
        ]);
    }

    public function ventanilla()
    {
        return view('paquetes_ems.ventanilla');
    }

    public function encargado(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $servicio = trim((string) $request->query('servicio', ''));
        $fechaDesde = trim((string) $request->query('from', ''));
        $fechaHasta = trim((string) $request->query('to', ''));

        $servicios = collect(['CONTRATO', 'EMS', 'CERTI', 'ORDI', 'SOLICITUD']);

        $emsQuery = PaqueteEms::query()
            ->leftJoin('paquetes_ems_formulario as formulario', 'formulario.paquete_ems_id', '=', 'paquetes_ems.id')
            ->leftJoin('estados as estados', 'estados.id', '=', 'paquetes_ems.estado_id')
            ->select([
                'paquetes_ems.id',
                DB::raw("coalesce(paquetes_ems.codigo::text, '') as codigo"),
                DB::raw("coalesce(paquetes_ems.cod_especial::text, '') as cod_especial"),
                DB::raw("coalesce(paquetes_ems.origen::text, '') as origen"),
                DB::raw("coalesce(paquetes_ems.ciudad::text, '') as ciudad"),
                DB::raw("coalesce(paquetes_ems.nombre_destinatario::text, '') as nombre_destinatario"),
                DB::raw("coalesce(paquetes_ems.telefono_destinatario::text, '') as telefono_destinatario"),
                'paquetes_ems.peso',
                'paquetes_ems.precio',
                'paquetes_ems.created_at',
                DB::raw("'EMS' as servicio"),
                DB::raw("coalesce(nullif(trim(formulario.tipo_correspondencia), ''), nullif(trim(formulario.servicio_especial), ''), nullif(trim(paquetes_ems.tipo_correspondencia), ''), nullif(trim(paquetes_ems.servicio_especial), ''), '-') as servicio_especial"),
                DB::raw("coalesce(estados.nombre_estado, 'SIN ESTADO') as estado_nombre"),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_ems.codigo', 'ILIKE', "%{$search}%")
                        ->orWhere('paquetes_ems.cod_especial', 'ILIKE', "%{$search}%");
                });
            })
            ->when($fechaDesde !== '', function ($query) use ($fechaDesde) {
                $query->whereDate('paquetes_ems.created_at', '>=', $fechaDesde);
            })
            ->when($fechaHasta !== '', function ($query) use ($fechaHasta) {
                $query->whereDate('paquetes_ems.created_at', '<=', $fechaHasta);
            });

        $contratosQuery = RecojoContrato::query()
            ->leftJoin('estados as estados', 'estados.id', '=', 'paquetes_contrato.estados_id')
            ->leftJoin('empresa as empresa_directa', 'empresa_directa.id', '=', 'paquetes_contrato.empresa_id')
            ->leftJoin('users as usuario_registro', 'usuario_registro.id', '=', 'paquetes_contrato.user_id')
            ->leftJoin('empresa as empresa_usuario', 'empresa_usuario.id', '=', 'usuario_registro.empresa_id')
            ->select([
                'paquetes_contrato.id',
                DB::raw("coalesce(paquetes_contrato.codigo::text, '') as codigo"),
                DB::raw("coalesce(paquetes_contrato.cod_especial::text, '') as cod_especial"),
                DB::raw("coalesce(paquetes_contrato.origen::text, '') as origen"),
                DB::raw("coalesce(paquetes_contrato.destino::text, '') as ciudad"),
                DB::raw("coalesce(paquetes_contrato.nombre_d::text, '') as nombre_destinatario"),
                DB::raw("coalesce(paquetes_contrato.telefono_d::text, '') as telefono_destinatario"),
                'paquetes_contrato.peso',
                'paquetes_contrato.precio',
                'paquetes_contrato.created_at',
                DB::raw("'CONTRATO' as servicio"),
                DB::raw("coalesce(nullif(trim(empresa_directa.nombre), ''), nullif(trim(empresa_usuario.nombre), ''), nullif(trim(paquetes_contrato.contenido), ''), '-') as servicio_especial"),
                DB::raw("coalesce(estados.nombre_estado, 'SIN ESTADO') as estado_nombre"),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_contrato.codigo', 'ILIKE', "%{$search}%")
                        ->orWhere('paquetes_contrato.cod_especial', 'ILIKE', "%{$search}%");
                });
            })
            ->when($fechaDesde !== '', function ($query) use ($fechaDesde) {
                $query->whereDate('paquetes_contrato.created_at', '>=', $fechaDesde);
            })
            ->when($fechaHasta !== '', function ($query) use ($fechaHasta) {
                $query->whereDate('paquetes_contrato.created_at', '<=', $fechaHasta);
            });

        $certiQuery = PaqueteCerti::query()
            ->leftJoin('estados as estados', 'estados.id', '=', 'paquetes_certi.fk_estado')
            ->select([
                'paquetes_certi.id',
                DB::raw("coalesce(paquetes_certi.codigo::text, '') as codigo"),
                DB::raw("coalesce(paquetes_certi.cod_especial::text, '') as cod_especial"),
                DB::raw("'-' as origen"),
                DB::raw("coalesce(paquetes_certi.cuidad::text, '') as ciudad"),
                DB::raw("coalesce(paquetes_certi.destinatario::text, '') as nombre_destinatario"),
                DB::raw("coalesce(paquetes_certi.telefono::text, '') as telefono_destinatario"),
                'paquetes_certi.peso',
                'paquetes_certi.precio',
                'paquetes_certi.created_at',
                DB::raw("'CERTI' as servicio"),
                DB::raw("coalesce(nullif(trim(paquetes_certi.tipo), ''), '-') as servicio_especial"),
                DB::raw("coalesce(estados.nombre_estado, 'SIN ESTADO') as estado_nombre"),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_certi.codigo', 'ILIKE', "%{$search}%")
                        ->orWhere('paquetes_certi.cod_especial', 'ILIKE', "%{$search}%");
                });
            })
            ->when($fechaDesde !== '', function ($query) use ($fechaDesde) {
                $query->whereDate('paquetes_certi.created_at', '>=', $fechaDesde);
            })
            ->when($fechaHasta !== '', function ($query) use ($fechaHasta) {
                $query->whereDate('paquetes_certi.created_at', '<=', $fechaHasta);
            });

        $ordiQuery = PaqueteOrdi::query()
            ->leftJoin('estados as estados', 'estados.id', '=', 'paquetes_ordi.fk_estado')
            ->select([
                'paquetes_ordi.id',
                DB::raw("coalesce(paquetes_ordi.codigo::text, '') as codigo"),
                DB::raw("coalesce(paquetes_ordi.cod_especial::text, '') as cod_especial"),
                DB::raw("'-' as origen"),
                DB::raw("coalesce(paquetes_ordi.ciudad::text, '') as ciudad"),
                DB::raw("coalesce(paquetes_ordi.destinatario::text, '') as nombre_destinatario"),
                DB::raw("coalesce(paquetes_ordi.telefono::text, '') as telefono_destinatario"),
                'paquetes_ordi.peso',
                'paquetes_ordi.precio',
                'paquetes_ordi.created_at',
                DB::raw("'ORDI' as servicio"),
                DB::raw("coalesce(nullif(trim(paquetes_ordi.observaciones), ''), nullif(trim(paquetes_ordi.aduana), ''), '-') as servicio_especial"),
                DB::raw("coalesce(estados.nombre_estado, 'SIN ESTADO') as estado_nombre"),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_ordi.codigo', 'ILIKE', "%{$search}%")
                        ->orWhere('paquetes_ordi.cod_especial', 'ILIKE', "%{$search}%");
                });
            })
            ->when($fechaDesde !== '', function ($query) use ($fechaDesde) {
                $query->whereDate('paquetes_ordi.created_at', '>=', $fechaDesde);
            })
            ->when($fechaHasta !== '', function ($query) use ($fechaHasta) {
                $query->whereDate('paquetes_ordi.created_at', '<=', $fechaHasta);
            });

        $solicitudesQuery = SolicitudCliente::query()
            ->leftJoin('estados as estados', 'estados.id', '=', 'solicitud_clientes.estado_id')
            ->select([
                'solicitud_clientes.id',
                DB::raw("coalesce(nullif(trim(solicitud_clientes.codigo_solicitud), ''), nullif(trim(solicitud_clientes.barcode), ''), 'SIN CODIGO') as codigo"),
                DB::raw("coalesce(solicitud_clientes.cod_especial::text, '') as cod_especial"),
                DB::raw("coalesce(solicitud_clientes.origen::text, '') as origen"),
                DB::raw("coalesce(solicitud_clientes.ciudad::text, '') as ciudad"),
                DB::raw("coalesce(solicitud_clientes.nombre_destinatario::text, '') as nombre_destinatario"),
                DB::raw("coalesce(solicitud_clientes.telefono_destinatario::text, '') as telefono_destinatario"),
                'solicitud_clientes.peso',
                'solicitud_clientes.precio',
                'solicitud_clientes.created_at',
                DB::raw("'SOLICITUD' as servicio"),
                DB::raw("coalesce(nullif(trim(solicitud_clientes.servicio_especial), ''), nullif(trim(solicitud_clientes.observacion), ''), '-') as servicio_especial"),
                DB::raw("coalesce(estados.nombre_estado, 'SIN ESTADO') as estado_nombre"),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('solicitud_clientes.codigo_solicitud', 'ILIKE', "%{$search}%")
                        ->orWhere('solicitud_clientes.barcode', 'ILIKE', "%{$search}%")
                        ->orWhere('solicitud_clientes.cod_especial', 'ILIKE', "%{$search}%");
                });
            })
            ->when($fechaDesde !== '', function ($query) use ($fechaDesde) {
                $query->whereDate('solicitud_clientes.created_at', '>=', $fechaDesde);
            })
            ->when($fechaHasta !== '', function ($query) use ($fechaHasta) {
                $query->whereDate('solicitud_clientes.created_at', '<=', $fechaHasta);
            });

        $union = $emsQuery
            ->unionAll($contratosQuery)
            ->unionAll($certiQuery)
            ->unionAll($ordiQuery)
            ->unionAll($solicitudesQuery);

        $paquetes = DB::query()
            ->fromSub($union, 'encargado_paquetes')
            ->when($servicio !== '', function ($query) use ($servicio) {
                $query->where('servicio', mb_strtoupper($servicio));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('paquetes_ems.encargado', [
            'paquetes' => $paquetes,
            'servicios' => $servicios,
            'search' => $search,
            'servicio' => $servicio,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
        ]);
    }

    public function cancelarEnvioEncargado(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'servicio' => ['required', 'string', Rule::in(['EMS', 'CONTRATO', 'CERTI', 'ORDI', 'SOLICITUD'])],
            'q' => ['nullable', 'string'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
        ]);

        $id = (int) $data['id'];
        $servicio = mb_strtoupper(trim((string) $data['servicio']));
        $actorUserId = (int) optional($request->user())->id;
        $updated = 0;

        DB::transaction(function () use ($servicio, $id, $actorUserId, &$updated) {
            $updated = match ($servicio) {
                'EMS' => $this->moveEncargadoRecordAndRegisterEvent(
                    PaqueteEms::query()->whereKey($id)->first(),
                    'estado_id',
                    0,
                    $servicio,
                    $actorUserId,
                    'Envio cancelado desde encargado.'
                ),
                'CONTRATO' => $this->moveEncargadoRecordAndRegisterEvent(
                    RecojoContrato::query()->whereKey($id)->first(),
                    'estados_id',
                    0,
                    $servicio,
                    $actorUserId,
                    'Envio cancelado desde encargado.'
                ),
                'CERTI' => $this->moveEncargadoRecordAndRegisterEvent(
                    PaqueteCerti::query()->whereKey($id)->first(),
                    'fk_estado',
                    0,
                    $servicio,
                    $actorUserId,
                    'Envio cancelado desde encargado.'
                ),
                'ORDI' => $this->moveEncargadoRecordAndRegisterEvent(
                    PaqueteOrdi::query()->whereKey($id)->first(),
                    'fk_estado',
                    0,
                    $servicio,
                    $actorUserId,
                    'Envio cancelado desde encargado.'
                ),
                'SOLICITUD' => $this->moveEncargadoRecordAndRegisterEvent(
                    SolicitudCliente::query()->whereKey($id)->first(),
                    'estado_id',
                    0,
                    $servicio,
                    $actorUserId,
                    'Solicitud cancelada desde encargado.'
                ),
                default => 0,
            };
        });

        return redirect()
            ->route('paquetes-ems.encargado', array_filter([
                'servicio' => $request->input('current_servicio'),
                'q' => $request->input('q'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'page' => $request->input('page'),
            ]))
            ->with(
                $updated > 0 ? 'success' : 'error',
                $updated > 0
                    ? 'El envio fue cancelado y enviado a estado 0.'
                    : 'No se pudo cancelar el envio seleccionado.'
            );
    }

    public function devolverEnvioEncargado(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'servicio' => ['required', 'string', Rule::in(['EMS', 'CONTRATO', 'CERTI', 'ORDI', 'SOLICITUD'])],
            'destino_accion' => ['required', 'string', Rule::in(['origen', 'destino', 'ventanilla'])],
            'q' => ['nullable', 'string'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
        ]);

        $id = (int) $data['id'];
        $servicio = mb_strtoupper(trim((string) $data['servicio']));
        $destinoAccion = trim((string) $data['destino_accion']);
        $actorUserId = (int) optional($request->user())->id;
        $updated = 0;

        $estadoAlmacenId = $this->findEstadoIdByNombre('ALMACEN');
        $estadoRecibidoId = $this->findEstadoIdByNombre('RECIBIDO');
        $estadoVentanillaId = $this->findEstadoIdByNombre('VENTANILLA');

        if (in_array($servicio, ['EMS', 'CONTRATO', 'SOLICITUD'], true)) {
            if ($destinoAccion === 'origen' && $estadoAlmacenId <= 0) {
                return back()->with('error', 'No existe el estado ALMACEN en la tabla estados.');
            }

            if ($destinoAccion === 'destino' && $estadoRecibidoId <= 0) {
                return back()->with('error', 'No existe el estado RECIBIDO en la tabla estados.');
            }
        }

        if (in_array($servicio, ['CERTI', 'ORDI'], true) && $estadoVentanillaId <= 0) {
            return back()->with('error', 'No existe el estado VENTANILLA en la tabla estados.');
        }

        DB::transaction(function () use (
            $servicio,
            $destinoAccion,
            $id,
            $actorUserId,
            $estadoAlmacenId,
            $estadoRecibidoId,
            $estadoVentanillaId,
            &$updated
        ) {
            if ($servicio === 'EMS') {
                $updated = $destinoAccion === 'origen'
                    ? $this->moveEncargadoRecordAndRegisterEvent(
                        PaqueteEms::query()->whereKey($id)->first(),
                        'estado_id',
                        (int) $estadoAlmacenId,
                        $servicio,
                        $actorUserId,
                        self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE
                    )
                    : $this->moveEncargadoRecordAndRegisterEvent(
                        PaqueteEms::query()->whereKey($id)->first(),
                        'estado_id',
                        (int) $estadoRecibidoId,
                        $servicio,
                        $actorUserId,
                        self::EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO
                    );

                return;
            }

            if ($servicio === 'CONTRATO') {
                $updated = $destinoAccion === 'origen'
                    ? $this->moveEncargadoRecordAndRegisterEvent(
                        RecojoContrato::query()->whereKey($id)->first(),
                        'estados_id',
                        (int) $estadoAlmacenId,
                        $servicio,
                        $actorUserId,
                        self::EVENTO_ID_PAQUETE_RECIBIDO_CLIENTE
                    )
                    : $this->moveEncargadoRecordAndRegisterEvent(
                        RecojoContrato::query()->whereKey($id)->first(),
                        'estados_id',
                        (int) $estadoRecibidoId,
                        $servicio,
                        $actorUserId,
                        self::EVENTO_ID_PAQUETE_RECIBIDO_ORIGEN_TRANSITO
                    );

                return;
            }

            if ($servicio === 'SOLICITUD') {
                $updated = $destinoAccion === 'origen'
                    ? $this->moveEncargadoRecordAndRegisterEvent(
                        SolicitudCliente::query()->whereKey($id)->first(),
                        'estado_id',
                        (int) $estadoAlmacenId,
                        $servicio,
                        $actorUserId,
                        TiktokerEvent::RECIBIDA_ALMACEN
                    )
                    : $this->moveEncargadoRecordAndRegisterEvent(
                        SolicitudCliente::query()->whereKey($id)->first(),
                        'estado_id',
                        (int) $estadoRecibidoId,
                        $servicio,
                        $actorUserId,
                        TiktokerEvent::RECIBIDA_TRANSITO
                    );

                return;
            }

            if ($servicio === 'CERTI') {
                $updated = $this->moveEncargadoRecordAndRegisterEvent(
                    PaqueteCerti::query()->whereKey($id)->first(),
                    'fk_estado',
                    (int) $estadoVentanillaId,
                    $servicio,
                    $actorUserId,
                    self::EVENTO_ID_CERTI_RECIBIDO_OFICINA_ENTREGA
                );

                return;
            }

            if ($servicio === 'ORDI') {
                $updated = $this->moveEncargadoRecordAndRegisterEvent(
                    PaqueteOrdi::query()->whereKey($id)->first(),
                    'fk_estado',
                    (int) $estadoVentanillaId,
                    $servicio,
                    $actorUserId,
                    'Envio devuelto a ventanilla desde encargado.'
                );
            }
        });

        return redirect()
            ->route('paquetes-ems.encargado', array_filter([
                'servicio' => $request->input('current_servicio'),
                'q' => $request->input('q'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'page' => $request->input('page'),
            ]))
            ->with(
                $updated > 0 ? 'success' : 'error',
                $updated > 0
                    ? $this->successMessageForDevolucion($servicio, $destinoAccion)
                    : 'No se pudo devolver el envio seleccionado.'
            );
    }

    public function actualizarPesoEncargado(Request $request)
    {
        $data = $request->validate([
            'id' => ['required', 'integer', 'min:1'],
            'servicio' => ['required', 'string', Rule::in(['EMS', 'CONTRATO', 'CERTI', 'ORDI', 'SOLICITUD'])],
            'peso' => ['required', 'numeric', 'min:0'],
            'q' => ['nullable', 'string'],
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
        ], [], [
            'id' => 'registro',
            'servicio' => 'servicio',
            'peso' => 'peso',
        ]);

        $id = (int) $data['id'];
        $servicio = mb_strtoupper(trim((string) $data['servicio']));
        $peso = (float) $data['peso'];
        $actorUserId = (int) optional($request->user())->id;
        $updated = 0;

        DB::transaction(function () use ($servicio, $id, $peso, $actorUserId, &$updated) {
            $updated = match ($servicio) {
                'EMS' => $this->updateEncargadoPesoAndRegisterEvent(
                    PaqueteEms::query()->whereKey($id)->first(),
                    $peso,
                    $servicio,
                    $actorUserId
                ),
                'CONTRATO' => $this->updateEncargadoPesoAndRegisterEvent(
                    RecojoContrato::query()->whereKey($id)->first(),
                    $peso,
                    $servicio,
                    $actorUserId
                ),
                'CERTI' => $this->updateEncargadoPesoAndRegisterEvent(
                    PaqueteCerti::query()->whereKey($id)->first(),
                    $peso,
                    $servicio,
                    $actorUserId
                ),
                'ORDI' => $this->updateEncargadoPesoAndRegisterEvent(
                    PaqueteOrdi::query()->whereKey($id)->first(),
                    $peso,
                    $servicio,
                    $actorUserId
                ),
                'SOLICITUD' => $this->updateEncargadoPesoAndRegisterEvent(
                    SolicitudCliente::query()->whereKey($id)->first(),
                    $peso,
                    $servicio,
                    $actorUserId
                ),
                default => 0,
            };
        });

        return redirect()
            ->route('paquetes-ems.encargado', array_filter([
                'servicio' => $request->input('current_servicio'),
                'q' => $request->input('q'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'page' => $request->input('page'),
            ]))
            ->with(
                $updated > 0 ? 'success' : 'error',
                $updated > 0
                    ? 'El peso fue actualizado y se registro su evento.'
                    : 'No se pudo actualizar el peso del envio seleccionado.'
            );
    }

    public function devolucion()
    {
        return view('paquetes_ems.devolucion');
    }

    public function recibirRegional()
    {
        return view('paquetes_ems.recibir-regional');
    }

    public function enTransito()
    {
        return view('paquetes_ems.en-transito');
    }

    public function entregados(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $user = $request->user();
        $printPermissions = [
            'feature.paquetes-ems.entregados.print',
        ];
        $deliverNotRegisteredPermissions = [
            'feature.paquetes-ems.entregados.deliver',
            'feature.paquetes-ems.almacen.registercontract',
            'feature.paquetes-ems.contrato-rapido.create.create',
            'feature.paquetes-ems.contrato-rapido.create.save',
        ];

        $canEmsEntregadosPrint = false;
        $canEntregaNoRegistrada = false;
        $superAdminRole = (string) config('acl.super_admin_role', 'administrador');

        if ($user && $superAdminRole !== '' && method_exists($user, 'hasRole') && $user->hasRole($superAdminRole)) {
            $canEmsEntregadosPrint = true;
            $canEntregaNoRegistrada = true;
        } elseif ($user) {
            foreach ($printPermissions as $permission) {
                if ($user->can($permission)) {
                    $canEmsEntregadosPrint = true;
                    break;
                }
            }

            foreach ($deliverNotRegisteredPermissions as $permission) {
                if ($user->can($permission)) {
                    $canEntregaNoRegistrada = true;
                    break;
                }
            }
        }

        $estadoEntregadoId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
            ->value('id');

        $emsQuery = PaqueteEms::query()
            ->leftJoin('cartero as asignacion', 'asignacion.id_paquetes_ems', '=', 'paquetes_ems.id')
            ->leftJoin('users as cartero_user', 'cartero_user.id', '=', 'asignacion.id_user')
            ->select([
                DB::raw("'EMS' as tipo_paquete"),
                'paquetes_ems.id',
                'paquetes_ems.codigo',
                'paquetes_ems.cod_especial',
                DB::raw('paquetes_ems.nombre_destinatario as destinatario'),
                DB::raw('paquetes_ems.telefono_destinatario as telefono'),
                DB::raw('paquetes_ems.ciudad as ciudad'),
                'paquetes_ems.peso',
                DB::raw('paquetes_ems.updated_at as fecha_entrega'),
                'asignacion.recibido_por',
                'asignacion.descripcion',
                DB::raw('COALESCE(asignacion.imagen, paquetes_ems.imagen) as imagen'),
                'cartero_user.name as asignado_a',
            ])
            ->when($estadoEntregadoId, function ($query) use ($estadoEntregadoId) {
                $query->where('paquetes_ems.estado_id', (int) $estadoEntregadoId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_ems.codigo', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.cod_especial', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.nombre_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.telefono_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.ciudad', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.recibido_por', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.descripcion', 'like', '%' . $search . '%')
                        ->orWhere('cartero_user.name', 'like', '%' . $search . '%');
                });
            });

        $contratosQuery = RecojoContrato::query()
            ->leftJoin('cartero as asignacion', 'asignacion.id_paquetes_contrato', '=', 'paquetes_contrato.id')
            ->leftJoin('users as cartero_user', 'cartero_user.id', '=', 'asignacion.id_user')
            ->select([
                DB::raw("'CONTRATO' as tipo_paquete"),
                'paquetes_contrato.id',
                'paquetes_contrato.codigo',
                'paquetes_contrato.cod_especial',
                DB::raw('paquetes_contrato.nombre_d as destinatario'),
                DB::raw('paquetes_contrato.telefono_d as telefono'),
                DB::raw('paquetes_contrato.destino as ciudad'),
                'paquetes_contrato.peso',
                DB::raw('paquetes_contrato.updated_at as fecha_entrega'),
                'asignacion.recibido_por',
                'asignacion.descripcion',
                DB::raw('COALESCE(asignacion.imagen, paquetes_contrato.imagen) as imagen'),
                'cartero_user.name as asignado_a',
            ])
            ->when($estadoEntregadoId, function ($query) use ($estadoEntregadoId) {
                $query->where('paquetes_contrato.estados_id', (int) $estadoEntregadoId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_contrato.codigo', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.cod_especial', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.nombre_d', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.telefono_d', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.destino', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.recibido_por', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.descripcion', 'like', '%' . $search . '%')
                        ->orWhere('cartero_user.name', 'like', '%' . $search . '%');
                });
            });

        $solicitudesQuery = SolicitudCliente::query()
            ->select([
                DB::raw("'SOLICITUD' as tipo_paquete"),
                'solicitud_clientes.id',
                DB::raw("COALESCE(NULLIF(TRIM(solicitud_clientes.codigo_solicitud), ''), NULLIF(TRIM(solicitud_clientes.barcode), ''), 'SIN CODIGO') as codigo"),
                DB::raw("COALESCE(NULLIF(TRIM(solicitud_clientes.cod_especial), ''), '-') as cod_especial"),
                DB::raw('solicitud_clientes.nombre_destinatario as destinatario'),
                DB::raw('solicitud_clientes.telefono_destinatario as telefono'),
                DB::raw('solicitud_clientes.ciudad as ciudad'),
                'solicitud_clientes.peso',
                DB::raw('solicitud_clientes.updated_at as fecha_entrega'),
                DB::raw("'-' as recibido_por"),
                DB::raw("'-' as descripcion"),
                DB::raw('NULL as imagen'),
                DB::raw("'-' as asignado_a"),
            ])
            ->when($estadoEntregadoId, function ($query) use ($estadoEntregadoId) {
                $query->where('solicitud_clientes.estado_id', (int) $estadoEntregadoId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('solicitud_clientes.codigo_solicitud', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.barcode', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.cod_especial', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.nombre_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.telefono_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.ciudad', 'like', '%' . $search . '%');
                });
            });

        $paquetes = DB::query()
            ->fromSub($emsQuery->unionAll($contratosQuery)->unionAll($solicitudesQuery), 'entregados')
            ->orderByDesc('fecha_entrega')
            ->paginate(20)
            ->withQueryString();

        return view('paquetes_ems.entregados', [
            'paquetes' => $paquetes,
            'search' => $search,
            'estadoEntregadoDisponible' => (bool) $estadoEntregadoId,
            'canEmsEntregadosPrint' => $canEmsEntregadosPrint,
            'canEntregaNoRegistrada' => $canEntregaNoRegistrada,
        ]);
    }

    public function devueltos(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $estadoDevolucionId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['DEVOLUCION'])
            ->value('id');

        $emsQuery = PaqueteEms::query()
            ->leftJoin('cartero as asignacion', 'asignacion.id_paquetes_ems', '=', 'paquetes_ems.id')
            ->leftJoin('users as cartero_user', 'cartero_user.id', '=', 'asignacion.id_user')
            ->select([
                DB::raw("'EMS' as tipo_paquete"),
                'paquetes_ems.id',
                'paquetes_ems.codigo',
                DB::raw('paquetes_ems.nombre_destinatario as destinatario'),
                DB::raw('paquetes_ems.telefono_destinatario as telefono'),
                DB::raw('paquetes_ems.ciudad as ciudad'),
                'paquetes_ems.peso',
                DB::raw('paquetes_ems.updated_at as fecha_devolucion'),
                'asignacion.recibido_por',
                'asignacion.descripcion',
                DB::raw('COALESCE(asignacion.imagen_devolucion, asignacion.imagen, paquetes_ems.imagen) as imagen'),
                'cartero_user.name as asignado_a',
            ])
            ->when($estadoDevolucionId, function ($query) use ($estadoDevolucionId) {
                $query->where('paquetes_ems.estado_id', (int) $estadoDevolucionId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_ems.codigo', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.nombre_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.telefono_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.ciudad', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.recibido_por', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.descripcion', 'like', '%' . $search . '%')
                        ->orWhere('cartero_user.name', 'like', '%' . $search . '%');
                });
            });

        $contratosQuery = RecojoContrato::query()
            ->leftJoin('cartero as asignacion', 'asignacion.id_paquetes_contrato', '=', 'paquetes_contrato.id')
            ->leftJoin('users as cartero_user', 'cartero_user.id', '=', 'asignacion.id_user')
            ->select([
                DB::raw("'CONTRATO' as tipo_paquete"),
                'paquetes_contrato.id',
                'paquetes_contrato.codigo',
                DB::raw('paquetes_contrato.nombre_d as destinatario'),
                DB::raw('paquetes_contrato.telefono_d as telefono'),
                DB::raw('paquetes_contrato.destino as ciudad'),
                'paquetes_contrato.peso',
                DB::raw('paquetes_contrato.updated_at as fecha_devolucion'),
                'asignacion.recibido_por',
                'asignacion.descripcion',
                DB::raw('COALESCE(asignacion.imagen_devolucion, asignacion.imagen, paquetes_contrato.imagen) as imagen'),
                'cartero_user.name as asignado_a',
            ])
            ->when($estadoDevolucionId, function ($query) use ($estadoDevolucionId) {
                $query->where('paquetes_contrato.estados_id', (int) $estadoDevolucionId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_contrato.codigo', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.nombre_d', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.telefono_d', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_contrato.destino', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.recibido_por', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.descripcion', 'like', '%' . $search . '%')
                        ->orWhere('cartero_user.name', 'like', '%' . $search . '%');
                });
            });

        $solicitudesQuery = SolicitudCliente::query()
            ->leftJoin('cartero as asignacion', 'asignacion.id_solicitud_cliente', '=', 'solicitud_clientes.id')
            ->leftJoin('users as cartero_user', 'cartero_user.id', '=', 'asignacion.id_user')
            ->select([
                DB::raw("'SOLICITUD' as tipo_paquete"),
                'solicitud_clientes.id',
                DB::raw("COALESCE(NULLIF(TRIM(solicitud_clientes.codigo_solicitud), ''), NULLIF(TRIM(solicitud_clientes.barcode), ''), 'SIN CODIGO') as codigo"),
                DB::raw('solicitud_clientes.nombre_destinatario as destinatario'),
                DB::raw('solicitud_clientes.telefono_destinatario as telefono'),
                DB::raw('solicitud_clientes.ciudad as ciudad'),
                'solicitud_clientes.peso',
                DB::raw('solicitud_clientes.updated_at as fecha_devolucion'),
                DB::raw("COALESCE(asignacion.recibido_por, solicitud_clientes.recepcionado_por, '-') as recibido_por"),
                DB::raw("COALESCE(asignacion.descripcion, solicitud_clientes.observacion, '-') as descripcion"),
                DB::raw('COALESCE(asignacion.imagen_devolucion, solicitud_clientes.imagen) as imagen'),
                DB::raw("COALESCE(cartero_user.name, '-') as asignado_a"),
            ])
            ->when($estadoDevolucionId, function ($query) use ($estadoDevolucionId) {
                $query->where('solicitud_clientes.estado_id', (int) $estadoDevolucionId);
            }, function ($query) {
                $query->whereRaw('1 = 0');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('solicitud_clientes.codigo_solicitud', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.barcode', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.nombre_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.telefono_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('solicitud_clientes.ciudad', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.recibido_por', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.descripcion', 'like', '%' . $search . '%')
                        ->orWhere('cartero_user.name', 'like', '%' . $search . '%');
                });
            });

        $paquetes = DB::query()
            ->fromSub($emsQuery->unionAll($contratosQuery)->unionAll($solicitudesQuery), 'devueltos')
            ->orderByDesc('fecha_devolucion')
            ->paginate(20)
            ->withQueryString();

        return view('paquetes_ems.devueltos', [
            'paquetes' => $paquetes,
            'search' => $search,
            'estadoDevolucionDisponible' => (bool) $estadoDevolucionId,
        ]);
    }

    public function planillaEntregados(Request $request)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.index.print',
            'feature.paquetes-ems.almacen.print',
            'feature.paquetes-ems.ventanilla.print',
            'feature.paquetes-ems.recibir-regional.print',
            'feature.paquetes-ems.en-transito.print',
        ]);

        $search = trim((string) $request->query('q', ''));
        $estadoEntregadoId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
            ->value('id') ?? 0);

        if ($estadoEntregadoId <= 0) {
            return redirect()
                ->route('paquetes-ems.entregados', ['q' => $search])
                ->with('error', 'No existe el estado ENTREGADO en la tabla estados.');
        }

        $paquetes = PaqueteEms::query()
            ->with(['tarifario.destino', 'formulario'])
            ->leftJoin('cartero as asignacion', 'asignacion.id_paquetes_ems', '=', 'paquetes_ems.id')
            ->select('paquetes_ems.*')
            ->where('paquetes_ems.estado_id', $estadoEntregadoId)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('paquetes_ems.codigo', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.nombre_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.telefono_destinatario', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.ciudad', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.nombre_remitente', 'like', '%' . $search . '%')
                        ->orWhere('paquetes_ems.contenido', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.recibido_por', 'like', '%' . $search . '%')
                        ->orWhere('asignacion.descripcion', 'like', '%' . $search . '%');
                });
            })
            ->orderBy('paquetes_ems.id')
            ->distinct()
            ->get();

        if ($paquetes->isEmpty()) {
            return redirect()
                ->route('paquetes-ems.entregados', ['q' => $search])
                ->with('error', 'No hay paquetes EMS entregados para generar la planilla.');
        }

        $generatedAt = now();
        $pdf = Pdf::loadView('paquetes_ems.planilla-entregados', [
            'paquetes' => $paquetes,
            'generatedAt' => $generatedAt,
            'search' => $search,
        ])->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'planilla-ems-entregados-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function createSolicitudDesdeEntregados(Request $request)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-contrato.create.create',
            'feature.paquetes-ems.index.create',
            'feature.paquetes-ems.almacen.create',
        ]);

        return view('paquetes_ems.solicitud-desde-entregados', [
            'empresas' => Empresa::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'sigla', 'codigo_cliente']),
            'ciudades' => self::CIUDADES_BOLIVIA,
            'returnQuery' => trim((string) $request->query('q', '')),
            'codigoMadreSugerido' => preg_match('/^[A-Za-z0-9]+$/', trim((string) $request->query('q', '')))
                ? strtoupper(trim((string) $request->query('q', '')))
                : '',
        ]);
    }

    public function storeSolicitudDesdeEntregados(Request $request)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-contrato.create.create',
            'feature.paquetes-ems.index.create',
            'feature.paquetes-ems.almacen.create',
        ]);

        $data = $request->validate([
            'empresa_id' => ['required', 'integer', 'exists:empresa,id'],
            'origen' => ['required', 'string', Rule::in(self::CIUDADES_BOLIVIA)],
            'destino' => ['required', 'string', Rule::in(self::CIUDADES_BOLIVIA)],
            'direccion_r' => ['required', 'string', 'max:255'],
            'direccion_d' => ['required', 'string', 'max:255'],
            'peso' => ['required', 'numeric', 'min:0.001'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'codigo_madre' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9]+$/'],
            'return_query' => ['nullable', 'string', 'max:255'],
        ], [], [
            'empresa_id' => 'empresa',
            'origen' => 'origen',
            'destino' => 'destino',
            'direccion_r' => 'origen direccion',
            'direccion_d' => 'destino direccion',
            'peso' => 'peso',
            'observacion' => 'observacion',
            'codigo_madre' => 'codigo madre',
        ]);

        $empresa = Empresa::query()->findOrFail((int) $data['empresa_id']);
        $user = Auth::user();

        if (! $user) {
            abort(403, 'No autenticado.');
        }

        $codigoCliente = strtoupper(trim((string) $empresa->codigo_cliente));
        $codigoCliente = preg_replace('/\s+/', '', $codigoCliente) ?: '';

        if ($codigoCliente === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'La empresa seleccionada no tiene codigo_cliente valido para seguir el correlativo.');
        }

        $estadoRecibidoId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['RECIBIDO'])
            ->value('id') ?? 0);

        if ($estadoRecibidoId <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No existe el estado RECIBIDO en la tabla estados.');
        }

        $eventoExiste = DB::table('eventos')
            ->where('id', self::EVENTO_ID_CONTRATO_RECIBIDO)
            ->exists();

        if (! $eventoExiste) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No existe el evento con ID '.self::EVENTO_ID_CONTRATO_RECIBIDO.' en la tabla eventos.');
        }

        $contrato = null;

        DB::transaction(function () use ($empresa, $user, $data, $codigoCliente, $estadoRecibidoId, &$contrato) {
            $correlativo = $this->nextCorrelativoEmpresa((int) $empresa->id, $codigoCliente);
            $codigo = $this->buildCodigoEmpresa($codigoCliente, $correlativo);
            $codigoMadre = strtoupper(trim((string) ($data['codigo_madre'] ?? '')));

            $contrato = RecojoContrato::query()->create([
                'user_id' => (int) $user->id,
                'empresa_id' => (int) $empresa->id,
                'codigo' => $codigo,
                'codigo_madre' => $codigoMadre !== '' ? $codigoMadre : null,
                'cod_especial' => null,
                'estados_id' => $estadoRecibidoId,
                'origen' => strtoupper(trim((string) $data['origen'])),
                'destino' => strtoupper(trim((string) $data['destino'])),
                'nombre_r' => strtoupper(trim((string) ($empresa->nombre ?: 'SIN REMITENTE'))),
                'telefono_r' => '-',
                'contenido' => 'SOLICITUD GENERADA DESDE EMS ENTREGADOS',
                'direccion_r' => strtoupper(trim((string) $data['direccion_r'])),
                'nombre_d' => 'SIN DESTINATARIO',
                'telefono_d' => null,
                'direccion_d' => strtoupper(trim((string) $data['direccion_d'])),
                'mapa' => null,
                'provincia' => null,
                'peso' => (float) $data['peso'],
                'precio' => null,
                'tarifa_contrato_id' => null,
                'fecha_recojo' => null,
                'observacion' => $this->nullableTrim($data['observacion'] ?? null),
                'justificacion' => null,
                'imagen' => null,
            ]);

            CodigoEmpresa::query()->create([
                'codigo' => $codigo,
                'barcode' => $codigo,
                'empresa_id' => (int) $empresa->id,
            ]);

            DB::table('eventos_contrato')->insert([
                'codigo' => $codigo,
                'evento_id' => self::EVENTO_ID_CONTRATO_RECIBIDO,
                'user_id' => (int) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($codigoMadre !== '') {
                $this->insertCodigoContinuacionEvents($codigoMadre, $codigo, (int) $user->id);
            }
        });

        $contrato->loadMissing('empresa:id,nombre,sigla,codigo_cliente');
        $generatedAt = now();
        $pdf = Pdf::loadView('paquetes_ems.solicitud-desde-entregados-boleta', [
            'contrato' => $contrato,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'boleta-recibido-' . $contrato->codigo . '-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function createEntregaNoRegistrada(Request $request)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.entregados.deliver',
            'feature.paquetes-ems.almacen.registercontract',
            'feature.paquetes-ems.contrato-rapido.create.create',
            'feature.paquetes-ems.contrato-rapido.create.save',
        ]);

        $user = $request->user();
        $origen = strtoupper(trim((string) optional($user)->ciudad));
        if ($origen === '') {
            $origen = strtoupper(trim((string) optional($user)->name));
        }

        $empresas = Empresa::query()
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre', 'sigla', 'codigo_cliente']);

        return view('paquetes_ems.entrega-no-registrada', [
            'origen' => $origen,
            'ciudades' => self::CIUDADES_BOLIVIA,
            'empresas' => $empresas,
            'provinciasPorDestino' => $this->buildProvinciasPorDestino(),
        ]);
    }

    public function storeEntregaNoRegistrada(Request $request)
    {
        $this->authorizeAnyPermission($request, [
            'feature.paquetes-ems.entregados.deliver',
            'feature.paquetes-ems.almacen.registercontract',
            'feature.paquetes-ems.contrato-rapido.create.save',
            'feature.paquetes-ems.contrato-rapido.create.create',
        ]);

        $esEmsInput = $request->boolean('es_ems');
        $origenInput = strtoupper(trim((string) $request->input('origen')));
        $fotoOpcional = ! $esEmsInput && $origenInput === 'LA PAZ';

        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50'],
            'es_ems' => ['nullable', 'boolean'],
            'resultado_entrega' => ['required', Rule::in(['entrega', 'intento', 'ida_vuelta'])],
            'recibido_por' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'foto' => [$fotoOpcional ? 'nullable' : 'required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,heic,heif'],
            'origen' => ['required', 'string', Rule::in(self::CIUDADES_BOLIVIA)],
            'destino' => ['required', 'string', Rule::in(self::CIUDADES_BOLIVIA)],
            'provincia' => ['nullable', 'string', 'max:255'],
            'peso' => ['required', 'numeric', 'min:0.001'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresa,id'],
        ], [], [
            'codigo' => 'codigo',
            'es_ems' => 'envio EMS',
            'resultado_entrega' => 'resultado',
            'recibido_por' => 'recibido por',
            'descripcion' => 'descripcion',
            'foto' => 'foto',
            'origen' => 'origen',
            'destino' => 'destino',
            'provincia' => 'provincia',
            'peso' => 'peso',
            'empresa_id' => 'empresa',
        ]);

        $user = $request->user();
        if (! $user) {
            abort(403, 'No autenticado.');
        }

        $codigo = strtoupper(trim((string) $data['codigo']));
        $codigo = preg_replace('/\s+/', '', $codigo) ?: '';

        if ($codigo === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'El codigo ingresado no es valido.');
        }

        $resultadoEntrega = (string) $data['resultado_entrega'];
        $estadoObjetivoNombre = match ($resultadoEntrega) {
            'intento' => 'DEVOLUCION',
            'ida_vuelta' => 'RECIBIDO',
            default => 'ENTREGADO',
        };
        $eventoObjetivoId = $resultadoEntrega === 'intento'
            ? self::EVENTO_ID_INTENTO_FALLIDO_ENTREGA
            : self::EVENTO_ID_PAQUETE_ENTREGADO_EXITOSAMENTE;
        $estadoObjetivoId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [$estadoObjetivoNombre])
            ->value('id') ?? 0);

        if ($estadoObjetivoId <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No existe el estado ' . $estadoObjetivoNombre . ' en la tabla estados.');
        }

        $eventoExiste = DB::table('eventos')
            ->where('id', $eventoObjetivoId)
            ->exists();

        if (! $eventoExiste) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No existe el evento con ID ' . $eventoObjetivoId . '.');
        }

        $existeEms = PaqueteEms::query()
            ->whereRaw('trim(upper(codigo)) = ?', [$codigo])
            ->exists();

        if ($existeEms) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No puedes registrar este codigo porque ya existe en paquetes EMS.');
        }

        $existeContrato = RecojoContrato::query()
            ->whereRaw('trim(upper(codigo)) = ?', [$codigo])
            ->exists();

        if ($existeContrato) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No puedes registrar este codigo porque ya existe en contratos.');
        }

        $esEms = (bool) ($data['es_ems'] ?? false);
        $imagenPath = $this->storeEntregaNoRegistradaPhoto($request);
        $recibidoPor = trim((string) $data['recibido_por']);
        $descripcionEntrega = trim((string) ($data['descripcion'] ?? ''));
        if ($descripcionEntrega === '') {
            $descripcionEntrega = match ($resultadoEntrega) {
                'intento' => 'INTENTO FALLIDO DE ENTREGA',
                'ida_vuelta' => 'PAQUETE IDA Y VUELTA RECIBIDO',
                default => 'PAQUETE ENTREGADO EXITOSAMENTE',
            };
        }
        $empresaIdDetectada = $this->resolveEmpresaIdByCodigoContrato($codigo);
        $empresaIdManual = !empty($data['empresa_id']) ? (int) $data['empresa_id'] : null;

        if (! $esEms && $empresaIdDetectada && $empresaIdManual && $empresaIdDetectada !== $empresaIdManual) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'El codigo ' . $codigo . ' ya esta asociado a otra empresa.');
        }

        if ($esEms) {
            $paqueteEms = null;
            $origen = strtoupper(trim((string) $data['origen']));

            DB::transaction(function () use (
                $codigo,
                $data,
                $user,
                $estadoObjetivoId,
                $eventoObjetivoId,
                $origen,
                $resultadoEntrega,
                $recibidoPor,
                $descripcionEntrega,
                $imagenPath,
                &$paqueteEms
            ) {
                $paqueteEms = PaqueteEms::query()->create([
                    'origen' => $origen,
                    'tipo_correspondencia' => 'EMS',
                    'servicio_especial' => null,
                    'contenido' => 'EMS',
                    'cantidad' => 1,
                    'peso' => (float) $data['peso'],
                    'codigo' => $codigo,
                    'cod_especial' => null,
                    'precio' => 0,
                    'nombre_remitente' => 'SIN REMITENTE',
                    'nombre_envia' => 'SIN REMITENTE',
                    'carnet' => 'SIN CARNET',
                    'telefono_remitente' => '-',
                    'nombre_destinatario' => 'SIN DESTINATARIO',
                    'telefono_destinatario' => '-',
                    'direccion' => 'SIN DIRECCION',
                    'referencia' => null,
                    'ciudad' => strtoupper(trim((string) $data['destino'])),
                    'tarifario_id' => null,
                    'estado_id' => $estadoObjetivoId,
                    'user_id' => (int) $user->id,
                    'imagen' => $resultadoEntrega === 'intento' ? null : $imagenPath,
                    'observacion' => 'ENVIO EMS NO REGISTRADO ENTREGADO DESDE EMS ENTREGADOS',
                ]);

                DB::table('eventos_ems')->insert([
                    'codigo' => $codigo,
                    'evento_id' => $eventoObjetivoId,
                    'user_id' => (int) $user->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('cartero')->insert([
                    'id_paquetes_ems' => (int) $paqueteEms->id,
                    'id_user' => (int) $user->id,
                    'id_estados' => $estadoObjetivoId,
                    'intento' => $resultadoEntrega === 'intento' ? 1 : 0,
                    'recibido_por' => $recibidoPor,
                    'descripcion' => $descripcionEntrega,
                    'imagen' => $resultadoEntrega === 'intento' ? null : $imagenPath,
                    'imagen_devolucion' => $resultadoEntrega === 'intento' ? $imagenPath : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

            return redirect()
                ->route($this->routeForEntregaNoRegistradaResultado($resultadoEntrega), ['q' => $codigo])
                ->with('success', 'Envio EMS no registrado guardado correctamente. Codigo: ' . $codigo . '.');
        }

        $contrato = null;
        $empresaId = $empresaIdDetectada ?: $empresaIdManual;
        $provincia = strtoupper(trim((string) ($data['provincia'] ?? '')));
        $origen = strtoupper(trim((string) $data['origen']));

        DB::transaction(function () use (
            $codigo,
            $data,
            $user,
            $estadoObjetivoId,
            $eventoObjetivoId,
            $origen,
            $empresaId,
            $empresaIdDetectada,
            $empresaIdManual,
            $provincia,
            $resultadoEntrega,
            $recibidoPor,
            $descripcionEntrega,
            $imagenPath,
            &$contrato
        ) {
            $contrato = RecojoContrato::query()->create([
                'user_id' => (int) $user->id,
                'empresa_id' => $empresaId,
                'codigo' => $codigo,
                'cod_especial' => null,
                'estados_id' => $estadoObjetivoId,
                'origen' => $origen,
                'destino' => strtoupper(trim((string) $data['destino'])),
                'nombre_r' => 'SIN REMITENTE',
                'telefono_r' => '-',
                'contenido' => 'CONTRATO',
                'cantidad' => '1',
                'direccion_r' => 'SIN DIRECCION',
                'nombre_d' => 'SIN DESTINATARIO',
                'telefono_d' => null,
                'direccion_d' => 'SIN DIRECCION',
                'mapa' => null,
                'provincia' => $provincia !== '' ? $provincia : null,
                'peso' => (float) $data['peso'],
                'fecha_recojo' => now(),
                'observacion' => 'ENVIO NO REGISTRADO ENTREGADO DESDE EMS ENTREGADOS',
                'justificacion' => null,
                'imagen' => $resultadoEntrega === 'intento' ? null : $imagenPath,
            ]);

            if (! $empresaIdDetectada && ! empty($empresaIdManual)) {
                $registroCodigoEmpresa = CodigoEmpresa::query()
                    ->whereRaw('trim(upper(codigo)) = ?', [$codigo])
                    ->first();

                if (! $registroCodigoEmpresa) {
                    CodigoEmpresa::query()->create([
                        'codigo' => $codigo,
                        'barcode' => $codigo,
                        'empresa_id' => (int) $empresaIdManual,
                    ]);
                }
            }

            DB::table('eventos_contrato')->insert([
                'codigo' => $codigo,
                'evento_id' => $eventoObjetivoId,
                'user_id' => (int) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('cartero')->insert([
                'id_paquetes_contrato' => (int) $contrato->id,
                'id_user' => (int) $user->id,
                'id_estados' => $estadoObjetivoId,
                'intento' => $resultadoEntrega === 'intento' ? 1 : 0,
                'recibido_por' => $recibidoPor,
                'descripcion' => $descripcionEntrega,
                'imagen' => $resultadoEntrega === 'intento' ? null : $imagenPath,
                'imagen_devolucion' => $resultadoEntrega === 'intento' ? $imagenPath : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route($this->routeForEntregaNoRegistradaResultado($resultadoEntrega), ['q' => $codigo])
            ->with('success', 'Envio no registrado guardado correctamente. Codigo: ' . $codigo . '.');
    }

    private function routeForEntregaNoRegistradaResultado(string $resultadoEntrega): string
    {
        return match ($resultadoEntrega) {
            'intento' => 'paquetes-ems.devueltos',
            'ida_vuelta' => 'paquetes-ems.almacen',
            default => 'paquetes-ems.entregados',
        };
    }

    public function createRegistroRapidoContrato()
    {
        $user = Auth::user();
        abort_if(!$user, 403, 'No autenticado.');
        $canRegisterQuickContractFromAlmacen = $user->can('feature.paquetes-ems.almacen.registercontract');

        $origen = strtoupper(trim((string) optional($user)->ciudad));
        if ($origen === '') {
            $origen = strtoupper(trim((string) optional($user)->name));
        }

        $empresas = Empresa::query()
            ->orderBy('nombre')
            ->orderBy('id')
            ->get(['id', 'nombre', 'sigla', 'codigo_cliente']);

        return view('paquetes_ems.registro-rapido-contrato', [
            'origen' => $origen,
            'ciudades' => self::CIUDADES_BOLIVIA,
            'empresas' => $empresas,
            'empresasCount' => $empresas->count(),
            'provinciasPorDestino' => $this->buildProvinciasPorDestino(),
            'listado' => [],
            'canQuickContractCreate' => $canRegisterQuickContractFromAlmacen
                || $user->can('feature.paquetes-ems.contrato-rapido.create.create'),
            'canQuickContractSave' => $canRegisterQuickContractFromAlmacen
                || $user->can('feature.paquetes-ems.contrato-rapido.create.save')
                || $user->can('feature.paquetes-ems.contrato-rapido.create.create'),
            'canQuickContractDelete' => $canRegisterQuickContractFromAlmacen
                || $user->can('feature.paquetes-ems.contrato-rapido.create.delete'),
        ]);
    }

    private function storeEntregaNoRegistradaPhoto(Request $request): ?string
    {
        if (! $request->hasFile('foto')) {
            return null;
        }

        return $request->file('foto')->store('carteros/entregas', 'public');
    }

    public function storeRegistroRapidoContrato(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        abort_unless(
            $user->can('feature.paquetes-ems.contrato-rapido.create.save')
                || $user->can('feature.paquetes-ems.contrato-rapido.create.create')
                || $user->can('feature.paquetes-ems.almacen.registercontract'),
            403,
            'No tienes permiso para guardar contratos rapidos.'
        );

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.codigo' => 'required|string|max:50',
            'items.*.destino' => ['required', 'string', Rule::in(self::CIUDADES_BOLIVIA)],
            'items.*.provincia' => 'nullable|string|max:255',
            'items.*.cantidad' => 'nullable|string|max:255',
            'items.*.peso' => 'required|numeric|min:0.001',
            'items.*.empresa_id' => 'nullable|integer|exists:empresa,id',
        ], [], [
            'items' => 'prelista',
            'items.*.codigo' => 'codigo',
            'items.*.destino' => 'destino',
            'items.*.provincia' => 'provincia',
            'items.*.cantidad' => 'cantidad',
            'items.*.peso' => 'peso',
            'items.*.empresa_id' => 'empresa',
        ]);

        $estadoAlmacenId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ALMACEN'])
            ->value('id') ?? 0);

        if ($estadoAlmacenId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No existe el estado ALMACEN en la tabla estados.',
            ], 422);
        }

        $eventoExiste = DB::table('eventos')
            ->where('id', self::EVENTO_ID_CONTRATO_RECOGIDO)
            ->exists();

        if (!$eventoExiste) {
            return response()->json([
                'success' => false,
                'message' => 'No existe el evento con ID ' . self::EVENTO_ID_CONTRATO_RECOGIDO . ' en la tabla eventos.',
            ], 422);
        }

        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? 'ORIGEN')));
        }

        $items = collect($validated['items'])
            ->map(function (array $item) {
                $codigo = strtoupper(trim((string) ($item['codigo'] ?? '')));
                $codigo = preg_replace('/\s+/', '', $codigo) ?: '';
                $provincia = strtoupper(trim((string) ($item['provincia'] ?? '')));
                return [
                    'codigo' => $codigo,
                    'destino' => strtoupper(trim((string) ($item['destino'] ?? ''))),
                    'provincia' => $provincia === '' ? null : $provincia,
                    'cantidad' => trim((string) ($item['cantidad'] ?? '')),
                    'peso' => (float) ($item['peso'] ?? 0),
                    'empresa_id' => !empty($item['empresa_id']) ? (int) $item['empresa_id'] : null,
                ];
            })
            ->values();

        if ($items->contains(fn (array $item) => $item['codigo'] === '')) {
            return response()->json([
                'success' => false,
                'message' => 'Hay codigos invalidos en la prelista.',
            ], 422);
        }

        $duplicadosPrelista = $items
            ->groupBy('codigo')
            ->filter(fn ($grupo) => $grupo->count() > 1)
            ->keys()
            ->values()
            ->all();

        if (!empty($duplicadosPrelista)) {
            return response()->json([
                'success' => false,
                'message' => 'Hay codigos repetidos en la prelista: ' . implode(', ', $duplicadosPrelista),
            ], 422);
        }

        $codigos = $items->pluck('codigo')->all();
        $existentesEms = PaqueteEms::query()
            ->where(function ($query) use ($codigos) {
                foreach ($codigos as $codigo) {
                    $query->orWhereRaw('trim(upper(codigo)) = ?', [$codigo]);
                }
            })
            ->pluck('codigo')
            ->map(fn ($codigo) => strtoupper(trim((string) $codigo)))
            ->unique()
            ->values()
            ->all();

        if (!empty($existentesEms)) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes registrar codigos que ya existen en paquetes EMS: ' . implode(', ', $existentesEms),
            ], 422);
        }

        $existentes = RecojoContrato::query()
            ->where(function ($query) use ($codigos) {
                foreach ($codigos as $codigo) {
                    $query->orWhereRaw('trim(upper(codigo)) = ?', [$codigo]);
                }
            })
            ->pluck('codigo')
            ->map(fn ($codigo) => strtoupper(trim((string) $codigo)))
            ->unique()
            ->values()
            ->all();

        if (!empty($existentes)) {
            return response()->json([
                'success' => false,
                'message' => 'Estos codigos ya existen: ' . implode(', ', $existentes),
            ], 422);
        }

        foreach ($items as $item) {
            $empresaIdDetectada = $this->resolveEmpresaIdByCodigoContrato((string) $item['codigo']);
            $empresaIdManual = !empty($item['empresa_id']) ? (int) $item['empresa_id'] : null;

            if ($empresaIdDetectada && $empresaIdManual && $empresaIdDetectada !== $empresaIdManual) {
                return response()->json([
                    'success' => false,
                    'message' => 'El codigo ' . $item['codigo'] . ' ya esta asociado a otra empresa.',
                ], 422);
            }
        }

        $creados = collect();
        $eventRows = [];
        DB::transaction(function () use ($items, $user, $estadoAlmacenId, $origen, &$creados, &$eventRows) {
            foreach ($items as $item) {
                $empresaIdDetectada = $this->resolveEmpresaIdByCodigoContrato($item['codigo']);
                $empresaIdManual = !empty($item['empresa_id']) ? (int) $item['empresa_id'] : null;

                $empresaId = $empresaIdDetectada ?: $empresaIdManual;
                $contrato = RecojoContrato::query()->create([
                    'user_id' => (int) $user->id,
                    'empresa_id' => $empresaId,
                    'codigo' => $item['codigo'],
                    'cod_especial' => null,
                    'estados_id' => $estadoAlmacenId,
                    'origen' => $origen,
                    'destino' => $item['destino'],
                    'nombre_r' => 'SIN REMITENTE',
                    'telefono_r' => '-',
                    'contenido' => 'CONTRATO',
                    'cantidad' => $item['cantidad'] !== '' ? $item['cantidad'] : null,
                    'direccion_r' => 'SIN DIRECCION',
                    'nombre_d' => 'SIN DESTINATARIO',
                    'telefono_d' => null,
                    'direccion_d' => 'SIN DIRECCION',
                    'mapa' => null,
                    'provincia' => $item['provincia'],
                    'peso' => $item['peso'],
                    'fecha_recojo' => now(),
                    'observacion' => 'REGISTRO RAPIDO DESDE ALMACEN EMS',
                    'justificacion' => null,
                    'imagen' => null,
                ]);

                if (!$empresaIdDetectada && !empty($empresaIdManual)) {
                    $codigoNormalizado = strtoupper(trim((string) $item['codigo']));
                    $registroCodigoEmpresa = CodigoEmpresa::query()
                        ->whereRaw('trim(upper(codigo)) = ?', [$codigoNormalizado])
                        ->first();

                    if (!$registroCodigoEmpresa) {
                        CodigoEmpresa::query()->create([
                            'codigo' => $codigoNormalizado,
                            'barcode' => $codigoNormalizado,
                            'empresa_id' => (int) $empresaIdManual,
                        ]);
                    }
                }

                $creados->push([
                    'id' => (int) $contrato->id,
                    'codigo' => (string) $contrato->codigo,
                    'cantidad' => (string) ($contrato->cantidad ?? ''),
                    'peso' => (string) $contrato->peso,
                    'origen' => (string) $contrato->origen,
                    'destino' => (string) $contrato->destino,
                    'empresa_id' => $empresaId ? (int) $empresaId : null,
                    'reporte_url' => route('paquetes-contrato.reporte', $contrato->id),
                ]);

                $codigoEvento = trim((string) $contrato->codigo);
                if ($codigoEvento !== '') {
                    $now = now();
                    $eventRows[] = [
                        'codigo' => $codigoEvento,
                        'evento_id' => self::EVENTO_ID_CONTRATO_RECOGIDO,
                        'user_id' => (int) $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($eventRows)) {
                DB::table('eventos_contrato')->insert($eventRows);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Se guardaron ' . $creados->count() . ' contrato(s) correctamente.',
            'items' => $creados->values()->all(),
        ]);
    }

    protected function resolveEmpresaIdByCodigoContrato(string $codigo): ?int
    {
        $codigoNormalizado = strtoupper(trim((string) $codigo));
        if ($codigoNormalizado === '') {
            return null;
        }

        $empresaIdPorCodigo = CodigoEmpresa::query()
            ->whereRaw('trim(upper(codigo)) = ?', [$codigoNormalizado])
            ->value('empresa_id');

        if (!empty($empresaIdPorCodigo)) {
            return (int) $empresaIdPorCodigo;
        }

        if (preg_match('/^C([A-Z0-9]+)A\d{5}BO$/', $codigoNormalizado, $matches)) {
            $codigoCliente = strtoupper(trim((string) ($matches[1] ?? '')));
            if ($codigoCliente !== '') {
                $empresaIdPorCliente = Empresa::query()
                    ->whereRaw('trim(upper(codigo_cliente)) = ?', [$codigoCliente])
                    ->value('id');

                if (!empty($empresaIdPorCliente)) {
                    return (int) $empresaIdPorCliente;
                }
            }
        }

        return null;
    }

    protected function buildProvinciasPorDestino(): array
    {
        return self::PROVINCIAS_POR_DEPARTAMENTO;
    }

    private function resolveTarifarioYPrecio(int $servicioExtraId, string $origen, int $destinoId, float $peso, bool $pagoDestinatario = false): array
    {
        $origenNombre = strtoupper(trim($origen));

        $origenId = (int) (Origen::query()
            ->whereRaw('trim(upper(nombre_origen)) = ?', [$origenNombre])
            ->value('id') ?? 0);

        if ($origenId <= 0) {
            throw new \RuntimeException('No existe el origen ' . $origenNombre . ' en la tabla origen.');
        }

        $tarifario = TarifarioTiktoker::query()
            ->where('origen_id', $origenId)
            ->where('destino_id', $destinoId)
            ->where('servicio_extra_id', $servicioExtraId)
            ->first();

        if (!$tarifario) {
            throw new \RuntimeException('No existe tarifario tiktoker para el servicio, origen y destino seleccionados.');
        }

        return [$tarifario, $this->calculatePrecioTiktoker($tarifario, $peso, $pagoDestinatario)];
    }

    private function calculatePrecioTiktoker(TarifarioTiktoker $tarifario, float $peso, bool $pagoDestinatario = false): float
    {
        if ($peso <= 2.000) {
            $precioBase = (float) $tarifario->peso1;
        } elseif ($peso <= 5.000) {
            $precioBase = (float) $tarifario->peso2;
        } else {
            $bloquesExtra = (int) ceil($peso - 5);
            $precioBase = (float) $tarifario->peso2 + ($bloquesExtra * (float) $tarifario->peso_extra);
        }

        if ($pagoDestinatario) {
            $precioBase += 2.50;
        }

        return round($precioBase, 2);
    }

    private function nullableTrim(?string $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function isPuertaAVentanillaService(?ServicioExtra $servicioExtra): bool
    {
        if (!$servicioExtra) {
            return false;
        }

        $text = $this->normalizeServiceText(
            (string) $servicioExtra->nombre . ' ' . (string) $servicioExtra->descripcion
        );

        return str_contains($text, 'puerta a ventanilla');
    }

    private function normalizeServiceText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $value
        );

        return preg_replace('/\s+/', ' ', $value) ?: '';
    }

    private function findEstadoIdByNombre(string $nombre): int
    {
        return (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', [mb_strtoupper(trim($nombre))])
            ->value('id') ?? 0);
    }

    private function moveEncargadoRecordAndRegisterEvent(
        $record,
        string $stateColumn,
        int $targetState,
        string $servicio,
        int $userId,
        int|string $eventReference
    ): int {
        if (!$record) {
            return 0;
        }

        $record->{$stateColumn} = $targetState;
        $record->updated_at = now();
        $saved = $record->save();

        if (!$saved) {
            return 0;
        }

        $this->registerEncargadoEvent($servicio, $record, $userId, $eventReference);

        return 1;
    }

    private function registerEncargadoEvent(string $servicio, $record, int $userId, int|string $eventReference): void
    {
        if ($userId <= 0 || !$record) {
            return;
        }

        $codigo = $this->resolveCodigoForEncargadoRecord($servicio, $record);
        if ($codigo === '') {
            return;
        }

        $eventoId = is_int($eventReference)
            ? $eventReference
            : $this->resolveEventIdByName($eventReference);

        if ($eventoId <= 0) {
            return;
        }

        $table = $this->resolveEventTableForEncargadoService($servicio);
        $payload = [
            'codigo' => $codigo,
            'evento_id' => $eventoId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($table === 'eventos_tiktoker') {
            $payload['cliente_id'] = null;
        }

        DB::table($table)->insert($payload);
    }

    private function resolveCodigoForEncargadoRecord(string $servicio, $record): string
    {
        if ($servicio === 'SOLICITUD') {
            return trim((string) ($record->codigo_solicitud ?? $record->barcode ?? ''));
        }

        return trim((string) ($record->codigo ?? ''));
    }

    private function resolveEventTableForEncargadoService(string $servicio): string
    {
        return match ($servicio) {
            'EMS' => 'eventos_ems',
            'CONTRATO' => 'eventos_contrato',
            'CERTI' => 'eventos_certi',
            'ORDI' => 'eventos_ordi',
            'SOLICITUD' => 'eventos_tiktoker',
            default => 'eventos_ems',
        };
    }

    private function resolveEventIdByName(string $nombreEvento): int
    {
        $nombreEvento = trim($nombreEvento);

        if ($nombreEvento === '') {
            return 0;
        }

        $existingId = (int) (DB::table('eventos')
            ->whereRaw('trim(upper(nombre_evento)) = ?', [mb_strtoupper($nombreEvento)])
            ->value('id') ?? 0);

        if ($existingId > 0) {
            return $existingId;
        }

        return (int) DB::table('eventos')->insertGetId([
            'nombre_evento' => $nombreEvento,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function successMessageForDevolucion(string $servicio, string $destinoAccion): string
    {
        if (in_array($servicio, ['CERTI', 'ORDI'], true)) {
            return 'El envio fue devuelto a VENTANILLA y se registro su evento.';
        }

        return match ($destinoAccion) {
            'origen' => 'El envio fue devuelto a ALMACEN de origen y se registro su evento.',
            'destino' => 'El envio fue devuelto a ALMACEN de destino en estado RECIBIDO y se registro su evento.',
            default => 'El envio fue actualizado y se registro su evento.',
        };
    }

    private function updateEncargadoPesoAndRegisterEvent($record, float $peso, string $servicio, int $userId): int
    {
        if (!$record) {
            return 0;
        }

        $record->peso = $peso;
        $record->updated_at = now();
        $saved = $record->save();

        if (!$saved) {
            return 0;
        }

        $this->registerEncargadoEvent(
            $servicio,
            $record,
            $userId,
            $servicio === 'SOLICITUD'
                ? 'Peso de solicitud actualizado desde encargado.'
                : 'Peso de envio actualizado desde encargado.'
        );

        return 1;
    }

    private function registrarEventosTiktoker(iterable $solicitudes, int $userId, int $eventoId): void
    {
        if ($userId <= 0 || $eventoId <= 0) {
            return;
        }

        $now = now();

        $rows = collect($solicitudes)
            ->map(function ($solicitud) use ($userId, $eventoId, $now) {
                $codigo = trim((string) ($solicitud->codigo_solicitud ?? $solicitud->barcode ?? ''));
                if ($codigo === '') {
                    return null;
                }

                return [
                    'codigo' => $codigo,
                    'evento_id' => $eventoId,
                    'user_id' => $userId,
                    'cliente_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if (!empty($rows)) {
            DB::table('eventos_tiktoker')->insert($rows);
        }
    }

    private function authorizeAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'No autenticado.');
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para acceder a esta ventana o accion.');
    }

    private function nextCorrelativoEmpresa(int $empresaId, string $codigoCliente): int
    {
        $cliente = $this->normalizarCodigoClienteEmpresa($codigoCliente);
        $pattern = '/^C'.preg_quote($cliente, '/').'A(\d{5})BO$/';
        $prefix = 'C'.$cliente.'A';
        $empresaIds = $this->empresaIdsConMismoCodigoCliente($empresaId, $cliente);
        $max = 0;

        $codigosEmpresa = CodigoEmpresa::query()
            ->whereIn('empresa_id', $empresaIds)
            ->where(function ($query) use ($prefix) {
                $query->where('codigo', 'like', $prefix.'%BO')
                    ->orWhere('barcode', 'like', $prefix.'%BO');
            })
            ->get(['codigo', 'barcode'])
            ->flatMap(fn ($row) => [$row->codigo, $row->barcode]);

        foreach ($codigosEmpresa as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $codigosContrato = RecojoContrato::query()
            ->where('codigo', 'like', $prefix.'%BO')
            ->pluck('codigo');

        foreach ($codigosContrato as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    private function normalizarCodigoClienteEmpresa(string $codigoCliente): string
    {
        $cliente = strtoupper(trim($codigoCliente));

        return preg_replace('/\s+/', '', $cliente) ?: '';
    }

    private function empresaIdsConMismoCodigoCliente(int $empresaId, string $codigoCliente): array
    {
        $cliente = $this->normalizarCodigoClienteEmpresa($codigoCliente);

        if ($cliente === '') {
            return [$empresaId];
        }

        $ids = Empresa::query()
            ->whereRaw("REPLACE(TRIM(UPPER(COALESCE(codigo_cliente, ''))), ' ', '') = ?", [$cliente])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return !empty($ids) ? $ids : [$empresaId];
    }

    private function insertCodigoContinuacionEvents(string $codigoMadre, string $codigoHijo, int $userId): void
    {
        $codigoMadre = strtoupper(trim($codigoMadre));
        $codigoHijo = strtoupper(trim($codigoHijo));

        if ($codigoMadre === '' || $codigoHijo === '' || $userId <= 0) {
            return;
        }

        $eventoMadreId = (int) DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Se genero el codigo hijo ' . $codigoHijo . ' como continuacion de este codigo madre.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoHijoId = (int) DB::table('eventos')->insertGetId([
            'nombre_evento' => 'Este codigo es la continuacion del codigo madre ' . $codigoMadre . '.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('eventos_contrato')->insert([
            'codigo' => $codigoHijo,
            'evento_id' => $eventoHijoId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tablaMadre = $this->resolveEventTableForCodigo($codigoMadre);
        DB::table($tablaMadre)->insert([
            'codigo' => $codigoMadre,
            'evento_id' => $eventoMadreId,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function resolveEventTableForCodigo(string $codigo): string
    {
        $codigo = strtoupper(trim($codigo));

        if ($codigo === '') {
            return 'eventos_contrato';
        }

        if (PaqueteEms::query()->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigo])->exists()) {
            return 'eventos_ems';
        }

        if (PaqueteCerti::query()->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigo])->exists()) {
            return 'eventos_certi';
        }

        if (PaqueteOrdi::query()->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigo])->exists()) {
            return 'eventos_ordi';
        }

        if (RecojoContrato::query()->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigo])->exists()) {
            return 'eventos_contrato';
        }

        if (DB::table('eventos_ems')->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigo])->exists()) {
            return 'eventos_ems';
        }

        if (DB::table('eventos_certi')->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigo])->exists()) {
            return 'eventos_certi';
        }

        if (DB::table('eventos_ordi')->whereRaw('TRIM(UPPER(codigo)) = ?', [$codigo])->exists()) {
            return 'eventos_ordi';
        }

        return 'eventos_contrato';
    }

    private function buildCodigoEmpresa(string $codigoCliente, int $correlativo): string
    {
        return 'C'.$this->normalizarCodigoClienteEmpresa($codigoCliente).'A'.str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT).'BO';
    }
}


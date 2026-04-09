<?php

namespace App\Http\Controllers;

use App\Models\CodigoEmpresa;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Origen;
use App\Models\PaqueteEms;
use App\Models\Recojo as RecojoContrato;
use App\Models\Destino;
use App\Models\ServicioExtra;
use App\Models\SolicitudCliente;
use App\Models\TarifarioTiktoker;
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
    private const EVENTO_ID_TIKTOKER_SOLICITUD_CREADA = 295;

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
            'CARRASCO', 'MIZQUE', 'PUNATA', 'BOLIVAR', 'TIRAQUE',
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
        ],
        'TARIJA' => [
            'CERCADO', 'ANICETO ARCE', 'BURDETT OCONNOR', 'GRAN CHACO',
            'JOSE MARIA AVILES', 'MENDEZ',
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
                    self::EVENTO_ID_TIKTOKER_SOLICITUD_CREADA
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
            'direccion_entrega' => $solicitud->direccion,
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
            'direccion_entrega' => ['required', 'string', 'max:255'],
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
            'direccion' => trim((string) $data['direccion_entrega']),
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

            return redirect()
                ->route('paquetes-ems.solicitudes.ticket', $solicitud);
        }

        $solicitud = SolicitudCliente::query()->create(array_merge($payload, [
            'cliente_id' => null,
        ]));

        $codigoSolicitud = 'SOL' . str_pad((string) $solicitud->id, 8, '0', STR_PAD_LEFT);

        $solicitud->update([
            'codigo_solicitud' => $codigoSolicitud,
            'barcode' => $codigoSolicitud,
        ]);

        $actorUserId = (int) optional(Auth::user())->id;
        if ($actorUserId > 0) {
            $this->registrarEventosTiktoker(
                [$solicitud],
                $actorUserId,
                self::EVENTO_ID_TIKTOKER_SOLICITUD_CREADA
            );
        }

        return redirect()
            ->route('paquetes-ems.solicitudes.ticket', $solicitud);
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
            'return_query' => ['nullable', 'string', 'max:255'],
        ], [], [
            'empresa_id' => 'empresa',
            'origen' => 'origen',
            'destino' => 'destino',
            'direccion_r' => 'origen direccion',
            'direccion_d' => 'destino direccion',
            'peso' => 'peso',
            'observacion' => 'observacion',
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

            $contrato = RecojoContrato::query()->create([
                'user_id' => (int) $user->id,
                'empresa_id' => (int) $empresa->id,
                'codigo' => $codigo,
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

    public function createRegistroRapidoContrato()
    {
        $user = Auth::user();
        abort_if(!$user, 403, 'No autenticado.');
        $canRegisterQuickContractFromAlmacen = $user->can('feature.paquetes-ems.almacen.registercontract');

        $origen = strtoupper(trim((string) optional($user)->ciudad));
        if ($origen === '') {
            $origen = strtoupper(trim((string) optional($user)->name));
        }

        return view('paquetes_ems.registro-rapido-contrato', [
            'origen' => $origen,
            'ciudades' => self::CIUDADES_BOLIVIA,
            'empresas' => Empresa::query()
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'sigla', 'codigo_cliente']),
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
        if ($peso <= 0.500) {
            $precioBase = (float) $tarifario->peso1;
        } elseif ($peso <= 2.000) {
            $precioBase = (float) $tarifario->peso2;
        } elseif ($peso <= 5.000) {
            $precioBase = (float) $tarifario->peso3;
        } else {
            $bloquesExtra = (int) ceil($peso - 5);
            $precioBase = (float) $tarifario->peso3 + ($bloquesExtra * (float) $tarifario->peso_extra);
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
        $cliente = strtoupper(trim($codigoCliente));
        $pattern = '/^C'.preg_quote($cliente, '/').'A(\d{5})BO$/';
        $prefix = 'C'.$cliente.'A';
        $max = 0;

        $codigosEmpresa = CodigoEmpresa::query()
            ->where('empresa_id', $empresaId)
            ->pluck('codigo');

        foreach ($codigosEmpresa as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $codigosContrato = RecojoContrato::query()
            ->where('empresa_id', $empresaId)
            ->where('codigo', 'like', $prefix.'%BO')
            ->pluck('codigo');

        foreach ($codigosContrato as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max + 1;
    }

    private function buildCodigoEmpresa(string $codigoCliente, int $correlativo): string
    {
        return 'C'.$codigoCliente.'A'.str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT).'BO';
    }
}


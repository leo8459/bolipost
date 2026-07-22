<?php

namespace App\Http\Controllers;

use App\Exports\Cn33DespachoExport;
use App\Exports\ContratoCn33Export;
use App\Models\CodigoEmpresa;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\PaqueteEms;
use App\Models\Recojo;
use App\Models\SolicitudCliente;
use App\Models\TarifaContrato;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class RecojoController extends Controller
{
    private const EVENTO_ID_CONTRATO_CREADO = 318;
    private const DEPARTAMENTOS = [
        'LA PAZ',
        'COCHABAMBA',
        'SANTA CRUZ',
        'ORURO',
        'POTOSI',
        'TARIJA',
        'SUCRE',
        'TRINIDAD',
        'COBIJA',
    ];

    public function index()
    {
        return view('paquetes_contrato.index');
    }

    public function recogerEnvios()
    {
        return view('paquetes_contrato.recoger-envios');
    }

    public function almacen()
    {
        return view('paquetes_contrato.almacen');
    }

    public function cartero()
    {
        return view('paquetes_contrato.cartero');
    }

    public function reimprimirCn33(Request $request)
    {
        $this->authorizeAnyPermission($request, $this->cn33PrintPermissions());

        $despacho = strtoupper(trim((string) $request->query('despacho', '')));
        $origen = strtoupper(trim((string) $request->query('origen', '')));
        $destino = strtoupper(trim((string) $request->query('destino', '')));
        $data = $this->cn33DataForDespacho($despacho, $origen, $destino);

        return view('paquetes_contrato.reimprimir-cn33', [
            'despacho' => $despacho,
            'origen' => $origen,
            'destino' => $destino,
            'origenes' => $this->cn33FilterOptions('origen', $despacho),
            'destinos' => $this->cn33FilterOptions('destino', $despacho),
            'paquetes' => $data['rows'],
            'totalCantidad' => $data['totalCantidad'],
            'totalPeso' => $data['totalPeso'],
        ]);
    }

    public function reimprimirCn33DespachoPdf(Request $request)
    {
        $this->authorizeAnyPermission($request, $this->cn33PrintPermissions());

        $despacho = strtoupper(trim((string) $request->query('despacho', '')));
        if ($despacho === '') {
            return redirect()
                ->route('dashboard.reimprimir-cn33')
                ->with('error', 'Ingresa el codigo de despacho para reimprimir CN-33.');
        }

        $origen = strtoupper(trim((string) $request->query('origen', '')));
        $destino = strtoupper(trim((string) $request->query('destino', '')));
        $data = $this->cn33DataForDespacho($despacho, $origen, $destino);
        if ($data['rows']->isEmpty()) {
            return redirect()
                ->route('dashboard.reimprimir-cn33', ['despacho' => $despacho, 'origen' => $origen, 'destino' => $destino])
                ->with('error', 'No se encontraron paquetes/contratos/solicitudes para el despacho ' . $despacho . '.');
        }

        $generatedAt = $data['generatedAt'];
        $loggedUserName = trim((string) optional(Auth::user())->name);

        $pdf = Pdf::loadView('paquetes_ems.reporte-regional', [
            'paquetes' => $data['rows'],
            'generatedAt' => $generatedAt,
            'currentManifiesto' => $despacho,
            'loggedInUserCity' => $data['originCity'] !== '' ? $data['originCity'] : 'N/A',
            'destinationCity' => $data['destinationCity'] !== '' ? $data['destinationCity'] : 'N/A',
            'selectedTransport' => 'N/A',
            'numeroVuelo' => '-',
            'loggedUserName' => $loggedUserName !== '' ? $loggedUserName : 'Usuario del sistema',
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'cn33-' . $despacho . '-reimpresion.pdf');
    }

    public function reimprimirCn33DespachoExcel(Request $request)
    {
        $this->authorizeAnyPermission($request, $this->cn33PrintPermissions());

        $despacho = strtoupper(trim((string) $request->query('despacho', '')));
        if ($despacho === '') {
            return redirect()
                ->route('dashboard.reimprimir-cn33')
                ->with('error', 'Ingresa el codigo de despacho para exportar el CN-33.');
        }

        $origen = strtoupper(trim((string) $request->query('origen', '')));
        $destino = strtoupper(trim((string) $request->query('destino', '')));
        $data = $this->cn33DataForDespacho($despacho, $origen, $destino);

        if ($data['rows']->isEmpty()) {
            return redirect()
                ->route('dashboard.reimprimir-cn33', ['despacho' => $despacho, 'origen' => $origen, 'destino' => $destino])
                ->with('error', 'No hay registros para exportar con esos filtros.');
        }

        return Excel::download(
            new Cn33DespachoExport(
                $data['rows'],
                $despacho,
                $data['generatedAt'],
                $data['originCity'] !== '' ? $data['originCity'] : 'N/A',
                $data['destinationCity'] !== '' ? $data['destinationCity'] : 'N/A',
                $origen,
                $destino
            ),
            'cn33-' . $despacho . '-reimpresion.xlsx'
        );
    }

    public function reimprimirCn33Excel(Request $request, Recojo $contrato)
    {
        $this->authorizeAnyPermission($request, $this->cn33PrintPermissions());

        $generatedAt = now();
        $contrato->loadMissing(['empresa:id,nombre,sigla', 'user.empresa:id,nombre,sigla']);

        return Excel::download(
            new ContratoCn33Export($contrato, $generatedAt, $this->verificationUrlFor($contrato)),
            'cn33-' . $contrato->codigo . '-' . $generatedAt->format('Ymd-His') . '.xlsx'
        );
    }

    public function gestor(Request $request)
    {
        $user = Auth::user();
        $empresaId = (int) ($user?->empresa_id ?? 0);
        $search = trim((string) $request->query('q', ''));
        $estadoId = (int) $request->query('estado_id', 0);
        $empresa = $empresaId > 0
            ? Empresa::query()->find($empresaId, ['id', 'nombre', 'sigla', 'codigo_cliente'])
            : null;

        $baseQuery = Recojo::query()
            ->with([
                'empresa:id,nombre,sigla,codigo_cliente',
                'estadoRegistro:id,nombre_estado',
            ])
            ->where('empresa_id', $empresaId > 0 ? $empresaId : -1);

        $contratos = (clone $baseQuery)
            ->when($estadoId > 0, function ($query) use ($estadoId) {
                $query->where('estados_id', $estadoId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($sub) use ($search) {
                    $sub->where('codigo', 'like', '%' . $search . '%')
                        ->orWhere('cod_especial', 'like', '%' . $search . '%')
                        ->orWhere('nombre_r', 'like', '%' . $search . '%')
                        ->orWhere('telefono_r', 'like', '%' . $search . '%')
                        ->orWhere('direccion_r', 'like', '%' . $search . '%')
                        ->orWhere('nombre_d', 'like', '%' . $search . '%')
                        ->orWhere('telefono_d', 'like', '%' . $search . '%')
                        ->orWhere('direccion_d', 'like', '%' . $search . '%')
                        ->orWhere('origen', 'like', '%' . $search . '%')
                        ->orWhere('destino', 'like', '%' . $search . '%')
                        ->orWhere('provincia', 'like', '%' . $search . '%')
                        ->orWhere('contenido', 'like', '%' . $search . '%');
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $estadoCounts = (clone $baseQuery)
            ->select('estados_id', DB::raw('count(*) as total'))
            ->groupBy('estados_id')
            ->pluck('total', 'estados_id');

        $totalContratos = (clone $baseQuery)->count();
        $totalPeso = (float) ((clone $baseQuery)->sum('peso') ?? 0);
        $estados = Cache::remember('lookup:paquetes-contrato:estados', now()->addMinutes(30), function () {
            return Estado::query()
                ->orderBy('nombre_estado')
                ->get(['id', 'nombre_estado']);
        });

        return view('paquetes_contrato.gestor', [
            'contratos' => $contratos,
            'empresa' => $empresa,
            'search' => $search,
            'estadoId' => $estadoId,
            'estados' => $estados,
            'estadoCounts' => $estadoCounts,
            'totalContratos' => $totalContratos,
            'totalPeso' => $totalPeso,
            'canContratoGestorPrint' => (bool) optional($user)->can('feature.paquetes-contrato.index.print'),
        ]);
    }

    public function entregados(Request $request)
    {
        $user = Auth::user();
        [$fechaDesde, $fechaHasta] = $this->resolveEntregadosDateRange($request);

        $contratos = $this->entregadosQueryForUser($user, $fechaDesde, $fechaHasta)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $stats = $this->buildEntregadosStats($user, $fechaDesde, $fechaHasta);

        return view('paquetes_contrato.entregados', [
            'contratos' => $contratos,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'stats' => $stats,
            'canContratoEntregadoPrint' => (bool) optional($user)->can('feature.paquetes-contrato.entregados.print'),
            'canContratoEntregadoExport' => (bool) optional($user)->can('feature.paquetes-contrato.entregados.export'),
        ]);

    // Si el usuario no tiene empresa, no mostramos nada
    if ($empresaId <= 0) {
        $contratos = Recojo::query()->whereRaw('1=0')->paginate(15);

        return view('paquetes_contrato.entregados', [
            'contratos' => $contratos,
            'canContratoEntregadoPrint' => (bool) optional($user)->can('feature.paquetes-contrato.entregados.print'),
            'canContratoEntregadoExport' => (bool) optional($user)->can('feature.paquetes-contrato.entregados.export'),
        ]);
    }

    // Buscar el ID del estado ENTREGADO (sin importar mayÃºsculas/espacios)
    $estadoEntregadoId = (int) (Estado::query()
        ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
        ->value('id') ?? 0);

    // Si no existe ese estado, no mostramos nada (evita bugs silenciosos)
    if ($estadoEntregadoId <= 0) {
        $contratos = Recojo::query()->whereRaw('1=0')->paginate(15);

        return view('paquetes_contrato.entregados', [
            'contratos' => $contratos,
            'canContratoEntregadoPrint' => (bool) optional($user)->can('feature.paquetes-contrato.entregados.print'),
            'canContratoEntregadoExport' => (bool) optional($user)->can('feature.paquetes-contrato.entregados.export'),
        ]);
    }

    $contratos = Recojo::query()
        ->with([
            'empresa:id,nombre,sigla',
            'user:id,name,empresa_id',
            'estadoRegistro:id,nombre_estado',
        ])
        ->where('empresa_id', $empresaId)                 // âœ… misma empresa
        ->where('estados_id', $estadoEntregadoId)         // âœ… ENTREGADO
        ->orderByDesc('id')
        ->paginate(15);

    return view('paquetes_contrato.entregados', [
        'contratos' => $contratos,
        'canContratoEntregadoPrint' => (bool) optional($user)->can('feature.paquetes-contrato.entregados.print'),
        'canContratoEntregadoExport' => (bool) optional($user)->can('feature.paquetes-contrato.entregados.export'),
    ]);
}

    public function entregadosPdf(Request $request)
    {
        $user = Auth::user();
        [$fechaDesde, $fechaHasta] = $this->resolveEntregadosDateRange($request);

        $contratos = $this->entregadosQueryForUser($user, $fechaDesde, $fechaHasta)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($contratos->isEmpty()) {
            return redirect()
                ->route('paquetes-contrato.entregados', array_filter([
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                ]))
                ->with('error', 'No hay contratos entregados para el rango de fechas seleccionado.');
        }

        $generatedAt = now();
        $stats = $this->buildEntregadosStats($user, $fechaDesde, $fechaHasta, $contratos);
        $pdf = Pdf::loadView('paquetes_contrato.entregados-pdf', [
            'contratos' => $contratos,
            'generatedAt' => $generatedAt,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'stats' => $stats,
            'empresaNombre' => optional($user?->empresa)->nombre ?? 'SIN EMPRESA',
            'usuarioNombre' => trim((string) ($user?->name ?? 'Usuario del sistema')),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contratos-entregados-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function create()
    {
        $user = Auth::user();
        if (!$user || empty($user->empresa_id)) {
            return redirect()
                ->route('paquetes-contrato.index')
                ->with('error', $this->missingEmpresaMessage());
        }

        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? '')));
        }
        $origen = $this->normalizeApiOrigen($origen);
        $provinciaOrigen = $this->normalizeProvinciaOrigenUsuario($user);

        return view('paquetes_contrato.create', [
            'origen' => $origen,
            'provinciaOrigen' => $provinciaOrigen,
            'departamentos' => self::DEPARTAMENTOS,
            'canContratoCreateSubmit' => $this->canSubmitContratoCreate($user),
            'canContratoCreateFrecuente' => $this->canManageContratoFrecuente($user),
        ]);
    }

    public function createConTarifa()
    {
        $user = Auth::user();
        if (!$user || empty($user->empresa_id)) {
            return redirect()
                ->route('paquetes-contrato.index')
                ->with('error', $this->missingEmpresaMessage());
        }

        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? '')));
        }
        $origen = $this->normalizeApiOrigen($origen);
        $provinciaOrigen = $this->normalizeProvinciaOrigenUsuario($user);

        $empresaId = (int) ($user->empresa_id ?? 0);
        $serviciosTarifa = collect();
        $provinciasPorDestino = [];

        if ($empresaId > 0) {
            $serviciosTarifa = TarifaContrato::query()
                ->where('empresa_id', $empresaId)
                ->select('servicio')
                ->distinct()
                ->orderBy('servicio')
                ->pluck('servicio');

            $rowsProvincias = TarifaContrato::query()
                ->where('empresa_id', $empresaId)
                ->whereRaw("trim(upper(servicio)) like '%INTERPROVINCIAL%'")
                ->whereNotNull('provincia')
                ->select('destino', 'provincia')
                ->get();

            foreach ($rowsProvincias as $row) {
                $destino = strtoupper(trim((string) $row->destino));
                $provincia = strtoupper(trim((string) $row->provincia));
                if ($destino === '' || $provincia === '') {
                    continue;
                }
                $provinciasPorDestino[$destino][] = $provincia;
            }

            foreach ($provinciasPorDestino as $destino => $provincias) {
                $provincias = array_values(array_unique($provincias));
                sort($provincias);
                $provinciasPorDestino[$destino] = $provincias;
            }
        }

        return view('paquetes_contrato.create-con-tarifa', [
            'origen' => $origen,
            'provinciaOrigen' => $provinciaOrigen,
            'departamentos' => self::DEPARTAMENTOS,
            'serviciosTarifa' => $serviciosTarifa,
            'provinciasPorDestino' => $provinciasPorDestino,
            'canContratoCreateTarifaSubmit' => $this->canSubmitContratoCreateConTarifa($user),
        ]);
    }

    public function storeConTarifa(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->guest(route('paquetes-contrato.index', absolute: false));
        }

        if (empty($user->empresa_id)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Tu usuario no tiene empresa asignada. Asigna empresa al usuario para generar codigo.');
        }

        $request->merge([
            'destino' => $this->normalizeDepartamentoContrato((string) $request->input('destino', '')),
        ]);

        $data = $request->validate([
            'nombre_r' => 'required|string|max:255',
            'telefono_r' => 'required|string|max:50',
            'contenido' => 'required|string',
            'direccion_r' => 'required|string|max:255',
            'nombre_d' => 'required|string|max:255',
            'telefono_d' => 'required|string|max:50',
            'servicio' => 'required|string|max:255',
            'destino' => 'required|string|in:' . implode(',', self::DEPARTAMENTOS),
            'direccion' => 'required|string|max:255',
            'mapa' => 'nullable|string|max:500',
            'numero_copias' => 'nullable|integer|min:1|max:3',
            'provincia' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(function () use ($request) {
                    return $this->normalizeServicioTarifa((string) $request->input('servicio')) === 'INTERPROVINCIAL';
                }),
            ],
        ], [
            'provincia.required' => 'La provincia es obligatoria cuando el servicio es INTERPROVINCIAL.',
        ]);

        $empresa = Empresa::query()->find((int) $user->empresa_id);
        if (!$empresa) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No se encontro la empresa asociada al usuario.');
        }

        $codigoCliente = strtoupper(trim((string) $empresa->codigo_cliente));
        $codigoCliente = preg_replace('/\s+/', '', $codigoCliente) ?: '';
        if ($codigoCliente === '') {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'La empresa asociada no tiene codigo_cliente valido.');
        }

        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? 'ORIGEN')));
        }
        $origen = $this->normalizeDepartamentoContrato($origen);
        $provinciaOrigen = $this->normalizeProvinciaOrigenUsuario($user);

        $destino = $this->normalizeDepartamentoContrato((string) $data['destino']);
        $servicio = $this->normalizeServicioTarifa((string) $data['servicio']);
        $provincia = !empty($data['provincia']) ? strtoupper(trim((string) $data['provincia'])) : null;

        $tarifa = $this->resolveTarifaContrato(
            (int) $empresa->id,
            $origen,
            $destino,
            $servicio,
            $provincia
        );

        if (!$tarifa) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No existe tarifa para la empresa y parametros seleccionados (origen, destino, provincia, servicio).');
        }

        $estadoSolicitudId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id') ?? 0);

        if ($estadoSolicitudId <= 0) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No existe el estado SOLICITUD en la tabla estados.');
        }

        $eventoExiste = DB::table('eventos')
            ->where('id', self::EVENTO_ID_CONTRATO_CREADO)
            ->exists();

        if (!$eventoExiste) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'No existe el evento con ID ' . self::EVENTO_ID_CONTRATO_CREADO . ' en la tabla eventos.');
        }

        $contrato = null;
        DB::transaction(function () use (
            $data,
            $user,
            $empresa,
            $codigoCliente,
            $origen,
            $provinciaOrigen,
            $estadoSolicitudId,
            $destino,
            $provincia,
            $tarifa,
            &$contrato
        ) {
            $correlativo = $this->nextCorrelativo((int) $empresa->id, $codigoCliente);
            $codigo = $this->buildCodigo($codigoCliente, $correlativo);
            $empresaIdDetectada = $this->resolveEmpresaIdByCodigo($codigo) ?? (int) $empresa->id;

            $contrato = Recojo::query()->create([
                'user_id' => (int) $user->id,
                'empresa_id' => $empresaIdDetectada,
                'codigo' => $codigo,
                'cod_especial' => null,
                'estados_id' => $estadoSolicitudId,
                'origen' => $origen,
                'provincia_origen' => $provinciaOrigen,
                'destino' => $destino,
                'nombre_r' => strtoupper(trim((string) $data['nombre_r'])),
                'telefono_r' => trim((string) $data['telefono_r']),
                'contenido' => trim((string) $data['contenido']),
                'cantidad' => '1',
                'direccion_r' => strtoupper(trim((string) $data['direccion_r'])),
                'nombre_d' => strtoupper(trim((string) $data['nombre_d'])),
                'telefono_d' => trim((string) $data['telefono_d']),
                'direccion_d' => strtoupper(trim((string) $data['direccion'])),
                'mapa' => !empty($data['mapa']) ? trim((string) $data['mapa']) : null,
                'provincia' => $provincia,
                'peso' => 0,
                'precio' => null,
                'tarifa_contrato_id' => (int) $tarifa->id,
                'fecha_recojo' => null,
                'observacion' => null,
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
                'evento_id' => self::EVENTO_ID_CONTRATO_CREADO,
                'user_id' => (int) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()
            ->route('paquetes-contrato.index')
            ->with('success', 'GUARDADO CON TARIFA (PESO PENDIENTE)')
            ->with('download_reporte_url', route('paquetes-contrato.reporte', [
                'contrato' => $contrato->id,
                'copias' => $this->normalizeNumeroCopias($data['numero_copias'] ?? null),
            ], false));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->guest(route('paquetes-contrato.index', absolute: false));
        }

        abort_unless($this->canSubmitContratoCreate($user), 403, 'No tienes permiso para guardar contratos.');

        $request->merge([
            'destino' => $this->normalizeDepartamentoContrato((string) $request->input('destino', '')),
        ]);

        $data = $request->validate($this->storeRules());

        try {
            $contrato = $this->createContratoDesdePayload($data, $user);
        } catch (\RuntimeException $exception) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('paquetes-contrato.index')
            ->with('success', 'GUARDADO')
            ->with('download_reporte_url', route('paquetes-contrato.reporte', [
                'contrato' => $contrato->id,
                'copias' => $this->normalizeNumeroCopias($data['numero_copias'] ?? null),
            ], false));
    }

    public function storePublic(Request $request)
    {
        $request->headers->set('Accept', 'application/json');
        $payload = $request->all();
        $payload['destino'] = $this->normalizeDepartamentoContrato((string) ($payload['destino'] ?? ''));

        $validator = Validator::make($payload, array_merge($this->storeRules(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'user_email' => 'nullable|email|exists:users,email',
            'user_ci' => 'nullable|string|max:50',
        ]));

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Los datos enviados no son validos.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $user = $this->resolveApiUser($data);

        if (!$user) {
            return response()->json([
                'message' => 'Los datos enviados no son validos.',
                'errors' => [
                    'user_id' => ['Debes enviar un usuario valido mediante user_id, user_email o user_ci.'],
                ],
            ], 422);
        }

        try {
            $contrato = $this->createContratoDesdePayload($data, $user);
        } catch (\RuntimeException $exception) {
            return response()->json([
                'message' => 'No se pudo generar la guia.',
                'errors' => [
                    'general' => [$exception->getMessage()],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'GUARDADO',
            'data' => [
                'id' => $contrato->id,
                'codigo' => $contrato->codigo,
                'reporte_url' => route('paquetes-contrato.reporte', [
                    'contrato' => $contrato->id,
                    'copias' => $this->normalizeNumeroCopias($data['numero_copias'] ?? null),
                ], false),
            ],
        ], 201);
    }

    public function reporte(Recojo $contrato)
    {
        $this->authorizeAnyPermission(request(), $this->cn33PrintPermissions());

        $generatedAt = now();
        $numeroCopias = $this->normalizeNumeroCopias(request()->query('copias', 1));
        $contrato->loadMissing(['empresa:id,nombre,sigla', 'user.empresa:id,nombre,sigla']);
        $pdf = Pdf::loadView('paquetes_contrato.reporte', [
            'contrato' => $contrato,
            'generatedAt' => $generatedAt,
            'numeroCopias' => $numeroCopias,
            'verificationUrl' => $this->verificationUrlFor($contrato),
        ])->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contrato-' . $contrato->codigo . '-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function reporteHoy()
    {
        $this->authorizeAnyPermission(request(), [
            'feature.paquetes-contrato.index.report',
            'feature.paquetes-contrato.almacen.report',
        ]);

        $hoy = now()->toDateString();
        $contratos = Recojo::query()
            ->with(['empresa:id,nombre,sigla', 'user.empresa:id,nombre,sigla'])
            ->whereDate('created_at', $hoy)
            ->orderBy('id')
            ->get();

        if ($contratos->isEmpty()) {
            return redirect()
                ->route('paquetes-contrato.index')
                ->with('error', 'No hay contratos generados hoy.');
        }

        $generatedAt = now();
        $pdf = Pdf::loadView('paquetes_contrato.reporte-hoy', [
            'contratos' => $contratos,
            'generatedAt' => $generatedAt,
            'fechaHoy' => $hoy,
            'verificationUrls' => $this->verificationUrlsFor($contratos),
        ])->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contratos-generados-hoy-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function verificarGuia(Request $request)
    {
        $contrato = $this->contratoFromVerificationRequest($request);
        $contrato->loadMissing(['empresa:id,nombre,sigla', 'user.empresa:id,nombre,sigla']);

        return view('paquetes_contrato.verificacion', [
            'contrato' => $contrato,
            'reimprimirUrl' => $this->verificationPdfUrlFor($contrato),
            'rastrearUrl' => URL::signedRoute('tracking.demo.signed', [
                'codigo' => $contrato->codigo,
            ]),
        ]);
    }

    public function verificarGuiaPdf(Request $request)
    {
        $contrato = $this->contratoFromVerificationRequest($request);
        $generatedAt = now();
        $contrato->loadMissing(['empresa:id,nombre,sigla', 'user.empresa:id,nombre,sigla']);

        $pdf = Pdf::loadView('paquetes_contrato.reporte', [
            'contrato' => $contrato,
            'generatedAt' => $generatedAt,
            'verificationUrl' => $this->verificationUrlFor($contrato),
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('guia-verificacion-' . $contrato->codigo . '.pdf');
    }

    private function verificationUrlFor(Recojo $contrato): string
    {
        return route('paquetes-contrato.verificar-guia', [
            't' => $this->verificationTokenFor($contrato),
        ]);
    }

    private function verificationPdfUrlFor(Recojo $contrato): string
    {
        return route('paquetes-contrato.verificar-guia.pdf', [
            't' => $this->verificationTokenFor($contrato),
        ]);
    }

    private function verificationUrlsFor($contratos): array
    {
        return $contratos
            ->mapWithKeys(fn (Recojo $contrato) => [$contrato->getKey() => $this->verificationUrlFor($contrato)])
            ->all();
    }

    private function verificationTokenFor(Recojo $contrato): string
    {
        return Crypt::encryptString((string) $contrato->getKey());
    }

    private function cn33PrintPermissions(): array
    {
        return [
            'feature.paquetes-contrato.index.print',
            'feature.paquetes-contrato.almacen.print',
            'feature.paquetes-contrato.recoger-envios.print',
            'feature.paquetes-contrato.cartero.print',
            'feature.paquetes-contrato.entregados.print',
            'feature.paquetes-contrato.create.create',
            'feature.paquetes-contrato.create-con-tarifa.create',
        ];
    }

    private function cn33DataForDespacho(string $despacho = '', string $origen = '', string $destino = ''): array
    {
        if ($despacho === '') {
            return $this->emptyCn33Data();
        }

        $paquetes = PaqueteEms::query()
            ->whereRaw('trim(upper(cod_especial)) = trim(upper(?))', [$despacho])
            ->with(['user:id,empresa_id', 'user.empresa:id,nombre'])
            ->orderBy('id')
            ->get([
                'id',
                'codigo',
                'cod_especial',
                'origen',
                'ciudad',
                'cantidad',
                'peso',
                'nombre_remitente',
                'observacion',
                'user_id',
                'created_at',
                'updated_at',
            ]);

        $contratos = Recojo::query()
            ->whereRaw('trim(upper(cod_especial)) = trim(upper(?))', [$despacho])
            ->with(['empresa:id,nombre'])
            ->orderBy('id')
            ->get([
                'id',
                'codigo',
                'cod_especial',
                'empresa_id',
                'origen',
                'destino',
                'peso',
                'nombre_r',
                'observacion',
                'created_at',
                'updated_at',
            ]);

        $solicitudes = SolicitudCliente::query()
            ->whereRaw('trim(upper(cod_especial)) = trim(upper(?))', [$despacho])
            ->orderBy('id')
            ->get([
                'id',
                'codigo_solicitud',
                'barcode',
                'cod_especial',
                'origen',
                'ciudad',
                'cantidad',
                'peso',
                'nombre_remitente',
                'observacion',
                'created_at',
                'updated_at',
            ]);

        $rows = collect($paquetes->map(function ($paquete) {
            return (object) [
                'tipo' => 'EMS',
                'codigo' => $paquete->codigo,
                'origen' => $paquete->origen,
                'destino' => $paquete->ciudad,
                'cantidad' => (int) ($paquete->cantidad ?? 1),
                'peso' => (float) ($paquete->peso ?? 0),
                'nombre_remitente' => $this->composeCn33Remitente(
                    $paquete->nombre_remitente,
                    optional(optional($paquete->user)->empresa)->nombre
                ),
                'observacion' => (string) ($paquete->observacion ?? ''),
                'created_at' => $paquete->created_at,
            ];
        })->all())
            ->concat($contratos->map(function ($contrato) {
                return (object) [
                    'tipo' => 'CONTRATO',
                    'codigo' => $contrato->codigo,
                    'origen' => $contrato->origen,
                    'destino' => $contrato->destino,
                    'cantidad' => 1,
                    'peso' => (float) ($contrato->peso ?? 0),
                    'nombre_remitente' => $this->composeCn33Remitente(
                        $contrato->nombre_r,
                        optional($contrato->empresa)->nombre
                    ),
                    'observacion' => (string) ($contrato->observacion ?? ''),
                    'created_at' => $contrato->created_at,
                ];
            })->all())
            ->concat($solicitudes->map(function ($solicitud) {
                return (object) [
                    'tipo' => 'SOLICITUD',
                    'codigo' => $solicitud->codigo_solicitud ?: ($solicitud->barcode ?: 'SIN CODIGO'),
                    'origen' => $solicitud->origen,
                    'destino' => $solicitud->ciudad,
                    'cantidad' => (int) ($solicitud->cantidad ?? 1),
                    'peso' => (float) ($solicitud->peso ?? 0),
                    'nombre_remitente' => (string) ($solicitud->nombre_remitente ?? 'SIN REMITENTE'),
                    'observacion' => (string) ($solicitud->observacion ?? ''),
                    'created_at' => $solicitud->created_at,
                ];
            })->all())
            ->values();

        $generatedAt = collect([$paquetes->max('updated_at'), $contratos->max('updated_at'), $solicitudes->max('updated_at')])
            ->filter()
            ->sortDesc()
            ->first() ?: now();

        if ($origen !== '') {
            $rows = $rows
                ->filter(fn ($row) => strtoupper(trim((string) ($row->origen ?? ''))) === $origen)
                ->values();
        }

        if ($destino !== '') {
            $rows = $rows
                ->filter(fn ($row) => strtoupper(trim((string) ($row->destino ?? ''))) === $destino)
                ->values();
        }

        return [
            'rows' => $rows,
            'totalCantidad' => (int) $rows->sum(fn ($row) => (int) ($row->cantidad ?? 1)),
            'totalPeso' => (float) $rows->sum(fn ($row) => (float) ($row->peso ?? 0)),
            'originCity' => trim((string) optional($rows->first())->origen),
            'destinationCity' => trim((string) optional($rows->first())->destino),
            'generatedAt' => $generatedAt,
        ];
    }

    private function emptyCn33Data(): array
    {
        return [
            'rows' => collect(),
            'totalCantidad' => 0,
            'totalPeso' => 0.0,
            'originCity' => '',
            'destinationCity' => '',
            'generatedAt' => now(),
        ];
    }

    private function cn33FilterOptions(string $field, string $despacho = ''): array
    {
        if (! in_array($field, ['origen', 'destino'], true)) {
            return [];
        }

        $despacho = strtoupper(trim($despacho));
        $data = $despacho !== ''
            ? $this->cn33DataForDespacho($despacho)
            : $this->cn33AllFilterRows();

        return $data['rows']
            ->pluck($field)
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter(fn ($value) => $value !== '' && $value !== '-')
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function cn33AllFilterRows(): array
    {
        $ems = PaqueteEms::query()
            ->whereNotNull('cod_especial')
            ->where('cod_especial', '<>', '')
            ->limit(5000)
            ->get(['origen', 'ciudad'])
            ->map(fn ($row) => (object) [
                'origen' => $row->origen,
                'destino' => $row->ciudad,
            ]);

        $contratos = Recojo::query()
            ->whereNotNull('cod_especial')
            ->where('cod_especial', '<>', '')
            ->limit(5000)
            ->get(['origen', 'destino'])
            ->map(fn ($row) => (object) [
                'origen' => $row->origen,
                'destino' => $row->destino,
            ]);

        $solicitudes = SolicitudCliente::query()
            ->whereNotNull('cod_especial')
            ->where('cod_especial', '<>', '')
            ->limit(5000)
            ->get(['origen', 'ciudad'])
            ->map(fn ($row) => (object) [
                'origen' => $row->origen,
                'destino' => $row->ciudad,
            ]);

        return [
            'rows' => $ems->concat($contratos)->concat($solicitudes)->values(),
        ];
    }

    private function composeCn33Remitente(?string $nombreRemitente, ?string $empresaNombre): string
    {
        $nombre = trim((string) $nombreRemitente);
        $empresa = trim((string) $empresaNombre);

        if ($empresa === '') {
            return $nombre !== '' ? $nombre : 'SIN REMITENTE';
        }

        if ($nombre === '' || in_array(strtoupper($nombre), ['SIN REMITENTE', '-'], true)) {
            return $empresa;
        }

        if (stripos($nombre, $empresa) !== false) {
            return $nombre;
        }

        return $nombre . ' / ' . $empresa;
    }

    private function entregadosQueryForUser($user, ?string $fechaDesde = null, ?string $fechaHasta = null)
    {
        $empresaId = (int) ($user?->empresa_id ?? 0);
        $estadoEntregadoId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
            ->value('id') ?? 0);

        $query = Recojo::query()
            ->with([
                'empresa:id,nombre,sigla',
                'user:id,name,empresa_id',
                'estadoRegistro:id,nombre_estado',
            ]);

        if ($empresaId <= 0 || $estadoEntregadoId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where('empresa_id', $empresaId)
            ->where('estados_id', $estadoEntregadoId)
            ->when($fechaDesde, fn ($sub) => $sub->whereDate('created_at', '>=', $fechaDesde))
            ->when($fechaHasta, fn ($sub) => $sub->whereDate('created_at', '<=', $fechaHasta));
    }

    private function resolveEntregadosDateRange(Request $request): array
    {
        $fechaDesde = $this->normalizeDateInput(trim((string) $request->query('fecha_desde', '')));
        $fechaHasta = $this->normalizeDateInput(trim((string) $request->query('fecha_hasta', '')));

        if ($fechaDesde && $fechaHasta && $fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        return [$fechaDesde, $fechaHasta];
    }

    private function normalizeDateInput(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildEntregadosStats($user, ?string $fechaDesde, ?string $fechaHasta, $rows = null): array
    {
        $rows = $rows ?? $this->entregadosQueryForUser($user, $fechaDesde, $fechaHasta)->get();

        $porDia = $rows
            ->groupBy(fn (Recojo $contrato) => optional($contrato->created_at)->format('Y-m-d') ?: 'sin-fecha')
            ->map(fn ($items, $fecha) => [
                'fecha' => $fecha,
                'label' => $fecha !== 'sin-fecha'
                    ? Carbon::parse($fecha)->locale('es')->translatedFormat('d M Y')
                    : 'Sin fecha',
                'total' => $items->count(),
                'peso' => (float) $items->sum(fn (Recojo $contrato) => (float) ($contrato->peso ?? 0)),
            ])
            ->sortBy('fecha')
            ->values();

        $diasCubiertos = (int) $porDia->where('fecha', '!=', 'sin-fecha')->count();

        return [
            'total' => $rows->count(),
            'peso_total' => (float) $rows->sum(fn (Recojo $contrato) => (float) ($contrato->peso ?? 0)),
            'dias_cubiertos' => $diasCubiertos,
            'promedio_diario' => $diasCubiertos > 0 ? round($rows->count() / $diasCubiertos, 2) : (float) $rows->count(),
            'por_dia' => $porDia,
        ];
    }

    private function contratoFromVerificationRequest(Request $request): Recojo
    {
        $token = trim((string) $request->query('t', ''));
        abort_if($token === '', 404);

        try {
            $id = Crypt::decryptString($token);
        } catch (\Throwable $e) {
            abort(404);
        }

        abort_unless(ctype_digit((string) $id), 404);

        return Recojo::query()->findOrFail((int) $id);
    }

    protected function nextCorrelativo(int $empresaId, string $codigoCliente): int
    {
        $cliente = $this->normalizarCodigoCliente($codigoCliente);
        $prefix = 'C' . $cliente . 'A';
        $pattern = '/^C' . preg_quote($cliente, '/') . 'A(\d{5})BO$/';
        $empresaIds = $this->empresaIdsConMismoCodigoCliente($empresaId, $cliente);
        $max = 0;

        $codigosEmpresa = CodigoEmpresa::query()
            ->whereIn('empresa_id', $empresaIds)
            ->where(function ($query) use ($prefix) {
                $query->where('codigo', 'like', $prefix . '%BO')
                    ->orWhere('barcode', 'like', $prefix . '%BO');
            })
            ->get(['codigo', 'barcode'])
            ->flatMap(fn ($row) => [$row->codigo, $row->barcode]);

        foreach ($codigosEmpresa as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $valor = (int) $matches[1];
                if ($valor > $max) {
                    $max = $valor;
                }
            }
        }

        $codigosContrato = Recojo::query()
            ->where('codigo', 'like', $prefix . '%BO')
            ->pluck('codigo');

        foreach ($codigosContrato as $codigo) {
            if (preg_match($pattern, strtoupper(trim((string) $codigo)), $matches)) {
                $valor = (int) $matches[1];
                if ($valor > $max) {
                    $max = $valor;
                }
            }
        }

        return $max + 1;
    }

    protected function buildCodigo(string $codigoCliente, int $correlativo): string
    {
        return 'C' . $this->normalizarCodigoCliente($codigoCliente) . 'A' . str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT) . 'BO';
    }

    protected function normalizarCodigoCliente(string $codigoCliente): string
    {
        $cliente = strtoupper(trim($codigoCliente));

        return preg_replace('/\s+/', '', $cliente) ?: '';
    }

    protected function empresaIdsConMismoCodigoCliente(int $empresaId, string $codigoCliente): array
    {
        $cliente = $this->normalizarCodigoCliente($codigoCliente);

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

    protected function normalizeServicioTarifa(string $servicio): string
    {
        $servicio = strtoupper(trim($servicio));
        $servicio = preg_replace('/\s+/', ' ', $servicio) ?? $servicio;
        $servicio = str_replace('LOCAL (REGULAR)', 'LOCAL(REGULAR)', $servicio);
        $servicio = str_replace('LOCAL (EXPRESS)', 'LOCAL(EXPRESS)', $servicio);

        return $servicio;
    }

    protected function resolveTarifaContrato(
        int $empresaId,
        string $origen,
        string $destino,
        string $servicio,
        ?string $provincia
    ): ?TarifaContrato {
        $baseQuery = TarifaContrato::query()
            ->where('empresa_id', $empresaId)
            ->whereRaw('trim(upper(origen)) = ?', [$origen])
            ->whereRaw('trim(upper(destino)) = ?', [$destino])
            ->whereRaw('trim(upper(servicio)) = ?', [$servicio]);

        if ($provincia !== null && $provincia !== '') {
            $exacta = (clone $baseQuery)
                ->whereRaw('trim(upper(provincia)) = ?', [$provincia])
                ->orderByDesc('id')
                ->first();

            if ($exacta) {
                return $exacta;
            }
        }

        return (clone $baseQuery)
            ->whereNull('provincia')
            ->orderByDesc('id')
            ->first();
    }

    protected function calculatePrecioContrato(float $peso, float $precioBaseKilo, float $precioKiloExtra): float
    {
        if ($peso <= 0) {
            return 0.00;
        }

        if ($peso <= 1.0) {
            return round($precioBaseKilo, 2);
        }

        $bloquesExtra = (int) ceil($peso - 1.0);
        $bloquesExtra = max(0, $bloquesExtra);

        return round($precioBaseKilo + ($bloquesExtra * $precioKiloExtra), 2);
    }

    protected function resolveEmpresaIdByCodigo(string $codigo): ?int
    {
        $codigoNormalizado = strtoupper(trim($codigo));

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

    protected function storeRules(): array
    {
        return [
            'nombre_r' => 'required|string|max:255',
            'telefono_r' => 'required|string|max:50',
            'contenido' => 'required|string',
            'cantidad' => 'required|integer|min:1',
            'direccion_r' => 'required|string|max:255',
            'nombre_d' => 'required|string|max:255',
            'telefono_d' => 'required|string|max:50',
            'destino' => 'required|string|in:' . implode(',', self::DEPARTAMENTOS),
            'direccion' => 'required|string|max:255',
            'mapa' => 'nullable|string|max:500',
            'provincia' => 'nullable|string|max:255',
            'peso' => 'nullable|numeric|min:0',
            'numero_copias' => 'nullable|integer|min:1|max:3',
        ];
    }

    protected function createContratoDesdePayload(array $data, User $user): Recojo
    {
        if (empty($user->empresa_id)) {
            throw new \RuntimeException($this->missingEmpresaMessage());
        }

        $empresa = Empresa::query()->find((int) $user->empresa_id);
        if (!$empresa) {
            throw new \RuntimeException('No se encontro la empresa asociada al usuario.');
        }

        $codigoCliente = strtoupper(trim((string) $empresa->codigo_cliente));
        $codigoCliente = preg_replace('/\s+/', '', $codigoCliente) ?: '';
        if ($codigoCliente === '') {
            throw new \RuntimeException('La empresa asociada no tiene codigo_cliente valido.');
        }

        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? 'ORIGEN')));
        }
        $origen = $this->normalizeApiOrigen($origen);
        $provinciaOrigen = $this->normalizeProvinciaOrigenUsuario($user);

        $estadoSolicitudId = (int) (Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id') ?? 0);

        if ($estadoSolicitudId <= 0) {
            throw new \RuntimeException('No existe el estado SOLICITUD en la tabla estados.');
        }

        $eventoExiste = DB::table('eventos')
            ->where('id', self::EVENTO_ID_CONTRATO_CREADO)
            ->exists();

        if (!$eventoExiste) {
            throw new \RuntimeException('No existe el evento con ID ' . self::EVENTO_ID_CONTRATO_CREADO . ' en la tabla eventos.');
        }

        $contrato = null;
        DB::transaction(function () use ($data, $user, $empresa, $codigoCliente, $origen, $provinciaOrigen, $estadoSolicitudId, &$contrato) {
            $correlativo = $this->nextCorrelativo((int) $empresa->id, $codigoCliente);
            $codigo = $this->buildCodigo($codigoCliente, $correlativo);
            $empresaIdDetectada = $this->resolveEmpresaIdByCodigo($codigo) ?? (int) $empresa->id;

            $contrato = Recojo::query()->create([
                'user_id' => (int) $user->id,
                'empresa_id' => $empresaIdDetectada,
                'codigo' => $codigo,
                'cod_especial' => null,
                'estados_id' => $estadoSolicitudId,
                'origen' => $origen,
                'provincia_origen' => $provinciaOrigen,
                'destino' => $this->normalizeDepartamentoContrato((string) $data['destino']),
                'nombre_r' => strtoupper(trim((string) $data['nombre_r'])),
                'telefono_r' => trim((string) $data['telefono_r']),
                'contenido' => trim((string) $data['contenido']),
                'cantidad' => (string) ((int) $data['cantidad']),
                'direccion_r' => strtoupper(trim((string) $data['direccion_r'])),
                'nombre_d' => strtoupper(trim((string) $data['nombre_d'])),
                'telefono_d' => trim((string) $data['telefono_d']),
                'direccion_d' => strtoupper(trim((string) $data['direccion'])),
                'mapa' => !empty($data['mapa']) ? trim((string) $data['mapa']) : null,
                'provincia' => !empty($data['provincia']) ? strtoupper(trim((string) $data['provincia'])) : null,
                'peso' => $data['peso'] ?? 0,
                'fecha_recojo' => null,
                'observacion' => null,
                'justificacion' => null,
                'imagen' => null,
            ]);

            // Reservamos codigo tambien en codigo_empresa para mantener correlativo global por empresa.
            CodigoEmpresa::query()->create([
                'codigo' => $codigo,
                'barcode' => $codigo,
                'empresa_id' => (int) $empresa->id,
            ]);

            DB::table('eventos_contrato')->insert([
                'codigo' => $codigo,
                'evento_id' => self::EVENTO_ID_CONTRATO_CREADO,
                'user_id' => (int) $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $contrato;
    }

    protected function normalizeApiOrigen(string $origen): string
    {
        return $this->normalizeDepartamentoContrato($origen);
    }

    protected function normalizeProvinciaOrigenUsuario(?User $user): ?string
    {
        $provincia = trim((string) ($user?->provincia_origen ?? ''));
        $provincia = function_exists('mb_strtoupper') ? mb_strtoupper($provincia, 'UTF-8') : strtoupper($provincia);
        $provincia = preg_replace('/\s+/', ' ', $provincia) ?: '';

        return $provincia !== '' ? $provincia : null;
    }

    protected function normalizeNumeroCopias(mixed $value): int
    {
        return max(1, min(3, (int) ($value ?: 1)));
    }

    protected function normalizeDepartamentoContrato(string $value): string
    {
        $normalized = strtoupper(trim($value));
        $normalized = strtr($normalized, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N',
        ]);
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?: '';

        if ($normalized === 'BENI' || str_contains($normalized, 'BENI')) {
            return 'TRINIDAD';
        }

        if ($normalized === 'PANDO' || str_contains($normalized, 'PANDO')) {
            return 'COBIJA';
        }

        if ($normalized === 'CHUQUISACA' || str_contains($normalized, 'CHUQUISACA')) {
            return 'SUCRE';
        }

        return $normalized;
    }

    protected function resolveApiUser(array $data): ?User
    {
        if (!empty($data['user_id'])) {
            return User::query()->find((int) $data['user_id']);
        }

        if (!empty($data['user_email'])) {
            return User::query()
                ->where('email', trim((string) $data['user_email']))
                ->first();
        }

        if (!empty($data['user_ci'])) {
            return User::query()
                ->where('ci', trim((string) $data['user_ci']))
                ->first();
        }

        return null;
    }

    protected function missingEmpresaMessage(): string
    {
        return 'Entra con un usuario que tenga empresa asociada.';
    }

    protected function canSubmitContratoCreate(User $user): bool
    {
        return $user->can('feature.paquetes-contrato.create.create')
            || $user->can('paquetes-contrato.store');
    }

    protected function canManageContratoFrecuente(User $user): bool
    {
        return $user->can('feature.paquetes-contrato.create.manage')
            || $user->can('paquetes-contrato.create');
    }

    protected function canSubmitContratoCreateConTarifa(User $user): bool
    {
        return $user->can('feature.paquetes-contrato.create-con-tarifa.create')
            || $user->can('paquetes-contrato.store-con-tarifa');
    }

    protected function authorizeAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();

        if (!$user) {
            abort(403, 'No autenticado.');
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'No tienes permiso para realizar esta accion.');
    }
}


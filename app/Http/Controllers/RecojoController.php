<?php

namespace App\Http\Controllers;

use App\Models\CodigoEmpresa;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Recojo;
use App\Models\TarifaContrato;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

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
        'CHUQUISACA',
        'BENI',
        'PANDO',
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

   public function entregados()
{
    $empresaId = (int) (Auth::user()->empresa_id ?? 0);

    // Si el usuario no tiene empresa, no mostramos nada
    if ($empresaId <= 0) {
        $contratos = Recojo::query()->whereRaw('1=0')->paginate(15);

        return view('paquetes_contrato.entregados', compact('contratos'));
    }

    // Buscar el ID del estado ENTREGADO (sin importar mayúsculas/espacios)
    $estadoEntregadoId = (int) (Estado::query()
        ->whereRaw('trim(upper(nombre_estado)) = ?', ['ENTREGADO'])
        ->value('id') ?? 0);

    // Si no existe ese estado, no mostramos nada (evita bugs silenciosos)
    if ($estadoEntregadoId <= 0) {
        $contratos = Recojo::query()->whereRaw('1=0')->paginate(15);

        return view('paquetes_contrato.entregados', compact('contratos'));
    }

    $contratos = Recojo::query()
        ->with([
            'empresa:id,nombre,sigla',
            'user:id,name,empresa_id',
            'estadoRegistro:id,nombre_estado',
        ])
        ->where('empresa_id', $empresaId)                 // ✅ misma empresa
        ->where('estados_id', $estadoEntregadoId)         // ✅ ENTREGADO
        ->orderByDesc('id')
        ->paginate(15);

    return view('paquetes_contrato.entregados', compact('contratos'));
}

    public function create()
    {
        $user = Auth::user();
        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? '')));
        }

        return view('paquetes_contrato.create', [
            'origen' => $origen,
            'departamentos' => self::DEPARTAMENTOS,
        ]);
    }

    public function createConTarifa()
    {
        $user = Auth::user();
        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? '')));
        }

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
            'departamentos' => self::DEPARTAMENTOS,
            'serviciosTarifa' => $serviciosTarifa,
            'provinciasPorDestino' => $provinciasPorDestino,
        ]);
    }

    public function storeConTarifa(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        if (empty($user->empresa_id)) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Tu usuario no tiene empresa asignada. Asigna empresa al usuario para generar codigo.');
        }

        $data = $request->validate([
            'nombre_r' => 'required|string|max:255',
            'telefono_r' => 'required|string|max:50',
            'contenido' => 'required|string',
            'direccion_r' => 'required|string|max:255',
            'nombre_d' => 'required|string|max:255',
            'telefono_d' => 'nullable|string|max:50',
            'servicio' => 'required|string|max:255',
            'destino' => 'required|string|in:' . implode(',', self::DEPARTAMENTOS),
            'direccion' => 'required|string|max:255',
            'mapa' => 'nullable|string|max:500',
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

        $destino = strtoupper(trim((string) $data['destino']));
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
                'destino' => $destino,
                'nombre_r' => strtoupper(trim((string) $data['nombre_r'])),
                'telefono_r' => trim((string) $data['telefono_r']),
                'contenido' => trim((string) $data['contenido']),
                'direccion_r' => strtoupper(trim((string) $data['direccion_r'])),
                'nombre_d' => strtoupper(trim((string) $data['nombre_d'])),
                'telefono_d' => !empty($data['telefono_d']) ? trim((string) $data['telefono_d']) : null,
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
            ->with('download_reporte_url', route('paquetes-contrato.reporte', $contrato->id));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

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
            ->with('download_reporte_url', route('paquetes-contrato.reporte', $contrato->id));
    }

    public function storePublic(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $validator = Validator::make($request->all(), array_merge($this->storeRules(), [
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
                'reporte_url' => route('paquetes-contrato.reporte', $contrato->id),
            ],
        ], 201);
    }

    public function reporte(Recojo $contrato)
    {
        $generatedAt = now();
        $pdf = Pdf::loadView('paquetes_contrato.reporte', [
            'contrato' => $contrato,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contrato-' . $contrato->codigo . '-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function reporteHoy()
    {
        $hoy = now()->toDateString();
        $contratos = Recojo::query()
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
        ])->setPaper('a4', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contratos-generados-hoy-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    protected function nextCorrelativo(int $empresaId, string $codigoCliente): int
    {
        $cliente = strtoupper(trim($codigoCliente));
        $prefix = 'C' . $cliente . 'A';
        $pattern = '/^C' . preg_quote($cliente, '/') . 'A(\d{5})BO$/';
        $max = 0;

        $codigosEmpresa = CodigoEmpresa::query()
            ->where('empresa_id', $empresaId)
            ->pluck('codigo');

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
        return 'C' . $codigoCliente . 'A' . str_pad((string) $correlativo, 5, '0', STR_PAD_LEFT) . 'BO';
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
            'direccion_r' => 'required|string|max:255',
            'nombre_d' => 'required|string|max:255',
            'telefono_d' => 'nullable|string|max:50',
            'destino' => 'required|string|in:' . implode(',', self::DEPARTAMENTOS),
            'direccion' => 'required|string|max:255',
            'mapa' => 'nullable|string|max:500',
            'provincia' => 'nullable|string|max:255',
        ];
    }

    protected function createContratoDesdePayload(array $data, User $user): Recojo
    {
        if (empty($user->empresa_id)) {
            throw new \RuntimeException('Tu usuario no tiene empresa asignada. Asigna empresa al usuario para generar codigo.');
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
        DB::transaction(function () use ($data, $user, $empresa, $codigoCliente, $origen, $estadoSolicitudId, &$contrato) {
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
                'destino' => strtoupper(trim((string) $data['destino'])),
                'nombre_r' => strtoupper(trim((string) $data['nombre_r'])),
                'telefono_r' => trim((string) $data['telefono_r']),
                'contenido' => trim((string) $data['contenido']),
                'direccion_r' => strtoupper(trim((string) $data['direccion_r'])),
                'nombre_d' => strtoupper(trim((string) $data['nombre_d'])),
                'telefono_d' => !empty($data['telefono_d']) ? trim((string) $data['telefono_d']) : null,
                'direccion_d' => strtoupper(trim((string) $data['direccion'])),
                'mapa' => !empty($data['mapa']) ? trim((string) $data['mapa']) : null,
                'provincia' => !empty($data['provincia']) ? strtoupper(trim((string) $data['provincia'])) : null,
                'peso' => 0,
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
}

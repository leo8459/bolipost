<?php

namespace App\Http\Controllers;

use App\Models\CodigoEmpresa;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\PaqueteEms;
use App\Models\Recojo as RecojoContrato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaquetesEmsController extends Controller
{
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

    public function index()
    {
        return view('paquetes_ems.index');
    }

    public function almacen()
    {
        return view('paquetes_ems.almacen');
    }

    public function ventanilla()
    {
        return view('paquetes_ems.ventanilla');
    }

    public function recibirRegional()
    {
        return view('paquetes_ems.recibir-regional');
    }

    public function entregados(Request $request)
    {
        $search = trim((string) $request->query('q', ''));

        $estadoDomicilioId = Estado::query()
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['DOMICILIO'])
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
                'cartero_user.name as asignado_a',
            ])
            ->when($estadoDomicilioId, function ($query) use ($estadoDomicilioId) {
                $query->where('paquetes_ems.estado_id', (int) $estadoDomicilioId);
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
                'cartero_user.name as asignado_a',
            ])
            ->when($estadoDomicilioId, function ($query) use ($estadoDomicilioId) {
                $query->where('paquetes_contrato.estados_id', (int) $estadoDomicilioId);
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

        $paquetes = DB::query()
            ->fromSub($emsQuery->unionAll($contratosQuery), 'entregados')
            ->orderByDesc('fecha_entrega')
            ->paginate(20)
            ->withQueryString();

        return view('paquetes_ems.entregados', [
            'paquetes' => $paquetes,
            'search' => $search,
            'estadoDomicilioDisponible' => (bool) $estadoDomicilioId,
        ]);
    }

    public function createRegistroRapidoContrato()
    {
        $user = Auth::user();
        $origen = strtoupper(trim((string) optional($user)->ciudad));
        if ($origen === '') {
            $origen = strtoupper(trim((string) optional($user)->name));
        }

        return view('paquetes_ems.registro-rapido-contrato', [
            'origen' => $origen,
            'ciudades' => self::CIUDADES_BOLIVIA,
            'listado' => [],
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

        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.codigo' => 'required|string|max:50',
            'items.*.destino' => ['required', 'string', Rule::in(self::CIUDADES_BOLIVIA)],
            'items.*.peso' => 'required|numeric|min:0.001',
        ], [], [
            'items' => 'prelista',
            'items.*.codigo' => 'codigo',
            'items.*.destino' => 'destino',
            'items.*.peso' => 'peso',
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

        $origen = strtoupper(trim((string) ($user->ciudad ?? '')));
        if ($origen === '') {
            $origen = strtoupper(trim((string) ($user->name ?? 'ORIGEN')));
        }

        $items = collect($validated['items'])
            ->map(function (array $item) {
                $codigo = strtoupper(trim((string) ($item['codigo'] ?? '')));
                $codigo = preg_replace('/\s+/', '', $codigo) ?: '';
                return [
                    'codigo' => $codigo,
                    'destino' => strtoupper(trim((string) ($item['destino'] ?? ''))),
                    'peso' => (float) ($item['peso'] ?? 0),
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

        $creados = collect();
        DB::transaction(function () use ($items, $user, $estadoAlmacenId, $origen, &$creados) {
            foreach ($items as $item) {
                $empresaId = $this->resolveEmpresaIdByCodigoContrato($item['codigo']);
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
                    'direccion_r' => 'SIN DIRECCION',
                    'nombre_d' => 'SIN DESTINATARIO',
                    'telefono_d' => null,
                    'direccion_d' => 'SIN DIRECCION',
                    'mapa' => null,
                    'provincia' => null,
                    'peso' => $item['peso'],
                    'fecha_recojo' => now()->toDateString(),
                    'observacion' => 'REGISTRO RAPIDO DESDE ALMACEN EMS',
                    'justificacion' => null,
                    'imagen' => null,
                ]);

                $creados->push([
                    'id' => (int) $contrato->id,
                    'codigo' => (string) $contrato->codigo,
                    'peso' => (string) $contrato->peso,
                    'origen' => (string) $contrato->origen,
                    'destino' => (string) $contrato->destino,
                    'reporte_url' => route('paquetes-contrato.reporte', $contrato->id),
                ]);
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
}

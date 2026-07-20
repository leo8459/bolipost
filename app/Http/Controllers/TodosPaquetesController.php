<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\Cartero;
use App\Models\CarteroAssignmentReport;
use App\Models\CarteroAssignmentReportItem;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use App\Models\SolicitudCliente;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TodosPaquetesController extends Controller
{
    private const DISTRIBUTION_ASSIGNEE_ROLES = [
        'auxiliar_urbano',
        'auxiliar_urbano_dnd',
        'auxiliar_7',
        'cartero_ems',
        'carteros_ems',
    ];

    private const TYPES = [
        'ems' => [
            'label' => 'EMS',
            'model' => PaqueteEms::class,
            'table' => 'paquetes_ems',
            'state_col' => 'estado_id',
            'editable' => [
                'codigo' => 'Codigo',
                'cod_especial' => 'Cod. especial',
                'origen' => 'Origen',
                'ciudad' => 'Destino',
                'nombre_remitente' => 'Remitente',
                'nombre_destinatario' => 'Destinatario',
                'telefono_destinatario' => 'Telefono destinatario',
                'direccion' => 'Direccion',
                'referencia' => 'Referencia',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'observacion' => 'Observacion',
            ],
            'numeric' => ['peso', 'precio'],
        ],
        'contrato' => [
            'label' => 'CONTRATO',
            'model' => Recojo::class,
            'table' => 'paquetes_contrato',
            'state_col' => 'estados_id',
            'editable' => [
                'codigo' => 'Codigo',
                'cod_especial' => 'Cod. especial',
                'origen' => 'Origen',
                'destino' => 'Destino',
                'nombre_r' => 'Remitente',
                'telefono_r' => 'Telefono remitente',
                'nombre_d' => 'Destinatario',
                'telefono_d' => 'Telefono destinatario',
                'direccion_d' => 'Direccion destinatario',
                'provincia' => 'Provincia',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'observacion' => 'Observacion',
            ],
            'numeric' => ['peso', 'precio'],
        ],
        'certi' => [
            'label' => 'CERTIFICADO',
            'model' => PaqueteCerti::class,
            'table' => 'paquetes_certi',
            'state_col' => 'fk_estado',
            'editable' => [
                'codigo' => 'Codigo',
                'cod_especial' => 'Cod. especial',
                'destinatario' => 'Destinatario',
                'telefono' => 'Telefono',
                'cuidad' => 'Ciudad',
                'zona' => 'Zona',
                'ventanilla' => 'Ventanilla',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'tipo' => 'Tipo',
                'aduana' => 'Aduana',
            ],
            'numeric' => ['peso', 'precio'],
        ],
        'ordi' => [
            'label' => 'ORDINARIO',
            'model' => PaqueteOrdi::class,
            'table' => 'paquetes_ordi',
            'state_col' => 'fk_estado',
            'editable' => [
                'codigo' => 'Codigo',
                'cod_especial' => 'Cod. especial',
                'destinatario' => 'Destinatario',
                'telefono' => 'Telefono',
                'ciudad' => 'Ciudad',
                'zona' => 'Zona',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'aduana' => 'Aduana',
                'observaciones' => 'Observaciones',
            ],
            'numeric' => ['peso', 'precio'],
        ],
        'solicitud' => [
            'label' => 'SOLICITUD',
            'model' => SolicitudCliente::class,
            'table' => 'solicitud_clientes',
            'state_col' => 'estado_id',
            'editable' => [
                'codigo_solicitud' => 'Codigo solicitud',
                'barcode' => 'Barcode',
                'cod_especial' => 'Cod. especial',
                'origen' => 'Origen',
                'ciudad' => 'Destino',
                'nombre_remitente' => 'Remitente',
                'nombre_destinatario' => 'Destinatario',
                'telefono_destinatario' => 'Telefono destinatario',
                'direccion' => 'Direccion',
                'peso' => 'Peso',
                'precio' => 'Precio',
                'observacion' => 'Observacion',
            ],
            'numeric' => ['peso', 'precio'],
        ],
    ];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $estadoId = (int) $request->query('estado_id', 0);

        $query = DB::query()->fromSub($this->buildUnionQuery(), 'p')
            ->when(array_key_exists($type, self::TYPES), fn ($q) => $q->where('type_key', $type))
            ->when($estadoId > 0, fn ($q) => $q->where('estado_id', $estadoId))
            ->when($search !== '', function ($q) use ($search) {
                $q->whereRaw('LOWER(search_blob) LIKE ?', ['%' . mb_strtolower($search) . '%']);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('record_id');

        $paquetes = $query->paginate(25)->withQueryString();
        $this->attachSalidaReports($paquetes->getCollection());
        $estados = Estado::query()->orderBy('nombre_estado')->get(['id', 'nombre_estado']);
        $editing = $this->resolveEditing($request);

        return view('todos_paquetes.index', [
            'paquetes' => $paquetes,
            'estados' => $estados,
            'types' => self::TYPES,
            'carteros' => $this->carterosDisponibles($request),
            'search' => $search,
            'type' => $type,
            'estadoId' => $estadoId,
            'editing' => $editing,
        ]);
    }

    public function updateEstado(Request $request, string $type, int $id)
    {
        $config = $this->typeConfig($type);

        $data = $request->validate([
            'estado_id' => ['required', 'integer', Rule::exists('estados', 'id')],
        ]);

        $model = $this->findPackage($config, $id);
        $stateColumn = $config['state_col'];
        $oldEstado = (int) ($model->{$stateColumn} ?? 0);
        $newEstado = (int) $data['estado_id'];

        if ($oldEstado === $newEstado) {
            return back()->with('success', 'El paquete ya tenia ese estado.');
        }

        DB::transaction(function () use ($model, $stateColumn, $newEstado) {
            $model->{$stateColumn} = $newEstado;
            $model->save();
        });

        return back()->with('success', 'Estado actualizado.');
    }

    public function updateDatos(Request $request, string $type, int $id)
    {
        $config = $this->typeConfig($type);
        $rules = [];

        foreach ($config['editable'] as $field => $label) {
            $rules[$field] = in_array($field, $config['numeric'] ?? [], true)
                ? ['nullable', 'numeric']
                : ['nullable', 'string', 'max:1000'];
        }

        $data = $request->validate($rules);

        $model = $this->findPackage($config, $id);

        DB::transaction(function () use ($model, $data) {
            foreach ($data as $field => $value) {
                $model->{$field} = is_string($value) ? trim($value) : $value;
            }

            $model->save();
        });

        return redirect()
            ->route('todos-paquetes.index', $request->only(['q', 'type', 'estado_id', 'page']))
            ->with('success', 'Datos actualizados.');
    }

    public function reimprimirGuia(string $type, int $id)
    {
        abort_unless($type === 'contrato', 404);

        $contrato = Recojo::query()->findOrFail($id);
        $contrato->loadMissing(['empresa:id,nombre,sigla', 'user.empresa:id,nombre,sigla']);
        $generatedAt = now();

        $pdf = Pdf::loadView('paquetes_contrato.reporte', [
            'contrato' => $contrato,
            'generatedAt' => $generatedAt,
            'verificationUrl' => route('paquetes-contrato.verificar-guia', [
                't' => Crypt::encryptString((string) $contrato->getKey()),
            ]),
        ])->setPaper('letter', 'portrait');

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, 'contrato-' . $contrato->codigo . '-' . $generatedAt->format('Ymd-His') . '.pdf');
    }

    public function reporteSalida(string $codigo)
    {
        $report = CarteroAssignmentReport::query()
            ->with(['assignedUser:id,name,ciudad', 'actorUser:id,name,ciudad'])
            ->where('codigo', $codigo)
            ->firstOrFail();

        $pdf = Pdf::loadView('carteros.asignacion-reporte', [
            'rows' => $report->rows ?? [],
            'assigned_at' => $report->assigned_at,
            'assigned_user' => $report->assignedUser,
            'actor_user' => $report->actorUser,
            'regional' => $report->regional,
            'summary_by_type' => $report->summary_by_type ?? [],
            'total_assigned' => (int) $report->total_assigned,
            'codigo_reporte' => $report->codigo,
        ])->setPaper('A4', 'portrait');

        return $pdf->stream('reporte-salida-' . $report->codigo . '.pdf');
    }

    public function cambiarCarteroReporte(Request $request, string $codigo)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ], [], [
            'user_id' => 'cartero',
        ]);

        $report = CarteroAssignmentReport::query()
            ->with('items')
            ->where('codigo', $codigo)
            ->firstOrFail();

        $newUser = User::query()->findOrFail((int) $data['user_id'], ['id', 'name', 'ciudad']);

        if (!$this->isAllowedCartero($newUser)) {
            return back()->with('error', 'El usuario seleccionado no tiene rol de cartero.');
        }

        if (!$this->isSameCity($request->user(), $newUser)) {
            return back()->with('error', 'Solo puedes cambiar a un cartero de tu mismo departamento.');
        }

        $items = $report->items
            ->map(fn ($item) => [
                'tipo' => strtoupper((string) $item->tipo_paquete),
                'id' => (int) $item->paquete_id,
            ])
            ->filter(fn ($item) => $item['id'] > 0 && $this->carteroColumnForType($item['tipo']) !== null)
            ->unique(fn ($item) => $item['tipo'] . ':' . $item['id'])
            ->values();

        if ($items->isEmpty()) {
            return back()->with('error', 'El reporte ' . $report->codigo . ' no tiene paquetes validos para cambiar de cartero.');
        }

        $updated = 0;
        $estadoCarteroId = $this->estadoIdByName('CARTERO');

        if ($estadoCarteroId <= 0) {
            return back()->with('error', 'No existe el estado CARTERO en la tabla estados.');
        }

        DB::transaction(function () use ($items, $newUser, $report, &$updated, $estadoCarteroId) {
            foreach ($items as $item) {
                $column = $this->carteroColumnForType($item['tipo']);
                if ($column === null) {
                    continue;
                }

                $affected = Cartero::query()
                    ->where($column, $item['id'])
                    ->where('id_estados', $estadoCarteroId)
                    ->update([
                        'id_user' => (int) $newUser->id,
                        'updated_at' => now(),
                    ]);

                $updated += $affected;
            }

            $report->forceFill([
                'assigned_user_id' => (int) $newUser->id,
                'actor_user_id' => (int) auth()->id(),
            ])->save();
        });

        return back()->with(
            $updated > 0 ? 'success' : 'error',
            $updated > 0
                ? 'Reporte ' . $report->codigo . ': ' . $updated . ' paquete(s) cambiados al cartero ' . $newUser->name . '.'
                : 'No se encontro ninguna asignacion activa de cartero para el reporte ' . $report->codigo . '.'
        );
    }

    private function attachSalidaReports($paquetes): void
    {
        $typeMap = [
            'ems' => 'EMS',
            'contrato' => 'CONTRATO',
            'certi' => 'CERTI',
            'ordi' => 'ORDI',
            'solicitud' => 'SOLICITUD',
        ];

        $pairs = collect($paquetes)
            ->map(fn ($paquete) => [
                'tipo' => $typeMap[$paquete->type_key] ?? '',
                'id' => (int) $paquete->record_id,
            ])
            ->filter(fn ($pair) => $pair['tipo'] !== '' && $pair['id'] > 0)
            ->unique(fn ($pair) => $pair['tipo'] . ':' . $pair['id'])
            ->values();

        if ($pairs->isEmpty()) {
            return;
        }

        $items = CarteroAssignmentReportItem::query()
            ->select([
                'cartero_assignment_report_items.tipo_paquete',
                'cartero_assignment_report_items.paquete_id',
                'cartero_assignment_reports.codigo',
                'cartero_assignment_reports.assigned_at',
            ])
            ->join('cartero_assignment_reports', 'cartero_assignment_reports.id', '=', 'cartero_assignment_report_items.cartero_assignment_report_id')
            ->where(function ($query) use ($pairs) {
                foreach ($pairs as $pair) {
                    $query->orWhere(function ($sub) use ($pair) {
                        $sub->where('cartero_assignment_report_items.tipo_paquete', $pair['tipo'])
                            ->where('cartero_assignment_report_items.paquete_id', $pair['id']);
                    });
                }
            })
            ->orderByDesc('cartero_assignment_reports.assigned_at')
            ->get()
            ->unique(fn ($item) => $item->tipo_paquete . ':' . $item->paquete_id)
            ->keyBy(fn ($item) => $item->tipo_paquete . ':' . $item->paquete_id);

        $paquetes->transform(function ($paquete) use ($typeMap, $items) {
            $key = ($typeMap[$paquete->type_key] ?? '') . ':' . (int) $paquete->record_id;
            $report = $items->get($key);
            $paquete->salida_report_codigo = $report?->codigo;
            $paquete->salida_report_assigned_at = $report?->assigned_at;

            return $paquete;
        });
    }

    private function carterosDisponibles(Request $request)
    {
        $hasGlobalDepartmentAccess = (bool) optional($request->user())->isGlobalDepartmentViewer();
        $userCity = $this->normalizeCity((string) optional($request->user())->ciudad);

        if (!$hasGlobalDepartmentAccess && $userCity === '') {
            return collect();
        }

        return User::query()
            ->whereHas('roles', function ($query) {
                $query->whereIn(DB::raw('LOWER(name)'), self::DISTRIBUTION_ASSIGNEE_ROLES);
            })
            ->when(!$hasGlobalDepartmentAccess, function ($query) use ($userCity) {
                $query->whereRaw('TRIM(UPPER(ciudad)) = ?', [$userCity]);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'ciudad']);
    }

    private function isAllowedCartero(User $user): bool
    {
        return User::query()
            ->whereKey($user->id)
            ->whereHas('roles', function ($query) {
                $query->whereIn(DB::raw('LOWER(name)'), self::DISTRIBUTION_ASSIGNEE_ROLES);
            })
            ->exists();
    }

    private function isSameCity($a, $b): bool
    {
        if ((bool) optional($a)->isGlobalDepartmentViewer()) {
            return true;
        }

        return $this->normalizeCity((string) optional($a)->ciudad) !== ''
            && $this->normalizeCity((string) optional($a)->ciudad) === $this->normalizeCity((string) optional($b)->ciudad);
    }

    private function normalizeCity(string $city): string
    {
        return strtoupper(trim($city));
    }

    private function estadoIdByName(string $name): int
    {
        return (int) (Estado::query()
            ->whereRaw('TRIM(UPPER(nombre_estado)) = ?', [strtoupper(trim($name))])
            ->value('id') ?? 0);
    }

    private function carteroColumnForType(string $type): ?string
    {
        return match (strtoupper(trim($type))) {
            'EMS' => 'id_paquetes_ems',
            'CERTI' => 'id_paquetes_certi',
            'ORDI' => 'id_paquetes_ordi',
            'CONTRATO' => 'id_paquetes_contrato',
            'SOLICITUD' => 'id_solicitud_cliente',
            default => null,
        };
    }

    private function buildUnionQuery()
    {
        $queries = [
            $this->selectFor('ems', 'paquetes_ems', 'estado_id', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => 'origen',
                'destino' => 'ciudad',
                'empresa' => null,
                'destinatario' => 'nombre_destinatario',
                'remitente' => 'nombre_remitente',
                'telefono' => 'telefono_destinatario',
                'peso' => 'peso',
                'precio' => 'precio',
            ]),
            $this->selectForContrato(),
            $this->selectFor('certi', 'paquetes_certi', 'fk_estado', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => null,
                'destino' => 'cuidad',
                'empresa' => null,
                'destinatario' => 'destinatario',
                'remitente' => null,
                'telefono' => 'telefono',
                'peso' => 'peso',
                'precio' => 'precio',
            ]),
            $this->selectFor('ordi', 'paquetes_ordi', 'fk_estado', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => null,
                'destino' => 'ciudad',
                'empresa' => null,
                'destinatario' => 'destinatario',
                'remitente' => null,
                'telefono' => 'telefono',
                'peso' => 'peso',
                'precio' => 'precio',
            ]),
            $this->selectFor('solicitud', 'solicitud_clientes', 'estado_id', [
                'codigo' => "COALESCE(NULLIF(TRIM(codigo_solicitud), ''), NULLIF(TRIM(barcode), ''), 'SIN CODIGO')",
                'cod_especial' => 'cod_especial',
                'origen' => 'origen',
                'destino' => 'ciudad',
                'empresa' => null,
                'destinatario' => 'nombre_destinatario',
                'remitente' => 'nombre_remitente',
                'telefono' => 'telefono_destinatario',
                'peso' => 'peso',
                'precio' => 'precio',
            ], true),
        ];

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function selectFor(string $type, string $table, string $stateColumn, array $columns, bool $rawCodigo = false)
    {
        $label = self::TYPES[$type]['label'];
        $selects = [
            DB::raw("'" . $type . "' as type_key"),
            DB::raw("'" . str_replace("'", "''", $label) . "' as tipo"),
            DB::raw($table . '.id as record_id'),
        ];

        foreach (['codigo', 'cod_especial', 'origen', 'destino', 'destinatario', 'remitente', 'telefono'] as $alias) {
            $column = $columns[$alias] ?? null;
            if ($alias === 'codigo' && $rawCodigo) {
                $selects[] = DB::raw($column . ' as codigo');
                continue;
            }

            $selects[] = $column
                ? DB::raw('COALESCE(' . $table . '.' . $column . "::text, '') as " . $alias)
                : DB::raw("'' as " . $alias);
        }

        $empresaColumn = $columns['empresa'] ?? null;
        $selects[] = $empresaColumn
            ? DB::raw('COALESCE(' . $table . '.' . $empresaColumn . "::text, '') as empresa")
            : DB::raw("'' as empresa");

        $selects[] = DB::raw('COALESCE(' . $table . '.' . ($columns['peso'] ?? 'id') . "::text, '') as peso");
        $selects[] = DB::raw('COALESCE(' . $table . '.' . ($columns['precio'] ?? 'id') . "::text, '') as precio");
        $selects[] = DB::raw($table . '.' . $stateColumn . ' as estado_id');
        $selects[] = DB::raw("COALESCE(estados.nombre_estado, 'SIN ESTADO') as estado_nombre");
        $selects[] = DB::raw($table . '.created_at as created_at');
        $selects[] = DB::raw($table . '.updated_at as updated_at');
        $selects[] = DB::raw($this->searchExpression($table, $columns, $rawCodigo) . ' as search_blob');

        return DB::table($table)
            ->leftJoin('estados', 'estados.id', '=', $table . '.' . $stateColumn)
            ->select($selects);
    }

    private function selectForContrato()
    {
        $table = 'paquetes_contrato';
        $label = self::TYPES['contrato']['label'];
        $empresaExpr = "COALESCE(NULLIF(TRIM(emp.nombre), ''), NULLIF(TRIM(emp_user.nombre), ''), '')";

        return DB::table($table)
            ->leftJoin('estados', 'estados.id', '=', $table . '.estados_id')
            ->leftJoin('empresa as emp', 'emp.id', '=', $table . '.empresa_id')
            ->leftJoin('users as u', 'u.id', '=', $table . '.user_id')
            ->leftJoin('empresa as emp_user', 'emp_user.id', '=', 'u.empresa_id')
            ->select([
                DB::raw("'contrato' as type_key"),
                DB::raw("'" . str_replace("'", "''", $label) . "' as tipo"),
                DB::raw($table . '.id as record_id'),
                DB::raw("COALESCE(" . $table . ".codigo::text, '') as codigo"),
                DB::raw("COALESCE(" . $table . ".cod_especial::text, '') as cod_especial"),
                DB::raw("COALESCE(" . $table . ".origen::text, '') as origen"),
                DB::raw("COALESCE(" . $table . ".destino::text, '') as destino"),
                DB::raw($empresaExpr . ' as empresa'),
                DB::raw("COALESCE(" . $table . ".nombre_d::text, '') as destinatario"),
                DB::raw("COALESCE(" . $table . ".nombre_r::text, '') as remitente"),
                DB::raw("COALESCE(" . $table . ".telefono_d::text, '') as telefono"),
                DB::raw("COALESCE(" . $table . ".peso::text, '') as peso"),
                DB::raw("COALESCE(" . $table . ".precio::text, '') as precio"),
                DB::raw($table . '.estados_id as estado_id'),
                DB::raw("COALESCE(estados.nombre_estado, 'SIN ESTADO') as estado_nombre"),
                DB::raw($table . '.created_at as created_at'),
                DB::raw($table . '.updated_at as updated_at'),
                DB::raw(
                    "LOWER(CONCAT_WS(' ', " .
                    "COALESCE(" . $table . ".codigo::text, ''), " .
                    "COALESCE(" . $table . ".cod_especial::text, ''), " .
                    "COALESCE(" . $table . ".origen::text, ''), " .
                    "COALESCE(" . $table . ".destino::text, ''), " .
                    $empresaExpr . ", " .
                    "COALESCE(" . $table . ".nombre_d::text, ''), " .
                    "COALESCE(" . $table . ".nombre_r::text, ''), " .
                    "COALESCE(" . $table . ".telefono_d::text, ''), " .
                    "COALESCE(estados.nombre_estado, '')" .
                    ")) as search_blob"
                ),
            ]);
    }

    private function searchExpression(string $table, array $columns, bool $rawCodigo): string
    {
        $parts = [];
        foreach ($columns as $alias => $column) {
            if ($column === null) {
                continue;
            }

            $parts[] = $rawCodigo && $alias === 'codigo'
                ? 'COALESCE((' . $column . ")::text, '')"
                : 'COALESCE(' . $table . '.' . $column . "::text, '')";
        }

        $parts[] = "COALESCE(estados.nombre_estado, '')";

        return 'LOWER(CONCAT_WS(\' \', ' . implode(', ', $parts) . '))';
    }

    private function resolveEditing(Request $request): ?array
    {
        $type = trim((string) $request->query('edit_type', ''));
        $id = (int) $request->query('edit_id', 0);

        if (!array_key_exists($type, self::TYPES) || $id <= 0) {
            return null;
        }

        $config = self::TYPES[$type];
        $model = $this->findPackage($config, $id);

        return [
            'type' => $type,
            'id' => $id,
            'label' => $config['label'],
            'fields' => $config['editable'],
            'numeric' => $config['numeric'] ?? [],
            'values' => collect(array_keys($config['editable']))
                ->mapWithKeys(fn ($field) => [$field => $model->{$field}])
                ->all(),
        ];
    }

    private function typeConfig(string $type): array
    {
        abort_unless(array_key_exists($type, self::TYPES), 404);

        return self::TYPES[$type];
    }

    private function findPackage(array $config, int $id): Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        return $modelClass::query()->findOrFail($id);
    }

}

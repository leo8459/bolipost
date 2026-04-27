<?php

namespace App\Http\Controllers;

use App\Models\Estado;
use App\Models\Evento;
use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use App\Models\SolicitudCliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TodosPaquetesController extends Controller
{
    private const DATA_EDIT_EVENT = 'Correccion de datos del paquete';

    private const TYPES = [
        'ems' => [
            'label' => 'EMS',
            'model' => PaqueteEms::class,
            'table' => 'paquetes_ems',
            'state_col' => 'estado_id',
            'event_table' => 'eventos_ems',
            'code_col' => 'codigo',
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
            'event_table' => 'eventos_contrato',
            'code_col' => 'codigo',
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
            'event_table' => 'eventos_certi',
            'code_col' => 'codigo',
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
            'event_table' => 'eventos_ordi',
            'code_col' => 'codigo',
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
            'event_table' => 'eventos_tiktoker',
            'code_col' => 'codigo_solicitud',
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
        $estados = Estado::query()->orderBy('nombre_estado')->get(['id', 'nombre_estado']);
        $editing = $this->resolveEditing($request);

        return view('todos_paquetes.index', [
            'paquetes' => $paquetes,
            'estados' => $estados,
            'types' => self::TYPES,
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

        DB::transaction(function () use ($model, $stateColumn, $newEstado, $config) {
            $model->{$stateColumn} = $newEstado;
            $model->save();

            $estadoNombre = (string) (Estado::query()->whereKey($newEstado)->value('nombre_estado') ?? $newEstado);
            $eventoId = $this->resolveEventId('Cambio manual de estado a ' . strtoupper(trim($estadoNombre)) . ' desde Todos los paquetes.');
            $this->registerEvent($config, $model, $eventoId);
        });

        return back()->with('success', 'Estado actualizado y evento registrado.');
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

        DB::transaction(function () use ($model, $data, $config) {
            foreach ($data as $field => $value) {
                $model->{$field} = is_string($value) ? trim($value) : $value;
            }

            $model->save();
            $eventoId = $this->resolveEventId(self::DATA_EDIT_EVENT);
            $this->registerEvent($config, $model, $eventoId);
        });

        return redirect()
            ->route('todos-paquetes.index', $request->only(['q', 'type', 'estado_id', 'page']))
            ->with('success', 'Datos actualizados y evento registrado.');
    }

    private function buildUnionQuery()
    {
        $queries = [
            $this->selectFor('ems', 'paquetes_ems', 'estado_id', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => 'origen',
                'destino' => 'ciudad',
                'destinatario' => 'nombre_destinatario',
                'remitente' => 'nombre_remitente',
                'telefono' => 'telefono_destinatario',
                'peso' => 'peso',
                'precio' => 'precio',
            ]),
            $this->selectFor('contrato', 'paquetes_contrato', 'estados_id', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => 'origen',
                'destino' => 'destino',
                'destinatario' => 'nombre_d',
                'remitente' => 'nombre_r',
                'telefono' => 'telefono_d',
                'peso' => 'peso',
                'precio' => 'precio',
            ]),
            $this->selectFor('certi', 'paquetes_certi', 'fk_estado', [
                'codigo' => 'codigo',
                'cod_especial' => 'cod_especial',
                'origen' => null,
                'destino' => 'cuidad',
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

    private function resolveEventId(string $eventName): int
    {
        return (int) Evento::query()->firstOrCreate(['nombre_evento' => $eventName])->id;
    }

    private function registerEvent(array $config, Model $model, int $eventoId): void
    {
        $codigo = trim((string) ($model->{$config['code_col']} ?? ''));
        if ($codigo === '' && $model instanceof SolicitudCliente) {
            $codigo = trim((string) ($model->barcode ?? ''));
        }

        if ($codigo === '' || $eventoId <= 0) {
            return;
        }

        $payload = [
            'codigo' => $codigo,
            'evento_id' => $eventoId,
            'user_id' => (int) Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if ($config['event_table'] === 'eventos_tiktoker') {
            $payload['cliente_id'] = $model instanceof SolicitudCliente ? $model->cliente_id : null;
        }

        DB::table($config['event_table'])->insert($payload);
    }
}

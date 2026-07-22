<?php

namespace App\Livewire;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class EventosSiopTabla extends Component
{
    use WithPagination;

    private const ROUTE_PERMISSION = 'eventos-siop.index';

    public $search = '';
    public $searchQuery = '';
    public $source_filter = '';
    public $sourceFilterQuery = '';
    public $fecha_desde = '';
    public $fecha_hasta = '';
    public $fechaDesdeQuery = '';
    public $fechaHastaQuery = '';
    public $editingId = null;
    public $editingTable = '';
    public $source_table = 'eventos_ems';
    public $codigo = '';
    public $evento_id = '';
    public $user_id = '';
    public $cliente_id = '';
    public $modalDataReady = false;

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        $query = trim((string) request()->query('q', ''));

        if ($query !== '') {
            $this->search = $query;
            $this->searchQuery = $query;
        }
    }

    public function searchRegistros(): void
    {
        $this->searchQuery = trim((string) $this->search);
        $this->sourceFilterQuery = trim((string) $this->source_filter);
        $this->fechaDesdeQuery = trim((string) $this->fecha_desde);
        $this->fechaHastaQuery = trim((string) $this->fecha_hasta);

        if ($this->fechaDesdeQuery !== '' && $this->fechaHastaQuery !== '' && $this->fechaHastaQuery < $this->fechaDesdeQuery) {
            [$this->fechaDesdeQuery, $this->fechaHastaQuery] = [$this->fechaHastaQuery, $this->fechaDesdeQuery];
            [$this->fecha_desde, $this->fecha_hasta] = [$this->fechaDesdeQuery, $this->fechaHastaQuery];
        }

        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset([
            'search',
            'searchQuery',
            'source_filter',
            'sourceFilterQuery',
            'fecha_desde',
            'fecha_hasta',
            'fechaDesdeQuery',
            'fechaHastaQuery',
        ]);

        $this->resetPage();
    }

    public function updatedSourceTable(string $value): void
    {
        if (! $this->supportsClienteId($value)) {
            $this->cliente_id = '';
        }
    }

    public function openCreateModal(): void
    {
        $this->authorizePermission($this->featurePermission('create'));
        $this->resetForm();
        $this->editingId = null;
        $this->editingTable = '';
        $this->modalDataReady = true;
        $this->dispatch('openEventosSiopModal');
    }

    public function openEditModal(string $table, int $id): void
    {
        $this->authorizePermission($this->featurePermission('edit'));

        if (! $this->isAllowedSourceTable($table)) {
            return;
        }

        $registro = DB::table($table)->where('id', $id)->first();

        if (! $registro) {
            return;
        }

        $this->editingId = (int) $registro->id;
        $this->editingTable = $table;
        $this->source_table = $table;
        $this->codigo = (string) $registro->codigo;
        $this->evento_id = (string) $registro->evento_id;
        $this->user_id = $registro->user_id !== null ? (string) $registro->user_id : '';
        $this->cliente_id = property_exists($registro, 'cliente_id') && $registro->cliente_id !== null
            ? (string) $registro->cliente_id
            : '';

        $this->modalDataReady = true;
        $this->dispatch('openEventosSiopModal');
    }

    public function save(): void
    {
        $this->authorizePermission($this->featurePermission($this->editingId ? 'edit' : 'create'));
        $this->validate($this->rules());

        $targetTable = $this->editingTable !== '' ? $this->editingTable : $this->source_table;

        if (! $this->isAllowedSourceTable($targetTable)) {
            abort(422, 'La tabla de eventos seleccionada no es valida.');
        }

        $payload = [
            'codigo' => trim((string) $this->codigo),
            'evento_id' => (int) $this->evento_id,
            'user_id' => $this->user_id !== '' ? (int) $this->user_id : null,
        ];

        if ($this->supportsClienteId($targetTable)) {
            $payload['cliente_id'] = $this->cliente_id !== '' ? (int) $this->cliente_id : null;
        }

        if ($this->editingId) {
            DB::table($targetTable)
                ->where('id', $this->editingId)
                ->update(array_merge($payload, ['updated_at' => now()]));

            session()->flash('success', 'Evento SIOP actualizado correctamente.');
        } else {
            DB::table($targetTable)->insert(array_merge($payload, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            session()->flash('success', 'Evento SIOP registrado correctamente.');
        }

        $this->dispatch('closeEventosSiopModal');
        $this->resetForm();
    }

    public function delete(string $table, int $id): void
    {
        $this->authorizePermission($this->featurePermission('delete'));

        if (! $this->isAllowedSourceTable($table)) {
            return;
        }

        DB::table($table)->where('id', $id)->delete();
        session()->flash('success', 'Evento SIOP eliminado correctamente.');
    }

    public function resetForm(): void
    {
        $this->reset(['codigo', 'evento_id', 'user_id', 'cliente_id', 'source_table']);
        $this->source_table = 'eventos_ems';
        $this->editingId = null;
        $this->editingTable = '';
        $this->resetValidation();
    }

    public function render()
    {
        $filteredQuery = $this->applyFilters(DB::query()->fromSub($this->buildUnionQuery(), 'eventos_siop'));

        $registros = (clone $filteredQuery)
            ->orderByDesc('created_at')
            ->orderByDesc('record_id')
            ->simplePaginate(30);

        $registros->setCollection(
            $registros->getCollection()->map(function ($registro) {
                $registro->imagen = $this->resolveImageForRecord($registro);
                $registro->reprint_url = $this->resolveReprintUrlForRecord($registro);

                return $registro;
            })
        );

        $resumen = $registros->getCollection()
            ->groupBy('source_table')
            ->map(function ($items) {
                $first = $items->first();

                return (object) [
                    'source_table' => $first->source_table,
                    'servicio' => $first->servicio,
                    'total' => $items->count(),
                ];
            })
            ->sortBy('servicio')
            ->values();

        return view('livewire.eventos-siop-tabla', [
            'registros' => $registros,
            'resumen' => $resumen,
            'sourceOptions' => $this->sourceOptions(),
            'eventos' => DB::table('eventos')->orderBy('nombre_evento')->get(['id', 'nombre_evento']),
            'users' => $this->modalDataReady
                ? DB::table('users')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'clientes' => $this->modalDataReady
                ? DB::table('clientes')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'canEventosCreate' => $this->userCan($this->featurePermission('create')),
            'canEventosEdit' => $this->userCan($this->featurePermission('edit')),
            'canEventosDelete' => $this->userCan($this->featurePermission('delete')),
            'supportsClienteId' => $this->supportsClienteId($this->editingTable !== '' ? $this->editingTable : $this->source_table),
        ]);
    }

    protected function rules(): array
    {
        return [
            'source_table' => ['required', 'string', Rule::in(array_keys($this->sourceOptions()))],
            'codigo' => ['required', 'string', 'max:255'],
            'evento_id' => ['required', 'integer', Rule::exists('eventos', 'id')],
            'user_id' => $this->supportsClienteId($this->editingTable !== '' ? $this->editingTable : $this->source_table)
                ? ['nullable', 'integer', Rule::exists('users', 'id')]
                : ['required', 'integer', Rule::exists('users', 'id')],
            'cliente_id' => $this->supportsClienteId($this->editingTable !== '' ? $this->editingTable : $this->source_table)
                ? ['nullable', 'integer', Rule::exists('clientes', 'id')]
                : ['nullable'],
        ];
    }

    private function applyFilters(Builder $query): Builder
    {
        $search = trim((string) $this->searchQuery);
        $source = trim((string) $this->sourceFilterQuery);
        $fechaDesde = trim((string) $this->fechaDesdeQuery);
        $fechaHasta = trim((string) $this->fechaHastaQuery);

        return $query
            ->when($search !== '', function (Builder $builder) use ($search) {
                $builder->where(function (Builder $sub) use ($search) {
                    $sub->where('codigo', 'ILIKE', '%' . $search . '%')
                        ->orWhere('evento_nombre', 'ILIKE', '%' . $search . '%')
                        ->orWhere('usuario_nombre', 'ILIKE', '%' . $search . '%')
                        ->orWhere('cliente_nombre', 'ILIKE', '%' . $search . '%')
                        ->orWhere('actor_nombre', 'ILIKE', '%' . $search . '%')
                        ->orWhere('servicio', 'ILIKE', '%' . $search . '%');
                });
            })
            ->when($source !== '' && $this->isAllowedSourceTable($source), function (Builder $builder) use ($source) {
                $builder->where('source_table', $source);
            })
            ->when($fechaDesde !== '', function (Builder $builder) use ($fechaDesde) {
                $builder->whereDate('created_at', '>=', $fechaDesde);
            })
            ->when($fechaHasta !== '', function (Builder $builder) use ($fechaHasta) {
                $builder->whereDate('created_at', '<=', $fechaHasta);
            });
    }

    private function buildUnionQuery(): Builder
    {
        $queries = [];

        if (Schema::hasTable('eventos_ems')) {
            $queries[] = DB::table('eventos_ems as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->selectRaw("
                    t.id as record_id,
                    'eventos_ems' as source_table,
                    'EMS' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    t.user_id,
                    u.name as usuario_nombre,
                    NULL::bigint as cliente_id,
                    NULL::text as cliente_nombre,
                    u.name as actor_nombre,
                    t.created_at,
                    NULL::text as imagen
                ");
        }

        if (Schema::hasTable('eventos_certi')) {
            $queries[] = DB::table('eventos_certi as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->selectRaw("
                    t.id as record_id,
                    'eventos_certi' as source_table,
                    'CERTI' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    t.user_id,
                    u.name as usuario_nombre,
                    NULL::bigint as cliente_id,
                    NULL::text as cliente_nombre,
                    u.name as actor_nombre,
                    t.created_at,
                    NULL::text as imagen
                ");
        }

        if (Schema::hasTable('eventos_ordi')) {
            $queries[] = DB::table('eventos_ordi as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->selectRaw("
                    t.id as record_id,
                    'eventos_ordi' as source_table,
                    'ORDI' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    t.user_id,
                    u.name as usuario_nombre,
                    NULL::bigint as cliente_id,
                    NULL::text as cliente_nombre,
                    u.name as actor_nombre,
                    t.created_at,
                    NULL::text as imagen
                ");
        }

        if (Schema::hasTable('eventos_contrato')) {
            $queries[] = DB::table('eventos_contrato as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->selectRaw("
                    t.id as record_id,
                    'eventos_contrato' as source_table,
                    'CONTRATO' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    t.user_id,
                    u.name as usuario_nombre,
                    NULL::bigint as cliente_id,
                    NULL::text as cliente_nombre,
                    u.name as actor_nombre,
                    t.created_at,
                    NULL::text as imagen
                ");
        }

        if (Schema::hasTable('eventos_despacho')) {
            $queries[] = DB::table('eventos_despacho as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->selectRaw("
                    t.id as record_id,
                    'eventos_despacho' as source_table,
                    'DESPACHO' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    t.user_id,
                    u.name as usuario_nombre,
                    NULL::bigint as cliente_id,
                    NULL::text as cliente_nombre,
                    u.name as actor_nombre,
                    t.created_at,
                    NULL::text as imagen
                ");
        }

        if (Schema::hasTable('eventos_tiktoker')) {
            $queries[] = DB::table('eventos_tiktoker as t')
                ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
                ->leftJoin('users as u', 'u.id', '=', 't.user_id')
                ->leftJoin('clientes as c', 'c.id', '=', 't.cliente_id')
                ->selectRaw("
                    t.id as record_id,
                    'eventos_tiktoker' as source_table,
                    'TIKTOKER' as servicio,
                    t.codigo,
                    t.evento_id,
                    e.nombre_evento as evento_nombre,
                    t.user_id,
                    u.name as usuario_nombre,
                    t.cliente_id,
                    c.name as cliente_nombre,
                    COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(c.name), ''), 'Sin actor') as actor_nombre,
                    t.created_at,
                    NULL::text as imagen
                ");
        }

        if ($queries === []) {
            return DB::table('eventos as e')->selectRaw("
                NULL::bigint as record_id,
                '' as source_table,
                '' as servicio,
                '' as codigo,
                NULL::bigint as evento_id,
                '' as evento_nombre,
                NULL::bigint as user_id,
                '' as usuario_nombre,
                NULL::bigint as cliente_id,
                '' as cliente_nombre,
                '' as actor_nombre,
                NULL::timestamp as created_at,
                NULL as imagen
            ")->whereRaw('1 = 0');
        }

        $union = array_shift($queries);

        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        return $union;
    }

    private function resolveImageForRecord(object $registro): ?string
    {
        $codigo = trim((string) ($registro->codigo ?? ''));

        if ($codigo === '') {
            return null;
        }

        return match ($registro->source_table ?? '') {
            'eventos_ems' => $this->resolveEmsImage($codigo),
            'eventos_certi' => $this->resolveSimpleImage('paquetes_certi', $codigo),
            'eventos_ordi' => $this->resolveSimpleImage('paquetes_ordi', $codigo),
            'eventos_contrato' => $this->resolveSimpleImage('paquetes_contrato', $codigo),
            'eventos_tiktoker' => $this->resolveTiktokerImage($codigo),
            default => null,
        };
    }

    private function resolveReprintUrlForRecord(object $registro): ?string
    {
        $codigo = trim((string) ($registro->codigo ?? ''));
        if ($codigo === '') {
            return null;
        }

        if (($registro->source_table ?? '') === 'eventos_despacho') {
            $despachoId = $this->resolveDespachoId($codigo);

            return $despachoId ? route('despachos.expedicion.pdf', ['id' => $despachoId], false) : null;
        }

        $type = $this->packageTypeForSourceTable((string) ($registro->source_table ?? ''));
        if ($type === null) {
            return null;
        }

        $packageId = $this->resolvePackageId($type, $codigo);

        return $packageId ? route('todos-paquetes.guia', ['type' => $type, 'id' => $packageId], false) : null;
    }

    private function packageTypeForSourceTable(string $sourceTable): ?string
    {
        return match ($sourceTable) {
            'eventos_ems' => 'ems',
            'eventos_certi' => 'certi',
            'eventos_ordi' => 'ordi',
            'eventos_contrato' => 'contrato',
            'eventos_tiktoker' => 'solicitud',
            default => null,
        };
    }

    private function resolvePackageId(string $type, string $codigo): ?int
    {
        $rowId = match ($type) {
            'ems' => DB::table('paquetes_ems')
                ->whereRaw('TRIM(UPPER(COALESCE(codigo, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orWhereRaw('TRIM(UPPER(COALESCE(cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            'certi' => DB::table('paquetes_certi')
                ->whereRaw('TRIM(UPPER(COALESCE(codigo, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orWhereRaw('TRIM(UPPER(COALESCE(cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            'ordi' => DB::table('paquetes_ordi')
                ->whereRaw('TRIM(UPPER(COALESCE(codigo, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orWhereRaw('TRIM(UPPER(COALESCE(cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            'contrato' => DB::table('paquetes_contrato')
                ->whereRaw('TRIM(UPPER(COALESCE(codigo, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orWhereRaw('TRIM(UPPER(COALESCE(cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            'solicitud' => DB::table('solicitud_clientes')
                ->whereRaw('TRIM(UPPER(COALESCE(codigo_solicitud, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orWhereRaw('TRIM(UPPER(COALESCE(barcode, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orWhereRaw('TRIM(UPPER(COALESCE(cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo])
                ->orderByDesc('id')
                ->value('id'),
            default => null,
        };

        return $rowId ? (int) $rowId : null;
    }

    private function resolveDespachoId(string $codigo): ?int
    {
        $rowId = DB::table('despacho')
            ->whereRaw('TRIM(UPPER(COALESCE(identificador, \'\'))) = TRIM(UPPER(?))', [$codigo])
            ->orWhereRaw('TRIM(UPPER(COALESCE(nro_despacho::text, \'\'))) = TRIM(UPPER(?))', [$codigo])
            ->orWhereRaw('TRIM(UPPER(id::text)) = TRIM(UPPER(?))', [$codigo])
            ->orderByDesc('id')
            ->value('id');

        return $rowId ? (int) $rowId : null;
    }

    private function resolveEmsImage(string $codigo): ?string
    {
        return DB::table('paquetes_ems as pe')
            ->leftJoin('cartero as c', 'c.id_paquetes_ems', '=', 'pe.id')
            ->whereRaw('TRIM(UPPER(pe.codigo)) = TRIM(UPPER(?))', [$codigo])
            ->orderByRaw('c.updated_at DESC NULLS LAST, c.id DESC, pe.id DESC')
            ->value(DB::raw('COALESCE(c.imagen_devolucion, c.imagen, pe.imagen)'));
    }

    private function resolveSimpleImage(string $table, string $codigo): ?string
    {
        return DB::table($table)
            ->whereRaw('TRIM(UPPER(codigo)) = TRIM(UPPER(?))', [$codigo])
            ->orderByDesc('id')
            ->value('imagen');
    }

    private function resolveTiktokerImage(string $codigo): ?string
    {
        return DB::table('solicitud_clientes')
            ->where(function ($query) use ($codigo) {
                $query->whereRaw('TRIM(UPPER(COALESCE(codigo_solicitud, \'\'))) = TRIM(UPPER(?))', [$codigo])
                    ->orWhereRaw('TRIM(UPPER(COALESCE(barcode, \'\'))) = TRIM(UPPER(?))', [$codigo])
                    ->orWhereRaw('TRIM(UPPER(COALESCE(cod_especial, \'\'))) = TRIM(UPPER(?))', [$codigo]);
            })
            ->orderByDesc('id')
            ->value('imagen');
    }

    private function sourceOptions(): array
    {
        return [
            'eventos_ems' => 'EMS',
            'eventos_certi' => 'CERTI',
            'eventos_ordi' => 'ORDI',
            'eventos_contrato' => 'CONTRATO',
            'eventos_despacho' => 'DESPACHO',
            'eventos_tiktoker' => 'TIKTOKER',
        ];
    }

    private function supportsClienteId(string $table): bool
    {
        return $table === 'eventos_tiktoker';
    }

    private function isAllowedSourceTable(string $table): bool
    {
        return array_key_exists($table, $this->sourceOptions());
    }

    private function featurePermission(string $action): string
    {
        return 'feature.' . self::ROUTE_PERMISSION . '.' . $action;
    }

    private function userCan(string $permission): bool
    {
        $user = auth()->user();

        return $user ? $user->can($permission) : false;
    }

    private function authorizePermission(string $permission): void
    {
        if (! $this->userCan($permission)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }
}

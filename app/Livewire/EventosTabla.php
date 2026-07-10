<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class EventosTabla extends Component
{
    use WithPagination;
    private const TIPO_ROUTE_PERMISSIONS = [
        'ems' => 'eventos-ems.index',
        'certi' => 'eventos-certi.index',
        'ordi' => 'eventos-ordi.index',
        'contrato' => 'eventos-contrato.index',
        'tiktoker' => 'eventos-tiktoker.index',
        'despacho' => 'eventos-despacho.index',
    ];

    public $tipo = 'ems';
    public $search = '';
    public $searchQuery = '';
    public $fecha_desde = '';
    public $fecha_hasta = '';
    public $descripcion_evento = '';
    public $fechaDesdeQuery = '';
    public $fechaHastaQuery = '';
    public $descripcionEventoQuery = '';
    public $editingId = null;
    public $codigo = '';
    public $evento_id = '';
    public $user_id = '';
    public $cliente_id = '';

    protected $paginationTheme = 'bootstrap';

    public function mount(string $tipo = 'ems'): void
    {
        $this->tipo = $this->normalizeTipo($tipo);
        $query = trim((string) request()->query('q', ''));

        if ($query !== '') {
            $this->search = $query;
            $this->searchQuery = $query;
        }
    }

    public function searchRegistros(): void
    {
        $this->searchQuery = trim((string) $this->search);
        $this->fechaDesdeQuery = trim((string) $this->fecha_desde);
        $this->fechaHastaQuery = trim((string) $this->fecha_hasta);
        $this->descripcionEventoQuery = trim((string) $this->descripcion_evento);

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
            'fecha_desde',
            'fecha_hasta',
            'descripcion_evento',
            'fechaDesdeQuery',
            'fechaHastaQuery',
            'descripcionEventoQuery',
        ]);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorizePermission($this->featurePermission('create'));
        $this->resetForm();
        $this->editingId = null;
        $this->dispatch('openEventosTablaModal');
    }

    public function openEditModal(int $id): void
    {
        $this->authorizePermission($this->featurePermission('edit'));
        $registro = $this->scopedTableQuery()->where('id', $id)->first();

        if (!$registro) {
            return;
        }

        $this->editingId = (int) $registro->id;
        $this->codigo = (string) $registro->codigo;
        $this->evento_id = (string) $registro->evento_id;
        $this->user_id = (string) $registro->user_id;
        $this->cliente_id = property_exists($registro, 'cliente_id') && $registro->cliente_id !== null
            ? (string) $registro->cliente_id
            : '';

        $this->dispatch('openEventosTablaModal');
    }

    public function save(): void
    {
        $this->authorizePermission($this->featurePermission($this->editingId ? 'edit' : 'create'));
        $this->validate($this->rules());

        $payload = [
            'codigo' => trim((string) $this->codigo),
            'evento_id' => (int) $this->evento_id,
            'user_id' => $this->user_id !== '' ? (int) $this->user_id : null,
        ];

        if ($this->supportsClienteId()) {
            $payload['cliente_id'] = $this->cliente_id !== '' ? (int) $this->cliente_id : null;
        }

        if ($this->editingId) {
            DB::table($this->tableName())
                ->where('id', $this->editingId)
                ->update(array_merge($payload, ['updated_at' => now()]));

            session()->flash('success', $this->pageConfig()['singular'] . ' actualizado correctamente.');
        } else {
            DB::table($this->tableName())->insert(array_merge($payload, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));

            session()->flash('success', $this->pageConfig()['singular'] . ' creado correctamente.');
        }

        $this->dispatch('closeEventosTablaModal');
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorizePermission($this->featurePermission('delete'));
        $deleted = $this->scopedTableQuery()->where('id', $id)->delete();

        if ($deleted === 0) {
            abort(403, 'No tienes permiso para eliminar este registro.');
        }

        session()->flash('success', $this->pageConfig()['singular'] . ' eliminado correctamente.');
    }

    public function resetForm(): void
    {
        $this->reset(['codigo', 'evento_id', 'user_id', 'cliente_id']);
        $this->resetValidation();
    }

    public function render()
    {
        $q = trim((string) $this->searchQuery);
        $fechaDesde = trim((string) $this->fechaDesdeQuery);
        $fechaHasta = trim((string) $this->fechaHastaQuery);
        $descripcionEvento = trim((string) $this->descripcionEventoQuery);
        $table = $this->tableName();
        $supportsClienteId = $this->supportsClienteId();
        $contratoBuscado = null;
        $usuarioNombreSelect = $this->tipo === 'ems'
            ? "COALESCE(
                (
                    SELECT u2.name
                    FROM paquetes_ems pe2
                    LEFT JOIN cartero c2 ON c2.id_paquetes_ems = pe2.id
                    LEFT JOIN users u2 ON u2.id = c2.id_user
                    WHERE TRIM(UPPER(pe2.codigo)) = TRIM(UPPER(t.codigo))
                        AND t.evento_id = 316
                    ORDER BY c2.updated_at DESC NULLS LAST, c2.id DESC
                    LIMIT 1
                ),
                u.name
            ) as usuario_nombre"
            : 'u.name as usuario_nombre';
        $imagenSelect = match ($this->tipo) {
            'ems' => "(
                SELECT COALESCE(c2.imagen, pe2.imagen)
                FROM paquetes_ems pe2
                LEFT JOIN cartero c2 ON c2.id_paquetes_ems = pe2.id
                WHERE TRIM(UPPER(pe2.codigo)) = TRIM(UPPER(t.codigo))
                ORDER BY c2.updated_at DESC NULLS LAST, c2.id DESC, pe2.id DESC
                LIMIT 1
            ) as imagen",
            default => 'NULL as imagen',
        };

        $empresaId = $this->authenticatedEmpresaId();

        $registrosQuery = DB::table($table . ' as t')
            ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->select([
                't.id',
                't.codigo',
                't.evento_id',
                't.user_id',
                't.created_at',
                'e.nombre_evento as evento_nombre',
                DB::raw($usuarioNombreSelect),
                DB::raw($imagenSelect),
            ])
            ->when($q !== '', function ($query) use ($q, $supportsClienteId, $table) {
                $query->where(function ($sub) use ($q, $supportsClienteId, $table) {
                    $sub->where('t.codigo', 'ILIKE', '%' . $q . '%')
                        ->orWhere('e.nombre_evento', 'ILIKE', '%' . $q . '%')
                        ->orWhere('u.name', 'ILIKE', '%' . $q . '%');

                    if (
                        $table === 'eventos_contrato'
                        && Schema::hasTable('paquetes_contrato')
                        && Schema::hasColumn('paquetes_contrato', 'codigo_madre')
                    ) {
                        $sub->orWhereIn('t.codigo', function ($childQuery) use ($q) {
                            $childQuery->select('codigo')
                                ->from('paquetes_contrato')
                                ->where('codigo_madre', 'ILIKE', '%' . $q . '%');
                        });
                    }

                    if ($supportsClienteId) {
                        $sub->orWhere('c.name', 'ILIKE', '%' . $q . '%');
                    }
                });
            })
            ->when($fechaDesde !== '', function ($query) use ($fechaDesde) {
                $query->whereDate('t.created_at', '>=', $fechaDesde);
            })
            ->when($fechaHasta !== '', function ($query) use ($fechaHasta) {
                $query->whereDate('t.created_at', '<=', $fechaHasta);
            })
            ->when($this->tipo === 'contrato' && $descripcionEvento !== '', function ($query) use ($descripcionEvento) {
                $query->where('e.nombre_evento', 'ILIKE', '%' . $descripcionEvento . '%');
            })
            ->when($this->tipo === 'contrato' && $empresaId > 0, function ($query) use ($empresaId) {
                $query->whereExists(function ($subQuery) use ($empresaId) {
                    $subQuery->selectRaw('1')
                        ->from('paquetes_contrato as pc')
                        ->whereColumn('pc.codigo', 't.codigo')
                        ->where('pc.empresa_id', $empresaId);
                });
            });

        if ($supportsClienteId) {
            $registrosQuery
                ->leftJoin('clientes as c', 'c.id', '=', 't.cliente_id')
                ->addSelect([
                    't.cliente_id',
                    DB::raw("COALESCE(NULLIF(TRIM(u.name), ''), NULLIF(TRIM(c.name), '')) as actor_nombre"),
                    'c.name as cliente_nombre',
                ]);
        }

        $registros = $registrosQuery
            ->orderByDesc('t.id')
            ->paginate(100);

        if ($this->tipo === 'contrato' && $q !== '') {
            $contratoBuscado = DB::table('paquetes_contrato as p')
                ->leftJoin('empresa as emp', 'emp.id', '=', 'p.empresa_id')
                ->select([
                    'p.id',
                    'p.codigo',
                    'p.codigo_madre',
                    'p.cod_especial',
                    'p.nombre_r',
                    'p.nombre_d',
                    'p.destino',
                    'p.telefono_d',
                    'p.imagen',
                    'p.updated_at',
                    'emp.nombre as empresa_nombre',
                    'emp.sigla as empresa_sigla',
                ])
                ->where(function ($query) use ($q) {
                    $query->where('p.codigo', 'ILIKE', '%' . $q . '%')
                        ->orWhere('p.cod_especial', 'ILIKE', '%' . $q . '%');
                })
                ->when($empresaId > 0, function ($query) use ($empresaId) {
                    $query->where('p.empresa_id', $empresaId);
                })
                ->orderByRaw('CASE WHEN upper(trim(p.codigo)) = upper(trim(?)) THEN 0 ELSE 1 END', [$q])
                ->orderByDesc('p.updated_at')
                ->first();
        }

        return view('livewire.eventos-tabla', [
            'registros' => $registros,
            'eventos' => DB::table('eventos')->orderBy('nombre_evento')->get(['id', 'nombre_evento']),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'clientes' => $supportsClienteId
                ? DB::table('clientes')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'config' => $this->pageConfig(),
            'canEventosCreate' => $this->userCan($this->featurePermission('create')),
            'canEventosEdit' => $this->userCan($this->featurePermission('edit')),
            'canEventosDelete' => $this->userCan($this->featurePermission('delete')),
            'supportsClienteId' => $supportsClienteId,
            'contratoBuscado' => $contratoBuscado,
            'fechaDesdeQuery' => $fechaDesde,
            'fechaHastaQuery' => $fechaHasta,
            'descripcionEventoQuery' => $descripcionEvento,
        ]);
    }

    protected function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:255'],
            'evento_id' => ['required', 'integer', Rule::exists('eventos', 'id')],
            'user_id' => $this->supportsClienteId()
                ? ['nullable', 'integer', Rule::exists('users', 'id')]
                : ['required', 'integer', Rule::exists('users', 'id')],
            'cliente_id' => $this->supportsClienteId()
                ? ['nullable', 'integer', Rule::exists('clientes', 'id')]
                : ['nullable'],
        ];
    }

    protected function tableName(): string
    {
        return $this->pageConfig()['table'];
    }

    protected function normalizeTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        return in_array($tipo, ['ems', 'certi', 'ordi', 'contrato', 'tiktoker', 'despacho'], true) ? $tipo : 'ems';
    }

    protected function pageConfig(): array
    {
        return match ($this->tipo) {
            'certi' => [
                'title' => 'Eventos CERTI',
                'singular' => 'Registro CERTI',
                'table' => 'eventos_certi',
            ],
            'ordi' => [
                'title' => 'Eventos ORDI',
                'singular' => 'Registro ORDI',
                'table' => 'eventos_ordi',
            ],
            'contrato' => [
                'title' => 'Eventos CONTRATO',
                'singular' => 'Registro CONTRATO',
                'table' => 'eventos_contrato',
            ],
            'tiktoker' => [
                'title' => 'Eventos TIKTOKER',
                'singular' => 'Registro TIKTOKER',
                'table' => 'eventos_tiktoker',
            ],
            'despacho' => [
                'title' => 'Eventos Despacho',
                'singular' => 'Registro Despacho',
                'table' => 'eventos_despacho',
            ],
            default => [
                'title' => 'Eventos EMS',
                'singular' => 'Registro EMS',
                'table' => 'eventos_ems',
            ],
        };
    }

    private function routePermission(): string
    {
        return self::TIPO_ROUTE_PERMISSIONS[$this->tipo] ?? self::TIPO_ROUTE_PERMISSIONS['ems'];
    }

    private function featurePermission(string $action): string
    {
        return 'feature.' . $this->routePermission() . '.' . $action;
    }

    private function userCan(string $permission): bool
    {
        $user = auth()->user();

        return $user ? $user->can($permission) : false;
    }

    private function supportsClienteId(): bool
    {
        return $this->tipo === 'tiktoker';
    }

    private function scopedTableQuery()
    {
        $query = DB::table($this->tableName());
        $empresaId = $this->authenticatedEmpresaId();

        if ($this->tipo === 'contrato' && $empresaId > 0) {
            $query->whereExists(function ($subQuery) use ($empresaId) {
                $subQuery->selectRaw('1')
                    ->from('paquetes_contrato as pc')
                    ->whereColumn('pc.codigo', $this->tableName() . '.codigo')
                    ->where('pc.empresa_id', $empresaId);
            });
        }

        return $query;
    }

    private function authenticatedEmpresaId(): int
    {
        return (int) (auth()->user()?->empresa_id ?? 0);
    }

    private function authorizePermission(string $permission): void
    {
        if (! $this->userCan($permission)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }
}


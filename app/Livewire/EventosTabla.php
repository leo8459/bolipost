<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
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
        'despacho' => 'eventos-despacho.index',
    ];

    public $tipo = 'ems';
    public $search = '';
    public $searchQuery = '';
    public $editingId = null;
    public $codigo = '';
    public $evento_id = '';
    public $user_id = '';

    protected $paginationTheme = 'bootstrap';

    public function mount(string $tipo = 'ems'): void
    {
        $this->tipo = $this->normalizeTipo($tipo);
    }

    public function searchRegistros(): void
    {
        $this->searchQuery = trim((string) $this->search);
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
        $registro = DB::table($this->tableName())->where('id', $id)->first();

        if (!$registro) {
            return;
        }

        $this->editingId = (int) $registro->id;
        $this->codigo = (string) $registro->codigo;
        $this->evento_id = (string) $registro->evento_id;
        $this->user_id = (string) $registro->user_id;

        $this->dispatch('openEventosTablaModal');
    }

    public function save(): void
    {
        $this->authorizePermission($this->featurePermission($this->editingId ? 'edit' : 'create'));
        $this->validate($this->rules());

        $payload = [
            'codigo' => trim((string) $this->codigo),
            'evento_id' => (int) $this->evento_id,
            'user_id' => (int) $this->user_id,
        ];

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
        DB::table($this->tableName())->where('id', $id)->delete();
        session()->flash('success', $this->pageConfig()['singular'] . ' eliminado correctamente.');
    }

    public function resetForm(): void
    {
        $this->reset(['codigo', 'evento_id', 'user_id']);
        $this->resetValidation();
    }

    public function render()
    {
        $q = trim((string) $this->searchQuery);
        $table = $this->tableName();

        $registros = DB::table($table . ' as t')
            ->leftJoin('eventos as e', 'e.id', '=', 't.evento_id')
            ->leftJoin('users as u', 'u.id', '=', 't.user_id')
            ->select([
                't.id',
                't.codigo',
                't.evento_id',
                't.user_id',
                't.created_at',
                'e.nombre_evento as evento_nombre',
                'u.name as usuario_nombre',
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('t.codigo', 'ILIKE', '%' . $q . '%')
                        ->orWhere('e.nombre_evento', 'ILIKE', '%' . $q . '%')
                        ->orWhere('u.name', 'ILIKE', '%' . $q . '%');
                });
            })
            ->orderByDesc('t.id')
            ->paginate(100);

        return view('livewire.eventos-tabla', [
            'registros' => $registros,
            'eventos' => DB::table('eventos')->orderBy('nombre_evento')->get(['id', 'nombre_evento']),
            'users' => DB::table('users')->orderBy('name')->get(['id', 'name']),
            'config' => $this->pageConfig(),
            'canEventosCreate' => $this->userCan($this->featurePermission('create')),
            'canEventosEdit' => $this->userCan($this->featurePermission('edit')),
            'canEventosDelete' => $this->userCan($this->featurePermission('delete')),
        ]);
    }

    protected function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:255'],
            'evento_id' => ['required', 'integer', Rule::exists('eventos', 'id')],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ];
    }

    protected function tableName(): string
    {
        return $this->pageConfig()['table'];
    }

    protected function normalizeTipo(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        return in_array($tipo, ['ems', 'certi', 'ordi', 'contrato', 'despacho'], true) ? $tipo : 'ems';
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

    private function authorizePermission(string $permission): void
    {
        if (! $this->userCan($permission)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }
}


<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class Users extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $groupByBillingSucursal = false;

    public $editingId = null;
    public $name = '';
    public $alias = '';
    public $email = '';
    public $password = '';
    public $ciudad = '';
    public $regionalesSeleccionadas = [];
    public $ci = '';
    public $empresa_id = '';
    public $sucursal_id = '';
    public $roleIds = [];

    public $newPassword = '';
    public $passwordUserId = null;

    public $statusUserId = null;
    public $statusAction = '';

    protected $paginationTheme = 'bootstrap';

    public function searchUsers(): void
    {
        $this->searchQuery = trim((string) $this->search);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorizePermission('users.create');
        $this->resetUserForm();
        $this->dispatch('openUserModal');
    }

    public function openEditModal(int $userId): void
    {
        $this->authorizePermission('users.edit');

        $user = User::query()->findOrFail($userId);

        $this->editingId = $user->id;
        $this->name = (string) $user->name;
        $this->alias = (string) $user->alias;
        $this->email = (string) $user->email;
        $this->password = '';
        $this->regionalesSeleccionadas = $user->regionalesLista();
        $this->ciudad = (string) ($this->regionalesSeleccionadas[0] ?? $user->ciudad);
        $this->ci = (string) $user->ci;
        $this->empresa_id = $user->empresa_id ? (string) $user->empresa_id : '';
        $this->sucursal_id = $user->sucursal_id ? (string) $user->sucursal_id : '';
        $this->roleIds = $user->roles()->pluck('roles.id')->map(fn ($id) => (string) $id)->all();

        $this->resetValidation();
        $this->dispatch('openUserModal');
    }

    public function saveUser(): void
    {
        if ($this->editingId) {
            $this->authorizePermission('users.update');
            $this->validate($this->updateRules());

            $user = User::query()->findOrFail((int) $this->editingId);
            $ciValue = trim((string) $this->ci);
            $regionales = $this->normalizeRegionalesSeleccionadas();
            $user->name = trim($this->name);
            $user->alias = strtolower(trim((string) $this->alias));
            $user->email = trim($this->email);
            $user->ciudad = $regionales[0] ?? '';
            $user->regionales = $regionales;
            // If CI is left empty on edit, keep existing value.
            $user->ci = $ciValue !== '' ? $ciValue : $user->ci;
            $user->empresa_id = $this->empresa_id !== '' ? (int) $this->empresa_id : null;
            $user->sucursal_id = $this->sucursal_id !== '' ? (int) $this->sucursal_id : null;

            if (trim($this->password) !== '') {
                $user->password = Hash::make($this->password);
            }

            $user->save();
            $user->syncRoles($this->resolveRoleNames());

            session()->flash('success', 'Usuario actualizado correctamente.');
        } else {
            $this->authorizePermission('users.store');
            $this->validate($this->createRules());

            $user = new User();
            $ciValue = trim((string) $this->ci);
            $regionales = $this->normalizeRegionalesSeleccionadas();
            $user->name = trim($this->name);
            $user->alias = strtolower(trim((string) $this->alias));
            $user->email = trim($this->email);
            $user->password = Hash::make($this->password);
            $user->ciudad = $regionales[0] ?? '';
            $user->regionales = $regionales;
            $user->ci = $ciValue !== '' ? $ciValue : null;
            $user->empresa_id = $this->empresa_id !== '' ? (int) $this->empresa_id : null;
            $user->sucursal_id = $this->sucursal_id !== '' ? (int) $this->sucursal_id : null;
            $user->save();
            $user->syncRoles($this->resolveRoleNames());

            session()->flash('success', 'Usuario creado correctamente.');
        }

        $this->dispatch('closeUserModal');
        $this->resetUserForm();
    }

    public function openPasswordModal(int $userId): void
    {
        $this->authorizePermission('users.update');
        $this->passwordUserId = $userId;
        $this->newPassword = '';
        $this->resetValidation();
        $this->dispatch('openPasswordModal');
    }

    public function updatePassword(): void
    {
        $this->authorizePermission('users.update');

        $this->validate([
            'newPassword' => 'required|string|min:8',
        ]);

        $user = User::query()->findOrFail((int) $this->passwordUserId);
        $user->password = Hash::make($this->newPassword);
        $user->save();

        session()->flash('success', 'Contrasena actualizada correctamente.');

        $this->newPassword = '';
        $this->passwordUserId = null;
        $this->dispatch('closePasswordModal');
    }

    public function confirmStatusAction(int $userId, string $action): void
    {
        if (! in_array($action, ['delete', 'restore'], true)) {
            return;
        }

        $permission = $action === 'delete' ? 'users.destroy' : 'users.restore';
        $this->authorizePermission($permission);

        $this->statusUserId = $userId;
        $this->statusAction = $action;
        $this->dispatch('openStatusModal');
    }

    public function applyStatusAction(): void
    {
        if (! in_array($this->statusAction, ['delete', 'restore'], true) || ! $this->statusUserId) {
            return;
        }

        $permission = $this->statusAction === 'delete' ? 'users.destroy' : 'users.restore';
        $this->authorizePermission($permission);

        $user = User::withTrashed()->findOrFail((int) $this->statusUserId);

        if ($this->statusAction === 'delete') {
            if ((int) auth()->id() === (int) $user->id) {
                session()->flash('warning', 'No puedes darte de baja a ti mismo.');
            } elseif (! $user->trashed()) {
                $user->delete();
                session()->flash('success', 'Usuario dado de baja correctamente.');
            }
        }

        if ($this->statusAction === 'restore' && $user->trashed()) {
            $user->restore();
            session()->flash('success', 'Usuario reactivado correctamente.');
        }

        $this->statusUserId = null;
        $this->statusAction = '';
        $this->dispatch('closeStatusModal');
    }

    public function closeAllModals(): void
    {
        $this->resetValidation();
        $this->newPassword = '';
    }

    public function toggleGroupByBillingSucursal(): void
    {
        $this->groupByBillingSucursal = ! $this->groupByBillingSucursal;
        $this->resetPage();
    }

    protected function createRules(): array
    {
        $regionales = $this->regionalesDisponibles();

        return [
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:255', Rule::unique('users', 'alias')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'regionalesSeleccionadas' => ['required', 'array', 'min:1'],
            'regionalesSeleccionadas.*' => ['required', 'string', Rule::in($regionales)],
            'ci' => ['nullable', 'string', 'max:255'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresa,id'],
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'roleIds' => ['nullable', 'array'],
            'roleIds.*' => ['integer', 'exists:roles,id'],
        ];
    }

    protected function updateRules(): array
    {
        $regionales = $this->regionalesDisponibles();

        return [
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:255', Rule::unique('users', 'alias')->ignore((int) $this->editingId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore((int) $this->editingId)],
            'password' => ['nullable', 'string', 'min:8'],
            'regionalesSeleccionadas' => ['required', 'array', 'min:1'],
            'regionalesSeleccionadas.*' => ['required', 'string', Rule::in($regionales)],
            'ci' => ['nullable', 'string', 'max:255'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresa,id'],
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'roleIds' => ['nullable', 'array'],
            'roleIds.*' => ['integer', 'exists:roles,id'],
        ];
    }

    protected function resolveRoleNames(): array
    {
        $roleIds = collect($this->roleIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($roleIds === []) {
            return [];
        }

        return Role::query()->whereIn('id', $roleIds)->pluck('name')->toArray();
    }

    protected function resetUserForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->alias = '';
        $this->email = '';
        $this->password = '';
        $this->ciudad = '';
        $this->regionalesSeleccionadas = [];
        $this->ci = '';
        $this->empresa_id = '';
        $this->sucursal_id = '';
        $this->roleIds = [];
        $this->resetValidation();
    }

    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();

        if (! $user || ! $user->can($permission)) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }

    protected function normalizeRegionalesSeleccionadas(): array
    {
        $validas = $this->regionalesDisponibles();

        $regionales = collect($this->regionalesSeleccionadas)
            ->map(fn ($regional) => strtoupper(trim((string) $regional)))
            ->filter(fn ($regional) => in_array($regional, $validas, true))
            ->unique()
            ->values()
            ->all();

        $this->ciudad = (string) ($regionales[0] ?? '');

        return $regionales;
    }

    protected function regionalesDisponibles(): array
    {
        return ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
    }

    public function render()
    {
        $q = trim((string) $this->searchQuery);

        $baseQuery = User::withTrashed()
            ->with(['empresa', 'sucursal', 'roles'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('alias', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('ci', 'like', '%'.$q.'%')
                        ->orWhereHas('empresa', function ($empresaQuery) use ($q) {
                            $empresaQuery->where('nombre', 'like', '%'.$q.'%')
                                ->orWhere('sigla', 'like', '%'.$q.'%')
                                ->orWhere('codigo_cliente', 'like', '%'.$q.'%');
                        })
                        ->orWhereHas('sucursal', function ($sucursalQuery) use ($q) {
                            $sucursalQuery->where('municipio', 'like', '%'.$q.'%')
                                ->orWhere('departamento', 'like', '%'.$q.'%')
                                ->orWhere('telefono', 'like', '%'.$q.'%');
                        });
                });
            });

        $groupedUsers = collect();

        if ($this->groupByBillingSucursal) {
            $groupedUsers = (clone $baseQuery)
                ->whereNotNull('sucursal_id')
                ->orderByRaw('CASE WHEN sucursal_id IS NULL THEN 1 ELSE 0 END')
                ->orderBy('sucursal_id')
                ->orderBy('name')
                ->get()
                ->groupBy(fn (User $user) => (string) $user->sucursal_id)
                ->map(function ($items, $groupKey) {
                    $firstUser = $items->first();
                    $sucursal = $firstUser?->sucursal;

                    return [
                        'key' => (string) $groupKey,
                        'label' => 'Suc. ' . $sucursal->codigoSucursal . ' / PV ' . $sucursal->puntoVenta . ' - ' . $sucursal->municipio,
                        'meta' => trim(collect([$sucursal->departamento, $sucursal->telefono ? 'Tel. ' . $sucursal->telefono : null])->filter()->implode(' | ')),
                        'users' => $items->values(),
                    ];
                })
                ->values();
        }

        $users = $this->groupByBillingSucursal
            ? $baseQuery->orderByDesc('id')->paginate(10, ['*'], 'page', 1)
            : $baseQuery->orderByDesc('id')->paginate(10);

        return view('livewire.users', [
            'users' => $users,
            'groupedUsers' => $groupedUsers,
            'roles' => Role::query()->orderBy('name')->get(),
            'empresas' => Empresa::query()->orderBy('codigo_cliente')->get(),
            'sucursales' => Sucursal::query()->orderBy('codigoSucursal')->orderBy('puntoVenta')->get(),
            'regionales' => $this->regionalesDisponibles(),
        ]);
    }
}

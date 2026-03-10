<?php

namespace App\Livewire;

use App\Models\Empresa;
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

    public $editingId = null;
    public $name = '';
    public $alias = '';
    public $email = '';
    public $password = '';
    public $ciudad = '';
    public $ci = '';
    public $empresa_id = '';
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
        $this->ciudad = (string) $user->ciudad;
        $this->ci = (string) $user->ci;
        $this->empresa_id = $user->empresa_id ? (string) $user->empresa_id : '';
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
            $user->name = trim($this->name);
            $user->alias = strtolower(trim((string) $this->alias));
            $user->email = trim($this->email);
            $user->ciudad = trim($this->ciudad);
            // If CI is left empty on edit, keep existing value.
            $user->ci = $ciValue !== '' ? $ciValue : $user->ci;
            $user->empresa_id = $this->empresa_id !== '' ? (int) $this->empresa_id : null;

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
            $user->name = trim($this->name);
            $user->alias = strtolower(trim((string) $this->alias));
            $user->email = trim($this->email);
            $user->password = Hash::make($this->password);
            $user->ciudad = trim($this->ciudad);
            $user->ci = $ciValue !== '' ? $ciValue : null;
            $user->empresa_id = $this->empresa_id !== '' ? (int) $this->empresa_id : null;
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

    protected function createRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'alias')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8'],
            'ciudad' => ['required', 'string', 'max:255'],
            'ci' => ['nullable', 'string', 'max:255'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresa,id'],
            'roleIds' => ['nullable', 'array'],
            'roleIds.*' => ['integer', 'exists:roles,id'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'alias' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('users', 'alias')->ignore((int) $this->editingId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore((int) $this->editingId)],
            'password' => ['nullable', 'string', 'min:8'],
            'ciudad' => ['required', 'string', 'max:255'],
            'ci' => ['nullable', 'string', 'max:255'],
            'empresa_id' => ['nullable', 'integer', 'exists:empresa,id'],
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
        $this->ci = '';
        $this->empresa_id = '';
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

    public function render()
    {
        $q = trim((string) $this->searchQuery);

        $users = User::withTrashed()
            ->with(['empresa', 'roles'])
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
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.users', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'empresas' => Empresa::query()->orderBy('codigo_cliente')->get(),
            'regionales' => ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'BENI', 'PANDO'],
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class Users extends Component
{
    use WithPagination;

    public $search = '';
    public $searchQuery = '';
    public $groupByBillingSucursal = false;
    public $showOnlyWithEmpresa = false;
    public $filterEmpresaId = '';
    public $empresaMode = false;

    public $editingId = null;
    public $name = '';
    public $alias = '';
    public $email = '';
    public $password = '';
    public $ciudad = '';
    public $provincia_origen = '';
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
    private const ROLE_GUARD_WEB = 'web';
    private const EMPRESA_ROLE_NAME = 'empresa';
    private const REQUIRED_WEB_ROLE_NAMES = ['conductor', self::EMPRESA_ROLE_NAME];

    public function mount(bool $empresaMode = false): void
    {
        $this->empresaMode = $empresaMode;
        $this->showOnlyWithEmpresa = $empresaMode;
    }

    public function searchUsers(): void
    {
        $this->searchQuery = trim((string) $this->search);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorizePermission('users.create');
        $this->resetUserForm();
        $this->applyEmpresaRoleMode();
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
        $this->provincia_origen = (string) ($user->provincia_origen ?? '');
        $this->ci = (string) $user->ci;
        $this->empresa_id = $user->empresa_id ? (string) $user->empresa_id : '';
        $this->sucursal_id = $user->sucursal_id ? (string) $user->sucursal_id : '';
        $this->roleIds = $user->roles()->pluck('roles.id')->map(fn ($id) => (string) $id)->all();
        $this->applyEmpresaRoleMode();

        $this->resetValidation();
        $this->dispatch('openUserModal');
    }

    public function saveUser(): void
    {
        if ($this->editingId) {
            $this->authorizePermission('users.update');
            $this->alias = $this->normalizeAlias($this->alias);
            $this->validate($this->updateRules());
            $this->ensureAliasIsAvailable($this->alias, (int) $this->editingId);

            $user = User::query()->findOrFail((int) $this->editingId);
            $ciValue = trim((string) $this->ci);
            $regionales = $this->normalizeRegionalesSeleccionadas();
            $user->name = trim($this->name);
            $user->alias = $this->alias;
            $user->email = trim($this->email);
            $user->ciudad = $regionales[0] ?? '';
            $user->provincia_origen = $this->normalizeProvinciaOrigen();
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
            $this->alias = $this->normalizeAlias($this->alias);
            $this->validate($this->createRules());
            $this->ensureAliasIsAvailable($this->alias);

            $user = new User();
            $ciValue = trim((string) $this->ci);
            $regionales = $this->normalizeRegionalesSeleccionadas();
            $user->name = trim($this->name);
            $user->alias = $this->alias;
            $user->email = trim($this->email);
            $user->password = Hash::make($this->password);
            $user->ciudad = $regionales[0] ?? '';
            $user->provincia_origen = $this->normalizeProvinciaOrigen();
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

    public function showAllUsers(): void
    {
        if ($this->empresaMode) {
            $this->showOnlyWithEmpresa = true;
            $this->resetPage();

            return;
        }

        $this->showOnlyWithEmpresa = false;
        $this->filterEmpresaId = '';
        $this->resetPage();
    }

    public function showEmpresaUsers(): void
    {
        $this->showOnlyWithEmpresa = true;
        $this->resetPage();
    }

    public function updatedFilterEmpresaId(): void
    {
        $this->showOnlyWithEmpresa = $this->filterEmpresaId !== '' || $this->showOnlyWithEmpresa;
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
            'provincia_origen' => [$this->empresaMode ? 'required' : 'nullable', 'string', 'max:255'],
            'ci' => ['nullable', 'string', 'max:255'],
            'empresa_id' => [$this->empresaMode ? 'required' : 'nullable', 'integer', 'exists:empresa,id'],
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'roleIds' => ['nullable', 'array'],
            'roleIds.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('guard_name', self::ROLE_GUARD_WEB)),
            ],
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
            'provincia_origen' => [$this->empresaMode ? 'required' : 'nullable', 'string', 'max:255'],
            'ci' => ['nullable', 'string', 'max:255'],
            'empresa_id' => [$this->empresaMode ? 'required' : 'nullable', 'integer', 'exists:empresa,id'],
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'roleIds' => ['nullable', 'array'],
            'roleIds.*' => [
                'integer',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('guard_name', self::ROLE_GUARD_WEB)),
            ],
        ];
    }

    protected function resolveRoleNames(): array
    {
        if ($this->empresaMode) {
            return [self::EMPRESA_ROLE_NAME];
        }

        $roleIds = collect($this->roleIds)
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($roleIds === []) {
            return [];
        }

        return Role::query()
            ->whereIn('id', $roleIds)
            ->where('guard_name', self::ROLE_GUARD_WEB)
            ->pluck('name')
            ->toArray();
    }

    protected function resetUserForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->alias = '';
        $this->email = '';
        $this->password = '';
        $this->ciudad = '';
        $this->provincia_origen = '';
        $this->regionalesSeleccionadas = [];
        $this->ci = '';
        $this->empresa_id = '';
        $this->sucursal_id = '';
        $this->roleIds = [];
        $this->resetValidation();
    }

    protected function applyEmpresaRoleMode(): void
    {
        if (! $this->empresaMode) {
            return;
        }

        $empresaRoleId = Role::query()
            ->where('guard_name', self::ROLE_GUARD_WEB)
            ->where('name', self::EMPRESA_ROLE_NAME)
            ->value('id');

        $this->roleIds = $empresaRoleId ? [(string) $empresaRoleId] : [];
        $this->sucursal_id = '';
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

    protected function normalizeProvinciaOrigen(): ?string
    {
        $provincia = strtoupper(trim((string) $this->provincia_origen));
        $this->provincia_origen = $provincia;

        return $provincia !== '' ? $provincia : null;
    }

    protected function regionalesDisponibles(): array
    {
        return ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'];
    }

    protected function normalizeAlias($alias): string
    {
        return strtolower(trim((string) $alias));
    }

    protected function ensureAliasIsAvailable(string $alias, ?int $ignoreUserId = null): void
    {
        if ($this->isAliasAvailable($alias, $ignoreUserId)) {
            return;
        }

        throw ValidationException::withMessages([
            'alias' => 'El alias ya esta registrado. Debe ser unico.',
        ]);
    }

    protected function isAliasAvailable(string $alias, ?int $ignoreUserId = null): bool
    {
        if ($alias === '') {
            return true;
        }

        return ! User::withTrashed()
            ->when($ignoreUserId, fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->whereRaw('LOWER(alias) = ?', [$alias])
            ->exists();
    }

    public function render()
    {
        $this->ensureRequiredWebRoles();

        $q = trim((string) $this->searchQuery);

        $baseQuery = User::withTrashed()
            ->with(['empresa', 'sucursal', 'roles'])
            ->when($this->showOnlyWithEmpresa, fn ($query) => $query->whereNotNull('empresa_id'))
            ->when($this->filterEmpresaId !== '', fn ($query) => $query->where('empresa_id', (int) $this->filterEmpresaId))
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('alias', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('provincia_origen', 'like', '%'.$q.'%')
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
            'roles' => Role::query()
                ->where('guard_name', self::ROLE_GUARD_WEB)
                ->when($this->empresaMode, fn ($query) => $query->where('name', self::EMPRESA_ROLE_NAME))
                ->orderBy('name')
                ->get(),
            'empresas' => Empresa::query()->orderBy('codigo_cliente')->get(),
            'sucursales' => Sucursal::query()->orderBy('codigoSucursal')->orderBy('puntoVenta')->get(),
            'regionales' => $this->regionalesDisponibles(),
        ]);
    }

    protected function ensureRequiredWebRoles(): void
    {
        foreach (self::REQUIRED_WEB_ROLE_NAMES as $roleName) {
            Role::query()->firstOrCreate(
                [
                    'name' => $roleName,
                    'guard_name' => self::ROLE_GUARD_WEB,
                ],
                []
            );
        }
    }
}

<?php

namespace App\Livewire;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
    public $filterCodigoCliente = '';
    public $filterEmpresaId = '';
    public $appliedFilterCodigoCliente = '';
    public $appliedFilterEmpresaId = '';
    public $empresaMode = false;

    public $editingId = null;
    public $name = '';
    public $alias = '';
    public $email = '';
    public $password = '';
    public $ciudad = '';
    public $regionalesSeleccionadas = [];
    public $provincia_origen = '';
    public $ci = '';
    public $empresa_id = '';
    public $sucursal_id = '';
    public $roleIds = [];

    public $newPassword = '';
    public $passwordUserId = null;

    public $statusUserId = null;
    public $statusAction = '';

    public $bulkCodigoCliente = '';
    public $bulkEmpresaIds = [];
    public $bulkStatusAction = 'delete';

    protected $paginationTheme = 'bootstrap';

    private const EMPRESA_ROLE_NAME = 'empresa';

    public function mount(bool $empresaMode = false): void
    {
        $this->empresaMode = $empresaMode;

        if ($this->empresaMode) {
            $this->showOnlyWithEmpresa = true;
            $this->groupByBillingSucursal = false;
        }
    }

    public function searchUsers(): void
    {
        $this->searchQuery = trim((string) $this->search);
        $this->resetPage();
    }

    public function openCreateModal(): void
    {
        $this->authorizePermission($this->permissionFor('create'));
        $this->resetUserForm();
        $this->applyEmpresaRoleMode();
        $this->dispatch('openUserModal');
    }

    public function openEditModal(int $userId): void
    {
        $this->authorizePermission($this->permissionFor('edit'));

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
            $this->authorizePermission($this->permissionFor('update'));
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
            $user->regionales = $regionales;
            $user->provincia_origen = $this->normalizeProvinciaOrigen();
            // If CI is left empty on edit, keep existing value.
            $user->ci = $ciValue !== '' ? $ciValue : $user->ci;
            $user->empresa_id = $this->empresa_id !== '' ? (int) $this->empresa_id : null;
            $user->sucursal_id = $this->empresaMode ? null : ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null);

            if (trim($this->password) !== '') {
                $user->password = Hash::make($this->password);
            }

            $user->save();
            $user->syncRoles($this->resolveRoleNames());

            session()->flash('success', 'Usuario actualizado correctamente.');
        } else {
            $this->authorizePermission($this->permissionFor('store'));
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
            $user->regionales = $regionales;
            $user->provincia_origen = $this->normalizeProvinciaOrigen();
            $user->ci = $ciValue !== '' ? $ciValue : null;
            $user->empresa_id = $this->empresa_id !== '' ? (int) $this->empresa_id : null;
            $user->sucursal_id = $this->empresaMode ? null : ($this->sucursal_id !== '' ? (int) $this->sucursal_id : null);
            $user->save();
            $user->syncRoles($this->resolveRoleNames());

            session()->flash('success', 'Usuario creado correctamente.');
        }

        $this->dispatch('closeUserModal');
        $this->resetUserForm();
    }

    public function openPasswordModal(int $userId): void
    {
        $this->authorizePermission($this->permissionFor('update'));
        $this->passwordUserId = $userId;
        $this->newPassword = '';
        $this->resetValidation();
        $this->dispatch('openPasswordModal');
    }

    public function updatePassword(): void
    {
        $this->authorizePermission($this->permissionFor('update'));

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

        $permission = $action === 'delete' ? $this->permissionFor('destroy') : $this->permissionFor('restore');
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

        $permission = $this->statusAction === 'delete' ? $this->permissionFor('destroy') : $this->permissionFor('restore');
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

    public function openBulkStatusModal(string $action = 'delete'): void
    {
        abort_unless($this->empresaMode, 404);
        abort_unless(in_array($action, ['delete', 'restore'], true), 404);
        $permission = $action === 'delete' ? $this->permissionFor('destroy') : $this->permissionFor('restore');
        $this->authorizePermission($permission);

        $this->bulkStatusAction = $action;
        $this->bulkCodigoCliente = '';
        $this->bulkEmpresaIds = [];

        if ($this->appliedFilterCodigoCliente !== '') {
            $this->bulkCodigoCliente = $this->normalizeCodigoCliente($this->appliedFilterCodigoCliente);
        }

        if ($this->appliedFilterEmpresaId !== '') {
            $codigo = Empresa::query()
                ->whereKey((int) $this->appliedFilterEmpresaId)
                ->value('codigo_cliente');

            $this->bulkCodigoCliente = $this->normalizeCodigoCliente($codigo);
            $this->bulkEmpresaIds = [(string) $this->appliedFilterEmpresaId];
        }

        $this->resetValidation(['bulkCodigoCliente', 'bulkEmpresaIds']);
        $this->dispatch('openBulkStatusModal');
    }

    public function updatedBulkCodigoCliente($value): void
    {
        $this->bulkCodigoCliente = $this->normalizeCodigoCliente($value);
        $this->bulkEmpresaIds = [];
        $this->resetValidation(['bulkCodigoCliente', 'bulkEmpresaIds']);
    }

    public function selectAllBulkCompanies(): void
    {
        $this->bulkEmpresaIds = $this->empresaIdsForCodigoCliente(
            $this->normalizeCodigoCliente($this->bulkCodigoCliente)
        )->map(fn ($id) => (string) $id)->all();
        $this->resetValidation('bulkEmpresaIds');
    }

    public function clearBulkCompanies(): void
    {
        $this->bulkEmpresaIds = [];
    }

    public function applyBulkStatusAction(): void
    {
        abort_unless($this->empresaMode, 404);
        abort_unless(in_array($this->bulkStatusAction, ['delete', 'restore'], true), 404);
        $permission = $this->bulkStatusAction === 'delete'
            ? $this->permissionFor('destroy')
            : $this->permissionFor('restore');
        $this->authorizePermission($permission);

        $codigo = $this->normalizeCodigoCliente($this->bulkCodigoCliente);
        if ($codigo === '') {
            $this->addError('bulkCodigoCliente', 'Selecciona un codigo de cliente.');

            return;
        }

        $selectedEmpresaIds = collect($this->bulkEmpresaIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($selectedEmpresaIds->isEmpty()) {
            $this->addError('bulkEmpresaIds', 'Selecciona al menos una empresa dependiente.');

            return;
        }

        $empresaIds = Empresa::query()
            ->whereIn('id', $selectedEmpresaIds)
            ->whereRaw("UPPER(TRIM(COALESCE(codigo_cliente, ''))) = ?", [$codigo])
            ->pluck('id');

        if ($empresaIds->isEmpty()) {
            $this->addError('bulkEmpresaIds', 'Las empresas seleccionadas ya no pertenecen a este codigo de cliente.');

            return;
        }

        $action = $this->bulkStatusAction;
        $currentUserId = (int) auth()->id();
        $affectedUsers = 0;
        $skippedCurrentUser = false;

        DB::transaction(function () use ($action, $empresaIds, $currentUserId, &$affectedUsers, &$skippedCurrentUser): void {
            $users = $action === 'delete'
                ? User::query()->whereIn('empresa_id', $empresaIds)->lockForUpdate()->get()
                : User::onlyTrashed()->whereIn('empresa_id', $empresaIds)->lockForUpdate()->get();

            foreach ($users as $user) {
                if ($action === 'delete' && (int) $user->id === $currentUserId) {
                    $skippedCurrentUser = true;

                    continue;
                }

                if ($action === 'delete') {
                    $user->delete();
                } else {
                    $user->restore();
                }
                $affectedUsers++;
            }
        });

        if ($affectedUsers > 0) {
            $statusText = $action === 'delete' ? 'de baja' : 'de alta';
            $message = "Se dieron {$statusText} {$affectedUsers} usuario(s) de {$empresaIds->count()} empresa(s) con el codigo {$codigo}.";
            if ($skippedCurrentUser) {
                $message .= ' Tu usuario no fue dado de baja.';
            }
            session()->flash('success', $message);
        } elseif ($skippedCurrentUser) {
            session()->flash('warning', 'No puedes darte de baja a ti mismo y no habia otros usuarios activos para este codigo.');
        } else {
            $statusText = $action === 'delete' ? 'activos' : 'inactivos';
            session()->flash('warning', "No hay usuarios {$statusText} para el codigo {$codigo}.");
        }

        $this->bulkCodigoCliente = '';
        $this->bulkEmpresaIds = [];
        $this->resetPage();
        $this->dispatch('closeBulkStatusModal');
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
            return;
        }

        $this->showOnlyWithEmpresa = false;
        $this->filterCodigoCliente = '';
        $this->filterEmpresaId = '';
        $this->appliedFilterCodigoCliente = '';
        $this->appliedFilterEmpresaId = '';
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
    }

    public function updatedFilterCodigoCliente($value): void
    {
        $this->filterCodigoCliente = $this->normalizeCodigoCliente($value);
        $this->filterEmpresaId = '';
        $this->showOnlyWithEmpresa = true;
    }

    public function applyFilters(): void
    {
        $codigo = $this->normalizeCodigoCliente($this->filterCodigoCliente);
        $empresaId = $this->filterEmpresaId !== '' ? (int) $this->filterEmpresaId : null;

        if ($empresaId) {
            $empresaBelongsToCodigo = Empresa::query()
                ->whereKey($empresaId)
                ->whereRaw("UPPER(TRIM(COALESCE(codigo_cliente, ''))) = ?", [$codigo])
                ->exists();

            if (! $empresaBelongsToCodigo) {
                $this->addError('filterEmpresaId', 'La empresa seleccionada no pertenece al codigo de cliente.');

                return;
            }
        }

        $this->appliedFilterCodigoCliente = $codigo;
        $this->appliedFilterEmpresaId = $empresaId ? (string) $empresaId : '';
        $this->resetValidation(['filterCodigoCliente', 'filterEmpresaId']);
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
            'provincia_origen' => ['nullable', 'string', 'max:255'],
            'empresa_id' => [$this->empresaMode ? 'required' : 'nullable', 'integer', 'exists:empresa,id'],
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
            'provincia_origen' => ['nullable', 'string', 'max:255'],
            'empresa_id' => [$this->empresaMode ? 'required' : 'nullable', 'integer', 'exists:empresa,id'],
            'sucursal_id' => ['nullable', 'integer', 'exists:sucursales,id'],
            'roleIds' => ['nullable', 'array'],
            'roleIds.*' => ['integer', 'exists:roles,id'],
        ];
    }

    protected function resolveRoleNames(): array
    {
        if ($this->empresaMode) {
            $this->ensureEmpresaRoleExists();

            return [self::EMPRESA_ROLE_NAME];
        }

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
        $this->provincia_origen = '';
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

        $empresaRoleId = Role::query()->where('name', self::EMPRESA_ROLE_NAME)->value('id');
        if (! $empresaRoleId) {
            $empresaRoleId = $this->ensureEmpresaRoleExists()->id;
        }

        $this->roleIds = $empresaRoleId ? [(string) $empresaRoleId] : [];
        $this->sucursal_id = '';
        $this->showOnlyWithEmpresa = true;
        $this->groupByBillingSucursal = false;
    }

    protected function normalizeProvinciaOrigen(): ?string
    {
        $provincia = trim((string) $this->provincia_origen);
        $provincia = function_exists('mb_strtoupper') ? mb_strtoupper($provincia, 'UTF-8') : strtoupper($provincia);
        $provincia = preg_replace('/\s+/', ' ', $provincia) ?: '';
        $this->provincia_origen = $provincia;

        return $provincia !== '' ? $provincia : null;
    }

    protected function permissionFor(string $action): string
    {
        if (! $this->empresaMode) {
            return match ($action) {
                'create' => 'users.create',
                'store' => 'users.store',
                'edit' => 'users.edit',
                'update' => 'users.update',
                'destroy' => 'users.destroy',
                'restore' => 'users.restore',
                default => 'users.index',
            };
        }

        return match ($action) {
            'create', 'store' => 'feature.users.empresas.create',
            'edit', 'update' => 'feature.users.empresas.edit',
            'destroy' => 'feature.users.empresas.delete',
            'restore' => 'feature.users.empresas.restore',
            default => 'users.empresas',
        };
    }

    protected function ensureEmpresaRoleExists(): Role
    {
        return Role::query()->firstOrCreate([
            'name' => self::EMPRESA_ROLE_NAME,
            'guard_name' => 'web',
        ]);
    }

    protected function authorizePermission(string $permission): void
    {
        $user = auth()->user();

        $allowed = $user && $user->can($permission);

        if (
            ! $allowed
            && str_starts_with($permission, 'feature.users.empresas.')
            && $user?->can('feature.users.empresas.manage')
        ) {
            $allowed = true;
        }

        if (! $allowed && str_starts_with($permission, 'feature.users.empresas.')) {
            $allowed = collect($this->empresaPermissionFallbacks($permission))
                ->contains(fn (string $fallback) => $user?->can($fallback));
        }

        if (! $allowed) {
            abort(403, 'No tienes permiso para realizar esta accion.');
        }
    }

    protected function empresaPermissionFallbacks(string $permission): array
    {
        return match ($permission) {
            'feature.users.empresas.create' => ['users.create', 'users.store'],
            'feature.users.empresas.edit' => ['users.edit', 'users.update'],
            'feature.users.empresas.delete' => ['users.destroy'],
            'feature.users.empresas.restore' => ['users.restore'],
            'feature.users.empresas.export' => ['users.excel', 'users.pdf'],
            default => [],
        };
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

    protected function normalizeAlias($alias): string
    {
        return strtolower(trim((string) $alias));
    }

    protected function normalizeCodigoCliente($codigo): string
    {
        $codigo = trim((string) $codigo);

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($codigo, 'UTF-8')
            : strtoupper($codigo);
    }

    protected function empresaIdsForCodigoCliente(string $codigo)
    {
        return Empresa::query()
            ->whereRaw("UPPER(TRIM(COALESCE(codigo_cliente, ''))) = ?", [$codigo])
            ->pluck('id');
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
        $q = trim((string) $this->searchQuery);

        $baseQuery = User::withTrashed()
            ->with(['empresa', 'sucursal', 'roles'])
            ->when($this->showOnlyWithEmpresa, fn ($query) => $query->whereNotNull('empresa_id'))
            ->when($this->appliedFilterCodigoCliente !== '', function ($query) {
                $query->whereHas('empresa', function ($empresaQuery) {
                    $empresaQuery->whereRaw(
                        "UPPER(TRIM(COALESCE(codigo_cliente, ''))) = ?",
                        [$this->normalizeCodigoCliente($this->appliedFilterCodigoCliente)]
                    );
                });
            })
            ->when($this->appliedFilterEmpresaId !== '', fn ($query) => $query->where('empresa_id', (int) $this->appliedFilterEmpresaId))
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

        $empresas = Empresa::query()->orderBy('codigo_cliente')->orderBy('nombre')->get();
        $codigoClienteOptions = $empresas
            ->filter(fn (Empresa $empresa) => $this->normalizeCodigoCliente($empresa->codigo_cliente) !== '')
            ->groupBy(fn (Empresa $empresa) => $this->normalizeCodigoCliente($empresa->codigo_cliente))
            ->map(fn ($items, $codigo) => [
                'codigo' => (string) $codigo,
                'empresas_count' => $items->count(),
            ])
            ->sortBy('codigo', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $bulkEmpresas = collect();
        if ($this->empresaMode && $this->bulkCodigoCliente !== '') {
            $bulkEmpresas = $empresas
                ->filter(fn (Empresa $empresa) => $this->normalizeCodigoCliente($empresa->codigo_cliente) === $this->bulkCodigoCliente)
                ->values();

            $bulkEmpresaIds = $bulkEmpresas->pluck('id');
            $bulkUsersByEmpresa = $bulkEmpresaIds->isEmpty()
                ? collect()
                : User::withTrashed()
                    ->whereIn('empresa_id', $bulkEmpresaIds)
                    ->get(['empresa_id', 'deleted_at'])
                    ->groupBy('empresa_id');

            $bulkEmpresas = $bulkEmpresas->map(function (Empresa $empresa) use ($bulkUsersByEmpresa): array {
                $users = $bulkUsersByEmpresa->get($empresa->id, collect());

                return [
                    'id' => $empresa->id,
                    'nombre' => $empresa->nombre,
                    'sigla' => $empresa->sigla,
                    'usuarios_activos' => $users->whereNull('deleted_at')->count(),
                    'usuarios_inactivos' => $users->whereNotNull('deleted_at')->count(),
                    'usuarios_total' => $users->count(),
                ];
            });
        }

        $filterEmpresas = $this->filterCodigoCliente === ''
            ? collect()
            : $empresas
                ->filter(fn (Empresa $empresa) => $this->normalizeCodigoCliente($empresa->codigo_cliente) === $this->filterCodigoCliente)
                ->values();

        $selectedBulkEmpresaIds = collect($this->bulkEmpresaIds)->map(fn ($id) => (int) $id);

        return view('livewire.users', [
            'users' => $users,
            'groupedUsers' => $groupedUsers,
            'roles' => Role::query()
                ->when($this->empresaMode, fn ($query) => $query->where('name', self::EMPRESA_ROLE_NAME))
                ->orderBy('name')
                ->get(),
            'empresas' => $empresas,
            'codigoClienteOptions' => $codigoClienteOptions,
            'filterEmpresas' => $filterEmpresas,
            'bulkEmpresas' => $bulkEmpresas,
            'bulkTotalAffectedUsers' => $bulkEmpresas
                ->whereIn('id', $selectedBulkEmpresaIds)
                ->sum($this->bulkStatusAction === 'restore' ? 'usuarios_inactivos' : 'usuarios_activos'),
            'sucursales' => Sucursal::query()->orderBy('codigoSucursal')->orderBy('puntoVenta')->get(),
            'regionales' => $this->regionalesDisponibles(),
        ]);
    }
}

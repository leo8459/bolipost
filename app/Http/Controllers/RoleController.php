<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AclPermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()
            ->withCount('permissions')
            ->orderBy('guard_name')
            ->orderBy('name')
            ->paginate(20);

        $roleIds = $roles->getCollection()->pluck('id');

        $assignedUsersByRole = DB::table(config('permission.table_names.model_has_roles'))
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->whereIn('model_has_roles.role_id', $roleIds)
            ->where('model_has_roles.model_type', User::class)
            ->whereNull('users.deleted_at')
            ->orderBy('users.name')
            ->get([
                'model_has_roles.role_id',
                'users.id',
                'users.name',
                'users.email',
            ])
            ->groupBy('role_id');

        $roles->setCollection(
            $roles->getCollection()->map(function (Role $role) use ($assignedUsersByRole) {
                $assignedUsers = collect($assignedUsersByRole->get($role->id, []))
                    ->map(fn (object $user): array => [
                        'id' => (int) $user->id,
                        'name' => (string) $user->name,
                        'email' => (string) $user->email,
                    ])
                    ->values();

                $role->assigned_users = $assignedUsers;
                $role->assigned_users_count = $assignedUsers->count();

                return $role;
            })
        );

        return view('role.index', compact('roles'))
            ->with('i', (request()->input('page', 1) - 1) * $roles->perPage());
    }

    public function create()
    {
        $role = new Role();
        $guardName = 'web';
        $permissionGroups = [];
        $menuPermissionSummary = [];
        $selectedPermissions = [];

        if ($guardName === 'web') {
            AclPermissionRegistry::syncPermissions($guardName);
            $permissionGroups = AclPermissionRegistry::groupedPermissionsForMatrix($guardName);
            $menuPermissionSummary = AclPermissionRegistry::menuPermissionSummary($permissionGroups);
        }

        return view('role.create', compact('role', 'permissionGroups', 'menuPermissionSummary', 'selectedPermissions'));
    }

    public function store(Request $request)
    {
        $guardName = $this->normalizeRequestedGuard($request->input('guard_name'));
        if ($guardName === 'web') {
            AclPermissionRegistry::syncPermissions($guardName);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where(fn ($query) => $query->where('guard_name', $guardName)),
            ],
            'guard_name' => ['nullable', 'string', Rule::in(['web', 'movil', 'mobile'])],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);
        [$normalizedPermissions, $duplicatePermissionsRemoved] = $this->normalizeSubmittedPermissions($validated['permissions'] ?? []);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => $guardName,
        ]);

        $role->syncPermissions($guardName === 'web' ? $normalizedPermissions : []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado correctamente.')
            ->with('warning', $duplicatePermissionsRemoved ? 'Se detectaron permisos repetidos en el envio y se limpiaron automaticamente.' : null);
    }

    public function show($id)
    {
        $role = Role::findOrFail($id);

        return view('role.show', compact('role'));
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $guardName = $this->normalizeRequestedGuard($role->guard_name);
        $permissionGroups = [];
        $menuPermissionSummary = [];

        if ($guardName === 'web') {
            AclPermissionRegistry::syncPermissions($guardName);
            $permissionGroups = AclPermissionRegistry::groupedPermissionsForMatrix($guardName);
            $menuPermissionSummary = AclPermissionRegistry::menuPermissionSummary($permissionGroups);
        }

        $selectedPermissions = $role->permissions()->pluck('name')->all();

        return view('role.edit', compact('role', 'permissionGroups', 'menuPermissionSummary', 'selectedPermissions'));
    }

    public function update(Request $request, Role $role)
    {
        $guardName = $this->normalizeRequestedGuard($request->input('guard_name', $role->guard_name));
        if ($guardName === 'web') {
            AclPermissionRegistry::syncPermissions($guardName);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')
                    ->where(fn ($query) => $query->where('guard_name', $guardName))
                    ->ignore($role->id),
            ],
            'guard_name' => ['nullable', 'string', Rule::in(['web', 'movil', 'mobile'])],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);
        [$normalizedPermissions, $duplicatePermissionsRemoved] = $this->normalizeSubmittedPermissions($validated['permissions'] ?? []);

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $guardName,
        ]);

        $role->syncPermissions($guardName === 'web' ? $normalizedPermissions : []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado correctamente.')
            ->with('warning', $duplicatePermissionsRemoved ? 'Se detectaron permisos repetidos en el envio y se limpiaron automaticamente.' : null);
    }

    public function duplicate(Role $role)
    {
        $guardName = $this->normalizeRequestedGuard($role->guard_name);
        if ($guardName === 'web') {
            AclPermissionRegistry::syncPermissions('web');
        }

        $baseName = trim($role->name.' copia');
        $newName = $baseName;
        $suffix = 2;

        while (Role::where('name', $newName)->where('guard_name', $guardName)->exists()) {
            $newName = $baseName.' '.$suffix;
            $suffix++;
        }

        $newRole = Role::create([
            'name' => $newName,
            'guard_name' => $guardName,
        ]);

        $newRole->syncPermissions($guardName === 'web' ? $role->permissions()->pluck('name')->all() : []);

        return redirect()->route('roles.edit', $newRole)
            ->with('success', 'Rol duplicado correctamente.');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        $assignedUsersCount = DB::table(config('permission.table_names.model_has_roles'))
            ->join('users', 'users.id', '=', 'model_has_roles.model_id')
            ->where('model_has_roles.role_id', $role->id)
            ->where('model_has_roles.model_type', User::class)
            ->whereNull('users.deleted_at')
            ->count();

        if ($role->name === config('acl.super_admin_role')) {
            return redirect()->route('roles.index')
                ->with('warning', 'No se puede eliminar el rol super administrador.');
        }

        if ($assignedUsersCount > 0) {
            return redirect()->route('roles.index')
                ->with('warning', 'No se puede eliminar el rol porque tiene usuarios asignados.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Rol eliminado correctamente.');
    }

    /**
     * @param  array<int, mixed>  $permissions
     * @return array{0: array<int, string>, 1: bool}
     */
    private function normalizeSubmittedPermissions(array $permissions): array
    {
        $submittedCount = count($permissions);

        $normalizedPermissions = collect($permissions)
            ->filter(fn (mixed $permission): bool => is_string($permission) && trim($permission) !== '')
            ->map(fn (string $permission): string => trim($permission))
            ->unique()
            ->values()
            ->all();

        return [$normalizedPermissions, count($normalizedPermissions) !== $submittedCount];
    }

    private function normalizeRequestedGuard(mixed $guardInput): string
    {
        $guard = mb_strtolower(trim((string) ($guardInput ?? 'web')));

        if ($guard === 'movil' || $guard === 'mobile') {
            return 'movil';
        }

        return 'web';
    }
}

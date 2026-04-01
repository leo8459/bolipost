<?php

namespace App\Http\Controllers;

use App\Support\AclPermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('permissions')->orderBy('name')->paginate(20);

        return view('role.index', compact('roles'))
            ->with('i', (request()->input('page', 1) - 1) * $roles->perPage());
    }

    public function create()
    {
        $role = new Role();
        $guardName = $role->guard_name ?: 'web';
        AclPermissionRegistry::syncPermissions($guardName);
        $permissionGroups = AclPermissionRegistry::groupedPermissionsForMatrix($guardName);
        $menuPermissionSummary = AclPermissionRegistry::menuPermissionSummary($permissionGroups);
        $selectedPermissions = [];

        return view('role.create', compact('role', 'permissionGroups', 'menuPermissionSummary', 'selectedPermissions'));
    }

    public function store(Request $request)
    {
        AclPermissionRegistry::syncPermissions('web');

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol creado correctamente.');
    }

    public function show($id)
    {
        $role = Role::findOrFail($id);

        return view('role.show', compact('role'));
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);
        $guardName = $role->guard_name ?: 'web';
        AclPermissionRegistry::syncPermissions($guardName);

        $permissionGroups = AclPermissionRegistry::groupedPermissionsForMatrix($guardName);
        $menuPermissionSummary = AclPermissionRegistry::menuPermissionSummary($permissionGroups);
        $selectedPermissions = $role->permissions()->pluck('name')->all();

        return view('role.edit', compact('role', 'permissionGroups', 'menuPermissionSummary', 'selectedPermissions'));
    }

    public function update(Request $request, Role $role)
    {
        AclPermissionRegistry::syncPermissions($role->guard_name ?: 'web');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->ignore($role->id),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role->update([
            'name' => $validated['name'],
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('roles.index')
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function duplicate(Role $role)
    {
        AclPermissionRegistry::syncPermissions($role->guard_name ?: 'web');

        $baseName = trim($role->name.' copia');
        $newName = $baseName;
        $suffix = 2;

        while (Role::where('name', $newName)->exists()) {
            $newName = $baseName.' '.$suffix;
            $suffix++;
        }

        $newRole = Role::create([
            'name' => $newName,
            'guard_name' => $role->guard_name ?: 'web',
        ]);

        $newRole->syncPermissions($role->permissions()->pluck('name')->all());

        return redirect()->route('roles.edit', $newRole)
            ->with('success', 'Rol duplicado correctamente.');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        if ($role->name === config('acl.super_admin_role')) {
            return redirect()->route('roles.index')
                ->with('warning', 'No se puede eliminar el rol super administrador.');
        }

        $role->delete();

        return redirect()->route('roles.index')
            ->with('success', 'Rol eliminado correctamente.');
    }
}

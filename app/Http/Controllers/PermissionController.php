<?php

namespace App\Http\Controllers;

use App\Support\AclPermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        AclPermissionRegistry::syncPermissions();

        $permissions = Permission::orderBy('name')->paginate(20);

        return view('permission.index', compact('permissions'))
            ->with('i', (request()->input('page', 1) - 1) * $permissions->perPage());
    }

    public function create()
    {
        $permission = new Permission();

        return view('permission.create', compact('permission'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name',
        ]);

        Permission::create([
            'name' => $request->name,
            'guard_name' => 'web',
        ]);

        return redirect()->route('permissions.index')
            ->with('success', 'Permiso creado correctamente.');
    }

    public function show($id)
    {
        $permission = Permission::findOrFail($id);

        return view('permission.show', compact('permission'));
    }

    public function edit($id)
    {
        $permission = Permission::findOrFail($id);

        return view('permission.edit', compact('permission'));
    }

    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions')->ignore($permission->id),
            ],
        ]);

        $permission->update($request->only('name'));

        return redirect()->route('permissions.index')
            ->with('success', 'Permiso actualizado correctamente.');
    }

    public function destroy($id)
    {
        Permission::findOrFail($id)->delete();

        return redirect()->route('permissions.index')
            ->with('success', 'Permiso eliminado correctamente.');
    }

    public function sync()
    {
        $permissionNames = AclPermissionRegistry::syncPermissions();

        return redirect()->route('permissions.index')
            ->with('success', 'Permisos sincronizados correctamente. Total: '.count($permissionNames));
    }
}

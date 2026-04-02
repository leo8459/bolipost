<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Support\ClienteAclPermissionRegistry;
use App\Support\ClienteAclRoleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ClientRoleController extends Controller
{
    public function index()
    {
        ClienteAclRoleManager::sync();

        $roles = Role::query()
            ->where('guard_name', 'cliente')
            ->withCount('permissions')
            ->orderBy('name')
            ->paginate(20);

        $roleIds = $roles->getCollection()->pluck('id');

        $assignedClientsByRole = DB::table(config('permission.table_names.model_has_roles'))
            ->join('clientes', 'clientes.id', '=', 'model_has_roles.model_id')
            ->whereIn('model_has_roles.role_id', $roleIds)
            ->where('model_has_roles.model_type', Cliente::class)
            ->orderBy('clientes.name')
            ->get([
                'model_has_roles.role_id',
                'clientes.id',
                'clientes.name',
                'clientes.email',
            ])
            ->groupBy('role_id');

        $roles->setCollection(
            $roles->getCollection()->map(function (Role $role) use ($assignedClientsByRole) {
                $assignedClients = collect($assignedClientsByRole->get($role->id, []))
                    ->map(fn (object $cliente): array => [
                        'id' => (int) $cliente->id,
                        'name' => (string) $cliente->name,
                        'email' => (string) $cliente->email,
                    ])
                    ->values();

                $role->assigned_clients = $assignedClients;
                $role->assigned_clients_count = $assignedClients->count();

                return $role;
            })
        );

        return view('client-role.index', compact('roles'))
            ->with('i', (request()->input('page', 1) - 1) * $roles->perPage());
    }

    public function create()
    {
        ClienteAclRoleManager::sync();

        $role = new Role(['guard_name' => 'cliente']);
        $permissionGroups = ClienteAclPermissionRegistry::groupedPermissionsForMatrix('cliente');
        $selectedPermissions = [];

        return view('client-role.create', compact('role', 'permissionGroups', 'selectedPermissions'));
    }

    public function store(Request $request)
    {
        ClienteAclRoleManager::sync();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')->where(fn ($query) => $query->where('guard_name', 'cliente')),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', 'cliente')),
            ],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'cliente',
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('client-roles.index')
            ->with('success', 'Rol de cliente creado correctamente.');
    }

    public function edit(Role $clientRole)
    {
        if ($clientRole->guard_name !== 'cliente') {
            return redirect()->route('client-roles.index')
                ->with('warning', 'Este modulo solo administra roles del portal cliente.');
        }

        ClienteAclRoleManager::sync();

        $role = $clientRole;
        $permissionGroups = ClienteAclPermissionRegistry::groupedPermissionsForMatrix('cliente');
        $selectedPermissions = $role->permissions()
            ->where('guard_name', 'cliente')
            ->pluck('name')
            ->all();

        return view('client-role.edit', compact('role', 'permissionGroups', 'selectedPermissions'));
    }

    public function update(Request $request, Role $clientRole)
    {
        if ($clientRole->guard_name !== 'cliente') {
            return redirect()->route('client-roles.index')
                ->with('warning', 'Este modulo solo administra roles del portal cliente.');
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles')
                    ->where(fn ($query) => $query->where('guard_name', 'cliente'))
                    ->ignore($clientRole->id),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => [
                'string',
                Rule::exists('permissions', 'name')->where(fn ($query) => $query->where('guard_name', 'cliente')),
            ],
        ]);

        $clientRole->update([
            'name' => $validated['name'],
        ]);

        $clientRole->syncPermissions($validated['permissions'] ?? []);

        return redirect()->route('client-roles.index')
            ->with('success', 'Rol de cliente actualizado correctamente.');
    }

    public function destroy(Role $clientRole)
    {
        if ($clientRole->guard_name !== 'cliente') {
            return redirect()->route('client-roles.index')
                ->with('warning', 'Este modulo solo administra roles del portal cliente.');
        }

        $assignedClientsCount = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_has_roles.role_id', $clientRole->id)
            ->where('model_has_roles.model_type', Cliente::class)
            ->count();

        if ($assignedClientsCount > 0) {
            return redirect()->route('client-roles.index')
                ->with('warning', 'No se puede eliminar el rol de cliente porque tiene clientes asignados.');
        }

        $clientRole->delete();

        return redirect()->route('client-roles.index')
            ->with('success', 'Rol de cliente eliminado correctamente.');
    }
}

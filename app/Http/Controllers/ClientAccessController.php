<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Support\ClienteAclRoleManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ClientAccessController extends Controller
{
    public function index()
    {
        ClienteAclRoleManager::sync();

        $clientes = Cliente::query()
            ->orderBy('name')
            ->paginate(20);

        return view('client-access.index', compact('clientes'))
            ->with('i', (request()->input('page', 1) - 1) * $clientes->perPage());
    }

    public function edit(Cliente $cliente)
    {
        ClienteAclRoleManager::sync();

        $availableRoles = Role::query()
            ->where('guard_name', 'cliente')
            ->orderBy('name')
            ->get();

        $selectedRoles = $cliente->getRoleNames()->all();

        return view('client-access.edit', compact('cliente', 'availableRoles', 'selectedRoles'));
    }

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'roles' => 'nullable|array',
            'roles.*' => [
                'string',
                Rule::exists('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'cliente')),
            ],
        ]);

        $cliente->syncRoles($validated['roles'] ?? []);

        return redirect()->route('client-access.index')
            ->with('success', 'Accesos del cliente actualizados correctamente.');
    }
}

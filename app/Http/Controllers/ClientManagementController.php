<?php

namespace App\Http\Controllers;

use App\Models\Cliente;

class ClientManagementController extends Controller
{
    public function index()
    {
        $clientes = Cliente::query()
            ->orderByDesc('id')
            ->paginate(20);

        return view('client-management.index', compact('clientes'))
            ->with('i', (request()->input('page', 1) - 1) * $clientes->perPage());
    }
}

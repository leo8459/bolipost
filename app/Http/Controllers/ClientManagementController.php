<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

    public function impersonate(Request $request, Cliente $cliente): RedirectResponse
    {
        $administrator = Auth::guard('web')->user();

        abort_unless($administrator?->isSuperAdmin(), 403, 'Solo el administrador puede ingresar como un cliente.');
        abort_if($request->session()->has('impersonator_id'), 403, 'Finaliza primero la sesion actual de usuario.');
        abort_if($request->session()->has('impersonator_cliente_admin_id'), 403, 'Ya existe una sesion de cliente iniciada por el administrador.');

        Auth::guard('cliente')->login($cliente);
        $request->session()->put('impersonator_cliente_admin_id', (int) $administrator->id);
        $request->session()->regenerate();

        Log::notice('El administrador ingreso al portal como cliente Delivery Express.', [
            'administrator_id' => $administrator->id,
            'cliente_id' => $cliente->id,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('clientes.dashboard')
            ->with('success', 'Ahora estas usando el perfil de '.$cliente->name.'.');
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $administratorId = (int) $request->session()->get('impersonator_cliente_admin_id', 0);
        $administrator = Auth::guard('web')->user();

        abort_unless(
            $administratorId > 0
                && (int) $administrator?->id === $administratorId
                && $administrator?->isSuperAdmin(),
            403,
            'No existe una sesion valida de administrador para finalizar.'
        );

        $clienteId = (int) Auth::guard('cliente')->id();
        Auth::guard('cliente')->logout();
        $request->session()->forget('impersonator_cliente_admin_id');
        $request->session()->regenerate();

        Log::notice('El administrador finalizo la sesion como cliente Delivery Express.', [
            'administrator_id' => $administratorId,
            'cliente_id' => $clienteId,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('clientes-admin.index')
            ->with('success', 'Volviste a tu cuenta de administrador.');
    }
}

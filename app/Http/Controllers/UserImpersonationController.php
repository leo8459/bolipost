<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserImpersonationController extends Controller
{
    private const SESSION_KEY = 'impersonator_id';

    public function store(Request $request, User $user): RedirectResponse
    {
        $administrator = $request->user();

        abort_unless($administrator?->isSuperAdmin(), 403, 'Solo el administrador puede ingresar como otro usuario.');
        abort_if($request->session()->has(self::SESSION_KEY), 403, 'Ya existe una sesion de usuario iniciada por el administrador.');
        abort_if((int) $administrator->id === (int) $user->id, 422, 'Ya te encuentras usando este perfil.');
        abort_if($user->trashed(), 422, 'No puedes ingresar con un usuario inactivo.');

        $request->session()->put(self::SESSION_KEY, (int) $administrator->id);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        Log::notice('El administrador ingreso como otro usuario.', [
            'administrator_id' => $administrator->id,
            'impersonated_user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Ahora estas usando el perfil de '.$user->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $administratorId = (int) $request->session()->get(self::SESSION_KEY, 0);
        abort_if($administratorId <= 0, 403, 'No existe una sesion de usuario para finalizar.');

        $administrator = User::query()->find($administratorId);
        abort_unless($administrator?->isSuperAdmin(), 403, 'No se pudo recuperar la cuenta administradora.');

        $impersonatedUserId = (int) $request->user()?->id;

        Auth::guard('web')->login($administrator);
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerate();

        Log::notice('El administrador finalizo el ingreso como otro usuario.', [
            'administrator_id' => $administrator->id,
            'impersonated_user_id' => $impersonatedUserId,
            'ip' => $request->ip(),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'Volviste a tu cuenta de administrador.');
    }
}

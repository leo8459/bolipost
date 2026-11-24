<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class InitialPasswordController extends Controller
{
    public const SESSION_KEY = 'initial_password_challenge';

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.initial-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'password' => [
                'bail', 'required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers(),
                function (string $attribute, string $value, Closure $fail) use ($user): void {
                    if (strlen($value) > 72) {
                        $fail('La contraseña debe ocupar como máximo 72 bytes.');

                        return;
                    }

                    if ($value === config('auth.company_initial_password') || Hash::check($value, $user->getAuthPassword())) {
                        $fail('Elija una contraseña diferente de la temporal y de la actual.');
                    }
                },
            ],
        ], [
            'password.required' => 'Ingrese su nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $updated = User::query()->whereKey($user->getKey())
            ->where('must_change_password', true)
            ->where('password', $user->getAuthPassword())
            ->update([
                'password' => Hash::make($validated['password']),
                'must_change_password' => false,
                'remember_token' => null,
            ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $updated
            ? 'Contraseña actualizada. Inicie sesión con su nueva contraseña.'
            : 'La solicitud cambió. Inicie sesión nuevamente.');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);
        if ($pending === null) {
            return null;
        }

        $user = User::query()->where('must_change_password', true)->find($pending['user_id']);
        if ($user && hash_equals(
            $pending['password_fingerprint'],
            Auth::guard('web')->hashPasswordForCookie($user->getAuthPassword()),
        )) {
            return $user;
        }

        $request->session()->forget(self::SESSION_KEY);

        return null;
    }
}

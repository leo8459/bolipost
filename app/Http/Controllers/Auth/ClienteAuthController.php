<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class ClienteAuthController extends Controller
{
    private function googleIsConfigured(): bool
    {
        return trim((string) config('services.google.client_id')) !== ''
            && trim((string) config('services.google.client_secret')) !== ''
            && trim((string) config('services.google.redirect')) !== '';
    }

    public function showLogin(): View
    {
        return view('auth.clientes-login');
    }

    public function login(Request $request): RedirectResponse
    {
        return redirect()
            ->route('auth.google.redirect')
            ->with('status', 'Ingresa con Google para validar que el correo sea real.');
    }

    public function showRegister(): View
    {
        return view('auth.clientes-register');
    }

    public function register(Request $request): RedirectResponse
    {
        return redirect()
            ->route('auth.google.redirect')
            ->with('status', 'El registro publico usa Google para validar el correo.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('cliente')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }

    public function dashboard(): View
    {
        return view('clientes.dashboard', [
            'cliente' => Auth::guard('cliente')->user(),
        ]);
    }

    public function redirectToGoogle(): SymfonyRedirectResponse
    {
        if (! $this->googleIsConfigured()) {
            return redirect()
                ->route('clientes.login')
                ->withErrors([
                    'google' => 'Falta configurar GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET y GOOGLE_REDIRECT_URI en el archivo .env.',
                ]);
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $googleUser = Socialite::driver('google')->user();
        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '') {
            return redirect()
                ->route('clientes.login')
                ->withErrors(['google' => 'Google no devolvio un correo valido.']);
        }

        $cliente = Cliente::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => trim((string) $googleUser->getName()) ?: 'Cliente Google',
                'provider' => 'google',
                'rol' => 'tiktokero',
                'email_verified_at' => now(),
                'password' => Hash::make(\Illuminate\Support\Str::random(32)),
            ]
        );

        $cliente->forceFill([
            'name' => trim((string) $googleUser->getName()) ?: $cliente->name,
            'provider' => 'google',
            'google_id' => (string) $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => now(),
            'rol' => 'tiktokero',
        ])->save();

        Auth::guard('cliente')->login($cliente, true);
        $request->session()->regenerate();

        return redirect()->route('clientes.dashboard');
    }
}

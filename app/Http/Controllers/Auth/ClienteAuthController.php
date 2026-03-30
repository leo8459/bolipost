<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            ->with('status', 'Continua con Google para crear tu cuenta.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('cliente')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }

    public function dashboard()
    {
        $cliente = Auth::guard('cliente')->user();

        if (! $cliente->perfilCompleto()) {
            return redirect()->route('clientes.profile.complete');
        }

        return view('clientes.dashboard', [
            'cliente' => $cliente,
        ]);
    }

    public function showCompleteProfile()
    {
        $cliente = Auth::guard('cliente')->user();

        if ($cliente->perfilCompleto()) {
            return redirect()->route('clientes.dashboard');
        }

        return view('auth.clientes-complete-profile', [
            'cliente' => $cliente,
            'tiposDocumento' => Cliente::tiposDocumentoIdentidad(),
        ]);
    }

    public function completeProfile(Request $request): RedirectResponse
    {
        $cliente = Auth::guard('cliente')->user();

        $validated = $request->validate([
            'tipodocumentoidentidad' => ['required', 'string', 'in:1,2,3,4,5'],
            'complemento' => ['nullable', 'string', 'max:50'],
            'numero_carnet' => ['required', 'string', 'max:50'],
            'razon_social' => ['required', 'string', 'max:255'],
            'telefono' => ['required', 'string', 'max:50'],
            'direccion' => ['required', 'string', 'max:255'],
        ]);

        $cliente->forceFill([
            'tipodocumentoidentidad' => trim((string) $validated['tipodocumentoidentidad']),
            'complemento' => trim((string) ($validated['complemento'] ?? '')) ?: null,
            'numero_carnet' => trim((string) $validated['numero_carnet']),
            'razon_social' => trim((string) $validated['razon_social']),
            'telefono' => trim((string) $validated['telefono']),
            'direccion' => trim((string) $validated['direccion']),
        ])->save();

        return redirect()
            ->route('clientes.dashboard')
            ->with('success', 'Tus datos fueron actualizados correctamente.');
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
                'password' => Hash::make(Str::random(32)),
            ]
        );

        $updates = [
            'name' => trim((string) $googleUser->getName()) ?: $cliente->name,
            'provider' => 'google',
            'google_id' => (string) $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => now(),
            'rol' => 'tiktokero',
        ];

        $cliente->forceFill($updates)->save();

        Auth::guard('cliente')->login($cliente, true);
        $request->session()->regenerate();

        return $cliente->perfilCompleto()
            ? redirect()->route('clientes.dashboard')
            : redirect()->route('clientes.profile.complete');
    }
}

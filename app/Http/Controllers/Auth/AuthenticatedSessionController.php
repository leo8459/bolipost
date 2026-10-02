<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\UserLoginLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        if (session()->has('url.intended') && $this->isUnsafeIntendedUrl((string) session('url.intended'))) {
            session()->forget('url.intended');
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        Auth::guard('cliente')->logout();
        $user = $request->user();
        $redirectUrl = route('home.welcome', absolute: false);
        $request->session()->forget('url.intended');

        Log::info('Login autenticado; preparando redireccion del panel interno.', [
            'user_id' => $user?->id,
            'alias' => $user?->alias,
            'role' => $user?->role,
            'redirect_url' => $redirectUrl,
            'session_id_before_regenerate' => $request->session()->getId(),
        ]);

        $request->session()->regenerate();

        Log::info('Sesion regenerada despues del login.', [
            'user_id' => $request->user()?->id,
            'session_id_after_regenerate' => $request->session()->getId(),
        ]);

        if ($user) {
            try {
                UserLoginLog::query()
                    ->where('user_id', $user->id)
                    ->whereNull('logged_out_at')
                    ->update(['logged_out_at' => now()]);

                UserLoginLog::create([
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_alias' => $user->alias,
                    'ip_address' => $request->ip(),
                    'user_agent' => (string) $request->userAgent(),
                    'session_id' => $request->session()->getId(),
                    'logged_in_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                Log::warning('No se pudo registrar el ingreso del usuario.', [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return redirect()->to($redirectUrl);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $redirectTo = $this->logoutRedirectUrl($request->user());
        $this->markCurrentLoginAsLoggedOut($request);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
        $request->session()->forget('url.intended');

        return redirect()->to($redirectTo);
    }

    public function destroyViaGet(Request $request): RedirectResponse
    {
        $redirectTo = $this->logoutRedirectUrl($request->user());
        $this->markCurrentLoginAsLoggedOut($request);

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget('url.intended');

        return redirect()->to($redirectTo);
    }

    private function markCurrentLoginAsLoggedOut(Request $request): void
    {
        $user = $request->user();
        $sessionId = $request->session()->getId();

        if (! $user || trim((string) $sessionId) === '') {
            return;
        }

        try {
            $loginLog = UserLoginLog::query()
                ->where('user_id', $user->getAuthIdentifier())
                ->where('session_id', $sessionId)
                ->whereNull('logged_out_at')
                ->latestLogin()
                ->first();

            if ($loginLog) {
                $loginLog->update(['logged_out_at' => now()]);
            }
        } catch (\Throwable $exception) {
            Log::warning('No se pudo registrar la salida del usuario.', [
                'user_id' => $user->getAuthIdentifier(),
                'session_id' => $sessionId,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function logoutRedirectUrl(?Authenticatable $user): string
    {
        if ($this->isEmpresaUser($user)) {
            return route('paquetes-contrato.index', absolute: false);
        }

        return route('login', absolute: false);
    }

    private function isEmpresaUser(?Authenticatable $user): bool
    {
        return $user !== null
            && method_exists($user, 'hasRole')
            && $user->hasRole('empresa');
    }

    private function isUnsafeIntendedUrl(string $url): bool
    {
        $normalized = trim($url);
        if ($normalized === '') {
            return false;
        }

        $path = (string) parse_url($normalized, PHP_URL_PATH);
        $query = (string) parse_url($normalized, PHP_URL_QUERY);

        return trim($path, '/') === 'logout'
            || str_contains($query, 'motivo=inactividad');
    }
}

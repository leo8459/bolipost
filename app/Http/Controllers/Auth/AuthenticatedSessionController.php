<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
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
        $fallbackUrl = $this->firstAuthorizedUrl($user);
        $rawIntendedUrl = (string) $request->session()->get('url.intended', $fallbackUrl);
        $intendedUrl = $this->isUnsafeIntendedUrl($rawIntendedUrl) ? $fallbackUrl : $rawIntendedUrl;

        if ($intendedUrl === $fallbackUrl) {
            $request->session()->forget('url.intended');
        }

        Log::info('Login autenticado; preparando redireccion del panel interno.', [
            'user_id' => $user?->id,
            'alias' => $user?->alias,
            'role' => $user?->role,
            'url_intended_original' => $rawIntendedUrl,
            'url_intended_resuelta' => $intendedUrl,
            'fallback_url' => $fallbackUrl,
            'session_id_before_regenerate' => $request->session()->getId(),
        ]);

        $request->session()->regenerate();

        Log::info('Sesion regenerada despues del login.', [
            'user_id' => $request->user()?->id,
            'session_id_after_regenerate' => $request->session()->getId(),
        ]);

        return redirect()->intended($fallbackUrl);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $redirectTo = $this->logoutRedirectUrl($request->user());

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();
        $request->session()->forget('url.intended');

        return redirect()->to($redirectTo);
    }

    public function destroyViaGet(Request $request): RedirectResponse
    {
        $redirectTo = $this->logoutRedirectUrl($request->user());

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->forget('url.intended');

        return redirect()->to($redirectTo);
    }

    private function firstAuthorizedUrl(?Authenticatable $user): string
    {
        if (! $user) {
            return route('login', absolute: false);
        }

        $role = mb_strtolower(trim((string) ($user->role ?? '')));
        if ($role === 'taller') {
            return route('livewire.workshops', absolute: false);
        }

        if ($this->isEmpresaUser($user)) {
            return route('paquetes-contrato.index', absolute: false);
        }

        return route('home.welcome', absolute: false);
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

    /**
     * @return array<int, string>
     */
    private function authorizedMenuUrls(Authenticatable $user): array
    {
        $urls = [];

        foreach ((array) config('adminlte.menu', []) as $item) {
            foreach ($this->extractAuthorizedMenuUrls($item, $user) as $url) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * @return array<int, string>
     */
    private function extractAuthorizedMenuUrls(array $item, Authenticatable $user): array
    {
        if (isset($item['header']) || isset($item['type'])) {
            return [];
        }

        $submenu = $item['submenu'] ?? null;
        if (is_array($submenu) && $submenu !== []) {
            $urls = [];

            foreach ($submenu as $child) {
                if (! is_array($child)) {
                    continue;
                }

                foreach ($this->extractAuthorizedMenuUrls($child, $user) as $url) {
                    $urls[] = $url;
                }
            }

            return $urls;
        }

        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '' || str_starts_with($url, 'http') || str_starts_with($url, '#')) {
            return [];
        }

        $routeName = $this->routeNameFromMenuUrl($url);
        if ($routeName === null) {
            return [];
        }

        if (! $user->can($routeName)) {
            return [];
        }

        return ['/' . trim($url, '/')];
    }

    private function routeNameFromMenuUrl(string $url): ?string
    {
        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        if ($path === '') {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create('/' . $path, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        $name = $route->getName();

        return is_string($name) && $name !== '' ? $name : null;
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

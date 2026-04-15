<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->to($this->firstAuthorizedUrl($request->user()));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
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

        foreach ($this->authorizedMenuUrls($user) as $url) {
            return $url;
        }

        return route('profile.edit', absolute: false);
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
}

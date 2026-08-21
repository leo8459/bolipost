<?php

namespace App\Http\Controllers;

use App\Models\ExternalApiToken;
use App\Services\ApiManualDocxService;
use App\Support\ExternalApiJwt;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExternalApiTokenController extends Controller
{
    public function index(Request $request)
    {
        $tokens = ExternalApiToken::query()
            ->latest('id')
            ->get();
        $apiCatalog = config('external_apis.catalog', []);
        $legacyAbilityNames = config('external_apis.legacy_names', []);
        $apiSelectionGroups = $this->apiSelectionGroups($apiCatalog);
        $routeInventoryCount = collect($apiSelectionGroups)->sum(fn (array $routes): int => count($routes));
        $routeAbilityNames = [];
        $abilityEndpoints = collect($apiSelectionGroups)
            ->flatten(1)
            ->groupBy('ability')
            ->map(fn ($routes): array => $routes
                ->unique(fn (array $route): string => $route['method'].'|'.$route['uri'])
                ->map(fn (array $route): array => [
                    'method' => $route['method'],
                    'path' => $route['uri'],
                ])
                ->values()
                ->all())
            ->all();
        $tokenEndpointMap = $tokens->mapWithKeys(function (ExternalApiToken $token) use ($abilityEndpoints): array {
            $hasPackageApi = collect($token->abilities ?? [])->contains(
                fn (string $ability): bool => Str::startsWith($ability, 'paquetes-contactos:')
            );
            $endpoints = collect($token->abilities ?? [])
                ->flatMap(fn (string $ability): array => $abilityEndpoints[$ability] ?? [])
                ->reject(fn (array $endpoint): bool => $hasPackageApi
                    && Str::startsWith($endpoint['path'], '/api/paquetes-contactos'))
                ->unique(fn (array $endpoint): string => $endpoint['method'].'|'.$endpoint['path'])
                ->values()
                ->all();

            if ($hasPackageApi) {
                array_unshift($endpoints, [
                    'method' => 'GET',
                    'path' => '/api/paquetes-contactos',
                ]);
            }

            return [$token->id => $endpoints];
        })->all();
        $createMode = $request->boolean('nueva');

        return view('configuracion.apis', compact(
            'tokens',
            'apiCatalog',
            'legacyAbilityNames',
            'apiSelectionGroups',
            'routeInventoryCount',
            'routeAbilityNames',
            'abilityEndpoints',
            'tokenEndpointMap',
            'createMode'
        ));
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalog
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function apiSelectionGroups(array $catalog): array
    {
        $groups = collect([
            'GET' => collect(),
            'POST' => collect(),
            'PUT / PATCH' => collect(),
            'DELETE' => collect(),
        ]);

        foreach ($catalog as $ability => $api) {
            foreach ($api['endpoints'] as $endpoint) {
                $method = strtoupper($endpoint['method']);
                $group = in_array($method, ['PUT', 'PATCH'], true) ? 'PUT / PATCH' : $method;

                if (! $groups->has($group)) {
                    continue;
                }

                $groups[$group]->put($method.'|'.$endpoint['path'], [
                    'method' => $method,
                    'uri' => $endpoint['path'],
                    'name' => $api['name'],
                    'description' => $api['description'],
                    'ability' => $ability,
                    'access' => 'Token externo configurable',
                    'access_color' => 'success',
                    'selectable' => true,
                    'query' => (string) ($endpoint['example'] ?? ''),
                    'body' => $endpoint['body'] ?? null,
                    'response' => $endpoint['response'] ?? null,
                ]);
            }
        }

        return $groups
            ->map(fn ($routes): array => $routes
                ->sortBy(fn (array $route): string => $route['uri'].'|'.$route['method'])
                ->values()
                ->all())
            ->filter(fn (array $routes): bool => $routes !== [])
            ->all();
    }

    public function store(Request $request)
    {
        $allowedAbilities = array_keys(config('external_apis.catalog', []));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'expires_at' => ['nullable', 'date', 'after:today'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', Rule::in($allowedAbilities)],
        ]);

        $expiresAt = ! empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null;

        $apiToken = ExternalApiToken::query()->create([
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'jti' => hash('sha256', Str::uuid()->toString().Str::random(32)),
            'token_hash' => hash('sha256', Str::random(80)),
            'abilities' => array_values(array_unique($data['abilities'])),
            'is_active' => true,
            'expires_at' => $expiresAt,
        ]);

        $jwt = ExternalApiJwt::issue($apiToken, null);
        $apiToken->forceFill([
            'token_hash' => hash('sha256', $jwt),
            'token_encrypted' => Crypt::encryptString($jwt),
            'token_plain' => $jwt,
        ])->save();

        return redirect()
            ->route('configuracion.apis.index')
            ->with('status', 'Token generado correctamente. Copia el token ahora; no se mostrara completo otra vez.')
            ->with('new_token', $jwt)
            ->with('manual_url', route('configuracion.apis.token-manual', $apiToken));
    }

    public function deactivate(ExternalApiToken $token)
    {
        $token->forceFill([
            'token_hash' => hash('sha256', Str::random(80).now()->timestamp),
            'token_encrypted' => null,
            'token_plain' => null,
            'is_active' => false,
            'revoked_at' => now(),
        ])->save();

        return back()->with('status', 'Token dado de baja y eliminado. La API ya no aceptara ese token.');
    }

    public function destroy(ExternalApiToken $token)
    {
        $token->forceFill([
            'token_hash' => hash('sha256', Str::random(80).now()->timestamp),
            'token_encrypted' => null,
            'token_plain' => null,
            'is_active' => false,
            'revoked_at' => now(),
        ])->save();

        $token->delete();

        return back()->with('status', 'Credencial API borrada correctamente. Su token ya no funciona.');
    }

    public function regenerate(ExternalApiToken $token)
    {
        $token->forceFill([
            'is_active' => true,
            'revoked_at' => null,
        ])->save();

        $jwt = ExternalApiJwt::issue($token, null);
        $token->forceFill([
            'token_hash' => hash('sha256', $jwt),
            'token_encrypted' => Crypt::encryptString($jwt),
            'token_plain' => $jwt,
        ])->save();

        return redirect()
            ->route('configuracion.apis.index')
            ->with('status', 'Token regenerado correctamente. El token anterior ya no sera aceptado.')
            ->with('new_token', $jwt);
    }

    public function activate(ExternalApiToken $token)
    {
        $token->forceFill([
            'is_active' => true,
            'revoked_at' => null,
        ])->save();

        $jwt = ExternalApiJwt::issue($token, null);
        $token->forceFill([
            'token_hash' => hash('sha256', $jwt),
            'token_encrypted' => Crypt::encryptString($jwt),
            'token_plain' => $jwt,
        ])->save();

        return redirect()
            ->route('configuracion.apis.index')
            ->with('status', 'Token activado y generado nuevamente.')
            ->with('new_token', $jwt);
    }

    public function downloadManual(ApiManualDocxService $manual)
    {
        $path = $manual->generate();

        return response()->download($path, 'manual-completo-apis-trackingbo.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    public function downloadTokenManual(ExternalApiToken $token, ApiManualDocxService $manual)
    {
        $path = $manual->generate($token);
        $filename = 'manual-api-'.Str::slug($token->name).'.docx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function routeInventory(): array
    {
        $groups = [
            'GET' => [],
            'POST' => [],
            'PUT / PATCH' => [],
            'DELETE' => [],
        ];

        foreach (Route::getRoutes() as $route) {
            if (! Str::startsWith($route->uri(), 'api/')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $group = in_array($method, ['PUT', 'PATCH'], true) ? 'PUT / PATCH' : $method;
                if (! isset($groups[$group])) {
                    continue;
                }

                $groups[$group][] = [
                    'method' => $method,
                    'uri' => '/'.$route->uri(),
                    'name' => $this->routeDisplayName($method, $route->uri()),
                    'ability' => $this->routeAbility($route, $method),
                    ...$this->routeAccess($route),
                ];
            }
        }

        foreach ($groups as &$routes) {
            usort($routes, fn (array $left, array $right): int => strcmp($left['uri'], $right['uri']));
        }

        return $groups;
    }

    private function routeAbility(LaravelRoute $route, string $method): string
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (preg_match('/EnsureExternalApiAbility:([^,]+)/', $middleware, $matches) === 1) {
                return $matches[1];
            }
        }

        if (Str::startsWith((string) $route->getName(), 'api.paquetes-contactos')) {
            return 'paquetes-contactos:read';
        }

        return 'route:'.strtoupper($method).':/'.$route->uri();
    }

    private function routeDisplayName(string $method, string $uri): string
    {
        $labels = [
            'activity-logs' => 'registros de actividad',
            'alerts' => 'alertas',
            'by-vehicle' => 'por vehículo',
            'clientes' => 'clientes',
            'db-snapshot' => 'respaldo de base de datos',
            'drivers' => 'conductores',
            'emergency-alerts' => 'alertas de emergencia',
            'fuel-logs' => 'registros de combustible',
            'location' => 'ubicación',
            'maintenance-requests' => 'solicitudes de mantenimiento',
            'mobile' => 'aplicación móvil',
            'operational-incident' => 'incidente operativo',
            'public' => 'servicios públicos',
            'reassignment' => 'reasignación',
            'resources' => 'recursos',
            'snapshot' => 'respaldo',
            'stage-event' => 'evento de etapa',
            'tracking' => 'seguimiento',
            'vehicle-logs' => 'registros de vehículos',
        ];

        $subject = collect(explode('/', Str::after($uri, 'api/')))
            ->map(function (string $segment) use ($labels): string {
                if (Str::startsWith($segment, '{')) {
                    return Str::headline(trim($segment, '{}'));
                }

                return $labels[$segment] ?? Str::headline($segment);
            })
            ->implode(' › ');

        $action = match ($method) {
            'GET' => 'Consultar',
            'POST' => 'Registrar o ejecutar',
            'PUT' => 'Reemplazar',
            'PATCH' => 'Actualizar',
            'DELETE' => 'Eliminar',
            default => $method,
        };

        return $action.' '.$subject;
    }

    /**
     * @return array{access: string, access_color: string, selectable: bool}
     */
    private function routeAccess(LaravelRoute $route): array
    {
        $middleware = implode('|', $route->gatherMiddleware());

        if (str_contains($middleware, 'EnsureExternalApiJwt')) {
            return ['access' => 'Token externo configurable', 'access_color' => 'success', 'selectable' => true];
        }

        if (str_contains($middleware, 'EnsureSiopApiToken')) {
            return ['access' => 'Token SIOP independiente', 'access_color' => 'warning', 'selectable' => false];
        }

        if (str_contains($middleware, 'Authenticate') || str_contains($middleware, 'EnsureSingleMobileSession')) {
            return ['access' => 'Sesión autenticada', 'access_color' => 'primary', 'selectable' => false];
        }

        if (str_contains($middleware, 'web')) {
            return ['access' => 'Sesión web o uso interno', 'access_color' => 'secondary', 'selectable' => false];
        }

        return ['access' => 'Pública o control propio', 'access_color' => 'info', 'selectable' => false];
    }
}

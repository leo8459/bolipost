<?php

namespace App\Http\Middleware;

use App\Services\AuditoriaService;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RegistrarAuditoria
{
    public function __construct(private AuditoriaService $auditoriaService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $this->registrar($request, $response);
        } catch (\Throwable $e) {
            // No bloquear flujo principal si auditoria falla.
        }

        return $response;
    }

    private function registrar(Request $request, Response $response): void
    {
        if ($this->debeIgnorarse($request)) {
            return;
        }

        $userId = $this->resolverUsuarioId($request);
        if ($userId <= 0) {
            return;
        }

        [$tipoEvento, $detalle] = $this->resolverAccion($request);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $tipoEvento = 'FALLIDO';
            $detalle = 'HTTP ' . $status . ' en ' . $this->resolverNombreOperacion($request, $detalle);
        }

        $this->auditoriaService->registrar($tipoEvento, $detalle, $userId);
    }

    private function resolverUsuarioId(Request $request): int
    {
        $userId = (int) Auth::guard('web')->id();
        if ($userId > 0) {
            return $userId;
        }

        return (int) optional($request->user())->id;
    }

    private function debeIgnorarse(Request $request): bool
    {
        foreach ([
            '_debugbar/*',
            'telescope/*',
            'horizon/*',
            'pulse/*',
            'up',
            'sanctum/csrf-cookie',
            'livewire/livewire.js',
            'livewire/livewire.min.js',
            'livewire/livewire.js.map',
        ] as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function esEntradaPestana(Request $request): bool
    {
        if (!$request->isMethod('GET') || $request->ajax() || $request->wantsJson()) {
            return false;
        }

        return true;
    }

    private function resolverNombrePestana(Request $request): string
    {
        $routeName = (string) optional($request->route())->getName();
        if ($routeName !== '') {
            return strtoupper(str_replace(['.', '-', '_'], ' ', $routeName));
        }

        $path = trim((string) $request->path(), '/');
        if ($path === '') {
            return 'INICIO';
        }

        return strtoupper(str_replace(['/', '-', '_'], ' ', $path));
    }

    private function resolverAccion(Request $request): array
    {
        if ($request->is('livewire/update')) {
            return $this->resolverAccionLivewire($request);
        }

        if ($this->esEntradaPestana($request)) {
            $nombre = $this->resolverNombrePestana($request);
            return ['ENTRO', 'Entro a pestana ' . $nombre];
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return ['CONSULTADO', $this->detalleEstandar('CONSULTA', $request)];
        }

        if ($request->isMethod('DELETE')) {
            return ['ELIMINADO', $this->detalleEstandar('ELIMINADO', $request)];
        }

        if ($request->isMethod('PUT') || $request->isMethod('PATCH')) {
            return ['EDICION', $this->detalleEstandar('EDICION', $request)];
        }

        if ($request->isMethod('POST')) {
            return ['CREADO', $this->detalleEstandar('CREADO', $request)];
        }

        return ['ACCION', $this->detalleEstandar('ACCION', $request)];
    }

    private function resolverAccionLivewire(Request $request): array
    {
        $component = data_get($request->input('components', []), '0');
        if (!is_array($component)) {
            return ['ACCION', 'Accion LIVEWIRE sin componente'];
        }

        $method = (string) data_get($component, 'calls.0.method', '');
        if ($method === '') {
            return ['ACCION', 'Accion LIVEWIRE sin metodo'];
        }

        $metodoLower = strtolower($method);
        $snapshot = (string) data_get($component, 'snapshot', '');
        $componentName = 'livewire';
        if ($snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $componentName = (string) data_get($decoded, 'memo.name', 'livewire');
            }
        }

        $tipoEvento = $this->clasificarMetodo($metodoLower);

        $detalle = 'Accion ' . strtoupper($method) . ' en ' . strtoupper(str_replace(['-', '_'], ' ', $componentName));
        return [$tipoEvento, $detalle];
    }

    private function clasificarMetodo(string $method): string
    {
        foreach (['delete', 'destroy', 'eliminar', 'borrar'] as $token) {
            if (str_contains($method, $token)) {
                return 'ELIMINADO';
            }
        }

        if (str_contains($method, 'save')) {
            return 'CREADO';
        }

        foreach (['search', 'buscar', 'filter', 'filtro'] as $token) {
            if (str_contains($method, $token)) {
                return 'CONSULTADO';
            }
        }

        foreach (['create', 'store', 'nuevo', 'registrar'] as $token) {
            if (str_contains($method, $token)) {
                return 'CREADO';
            }
        }

        foreach ([
            'save',
            'update',
            'edit',
            'marcar',
            'mandar',
            'confirmar',
            'recibir',
            'devolver',
            'entrega',
            'assign',
            'asignar',
            'baja',
            'rezago',
        ] as $token) {
            if (str_contains($method, $token)) {
                return 'EDICION';
            }
        }

        return 'ACCION';
    }

    private function detalleEstandar(string $tipo, Request $request): string
    {
        $routeName = (string) optional($request->route())->getName();
        if ($routeName === '') {
            $routeName = strtoupper(str_replace(['/', '-', '_'], ' ', (string) $request->path()));
        }

        return $tipo . ' en ' . strtoupper(str_replace(['.', '-', '_'], ' ', $routeName));
    }

    private function resolverNombreOperacion(Request $request, string $detalle): string
    {
        $routeName = (string) optional($request->route())->getName();
        if ($routeName !== '') {
            return strtoupper(str_replace(['.', '-', '_'], ' ', $routeName));
        }

        $path = trim((string) $request->path(), '/');
        if ($path !== '') {
            return strtoupper(str_replace(['/', '-', '_'], ' ', $path));
        }

        return strtoupper($detalle);
    }
}

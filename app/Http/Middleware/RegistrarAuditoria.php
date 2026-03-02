<?php

namespace App\Http\Middleware;

use App\Services\AuditoriaService;
use Closure;
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
        if ($response->getStatusCode() >= 400) {
            return;
        }

        $userId = (int) optional($request->user())->id;
        if ($userId <= 0) {
            return;
        }

        if ($this->esEntradaPestana($request)) {
            $nombre = $this->resolverNombrePestana($request);
            $this->auditoriaService->registrar('ENTRO', 'Entro a pestana ' . $nombre, $userId);
            return;
        }

        $accion = $this->resolverAccion($request);
        if (!$accion) {
            return;
        }

        [$tipoEvento, $detalle] = $accion;
        $this->auditoriaService->registrar($tipoEvento, $detalle, $userId);
    }

    private function esEntradaPestana(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }

        if ($request->is('livewire/*') || $request->is('api/*')) {
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

    private function resolverAccion(Request $request): ?array
    {
        if ($request->is('livewire/update')) {
            return $this->resolverAccionLivewire($request);
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

        return null;
    }

    private function resolverAccionLivewire(Request $request): ?array
    {
        $component = data_get($request->input('components', []), '0');
        if (!is_array($component)) {
            return null;
        }

        $method = (string) data_get($component, 'calls.0.method', '');
        if ($method === '') {
            return null;
        }

        $metodoLower = strtolower($method);
        if (
            str_starts_with($metodoLower, 'open') ||
            str_starts_with($metodoLower, 'close') ||
            str_starts_with($metodoLower, 'toggle') ||
            str_starts_with($metodoLower, 'search') ||
            str_starts_with($metodoLower, 'updated') ||
            str_starts_with($metodoLower, 'reset')
        ) {
            return null;
        }

        $snapshot = (string) data_get($component, 'snapshot', '');
        $componentName = 'livewire';
        if ($snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            if (is_array($decoded)) {
                $componentName = (string) data_get($decoded, 'memo.name', 'livewire');
            }
        }

        $tipoEvento = $this->clasificarMetodo($metodoLower);
        if ($tipoEvento === null) {
            return null;
        }

        $detalle = 'Accion ' . strtoupper($method) . ' en ' . strtoupper(str_replace(['-', '_'], ' ', $componentName));
        return [$tipoEvento, $detalle];
    }

    private function clasificarMetodo(string $method): ?string
    {
        foreach (['delete', 'destroy', 'eliminar', 'borrar'] as $token) {
            if (str_contains($method, $token)) {
                return 'ELIMINADO';
            }
        }

        if (str_contains($method, 'save')) {
            return 'CREADO';
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

        return null;
    }

    private function detalleEstandar(string $tipo, Request $request): string
    {
        $routeName = (string) optional($request->route())->getName();
        if ($routeName === '') {
            $routeName = strtoupper(str_replace(['/', '-', '_'], ' ', (string) $request->path()));
        }

        return $tipo . ' en ' . strtoupper(str_replace(['.', '-', '_'], ' ', $routeName));
    }
}

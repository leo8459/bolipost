<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SetSystemAuditContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (DB::getDriverName() !== 'pgsql') {
            return $next($request);
        }

        $user = $request->user();
        if (! $user) {
            try {
                $user = auth('sanctum')->user();
            } catch (\Throwable) {
                // Las rutas públicas igual quedan registradas con IP y agente de usuario.
            }
        }
        $context = [
            'app.audit_user_id' => $user?->getAuthIdentifier(),
            'app.audit_user_name' => $user?->name,
            'app.audit_user_alias' => $user?->alias,
            'app.audit_request_ip' => $request->ip(),
            'app.audit_user_agent' => $request->userAgent(),
        ];

        try {
            $connection = DB::connection();
            $assignments = [];
            $bindings = [];

            foreach ($context as $key => $value) {
                $assignments[] = "set_config('{$key}', ?, false)";
                $bindings[] = (string) ($value ?? '');
            }

            $connection->select('SELECT '.implode(', ', $assignments), $bindings);
        } catch (\Throwable) {
            return $next($request);
        }

        try {
            return $next($request);
        } finally {
            try {
                $clear = array_map(fn (string $key): string => "set_config('{$key}', '', false)", array_keys($context));
                $connection->select('SELECT '.implode(', ', $clear));
            } catch (\Throwable) {
                // La auditoría nunca debe impedir que se complete una solicitud.
            }
        }
    }
}

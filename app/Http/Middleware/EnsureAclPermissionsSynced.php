<?php

namespace App\Http\Middleware;

use App\Support\AclRoleManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAclPermissionsSynced
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('acl.sync.enabled', true)) {
            return $next($request);
        }

        if (! $request->user()) {
            return $next($request);
        }

        $ttlSeconds = max(30, (int) config('acl.sync.ttl_seconds', 300));
        $cacheKey = 'acl:permissions:sync:at';

        if (! Cache::has($cacheKey)) {
            try {
                $this->syncAsSystem();
                Cache::put($cacheKey, now()->toIso8601String(), now()->addSeconds($ttlSeconds));
            } catch (\Throwable $exception) {
                Log::warning('No se pudo sincronizar ACL automaticamente.', [
                    'message' => $exception->getMessage(),
                    'exception' => $exception::class,
                ]);
            }
        }

        return $next($request);
    }

    private function syncAsSystem(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            AclRoleManager::sync();
            return;
        }

        $connection = DB::connection();
        $previousContext = $connection->selectOne(<<<'SQL'
SELECT
    current_setting('app.audit_user_id', true) AS user_id,
    current_setting('app.audit_user_name', true) AS user_name,
    current_setting('app.audit_user_alias', true) AS user_alias
SQL);

        $connection->select(<<<'SQL'
SELECT
    set_config('app.audit_user_id', '', false),
    set_config('app.audit_user_name', 'Sistema (sincronizacion ACL)', false),
    set_config('app.audit_user_alias', 'ACL automatico', false)
SQL);

        try {
            AclRoleManager::sync();
        } finally {
            $connection->select(<<<'SQL'
SELECT
    set_config('app.audit_user_id', ?, false),
    set_config('app.audit_user_name', ?, false),
    set_config('app.audit_user_alias', ?, false)
SQL, [
                (string) ($previousContext->user_id ?? ''),
                (string) ($previousContext->user_name ?? ''),
                (string) ($previousContext->user_alias ?? ''),
            ]);
        }
    }
}


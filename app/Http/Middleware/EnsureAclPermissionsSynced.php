<?php

namespace App\Http\Middleware;

use App\Support\AclRoleManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
                AclRoleManager::sync();
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
}


<?php

namespace App\Http\Middleware;

use App\Support\ClienteAclRoleManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureClienteAclPermissionsSynced
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('acl_cliente.sync.enabled', true)) {
            return $next($request);
        }

        if (! $request->user('cliente')) {
            return $next($request);
        }

        $ttlSeconds = max(30, (int) config('acl_cliente.sync.ttl_seconds', 300));
        $cacheKey = 'acl:cliente:permissions:sync:at';

        if (! Cache::has($cacheKey)) {
            ClienteAclRoleManager::sync();
            Cache::put($cacheKey, now()->toIso8601String(), now()->addSeconds($ttlSeconds));
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\InternationalMenuAcl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternationalMenuPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $submenuPermission = InternationalMenuAcl::permissionForRequest($request);

        abort_unless(
            InternationalMenuAcl::userCanAccess($user, $submenuPermission),
            403,
            'No tienes permiso para acceder a esta sección de Internacional.'
        );

        return $next($request);
    }
}

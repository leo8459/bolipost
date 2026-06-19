<?php

namespace App\Http\Middleware;

use App\Services\EmpresaContractUserSyncService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmpresaContractUsersActive
{
    public function __construct(
        private readonly EmpresaContractUserSyncService $syncService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $this->syncService->ensureAuthenticatedUserIsActive($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->withErrors([
                    'alias' => 'El contrato de su empresa vencio y su usuario fue dado de baja automaticamente.',
                ]);
        }

        View::share('empresaContractAlerts', $this->syncService->buildExpirationAlertsForUser($user));

        return $next($request);
    }
}

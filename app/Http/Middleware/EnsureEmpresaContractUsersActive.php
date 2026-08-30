<?php

namespace App\Http\Middleware;

use App\Services\AlertaEmpresaService;
use App\Services\EmpresaContractUserSyncService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmpresaContractUsersActive
{
    public function __construct(
        private readonly EmpresaContractUserSyncService $syncService,
        private readonly AlertaEmpresaService $alertaEmpresaService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // This middleware is global, but empresa contracts and alerts belong only
        // to users from the internal "web" guard. Client routes switch Laravel's
        // default guard to "cliente", so Request::user() can return a Cliente and
        // cause the strictly typed AlertaEmpresaService to throw a TypeError.
        $user = Auth::guard('web')->user();

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
        View::share('alertaEmpresaPendiente', $this->alertaEmpresaService->siguienteNoLeida($user));

        return $next($request);
    }
}

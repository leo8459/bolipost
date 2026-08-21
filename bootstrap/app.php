<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\EnsureAclPermissionsSynced;
use App\Http\Middleware\EnsureClienteAclPermissionsSynced;
use App\Http\Middleware\EnsureClienteProfileComplete;
use App\Http\Middleware\EnsureClienteRoutePermission;
use App\Http\Middleware\EnsureEmpresaContractUsersActive;
use App\Http\Middleware\EnsureExternalApiAbility;
use App\Http\Middleware\EnsureExternalApiJwt;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\EnsureInternalWebAccess;
use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\EnsureSingleMobileSession;
use App\Http\Middleware\EnsureSiopApiToken;
use App\Http\Middleware\RedirectIfClienteAuthenticated;
use App\Http\Middleware\RegistrarAuditoria;
use App\Http\Middleware\UseClienteGuard;
use App\Services\ContratoCodigoService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['api/*']);

        $middleware->alias([
            'route.permission' => EnsureRoutePermission::class,
            'route.permission.cliente' => EnsureClienteRoutePermission::class,
            'guest.cliente' => RedirectIfClienteAuthenticated::class,
            'cliente.guard' => UseClienteGuard::class,
            'cliente.profile.complete' => EnsureClienteProfileComplete::class,
            'cliente.acl.sync' => EnsureClienteAclPermissionsSynced::class,
            'internal.only' => EnsureInternalWebAccess::class,
            'single.mobile.session' => EnsureSingleMobileSession::class,
            'empresa.contract.active' => EnsureEmpresaContractUsersActive::class,
            'siop.api.token' => EnsureSiopApiToken::class,
            'external.api.jwt' => EnsureExternalApiJwt::class,
            'external.api.ability' => EnsureExternalApiAbility::class,
            'force.json' => ForceJsonResponse::class,
            'abilities' => CheckAbilities::class,
        ]);

        $middleware->web(append: [
            ApplySecurityHeaders::class,
            EnsureAclPermissionsSynced::class,
            EnsureEmpresaContractUsersActive::class,
            RegistrarAuditoria::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $e, Request $request) {
            $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());
            $detail = strtolower($e->getMessage());
            $esCodigoContratoDuplicado = in_array($sqlState, ['23000', '23505'], true)
                && (
                    str_contains($detail, 'paquetes_contrato_codigo_unique')
                    || str_contains($detail, 'paquetes_contrato.codigo')
                );

            if (! $esCodigoContratoDuplicado) {
                return null;
            }

            $message = ContratoCodigoService::MENSAJE_CODIGO_DUPLICADO;

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 409);
            }

            return redirect()->back()->withInput()->with('error', $message);
        });

        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            $message = 'Sesion vencida, por favor actualice la pagina.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 419);
            }

            $loginRoute = $request->is('clientes/*') && Route::has('clientes.login')
                ? 'clientes.login'
                : 'login';

            if ($request->is('paquetes-contrato') || $request->is('paquetes-contrato/*')) {
                return redirect()
                    ->guest(route('paquetes-contrato.index', absolute: false))
                    ->with('status', $message)
                    ->with('session_expired', true);
            }

            return redirect()
                ->route($loginRoute)
                ->with('status', $message)
                ->with('session_expired', true);
        });
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('tracking:check')->cron('0 */8 * * *')->withoutOverlapping();
    })
    ->create();

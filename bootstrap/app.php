<?php

use Illuminate\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;

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
            'route.permission' => \App\Http\Middleware\EnsureRoutePermission::class,
            'route.permission.cliente' => \App\Http\Middleware\EnsureClienteRoutePermission::class,
            'guest.cliente' => \App\Http\Middleware\RedirectIfClienteAuthenticated::class,
            'cliente.guard' => \App\Http\Middleware\UseClienteGuard::class,
            'cliente.profile.complete' => \App\Http\Middleware\EnsureClienteProfileComplete::class,
            'cliente.acl.sync' => \App\Http\Middleware\EnsureClienteAclPermissionsSynced::class,
            'internal.only' => \App\Http\Middleware\EnsureInternalWebAccess::class,
            'single.mobile.session' => \App\Http\Middleware\EnsureSingleMobileSession::class,
            'empresa.contract.active' => \App\Http\Middleware\EnsureEmpresaContractUsersActive::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\ApplySecurityHeaders::class,
            \App\Http\Middleware\EnsureAclPermissionsSynced::class,
            \App\Http\Middleware\EnsureEmpresaContractUsersActive::class,
            \App\Http\Middleware\RegistrarAuditoria::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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


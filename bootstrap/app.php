<?php

use Illuminate\Foundation\Application;
<<<<<<< HEAD
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
=======
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
>>>>>>> a41ccfb (Uchazara)

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
<<<<<<< HEAD
=======
        $middleware->statefulApi();

>>>>>>> a41ccfb (Uchazara)
        $middleware->alias([
            'route.permission' => \App\Http\Middleware\EnsureRoutePermission::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\EnsureAclPermissionsSynced::class,
            \App\Http\Middleware\RegistrarAuditoria::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
<<<<<<< HEAD
        //
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('tracking:check')->cron('0 */8 * * *')->withoutOverlapping();
    })
    ->create();
=======
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            return null;
        });
    })->create();
>>>>>>> a41ccfb (Uchazara)

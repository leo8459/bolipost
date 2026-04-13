<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ClienteAuthController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest.cliente', 'throttle:10,1'])->group(function () {
    Route::get('clientes/login', [ClienteAuthController::class, 'showLogin'])->name('clientes.login');
    Route::post('clientes/login', [ClienteAuthController::class, 'login'])->name('clientes.login.store');
    Route::get('clientes/register', [ClienteAuthController::class, 'showRegister'])->name('clientes.register');
    Route::post('clientes/register', [ClienteAuthController::class, 'register'])->name('clientes.register.store');
    Route::get('auth/google/redirect', [ClienteAuthController::class, 'redirectToGoogle'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [ClienteAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
});

Route::middleware(['cliente.guard', 'auth:cliente', 'cliente.acl.sync', 'route.permission.cliente'])->group(function () {
    Route::get('clientes/dashboard', [ClienteAuthController::class, 'dashboard'])
        ->name('clientes.dashboard');
    Route::post('clientes/logout', [ClienteAuthController::class, 'logout'])->name('clientes.logout');
    Route::get('clientes/completar-perfil', [ClienteAuthController::class, 'showCompleteProfile'])->name('clientes.profile.complete');
    Route::post('clientes/completar-perfil', [ClienteAuthController::class, 'completeProfile'])->name('clientes.profile.complete.store');

        Route::middleware('cliente.profile.complete')->group(function () {
            Route::get('clientes/solicitudes', [\App\Http\Controllers\ClienteSolicitudController::class, 'create'])
                ->name('clientes.solicitudes.index');
            Route::get('clientes/solicitudes/nueva', [\App\Http\Controllers\ClienteSolicitudController::class, 'create'])
                ->name('clientes.solicitudes.create');
            Route::post('clientes/solicitudes', [\App\Http\Controllers\ClienteSolicitudController::class, 'store'])
                ->name('clientes.solicitudes.store');
            Route::get('clientes/mis-solicitudes', [\App\Http\Controllers\ClienteSolicitudController::class, 'history'])
                ->name('clientes.solicitudes.history');
        });
});

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::get('logout', [AuthenticatedSessionController::class, 'destroyViaGet'])
    ->name('logout.get');

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

<?php

use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\PreregistroController;
use App\Http\Controllers\RecojoController;
use Illuminate\Support\Facades\Route;

Route::post('/public/paquetes-contrato', [RecojoController::class, 'storePublic'])
    ->name('api.public.paquetes-contrato.store');

Route::get('/public/tracking/eventos', [BusquedaController::class, 'consultarEventosTrackingPublico'])
    ->name('api.public.tracking.eventos');
Route::post('/public/preregistros', [PreregistroController::class, 'publicStoreApi'])
    ->name('api.public.preregistros.store');



Route::post('/subscribe', [BusquedaController::class, 'subscribe']);
Route::post('/unsubscribe', [BusquedaController::class, 'unsubscribe']);

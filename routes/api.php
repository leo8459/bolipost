<?php

use App\Http\Controllers\RecojoController;
use Illuminate\Support\Facades\Route;

Route::post('/public/paquetes-contrato', [RecojoController::class, 'storePublic'])
    ->name('api.public.paquetes-contrato.store');

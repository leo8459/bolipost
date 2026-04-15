<?php

use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\PreregistroController;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\AlertReadApiController;
use App\Http\Controllers\Api\FuelLogApiController;
use App\Http\Controllers\Api\FuelScrapeApiController;
use App\Http\Controllers\Api\MaintenanceRequestApiController;
use App\Http\Controllers\Api\MobileCrudApiController;
use App\Http\Controllers\Api\MobileDbSnapshotController;
use App\Http\Controllers\Api\MobileSnapshotController;
use App\Http\Controllers\Api\MobileUtilityController;
use App\Http\Controllers\Api\QrDecoderApiController;
use App\Http\Controllers\Api\VehicleLogApiController;
use App\Http\Controllers\RecojoController;
use Illuminate\Support\Facades\Route;

Route::post('/public/paquetes-contrato', [RecojoController::class, 'storePublic'])
    ->name('api.public.paquetes-contrato.store');

Route::get('/public/tracking/eventos', [BusquedaController::class, 'consultarEventosTrackingPublico'])
    ->name('api.public.tracking.eventos');
Route::get('/public/tracking/captcha', [BusquedaController::class, 'captchaTrackingPublico'])
    ->middleware('throttle:30,1')
    ->name('api.public.tracking.captcha');
Route::post('/public/tracking/access', [BusquedaController::class, 'autorizarTrackingPublico'])
    ->middleware('throttle:30,1')
    ->name('api.public.tracking.access');
Route::post('/public/preregistros', [PreregistroController::class, 'publicStoreApi'])
    ->name('api.public.preregistros.store');

Route::post('/subscribe', [BusquedaController::class, 'subscribe']);
Route::post('/unsubscribe', [BusquedaController::class, 'unsubscribe']);
Route::post('/fuel-logs/scrape-from-qr', [FuelScrapeApiController::class, 'scrapeFromQr'])
    ->middleware('throttle:30,1');

Route::middleware('web')->group(function () {
    Route::post('/mobile/login', [AuthTokenController::class, 'login']);
    Route::post('/maintenance-requests', [MaintenanceRequestApiController::class, 'store']);
    Route::get('/maintenance-requests', [MaintenanceRequestApiController::class, 'index']);
    Route::post('/fuel-logs', [FuelLogApiController::class, 'store']);
    Route::post('/qr/decode-from-image', [QrDecoderApiController::class, 'decodeFromImage']);
    Route::put('/siat/consulta-factura', [MobileUtilityController::class, 'siatConsultaFactura']);

    Route::middleware(['auth:web', 'single.mobile.session'])->group(function () {
        Route::get('/mobile/me', [AuthTokenController::class, 'me']);
        Route::get('/mobile/bootstrap', [AuthTokenController::class, 'bootstrap']);
        Route::post('/mobile/logout', [AuthTokenController::class, 'logout']);
        Route::post('/mobile/snapshot', [MobileSnapshotController::class, 'store']);
        Route::patch('/alerts/{alert}/read', [AlertReadApiController::class, 'markRead']);

        Route::get('/fuel-logs', [FuelLogApiController::class, 'index']);
        Route::get('/fuel-logs/{fuelLog}', [FuelLogApiController::class, 'show']);
        Route::get('/fuel-logs/by-vehicle/{vehicle}', [FuelLogApiController::class, 'byVehicle']);
        Route::get('/vehicle-logs', [VehicleLogApiController::class, 'index']);
        Route::post('/vehicle-logs', [VehicleLogApiController::class, 'store']);
        Route::post('/vehicle-logs/point-to-point', [VehicleLogApiController::class, 'pointToPoint']);
        Route::post('/vehicle-logs/stage-event', [VehicleLogApiController::class, 'storeStageEvent']);
        Route::post('/vehicle-logs/reassignment/qr', [VehicleLogApiController::class, 'createReassignmentQr']);
        Route::post('/vehicle-logs/reassignment/accept', [VehicleLogApiController::class, 'acceptReassignment']);
        Route::get('/vehicle-logs/{vehicleLog}', [VehicleLogApiController::class, 'show']);

        Route::post('/emergency-alerts', [MobileUtilityController::class, 'emergencyAlert']);
        Route::get('/activity-logs', [MobileUtilityController::class, 'activityIndex']);
        Route::post('/activity-logs', [MobileUtilityController::class, 'activityStore']);
        Route::post('/mobile/location/heartbeat', [MobileUtilityController::class, 'locationHeartbeat']);
        Route::post('/mobile/operational-incident', [MobileUtilityController::class, 'reportOperationalIncident']);
        Route::post('/mobile/bitacora/load', [MobileUtilityController::class, 'bitacoraLoad']);
        Route::get('/mobile/bitacora/session-health', [MobileUtilityController::class, 'sessionHealth']);
        Route::post('/mobile/bitacora/investigation-ticket/confirm', [MobileUtilityController::class, 'confirmInvestigationTicket']);
        Route::post('/mobile/db-snapshot/chunk', [MobileDbSnapshotController::class, 'chunk']);
        Route::post('/mobile/db-snapshot/finish', [MobileDbSnapshotController::class, 'finish']);

        Route::get('/mobile/resources', [MobileCrudApiController::class, 'resources']);
        Route::get('/drivers', [MobileCrudApiController::class, 'index'])->defaults('resource', 'drivers');
        Route::get('/mobile/{resource}', [MobileCrudApiController::class, 'index']);
        Route::post('/mobile/{resource}', [MobileCrudApiController::class, 'store']);
        Route::get('/mobile/{resource}/{id}', [MobileCrudApiController::class, 'show'])
            ->whereNumber('id');
        Route::put('/mobile/{resource}/{id}', [MobileCrudApiController::class, 'update'])
            ->whereNumber('id');
        Route::patch('/mobile/{resource}/{id}', [MobileCrudApiController::class, 'update'])
            ->whereNumber('id');
        Route::delete('/mobile/{resource}/{id}', [MobileCrudApiController::class, 'destroy'])
            ->whereNumber('id');
    });
});

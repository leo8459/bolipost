<?php

use App\Http\Controllers\Api\AlertReadApiController;
use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\ChasquiAuthApiController;
use App\Http\Controllers\Api\ClienteAuthApiController;
use App\Http\Controllers\Api\ClienteSolicitudApiController;
use App\Http\Controllers\Api\DireccionDestinoApiController;
use App\Http\Controllers\Api\EventosSiopApiController;
use App\Http\Controllers\Api\ExternalClienteSolicitudApiController;
use App\Http\Controllers\Api\FuelLogApiController;
use App\Http\Controllers\Api\FuelScrapeApiController;
use App\Http\Controllers\Api\MaintenanceRequestApiController;
use App\Http\Controllers\Api\MobileCrudApiController;
use App\Http\Controllers\Api\MobileDbSnapshotController;
use App\Http\Controllers\Api\MobileSnapshotController;
use App\Http\Controllers\Api\MobileUtilityController;
use App\Http\Controllers\Api\PaqueteContactoApiController;
use App\Http\Controllers\Api\QrDecoderApiController;
use App\Http\Controllers\Api\SiopAuthApiController;
use App\Http\Controllers\Api\VehicleLogApiController;
use App\Http\Controllers\AppConfigController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\CarterosController;
use App\Http\Controllers\PreregistroController;
use App\Http\Controllers\RecojoController;
use App\Support\ChasquiCartero;
use Illuminate\Support\Facades\Route;

Route::prefix('clientes')->group(function () {
    Route::post('/register', [ClienteAuthApiController::class, 'register'])
        ->middleware('throttle:5,1')
        ->name('api.clientes.register');
    Route::post('/login', [ClienteAuthApiController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('api.clientes.login');
    Route::post('/google-login', [ClienteAuthApiController::class, 'googleLogin'])
        ->middleware('throttle:10,1')
        ->name('api.clientes.google-login');

    Route::middleware(['auth:sanctum', 'abilities:cliente'])->group(function () {
        Route::get('/me', [ClienteAuthApiController::class, 'me'])->name('api.clientes.me');
        Route::post('/logout', [ClienteAuthApiController::class, 'logout'])->name('api.clientes.logout');
        Route::get('/solicitudes', [ClienteSolicitudApiController::class, 'index'])
            ->name('api.clientes.solicitudes.index');
        Route::post('/solicitudes', [ClienteSolicitudApiController::class, 'store'])
            ->middleware('throttle:30,1')
            ->name('api.clientes.solicitudes.store');
    });
});

Route::post('/public/paquetes-contrato', [RecojoController::class, 'storePublic'])
    ->name('api.public.paquetes-contrato.store');

Route::get('/public/tracking/eventos', [BusquedaController::class, 'consultarEventosTrackingPublico'])
    ->name('api.public.tracking.eventos');
Route::get('/public/app-version', [AppConfigController::class, 'publicVersion'])
    ->name('api.public.app-version');
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
Route::get('/siop/eventos', [EventosSiopApiController::class, 'index'])
    ->middleware(['siop.api.token', 'throttle:20,1'])
    ->name('api.siop.eventos.index');

Route::prefix('siop')->middleware(['auth:sanctum', 'abilities:siop'])->group(function () {
    Route::get('/me', [SiopAuthApiController::class, 'me'])->name('api.siop.me');
    Route::post('/logout', [SiopAuthApiController::class, 'logout'])->name('api.siop.logout');
});

Route::middleware(['force.json', 'external.api.jwt', 'throttle:120,1'])->group(function () {
    Route::post('/integraciones/siop/login', [SiopAuthApiController::class, 'login'])
        ->middleware(['external.api.ability:siop:login', 'throttle:10,1'])
        ->name('api.integraciones.siop.login');
    Route::post('/integraciones/chasqui/login', [ChasquiAuthApiController::class, 'login'])
        ->middleware(['external.api.ability:chasqui:login', 'throttle:10,1'])
        ->name('api.integraciones.chasqui.login');
    Route::get('/integraciones/solicitudes-clientes', [ExternalClienteSolicitudApiController::class, 'globalIndex'])
        ->middleware('external.api.ability:solicitudes-clientes:read')
        ->name('api.integraciones.solicitudes-clientes.index');
    Route::post('/integraciones/solicitudes-clientes', [ExternalClienteSolicitudApiController::class, 'globalStore'])
        ->middleware('external.api.ability:solicitudes-clientes:create')
        ->name('api.integraciones.solicitudes-clientes.store');
    Route::post('/integraciones/clientes', [ClienteAuthApiController::class, 'register'])
        ->middleware('external.api.ability:clientes:create')
        ->name('api.integraciones.clientes.store');
    Route::post('/integraciones/clientes/google-login', [ClienteAuthApiController::class, 'googleLogin'])
        ->middleware('external.api.ability:clientes:google-login')
        ->name('api.integraciones.clientes.google-login');
    Route::post('/integraciones/clientes/login', [ClienteAuthApiController::class, 'login'])
        ->middleware('external.api.ability:clientes:login')
        ->name('api.integraciones.clientes.login');
    Route::get('/integraciones/clientes/{cliente}/solicitudes', [ExternalClienteSolicitudApiController::class, 'index'])
        ->middleware('external.api.ability:clientes:solicitudes:read')
        ->whereNumber('cliente')
        ->name('api.integraciones.clientes.solicitudes.index');
    Route::post('/integraciones/clientes/{cliente}/solicitudes', [ExternalClienteSolicitudApiController::class, 'store'])
        ->middleware('external.api.ability:clientes:solicitudes:create')
        ->whereNumber('cliente')
        ->name('api.integraciones.clientes.solicitudes.store');

    Route::get('/paquetes-contactos', [PaqueteContactoApiController::class, 'index'])
        ->name('api.paquetes-contactos.index');
    Route::get('/paquetes-contactos/{tipo}', [PaqueteContactoApiController::class, 'index'])
        ->where('tipo', 'certi|contrato|ems|ordinario|solicitud')
        ->name('api.paquetes-contactos.tipo');
    Route::get('/paquetes-eventos', [PaqueteContactoApiController::class, 'index'])
        ->middleware('external.api.ability:paquetes-eventos:read')
        ->name('api.paquetes-eventos.index');

    Route::get('/direcciones-destino', [DireccionDestinoApiController::class, 'index'])
        ->middleware('external.api.ability:direcciones-destino:read')
        ->name('api.direcciones-destino.index');
    Route::get('/direcciones-destino/todos', [DireccionDestinoApiController::class, 'todos'])
        ->middleware('external.api.ability:direcciones-destino:read')
        ->name('api.direcciones-destino.todos');
    Route::get('/direcciones-destino/todo', [DireccionDestinoApiController::class, 'todo'])
        ->middleware('external.api.ability:direcciones-destino:read')
        ->name('api.direcciones-destino.todo');
    Route::get('/direcciones-destino/cantidad', [DireccionDestinoApiController::class, 'cantidad'])
        ->middleware('external.api.ability:direcciones-destino:read')
        ->name('api.direcciones-destino.cantidad');
    Route::get('/direcciones-destino/{tipo}/{id}', [DireccionDestinoApiController::class, 'show'])
        ->middleware('external.api.ability:direcciones-destino:read')
        ->whereNumber('id')
        ->name('api.direcciones-destino.show');
    Route::match(['put', 'patch', 'post'], '/direcciones-destino/{tipo}/{id}', [DireccionDestinoApiController::class, 'update'])
        ->middleware('external.api.ability:direcciones-destino:update')
        ->whereNumber('id')
        ->name('api.direcciones-destino.update');
});

Route::prefix('chasqui')->middleware([
    'force.json',
    'external.api.jwt',
    'auth:sanctum',
    ChasquiCartero::abilityMiddleware(),
    'chasqui.cartero',
    'throttle:120,1',
])->group(function () {
    Route::get('/paquetes-asignados', [CarterosController::class, 'chasquiAssignedData'])
        ->middleware('external.api.ability:chasqui:paquetes:read')
        ->name('api.chasqui.paquetes-asignados');
    Route::post('/paquetes/asignar', [CarterosController::class, 'assignChasqui'])
        ->middleware('external.api.ability:chasqui:paquetes:assign')
        ->name('api.chasqui.paquetes.asignar');
    Route::post('/paquetes/entregar', [CarterosController::class, 'deliverChasquiPackage'])
        ->middleware('external.api.ability:chasqui:paquetes:deliver')
        ->name('api.chasqui.paquetes.entregar');
    Route::get('/notificaciones/pendientes', [CarterosController::class, 'chasquiPendingNotification'])
        ->middleware('external.api.ability:chasqui:notificaciones:read')
        ->name('api.chasqui.notificaciones.pendientes');
});

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
        Route::post('/mobile/maintenance-requests', [MaintenanceRequestApiController::class, 'storeMobile']);
        Route::get('/mobile/maintenance-requests', [MaintenanceRequestApiController::class, 'indexMobile']);
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

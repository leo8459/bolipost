<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleHasPermissionController;
use App\Http\Controllers\PlantillaController;
use App\Http\Controllers\PaquetesEmsController;
use App\Http\Controllers\PaquetesEmsBoletaController;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\DestinoController;
use App\Http\Controllers\PesoController;
use App\Http\Controllers\OrigenController;
use App\Http\Controllers\TarifarioController;
use App\Http\Controllers\PaquetesCertiController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\VentanillaController;
use App\Http\Controllers\DespachoController;
use App\Http\Controllers\SacaController;
use App\Http\Controllers\CarterosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/rastreo-demo', function () {
    return view('tracking-demo');
})->name('tracking.demo');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::get('users/{id}/delete', [UserController::class, 'delete'])->name('users.delete');
    Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::put('utest/{id}/restoring', [UserController::class, 'restoring'])->name('users.restoring');
    Route::get('users/excel', [UserController::class, 'excel'])->name('users.excel');
    Route::get('users/pdf', [UserController::class, 'pdf'])->name('users.pdf');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

    //Roles
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/role/create', [RoleController::class, 'create'])->name('roles.create');
    // Route::get('/role/{role}', [RoleController::class, 'show'])->name('roles.show');
    Route::post('/role', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/role/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/role/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/role/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    //Permisos
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::get('/permission/create', [PermissionController::class, 'create'])->name('permissions.create');
    // Route::get('/permission/{permission}', [PermissionController::class, 'show'])->name('permissions.show');
    Route::post('/permission', [PermissionController::class, 'store'])->name('permissions.store');
    Route::get('/permission/{permission}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
    Route::put('/permission/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
    Route::delete('/permission/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

    //Accesos
    Route::get('/role-has-permissions', [RoleHasPermissionController::class, 'index'])->name('role-has-permissions.index');
    Route::get('/role-has-permission/create', [RoleHasPermissionController::class, 'create'])->name('role-has-permissions.create');
    // Route::get('/role-has-permission/{roleHasPermission}', [RoleHasPermissionController::class, 'show'])->name('role-has-permissions.show');
    Route::post('/role-has-permission', [RoleHasPermissionController::class, 'store'])->name('role-has-permissions.store');
    Route::get('/role-has-permission/{roleHasPermission}/edit', [RoleHasPermissionController::class, 'edit'])->name('role-has-permissions.edit');
    Route::put('/role-has-permission/{roleHasPermission', [RoleHasPermissionController::class, 'update'])->name('role-has-permissions.update');
    Route::delete('/role-has-permission/{roleHasPermission}', [RoleHasPermissionController::class, 'destroy'])->name('role-has-permissions.destroy');


    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    //gets
    Route::get('/plantilla', [PlantillaController::class, 'getplantilla']);
    Route::get('/paquetes-ems', [PaquetesEmsController::class, 'index'])->name('paquetes-ems.index');
    Route::get('/paquetes-ems/almacen', [PaquetesEmsController::class, 'almacen'])->name('paquetes-ems.almacen');
    Route::get('/paquetes-ems/recibir-regional', [PaquetesEmsController::class, 'recibirRegional'])->name('paquetes-ems.recibir-regional');
    Route::get('/paquetes-ems/{paquete}/boleta', [PaquetesEmsBoletaController::class, 'show'])->name('paquetes-ems.boleta');
    Route::get('/servicios', [ServicioController::class, 'index'])->name('servicios.index');
    Route::get('/destinos', [DestinoController::class, 'index'])->name('destinos.index');
    Route::get('/pesos', [PesoController::class, 'index'])->name('pesos.index');
    Route::get('/origenes', [OrigenController::class, 'index'])->name('origenes.index');
    Route::get('/tarifario', [TarifarioController::class, 'index'])->name('tarifario.index');
    Route::get('/paquetes-certificados/almacen', [PaquetesCertiController::class, 'almacen'])->name('paquetes-certificados.almacen');
    Route::get('/paquetes-certificados/inventario', [PaquetesCertiController::class, 'inventario'])->name('paquetes-certificados.inventario');
    Route::get('/paquetes-certificados/rezago', [PaquetesCertiController::class, 'rezago'])->name('paquetes-certificados.rezago');
    Route::get('/paquetes-certificados/todos', [PaquetesCertiController::class, 'todos'])->name('paquetes-certificados.todos');
    Route::get('/paquetes-certificados/baja-pdf', [PaquetesCertiController::class, 'bajaPdf'])->name('paquetes-certificados.baja-pdf');
    Route::get('/paquetes-certificados/rezago-pdf', [PaquetesCertiController::class, 'rezagoPdf'])->name('paquetes-certificados.rezago-pdf');
    Route::get('/estados', [EstadoController::class, 'index'])->name('estados.index');
    Route::get('/ventanillas', [VentanillaController::class, 'index'])->name('ventanillas.index');
    Route::get('/despachos/abiertos', [DespachoController::class, 'index'])->name('despachos.abiertos');
    Route::get('/despachos/expedicion', [DespachoController::class, 'expedicion'])->name('despachos.expedicion');
    Route::get('/despachos/{id}/expedicion-pdf', [DespachoController::class, 'expedicionPdf'])->name('despachos.expedicion.pdf');
    Route::get('/despachos/admitidos', [DespachoController::class, 'admitidos'])->name('despachos.admitidos');
    Route::get('/despachos/todos', [DespachoController::class, 'todos'])->name('despachos.todos');
    Route::get('/sacas', [SacaController::class, 'index'])->name('sacas.index');
    Route::get('/carteros/distribucion', [CarterosController::class, 'distribucion'])->name('carteros.distribucion');
    Route::get('/carteros/asignados', [CarterosController::class, 'asignados'])->name('carteros.asignados');
    Route::get('/carteros/cartero', [CarterosController::class, 'cartero'])->name('carteros.cartero');
    Route::get('/carteros/devolucion', [CarterosController::class, 'devolucion'])->name('carteros.devolucion');
    Route::get('/carteros/domicilio', [CarterosController::class, 'domicilio'])->name('carteros.domicilio');
    Route::get('/carteros/entrega', [CarterosController::class, 'entregaForm'])->name('carteros.entrega');
    Route::get('/api/carteros/distribucion', [CarterosController::class, 'distribucionData'])->name('api.carteros.distribucion');
    Route::get('/api/carteros/asignados', [CarterosController::class, 'asignadosData'])->name('api.carteros.asignados');
    Route::get('/api/carteros/cartero', [CarterosController::class, 'carteroData'])->name('api.carteros.cartero');
    Route::get('/api/carteros/provincia', [CarterosController::class, 'provinciaData'])->name('api.carteros.provincia');
    Route::get('/api/carteros/devolucion', [CarterosController::class, 'devolucionData'])->name('api.carteros.devolucion');
    Route::get('/api/carteros/domicilio', [CarterosController::class, 'domicilioData'])->name('api.carteros.domicilio');
    Route::get('/api/carteros/users', [CarterosController::class, 'users'])->name('api.carteros.users');
    Route::post('/api/carteros/asignar', [CarterosController::class, 'assign'])->name('api.carteros.asignar');
    Route::post('/api/carteros/registrar-guia', [CarterosController::class, 'registerGuide'])->name('api.carteros.registrar-guia');
    Route::post('/api/carteros/devolver-almacen', [CarterosController::class, 'returnToAlmacen'])->name('api.carteros.devolver-almacen');
    Route::post('/api/carteros/aceptar-paquetes', [CarterosController::class, 'acceptPackages'])->name('api.carteros.aceptar-paquetes');
    Route::post('/carteros/entrega', [CarterosController::class, 'deliverPackage'])->name('carteros.entrega.store');
    Route::post('/carteros/entrega/intento', [CarterosController::class, 'addAttempt'])->name('carteros.entrega.intento');
});

require __DIR__ . '/auth.php';







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
use App\Http\Controllers\TarifaContratoController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PaquetesCertiController;
use App\Http\Controllers\EstadoController;
use App\Http\Controllers\VentanillaController;
use App\Http\Controllers\DespachoController;
use App\Http\Controllers\SacaController;
use App\Http\Controllers\CarterosController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BusquedaController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\PaquetesOrdiController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\EventosAuditoriaController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\CodigoEmpresaController;
use App\Http\Controllers\AreaContratosController;
use App\Http\Controllers\IndicadorController;
use App\Http\Controllers\RecojoController;
use App\Http\Controllers\ZonaPaqueteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportesController;
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

Route::get('/', [BusquedaController::class, 'landing'])->name('welcome');

Route::redirect('/rastreo-demo', '/trackingbo', 301);
Route::get('/trackingbo', [BusquedaController::class, 'mostrarTracking'])->name('tracking.demo');

Route::match(['GET', 'POST'], '/api/busqueda/ems-eventos', [BusquedaController::class, 'consultarEventosTracking'])
    ->name('api.busqueda.ems-eventos');
Route::get('/api/busqueda/captcha', [BusquedaController::class, 'captchaTracking'])
    ->name('api.busqueda.captcha');

Route::get('/api/public/zona-paquete', [ZonaPaqueteController::class, 'buscar'])
    ->name('api.public.zona-paquete');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified', 'route.permission'])
    ->name('dashboard');
Route::get('/dashboard/export/excel', [DashboardController::class, 'exportExcel'])
    ->middleware(['auth', 'verified', 'route.permission'])
    ->name('dashboard.export.excel');
Route::get('/dashboard/export/pdf', [DashboardController::class, 'exportPdf'])
    ->middleware(['auth', 'verified', 'route.permission'])
    ->name('dashboard.export.pdf');
Route::get('/reportes', [ReportesController::class, 'index'])
    ->middleware(['auth', 'verified', 'route.permission'])
    ->name('reportes.index');
Route::get('/reportes/{scope}', [ReportesController::class, 'show'])
    ->middleware(['auth', 'verified', 'route.permission'])
    ->where('scope', 'general|contrato|ems|certi|ordi')
    ->name('reportes.scope');
Route::get('/reportes/{scope}/export/excel', [ReportesController::class, 'exportExcel'])
    ->middleware(['auth', 'verified', 'route.permission'])
    ->where('scope', 'general|contrato|ems|certi|ordi')
    ->name('reportes.export.excel');
Route::get('/reportes/{scope}/export/pdf', [ReportesController::class, 'exportPdf'])
    ->middleware(['auth', 'verified', 'route.permission'])
    ->where('scope', 'general|contrato|ems|certi|ordi')
    ->name('reportes.export.pdf');

Route::middleware(['auth', 'route.permission'])->group(function () {
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
    Route::put('/role-has-permission/{roleHasPermission}', [RoleHasPermissionController::class, 'update'])->name('role-has-permissions.update');
    Route::delete('/role-has-permission/{roleHasPermission}', [RoleHasPermissionController::class, 'destroy'])->name('role-has-permissions.destroy');


    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');


    //gets
    Route::get('/plantilla', [PlantillaController::class, 'getplantilla']);
    Route::get('/paquetes-ems', [PaquetesEmsController::class, 'index'])->name('paquetes-ems.index');
    Route::get('/paquetes-ems/create', [PaquetesEmsController::class, 'create'])->name('paquetes-ems.create');
    Route::get('/paquetes-ems/almacen', [PaquetesEmsController::class, 'almacen'])->name('paquetes-ems.almacen');
    Route::get('/paquetes-ems/almacen-admisiones', [PaquetesEmsController::class, 'almacenAdmisiones'])->name('paquetes-ems.almacen-admisiones');
    Route::get('/paquetes-ems/ventanilla', [PaquetesEmsController::class, 'ventanilla'])->name('paquetes-ems.ventanilla');
    Route::get('/paquetes-ems/en-transito', [PaquetesEmsController::class, 'enTransito'])->name('paquetes-ems.en-transito');
    Route::get('/paquetes-ems/recibir-regional', [PaquetesEmsController::class, 'recibirRegional'])->name('paquetes-ems.recibir-regional');
    Route::get('/paquetes-ems/entregados', [PaquetesEmsController::class, 'entregados'])->name('paquetes-ems.entregados');
    Route::get('/paquetes-ems/registro-rapido-contrato', [PaquetesEmsController::class, 'createRegistroRapidoContrato'])->name('paquetes-ems.contrato-rapido.create');
    Route::post('/paquetes-ems/registro-rapido-contrato', [PaquetesEmsController::class, 'storeRegistroRapidoContrato'])->name('paquetes-ems.contrato-rapido.store');
    Route::get('/paquetes-ems/{paquete}/boleta', [PaquetesEmsBoletaController::class, 'show'])->name('paquetes-ems.boleta');
    Route::get('/servicios', [ServicioController::class, 'index'])->name('servicios.index');
    Route::get('/destinos', [DestinoController::class, 'index'])->name('destinos.index');
    Route::get('/pesos', [PesoController::class, 'index'])->name('pesos.index');
    Route::get('/origenes', [OrigenController::class, 'index'])->name('origenes.index');
    Route::get('/tarifario', [TarifarioController::class, 'index'])->name('tarifario.index');
    Route::get('/tarifa-contrato', [TarifaContratoController::class, 'index'])->name('tarifa-contrato.index');
    Route::get('/tarifa-contrato/create', [TarifaContratoController::class, 'create'])->name('tarifa-contrato.create');
    Route::get('/tarifa-contrato/import', [TarifaContratoController::class, 'importForm'])->name('tarifa-contrato.import-form');
    Route::post('/tarifa-contrato/import', [TarifaContratoController::class, 'import'])->name('tarifa-contrato.import');
    Route::get('/tarifa-contrato/plantilla-excel', [TarifaContratoController::class, 'downloadTemplateExcel'])->name('tarifa-contrato.template-excel');
    Route::get('/importar/paquets', [ImportController::class, 'paquets'])->name('importar.paquets');
    Route::post('/importar/paquets', [ImportController::class, 'importPaquets'])->name('importar.paquets.store');
    Route::get('/importar/paquets/plantilla-excel', [ImportController::class, 'downloadPaquetsTemplateExcel'])->name('importar.paquets.template-excel');
    Route::post('/tarifa-contrato', [TarifaContratoController::class, 'store'])->name('tarifa-contrato.store');
    Route::get('/tarifa-contrato/{tarifaContrato}/edit', [TarifaContratoController::class, 'edit'])->name('tarifa-contrato.edit');
    Route::put('/tarifa-contrato/{tarifaContrato}', [TarifaContratoController::class, 'update'])->name('tarifa-contrato.update');
    Route::delete('/tarifa-contrato/{tarifaContrato}', [TarifaContratoController::class, 'destroy'])->name('tarifa-contrato.destroy');
    Route::get('/paquetes-certificados/almacen', [PaquetesCertiController::class, 'almacen'])->name('paquetes-certificados.almacen');
    Route::get('/paquetes-certificados/inventario', [PaquetesCertiController::class, 'inventario'])->name('paquetes-certificados.inventario');
    Route::get('/paquetes-certificados/rezago', [PaquetesCertiController::class, 'rezago'])->name('paquetes-certificados.rezago');
    Route::get('/paquetes-certificados/todos', [PaquetesCertiController::class, 'todos'])->name('paquetes-certificados.todos');
    Route::get('/paquetes-certificados/baja-pdf', [PaquetesCertiController::class, 'bajaPdf'])->name('paquetes-certificados.baja-pdf');
    Route::get('/paquetes-certificados/rezago-pdf', [PaquetesCertiController::class, 'rezagoPdf'])->name('paquetes-certificados.rezago-pdf');
    Route::get('/paquetes-ordinarios', [PaquetesOrdiController::class, 'index'])->name('paquetes-ordinarios.index');
    Route::get('/paquetes-ordinarios/despacho', [PaquetesOrdiController::class, 'despacho'])->name('paquetes-ordinarios.despacho');
    Route::get('/paquetes-ordinarios/almacen', [PaquetesOrdiController::class, 'almacen'])->name('paquetes-ordinarios.almacen');
    Route::get('/paquetes-ordinarios/entregado', [PaquetesOrdiController::class, 'entregado'])->name('paquetes-ordinarios.entregado');
    Route::get('/paquetes-ordinarios/rezago', [PaquetesOrdiController::class, 'rezago'])->name('paquetes-ordinarios.rezago');
    Route::get('/estados', [EstadoController::class, 'index'])->name('estados.index');
    Route::get('/eventos', [EventoController::class, 'index'])->name('eventos.index');
    Route::get('/eventos-ems', [EventoController::class, 'emsIndex'])->name('eventos-ems.index');
    Route::get('/eventos-certi', [EventoController::class, 'certiIndex'])->name('eventos-certi.index');
    Route::get('/eventos-ordi', [EventoController::class, 'ordiIndex'])->name('eventos-ordi.index');
    Route::get('/eventos-despacho', [EventoController::class, 'despachoIndex'])->name('eventos-despacho.index');
    Route::get('/eventos-contrato', [EventoController::class, 'contratoIndex'])->name('eventos-contrato.index');
    Route::get('/auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');
    Route::get('/eventos-auditoria', [EventosAuditoriaController::class, 'index'])->name('eventos-auditoria.index');
    Route::get('/empresas', [EmpresaController::class, 'index'])->name('empresas.index');
    Route::get('/codigo-empresa', [CodigoEmpresaController::class, 'index'])->name('codigo-empresa.index');
    Route::get('/paquetes-contrato', [RecojoController::class, 'index'])->name('paquetes-contrato.index');
    Route::get('/paquetes-contrato/recoger-envios', [RecojoController::class, 'recogerEnvios'])->name('paquetes-contrato.recoger-envios');
    Route::get('/paquetes-contrato/almacen', [RecojoController::class, 'almacen'])->name('paquetes-contrato.almacen');
    Route::get('/paquetes-contrato/entregados', [RecojoController::class, 'entregados'])->name('paquetes-contrato.entregados');
    Route::get('/paquetes-contrato/cartero', [RecojoController::class, 'cartero'])->name('paquetes-contrato.cartero');
    Route::get('/paquetes-contrato/create', [RecojoController::class, 'create'])->name('paquetes-contrato.create');
    Route::get('/paquetes-contrato/create-con-tarifa', [RecojoController::class, 'createConTarifa'])->name('paquetes-contrato.create-con-tarifa');
    Route::post('/paquetes-contrato', [RecojoController::class, 'store'])->name('paquetes-contrato.store');
    Route::post('/paquetes-contrato/con-tarifa', [RecojoController::class, 'storeConTarifa'])->name('paquetes-contrato.store-con-tarifa');
    Route::get('/paquetes-contrato/reporte-hoy', [RecojoController::class, 'reporteHoy'])->name('paquetes-contrato.reporte-hoy');
    Route::get('/paquetes-contrato/{contrato}/reporte', [RecojoController::class, 'reporte'])->name('paquetes-contrato.reporte');
    Route::get('/area-contratos/todos', [AreaContratosController::class, 'todos'])->name('area-contratos.todos');
    Route::get('/area-contratos/entregados', [AreaContratosController::class, 'entregados'])->name('area-contratos.entregados');
    Route::get('/indicadores/contratos/entregados', [IndicadorController::class, 'contratosEntregados'])->name('indicadores.contratos.entregados');
    Route::get('/indicadores/contratos/inventario', [IndicadorController::class, 'contratosInventario'])->name('indicadores.contratos.inventario');
    Route::get('/indicadores/ems/entregados', [IndicadorController::class, 'emsEntregados'])->name('indicadores.ems.entregados');
    Route::get('/indicadores/ems/inventario', [IndicadorController::class, 'emsInventario'])->name('indicadores.ems.inventario');
    Route::get('/indicadores/certificados/entregados', [IndicadorController::class, 'certificadosEntregados'])->name('indicadores.certificados.entregados');
    Route::get('/indicadores/certificados/inventario', [IndicadorController::class, 'certificadosInventario'])->name('indicadores.certificados.inventario');
    Route::get('/indicadores/ordinarios/entregados', [IndicadorController::class, 'ordinariosEntregados'])->name('indicadores.ordinarios.entregados');
    Route::get('/indicadores/ordinarios/inventario', [IndicadorController::class, 'ordinariosInventario'])->name('indicadores.ordinarios.inventario');
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

    Route::get('/respaldos', [BackupController::class, 'index'])->name('backups.index');
    Route::post('/respaldos/base-datos', [BackupController::class, 'backupDatabase'])->name('backups.database');
    Route::post('/respaldos/sistema', [BackupController::class, 'backupSystem'])->name('backups.system');
    Route::get('/respaldos/descargar/{file}', [BackupController::class, 'download'])->name('backups.download');
});

require __DIR__ . '/auth.php';







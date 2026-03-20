<?php

namespace App\Support;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;
use ReflectionClass;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AclPermissionRegistry
{
    /**
     * Route action labels.
     *
     * @var array<string, string>
     */
    private const ACTION_LABELS = [
        'index' => 'Acceso:Clasificacion',
        'show' => 'Ver detalle',
        'create' => 'Abrir formulario',
        'store' => 'Guardar nuevo',
        'edit' => 'Abrir edicion',
        'update' => 'Actualizar',
        'destroy' => 'Eliminar',
        'delete' => 'Eliminar',
        'restore' => 'Restaurar',
        'restoring' => 'Restaurar',
        'import' => 'Importar',
        'export' => 'Exportar',
        'excel' => 'Exportar Excel',
        'pdf' => 'Exportar PDF',
        'download' => 'Descargar',
        'search' => 'Buscar',
        'sync' => 'Sincronizar permisos',
        'entrega' => 'Registrar entrega',
        'boleta' => 'Ver boleta',
        'access' => 'Acceder',
    ];

    /**
     * Functional permission labels (button/action level).
     *
     * @var array<string, string>
     */
    private const FEATURE_ACTION_LABELS = [
        'view' => 'Botones de visualizacion',
        'create' => 'Botones de registro',
        'save' => 'Botones de guardado',
        'edit' => 'Botones de edicion',
        'duplicate' => 'Botones de duplicado',
        'reencaminar' => 'Botones de reencaminado',
        'delete' => 'Botones de eliminacion',
        'dropoff' => 'Botones de baja',
        'rezago' => 'Botones de rezago',
        'restore' => 'Botones de restauracion',
        'export' => 'Botones de exportacion/descarga',
        'import' => 'Botones de importacion',
        'assign' => 'Botones de movimiento/recepcion',
        'confirm' => 'Botones de confirmacion',
        'deliver' => 'Botones de entrega',
        'attempt' => 'Botones de intento',
        'guide' => 'Botones de guia/provincia',
        'province' => 'Botones de vista provincia',
        'report' => 'Botones de reporte',
        'print' => 'Botones de impresion/boleta',
        'manage' => 'Botones de administracion general',
    ];

    /**
     * Route suffix to feature action map.
     *
     * @var array<string, string>
     */
    private const FEATURE_ACTION_FROM_ROUTE = [
        'index' => 'view',
        'show' => 'view',
        'search' => 'view',
        'create' => 'create',
        'store' => 'create',
        'edit' => 'edit',
        'update' => 'edit',
        'destroy' => 'delete',
        'delete' => 'delete',
        'restore' => 'restore',
        'restoring' => 'restore',
        'export' => 'export',
        'excel' => 'export',
        'pdf' => 'export',
        'download' => 'export',
        'import' => 'import',
        'asignar' => 'assign',
        'assign' => 'assign',
        'confirmar' => 'confirm',
        'confirm' => 'confirm',
        'entrega' => 'deliver',
        'entregar' => 'deliver',
        'reporte' => 'report',
        'report' => 'report',
        'boleta' => 'print',
        'imprimir' => 'print',
        'print' => 'print',
    ];

    /**
     * Livewire component -> ACL module map.
     *
     * @var array<string, string>
     */
    private const LIVEWIRE_COMPONENT_MODULES = [
        'Auditoria' => 'auditoria',
        'CodigoEmpresa' => 'codigo-empresa',
        'Despacho' => 'despachos',
        'DespachoAdmitido' => 'despachos',
        'DespachoExpedicion' => 'despachos',
        'DespachoTodos' => 'despachos',
        'Destino' => 'destinos',
        'Empresa' => 'empresas',
        'Estado' => 'estados',
        'Evento' => 'eventos',
        'EventosAuditoria' => 'eventos-auditoria',
        'EventosTabla' => 'eventos',
        'Origen' => 'origenes',
        'PaqueteCerti' => 'paquetes-certificados',
        'PaquetesEms' => 'paquetes-ems',
        'PaquetesOrdi' => 'paquetes-ordinarios',
        'Peso' => 'pesos',
        'Plantilla' => 'paquetes-ems',
        'Recojo' => 'paquetes-contrato',
        'RecojoCartero' => 'paquetes-contrato',
        'RecojoRecogerEnvios' => 'paquetes-contrato',
        'Saca' => 'sacas',
        'Servicio' => 'servicios',
        'Tarifario' => 'tarifario',
        'Users' => 'users',
        'Ventanilla' => 'ventanillas',
    ];

    /**
     * Dynamic module map for EventosTabla by tipo.
     *
     * @var array<string, string>
     */
    private const EVENTOS_TABLA_TIPO_MODULES = [
        'ems' => 'eventos-ems',
        'certi' => 'eventos-certi',
        'ordi' => 'eventos-ordi',
        'despacho' => 'eventos-despacho',
        'contrato' => 'eventos-contrato',
    ];

    /**
     * Dynamic route map for EventosTabla by tipo.
     *
     * @var array<string, string>
     */
    private const EVENTOS_TABLA_ROUTE_MODULES = [
        'ems' => 'eventos-ems.index',
        'certi' => 'eventos-certi.index',
        'ordi' => 'eventos-ordi.index',
        'despacho' => 'eventos-despacho.index',
        'contrato' => 'eventos-contrato.index',
    ];

    /**
     * Method overrides for Livewire action inference.
     *
     * @var array<string, string>
     */
    private const LIVEWIRE_METHOD_ACTION_OVERRIDES = [
        'admitirdespachos' => 'confirm',
        'altaaalmacen' => 'restore',
        'bajamasiva' => 'dropoff',
        'bajapaquetes' => 'dropoff',
        'cerrardespacho' => 'confirm',
        'confirmarmandargeneradoshoy' => 'confirm',
        'confirmarrecibir' => 'assign',
        'devolveraadmisiones' => 'restore',
        'devolveraclasificacion' => 'restore',
        'devolverrezagoaalmacen' => 'restore',
        'despacharseleccionados' => 'assign',
        'ejecutaroperacion' => 'manage',
        'enqueuereceptaculo' => 'assign',
        'guardarpesocontratoporcodigo' => 'edit',
        'intervenirdespacho' => 'confirm',
        'mandarseleccionadosalmacen' => 'assign',
        'mandarseleccionadoscontratosregional' => 'assign',
        'mandarseleccionadosgeneradoshoy' => 'assign',
        'mandarseleccionadosregional' => 'assign',
        'mandarseleccionadossinfiltrofecha' => 'assign',
        'mandarseleccionadosventanillaems' => 'assign',
        'marcarinventario' => 'edit',
        'marcarventanilla' => 'assign',
        'openadmitirmodal' => 'confirm',
        'opencontratopesomodal' => 'edit',
        'opencontratoregistrarmodal' => 'create',
        'openentregaventanillamodal' => 'deliver',
        'openintervencionmodal' => 'confirm',
        'openreencaminarmodal' => 'reencaminar',
        'openrecibirmodal' => 'assign',
        'openrecibirregionalmodal' => 'assign',
        'openregionalcontratomodal' => 'assign',
        'openregionalmodal' => 'assign',
        'openpasswordmodal' => 'edit',
        'previewadmitir' => 'confirm',
        'reaperturasaca' => 'restore',
        'recibirseleccionadosregional' => 'assign',
        'registrarcontratorapido' => 'create',
        'registrarintervencion' => 'confirm',
        'reimprimircn33' => 'print',
        'reimprimirformularioentrega' => 'print',
        'reimprimirmanifiesto' => 'print',
        'removebatchrow' => 'assign',
        'removescanned' => 'assign',
        'rezagomasivo' => 'rezago',
        'rezagopaquetes' => 'rezago',
        'saveconfirmed' => 'confirm',
        'savereencaminar' => 'reencaminar',
        'scanandsearch' => 'assign',
        'togglecn33reprint' => 'print',
        'updatepassword' => 'edit',
        'volverapertura' => 'restore',
    ];

    /**
     * Window route names by component mode.
     *
     * @var array<string, array<string, string>>
     */
    private const WINDOW_ROUTE_MODULES = [
        'Despacho' => [
            'default' => 'despachos.abiertos',
        ],
        'DespachoExpedicion' => [
            'default' => 'despachos.expedicion',
        ],
        'DespachoAdmitido' => [
            'default' => 'despachos.admitidos',
        ],
        'DespachoTodos' => [
            'default' => 'despachos.todos',
        ],
        'Recojo' => [
            'general' => 'paquetes-contrato.index',
            'almacen' => 'paquetes-contrato.almacen',
        ],
        'RecojoRecogerEnvios' => [
            'default' => 'paquetes-contrato.recoger-envios',
        ],
        'RecojoCartero' => [
            'default' => 'paquetes-contrato.cartero',
        ],
        'PaquetesEms' => [
            'admision' => 'paquetes-ems.index',
            'create_ems' => 'paquetes-ems.create',
            'almacen_ems' => 'paquetes-ems.almacen',
            'ventanilla_ems' => 'paquetes-ems.ventanilla',
            'transito_ems' => 'paquetes-ems.recibir-regional',
            'en_transito_ems' => 'paquetes-ems.en-transito',
        ],
        'PaquetesOrdi' => [
            'clasificacion' => 'paquetes-ordinarios.index',
            'despacho' => 'paquetes-ordinarios.despacho',
            'almacen' => 'paquetes-ordinarios.almacen',
            'entregado' => 'paquetes-ordinarios.entregado',
            'rezago' => 'paquetes-ordinarios.rezago',
        ],
        'PaqueteCerti' => [
            'almacen' => 'paquetes-certificados.almacen',
            'inventario' => 'paquetes-certificados.inventario',
            'rezago' => 'paquetes-certificados.rezago',
            'todos' => 'paquetes-certificados.todos',
        ],
        'Saca' => [
            'default' => 'sacas.index',
        ],
    ];

    /**
     * Supported feature actions by window route.
     *
     * @var array<string, array<int, string>>
     */
    private const WINDOW_FEATURE_ALLOWLIST = [
        'despachos.abiertos' => ['create', 'edit', 'delete', 'assign', 'confirm', 'restore'],
        'despachos.expedicion' => ['print', 'confirm', 'restore', 'edit'],
        'despachos.admitidos' => ['assign', 'confirm'],
        'despachos.todos' => [],
        'eventos-ems.index' => ['create', 'edit', 'delete'],
        'eventos-certi.index' => ['create', 'edit', 'delete'],
        'eventos-ordi.index' => ['create', 'edit', 'delete'],
        'eventos-despacho.index' => ['create', 'edit', 'delete'],
        'eventos-contrato.index' => ['create', 'edit', 'delete'],
        'paquetes-contrato.index' => ['edit', 'delete', 'print', 'report'],
        'paquetes-contrato.almacen' => ['edit', 'delete', 'print', 'report'],
        'paquetes-contrato.recoger-envios' => ['assign', 'print'],
        'paquetes-contrato.cartero' => ['print'],
        'paquetes-contrato.create' => ['create', 'manage'],
        'paquetes-contrato.create-con-tarifa' => ['create'],
        'paquetes-contrato.entregados' => ['print', 'export'],
        'paquetes-ems.index' => ['create', 'edit', 'delete', 'print', 'assign'],
        'paquetes-ems.create' => ['create'],
        'paquetes-ems.almacen' => ['create', 'edit', 'print', 'restore', 'registercontract', 'weighcontract', 'sendventanilla', 'sendregional', 'reprintcn33'],
        'paquetes-ems.contrato-rapido.create' => ['create', 'save', 'delete'],
        'paquetes-ems.ventanilla' => ['deliver', 'edit', 'print'],
        'paquetes-ems.recibir-regional' => ['assign', 'edit', 'print'],
        'paquetes-ems.en-transito' => ['edit', 'print'],
        'paquetes-ordinarios.index' => ['create', 'edit', 'delete', 'assign'],
        'paquetes-ordinarios.almacen' => ['edit', 'reencaminar', 'assign', 'dropoff', 'rezago'],
        'paquetes-ordinarios.despacho' => ['edit', 'restore', 'print'],
        'paquetes-ordinarios.entregado' => ['edit', 'restore', 'print'],
        'paquetes-ordinarios.rezago' => ['edit', 'restore'],
        'paquetes-certificados.almacen' => ['create', 'edit', 'reencaminar', 'delete', 'dropoff', 'rezago'],
        'paquetes-certificados.inventario' => ['edit', 'delete', 'assign', 'export'],
        'paquetes-certificados.rezago' => ['edit', 'delete', 'assign'],
        'paquetes-certificados.todos' => ['edit', 'delete'],
        'servicios.index' => ['create', 'edit', 'delete'],
        'sucursales.index' => ['create', 'edit', 'delete'],
        'sacas.index' => ['create', 'edit', 'delete', 'assign', 'confirm'],
    ];

    /**
     * Explicit Livewire method targets for window-specific actions.
     *
     * @var array<string, array<string, array<int, array{module:string,action:string}>>>
     */
    private const LIVEWIRE_METHOD_TARGET_OVERRIDES = [
        'Despacho' => [
            'opencreatemodal' => [['module' => 'despachos.abiertos', 'action' => 'create']],
            'openeditmodal' => [['module' => 'despachos.abiertos', 'action' => 'edit']],
            'save' => [
                ['module' => 'despachos.abiertos', 'action' => 'create'],
                ['module' => 'despachos.abiertos', 'action' => 'edit'],
            ],
            'delete' => [['module' => 'despachos.abiertos', 'action' => 'delete']],
            'expedicion' => [['module' => 'despachos.abiertos', 'action' => 'confirm']],
            'reaperturasaca' => [['module' => 'despachos.abiertos', 'action' => 'restore']],
        ],
        'DespachoExpedicion' => [
            'intervenirdespacho' => [['module' => 'despachos.expedicion', 'action' => 'confirm']],
            'volverapertura' => [['module' => 'despachos.expedicion', 'action' => 'restore']],
            'openintervencionmodal' => [['module' => 'despachos.expedicion', 'action' => 'edit']],
            'registrarintervencion' => [['module' => 'despachos.expedicion', 'action' => 'edit']],
        ],
        'DespachoAdmitido' => [
            'openadmitirmodal' => [['module' => 'despachos.admitidos', 'action' => 'confirm']],
            'admitirdespachos' => [['module' => 'despachos.admitidos', 'action' => 'confirm']],
            'previewadmitir' => [['module' => 'despachos.admitidos', 'action' => 'assign']],
            'scanandsearch' => [['module' => 'despachos.admitidos', 'action' => 'assign']],
            'removescanned' => [['module' => 'despachos.admitidos', 'action' => 'assign']],
        ],
        'EventosTabla' => [
            'opencreatemodal' => [['module' => 'eventos-ems.index', 'action' => 'create']],
            'openeditmodal' => [['module' => 'eventos-ems.index', 'action' => 'edit']],
            'save' => [
                ['module' => 'eventos-ems.index', 'action' => 'create'],
                ['module' => 'eventos-ems.index', 'action' => 'edit'],
            ],
            'delete' => [['module' => 'eventos-ems.index', 'action' => 'delete']],
        ],
        'Recojo' => [
            'openeditmodal' => [
                ['module' => 'paquetes-contrato.index', 'action' => 'edit'],
                ['module' => 'paquetes-contrato.almacen', 'action' => 'edit'],
            ],
            'save' => [
                ['module' => 'paquetes-contrato.index', 'action' => 'edit'],
                ['module' => 'paquetes-contrato.almacen', 'action' => 'edit'],
            ],
            'delete' => [
                ['module' => 'paquetes-contrato.index', 'action' => 'delete'],
                ['module' => 'paquetes-contrato.almacen', 'action' => 'delete'],
            ],
        ],
        'RecojoRecogerEnvios' => [
            'mandarseleccionadosalmacen' => [['module' => 'paquetes-contrato.recoger-envios', 'action' => 'assign']],
        ],
        'PaquetesEms' => [
            'opencreatemodal' => [
                ['module' => 'paquetes-ems.index', 'action' => 'create'],
                ['module' => 'paquetes-ems.almacen', 'action' => 'create'],
            ],
            'openregionalmodal' => [['module' => 'paquetes-ems.almacen', 'action' => 'sendregional']],
            'openregionalcontratomodal' => [['module' => 'paquetes-ems.almacen', 'action' => 'sendregional']],
            'opencontratoregistrarmodal' => [['module' => 'paquetes-ems.almacen', 'action' => 'registercontract']],
            'registrarcontratorapido' => [['module' => 'paquetes-ems.almacen', 'action' => 'registercontract']],
            'opencontratopesomodal' => [['module' => 'paquetes-ems.almacen', 'action' => 'weighcontract']],
            'buscarcontratoparapeso' => [['module' => 'paquetes-ems.almacen', 'action' => 'weighcontract']],
            'guardarpesocontratoporcodigo' => [['module' => 'paquetes-ems.almacen', 'action' => 'weighcontract']],
            'togglecn33reprint' => [['module' => 'paquetes-ems.almacen', 'action' => 'reprintcn33']],
            'reimprimircn33' => [['module' => 'paquetes-ems.almacen', 'action' => 'reprintcn33']],
            'mandarseleccionadosgeneradoshoy' => [['module' => 'paquetes-ems.index', 'action' => 'assign']],
            'confirmarmandargeneradoshoy' => [['module' => 'paquetes-ems.index', 'action' => 'assign']],
            'mandarseleccionadossinfiltrofecha' => [['module' => 'paquetes-ems.index', 'action' => 'assign']],
            'mandarseleccionadosregional' => [['module' => 'paquetes-ems.almacen', 'action' => 'sendregional']],
            'mandarseleccionadoscontratosregional' => [['module' => 'paquetes-ems.almacen', 'action' => 'sendregional']],
            'mandarseleccionadosventanillaems' => [['module' => 'paquetes-ems.almacen', 'action' => 'sendventanilla']],
            'openentregaventanillamodal' => [['module' => 'paquetes-ems.ventanilla', 'action' => 'deliver']],
            'confirmarentregaventanilla' => [['module' => 'paquetes-ems.ventanilla', 'action' => 'deliver']],
            'openrecibirregionalmodal' => [['module' => 'paquetes-ems.recibir-regional', 'action' => 'assign']],
            'recibirseleccionadosregional' => [['module' => 'paquetes-ems.recibir-regional', 'action' => 'assign']],
            'devolveraadmisiones' => [['module' => 'paquetes-ems.almacen', 'action' => 'restore']],
            'delete' => [['module' => 'paquetes-ems.index', 'action' => 'delete']],
        ],
        'PaquetesOrdi' => [
            'openrecibirmodal' => [['module' => 'paquetes-ordinarios.almacen', 'action' => 'assign']],
            'addcodigorecibir' => [['module' => 'paquetes-ordinarios.almacen', 'action' => 'assign']],
            'confirmarrecibir' => [['module' => 'paquetes-ordinarios.almacen', 'action' => 'assign']],
            'openreencaminarmodal' => [['module' => 'paquetes-ordinarios.almacen', 'action' => 'reencaminar']],
            'savereencaminar' => [['module' => 'paquetes-ordinarios.almacen', 'action' => 'reencaminar']],
            'bajapaquetes' => [['module' => 'paquetes-ordinarios.almacen', 'action' => 'dropoff']],
            'rezagopaquetes' => [['module' => 'paquetes-ordinarios.almacen', 'action' => 'rezago']],
            'opencreatemodal' => [['module' => 'paquetes-ordinarios.index', 'action' => 'create']],
            'despacharseleccionados' => [['module' => 'paquetes-ordinarios.index', 'action' => 'assign']],
            'reimprimirmanifiesto' => [['module' => 'paquetes-ordinarios.despacho', 'action' => 'print']],
            'delete' => [['module' => 'paquetes-ordinarios.index', 'action' => 'delete']],
            'devolveraclasificacion' => [['module' => 'paquetes-ordinarios.despacho', 'action' => 'restore']],
            'altaaalmacen' => [['module' => 'paquetes-ordinarios.entregado', 'action' => 'restore']],
            'reimprimirformularioentrega' => [['module' => 'paquetes-ordinarios.entregado', 'action' => 'print']],
            'devolverrezagoaalmacen' => [['module' => 'paquetes-ordinarios.rezago', 'action' => 'restore']],
        ],
        'PaqueteCerti' => [
            'opencreatemodal' => [['module' => 'paquetes-certificados.almacen', 'action' => 'create']],
            'openreencaminarmodal' => [['module' => 'paquetes-certificados.almacen', 'action' => 'reencaminar']],
            'savereencaminar' => [['module' => 'paquetes-certificados.almacen', 'action' => 'reencaminar']],
            'bajamasiva' => [['module' => 'paquetes-certificados.almacen', 'action' => 'dropoff']],
            'rezagomasivo' => [['module' => 'paquetes-certificados.almacen', 'action' => 'rezago']],
            'marcarinventario' => [['module' => 'paquetes-certificados.almacen', 'action' => 'edit']],
            'marcarventanilla' => [
                ['module' => 'paquetes-certificados.inventario', 'action' => 'assign'],
                ['module' => 'paquetes-certificados.rezago', 'action' => 'assign'],
            ],
            'reimprimirpdf' => [['module' => 'paquetes-certificados.inventario', 'action' => 'export']],
        ],
        'Saca' => [
            'opencreatemodal' => [['module' => 'sacas.index', 'action' => 'create']],
            'openeditmodal' => [['module' => 'sacas.index', 'action' => 'edit']],
            'save' => [
                ['module' => 'sacas.index', 'action' => 'create'],
                ['module' => 'sacas.index', 'action' => 'edit'],
            ],
            'delete' => [['module' => 'sacas.index', 'action' => 'delete']],
            'cerrardespacho' => [['module' => 'sacas.index', 'action' => 'confirm']],
            'seleccionarcodespecial' => [['module' => 'sacas.index', 'action' => 'assign']],
            'addcurrenttobatch' => [['module' => 'sacas.index', 'action' => 'assign']],
            'removebatchrow' => [['module' => 'sacas.index', 'action' => 'assign']],
        ],
    ];

    /**
     * Route access overrides for windows that can be opened from multiple feature contexts.
     *
     * @var array<string, array<int, string>>
     */
    private const ROUTE_ACCESS_PERMISSION_OVERRIDES = [
        'paquetes-ems.create' => [
            'paquetes-ems.create',
            'feature.paquetes-ems.index.create',
            'feature.paquetes-ems.almacen.create',
        ],
        'sacas.index' => [
            'sacas.index',
            'feature.despachos.abiertos.assign',
        ],
        'paquetes-ems.boleta' => [
            'paquetes-ems.boleta',
            'feature.paquetes-ems.index.print',
            'feature.paquetes-ems.almacen.print',
            'feature.paquetes-ems.ventanilla.print',
            'feature.paquetes-ems.recibir-regional.print',
            'feature.paquetes-ems.en-transito.print',
            'feature.paquetes-ems.index.create',
            'feature.paquetes-ems.almacen.create',
        ],
        'paquetes-ems.contrato-rapido.store' => [
            'paquetes-ems.contrato-rapido.store',
            'feature.paquetes-ems.contrato-rapido.create.save',
            'feature.paquetes-ems.contrato-rapido.create.create',
        ],
    ];

    /**
     * @var array<int, string>|null
     */
    private static ?array $cachedLivewireFeaturePermissions = null;

    /**
     * @var array<int, string>|null
     */
    private static ?array $cachedRoutePermissions = null;

    /**
     * @var array<string, bool>|null
     */
    private static ?array $cachedRouteLookup = null;

    /**
     * @var array<string, bool>|null
     */
    private static ?array $cachedExistingPermissionLookup = null;

    /**
     * Sync discovered permissions into database.
     *
     * @return array<int, string>
     */
    public static function syncPermissions(): array
    {
        $guardName = (string) config('auth.defaults.guard', 'web');
        $permissionNames = self::allPermissionNames();
        $timestamp = now();
        $rows = collect($permissionNames)
            ->map(fn (string $permissionName): array => [
                'name' => $permissionName,
                'guard_name' => $guardName,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ])
            ->values()
            ->all();

        if ($rows !== []) {
            Permission::query()->upsert(
                $rows,
                ['name', 'guard_name'],
                ['updated_at']
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        self::$cachedExistingPermissionLookup = null;

        return $permissionNames;
    }

    /**
     * List all permission names discovered from routes + feature actions + custom entries.
     *
     * @return array<int, string>
     */
    public static function allPermissionNames(): array
    {
        $routePermissions = self::routePermissionNames();
        $featurePermissions = self::featurePermissionNamesFromRoutes($routePermissions);
        $livewireFeaturePermissions = self::featurePermissionNamesFromLivewireComponents();

        $customPermissions = collect(config('acl.custom_permissions', []))
            ->filter(fn (mixed $permission): bool => is_string($permission) && $permission !== '');

        return collect($routePermissions)
            ->merge($featurePermissions)
            ->merge($livewireFeaturePermissions)
            ->merge($customPermissions)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function routePermissionNames(): array
    {
        if (self::$cachedRoutePermissions !== null) {
            return self::$cachedRoutePermissions;
        }

        self::$cachedRoutePermissions = collect(Route::getRoutes())
            ->filter(fn (IlluminateRoute $route): bool => self::isProtectedRoute($route))
            ->map(fn (IlluminateRoute $route): string => (string) $route->getName())
            ->unique()
            ->sort()
            ->values()
            ->all();

        self::$cachedRouteLookup = collect(self::$cachedRoutePermissions)
            ->mapWithKeys(fn (string $permission): array => [$permission => true])
            ->all();

        return self::$cachedRoutePermissions;
    }

    /**
     * Functional permissions that can also authorize route actions.
     *
     * @return array<int, string>
     */
    public static function featurePermissionsForRoute(string $routePermission): array
    {
        if ($routePermission === '') {
            return [];
        }

        [$moduleKey, $actionKey] = self::splitPermissionName($routePermission);
        $featureAction = self::canonicalFeatureAction($routePermission, $actionKey);

        return array_values(array_unique([
            self::featurePermissionName($moduleKey, $featureAction),
            self::featurePermissionName($moduleKey, 'manage'),
        ]));
    }

    /**
     * Permission candidates that can authorize opening a route/window.
     *
     * @return array<int, string>
     */
    public static function authorizationPermissionsForRouteAccess(string $routePermission): array
    {
        if ($routePermission === '') {
            return [];
        }

        if (isset(self::ROUTE_ACCESS_PERMISSION_OVERRIDES[$routePermission])) {
            return array_values(array_unique(self::ROUTE_ACCESS_PERMISSION_OVERRIDES[$routePermission]));
        }

        return [$routePermission];
    }

    /**
     * Livewire action permissions used in middleware/hooks.
     *
     * @return array<int, string>
     */
    public static function authorizationPermissionsForLivewireAction(
        string $componentClass,
        string $methodName,
        ?object $component = null,
        bool $includeAmbiguous = false
    ): array {
        $targets = self::livewireFeatureTargets($componentClass, $methodName, $component, $includeAmbiguous);

        if ($targets === []) {
            return [];
        }

        $permissions = [];

        foreach ($targets as $target) {
            foreach (self::authorizationPermissionsForModuleAction(
                $target['module'],
                $target['action'],
                false
            ) as $permission) {
                $permissions[] = $permission;
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * @return array<int, string>
     */
    public static function featurePermissionsForLivewireAction(
        string $componentClass,
        string $methodName,
        ?object $component = null,
        bool $includeAmbiguous = false
    ): array {
        $targets = self::livewireFeatureTargets($componentClass, $methodName, $component, $includeAmbiguous);

        if ($targets === []) {
            return [];
        }

        $permissions = [];

        foreach ($targets as $target) {
            $permissions[] = self::featurePermissionName($target['module'], $target['action']);
            $permissions[] = self::featurePermissionName($target['module'], 'manage');
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Resolve ACL module keys from a Livewire component class/instance.
     *
     * @return array<int, string>
     */
    public static function moduleKeysForLivewireComponent(string $componentClass, ?object $component = null): array
    {
        return self::livewireModulesForComponent($componentClass, $component);
    }

    /**
     * Permission candidates that can authorize a module feature action.
     *
     * @return array<int, string>
     */
    public static function authorizationPermissionsForModuleAction(
        string $moduleKey,
        string $featureAction,
        bool $includeManage = true
    ): array
    {
        if ($moduleKey === '' || $featureAction === '') {
            return [];
        }

        $permissions = [self::featurePermissionName($moduleKey, $featureAction)];

        if ($includeManage) {
            $permissions[] = self::featurePermissionName($moduleKey, 'manage');
        }

        foreach (self::routePermissionsForModuleAction($moduleKey, $featureAction) as $routePermission) {
            $permissions[] = $routePermission;
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Group permissions by module for role checkbox matrix.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function groupedPermissionsForMatrix(): array
    {
        $excludedPermissions = collect(config('acl.excluded_route_permissions', []));

        $permissionNames = Permission::query()
            ->orderBy('name')
            ->pluck('name')
            ->reject(fn (string $permissionName): bool => $excludedPermissions->contains($permissionName))
            ->reject(fn (string $permissionName): bool => self::shouldHidePermissionFromMatrix($permissionName))
            ->values();

        if ($permissionNames->isEmpty()) {
            $permissionNames = collect(self::syncPermissions())
                ->reject(fn (string $permissionName): bool => $excludedPermissions->contains($permissionName))
                ->reject(fn (string $permissionName): bool => self::shouldHidePermissionFromMatrix($permissionName))
                ->values();
        }

        $moduleLabels = (array) config('acl.module_labels', []);
        $groups = [];

        foreach ($permissionNames as $permissionName) {
            [$moduleKey, $actionKey] = self::splitPermissionName($permissionName);

            if (! isset($groups[$moduleKey])) {
                $groups[$moduleKey] = [
                    'module_key' => $moduleKey,
                    'module_label' => $moduleLabels[$moduleKey] ?? self::humanize($moduleKey),
                    'permissions' => [],
                ];
            }

            $groups[$moduleKey]['permissions'][] = [
                'name' => $permissionName,
                'action_key' => $actionKey,
                'action_label' => self::actionLabel($permissionName, $actionKey),
                'hint' => self::permissionHint($permissionName, $moduleKey, $actionKey),
                'type' => self::permissionType($permissionName),
                'type_label' => self::permissionTypeLabel($permissionName),
            ];
        }

        ksort($groups);

        return array_values($groups);
    }

    /**
     * Build a readable menu -> submenu -> window -> actions summary.
     *
     * @param  array<int, array<string, mixed>>|null  $permissionGroups
     * @return array<int, array<string, mixed>>
     */
    public static function menuPermissionSummary(?array $permissionGroups = null): array
    {
        $permissionGroups ??= self::groupedPermissionsForMatrix();

        $groupsByModule = collect($permissionGroups)
            ->mapWithKeys(fn (array $group): array => [$group['module_key'] => $group]);

        $summary = [];

        foreach ((array) config('adminlte.menu', []) as $item) {
            $node = self::buildMenuSummaryNode($item, $groupsByModule, true);

            if ($node !== null) {
                $summary[] = $node;
            }
        }

        return $summary;
    }

    /**
     * Check whether a permission exists in cached Spatie registry.
     */
    public static function permissionExists(string $permissionName): bool
    {
        if ($permissionName === '') {
            return false;
        }

        $lookup = self::existingPermissionLookup();

        return isset($lookup[$permissionName]);
    }

    /**
     * @return array<string, bool>
     */
    public static function existingPermissionLookup(): array
    {
        if (self::$cachedExistingPermissionLookup !== null) {
            return self::$cachedExistingPermissionLookup;
        }

        self::$cachedExistingPermissionLookup = app(PermissionRegistrar::class)
            ->getPermissions()
            ->pluck('name')
            ->mapWithKeys(fn (string $permissionName): array => [$permissionName => true])
            ->all();

        return self::$cachedExistingPermissionLookup;
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    public static function existingPermissionsFrom(array $permissions): array
    {
        if ($permissions === []) {
            return [];
        }

        $lookup = self::existingPermissionLookup();

        return array_values(array_filter(
            array_unique($permissions),
            fn (string $permissionName): bool => isset($lookup[$permissionName])
        ));
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function splitPermissionName(string $permissionName): array
    {
        $segments = explode('.', $permissionName);

        if (($segments[0] ?? '') === 'feature' && count($segments) >= 3) {
            $actionKey = (string) array_pop($segments);
            array_shift($segments);
            $moduleKey = implode('.', $segments);

            return [$moduleKey, $actionKey];
        }

        $moduleKey = $segments[0] ?? $permissionName;

        if ($moduleKey === 'api' && isset($segments[1])) {
            $moduleKey = 'api.'.$segments[1];
        }

        $actionKey = count($segments) > 1 ? (string) end($segments) : 'access';

        return [$moduleKey, $actionKey];
    }

    public static function permissionType(string $permissionName): string
    {
        if (str_starts_with($permissionName, 'feature.')) {
            return 'feature';
        }

        if (self::isRoutePermission($permissionName)) {
            return 'route';
        }

        return 'custom';
    }

    public static function permissionTypeLabel(string $permissionName): string
    {
        return match (self::permissionType($permissionName)) {
            'feature' => 'Boton/Funcionalidad',
            'route' => 'Ruta/Ventana',
            default => 'Personalizado',
        };
    }

    /**
     * @param  array<int, string>  $routePermissions
     * @return array<int, string>
     */
    private static function featurePermissionNamesFromRoutes(array $routePermissions): array
    {
        $featurePermissions = [];

        foreach ($routePermissions as $routePermission) {
            foreach (self::featurePermissionsForRoute($routePermission) as $featurePermission) {
                $featurePermissions[] = $featurePermission;
            }
        }

        return collect($featurePermissions)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function featurePermissionNamesFromLivewireComponents(): array
    {
        if (self::$cachedLivewireFeaturePermissions !== null) {
            return self::$cachedLivewireFeaturePermissions;
        }

        $permissions = [];

        foreach (self::livewireComponentClasses() as $componentClass) {
            $reflection = new ReflectionClass($componentClass);

            foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->class !== $reflection->getName()) {
                    continue;
                }

                foreach (self::featurePermissionsForLivewireAction(
                    $componentClass,
                    $method->getName(),
                    null,
                    true
                ) as $permissionName) {
                    $permissions[] = $permissionName;
                }
            }
        }

        self::$cachedLivewireFeaturePermissions = collect($permissions)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return self::$cachedLivewireFeaturePermissions;
    }

    /**
     * @return array<int, class-string>
     */
    private static function livewireComponentClasses(): array
    {
        $files = glob(app_path('Livewire/*.php')) ?: [];

        return collect($files)
            ->map(fn (string $file): string => 'App\\Livewire\\'.pathinfo($file, PATHINFO_FILENAME))
            ->filter(fn (string $class): bool => class_exists($class))
            ->filter(fn (string $class): bool => is_subclass_of($class, LivewireComponent::class))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{module:string,action:string}>
     */
    private static function livewireFeatureTargets(
        string $componentClass,
        string $methodName,
        ?object $component = null,
        bool $includeAmbiguous = false
    ): array {
        $componentBaseName = class_basename($componentClass);
        $normalizedMethod = strtolower(trim((string) Str::of($methodName)->replaceMatches('/[^a-z0-9]/', '')));

        if (
            isset(self::LIVEWIRE_METHOD_TARGET_OVERRIDES[$componentBaseName][$normalizedMethod])
            && self::LIVEWIRE_METHOD_TARGET_OVERRIDES[$componentBaseName][$normalizedMethod] !== []
        ) {
            return self::resolveExplicitLivewireTargets(
                $componentBaseName,
                self::LIVEWIRE_METHOD_TARGET_OVERRIDES[$componentBaseName][$normalizedMethod],
                $component
            );
        }

        $actions = self::livewireFeatureActionsForMethod($methodName, $component, $includeAmbiguous);

        if ($actions === []) {
            return [];
        }

        $modules = self::livewireModulesForComponent($componentClass, $component);

        if ($modules === []) {
            return [];
        }

        $targets = [];

        foreach ($modules as $moduleKey) {
            foreach ($actions as $action) {
                $targets[] = [
                    'module' => $moduleKey,
                    'action' => $action,
                ];
            }
        }

        return $targets;
    }

    /**
     * @return array<int, string>
     */
    private static function livewireModulesForComponent(string $componentClass, ?object $component = null): array
    {
        $baseName = class_basename($componentClass);

        if ($baseName === 'EventosTabla') {
            return self::eventosTablaModules($component);
        }

        $windowModules = self::windowRouteModulesForComponent($baseName, $component);

        if ($windowModules !== []) {
            return $windowModules;
        }

        if (isset(self::LIVEWIRE_COMPONENT_MODULES[$baseName])) {
            return [self::LIVEWIRE_COMPONENT_MODULES[$baseName]];
        }

        $fallbackModule = Str::kebab(Str::pluralStudly($baseName));

        return [$fallbackModule];
    }

    /**
     * @return array<int, string>
     */
    private static function windowRouteModulesForComponent(string $baseName, ?object $component = null): array
    {
        if ($baseName === 'EventosTabla') {
            if ($component && property_exists($component, 'tipo')) {
                $tipo = strtolower(trim((string) $component->tipo));

                if (isset(self::EVENTOS_TABLA_ROUTE_MODULES[$tipo])) {
                    return [self::EVENTOS_TABLA_ROUTE_MODULES[$tipo]];
                }
            }

            return array_values(array_unique(array_values(self::EVENTOS_TABLA_ROUTE_MODULES)));
        }

        $map = self::WINDOW_ROUTE_MODULES[$baseName] ?? [];

        if ($map === []) {
            return [];
        }

        if ($component && property_exists($component, 'mode')) {
            $mode = strtolower(trim((string) $component->mode));

            if (isset($map[$mode])) {
                return [$map[$mode]];
            }
        }

        return array_values(array_unique(array_values($map)));
    }

    /**
     * @param  array<int, array{module:string,action:string}>  $targets
     * @return array<int, array{module:string,action:string}>
     */
    private static function resolveExplicitLivewireTargets(string $componentBaseName, array $targets, ?object $component = null): array
    {
        $currentWindowModules = self::windowRouteModulesForComponent($componentBaseName, $component);
        $currentWindowModule = $currentWindowModules[0] ?? null;
        $resolvedTargets = [];

        foreach ($targets as $target) {
            $module = (string) ($target['module'] ?? '');
            $action = (string) ($target['action'] ?? '');

            if ($module === '' || $action === '') {
                continue;
            }

            if (
                $componentBaseName === 'EventosTabla'
                && $currentWindowModule !== null
                && str_starts_with($module, 'eventos-')
            ) {
                $module = $currentWindowModule;
            }

            if (
                $currentWindowModule !== null
                && str_contains($module, '.')
                && isset(self::WINDOW_ROUTE_MODULES[$componentBaseName])
                && in_array($module, self::WINDOW_ROUTE_MODULES[$componentBaseName], true)
                && ! in_array($module, $currentWindowModules, true)
            ) {
                continue;
            }

            $resolvedTargets[] = [
                'module' => $module,
                'action' => $action,
            ];
        }

        return array_values(array_unique($resolvedTargets, SORT_REGULAR));
    }

    /**
     * @return array<int, string>
     */
    private static function eventosTablaModules(?object $component = null): array
    {
        if ($component && property_exists($component, 'tipo')) {
            $tipo = strtolower(trim((string) $component->tipo));

            if (isset(self::EVENTOS_TABLA_TIPO_MODULES[$tipo])) {
                return [self::EVENTOS_TABLA_TIPO_MODULES[$tipo]];
            }
        }

        return collect(self::EVENTOS_TABLA_TIPO_MODULES)
            ->values()
            ->push('eventos')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function livewireFeatureActionsForMethod(
        string $methodName,
        ?object $component = null,
        bool $includeAmbiguous = false
    ): array {
        $method = strtolower(trim($methodName));
        $normalized = (string) Str::of($method)->replaceMatches('/[^a-z0-9]/', '');

        if ($normalized === '' || str_starts_with($normalized, '__')) {
            return [];
        }

        if (in_array($normalized, ['render', 'rendered', 'mount', 'hydrate', 'boot', 'destroy'], true)) {
            return [];
        }

        if (
            str_starts_with($normalized, 'updated')
            || str_starts_with($normalized, 'updating')
            || str_starts_with($normalized, 'reset')
            || str_starts_with($normalized, 'search')
            || str_starts_with($normalized, 'get')
            || str_starts_with($normalized, 'set')
            || str_starts_with($normalized, 'toggle')
            || str_starts_with($normalized, 'change')
        ) {
            return [];
        }

        if ($normalized === 'applystatusaction') {
            if ($includeAmbiguous || ! $component || ! property_exists($component, 'statusAction')) {
                return ['delete', 'restore'];
            }

            $statusAction = strtolower(trim((string) $component->statusAction));

            return match ($statusAction) {
                'delete' => ['delete'],
                'restore' => ['restore'],
                default => ['manage'],
            };
        }

        if (isset(self::LIVEWIRE_METHOD_ACTION_OVERRIDES[$normalized])) {
            return [self::LIVEWIRE_METHOD_ACTION_OVERRIDES[$normalized]];
        }

        if (str_starts_with($normalized, 'opencreate') || str_starts_with($normalized, 'create')) {
            return ['create'];
        }

        if (
            str_starts_with($normalized, 'openedit')
            || str_starts_with($normalized, 'edit')
            || str_contains($normalized, 'password')
        ) {
            return ['edit'];
        }

        if (str_contains($normalized, 'reencaminar')) {
            return ['reencaminar'];
        }

        if (self::containsAny($normalized, ['delete', 'destroy', 'baja'])) {
            return ['delete'];
        }

        if (self::containsAny($normalized, ['restore', 'restaur', 'alta', 'reapertura', 'volverapertura'])) {
            return ['restore'];
        }

        if (self::containsAny($normalized, ['excel', 'pdf', 'export', 'download'])) {
            return ['export'];
        }

        if (str_contains($normalized, 'import')) {
            return ['import'];
        }

        if (self::containsAny($normalized, ['boleta', 'imprimir', 'reimprimir', 'manifiesto', 'print'])) {
            return ['print'];
        }

        if (self::containsAny($normalized, ['reporte', 'report'])) {
            return ['report'];
        }

        if (self::containsAny($normalized, ['entrega', 'deliver'])) {
            return ['deliver'];
        }

        if (self::containsAny($normalized, ['confirm', 'admit', 'intervencion', 'intervenir'])) {
            return ['confirm'];
        }

        if (self::containsAny($normalized, ['asign', 'assign', 'mandar', 'despach', 'recibir', 'batch', 'enqueue', 'devolver'])) {
            return ['assign'];
        }

        if (
            str_starts_with($normalized, 'save')
            || str_starts_with($normalized, 'guardar')
            || str_starts_with($normalized, 'registrar')
            || str_starts_with($normalized, 'ejecutar')
        ) {
            if (self::containsAny($normalized, ['confirm'])) {
                return ['confirm'];
            }

            if (str_contains($normalized, 'reencaminar')) {
                return ['reencaminar'];
            }

            if (self::containsAny($normalized, ['peso'])) {
                return ['edit'];
            }

            if ($includeAmbiguous) {
                return ['create', 'edit'];
            }

            if ($component && property_exists($component, 'editingId') && ! empty($component->editingId)) {
                return ['edit'];
            }

            return ['create'];
        }

        if (str_starts_with($normalized, 'open')) {
            if (str_contains($normalized, 'entrega')) {
                return ['deliver'];
            }

            if (self::containsAny($normalized, ['admit', 'intervencion'])) {
                return ['confirm'];
            }

            if (self::containsAny($normalized, ['regional', 'recibir', 'contrato', 'peso'])) {
                return ['assign'];
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private static function routePermissionsForModuleAction(string $moduleKey, string $featureAction): array
    {
        $moduleRoutes = collect(self::routePermissionNames())
            ->filter(fn (string $routePermission): bool => $routePermission === $moduleKey || str_starts_with($routePermission, $moduleKey.'.'))
            ->values();

        if ($moduleRoutes->isEmpty()) {
            return [];
        }

        if ($featureAction === 'manage') {
            return $moduleRoutes->all();
        }

        return $moduleRoutes
            ->filter(function (string $routePermission) use ($featureAction): bool {
                [, $actionKey] = self::splitPermissionName($routePermission);

                return self::canonicalFeatureAction($routePermission, $actionKey) === $featureAction;
            })
            ->values()
            ->all();
    }

    private static function featurePermissionName(string $moduleKey, string $featureAction): string
    {
        return 'feature.'.$moduleKey.'.'.$featureAction;
    }

    private static function isRoutePermission(string $permissionName): bool
    {
        if (self::$cachedRouteLookup === null) {
            self::routePermissionNames();
        }

        return isset(self::$cachedRouteLookup[$permissionName]);
    }

    private static function canonicalFeatureAction(string $routePermission, string $actionKey): string
    {
        if (isset(self::FEATURE_ACTION_FROM_ROUTE[$actionKey])) {
            return self::FEATURE_ACTION_FROM_ROUTE[$actionKey];
        }

        if (str_contains($routePermission, '.')) {
            return 'manage';
        }

        return 'view';
    }

    private static function isProtectedRoute(IlluminateRoute $route): bool
    {
        $name = $route->getName();

        if (! is_string($name) || $name === '') {
            return false;
        }

        if (in_array($name, (array) config('acl.excluded_route_permissions', []), true)) {
            return false;
        }

        $middlewares = $route->gatherMiddleware();

        return collect($middlewares)->contains(function (string $middleware): bool {
            return $middleware === 'auth' || str_starts_with($middleware, 'auth:');
        });
    }

    private static function actionLabel(string $permissionName, string $actionKey): string
    {
        $specialLabels = [
            'feature.paquetes-ordinarios.restore' => 'Botones: Alta y Devolver',
            'feature.paquetes-ordinarios.print' => 'Botones: Reimprimir',
            'feature.paquetes-certificados.assign' => 'Botones: Alta/Devuelto a ventanilla',
            'feature.paquetes-certificados.export' => 'Boton: Reimprimir PDF',
            'feature.paquetes-ordinarios.index.create' => 'Boton: Nuevo',
            'feature.paquetes-ordinarios.index.assign' => 'Boton: Despachar',
            'feature.paquetes-ordinarios.index.delete' => 'Boton: Borrar',
            'feature.paquetes-ordinarios.almacen.assign' => 'Boton: Recibir paquetes',
            'feature.paquetes-ordinarios.almacen.dropoff' => 'Boton: Baja de paquetes',
            'feature.paquetes-ordinarios.almacen.rezago' => 'Boton: Enviar a rezago',
            'feature.paquetes-ordinarios.despacho.restore' => 'Boton: Devolver a clasificacion',
            'feature.paquetes-ordinarios.despacho.print' => 'Boton: Reimprimir manifiesto',
            'feature.paquetes-ordinarios.entregado.restore' => 'Boton: Alta',
            'feature.paquetes-ordinarios.entregado.print' => 'Boton: Reimprimir formulario',
            'feature.paquetes-ordinarios.rezago.restore' => 'Boton: Devolver a almacen',
            'feature.paquetes-certificados.almacen.create' => 'Boton: Nuevo',
            'feature.paquetes-certificados.almacen.dropoff' => 'Boton: Baja',
            'feature.paquetes-certificados.almacen.rezago' => 'Boton: Rezago',
            'feature.paquetes-certificados.inventario.assign' => 'Boton: Alta a ventanilla',
            'feature.paquetes-certificados.inventario.export' => 'Boton: Reimprimir PDF',
            'feature.paquetes-certificados.rezago.assign' => 'Boton: Devuelto a ventanilla',
            'feature.paquetes-ems.index.create' => 'Boton: Nuevo',
            'feature.paquetes-ems.index.assign' => 'Boton: Generados hoy / Mandar seleccionados',
            'feature.paquetes-ems.index.delete' => 'Boton: Eliminar',
            'feature.paquetes-ems.index.print' => 'Boton: Reimprimir boleta',
            'feature.paquetes-ems.almacen.create' => 'Boton: Nuevo',
            'feature.paquetes-ems.almacen.edit' => 'Boton: Editar',
            'feature.paquetes-ems.almacen.assign' => 'Boton: Acciones generales de movimiento',
            'feature.paquetes-ems.almacen.print' => 'Boton: Reimprimir boleta',
            'feature.paquetes-ems.almacen.restore' => 'Boton: Devolver a admisiones',
            'feature.paquetes-ems.almacen.registercontract' => 'Boton: Registrar contrato',
            'feature.paquetes-ems.almacen.weighcontract' => 'Boton: Anadir peso contrato',
            'feature.paquetes-ems.almacen.sendventanilla' => 'Boton: Enviar a ventanilla EMS',
            'feature.paquetes-ems.almacen.sendregional' => 'Boton: Manda a regional',
            'feature.paquetes-ems.almacen.reprintcn33' => 'Boton: Reimprimir CN-33',
            'feature.paquetes-ems.contrato-rapido.create.create' => 'Boton: Anadir a prelista / Duplicar',
            'feature.paquetes-ems.contrato-rapido.create.save' => 'Boton: Guardar todos',
            'feature.paquetes-ems.contrato-rapido.create.delete' => 'Boton: Quitar de prelista',
            'feature.paquetes-ems.ventanilla.deliver' => 'Boton: Entregar seleccionados',
            'feature.paquetes-ems.ventanilla.print' => 'Boton: Reimprimir boleta',
            'feature.paquetes-ems.recibir-regional.assign' => 'Boton: Recibir regional',
            'feature.paquetes-ems.recibir-regional.print' => 'Boton: Reimprimir boleta',
            'feature.paquetes-ems.en-transito.print' => 'Boton: Reimprimir boleta',
            'feature.paquetes-contrato.index.report' => 'Boton: Imprimir generados hoy',
            'feature.paquetes-contrato.index.print' => 'Boton: Reimprimir rotulo',
            'feature.paquetes-contrato.almacen.report' => 'Boton: Imprimir generados hoy',
            'feature.paquetes-contrato.almacen.print' => 'Boton: Reimprimir rotulo',
            'feature.paquetes-contrato.recoger-envios.assign' => 'Boton: Mandar seleccionados a almacen',
            'feature.paquetes-contrato.recoger-envios.print' => 'Boton: Reimprimir rotulo',
            'feature.paquetes-contrato.cartero.print' => 'Boton: Reimprimir rotulo',
            'feature.paquetes-contrato.create.create' => 'Boton: Guardar contrato',
            'feature.paquetes-contrato.create.manage' => 'Boton: Guardar envio frecuente',
            'feature.paquetes-contrato.create-con-tarifa.create' => 'Boton: Guardar contrato con tarifa',
            'feature.paquetes-contrato.entregados.print' => 'Boton: Reimprimir rotulo',
            'feature.paquetes-contrato.entregados.export' => 'Boton: Descargar imagen',
            'feature.carteros.distribucion.assign' => 'Boton: Asignar',
            'feature.carteros.distribucion.selfassign' => 'Boton: Autoasignarme',
            'feature.carteros.cartero.guide' => 'Boton: Mandar provincia',
            'feature.carteros.cartero.province' => 'Boton: Mostrar provincias',
            'feature.carteros.cartero.deliver' => 'Boton: Abrir entrega',
            'feature.carteros.devolucion.restore' => 'Boton: Recuperar',
            'feature.carteros.entrega.deliver' => 'Boton: Confirmar entrega',
            'feature.carteros.entrega.attempt' => 'Boton: Agregar intento',
            'feature.eventos-ems.index.create' => 'Boton: Nuevo registro EMS',
            'feature.eventos-ems.index.edit' => 'Boton: Editar registro EMS',
            'feature.eventos-ems.index.delete' => 'Boton: Eliminar registro EMS',
            'feature.eventos-contrato.index.create' => 'Boton: Nuevo registro Contrato',
            'feature.eventos-contrato.index.edit' => 'Boton: Editar registro Contrato',
            'feature.eventos-contrato.index.delete' => 'Boton: Eliminar registro Contrato',
            'feature.servicios.create' => 'Boton: Nuevo servicio / Guardar servicio',
            'feature.servicios.edit' => 'Boton: Editar servicio / Guardar cambios',
            'feature.servicios.delete' => 'Boton: Dar de baja servicio',
            'feature.sucursales.create' => 'Boton: Nueva sucursal / Guardar sucursal',
            'feature.sucursales.edit' => 'Boton: Editar sucursal / Guardar cambios',
            'feature.sucursales.delete' => 'Boton: Dar de baja sucursal',
            'feature.despachos.abiertos.create' => 'Boton: Nuevo despacho',
            'feature.despachos.abiertos.edit' => 'Boton: Editar despacho',
            'feature.despachos.abiertos.delete' => 'Boton: Eliminar despacho',
            'feature.despachos.abiertos.assign' => 'Boton: Abrir sacas',
            'feature.despachos.abiertos.confirm' => 'Boton: Enviar a expedicion',
            'feature.despachos.abiertos.restore' => 'Boton: Reapertura de saca',
            'feature.despachos.expedicion.print' => 'Boton: Reimprimir CN',
            'feature.despachos.expedicion.confirm' => 'Boton: Intervenir despacho',
            'feature.despachos.expedicion.restore' => 'Boton: Volver a apertura',
            'feature.despachos.expedicion.edit' => 'Boton: Registrar intervencion',
            'feature.despachos.admitidos.assign' => 'Boton: Buscar/quitar receptaculos',
            'feature.despachos.admitidos.confirm' => 'Boton: Admitir despachos',
            'feature.sacas.index.create' => 'Boton: Nueva saca',
            'feature.sacas.index.edit' => 'Boton: Editar saca',
            'feature.sacas.index.delete' => 'Boton: Eliminar saca',
            'feature.sacas.index.assign' => 'Boton: Armar lista de sacas',
            'feature.sacas.index.confirm' => 'Boton: Cerrar despacho',
        ];

        if (isset($specialLabels[$permissionName])) {
            return $specialLabels[$permissionName];
        }

        $permissionType = self::permissionType($permissionName);

        if ($permissionType === 'feature') {
            return self::FEATURE_ACTION_LABELS[$actionKey] ?? ('Boton: '.self::humanize($actionKey));
        }

        if ($permissionType === 'custom') {
            return 'Funcionalidad: '.self::humanize($actionKey);
        }

        if (isset(self::ACTION_LABELS[$actionKey])) {
            return self::ACTION_LABELS[$actionKey];
        }

        if (str_ends_with($permissionName, '.pdf')) {
            return 'Exportar PDF';
        }

        if (str_ends_with($permissionName, '.excel')) {
            return 'Exportar Excel';
        }

        return 'Acceso: '.self::humanize($actionKey);
    }

    /**
     * @param  \Illuminate\Support\Collection<string, array<string, mixed>>  $groupsByModule
     * @return array<string, mixed>|null
     */
    private static function buildMenuSummaryNode(array $item, $groupsByModule, bool $isTopLevel = false): ?array
    {
        if (isset($item['header']) || isset($item['type'])) {
            return null;
        }

        $label = trim((string) ($item['text'] ?? ''));
        $submenu = $item['submenu'] ?? null;

        if (is_array($submenu) && $submenu !== []) {
            $children = collect($submenu)
                ->map(fn (array $child): ?array => self::buildMenuSummaryNode($child, $groupsByModule, false))
                ->filter()
                ->values()
                ->all();

            if ($children === []) {
                return null;
            }

            return [
                'label' => $label !== '' ? $label : 'Menu',
                'level' => $isTopLevel ? 'tab' : 'submenu',
                'children' => $children,
            ];
        }

        $url = trim((string) ($item['url'] ?? ''));
        $routeName = self::resolveRouteNameFromMenuUrl($url);

        if ($routeName === null) {
            return null;
        }

        [$moduleKey] = self::splitPermissionName($routeName);
        $group = $groupsByModule->get($moduleKey);
        $windowFeatureGroup = $groupsByModule->get($routeName);

        $routePermission = null;
        $featurePermissions = [];

        if (is_array($group)) {
            $routePermission = collect($group['permissions'] ?? [])->firstWhere('name', $routeName);
        }

        if (is_array($windowFeatureGroup)) {
            $featurePermissions = collect($windowFeatureGroup['permissions'] ?? [])
                ->filter(function (array $permission) use ($routeName): bool {
                    if (($permission['type'] ?? null) !== 'feature') {
                        return false;
                    }

                    return self::isSupportedWindowFeaturePermission(
                        (string) ($permission['name'] ?? ''),
                        $routeName
                    );
                })
                ->values()
                ->all();
        }

        return [
            'label' => $label !== '' ? $label : $routeName,
            'level' => 'window',
            'route' => $routePermission,
            'route_name' => $routeName,
            'module_key' => $moduleKey,
            'module_label' => is_array($windowFeatureGroup)
                ? ($windowFeatureGroup['module_label'] ?? self::humanize($routeName))
                : (is_array($group) ? ($group['module_label'] ?? self::humanize($moduleKey)) : self::humanize($moduleKey)),
            'actions' => $featurePermissions,
        ];
    }

    private static function resolveRouteNameFromMenuUrl(string $url): ?string
    {
        if (
            $url === ''
            || str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
            || str_starts_with($url, '#')
            || str_starts_with($url, 'mailto:')
            || str_starts_with($url, 'tel:')
        ) {
            return null;
        }

        $path = trim(parse_url($url, PHP_URL_PATH) ?? '', '/');

        if ($path === '') {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create('/'.$path, 'GET'));
        } catch (\Throwable) {
            return null;
        }

        $name = $route->getName();

        return is_string($name) && $name !== '' ? $name : null;
    }

    private static function permissionHint(string $permissionName, string $moduleKey, string $actionKey): ?string
    {
        $specialHints = [
            'feature.paquetes-ordinarios.restore' => 'Controla los botones Alta en Entregado, Devolver en Rezago y Devolver en Despacho.',
            'feature.paquetes-ordinarios.print' => 'Controla Reimprimir manifiesto en Despacho y Reimprimir formulario en Entregado.',
            'feature.paquetes-ordinarios.assign' => 'Controla Recibir paquetes y Despachar en Ordinarios.',
            'feature.paquetes-certificados.assign' => 'Controla los botones Alta y Devuelto a ventanilla en Inventario y Rezago.',
            'feature.paquetes-certificados.export' => 'Controla el boton Reimprimir PDF en Inventario.',
            'feature.paquetes-certificados.dropoff' => 'Controla el boton Baja en Almacen.',
            'feature.paquetes-certificados.rezago' => 'Controla el boton Rezago en Almacen.',
            'feature.paquetes-ordinarios.index.create' => 'Controla el boton Nuevo de la ventana Clasificacion.',
            'feature.paquetes-ordinarios.index.assign' => 'Controla el boton Despachar de la ventana Clasificacion.',
            'feature.paquetes-ordinarios.index.delete' => 'Controla el boton Borrar dentro de la ventana Clasificacion.',
            'feature.paquetes-ordinarios.almacen.assign' => 'Controla Recibir paquetes en la ventana Almacen.',
            'feature.paquetes-ordinarios.almacen.dropoff' => 'Controla Baja de paquetes en la ventana Almacen.',
            'feature.paquetes-ordinarios.almacen.rezago' => 'Controla el boton Rezago en la ventana Almacen.',
            'feature.paquetes-ordinarios.despacho.restore' => 'Controla el boton Devolver en la ventana Despacho.',
            'feature.paquetes-ordinarios.despacho.print' => 'Controla Reimprimir manifiesto en la ventana Despacho.',
            'feature.paquetes-ordinarios.entregado.restore' => 'Controla el boton Alta en la ventana Entregado.',
            'feature.paquetes-ordinarios.entregado.print' => 'Controla Reimprimir formulario en la ventana Entregado.',
            'feature.paquetes-ordinarios.rezago.restore' => 'Controla el boton Devolver en la ventana Rezago.',
            'feature.paquetes-certificados.almacen.create' => 'Controla el boton Nuevo en la ventana Almacen.',
            'feature.paquetes-certificados.almacen.dropoff' => 'Controla el boton Baja en la ventana Almacen.',
            'feature.paquetes-certificados.almacen.rezago' => 'Controla el boton Rezago en la ventana Almacen.',
            'feature.paquetes-certificados.inventario.assign' => 'Controla el boton Alta en la ventana Inventario.',
            'feature.paquetes-certificados.inventario.export' => 'Controla Reimprimir PDF en la ventana Inventario.',
            'feature.paquetes-certificados.rezago.assign' => 'Controla el boton Devuelto en la ventana Rezago.',
            'feature.paquetes-ems.index.create' => 'Controla el boton Nuevo dentro de la ventana Admisiones.',
            'feature.paquetes-ems.index.assign' => 'Controla Generados hoy y Mandar seleccionados dentro de la ventana Admisiones.',
            'feature.paquetes-ems.index.delete' => 'Controla el boton Eliminar dentro de la ventana Admisiones.',
            'feature.paquetes-ems.index.print' => 'Controla Reimprimir boleta dentro de la ventana Admisiones.',
            'feature.paquetes-ems.almacen.create' => 'Controla solo el boton Nuevo dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.almacen.edit' => 'Controla solo el boton Editar dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.almacen.assign' => 'Controla acciones generales de movimiento heredadas dentro de Almacen EMS.',
            'feature.paquetes-ems.almacen.print' => 'Controla solo Reimprimir boleta dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.almacen.restore' => 'Controla Devolver a admisiones dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.almacen.registercontract' => 'Controla el boton Registrar contrato dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.almacen.weighcontract' => 'Controla el boton Anadir peso contrato y su modal dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.almacen.sendventanilla' => 'Controla el boton Enviar a ventanilla EMS dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.almacen.sendregional' => 'Controla el boton Manda a regional y su modal dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.almacen.reprintcn33' => 'Controla el boton Reimprimir CN-33 dentro de la ventana Almacen EMS.',
            'feature.paquetes-ems.contrato-rapido.create.create' => 'Controla Anadir a prelista y el boton Duplicar dentro del submenu Registro rapido contrato.',
            'feature.paquetes-ems.contrato-rapido.create.save' => 'Controla el boton Guardar todos dentro del submenu Registro rapido contrato.',
            'feature.paquetes-ems.contrato-rapido.create.delete' => 'Controla Quitar dentro del submenu Registro rapido contrato.',
            'feature.paquetes-ems.ventanilla.deliver' => 'Controla Entregar seleccionados dentro de la ventana Ventanilla EMS.',
            'feature.paquetes-ems.ventanilla.print' => 'Controla Reimprimir boleta dentro de la ventana Ventanilla EMS.',
            'feature.paquetes-ems.recibir-regional.assign' => 'Controla Recibir dentro de la ventana Recibir regional.',
            'feature.paquetes-ems.recibir-regional.print' => 'Controla Reimprimir boleta dentro de la ventana Recibir regional.',
            'feature.paquetes-ems.en-transito.print' => 'Controla Reimprimir boleta dentro de la ventana En transito.',
            'feature.paquetes-contrato.index.report' => 'Controla el boton Imprimir generados hoy dentro de Gestion contratos.',
            'feature.paquetes-contrato.index.print' => 'Controla Reimprimir rotulo dentro de Gestion contratos.',
            'feature.paquetes-contrato.almacen.report' => 'Controla el boton Imprimir generados hoy dentro de Almacen contratos.',
            'feature.paquetes-contrato.almacen.print' => 'Controla Reimprimir rotulo dentro de Almacen contratos.',
            'feature.paquetes-contrato.recoger-envios.assign' => 'Controla Mandar seleccionados a ALMACEN dentro de Recoger envios contratos.',
            'feature.paquetes-contrato.recoger-envios.print' => 'Controla Reimprimir rotulo dentro de Recoger envios contratos.',
            'feature.paquetes-contrato.cartero.print' => 'Controla Reimprimir rotulo dentro de Cartero contratos.',
            'feature.paquetes-contrato.create.create' => 'Controla el boton Guardar contrato dentro de Crear contrato.',
            'feature.paquetes-contrato.create.manage' => 'Controla el boton Guardar envio frecuente dentro de Crear contrato.',
            'feature.paquetes-contrato.create-con-tarifa.create' => 'Controla el boton Guardar contrato con tarifa dentro de Crear con tarifa.',
            'feature.paquetes-contrato.entregados.print' => 'Controla Reimprimir rotulo dentro de Contratos entregados.',
            'feature.paquetes-contrato.entregados.export' => 'Controla Descargar imagen dentro de Contratos entregados.',
            'feature.carteros.distribucion.assign' => 'Controla el boton Asignar en la ventana Distribucion.',
            'feature.carteros.distribucion.selfassign' => 'Controla la autoasignacion de paquetes al usuario actual en la ventana Distribucion.',
            'feature.carteros.cartero.guide' => 'Controla Mandar provincia y Guardar guia dentro de la ventana Cartero.',
            'feature.carteros.cartero.province' => 'Controla el boton Mostrar provincias dentro de la ventana Cartero.',
            'feature.carteros.cartero.deliver' => 'Controla el boton Entregar correspondencia dentro de la ventana Cartero.',
            'feature.carteros.devolucion.restore' => 'Controla el boton Recuperar dentro de la ventana Devolucion.',
            'feature.carteros.entrega.deliver' => 'Controla el boton Confirmar entrega dentro de la ventana Entrega.',
            'feature.carteros.entrega.attempt' => 'Controla el boton Agregar intento dentro de la ventana Entrega.',
            'feature.eventos-ems.index.create' => 'Controla el boton Nuevo y Crear dentro de la ventana Eventos EMS.',
            'feature.eventos-ems.index.edit' => 'Controla el boton Editar y Guardar cambios dentro de la ventana Eventos EMS.',
            'feature.eventos-ems.index.delete' => 'Controla el boton Eliminar dentro de la ventana Eventos EMS.',
            'feature.eventos-contrato.index.create' => 'Controla el boton Nuevo y Crear dentro de la ventana Eventos Contrato.',
            'feature.eventos-contrato.index.edit' => 'Controla el boton Editar y Guardar cambios dentro de la ventana Eventos Contrato.',
            'feature.eventos-contrato.index.delete' => 'Controla el boton Eliminar dentro de la ventana Eventos Contrato.',
            'feature.servicios.create' => 'Controla el boton Crear Nuevo y Guardar dentro del modulo Servicios.',
            'feature.servicios.edit' => 'Controla el boton Editar y Guardar cambios dentro del modulo Servicios.',
            'feature.servicios.delete' => 'Controla el boton Dar de baja dentro del modulo Servicios.',
            'feature.sucursales.create' => 'Controla el boton Crear Nuevo y Guardar dentro del modulo Sucursales.',
            'feature.sucursales.edit' => 'Controla el boton Editar y Guardar cambios dentro del modulo Sucursales.',
            'feature.sucursales.delete' => 'Controla el boton Dar de baja dentro del modulo Sucursales.',
            'feature.despachos.abiertos.create' => 'Controla el boton Nuevo en la ventana Despachos abiertos.',
            'feature.despachos.abiertos.edit' => 'Controla el boton Editar en la ventana Despachos abiertos.',
            'feature.despachos.abiertos.delete' => 'Controla el boton Eliminar en la ventana Despachos abiertos.',
            'feature.despachos.abiertos.assign' => 'Controla el boton Abrir/Asignar sacas desde la ventana Despachos abiertos.',
            'feature.despachos.abiertos.confirm' => 'Controla el boton Enviar a expedicion y su impresion automatica.',
            'feature.despachos.abiertos.restore' => 'Controla el boton Reapertura de saca en la ventana Despachos abiertos.',
            'feature.despachos.expedicion.print' => 'Controla el boton Reimprimir CN dentro de la ventana Despachos expedicion.',
            'feature.despachos.expedicion.confirm' => 'Controla el boton Intervenir despacho dentro de la ventana Despachos expedicion.',
            'feature.despachos.expedicion.restore' => 'Controla el boton Volver a apertura dentro de la ventana Despachos expedicion.',
            'feature.despachos.expedicion.edit' => 'Controla Abrir modal y Guardar intervencion dentro de la ventana Despachos expedicion.',
            'feature.despachos.admitidos.assign' => 'Controla Buscar sacas, escaneo y Quitar receptaculos dentro de la ventana Despachos admitidos.',
            'feature.despachos.admitidos.confirm' => 'Controla Abrir el modal Admitir despachos y el boton Recibir despachos.',
            'feature.sacas.index.create' => 'Controla el boton Nuevo y Crear dentro de la ventana Sacas.',
            'feature.sacas.index.edit' => 'Controla el boton Editar y Guardar cambios dentro de la ventana Sacas.',
            'feature.sacas.index.delete' => 'Controla el boton Eliminar dentro de la ventana Sacas.',
            'feature.sacas.index.assign' => 'Controla seleccionar cod especial, Anadir a la lista y Quitar filas dentro de la ventana Sacas.',
            'feature.sacas.index.confirm' => 'Controla el boton Cerrar despacho dentro de la ventana Sacas.',
        ];

        if (isset($specialHints[$permissionName])) {
            return $specialHints[$permissionName];
        }

        if (self::permissionType($permissionName) === 'route') {
            return 'Solo controla la apertura de esta ventana. Los botones se administran aparte.';
        }

        if ($moduleKey !== '' && $actionKey !== '') {
            return 'Controla una accion especifica dentro del modulo '.self::humanize($moduleKey).'.';
        }

        return null;
    }

    private static function humanize(string $value): string
    {
        return Str::headline(str_replace(['.', '-', '_'], ' ', $value));
    }

    private static function shouldHidePermissionFromMatrix(string $permissionName): bool
    {
        $legacyFeaturePrefixes = [
            'feature.despachos.',
            'feature.paquetes-contrato.',
            'feature.paquetes-ems.',
            'feature.paquetes-ordinarios.',
            'feature.paquetes-certificados.',
            'feature.carteros.',
            'feature.sacas.',
        ];

        foreach ($legacyFeaturePrefixes as $prefix) {
            if (! str_starts_with($permissionName, $prefix)) {
                continue;
            }

            [$moduleKey, $actionKey] = self::splitPermissionName($permissionName);

            if (! str_contains($moduleKey, '.')) {
                return true;
            }

            return ! self::isAllowedFeatureActionForWindow($moduleKey, $actionKey);
        }

        return false;
    }

    private static function isSupportedWindowFeaturePermission(string $permissionName, string $routeName): bool
    {
        [, $actionKey] = self::splitPermissionName($permissionName);

        return self::isAllowedFeatureActionForWindow($routeName, $actionKey);
    }

    private static function isAllowedFeatureActionForWindow(string $routeName, string $actionKey): bool
    {
        $allowedActions = self::WINDOW_FEATURE_ALLOWLIST[$routeName] ?? null;

        if ($allowedActions === null) {
            return true;
        }

        return in_array($actionKey, $allowedActions, true);
    }

    /**
     * @param  array<int, string>  $needles
     */
    private static function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}

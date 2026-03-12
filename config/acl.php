<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Permission Middleware
    |--------------------------------------------------------------------------
    |
    | If enabled, every authenticated route with a name can be protected by
    | a permission with the same route name (for example: users.index).
    |
    */
    'route_permission' => [
        'enabled' => true,
        'allow_when_permission_missing' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin Role
    |--------------------------------------------------------------------------
    |
    | This role bypasses route permission checks.
    |
    */
    'super_admin_role' => 'administrador',

    /*
    |--------------------------------------------------------------------------
    | Excluded Route Names
    |--------------------------------------------------------------------------
    |
    | These route names are excluded from automatic permission discovery and
    | from route-level permission checks.
    |
    */
    'excluded_route_permissions' => [
        'welcome',
        'tracking.demo',
        'api.busqueda.ems-eventos',
        'api.busqueda.captcha',
        'api.public.zona-paquete',
        'login',
        'register',
        'password.request',
        'password.email',
        'password.reset',
        'password.store',
        'password.confirm',
        'password.update',
        'verification.notice',
        'verification.verify',
        'verification.send',
        'logout',
        'role-has-permissions.index',
        'role-has-permissions.create',
        'role-has-permissions.store',
        'role-has-permissions.edit',
        'role-has-permissions.update',
        'role-has-permissions.destroy',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'acl.livewire-actions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Add non-route permissions here (for button-only actions, etc).
    |
    */
    'custom_permissions' => [],

    /*
    |--------------------------------------------------------------------------
    | Automatic ACL Sync
    |--------------------------------------------------------------------------
    |
    | Keeps route and action permissions synchronized in production without
    | requiring manual seeding after each deployment.
    |
    */
    'sync' => [
        'enabled' => true,
        'ttl_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Behaviour
    |--------------------------------------------------------------------------
    */
    'ui' => [
        'auto_hide_livewire_actions' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Roles
    |--------------------------------------------------------------------------
    */
    'default_roles' => [
        'administrador',
        'gestor',
        'encargado',
        'auxiliar',
        'cartero',
        'expedicion',
        'encaminamiento',
        'empresa',
        'admisiones',
    ],

    /*
    |--------------------------------------------------------------------------
    | Initial Role Templates (Wildcard Patterns)
    |--------------------------------------------------------------------------
    |
    | Used by AclSeeder to grant initial permissions. Roles with existing
    | permissions are not overwritten (except super admin).
    |
    */
    'role_templates' => [
        'administrador' => ['*'],
        'gestor' => [
            'dashboard*',
            'reportes.*',
            'paquetes-ems.*',
            'paquetes-certificados.*',
            'paquetes-ordinarios.*',
            'paquetes-contrato.*',
            'area-contratos.*',
            'indicadores.*',
            'despachos.*',
            'sacas.*',
            'carteros.*',
            'api.carteros.*',
            'eventos*',
            'auditoria.index',
            'eventos-auditoria.index',
            'tarifa-contrato.*',
            'importar.paquets*',
        ],
        'encargado' => [
            'dashboard*',
            'reportes.*',
            'paquetes-ems.*',
            'paquetes-certificados.*',
            'paquetes-ordinarios.*',
            'paquetes-contrato.*',
            'area-contratos.*',
            'indicadores.*',
            'despachos.*',
            'sacas.index',
            'carteros.*',
            'api.carteros.*',
            'eventos*',
        ],
        'auxiliar' => [
            'dashboard',
            'reportes.index',
            'reportes.scope',
            'paquetes-ems.index',
            'paquetes-ems.create',
            'paquetes-ems.boleta',
            'paquetes-ems.ventanilla',
            'paquetes-certificados.*',
            'paquetes-ordinarios.*',
            'paquetes-contrato.index',
            'paquetes-contrato.create',
            'paquetes-contrato.store',
            'despachos.abiertos',
            'despachos.admitidos',
        ],
        'cartero' => [
            'dashboard',
            'carteros.*',
            'api.carteros.*',
            'paquetes-contrato.cartero',
            'paquetes-contrato.entregados',
            'paquetes-contrato.reporte*',
        ],
        'expedicion' => [
            'dashboard',
            'despachos.*',
            'sacas.index',
            'eventos-despacho.index',
        ],
        'encaminamiento' => [
            'dashboard',
            'paquetes-ems.almacen',
            'paquetes-ems.en-transito',
            'paquetes-ems.recibir-regional',
            'paquetes-ordinarios.despacho',
            'paquetes-ordinarios.almacen',
            'despachos.abiertos',
            'despachos.expedicion',
        ],
        'empresa' => [
            'dashboard',
            'paquetes-contrato.index',
            'paquetes-contrato.create',
            'paquetes-contrato.create-con-tarifa',
            'paquetes-contrato.store',
            'paquetes-contrato.store-con-tarifa',
            'paquetes-contrato.reporte*',
            'paquetes-contrato.entregados',
        ],
        'admisiones' => [
            'dashboard',
            'paquetes-ems.index',
            'paquetes-ems.create',
            'paquetes-ems.ventanilla',
            'paquetes-ems.contrato-rapido.*',
            'paquetes-ems.boleta',
            'paquetes-contrato.create',
            'paquetes-contrato.store',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Module Labels
    |--------------------------------------------------------------------------
    */
    'module_labels' => [
        'users' => 'Usuarios',
        'roles' => 'Roles',
        'permissions' => 'Permisos',
        'role-has-permissions' => 'Accesos',
        'dashboard' => 'Dashboard',
        'reportes' => 'Reportes',
        'paquetes-ems' => 'Paquetes EMS',
        'paquetes-certificados' => 'Paquetes Certificados',
        'paquetes-ordinarios' => 'Paquetes Ordinarios',
        'paquetes-contrato' => 'Paquetes Contrato',
        'area-contratos' => 'Area Contratos',
        'indicadores' => 'Indicadores',
        'despachos' => 'Despachos',
        'sacas' => 'Sacas',
        'carteros' => 'Carteros',
        'api.carteros' => 'API Carteros',
        'empresas' => 'Empresas',
        'codigo-empresa' => 'Codigo Empresa',
        'eventos' => 'Eventos',
        'eventos-ems' => 'Eventos EMS',
        'eventos-certi' => 'Eventos Certificados',
        'eventos-ordi' => 'Eventos Ordinarios',
        'eventos-despacho' => 'Eventos Despachos',
        'eventos-contrato' => 'Eventos Contratos',
        'eventos-auditoria' => 'Eventos Auditoria',
        'auditoria' => 'Auditoria',
        'servicios' => 'Servicios',
        'origenes' => 'Origenes',
        'destinos' => 'Destinos',
        'pesos' => 'Pesos',
        'tarifario' => 'Tarifario',
        'tarifa-contrato' => 'Tarifa Contrato',
        'importar' => 'Importaciones',
        'backups' => 'Respaldos',
    ],
];

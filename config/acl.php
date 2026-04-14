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
        'clientes.login',
        'auth.google.redirect',
        'auth.google.callback',
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
        'paquetes-certificados.baja-pdf',
        'paquetes-certificados.rezago-pdf',
        'api.carteros.distribucion',
        'api.carteros.asignados',
        'api.carteros.cartero',
        'api.carteros.provincia',
        'api.carteros.devolucion',
        'api.carteros.domicilio',
        'api.carteros.users',
        'api.carteros.asignar',
        'api.carteros.registrar-guia',
        'api.carteros.devolver-almacen',
        'api.carteros.aceptar-paquetes',
        'carteros.distribucion.report',
        'tarifario.pdf',
        'tarifario.global-excel',
        'despachos.expedicion.pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Add non-route permissions here (for button-only actions, etc).
    |
    */
    'custom_permissions' => [
        'pulse',
        'feature.carteros.distribucion.assign',
        'feature.carteros.distribucion.selfassign',
        'feature.carteros.cartero.guide',
        'feature.carteros.cartero.province',
        'feature.carteros.cartero.deliver',
        'feature.carteros.devolucion.restore',
        'feature.carteros.entrega.deliver',
        'feature.carteros.entrega.attempt',
        'feature.paquetes-contrato.index.report',
        'feature.paquetes-contrato.index.print',
        'feature.paquetes-contrato.index.create',
        'feature.paquetes-contrato.index.manage',
        'feature.paquetes-contrato.almacen.report',
        'feature.paquetes-contrato.almacen.print',
        'feature.paquetes-contrato.recoger-envios.print',
        'feature.paquetes-contrato.cartero.print',
        'feature.paquetes-contrato.create.create',
        'feature.paquetes-contrato.create.manage',
        'feature.paquetes-contrato.create-con-tarifa.create',
        'feature.paquetes-contrato.entregados.print',
        'feature.paquetes-contrato.entregados.export',
        'feature.paquetes-ems.contrato-rapido.create.create',
        'feature.paquetes-ems.contrato-rapido.create.save',
        'feature.paquetes-ems.contrato-rapido.create.delete',
        'feature.paquetes-ems.almacen.registercontract',
        'feature.paquetes-ems.almacen.weighcontract',
        'feature.paquetes-ems.almacen.weightiktoker',
        'feature.paquetes-ems.almacen.sendventanilla',
        'feature.paquetes-ems.almacen.sendregional',
        'feature.paquetes-ems.almacen.reprintcn33',
        'feature.paquetes-ems.devolucion.deliver',
        'feature.paquetes-ems.devolucion.print',
        'feature.empresas.export',
        'log-viewer.index',
        'feature.paquetes-ems.solicitudes.index.create',
        'feature.paquetes-ems.solicitudes.index.assign',
        'feature.paquetes-ems.solicitudes.index.print',
        'feature.despachos.abiertos.create',
        'feature.despachos.abiertos.edit',
        'feature.despachos.abiertos.delete',
        'feature.despachos.abiertos.opensacas',
        'feature.despachos.abiertos.confirm',
        'feature.despachos.abiertos.restore',
        'feature.despachos.expedicion.print',
        'feature.despachos.expedicion.confirm',
        'feature.despachos.expedicion.restore',
        'feature.despachos.expedicion.edit',
        'feature.despachos.admitidos.assign',
        'feature.despachos.admitidos.confirm',
        'feature.dashboard.facturacion',
        'feature.sacas.index.create',
        'feature.sacas.index.edit',
        'feature.sacas.index.delete',
        'feature.sacas.index.assign',
        'feature.sacas.index.confirm',
        'feature.tarifa-contrato.create',
        'feature.tarifa-contrato.duplicate',
        'feature.tarifa-contrato.edit',
        'feature.tarifa-contrato.delete',
        'feature.tarifa-contrato.save',
        'feature.tarifa-contrato.import',
        'feature.tarifa-contrato.export',
    ],

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
        'auto_hide_livewire_actions' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Roles
    |--------------------------------------------------------------------------
    */
    'default_roles' => [
        'administrador',
    ],

    /*
    |--------------------------------------------------------------------------
    | Legacy Role Aliases
    |--------------------------------------------------------------------------
    |
    | Maps legacy role names from the previous ACL to the canonical role names
    | used by the current permission templates.
    |
    */
    'legacy_role_aliases' => [
        'ADMINISTRADOR' => 'administrador',
        'ENCARGADO EMS' => 'encargado',
        'ENCARGADO URBANO' => 'encargado',
        'ENCARGADO TRATAMIENTO' => 'encargado',
        'AUXILIAR URBANO' => 'auxiliar',
        'AUXILIAR URBANO 7' => 'auxiliar',
        'AUXILIAR TRATAMIENTO' => 'auxiliar',
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
        // En web mantenemos solo el rol tecnico base.
        // Los roles operativos se administran manualmente desde el panel.
        'administrador' => ['*'],
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
        'malencaminados' => 'Malencaminados',
        'mis-ventas' => 'Mis Ventas',
        'paquetes-ems' => 'Paquetes EMS',
        'paquetes-ems.almacen-admisiones' => 'Almacen admisiones',
        'paquetes-ems.solicitudes.index' => 'Solicitudes EMS',
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
        'bitacoras' => 'Bitacoras',
        'importar' => 'Importaciones',
        'backups' => 'Respaldos',
    ],
];

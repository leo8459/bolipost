<?php

return [
    'route_permission' => [
        'enabled' => true,
        'allow_when_permission_missing' => false,
    ],

    'excluded_route_permissions' => [
        'clientes.login',
        'clientes.login.store',
        'clientes.register',
        'clientes.register.store',
        'clientes.logout',
        'clientes.profile.complete',
        'clientes.profile.complete.store',
        'auth.google.redirect',
        'auth.google.callback',
    ],

    'default_roles' => [
        'tiktokero',
    ],

    'role_templates' => [
        'tiktokero' => [
            'clientes.dashboard',
            'clientes.solicitudes.*',
        ],
    ],

    'module_labels' => [
        'clientes.dashboard' => 'Panel Cliente',
        'clientes.solicitudes' => 'Solicitudes Cliente',
    ],

    'sync' => [
        'enabled' => true,
        'ttl_seconds' => 300,
    ],
];

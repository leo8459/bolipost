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

    'route_permissions' => [
        'clientes.dashboard' => [
            'label' => 'Acceso al panel cliente',
            'module' => 'clientes.dashboard',
        ],
        'clientes.solicitudes.create' => [
            'label' => 'Acceso a nueva solicitud',
            'module' => 'clientes.solicitudes',
        ],
        'clientes.solicitudes.history' => [
            'label' => 'Acceso a historial de solicitudes',
            'module' => 'clientes.solicitudes',
        ],
    ],

    'route_permission_aliases' => [
        'clientes.solicitudes.index' => 'clientes.solicitudes.create',
        'clientes.solicitudes.store' => 'clientes.solicitudes.create',
    ],

    'feature_permissions' => [
        'feature.clientes.dashboard.create' => [
            'label' => 'Boton Nueva solicitud',
            'module' => 'clientes.dashboard',
            'hint' => 'Controla el boton Nueva solicitud dentro del panel cliente.',
        ],
        'feature.clientes.dashboard.history' => [
            'label' => 'Boton Mis solicitudes',
            'module' => 'clientes.dashboard',
            'hint' => 'Controla el boton Mis solicitudes dentro del panel cliente.',
        ],
    ],

    'default_roles' => [
        'tiktokero',
    ],

    'security' => [
        'verified_google_email_required' => (bool) env('CLIENTE_REQUIRE_VERIFIED_GOOGLE_EMAIL', true),
        'require_existing_account' => (bool) env('CLIENTE_REQUIRE_EXISTING_ACCOUNT', false),
        'allowed_google_domains' => array_values(array_filter(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            explode(',', (string) env('CLIENTE_ALLOWED_GOOGLE_DOMAINS', ''))
        ))),
    ],

    'role_templates' => [
        'tiktokero' => [
            'clientes.dashboard',
            'clientes.solicitudes.create',
            'clientes.solicitudes.history',
            'feature.clientes.dashboard.create',
            'feature.clientes.dashboard.history',
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

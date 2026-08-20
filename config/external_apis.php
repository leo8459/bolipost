<?php

return [
    'catalog' => [
        'paquetes-contactos:certi:read' => [
            'name' => 'Consultar paquetes CERTI',
            'description' => 'Muestra el código y los datos disponibles del destinatario de los envíos certificados.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-certificate',
            'color' => 'primary',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/paquetes-contactos/certi', 'example' => '?per_page=50&page=1'],
            ],
        ],
        'paquetes-contactos:contrato:read' => [
            'name' => 'Consultar paquetes de contrato',
            'description' => 'Muestra códigos, nombres y teléfonos del remitente y destinatario de paquetes por contrato.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-file-contract',
            'color' => 'primary',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/paquetes-contactos/contrato', 'example' => '?per_page=50&page=1'],
            ],
        ],
        'paquetes-contactos:ems:read' => [
            'name' => 'Consultar paquetes EMS',
            'description' => 'Muestra códigos, nombres y teléfonos del remitente y destinatario de envíos EMS.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-shipping-fast',
            'color' => 'primary',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/paquetes-contactos/ems', 'example' => '?per_page=50&page=1'],
            ],
        ],
        'paquetes-contactos:ordinario:read' => [
            'name' => 'Consultar paquetes ordinarios',
            'description' => 'Muestra el código y los datos disponibles del destinatario de envíos ordinarios.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-box',
            'color' => 'primary',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/paquetes-contactos/ordinario', 'example' => '?per_page=50&page=1'],
            ],
        ],
        'paquetes-contactos:solicitud:read' => [
            'name' => 'Consultar solicitudes Delivery Express',
            'description' => 'Muestra códigos, nombres y teléfonos del remitente y destinatario de las solicitudes.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-clipboard-list',
            'color' => 'primary',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/paquetes-contactos/solicitud', 'example' => '?per_page=50&page=1'],
            ],
        ],
        'direcciones-destino:read' => [
            'name' => 'Consultar direcciones de entrega',
            'description' => 'Permite buscar y revisar direcciones registradas en EMS, contratos, CERTI y ordinarios.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-map-marker-alt',
            'color' => 'info',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/direcciones-destino', 'example' => '?tipo=ems&per_page=25&page=1'],
                ['method' => 'GET', 'path' => '/api/direcciones-destino/{tipo}/{id}', 'example' => ''],
            ],
        ],
        'direcciones-destino:update' => [
            'name' => 'Actualizar direcciones de entrega',
            'description' => 'Autoriza modificar dirección, ciudad, referencia y teléfono del destinatario.',
            'access' => 'Escritura',
            'icon' => 'fas fa-map-marked-alt',
            'color' => 'warning',
            'endpoints' => [
                ['method' => 'PATCH', 'path' => '/api/direcciones-destino/{tipo}/{id}', 'example' => ''],
            ],
        ],
    ],

    'legacy_names' => [
        'paquetes-contactos:read' => 'Todos los paquetes y contactos (permiso anterior)',
    ],
];

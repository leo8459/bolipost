<?php

return [
    'catalog' => [
        'chasqui:login' => [
            'name' => 'INICIO SESION CHASQUIAPP',
            'description' => 'Valida el alias y la contrasena de un cartero habilitado y devuelve su Bearer Token personal.',
            'access' => 'Autenticacion',
            'icon' => 'fas fa-sign-in-alt',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/integraciones/chasqui/login',
                    'example' => '',
                    'body' => [
                        'alias' => 'cartero.chasqui',
                        'password' => 'ClaveSegura123',
                        'device_name' => 'ChasquiApp Android',
                    ],
                    'response' => [
                        'message' => 'Inicio de sesion ChasquiApp correcto.',
                        'token_type' => 'Bearer',
                        'access_token' => 'TOKEN_PERSONAL_DEL_CARTERO',
                        'user' => [
                            'id' => 1,
                            'name' => 'Cartero Chasqui',
                            'alias' => 'cartero.chasqui',
                            'ciudad' => 'LA PAZ',
                            'role_id' => 5,
                            'role' => 'cartero_ems',
                            'roles' => ['cartero_ems'],
                        ],
                    ],
                ],
            ],
        ],
        'chasqui:paquetes:read' => [
            'name' => 'CHASQUIAPP - Paquetes asignados al cartero',
            'description' => 'Lista solamente los paquetes activos asignados al cartero autenticado. Requiere Authorization Bearer con el token personal y X-API-Token con la credencial de integracion.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-boxes',
            'color' => 'success',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/chasqui/paquetes-asignados',
                    'example' => '?per_page=25&page=1&search=EE123',
                    'response' => [
                        'data' => [
                            [
                                'id' => 1,
                                'tipo_paquete' => 'EMS',
                                'codigo' => 'EE123456789BO',
                                'destinatario' => 'Destinatario Demo',
                                'estado' => 'CARTERO',
                            ],
                        ],
                        'meta' => [
                            'page' => 1,
                            'per_page' => 25,
                            'total' => 1,
                            'last_page' => 1,
                        ],
                    ],
                ],
            ],
        ],
        'chasqui:paquetes:assign' => [
            'name' => 'CHASQUIAPP - Asignar paquetes al cartero',
            'description' => 'Asigna al cartero autenticado los paquetes indicados, respetando estado, regional, conflictos y eventos de distribucion. Requiere Authorization Bearer y X-API-Token.',
            'access' => 'Escritura',
            'icon' => 'fas fa-user-check',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/chasqui/paquetes/asignar',
                    'example' => '',
                    'body' => [
                        'items' => [
                            ['id' => 15, 'tipo_paquete' => 'EMS'],
                            ['id' => 28, 'tipo_paquete' => 'CONTRATO'],
                        ],
                    ],
                    'response' => [
                        'message' => 'Paquetes asignados correctamente en estado CARTERO.',
                        'updated' => [
                            'ems' => 1,
                            'contrato' => 1,
                            'total' => 2,
                        ],
                    ],
                ],
            ],
        ],
        'siop:login' => [
            'name' => 'INICIO SESION SIOP',
            'description' => 'Valida el alias y la contrasena de un usuario de SIOP y devuelve un Bearer Token personal para la aplicacion.',
            'access' => 'Autenticacion',
            'icon' => 'fas fa-sign-in-alt',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/integraciones/siop/login',
                    'example' => '',
                    'body' => [
                        'alias' => 'usuario.siop',
                        'password' => 'ClaveSegura123',
                        'device_name' => 'Aplicacion SIOP',
                    ],
                    'response' => [
                        'message' => 'Inicio de sesion SIOP correcto.',
                        'token_type' => 'Bearer',
                        'access_token' => 'TOKEN_PERSONAL_DEL_USUARIO_SIOP',
                        'user' => [
                            'id' => 1,
                            'name' => 'Usuario SIOP',
                            'alias' => 'usuario.siop',
                        ],
                    ],
                ],
            ],
        ],
        'clientes:create' => [
            'name' => 'Crear usuario Delivery Express',
            'description' => 'Registra un nuevo cliente y devuelve su Bearer Token personal para utilizar las APIs de Delivery Express.',
            'access' => 'Escritura',
            'icon' => 'fas fa-user-plus',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/integraciones/clientes',
                    'example' => '',
                    'body' => [
                        'name' => 'Cliente API',
                        'email' => 'cliente.api@example.com',
                        'password' => 'ClaveSegura123',
                        'password_confirmation' => 'ClaveSegura123',
                        'device_name' => 'Postman Delivery Express',
                    ],
                    'response' => [
                        'message' => 'Cliente registrado correctamente.',
                        'token_type' => 'Bearer',
                        'access_token' => 'TOKEN_PERSONAL_DEL_CLIENTE',
                    ],
                ],
            ],
        ],
        'clientes:google-login' => [
            'name' => 'Iniciar sesion Delivery Express con Google',
            'description' => 'Valida el ID token de Google mediante una integracion autorizada y devuelve el Bearer Token personal del cliente.',
            'access' => 'Autenticacion',
            'icon' => 'fab fa-google',
            'color' => 'danger',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/integraciones/clientes/google-login',
                    'example' => '',
                    'body' => [
                        'id_token' => 'ID_TOKEN_ENTREGADO_POR_GOOGLE',
                        'device_name' => 'Postman Delivery Express',
                    ],
                    'response' => [
                        'message' => 'Inicio de sesion con Google correcto.',
                        'token_type' => 'Bearer',
                        'access_token' => 'TOKEN_PERSONAL_DEL_CLIENTE',
                    ],
                ],
            ],
        ],
        'clientes:login' => [
            'name' => 'Iniciar sesion Delivery Express con usuario y contrasena',
            'description' => 'Valida el correo y la contrasena del cliente y devuelve su Bearer Token personal.',
            'access' => 'Autenticacion',
            'icon' => 'fas fa-sign-in-alt',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/integraciones/clientes/login',
                    'example' => '',
                    'body' => [
                        'email' => 'cliente.api@example.com',
                        'password' => 'ClaveSegura123',
                        'device_name' => 'Postman Delivery Express',
                    ],
                    'response' => [
                        'message' => 'Inicio de sesion correcto.',
                        'token_type' => 'Bearer',
                        'access_token' => 'TOKEN_PERSONAL_DEL_CLIENTE',
                    ],
                ],
            ],
        ],
        'clientes:solicitudes:create' => [
            'name' => 'Crear solicitud Delivery Express para un cliente',
            'description' => 'Registra una solicitud para el cliente indicado en la URL.',
            'access' => 'Escritura',
            'icon' => 'fas fa-plus-circle',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/integraciones/clientes/{cliente}/solicitudes',
                    'example' => '',
                    'body' => [
                        'servicio_extra_id' => 1,
                        'origen' => 'LA PAZ',
                        'destino_id' => 2,
                        'cantidad' => 1,
                        'contenido' => 'Documentos',
                        'nombre_remitente' => 'Cliente Demo',
                        'carnet' => '1234567',
                        'telefono_remitente' => '70000000',
                        'nombre_destinatario' => 'Destinatario Demo',
                        'telefono_destinatario' => '71111111',
                        'direccion_recojo' => 'Zona Central',
                        'direccion_entrega' => 'Avenida Principal',
                    ],
                    'response' => [
                        'message' => 'Solicitud registrada correctamente.',
                        'solicitud' => ['codigo_solicitud' => 'SOL00000001'],
                    ],
                ],
            ],
        ],
        'clientes:solicitudes:read' => [
            'name' => 'Ver solicitudes Delivery Express de un cliente',
            'description' => 'Lista las solicitudes generadas por el cliente indicado en la URL.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-clipboard-list',
            'color' => 'success',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/integraciones/clientes/{cliente}/solicitudes', 'example' => '?per_page=50&page=1'],
            ],
        ],
        'solicitudes-clientes:read' => [
            'name' => 'Ver todos los paquetes de solicitudes de clientes',
            'description' => 'Lista todas las solicitudes Delivery Express de todos los clientes, con paginacion, datos del cliente, estado, servicio y destino.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-boxes',
            'color' => 'success',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/integraciones/solicitudes-clientes',
                    'example' => '?per_page=50&page=1',
                    'response' => [
                        'message' => 'Solicitudes de clientes obtenidas correctamente.',
                        'solicitudes' => [
                            'current_page' => 1,
                            'data' => [
                                [
                                    'id' => 1,
                                    'cliente_id' => 1,
                                    'codigo_solicitud' => 'SL00000001LP',
                                    'nombre_remitente' => 'CLIENTE DEMO',
                                    'nombre_destinatario' => 'DESTINATARIO DEMO',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'solicitudes-clientes:create' => [
            'name' => 'Crear solicitud de cliente',
            'description' => 'Crea una solicitud Delivery Express para el cliente indicado mediante cliente_id.',
            'access' => 'Escritura',
            'icon' => 'fas fa-plus-square',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/integraciones/solicitudes-clientes',
                    'example' => '',
                    'body' => [
                        'cliente_id' => 1,
                        'servicio_extra_id' => 1,
                        'origen' => 'LA PAZ',
                        'destino_id' => 2,
                        'cantidad' => 1,
                        'contenido' => 'Documentos',
                        'nombre_remitente' => 'Cliente Demo',
                        'carnet' => '1234567',
                        'telefono_remitente' => '70000000',
                        'nombre_destinatario' => 'Destinatario Demo',
                        'telefono_destinatario' => '71111111',
                        'direccion_recojo' => 'Zona Central',
                        'direccion_entrega' => 'Avenida Principal',
                    ],
                    'response' => [
                        'message' => 'Solicitud registrada correctamente.',
                        'solicitud' => ['codigo_solicitud' => 'SL00000001LP'],
                    ],
                ],
            ],
        ],
        'paquetes-eventos:read' => [
            'name' => 'EVENTOS SIOP',
            'description' => 'Lista paquetes ordinarios, EMS, de contrato, certificados y solicitudes de clientes, incluyendo todo el historial de eventos de cada envio.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-route',
            'color' => 'success',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/paquetes-eventos',
                    'example' => '?per_page=50&page=1',
                    'response' => [
                        'data' => [
                            [
                                'tipo' => 'ems',
                                'codigo' => 'EE123456789BO',
                                'estado' => ['id' => 2, 'nombre' => 'EN TRANSITO'],
                                'cantidad_eventos' => 2,
                                'eventos' => [
                                    [
                                        'evento_id' => 1,
                                        'nombre' => 'Envio admitido.',
                                        'usuario' => ['id' => 4, 'nombre' => 'Operador'],
                                        'fecha' => '2026-08-26T10:30:00-04:00',
                                    ],
                                ],
                            ],
                        ],
                        'paginacion' => [
                            'pagina_actual' => 1,
                            'por_pagina' => 50,
                            'total_registros' => 1,
                        ],
                        'tipos_incluidos' => ['certi', 'contrato', 'ems', 'ordinario', 'solicitud'],
                    ],
                ],
            ],
        ],
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
                [
                    'method' => 'PATCH',
                    'path' => '/api/direcciones-destino/{tipo}/{id}',
                    'example' => '',
                    'body' => [
                        'direccion' => 'Avenida Principal Nro. 123',
                        'ciudad' => 'LA PAZ',
                        'referencia' => 'Frente a la plaza',
                        'telefono' => '70000000',
                    ],
                    'response' => ['message' => 'Direccion actualizada correctamente.'],
                ],
            ],
        ],
    ],

    'legacy_names' => [
        'paquetes-contactos:read' => 'Todos los paquetes y contactos (permiso anterior)',
    ],

];

<?php

return [
    'catalog' => [
        'packgo:mobile-auth' => [
            'name' => 'PACKGO - Autenticacion movil',
            'description' => 'Permite iniciar, validar y cerrar sesion desde PackGo. Todas las solicitudes deben enviar X-API-Token con la credencial de integracion.',
            'access' => 'Autenticacion',
            'icon' => 'fas fa-mobile-alt',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/mobile/login',
                    'example' => '',
                    'body' => [
                        'login' => 'conductor.alias',
                        'password' => 'ClaveSegura123',
                        'device_name' => 'PackGo Android',
                        'device_id' => 'packgo-dispositivo-unico',
                    ],
                    'response' => [
                        'success' => true,
                        'auth_mode' => 'session',
                        'user' => [
                            'id' => 1,
                            'name' => 'Conductor Demo',
                            'role' => 'driver',
                            'driver_id' => 1,
                        ],
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/mobile/me',
                    'example' => '',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/mobile/bootstrap',
                    'example' => '',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/mobile/logout',
                    'example' => '',
                ],
            ],
        ],
        'packgo:alerts-support-sync' => [
            'name' => 'PACKGO - Alertas y soporte',
            'description' => 'Sincroniza alertas, tipos y solicitudes de mantenimiento utilizadas por PackGo.',
            'access' => 'Lectura y escritura',
            'icon' => 'fas fa-tools',
            'color' => 'warning',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/mobile/maintenance_alerts', 'example' => '?per_page=50&page=1'],
                ['method' => 'GET', 'path' => '/api/mobile/maintenance_types', 'example' => '?per_page=50&page=1'],
                [
                    'method' => 'GET',
                    'path' => '/api/mobile/maintenance-requests',
                    'example' => '?per_page=50&page=1',
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/mobile/maintenance-requests',
                    'example' => '',
                    'body' => [
                        'vehicle_id' => 1,
                        'maintenance_type_id' => 1,
                        'descripcion' => 'Revision solicitada desde PackGo',
                    ],
                ],
            ],
        ],
        'packgo:fuel-qr' => [
            'name' => 'PACKGO - Combustible y QR',
            'description' => 'Permite registrar combustible, decodificar QR y consultar facturas SIAT desde PackGo.',
            'access' => 'Lectura y escritura',
            'icon' => 'fas fa-gas-pump',
            'color' => 'success',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/fuel-logs', 'example' => '?per_page=50&page=1'],
                ['method' => 'POST', 'path' => '/api/fuel-logs', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/fuel-logs/scrape-from-qr', 'example' => '', 'body' => ['url' => 'https://pilotosiat.impuestos.gob.bo/consulta/QR?...']],
                ['method' => 'POST', 'path' => '/api/qr/decode-from-image', 'example' => '', 'body' => ['image_base64' => 'BASE64_DE_LA_IMAGEN']],
                ['method' => 'PUT', 'path' => '/api/siat/consulta-factura', 'example' => ''],
            ],
        ],
        'bitacoras:create' => [
            'name' => 'CREAR BITACORA',
            'description' => 'Crea registros de bitacora vehicular con los mismos datos y validaciones principales de la vista de Bitacora Vehicular.',
            'access' => 'Escritura con foto',
            'icon' => 'fas fa-clipboard-check',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/bitacoras/vehiculos',
                    'example' => '',
                    'response' => [
                        'count' => 2,
                        'data' => [
                            [
                                'id' => 1,
                                'placa' => 'ABC-123',
                                'kilometraje_actual' => 12500.50,
                                'activo' => true,
                                'tiene_asignacion_activa' => true,
                                'asignacion_activa' => [
                                    'driver_id' => 1,
                                    'conductor' => 'Conductor Demo',
                                ],
                            ],
                        ],
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/bitacoras/conductores',
                    'example' => '',
                    'response' => [
                        'count' => 3,
                        'data' => [
                            [
                                'id' => 1,
                                'nombre' => 'Conductor Demo',
                                'activo' => true,
                                'tiene_asignacion_activa' => false,
                                'asignacion_activa' => null,
                            ],
                        ],
                    ],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/bitacoras',
                    'example' => '',
                    'body_type' => 'form-data',
                    'body' => [
                        'vehicles_id' => 1,
                        'drivers_id' => 1,
                        'fecha' => '2026-09-10',
                        'kilometraje_salida' => 12500.50,
                        'kilometraje_recorrido' => 18.75,
                        'cantidad_paquetes' => 24,
                        'recorrido_inicio' => 'Oficina Central, La Paz',
                        'latitud_inicio' => -16.495545,
                        'logitud_inicio' => -68.133592,
                        'recorrido_destino' => 'Sucursal Miraflores, La Paz',
                        'latitud_destino' => -16.503105,
                        'logitud_destino' => -68.121558,
                        'fuel_log_id' => null,
                        'firma_digital' => null,
                        'odometro_photo' => '@foto_odometro.jpg (tipo File)',
                    ],
                    'response' => [
                        'message' => 'Registro de bitacora creado correctamente.',
                        'data' => [
                            'id' => 1,
                            'kilometraje_llegada' => '12519.25',
                            'kilometraje_recorrido' => '18.75',
                        ],
                    ],
                ],
            ],
        ],
        'bitacoras:read' => [
            'name' => 'VER BITACORAS',
            'description' => 'Consulta las bitacoras vehiculares activas, con filtros por vehiculo, conductor, fechas y texto.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-clipboard-list',
            'color' => 'info',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/bitacoras',
                    'example' => '?per_page=20&page=1&vehicle_id=1&date_from=2026-09-01&date_to=2026-09-10',
                    'response' => [
                        'data' => [],
                        'current_page' => 1,
                        'per_page' => 20,
                        'total' => 0,
                    ],
                ],
            ],
        ],
        'gasolinas:create' => [
            'name' => 'CREAR GASOLINA',
            'description' => 'Crea registros de combustible con vehiculo, conductor, factura, cantidad, precio y gasolinera.',
            'access' => 'Escritura',
            'icon' => 'fas fa-gas-pump',
            'color' => 'success',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/gasolinas',
                    'example' => '',
                    'body_type' => 'form-data',
                    'body' => [
                        'vehicle_id' => 1,
                        'driver_id' => 1,
                        'numero_factura' => 'FAC-001',
                        'nombre_cliente' => 'Correos de Bolivia',
                        'fecha_emision' => '2026-09-10 15:00:00',
                        'cantidad' => 20.5,
                        'precio_unitario' => 3.74,
                        'razon_social_emisor' => 'Gasolinera Central',
                        'nit_emisor' => '123456789',
                        'direccion_emisor' => 'La Paz',
                        'invoice_photo' => '@factura.jpg (opcional, tipo File)',
                    ],
                ],
            ],
        ],
        'gasolinas:read' => [
            'name' => 'VER GASOLINA',
            'description' => 'Consulta registros de combustible con factura, gasolinera, vehiculo y conductor.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-gas-pump',
            'color' => 'info',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/gasolinas',
                    'example' => '?per_page=20&page=1&vehicle_id=1&driver_id=1&date_from=2026-09-01&date_to=2026-09-10',
                ],
            ],
        ],
        'mantenimientos:create' => [
            'name' => 'SOLICITAR MANTENIMIENTOS',
            'description' => 'Permite consultar vehiculos, conductores y tipos de mantenimiento disponibles, y registrar una solicitud igual que en el modulo de citas de mantenimiento.',
            'access' => 'Escritura con documentos',
            'icon' => 'fas fa-tools',
            'color' => 'warning',
            'created_at' => '2026-09-11 11:08:00',
            'selection_endpoint' => '/api/mantenimientos',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/mantenimientos/vehiculos',
                    'example' => '',
                    'response' => [
                        'count' => 1,
                        'data' => [[
                            'id' => 1,
                            'placa' => 'ABC-123',
                            'marca' => 'Toyota',
                            'modelo' => 'Hilux',
                            'kilometraje_actual' => 12500.5,
                            'disponible_para_mantenimiento' => true,
                            'asignacion_activa' => [
                                'driver_id' => 1,
                                'conductor' => 'Conductor Demo',
                            ],
                        ]],
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/mantenimientos/conductores',
                    'example' => '',
                    'response' => [
                        'count' => 1,
                        'data' => [[
                            'id' => 1,
                            'nombre' => 'Conductor Demo',
                            'licencia' => 'LIC-001',
                            'activo' => true,
                        ]],
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/mantenimientos/tipos',
                    'example' => '?vehicle_id=1',
                    'response' => [
                        'count' => 1,
                        'data' => [[
                            'id' => 1,
                            'nombre' => 'Cambio de aceite',
                            'categoria' => 'preventivo_km',
                            'es_preventivo' => true,
                            'cada_km' => 5000,
                        ]],
                    ],
                ],
                [
                    'method' => 'POST',
                    'path' => '/api/mantenimientos',
                    'example' => '',
                    'body_type' => 'form-data',
                    'body' => [
                        'vehicle_id' => 1,
                        'driver_id' => 1,
                        'maintenance_type_id' => 1,
                        'fecha_programada' => '2026-09-20 09:30:00',
                        'es_accidente' => false,
                        'evidencia' => '@foto_evidencia.jpg (opcional, tipo File)',
                        'formulario_documento' => '@formulario.pdf (opcional, tipo File)',
                    ],
                    'response' => [
                        'message' => 'Solicitud de mantenimiento registrada correctamente.',
                        'data' => [
                            'id' => 1,
                            'estado' => 'Pendiente',
                            'origen_solicitud' => 'external_api',
                        ],
                    ],
                ],
            ],
        ],
        'mantenimientos:read' => [
            'name' => 'VER MANTENIMIENTOS',
            'description' => 'Consulta todas las solicitudes y citas de mantenimiento con vehiculo, conductor, tipo, estado, fechas y enlaces protegidos a sus documentos.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-calendar-check',
            'color' => 'info',
            'created_at' => '2026-09-11 11:08:00',
            'selection_endpoint' => '/api/mantenimientos',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/mantenimientos',
                    'example' => '?per_page=20&page=1&vehicle_id=1&driver_id=1&status=Pendiente&date_from=2026-09-01&date_to=2026-09-30',
                    'response' => [
                        'data' => [],
                        'current_page' => 1,
                        'per_page' => 20,
                        'total' => 0,
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/mantenimientos/{maintenanceAppointment}/evidencia',
                    'example' => '',
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/mantenimientos/{maintenanceAppointment}/formulario',
                    'example' => '',
                ],
            ],
        ],
        'packgo:bitacora-route' => [
            'name' => 'PACKGO - Bitacora y rutas',
            'description' => 'Permite sincronizar bitacora, ubicacion, recorridos y reasignaciones de vehiculos desde PackGo.',
            'access' => 'Lectura y escritura',
            'icon' => 'fas fa-route',
            'color' => 'info',
            'endpoints' => [
                ['method' => 'GET', 'path' => '/api/vehicle-logs', 'example' => '?per_page=50&page=1'],
                ['method' => 'POST', 'path' => '/api/vehicle-logs', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/vehicle-logs/point-to-point', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/vehicle-logs/stage-event', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/vehicle-logs/reassignment/qr', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/vehicle-logs/reassignment/accept', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/mobile/location/heartbeat', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/mobile/bitacora/load', 'example' => ''],
                ['method' => 'GET', 'path' => '/api/mobile/bitacora/session-health', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/mobile/bitacora/investigation-ticket/confirm', 'example' => ''],
                ['method' => 'POST', 'path' => '/api/activity-logs', 'example' => ''],
                ['method' => 'GET', 'path' => '/api/activity-logs', 'example' => ''],
            ],
        ],
        'correos:send' => [
            'name' => 'ENVIO DE CORREOS',
            'description' => 'Envia correos mediante la cuenta SMTP institucional configurada. El remitente siempre es el definido por el servidor.',
            'access' => 'Escritura',
            'icon' => 'fas fa-envelope',
            'color' => 'primary',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/integraciones/correos/enviar',
                    'example' => '',
                    'body' => [
                        'para' => ['destinatario@ejemplo.com'],
                        'cc' => ['copia@ejemplo.com'],
                        'cco' => [],
                        'asunto' => 'Notificacion de Correos de Bolivia',
                        'mensaje' => 'Contenido del correo que se desea enviar.',
                        'formato' => 'texto',
                        'responder_a' => 'respuesta@ejemplo.com',
                    ],
                    'response' => [
                        'message' => 'Correo enviado correctamente.',
                        'data' => [
                            'para' => ['destinatario@ejemplo.com'],
                            'cc' => ['copia@ejemplo.com'],
                            'cco' => [],
                            'asunto' => 'Notificacion de Correos de Bolivia',
                            'formato' => 'texto',
                            'enviado_en' => '2026-09-08T10:30:00-04:00',
                        ],
                    ],
                ],
            ],
        ],
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
        'chasqui:paquetes:deliver' => [
            'name' => 'ENTREGA CARTEROS',
            'description' => 'Permite al cartero autenticado confirmar la entrega de uno de sus paquetes asignados, registrando receptor, fecha, observacion, evento de seguimiento y fotografia obligatoria.',
            'access' => 'Escritura con foto',
            'icon' => 'fas fa-camera-retro',
            'color' => 'success',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/chasqui/paquetes/entregar',
                    'example' => '',
                    'body_type' => 'form-data',
                    'headers' => [
                        'Authorization' => 'Bearer TOKEN_PERSONAL_DEL_CARTERO',
                        'X-API-Token' => 'TOKEN_JWT_DE_LA_INTEGRACION',
                        'Accept' => 'application/json',
                    ],
                    'body' => [
                        'tipo_paquete' => 'EMS',
                        'id' => 15,
                        'recibido_por' => 'Maria Perez',
                        'fecha_entrega' => '2026-09-01T14:30',
                        'descripcion' => 'Entregado personalmente en domicilio.',
                        'foto' => '@foto_entrega.jpg (tipo File)',
                    ],
                    'response' => [
                        'message' => 'Correspondencia entregada correctamente.',
                        'data' => [
                            'id' => 15,
                            'tipo_paquete' => 'EMS',
                            'codigo' => 'EE123456789BO',
                            'estado' => 'ENTREGADO',
                            'recibido_por' => 'Maria Perez',
                            'fecha_entrega' => '2026-09-01T14:30:00-04:00',
                            'foto_guardada' => true,
                        ],
                    ],
                ],
            ],
        ],
        'chasqui:notificaciones:read' => [
            'name' => 'NOTIFICACIONES CARTEROS',
            'description' => 'Indica si el cartero autenticado tiene paquetes pendientes y devuelve el titulo, mensaje e intervalo configurable que ChasquiApp debe usar para programar la notificacion del celular.',
            'access' => 'Solo lectura',
            'icon' => 'fas fa-bell',
            'color' => 'warning',
            'endpoints' => [
                [
                    'method' => 'GET',
                    'path' => '/api/chasqui/notificaciones/pendientes',
                    'example' => '',
                    'headers' => [
                        'Authorization' => 'Bearer TOKEN_PERSONAL_DEL_CARTERO',
                        'X-API-Token' => 'TOKEN_JWT_DE_LA_INTEGRACION',
                        'Accept' => 'application/json',
                    ],
                    'response' => [
                        'should_notify' => true,
                        'pending_packages' => 3,
                        'notification' => [
                            'enabled' => true,
                            'title' => 'ChasquiApp',
                            'message' => 'Tienes paquetes pendientes',
                            'interval_minutes' => 15,
                            'interval_seconds' => 900,
                        ],
                        'pending_by_type' => [
                            'EMS' => 2,
                            'CONTRATO' => 1,
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
                            'role_id' => 5,
                            'role' => 'cartero_ems',
                            'roles' => ['cartero_ems'],
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
        'clientes:update' => [
            'name' => 'Editar usuario Delivery Express',
            'description' => 'Actualiza el nombre, numero de carnet, telefono y direccion del cliente indicado en la URL.',
            'access' => 'Escritura',
            'icon' => 'fas fa-user-edit',
            'color' => 'warning',
            'endpoints' => [
                [
                    'method' => 'PATCH',
                    'path' => '/api/integraciones/clientes/{cliente}',
                    'example' => '',
                    'body' => [
                        'name' => 'Cliente Actualizado',
                        'numero_carnet' => '1234567',
                        'telefono' => '70000000',
                        'direccion' => 'Avenida Principal 123',
                    ],
                    'response' => [
                        'message' => 'Cliente actualizado correctamente.',
                        'cliente' => [
                            'id' => 1,
                            'name' => 'Cliente Actualizado',
                            'numero_carnet' => '1234567',
                            'telefono' => '70000000',
                            'direccion' => 'Avenida Principal 123',
                        ],
                    ],
                ],
            ],
        ],
        'clientes:password:update' => [
            'name' => 'Actualizar contraseña de cliente',
            'description' => 'Actualiza de forma segura la contraseña del cliente indicado en la URL. La nueva contraseña debe tener al menos 8 caracteres y enviarse con su confirmación.',
            'access' => 'Escritura',
            'icon' => 'fas fa-key',
            'color' => 'warning',
            'endpoints' => [
                [
                    'method' => 'PATCH',
                    'path' => '/api/integraciones/clientes/{cliente}/password',
                    'example' => '',
                    'body' => [
                        'password' => 'NuevaClaveSegura123',
                        'password_confirmation' => 'NuevaClaveSegura123',
                    ],
                    'response' => [
                        'message' => 'Contraseña del cliente actualizada correctamente.',
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
        'paquetes-contrato:pickup' => [
            'name' => 'RECOJO DE PAQUETES',
            'description' => 'Recoge uno o varios paquetes de contrato o solicitudes Delivery Express en estado SOLICITUD. El peso es obligatorio para paquetes de contrato (entre 0,001 y 700,000 kg) y opcional para Delivery Express. Los pasa a ALMACEN y registra el evento de recojo correspondiente. El alcance regional corresponde al usuario que creo la credencial.',
            'access' => 'Escritura',
            'icon' => 'fas fa-dolly',
            'color' => 'warning',
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/paquetes-contrato/recoger',
                    'example' => '',
                    'body' => [
                        'envios' => [
                            [
                                'codigo' => 'CEMPRESA00001BO',
                                'peso' => 1.000,
                            ],
                            [
                                'codigo' => 'SL00000001LP',
                            ],
                        ],
                    ],
                    'response' => [
                        'message' => '2 envio(s) enviado(s) a ALMACEN.',
                        'actualizados' => 2,
                        'actualizados_por_tipo' => ['contrato' => 1, 'solicitud' => 1],
                        'codigos' => ['CEMPRESA00001BO', 'SL00000001LP'],
                        'no_procesados' => [],
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

# API de clientes Delivery Express

Base local: `http://127.0.0.1:8000/api`

## Credencial de integracion seleccionable

Desde `/configuracion/apis?nueva=1` se pueden seleccionar los permisos de Google, crear solicitudes y consultar solicitudes. El token JWT generado alli se utiliza en estas rutas:

- `POST /integraciones/clientes/google-login`
- `POST /integraciones/clientes/login`
- `POST /integraciones/clientes`
- `POST /integraciones/clientes/{cliente}/solicitudes`
- `GET /integraciones/clientes/{cliente}/solicitudes`

```http
Authorization: Bearer TOKEN_JWT_DE_LA_INTEGRACION
Accept: application/json
```

En `{cliente}` se coloca el ID numerico del cliente. Cada permiso puede seleccionarse y revocarse de manera independiente desde el catalogo.

Para crear un usuario con la credencial de integracion:

```json
{
  "name": "Cliente API",
  "email": "cliente@example.com",
  "password": "ClaveSegura123",
  "password_confirmation": "ClaveSegura123",
  "device_name": "Delivery Express"
}
```

Para iniciar sesion con correo y contrasena:

```json
{
  "email": "cliente.api@example.com",
  "password": "ClaveSegura123",
  "device_name": "Postman Delivery Express"
}
```

El portal web continúa iniciando sesión desde `GET /clientes/login`. Para una aplicación móvil o un sistema externo se usa un ID token de Google y la API devuelve un token Bearer de Sanctum.

## 1. Iniciar sesión con Google

`POST /clientes/google-login`

```json
{
  "id_token": "ID_TOKEN_ENTREGADO_POR_GOOGLE",
  "device_name": "Delivery Express Android"
}
```

Respuesta correcta:

```json
{
  "message": "Inicio de sesion con Google correcto.",
  "token_type": "Bearer",
  "access_token": "TOKEN_DE_LA_API",
  "cliente": {}
}
```

La aplicación debe solicitar el ID token usando como `serverClientId` el valor de `GOOGLE_CLIENT_ID` configurado en el servidor.

## 2. Consultar el cliente autenticado

`GET /clientes/me`

```http
Authorization: Bearer TOKEN_DE_LA_API
Accept: application/json
```

## 3. Crear una solicitud

`POST /clientes/solicitudes`

```http
Authorization: Bearer TOKEN_DE_LA_API
Accept: application/json
Content-Type: application/json
```

```json
{
  "servicio_extra_id": 1,
  "origen": "LA PAZ",
  "destino_id": 2,
  "cantidad": 1,
  "contenido": "Documentos",
  "nombre_remitente": "Cliente Demo",
  "carnet": "1234567",
  "telefono_remitente": "70000000",
  "nombre_destinatario": "Destinatario Demo",
  "telefono_destinatario": "71111111",
  "direccion_recojo": "Zona Central",
  "direccion_entrega": "Avenida Principal"
}
```

La solicitud se registra con el mismo código, estado, tarifa y evento que usa el portal web.

## 4. Listar las solicitudes del usuario

`GET /clientes/solicitudes?per_page=50&page=1`

```http
Authorization: Bearer TOKEN_DE_LA_API
Accept: application/json
```

El servidor obtiene el cliente desde el token. No acepta un `cliente_id` enviado por el consumidor, por lo que cada usuario solo puede ver y crear solicitudes para su propia cuenta.

## 5. Cerrar sesión

`POST /clientes/logout`

Este endpoint revoca el token Bearer usado en la petición.

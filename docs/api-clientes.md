# API de autenticacion de clientes

Base local: `http://127.0.0.1:8000`

Todas las solicitudes deben enviar estos encabezados:

```http
Accept: application/json
Content-Type: application/json
```

## 1. Registrar cliente

`POST /api/clientes/register`

Los campos de perfil son opcionales. `password_confirmation` debe ser igual a `password`.

```json
{
  "name": "Juan Perez",
  "email": "juan.perez@example.com",
  "password": "ClaveSegura123",
  "password_confirmation": "ClaveSegura123",
  "device_name": "Sistema companero",
  "tipodocumentoidentidad": "1",
  "numero_carnet": "12345678",
  "complemento": "1A",
  "razon_social": "Juan Perez",
  "telefono": "71234567",
  "direccion": "Av. Principal 123"
}
```

Respuesta exitosa: `201 Created`. Devuelve `access_token`, `token_type` y los datos del cliente.

## 2. Iniciar sesion

`POST /api/clientes/login`

```json
{
  "email": "juan.perez@example.com",
  "password": "ClaveSegura123",
  "device_name": "Sistema companero"
}
```

Respuesta exitosa: `200 OK`. Las credenciales incorrectas devuelven `401`; los datos no validos devuelven `422`.

Para consumir futuras rutas protegidas, enviar el token recibido:

```http
Authorization: Bearer <access_token>
```

La coleccion importable se encuentra en `docs/postman/Bolipost-clientes-auth.postman_collection.json`. Las dos solicitudes guardan automaticamente el token recibido en la variable de coleccion `access_token`.

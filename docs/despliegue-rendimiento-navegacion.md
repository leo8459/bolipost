# Actualizacion de rendimiento de navegacion

## Cambios del .env

Reemplazar esta linea:

```dotenv
CACHE_DRIVER=file
```

por:

```dotenv
CACHE_STORE=file
```

No se requieren otras variables nuevas. Si el servidor ya usa `CACHE_STORE=redis`
o un cache compartido entre varios servidores, conservar ese valor.
El codigo mantiene compatibilidad con `CACHE_DRIVER` para instalaciones antiguas.

## Aplicacion en el servidor

Subir todos los archivos PHP, Blade, JS y CSS de esta actualizacion. No requiere
migraciones de base de datos ni compilar con npm; los nuevos JS/CSS estan en `public`.
Archivos de la aplicacion que deben actualizarse:

```text
app/Console/Commands/SyncAclPermissions.php
app/Http/Controllers/FacturacionCartController.php
app/Models/User.php
app/Models/Cliente.php
config/acl.php
config/acl_cliente.php
config/cache.php
config/adminlte.php
public/css/facturacion-shortcut-launcher.css
public/css/adminlte-custom.css
public/images/AGBClogo1.png
public/js/facturacion-shortcut-loader.js
public/js/adminlte-navigation.js
public/images/user-avatar.svg
resources/views/partials/facturacion-shortcut-launcher.blade.php
resources/views/partials/facturacion-shortcut.blade.php
resources/views/clientes/dashboard.blade.php
resources/views/vendor/adminlte/page.blade.php
routes/console.php
routes/web.php
```

Desde la carpeta del proyecto, con el usuario que ejecuta la aplicacion:

```sh
php artisan config:clear
php artisan view:clear
php artisan acl:sync
php artisan config:cache
```

`acl:sync` debe terminar correctamente antes de volver a atender usuarios. Sincroniza
roles y permisos internos y de clientes, usando las reglas existentes del sistema.
Ejecutarlo tambien despues de futuros despliegues que agreguen pantallas o permisos.
El directorio `storage/framework/cache/data` debe permitir escritura al usuario de PHP.

## Programador de tareas

La sincronizacion periodica de permisos pasa a `acl:sync`, cada cinco minutos.
Verificar que Laravel Scheduler se ejecute cada minuto. Si ya existe esta tarea,
conservarla sin duplicarla. Ejemplo de cron Linux (ajustar las rutas):

```cron
* * * * * cd /ruta/bolipost && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Comprobar la programacion con:

```sh
php artisan schedule:list
```

Si el alojamiento no permite programar tareas, se puede habilitar temporalmente
`ACL_SYNC_ON_REQUEST=true` y ejecutar `php artisan config:cache`. Ese modo conserva
la sincronizacion durante solicitudes y puede volver a causar pausas periodicas.
No es necesario agregar esa variable cuando el scheduler esta funcionando.

## Verificacion

Navegar entre Inicio, Solicitudes EMS y Usuarios: ya no se consulta la caja ni el
carrito para cargar cada pagina. Pulsar Facturacion debe cargar su dialogo y permitir
usar los flujos existentes. Los mensajes, descargas y QR tras una operacion de
facturacion conservan la carga completa para mostrar su resultado inmediatamente.
Una emision pendiente en la pestana sigue cargando su seguimiento al navegar.

Comparar nuevas mediciones de Cargado de ventanas despues del despliegue. Los
registros anteriores no cambian; no representan el rendimiento de esta version.

## Menu bloqueado al iniciar sesion

El menu superior muestra el logo institucional local `public/images/AGBClogo1.png`
junto al nombre del usuario, con formato ovalado. El perfil cliente sin foto usa
`public/images/user-avatar.svg`. Se elimina la
descarga de una foto aleatoria de `picsum.photos` al abrir cada pantalla.
AdminLTE esperaba el evento `window.load` para activar los submenus; una imagen
externa pendiente mantenia el menu sin responder. `adminlte-navigation.js` lo
activa cuando sus elementos y dependencias estan disponibles, sin esperar imagenes.
Tambien evita registrar el manejador dos veces al terminar la carga completa.

Para aplicar esta correccion puntual, actualizar `app/Models/User.php`,
`app/Models/Cliente.php`, `resources/views/clientes/dashboard.blade.php`,
`config/adminlte.php`, `public/js/adminlte-navigation.js`, `public/css/adminlte-custom.css`,
`public/images/AGBClogo1.png` y `public/images/user-avatar.svg`, y ejecutar `php artisan view:clear` y
`php artisan config:cache`.
No hay cambios adicionales del `.env` para esta correccion.

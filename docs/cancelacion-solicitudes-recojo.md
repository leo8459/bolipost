# Cancelacion automatica de solicitudes de recojo

El comando `php artisan contracts:cancel-expired-pickups` cambia a CANCELADO los
envios de `paquetes_contrato` que siguen en SOLICITUD y cuya fecha de creacion
es anterior a hace 20 dias. Con exactamente 20 dias todavia no se cancelan.
La regla se aplica a todas las modalidades de envio.

Se conserva el registro y su fecha de creacion. El comando exige que existan
ambos estados; no utiliza identificadores fijos. Una segunda ejecucion no
vuelve a modificar envios ya cancelados. Para consultar sin modificar:

```sh
php artisan contracts:cancel-expired-pickups --dry-run
```

Laravel programa esta revision cada hora. El programador debe estar activo en
el servidor, igual que para las demas tareas automaticas del proyecto:

## Servidor con Docker

`docker/supervisord.conf` incluye `laravel-scheduler`, que arranca
`php artisan schedule:work` automaticamente y lo reinicia si se detiene.
Esto activa todas las tareas definidas en el programador Laravel.
El contenedor debe estar en funcionamiento y `www-data` debe tener permisos
de escritura en `storage` y `bootstrap/cache`.

Tras subir el codigo actualizado, desde la carpeta del proyecto del servidor:

```sh
docker compose up -d --build app
docker compose top app
docker compose exec app php artisan schedule:list
docker compose exec app php artisan contracts:cancel-expired-pickups --dry-run
```

Debe aparecer un proceso `php artisan schedule:work` en el contenedor y
`contracts:cancel-expired-pickups` en la lista de tareas, con frecuencia horaria.
El archivo de Supervisor del servidor debe estar actualizado, ya que Compose
lo monta desde la carpeta `docker` del servidor. No configurar otro cron para
`schedule:run` si este proceso ya esta activo.

## Servidor sin Docker

Configurar una entrada de cron por minuto con la ruta real del proyecto:

```cron
* * * * * cd /ruta/bolipost && php artisan schedule:run >> /dev/null 2>&1
```

En Windows, mantener `php artisan schedule:work` como servicio o configurar el
Programador de tareas para ejecutar `php artisan schedule:run` cada minuto en
la carpeta del proyecto. No depende de que un usuario abra la pagina.

En la PC de desarrollo se registro la tarea de Windows
`Bolipost - Cancelar solicitudes de recojo vencidas`, que ejecuta directamente
este comando cada hora con la cuenta actual. Requiere que la PC este encendida
y la sesion de Windows iniciada; no requiere tener el navegador abierto.
Esta tarea local no se transfiere al desplegar el codigo en otro servidor.

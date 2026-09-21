# Cierre diario de contratos y EMS

En **Administrador → Envío de correo → Cierre diario**, el cierre diario usa los mismos destinatarios guardados para los avisos de vencimiento de la página **Contratos**. Tiene un control independiente para activar/desactivar el envío automático y un botón **Enviar cierre ahora**.

El envío automático se ejecuta todos los días a las **20:00 en America/La_Paz**. Resume registros, entregas (evento 316), movimientos por evento y pendientes por cartero. Adjunta un Excel dividido por departamento de destino con todos los pendientes. Los pendientes incluyen solicitudes sin recojo y cualquier estado distinto de ENTREGADO o CANCELADO. Solo considera la última asignación y exige que tanto el paquete como la asignación estén en CARTERO para identificar un cartero activo.

El reporte cubre movimientos desde las 00:00 hasta la hora del corte; los pendientes son acumulados. Es informativo y no bloquea operaciones posteriores. El envío manual no impide el envío automático nocturno. Un cierre automático exitoso no se repite durante el mismo día.

## Ejecución en el servidor

Las hojas de departamentos incluyen el estado actual y el historial de estados y eventos, con fecha y hora de Bolivia. La hoja **Historial** contiene un evento por fila para cada pendiente, incluidos eventos de días anteriores hasta el corte. Si no hay eventos registrados se indica en el detalle; no se reconstruyen cambios que el sistema no haya guardado.

Se requiere la misma configuración SMTP y el programador Laravel que utilizan los avisos de vencimiento. En Linux, mantener una entrada de cron cada minuto, reemplazando la ruta por la del proyecto:

```cron
* * * * * cd /ruta/bolipost && php artisan schedule:run >> /dev/null 2>&1
```

En Windows puede mantenerse `php artisan schedule:work` como servicio o configurar el Programador de tareas para ejecutar `php artisan schedule:run` cada minuto dentro del proyecto.

Comando para ejecutar el cierre automático bajo demanda:

```sh
php artisan operations:send-daily-closing
```

`--force` permite repetir el cierre de hoy, pero respeta el interruptor de envío automático. Si faltan destinatarios no envía ni marca el cierre como realizado. Si el envío falla, el comando falla y no marca el cierre como enviado; un reintento puede repetir el correo a los destinatarios que ya lo recibieron antes del fallo.

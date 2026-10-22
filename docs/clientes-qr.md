# QR de solicitudes de clientes

Cada QR nuevo se guarda en `cliente_solicitud_qrs` con su cliente, carrito remoto, imagen, fecha de generación y vencimiento a las 48 horas. Al registrar la solicitud se vincula ese mismo QR. Reintentar el registro no crea otra solicitud para el mismo intento.

El botón **Ver QR** permite consultar exclusivamente los QR del cliente autenticado. Comprueba el pago al abrirse y cada 15 segundos mientras el visor está abierto. Los QR pagados o vencidos dejan de mostrarse para el cobro.

`php artisan clientes:sync-qr` consulta Facturación y registra los pagos confirmados. Al vencer 48 horas, cambia la guía a `CANCELADO` únicamente si Facturación devuelve un estado reconocido de impago. Una conexión fallida o un estado desconocido se reintenta; no demuestra impago. La cancelación queda registrada en la justificación de la solicitud. Esto cancela la guía local, no revoca un QR bancario a través de la API.

## Ejecución automática

Laravel programa `clientes:sync-qr` cada minuto en `routes/console.php`. En el servidor se debe mantener activo el scheduler habitual (`php artisan schedule:run` cada minuto).

En el equipo Windows de desarrollo se registró la tarea `Bolipost-Clientes-QR`, que ejecuta exclusivamente `clientes:sync-qr` cada minuto con la cuenta actual. Requiere que el equipo esté encendido y la sesión de Windows esté abierta. Al mover el proyecto a otro equipo, debe configurarse su scheduler allí. Si se activa también el scheduler general en este equipo, puede eliminarse esta tarea específica para evitar consultas duplicadas.

## Solicitudes anteriores

Las solicitudes creadas antes de este cambio no guardaban la imagen ni el identificador del carrito QR. Se muestran como **Sin QR guardado** y no se cancelan por este mecanismo. No se vinculan por coincidencias de monto o fecha, porque podrían corresponder a otra solicitud.

## Verificación

`php artisan test --compact --filter=ClienteSolicitudQrTest`

Las pruebas usan SQLite en memoria y respuestas simuladas de Facturación; no generan cobros reales.

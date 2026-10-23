# QR de solicitudes de clientes

Facturación es la fuente de verdad para el carrito, el QR y el estado del pago. Bolipost conserva en su base únicamente la solicitud de envío que se registra después de confirmar el pago; este flujo no requiere una tabla ni una migración local para los QR.

La cotización se vuelve a calcular en el servidor al solicitar el QR. Si el precio cambió desde que se mostró en pantalla, no se emite el QR: se devuelve el nuevo importe para que el cliente lo revise. El carrito enviado a Facturación debe contener una sola línea y su total debe coincidir con la cotización.

Cada `qr_attempt_id` de cliente online usa un carrito independiente en Facturación. Esto evita que líneas de borradores anteriores se sumen al cobro. El formulario de nueva solicitud no recupera automáticamente un QR anterior; esos pagos se consultan en **Mis solicitudes**.

Se permite generar un QR nuevo aunque haya otros intentos pendientes o pagos confirmados en registro. Cada intento usa un carrito y un código independientes; los anteriores se consultan en **Mis solicitudes**.

Después de generar el QR, la ventana de pago consulta su estado cada tres segundos. Una vez confirmado, solicita la factura y registra la solicitud de envío. El historial consulta el QR directamente en Facturación.

## Conciliacion automatica

Bolipost ejecuta `clientes:sync-qr` cada minuto. El comando obtiene de Facturacion los QR no pagados de cliente online y consulta su estado con la emision automatica de factura desactivada. Si ya existe una solicitud local vinculada, marca el pago como pagado al confirmarse. A las 48 horas, si sigue impago, marca esa solicitud local como CANCELADO. Si todavia no existe una solicitud vinculada, el intento se considera vencido por su fecha de creacion y deja de bloquear un nuevo QR. La regla de 48 horas no cancela el QR en el banco; Facturacion conserva el QR y refleja la respuesta del proveedor cuando se consulta.

La cola protegida de QR pendientes se implementa en `FacturacionCartIntegrationController` del proyecto `apifacturacionagbc`; esa version debe desplegarse en el servicio indicado por `FACTURACION_BRIDGE_BASE_URL`. El programador de Laravel debe estar activo para que corra la reconciliacion cada minuto. En Windows se necesita una tarea que ejecute `php artisan schedule:run` cada minuto; en este equipo no encontre la tarea `Bolipost-Clientes-QR`.

## Despliegue

La separación de carritos por intento se implementa en `FacturacionCartIntegrationController` del proyecto `apifacturacionagbc`. Esa versión debe estar desplegada en el servicio indicado por `FACTURACION_BRIDGE_BASE_URL`; Bolipost valida el total antes de entregar el QR incluso si recibe una respuesta de carrito incorrecta.

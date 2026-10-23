# Incidente de facturación del 22/10/2026

El pago `VQC-0000004392` (carrito 13311, transacción QR 4731459) figura pagado por Bs 1,00. No tiene una venta fiscal vinculada. La consulta de datos se realizó en una transacción de solo lectura.

## Causa

La regla anidada `resumen_origen.qr_attempt_id` hacía que Laravel excluyera las demás claves del array al obtener los datos validados. Para cliente online se guardaba solo el identificador del intento; para los ítems de cajero se perdía todo el resumen. Así desaparecían tanto los datos del envío como los códigos fiscales SIN.

La API marcaba el carrito `EMITIENDO` antes de construir y validar el contenido fiscal. Al faltar los códigos SIN, la preparación abortaba con `El borrador tiene items sin datos SIN completos`, dejando el carrito bloqueado. Las consultas posteriores del código de orden al servicio fiscal responden `400 / Recurso no encontrado`, como muestran los logs recibidos.

## Corrección preparada

- API: `app/Http/Requests/FacturacionCartItemRequest.php` conserva el resumen completo y valida el UUID online por separado.
- API: `app/Http/Controllers/FacturacionCartIntegrationController.php` prepara el contenido fiscal antes de marcar `EMITIENDO`.
- Bolipost: conserva importe, código y confirmación de pago al fallar el registro; identifica los datos de envío ausentes y detiene el reintento automático de ese error permanente.

La corrección no agrega migraciones ni crea nuevos cobros. Deben desplegarse juntos el controlador y el nuevo FormRequest en `apifacturacionagbc`; también deben desplegarse el controlador y las dos vistas de solicitudes en Bolipost.

## Carritos encontrados afectados

En la lectura de las 16:20 se encontraron estos carritos en `EMITIENDO`, sin venta vinculada y con preparación fiscal inválida por metadatos ausentes:

| Carrito | Código | Origen de los ítems |
| --- | --- | --- |
| 13309 | VFC-0000010645 | Paquete EMS 10306 |
| 13311 | VQC-0000004392 | Cliente online, intento 0ec76722-3a19-45fd-b04d-49134bf2be5e |
| 13312 | VFC-0000010647 | Concepto 4 |
| 13313 | VFC-0000010648 | Paquete EMS 10308 |
| 13314 | VFC-0000010649 | Conceptos 14, 15, 16, 17 y 1 |
| 13316 | VFC-0000010650 | Concepto 6 |

La corrección del código evita la pérdida en nuevos envíos de datos; no reconstruye lo ya perdido. Los metadatos del cajero deben recuperarse desde sus registros de origen. El intento online requiere los datos originales del envío: su resumen solo conserva `qr_attempt_id`. No se deben inventar direcciones ni generar otro cobro para reemplazar este pago.

Antes de desbloquear un carrito existente, hay que confirmar su resultado fiscal y recuperar el contenido original. En esta intervención no se modificaron estados ni registros reales, ni se emitieron facturas o QR.

## Comprobaciones

Pasaron cuatro pruebas de validación en la API y una prueba del estado QR pagado con datos de envío ausentes en Bolipost, además de los controles de sintaxis PHP. Las pruebas no consultan proveedores ni realizan pagos.

## Revisión del carrito de cajeros

En la revisión posterior se confirmó que la validación corregida ya está activa en `safe.correos.gob.bo`: una petición incompleta a `cart/items/upsert` se rechazó antes de guardar y devolvió el mensaje del nuevo FormRequest. No se generaron ítems de prueba en producción.

Las consultas reales a `cart/context` de los cinco cajeros afectados respondieron HTTP 200 y devolvieron sus ítems:

| Carrito | Ítems | Total Bs | Estado fiscal |
| --- | --- | --- | --- |
| 13309 | 2 | 56,00 | EMITIENDO |
| 13312 | 1 | 25,00 | EMITIENDO |
| 13313 | 1 | 38,00 | EMITIENDO |
| 13314 | 5 | 388,00 | EMITIENDO |
| 13316 | 1 | 25,00 | EMITIENDO |

Se encontraron nuevos ítems de cajero con todos sus campos fiscales en los carritos 13309 y 13312. Esto confirma que el guardado actual funciona en esos casos; no prueba el comportamiento del navegador de cada cajero. Los estados `EMITIENDO` anteriores siguen bloqueando la emisión con HTTP 409, incluso cuando se añade un ítem nuevo o se reemplaza el contenido del carrito.

También se comprobaron dos facturaciones posteriores correctas: `VFC-0000010651` por Bs 25,00 y `VQC-0000004393` por Bs 21,00, ambas registradas como `FACTURADA` a las 16:24 según los datos del servidor. El problema no afecta a todas las ventas nuevas.

Queda pendiente recuperar y revisar los cinco carritos de cajero ya bloqueados y el intento online 13311. No se restablecieron estados fiscales ni se reemitieron facturas. El acceso SSH fue rechazado por la revisión automática de herramientas; no se pudieron leer los logs actuales del servidor por esa vía. Se utilizaron los logs compartidos por el usuario, la API y consultas de base de datos de solo lectura.

## Recuperación autorizada de los cajeros

Posteriormente el usuario autorizó desbloquear y vaciar los cinco carritos, con la condición de comprobar primero que no tuvieran factura.

Se consultaron directamente los cinco códigos de orden en SEFE mediante la API: todos devolvieron HTTP 400 con el mensaje fiscal específico `Recurso no encontrado`. Se comprobó que no existieran ventas vinculadas por carrito ni por código, notificaciones fiscales, seguimiento fiscal o registros QR asociados. El carrito 13312 tenía seleccionada la opción QR, pero no tenía transacción ni QR generado.

En una sola transacción se reiniciaron los carritos **13309, 13312, 13313, 13314 y 13316**: se retiraron sus diez ítems de borrador, se dejaron importes en cero, pago pendiente y estado `borrador`, y se limpiaron los identificadores y el bloqueo de emisión. Cada carrito conserva una nota de revisión con el código anterior. No había registros de ventas que eliminar ni facturas que anular.

La operación comprobó nuevamente la ausencia de registros fiscales y que el contenido no hubiera cambiado desde la revisión. Se guardó el respaldo completo antes de modificar los datos en `C:/xampp/htdocs/proyectos/apifacturacionagbc/storage/app/cashier-recovery-20261022-before-163308.json`, junto con la evidencia de las consultas fiscales en `cashier-recovery-20261022-evidence.json` del mismo directorio. Ambos archivos son privados y no se incluyen en Git.

La comprobación posterior de `cart/context` respondió HTTP 200 para los cinco cajeros, devolviendo sus carritos en `borrador`, sin estado de emisión, sin ítems y con total cero. Los cajeros deben recargar la pantalla y registrar nuevamente los cobros. No se emitieron facturas durante la recuperación.

El intento online 13311, que tiene un QR pagado, no formaba parte de estos cinco carritos y conserva su pago y sus datos.

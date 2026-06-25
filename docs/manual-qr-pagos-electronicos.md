# MANUAL DE USUARIO

## NOMBRE DEL SISTEMA

SISTEMA INTEGRAL OPERATIVO POSTAL - MODULO FACTURACION

## TEMA DEL DOCUMENTO

Integracion de facturacion, QR y pagos electronicos  
Analisis oficial y no oficial

## HECHO POR

Israel Enrique Uchazara Carrillo  
Telefono: 64229403  
Correo electronico: israelquique714@gmail.com  
GitHub: https://github.com/Israel-Quique

La Paz - Bolivia  
2026

---

## INDICE

1. Introduccion
2. Capitulo 1 - Antecedentes
3. Capitulo 2 - Justificaciones
4. Capitulo 3 - Diseno teorico de la investigacion
5. Capitulo 4 - Problematica
6. Capitulo 5 - Resultados
7. Conclusiones
8. Recomendaciones
9. Anexos

---

## INTRODUCCION

El Sistema Integral Operativo Postal - Modulo Facturacion es el componente encargado de registrar, procesar y dar seguimiento a operaciones de venta y emision de comprobantes dentro del entorno institucional. Su finalidad es permitir que el operador gestione datos de facturacion, emita facturas electronicas y utilice mecanismos modernos de cobro, como el pago mediante codigo QR, desde una misma plataforma de trabajo.

Dentro de esta arquitectura, el sistema principal no se conecta directamente con todos los servicios externos, sino que utiliza una capa intermedia de integracion para centralizar la comunicacion con las APIs de facturacion y de pagos electronicos. A partir de la revision del codigo fuente se identifico que el proyecto `bolipost` funciona como sistema cliente, mientras que el proyecto `apifacturacionagbc` opera como bridge de integracion para administrar el carrito de facturacion, emitir ventas, consultar estados, generar checkout QR y consultar pagos QR.

El presente documento describe especificamente el funcionamiento del flujo de facturacion, QR y pagos electronicos, diferenciando las integraciones oficiales de las no oficiales. Asimismo, organiza la informacion en capitulos que explican los antecedentes, las justificaciones, el alcance, la problematica, los resultados obtenidos y las recomendaciones para su uso y futura mejora.

---

## CAPITULO 1 - ANTECEDENTES

### 1.1 Antecedentes del Proyecto

Antes de la integracion actual, los procesos de facturacion y cobro electronico solian ejecutarse de forma separada, lo que dificultaba el control integral de cada transaccion. En ese escenario, una parte del proceso podia registrar la venta, otra emitir la factura y otra administrar el cobro, provocando fragmentacion operativa, mayor dependencia de verificaciones manuales y menor trazabilidad.

Con la implementacion del modulo de facturacion, el sistema paso a concentrar en una sola experiencia operativa la seleccion del canal de emision, el registro de datos fiscales, la solicitud de emision, la visualizacion del QR de pago y la consulta posterior del estado de la transaccion.

Segun la revision realizada, el proyecto `bolipost` no emite directamente contra el servicio externo final, sino que se comunica con un servicio intermedio definido por la variable `FACTURACION_BRIDGE_BASE_URL`. Ese servicio corresponde al proyecto `apifacturacionagbc`, que expone endpoints para:

- administrar el carrito de facturacion
- actualizar datos de facturacion
- emitir ventas
- consultar estados
- generar checkout QR
- consultar pagos QR

Esta estructura permite desacoplar la operacion del sistema principal respecto a los detalles tecnicos de integracion con terceros.

### 1.2 Novedad Cientifica

La novedad de la solucion consiste en integrar en un mismo flujo operativo dos procesos diferentes pero complementarios: la facturacion electronica y el cobro electronico por QR. En lugar de depender de sistemas aislados, la plataforma coordina el envio, la respuesta y el seguimiento desde una sola interfaz funcional.

Los principales elementos innovadores identificados son:

- seleccion dinamica del canal de emision
- uso de un bridge de integracion para desacoplar el sistema cliente
- reutilizacion de un mismo borrador para factura electronica o QR
- almacenamiento del estado del pago junto al estado de emision
- consulta posterior de la transaccion sin rehacer todo el proceso

Tambien se observa una diferenciacion clara de proveedores:

- la facturacion oficial se encamina hacia AGETIC/SEFE
- el cobro QR se encamina hacia Qhantuy Checkout

Por tanto, la innovacion no solo es funcional, sino tambien arquitectonica.

---

## CAPITULO 2 - JUSTIFICACIONES

### 2.1 Justificacion

La integracion de facturacion, QR y pagos electronicos responde a la necesidad de mejorar el control del proceso comercial, reducir tareas manuales y ofrecer mecanismos de cobro mas agiles para los usuarios institucionales.

### 2.1.1 Justificacion Economica

El sistema optimiza recursos al reducir reprocesos, errores de digitacion y tiempo invertido en conciliaciones manuales. Al mantener en un mismo flujo el borrador de venta, el estado de emision y el estado de pago, disminuye la dispersion de informacion y mejora la eficiencia administrativa.

### 2.1.2 Justificacion Social

La solucion beneficia a los usuarios internos porque simplifica la operacion diaria y les permite trabajar con informacion mas clara y trazable. Tambien beneficia al usuario final al facilitar un medio de pago electronico rapido mediante QR, sin romper el flujo normal de facturacion.

### 2.1.3 Justificacion Tecnica

La arquitectura implementada distribuye adecuadamente las responsabilidades:

- `bolipost` administra la interfaz, validaciones operativas y experiencia del usuario
- `apifacturacionagbc` concentra la logica de integracion tecnica
- AGETIC/SEFE atiende la emision oficial de factura electronica
- Qhantuy atiende la generacion y seguimiento del pago QR

Esta separacion permite modificar configuraciones o integraciones sin rehacer por completo el sistema principal.

---

## CAPITULO 3 - DISENO TEORICO DE LA INVESTIGACION

### 3.1 Delimitacion del Contenido

El presente documento estudia exclusivamente el flujo relacionado con facturacion electronica, generacion de QR y pagos electronicos dentro del Modulo Facturacion.

### 3.1.1 Alcances

El analisis cubre:

- seleccion del canal de emision desde la interfaz de facturacion
- sincronizacion de datos con el bridge de integracion
- emision mediante factura electronica
- generacion de checkout QR
- consulta de estado del pago QR
- diferenciacion entre integracion oficial y no oficial
- identificacion de endpoints, autenticacion y variables de entorno relevantes

### 3.1.2 Limites

El proyecto presenta los siguientes limites:

- no administra todos los modulos del sistema postal, sino unicamente el flujo vinculado a facturacion y cobros electronicos
- no sustituye a los servicios externos oficiales o privados, ya que depende de AGETIC/SEFE para la facturacion y de Qhantuy para el pago QR
- no controla internamente toda la logica normativa externa, porque parte de la validacion y respuesta final pertenece a los servicios consumidos por API
- no garantiza la continuidad del proceso cuando exista caida, lentitud o rechazo por parte de los proveedores externos
- no cubre otros medios de pago electronico distintos a los implementados en el flujo actual de QR

### 3.2 Delimitacion Espacial

La solucion esta orientada al entorno institucional donde se opera el Sistema Integral Operativo Postal y donde se requiere integracion con servicios de facturacion electronica y cobro QR.

### 3.2.1 Delimitacion Temporal

El analisis corresponde al estado del codigo revisado en fecha 15 de junio de 2026.

### 3.2.2 Delimitacion Geografica

La implementacion aplica a operaciones institucionales en Bolivia, particularmente en contextos donde se utilizan servicios nacionales de facturacion electronica y mecanismos de pago QR.

---

## CAPITULO 4 - PROBLEMATICA

### 4.1 Planteamiento del Problema

El problema principal consistia en la falta de un flujo integrado que permitiera registrar una venta, definir su modalidad de emision, generar un mecanismo de cobro electronico y consultar el resultado final desde una sola experiencia operativa. Esta situacion provocaba dependencia de tareas manuales, menor trazabilidad y dificultad para verificar el estado real de cada transaccion.

Adicionalmente, existia la necesidad de diferenciar claramente dos procesos que suelen confundirse:

- la emision oficial de la factura electronica
- el cobro electronico mediante QR

Ambos estan relacionados dentro del flujo del sistema, pero tecnicamente no representan el mismo servicio.

### 4.2 Objetivo del proyecto

### 4.2.1 Objetivo general

Integrar y documentar el flujo de facturacion electronica y pagos mediante QR dentro del Modulo Facturacion del Sistema Integral Operativo Postal.

### 4.2.2 Objetivos especificos

- Identificar como el sistema cliente se conecta con el proyecto bridge de facturacion.
- Describir los endpoints involucrados en el flujo de factura electronica y QR.
- Diferenciar la integracion oficial de facturacion respecto al proveedor privado de pagos QR.
- Explicar como se registran y consultan los estados de emision y pago.
- Elaborar una base documental util para manual institucional y anexos tecnicos.

---

## CAPITULO 5 - RESULTADOS

### 5.1 Aporte

Del analisis realizado se identifico el siguiente flujo funcional:

1. El usuario selecciona en el Modulo Facturacion el canal de emision: `factura_electronica` o `qr`.
2. El sistema `bolipost` sincroniza la informacion del borrador con el bridge configurado en `FACTURACION_BRIDGE_BASE_URL`.
3. El proyecto `apifacturacionagbc` administra el carrito, valida la informacion y define el flujo de emision correspondiente.
4. Si el canal elegido es `factura_electronica`, el bridge deriva la solicitud al controlador de facturacion que consume el servicio oficial configurado en `AGETIC_BASE_URL`.
5. Si el canal elegido es `qr`, el bridge deriva la solicitud al controlador `QhantuyQrController`, que consume `QHANTUY_CHECKOUT_BASE_URL` y `QHANTUY_CHECK_PAYMENTS_URL`.
6. El bridge devuelve al sistema cliente el resultado de la operacion, incluyendo estado, codigo de orden, codigo de seguimiento, imagen QR o URL asociada.
7. El sistema permite posteriormente consultar el estado del proceso para verificar si la transaccion se mantiene pendiente, fue pagada, fue cancelada o concluyo con factura emitida.

### 5.2 Impacto

La integracion mejora significativamente la trazabilidad del proceso, ya que en un mismo registro se conserva:

- canal de emision
- metodo de pago
- estado de pago
- codigo de orden
- codigo de seguimiento
- respuesta de emision o de checkout

Esto facilita control interno, seguimiento operativo y verificaciones posteriores.

### 5.3 Oportunidades

Las principales oportunidades de mejora son:

- fortalecer la documentacion institucional con diagramas de flujo y secuencia
- separar visualmente en la interfaz los conceptos de facturacion y cobro QR
- incorporar reportes de conciliacion entre emision y pago
- mejorar auditoria sobre callbacks y cambios de estado del QR
- ampliar la documentacion tecnica de configuracion y recuperacion ante fallos

---

## CONCLUSIONES

1. El Sistema Integral Operativo Postal - Modulo Facturacion utiliza al proyecto `apifacturacionagbc` como capa intermedia de integracion.
2. La emision de factura electronica identificada en el codigo corresponde al flujo oficial, ya que el bridge consume el servicio configurado en `AGETIC_BASE_URL`.
3. El cobro QR corresponde a una integracion no oficial de facturacion, porque depende del proveedor privado Qhantuy para generar y consultar el pago electronico.
4. Facturacion electronica y pago QR son procesos relacionados, pero tecnicamente diferentes dentro de la arquitectura del sistema.
5. La existencia de un bridge centralizado mejora mantenibilidad, seguridad y trazabilidad en el proceso de integracion.

---

## RECOMENDACIONES

1. Mantener en la documentacion institucional una separacion clara entre flujo de facturacion oficial y flujo de pago QR.
2. Resguardar tokens, appkeys y endpoints sensibles fuera de anexos publicos.
3. Agregar capturas funcionales del modulo para reforzar el manual de usuario.
4. Elaborar un diagrama de secuencia con los actores: usuario, `bolipost`, `apifacturacionagbc`, AGETIC/SEFE y Qhantuy.
5. Definir institucionalmente el rol formal del proveedor QR como servicio complementario de pagos electronicos.

---

## ANEXOS

### Anexo A - Clasificacion oficial y no oficial

- Integracion oficial: flujo de factura electronica desde `apifacturacionagbc` hacia AGETIC/SEFE.
- Integracion no oficial: flujo de pago QR desde `apifacturacionagbc` hacia Qhantuy Checkout.
- Integracion interna: flujo desde `bolipost` hacia `apifacturacionagbc` mediante autenticacion Bearer.

### Anexo B - Endpoints identificados en `apifacturacionagbc`

- `POST /api/factura-venta/emitir`
- `GET /api/factura-venta/consultar/{codigoSeguimiento}`
- `POST /api/factura-venta/cart/emitir`
- `POST /api/factura-venta/cart/consultar`
- `POST /api/factura-venta/qr/checkout`
- `POST /api/factura-venta/qr/check-payments`
- `GET /api/qhantuy/callback`

### Anexo C - Variables de entorno relevantes

- En `bolipost`
- `FACTURACION_BRIDGE_BASE_URL`
- `FACTURACION_BRIDGE_TOKEN`
- `FACTURACION_BRIDGE_TIMEOUT`
- `FACTURACION_BRIDGE_SSL_VERIFY`

- En `apifacturacionagbc`
- `AGETIC_BASE_URL`
- `AGETIC_TOKEN`
- `FACTURACION_INTEGRATION_TOKEN`
- `QHANTUY_CHECKOUT_BASE_URL`
- `QHANTUY_CHECK_PAYMENTS_URL`
- `QHANTUY_CHECKOUT_TOKEN`
- `QHANTUY_CHECKOUT_APPKEY`
- `QHANTUY_CHECKOUT_CALLBACK_URL`

### Anexo D - Evidencia tecnica resumida

- El sistema permite escoger `factura_electronica` o `qr` desde la interfaz de facturacion.
- `bolipost` realiza requests autenticados al bridge usando `FACTURACION_BRIDGE_TOKEN`.
- `apifacturacionagbc` protege sus endpoints mediante middleware `factura.auth`.
- Si el canal es `qr`, el bridge ejecuta `QhantuyQrController::checkout`.
- Si el canal es `factura_electronica`, el bridge ejecuta `FacturaVentaApiController::emitir`.

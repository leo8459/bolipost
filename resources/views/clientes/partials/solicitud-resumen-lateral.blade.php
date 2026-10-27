<aside class="solicitud-live-summary" aria-labelledby="{{ $resumenId }}-titulo">
    <div class="solicitud-summary-heading">
        <span class="solicitud-service-icon"><i class="fas fa-box" aria-hidden="true"></i></span>
        <h5 id="{{ $resumenId }}-titulo" class="mb-0">Resumen del env&iacute;o</h5>
    </div>

    <div class="solicitud-route-summary">
        <div class="solicitud-route-point">
            <span class="solicitud-route-marker origin"><i class="fas fa-circle" aria-hidden="true"></i></span>
            <div><span>Origen</span><strong id="{{ $resumenId }}-origen">Por seleccionar</strong></div>
        </div>
        <div class="solicitud-route-connector" aria-hidden="true"></div>
        <div class="solicitud-route-point">
            <span class="solicitud-route-marker"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></span>
            <div><span>Destino</span><strong id="{{ $resumenId }}-destino">Por seleccionar</strong></div>
        </div>
    </div>

    <dl class="solicitud-live-details">
        <div><dt>Servicio</dt><dd id="{{ $resumenId }}-servicio">Por seleccionar</dd></div>
        <div><dt>Contenido</dt><dd id="{{ $resumenId }}-contenido">Por definir</dd></div>
    </dl>

    @if($mostrarRemitente)
        <div class="solicitud-live-contact">
            <h6>Datos del remitente</h6>
            <div><span>Nombre</span><strong id="{{ $resumenId }}-nombre-remitente">Por completar</strong></div>
            <div><span>Tel&eacute;fono</span><strong id="{{ $resumenId }}-telefono-remitente">Por completar</strong></div>
            <div><span>Direcci&oacute;n de recojo</span><strong id="{{ $resumenId }}-direccion-recojo">Por completar</strong></div>
        </div>
    @endif

    @if($mostrarDestinatario)
        <div class="solicitud-live-contact">
            <h6>Datos del destinatario</h6>
            <div><span>Nombre</span><strong id="{{ $resumenId }}-nombre-destinatario">Por completar</strong></div>
            <div><span>Tel&eacute;fono</span><strong id="{{ $resumenId }}-telefono-destinatario">Por completar</strong></div>
            <div><span>Direcci&oacute;n de entrega</span><strong id="{{ $resumenId }}-direccion-entrega">Por completar</strong></div>
        </div>
    @endif

    <div class="solicitud-live-price solicitud-step-price">
        <span class="solicitud-live-price-label">Precio del env&iacute;o</span>
        <strong id="{{ $resumenId }}-precio" aria-live="polite">Por calcular</strong>
        <small id="{{ $resumenId }}-recargo" class="solicitud-destination-fee" hidden>
            Incluye Bs 2,50 por pago en destino.
        </small>
    </div>

    <div class="solicitud-package-notice">
        <span class="solicitud-package-icon"><i class="fas fa-box-open" aria-hidden="true"></i></span>
        <div>
            <strong>Medidas del paquete</strong>
            <p>Menos de 3 kg y de 50 × 40 × 30 cm. Si supera los l&iacute;mites, se aplica un recargo de Bs 10.</p>
        </div>
    </div>
</aside>

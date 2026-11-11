<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket de entrega {{ $ticket['codigo'] }}</title>
    <style>
        @page { margin: 10px 8px 12px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #142b42; font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        .ticket { width: 100%; }
        .center { text-align: center; }
        .brand { margin-bottom: 3px; color: #174d7c; font-size: 14px; font-weight: 800; }
        .office { color: #526c82; font-size: 9px; font-weight: 700; }
        .kind { margin-top: 10px; color: #6d8192; font-size: 8px; font-weight: 700; letter-spacing: 1px; }
        .title { margin: 3px 0 0; color: #173d62; font-size: 16px; font-weight: 800; }
        .rule { margin: 10px 0; border-top: 1px dashed #8b9ba8; }
        .code-label { color: #718397; font-size: 8px; font-weight: 700; letter-spacing: .8px; text-align: center; }
        .code { margin: 4px 0; color: #102b45; font-size: 16px; font-weight: 800; letter-spacing: .5px; text-align: center; }
        .barcode { margin: 7px auto 4px; text-align: center; }
        .barcode img { width: 185px; height: 40px; }
        .row { margin: 7px 0; line-height: 1.4; overflow-wrap: anywhere; }
        .label { display: block; margin-bottom: 2px; color: #728497; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .value { color: #1e364b; font-size: 10px; font-weight: 700; }
        .pickup { margin-top: 11px; padding: 9px; border: 1px solid #d8e1e9; border-radius: 7px; background: #f7f9fb; }
        .pickup-title { margin-bottom: 7px; color: #173d62; font-size: 9px; font-weight: 800; text-align: center; }
        .blank { margin: 8px 0 10px; padding-bottom: 3px; border-bottom: 1px solid #748391; }
        .blank-label { color: #63788b; font-size: 8px; }
        .signature { height: 35px; margin-top: 23px; border-top: 1px solid #748391; color: #63788b; font-size: 8px; text-align: center; }
        .footer { margin-top: 10px; color: #778797; font-size: 7px; line-height: 1.45; text-align: center; }
    </style>
</head>
<body>
    <main class="ticket">
        <header class="center">
            <div class="brand">CORREOS DE BOLIVIA</div>
            @if($ticket['oficina'] !== '—')
                <div class="office">{{ $ticket['oficina'] }}</div>
            @endif
            <div class="kind">COMPROBANTE POSTAL</div>
            <h1 class="title">TICKET DE ENTREGA</h1>
        </header>

        <div class="rule"></div>

        <div class="code-label">CÓDIGO DE ENVÍO</div>
        <div class="code">{{ $ticket['codigo'] }}</div>
        @if($ticket['barcode'])
            <div class="barcode"><img src="data:image/png;base64,{{ $ticket['barcode'] }}" alt="Código de barras"></div>
        @endif

        <div class="rule"></div>

        @if($ticket['remitente'] !== '—')
            <div class="row"><span class="label">Remitente</span><span class="value">{{ $ticket['remitente'] }}</span></div>
        @endif
        @if($ticket['destinatario'] !== '—')
            <div class="row"><span class="label">Destinatario</span><span class="value">{{ $ticket['destinatario'] }}</span></div>
        @endif
        @if($ticket['servicio'] !== '—')
            <div class="row"><span class="label">Servicio</span><span class="value">{{ $ticket['servicio'] }}</span></div>
        @endif
        @if($ticket['destino'] !== '—')
            <div class="row"><span class="label">Destino</span><span class="value">{{ $ticket['destino'] }}</span></div>
        @endif
        @if($ticket['estado'] !== '—')
            <div class="row"><span class="label">Estado postal</span><span class="value">{{ $ticket['estado'] }}</span></div>
        @endif

        <section class="pickup">
            <div class="pickup-title">CONSTANCIA DE RECEPCIÓN</div>
            <div class="blank"><span class="blank-label">Recibido por (nombre):</span></div>
            <div class="blank"><span class="blank-label">Documento de identidad:</span></div>
            <div class="blank"><span class="blank-label">Fecha y hora:</span></div>
            <div class="signature">Firma de quien recibe</div>
        </section>

        <div class="footer">
            Generado manualmente el {{ $ticket['generado_en']->format('d/m/Y H:i') }} por {{ $ticket['generado_por'] }}.<br>
            Este comprobante no es una factura y no registra por sí solo la entrega en el sistema postal.
        </div>
    </main>
</body>
</html>

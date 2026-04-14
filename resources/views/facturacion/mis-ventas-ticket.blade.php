<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket {{ $ticket['orden'] }}</title>
    <style>
        @page { margin: 10px 8px 12px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
        }
        .ticket {
            width: 100%;
        }
        .center { text-align: center; }
        .title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .subtitle {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .muted {
            color: #444;
        }
        .line {
            border-top: 1px dashed #444;
            margin: 10px 0;
        }
        .row {
            margin-bottom: 6px;
            line-height: 1.35;
        }
        .label {
            font-weight: 700;
        }
        .amount {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            margin: 8px 0 10px;
        }
        .qr {
            text-align: center;
            margin: 10px 0 8px;
        }
        .qr img {
            width: 130px;
            height: 130px;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            color: #333;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="center">
            <div class="title">{{ $ticket['empresa'] }}</div>
            <div class="subtitle">{{ $ticket['sucursal'] }}</div>
            @if($ticket['direccion'] !== '')
                <div class="muted">{{ $ticket['direccion'] }}</div>
            @endif
            @if($ticket['telefono'] !== '')
                <div class="muted">Telefono: {{ $ticket['telefono'] }}</div>
            @endif
        </div>

        <div class="line"></div>

        <div class="row"><span class="label">NIT:</span> {{ $ticket['nit'] }}</div>
        <div class="row"><span class="label">ORDEN:</span> {{ $ticket['orden'] }}</div>
        <div class="row"><span class="label">NOMBRE:</span> {{ $ticket['nombre'] }}</div>
        <div class="row"><span class="label">NIT/CI/CEX:</span> {{ $ticket['documento'] }}</div>
        <div class="row"><span class="label">FACTURA N°:</span> {{ $ticket['numero_factura'] }}</div>
        <div class="row"><span class="label">FECHA:</span> {{ $ticket['fecha'] }}</div>

        <div class="amount">TOTAL Bs: {{ number_format((float) $ticket['importe'], 2) }}</div>

        @if($ticket['qr_image'])
            <div class="qr">
                <img src="data:image/png;base64,{{ $ticket['qr_image'] }}" alt="QR de factura">
            </div>
            <div class="footer">Visualice su factura desde el QR</div>
        @endif

        <div class="line"></div>

        <div class="footer">
            <div>Forma de pago: {{ $ticket['metodo_pago'] }}</div>
            @if($ticket['pdf_url'] !== '')
                <div>Comprobante original disponible en PDF</div>
            @endif
        </div>
    </div>
</body>
</html>

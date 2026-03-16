<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ticket de preregistro</title>
    <style>
        @page { size: A6 portrait; margin: 10mm; }
        body {
            font-family: Arial, sans-serif;
            color: #17375f;
            font-size: 11px;
        }
        .ticket {
            border: 1px solid #1f4e8c;
            border-radius: 14px;
            padding: 14px;
            background: #fffef8;
        }
        .brand {
            text-align: center;
            margin-bottom: 12px;
        }
        .brand h1 {
            margin: 0 0 4px;
            font-size: 18px;
            color: #20539A;
        }
        .brand p {
            margin: 0;
            color: #5b6f8d;
            font-size: 10px;
        }
        .code-box {
            text-align: center;
            border: 1px dashed #20539A;
            border-radius: 12px;
            padding: 10px;
            background: #fff7d6;
            margin-bottom: 12px;
        }
        .code-label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #20539A;
            margin-bottom: 6px;
        }
        .code-value {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: .08em;
            color: #153a69;
        }
        .barcode {
            text-align: center;
            margin: 10px 0 12px;
        }
        .section {
            border-top: 1px solid #d7e0ee;
            padding-top: 10px;
            margin-top: 10px;
        }
        .section:first-of-type {
            border-top: 0;
            padding-top: 0;
            margin-top: 0;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #20539A;
            margin-bottom: 6px;
        }
        .row {
            margin-bottom: 4px;
        }
        .label {
            font-weight: bold;
            color: #4c6485;
        }
        .footer {
            margin-top: 12px;
            text-align: center;
            font-size: 10px;
            color: #5c708e;
        }
    </style>
</head>
@php
    $codigo = (string) ($preregistro->codigo_generado ?: $preregistro->codigo_preregistro ?: '');
    $barcodePng = null;
    if ($codigo !== '' && class_exists('\DNS1D')) {
        try {
            $barcodePng = DNS1D::getBarcodePNG($codigo, 'C128', 2, 45);
        } catch (\Throwable $e) {
            $barcodePng = null;
        }
    }
@endphp
<body>
    <div class="ticket">
        <div class="brand">
            <h1>TrackingBO</h1>
            <p>Ticket de preregistro de envio desde casa</p>
        </div>

        <div class="code-box">
            <span class="code-label">Codigo generado</span>
            <div class="code-value">{{ $codigo }}</div>
        </div>

        <div class="barcode">
            @if($barcodePng)
                <img src="data:image/png;base64,{{ $barcodePng }}" alt="Codigo de barras" width="220" height="55">
            @elseif($codigo !== '' && class_exists('\DNS1D'))
                {!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.3, 45) !!}
            @endif
        </div>

        <div class="section">
            <div class="section-title">Datos del envio</div>
            <div class="row"><span class="label">Fecha:</span> {{ optional($preregistro->created_at)->format('d/m/Y H:i') }}</div>
            <div class="row"><span class="label">Origen:</span> {{ $preregistro->origen }}</div>
            <div class="row"><span class="label">Destino:</span> {{ optional($preregistro->destino)->nombre_destino ?: $preregistro->ciudad }}</div>
            <div class="row"><span class="label">Servicio:</span> {{ optional($preregistro->servicio)->nombre_servicio }}</div>
            <div class="row"><span class="label">Peso:</span> {{ $preregistro->peso }}</div>
            <div class="row"><span class="label">Contenido:</span> {{ $preregistro->contenido }}</div>
        </div>

        <div class="section">
            <div class="section-title">Remitente y destinatario</div>
            <div class="row"><span class="label">Remitente:</span> {{ $preregistro->nombre_remitente }}</div>
            <div class="row"><span class="label">Destinatario:</span> {{ $preregistro->nombre_destinatario }}</div>
            <div class="row"><span class="label">Direccion:</span> {{ $preregistro->direccion }}</div>
        </div>

        <div class="footer">
            Presenta este codigo en admision para recuperar automaticamente tu preregistro.
        </div>
    </div>
</body>
</html>

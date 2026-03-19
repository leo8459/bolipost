<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ticket {{ $solicitud->codigo_solicitud }}</title>
    <style>
        @page { size: 80mm auto; margin: 4mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #fff;
        }
        .ticket {
            width: 72mm;
            margin: 0 auto;
            padding: 2mm 0 6mm;
            font-size: 11px;
            line-height: 1.35;
        }
        .center { text-align: center; }
        .title {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }
        .subtitle {
            font-size: 11px;
            margin: 2px 0 8px;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 7px 0;
        }
        .code {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 1px;
            margin: 4px 0 2px;
        }
        .label {
            font-weight: 700;
            display: block;
        }
        .row {
            margin: 0 0 6px;
            word-break: break-word;
        }
        .price {
            font-size: 18px;
            font-weight: 700;
        }
        .barcode {
            margin: 6px 0 4px;
            text-align: center;
        }
        .barcode img {
            max-width: 100%;
            height: auto;
        }
        .muted {
            font-size: 10px;
        }
        .actions {
            width: 72mm;
            margin: 8px auto 0;
            text-align: center;
        }
        @media print {
            .actions { display: none; }
        }
    </style>
</head>
<body>
    @php
        $codigo = (string) $solicitud->codigo_solicitud;
        $barcodePng = null;

        if ($codigo !== '' && class_exists('\DNS1D')) {
            try {
                $barcodePng = DNS1D::getBarcodePNG($codigo, 'C128', 1.5, 40);
            } catch (\Throwable $exception) {
                $barcodePng = null;
            }
        }
    @endphp

    <div class="ticket">
        <div class="center">
            <p class="title">Correos de Bolivia</p>
            <p class="subtitle">Ticket de solicitud EMS</p>
        </div>

        <div class="divider"></div>

        <div class="center">
            <div class="code">{{ $codigo }}</div>
            @if($barcodePng)
                <div class="barcode">
                    <img src="data:image/png;base64,{{ $barcodePng }}" alt="Codigo de barras {{ $codigo }}">
                </div>
            @elseif($codigo !== '' && class_exists('\DNS1D'))
                <div class="barcode">{!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.1, 35) !!}</div>
            @endif
        </div>

        <div class="divider"></div>

        <div class="row">
            <span class="label">Servicio</span>
            {{ $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? '-') }}
        </div>
        <div class="row">
            <span class="label">Estado</span>
            {{ $solicitud->estadoRegistro?->nombre_estado ?? '-' }}
        </div>
        <div class="row">
            <span class="label">Origen / Destino</span>
            {{ $solicitud->origen ?: '-' }} / {{ $solicitud->destino?->nombre_destino ?: ($solicitud->ciudad ?: '-') }}
        </div>
        <div class="row">
            <span class="label">Remitente</span>
            {{ $solicitud->nombre_remitente ?: '-' }}
        </div>
        <div class="row">
            <span class="label">Destinatario</span>
            {{ $solicitud->nombre_destinatario ?: '-' }}
        </div>
        <div class="row">
            <span class="label">Contenido</span>
            {{ $solicitud->contenido ?: '-' }}
        </div>
        <div class="row">
            <span class="label">Peso</span>
            {{ $solicitud->peso !== null ? number_format((float) $solicitud->peso, 3, '.', '') . ' kg' : '-' }}
        </div>
        <div class="row">
            <span class="label">Pago en destinatario</span>
            {{ $solicitud->pago_destinatario ? 'SI' : 'NO' }}
        </div>

        <div class="divider"></div>

        <div class="center">
            <div class="muted">Total a cobrar</div>
            <div class="price">Bs {{ $solicitud->precio !== null ? number_format((float) $solicitud->precio, 2, '.', '') : '0.00' }}</div>
        </div>

        <div class="divider"></div>

        <div class="center muted">
            Fecha: {{ optional($solicitud->updated_at ?? $solicitud->created_at)->format('d/m/Y H:i') }}<br>
            Impresion para Epson TM-T20II
        </div>
    </div>

    <div class="actions">
        <button type="button" onclick="window.print()">Imprimir</button>
        <button type="button" onclick="window.close()">Cerrar</button>
    </div>

    <script>
    window.addEventListener('load', function () {
        setTimeout(function () {
            window.print();
        }, 250);
    });
    </script>
</body>
</html>

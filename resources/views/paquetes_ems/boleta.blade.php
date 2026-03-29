<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Boleta EMS</title>
    <style>
        @page { size: 80mm 260mm; margin: 4mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #000;
            background: #fff;
        }
        body {
            width: 72mm;
            margin: 0 auto;
            font-size: 10px;
            line-height: 1.35;
        }
        .ticket {
            width: 72mm;
            margin: 0 auto 6mm;
            page-break-inside: avoid;
        }
        .ticket:last-child {
            margin-bottom: 0;
        }
        .center { text-align: center; }
        .brand {
            border: 1px solid #000;
            padding: 2mm;
        }
        .brand img {
            max-width: 100%;
            height: auto;
        }
        .brand-title {
            font-size: 12px;
            font-weight: 700;
            margin-top: 2mm;
        }
        .copy-label {
            font-size: 9px;
            font-weight: 700;
            margin-top: 1mm;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .watermark {
            margin-top: 1mm;
            font-size: 8px;
            font-weight: 700;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 2.5mm 0;
        }
        .barcode {
            text-align: center;
        }
        .barcode img {
            max-width: 100%;
            height: auto;
        }
        .code {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: .06em;
            margin-top: 1mm;
            word-break: break-word;
        }
        .grid-2 {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .grid-2 > div {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 1.5mm;
        }
        .grid-2 > div:last-child {
            padding-right: 0;
            padding-left: 1.5mm;
        }
        .section {
            margin-bottom: 2mm;
            word-break: break-word;
        }
        .label {
            display: block;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .5mm;
        }
        .value {
            font-size: 10px;
            min-height: 4mm;
        }
        .value-sm {
            font-size: 9px;
        }
        .box {
            border: 1px solid #000;
            padding: 2mm;
        }
        .qr-row {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .qr-item {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .qr-item img {
            width: 22mm;
            height: 22mm;
            object-fit: contain;
        }
        .qr-caption {
            font-size: 8px;
            margin-bottom: 1mm;
        }
        .footer-note {
            font-size: 8px;
            text-align: center;
        }
        .signature-line {
            display: block;
            margin-top: 3mm;
            border-top: 1px solid #000;
            height: 0;
        }
    </style>
</head>
@php
    $codigo = (string) ($paquete->codigo ?? '');
    $barcodePngB64 = null;

    if ($codigo !== '' && class_exists('\DNS1D')) {
        try {
            $barcodePngB64 = DNS1D::getBarcodePNG($codigo, 'C128', 1.5, 42);
        } catch (\Throwable $e) {
            $barcodePngB64 = null;
        }
    }

    $origen = (string) ($paquete->origen ?? '');
    $ciudad = (string) ($paquete->ciudad ?? '');
    $nombreRemitente = (string) ($paquete->nombre_remitente ?? '');
    $nombreDestinatario = (string) ($paquete->nombre_destinatario ?? '');
    $telefonoRemitente = (string) ($paquete->telefono_remitente ?? '');
    $telefonoDestinatario = (string) ($paquete->telefono_destinatario ?? '');
    $contenido = (string) ($paquete->contenido ?? '');
    $peso = $paquete->peso !== null && $paquete->peso !== '' ? number_format((float) $paquete->peso, 3, '.', '') . ' kg' : '-';
    $precio = $paquete->precio !== null && $paquete->precio !== '' ? number_format((float) $paquete->precio, 2, '.', '') . ' Bs' : '-';
    $fecha = $paquete->created_at ?? now();
    $destinoTarifa = (string) (optional(optional($paquete->tarifario)->destino)->nombre_destino ?? '');
    $direccion = (string) ($paquete->direccion ?? optional($paquete->formulario)->direccion ?? '');
    $usuario = trim((string) (Auth::user()->name ?? ''));

    $marcaAgua = match ($destinoTarifa) {
        'SUPEREXPRESS' => 'NACIONAL SUPEREXPRESS',
        'DEVOLUCION' => 'NACIONAL CON DEVOLUCION',
        'NACIONAL' => 'NACIONAL EMS',
        'POSTPAGO' => 'NACIONAL POSTPAGO',
        'CIUDADES INTERMEDIAS' => 'CIUDADES INTERMEDIAS',
        'TRINIDAD COBIJA' => 'TRINIDAD COBIJA',
        'RIVERALTA GUAYARAMERIN' => 'RIVERALTA GUAYARAMERIN',
        'EMS COBERTURA 1' => 'EMS COBERTURA 1',
        'EMS COBERTURA 2' => 'EMS COBERTURA 2',
        'EMS COBERTURA 3' => 'EMS COBERTURA 3',
        'EMS COBERTURA 4' => 'EMS COBERTURA 4',
        default => '',
    };

    $logoPath = public_path('images/AGBClogo2.png');
    $qrRastreoPath = public_path('images/qr_trackingbo_8100.png');
    $qrWebPath = public_path('images/qr_correos_gob_bo.png');

    $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    $qrRastreoPngB64 = file_exists($qrRastreoPath) ? base64_encode(file_get_contents($qrRastreoPath)) : null;
    $qrWebPngB64 = file_exists($qrWebPath) ? base64_encode(file_get_contents($qrWebPath)) : null;
@endphp
<body>
@for ($i = 0; $i < 2; $i++)
    <div class="ticket">
        <div class="brand center">
            @if($logoB64)
                <img src="data:image/png;base64,{{ $logoB64 }}" alt="Correos de Bolivia">
            @endif
            <div class="brand-title">Boleta EMS</div>
            <div class="copy-label">Copia {{ $i + 1 }} de 2</div>
            @if($marcaAgua !== '')
                <div class="watermark">{{ $marcaAgua }}</div>
            @endif
        </div>

        <div class="divider"></div>

        <div class="barcode">
            @if($barcodePngB64)
                <img src="data:image/png;base64,{{ $barcodePngB64 }}" alt="Codigo de barras {{ $codigo }}">
            @elseif($codigo !== '' && class_exists('\DNS1D'))
                {!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.0, 42) !!}
            @endif
            <div class="code">{{ $codigo !== '' ? $codigo : 'SIN CODIGO' }}</div>
        </div>

        <div class="divider"></div>

        <div class="grid-2">
            <div>
                <div class="section">
                    <span class="label">Of. origen</span>
                    <div class="value">{{ $origen !== '' ? $origen : '-' }}</div>
                </div>
            </div>
            <div>
                <div class="section">
                    <span class="label">Of. destino</span>
                    <div class="value">{{ $ciudad !== '' ? $ciudad : '-' }}</div>
                </div>
            </div>
        </div>

        <div class="box">
            <div class="section">
                <span class="label">Nombre remitente</span>
                <div class="value">{{ $nombreRemitente !== '' ? $nombreRemitente : '-' }}</div>
            </div>
            <div class="section">
                <span class="label">Telefono remitente</span>
                <div class="value">{{ $telefonoRemitente !== '' ? $telefonoRemitente : '-' }}</div>
            </div>
            <div class="section">
                <span class="label">Nombre destinatario</span>
                <div class="value">{{ $nombreDestinatario !== '' ? $nombreDestinatario : '-' }}</div>
            </div>
            <div class="section">
                <span class="label">Direccion y telefono destinatario</span>
                <div class="value value-sm">
                    {{ $direccion !== '' ? $direccion : '-' }}<br>
                    {{ $telefonoDestinatario !== '' ? $telefonoDestinatario : '-' }}
                </div>
            </div>
            <div class="section">
                <span class="label">Descripcion</span>
                <div class="value value-sm">
                    {{ $contenido !== '' ? $contenido : '-' }}<br>
                    DESTINO: {{ $destinoTarifa !== '' ? $destinoTarifa : '-' }}
                </div>
            </div>
        </div>

        <div class="divider"></div>

        <div class="grid-2">
            <div>
                <div class="section">
                    <span class="label">Fecha y hora</span>
                    <div class="value value-sm">{{ \Carbon\Carbon::parse($fecha)->format('Y-m-d H:i:s') }}</div>
                </div>
                <div class="section">
                    <span class="label">Peso</span>
                    <div class="value">{{ $peso }}</div>
                </div>
            </div>
            <div>
                <div class="section">
                    <span class="label">Importe</span>
                    <div class="value">{{ $precio }}</div>
                </div>
                <div class="section">
                    <span class="label">Usuario</span>
                    <div class="value value-sm">{{ $usuario !== '' ? $usuario : '-' }}</div>
                </div>
            </div>
        </div>

        <div class="section">
            <span class="label">Firma</span>
            <span class="signature-line"></span>
        </div>

        <div class="divider"></div>

        <div class="qr-row">
            <div class="qr-item">
                <div class="qr-caption">Codigo de rastreo</div>
                @if($qrRastreoPngB64)
                    <img src="data:image/png;base64,{{ $qrRastreoPngB64 }}" alt="QR rastreo">
                @endif
            </div>
            <div class="qr-item">
                <div class="qr-caption">Sitio web</div>
                @if($qrWebPngB64)
                    <img src="data:image/png;base64,{{ $qrWebPngB64 }}" alt="QR web">
                @endif
            </div>
        </div>

        <div class="divider"></div>

        <div class="footer-note">
            Rastreo: tracking.correos.gob.bo<br>
            Web: correos.gob.bo
        </div>
    </div>
@endfor
</body>
</html>

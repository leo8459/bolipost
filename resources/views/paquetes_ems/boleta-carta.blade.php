<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Boleta EMS Carta</title>
    <style>
        @page { size: letter; margin: 18mm 16mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Verdana, DejaVu Sans, sans-serif;
            color: #000;
            font-size: 12px;
            line-height: 1.35;
        }
        .sheet {
            width: 100%;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: middle;
        }
        .header-left {
            width: 58%;
        }
        .header-right {
            width: 42%;
            text-align: right;
        }
        .logo {
            max-width: 165px;
            max-height: 58px;
            margin-bottom: 6px;
        }
        .title {
            color: #000;
            font-size: 24px;
            font-weight: 800;
            margin: 0;
            text-transform: uppercase;
        }
        .subtitle {
            color: #000;
            font-size: 12px;
            font-weight: 700;
        }
        .barcode img {
            width: 260px;
            height: 54px;
            object-fit: fill;
        }
        .code {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .08em;
            margin-top: 4px;
        }
        .badge {
            display: inline-block;
            border: 1px solid #000;
            color: #000;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
            margin-top: 6px;
        }
        .grid {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 12px;
        }
        .col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .col:first-child {
            padding-right: 7px;
        }
        .col:last-child {
            padding-left: 7px;
        }
        .box {
            border: 1px solid #000;
            border-radius: 6px;
            padding: 10px;
            min-height: 118px;
            margin-bottom: 12px;
        }
        .box-title {
            color: #000;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
            margin-bottom: 8px;
        }
        .row {
            display: table;
            width: 100%;
            margin-bottom: 6px;
        }
        .label,
        .value {
            display: table-cell;
            vertical-align: top;
        }
        .label {
            width: 36%;
            color: #000;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .value {
            width: 64%;
            color: #000;
            font-size: 12px;
            font-weight: 700;
            word-break: break-word;
        }
        .full-box {
            border: 1px solid #000;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 12px;
        }
        .summary {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-bottom: 14px;
        }
        .summary-item {
            display: table-cell;
            border: 1px solid #000;
            padding: 9px;
            text-align: center;
        }
        .summary-item + .summary-item {
            border-left: 0;
        }
        .summary-label {
            color: #000;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .summary-value {
            color: #000;
            font-size: 15px;
            font-weight: 800;
        }
        .responsibility {
            border: 1px solid #000;
            padding: 9px;
            font-size: 10px;
            text-align: justify;
            margin-top: 10px;
        }
        .signatures {
            display: table;
            width: 100%;
            table-layout: fixed;
            margin-top: 34px;
        }
        .signature {
            display: table-cell;
            width: 50%;
            text-align: center;
            padding: 0 18px;
        }
        .line {
            border-top: 1px solid #000;
            padding-top: 6px;
            font-weight: 800;
            font-size: 11px;
            text-transform: uppercase;
        }
        .footer {
            margin-top: 18px;
            text-align: center;
            color: #000;
            font-size: 10px;
        }
    </style>
</head>
@php
    $codigo = (string) ($paquete->codigo ?? '');
    $barcodePngB64 = null;

    if ($codigo !== '' && class_exists('\DNS1D')) {
        try {
            $barcodePngB64 = DNS1D::getBarcodePNG($codigo, 'C128', 2.0, 70);
        } catch (\Throwable $e) {
            $barcodePngB64 = null;
        }
    }

    $origen = (string) ($paquete->origen ?? '');
    $ciudad = (string) ($paquete->ciudad ?? '');
    $nombreRemitente = (string) ($paquete->nombre_remitente ?? '');
    $nombreEnvia = (string) ($paquete->nombre_envia ?? '');
    $carnet = (string) ($paquete->carnet ?? '');
    $nombreDestinatario = (string) ($paquete->nombre_destinatario ?? '');
    $telefonoRemitente = (string) ($paquete->telefono_remitente ?? '');
    $telefonoDestinatario = (string) ($paquete->telefono_destinatario ?? '');
    $contenido = (string) ($paquete->contenido ?? '');
    $direccion = (string) ($paquete->direccion ?? optional($paquete->formulario)->direccion ?? '');
    $referencia = (string) ($paquete->referencia ?? optional($paquete->formulario)->referencia ?? '');
    $servicio = (string) (optional(optional($paquete->tarifario)->servicio)->nombre_servicio ?? $paquete->tipo_correspondencia ?? '');
    $servicioEspecial = (string) ($paquete->servicio_especial ?? '');
    $cantidad = $paquete->cantidad !== null && $paquete->cantidad !== '' ? (string) $paquete->cantidad : '-';
    $peso = $paquete->peso !== null && $paquete->peso !== '' ? number_format((float) $paquete->peso, 3, '.', '') . ' kg' : '-';
    $precio = $paquete->precio !== null && $paquete->precio !== '' ? number_format((float) $paquete->precio, 2, '.', '') . ' Bs' : '-';
    $fecha = \Carbon\Carbon::parse($paquete->created_at ?? now())->format('d/m/Y H:i:s');
    $usuario = trim((string) (Auth::user()->name ?? ''));
    $logoPath = public_path('images/AGBClogo1.png');
    $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
@endphp
<body>
    <main class="sheet">
        <header class="header">
            <div class="header-left">
                @if($logoB64)
                    <img src="data:image/png;base64,{{ $logoB64 }}" class="logo" alt="Correos de Bolivia">
                @endif
                <h1 class="title">Boleta EMS</h1>
                <div class="subtitle">Comprobante de admision postal</div>
                @if($servicio !== '')
                    <div class="badge">{{ $servicio }}</div>
                @endif
            </div>
            <div class="header-right">
                <div class="barcode">
                    @if($barcodePngB64)
                        <img src="data:image/png;base64,{{ $barcodePngB64 }}" alt="Codigo de barras {{ $codigo }}">
                    @endif
                    <div class="code">{{ $codigo !== '' ? $codigo : 'SIN CODIGO' }}</div>
                </div>
            </div>
        </header>

        <section class="summary">
            <div class="summary-item">
                <div class="summary-label">Fecha</div>
                <div class="summary-value">{{ $fecha }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Origen</div>
                <div class="summary-value">{{ $origen !== '' ? $origen : '-' }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Destino</div>
                <div class="summary-value">{{ $ciudad !== '' ? $ciudad : '-' }}</div>
            </div>
        </section>

        <section class="grid">
            <div class="col">
                <div class="box">
                    <div class="box-title">Remitente</div>
                    <div class="row"><div class="label">Nombre</div><div class="value">{{ $nombreRemitente ?: '-' }}</div></div>
                    <div class="row"><div class="label">Empresa</div><div class="value">{{ $nombreEnvia ?: '-' }}</div></div>
                    <div class="row"><div class="label">Carnet</div><div class="value">{{ $carnet ?: '-' }}</div></div>
                    <div class="row"><div class="label">Telefono</div><div class="value">{{ $telefonoRemitente ?: '-' }}</div></div>
                </div>
            </div>
            <div class="col">
                <div class="box">
                    <div class="box-title">Destinatario</div>
                    <div class="row"><div class="label">Nombre</div><div class="value">{{ $nombreDestinatario ?: '-' }}</div></div>
                    <div class="row"><div class="label">Telefono</div><div class="value">{{ $telefonoDestinatario ?: '-' }}</div></div>
                    <div class="row"><div class="label">Direccion</div><div class="value">{{ $direccion ?: '-' }}</div></div>
                    <div class="row"><div class="label">Referencia</div><div class="value">{{ $referencia ?: '-' }}</div></div>
                </div>
            </div>
        </section>

        <section class="full-box">
            <div class="box-title">Detalle del envio</div>
            <div class="grid">
                <div class="col">
                    <div class="row"><div class="label">Contenido</div><div class="value">{{ $contenido ?: '-' }}</div></div>
                    <div class="row"><div class="label">Servicio esp.</div><div class="value">{{ $servicioEspecial ?: '-' }}</div></div>
                    <div class="row"><div class="label">Usuario</div><div class="value">{{ $usuario ?: '-' }}</div></div>
                </div>
                <div class="col">
                    <div class="row"><div class="label">Cantidad</div><div class="value">{{ $cantidad }}</div></div>
                    <div class="row"><div class="label">Peso</div><div class="value">{{ $peso }}</div></div>
                    <div class="row"><div class="label">Importe</div><div class="value">{{ $precio }}</div></div>
                </div>
            </div>
        </section>

        <section class="responsibility">
            El cliente declara que los datos proporcionados son ciertos; y que el contenido cumple con las normas de seguridad postal,
            bajo su unica y exclusiva responsabilidad.
        </section>

        <section class="signatures">
            <div class="signature"><div class="line">Firma del cliente</div></div>
            <div class="signature"><div class="line">Firma del operador</div></div>
        </section>

        <footer class="footer">
            Documento generado por TrackingBO - Correos de Bolivia
        </footer>
    </main>
</body>
</html>

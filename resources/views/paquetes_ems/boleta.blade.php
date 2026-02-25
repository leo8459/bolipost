<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Boleta CN22</title>
    <style>
        @page { size: letter; margin: 8mm; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            width: 200mm;
            height: 263mm;
            box-sizing: border-box;
        }
        body { padding: 8mm; }
        .boleta { position: relative; height: 65mm; width: 200mm; }
        .boleta + .boleta { margin-top: 0.1mm; border-top: 0; padding-top: 0; }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 9px; }
        @media print{
            html, body { width: 200mm; height: 263mm; }
            body { padding: 8mm; }
            .boleta { width: 200mm; }
        }
        th, td { border: 1px solid #000; padding: 1px; vertical-align: top; }
        thead { background-color: #ffffff; }
        .watermark-local {
            position: absolute;
            top: 10%;
            left: 35%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 42px;
            color: rgba(120, 120, 120, .18);
            white-space: nowrap;
            pointer-events: none;
            user-select: none;
            z-index: 5;
        }
        .barcode { text-align: center; }
    </style>
</head>
@php
    $codigo = $paquete->codigo ?? '';
    $barcodePngB64 = null;
    if (!empty($codigo)) {
        try {
            $barcodePngB64 = DNS1D::getBarcodePNG($codigo, 'C128');
        } catch (\Throwable $e) {
            $barcodePngB64 = null;
        }
    }
    $origen = $paquete->origen ?? '';
    $ciudad = $paquete->ciudad ?? '';
    $nombre_remitente = $paquete->nombre_remitente ?? '';
    $nombre_destinatario = $paquete->nombre_destinatario ?? '';
    $telefono_remitente = $paquete->telefono_remitente ?? '';
    $telefono_destinatario = $paquete->telefono_destinatario ?? '';
    $contenido = $paquete->contenido ?? '';
    $peso = $paquete->peso ?? '';
    $precio = $paquete->precio ?? '';
    $fecha = $paquete->created_at ?? now();
    $destino = optional(optional($paquete->tarifario)->destino)->nombre_destino ?? '';
    $direccion = $paquete->direccion ?? optional($paquete->formulario)->direccion ?? '';

    $marcaAgua = match ($destino) {
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

    $logoPath = public_path('images/images.png');
    $qrRastreoPath = public_path('images/qr_trackingbo_8100.png');
    $qrWebPath     = public_path('images/qr_correos_gob_bo.png');

    $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    $qrRastreoPngB64 = file_exists($qrRastreoPath) ? base64_encode(file_get_contents($qrRastreoPath)) : null;
    $qrWebPngB64     = file_exists($qrWebPath) ? base64_encode(file_get_contents($qrWebPath)) : null;
@endphp

<body>
@for ($i = 0; $i < 2; $i++)
    <div class="boleta">
        <table>
            <colgroup>
                <col style="width: 10%">
                <col style="width: 12%">
                <col style="width: 11%">
                <col style="width: 12%">
                <col style="width: 20%">
                <col style="width: 17%">
                <col style="width: 8%">
                <col style="width: 10%">
            </colgroup>
            <thead>
                <tr>
                    <td colspan="3">
                        @if($logoB64)
                            <img src="data:image/png;base64,{{ $logoB64 }}" alt="AGBC" width="150" height="50"><br>
                        @endif
                    </td>
                    <td colspan="3" rowspan="2" class="barcode">
                        <div style="display: inline-block; text-align: center;">
                            <div style="margin-bottom: 5px;">
                                @if($barcodePngB64)
                                    <img src="data:image/png;base64,{{ $barcodePngB64 }}" alt="Barcode" width="180" height="40">
                                @else
                                    {!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.0, 40) !!}
                                @endif
                            </div>
                            <span style="font-size: 16px; font-weight: bold;">{{ $codigo }}</span>
                        </div>
                    </td>
                    <td rowspan="8" style="text-align:center;font-size:7px;vertical-align:middle;">
                        <div style="font-size:8px; margin-bottom:2px;">Código de rastreo</div>
                        @if($qrRastreoPngB64)
                            <img src="data:image/png;base64,{{ $qrRastreoPngB64 }}" alt="QR Rastreo" width="60" height="60"><br>
                        @endif
                        <hr style="border:0;border-top:1px dotted #000;margin:4px 0;">
                        <div style="font-size:8px; margin-bottom:2px;">Sitio web</div>
                        @if($qrWebPngB64)
                            <img src="data:image/png;base64,{{ $qrWebPngB64 }}" alt="QR Web" width="60" height="60"><br>
                        @endif
                        <span style="font-size:8px;">correos.gob.bo</span>
                    </td>
                </tr>
                <tr>
                    <td>OF. ORIGEN: <br>
                        <div style="text-align: right;">{{ $origen }}</div>
                    </td>
                    <td>OF. DESTINO: <br>
                        <div style="text-align: right;">{{ $ciudad }}</div>
                    </td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="3" rowspan="2">
                        NOMBRE REMITENTE: <br>
                        <div style="text-align: right; font-size: 8px;">{{ $nombre_remitente }}</div>
                    </td>
                    <td colspan="3" rowspan="2">NOMBRE DESTINATARIO: <br>
                        <div style="text-align: right; font-size: 8px;">{{ $nombre_destinatario }}</div>
                    </td>
                </tr>
                <tr></tr>
                <tr>
                    <td colspan="3" rowspan="2">DIRECCIÓN Y TELÉFONO:
                        <div style="text-align: right; font-size: 8px;"><br>{{ $telefono_remitente }}</div>
                    </td>
                    <td colspan="3" rowspan="2">DIRECCIÓN Y TELÉFONO:<br>
                        <div style="text-align: right; font-size: 8px;">
                            {{ $direccion }}<br>{{ $telefono_destinatario }}</div>
                    </td>
                </tr>
                <tr></tr>
                <tr>
                    <td colspan="3">DESCRIPCIÓN:
                        <div style="text-align: justify; font-size: 8px; word-wrap: break-word; white-space: pre-line;">
                            @if (!empty($contenido))
                                {{ $contenido }}<br>
                            @endif
                            DESTINO: {{ $destino }}
                        </div>
                    </td>
                    <td rowspan="2" style="vertical-align: top;">
                        {{ Auth::user()->name ?? '' }}:<br>
                    </td>
                    <td colspan="2" rowspan="2" style="vertical-align: top;">FIRMA :<br></td>
                </tr>
                <tr>
                    <td>FECHA Y HORA:<br>
                        <div style="text-align: right;">{{ \Carbon\Carbon::parse($fecha)->format('Y-m-d H:i:s') }}</div>
                    </td>
                    <td>PESO:<br>
                        <div style="text-align: right;">{{ $peso }} kg</div>
                    </td>
                    <td>IMPORTE: <br>
                        <div style="text-align: right;">{{ $precio }}</div>
                    </td>
                </tr>
            </thead>
        </table>
        @if ($marcaAgua)
            <div class="watermark-local">{{ $marcaAgua }}</div>
        @endif
    </div>
@endfor
</body>
</html>

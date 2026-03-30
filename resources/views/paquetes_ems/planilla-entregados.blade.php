<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planilla EMS entregados</title>
    <style>
        @page { size: letter; margin: 8mm; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            color: #000;
        }
        body { padding: 0; }
        .sheet {
            height: 263mm;
            page-break-after: always;
        }
        .sheet:last-child {
            page-break-after: auto;
        }
        .boleta {
            position: relative;
            height: 123mm;
            width: 200mm;
            margin: 0 auto 1.5mm auto;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .boleta:last-child {
            margin-bottom: 0;
        }
        table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 9px; }
        th, td { border: 1px solid #000; padding: 1px; vertical-align: top; }
        thead { background-color: #ffffff; }
        .watermark-local {
            position: absolute;
            top: 12%;
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
        .sheet-spacer {
            height: 1.5mm;
        }
    </style>
</head>
@php
    $logoPath = public_path('images/images.png');
    $qrRastreoPath = public_path('images/qr_trackingbo_8100.png');
    $qrWebPath = public_path('images/qr_correos_gob_bo.png');

    $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    $qrRastreoPngB64 = file_exists($qrRastreoPath) ? base64_encode(file_get_contents($qrRastreoPath)) : null;
    $qrWebPngB64 = file_exists($qrWebPath) ? base64_encode(file_get_contents($qrWebPath)) : null;
@endphp
<body>
@foreach($paquetes->chunk(2) as $grupo)
    <div class="sheet">
        @foreach($grupo as $paquete)
            @php
                $codigo = (string) ($paquete->codigo ?? '');
                $barcodePngB64 = null;
                if ($codigo !== '') {
                    try {
                        $barcodePngB64 = DNS1D::getBarcodePNG($codigo, 'C128');
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
                $peso = $paquete->peso ?? '';
                $precio = $paquete->precio ?? '';
                $fecha = $paquete->created_at ?? $paquete->updated_at ?? now();
                $destino = (string) (optional(optional($paquete->tarifario)->destino)->nombre_destino ?? '');
                $direccion = (string) ($paquete->direccion ?? optional($paquete->formulario)->direccion ?? '');

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
                    default => 'EMS ENTREGADO',
                };
            @endphp

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
                                        @elseif($codigo !== '' && class_exists('\DNS1D'))
                                            {!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.0, 40) !!}
                                        @endif
                                    </div>
                                    <span style="font-size: 16px; font-weight: bold;">{{ $codigo }}</span>
                                </div>
                            </td>
                            <td rowspan="8" style="text-align:center;font-size:7px;vertical-align:middle;">
                                <div style="font-size:8px; margin-bottom:2px;">Codigo de rastreo</div>
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
                                <div style="text-align: right; font-size: 8px;">{{ $nombreRemitente }}</div>
                            </td>
                            <td colspan="3" rowspan="2">NOMBRE DESTINATARIO: <br>
                                <div style="text-align: right; font-size: 8px;">{{ $nombreDestinatario }}</div>
                            </td>
                        </tr>
                        <tr></tr>
                        <tr>
                            <td colspan="3" rowspan="2">DIRECCION Y TELEFONO:
                                <div style="text-align: right; font-size: 8px;"><br>{{ $telefonoRemitente }}</div>
                            </td>
                            <td colspan="3" rowspan="2">DIRECCION Y TELEFONO:<br>
                                <div style="text-align: right; font-size: 8px;">
                                    {{ $direccion }}<br>{{ $telefonoDestinatario }}</div>
                            </td>
                        </tr>
                        <tr></tr>
                        <tr>
                            <td colspan="3">DESCRIPCION:
                                <div style="text-align: justify; font-size: 8px; word-wrap: break-word; white-space: pre-line;">
                                    @if ($contenido !== '')
                                        {{ $contenido }}<br>
                                    @endif
                                    DESTINO: {{ $destino }}
                                </div>
                            </td>
                            <td rowspan="2" style="vertical-align: top;">
                                {{ auth()->user()->name ?? '' }}:<br>
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
                <div class="watermark-local">{{ $marcaAgua }}</div>
            </div>

            @if (! $loop->last)
                <div class="sheet-spacer"></div>
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>

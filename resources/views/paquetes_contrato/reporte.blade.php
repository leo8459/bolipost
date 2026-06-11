<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Guia Contrato</title>
    <style>
        @page { size: letter; margin: 5mm 6mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Verdana, DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #000;
        }
        .sheet {
            width: 100%;
        }
        .guide-copy {
            height: 81mm;
            border: 1.4px solid #000;
            border-radius: 5px;
            margin-bottom: 2.2mm;
            overflow: hidden;
            page-break-inside: avoid;
        }
        .guide-copy:last-child {
            margin-bottom: 0;
        }
        .guide-header {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            background: #fff;
            border-bottom: 1px solid #000;
        }
        .guide-header td {
            border: 0;
            padding: 1.5px 6px;
            vertical-align: middle;
        }
        .brand-logo {
            display: block;
            width: 25mm;
            max-height: 8mm;
            object-fit: contain;
        }
        .brand-layout {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .brand-layout td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }
        .brand-logo-slot {
            width: 27mm;
            text-align: left;
        }
        .brand-qr-slot {
            width: 20mm;
            text-align: left;
            padding-left: 3mm !important;
        }
        .copy-badge {
            display: inline-block;
            min-width: 86px;
            padding: 3px 7px;
            border-radius: 12px;
            background: #fff;
            color: #000;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }
        .barcode-cell {
            text-align: center;
        }
        .verification-qr {
            display: inline-block;
            width: 13mm;
            height: 13mm;
        }
        .verification-label {
            display: block;
            margin-top: 1px;
            font-size: 5px;
            font-weight: 800;
            line-height: 1;
            text-transform: uppercase;
        }
        .barcode-code {
            margin-top: 1px;
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: .4px;
            color: #000;
        }
        .content {
            padding: 3px 6px 2px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .info-table td {
            border: 1px solid #000;
            padding: 1.5px 4px;
            vertical-align: top;
            line-height: 1.02;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .section-label {
            display: block;
            margin-bottom: 1px;
            color: #000;
            font-size: 7.2px;
            font-weight: 800;
            letter-spacing: .35px;
            text-transform: uppercase;
        }
        .value {
            font-size: 9px;
            font-weight: 700;
            color: #000;
        }
        .small-value {
            font-size: 8.1px;
            font-weight: 600;
        }
        .long-cell {
            height: 11mm;
            overflow: hidden;
        }
        .long-value {
            display: block;
            max-height: 8mm;
            overflow: hidden;
            font-size: 7.3px;
            font-weight: 700;
            line-height: 1.0;
            word-break: break-word;
        }
        .content-value {
            display: block;
            max-height: 9mm;
            overflow: hidden;
            font-size: 7px;
            font-weight: 700;
            line-height: 1.0;
            word-break: break-word;
        }
        .return-title {
            font-size: 7.8px;
            font-weight: 800;
            color: #000;
            text-transform: uppercase;
        }
        .return-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: -1px;
        }
        .return-grid td {
            border: 1px solid #000;
            padding: 1.5px 3px;
            font-size: 7.1px;
            line-height: 1.0;
        }
        .box {
            display: inline-block;
            width: 7px;
            height: 7px;
            border: 1px solid #000;
            float: right;
        }
        .note {
            margin-top: 2px;
            padding: 2px 5px;
            border: 1px solid #000;
            background: #fff;
            font-size: 6.5px;
            line-height: 1.0;
            text-align: justify;
        }
        .signatures {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 0;
        }
        .signatures td {
            border: 0;
            padding: .5mm 10px 0;
            text-align: center;
            font-size: 7px;
            font-weight: 700;
            color: #000;
            height: 6mm;
            vertical-align: middle;
        }
    </style>
</head>
@php
    $codigo = (string) ($contrato->codigo ?? '');
    $barcodePngB64 = null;
    if ($codigo !== '' && class_exists('\DNS1D')) {
        try {
            $barcodePngB64 = @DNS1D::getBarcodePNG($codigo, 'C128', 1.45, 36);
        } catch (\Throwable $e) {
            $barcodePngB64 = null;
        }
    }
    $verificationQrB64 = null;
    $verificationUrl = $verificationUrl ?? null;
    if (!empty($verificationUrl) && class_exists('\DNS2D')) {
        try {
            $verificationQrB64 = @DNS2D::getBarcodePNG($verificationUrl, 'QRCODE', 4, 4);
        } catch (\Throwable $e) {
            $verificationQrB64 = null;
        }
    }

    $departamentoDestino = (string) ($contrato->destino ?? '-');
    $provincia = trim((string) ($contrato->provincia ?? ''));
    $departamentoDetalle = $departamentoDestino;
    if ($provincia !== '') {
        $departamentoDetalle .= ' - PROVINCIA: ' . strtoupper($provincia);
    }
    $fechaRecojo = optional($contrato->fecha_recojo ?? null)->format('d/m/Y H:i') ?: optional($contrato->created_at ?? null)->format('d/m/Y H:i');
    $empresaNombre = trim((string) (optional($contrato->empresa)->nombre ?? optional(optional($contrato->user)->empresa)->nombre ?? ''));
    $copias = ['ORIGINAL', 'COPIA 1', 'COPIA 2'];
    $logoPath = public_path('images/AGBClogo1.png');
    $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
@endphp
<body>
    <div class="sheet">
        @foreach($copias as $copia)
            <div class="guide-copy">
                <table class="guide-header">
                    <tr>
                        <td style="width: 40%;">
                            <table class="brand-layout">
                                <tr>
                                    <td class="brand-logo-slot">
                                        @if($logoB64)
                                            <img class="brand-logo" src="data:image/png;base64,{{ $logoB64 }}" alt="Correos de Bolivia">
                                        @else
                                            <strong>Correos de Bolivia</strong>
                                        @endif
                                    </td>
                                    <td class="brand-qr-slot">
                                        @if($verificationQrB64)
                                            <img class="verification-qr" src="data:image/png;base64,{{ $verificationQrB64 }}" alt="QR verificacion">
                                            <span class="verification-label">QR verificacion</span>
                                        @endif
                                    </td>
                                    <td></td>
                                </tr>
                            </table>
                        </td>
                        <td style="width: 20%; text-align:center;">
                            <span class="copy-badge">{{ $copia }}</span>
                        </td>
                        <td class="barcode-cell" style="width: 40%;">
                            @if($barcodePngB64)
                                <img src="data:image/png;base64,{{ $barcodePngB64 }}" alt="Barcode" width="235" height="34">
                            @elseif($codigo !== '' && class_exists('\DNS1D'))
                                {!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.05, 34) !!}
                            @endif
                            <div class="barcode-code">{{ $codigo }}</div>
                        </td>
                    </tr>
                </table>

                <div class="content">
                    <table class="info-table">
                        <tr>
                            <td colspan="2" style="width: 50%;">
                                <span class="section-label">Remitente</span>
                                <span class="value">{{ $contrato->nombre_r }}</span>
                            </td>
                            <td colspan="2" style="width: 50%;">
                                <span class="section-label">Destinatario</span>
                                <span class="value">{{ $contrato->nombre_d }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <span class="section-label">Telefono remitente</span>
                                <span class="small-value">{{ $contrato->telefono_r }}</span>
                            </td>
                            <td>
                                <span class="section-label">Origen</span>
                                <span class="small-value">{{ $contrato->origen }}</span>
                            </td>
                            <td>
                                <span class="section-label">Telefono destinatario</span>
                                <span class="small-value">{{ $contrato->telefono_d ?: '-' }}</span>
                            </td>
                            <td>
                                <span class="section-label">Destino</span>
                                <span class="small-value">{{ $departamentoDetalle }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" class="long-cell">
                                <span class="section-label">Direccion remitente</span>
                                <span class="long-value">{{ $contrato->direccion_r }}</span>
                            </td>
                            <td colspan="2" class="long-cell">
                                <span class="section-label">Direccion destinatario</span>
                                <span class="long-value">{{ $contrato->direccion_d }}</span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <span class="section-label">Empresa</span>
                                <span class="small-value">{{ $empresaNombre !== '' ? $empresaNombre : '-' }}</span>
                            </td>
                            <td>
                                <span class="section-label">Fecha solicitud</span>
                                <span class="small-value">{{ $fechaRecojo ?: '-' }}</span>
                            </td>
                            <td>
                                <span class="section-label">Contenido</span>
                                <span class="content-value">{{ $contrato->contenido ?: '-' }}</span>
                            </td>
                        </tr>
                    </table>

                    <table class="return-grid">
                        <tr>
                            <td style="width: 18%;"><span class="return-title">Devolucion CN 15</span></td>
                            <td>Se mudo <span class="box"></span><br></td>
                            <td>No reclamado <span class="box"></span><br></td>
                            <td>Desconocido <span class="box"></span><br></td>
                            <td>Rechazado <span class="box"></span><br></td>
                            <td>Direccion insuficiente <span class="box"></span><br></td>
                            <td>Se ausento <span class="box"></span><br></td>
                        </tr>
                    </table>

                    <div class="note">
                        El cliente declara que los datos proporcionados son ciertos y que el contenido cumple con las normas de seguridad postal, bajo su unica y exclusiva responsabilidad. Correos de Bolivia recibe esta guia para su proceso de admision y distribucion.
                    </div>

                    <table class="signatures">
                        <tr>
                            <td>Firma remitente</td>
                            <td>Recibido por Correos de Bolivia</td>
                            <td>Firma destinatario</td>
                        </tr>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>

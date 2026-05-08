<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte Contrato</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #000; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th {
            border: 1px solid #333;
            padding: 6px;
            vertical-align: top;
            line-height: 1.25;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .label { font-weight: 700; }
        .value { white-space: normal; word-wrap: break-word; overflow-wrap: break-word; }
        .barcode-cell { text-align: center; vertical-align: middle; }
        .barcode-code { margin-top: 4px; font-size: 24px; font-weight: 700; letter-spacing: 0.5px; word-wrap: break-word; }
        .box { display: inline-block; width: 14px; height: 14px; border: 1px solid #333; vertical-align: middle; margin-left: 8px; }
        .dots { border-top: 2px dotted #666; margin-top: 16px; }
        .compact { font-size: 12px; }
        .full-text { min-height: 26px; }
    </style>
</head>
@php
    $codigo = (string) ($contrato->codigo ?? '');
    $barcodePngB64 = null;
    if ($codigo !== '' && class_exists('\DNS1D')) {
        try {
            $barcodePngB64 = DNS1D::getBarcodePNG($codigo, 'C128', 1.6, 45);
        } catch (\Throwable $e) {
            $barcodePngB64 = null;
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
@endphp
<body>
    <table>
        <tr>
            <td style="width:48%;">
                <span class="label">REMITENTE:</span> <span class="value">{{ $contrato->nombre_r }}</span>
            </td>
            <td class="barcode-cell" rowspan="3">
                @if($barcodePngB64)
                    <img src="data:image/png;base64,{{ $barcodePngB64 }}" alt="Barcode" width="320" height="55">
                @elseif($codigo !== '' && class_exists('\DNS1D'))
                    {!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.2, 45) !!}
                @endif
                <div class="barcode-code">{{ $codigo }}</div>
            </td>
        </tr>
        <tr>
            <td><span class="label">TELEFONO:</span> <span class="value">{{ $contrato->telefono_r }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Direccion:</span> <span class="value">{{ $contrato->direccion_r }}</span></td>
        </tr>
        <tr>
            <td><span class="label">Departamento:</span> <span class="value">{{ $contrato->origen }}</span></td>
            <td><span class="label">Empresa:</span> <span class="value">{{ $empresaNombre !== '' ? $empresaNombre : '-' }}</span></td>
        </tr>
        <tr>
            <td style="width:24%;">
                <span class="label">DEVOLUCION</span><br>
                RETOUR
            </td>
            <td style="width:24%;">
                <span class="label">CN 15</span>
            </td>
        </tr>
    </table>

    <table style="margin-top:-1px;">
        <tr>
            <td style="width:25%;">
                <span class="label">Se Mudo</span><span class="box"></span><br>
                Demenage
            </td>
            <td style="width:25%;">
                <span class="label">No Reclamado</span><span class="box"></span><br>
                Non Reclamado
            </td>
            <td style="width:50%;">
                <span class="label">DESTINATARIO:</span> <span class="value">{{ $contrato->nombre_d }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Desconocido</span><span class="box"></span><br>
                Inconnu
            </td>
            <td>
                <span class="label">Rechazado</span><span class="box"></span><br>
                Refuse
            </td>
            <td>
                <span class="label">TELEFONO DESTINATARIO:</span> <span class="value">{{ $contrato->telefono_d ?: '-' }}</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Direccion Insuficiente</span><span class="box"></span><br>
                Adresse Insuffisante
            </td>
            <td>
                <span class="label">Se Asento</span><span class="box"></span><br>
                Parti
            </td>
            <td>
                <span class="label">Direccion:</span> <span class="value">{{ $contrato->direccion_d }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="3">
                <span class="label">Departamento:</span> <span class="value">{{ $departamentoDetalle }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="full-text">
                <span class="label">Contenido:</span> <span class="value">{{ $contrato->contenido ?: '-' }}</span>
            </td>
        </tr>
        <tr>
            <td colspan="3" class="compact">
                <span class="label">Fecha de solicitud:</span> <span class="value">{{ $fechaRecojo ?: '-' }}</span>
                @if(!empty($contrato->justificacion))
                    <br><span class="label">Justificacion:</span> <span class="value">{{ $contrato->justificacion }}</span>
                @endif
            </td>
        </tr>
    </table>

    <div class="dots"></div>
</body>
</html>

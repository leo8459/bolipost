<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte Contrato</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #000; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { border: 1px solid #333; padding: 6px; vertical-align: top; }
        .label { font-weight: 700; }
        .barcode-cell { text-align: center; vertical-align: middle; }
        .barcode-code { margin-top: 4px; font-size: 26px; font-weight: 700; letter-spacing: 1px; }
        .box { display: inline-block; width: 14px; height: 14px; border: 1px solid #333; vertical-align: middle; margin-left: 8px; }
        .dots { border-top: 2px dotted #666; margin-top: 16px; }
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
@endphp
<body>
    <table>
        <tr>
            <td style="width:48%;">
                <span class="label">REMITENTE:</span> {{ $contrato->nombre_r }}
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
            <td><span class="label">TELEFONO:</span> {{ $contrato->telefono_r }}</td>
        </tr>
        <tr>
            <td><span class="label">Direccion:</span> {{ $contrato->direccion_r }}</td>
        </tr>
        <tr>
            <td><span class="label">Departamento:</span> {{ $contrato->origen }}</td>
            <td></td>
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
                <span class="label">DESTINATARIO:</span> {{ $contrato->nombre_d }}
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
                <span class="label">TELEFONO DESTINATARIO:</span> {{ $contrato->telefono_d ?: '-' }}
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
                <span class="label">Direccion:</span> {{ $contrato->direccion_d }}
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="label">Contenido:</span> {{ $contrato->contenido }}
            </td>
            <td>
                <span class="label">Departamento:</span> {{ $departamentoDetalle }}
            </td>
        </tr>
    </table>

    <div class="dots"></div>
</body>
</html>

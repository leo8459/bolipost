<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Planilla de Entrega</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #000; margin: 0; }
        .sheet {
            page-break-after: always;
        }
        .sheet:last-child {
            page-break-after: auto;
        }
        .ticket {
            page-break-inside: avoid;
            break-inside: avoid;
            margin-bottom: 8mm;
        }
        .ticket:last-child {
            margin-bottom: 0;
        }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { border: 1px solid #333; padding: 6px; vertical-align: top; }
        .label { font-weight: 700; }
        .barcode-cell { text-align: center; vertical-align: middle; }
        .barcode-code { margin-top: 4px; font-size: 22px; font-weight: 700; letter-spacing: 1px; }
        .box-title {
            font-size: 16px;
            font-weight: 700;
            text-align: center;
            padding: 8px 0;
        }
        .muted {
            color: #555;
            font-size: 11px;
        }
    </style>
</head>
@php
    $codigo = (string) ($contrato->codigo ?? '');
    $barcodePngB64 = null;
    if ($codigo !== '' && class_exists('\DNS1D')) {
        try {
            $barcodePngB64 = DNS1D::getBarcodePNG($codigo, 'C128', 1.5, 45);
        } catch (\Throwable $e) {
            $barcodePngB64 = null;
        }
    }
@endphp
<body>
    <div class="sheet">
        @for ($i = 0; $i < 2; $i++)
            <div class="ticket">
                <table>
                    <tr>
                        <td colspan="2" class="box-title">PLANILLA DE ENTREGA</td>
                    </tr>
                    <tr>
                        <td style="width: 52%;">
                            <div><span class="label">EMPRESA:</span> {{ optional($contrato->empresa)->nombre ?? 'SIN EMPRESA' }}</div>
                            <div><span class="label">SIGLA:</span> {{ optional($contrato->empresa)->sigla ?? '-' }}</div>
                            <div><span class="label">CODIGO CLIENTE:</span> {{ optional($contrato->empresa)->codigo_cliente ?? '-' }}</div>
                            <div><span class="label">TIPO:</span> PLANILLA DE ENTREGA</div>
                        </td>
                        <td class="barcode-cell">
                            @if($barcodePngB64)
                                <img src="data:image/png;base64,{{ $barcodePngB64 }}" alt="Barcode" width="300" height="55">
                            @elseif($codigo !== '' && class_exists('\DNS1D'))
                                {!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.2, 45) !!}
                            @endif
                            <div class="barcode-code">{{ $codigo }}</div>
                        </td>
                    </tr>
                </table>

                <table style="margin-top:-1px;">
                    <tr>
                        <td style="width: 25%;"><span class="label">ORIGEN:</span><br>{{ $contrato->origen }}</td>
                        <td style="width: 25%;"><span class="label">DESTINO:</span><br>{{ $contrato->destino }}</td>
                        <td style="width: 20%;"><span class="label">PESO:</span><br>{{ $contrato->peso }} kg</td>
                        <td style="width: 30%;"><span class="label">FECHA:</span><br>{{ optional($generatedAt)->format('Y-m-d H:i:s') }}</td>
                    </tr>
                    <tr>
                        <td colspan="4">
                            <span class="label">OBSERVACION:</span><br>
                            {{ $contrato->observacion ?: 'SIN OBSERVACION' }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" style="height: 58px;">
                            <span class="label">RECIBIDO POR:</span>
                        </td>
                        <td colspan="2" style="height: 58px;">
                            <span class="label">FIRMA:</span>
                        </td>
                    </tr>
                </table>

                <div class="muted" style="margin-top: 6px;">
                    Copia {{ $i + 1 }} de 2
                </div>
            </div>
        @endfor
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Hoja de ruta CN {{ $hoja_ruta }}</title>
    <style>
        @page { margin: 24px 28px 34px; }
        body { color: #111; font-family: DejaVu Sans, monospace; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .header td { padding: 1px 3px; vertical-align: top; }
        .title { font-size: 15px; font-weight: bold; letter-spacing: 1px; text-align: center; }
        .meta { margin-top: 8px; }
        .meta td { padding: 2px 4px; }
        .label { color: #444; font-size: 8px; text-transform: uppercase; }
        .value { font-weight: bold; }
        .summary { margin: 10px 0; border: 1px solid #333; }
        .summary th, .summary td { border: 1px solid #555; padding: 4px; text-align: center; }
        .summary th { background: #eee; }
        .detail-title { border-bottom: 2px solid #222; font-size: 10px; font-weight: bold; padding: 3px 0; }
        .detail { margin-top: 4px; }
        .detail th, .detail td { border: 1px solid #555; padding: 4px 3px; }
        .detail th { background: #e7e7e7; font-size: 7px; text-align: center; }
        .detail td.number { text-align: right; }
        .detail td.center { text-align: center; }
        .totals td { border-top: 2px solid #222; font-weight: bold; }
        .notes { border: 1px solid #555; margin-top: 10px; padding: 6px; min-height: 24px; }
        .signatures { margin-top: 55px; }
        .signatures td { text-align: center; width: 50%; }
        .signature-line { border-top: 1px dashed #111; display: inline-block; padding-top: 5px; width: 210px; }
        .footer { bottom: 8px; color: #666; font-size: 7px; left: 28px; position: fixed; right: 28px; text-align: center; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="34%">
                <div class="label">Administracion expedidora</div>
                <div class="value">{{ strtoupper($administracion_expedidora) }}</div>
                <div class="label" style="margin-top: 5px">Oficina de cambio expedidora</div>
                <div class="value">{{ strtoupper($oficina_cambio) }}</div>
            </td>
            <td width="32%" class="title">HOJA DE RUTA<br><span style="font-size: 11px">{{ strtoupper($hoja_ruta) }}</span></td>
            <td width="34%">
                <table>
                    <tr><td class="label">Fecha</td><td class="value">{{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }}</td></tr>
                    <tr><td class="label">Despacho</td><td class="value">{{ strtoupper($despacho) }}</td></tr>
                    <tr><td class="label">Servicio</td><td class="value">{{ strtoupper($servicio) }}</td></tr>
                    <tr><td class="label">Transporte</td><td class="value">{{ strtoupper($transporte ?: '-') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td width="55%"><span class="label">Itinerario:</span> <span class="value">{{ strtoupper($itinerario ?: '-') }}</span></td>
            <td><span class="label">Peso bruto:</span> <span class="value">{{ number_format($totalPeso, 3, ',', '.') }} kg</span></td>
            <td><span class="label">Boletin:</span> <span class="value">{{ strtoupper($boletin ?: '-') }}</span></td>
        </tr>
    </table>

    <table class="summary">
        <thead><tr><th>Pais de destino</th><th>Oficina</th><th>Despacho</th><th>Devol.</th><th>Vacios</th><th>Estado</th></tr></thead>
        <tbody>
            @foreach ($destinations as $destination)
                <tr>
                    <td>{{ $destination['pais'] }} ({{ $destination['codigo'] }})</td>
                    <td>{{ $destination['oficina'] }}</td>
                    <td>{{ $destination['cantidad'] }} / {{ number_format($destination['peso'], 3, ',', '.') }} kg</td>
                    <td>0</td><td>0</td><td>LISTA</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="detail-title">INSCRIPCION DETALLADA</div>
    <table class="detail">
        <thead>
            <tr>
                <th width="3%">No.</th><th width="15%">ENVIO</th><th width="6%">ORIG.</th><th width="6%">DEST.</th>
                <th width="8%">PAIS</th><th width="8%">PESO KG.</th><th width="10%">VALOR DECLARADO</th>
                <th width="11%">C. PARTE ADEUDADA EXPED.</th><th width="11%">C. PARTE ADEUDADA DEST.</th><th>OBS.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td><td>{{ $row['envio'] }}</td><td class="center">{{ $row['origen'] }}</td>
                    <td class="center">{{ $row['destino'] }}</td><td class="center">{{ $row['pais_codigo'] }}</td>
                    <td class="number">{{ number_format($row['peso'], 3, ',', '.') }}</td>
                    <td class="number">{{ number_format($row['valor_declarado'], 2, ',', '.') }}</td>
                    <td class="number">{{ number_format($row['porte_expedidor'], 2, ',', '.') }}</td>
                    <td class="number">{{ number_format($row['porte_destinatario'], 2, ',', '.') }}</td><td>{{ $row['observacion'] ?: '-' }}</td>
                </tr>
            @endforeach
            <tr class="totals">
                <td colspan="5">TOTAL DE LISTA ({{ $rows->count() }} encomienda(s))</td>
                <td class="number">{{ number_format($totalPeso, 3, ',', '.') }}</td>
                <td class="number">{{ number_format($totalValor, 2, ',', '.') }}</td>
                <td class="number">{{ number_format($totalPorteExpedidor, 2, ',', '.') }}</td>
                <td class="number">{{ number_format($totalPorteDestinatario, 2, ',', '.') }}</td><td></td>
            </tr>
        </tbody>
    </table>

    <div class="notes"><span class="label">Observaciones:</span> {{ $observaciones_globales ?: 'Sin observaciones.' }}</div>
    <table class="signatures"><tr><td><span class="signature-line">OFICINA EXPEDIDORA</span></td><td><span class="signature-line">OFICINA RECEPTORA</span></td></tr></table>
    <div class="footer">Correos de Bolivia - Documento generado por TrackingBO</div>
</body>
</html>

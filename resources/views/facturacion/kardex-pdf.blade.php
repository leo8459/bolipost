<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 16px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #111827; }
        table { width: 100%; border-collapse: collapse; }
        .logos td { border: 0; height: 54px; text-align: center; vertical-align: middle; }
        .logos img { max-height: 48px; max-width: 190px; }
        .title-box { border: 1px solid #111; text-align:center; font-weight:700; line-height:1.45; padding:5px; }
        .right-box td, .meta td { border: 1px solid #111; padding: 4px; }
        .meta { margin-top: 6px; }
        .meta-label { font-weight:700; }
        .meta-value { text-align:center; font-weight:700; }
        .data { margin-top: 8px; }
        .data th { border:1px solid #111; background:#d9d9d9; text-align:center; font-weight:700; padding:5px 3px; }
        .data td { border:1px solid #111; padding:3px; vertical-align:middle; }
        .center { text-align:center; } .right { text-align:right; }
        .code { font-family: DejaVu Sans Mono, monospace; text-transform: uppercase; font-size: 7.5px; }
        .annulled-row td { background:#fff1f2; color:#7f1d1d; font-weight:700; }
        .annulled-invoice { background:#ffc7ce !important; color:#c00000 !important; font-weight:700; }
        .totals td { border:1px solid #111; padding:4px; font-weight:700; }
        .footer td { border:1px solid #111; height:70px; vertical-align:bottom; text-align:center; font-size:7px; }
        .footer .obs { text-align:left; vertical-align:top; }
    </style>
</head>
<body>
    <table class="logos">
        <tr>
            <td><img src="{{ public_path('images/LOGO 19-2-26.png') }}"></td>
            <td><img src="{{ public_path('images/LOGO-BOLIVIA.png') }}"></td>
            <td><img src="{{ public_path('images/ministerio-obras-publicas.png') }}"></td>
        </tr>
    </table>

    <table>
        <tr>
            <td style="width:16%"></td>
            <td style="width:34%" class="title-box">KARDEX DIARIO DE RENDICIÓN<br>AGENCIA BOLIVIANA DE CORREOS<br>EXPRESADO EN BS.</td>
            <td style="width:20%"></td>
            <td style="width:30%">
                <table class="right-box">
                    <tr><td>Dirección de Operaciones</td></tr>
                    <tr><td>Admision</td></tr>
                    <tr><td>Kardex 1</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="meta-label">Oficina Postal:</td><td class="meta-value">{{ $officePostal }}</td>
            <td class="meta-label">Nombre Responsable:</td><td class="meta-value">{{ $responsableName }}</td>
            <td class="meta-label">Fecha de recaudación:</td><td class="meta-value">{{ \Carbon\Carbon::parse($filters['to'] ?? now())->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Ventanilla:</td><td class="meta-value">{{ $ventanillaName }}</td>
            <td colspan="4"></td>
        </tr>
    </table>

    <table class="data">
        <thead><tr><th>N°</th><th>FECHA</th><th>CANTIDAD</th><th>REGIONAL</th><th>TIPO DE CORRESPONDENCIA</th><th>CODIGO DE ENVIO</th><th>PESO</th><th>PAIS/CIUDAD DE DESTINO</th><th>N° FACTURA</th><th>IMPORTE</th></tr></thead>
        <tbody>
            @forelse($visibleRows as $row)
                <tr class="{{ data_get($row, 'es_anulada') ? 'annulled-row' : '' }}">
                    <td class="center">{{ data_get($row, 'nro') }}</td>
                    <td class="center">{{ data_get($row, 'fecha') }}</td>
                    <td class="center">{{ data_get($row, 'cantidad') }}</td>
                    <td class="center">{{ data_get($row, 'origen') }}</td>
                    <td>{{ data_get($row, 'tipo_correspondencia') }}</td>
                    <td class="code">{{ data_get($row, 'guia_casilla') }}</td>
                    <td class="center">{{ data_get($row, 'peso') !== null && data_get($row, 'peso') !== '' ? number_format((float) data_get($row, 'peso'), 3, ',', '.') : '' }}</td>
                    <td class="center">{{ data_get($row, 'pais_ciudad') }}</td>
                    <td class="center {{ data_get($row, 'es_anulada') ? 'annulled-invoice' : '' }}">{{ data_get($row, 'numero_factura') }}</td>
                    <td class="right">Bs {{ number_format((float) data_get($row, 'importe', 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="center">Sin datos para los filtros aplicados.</td></tr>
            @endforelse
        </tbody>
        <tfoot class="totals">
            <tr><td colspan="9" class="right">TOTAL PARCIAL</td><td class="right">Bs {{ number_format((float) $totalImporte, 2, ',', '.') }}</td></tr>
            <tr><td colspan="9" class="right">TOTAL GENERAL</td><td class="right">Bs {{ number_format((float) $totalImporte, 2, ',', '.') }}</td></tr>
        </tfoot>
    </table>

    <table class="footer">
        <tr>
            <td class="obs" style="width:48%">Observaciones</td>
            <td style="width:17%">SELLO / FIRMA DE CONFORMIDAD<br>RECAUDADOR</td>
            <td style="width:17%">SELLO / FIRMA DE CONFORMIDAD<br>REVISOR</td>
            <td style="width:18%">SELLO RECEPCIÓN<br>TESORERÍA</td>
        </tr>
    </table>
</body>
</html>
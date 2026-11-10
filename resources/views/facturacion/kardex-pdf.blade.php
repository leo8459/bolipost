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
        .active-invoice { color:#198754; font-weight:700; }
        .casilla-detail { display:block; margin-top:2px; font-size:7.5px; font-weight:400; }
        .totals td { border:1px solid #111; padding:4px; font-weight:700; }
        .footer td { border:1px solid #111; height:70px; vertical-align:bottom; text-align:center; font-size:7px; }
        .footer .obs { text-align:left; vertical-align:top; }
        .cashier-section + .cashier-section { page-break-before: always; }
        .data thead { display: table-header-group; }
        .data tr { page-break-inside: avoid; }
        .operation-group td { background:#1d4f7a; color:#fff; font-weight:700; padding:5px; }
        .service-group td { background:#d9eaf7; font-weight:700; padding:4px; }
    </style>
</head>
<body>
    @php
        $regionalCashierSections = collect($cashierSections ?? []);
        $individualCashierForms = !empty($isRegionalKardex) && $regionalCashierSections->isNotEmpty();
        $pdfSections = $individualCashierForms
            ? $regionalCashierSections
            : collect([[
                'rows' => $visibleRows,
                'responsable_name' => $responsableName,
                'ventanilla_name' => $ventanillaName,
                'total_importe' => $totalImporte,
                'operation_groups' => collect($operationGroups ?? []),
            ]]);
    @endphp

    @foreach($pdfSections as $sectionIndex => $section)
    <div class="cashier-section">
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
            <td style="width:34%" class="title-box">{{ $individualCashierForms ? 'KARDEX DIARIO DE RENDICIÓN' : (!empty($isRegionalKardex) ? 'KARDEX POR SUCURSAL DIARIO' : 'KARDEX DIARIO DE RENDICIÓN') }}<br>AGENCIA BOLIVIANA DE CORREOS<br>EXPRESADO EN BS.</td>
            <td style="width:20%"></td>
            <td style="width:30%">
                <table class="right-box">
                    <tr><td>Dirección de Operaciones</td></tr>
                    <tr><td>ADMISIONES / ENTREGAS</td></tr>
                    <tr><td>{{ $individualCashierForms ? 'Kardex 1' : (!empty($isRegionalKardex) ? 'Kardex por Sucursal' : 'Kardex 1') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="meta">
        <tr>
            <td class="meta-label">Oficina Postal:</td><td class="meta-value">{{ $officePostal }}</td>
            <td class="meta-label">Nombre Responsable:</td><td class="meta-value">{{ data_get($section, 'responsable_name', $responsableName) }}</td>
            <td class="meta-label">Fecha de recaudación:</td><td class="meta-value">{{ \Carbon\Carbon::parse($filters['to'] ?? now())->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="meta-label">Ventanilla:</td><td class="meta-value">{{ data_get($section, 'ventanilla_name', $ventanillaName) }}</td>
            <td colspan="4"></td>
        </tr>
    </table>

    <table class="data">
        <thead><tr><th>N°</th><th>FECHA</th><th>CANTIDAD</th><th>REGIONAL</th><th>TIPO DE CORRESPONDENCIA</th><th>CÓDIGO DE ENVÍO</th><th>PESO</th><th>PAÍS/CIUDAD DE DESTINO / CIUDAD DE ORIGEN</th><th>OPERACIÓN</th><th>N° FACTURA</th><th>IMPORTE</th></tr></thead>
        <tbody>
            @php
                $sectionRows = collect(data_get($section, 'rows', $visibleRows));
                $sectionOperationGroups = collect(data_get($section, 'operation_groups', []));
            @endphp
            @if($sectionOperationGroups->isEmpty())
                <tr><td colspan="11" class="center">Sin datos para los filtros aplicados.</td></tr>
            @else
            @foreach($sectionOperationGroups as $operationGroup)
                @php($operationServiceGroups = collect(data_get($operationGroup, 'service_groups', [])))
                <tr class="operation-group"><td colspan="9">{{ data_get($operationGroup, 'label') }} · {{ number_format(collect(data_get($operationGroup, 'rows', []))->count()) }} registros</td><td colspan="2" class="right">Bs {{ number_format((float) data_get($operationGroup, 'total_importe', 0), 2, ',', '.') }}</td></tr>
                @foreach($operationServiceGroups as $serviceName => $serviceRows)
                    @php($serviceRows = collect($serviceRows))
                    @if(str_contains(mb_strtoupper((string) $serviceName), 'SERVICIO DE PAGO DE CONTRATOS') || str_contains(mb_strtoupper((string) $serviceName), 'SERVICIO ECA'))
                <tr class="service-group"><td colspan="9">{{ mb_strtoupper((string) $serviceName) }} · {{ number_format($serviceRows->count()) }} registros</td><td colspan="2" class="right">Bs {{ number_format((float) $serviceRows->sum(fn ($row) => (float) data_get($row, 'importe', 0)), 2, ',', '.') }}</td></tr>
                    @endif
                    @foreach($serviceRows as $row)
                <tr class="{{ data_get($row, 'es_anulada') ? 'annulled-row' : '' }}">
                    <td class="center">{{ data_get($row, 'nro') }}</td>
                    <td class="center">{{ data_get($row, 'fecha') }}</td>
                    <td class="center">{{ data_get($row, 'cantidad') }}</td>
                    <td class="center">{{ data_get($row, 'origen') }}</td>
                    @if(data_get($row, 'es_casilla'))
                        <td colspan="4" class="center">
                            <strong>{{ data_get($row, 'tipo_correspondencia') }}</strong>
                            @if(trim((string) data_get($row, 'detalle_casilla', '')) !== '')
                                <span class="casilla-detail">{{ data_get($row, 'detalle_casilla') }}</span>
                            @endif
                        </td>
                    @else
                        <td>{{ data_get($row, 'tipo_correspondencia') }}</td>
                        <td class="code">{{ data_get($row, 'guia_casilla') }}</td>
                        <td class="center">{{ data_get($row, 'peso') !== null && data_get($row, 'peso') !== '' ? number_format((float) data_get($row, 'peso'), 3, ',', '.') : '' }}</td>
                        <td class="center">{{ data_get($row, 'pais_ciudad') }}<br><small>{{ data_get($row, 'ubicacion_tipo') }}</small></td>
                    @endif
                    <td class="center">
                        {{ data_get($row, 'modalidad_entrega', '-') }}
                        @if(trim((string) data_get($row, 'operacion_detalle', '')) !== '')
                            <br><small>{{ data_get($row, 'operacion_detalle') }}</small>
                        @endif
                    </td>
                    <td class="center {{ data_get($row, 'es_anulada') ? 'annulled-invoice' : 'active-invoice' }}">{{ data_get($row, 'numero_factura') }}</td>
                    <td class="right">Bs {{ number_format((float) data_get($row, 'importe', 0), 2, ',', '.') }}</td>
                </tr>
                    @endforeach
                @endforeach
            @endforeach
            @endif
        </tbody>
        <tfoot class="totals">
            <tr><td colspan="10" class="right">TOTAL PARCIAL</td><td class="right">Bs {{ number_format((float) data_get($section, 'total_importe', $totalImporte), 2, ',', '.') }}</td></tr>
            <tr><td colspan="10" class="right">TOTAL GENERAL</td><td class="right">Bs {{ number_format((float) data_get($section, 'total_importe', $totalImporte), 2, ',', '.') }}</td></tr>
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
    </div>
    @endforeach
</body>
</html>

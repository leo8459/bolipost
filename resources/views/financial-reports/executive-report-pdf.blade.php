<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte ejecutivo de ventas por servicio</title>
    <style>
        @page { margin: 20mm 14mm 18mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 9px; line-height: 1.4; color: #172033; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .header td { vertical-align: middle; }
        .logo { width: 145px; max-height: 52px; }
        .institution { text-align: right; color: #31547d; font-size: 9px; text-transform: uppercase; letter-spacing: .4px; }
        .institution strong { display: block; margin-bottom: 2px; color: #153f72; font-size: 11px; }
        .title-block { padding: 14px 16px; border-left: 5px solid #f5b800; background: #123f73; color: #fff; }
        .title { margin: 0 0 3px; font-size: 19px; text-transform: uppercase; letter-spacing: .3px; }
        .subtitle { font-size: 10px; color: #dce9f6; }
        .meta { width: 100%; margin: 10px 0 13px; border-collapse: collapse; }
        .meta td { width: 33.33%; padding: 6px 8px; border: 1px solid #d7e0e9; background: #f7f9fc; }
        .label { display: block; margin-bottom: 2px; color: #64748b; font-size: 7.5px; font-weight: bold; text-transform: uppercase; }
        .metrics { width: 100%; margin-bottom: 13px; border-collapse: separate; border-spacing: 5px 0; }
        .metrics td { width: 25%; padding: 10px 8px; border: 1px solid #d7e0e9; border-top: 3px solid #f5b800; background: #fff; }
        .metric-value { display: block; margin-top: 3px; color: #123f73; font-size: 15px; font-weight: bold; }
        .section-title { margin: 14px 0 7px; padding-bottom: 4px; border-bottom: 2px solid #f5b800; color: #123f73; font-size: 12px; text-transform: uppercase; }
        .executive-box { padding: 11px 13px; border: 1px solid #cbd8e6; background: #f4f8fc; }
        .executive-box p { margin: 0 0 6px; }
        .executive-box p:last-child { margin-bottom: 0; }
        .highlight { color: #123f73; font-weight: bold; }
        table.sheet { width: 100%; margin-bottom: 12px; border-collapse: collapse; }
        .sheet thead { display: table-header-group; }
        .sheet th { padding: 6px 5px; border: 1px solid #b9c8d8; background: #123f73; color: #fff; font-size: 7.5px; text-transform: uppercase; }
        .sheet td { padding: 5px; border: 1px solid #cbd5df; vertical-align: top; }
        .sheet tbody tr:nth-child(even) td { background: #f7f9fc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .strong { font-weight: bold; }
        .money { white-space: nowrap; color: #173f6d; font-weight: bold; }
        .muted { color: #64748b; }
        .group-block { margin-top: 11px; page-break-inside: avoid; }
        .group-heading { padding: 7px 9px; background: #e8f0f8; border-left: 4px solid #123f73; color: #123f73; font-size: 10px; font-weight: bold; }
        .group-heading span { float: right; }
        .detail-table { margin-top: 0 !important; }
        .detail-table th { background: #3e6288; }
        .note { margin-top: 12px; padding: 8px 10px; border-left: 3px solid #f5b800; background: #fff9e6; color: #5f5230; font-size: 8px; }
        .signatures { width: 100%; margin: 18px 0 10px; border-collapse: separate; border-spacing: 24px 0; page-break-inside: avoid; }
        .signatures td { width: 50%; padding-top: 7px; border-top: 1px solid #64748b; text-align: center; color: #475569; }
        .footer { position: fixed; right: 0; bottom: -12mm; left: 0; padding-top: 4px; border-top: 1px solid #d7e0e9; color: #718096; font-size: 7.5px; }
        .footer .page { float: right; }
        .footer .page:after { content: counter(page); }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/AGBClogo2.png');
        $logoData = is_file($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
        $totalAmount = (float) ($summary['totalMonto'] ?? 0);
    @endphp

    <div class="footer">
        Documento de uso interno - Direccion Financiera
        <span class="page">Pagina </span>
    </div>

    <table class="header">
        <tr>
            <td>
                @if($logoData)
                    <img class="logo" src="data:image/png;base64,{{ $logoData }}" alt="Correos de Bolivia">
                @endif
            </td>
            <td class="institution">
                <strong>Correos de Bolivia</strong>
                Direccion Financiera<br>Unidad de analisis y seguimiento
            </td>
        </tr>
    </table>

    <div class="title-block">
        <h1 class="title">Reporte ejecutivo de ventas por servicio</h1>
        <div class="subtitle">Resumen gerencial y detalle consolidado para la toma de decisiones financieras</div>
    </div>

    <table class="meta">
        <tr>
            <td><span class="label">Periodo analizado</span>{{ $periodLabel }}</td>
            <td><span class="label">Fecha de emision</span>{{ $generatedAt->format('d/m/Y H:i') }}</td>
            <td><span class="label">Cobertura</span>{{ count($selectedServices) }} servicios seleccionados</td>
        </tr>
    </table>

    <table class="metrics">
        <tr>
            <td><span class="label">Monto total</span><span class="metric-value">Bs {{ number_format($totalAmount, 2) }}</span></td>
            <td><span class="label">Ventas registradas</span><span class="metric-value">{{ number_format((int) ($summary['cantidadVentas'] ?? 0)) }}</span></td>
            <td><span class="label">Cantidad total</span><span class="metric-value">{{ number_format((float) ($summary['totalCantidad'] ?? 0), 2) }}</span></td>
            <td><span class="label">Ticket promedio</span><span class="metric-value">Bs {{ number_format($averageTicket, 2) }}</span></td>
        </tr>
    </table>

    <h2 class="section-title">Resumen ejecutivo</h2>
    <div class="executive-box">
        @if($topGroup)
            <p>Durante <span class="highlight">{{ $periodLabel }}</span> se registraron <span class="highlight">{{ number_format((int) ($summary['cantidadVentas'] ?? 0)) }} ventas</span>, por un monto consolidado de <span class="highlight">Bs {{ number_format($totalAmount, 2) }}</span>.</p>
            <p>El grupo con mayor aporte fue <span class="highlight">{{ $topGroup['servicio'] }}</span>, con <span class="highlight">Bs {{ number_format((float) $topGroup['totalMonto'], 2) }}</span>, equivalente al <span class="highlight">{{ number_format($topGroupShare, 1) }}%</span> del monto total analizado.</p>
            <p>La operacion comprende <span class="highlight">{{ number_format($serviceGroups->count()) }} grupos</span> y <span class="highlight">{{ number_format($services->count()) }} subservicios</span>. El ingreso promedio por venta fue de <span class="highlight">Bs {{ number_format($averageTicket, 2) }}</span>.</p>
        @else
            <p>No se encontraron operaciones para los criterios seleccionados. Se recomienda verificar el periodo y los servicios incluidos en el filtro.</p>
        @endif
    </div>

    <h2 class="section-title">Ranking consolidado por grupo</h2>
    <table class="sheet">
        <thead>
            <tr>
                <th style="width: 5%">Pos.</th>
                <th style="width: 31%">Grupo de servicio</th>
                <th style="width: 11%" class="right">Ventas</th>
                <th style="width: 13%" class="right">Detalles</th>
                <th style="width: 13%" class="right">Cantidad</th>
                <th style="width: 17%" class="right">Monto</th>
                <th style="width: 10%" class="right">Part.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($serviceGroups as $group)
                @php($share = $totalAmount > 0 ? ((float) $group['totalMonto'] / $totalAmount) * 100 : 0)
                <tr>
                    <td class="center strong">{{ $loop->iteration }}</td>
                    <td class="strong">{{ $group['servicio'] }}</td>
                    <td class="right">{{ number_format((int) $group['cantidadVentas']) }}</td>
                    <td class="right">{{ number_format((int) $group['cantidadDetalles']) }}</td>
                    <td class="right">{{ number_format((float) $group['totalCantidad'], 2) }}</td>
                    <td class="right money">Bs {{ number_format((float) $group['totalMonto'], 2) }}</td>
                    <td class="right">{{ number_format($share, 1) }}%</td>
                </tr>
            @empty
                <tr><td colspan="7" class="center muted">Sin datos para el periodo seleccionado.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($serviceGroups->isNotEmpty())
        <div class="page-break"></div>
        <h2 class="section-title">Detalle por grupo y subservicio</h2>

        @foreach($serviceGroups as $group)
            <div class="group-block">
                <div class="group-heading">
                    {{ $group['servicio'] }}
                    <span>Subtotal: Bs {{ number_format((float) $group['totalMonto'], 2) }}</span>
                </div>
                <table class="sheet detail-table">
                    <thead>
                        <tr>
                            <th style="width: 36%">Subservicio</th>
                            <th style="width: 12%" class="right">Ventas</th>
                            <th style="width: 13%" class="right">Detalles</th>
                            <th style="width: 13%" class="right">Cantidad</th>
                            <th style="width: 16%" class="right">Monto</th>
                            <th style="width: 10%" class="right">Part.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group['_children'] ?? [] as $child)
                            @php($childShare = $totalAmount > 0 ? ((float) $child['totalMonto'] / $totalAmount) * 100 : 0)
                            <tr>
                                <td>{{ $child['servicio'] }}</td>
                                <td class="right">{{ number_format((int) $child['cantidadVentas']) }}</td>
                                <td class="right">{{ number_format((int) $child['cantidadDetalles']) }}</td>
                                <td class="right">{{ number_format((float) $child['totalCantidad'], 2) }}</td>
                                <td class="right money">Bs {{ number_format((float) $child['totalMonto'], 2) }}</td>
                                <td class="right">{{ number_format($childShare, 1) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

    <table class="signatures">
        <tr>
            <td>Elaborado por<br><strong>Unidad responsable</strong></td>
            <td>Revisado por<br><strong>Director Financiero</strong></td>
        </tr>
    </table>

    <div class="note">
        <strong>Nota metodologica:</strong> los importes y cantidades corresponden a la consolidacion de los servicios y meses seleccionados. La participacion se calcula sobre el monto total del reporte. Este documento debe contrastarse con los respaldos transaccionales antes del cierre contable definitivo.
    </div>
</body>
</html>

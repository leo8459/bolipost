<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte ejecutivo de flujo de cajero</title>
    <style>
        @page { margin: 17mm 13mm 17mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8.5px; line-height: 1.35; color: #172033; }
        .header, .meta, .metrics, .sheet, .signatures { width: 100%; border-collapse: collapse; }
        .header { margin-bottom: 12px; }
        .header td { vertical-align: middle; }
        .logo { width: 142px; max-height: 50px; }
        .institution { text-align: right; color: #31547d; font-size: 8px; text-transform: uppercase; letter-spacing: .35px; }
        .institution strong { display: block; color: #153f72; font-size: 10.5px; }
        .title-block { padding: 12px 15px; border-left: 5px solid #f5b800; background: #123f73; color: #fff; }
        .title { margin: 0 0 3px; font-size: 18px; text-transform: uppercase; }
        .subtitle { color: #dce9f6; font-size: 9.5px; }
        .meta { margin: 9px 0; }
        .meta td { width: 33.33%; padding: 6px 7px; border: 1px solid #d7e0e9; background: #f7f9fc; }
        .label { display: block; margin-bottom: 2px; color: #64748b; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .scope-note { margin: 0 0 10px; padding: 8px 10px; border: 1px solid #efd47b; border-left: 4px solid #f5b800; background: #fff9e6; color: #5f5230; }
        .metrics { margin-bottom: 10px; border-collapse: separate; border-spacing: 4px 0; }
        .metrics td { width: 33.33%; padding: 8px 7px; border: 1px solid #d7e0e9; border-top: 3px solid #f5b800; }
        .metric-value { display: block; margin-top: 2px; color: #123f73; font-size: 13px; font-weight: bold; }
        .section-title { margin: 12px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #f5b800; color: #123f73; font-size: 11.5px; text-transform: uppercase; }
        .executive-box { padding: 9px 11px; border: 1px solid #cbd8e6; background: #f4f8fc; }
        .executive-box p { margin: 0 0 5px; }
        .executive-box p:last-child { margin-bottom: 0; }
        .highlight { color: #123f73; font-weight: bold; }
        .sheet { margin-bottom: 10px; }
        .sheet thead { display: table-header-group; }
        .sheet th { padding: 5px 4px; border: 1px solid #b9c8d8; background: #123f73; color: #fff; font-size: 7px; text-transform: uppercase; }
        .sheet td { padding: 4px; border: 1px solid #cbd5df; vertical-align: top; }
        .sheet tbody tr:nth-child(even) td { background: #f7f9fc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .strong { font-weight: bold; }
        .money { white-space: nowrap; color: #173f6d; font-weight: bold; }
        .muted { color: #64748b; }
        .nowrap { white-space: nowrap; }
        .page-break { page-break-before: always; }
        .signatures { margin: 20px 0 8px; border-collapse: separate; border-spacing: 25px 0; page-break-inside: avoid; }
        .signatures td { width: 50%; padding-top: 7px; border-top: 1px solid #64748b; text-align: center; color: #475569; }
        .method-note { margin-top: 10px; padding: 7px 9px; border-left: 3px solid #94a3b8; background: #f8fafc; color: #526174; font-size: 7.5px; }
        .footer { position: fixed; right: 0; bottom: -11mm; left: 0; padding-top: 4px; border-top: 1px solid #d7e0e9; color: #718096; font-size: 7px; }
        .footer .page { float: right; }
        .footer .page:after { content: counter(page); }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/AGBClogo2.png');
        $logoData = is_file($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
        $totalAmount = (float) ($summary['totalMonto'] ?? 0);
        $totalSales = (float) ($summary['cantidadVentas'] ?? 0);
    @endphp

    <div class="footer">
        Documento de uso interno - Dirección Financiera
        <span class="page">Página </span>
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
                Dirección Financiera<br>Informe para toma de decisiones
            </td>
        </tr>
    </table>

    <div class="title-block">
        <h1 class="title">Reporte ejecutivo de flujo de cajero</h1>
        <div class="subtitle">Ingresos de ventanilla consolidados por cajero y servicio</div>
    </div>

    <table class="meta">
        <tr>
            <td><span class="label">Periodo analizado</span>{{ $periodLabel }}</td>
            <td><span class="label">Fecha de emisión</span>{{ $generatedAt->format('d/m/Y H:i') }}</td>
            <td><span class="label">Departamento</span>{{ $selectedDepartment !== '' ? $selectedDepartment : 'Todos los departamentos' }}</td>
        </tr>
    </table>

    <div class="scope-note">
        <strong>Alcance:</strong> este informe excluye completamente el servicio de contratos, tanto de los cuadros como de los totales, porque corresponde a cuentas por cobrar y no a ingresos de ventanilla del periodo.
    </div>

    <table class="metrics">
        <tr>
            <td><span class="label">Ingresos de ventanilla nacional</span><span class="metric-value">Bs {{ \App\Support\BolivianNumber::format($totalAmount, 2) }}</span></td>
            <td><span class="label">Ventas realizadas</span><span class="metric-value">{{ \App\Support\BolivianNumber::format($totalSales) }}</span></td>
            <td><span class="label">Cajeros</span><span class="metric-value">{{ \App\Support\BolivianNumber::format($cashierRows->count()) }}</span></td>
        </tr>
    </table>

    <h2 class="section-title">Resumen ejecutivo</h2>
    <div class="executive-box">
        @if($totalSales > 0)
            <p>En <span class="highlight">{{ $periodLabel }}</span> se registraron <span class="highlight">{{ \App\Support\BolivianNumber::format($totalSales) }} ventas realizadas</span> por <span class="highlight">Bs {{ \App\Support\BolivianNumber::format($totalAmount, 2) }}</span>, con un ingreso promedio de <span class="highlight">Bs {{ \App\Support\BolivianNumber::format($averageTicket, 2) }}</span> por venta.</p>
            @if($topCashier)
                <p>El cajero con mayor ingreso registrado fue <span class="highlight">{{ $topCashier['usuarioNombre'] }}</span>, con <span class="highlight">Bs {{ \App\Support\BolivianNumber::format((float) $topCashier['totalMonto'], 2) }}</span>.</p>
            @endif
            @if($topService)
                <p>El grupo de servicio de mayor aporte fue <span class="highlight">{{ $topService['servicio'] }}</span>, con <span class="highlight">Bs {{ \App\Support\BolivianNumber::format((float) $topService['totalMonto'], 2) }}</span>.</p>
            @endif
        @else
            <p>No se encontraron ingresos de ventanilla para los criterios seleccionados, una vez excluidos los contratos.</p>
        @endif
    </div>

    <h2 class="section-title">Ingresos por cajero</h2>
    <table class="sheet">
        <thead>
            <tr>
                <th style="width: 5%">Pos.</th>
                <th style="width: 23%">Cajero</th>
                <th style="width: 14%">Regional / departamento</th>
                <th style="width: 9%" class="right">Ventas realizadas</th>
                <th style="width: 10%" class="right">Cantidad de paquetes</th>
                <th style="width: 9%" class="right">Días trabajados</th>
                <th style="width: 14%" class="right">Promedio por día</th>
                <th style="width: 16%" class="right">Ingresos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($cashierRows as $cashier)
                <tr>
                    <td class="center strong">{{ $loop->iteration }}</td>
                    <td><span class="strong">{{ $cashier['usuarioNombre'] }}</span>@if($cashier['usuarioCarnet'] !== '')<br><span class="muted">CI: {{ $cashier['usuarioCarnet'] }}</span>@endif</td>
                    <td>{{ $cashier['departamento'] }}</td>
                    <td class="right">{{ \App\Support\BolivianNumber::format((float) $cashier['cantidadVentas']) }}</td>
                    <td class="right">{{ \App\Support\BolivianNumber::format((float) $cashier['totalCantidad'], 2) }}</td>
                    <td class="right">{{ \App\Support\BolivianNumber::format((float) ($cashier['diasTrabajados'] ?? 0)) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($cashier['promedioDiario'] ?? 0), 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) $cashier['totalMonto'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="center muted">Sin información por cajero.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2 class="section-title">Ingresos por grupo de servicio</h2>
    <table class="sheet">
        <thead>
            <tr>
                <th style="width: 6%">Pos.</th>
                <th style="width: 39%">Grupo de servicio</th>
                <th style="width: 15%" class="right">Ventas realizadas</th>
                <th style="width: 18%" class="right">Cantidad de paquetes</th>
                <th style="width: 22%" class="right">Ingresos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($serviceGroups as $group)
                <tr>
                    <td class="center strong">{{ $loop->iteration }}</td>
                    <td class="strong">{{ $group['servicio'] }}</td>
                    <td class="right">{{ \App\Support\BolivianNumber::format((float) $group['cantidadVentas']) }}</td>
                    <td class="right">{{ \App\Support\BolivianNumber::format((float) $group['totalCantidad'], 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) $group['totalMonto'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="center muted">Sin información por servicio.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>Elaborado por<br><strong>Unidad responsable</strong></td>
            <td>Revisado por<br><strong>Director Financiero</strong></td>
        </tr>
    </table>

    <div class="method-note">
        <strong>Nota metodológica:</strong> los días trabajados corresponden a fechas distintas en las que cada cajero registró ventas; el promedio por día divide sus ingresos entre esos días. Los importes corresponden a la consolidación de los meses y servicios seleccionados. Los contratos se excluyen por su tratamiento como cuentas por cobrar. El reporte debe contrastarse con los respaldos transaccionales antes del cierre contable definitivo.
    </div>
</body>
</html>

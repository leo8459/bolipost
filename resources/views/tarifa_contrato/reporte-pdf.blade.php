<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Tarifas de Contrato</title>
    <style>
        @page { margin: 24px 28px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #172033;
            font-size: 11px;
            line-height: 1.35;
        }
        .header {
            border-bottom: 3px solid #20539A;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .title {
            color: #20539A;
            font-size: 22px;
            font-weight: 800;
            margin: 0;
        }
        .subtitle {
            color: #64748b;
            margin-top: 4px;
        }
        .meta {
            text-align: right;
            color: #64748b;
            font-size: 10px;
        }
        .metrics {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 12px -8px 18px;
        }
        .metric {
            border: 1px solid #dbe3f0;
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
        }
        .metric-label {
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
        }
        .metric-value {
            color: #20539A;
            font-size: 26px;
            font-weight: 800;
            margin-top: 4px;
        }
        .section-title {
            color: #20539A;
            font-size: 13px;
            font-weight: 800;
            margin: 16px 0 8px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.data th {
            background: #20539A;
            color: #fff;
            text-align: left;
            font-size: 10px;
            padding: 7px;
            text-transform: uppercase;
        }
        table.data td {
            border-bottom: 1px solid #e5e7eb;
            padding: 7px;
            vertical-align: top;
        }
        table.data tr:nth-child(even) td {
            background: #f8fafc;
        }
        .num {
            text-align: right;
            font-weight: 800;
        }
        .empty {
            border: 1px solid #e5e7eb;
            color: #64748b;
            padding: 12px;
            text-align: center;
        }
        .footer {
            position: fixed;
            bottom: -8px;
            left: 0;
            right: 0;
            color: #94a3b8;
            font-size: 9px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="footer">Reporte generado por BOLIPOST</div>

    <table style="width: 100%;" class="header">
        <tr>
            <td>
                <h1 class="title">Reporte de Tarifas de Contrato</h1>
                <div class="subtitle">Resumen general sin filtro de fecha</div>
            </td>
            <td class="meta">
                Generado: {{ $generatedAt->format('d/m/Y H:i') }}<br>
                Modulo: Tarifa Contrato
            </td>
        </tr>
    </table>

    <table class="metrics">
        <tr>
            <td class="metric">
                <div class="metric-label">Total de tarifas</div>
                <div class="metric-value">{{ number_format((int) $totalTarifas) }}</div>
            </td>
            <td class="metric">
                <div class="metric-label">Empresas con tarifa</div>
                <div class="metric-value">{{ number_format((int) $totalEmpresasConTarifa) }}</div>
            </td>
            <td class="metric">
                <div class="metric-label">Servicios tarifados</div>
                <div class="metric-value">{{ number_format((int) $totalServicios) }}</div>
            </td>
            <td class="metric">
                <div class="metric-label">Rutas origen-destino</div>
                <div class="metric-value">{{ number_format((int) $totalRutas) }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Tarifas por empresa</div>
    @if (($tarifasPorEmpresa ?? collect())->count() > 0)
        <table class="data">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th style="width: 90px;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tarifasPorEmpresa as $row)
                    <tr>
                        <td>{{ $row->empresa_nombre }}</td>
                        <td class="num">{{ number_format((int) $row->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No hay tarifas registradas por empresa.</div>
    @endif

    <div class="section-title">Tarifas por servicio</div>
    @if (($tarifasPorServicio ?? collect())->count() > 0)
        <table class="data">
            <thead>
                <tr>
                    <th>Servicio</th>
                    <th style="width: 90px;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tarifasPorServicio as $row)
                    <tr>
                        <td>{{ $row->servicio_nombre }}</td>
                        <td class="num">{{ number_format((int) $row->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No hay tarifas registradas por servicio.</div>
    @endif

    <div class="section-title">Principales rutas por cantidad de tarifas</div>
    @if (($tarifasPorRuta ?? collect())->count() > 0)
        <table class="data">
            <thead>
                <tr>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th style="width: 90px;">Cantidad</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tarifasPorRuta as $row)
                    <tr>
                        <td>{{ $row->origen_nombre }}</td>
                        <td>{{ $row->destino_nombre }}</td>
                        <td class="num">{{ number_format((int) $row->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No hay rutas registradas.</div>
    @endif
</body>
</html>

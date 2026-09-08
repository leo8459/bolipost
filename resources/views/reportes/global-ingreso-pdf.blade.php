<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $scopeLabel }}</title>
    <style>
        @page { margin: 10px; }
        body {
            font-family: Verdana, DejaVu Sans, sans-serif;
            font-size: 7px;
            color: #222222;
        }
        .head, .kpi, .table, .columns {
            width: 100%;
            border-collapse: collapse;
        }
        .head {
            border: 1px solid #9ca3af;
            margin-bottom: 8px;
        }
        .head td {
            padding: 5px 7px;
            vertical-align: middle;
        }
        .title {
            font-size: 15px;
            font-weight: 700;
            margin: 0;
        }
        .meta {
            color: #444444;
            font-size: 7px;
            margin-top: 2px;
        }
        .logo {
            width: 54px;
            height: auto;
        }
        .note {
            border: 1px solid #c4c4c4;
            background: #f8fafc;
            padding: 6px 8px;
            margin-bottom: 8px;
            font-size: 7px;
            color: #333333;
        }
        .kpi { margin-bottom: 8px; }
        .kpi td, .table th, .table td {
            border: 1px solid #b5b5b5;
            padding: 2px 3px;
        }
        .kpi td {
            text-align: center;
            background: #ffffff;
        }
        .kpi .k {
            color: #666666;
            font-size: 5.7px;
            text-transform: uppercase;
        }
        .kpi .v {
            color: #111111;
            font-size: 8.5px;
            font-weight: 700;
            margin-top: 1px;
        }
        .section-title {
            margin: 8px 0 4px;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            border-left: 4px solid #9ca3af;
            padding-left: 6px;
        }
        .table th {
            background: #f3f4f6;
            color: #111111;
            font-size: 6.2px;
            text-transform: uppercase;
        }
        .table td { font-size: 6.3px; }
        .num { text-align: right; }
        .columns > tbody > tr > td {
            width: 50%;
            vertical-align: top;
        }
        .column-left { padding-right: 4px; }
        .column-right { padding-left: 4px; }
        .chart-columns {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        .chart-columns > tbody > tr > td {
            width: 33.33%;
            vertical-align: top;
            padding: 0 3px;
        }
        .chart-columns > tbody > tr > td:first-child { padding-left: 0; }
        .chart-columns > tbody > tr > td:last-child { padding-right: 0; }
        .chart-card {
            border: 1px solid #b5b5b5;
            padding: 5px;
            page-break-inside: avoid;
        }
        .chart-title {
            font-size: 7px;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .bar-chart {
            width: 100%;
            border-collapse: collapse;
        }
        .bar-chart td {
            border: 0;
            padding: 1.5px 2px;
            font-size: 5.8px;
            vertical-align: middle;
        }
        .bar-label {
            width: 31%;
            white-space: nowrap;
            overflow: hidden;
        }
        .bar-value {
            width: 17%;
            text-align: right;
            white-space: nowrap;
            font-weight: 700;
        }
        .bar-track {
            width: 100%;
            height: 7px;
            background: #e5e7eb;
        }
        .bar-fill {
            height: 7px;
            min-width: 1px;
        }
        .avoid-break { page-break-inside: avoid; }
        .small-note {
            color: #666666;
            font-size: 6.2px;
            margin: 2px 0 4px;
        }
        .footer {
            margin-top: 8px;
            text-align: right;
            font-size: 6.5px;
            color: #555555;
        }
    </style>
</head>
<body>
@php
    $logoPath = public_path('images/AGBClogo1.png');
    $logoData = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
    $stats = $pdfStatistics ?? [];
    $chartMonths = collect($stats['meses'] ?? [])->take(-12)->values();
    $maxMonthQuantity = max(1, (int) $chartMonths->max('cantidad'));
    $chartDestinations = collect($stats['destinos'] ?? [])->take(10)->values();
    $maxDestinationQuantity = max(1, (int) $chartDestinations->max('cantidad'));
    $statusColors = ['#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#6b7280'];
@endphp

<table class="head">
    <tr>
        <td width="85">
            @if($logoData)
                <img src="{{ $logoData }}" class="logo" alt="Logo sistema">
            @endif
        </td>
        <td>
            <div class="title">{{ $scopeLabel }}</div>
            <div class="meta"><strong>Reporte estadístico de registros creados, excepto cancelados</strong></div>
            <div class="meta">Módulos: {{ implode(', ', $moduleLabels) }}</div>
            @if(!empty($selectedMonthLabels))
                <div class="meta">Meses: {{ implode(', ', $selectedMonthLabels) }}</div>
            @elseif(!empty($from) || !empty($to))
                <div class="meta">Rango: {{ $from ?: 'inicio' }} - {{ $to ?: 'fin' }}</div>
            @else
                <div class="meta">Rango: todos</div>
            @endif
            <div class="meta">Fecha de emisión: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<div class="note">
    Estadísticas calculadas sobre los {{ \App\Support\BolivianNumber::format($pdfTotalRows ?? ($summary['total_filtrado'] ?? 0)) }}
    registros filtrados. El detalle individual de los envíos está disponible en el Excel.
</div>

<table class="kpi">
    <tr>
        <td><div class="k">Registrados</div><div class="v">{{ \App\Support\BolivianNumber::format($summary['registrados'] ?? ($summary['total'] ?? 0)) }}</div></td>
        <td><div class="k">Filtrados</div><div class="v">{{ \App\Support\BolivianNumber::format($summary['total_filtrado'] ?? ($summary['total'] ?? 0)) }}</div></td>
        <td><div class="k">Entregados</div><div class="v">{{ \App\Support\BolivianNumber::format($summary['entregados'] ?? 0) }}</div></td>
        <td><div class="k">No entregados</div><div class="v">{{ \App\Support\BolivianNumber::format($summary['no_entregados'] ?? 0) }}</div></td>
        <td><div class="k">Tasa de entrega</div><div class="v">{{ \App\Support\BolivianNumber::format((float) ($stats['tasa_entrega'] ?? 0), 1) }}%</div></td>
        <td><div class="k">Peso total</div><div class="v">{{ \App\Support\BolivianNumber::format((float) ($totals['peso_total'] ?? 0), 3) }}</div></td>
        <td><div class="k">Peso promedio</div><div class="v">{{ \App\Support\BolivianNumber::format((float) ($stats['peso_promedio'] ?? 0), 3) }}</div></td>
        <td><div class="k">Peso mediano</div><div class="v">{{ \App\Support\BolivianNumber::format((float) ($stats['peso_mediano'] ?? 0), 3) }}</div></td>
        <td><div class="k">Peso máximo</div><div class="v">{{ \App\Support\BolivianNumber::format((float) ($stats['peso_maximo'] ?? 0), 3) }}</div></td>
    </tr>
</table>

<div class="section-title">Gráficos estadísticos</div>
<table class="chart-columns">
    <tr>
        <td>
            <div class="chart-card">
                <div class="chart-title">Distribución por situación</div>
                <table class="bar-chart">
                    @forelse(($stats['situaciones'] ?? []) as $index => $statusRow)
                        @php $statusWidth = max(0, min(100, (float) $statusRow['porcentaje'])); @endphp
                        <tr>
                            <td class="bar-label">{{ $statusRow['label'] }}</td>
                            <td>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: {{ $statusWidth }}%; background: {{ $statusColors[$index] ?? '#6b7280' }};"></div>
                                </div>
                            </td>
                            <td class="bar-value">{{ \App\Support\BolivianNumber::format((float) $statusRow['porcentaje'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td>Sin datos.</td></tr>
                    @endforelse
                </table>
            </div>
        </td>
        <td>
            <div class="chart-card">
                <div class="chart-title">Volumen mensual</div>
                <table class="bar-chart">
                    @forelse($chartMonths as $monthRow)
                        @php $monthWidth = ((int) $monthRow['cantidad'] / $maxMonthQuantity) * 100; @endphp
                        <tr>
                            <td class="bar-label">{{ $monthRow['periodo'] }}</td>
                            <td>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: {{ $monthWidth }}%; background: #2563eb;"></div>
                                </div>
                            </td>
                            <td class="bar-value">{{ \App\Support\BolivianNumber::format((int) $monthRow['cantidad']) }}</td>
                        </tr>
                    @empty
                        <tr><td>Sin datos.</td></tr>
                    @endforelse
                </table>
            </div>
        </td>
        <td>
            <div class="chart-card">
                <div class="chart-title">Destinos principales</div>
                <table class="bar-chart">
                    @forelse($chartDestinations as $destinationRow)
                        @php $destinationWidth = ((int) $destinationRow['cantidad'] / $maxDestinationQuantity) * 100; @endphp
                        <tr>
                            <td class="bar-label">{{ $destinationRow['label'] }}</td>
                            <td>
                                <div class="bar-track">
                                    <div class="bar-fill" style="width: {{ $destinationWidth }}%; background: #fbbf24;"></div>
                                </div>
                            </td>
                            <td class="bar-value">{{ \App\Support\BolivianNumber::format((int) $destinationRow['cantidad']) }}</td>
                        </tr>
                    @empty
                        <tr><td>Sin datos.</td></tr>
                    @endforelse
                </table>
            </div>
        </td>
    </tr>
</table>

<table class="columns">
    <tr>
        <td class="column-left">
            <div class="avoid-break">
                <div class="section-title">Distribución por situación</div>
                <table class="table">
                    <thead>
                        <tr><th>Situación</th><th class="num">Cantidad</th><th class="num">Porcentaje</th></tr>
                    </thead>
                    <tbody>
                        @forelse(($stats['situaciones'] ?? []) as $statusRow)
                            <tr>
                                <td>{{ $statusRow['label'] }}</td>
                                <td class="num">{{ \App\Support\BolivianNumber::format((int) $statusRow['cantidad']) }}</td>
                                <td class="num">{{ \App\Support\BolivianNumber::format((float) $statusRow['porcentaje'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Sin datos de situación.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </td>
        <td class="column-right">
            <div class="avoid-break">
                <div class="section-title">Resumen por módulo</div>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Módulo</th><th class="num">Cantidad</th><th class="num">Part.</th>
                            <th class="num">Entregados</th><th class="num">Pendientes</th><th class="num">Peso</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($stats['modulos'] ?? []) as $moduleRow)
                            <tr>
                                <td>{{ $moduleRow['label'] }}</td>
                                <td class="num">{{ \App\Support\BolivianNumber::format((int) $moduleRow['cantidad']) }}</td>
                                <td class="num">{{ \App\Support\BolivianNumber::format((float) $moduleRow['participacion'], 1) }}%</td>
                                <td class="num">{{ \App\Support\BolivianNumber::format((int) $moduleRow['entregados']) }}</td>
                                <td class="num">{{ \App\Support\BolivianNumber::format((int) $moduleRow['pendientes']) }}</td>
                                <td class="num">{{ \App\Support\BolivianNumber::format((float) $moduleRow['peso'], 3) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6">Sin datos por módulo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </td>
    </tr>
</table>

<div class="avoid-break">
    <div class="section-title">Evolución mensual</div>
    @if(($stats['meses_total'] ?? 0) > count($stats['meses'] ?? []))
        <div class="small-note">Se muestran los últimos {{ count($stats['meses'] ?? []) }} meses de {{ \App\Support\BolivianNumber::format((int) $stats['meses_total']) }} meses con actividad.</div>
    @endif
    <table class="table">
        <thead>
            <tr>
                <th>Mes</th><th class="num">Cantidad</th><th class="num">Entregados</th>
                <th class="num">Pendientes</th><th class="num">Peso</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($stats['meses'] ?? []) as $monthRow)
                <tr>
                    <td>{{ $monthRow['periodo'] }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format((int) $monthRow['cantidad']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format((int) $monthRow['entregados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format((int) $monthRow['pendientes']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format((float) $monthRow['peso'], 3) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sin datos mensuales.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<table class="columns">
    <tr>
        <td class="column-left">
            <div class="section-title">Servicios principales</div>
            @if(($stats['servicios_total'] ?? 0) > count($stats['servicios'] ?? []))
                <div class="small-note">Se muestran los 15 servicios con mayor cantidad.</div>
            @endif
            <table class="table">
                <thead>
                    <tr><th>Servicio</th><th class="num">Cantidad</th><th class="num">Part.</th><th class="num">Peso</th></tr>
                </thead>
                <tbody>
                    @forelse(($stats['servicios'] ?? []) as $serviceRow)
                        <tr>
                            <td>{{ $serviceRow['label'] }}</td>
                            <td class="num">{{ \App\Support\BolivianNumber::format((int) $serviceRow['cantidad']) }}</td>
                            <td class="num">{{ \App\Support\BolivianNumber::format((float) $serviceRow['participacion'], 1) }}%</td>
                            <td class="num">{{ \App\Support\BolivianNumber::format((float) $serviceRow['peso'], 3) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Sin datos por servicio.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
        <td class="column-right">
            <div class="section-title">Destinos principales</div>
            @if(($stats['destinos_total'] ?? 0) > count($stats['destinos'] ?? []))
                <div class="small-note">Se muestran los 10 destinos con mayor cantidad.</div>
            @endif
            <table class="table">
                <thead>
                    <tr><th>Destino</th><th class="num">Cantidad</th><th class="num">Participación</th></tr>
                </thead>
                <tbody>
                    @forelse(($stats['destinos'] ?? []) as $destinationRow)
                        <tr>
                            <td>{{ $destinationRow['label'] }}</td>
                            <td class="num">{{ \App\Support\BolivianNumber::format((int) $destinationRow['cantidad']) }}</td>
                            <td class="num">{{ \App\Support\BolivianNumber::format((float) $destinationRow['participacion'], 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Sin datos por destino.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>

<div class="footer">Correos de Bolivia | Sistema de Reportes</div>
</body>
</html>

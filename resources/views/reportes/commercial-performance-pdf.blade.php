<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $scopeLabel }}</title>
    <style>
        @page { margin: 14px; }
        body { font-family: Verdana, DejaVu Sans, sans-serif; font-size: 8.5px; color: #222; }
        table { width: 100%; border-collapse: collapse; }
        .head, .kpi, .table { margin-bottom: 8px; }
        .head td, .kpi td, .table th, .table td { border: 1px solid #b5b5b5; padding: 4px 5px; }
        .title { font-size: 18px; font-weight: 700; }
        .meta { font-size: 8px; color: #444; }
        .kpi td { text-align: center; }
        .k { font-size: 7px; text-transform: uppercase; color: #666; }
        .v { font-size: 11px; font-weight: 700; }
        .table th { background: #f3f4f6; font-size: 7.2px; text-transform: uppercase; }
        .num { text-align: right; }
        .section-title { margin: 8px 0 4px; font-size: 9.5px; font-weight: 700; text-transform: uppercase; }
    </style>
</head>
<body>
<table class="head">
    <tr>
        <td>
            <div class="title">{{ $scopeLabel }}</div>
            <div class="meta">Rango: {{ !empty($from) || !empty($to) ? (($from ?: 'inicio') . ' - ' . ($to ?: 'fin')) : 'todos' }}</div>
            <div class="meta">Lineas: {{ !empty($selectedLines) ? implode(', ', $selectedLines) : 'Todas' }}</div>
            <div class="meta">Fecha de emision: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="kpi">
    <tr>
        <td><div class="k">Lineas</div><div class="v">{{ number_format((int) ($commercialTotals['lineas'] ?? 0)) }}</div></td>
        <td><div class="k">Registros</div><div class="v">{{ number_format((int) ($commercialTotals['registros'] ?? 0)) }}</div></td>
        <td><div class="k">Peso total</div><div class="v">{{ number_format((float) ($commercialTotals['peso_total'] ?? 0), 3) }}</div></td>
        <td><div class="k">Ingresos</div><div class="v">{{ number_format((float) ($commercialTotals['precio_total'] ?? 0), 2) }}</div></td>
        <td><div class="k">Top línea</div><div class="v">{{ $commercialTotals['top_linea'] ?? '-' }}</div></td>
        <td><div class="k">Top cantidad</div><div class="v">{{ number_format((int) ($commercialTotals['top_linea_cantidad'] ?? 0)) }}</div></td>
    </tr>
</table>

<div class="section-title">Ranking por línea de negocio</div>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Línea</th>
            <th class="num">Cantidad</th>
            <th class="num">Entregados</th>
            <th class="num">No entregados</th>
            <th class="num">Peso</th>
            <th class="num">Bs</th>
            <th>Servicio líder</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($lineRows ?? []) as $idx => $lineRow)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $lineRow['linea'] }}</td>
                <td class="num">{{ number_format((int) $lineRow['cantidad']) }}</td>
                <td class="num">{{ number_format((int) $lineRow['entregados']) }}</td>
                <td class="num">{{ number_format((int) $lineRow['no_entregados']) }}</td>
                <td class="num">{{ number_format((float) $lineRow['peso'], 3) }}</td>
                <td class="num">{{ number_format((float) $lineRow['precio'], 2) }}</td>
                <td>{{ $lineRow['top_servicio'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8">Sin resultados.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Detalle por servicio</div>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Línea</th>
            <th>Servicio</th>
            <th class="num">Cantidad</th>
            <th class="num">Peso</th>
            <th class="num">Bs</th>
            <th>Último registro</th>
        </tr>
    </thead>
    <tbody>
        @forelse(collect($serviceRows ?? [])->take(200) as $idx => $serviceRow)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $serviceRow['linea'] }}</td>
                <td>{{ $serviceRow['servicio'] }}</td>
                <td class="num">{{ number_format((int) $serviceRow['cantidad']) }}</td>
                <td class="num">{{ number_format((float) $serviceRow['peso'], 3) }}</td>
                <td class="num">{{ number_format((float) $serviceRow['precio'], 2) }}</td>
                <td>{{ $serviceRow['ultimo_registro'] }}</td>
            </tr>
        @empty
            <tr><td colspan="7">Sin resultados.</td></tr>
        @endforelse
    </tbody>
</table>

@php
    $commercialKpis = $commercialKpis ?? [];
    $effectiveness = $commercialKpis['effectiveness'] ?? [];
    $sla = $commercialKpis['sla'] ?? [];
    $budget = $commercialKpis['budget'] ?? [];
    $heatmap = $commercialKpis['heatmap'] ?? [];
    $collections = $commercialKpis['collections'] ?? [];
@endphp

<div class="section-title">Efectividad de entrega</div>
<table class="table">
    <thead>
        <tr>
            <th>Linea</th>
            <th class="num">Total</th>
            <th class="num">Entregados</th>
            <th class="num">Devol.</th>
            <th class="num">Rezago</th>
            <th class="num">% Efec.</th>
        </tr>
    </thead>
    <tbody>
        @forelse(collect($effectiveness['rows'] ?? [])->take(12) as $row)
            <tr>
                <td>{{ $row['linea'] }}</td>
                <td class="num">{{ number_format((int) $row['total']) }}</td>
                <td class="num">{{ number_format((int) $row['entregados']) }}</td>
                <td class="num">{{ number_format((int) $row['devoluciones']) }}</td>
                <td class="num">{{ number_format((int) $row['rezago']) }}</td>
                <td class="num">{{ number_format((float) $row['efectividad_pct'], 2) }}%</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin datos.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Tiempos de servicio (SLA)</div>
<table class="table">
    <thead>
        <tr>
            <th>Linea</th>
            <th class="num">Entregados</th>
            <th>Promedio</th>
            <th>Minimo</th>
            <th>Maximo</th>
        </tr>
    </thead>
    <tbody>
        @forelse(collect($sla['rows'] ?? [])->take(12) as $row)
            <tr>
                <td>{{ $row['linea'] }}</td>
                <td class="num">{{ number_format((int) $row['entregados']) }}</td>
                <td>{{ $row['promedio'] }}</td>
                <td>{{ $row['minimo'] }}</td>
                <td>{{ $row['maximo'] }}</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin datos.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Ejecucion presupuestaria de contratos</div>
<table class="table">
    <thead>
        <tr>
            <th>Empresa</th>
            <th class="num">Presupuesto</th>
            <th class="num">Consumido</th>
            <th class="num">Saldo</th>
            <th class="num">% Ejec.</th>
            <th>Alerta</th>
        </tr>
    </thead>
    <tbody>
        @forelse(collect($budget['rows'] ?? [])->take(12) as $row)
            <tr>
                <td>{{ $row['empresa'] }}</td>
                <td class="num">{{ number_format((float) $row['presupuesto'], 2) }}</td>
                <td class="num">{{ number_format((float) $row['consumido'], 2) }}</td>
                <td class="num">{{ number_format((float) $row['saldo'], 2) }}</td>
                <td class="num">{{ number_format((float) $row['ejecucion_pct'], 2) }}%</td>
                <td>{{ $row['alerta'] }}</td>
            </tr>
        @empty
            <tr><td colspan="6">Sin datos.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Mapas de calor comercial</div>
<table class="table">
    <thead>
        <tr>
            <th>Tipo</th>
            <th>Ubicacion / Ruta</th>
            <th class="num">Cantidad</th>
        </tr>
    </thead>
    <tbody>
        @foreach(collect($heatmap['origenes'] ?? [])->take(5) as $row)
            <tr>
                <td>Origen</td>
                <td>{{ $row['ubicacion'] }}</td>
                <td class="num">{{ number_format((int) $row['cantidad']) }}</td>
            </tr>
        @endforeach
        @foreach(collect($heatmap['destinos'] ?? [])->take(5) as $row)
            <tr>
                <td>Destino</td>
                <td>{{ $row['ubicacion'] }}</td>
                <td class="num">{{ number_format((int) $row['cantidad']) }}</td>
            </tr>
        @endforeach
        @foreach(collect($heatmap['rutas'] ?? [])->take(5) as $row)
            <tr>
                <td>Ruta</td>
                <td>{{ $row['ruta'] }}</td>
                <td class="num">{{ number_format((int) $row['cantidad']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="section-title">Estado de cobranza</div>
<table class="table">
    <thead>
        <tr>
            <th>Empresa</th>
            <th class="num">Facturado</th>
            <th class="num">Cobrado</th>
            <th class="num">Pendiente</th>
            <th class="num">% Cobranza</th>
        </tr>
    </thead>
    <tbody>
        @forelse(collect($collections['rows'] ?? [])->take(12) as $row)
            <tr>
                <td>{{ $row['empresa'] }}</td>
                <td class="num">{{ number_format((float) $row['facturado'], 2) }}</td>
                <td class="num">{{ number_format((float) $row['cobrado'], 2) }}</td>
                <td class="num">{{ number_format((float) $row['pendiente'], 2) }}</td>
                <td class="num">{{ number_format((float) $row['cobranza_pct'], 2) }}%</td>
            </tr>
        @empty
            <tr><td colspan="5">Sin datos.</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>

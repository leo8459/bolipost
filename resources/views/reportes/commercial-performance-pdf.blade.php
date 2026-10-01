<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $scopeLabel }}</title>
    <style>
        @page { margin: 22px 24px; }
        body { color: #263b50; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        table { border-collapse: collapse; width: 100%; }
        .header { background: #17324d; color: #fff; margin-bottom: 12px; }
        .header td { padding: 14px 16px; }
        .eyebrow { color: #bfd4e8; font-size: 7px; font-weight: bold; letter-spacing: 1px; }
        .title { font-size: 17px; font-weight: bold; margin: 4px 0; }
        .subtitle { color: #e3edf6; font-size: 8px; }
        .meta { color: #d2e0ed; font-size: 7px; text-align: right; }
        .kpis { margin-bottom: 14px; }
        .kpis td { background: #f3f7fb; border: 1px solid #e1e9f0; padding: 9px 8px; text-align: center; width: 20%; }
        .k { color: #667b8f; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .v { color: #17324d; font-size: 13px; font-weight: bold; margin-top: 4px; }
        .section-title { border-bottom: 1px solid #d7e1ea; color: #17324d; font-size: 9px; font-weight: bold; margin: 13px 0 5px; padding-bottom: 4px; text-transform: uppercase; }
        .section-note { color: #718096; font-size: 7px; margin: -2px 0 5px; }
        .report-table { margin-bottom: 10px; page-break-inside: auto; }
        .report-table thead { display: table-header-group; }
        .report-table tr { page-break-inside: avoid; }
        .report-table th, .report-table td { border: 1px solid #dce4eb; padding: 5px 6px; }
        .report-table th { background: #edf3f8; color: #50677d; font-size: 6.6px; text-align: left; text-transform: uppercase; }
        .report-table td { font-size: 7px; }
        .num { text-align: right !important; }
        .center { text-align: center; }
        .footer { border-top: 1px solid #dce4eb; color: #7b8a99; font-size: 6.5px; margin-top: 12px; padding-top: 5px; }
    </style>
</head>
<body>
@php
    $commercialTotals = $commercialTotals ?? [];
    $commercialKpis = $commercialKpis ?? [];
    $effectiveness = $commercialKpis['effectiveness'] ?? [];
    $sla = $commercialKpis['sla'] ?? [];
    $heatmap = $commercialKpis['heatmap'] ?? [];
    $periodLabel = !empty($from) || !empty($to)
        ? (($from ? date('d/m/Y', strtotime($from)) : 'Inicio') . ' – ' . ($to ? date('d/m/Y', strtotime($to)) : 'Hoy'))
        : 'Todo el historial';
@endphp

<table class="header">
    <tr>
        <td style="width:72%">
            <div class="eyebrow">DIRECCIÓN COMERCIAL · REPORTE OPERATIVO</div>
            <div class="title">Rendimiento de servicios y productos</div>
            <div class="subtitle">Actividad, entregas, tiempos de servicio y cobertura.</div>
        </td>
        <td class="meta">
            <div><strong>Periodo</strong></div>
            <div>{{ $periodLabel }}</div>
            <div style="margin-top:5px"><strong>Líneas</strong></div>
            <div>{{ !empty($selectedLines) ? implode(', ', $selectedLines) : 'Todas' }}</div>
            <div style="margin-top:5px">Emitido {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="kpis">
    <tr>
        <td><div class="k">Líneas activas</div><div class="v">{{ \App\Support\BolivianNumber::format((int) ($commercialTotals['lineas'] ?? 0)) }}</div></td>
        <td><div class="k">Registros</div><div class="v">{{ \App\Support\BolivianNumber::format((int) ($commercialTotals['registros'] ?? 0)) }}</div></td>
        <td><div class="k">Entregas confirmadas</div><div class="v">{{ \App\Support\BolivianNumber::format((int) ($commercialTotals['entregados'] ?? 0)) }}</div></td>
        <td><div class="k">Efectividad</div><div class="v">{{ \App\Support\BolivianNumber::format((float) ($effectiveness['efectividad_pct'] ?? 0), 1) }}%</div></td>
        <td><div class="k">Peso procesado (kg)</div><div class="v">{{ \App\Support\BolivianNumber::format((float) ($commercialTotals['peso_total'] ?? 0), 3) }}</div></td>
    </tr>
</table>

<div class="section-title">Desempeño por línea de negocio</div>
<div class="section-note">Ordenado de mayor a menor por cantidad de registros.</div>
<table class="report-table">
    <thead><tr><th class="center">#</th><th>Línea</th><th class="num">Registros</th><th class="num">Entregados</th><th class="num">No entregados</th><th class="num">Efectividad</th><th class="num">Peso (kg)</th><th>Servicio principal</th><th>Último registro</th></tr></thead>
    <tbody>
        @forelse(($lineRows ?? []) as $idx => $lineRow)
            @php($lineEffectiveness = (int) ($lineRow['cantidad'] ?? 0) > 0 ? ((int) ($lineRow['entregados'] ?? 0) / (int) $lineRow['cantidad']) * 100 : 0)
            <tr>
                <td class="center">{{ $idx + 1 }}</td>
                <td>{{ $lineRow['linea'] }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format((int) $lineRow['cantidad']) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format((int) $lineRow['entregados']) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format((int) $lineRow['no_entregados']) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($lineEffectiveness, 1) }}%</td>
                <td class="num">{{ \App\Support\BolivianNumber::format((float) $lineRow['peso'], 3) }}</td>
                <td>{{ $lineRow['top_servicio'] }} ({{ \App\Support\BolivianNumber::format((int) $lineRow['top_servicio_cantidad']) }})</td>
                <td>{{ $lineRow['ultimo_registro'] }}</td>
            </tr>
        @empty
            <tr><td colspan="9">No hay registros para los filtros seleccionados.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Detalle por servicio</div>
<table class="report-table">
    <thead><tr><th class="center">#</th><th>Línea</th><th>Servicio</th><th class="num">Registros</th><th class="num">Entregados</th><th class="num">No entregados</th><th class="num">Peso (kg)</th><th>Último registro</th></tr></thead>
    <tbody>
        @forelse(collect($serviceRows ?? [])->take(300) as $idx => $serviceRow)
            <tr>
                <td class="center">{{ $idx + 1 }}</td>
                <td>{{ $serviceRow['linea'] }}</td>
                <td>{{ $serviceRow['servicio'] }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format((int) $serviceRow['cantidad']) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format((int) $serviceRow['entregados']) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format((int) $serviceRow['no_entregados']) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format((float) $serviceRow['peso'], 3) }}</td>
                <td>{{ $serviceRow['ultimo_registro'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8">No hay servicios para mostrar.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Efectividad por línea</div>
<table class="report-table">
    <thead><tr><th>Línea</th><th class="num">Total</th><th class="num">Entregados</th><th class="num">Devoluciones</th><th class="num">Rezago</th><th class="num">Pendientes</th><th class="num">Efectividad</th></tr></thead>
    <tbody>
        @forelse(collect($effectiveness['rows'] ?? [])->take(20) as $row)
            <tr><td>{{ $row['linea'] }}</td><td class="num">{{ \App\Support\BolivianNumber::format((int) $row['total']) }}</td><td class="num">{{ \App\Support\BolivianNumber::format((int) $row['entregados']) }}</td><td class="num">{{ \App\Support\BolivianNumber::format((int) $row['devoluciones']) }}</td><td class="num">{{ \App\Support\BolivianNumber::format((int) $row['rezago']) }}</td><td class="num">{{ \App\Support\BolivianNumber::format((int) $row['pendientes']) }}</td><td class="num">{{ \App\Support\BolivianNumber::format((float) $row['efectividad_pct'], 1) }}%</td></tr>
        @empty
            <tr><td colspan="7">Sin datos de entrega.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Tiempos de servicio (SLA)</div>
<table class="report-table">
    <thead><tr><th>Línea</th><th class="num">Entregados</th><th>Promedio</th><th>Mínimo</th><th>Máximo</th></tr></thead>
    <tbody>
        @forelse(collect($sla['rows'] ?? [])->take(20) as $row)
            <tr><td>{{ $row['linea'] }}</td><td class="num">{{ \App\Support\BolivianNumber::format((int) $row['entregados']) }}</td><td>{{ $row['promedio'] }}</td><td>{{ $row['minimo'] }}</td><td>{{ $row['maximo'] }}</td></tr>
        @empty
            <tr><td colspan="5">Sin datos de tiempo de servicio.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Cobertura operativa</div>
@php
    $coverageRows = collect([])
        ->concat(collect($heatmap['origenes'] ?? [])->take(8)->map(fn ($row) => ['tipo' => 'Origen', 'ubicacion' => $row['ubicacion'], 'cantidad' => $row['cantidad']]))
        ->concat(collect($heatmap['destinos'] ?? [])->take(8)->map(fn ($row) => ['tipo' => 'Destino', 'ubicacion' => $row['ubicacion'], 'cantidad' => $row['cantidad']]))
        ->concat(collect($heatmap['rutas'] ?? [])->take(8)->map(fn ($row) => ['tipo' => 'Ruta', 'ubicacion' => $row['ruta'], 'cantidad' => $row['cantidad']]));
@endphp
<table class="report-table">
    <thead><tr><th>Tipo</th><th>Ubicación o ruta</th><th class="num">Registros</th></tr></thead>
    <tbody>
        @forelse($coverageRows as $row)
            <tr><td>{{ $row['tipo'] }}</td><td>{{ $row['ubicacion'] }}</td><td class="num">{{ \App\Support\BolivianNumber::format((int) $row['cantidad']) }}</td></tr>
        @empty
            <tr><td colspan="3">Sin información de cobertura disponible.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">Reporte generado el {{ now()->format('d/m/Y H:i') }} · Los tiempos de servicio se calculan desde el registro hasta la entrega confirmada.</div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte departamento</title>
    <style>
        @page { margin: 18px; }
        body { font-family: Verdana, DejaVu Sans, sans-serif; font-size: 9px; color: #172033; }
        .header { background: #20539A; color: #fff; padding: 14px 16px; border-radius: 10px; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; }
        .meta { margin-top: 4px; font-size: 9px; opacity: .95; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 10px; }
        .kpis td { border: 1px solid #d7e3f2; border-radius: 8px; padding: 8px; }
        .k { color: #63758c; font-size: 8px; text-transform: uppercase; }
        .v { font-size: 15px; font-weight: bold; margin-top: 2px; color: #174c88; }
        .section-title { background: #edf4fd; border: 1px solid #d7e3f2; padding: 7px; font-weight: bold; color: #20539A; margin-top: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d7e3f2; padding: 5px; }
        th { background: #f0f5fb; color: #244d80; text-transform: uppercase; font-size: 8px; }
        tr:nth-child(even) td { background: #fbfdff; }
        .num { text-align: right; }
        .pill { display: inline-block; background: #edf2fb; color: #20539A; border-radius: 999px; padding: 2px 7px; font-weight: bold; }
    </style>
</head>
<body>
@php
    $item = $departamentoReporte;
    $totalesModulo = $item->entregados_por_modulo ?? [];
@endphp

<div class="header">
    <h1>Reporte de cumplimiento - {{ $item->departamento }}</h1>
    <div class="meta">
        Rango: {{ $rangoLabel }} |
        Departamento: {{ $item->departamento }} |
        Emitido: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

<table class="kpis">
    <tr>
        <td><div class="k">Registrados</div><div class="v">{{ number_format((int) $item->total) }}</div></td>
        <td><div class="k">Entregados</div><div class="v">{{ number_format((int) $item->entregados) }}</div></td>
        <td><div class="k">Pendientes</div><div class="v">{{ number_format((int) $item->pendientes) }}</div></td>
        <td><div class="k">Cumplimiento</div><div class="v">{{ number_format((float) $item->cumplimiento, 1) }}%</div></td>
        <td><div class="k">Quien entrega mas</div><div class="v" style="font-size:11px;">{{ $item->top_entregador }}</div></td>
    </tr>
</table>

<div class="section-title">Entregados por modulo</div>
<table>
    <thead>
        <tr>
            <th>EMS</th>
            <th>Contratos</th>
            <th>Certificados</th>
            <th>Ordinarios</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="num">{{ number_format((int) ($totalesModulo['EMS'] ?? 0)) }}</td>
            <td class="num">{{ number_format((int) ($totalesModulo['CONTRATOS'] ?? 0)) }}</td>
            <td class="num">{{ number_format((int) ($totalesModulo['CERTIFICADOS'] ?? 0)) }}</td>
            <td class="num">{{ number_format((int) ($totalesModulo['ORDINARIOS'] ?? 0)) }}</td>
        </tr>
    </tbody>
</table>

<div class="section-title">Detalle de paquetes entregados</div>
<table>
    <thead>
        <tr>
            <th>Modulo</th>
            <th>Codigo</th>
            <th>Entregado por</th>
            <th>Fecha entrega</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($item->entregados_detalle ?? []) as $detalle)
            <tr>
                <td>{{ $detalle['modulo'] ?? '-' }}</td>
                <td><span class="pill">{{ $detalle['codigo'] ?? '-' }}</span></td>
                <td>{{ $detalle['usuario'] ?? '-' }}</td>
                <td>{{ !empty($detalle['entregado_at'] ?? '') ? \Illuminate\Support\Carbon::parse($detalle['entregado_at'])->format('d/m/Y H:i') : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">No hay paquetes entregados para este departamento.</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>

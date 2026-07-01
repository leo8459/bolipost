<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Competencia departamentos</title>
    <style>
        @page { margin: 18px; }
        body { font-family: Verdana, DejaVu Sans, sans-serif; font-size: 9px; color: #172033; }
        .header { background: #20539A; color: #fff; padding: 14px 16px; border-radius: 10px; margin-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; }
        .meta { margin-top: 4px; font-size: 9px; opacity: .95; }
        .leader { background: #e7f7fb; border: 1px solid #9bdded; color: #116073; border-radius: 9px; padding: 9px 11px; margin-bottom: 10px; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 10px; }
        .kpis td { border: 1px solid #d7e3f2; border-radius: 8px; padding: 8px; }
        .k { color: #63758c; font-size: 8px; text-transform: uppercase; }
        .v { font-size: 14px; font-weight: bold; margin-top: 2px; color: #174c88; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d7e3f2; padding: 5px; }
        th { background: #f0f5fb; color: #244d80; text-transform: uppercase; font-size: 8px; }
        tr:nth-child(even) td { background: #fbfdff; }
        .num { text-align: right; }
        .rank { font-weight: bold; color: #20539A; }
    </style>
</head>
<body>
@php
    $rows = $rankingDepartamentos ?? collect();
    $leader = $rows->first();
@endphp

<div class="header">
    <h1>Competencia de cumplimiento por departamentos</h1>
    <div class="meta">
        Rango: {{ $rangoLabel }} |
        Modulos: {{ collect($modulosSeleccionados)->map(fn($m) => $modulosDisponibles[$m]['label'] ?? strtoupper($m))->implode(', ') }} |
        Emitido: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

@if($leader)
    <div class="leader">
        <strong>Departamento lider:</strong> {{ $leader->departamento }}
        tiene <strong>{{ number_format((float) ($leader->puntaje_ranking ?? 0), 1) }}%</strong>
        de valor ranking.
        Su parte nacional es <strong>{{ number_format((float) ($leader->participacion_nacional ?? 0), 1) }}%</strong>
        y cumplio <strong>{{ number_format((float) $leader->cumplimiento, 1) }}%</strong> de esa parte.
        Mejor entregador: <strong>{{ $leader->top_entregador }}</strong>
        ({{ number_format((int) $leader->top_entregador_total) }} entregas).
        <br>
        <strong>Formula del ranking:</strong> {{ (int) ($leader->ranking_cumplimiento_peso ?? 70) }}% cumplimiento + {{ (int) ($leader->ranking_participacion_peso ?? 30) }}% parte nacional.
    </div>
@endif

<table class="kpis">
    <tr>
        <td><div class="k">Total registrados</div><div class="v">{{ number_format((int) ($totales['paquetes'] ?? 0)) }}</div></td>
        <td><div class="k">Total entregados</div><div class="v">{{ number_format((int) ($totales['entregados'] ?? 0)) }}</div></td>
        <td><div class="k">Total pendientes</div><div class="v">{{ number_format((int) ($totales['pendientes'] ?? 0)) }}</div></td>
        <td><div class="k">Cumplimiento global</div><div class="v">{{ number_format((float) ($totales['porcentaje_entrega'] ?? 0), 1) }}%</div></td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Departamento</th>
            <th>Registrados</th>
            <th>Entregados</th>
            <th>Transito</th>
            <th>Pendientes</th>
            <th>Parte nacional</th>
            <th>Cumplio de su parte</th>
            <th>Valor ranking</th>
            <th>Quien entrega mas</th>
            <th>Detalle por modulo</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $item)
            @php($mods = $item->entregados_por_modulo ?? [])
            <tr>
                <td class="num rank">{{ $item->puesto }}</td>
                <td><strong>{{ $item->departamento }}</strong></td>
                <td class="num">{{ number_format((int) $item->total) }}</td>
                <td class="num">{{ number_format((int) $item->entregados) }}</td>
                <td class="num">{{ number_format((int) ($item->transito ?? 0)) }}</td>
                <td class="num">{{ number_format((int) $item->pendientes) }}</td>
                <td class="num">
                    {{ number_format((float) ($item->participacion_nacional ?? 0), 1) }}%<br>
                    {{ number_format((int) $item->total) }} de {{ number_format((int) ($item->total_nacional ?? 0)) }}
                </td>
                <td class="num">
                    {{ number_format((float) $item->cumplimiento, 1) }}%<br>
                    aporta {{ number_format((float) ($item->aporte_entregado_nacional ?? 0), 1) }}%
                </td>
                <td class="num">
                    {{ number_format((float) ($item->puntaje_ranking ?? 0), 1) }}%<br>
                    {{ (int) ($item->ranking_cumplimiento_peso ?? 70) }}% cumpl. + {{ (int) ($item->ranking_participacion_peso ?? 30) }}% parte
                </td>
                <td>{{ $item->top_entregador }} ({{ number_format((int) $item->top_entregador_total) }})</td>
                <td>
                    EMS: {{ number_format((int) ($mods['EMS'] ?? 0)) }},
                    Contratos: {{ number_format((int) ($mods['CONTRATOS'] ?? 0)) }},
                    Certificados: {{ number_format((int) ($mods['CERTIFICADOS'] ?? 0)) }},
                    Ordinarios: {{ number_format((int) ($mods['ORDINARIOS'] ?? 0)) }}
                </td>
            </tr>
        @empty
            <tr><td colspan="11">Sin datos por departamento.</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>

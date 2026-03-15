<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Board Report - Dashboard Corporativo</title>
    <style>
        @page { margin: 18px 18px 22px; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #12263d;
            line-height: 1.35;
        }

        .footer-fixed {
            position: fixed;
            bottom: -12px;
            right: 0;
            left: 0;
            text-align: right;
            font-size: 7.5px;
            color: #7b90aa;
        }
        .footer-fixed .page:before { content: counter(page); }

        .cover {
            border-radius: 16px;
            border: 1px solid #184579;
            background: #12457f;
            color: #fff;
            padding: 16px 18px 14px;
            position: relative;
            overflow: hidden;
            margin-bottom: 10px;
        }
        .cover .glow-a,
        .cover .glow-b {
            position: absolute;
            border-radius: 999px;
            background: #ffd75e;
            opacity: .18;
        }
        .cover .glow-a { width: 170px; height: 170px; top: -75px; right: -55px; }
        .cover .glow-b { width: 120px; height: 120px; bottom: -56px; right: 150px; background: #7bc9ff; }
        .cover h1 { margin: 0; font-size: 22px; letter-spacing: .4px; }
        .cover .sub { margin-top: 3px; font-size: 11px; opacity: .93; }
        .cover .meta { margin-top: 10px; font-size: 8.4px; opacity: .96; }
        .cover .meta strong { color: #ffe083; }

        .kpi {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin-bottom: 10px;
        }
        .kpi td {
            color: #fff;
            border-radius: 9px;
            padding: 7px 9px;
            vertical-align: top;
        }
        .kpi .k { font-size: 7.8px; text-transform: uppercase; opacity: .95; letter-spacing: .3px; }
        .kpi .v { margin-top: 2px; font-size: 13px; font-weight: 700; }
        .kpi .b1 { background: #244f92; }
        .kpi .b2 { background: #1f8355; }
        .kpi .b3 { background: #c58416; }
        .kpi .b4 { background: #b63b3b; }
        .kpi .b5 { background: #1f6f9b; }
        .kpi .b6 { background: #6b58b4; }

        .pulse {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .pulse td {
            border: 1px solid #d7e3f2;
            border-radius: 10px;
            background: #fff;
            padding: 8px 9px;
            vertical-align: middle;
        }
        .score-cell {
            width: 140px;
            text-align: center;
            border-right: 0 !important;
        }
        .score-pill {
            display: inline-block;
            min-width: 96px;
            border-radius: 11px;
            color: #fff;
            font-weight: 700;
            padding: 7px 8px;
        }
        .score-pill .n { font-size: 18px; line-height: 1; }
        .score-pill .t { font-size: 7.7px; margin-top: 2px; text-transform: uppercase; letter-spacing: .3px; }
        .desc-cell { border-left: 0 !important; font-size: 9px; color: #2f4764; }

        .section {
            border: 1px solid #d7e3f2;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
            margin-bottom: 9px;
        }
        .section .h {
            background: #edf4fd;
            border-bottom: 1px solid #d7e3f2;
            color: #214f86;
            padding: 7px 9px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .2px;
            text-transform: uppercase;
        }
        .section .b { padding: 8px 9px; }

        .bullets { margin: 0; padding-left: 15px; }
        .bullets li { margin-bottom: 4px; }

        .grid2 {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
        }
        .mini {
            border: 1px solid #d7e3f2;
            border-radius: 8px;
            background: #fff;
            padding: 6px 8px;
        }
        .mini .k { font-size: 8px; color: #607998; text-transform: uppercase; }
        .mini .v { margin-top: 2px; font-size: 12px; font-weight: 700; color: #1f4e86; }

        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            border: 1px solid #d7e3f2;
            padding: 4px 5px;
        }
        .table th {
            background: #eef4fc;
            color: #2a517d;
            font-size: 7.9px;
            text-transform: uppercase;
            letter-spacing: .2px;
        }
        .table tr:nth-child(even) td { background: #fbfdff; }
        .num { text-align: right; }
        .muted { color: #7186a2; }

        .tag { display: inline-block; border-radius: 999px; padding: 1px 6px; font-size: 7.6px; font-weight: 700; }
        .ok { background: #e8f7ed; color: #1f7548; }
        .warn { background: #fff2de; color: #8c5a00; }
        .bad { background: #fdeaea; color: #962a2a; }

        .bar-wrap {
            width: 100%;
            height: 8px;
            background: #e9f0fa;
            border: 1px solid #d2deef;
            border-radius: 999px;
            overflow: hidden;
        }
        .bar { height: 100%; background: #2c7cc7; }

        .matrix td {
            border: 1px solid #d7e3f2;
            padding: 6px;
            border-radius: 7px;
            background: #fff;
            vertical-align: top;
        }
        .matrix .title { font-size: 8.2px; text-transform: uppercase; color: #5c7594; }
        .matrix .val { font-size: 12px; font-weight: 700; color: #1e4c82; margin-top: 2px; }

        .break { page-break-before: always; }
    </style>
</head>
<body>
@php
    $score = (float) ($totales['porcentaje_entrega'] ?? 0);
    $scoreLabel = $score >= 85 ? 'ALTO' : ($score >= 65 ? 'MEDIO' : 'CRITICO');
    $scoreColor = $score >= 85 ? '#1f7a4b' : ($score >= 65 ? '#b87813' : '#a33535');
    $rezagoPct = (float) ($insightsEjecutivos['ratios']['rezago_pct'] ?? 0);
    $atrasoPct = (float) ($insightsEjecutivos['ratios']['atraso_pct'] ?? 0);
    $varReg = $insightsEjecutivos['variaciones']['registros_pct'] ?? null;
    $varEnt = $insightsEjecutivos['variaciones']['entregas_pct'] ?? null;
    $modMejor = $insightsEjecutivos['modulo_mejor']['label'] ?? 'N/D';
    $modRiesgo = $insightsEjecutivos['modulo_riesgo']['label'] ?? 'N/D';
    $modCarga = $insightsEjecutivos['modulo_mayor_carga']['label'] ?? 'N/D';
@endphp

<div class="footer-fixed">Board Report | Pagina <span class="page"></span></div>

<div class="cover">
    <div class="glow-a"></div>
    <div class="glow-b"></div>
    <h1>Board Performance Report</h1>
    <div class="sub">Analitica ejecutiva de cumplimiento, productividad y riesgo operativo</div>
    <div class="meta">
        <strong>Rango:</strong> {{ $rangoLabel }} |
        <strong>Agrupacion:</strong> {{ strtoupper($agrupacion) }} |
        <strong>Fecha de emision:</strong> {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

<table class="kpi">
    <tr>
        <td class="b1"><div class="k">Total registrados</div><div class="v">{{ number_format($totales['paquetes']) }}</div></td>
        <td class="b2"><div class="k">Total entregados</div><div class="v">{{ number_format($totales['entregados']) }}</div></td>
        <td class="b3"><div class="k">Pendientes</div><div class="v">{{ number_format($totales['pendientes']) }}</div></td>
        <td class="b4"><div class="k">Rezago</div><div class="v">{{ number_format($totales['rezago']) }}</div></td>
        <td class="b5"><div class="k">Atrasados</div><div class="v">{{ number_format($totales['atrasados']) }}</div></td>
        <td class="b6"><div class="k">Ingresos (Bs)</div><div class="v">{{ number_format($totales['ingresos'], 2) }}</div></td>
    </tr>
</table>

<table class="pulse">
    <tr>
        <td class="score-cell">
            <span class="score-pill" style="background: {{ $scoreColor }};">
                <div class="n">{{ number_format($score, 1) }}%</div>
                <div class="t">Salud Operativa {{ $scoreLabel }}</div>
            </span>
        </td>
        <td class="desc-cell">
            El comportamiento global presenta <strong>{{ number_format($score, 1) }}%</strong> de cumplimiento de entrega.
            Rezago en <strong>{{ number_format($rezagoPct, 1) }}%</strong> del flujo y retraso en
            <strong>{{ number_format($atrasoPct, 1) }}%</strong>, lo que determina prioridad sobre capacidad de salida y cierre de ciclo.
        </td>
    </tr>
</table>

<div class="section">
    <div class="h">Executive Storyline</div>
    <div class="b">
        <ul class="bullets">
            @foreach(($insightsEjecutivos['resumen_ejecutivo'] ?? []) as $linea)
                <li>{{ $linea }}</li>
            @endforeach
        </ul>
    </div>
</div>

<table class="matrix" style="width:100%; border-collapse:separate; border-spacing:6px;">
    <tr>
        <td width="20%"><div class="title">Mejor modulo</div><div class="val">{{ $modMejor }}</div></td>
        <td width="20%"><div class="title">Modulo de riesgo</div><div class="val">{{ $modRiesgo }}</div></td>
        <td width="20%"><div class="title">Mayor carga</div><div class="val">{{ $modCarga }}</div></td>
        <td width="20%"><div class="title">Var. registros</div><div class="val">{{ $varReg !== null ? (($varReg >= 0 ? '+' : '') . number_format($varReg, 1) . '%') : 'N/D' }}</div></td>
        <td width="20%"><div class="title">Var. entregas</div><div class="val">{{ $varEnt !== null ? (($varEnt >= 0 ? '+' : '') . number_format($varEnt, 1) . '%') : 'N/D' }}</div></td>
    </tr>
</table>

<table class="grid2">
    <tr>
        <td width="50%"><div class="mini"><div class="k">Registros hoy / semana / mes</div><div class="v">{{ number_format($kpisPeriodo['registros']['dia']) }} / {{ number_format($kpisPeriodo['registros']['semana']) }} / {{ number_format($kpisPeriodo['registros']['mes']) }}</div></div></td>
        <td width="50%"><div class="mini"><div class="k">Entregas hoy / semana / mes</div><div class="v">{{ number_format($kpisPeriodo['entregas']['dia']) }} / {{ number_format($kpisPeriodo['entregas']['semana']) }} / {{ number_format($kpisPeriodo['entregas']['mes']) }}</div></div></td>
    </tr>
</table>

<div class="section">
    <div class="h">Modulo Performance Board</div>
    <div class="b">
        <table class="table">
            <thead>
            <tr>
                <th>Modulo</th>
                <th class="num">Reg.</th>
                <th class="num">Ent.</th>
                <th class="num">Pend.</th>
                <th class="num">Rez.</th>
                <th class="num">Atraso</th>
                <th class="num">Tasa</th>
                <th>Barra</th>
                <th class="num">Ingresos</th>
            </tr>
            </thead>
            <tbody>
            @forelse($resumenPorModulo as $fila)
                @php $tasa = (float) $fila['tasa_entrega']; @endphp
                <tr>
                    <td><strong>{{ $fila['label'] }}</strong></td>
                    <td class="num">{{ number_format($fila['total']) }}</td>
                    <td class="num">{{ number_format($fila['entregados']) }}</td>
                    <td class="num">{{ number_format($fila['pendientes']) }}</td>
                    <td class="num">{{ number_format($fila['rezago']) }}</td>
                    <td class="num">{{ number_format($fila['atrasados']) }}</td>
                    <td class="num"><span class="tag {{ $tasa >= 80 ? 'ok' : ($tasa >= 50 ? 'warn' : 'bad') }}">{{ number_format($tasa,1) }}%</span></td>
                    <td><div class="bar-wrap"><div class="bar" style="width: {{ max(0,min(100,$tasa)) }}%;"></div></div></td>
                    <td class="num">{{ number_format($fila['ingresos'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Sin datos para el periodo seleccionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="section">
    <div class="h">Insights Prioritarios</div>
    <div class="b">
        <ul class="bullets">
            @foreach(($insightsEjecutivos['hallazgos'] ?? []) as $linea)
                <li>{{ $linea }}</li>
            @endforeach
        </ul>
    </div>
</div>

<div class="break"></div>

<div class="cover" style="padding:12px 14px 10px; margin-bottom:8px;">
    <h1 style="font-size:16px;">Analitica Detallada y Productividad</h1>
    <div class="sub" style="font-size:9.6px;">Tendencia temporal, desempeno de equipos y acciones recomendadas</div>
</div>

<div class="section">
    <div class="h">Tendencia Registros vs Entregas ({{ $rangoTendenciaLabel }})</div>
    <div class="b">
        <table class="table">
            <thead>
            <tr>
                <th>Periodo</th>
                <th class="num">Registros</th>
                <th class="num">Entregados</th>
                <th class="num">% Cumplimiento</th>
            </tr>
            </thead>
            <tbody>
            @foreach($trendLabels as $i => $label)
                @php
                    $reg = (int) ($trendSeries['registros'][$i] ?? 0);
                    $ent = (int) ($trendSeries['entregados'][$i] ?? 0);
                    $pct = $reg > 0 ? round(($ent * 100) / $reg, 1) : 0;
                @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td class="num">{{ number_format($reg) }}</td>
                    <td class="num">{{ number_format($ent) }}</td>
                    <td class="num">{{ number_format($pct, 1) }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<table class="grid2">
    <tr>
        <td width="50%">
            <div class="section">
                <div class="h">Top Entregadores</div>
                <div class="b">
                    <table class="table">
                        <thead><tr><th>Usuario</th><th class="num">Total</th><th>Detalle</th></tr></thead>
                        <tbody>
                        @forelse($rankingEntregadores as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td class="num">{{ number_format((int) $item->total_entregados) }}</td>
                                <td>E:{{ (int) $item->ems }} C:{{ (int) $item->contrato }} Ce:{{ (int) $item->certi }} O:{{ (int) $item->ordi }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">Sin datos.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </td>
        <td width="50%">
            <div class="section">
                <div class="h">Top Registradores</div>
                <div class="b">
                    <table class="table">
                        <thead><tr><th>Usuario</th><th class="num">Total</th><th>Detalle</th></tr></thead>
                        <tbody>
                        @forelse($rankingRegistradores as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td class="num">{{ number_format((int) $item->total_registrados) }}</td>
                                <td>E:{{ (int) $item->ems }} C:{{ (int) $item->contrato }} Ce:{{ (int) $item->certi }} O:{{ (int) $item->ordi }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">Sin datos.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </td>
    </tr>
</table>

<div class="section">
    <div class="h">Plan de Accion Ejecutivo</div>
    <div class="b">
        <ul class="bullets">
            @foreach(($insightsEjecutivos['recomendaciones'] ?? []) as $linea)
                <li>{{ $linea }}</li>
            @endforeach
        </ul>
    </div>
</div>

<div style="text-align:right; font-size:7.8px; color:#7d91aa; margin-top:2px;">
    Correos de Bolivia | Board-Level Operational Intelligence
</div>
</body>
</html>


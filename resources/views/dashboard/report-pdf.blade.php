<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte del Dashboard Corporativo</title>
    <style>
        @page { margin: 18px 18px 22px; }
        * { box-sizing: border-box; }
        body {
            font-family: Verdana, DejaVu Sans, sans-serif;
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

        .chart-legend { margin-bottom: 8px; color: #607998; font-size: 8px; }
        .chart-legend-item { display: inline-block; margin-right: 14px; }
        .chart-legend-swatch { display: inline-block; width: 8px; height: 8px; margin-right: 4px; border-radius: 50%; }
        .chart-bars { width: 100%; border-collapse: separate; border-spacing: 0 7px; }
        .chart-bars td { border: 0; padding: 2px 4px; vertical-align: middle; }
        .chart-bars .chart-label { width: 105px; font-weight: 700; }
        .chart-bars .chart-value { width: 120px; text-align: right; white-space: nowrap; }
        .chart-track { width: 100%; height: 13px; overflow: hidden; border-radius: 8px; background: #edf2f8; }
        .chart-fill { height: 13px; border-radius: 8px; }
        .chart-fill-delivered { background: #208354; }
        .chart-fill-pending { background: #c58416; }
        .trend-bars { table-layout: fixed; }
        .trend-bars th, .trend-bars td { padding: 3px 4px; font-size: 7.2px; }
        .break { page-break-before: always; }
    </style>
</head>
<body>
@php
    $score = (float) ($totales['porcentaje_entrega'] ?? 0);
    $scoreLabel = $score >= 85 ? 'ALTO' : ($score >= 65 ? 'MEDIO' : 'CRÍTICO');
    $scoreColor = $score >= 85 ? '#1f7a4b' : ($score >= 65 ? '#b87813' : '#a33535');
    $agrupacionLabel = match ($agrupacion) {
        'day' => 'Día',
        'week' => 'Semana',
        'month' => 'Mes',
        default => strtoupper($agrupacion),
    };
    $modulosDelReporte = collect($modulosSeleccionados ?? [])
        ->map(fn ($key) => $modulosDisponibles[$key]['label'] ?? strtoupper($key))
        ->implode(', ');
    $modulosDelReporte = $modulosDelReporte !== '' ? $modulosDelReporte : 'Todos';
    $rezagoPct = (float) ($insightsEjecutivos['ratios']['rezago_pct'] ?? 0);
    $atrasoPct = (float) ($insightsEjecutivos['ratios']['atraso_pct'] ?? 0);
    $varReg = $insightsEjecutivos['variaciones']['registros_pct'] ?? null;
    $varEnt = $insightsEjecutivos['variaciones']['entregas_pct'] ?? null;
    $modMejor = $insightsEjecutivos['modulo_mejor']['label'] ?? 'N/D';
    $modRiesgo = $insightsEjecutivos['modulo_riesgo']['label'] ?? 'N/D';
    $modCarga = $insightsEjecutivos['modulo_mayor_carga']['label'] ?? 'N/D';
    $versusLabels = array_values((array) data_get($chartVersus ?? [], 'labels', ['Entregados', 'Pendientes']));
    $versusValues = array_map('intval', array_values((array) data_get($chartVersus ?? [], 'totales', [])));
    $versusMaximum = max(1, max(array_merge([0], $versusValues)));
    $versusTotal = array_sum($versusValues);
    $trendLabels = array_values((array) ($trendLabels ?? []));
    $trendRegistered = array_map('intval', array_values((array) data_get($trendSeries ?? [], 'registros', [])));
    $trendDelivered = array_map('intval', array_values((array) data_get($trendSeries ?? [], 'entregados', [])));
    $trendMaximum = max(1, max(array_merge([0], $trendRegistered, $trendDelivered)));
    $deliveryExpressPickupCount = (int) data_get($deliveryExpressPickupAlert ?? [], 'count', 0);
    $deliveryExpressDepartments = collect(data_get($deliveryExpressPickupAlert ?? [], 'departments', []));
    $contractPickupCount = (int) ($contratosPorRecoger ?? 0);
    $contractPickupDepartments = collect($contratosPorRecogerPorDepartamento ?? []);
    $regionalPendingCount = (int) data_get($regionalPendingAlert ?? [], 'count', 0);
    $regionalPendingDepartments = collect(data_get($regionalPendingAlert ?? [], 'departments', []));
    $carteroAlertCount = (int) data_get($carteroPendingAlert ?? [], 'count', 0);
    $carteroSummaryEnabled = (bool) data_get($carteroPendingSummary ?? [], 'enabled', false);
    $carteroSummaryDepartments = collect(data_get($carteroPendingSummary ?? [], 'departments', []));
    $carteroSummaryRows = collect(data_get($carteroPendingSummary ?? [], 'rows', []));
    $carteroSummaryPendingCount = $carteroSummaryDepartments->isNotEmpty()
        ? (int) $carteroSummaryDepartments->sum(fn ($department) => (int) ($department->total_pendientes ?? 0))
        : (int) $carteroSummaryRows->sum(fn ($row) => (int) ($row->pendientes ?? 0));
    $pendingCn33Count = (int) data_get($pendingCn33Alert ?? [], 'count', 0);
    $pendingCn33Departments = collect(data_get($pendingCn33Alert ?? [], 'rows', []))
        ->groupBy(fn ($row) => trim((string) ($row->regional ?? '')) ?: 'SIN DEPARTAMENTO')
        ->map(fn ($rows, $department) => $department . ': ' . $rows->count())
        ->implode(', ');
    $hasOperationalAlerts = $deliveryExpressPickupCount > 0
        || $contractPickupCount > 0
        || $regionalPendingCount > 0
        || $carteroAlertCount > 0
        || $carteroSummaryEnabled
        || $pendingCn33Count > 0;
@endphp

<div class="footer-fixed">Reporte del dashboard | Página <span class="page"></span></div>

<div class="cover">
    <div class="glow-a"></div>
    <div class="glow-b"></div>
    <h1>Reporte del Dashboard Corporativo</h1>
    <div class="sub">Indicadores de entregas, productividad y riesgo operativo</div>
    <div class="meta">
        <div><strong>Período:</strong> {{ $rangoLabel }} | <strong>Agrupar por:</strong> {{ $agrupacionLabel }}</div>
        <div><strong>Departamento destino:</strong> {{ ($departamento ?? '') !== '' ? $departamento : 'Todos' }}</div>
        <div><strong>Módulos incluidos:</strong> {{ $modulosDelReporte }}</div>
        <div><strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

<table class="kpi">
    <tr>
        <td class="b1"><div class="k">Total registrados</div><div class="v">{{ \App\Support\BolivianNumber::format($totales['paquetes']) }}</div></td>
        <td class="b2"><div class="k">Total entregados</div><div class="v">{{ \App\Support\BolivianNumber::format($totales['entregados']) }}</div></td>
        <td class="b3"><div class="k">Pendientes</div><div class="v">{{ \App\Support\BolivianNumber::format($totales['pendientes']) }}</div></td>
        <td class="b4"><div class="k">Rezago</div><div class="v">{{ \App\Support\BolivianNumber::format($totales['rezago']) }}</div></td>
        <td class="b5"><div class="k">Atrasados</div><div class="v">{{ \App\Support\BolivianNumber::format($totales['atrasados']) }}</div></td>
        <td class="b6"><div class="k">Ingresos (Bs)</div><div class="v">{{ \App\Support\BolivianNumber::format($totales['ingresos'], 2) }}</div></td>
    </tr>
</table>

@if($hasOperationalAlerts)
<div class="section">
    <div class="h">Alertas operativas actuales</div>
    <div class="b">
        <p class="muted" style="margin-top:0;">Estos pendientes reflejan el estado operativo al generar el reporte y no dependen del período seleccionado.</p>
        <ul class="bullets">
            @if($deliveryExpressPickupCount > 0)
                <li>
                    <strong>Delivery Express por recoger:</strong>
                    {{ \App\Support\BolivianNumber::format($deliveryExpressPickupCount) }} solicitudes
                    ({{ data_get($deliveryExpressPickupAlert, 'is_national') ? 'nivel nacional' : data_get($deliveryExpressPickupAlert, 'scope_label', 'regional') }}).
                    @if($deliveryExpressDepartments->isNotEmpty())
                        Departamentos:
                        @foreach($deliveryExpressDepartments as $department)
                            {{ $department->departamento }}: {{ \App\Support\BolivianNumber::format((int) $department->total) }}@if(!$loop->last), @endif
                        @endforeach
                    @endif
                </li>
            @endif
            @if($contractPickupCount > 0)
                <li>
                    <strong>Envíos de contrato por recoger:</strong>
                    {{ \App\Support\BolivianNumber::format($contractPickupCount) }}
                    ({{ ($pickupAlertIsNational ?? false) ? 'nivel nacional' : ($userCity ?? 'regional') }}).
                    @if($contractPickupDepartments->isNotEmpty())
                        Departamentos:
                        @foreach($contractPickupDepartments as $department)
                            {{ $department->departamento }}: {{ \App\Support\BolivianNumber::format((int) $department->total) }}@if(!$loop->last), @endif
                        @endforeach
                    @endif
                </li>
            @endif
            @if($regionalPendingCount > 0)
                <li>
                    <strong>Envíos pendientes por más de {{ (int) data_get($regionalPendingAlert, 'hours', 72) }} horas hábiles:</strong>
                    {{ \App\Support\BolivianNumber::format($regionalPendingCount) }}
                    ({{ data_get($regionalPendingAlert, 'scope') === 'nacional' ? 'nivel nacional' : data_get($regionalPendingAlert, 'regional', 'regional') }}).
                    @if($regionalPendingDepartments->isNotEmpty())
                        Departamentos:
                        @foreach($regionalPendingDepartments as $department)
                            {{ $department->departamento }}: {{ \App\Support\BolivianNumber::format((int) $department->total) }}@if(!$loop->last), @endif
                        @endforeach
                    @endif
                </li>
            @endif
            @if($carteroAlertCount > 0)
                <li><strong>Pendientes en la bandeja del cartero {{ data_get($carteroPendingAlert, 'name', '') }}:</strong> {{ \App\Support\BolivianNumber::format($carteroAlertCount) }} paquetes.</li>
            @endif
            @if($carteroSummaryEnabled)
                <li>
                    <strong>Paquetes en bandejas CARTERO:</strong>
                    {{ \App\Support\BolivianNumber::format($carteroSummaryPendingCount) }}
                    ({{ data_get($carteroPendingSummary, 'scope') === 'nacional' ? 'nivel nacional' : ($userCity ?? 'regional') }}).
                </li>
            @endif
            @if($pendingCn33Count > 0)
                <li>
                    <strong>CN-33 sin bitácora:</strong>
                    {{ \App\Support\BolivianNumber::format($pendingCn33Count) }} registros con más de
                    {{ (int) data_get($pendingCn33Alert, 'grace_hours', 24) }} horas de retraso
                    ({{ data_get($pendingCn33Alert, 'regional') ?: 'nivel nacional' }}).
                    @if($pendingCn33Departments !== '') Departamentos: {{ $pendingCn33Departments }}.@endif
                </li>
            @endif
        </ul>
    </div>
</div>
@endif

<table class="pulse">
    <tr>
        <td class="score-cell">
            <span class="score-pill" style="background: {{ $scoreColor }};">
                <div class="n">{{ \App\Support\BolivianNumber::format($score, 1) }}%</div>
                <div class="t">Cumplimiento {{ $scoreLabel }}</div>
            </span>
        </td>
        <td class="desc-cell">
            El cumplimiento de entrega es <strong>{{ \App\Support\BolivianNumber::format($score, 1) }}%</strong>.
            El <strong>{{ \App\Support\BolivianNumber::format($rezagoPct, 1) }}%</strong> está en rezago y el
            <strong>{{ \App\Support\BolivianNumber::format($atrasoPct, 1) }}%</strong> presenta retraso.
        </td>
    </tr>
</table>

<div class="section chart-section">
    <div class="h">Grafico comparativo: entregados y pendientes</div>
    <div class="b">
        <div class="chart-legend">
            <span class="chart-legend-item"><span class="chart-legend-swatch chart-fill-delivered"></span>Entregados</span>
            <span class="chart-legend-item"><span class="chart-legend-swatch chart-fill-pending"></span>Pendientes</span>
        </div>
        <table class="chart-bars">
            <tbody>
            @forelse($versusLabels as $index => $label)
                @php
                    $value = (int) ($versusValues[$index] ?? 0);
                    $barWidth = round(($value * 100) / $versusMaximum, 2);
                    $share = $versusTotal > 0 ? round(($value * 100) / $versusTotal, 1) : 0;
                    $barClass = $index === 0 ? 'chart-fill-delivered' : 'chart-fill-pending';
                @endphp
                <tr>
                    <td class="chart-label">{{ $label }}</td>
                    <td><div class="chart-track"><div class="chart-fill {{ $barClass }}" style="width: {{ $barWidth }}%;"></div></div></td>
                    <td class="chart-value">{{ \App\Support\BolivianNumber::format($value) }} ({{ \App\Support\BolivianNumber::format($share, 1) }}%)</td>
                </tr>
            @empty
                <tr><td class="muted">Sin datos para el periodo seleccionado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="section">
    <div class="h">Resumen ejecutivo</div>
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
        <td width="20%"><div class="title">Mejor módulo</div><div class="val">{{ $modMejor }}</div></td>
        <td width="20%"><div class="title">Módulo con mayor riesgo</div><div class="val">{{ $modRiesgo }}</div></td>
        <td width="20%"><div class="title">Módulo con más envíos</div><div class="val">{{ $modCarga }}</div></td>
        <td width="20%"><div class="title">Cambio en registros</div><div class="val">{{ $varReg !== null ? (($varReg >= 0 ? '+' : '') . \App\Support\BolivianNumber::format($varReg, 1) . '%') : 'N/D' }}</div></td>
        <td width="20%"><div class="title">Cambio en entregas</div><div class="val">{{ $varEnt !== null ? (($varEnt >= 0 ? '+' : '') . \App\Support\BolivianNumber::format($varEnt, 1) . '%') : 'N/D' }}</div></td>
    </tr>
</table>

<div class="section">
    <div class="h">Ranking de departamentos por cumplimiento</div>
    <div class="b">
        @if(($rankingDepartamentos ?? collect())->isNotEmpty())
            @php
                $topDepartamento = ($rankingDepartamentos ?? collect())->first();
            @endphp
            <p style="margin-top:0;">
                <strong>#1 {{ $topDepartamento->departamento }}</strong> tiene
                <strong>{{ \App\Support\BolivianNumber::format((float) $topDepartamento->cumplimiento, 1) }}%</strong>
                de cumplimiento.
                Mayor cantidad de entregas: <strong>{{ $topDepartamento->top_entregador }}</strong>
                ({{ \App\Support\BolivianNumber::format((int) $topDepartamento->top_entregador_total) }} entregas).
            </p>
        @endif
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Departamento</th>
                    <th>Envíos registrados</th>
                    <th>Envíos entregados</th>
                    <th>En tránsito</th>
                    <th>Pendientes</th>
                    <th>Cumplimiento</th>
                    <th>Mayor cantidad de entregas</th>
                </tr>
            </thead>
            <tbody>
                @forelse(($rankingDepartamentos ?? collect()) as $item)
                    <tr>
                        <td class="num">{{ $item->puesto }}</td>
                        <td>{{ $item->departamento }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->total) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->entregados) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) ($item->transito ?? 0)) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->pendientes) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((float) $item->cumplimiento, 1) }}%</td>
                        <td>{{ $item->top_entregador }} ({{ \App\Support\BolivianNumber::format((int) $item->top_entregador_total) }})</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="muted">Sin datos por departamento.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="section">
    <div class="h">Resultados por módulo</div>
    <div class="b">
        <p class="muted" style="margin-top:0;">Los ingresos se expresan en bolivianos (Bs). Los contratos no se incluyen en ingresos por el esquema tarifario.</p>
        <table class="table">
            <thead>
            <tr>
                <th>Módulo</th>
                <th class="num">Registrados</th>
                <th class="num">Entregados</th>
                <th class="num">Pendientes</th>
                <th class="num">En plazo</th>
                <th class="num">Con retraso</th>
                <th class="num">Rezago</th>
                <th class="num">Entrega (%)</th>
                <th class="num">Peso (kg)</th>
                <th>Nivel de cumplimiento</th>
                <th class="num">Ingresos (Bs)</th>
            </tr>
            </thead>
            <tbody>
            @if(!empty($resumenPorModulo))
            @foreach($resumenPorModulo as $fila)
                @php $tasa = (float) $fila['tasa_entrega']; @endphp
                <tr>
                    <td><strong>{{ $fila['label'] }}</strong></td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($fila['total']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($fila['entregados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($fila['pendientes']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($fila['correctos']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($fila['atrasados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($fila['rezago']) }}</td>
                    <td class="num"><span class="tag {{ $tasa >= 80 ? 'ok' : ($tasa >= 50 ? 'warn' : 'bad') }}">{{ \App\Support\BolivianNumber::format($tasa,1) }}%</span></td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($fila['peso_total'], 3) }}</td>
                    <td><div class="bar-wrap"><div class="bar" style="width: {{ max(0,min(100,$tasa)) }}%;"></div></div></td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($fila['ingresos'], 2) }}</td>
                </tr>
            @endforeach
            @else
                <tr><td colspan="11" class="muted">Sin datos para el periodo seleccionado.</td></tr>
            @endif
            </tbody>
        </table>
    </div>
</div>

<div class="section">
    <div class="h">Hallazgos principales</div>
    <div class="b">
        <ul class="bullets">
            @foreach(($insightsEjecutivos['hallazgos'] ?? []) as $linea)
                <li>{{ $linea }}</li>
            @endforeach
        </ul>
    </div>
</div>

<div class="cover" style="padding:12px 14px 10px; margin-bottom:8px;">
    <h1 style="font-size:16px;">Tendencias y productividad</h1>
    <div class="sub" style="font-size:9.6px;">Evolución de envíos y resultados por persona</div>
</div>

<div class="section chart-section">
    <div class="h">Grafico de tendencia de registros y entregas ({{ $rangoTendenciaLabel }})</div>
    <div class="b">
        <div class="chart-legend">
            <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:#2473b8;"></span>Registros</span>
            <span class="chart-legend-item"><span class="chart-legend-swatch" style="background:#208354;"></span>Entregados</span>
        </div>
        @if(count($trendLabels) > 0)
            <table class="trend-bars table">
                <colgroup>
                    <col style="width: 13%;">
                    <col style="width: 9%;">
                    <col style="width: 29%;">
                    <col style="width: 9%;">
                    <col style="width: 40%;">
                </colgroup>
                <thead>
                    <tr>
                        <th rowspan="2">Periodo</th>
                        <th colspan="2" class="num">Registros</th>
                        <th colspan="2" class="num">Entregados</th>
                    </tr>
                    <tr>
                        <th class="num">Cantidad</th>
                        <th>Grafico</th>
                        <th class="num">Cantidad</th>
                        <th>Grafico</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($trendLabels as $index => $label)
                        @php
                            $registeredValue = (int) ($trendRegistered[$index] ?? 0);
                            $deliveredValue = (int) ($trendDelivered[$index] ?? 0);
                            $registeredWidth = round(($registeredValue * 100) / $trendMaximum, 2);
                            $deliveredWidth = round(($deliveredValue * 100) / $trendMaximum, 2);
                        @endphp
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="num">{{ \App\Support\BolivianNumber::format($registeredValue) }}</td>
                            <td><div class="chart-track"><div class="chart-fill" style="width: {{ $registeredWidth }}%; background:#2473b8;"></div></div></td>
                            <td class="num">{{ \App\Support\BolivianNumber::format($deliveredValue) }}</td>
                            <td><div class="chart-track"><div class="chart-fill chart-fill-delivered" style="width: {{ $deliveredWidth }}%;"></div></div></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="muted">Sin datos de tendencia para el periodo seleccionado.</p>
        @endif
    </div>
</div>
<div class="section">
    <div class="h">Envíos registrados y entregados por período ({{ $rangoTendenciaLabel }})</div>
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
                    <td class="num">{{ \App\Support\BolivianNumber::format($reg) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($ent) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($pct, 1) }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="section">
    <div class="b">
        <table class="table">
            <thead>
                <tr><th colspan="3">Personas con mas entregas</th></tr>
                <tr><th>Persona</th><th class="num">Entregas</th><th>Cantidad por modulo</th></tr>
            </thead>
            <tbody>
            @forelse($rankingEntregadores as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->total_entregados) }}</td>
                    <td>EMS: {{ (int) $item->ems }} | Contratos: {{ (int) $item->contrato }} | Certificados: {{ (int) $item->certi }} | Ordinarios: {{ (int) $item->ordi }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Sin datos.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="section">
    <div class="b">
        <table class="table">
            <thead>
                <tr><th colspan="3">Personas con mas registros</th></tr>
                <tr><th>Persona</th><th class="num">Registros</th><th>Cantidad por modulo</th></tr>
            </thead>
            <tbody>
            @forelse($rankingRegistradores as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->total_registrados) }}</td>
                    <td>EMS: {{ (int) $item->ems }} | Contratos: {{ (int) $item->contrato }} | Certificados: {{ (int) $item->certi }} | Ordinarios: {{ (int) $item->ordi }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">Sin datos.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="section">
    <div class="h">Recomendaciones de gestión</div>
    <div class="b">
        <ul class="bullets">
            @foreach(($insightsEjecutivos['recomendaciones'] ?? []) as $linea)
                <li>{{ $linea }}</li>
            @endforeach
        </ul>
    </div>
</div>

<div style="text-align:right; font-size:7.8px; color:#7d91aa; margin-top:2px;">
    Correos de Bolivia | Reporte operativo del dashboard
</div>
</body>
</html>


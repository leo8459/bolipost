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
        .head {
            width: 100%;
            border: 1px solid #9ca3af;
            border-collapse: collapse;
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
        .kpi,
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .kpi {
            margin-bottom: 8px;
        }
        .kpi td,
        .table th,
        .table td {
            border: 1px solid #b5b5b5;
            padding: 2px 3px;
        }
        .kpi td {
            text-align: center;
            background: #ffffff;
        }
        .kpi .k {
            color: #666666;
            font-size: 6px;
            text-transform: uppercase;
        }
        .kpi .v {
            color: #111111;
            font-size: 9px;
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
        .note {
            border: 1px solid #c4c4c4;
            background: #f8fafc;
            padding: 6px 8px;
            margin-bottom: 8px;
            font-size: 7px;
            color: #333333;
        }
        .table th {
            background: #f3f4f6;
            color: #111111;
            font-size: 6.2px;
            text-transform: uppercase;
        }
        .table td {
            font-size: 6.3px;
        }
        .num {
            text-align: right;
        }
        .tfoot td {
            background: #f3f4f6;
            font-weight: 700;
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
            <div class="meta"><strong>Reporte imprimible de registros creados, excepto cancelados</strong></div>
            <div class="meta">Modulos: {{ implode(', ', $moduleLabels) }}</div>
            @if(!empty($selectedMonthLabels))
                <div class="meta">Meses: {{ implode(', ', $selectedMonthLabels) }}</div>
            @elseif(!empty($from) || !empty($to))
                <div class="meta">Rango: {{ $from ?: 'inicio' }} - {{ $to ?: 'fin' }}</div>
            @else
                <div class="meta">Rango: todos</div>
            @endif
            <div class="meta">Fecha de emision: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<div class="note">
    Este PDF muestra los totales completos de {{ number_format($pdfTotalRows ?? ($summary['total_filtrado'] ?? 0)) }}
    registros filtrados y los primeros {{ number_format(count($rows)) }} registros de detalle para que el archivo no se corte.
    El detalle completo esta en el Excel.
</div>

<table class="kpi">
    <tr>
        <td><div class="k">Registrados</div><div class="v">{{ number_format($summary['registrados'] ?? ($summary['total'] ?? 0)) }}</div></td>
        <td><div class="k">Mostrados</div><div class="v">{{ number_format($summary['total_filtrado'] ?? ($summary['total'] ?? 0)) }}</div></td>
        <td><div class="k">Entregados</div><div class="v">{{ number_format($summary['entregados'] ?? 0) }}</div></td>
        <td><div class="k">No entregados</div><div class="v">{{ number_format($summary['no_entregados'] ?? 0) }}</div></td>
        <td><div class="k">Peso total</div><div class="v">{{ number_format((float) ($totals['peso_total'] ?? 0), 3) }}</div></td>
        <td><div class="k">Ingreso total Bs</div><div class="v">{{ number_format((float) ($totals['precio_total'] ?? 0), 2) }}</div></td>
    </tr>
</table>

<div class="section-title">Resumen por grupo</div>
<table class="table">
    <thead>
        <tr>
            <th>Grupo</th>
            <th class="num">Cantidad</th>
            <th class="num">Peso</th>
            <th class="num">Bs</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($moduleSummary ?? []) as $moduleRow)
            <tr>
                <td>{{ $moduleRow['label'] }}</td>
                <td class="num">{{ number_format((int) $moduleRow['total']) }}</td>
                <td class="num">{{ number_format((float) $moduleRow['peso'], 3) }}</td>
                <td class="num">{{ number_format((float) $moduleRow['precio'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">Sin datos por grupo.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Detalle visible en PDF</div>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Codigo</th>
            <th>Modulo</th>
            <th>Servicio</th>
            <th>Estado</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Destinatario</th>
            <th class="num">Peso</th>
            <th class="num">Bs</th>
            <th>Fecha</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $idx => $row)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $row['codigo'] }}</td>
                <td>{{ $row['modulo_label'] }}</td>
                <td>{{ $row['servicio'] }}</td>
                <td>{{ $row['estado'] }}</td>
                <td>{{ $row['origen'] }}</td>
                <td>{{ $row['destino'] }}</td>
                <td>{{ $row['destinatario'] }}</td>
                <td class="num">{{ number_format((float) $row['peso'], 3) }}</td>
                <td class="num">{{ number_format((float) $row['precio'], 2) }}</td>
                <td>{{ $row['created_at'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11">Sin resultados para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot class="tfoot">
        <tr>
            <td colspan="8">Totales generales de todos los registros filtrados</td>
            <td class="num">{{ number_format((float) ($totals['peso_total'] ?? 0), 3) }}</td>
            <td class="num">{{ number_format((float) ($totals['precio_total'] ?? 0), 2) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<div class="footer">Correos de Bolivia | Sistema de Reportes</div>
</body>
</html>

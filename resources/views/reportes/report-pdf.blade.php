<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $scopeLabel }}</title>
    <style>
        @page { margin: 14px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.2px;
            color: #222222;
        }
        .head {
            width: 100%;
            border: 1px solid #9ca3af;
            border-radius: 8px;
            background: #ffffff;
            margin-bottom: 8px;
            border-collapse: collapse;
        }
        .head td {
            padding: 9px 10px;
            vertical-align: middle;
        }
        .head .title {
            font-size: 20px;
            color: #111111;
            font-weight: 700;
            margin: 0;
        }
        .head .meta {
            color: #444444;
            font-size: 9px;
            margin-top: 2px;
        }
        .logo {
            width: 74px;
            height: auto;
            padding: 0;
        }

        .kpi {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .kpi td {
            border: 1px solid #b5b5b5;
            padding: 5px 4px;
            text-align: center;
            background: #ffffff;
        }
        .kpi .k {
            color: #666666;
            font-size: 7.4px;
            text-transform: uppercase;
        }
        .kpi .v {
            color: #111111;
            font-size: 12px;
            font-weight: 700;
            margin-top: 1px;
        }

        .section-title {
            margin: 8px 0 4px;
            font-size: 10px;
            color: #111111;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .2px;
            border-left: 4px solid #9ca3af;
            padding-left: 6px;
        }
        .help-box {
            border: 1px solid #c4c4c4;
            background: #fcfcfc;
            padding: 6px 8px;
            margin-bottom: 8px;
            font-size: 8.2px;
            color: #333333;
        }
        .help-box b {
            color: #111111;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th,
        .table td {
            border: 1px solid #b5b5b5;
            padding: 3px 4px;
        }
        .table th {
            background: #f3f4f6;
            color: #111111;
            font-size: 7.3px;
            text-transform: uppercase;
            letter-spacing: .2px;
        }
        .table td {
            font-size: 8px;
        }
        .table tr:nth-child(even) td {
            background: #fafafa;
        }
        .num {
            text-align: right;
        }
        .tfoot td {
            background: #f3f4f6;
            font-weight: 700;
            color: #111111;
        }
        .footer {
            margin-top: 8px;
            text-align: right;
            font-size: 8px;
            color: #555555;
        }
    </style>
</head>
<body>
@php
    $logoPath = public_path('images/AGBClogo2.png');
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
            <div class="meta"><strong>Reporte de seguimiento de paquetes</strong></div>
            <div class="meta">Modulos: {{ implode(', ', $moduleLabels) }}</div>
            <div class="meta">Fecha de emision: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="kpi">
    <tr>
        <td><div class="k">Total de paquetes</div><div class="v">{{ number_format($summary['registrados'] ?? ($summary['total'] ?? 0)) }}</div></td>
        <td><div class="k">Paquetes mostrados</div><div class="v">{{ number_format($summary['total_filtrado'] ?? ($summary['total'] ?? 0)) }}</div></td>
        <td><div class="k">Entregados</div><div class="v">{{ number_format($summary['entregados'] ?? 0) }}</div></td>
        <td><div class="k">Pendientes</div><div class="v">{{ number_format($summary['no_entregados'] ?? 0) }}</div></td>
        <td><div class="k">Peso total</div><div class="v">{{ number_format((float) ($totals['peso_total'] ?? 0), 3) }}</div></td>
        <td><div class="k">Ingreso total (Bs)</div><div class="v">{{ number_format((float) ($totals['precio_total'] ?? 0), 2) }}</div></td>
    </tr>
</table>

<div class="help-box">
    <b>Como leer este reporte:</b> "Total de paquetes" es todo el universo consultado, "Paquetes mostrados" es el resultado con filtros.
    "Pendientes" son los no entregados. El bloque por modulo te ayuda a ver donde hay mas carga.
</div>

<div class="section-title">Resumen rapido por modulo</div>
<table class="table">
    <thead>
        <tr>
            <th>Modulo</th>
            <th class="num">Total</th>
            <th class="num">Entregados</th>
            <th class="num">Pendientes</th>
            <th class="num">Peso total</th>
            <th class="num">Ingreso (Bs)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($moduleSummary as $mod)
            <tr>
                <td>{{ $mod['label'] }}</td>
                <td class="num">{{ number_format($mod['total']) }}</td>
                <td class="num">{{ number_format($mod['entregados']) }}</td>
                <td class="num">{{ number_format($mod['no_entregados']) }}</td>
                <td class="num">{{ number_format((float) $mod['peso'], 3) }}</td>
                <td class="num">Bs {{ number_format((float) $mod['precio'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">Sin datos por modulo.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Detalle de paquetes (lista completa)</div>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Modulo</th>
            <th>Codigo</th>
            <th>Estado actual</th>
            <th>Situacion</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Remitente</th>
            <th>Destinatario</th>
            <th>Empresa/Cliente</th>
            <th>Responsable</th>
            <th class="num">Peso</th>
            <th class="num">Monto (Bs)</th>
            <th>Fecha registro</th>
            <th>Ult. actualizacion</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $idx => $row)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $row['modulo_label'] }}</td>
                <td>{{ $row['codigo'] }}</td>
                <td>{{ $row['estado'] }}</td>
                <td>{{ $row['situacion'] }}</td>
                <td>{{ $row['origen'] }}</td>
                <td>{{ $row['destino'] }}</td>
                <td>{{ $row['remitente'] }}</td>
                <td>{{ $row['destinatario'] }}</td>
                <td>{{ $row['empresa'] }}</td>
                <td>{{ $row['usuario'] }}</td>
                <td class="num">{{ number_format((float) $row['peso'], 3) }}</td>
                <td class="num">Bs {{ number_format((float) $row['precio'], 2) }}</td>
                <td>{{ $row['created_at'] }}</td>
                <td>{{ $row['updated_at'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="15">Sin resultados para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
    <tfoot class="tfoot">
        <tr>
            <td colspan="11">Totales generales</td>
            <td class="num">{{ number_format((float) ($totals['peso_total'] ?? 0), 3) }}</td>
            <td class="num">Bs {{ number_format((float) ($totals['precio_total'] ?? 0), 2) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>

<div class="footer">Correos de Bolivia | Sistema de Reportes</div>
</body>
</html>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $scopeLabel }}</title>
    <style>
        @page { margin: 14px; }
        body {
            font-family: Verdana, DejaVu Sans, sans-serif;
            font-size: 8.5px;
            color: #222222;
        }
        .head, .table, .kpi {
            width: 100%;
            border-collapse: collapse;
        }
        .head {
            margin-bottom: 8px;
            border: 1px solid #9ca3af;
        }
        .head td {
            padding: 8px 10px;
            vertical-align: middle;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
        }
        .meta {
            font-size: 8px;
            color: #444444;
            margin-top: 2px;
        }
        .logo {
            width: 70px;
            height: auto;
        }
        .kpi {
            margin-bottom: 8px;
        }
        .kpi td, .table th, .table td {
            border: 1px solid #b5b5b5;
            padding: 4px 5px;
        }
        .kpi td {
            text-align: center;
        }
        .k {
            font-size: 7px;
            text-transform: uppercase;
            color: #666666;
        }
        .v {
            font-size: 11px;
            font-weight: 700;
            margin-top: 2px;
        }
        .section-title {
            margin: 8px 0 4px;
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            border-left: 4px solid #9ca3af;
            padding-left: 6px;
        }
        .table th {
            background: #f3f4f6;
            font-size: 7.2px;
            text-transform: uppercase;
        }
        .num {
            text-align: right;
        }
        .footer {
            margin-top: 8px;
            text-align: right;
            font-size: 7px;
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
            <div class="meta">Módulos: {{ implode(', ', $moduleLabels) }}</div>
            <div class="meta">Recepción: {{ !empty($selectedReceptionChannels) ? implode(', ', $selectedReceptionChannels) : 'Todas' }}</div>
            <div class="meta">Servicios: {{ !empty($selectedServices) ? implode(', ', $selectedServices) : 'Todos' }}</div>
            <div class="meta">
                @if(!empty($selectedMonthLabels))
                    Meses: {{ implode(', ', $selectedMonthLabels) }}
                @elseif(!empty($from) || !empty($to))
                    Rango: {{ $from ?: 'inicio' }} - {{ $to ?: 'fin' }}
                @else
                    Rango: todos
                @endif
            </div>
            <div class="meta">Fecha de emision: {{ now()->format('d/m/Y H:i') }}</div>
        </td>
    </tr>
</table>

<table class="kpi">
    <tr>
        <td><div class="k">Servicios</div><div class="v">{{ number_format((int) ($serviceTotals['servicios'] ?? 0)) }}</div></td>
        <td><div class="k">Registros</div><div class="v">{{ number_format((int) ($serviceTotals['registros'] ?? 0)) }}</div></td>
        <td><div class="k">Empresa</div><div class="v">{{ number_format((int) ($serviceTotals['empresa_count'] ?? 0)) }}</div></td>
        <td><div class="k">Admisión</div><div class="v">{{ number_format((int) ($serviceTotals['admision_count'] ?? 0)) }}</div></td>
        <td><div class="k">Peso total</div><div class="v">{{ number_format((float) ($serviceTotals['peso_total'] ?? 0), 3) }}</div></td>
        <td><div class="k">Ingreso Bs</div><div class="v">{{ number_format((float) ($serviceTotals['precio_total'] ?? 0), 2) }}</div></td>
    </tr>
</table>

<div class="section-title">Resumen por servicio</div>
<table class="table">
    <thead>
        <tr>
            <th>#</th>
            <th>Servicio</th>
            <th class="num">Cantidad</th>
            <th class="num">Entregados</th>
            <th class="num">No entregados</th>
            <th class="num">Peso</th>
            <th class="num">Bs</th>
            <th>Recepción</th>
            <th>Módulos</th>
            <th>Último registro</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($serviceRows ?? []) as $idx => $serviceRow)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $serviceRow['servicio'] }}</td>
                <td class="num">{{ number_format((int) $serviceRow['cantidad']) }}</td>
                <td class="num">{{ number_format((int) $serviceRow['entregados']) }}</td>
                <td class="num">{{ number_format((int) $serviceRow['no_entregados']) }}</td>
                <td class="num">{{ number_format((float) $serviceRow['peso'], 3) }}</td>
                <td class="num">{{ number_format((float) $serviceRow['precio'], 2) }}</td>
                <td>{{ $serviceRow['canales_texto'] ?: '-' }}</td>
                <td>{{ $serviceRow['modulos_texto'] ?: '-' }}</td>
                <td>{{ $serviceRow['ultimo_registro'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10">Sin resultados para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    TrackingBO - Global por servicio
</div>
</body>
</html>

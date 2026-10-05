<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte ejecutivo de entregas</title>
    <style>
        @page { size: A4 landscape; margin: 26px 28px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #24364b; }
        table { width: 100%; border-collapse: collapse; }
        .cover-head { margin-bottom: 10px; }
        .cover-head td { padding: 10px 12px; vertical-align: middle; }
        .brand { background: #123b70; color: #ffffff; }
        .brand td { border: 0; }
        .logo { width: 56px; height: auto; }
        .eyebrow { font-size: 7px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #d4e3f6; }
        .title { margin: 3px 0; font-size: 19px; font-weight: bold; color: #ffffff; }
        .meta { font-size: 8px; color: #e2eaf5; }
        .kpis { margin-bottom: 12px; table-layout: fixed; }
        .kpis td { width: 50%; padding: 6px 8px; border: 1px solid #d8e1ec; background: #f7f9fc; text-align: center; }
        .kpi-label { color: #66778b; font-size: 6.5px; font-weight: bold; text-transform: uppercase; }
        .kpi-value { margin-top: 4px; color: #123b70; font-size: 13px; font-weight: bold; }
        .section-title { margin: 10px 0 5px; padding: 6px 8px; border-left: 4px solid #1f5fae; background: #edf3fa; color: #123b70; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .table th, .table td { padding: 5px 5px; border: 1px solid #d8e1ec; }
        .table th { background: #123b70; color: #ffffff; font-size: 6.7px; text-align: left; text-transform: uppercase; }
        .table td { font-size: 7.4px; }
        .table tbody tr:nth-child(even) td { background: #f7f9fc; }
        .table .total-row td { background: #e9f0f8; color: #123b70; font-weight: bold; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .dept-page { page-break-before: always; }
        .dept-head { margin-bottom: 8px; }
        .dept-head td { padding: 8px 10px; border: 1px solid #d8e1ec; }
        .dept-name { color: #123b70; font-size: 16px; font-weight: bold; }
        .dept-subtitle { margin-top: 3px; color: #66778b; font-size: 8px; }
        .dept-kpis { margin-bottom: 9px; table-layout: fixed; }
        .dept-kpis td { width: 50%; padding: 5px 8px; border: 1px solid #d8e1ec; text-align: center; }
        .dept-kpis .kpi-value { font-size: 10px; }
        .courier-table thead { display: table-header-group; }
        .courier-table tr { page-break-inside: avoid; }
        .courier-name { font-weight: bold; color: #20344a; }
        .empty { padding: 20px; border: 1px solid #d8e1ec; color: #66778b; text-align: center; }
        .footer { position: fixed; right: 0; bottom: -25px; left: 0; padding-top: 5px; border-top: 1px solid #d8e1ec; color: #77869a; font-size: 7px; }
        .footer-left { float: left; }
    </style>
</head>
<body>
@php
    $carteros = collect($entregadores ?? []);
    $departamentos = collect($resumenDepartamentos ?? []);
    $totalAsignados = (int) $carteros->sum('total_asignados');
    $totalFisica = (int) $carteros->sum('total_cartero_entregados');
    $totalVentanilla = (int) $carteros->sum('total_ventanilla');
    $totalEntregados = (int) $carteros->sum('total_entregados');
    $totalPendientes = (int) $carteros->sum('pendientes_asignados');
    $cumplimientoGeneral = (float) ($cumplimientoGeneral ?? 0);
    $logoPath = public_path('images/AGBClogo1.png');
    $logoData = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
    $moduleLabels = collect($modulosSeleccionados ?? [])
        ->map(fn ($key) => $modulosDisponibles[$key]['label'] ?? strtoupper((string) $key))
        ->implode(', ');
@endphp

<table class="cover-head">
    <tr class="brand">
        <td width="70">
            @if($logoData)
                <img src="{{ $logoData }}" class="logo" alt="Bolipost">
            @endif
        </td>
        <td>
            <div class="eyebrow">Bolipost | Rendimiento operativo</div>
            <div class="title">Reporte ejecutivo de entregas</div>
            <div class="meta">
                Periodo: {{ $rangoLabel ?? 'Todo el historial' }}
                @if(!empty($rangoDesde) || !empty($rangoHasta))
                    ({{ $rangoDesde ?: 'inicio' }} a {{ $rangoHasta ?: 'fin' }})
                @endif
                | Módulos: {{ $moduleLabels ?: 'Todos' }}
                | Emitido: {{ now()->format('d/m/Y H:i') }}
            </div>
        </td>
    </tr>
</table>

<table class="kpis">
    <tbody>
        <tr>
            <td><div class="kpi-label">Carteros</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($carteros->count()) }}</div></td>
            <td><div class="kpi-label">Asignados</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($totalAsignados) }}</div></td>
        </tr>
        <tr>
            <td><div class="kpi-label">Entrega física</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($totalFisica) }}</div></td>
            <td><div class="kpi-label">Ventanilla</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($totalVentanilla) }}</div></td>
        </tr>
        <tr>
            <td><div class="kpi-label">Total entregados</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($totalEntregados) }}</div></td>
            <td><div class="kpi-label">Promedio diario</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($promedioDiarioGeneral ?? 0, 2) }}</div></td>
        </tr>
        <tr>
            <td><div class="kpi-label">Pendientes</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($totalPendientes) }}</div></td>
            <td><div class="kpi-label">Cumplimiento</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($cumplimientoGeneral, 1) }}%</div></td>
        </tr>
    </tbody>
</table>

<div class="section-title">Resumen por departamento</div>
@if($departamentos->isEmpty())
    <div class="empty">No hay entregas ni asignaciones para los filtros seleccionados.</div>
@else
    <table class="table">
        <thead>
            <tr>
                <th>Departamento</th>
                <th class="num">Carteros</th>
                <th class="num">Asignados</th>
                <th class="num">Entrega física</th>
                <th class="num">Ventanilla</th>
                <th class="num">Total entregados</th>
                <th class="num">Promedio diario</th>
                <th class="num">Pendientes</th>
                <th class="num">Cumplimiento</th>
            </tr>
        </thead>
        <tbody>
            @foreach($departamentos as $departamento)
                @php
                    $nombreDepartamento = mb_convert_case(mb_strtolower($departamento['departamento'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                @endphp
                <tr>
                    <td>{{ $nombreDepartamento }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['cantidad_carteros']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['total_asignados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['total_cartero_entregados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['total_ventanilla']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['total_entregados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['promedio_diario'], 2) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['pendientes_asignados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['cumplimiento'], 1) }}%</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>TOTAL GENERAL</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($carteros->count()) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($totalAsignados) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($totalFisica) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($totalVentanilla) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($totalEntregados) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($promedioDiarioGeneral ?? 0, 2) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($totalPendientes) }}</td>
                <td class="num">{{ \App\Support\BolivianNumber::format($cumplimientoGeneral, 1) }}%</td>
            </tr>
        </tbody>
    </table>
@endif

@foreach($departamentos as $departamento)
    @php
        $nombreDepartamento = mb_convert_case(mb_strtolower($departamento['departamento'], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $carterosDepartamento = collect($departamento['carteros']);
    @endphp
    <div class="dept-page">
        <table class="dept-head">
            <tr>
                <td>
                    <div class="eyebrow" style="color:#66778b">Detalle departamental</div>
                    <div class="dept-name">{{ $nombreDepartamento }}</div>
                    <div class="dept-subtitle">{{ \App\Support\BolivianNumber::format($departamento['cantidad_carteros']) }} carteros | {{ $rangoLabel ?? 'Todo el historial' }}</div>
                </td>
            </tr>
        </table>

        <table class="dept-kpis">
            <tbody>
                <tr>
                    <td><div class="kpi-label">Asignados</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($departamento['total_asignados']) }}</div></td>
                    <td><div class="kpi-label">Entrega física</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($departamento['total_cartero_entregados']) }}</div></td>
                </tr>
                <tr>
                    <td><div class="kpi-label">Ventanilla</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($departamento['total_ventanilla']) }}</div></td>
                    <td><div class="kpi-label">Total entregados</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($departamento['total_entregados']) }}</div></td>
                </tr>
                <tr>
                    <td><div class="kpi-label">Promedio diario</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($departamento['promedio_diario'], 2) }}</div></td>
                    <td><div class="kpi-label">Pendientes</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($departamento['pendientes_asignados']) }}</div></td>
                </tr>
                <tr>
                    <td><div class="kpi-label">Cumplimiento</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($departamento['cumplimiento'], 1) }}%</div></td>
                    <td><div class="kpi-label">Días considerados (Lun-Sáb)</div><div class="kpi-value">{{ \App\Support\BolivianNumber::format($diasLaborables ?? 0) }}</div></td>
                </tr>
            </tbody>
        </table>

        <div class="section-title">Rendimiento por cartero</div>
        <table class="table courier-table">
            <thead>
                <tr>
                    <th class="center">#</th>
                    <th>Cartero</th>
                    <th class="num">Asignados</th>
                    <th class="num">Entrega física</th>
                    <th class="num">Ventanilla</th>
                    <th class="num">Total entregados</th>
                    <th class="num">Promedio diario</th>
                    <th class="num">Pendientes</th>
                    <th class="num">Cumplimiento</th>
                </tr>
            </thead>
            <tbody>
                @foreach($carterosDepartamento as $item)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td class="courier-name">{{ $item->name }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->total_asignados) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->total_cartero_entregados) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->total_ventanilla) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->total_entregados) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((float) $item->promedio_diario, 2) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((int) $item->pendientes_asignados) }}</td>
                        <td class="num">{{ \App\Support\BolivianNumber::format((float) $item->cumplimiento_asignados, 1) }}%</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2">TOTAL {{ strtoupper($departamento['departamento']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['total_asignados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['total_cartero_entregados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['total_ventanilla']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['total_entregados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['promedio_diario'], 2) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['pendientes_asignados']) }}</td>
                    <td class="num">{{ \App\Support\BolivianNumber::format($departamento['cumplimiento'], 1) }}%</td>
                </tr>
            </tbody>
        </table>
        <div class="dept-subtitle">El cumplimiento incluye las entregas por cartero y ventanilla; los pendientes se suman por cartero para que los excedentes de uno no oculten pendientes de otro. El promedio diario considera {{ \App\Support\BolivianNumber::format($diasLaborables ?? 0) }} días de lunes a sábado.</div>
    </div>
@endforeach

<div class="footer"><span class="footer-left">BOLIPOST | Reporte ejecutivo de entregas | Promedio: lunes a sábado, sin domingos</span></div>
</body>
</html>

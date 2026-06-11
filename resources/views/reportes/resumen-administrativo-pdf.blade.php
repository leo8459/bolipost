<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen ejecutivo de paquetes</title>
    <style>
        @page { margin: 18px; }
        body { font-family: DejaVu Sans, Verdana, sans-serif; color: #10233f; font-size: 10px; }
        .cover { border: 2px solid #20539a; border-radius: 14px; padding: 24px 28px; margin-bottom: 14px; background: #f8fbff; }
        .brand { width: 100%; border-collapse: collapse; }
        .brand td { vertical-align: middle; }
        .logo { width: 92px; height: auto; }
        .eyebrow { color: #6b7280; font-size: 9px; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }
        h1 { margin: 4px 0 0; font-size: 28px; color: #20539a; line-height: 1.1; }
        .meta { margin-top: 6px; color: #475569; font-size: 10px; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 10px -8px 4px; }
        .kpis td { width: 25%; border: 1px solid #d5e2f2; border-radius: 10px; padding: 12px; background: #ffffff; }
        .k { color: #64748b; font-size: 8px; text-transform: uppercase; font-weight: 700; }
        .v { margin-top: 4px; color: #10233f; font-size: 20px; font-weight: 800; }
        .note { margin-top: 4px; color: #9a5b00; font-size: 7px; font-weight: 700; line-height: 1.35; }
        .department-summary { width: 100%; border-collapse: separate; border-spacing: 8px; margin: 4px -8px 10px; }
        .department-summary td { width: 50%; border: 1px solid #cbdcf2; border-radius: 12px; padding: 13px 14px; background: #ffffff; }
        .department-summary .label { color: #64748b; font-size: 8px; font-weight: 800; text-transform: uppercase; }
        .department-summary .name { margin-top: 4px; color: #20539a; font-size: 17px; font-weight: 900; }
        .department-summary .count { margin-top: 3px; color: #10233f; font-size: 11px; font-weight: 800; }
        .section-title {
            margin: 14px 0 7px;
            padding: 7px 10px;
            border-left: 8px solid #fecc36;
            border-top: 1px solid #cbdcf2;
            border-bottom: 1px solid #cbdcf2;
            background: #eef5ff;
            color: #10233f;
            font-size: 13px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .35px;
        }
        .efficiency-table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        .efficiency-table th { background: #eef5ff; color: #20539a; border: 1px solid #cbdcf2; padding: 6px; font-size: 7.5px; text-transform: uppercase; }
        .efficiency-table td { border: 1px solid #d8e3f1; padding: 6px; font-size: 8.5px; }
        .efficiency-table .avg { color: #177245; font-weight: 900; }
        .user-card { width: 100%; border-collapse: collapse; margin-bottom: 8px; page-break-inside: avoid; border: 1px solid #cbd5e1; }
        .user-card td { padding: 0; vertical-align: top; }
        .user-rank { width: 34px; background: #20539a; color: #ffffff; text-align: center; font-size: 13px; font-weight: 900; padding-top: 14px !important; }
        .user-main { padding: 10px 12px !important; }
        .user-name { color: #10233f; font-size: 13px; font-weight: 900; text-transform: uppercase; }
        .user-meta { margin-top: 3px; color: #64748b; font-size: 8.5px; font-weight: 700; text-transform: uppercase; }
        .line-block { margin-top: 8px; border-top: 1px solid #e2e8f0; padding-top: 7px; }
        .line-label { color: #20539a; font-size: 8px; font-weight: 900; text-transform: uppercase; }
        .line-value { margin-top: 2px; color: #10233f; font-size: 9px; line-height: 1.45; }
        .user-metrics { width: 210px; padding: 8px !important; background: #f8fbff; border-left: 1px solid #cbd5e1; }
        .mini-metric { border: 1px solid #d8e3f1; background: #ffffff; border-radius: 8px; padding: 6px 8px; margin-bottom: 6px; }
        .mini-label { color: #64748b; font-size: 7.2px; font-weight: 900; text-transform: uppercase; }
        .mini-value { margin-top: 2px; color: #10233f; font-size: 13px; font-weight: 900; }
        .num { text-align: right; }
        .footer { margin-top: 10px; text-align: right; color: #64748b; font-size: 8px; }
    </style>
</head>
<body>
@php
    $logoPath = public_path('images/AGBClogo1.png');
    $logoData = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;
    $admin = $administrativeSummary ?? [];
    $ranking = collect($admin['ranking'] ?? []);
    $eficienciaServicios = collect($admin['eficiencia_servicios'] ?? []);
    $pesoPorModulo = collect($admin['peso_por_modulo'] ?? []);
    $ventanillaPorModulo = collect($admin['ventanilla_por_modulo'] ?? []);
    $topVentanilla = $admin['top_ventanilla'] ?? null;
    $entregasVentanillaTop = collect($admin['entregas_ventanilla_top'] ?? []);
    $entregasCarteroTop = collect($admin['entregas_cartero_top'] ?? []);
    $malencaminados = $admin['malencaminados'] ?? [];
    $malencaminadosPorModulo = collect($malencaminados['por_modulo'] ?? []);
    $ultimosMalencaminados = collect($malencaminados['ultimos'] ?? []);
    $topOrigen = $admin['top_origen'] ?? null;
    $topDestino = $admin['top_destino'] ?? null;
    $rankingOrigenes = collect($admin['ranking_origenes'] ?? []);
    $rankingDestinos = collect($admin['ranking_destinos'] ?? []);
    $departamentoOrigenSeleccionado = $departamentoOrigen ?? '';
    $departamentoDestinoSeleccionado = $departamentoDestino ?? ($departamento ?? '');
    $mesesSeleccionadosTexto = !empty($selectedMonthLabels ?? []) ? implode(', ', $selectedMonthLabels) : '';
@endphp

<div class="cover">
    <table class="brand">
        <tr>
            <td width="115">
                @if($logoData)
                    <img src="{{ $logoData }}" class="logo" alt="Correos de Bolivia">
                @endif
            </td>
            <td>
                <div class="eyebrow">Reporte ejecutivo de paquetes</div>
                <h1>Resumen ejecutivo</h1>
                <div class="meta">Generado: {{ now()->format('d/m/Y H:i') }} | Rango: {{ $mesesSeleccionadosTexto !== '' ? 'Meses: ' . $mesesSeleccionadosTexto : (($from ?: 'inicio') . ' - ' . ($to ?: 'actualidad')) }}</div>
                <div class="meta">Filtros: {{ $search !== '' ? 'Busqueda "' . $search . '"' : 'Sin busqueda' }}{{ $departamentoOrigenSeleccionado ? ' | Origen ' . $departamentoOrigenSeleccionado : '' }}{{ $departamentoDestinoSeleccionado ? ' | Destino ' . $departamentoDestinoSeleccionado : '' }}</div>
            </td>
        </tr>
    </table>

    <table class="kpis">
        <tr>
            <td><div class="k">Paquetes generados</div><div class="v">{{ number_format($admin['total_admisiones'] ?? 0) }}</div></td>
            <td><div class="k">Usuarios activos</div><div class="v">{{ number_format($admin['usuarios_activos'] ?? 0) }}</div></td>
            <td><div class="k">Peso total</div><div class="v">{{ number_format((float) ($admin['peso_total'] ?? 0), 3) }}</div></td>
            <td><div class="k">Costo total Bs</div><div class="v">{{ number_format((float) ($admin['costo_total'] ?? 0), 2) }}</div><div class="note">Contratos no sumados por tema tarifario.</div></td>
        </tr>
    </table>
</div>

<table class="department-summary">
    <tr>
        <td>
            <div class="label">Departamento que genero mas</div>
            <div class="name">{{ $topOrigen['nombre'] ?? 'SIN ORIGEN' }}</div>
            <div class="count">{{ number_format($topOrigen['total'] ?? 0) }} paquetes registrados en los 4 modulos</div>
        </td>
        <td>
            <div class="label">Departamento que recibio mas como destino</div>
            <div class="name">{{ $topDestino['nombre'] ?? 'SIN DESTINO' }}</div>
            <div class="count">{{ number_format($topDestino['total'] ?? 0) }} paquetes recibidos</div>
        </td>
    </tr>
</table>

<div class="section-title">Peso total por servicio</div>
<table class="efficiency-table">
    <thead>
        <tr>
            <th>Servicio</th>
            <th width="18%">Paquetes</th>
            <th width="20%">Peso total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($pesoPorModulo as $pesoModulo)
            <tr>
                <td><strong>{{ $pesoModulo['servicio'] }}</strong></td>
                <td class="num">{{ number_format((int) $pesoModulo['total']) }}</td>
                <td class="num">{{ number_format((float) $pesoModulo['peso'], 3) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">Sin peso registrado para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Paquetes en ventanilla/almacen por servicio</div>
<table class="efficiency-table">
    <thead>
        <tr>
            <th>Servicio</th>
            <th width="18%">En ventanilla/almacen</th>
            <th width="20%">Peso total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong>Mayor en ventanilla/almacen: {{ $topVentanilla['servicio'] ?? 'SIN DATOS' }}</strong></td>
            <td class="num"><strong>{{ number_format((int) ($topVentanilla['total'] ?? 0)) }}</strong></td>
            <td class="num"><strong>{{ number_format((float) ($topVentanilla['peso'] ?? 0), 3) }}</strong></td>
        </tr>
        @forelse($ventanillaPorModulo as $ventanillaModulo)
            <tr>
                <td><strong>{{ $ventanillaModulo['servicio'] }}</strong></td>
                <td class="num">{{ number_format((int) $ventanillaModulo['total']) }}</td>
                <td class="num">{{ number_format((float) $ventanillaModulo['peso'], 3) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">Sin paquetes en ventanilla para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table width="100%" style="border-collapse: separate; border-spacing: 8px; margin: 4px -8px 10px;">
    <tr>
        <td width="50%" style="vertical-align: top;">
            <div class="section-title">Top entregas por ventanilla</div>
            <table class="efficiency-table">
                <thead>
                    <tr>
                        <th width="8%">#</th>
                        <th>Entrego</th>
                        <th width="30%">Servicios</th>
                        <th width="16%">Entregas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entregasVentanillaTop as $entregaRow)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $entregaRow['usuario'] }}</strong></td>
                            <td>{{ $entregaRow['servicio'] }}</td>
                            <td class="num">{{ number_format((int) $entregaRow['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Sin entregas por ventanilla para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
        <td width="50%" style="vertical-align: top;">
            <div class="section-title">Top entregas por cartero</div>
            <table class="efficiency-table">
                <thead>
                    <tr>
                        <th width="8%">#</th>
                        <th>Entrego</th>
                        <th width="30%">Servicios</th>
                        <th width="16%">Entregas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entregasCarteroTop as $entregaRow)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $entregaRow['usuario'] }}</strong></td>
                            <td>{{ $entregaRow['servicio'] }}</td>
                            <td class="num">{{ number_format((int) $entregaRow['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">Sin entregas por cartero para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>

<div class="section-title">Malencaminados con cambio de destino</div>
<table class="efficiency-table">
    <thead>
        <tr>
            <th>Servicio</th>
            <th width="18%">Cambios</th>
            <th width="22%">Malencaminamientos</th>
        </tr>
    </thead>
    <tbody>
        @forelse($malencaminadosPorModulo as $malModulo)
            <tr>
                <td><strong>{{ $malModulo['servicio'] }}</strong></td>
                <td class="num">{{ number_format((int) $malModulo['total']) }}</td>
                <td class="num">{{ number_format((int) $malModulo['malencaminamientos']) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">Sin malencaminados para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table class="efficiency-table">
    <thead>
        <tr>
            <th width="13%">Fecha</th>
            <th width="15%">Codigo</th>
            <th width="14%">Servicio</th>
            <th width="16%">Origen</th>
            <th>Destino anterior</th>
            <th>Destino nuevo</th>
        </tr>
    </thead>
    <tbody>
        @forelse($ultimosMalencaminados as $malRow)
            <tr>
                <td>{{ $malRow['created_at'] }}</td>
                <td><strong>{{ $malRow['codigo'] }}</strong></td>
                <td>{{ $malRow['servicio'] }}</td>
                <td>{{ $malRow['departamento_origen'] }}</td>
                <td>{{ $malRow['destino_anterior'] }}</td>
                <td><strong>{{ $malRow['destino_nuevo'] }}</strong></td>
            </tr>
        @empty
            <tr>
                <td colspan="6">Sin cambios malencaminados para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table width="100%" style="border-collapse: separate; border-spacing: 8px; margin: 4px -8px 10px;">
    <tr>
        <td width="50%" style="vertical-align: top;">
            <div class="section-title">Top origenes que registran mas</div>
            <table class="efficiency-table">
                <thead>
                    <tr>
                        <th width="8%">#</th>
                        <th>Origen</th>
                        <th width="22%">Paquetes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rankingOrigenes as $origenRow)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $origenRow['nombre'] }}</strong></td>
                            <td class="num">{{ number_format((int) $origenRow['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Sin origenes para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
        <td width="50%" style="vertical-align: top;">
            <div class="section-title">Top destinos que reciben mas</div>
            <table class="efficiency-table">
                <thead>
                    <tr>
                        <th width="8%">#</th>
                        <th>Destino</th>
                        <th width="22%">Paquetes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rankingDestinos as $destinoRow)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td><strong>{{ $destinoRow['nombre'] }}</strong></td>
                            <td class="num">{{ number_format((int) $destinoRow['total']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">Sin destinos para los filtros seleccionados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </td>
    </tr>
</table>

<div class="section-title">Servicios mas eficientes en ventanilla: mejor a peor</div>
<table class="efficiency-table">
    <thead>
        <tr>
            <th width="4%">#</th>
            <th>Servicio</th>
            <th width="13%">Entregados</th>
            <th width="16%">Promedio</th>
            <th width="16%">Mejor tiempo</th>
            <th width="16%">Mayor tiempo</th>
        </tr>
    </thead>
    <tbody>
        @forelse($eficienciaServicios as $servicioRow)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $servicioRow['servicio'] }}</strong></td>
                <td class="num">{{ number_format((int) $servicioRow['total']) }}</td>
                <td class="num avg">{{ $servicioRow['promedio'] }}</td>
                <td class="num">{{ $servicioRow['mejor_tiempo'] }}</td>
                <td class="num">{{ $servicioRow['mayor_tiempo'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">Sin paquetes entregados para calcular eficiencia.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<div class="section-title">Listado ejecutivo: de quien genero mas a quien genero menos</div>
@forelse($ranking as $item)
    <table class="user-card">
        <tr>
            <td class="user-rank">{{ $loop->iteration }}</td>
            <td class="user-main">
                <div class="user-name">{{ $item['usuario'] }}</div>
                <div class="user-meta">Registro: {{ $item['usuario'] }} | Regional: {{ $item['regional'] }} | Origen: {{ $item['origen'] }}</div>

                <div class="line-block">
                    <div class="line-label">Servicios generados</div>
                    <div class="line-value">{{ $item['servicio'] }}</div>
                </div>

                <div class="line-block">
                    <div class="line-label">Destinos recibidos</div>
                    <div class="line-value">{{ $item['destino'] }}</div>
                </div>

                <div class="line-block">
                    <div class="line-label">Entregado por</div>
                    <div class="line-value">{{ $item['entregado_por'] }}</div>
                </div>
            </td>
            <td class="user-metrics">
                <div class="mini-metric">
                    <div class="mini-label">Paquetes</div>
                    <div class="mini-value">{{ number_format($item['total']) }}</div>
                </div>
                <div class="mini-metric">
                    <div class="mini-label">Peso total</div>
                    <div class="mini-value">{{ number_format((float) $item['peso'], 3) }}</div>
                </div>
                <div class="mini-metric" style="margin-bottom:0;">
                    <div class="mini-label">Costo Bs</div>
                    <div class="mini-value">{{ number_format((float) $item['precio'], 2) }}</div>
                </div>
            </td>
        </tr>
    </table>
@empty
    <table class="user-card">
        <tr>
            <td class="user-main">Sin paquetes para los filtros seleccionados.</td>
        </tr>
    </table>
@endforelse

<div class="footer">Correos de Bolivia | Resumen ejecutivo de paquetes</div>
</body>
</html>

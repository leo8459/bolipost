@extends('adminlte::page')

@section('title', 'Entregas')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="m-0">Entregas</h1>
            <div class="text-muted">Ranking operativo por cartero y tipo de servicio.</div>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Volver al dashboard</a>
    </div>
@stop

@section('content')
    <style>
        .entregas-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .08);
            overflow: hidden;
        }

        .entregas-page {
            padding-bottom: 90px;
        }

        .entregas-filter {
            border: 1px solid #dbe3ef;
            border-radius: 12px;
            background: #f8fafc;
        }

        .entregas-filter label {
            color: #1f3d6d;
            font-weight: 800;
        }

        .entregas-summary {
            display: grid;
            grid-template-columns: repeat(6, minmax(140px, 1fr));
            gap: 10px;
        }

        .entregas-kpi {
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            padding: 9px 12px;
            background: #fff;
            border-top: 3px solid #1f5fae;
        }

        .entregas-kpi span {
            display: block;
            color: #64748b;
            font-size: .78rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .entregas-kpi strong {
            color: #0f2851;
            font-size: 1.25rem;
        }

        .excel-wrap {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
            background: #fff;
        }

        .excel-table {
            width: 100%;
            min-width: 0;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
            table-layout: fixed;
        }

        .excel-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            border-right: 1px solid #b9c7d8;
            border-bottom: 2px solid #8fa4bf;
            background: #e8f0fb;
            color: #123b70;
            font-size: .68rem;
            font-weight: 900;
            text-transform: uppercase;
            line-height: 1.05;
            padding: .48rem .28rem;
            text-align: center;
            white-space: normal;
        }

        .excel-table tbody td {
            border-right: 1px solid #d7e0ec;
            border-bottom: 1px solid #d7e0ec;
            background: #fff;
            vertical-align: middle;
            padding: .4rem .34rem;
            font-size: .84rem;
        }

        .excel-table tbody tr:nth-child(even) td {
            background: #fbfdff;
        }

        .excel-table tbody tr:hover td {
            background: #fff8db;
        }

        .excel-table th:first-child,
        .excel-table td:first-child {
            border-left: 0;
        }

        .excel-rank {
            display: inline-flex;
            width: 24px;
            height: 24px;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background: #edf2f7;
            color: #1e3a5f;
            font-weight: 900;
        }

        .excel-user {
            color: #0f172a;
            font-weight: 900;
            font-size: .86rem;
            line-height: 1.18;
            word-break: break-word;
        }

        .excel-city {
            display: inline-block;
            margin-top: 3px;
            color: #64748b;
            font-size: .7rem;
        }

        .metric-cell {
            color: #0f2851;
            font-variant-numeric: tabular-nums;
            font-weight: 900;
            text-align: right;
            white-space: nowrap;
        }

        .metric-cell.total {
            background: #eef6ff !important;
            color: #0b4c8c;
            font-size: .9rem;
        }

        .service-pill {
            display: inline-block;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            max-width: 100%;
            padding: 4px 8px;
            background: #f8fafc;
            color: #123b70;
            font-weight: 900;
            font-size: .74rem;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }

        .service-total {
            display: block;
            margin-top: 2px;
            color: #64748b;
            font-size: .72rem;
            font-weight: 700;
            text-align: center;
        }

        .fulfillment {
            min-width: 0;
        }

        .fulfillment-value {
            display: block;
            color: #0f2851;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            text-align: right;
        }

        .fulfillment-track {
            height: 5px;
            margin-top: 4px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .fulfillment-bar {
            display: block;
            height: 100%;
            border-radius: 999px;
            background: #1f5fae;
        }

        @media (max-width: 991.98px) {
            .entregas-summary {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }

            .excel-wrap {
                overflow-x: auto;
            }

            .excel-table {
                min-width: 980px;
            }
        }
    </style>

    @php
        $totalAsignados = (int) $entregadores->sum('total_asignados');
        $totalGeneral = (int) $entregadores->sum('total_entregados');
        $totalVentanilla = (int) $entregadores->sum('total_ventanilla');
        $totalCarteroEntregados = (int) $entregadores->sum('total_cartero_entregados');
        $totalPendientesAsignados = (int) $entregadores->sum('pendientes_asignados');
        $cumplimientoGeneral = $totalAsignados > 0 ? round(($totalCarteroEntregados * 100) / $totalAsignados, 1) : 0;
        $totalEms = (int) $entregadores->sum('ems');
        $totalContratos = (int) $entregadores->sum('contrato');
        $totalCarteros = (int) $entregadores->count();
    @endphp

    <div class="entregas-page">
    <div class="card entregas-card mb-3">
        <div class="card-body entregas-filter">
            <form method="GET" action="{{ route('entregas.index') }}" class="row">
                <div class="col-md-3 mb-3">
                    <label class="mb-1">Rango</label>
                    <select class="form-control" name="range">
                        @php
                            $rangeValue = old('range', request('range', $rangoKey ?? 'all'));
                        @endphp
                        <option value="all" @selected($rangeValue === 'all')>Todo el historial</option>
                        <option value="today" @selected($rangeValue === 'today')>Hoy</option>
                        <option value="7d" @selected($rangeValue === '7d')>Ultimos 7 dias</option>
                        <option value="month" @selected($rangeValue === 'month')>Mes actual</option>
                        <option value="custom" @selected($rangeValue === 'custom')>Personalizado</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="mb-1">Desde</label>
                    <input type="date" class="form-control" name="from" value="{{ request('from', $rangoDesde ?? '') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="mb-1">Hasta</label>
                    <input type="date" class="form-control" name="to" value="{{ request('to', $rangoHasta ?? '') }}">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="mb-1">Departamento del cartero</label>
                    <select class="form-control" name="cartero_departamento">
                        <option value="">Todos</option>
                        @foreach($departamentosDisponibles as $dep)
                            <option value="{{ $dep }}" @selected(($departamentoCartero ?? '') === $dep)>{{ $dep }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 mb-3">
                    <label class="mb-2 d-block">Modulos</label>
                    <div class="d-flex flex-wrap" style="gap: .65rem 1rem;">
                        @foreach($modulosDisponibles as $modKey => $modConfig)
                            <label class="mb-0" style="font-weight:600;">
                                <input type="checkbox" name="modules[]" value="{{ $modKey }}" @checked(in_array($modKey, $modulosSeleccionados, true))>
                                {{ $modConfig['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-12 d-flex flex-wrap align-items-center" style="gap: 8px;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('entregas.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                    <span class="text-muted ml-md-2">
                        {{ $rangoLabel ?? 'Todo el historial' }}
                        @if(($departamentoCartero ?? '') !== '')
                            | Carteros de {{ $departamentoCartero }}
                        @endif
                    </span>
                </div>
            </form>
        </div>
    </div>

    <div class="entregas-summary mb-3">
        <div class="entregas-kpi"><span>Total asignados</span><strong>{{ number_format($totalAsignados) }}</strong></div>
        <div class="entregas-kpi"><span>Entregados cartero</span><strong>{{ number_format($totalCarteroEntregados) }}</strong></div>
        <div class="entregas-kpi"><span>Entregados ventanilla</span><strong>{{ number_format($totalVentanilla) }}</strong></div>
        <div class="entregas-kpi"><span>Total entregados</span><strong>{{ number_format($totalGeneral) }}</strong></div>
        <div class="entregas-kpi"><span>Pendientes asignados</span><strong>{{ number_format($totalPendientesAsignados) }}</strong></div>
        <div class="entregas-kpi"><span>Cumplimiento</span><strong>{{ number_format($cumplimientoGeneral, 1) }}%</strong></div>
    </div>

    <div class="card entregas-card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <strong>Asignados vs entregados por cartero</strong>
                <span class="text-muted ml-2">({{ $rangoLabel ?? 'Todo el historial' }})</span>
            </div>
            <span class="text-muted small">Vista tipo Excel con separacion por columnas</span>
        </div>
        <div class="card-body p-3">
            <div class="excel-wrap">
                <table class="table table-sm excel-table">
                    <colgroup>
                        <col style="width:3%;">
                        <col style="width:13%;">
                        <col style="width:7%;">
                        <col style="width:8%;">
                        <col style="width:8%;">
                        <col style="width:8%;">
                        <col style="width:7%;">
                        <col style="width:9%;">
                        <col style="width:4.5%;">
                        <col style="width:6%;">
                        <col style="width:6%;">
                        <col style="width:6%;">
                        <col style="width:14.5%;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Usuario<br>Regional</th>
                            <th title="Asignados">Asig.</th>
                            <th title="Entregados por cartero">Cart.</th>
                            <th title="Entregados por ventanilla">Vent.</th>
                            <th title="Total entregados">Total</th>
                            <th title="Pendientes asignados">Pend.</th>
                            <th title="Cumplimiento">Cumpl.</th>
                            <th class="text-right">EMS</th>
                            <th class="text-right">Contr.</th>
                            <th class="text-right">Cert.</th>
                            <th class="text-right">Ord.</th>
                            <th>Servicio top</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entregadores as $item)
                            @php
                                $cumplimiento = (float) $item->cumplimiento_asignados;
                                $cumplimientoBar = min(100, max(0, $cumplimiento));
                            @endphp
                            <tr>
                                <td><span class="excel-rank">{{ $loop->iteration }}</span></td>
                                <td>
                                    <div class="excel-user">{{ $item->name }}</div>
                                    <span class="excel-city">{{ $item->ciudad ?: 'SIN DEPARTAMENTO' }}</span>
                                </td>
                                <td class="metric-cell total">{{ number_format((int) $item->total_asignados) }}</td>
                                <td class="metric-cell total">{{ number_format((int) $item->total_cartero_entregados) }}</td>
                                <td class="metric-cell total">{{ number_format((int) $item->total_ventanilla) }}</td>
                                <td class="metric-cell total">{{ number_format((int) $item->total_entregados) }}</td>
                                <td class="metric-cell">{{ number_format((int) $item->pendientes_asignados) }}</td>
                                <td>
                                    <div class="fulfillment">
                                        <span class="fulfillment-value">{{ number_format($cumplimiento, 1) }}%</span>
                                        <span class="fulfillment-track">
                                            <span class="fulfillment-bar" style="width: {{ $cumplimientoBar }}%;"></span>
                                        </span>
                                    </div>
                                </td>
                                <td class="metric-cell">{{ number_format((int) $item->ems) }}</td>
                                <td class="metric-cell">{{ number_format((int) $item->contrato) }}</td>
                                <td class="metric-cell">{{ number_format((int) $item->certi) }}</td>
                                <td class="metric-cell">{{ number_format((int) $item->ordi) }}</td>
                                <td>
                                    <span class="service-pill" title="{{ $item->servicio_mas_entregado }}">{{ $item->servicio_mas_entregado }}</span>
                                    <span class="service-total">{{ number_format((int) $item->servicio_mas_entregado_total) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted py-4">No hay asignaciones ni entregas para el filtro seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>
@stop

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
            grid-template-columns: repeat(5, minmax(140px, 1fr));
            gap: 10px;
        }

        .entregas-kpi {
            border: 1px solid #dbe3ef;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fff;
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
            overflow: auto;
            background: #fff;
        }

        .excel-table {
            min-width: 1040px;
            margin: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .excel-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            border-right: 1px solid #b9c7d8;
            border-bottom: 2px solid #8fa4bf;
            background: #e8f0fb;
            color: #123b70;
            font-size: .78rem;
            font-weight: 900;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .excel-table tbody td {
            border-right: 1px solid #d7e0ec;
            border-bottom: 1px solid #d7e0ec;
            background: #fff;
            vertical-align: middle;
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
            width: 28px;
            height: 28px;
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
        }

        .excel-city {
            display: inline-block;
            margin-top: 3px;
            color: #64748b;
            font-size: .8rem;
        }

        .metric-cell {
            color: #0f2851;
            font-variant-numeric: tabular-nums;
            font-weight: 900;
            text-align: right;
        }

        .metric-cell.total {
            background: #eef6ff !important;
            color: #0b4c8c;
            font-size: 1rem;
        }

        .service-pill {
            display: inline-block;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 5px 10px;
            background: #f8fafc;
            color: #123b70;
            font-weight: 900;
        }

        @media (max-width: 991.98px) {
            .entregas-summary {
                grid-template-columns: repeat(2, minmax(140px, 1fr));
            }
        }
    </style>

    @php
        $totalAsignados = (int) $entregadores->sum('total_asignados');
        $totalGeneral = (int) $entregadores->sum('total_entregados');
        $totalPendientesAsignados = (int) $entregadores->sum('pendientes_asignados');
        $cumplimientoGeneral = $totalAsignados > 0 ? round(($totalGeneral * 100) / $totalAsignados, 1) : 0;
        $totalEms = (int) $entregadores->sum('ems');
        $totalContratos = (int) $entregadores->sum('contrato');
        $totalCarteros = (int) $entregadores->count();
    @endphp

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
        <div class="entregas-kpi"><span>Total entregados</span><strong>{{ number_format($totalGeneral) }}</strong></div>
        <div class="entregas-kpi"><span>Pendientes asignados</span><strong>{{ number_format($totalPendientesAsignados) }}</strong></div>
        <div class="entregas-kpi"><span>Cumplimiento</span><strong>{{ number_format($cumplimientoGeneral, 1) }}%</strong></div>
        <div class="entregas-kpi"><span>Carteros</span><strong>{{ number_format($totalCarteros) }}</strong></div>
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
                    <thead>
                        <tr>
                            <th style="width: 54px;">#</th>
                            <th>Usuario / Regional</th>
                            <th class="text-right">Asignados</th>
                            <th class="text-right">Entregados</th>
                            <th class="text-right">Pendientes</th>
                            <th class="text-right">Cumplimiento</th>
                            <th class="text-right">EMS</th>
                            <th class="text-right">Contratos</th>
                            <th class="text-right">Certificados</th>
                            <th class="text-right">Ordinarios</th>
                            <th>Servicio mas entregado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entregadores as $item)
                            <tr>
                                <td><span class="excel-rank">{{ $loop->iteration }}</span></td>
                                <td>
                                    <div class="excel-user">{{ $item->name }}</div>
                                    <span class="excel-city">{{ $item->ciudad ?: 'SIN DEPARTAMENTO' }}</span>
                                </td>
                                <td class="metric-cell total">{{ number_format((int) $item->total_asignados) }}</td>
                                <td class="metric-cell total">{{ number_format((int) $item->total_entregados) }}</td>
                                <td class="metric-cell">{{ number_format((int) $item->pendientes_asignados) }}</td>
                                <td class="metric-cell">{{ number_format((float) $item->cumplimiento_asignados, 1) }}%</td>
                                <td class="metric-cell">{{ number_format((int) $item->ems) }}</td>
                                <td class="metric-cell">{{ number_format((int) $item->contrato) }}</td>
                                <td class="metric-cell">{{ number_format((int) $item->certi) }}</td>
                                <td class="metric-cell">{{ number_format((int) $item->ordi) }}</td>
                                <td>
                                    <span class="service-pill">{{ $item->servicio_mas_entregado }}</span>
                                    <span class="text-muted ml-1">({{ number_format((int) $item->servicio_mas_entregado_total) }})</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No hay asignaciones ni entregas para el filtro seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop

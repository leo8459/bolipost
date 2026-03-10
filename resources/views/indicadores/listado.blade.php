@extends('adminlte::page')
@section('title', $pageTitle)
@section('template_title')
    {{ $pageTitle }}
@endsection

@section('content')
    <div class="area-contratos-wrap">
        <div class="card area-contratos-card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title mb-2 mb-md-0">{{ $modulo }} - {{ $filtro }}</h3>
                @if (!empty($showSla))
                    <div class="sla-summary-group mb-2 mb-md-0">
                        <span class="sla-summary-pill sla-summary-green">
                            En plazo: {{ number_format((int) ($slaResumen['correcto'] ?? 0)) }}
                        </span>
                        <span class="sla-summary-pill sla-summary-yellow">
                            Retraso: {{ number_format((int) ($slaResumen['retraso'] ?? 0)) }}
                        </span>
                        <span class="sla-summary-pill sla-summary-red">
                            Rezago: {{ number_format((int) ($slaResumen['rezago'] ?? 0)) }}
                        </span>
                    </div>
                @endif
                <span class="area-badge">Total: {{ $rows->total() }}</span>
            </div>
            <div class="card-body">
                @if ($isEntregados && !$estadoEntregadoDisponible)
                    <div class="alert alert-warning">
                        No existe el estado ENTREGADO en la tabla estados.
                    </div>
                @endif

                <form method="GET" action="{{ route($searchRouteName) }}" class="row mb-3">
                    <div class="col-md-10 mb-2 mb-md-0">
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Buscar por codigo, estado, destino, destinatario, empresa o usuario..."
                        >
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Buscar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Peso</th>
                                <th>Empresa</th>
                                <th>Usuario</th>
                                <th>Actualizado</th>
                                @if (!empty($showSla))
                                    <th>Tiempo (d/h)</th>
                                    <th>Situacion</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr @if(!empty($showSla) && !empty($row->sla_color_class)) class="{{ $row->sla_color_class }}-row" @endif>
                                    <td>{{ $row->codigo }}</td>
                                    <td>{{ $row->estado }}</td>
                                    <td>{{ $row->origen }}</td>
                                    <td>{{ $row->destino }}</td>
                                    <td>{{ $row->remitente }}</td>
                                    <td>{{ $row->destinatario }}</td>
                                    <td>{{ $row->peso }}</td>
                                    <td>{{ $row->empresa }}</td>
                                    <td>{{ $row->usuario }}</td>
                                    <td>
                                        {{ !empty($row->fecha_actualizacion) ? \Illuminate\Support\Carbon::parse($row->fecha_actualizacion)->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    @if (!empty($showSla))
                                        <td>
                                            {{ $row->sla_texto ?? '-' }}
                                            @if (!empty($row->sla_is_provincia))
                                                <small class="d-block text-muted">+1d provincia</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $estadoSlaTexto = match ($row->sla_color ?? '') {
                                                    'VERDE' => 'En plazo',
                                                    'AMARILLO' => 'Retraso',
                                                    'ROJO' => 'Rezago',
                                                    default => 'Sin datos',
                                                };
                                            @endphp
                                            <span class="sla-pill {{ $row->sla_color_class ?? 'sla-gray' }}">
                                                {{ $estadoSlaTexto }}
                                            </span>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ !empty($showSla) ? 12 : 10 }}" class="text-center py-4">
                                        No hay registros para este filtro.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-end">
                    {{ $rows->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    <style>
        .area-contratos-wrap {
            background: linear-gradient(180deg, #f8faff 0%, #f1f5fe 100%);
            border: 1px solid #e2e8f6;
            border-radius: 14px;
            padding: 14px;
        }

        .area-contratos-card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 12px 26px rgba(28, 45, 94, 0.1);
            overflow: hidden;
        }

        .area-contratos-card .card-header {
            background: linear-gradient(95deg, #20539A 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
            padding: 0.95rem 1.1rem;
        }

        .area-badge {
            background: rgba(185, 156, 70, 0.2);
            color: #3f3514;
            border: 1px solid rgba(185, 156, 70, 0.35);
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 700;
            padding: 0.28rem 0.6rem;
        }
        .sla-summary-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .sla-summary-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.28rem 0.55rem;
            font-size: 0.76rem;
            font-weight: 700;
            border: 1px solid transparent;
        }
        .sla-summary-green {
            background: #d1fae5;
            color: #065f46;
            border-color: #86efac;
        }
        .sla-summary-yellow {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }
        .sla-summary-red {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .area-contratos-card .table thead th {
            background: #edf1fb;
            color: #20539A;
            border-bottom: 1px solid #dbe2f2;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }

        .sla-pill {
            display: inline-block;
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 0.78rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .sla-green {
            background: #d1fae5;
            color: #065f46;
            border-color: #86efac;
        }

        .sla-yellow {
            background: #fef3c7;
            color: #92400e;
            border-color: #fde68a;
        }

        .sla-red {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .sla-gray {
            background: #e5e7eb;
            color: #374151;
            border-color: #d1d5db;
        }

        .sla-green-row td {
            background-color: #f0fdf4;
        }

        .sla-yellow-row td {
            background-color: #fffbeb;
        }

        .sla-red-row td {
            background-color: #fef2f2;
        }
    </style>
@endsection

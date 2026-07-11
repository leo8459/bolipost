@extends('adminlte::page')

@section('title', 'Performance')

@section('content_header')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h1 class="mb-1">Performance</h1>
            <small class="text-muted">Consulta tipo Correos de Bolivia para revisar eventos por origen, destino y estado operativo.</small>
        </div>
        <div class="mt-3 mt-lg-0 d-flex flex-wrap">
            <a href="{{ route('performance.export.excel', request()->query()) }}" class="btn btn-success mr-2 mb-2">
                <i class="fas fa-file-excel mr-1"></i> Exportar Excel
            </a>
            <a href="{{ route('performance.export.pdf', request()->query()) }}" class="btn btn-danger mb-2" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf mr-1"></i> Exportar PDF
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="performance-qcs">
        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header performance-header">
                <div>
                    <h3 class="mb-1">Búsqueda de eventos</h3>
                    <small>Filtra por año, origen, destino, servicio y evento para generar tu tabla.</small>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('performance.index') }}">
                    <div class="row">
                        <div class="col-lg-3 mb-2">
                            <label class="font-weight-bold">Buscar</label>
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control"
                                placeholder="Código, actor, evento, origen o destino">
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="font-weight-bold">Año desde</label>
                            <select name="from_year" class="form-control">
                                @foreach($yearOptions as $yearOption)
                                    <option value="{{ $yearOption }}" @selected((int) $filters['from_year'] === (int) $yearOption)>{{ $yearOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="font-weight-bold">Mes desde</label>
                            <select name="from_month" class="form-control">
                                @foreach($monthOptions as $monthValue => $monthLabel)
                                    <option value="{{ $monthValue }}" @selected((int) $filters['from_month'] === (int) $monthValue)>{{ $monthLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="font-weight-bold">Año hasta</label>
                            <select name="to_year" class="form-control">
                                @foreach($yearOptions as $yearOption)
                                    <option value="{{ $yearOption }}" @selected((int) $filters['to_year'] === (int) $yearOption)>{{ $yearOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-1 mb-2">
                            <label class="font-weight-bold">Mes hasta</label>
                            <select name="to_month" class="form-control">
                                @foreach($monthOptions as $monthValue => $monthLabel)
                                    <option value="{{ $monthValue }}" @selected((int) $filters['to_month'] === (int) $monthValue)>{{ $monthLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="font-weight-bold">Servicio</label>
                            <select name="servicio" class="form-control">
                                <option value="">Todos</option>
                                @foreach($serviceOptions as $serviceValue => $serviceLabel)
                                    <option value="{{ $serviceValue }}" @selected($filters['servicio'] === $serviceValue)>{{ $serviceLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 mb-2">
                            <label class="font-weight-bold">Evento</label>
                            <select name="evento_id" class="form-control">
                                <option value="0">Todos</option>
                                @foreach($eventOptions as $eventOption)
                                    <option value="{{ (int) $eventOption->id }}" @selected((int) $filters['evento_id'] === (int) $eventOption->id)>
                                        {{ $eventOption->nombre_evento }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4 mb-2">
                            <label class="font-weight-bold">Origen</label>
                            <select name="origen" class="form-control">
                                <option value="">Todos</option>
                                @foreach($originOptions as $originOption)
                                    <option value="{{ $originOption }}" @selected($filters['origen'] === $originOption)>{{ $originOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 mb-2">
                            <label class="font-weight-bold">Destino</label>
                            <select name="destino" class="form-control">
                                <option value="">Todos</option>
                                @foreach($destinationOptions as $destinationOption)
                                    <option value="{{ $destinationOption }}" @selected($filters['destino'] === $destinationOption)>{{ $destinationOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-2">
                            <label class="font-weight-bold">Filas detalle</label>
                            <select name="per_page" class="form-control">
                                @foreach([25, 50, 100, 150] as $pageSize)
                                    <option value="{{ $pageSize }}" @selected((int) $filters['per_page'] === $pageSize)>{{ $pageSize }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-2 d-flex align-items-end">
                            <div class="w-100 d-flex flex-wrap">
                                <button type="submit" class="btn btn-primary mr-2 mb-2 flex-fill">
                                    <i class="fas fa-search mr-1"></i> Buscar
                                </button>
                                <a href="{{ route('performance.index') }}" class="btn btn-outline-secondary mb-2 flex-fill">
                                    Limpiar
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="performance-filters-note">
                    @foreach($filterSummary as $label => $value)
                        <span class="performance-chip"><strong>{{ $label }}:</strong> {{ $value }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-lg-3 mb-2">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Registros encontrados</div>
                        <div class="performance-stat-value">{{ number_format($summary['total_registros']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 mb-2">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Orígenes visibles</div>
                        <div class="performance-stat-value">{{ number_format($summary['origenes']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 mb-2">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Destinos visibles</div>
                        <div class="performance-stat-value">{{ number_format($summary['destinos']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 mb-2">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Eventos en tabla</div>
                        <div class="performance-stat-value">{{ number_format($summary['eventos']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <section class="card border-0 shadow-sm mb-2">
            <div class="card-header performance-header">
                <div>
                    <h3 class="mb-1">Tabla consolidada</h3>
                    <small>Vista tipo Correos de Bolivia por origen, destino, año, mes y conteo de eventos.</small>
                </div>
            </div>
            @if(count($eventLegend) > 0)
                <div class="performance-legend-bar">
                    <details class="performance-legend-details">
                        <summary>Ver referencia de columnas</summary>
                        <div class="performance-legend-grid">
                            @foreach($eventLegend as $legendItem)
                                <span class="performance-legend-chip" title="{{ $legendItem['label'] }}">
                                    <strong>{{ $legendItem['key'] }}</strong>
                                    <span>{{ $legendItem['label'] }}</span>
                                </span>
                            @endforeach
                        </div>
                    </details>
                </div>
            @endif
            <div class="card-body p-0">
                <div class="table-responsive performance-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0 performance-matrix">
                        <thead>
                            <tr>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Año</th>
                                <th>Mes</th>
                                @forelse($eventColumns as $eventIndex => $eventColumn)
                                    <th class="performance-event-head">
                                        <span class="performance-event-key"
                                            data-toggle="tooltip"
                                            data-placement="top"
                                            data-trigger="hover click"
                                            data-html="true"
                                            title="{{ $eventColumn }}">
                                            {{ $eventLegend[$eventIndex]['key'] ?? $eventColumn }}
                                        </span>
                                    </th>
                                @empty
                                    <th>Sin eventos</th>
                                @endforelse
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($matrixRows as $matrixRow)
                                <tr>
                                    <td>{{ $matrixRow['origen'] }}</td>
                                    <td>{{ $matrixRow['destino'] }}</td>
                                    <td class="text-center">{{ $matrixRow['anio'] }}</td>
                                    <td class="text-center">{{ $matrixRow['mes_label'] }}</td>
                                    @foreach($eventColumns as $eventColumn)
                                        <td class="text-right">{{ number_format((int) ($matrixRow['counts'][$eventColumn] ?? 0)) }}</td>
                                    @endforeach
                                    <td class="text-right font-weight-bold">{{ number_format((int) $matrixRow['total']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 5 + count($eventColumns) }}" class="text-center text-muted py-4">
                                        No hay resultados para los filtros seleccionados.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if(count($matrixRows) > 0)
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right">Totales</th>
                                    @foreach($eventColumns as $eventColumn)
                                        <th class="text-right">{{ number_format((int) ($matrixTotals['events'][$eventColumn] ?? 0)) }}</th>
                                    @endforeach
                                    <th class="text-right">{{ number_format((int) ($matrixTotals['grand_total'] ?? 0)) }}</th>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </section>

        <div class="row">
            <div class="col-lg-4 mb-2">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Transiciones analizadas</div>
                        <div class="performance-stat-value">{{ number_format($transitionSummary['total_transiciones'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-2">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Promedio general entre eventos</div>
                        <div class="performance-stat-value">{{ number_format((float) ($transitionSummary['promedio_general_dias'] ?? 0), 2) }} días</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-2">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Rutas evento a evento</div>
                        <div class="performance-stat-value">{{ number_format($transitionSummary['rutas'] ?? 0) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <section class="card border-0 shadow-sm mb-2">
            <div class="card-header performance-header">
                <div>
                    <h3 class="mb-1">Promedio de días entre eventos</h3>
                    <small>Se calcula por código, tomando el siguiente evento cronológico y promediando los días transcurridos.</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive performance-table-wrap performance-transition-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0 performance-matrix">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Evento inicial</th>
                                <th>Evento siguiente</th>
                                <th class="text-right">Casos</th>
                                <th class="text-right">Promedio días</th>
                                <th class="text-right">Mínimo</th>
                                <th class="text-right">Máximo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transitionRows as $transitionRow)
                                <tr>
                                    <td>{{ $transitionRow['servicio'] }}</td>
                                    <td>{{ $transitionRow['origen'] }}</td>
                                    <td>{{ $transitionRow['destino'] }}</td>
                                    <td>{{ $transitionRow['evento_origen'] }}</td>
                                    <td>{{ $transitionRow['evento_destino'] }}</td>
                                    <td class="text-right">{{ number_format((int) $transitionRow['total_transiciones']) }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format((float) $transitionRow['promedio_dias'], 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $transitionRow['minimo_dias'], 2) }}</td>
                                    <td class="text-right">{{ number_format((float) $transitionRow['maximo_dias'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No hay suficientes eventos consecutivos para calcular promedios.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card border-0 shadow-sm performance-detail-card">
            <div class="card-header performance-header">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <h3 class="mb-1">Detalle de registros</h3>
                        <small>Eventos encontrados según la búsqueda actual.</small>
                    </div>
                    <button class="btn btn-light btn-sm performance-toggle" type="button" data-toggle="collapse" data-target="#performanceDetailBody" aria-expanded="false" aria-controls="performanceDetailBody">
                        Ver detalle
                    </button>
                </div>
            </div>
            <div id="performanceDetailBody" class="collapse">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Servicio</th>
                                    <th>Código</th>
                                    <th>Evento</th>
                                    <th>Origen</th>
                                    <th>Destino</th>
                                    <th>Actor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($details as $detail)
                                    <tr>
                                        <td>{{ \Illuminate\Support\Carbon::parse($detail->created_at)->format('d/m/Y H:i') }}</td>
                                        <td>{{ $detail->servicio }}</td>
                                        <td>{{ $detail->codigo }}</td>
                                        <td>{{ $detail->evento_nombre }}</td>
                                        <td>{{ $detail->origen }}</td>
                                        <td>{{ $detail->destino }}</td>
                                        <td>{{ $detail->actor_nombre }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No hay detalle disponible.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($details instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator || $details instanceof \Illuminate\Pagination\Paginator)
                    <div class="card-footer">
                        {{ $details->links() }}
                    </div>
                @endif
            </div>
        </section>
    </div>
@stop

@section('css')
    <style>
        .performance-header {
            background: linear-gradient(90deg, #0f4c81, #1f6aa6);
            color: #fff;
            border-bottom: 0;
            padding: 0.65rem 1rem;
        }

        .performance-header small,
        .performance-header h3 {
            color: #fff;
        }

        .performance-qcs .card-body {
            padding: 0.65rem 0.8rem;
        }

        .performance-qcs label {
            margin-bottom: 0.22rem;
            font-size: 0.78rem;
        }

        .performance-qcs .form-control {
            height: calc(1.72rem + 2px);
            padding: 0.14rem 0.45rem;
            font-size: 0.8rem;
        }

        .performance-qcs .btn {
            padding: 0.3rem 0.55rem;
            font-size: 0.8rem;
        }

        .performance-filters-note {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 0.15rem;
        }

        .performance-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.28rem 0.62rem;
            border-radius: 999px;
            background: #eef4ff;
            color: #20539a;
            font-size: 0.76rem;
        }

        .performance-stat {
            border-radius: 18px;
        }

        .performance-stat .card-body {
            padding: 0.55rem 0.85rem;
        }

        .performance-stat-label {
            color: #5b6d86;
            font-size: 0.72rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .performance-stat-value {
            color: #163b6d;
            font-size: 1.2rem;
            font-weight: 800;
            margin-top: 0.05rem;
            line-height: 1.05;
        }

        .performance-table-wrap {
            max-height: calc(100vh - 330px);
            min-height: 280px;
        }

        .performance-transition-wrap {
            max-height: 420px;
            min-height: 220px;
        }

        .performance-legend-bar {
            padding: 0.3rem 0.7rem 0;
            background: #f7faff;
            border-bottom: 1px solid #dbe6f5;
        }

        .performance-legend-details summary {
            cursor: pointer;
            color: #1c4f92;
            font-size: 0.72rem;
            font-weight: 700;
            outline: none;
        }

        .performance-legend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 0.3rem;
            padding: 0.45rem 0 0.35rem;
        }

        .performance-legend-chip {
            display: flex;
            align-items: center;
            gap: 0.42rem;
            padding: 0.22rem 0.38rem;
            border: 1px solid #d6e2f1;
            border-radius: 8px;
            background: #fff;
            font-size: 0.68rem;
            color: #27476c;
        }

        .performance-legend-chip strong {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            min-width: 1.35rem;
            height: 1.35rem;
            border-radius: 999px;
            background: #eaf2ff;
            color: #17498b;
            font-size: 0.68rem;
        }

        .performance-legend-chip span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .performance-matrix thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #eaf2fb;
            color: #163b6d;
            white-space: nowrap;
        }

        .performance-matrix td,
        .performance-matrix th {
            font-size: 0.68rem;
            vertical-align: middle;
            padding: 0.14rem 0.24rem;
        }

        .performance-matrix tfoot th {
            background: #f7f9fc;
        }

        .performance-event-head {
            text-align: center;
        }

        .performance-event-key {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.28rem;
            min-height: 1.28rem;
            border-radius: 999px;
            background: #eaf2ff;
            color: #17498b;
            font-weight: 700;
            cursor: help;
            line-height: 1;
            border: 1px solid #c8daf7;
            transition: all 0.15s ease;
        }

        .performance-event-key:hover,
        .performance-event-key:focus {
            background: #17498b;
            color: #fff;
            border-color: #17498b;
        }

        .performance-qcs .table-sm th,
        .performance-qcs .table-sm td {
            padding: 0.16rem 0.28rem;
            font-size: 0.69rem;
        }

        .performance-header h3 {
            font-size: 0.95rem;
        }

        .performance-header small {
            font-size: 0.74rem;
        }

        .performance-detail-card .card-footer {
            padding: 0.35rem 0.65rem;
        }

        .performance-toggle {
            white-space: nowrap;
        }

        .tooltip.show {
            opacity: 1;
        }

        .tooltip .tooltip-inner {
            max-width: 320px;
            padding: 0.45rem 0.6rem;
            background: #173f74;
            color: #fff;
            font-size: 0.76rem;
            line-height: 1.35;
            text-align: left;
            border-radius: 8px;
            box-shadow: 0 8px 18px rgba(15, 45, 82, 0.22);
        }

        .bs-tooltip-top .arrow::before,
        .bs-tooltip-auto[x-placement^="top"] .arrow::before {
            border-top-color: #173f74;
        }

        @media (min-width: 1200px) {
            .performance-qcs {
                zoom: 0.88;
            }
        }

        @media (min-width: 1500px) {
            .performance-qcs {
                zoom: 0.82;
            }
        }
    </style>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (window.jQuery && typeof window.jQuery.fn.tooltip === 'function') {
                window.jQuery('[data-toggle="tooltip"]').tooltip({
                    container: 'body',
                    trigger: 'hover focus click',
                    animation: false
                });
            }
        });
    </script>
@stop

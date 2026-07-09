@extends('adminlte::page')

@section('title', 'Performance')

@section('content_header')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h1 class="mb-1">Performance</h1>
            <small class="text-muted">Consulta tipo QCS para revisar eventos por origen, destino y estado operativo.</small>
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
                    <h3 class="mb-1">Busqueda de eventos</h3>
                    <small>Filtra por anio, origen, destino, servicio y evento para generar tu tabla.</small>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('performance.index') }}">
                    <div class="row">
                        <div class="col-lg-3 mb-3">
                            <label class="font-weight-bold">Buscar</label>
                            <input type="text" name="q" value="{{ $filters['q'] }}" class="form-control"
                                placeholder="Codigo, actor, evento, origen o destino">
                        </div>
                        <div class="col-lg-2 mb-3">
                            <label class="font-weight-bold">Anio desde</label>
                            <select name="from_year" class="form-control">
                                @foreach($yearOptions as $yearOption)
                                    <option value="{{ $yearOption }}" @selected((int) $filters['from_year'] === (int) $yearOption)>{{ $yearOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-3">
                            <label class="font-weight-bold">Mes desde</label>
                            <select name="from_month" class="form-control">
                                @foreach($monthOptions as $monthValue => $monthLabel)
                                    <option value="{{ $monthValue }}" @selected((int) $filters['from_month'] === (int) $monthValue)>{{ $monthLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-3">
                            <label class="font-weight-bold">Anio hasta</label>
                            <select name="to_year" class="form-control">
                                @foreach($yearOptions as $yearOption)
                                    <option value="{{ $yearOption }}" @selected((int) $filters['to_year'] === (int) $yearOption)>{{ $yearOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-1 mb-3">
                            <label class="font-weight-bold">Mes hasta</label>
                            <select name="to_month" class="form-control">
                                @foreach($monthOptions as $monthValue => $monthLabel)
                                    <option value="{{ $monthValue }}" @selected((int) $filters['to_month'] === (int) $monthValue)>{{ $monthLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-3">
                            <label class="font-weight-bold">Servicio</label>
                            <select name="servicio" class="form-control">
                                <option value="">Todos</option>
                                @foreach($serviceOptions as $serviceValue => $serviceLabel)
                                    <option value="{{ $serviceValue }}" @selected($filters['servicio'] === $serviceValue)>{{ $serviceLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-3 mb-3">
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
                        <div class="col-lg-4 mb-3">
                            <label class="font-weight-bold">Origen</label>
                            <select name="origen" class="form-control">
                                <option value="">Todos</option>
                                @foreach($originOptions as $originOption)
                                    <option value="{{ $originOption }}" @selected($filters['origen'] === $originOption)>{{ $originOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label class="font-weight-bold">Destino</label>
                            <select name="destino" class="form-control">
                                <option value="">Todos</option>
                                @foreach($destinationOptions as $destinationOption)
                                    <option value="{{ $destinationOption }}" @selected($filters['destino'] === $destinationOption)>{{ $destinationOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-3">
                            <label class="font-weight-bold">Filas detalle</label>
                            <select name="per_page" class="form-control">
                                @foreach([25, 50, 100, 150] as $pageSize)
                                    <option value="{{ $pageSize }}" @selected((int) $filters['per_page'] === $pageSize)>{{ $pageSize }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-2 mb-3 d-flex align-items-end">
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
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Registros encontrados</div>
                        <div class="performance-stat-value">{{ number_format($summary['total_registros']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Origenes visibles</div>
                        <div class="performance-stat-value">{{ number_format($summary['origenes']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Destinos visibles</div>
                        <div class="performance-stat-value">{{ number_format($summary['destinos']) }}</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 mb-4">
                <div class="card border-0 shadow-sm h-100 performance-stat">
                    <div class="card-body">
                        <div class="performance-stat-label">Eventos en tabla</div>
                        <div class="performance-stat-value">{{ number_format($summary['eventos']) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <section class="card border-0 shadow-sm mb-4">
            <div class="card-header performance-header">
                <div>
                    <h3 class="mb-1">Tabla consolidada</h3>
                    <small>Vista tipo QCS por origen, destino, anio, mes y conteo de eventos.</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive performance-table-wrap">
                    <table class="table table-sm table-bordered table-hover mb-0 performance-matrix">
                        <thead>
                            <tr>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Anio</th>
                                <th>Mes</th>
                                @forelse($eventColumns as $eventColumn)
                                    <th>{{ $eventColumn }}</th>
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

        <section class="card border-0 shadow-sm">
            <div class="card-header performance-header">
                <div>
                    <h3 class="mb-1">Detalle de registros</h3>
                    <small>Eventos encontrados segun la busqueda actual.</small>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Servicio</th>
                                <th>Codigo</th>
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
        </section>
    </div>
@stop

@section('css')
    <style>
        .performance-header {
            background: linear-gradient(90deg, #0f4c81, #1f6aa6);
            color: #fff;
            border-bottom: 0;
        }

        .performance-header small,
        .performance-header h3 {
            color: #fff;
        }

        .performance-filters-note {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 0.4rem;
        }

        .performance-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.45rem 0.8rem;
            border-radius: 999px;
            background: #eef4ff;
            color: #20539a;
            font-size: 0.82rem;
        }

        .performance-stat {
            border-radius: 18px;
        }

        .performance-stat-label {
            color: #5b6d86;
            font-size: 0.82rem;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.04em;
        }

        .performance-stat-value {
            color: #163b6d;
            font-size: 2rem;
            font-weight: 800;
            margin-top: 0.35rem;
        }

        .performance-table-wrap {
            max-height: 580px;
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
            font-size: 0.83rem;
            vertical-align: middle;
        }

        .performance-matrix tfoot th {
            background: #f7f9fc;
        }
    </style>
@stop

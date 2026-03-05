@extends('adminlte::page')

@section('title', 'Dashboard Corporativo')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Dashboard Corporativo</h1>
            <small class="text-muted">Panel administrativo de operaciones y entregas</small>
        </div>
        <div class="mt-2 mt-md-0 text-md-right">
            <div class="text-muted mb-2">
                <strong>Rango:</strong> {{ $rangoLabel }}
            </div>
            <div>
                <a href="{{ route('dashboard.export.pdf', request()->query()) }}" class="btn btn-danger btn-sm">
                    Exportar PDF
                </a>
            </div>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-filtro mb-3">
        <div class="card-header">
            <strong>Filtros y Vista</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard') }}">
                <input type="hidden" name="range" value="{{ $rangoKey }}">

                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <label class="d-block font-weight-bold">Módulos incluidos</label>
                        <div class="d-flex flex-wrap">
                            @foreach($modulosDisponibles as $key => $modulo)
                                <div class="custom-control custom-checkbox mr-4 mb-2">
                                    <input
                                        class="custom-control-input"
                                        type="checkbox"
                                        id="modulo_{{ $key }}"
                                        name="modules[]"
                                        value="{{ $key }}"
                                        {{ in_array($key, $modulosSeleccionados, true) ? 'checked' : '' }}
                                    >
                                    <label class="custom-control-label" for="modulo_{{ $key }}">
                                        {{ $modulo['label'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="row">
                            <div class="col-6">
                                <label for="from" class="font-weight-bold">Desde</label>
                                <input type="date" id="from" name="from" class="form-control" value="{{ $rangoDesde }}">
                            </div>
                            <div class="col-6">
                                <label for="to" class="font-weight-bold">Hasta</label>
                                <input type="date" id="to" name="to" class="form-control" value="{{ $rangoHasta }}">
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-2 mb-3">
                        <label for="group" class="font-weight-bold">Agrupar por</label>
                        <select id="group" name="group" class="form-control">
                            <option value="day" {{ $agrupacion === 'day' ? 'selected' : '' }}>Día</option>
                            <option value="week" {{ $agrupacion === 'week' ? 'selected' : '' }}>Semana</option>
                            <option value="month" {{ $agrupacion === 'month' ? 'selected' : '' }}>Mes</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center">
                    <button type="submit" class="btn btn-primary mr-2 mb-2">Aplicar filtros</button>
                    <button type="submit" name="range" value="today" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">Hoy</button>
                    <button type="submit" name="range" value="7d" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">7 días</button>
                    <button type="submit" name="range" value="30d" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">30 días</button>
                    <button type="submit" name="range" value="month" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">Mes actual</button>
                    <button type="submit" name="range" value="all" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">Todo historial</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-navy">
                <div class="metric-value">{{ number_format($totales['paquetes']) }}</div>
                <div class="metric-label">Registrados</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-green">
                <div class="metric-value">{{ number_format($totales['entregados']) }}</div>
                <div class="metric-label">Entregados ({{ number_format($totales['porcentaje_entrega'], 1) }}%)</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-orange">
                <div class="metric-value">{{ number_format($totales['pendientes']) }}</div>
                <div class="metric-label">Pendientes</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-blue">
                <div class="metric-value">Bs {{ number_format($totales['ingresos'], 2) }}</div>
                <div class="metric-label">Ingresos</div>
            </div>
        </div>
    </div>

    <div class="row mt-1">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini">
                <small>Registrados Hoy</small>
                <h4>{{ number_format($kpisPeriodo['registros']['dia']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini">
                <small>Registrados Semana</small>
                <h4>{{ number_format($kpisPeriodo['registros']['semana']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini">
                <small>Registrados Mes</small>
                <h4>{{ number_format($kpisPeriodo['registros']['mes']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini kpi-ok">
                <small>Entregados Hoy</small>
                <h4>{{ number_format($kpisPeriodo['entregas']['dia']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini kpi-ok">
                <small>Entregados Semana</small>
                <h4>{{ number_format($kpisPeriodo['entregas']['semana']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini kpi-ok">
                <small>Entregados Mes</small>
                <h4>{{ number_format($kpisPeriodo['entregas']['mes']) }}</h4>
            </div>
        </div>
    </div>

    <div class="row mt-1">
        <div class="col-md-6">
            <div class="alert alert-warning mb-2">
                <strong>Entregados con retraso:</strong> {{ number_format($totales['atrasados']) }}
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert alert-danger mb-2">
                <strong>Ya en rezago:</strong> {{ number_format($totales['rezago']) }}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <strong>Distribución por módulo</strong>
                </div>
                <div class="card-body">
                    <canvas id="chartModulos" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <strong>Estado operativo por módulo</strong>
                </div>
                <div class="card-body">
                    <canvas id="chartEstados" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Tendencia de Registros vs Entregas</strong>
            <span class="text-muted">{{ $rangoTendenciaLabel }} | Agrupación: {{ strtoupper($agrupacion) }}</span>
        </div>
        <div class="card-body">
            <canvas id="chartTendencia" height="110"></canvas>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <strong>Resumen ejecutivo por módulo</strong>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Módulo</th>
                                <th class="text-right">Registrados</th>
                                <th class="text-right">Entregados</th>
                                <th class="text-right">Pendientes</th>
                                <th class="text-right">Con retraso</th>
                                <th class="text-right">Rezago</th>
                                <th class="text-right">Tasa entrega</th>
                                <th class="text-right">Peso</th>
                                <th class="text-right">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resumenPorModulo as $fila)
                                <tr>
                                    <td><strong>{{ $fila['label'] }}</strong></td>
                                    <td class="text-right">{{ number_format($fila['total']) }}</td>
                                    <td class="text-right text-success">{{ number_format($fila['entregados']) }}</td>
                                    <td class="text-right">{{ number_format($fila['pendientes']) }}</td>
                                    <td class="text-right text-warning">{{ number_format($fila['atrasados']) }}</td>
                                    <td class="text-right text-danger">{{ number_format($fila['rezago']) }}</td>
                                    <td class="text-right">{{ number_format($fila['tasa_entrega'], 1) }}%</td>
                                    <td class="text-right">{{ number_format($fila['peso_total'], 3) }}</td>
                                    <td class="text-right">Bs {{ number_format($fila['ingresos'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No hay datos para los filtros seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Top Entregadores</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th class="text-right">Entregas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rankingEntregadores as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->name }}</strong><br>
                                        <small class="text-muted">
                                            E:{{ (int) $item->ems }} C:{{ (int) $item->contrato }} Ce:{{ (int) $item->certi }} O:{{ (int) $item->ordi }}
                                        </small>
                                    </td>
                                    <td class="text-right">{{ number_format((int) $item->total_entregados) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Sin datos</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong>Top Registradores</strong>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th class="text-right">Registros</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rankingRegistradores as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item->name }}</strong><br>
                                        <small class="text-muted">
                                            E:{{ (int) $item->ems }} C:{{ (int) $item->contrato }} Ce:{{ (int) $item->certi }} O:{{ (int) $item->ordi }}
                                        </small>
                                    </td>
                                    <td class="text-right">{{ number_format((int) $item->total_registrados) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-4">Sin datos</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if(!$estadoEntregadoDisponible || !$estadoRezagoDisponible)
        <div class="alert alert-warning">
            <strong>Advertencia de configuración:</strong>
            @if(!$estadoEntregadoDisponible)
                No existe estado <strong>ENTREGADO</strong> en catálogo.
            @endif
            @if(!$estadoRezagoDisponible)
                No existe estado <strong>REZAGO</strong> en catálogo.
            @endif
        </div>
    @endif

    @include('footer')
@stop

@section('css')
    <style>
        .card-filtro {
            border-top: 3px solid #20539a;
        }
        .metric-card {
            border-radius: 12px;
            color: #fff;
            padding: 18px 20px;
            margin-bottom: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, .10);
        }
        .metric-value {
            font-size: 1.65rem;
            font-weight: 700;
            line-height: 1.1;
        }
        .metric-label {
            margin-top: 4px;
            opacity: .95;
            font-weight: 500;
            font-size: .9rem;
        }
        .metric-navy { background: linear-gradient(135deg, #1f3f77, #2f5da8); }
        .metric-green { background: linear-gradient(135deg, #1b7e42, #28a745); }
        .metric-orange { background: linear-gradient(135deg, #bb6d00, #f39c12); }
        .metric-blue { background: linear-gradient(135deg, #006d9a, #17a2b8); }

        .kpi-mini {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 12px;
            background: #fff;
            margin-bottom: 10px;
            min-height: 78px;
        }
        .kpi-mini h4 {
            margin: 6px 0 0;
            font-weight: 700;
            color: #1f3f77;
        }
        .kpi-mini small {
            color: #6c757d;
            font-weight: 600;
        }
        .kpi-ok h4 {
            color: #1b7e42;
        }
    </style>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const chartModulosData = @json($chartModulos);
        const chartEstadosData = @json($chartEstados);
        const trendLabels = @json($trendLabels);
        const trendSeries = @json($trendSeries);

        new Chart(document.getElementById('chartModulos'), {
            type: 'doughnut',
            data: {
                labels: chartModulosData.labels,
                datasets: [{
                    data: chartModulosData.totales,
                    backgroundColor: ['#20539a', '#28a745', '#f39c12', '#17a2b8'],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        new Chart(document.getElementById('chartEstados'), {
            type: 'bar',
            data: {
                labels: chartEstadosData.labels,
                datasets: [
                    { label: 'Entregados', data: chartEstadosData.entregados, backgroundColor: '#28a745' },
                    { label: 'Pendientes', data: chartEstadosData.pendientes, backgroundColor: '#f39c12' },
                    { label: 'Rezago', data: chartEstadosData.rezago, backgroundColor: '#dc3545' },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });

        new Chart(document.getElementById('chartTendencia'), {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Registrados',
                        data: trendSeries.registros,
                        borderColor: '#20539a',
                        backgroundColor: '#20539a',
                        tension: .25,
                        fill: false
                    },
                    {
                        label: 'Entregados',
                        data: trendSeries.entregados,
                        borderColor: '#28a745',
                        backgroundColor: '#28a745',
                        tension: .25,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                }
            }
        });
    </script>
@stop

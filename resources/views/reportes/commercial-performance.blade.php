@extends('adminlte::page')

@section('title', 'Rendimiento de Servicios o Productos')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Rendimiento de Servicios o Productos</h1>
            <small class="text-muted">Analítica transaccional por línea de negocio y servicio con rango de fechas.</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>
    </div>
@stop

@section('content')
    @php
        $lineRows = collect($lineRows ?? []);
        $serviceRows = collect($serviceRows ?? []);
        $commercialTotals = $commercialTotals ?? [];
        $commercialKpis = $commercialKpis ?? [];
        $effectiveness = $commercialKpis['effectiveness'] ?? [];
        $sla = $commercialKpis['sla'] ?? [];
        $budget = $commercialKpis['budget'] ?? [];
        $heatmap = $commercialKpis['heatmap'] ?? [];
        $collections = $commercialKpis['collections'] ?? [];
    @endphp

    <div class="card card-outline card-primary">
        <div class="card-header"><strong><i class="fas fa-filter mr-1"></i> Filtros</strong></div>
        <form method="GET" action="{{ route('dashboard.comercial.rendimiento-servicios') }}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label>Fecha desde</label>
                        <input type="date" class="form-control" name="from" value="{{ $from }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Fecha hasta</label>
                        <input type="date" class="form-control" name="to" value="{{ $to }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Líneas de negocio</label>
                        <div class="border rounded p-2 bg-white" style="max-height:160px; overflow:auto;">
                            @foreach(($lineOptions ?? []) as $lineOption)
                                <label class="d-block mb-1">
                                    <input type="checkbox" name="lineas[]" value="{{ $lineOption }}" {{ in_array($lineOption, $selectedLines ?? [], true) ? 'checked' : '' }}>
                                    <span class="ml-1">{{ $lineOption }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search mr-1"></i> Buscar</button>
                    <a href="{{ route('dashboard.comercial.rendimiento-servicios') }}" class="btn btn-outline-secondary"><i class="fas fa-undo mr-1"></i> Limpiar</a>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="{{ route('dashboard.comercial.rendimiento-servicios.excel', request()->query()) }}" class="btn btn-success mr-2">
                        <i class="fas fa-file-excel mr-1"></i> Excel
                    </a>
                    <a href="{{ route('dashboard.comercial.rendimiento-servicios.pdf', request()->query()) }}" class="btn btn-danger" target="_blank">
                        <i class="fas fa-file-pdf mr-1"></i> PDF
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-3 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-primary"><i class="fas fa-sitemap"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Líneas</span>
                    <span class="info-box-number">{{ number_format((int) ($commercialTotals['lineas'] ?? 0)) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-info"><i class="fas fa-hashtag"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Registros</span>
                    <span class="info-box-number">{{ number_format((int) ($commercialTotals['registros'] ?? 0)) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-warning"><i class="fas fa-weight-hanging"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Peso total</span>
                    <span class="info-box-number">{{ number_format((float) ($commercialTotals['peso_total'] ?? 0), 3) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Ingresos</span>
                    <span class="info-box-number">Bs {{ number_format((float) ($commercialTotals['precio_total'] ?? 0), 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Ranking por línea de negocio</strong>
            <span class="text-muted small">La primera fila es la que más movimiento tuvo</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Línea de negocio</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Entregados</th>
                            <th class="text-right">No entregados</th>
                            <th class="text-right">Peso</th>
                            <th class="text-right">Bs</th>
                            <th>Servicio líder</th>
                            <th>Último registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lineRows as $lineRow)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="font-weight-bold">{{ $lineRow['linea'] }}</td>
                                <td class="text-right">{{ number_format((int) $lineRow['cantidad']) }}</td>
                                <td class="text-right">{{ number_format((int) $lineRow['entregados']) }}</td>
                                <td class="text-right">{{ number_format((int) $lineRow['no_entregados']) }}</td>
                                <td class="text-right">{{ number_format((float) $lineRow['peso'], 3) }}</td>
                                <td class="text-right">Bs {{ number_format((float) $lineRow['precio'], 2) }}</td>
                                <td>{{ $lineRow['top_servicio'] }} ({{ number_format((int) $lineRow['top_servicio_cantidad']) }})</td>
                                <td>{{ $lineRow['ultimo_registro'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Sin resultados para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header"><strong>Detalle por servicio</strong></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Línea</th>
                            <th>Servicio</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Entregados</th>
                            <th class="text-right">No entregados</th>
                            <th class="text-right">Peso</th>
                            <th class="text-right">Bs</th>
                            <th>Último registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceRows as $serviceRow)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $serviceRow['linea'] }}</td>
                                <td class="font-weight-bold">{{ $serviceRow['servicio'] }}</td>
                                <td class="text-right">{{ number_format((int) $serviceRow['cantidad']) }}</td>
                                <td class="text-right">{{ number_format((int) $serviceRow['entregados']) }}</td>
                                <td class="text-right">{{ number_format((int) $serviceRow['no_entregados']) }}</td>
                                <td class="text-right">{{ number_format((float) $serviceRow['peso'], 3) }}</td>
                                <td class="text-right">Bs {{ number_format((float) $serviceRow['precio'], 2) }}</td>
                                <td>{{ $serviceRow['ultimo_registro'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Sin detalle disponible.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card card-outline card-success">
                <div class="card-header">
                    <strong>Efectividad de entrega</strong>
                    <div class="small text-muted">{{ $effectiveness['metodologia'] ?? '' }}</div>
                </div>
                <div class="card-body">
                    <div style="height: 280px;">
                        <canvas id="chartEffectiveness"></canvas>
                    </div>
                </div>
                <div class="card-body p-0 border-top">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Linea</th>
                                    <th class="text-right">Total</th>
                                    <th class="text-right">Entregados</th>
                                    <th class="text-right">Devol.</th>
                                    <th class="text-right">Rezago</th>
                                    <th class="text-right">% Efec.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(collect($effectiveness['rows'] ?? [])->take(10) as $row)
                                    <tr>
                                        <td>{{ $row['linea'] }}</td>
                                        <td class="text-right">{{ number_format((int) $row['total']) }}</td>
                                        <td class="text-right">{{ number_format((int) $row['entregados']) }}</td>
                                        <td class="text-right">{{ number_format((int) $row['devoluciones']) }}</td>
                                        <td class="text-right">{{ number_format((int) $row['rezago']) }}</td>
                                        <td class="text-right">{{ number_format((float) $row['efectividad_pct'], 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">Sin datos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <strong>Tiempos de servicio (SLA)</strong>
                    <div class="small text-muted">{{ $sla['metodologia'] ?? '' }}</div>
                </div>
                <div class="card-body">
                    <div style="height: 280px;">
                        <canvas id="chartSla"></canvas>
                    </div>
                </div>
                <div class="card-body p-0 border-top">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Linea</th>
                                    <th class="text-right">Entregados</th>
                                    <th>Promedio</th>
                                    <th>Minimo</th>
                                    <th>Maximo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(collect($sla['rows'] ?? [])->take(10) as $row)
                                    <tr>
                                        <td>{{ $row['linea'] }}</td>
                                        <td class="text-right">{{ number_format((int) $row['entregados']) }}</td>
                                        <td>{{ $row['promedio'] }}</td>
                                        <td>{{ $row['minimo'] }}</td>
                                        <td>{{ $row['maximo'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">Sin datos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card card-outline card-warning">
                <div class="card-header">
                    <strong>Ejecucion presupuestaria de contratos</strong>
                    <div class="small text-muted">{{ $budget['metodologia'] ?? '' }}</div>
                </div>
                <div class="card-body">
                    <div style="height: 280px;">
                        <canvas id="chartBudget"></canvas>
                    </div>
                </div>
                <div class="card-body p-0 border-top">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th class="text-right">Presupuesto</th>
                                    <th class="text-right">Consumido</th>
                                    <th class="text-right">Saldo</th>
                                    <th class="text-right">% Ejec.</th>
                                    <th>Alerta</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(collect($budget['rows'] ?? [])->take(10) as $row)
                                    <tr>
                                        <td>{{ $row['empresa'] }}</td>
                                        <td class="text-right">Bs {{ number_format((float) $row['presupuesto'], 2) }}</td>
                                        <td class="text-right">Bs {{ number_format((float) $row['consumido'], 2) }}</td>
                                        <td class="text-right">Bs {{ number_format((float) $row['saldo'], 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $row['ejecucion_pct'], 2) }}%</td>
                                        <td>{{ $row['alerta'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">Sin datos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <strong>Estado de cobranza</strong>
                    <div class="small text-muted">{{ $collections['metodologia'] ?? '' }}</div>
                </div>
                <div class="card-body">
                    <div style="height: 280px;">
                        <canvas id="chartCollections"></canvas>
                    </div>
                </div>
                <div class="card-body p-0 border-top">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th class="text-right">Facturado</th>
                                    <th class="text-right">Cobrado</th>
                                    <th class="text-right">Pendiente</th>
                                    <th class="text-right">% Cobranza</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(collect($collections['rows'] ?? [])->take(10) as $row)
                                    <tr>
                                        <td>{{ $row['empresa'] }}</td>
                                        <td class="text-right">Bs {{ number_format((float) $row['facturado'], 2) }}</td>
                                        <td class="text-right">Bs {{ number_format((float) $row['cobrado'], 2) }}</td>
                                        <td class="text-right">Bs {{ number_format((float) $row['pendiente'], 2) }}</td>
                                        <td class="text-right">{{ number_format((float) $row['cobranza_pct'], 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-3">Sin datos.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(empty($collections['fuente_disponible']))
                        <div class="alert alert-warning rounded-0 border-0 mb-0">
                            La fuente real de cobranza aun no esta estructurada en la BD actual. Este bloque queda listo para cruzarla cuando se integre.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-dark">
        <div class="card-header">
            <strong>Mapas de calor comercial</strong>
            <div class="small text-muted">{{ $heatmap['metodologia'] ?? '' }}</div>
        </div>
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div style="height: 280px;">
                        <canvas id="chartHeatOrigins"></canvas>
                    </div>
                </div>
                <div class="col-md-6">
                    <div style="height: 280px;">
                        <canvas id="chartHeatRoutes"></canvas>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <h6>Top origenes</h6>
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Origen</th>
                                <th class="text-right">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(collect($heatmap['origenes'] ?? [])->take(8) as $row)
                                <tr>
                                    <td>{{ $row['ubicacion'] }}</td>
                                    <td class="text-right">{{ number_format((int) $row['cantidad']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">Sin datos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="col-md-4">
                    <h6>Top destinos</h6>
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Destino</th>
                                <th class="text-right">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(collect($heatmap['destinos'] ?? [])->take(8) as $row)
                                <tr>
                                    <td>{{ $row['ubicacion'] }}</td>
                                    <td class="text-right">{{ number_format((int) $row['cantidad']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">Sin datos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="col-md-4">
                    <h6>Top rutas</h6>
                    <table class="table table-sm table-striped">
                        <thead>
                            <tr>
                                <th>Ruta</th>
                                <th class="text-right">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse(collect($heatmap['rutas'] ?? [])->take(8) as $row)
                                <tr>
                                    <td>{{ $row['ruta'] }}</td>
                                    <td class="text-right">{{ number_format((int) $row['cantidad']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">Sin datos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop

@section('js')
    @parent
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            const effectivenessRows = @json(collect($effectiveness['rows'] ?? [])->take(8)->values());
            const slaRows = @json(collect($sla['rows'] ?? [])->take(8)->values());
            const budgetRows = @json(collect($budget['rows'] ?? [])->take(8)->values());
            const collectionRows = @json(collect($collections['rows'] ?? [])->take(8)->values());
            const heatOrigins = @json(collect($heatmap['origenes'] ?? [])->take(8)->values());
            const heatRoutes = @json(collect($heatmap['rutas'] ?? [])->take(8)->values());

            const palette = ['#1f5fae', '#2ca58d', '#f4a259', '#bc4749', '#6c5ce7', '#00a8e8', '#f72585', '#6a994e'];

            new Chart(document.getElementById('chartEffectiveness'), {
                type: 'pie',
                data: {
                    labels: ['Entregados', 'Devoluciones', 'Rezago', 'Pendientes'],
                    datasets: [{
                        data: [
                            {{ (int) ($effectiveness['entregados'] ?? 0) }},
                            {{ (int) ($effectiveness['devoluciones'] ?? 0) }},
                            {{ (int) ($effectiveness['rezago'] ?? 0) }},
                            {{ (int) ($effectiveness['pendientes'] ?? 0) }}
                        ],
                        backgroundColor: ['#2ca58d', '#f4a259', '#bc4749', '#9aa5b1']
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        title: { display: true, text: 'Distribucion operativa del periodo' }
                    }
                }
            });

            new Chart(document.getElementById('chartSla'), {
                type: 'bar',
                data: {
                    labels: slaRows.map(row => row.linea),
                    datasets: [{
                        label: 'Promedio horas',
                        data: slaRows.map(row => Number(row.promedio_horas || 0)),
                        backgroundColor: '#1f5fae'
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Tiempo promedio por linea' }
                    }
                }
            });

            new Chart(document.getElementById('chartBudget'), {
                type: 'bar',
                data: {
                    labels: budgetRows.map(row => row.empresa),
                    datasets: [{
                        label: '% Ejecucion',
                        data: budgetRows.map(row => Number(row.ejecucion_pct || 0)),
                        backgroundColor: budgetRows.map(row => row.alerta === 'AGOTADO' ? '#bc4749' : (row.alerta === 'ALERTA' ? '#f4a259' : '#2ca58d'))
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'Consumo de presupuesto por empresa' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            new Chart(document.getElementById('chartCollections'), {
                type: 'bar',
                data: {
                    labels: collectionRows.map(row => row.empresa),
                    datasets: [{
                        label: 'Facturado',
                        data: collectionRows.map(row => Number(row.facturado || 0)),
                        backgroundColor: '#1f5fae'
                    }, {
                        label: 'Cobrado',
                        data: collectionRows.map(row => Number(row.cobrado || 0)),
                        backgroundColor: '#2ca58d'
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        title: { display: true, text: 'Facturado vs cobrado' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            new Chart(document.getElementById('chartHeatOrigins'), {
                type: 'bar',
                data: {
                    labels: heatOrigins.map(row => row.ubicacion),
                    datasets: [{
                        label: 'Cantidad',
                        data: heatOrigins.map(row => Number(row.cantidad || 0)),
                        backgroundColor: palette
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Top origenes' }
                    }
                }
            });

            new Chart(document.getElementById('chartHeatRoutes'), {
                type: 'bar',
                data: {
                    labels: heatRoutes.map(row => row.ruta),
                    datasets: [{
                        label: 'Cantidad',
                        data: heatRoutes.map(row => Number(row.cantidad || 0)),
                        backgroundColor: '#6c5ce7'
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        title: { display: true, text: 'Top rutas comerciales' }
                    }
                }
            });
        })();
    </script>
@stop

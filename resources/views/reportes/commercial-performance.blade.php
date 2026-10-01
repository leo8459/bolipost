@extends('adminlte::page')

@section('title', 'Rendimiento comercial')

@section('content_header')
    <div class="report-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <div class="report-eyebrow">DIRECCIÓN COMERCIAL · INDICADORES OPERATIVOS</div>
            <h1 class="mb-1">Rendimiento de servicios y productos</h1>
            <p class="mb-0 text-muted">Actividad, entregas y tiempos de servicio en el periodo seleccionado.</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary mt-3 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i> Volver al dashboard
        </a>
    </div>
@stop

@section('css')
    @parent
    <style>
        .report-heading { padding: 4px 0 12px; }
        .report-heading h1 { color: #17324d; font-size: 1.65rem; font-weight: 700; letter-spacing: -.02em; }
        .report-heading p { font-size: .92rem; }
        .report-eyebrow, .report-section-kicker { color: #56728d; font-size: .69rem; font-weight: 700; letter-spacing: .09em; }
        #commercial-performance { --report-ink: #17324d; --report-muted: #718096; --report-line: #e5ebf1; --report-blue: #2364aa; }
        #commercial-performance .report-card { border: 1px solid var(--report-line); border-radius: 10px; box-shadow: 0 3px 12px rgba(25, 48, 74, .045); }
        #commercial-performance .report-card > .card-header { background: #fff; border-bottom: 1px solid var(--report-line); border-radius: 10px 10px 0 0; padding: 16px 20px; }
        #commercial-performance .report-card > .card-body { padding: 20px; }
        #commercial-performance .report-card-title { color: var(--report-ink); font-size: 1rem; font-weight: 700; margin: 0; }
        #commercial-performance .report-card-subtitle { color: var(--report-muted); font-size: .8rem; margin-top: 4px; }
        #commercial-performance .report-filter-label { color: #31465b; font-size: .78rem; font-weight: 700; margin-bottom: 7px; }
        #commercial-performance .form-control { border-color: #d9e2ec; border-radius: 6px; height: 39px; }
        #commercial-performance .line-options { background: #f8fafc; border: 1px solid var(--report-line); border-radius: 7px; max-height: 122px; overflow: auto; padding: 10px 12px; }
        #commercial-performance .line-option { color: #354b60; font-size: .8rem; margin: 0 0 7px; }
        #commercial-performance .line-option:last-child { margin-bottom: 0; }
        #commercial-performance .report-meta { color: var(--report-muted); font-size: .78rem; }
        #commercial-performance .report-meta strong { color: #40576d; }
        #commercial-performance .metric-card { background: #fff; border: 1px solid var(--report-line); border-radius: 10px; height: 100%; min-height: 120px; overflow: hidden; padding: 17px 18px; position: relative; }
        #commercial-performance .metric-card:before { background: var(--metric-color, #2364aa); content: ''; height: 100%; left: 0; position: absolute; top: 0; width: 4px; }
        #commercial-performance .metric-top { align-items: center; color: var(--report-muted); display: flex; font-size: .74rem; font-weight: 700; justify-content: space-between; text-transform: uppercase; }
        #commercial-performance .metric-icon { align-items: center; background: #edf4fb; border-radius: 9px; color: var(--metric-color, #2364aa); display: inline-flex; font-size: .95rem; height: 34px; justify-content: center; width: 34px; }
        #commercial-performance .metric-value { color: var(--report-ink); font-size: 1.7rem; font-weight: 700; letter-spacing: -.035em; line-height: 1.15; margin-top: 10px; }
        #commercial-performance .metric-note { color: var(--report-muted); font-size: .75rem; margin-top: 5px; }
        #commercial-performance .report-section { margin-top: 24px; }
        #commercial-performance .report-section-heading { align-items: end; display: flex; justify-content: space-between; margin: 0 0 11px; }
        #commercial-performance .report-section-heading h2 { color: var(--report-ink); font-size: 1.08rem; font-weight: 700; margin: 3px 0 0; }
        #commercial-performance .report-section-note { color: var(--report-muted); font-size: .77rem; }
        #commercial-performance .report-table { color: #354b60; font-size: .8rem; margin-bottom: 0; }
        #commercial-performance .report-table thead th { background: #f5f8fb; border-bottom: 1px solid #e2e9f0; border-top: 0; color: #647b91; font-size: .68rem; font-weight: 700; letter-spacing: .045em; padding: 11px 12px; text-transform: uppercase; white-space: nowrap; }
        #commercial-performance .report-table tbody td { border-top: 1px solid #edf1f5; padding: 11px 12px; vertical-align: middle; }
        #commercial-performance .report-table tbody tr:hover { background: #f9fbfd; }
        #commercial-performance .table-responsive { border-radius: 0 0 10px 10px; }
        #commercial-performance .rank-badge { align-items: center; background: #edf4fb; border-radius: 50%; color: #28649e; display: inline-flex; font-size: .72rem; font-weight: 700; height: 26px; justify-content: center; width: 26px; }
        #commercial-performance .value-badge { background: #eef5fb; border-radius: 12px; color: #315a7e; display: inline-block; font-size: .72rem; font-weight: 700; padding: 4px 8px; white-space: nowrap; }
        #commercial-performance .status-pill { background: #e8f5ef; border-radius: 12px; color: #24734f; font-size: .72rem; font-weight: 700; padding: 4px 8px; white-space: nowrap; }
        #commercial-performance .chart-wrap { height: 255px; position: relative; }
        #commercial-performance .coverage-table { margin-top: 16px; }
        #commercial-performance .empty-state { color: var(--report-muted); padding: 26px 14px !important; text-align: center; }
        @media (max-width: 767.98px) {
            .report-heading h1 { font-size: 1.35rem; }
            #commercial-performance .report-card > .card-body { padding: 15px; }
            #commercial-performance .metric-card { min-height: 108px; padding: 14px; }
            #commercial-performance .metric-value { font-size: 1.45rem; }
            #commercial-performance .report-section-heading { align-items: flex-start; flex-direction: column; }
            #commercial-performance .chart-wrap { height: 220px; }
        }
    </style>
@stop

@section('content')
    @php
        $lineRows = collect($lineRows ?? []);
        $serviceRows = collect($serviceRows ?? []);
        $commercialTotals = $commercialTotals ?? [];
        $commercialKpis = $commercialKpis ?? [];
        $effectiveness = $commercialKpis['effectiveness'] ?? [];
        $sla = $commercialKpis['sla'] ?? [];
        $heatmap = $commercialKpis['heatmap'] ?? [];
        $selectedLines = $selectedLines ?? [];
        $periodLabel = !empty($from) || !empty($to)
            ? (($from ? date('d/m/Y', strtotime($from)) : 'Inicio') . ' – ' . ($to ? date('d/m/Y', strtotime($to)) : 'Hoy'))
            : 'Todo el historial';
        $linesLabel = count($selectedLines) ? count($selectedLines) . ' líneas seleccionadas' : 'Todas las líneas';
        $coverageLists = [
            ['title' => 'Principales orígenes', 'rows' => $heatmap['origenes'] ?? [], 'key' => 'ubicacion'],
            ['title' => 'Principales destinos', 'rows' => $heatmap['destinos'] ?? [], 'key' => 'ubicacion'],
            ['title' => 'Rutas más frecuentes', 'rows' => $heatmap['rutas'] ?? [], 'key' => 'ruta'],
        ];
    @endphp

    <div id="commercial-performance">
        <div class="card report-card mb-4">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h2 class="report-card-title"><i class="fas fa-sliders-h text-primary mr-2"></i>Filtros del reporte</h2>
                    <div class="report-card-subtitle">Ajusta el periodo y las líneas que quieres comparar.</div>
                </div>
                <div class="mt-3 mt-md-0 d-flex">
                    <a href="{{ route('dashboard.comercial.rendimiento-servicios.excel', request()->query()) }}" class="btn btn-outline-success btn-sm mr-2">
                        <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                    </a>
                    <a href="{{ route('dashboard.comercial.rendimiento-servicios.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener">
                        <i class="fas fa-file-pdf mr-1"></i> Exportar PDF
                    </a>
                </div>
            </div>
            <form method="GET" action="{{ route('dashboard.comercial.rendimiento-servicios') }}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label class="report-filter-label" for="commercial-from">Fecha desde</label>
                            <input id="commercial-from" type="date" class="form-control" name="from" value="{{ $from }}">
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label class="report-filter-label" for="commercial-to">Fecha hasta</label>
                            <input id="commercial-to" type="date" class="form-control" name="to" value="{{ $to }}">
                        </div>
                        <div class="col-md-6">
                            <div class="report-filter-label">Líneas de negocio</div>
                            <div class="line-options row mx-0">
                                @forelse(($lineOptions ?? []) as $lineOption)
                                    <div class="col-sm-6 col-lg-4 px-1">
                                        <label class="custom-control custom-checkbox line-option">
                                            <input type="checkbox" class="custom-control-input" name="lineas[]" value="{{ $lineOption }}" {{ in_array($lineOption, $selectedLines, true) ? 'checked' : '' }}>
                                            <span class="custom-control-label">{{ $lineOption }}</span>
                                        </label>
                                    </div>
                                @empty
                                    <span class="text-muted small">No hay líneas disponibles.</span>
                                @endforelse
                            </div>
                            <div class="report-meta mt-1">Si no seleccionas una línea, se mostrarán todas.</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center">
                    <div class="report-meta mb-2 mb-md-0">
                        <strong>Periodo:</strong> {{ $periodLabel }} <span class="mx-1">·</span> <strong>{{ $linesLabel }}</strong>
                    </div>
                    <div>
                        <a href="{{ route('dashboard.comercial.rendimiento-servicios') }}" class="btn btn-light border mr-2">Limpiar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-1"></i> Aplicar filtros</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="row">
            <div class="col-6 col-lg-3 mb-3">
                <div class="metric-card" style="--metric-color:#2364aa">
                    <div class="metric-top"><span>Registros</span><span class="metric-icon"><i class="fas fa-boxes"></i></span></div>
                    <div class="metric-value">{{ \App\Support\BolivianNumber::format((int) ($commercialTotals['registros'] ?? 0)) }}</div>
                    <div class="metric-note">{{ \App\Support\BolivianNumber::format((int) ($commercialTotals['lineas'] ?? 0)) }} líneas de negocio</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="metric-card" style="--metric-color:#25805a">
                    <div class="metric-top"><span>Entregas confirmadas</span><span class="metric-icon"><i class="fas fa-check-circle"></i></span></div>
                    <div class="metric-value">{{ \App\Support\BolivianNumber::format((int) ($commercialTotals['entregados'] ?? 0)) }}</div>
                    <div class="metric-note">{{ \App\Support\BolivianNumber::format((int) ($commercialTotals['no_entregados'] ?? 0)) }} sin entrega confirmada</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="metric-card" style="--metric-color:#7755a6">
                    <div class="metric-top"><span>Efectividad de entrega</span><span class="metric-icon"><i class="fas fa-bullseye"></i></span></div>
                    <div class="metric-value">{{ \App\Support\BolivianNumber::format((float) ($effectiveness['efectividad_pct'] ?? 0), 1) }}<small class="ml-1">%</small></div>
                    <div class="metric-note">Sobre registros operativos del periodo</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="metric-card" style="--metric-color:#bd7c27">
                    <div class="metric-top"><span>Peso procesado</span><span class="metric-icon"><i class="fas fa-weight-hanging"></i></span></div>
                    <div class="metric-value">{{ \App\Support\BolivianNumber::format((float) ($commercialTotals['peso_total'] ?? 0), 3) }}</div>
                    <div class="metric-note">Kilogramos registrados</div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <div class="report-section-heading">
                <div><div class="report-section-kicker">CALIDAD DEL SERVICIO</div><h2>Entrega y tiempos de atención</h2></div>
                <div class="report-section-note">Indicadores calculados con los registros del periodo seleccionado.</div>
            </div>
            <div class="row">
                <div class="col-lg-6 mb-3">
                    <div class="card report-card h-100">
                        <div class="card-header">
                            <h3 class="report-card-title">Efectividad de entrega</h3>
                            <div class="report-card-subtitle">{{ $effectiveness['metodologia'] ?? 'Distribución del estado operativo de los registros.' }}</div>
                        </div>
                        <div class="card-body"><div class="chart-wrap"><canvas id="chartEffectiveness" aria-label="Distribución operativa de entregas"></canvas></div></div>
                        <div class="table-responsive">
                            <table class="table report-table">
                                <thead><tr><th>Línea</th><th class="text-right">Total</th><th class="text-right">Entregados</th><th class="text-right">Devoluciones</th><th class="text-right">Rezago</th><th class="text-right">Efectividad</th></tr></thead>
                                <tbody>
                                    @forelse(collect($effectiveness['rows'] ?? [])->take(10) as $row)
                                        <tr>
                                            <td class="font-weight-bold">{{ $row['linea'] }}</td>
                                            <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $row['total']) }}</td>
                                            <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $row['entregados']) }}</td>
                                            <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $row['devoluciones']) }}</td>
                                            <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $row['rezago']) }}</td>
                                            <td class="text-right"><span class="status-pill">{{ \App\Support\BolivianNumber::format((float) $row['efectividad_pct'], 1) }}%</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="6" class="empty-state">No hay datos de entrega para estos filtros.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 mb-3">
                    <div class="card report-card h-100">
                        <div class="card-header">
                            <h3 class="report-card-title">Tiempo de servicio (SLA)</h3>
                            <div class="report-card-subtitle">{{ $sla['metodologia'] ?? 'Tiempo transcurrido hasta la entrega final confirmada.' }}</div>
                        </div>
                        <div class="card-body"><div class="chart-wrap"><canvas id="chartSla" aria-label="Tiempo promedio de servicio por línea"></canvas></div></div>
                        <div class="table-responsive">
                            <table class="table report-table">
                                <thead><tr><th>Línea</th><th class="text-right">Entregados</th><th>Promedio</th><th>Mínimo</th><th>Máximo</th></tr></thead>
                                <tbody>
                                    @forelse(collect($sla['rows'] ?? [])->take(10) as $row)
                                        <tr><td class="font-weight-bold">{{ $row['linea'] }}</td><td class="text-right">{{ \App\Support\BolivianNumber::format((int) $row['entregados']) }}</td><td><span class="value-badge">{{ $row['promedio'] }}</span></td><td>{{ $row['minimo'] }}</td><td>{{ $row['maximo'] }}</td></tr>
                                    @empty
                                        <tr><td colspan="5" class="empty-state">No hay entregas con tiempo medible en este periodo.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="report-section">
            <div class="report-section-heading">
                <div><div class="report-section-kicker">VOLUMEN DE ACTIVIDAD</div><h2>Desempeño por línea de negocio</h2></div>
                <div class="report-section-note">Ordenado por cantidad de registros.</div>
            </div>
            <div class="card report-card">
                <div class="table-responsive">
                    <table class="table report-table">
                        <thead><tr><th class="text-center">#</th><th>Línea de negocio</th><th class="text-right">Registros</th><th class="text-right">Entregados</th><th class="text-right">No entregados</th><th class="text-right">Efectividad</th><th class="text-right">Peso (kg)</th><th>Servicio principal</th><th>Último registro</th></tr></thead>
                        <tbody>
                            @forelse($lineRows as $lineRow)
                                @php($lineEffectiveness = (int) ($lineRow['cantidad'] ?? 0) > 0 ? ((int) ($lineRow['entregados'] ?? 0) / (int) $lineRow['cantidad']) * 100 : 0)
                                <tr>
                                    <td class="text-center"><span class="rank-badge">{{ $loop->iteration }}</span></td>
                                    <td class="font-weight-bold">{{ $lineRow['linea'] }}</td>
                                    <td class="text-right font-weight-bold">{{ \App\Support\BolivianNumber::format((int) $lineRow['cantidad']) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $lineRow['entregados']) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $lineRow['no_entregados']) }}</td>
                                    <td class="text-right"><span class="status-pill">{{ \App\Support\BolivianNumber::format($lineEffectiveness, 1) }}%</span></td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $lineRow['peso'], 3) }}</td>
                                    <td>{{ $lineRow['top_servicio'] }} <span class="value-badge ml-1">{{ \App\Support\BolivianNumber::format((int) $lineRow['top_servicio_cantidad']) }}</span></td>
                                    <td class="text-nowrap">{{ $lineRow['ultimo_registro'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="empty-state">No hay registros para los filtros seleccionados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="report-section">
            <div class="report-section-heading">
                <div><div class="report-section-kicker">DESGLOSE</div><h2>Detalle por servicio</h2></div>
                <div class="report-section-note">Cada servicio se agrupa dentro de su línea de negocio.</div>
            </div>
            <div class="card report-card">
                <div class="table-responsive">
                    <table class="table report-table">
                        <thead><tr><th class="text-center">#</th><th>Línea</th><th>Servicio</th><th class="text-right">Registros</th><th class="text-right">Entregados</th><th class="text-right">No entregados</th><th class="text-right">Peso (kg)</th><th>Último registro</th></tr></thead>
                        <tbody>
                            @forelse($serviceRows as $serviceRow)
                                <tr>
                                    <td class="text-center"><span class="rank-badge">{{ $loop->iteration }}</span></td>
                                    <td>{{ $serviceRow['linea'] }}</td>
                                    <td class="font-weight-bold">{{ $serviceRow['servicio'] }}</td>
                                    <td class="text-right font-weight-bold">{{ \App\Support\BolivianNumber::format((int) $serviceRow['cantidad']) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $serviceRow['entregados']) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $serviceRow['no_entregados']) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $serviceRow['peso'], 3) }}</td>
                                    <td class="text-nowrap">{{ $serviceRow['ultimo_registro'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="empty-state">No hay servicios para mostrar.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="report-section mb-4">
            <div class="report-section-heading">
                <div><div class="report-section-kicker">COBERTURA</div><h2>Orígenes, destinos y rutas</h2></div>
                <div class="report-section-note">Ubicaciones ordenadas por volumen de registros.</div>
            </div>
            <div class="card report-card">
                <div class="card-header"><div class="report-card-subtitle mt-0">{{ $heatmap['metodologia'] ?? 'Frecuencia de registros agrupada por origen, destino y ruta.' }}</div></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-6 mb-3"><div class="chart-wrap"><canvas id="chartHeatOrigins" aria-label="Principales ubicaciones de origen"></canvas></div></div>
                        <div class="col-lg-6 mb-3"><div class="chart-wrap"><canvas id="chartHeatRoutes" aria-label="Principales rutas"></canvas></div></div>
                    </div>
                    <div class="row coverage-table">
                        @foreach($coverageLists as $coverage)
                            <div class="col-lg-4 mb-3 mb-lg-0">
                                <h3 class="report-card-title mb-2">{{ $coverage['title'] }}</h3>
                                <div class="table-responsive">
                                    <table class="table report-table">
                                        <thead><tr><th>{{ $coverage['key'] === 'ruta' ? 'Ruta' : 'Ubicación' }}</th><th class="text-right">Registros</th></tr></thead>
                                        <tbody>
                                            @forelse(collect($coverage['rows'])->take(8) as $row)
                                                <tr><td>{{ $row[$coverage['key']] }}</td><td class="text-right">{{ \App\Support\BolivianNumber::format((int) $row['cantidad']) }}</td></tr>
                                            @empty
                                                <tr><td colspan="2" class="empty-state">Sin datos disponibles.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    </div>
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
            if (typeof Chart === 'undefined') return;

            const slaRows = @json(collect($sla['rows'] ?? [])->take(8)->values());
            const heatOrigins = @json(collect($heatmap['origenes'] ?? [])->take(8)->values());
            const heatRoutes = @json(collect($heatmap['rutas'] ?? [])->take(8)->values());
            const colors = ['#2364aa', '#25805a', '#bd7c27', '#ba5960', '#7755a6', '#158895', '#d16b3c', '#62758a'];

            const effectivenessCanvas = document.getElementById('chartEffectiveness');
            if (effectivenessCanvas) {
                new Chart(effectivenessCanvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['Entregados', 'Devoluciones', 'Rezago', 'Pendientes'],
                        datasets: [{
                            data: [
                                {{ (int) ($effectiveness['entregados'] ?? 0) }},
                                {{ (int) ($effectiveness['devoluciones'] ?? 0) }},
                                {{ (int) ($effectiveness['rezago'] ?? 0) }},
                                {{ (int) ($effectiveness['pendientes'] ?? 0) }}
                            ],
                            backgroundColor: ['#25805a', '#bd7c27', '#ba5960', '#c9d2dc'],
                            borderWidth: 0,
                            hoverOffset: 5
                        }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        cutout: '67%',
                        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 18 } } }
                    }
                });
            }

            const slaCanvas = document.getElementById('chartSla');
            if (slaCanvas) {
                new Chart(slaCanvas, {
                    type: 'bar',
                    data: {
                        labels: slaRows.map(row => row.linea),
                        datasets: [{ label: 'Horas promedio', data: slaRows.map(row => Number(row.promedio_horas || 0)), backgroundColor: '#2364aa', borderRadius: 5, maxBarThickness: 24 }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, grid: { color: '#edf1f5' }, title: { display: true, text: 'Horas' } }, y: { grid: { display: false } } }
                    }
                });
            }

            const originsCanvas = document.getElementById('chartHeatOrigins');
            if (originsCanvas) {
                new Chart(originsCanvas, {
                    type: 'bar',
                    data: { labels: heatOrigins.map(row => row.ubicacion), datasets: [{ label: 'Registros', data: heatOrigins.map(row => Number(row.cantidad || 0)), backgroundColor: colors, borderRadius: 5, maxBarThickness: 34 }] },
                    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#edf1f5' } }, x: { grid: { display: false } } } }
                });
            }

            const routesCanvas = document.getElementById('chartHeatRoutes');
            if (routesCanvas) {
                new Chart(routesCanvas, {
                    type: 'bar',
                    data: { labels: heatRoutes.map(row => row.ruta), datasets: [{ label: 'Registros', data: heatRoutes.map(row => Number(row.cantidad || 0)), backgroundColor: '#7755a6', borderRadius: 5, maxBarThickness: 24 }] },
                    options: { maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { color: '#edf1f5' } }, y: { grid: { display: false } } } }
                });
            }
        })();
    </script>
@stop

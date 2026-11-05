@extends('adminlte::page')

@section('title', 'Rendimiento comercial')

@section('content_header')
    @php
        $headingPeriodFiltered = !empty($monthFrom) || !empty($monthTo) || !empty($from) || !empty($to);
        $headingPeriodLabel = $periodLabel ?? ($headingPeriodFiltered
            ? (($from ? date('d/m/Y', strtotime($from)) : 'Inicio') . ' – ' . ($to ? date('d/m/Y', strtotime($to)) : 'Hoy'))
            : 'Todo el historial');
    @endphp
    <div class="report-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <div class="report-eyebrow">DIRECCIÓN COMERCIAL · INDICADORES OPERATIVOS</div>
            <div class="d-flex flex-wrap align-items-center">
                <h1 class="mb-1 mr-3">Rendimiento de servicios y productos</h1>
                <span class="report-period-badge {{ $headingPeriodFiltered ? 'report-period-badge--filtered' : '' }}">
                    <i class="far fa-calendar-alt mr-1"></i>
                    {{ $headingPeriodFiltered ? 'Periodo filtrado: ' : 'Periodo: ' }}{{ $headingPeriodLabel }}
                </span>
            </div>
            <p class="mb-0 text-muted">Consulta tu regional, compara su actividad mensual y descarga el reporte.</p>
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
        .report-period-badge { background: #e9f1fb; border: 1px solid #cbdcf0; border-radius: 16px; color: #315a7e; display: inline-block; font-size: .76rem; font-weight: 700; margin-bottom: 4px; padding: 5px 10px; }
        .report-period-badge--filtered { background: #e8f5ef; border-color: #c8e7d6; color: #24734f; }
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
        #commercial-performance .monthly-chart-wrap { height: 290px; position: relative; }
        #commercial-performance .coverage-table { margin-top: 16px; }
        #commercial-performance .empty-state { color: var(--report-muted); padding: 26px 14px !important; text-align: center; }
        #commercial-performance .regional-options { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        #commercial-performance .regional-choice { display: flex; align-items: center; gap: 9px; padding: 10px 12px; border: 1px solid #dce5ee; border-radius: 8px; font-size: .8rem; cursor: pointer; margin: 0; background: #fff; }
        #commercial-performance .regional-choice:has(input:checked) { background: #eaf3ff; border-color: #2364aa; color: #174e88; }
        #commercial-performance .regional-choice input { accent-color: #2364aa; width: 16px; height: 16px; }
        #commercial-performance .filter-regionals { background: #f7f9fc; border: 1px solid #e5ebf1; border-radius: 10px; padding: 16px; margin-top: 20px; }
        #commercial-performance .report-navigation { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 20px; }
        #commercial-performance .report-navigation a { padding: 8px 14px; background: #fff; color: #315a7e; border: 1px solid #dce5ee; border-radius: 20px; font-size: .8rem; }
        #commercial-performance .report-navigation a:hover { background: #eaf3ff; text-decoration: none; }
        #commercial-performance .report-section { scroll-margin-top: 25px; }
        #commercial-performance .regional-table { max-height: 430px; overflow: auto; }
        #commercial-performance .regional-table thead th { position: sticky; top: 0; z-index: 1; }
        #commercial-performance .scope-badge { display: inline-block; background: #eaf3ff; border-radius: 6px; padding: 5px 9px; margin: 3px 4px 3px 0; color: #315a7e; }
        #commercial-performance details summary { cursor: pointer; color: #2364aa; padding: 12px 18px; font-weight: 600; font-size: .82rem; }
        @media (max-width: 767.98px) {
            .report-heading h1 { font-size: 1.35rem; }
            #commercial-performance .report-card > .card-body { padding: 15px; }
            #commercial-performance .metric-card { min-height: 108px; padding: 14px; }
            #commercial-performance .metric-value { font-size: 1.45rem; }
            #commercial-performance .report-section-heading { align-items: flex-start; flex-direction: column; }
            #commercial-performance .chart-wrap { height: 220px; }
            #commercial-performance .monthly-chart-wrap { height: 250px; }
            #commercial-performance .regional-options { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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
        $periodLabel = $periodLabel ?? (!empty($from) || !empty($to)
            ? (($from ? date('d/m/Y', strtotime($from)) : 'Inicio') . ' – ' . ($to ? date('d/m/Y', strtotime($to)) : 'Hoy'))
            : 'Todo el historial');
        $monthlyRows = collect($monthlyRows ?? []);
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
                    <div class="report-card-subtitle">Elige los meses, las líneas de negocio y una o varias regionales.</div>
                </div>
                <div class="mt-3 mt-md-0 d-flex">
                    <a href="{{ route('dashboard.comercial.rendimiento-servicios.excel', request()->query()) }}" class="btn btn-outline-success btn-sm mr-2" data-commercial-export="excel">
                        <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                    </a>
                    <a href="{{ route('dashboard.comercial.rendimiento-servicios.pdf', request()->query()) }}" class="btn btn-outline-danger btn-sm" target="_blank" rel="noopener" data-commercial-export="pdf">
                        <i class="fas fa-file-pdf mr-1"></i> Exportar PDF
                    </a>
                </div>
            </div>
            <form id="commercial-performance-filter" method="GET" action="{{ route('dashboard.comercial.rendimiento-servicios') }}">
                @if(empty($monthFrom) && empty($monthTo))
                    @if(!empty($from))<input type="hidden" name="from" value="{{ $from }}">@endif
                    @if(!empty($to))<input type="hidden" name="to" value="{{ $to }}">@endif
                @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label class="report-filter-label" for="commercial-month-from">Mes desde</label>
                            <input id="commercial-month-from" type="month" class="form-control" name="from_month" value="{{ $monthFrom }}">
                        </div>
                        <div class="col-md-3 mb-3 mb-md-0">
                            <label class="report-filter-label" for="commercial-month-to">Mes hasta</label>
                            <input id="commercial-month-to" type="month" class="form-control" name="to_month" value="{{ $monthTo }}">
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
                            <div class="report-meta mt-1">Si no seleccionas una línea, se mostrarán todas. El primer mes se compara con el mes anterior.</div>
                        </div>
                    </div>
                    <div class="filter-regionals">
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <div class="report-filter-label"><i class="fas fa-map-marker-alt mr-1"></i> Departamentos / regionales</div>
                                <p class="report-meta mb-1">Selecciona varias para compararlas. Sin selección se incluyen todas.</p>
                                <div class="report-meta">Sucre corresponde a Chuquisaca, Trinidad a Beni y Cobija a Pando.</div>
                            </div>
                            <div class="col-md-4 mt-2 mt-md-0">
                                <label for="commercial-regional-type" class="report-filter-label">Consultar por</label>
                                <select id="commercial-regional-type" name="regional_tipo" class="form-control">
                                    <option value="origen" {{ $regionalType === 'origen' ? 'selected' : '' }}>Regional de origen</option>
                                    <option value="destino" {{ $regionalType === 'destino' ? 'selected' : '' }}>Regional de destino</option>
                                </select>
                            </div>
                        </div>
                        <div class="regional-options">
                            @foreach($regionalOptions as $regionalOption)
                                <label class="regional-choice">
                                    <input type="checkbox" name="regionales[]" value="{{ $regionalOption }}" {{ in_array($regionalOption, $selectedRegionals, true) ? 'checked' : '' }}>
                                    <span>{{ $regionalOption }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button type="button" id="commercial-all-regionals" class="btn btn-link btn-sm px-0 mt-2">Quitar selección y mostrar todas</button>
                    </div>
                </div>
                <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center">
                    <div class="report-meta mb-2 mb-md-0">
                        <span class="scope-badge"><i class="far fa-calendar-alt mr-1"></i>{{ $periodLabel }}</span>
                        <span class="scope-badge">{{ $regionalLabel }}</span>
                        <span class="scope-badge">{{ $linesLabel }}</span>
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

        <nav class="report-navigation" aria-label="Secciones del reporte">
            <a href="#commercial-regionals"><i class="fas fa-map-marker-alt mr-1"></i> Por regional</a>
            <a href="#commercial-monthly"><i class="fas fa-chart-line mr-1"></i> Evolución mensual</a>
            <a href="#commercial-quality">Entregas y tiempos</a>
            <a href="#commercial-services">Detalle por servicio</a>
        </nav>

        <div id="commercial-regionals" class="report-section">
            <div class="report-section-heading">
                <div><div class="report-section-kicker">COMPARACIÓN REGIONAL</div><h2>Actividad por departamento</h2></div>
                <div class="report-section-note">{{ $regionalLabel }}</div>
            </div>
            <div class="card report-card">
                @if(count($regionalRows))
                <div class="card-body">
                    <div class="chart-wrap"><canvas id="chartCommercialRegionals" aria-label="Cantidad de registros por regional"></canvas></div>
                </div>
                @endif
                <div class="table-responsive regional-table">
                    @include('reportes.partials.commercial-regional-table')
                </div>
            </div>
        </div>

        <div id="commercial-monthly" class="report-section">
            <div class="report-section-heading">
                <div><div class="report-section-kicker">TENDENCIA MENSUAL</div><h2>Actividad y variación frente al mes anterior</h2></div>
                <div class="report-section-note">El porcentaje se calcula sobre los registros del mes calendario anterior.</div>
            </div>
            <div class="card report-card mb-3">
                <div class="card-header">
                    <h3 class="report-card-title">Registros y crecimiento mensual</h3>
                    <div class="report-card-subtitle">La comparación incluye el mes previo al rango como referencia.</div>
                </div>
                <div class="card-body"><div class="monthly-chart-wrap"><canvas id="chartCommercialMonthly" aria-label="Registros mensuales y porcentaje de variación"></canvas></div></div>
            </div>
            <div class="card report-card">
                <details>
                    <summary>Ver detalle mensual · {{ $monthlyRows->count() }} meses</summary>
                <div class="table-responsive">
                    <table class="table report-table">
                        <thead><tr><th>Mes</th><th class="text-right">Registros</th><th class="text-right">Mes anterior</th><th class="text-right">Variación</th><th class="text-right">Crecimiento</th><th class="text-right">Entregados</th><th class="text-right">No entregados</th><th class="text-right">Peso (kg)</th></tr></thead>
                        <tbody>
                            @forelse($monthlyRows as $monthRow)
                                @php
                                    $growth = $monthRow['variacion_pct'] ?? null;
                                    $growthClass = $growth === null ? 'text-muted' : ($growth >= 0 ? 'text-success' : 'text-danger');
                                @endphp
                                <tr>
                                    <td class="font-weight-bold">{{ $monthRow['periodo'] }}</td>
                                    <td class="text-right font-weight-bold">{{ \App\Support\BolivianNumber::format((int) $monthRow['registros']) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $monthRow['mes_anterior_registros']) }} <small class="text-muted">({{ $monthRow['mes_anterior'] }})</small></td>
                                    <td class="text-right {{ $growthClass }}">{{ ((int) $monthRow['variacion'] > 0 ? '+' : '') . \App\Support\BolivianNumber::format((int) $monthRow['variacion']) }}</td>
                                    <td class="text-right font-weight-bold {{ $growthClass }}">
                                        @if($growth === null)
                                            {{ (int) $monthRow['registros'] > 0 ? 'Nuevo' : 'Sin base' }}
                                        @else
                                            {{ $growth > 0 ? '+' : '' }}{{ \App\Support\BolivianNumber::format((float) $growth, 2) }}%
                                        @endif
                                    </td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $monthRow['entregados']) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((int) $monthRow['no_entregados']) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $monthRow['peso'], 3) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="empty-state">No hay registros mensuales para los filtros seleccionados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </details>
            </div>
        </div>

        <div id="commercial-quality" class="report-section">
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
                                @php
                                    $lineEffectiveness = (int) ($lineRow['cantidad'] ?? 0) > 0
                                        ? ((int) ($lineRow['entregados'] ?? 0) / (int) $lineRow['cantidad']) * 100
                                        : 0;
                                @endphp
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

        <div id="commercial-services" class="report-section">
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
            const monthlyRows = @json(collect($monthlyRows ?? [])->values());
            const colors = ['#2364aa', '#25805a', '#bd7c27', '#ba5960', '#7755a6', '#158895', '#d16b3c', '#62758a'];
            const regionalRows = @json($regionalRows);
            const regionalCanvas = document.getElementById('chartCommercialRegionals');
            if (regionalCanvas && regionalRows.length) {
                new Chart(regionalCanvas, {
                    type: 'bar',
                    data: {
                        labels: regionalRows.map(row => row.regional),
                        datasets: [{ label: 'Registros', data: regionalRows.map(row => row.registros), backgroundColor: '#2364aa', borderRadius: 5, maxBarThickness: 24 }]
                    },
                    options: {
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: { legend: { display: false } },
                        scales: { x: { beginAtZero: true, ticks: { precision: 0 } }, y: { grid: { display: false } } }
                    }
                });
            }

            const monthlyCanvas = document.getElementById('chartCommercialMonthly');
            if (monthlyCanvas && monthlyRows.length) {
                new Chart(monthlyCanvas, {
                    type: 'bar',
                    data: {
                        labels: monthlyRows.map(row => row.periodo),
                        datasets: [
                            { type: 'bar', label: 'Registros del mes', data: monthlyRows.map(row => Number(row.registros || 0)), backgroundColor: '#2364aa', borderRadius: 4, maxBarThickness: 32, yAxisID: 'y' },
                            { type: 'bar', label: 'Mes anterior', data: monthlyRows.map(row => Number(row.mes_anterior_registros || 0)), backgroundColor: '#c9d8e8', borderRadius: 4, maxBarThickness: 32, yAxisID: 'y' },
                            { type: 'line', label: 'Variación (%)', data: monthlyRows.map(row => row.variacion_pct === null ? null : Number(row.variacion_pct)), borderColor: '#25805a', backgroundColor: '#25805a', pointRadius: 3, borderWidth: 2, tension: .2, yAxisID: 'y1' }
                        ]
                    },
                    options: {
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 18 } } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#edf1f5' }, title: { display: true, text: 'Registros' } },
                            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Variación (%)' }, ticks: { callback: value => `${value}%` } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }

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
    <script>
        (function () {
            const filterForm = document.getElementById('commercial-performance-filter');
            document.getElementById('commercial-all-regionals')?.addEventListener('click', function () {
                filterForm.querySelectorAll('input[name="regionales[]"]').forEach(input => { input.checked = false; });
            });
            const title = document.getElementById('globalPdfGenerationTitle');
            const message = document.getElementById('globalPdfGenerationMessage');

            filterForm?.addEventListener('submit', function () {
                window.showReportLoadingModal?.('Aplicando filtros', 'Espere por favor, estamos actualizando el reporte.');
            });

            const filenameFrom = (contentDisposition, fallback) => {
                const match = /filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i.exec(contentDisposition || '');
                let value = match?.[2] || fallback;
                if (match?.[1]) {
                    try { value = decodeURIComponent(match[1]); } catch (error) { value = match[1]; }
                }
                return value.replace(/[\\/:*?"<>|]/g, '_');
            };

            const fetchBlob = async (response, format) => {
                if (!response.ok) throw new Error('No se pudo generar el archivo. Intenta de nuevo.');
                const total = Number(response.headers.get('Content-Length') || 0);
                const reader = response.body?.getReader();
                if (!reader) return response.blob();

                const chunks = [];
                let loaded = 0;
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    chunks.push(value);
                    loaded += value.byteLength;
                    const loadedMb = (loaded / 1048576).toFixed(1);
                    const totalMb = (total / 1048576).toFixed(1);
                    if (window.updateReportLoadingModal) {
                        window.updateReportLoadingModal(total > 0
                            ? `Descargando ${format.toUpperCase()}: ${Math.min(100, Math.round((loaded / total) * 100))}% (${loadedMb} de ${totalMb} MB).`
                            : `Descargando ${format.toUpperCase()}: ${loadedMb} MB.`);
                    } else if (message) {
                        message.textContent = `Descargando ${format.toUpperCase()}: ${loadedMb} MB.`;
                    }
                }

                return new Blob(chunks, { type: response.headers.get('Content-Type') || 'application/octet-stream' });
            };

            document.addEventListener('click', async function (event) {
                const link = event.target.closest?.('[data-commercial-export]');
                if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

                event.preventDefault();
                event.stopImmediatePropagation();
                const format = link.dataset.commercialExport;
                const popup = format === 'pdf' ? window.open('about:blank', '_blank') : null;
                const fileTitle = format === 'pdf' ? 'Generando PDF' : 'Generando Excel';
                window.showReportLoadingModal?.(fileTitle, `Estamos preparando el reporte. Espere por favor.`);

                if (format === 'pdf' && !popup) {
                    if (title) title.textContent = 'Permite las ventanas emergentes';
                    if (message) message.textContent = 'El PDF se abrirá en una pestaña nueva. Permite ventanas emergentes para este sitio e intenta de nuevo.';
                    window.setTimeout(() => window.hideReportLoadingModal?.(), 4500);
                    return;
                }

                try {
                    const response = await fetch(link.href, {
                        credentials: 'same-origin',
                        headers: {
                            Accept: format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'X-Pdf-Modal-Ignore': 'true'
                        }
                    });
                    const blob = await fetchBlob(response, format);
                    const filename = filenameFrom(response.headers.get('Content-Disposition'), `rendimiento-servicios.${format === 'pdf' ? 'pdf' : 'xlsx'}`);
                    const objectUrl = URL.createObjectURL(blob);

                    if (format === 'pdf' && popup) {
                        popup.location.href = objectUrl;
                    } else if (format === 'pdf') {
                        const pdfWindow = window.open(objectUrl, '_blank');
                        if (!pdfWindow) throw new Error('El navegador bloqueó la ventana del PDF. Permite las ventanas emergentes e intenta de nuevo.');
                    } else {
                        const download = document.createElement('a');
                        download.href = objectUrl;
                        download.download = filename;
                        document.body.appendChild(download);
                        download.click();
                        download.remove();
                    }

                    window.updateReportLoadingModal?.('Reporte listo.');
                    window.setTimeout(() => {
                        window.hideReportLoadingModal?.();
                    }, 900);
                    window.setTimeout(() => URL.revokeObjectURL(objectUrl), format === 'pdf' ? 60000 : 30000);
                } catch (error) {
                    if (popup && !popup.closed) popup.close();
                    if (title) title.textContent = 'No se pudo generar el reporte';
                    if (message) message.textContent = error.message || 'Ocurrió un problema al preparar el archivo.';
                    window.setTimeout(() => window.hideReportLoadingModal?.(), 4500);
                }
            }, true);
        })();
    </script>
@stop

@extends('adminlte::page')

@section('title', 'Dashboard Corporativo')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Dashboard Corporativo</h1>
            <small class="text-muted">Panel administrativo de operaciones y entregas</small>
        </div>
        <div class="text-muted mt-2 mt-md-0">
            <strong>Rango:</strong> {{ $rangoLabel }}
        </div>
    </div>
@stop

@section('content')
    <div id="dashboardApp">
    <div class="card card-filtro mb-3" data-focus-hide="true">
        <div class="card-header">
            <strong>Filtros y Vista</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard') }}" id="dashboardFiltersForm">
                <input type="hidden" name="range" value="{{ $rangoKey }}">

                <div id="filtersFullContent">
                <div class="row filter-main-grid">
                    <div class="col-lg-7 mb-3">
                        <label class="d-block font-weight-bold">Modulos incluidos</label>
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

                    <div class="col-lg-5 mb-3 d-flex flex-column justify-content-end">
                        <div class="d-flex justify-content-lg-end">
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-primary filter-toggle-btn"
                                id="toggleAdvancedFilters"
                                aria-expanded="true"
                                aria-controls="advancedFiltersPanel"
                            >
                                <i class="fas fa-filter mr-1"></i>
                                <span id="toggleAdvancedFiltersText">Ocultar filtro</span>
                                <i class="fas fa-chevron-up ml-2" id="advancedFiltersChevron"></i>
                            </button>
                        </div>
                        <div class="filter-summary mt-2 d-flex flex-wrap justify-content-lg-end">
                            <span class="badge badge-light border mr-2 mb-1">
                                Desde: {{ $rangoDesde ? \Carbon\Carbon::parse($rangoDesde)->format('d/m/Y') : '-' }}
                            </span>
                            <span class="badge badge-light border mr-2 mb-1">
                                Hasta: {{ $rangoHasta ? \Carbon\Carbon::parse($rangoHasta)->format('d/m/Y') : '-' }}
                            </span>
                            <span class="badge badge-info mb-1">
                                Agrupar: {{ strtoupper($agrupacion) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div id="advancedFiltersPanel">
                    <div class="row">
                        <div class="col-lg-4 mb-3">
                            <label for="from" class="font-weight-bold">Desde</label>
                            <input type="date" id="from" name="from" class="form-control" value="{{ $rangoDesde }}" data-auto-filter="true">
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="to" class="font-weight-bold">Hasta</label>
                            <input type="date" id="to" name="to" class="form-control" value="{{ $rangoHasta }}" data-auto-filter="true">
                        </div>
                        <div class="col-lg-4 mb-3">
                            <label for="group" class="font-weight-bold">Agrupar por</label>
                            <select id="group" name="group" class="form-control" data-auto-filter="true">
                                <option value="day" {{ $agrupacion === 'day' ? 'selected' : '' }}>Dia</option>
                                <option value="week" {{ $agrupacion === 'week' ? 'selected' : '' }}>Semana</option>
                                <option value="month" {{ $agrupacion === 'month' ? 'selected' : '' }}>Mes</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center filters-actions-row">
                    <button type="submit" class="btn btn-primary mr-2 mb-2">Aplicar filtros</button>
                    <button type="submit" name="range" value="today" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">Hoy</button>
                    <button type="submit" name="range" value="7d" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">7 dias</button>
                    <button type="submit" name="range" value="30d" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">30 dias</button>
                    <button type="submit" name="range" value="month" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">Mes actual</button>
                    <button type="submit" name="range" value="all" class="btn btn-outline-secondary mr-2 mb-2" onclick="document.getElementById('from').value='';document.getElementById('to').value='';">Todo historial</button>
                </div>
                </div>

                <div id="filtersCompactBar" class="filter-compact-bar d-none">
                    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
                        <div class="d-flex flex-wrap align-items-center mb-2 mb-lg-0">
                            <span class="badge badge-primary mr-2 mb-1">Filtros ocultos</span>
                            <span class="badge badge-light border mr-2 mb-1">Desde: {{ $rangoDesde ? \Carbon\Carbon::parse($rangoDesde)->format('d/m/Y') : '-' }}</span>
                            <span class="badge badge-light border mr-2 mb-1">Hasta: {{ $rangoHasta ? \Carbon\Carbon::parse($rangoHasta)->format('d/m/Y') : '-' }}</span>
                            <span class="badge badge-info mr-2 mb-1">Agrupar: {{ strtoupper($agrupacion) }}</span>
                            <span class="badge badge-secondary mb-1">Modulos: {{ count($modulosSeleccionados) }}</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" id="expandTopFilters">
                            <i class="fas fa-sliders-h mr-1"></i> Mostrar filtros
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-guia mb-3" data-widget="guia_rapida" data-top-block="guide">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <div class="font-weight-bold mb-1">Guia rapida de uso</div>
                    <div class="text-muted small mb-2">
                        1) Ajusta filtros. 2) Revisa indicadores clave. 3) Cambia tipos de grafico o personaliza bloques.
                    </div>
                    <div class="d-flex flex-wrap">
                        <span class="badge badge-pill badge-light border mr-2 mb-2">
                            Rango: {{ $rangoLabel }}
                        </span>
                        <span class="badge badge-pill badge-light border mr-2 mb-2">
                            Agrupacion: {{ strtoupper($agrupacion) }}
                        </span>
                        @foreach($modulosSeleccionados as $moduloKey)
                            <span class="badge badge-pill badge-info mr-2 mb-2">
                                {{ $modulosDisponibles[$moduloKey]['label'] ?? strtoupper($moduloKey) }}
                            </span>
                        @endforeach
                    </div>
                </div>
                <div class="col-lg-4 mt-3 mt-lg-0">
                    <div class="d-flex flex-wrap justify-content-lg-end">
                        <button type="button" class="btn btn-sm btn-outline-primary mr-2 mb-2" data-preset="cliente">Vista cliente</button>
                        <button type="button" class="btn btn-sm btn-outline-success mr-2 mb-2" data-preset="operativa">Vista operativa</button>
                        <button type="button" class="btn btn-sm btn-outline-dark mr-2 mb-2" data-preset="completa">Vista completa</button>
                        <button type="button" class="btn btn-sm btn-outline-info mr-2 mb-2" id="toggleFocusMode">Modo enfoque</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary mr-2 mb-2" id="toggleDensity">Vista compacta</button>
                        <button type="button" class="btn btn-sm btn-outline-primary mr-2 mb-2" id="replayCounters">Animar KPIs</button>
                        <span class="badge badge-success align-self-center mb-2 py-2 px-3">
                            <i class="fas fa-sync-alt mr-1"></i> KPI automatico
                        </span>
                    </div>
                    <small id="dashboardPresetStatus" class="text-muted d-block text-lg-right"></small>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-personalizar mb-3" data-focus-hide="true" data-top-block="customize">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Personalizar Dashboard</strong>
            <button
                class="btn btn-sm btn-outline-secondary"
                type="button"
                data-toggle="collapse"
                data-target="#dashboardCustomizePanel"
                aria-expanded="false"
                aria-controls="dashboardCustomizePanel"
            >
                Mostrar/Ocultar
            </button>
        </div>
        <div id="dashboardCustomizePanel" class="collapse show">
            <div class="card-body">
                <div class="alert alert-light border py-2 mb-3">
                    Activa o desactiva bloques y columnas. La configuracion se guarda automaticamente para este usuario.
                </div>
                <div class="row">
                    <div class="col-lg-7">
                        <label class="font-weight-bold d-block mb-2">Bloques visibles</label>
                        <div class="d-flex flex-wrap">
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_guia_rapida" data-setting-widget="guia_rapida">
                                <label class="custom-control-label" for="cfg_guia_rapida">Guia rapida</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_cards_principales" data-setting-widget="cards_principales">
                                <label class="custom-control-label" for="cfg_cards_principales">Tarjetas principales</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_cards_periodo" data-setting-widget="cards_periodo">
                                <label class="custom-control-label" for="cfg_cards_periodo">Tarjetas por periodo</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_alertas_operativas" data-setting-widget="alertas_operativas">
                                <label class="custom-control-label" for="cfg_alertas_operativas">Alertas operativas</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_ayuda_graficos" data-setting-widget="ayuda_graficos">
                                <label class="custom-control-label" for="cfg_ayuda_graficos">Ayuda de graficos</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_chart_modulos" data-setting-widget="chart_modulos">
                                <label class="custom-control-label" for="cfg_chart_modulos">Grafico distribucion</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_chart_estados" data-setting-widget="chart_estados">
                                <label class="custom-control-label" for="cfg_chart_estados">Grafico estados</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_chart_tendencia" data-setting-widget="chart_tendencia">
                                <label class="custom-control-label" for="cfg_chart_tendencia">Grafico tendencia</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_chart_versus" data-setting-widget="chart_versus">
                                <label class="custom-control-label" for="cfg_chart_versus">Grafico versus (entregados vs pendientes)</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_tabla_resumen" data-setting-widget="tabla_resumen">
                                <label class="custom-control-label" for="cfg_tabla_resumen">Tabla resumen</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_ranking_entregadores" data-setting-widget="ranking_entregadores">
                                <label class="custom-control-label" for="cfg_ranking_entregadores">Top entregadores</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_ranking_registradores" data-setting-widget="ranking_registradores">
                                <label class="custom-control-label" for="cfg_ranking_registradores">Top registradores</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <label class="font-weight-bold d-block mb-2">Columnas de tabla resumen</label>
                        <div class="d-flex flex-wrap">
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_col_registrados" data-setting-column="registrados">
                                <label class="custom-control-label" for="cfg_col_registrados">Registrados</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_col_entregados" data-setting-column="entregados">
                                <label class="custom-control-label" for="cfg_col_entregados">Entregados</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_col_pendientes" data-setting-column="pendientes">
                                <label class="custom-control-label" for="cfg_col_pendientes">Pendientes</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_col_atrasados" data-setting-column="atrasados">
                                <label class="custom-control-label" for="cfg_col_atrasados">Con retraso</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_col_rezago" data-setting-column="rezago">
                                <label class="custom-control-label" for="cfg_col_rezago">Rezago</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_col_tasa" data-setting-column="tasa_entrega">
                                <label class="custom-control-label" for="cfg_col_tasa">Tasa entrega</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_col_peso" data-setting-column="peso_total">
                                <label class="custom-control-label" for="cfg_col_peso">Peso</label>
                            </div>
                            <div class="custom-control custom-checkbox mr-4 mb-2">
                                <input type="checkbox" class="custom-control-input" id="cfg_col_ingresos" data-setting-column="ingresos">
                                <label class="custom-control-label" for="cfg_col_ingresos">Ingresos</label>
                            </div>
                        </div>
                        <button id="resetDashboardConfig" type="button" class="btn btn-outline-primary btn-sm mt-2">
                            Restablecer configuracion
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row" data-widget="cards_principales">
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-navy">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-value js-counter" data-counter-target="{{ $totales['paquetes'] }}" data-counter-format="int">{{ number_format($totales['paquetes']) }}</div>
                        <div class="metric-label">Registrados</div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-boxes"></i></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-green">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-value js-counter" data-counter-target="{{ $totales['entregados'] }}" data-counter-format="int">{{ number_format($totales['entregados']) }}</div>
                        <div class="metric-label">Entregados ({{ number_format($totales['porcentaje_entrega'], 1) }}%)</div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-check-circle"></i></div>
                </div>
                <div class="metric-progress">
                    <div class="metric-progress-bar" style="width: {{ min(100, max(0, $totales['porcentaje_entrega'])) }}%;"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-orange">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-value js-counter" data-counter-target="{{ $totales['pendientes'] }}" data-counter-format="int">{{ number_format($totales['pendientes']) }}</div>
                        <div class="metric-label">Pendientes</div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-hourglass-half"></i></div>
                </div>
                <div class="metric-progress">
                    <div class="metric-progress-bar" style="width: {{ min(100, max(0, 100 - $totales['porcentaje_entrega'])) }}%;"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card metric-blue">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="metric-value">Bs <span class="js-counter" data-counter-target="{{ $totales['ingresos'] }}" data-counter-format="money">{{ number_format($totales['ingresos'], 2) }}</span></div>
                        <div class="metric-label">Ingresos</div>
                    </div>
                    <div class="metric-icon"><i class="fas fa-coins"></i></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-1" data-widget="cards_periodo">
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini">
                <small>Registrados Hoy</small>
                <h4 class="js-counter" data-counter-target="{{ $kpisPeriodo['registros']['dia'] }}" data-counter-format="int">{{ number_format($kpisPeriodo['registros']['dia']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini">
                <small>Registrados Semana</small>
                <h4 class="js-counter" data-counter-target="{{ $kpisPeriodo['registros']['semana'] }}" data-counter-format="int">{{ number_format($kpisPeriodo['registros']['semana']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini">
                <small>Registrados Mes</small>
                <h4 class="js-counter" data-counter-target="{{ $kpisPeriodo['registros']['mes'] }}" data-counter-format="int">{{ number_format($kpisPeriodo['registros']['mes']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini kpi-ok">
                <small>Entregados Hoy</small>
                <h4 class="js-counter" data-counter-target="{{ $kpisPeriodo['entregas']['dia'] }}" data-counter-format="int">{{ number_format($kpisPeriodo['entregas']['dia']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini kpi-ok">
                <small>Entregados Semana</small>
                <h4 class="js-counter" data-counter-target="{{ $kpisPeriodo['entregas']['semana'] }}" data-counter-format="int">{{ number_format($kpisPeriodo['entregas']['semana']) }}</h4>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6">
            <div class="kpi-mini kpi-ok">
                <small>Entregados Mes</small>
                <h4 class="js-counter" data-counter-target="{{ $kpisPeriodo['entregas']['mes'] }}" data-counter-format="int">{{ number_format($kpisPeriodo['entregas']['mes']) }}</h4>
            </div>
        </div>
    </div>

    <div class="row mt-1" data-widget="alertas_operativas">
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

    <div class="alert alert-light border mb-3" data-widget="ayuda_graficos">
        <strong>Como leer este panel:</strong>
        <span class="badge badge-success ml-1">Verde = entregado</span>
        <span class="badge badge-warning ml-1">Amarillo = pendiente</span>
        <span class="badge badge-danger ml-1">Rojo = rezago</span>
        <span class="ml-2 text-muted">Usa el boton de expandir para ver un grafico grande y cierra con ESC.</span>
    </div>

    <div class="row">
        <div class="col-lg-5" data-widget="chart_modulos">
            <div class="card chart-card" id="cardChartModulos">
                <div class="card-header chart-header-flex">
                    <div>
                        <strong>Distribucion por modulo</strong>
                        <div class="chart-helper">Muestra donde se concentra el volumen total.</div>
                    </div>
                    <div class="chart-type-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary chart-action-btn" data-chart-download="chartModulos" title="Descargar PNG">
                            <i class="fas fa-download"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary chart-action-btn" data-chart-fullscreen="cardChartModulos" title="Pantalla completa">
                            <i class="fas fa-expand"></i>
                        </button>
                        <label class="chart-type-label mb-0">Tipo</label>
                        <select id="chartModulosType" class="form-control form-control-sm chart-type-select">
                            <option value="doughnut">Donut</option>
                            <option value="pie">Torta</option>
                            <option value="bar">Barras</option>
                            <option value="polarArea">Polar</option>
                            <option value="line">Lineal</option>
                            <option value="radar">Radar</option>
                        </select>
                    </div>
                </div>
                <div class="card-body chart-canvas-body chart-canvas-body-md">
                    <canvas id="chartModulos" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-7" data-widget="chart_estados">
            <div class="card chart-card" id="cardChartEstados">
                <div class="card-header chart-header-flex">
                    <div>
                        <strong>Estado operativo por modulo</strong>
                        <div class="chart-helper">Compara entregados, pendientes y rezago por modulo.</div>
                    </div>
                    <div class="chart-type-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary chart-action-btn" data-chart-download="chartEstados" title="Descargar PNG">
                            <i class="fas fa-download"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary chart-action-btn" data-chart-fullscreen="cardChartEstados" title="Pantalla completa">
                            <i class="fas fa-expand"></i>
                        </button>
                        <label class="chart-type-label mb-0">Tipo</label>
                        <select id="chartEstadosType" class="form-control form-control-sm chart-type-select">
                            <option value="bar">Barras</option>
                            <option value="bar_h">Barras horizontales</option>
                            <option value="line">Lineal</option>
                            <option value="area">Area</option>
                            <option value="radar">Radar</option>
                        </select>
                    </div>
                </div>
                <div class="card-body chart-canvas-body chart-canvas-body-md">
                    <canvas id="chartEstados" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card chart-card" data-widget="chart_tendencia" id="cardChartTendencia">
        <div class="card-header chart-header-flex">
            <div>
                <strong>Tendencia de Registros vs Entregas</strong>
                <div class="text-muted small">{{ $rangoTendenciaLabel }} | Agrupacion: {{ strtoupper($agrupacion) }}</div>
                <div class="chart-helper">Si la linea azul sube mas que la verde, se acumulan pendientes.</div>
            </div>
            <div class="chart-type-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary chart-action-btn" data-chart-download="chartTendencia" title="Descargar PNG">
                    <i class="fas fa-download"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary chart-action-btn" data-chart-fullscreen="cardChartTendencia" title="Pantalla completa">
                    <i class="fas fa-expand"></i>
                </button>
                <label class="chart-type-label mb-0">Tipo</label>
                <select id="chartTendenciaType" class="form-control form-control-sm chart-type-select">
                    <option value="line">Lineal</option>
                    <option value="bar">Barras</option>
                    <option value="bar_h">Barras horizontales</option>
                    <option value="area">Area</option>
                    <option value="radar">Radar</option>
                </select>
            </div>
        </div>
        <div class="card-body chart-canvas-body chart-canvas-body-lg">
            <canvas id="chartTendencia" height="110"></canvas>
        </div>
    </div>

    <div class="card chart-card" data-widget="chart_versus" id="cardChartVersus">
        <div class="card-header chart-header-flex">
            <div>
                <strong>Versus General: Entregados vs Pendientes</strong>
                <div class="text-muted small">Comparativo directo del total filtrado</div>
                <div class="chart-helper">Lectura rapida del cumplimiento general.</div>
                <div class="d-flex flex-wrap mt-1">
                    <span class="badge badge-success mr-2 mb-1">Entregados: {{ number_format($totales['entregados']) }}</span>
                    <span class="badge badge-warning mr-2 mb-1">Pendientes: {{ number_format($totales['pendientes']) }}</span>
                    <span class="badge badge-primary mb-1">Cumplimiento: {{ number_format($totales['porcentaje_entrega'], 1) }}%</span>
                </div>
            </div>
            <div class="chart-type-wrap">
                <button type="button" class="btn btn-sm btn-outline-secondary chart-action-btn" data-chart-download="chartVersus" title="Descargar PNG">
                    <i class="fas fa-download"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary chart-action-btn" data-chart-fullscreen="cardChartVersus" title="Pantalla completa">
                    <i class="fas fa-expand"></i>
                </button>
                <label class="chart-type-label mb-0">Tipo</label>
                <select id="chartVersusType" class="form-control form-control-sm chart-type-select">
                    <option value="doughnut">Donut</option>
                    <option value="pie">Torta</option>
                    <option value="bar">Barras</option>
                    <option value="bar_h">Barras horizontales</option>
                    <option value="line">Lineal</option>
                    <option value="area">Area</option>
                    <option value="radar">Radar</option>
                    <option value="polarArea">Polar</option>
                </select>
            </div>
        </div>
        <div class="card-body chart-canvas-body chart-canvas-body-sm">
            <canvas id="chartVersus" height="100"></canvas>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card" data-widget="tabla_resumen">
                <div class="card-header">
                    <strong>Resumen ejecutivo por modulo</strong>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Modulo</th>
                                <th class="text-right" data-col="registrados">Registrados</th>
                                <th class="text-right" data-col="entregados">Entregados</th>
                                <th class="text-right" data-col="pendientes">Pendientes</th>
                                <th class="text-right" data-col="atrasados">Con retraso</th>
                                <th class="text-right" data-col="rezago">Rezago</th>
                                <th class="text-right" data-col="tasa_entrega">Tasa entrega</th>
                                <th class="text-right" data-col="peso_total">Peso</th>
                                <th class="text-right" data-col="ingresos">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($resumenPorModulo as $fila)
                                <tr>
                                    <td><strong>{{ $fila['label'] }}</strong></td>
                                    <td class="text-right" data-col="registrados">{{ number_format($fila['total']) }}</td>
                                    <td class="text-right text-success" data-col="entregados">{{ number_format($fila['entregados']) }}</td>
                                    <td class="text-right" data-col="pendientes">{{ number_format($fila['pendientes']) }}</td>
                                    <td class="text-right text-warning" data-col="atrasados">{{ number_format($fila['atrasados']) }}</td>
                                    <td class="text-right text-danger" data-col="rezago">{{ number_format($fila['rezago']) }}</td>
                                    <td class="text-right" data-col="tasa_entrega">
                                        <div class="tasa-entrega-wrap">
                                            <span>{{ number_format($fila['tasa_entrega'], 1) }}%</span>
                                            <div class="tasa-entrega-bar">
                                                <div class="tasa-entrega-fill" style="width: {{ min(100, max(0, $fila['tasa_entrega'])) }}%;"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-right" data-col="peso_total">{{ number_format($fila['peso_total'], 3) }}</td>
                                    <td class="text-right" data-col="ingresos">Bs {{ number_format($fila['ingresos'], 2) }}</td>
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
            <div class="card mb-3" data-widget="ranking_entregadores">
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

            <div class="card" data-widget="ranking_registradores">
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
        <div class="alert alert-warning" data-widget="alerta_catalogo">
            <strong>Advertencia de configuracion:</strong>
            @if(!$estadoEntregadoDisponible)
                No existe estado <strong>ENTREGADO</strong> en catalogo.
            @endif
            @if(!$estadoRezagoDisponible)
                No existe estado <strong>REZAGO</strong> en catalogo.
            @endif
        </div>
    @endif

    </div>
    @include('footer')
@stop

@section('css')
    <style>
        #dashboardApp {
            animation: dashboardFadeIn .35s ease-out;
        }
        @keyframes dashboardFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        #dashboardApp .card {
            border-radius: 10px;
            box-shadow: 0 5px 14px rgba(13, 32, 67, .06);
            border: 1px solid #e9eef6;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        #dashboardApp .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 9px 22px rgba(13, 32, 67, .10);
        }
        .card-filtro {
            border-top: 3px solid #20539a;
            background: linear-gradient(180deg, #fcfdff 0%, #ffffff 100%);
        }
        .filter-toggle-btn {
            font-weight: 700;
            border-radius: 9px;
            min-width: 130px;
        }
        .filter-summary .badge {
            font-size: .78rem;
        }
        .filter-compact-bar {
            border: 1px dashed #b8c8e6;
            border-radius: 10px;
            padding: 10px 12px;
            background: #f7faff;
        }
        #dashboardApp.top-filters-collapsed #filtersFullContent {
            display: none;
        }
        #dashboardApp.top-filters-collapsed [data-top-block="guide"],
        #dashboardApp.top-filters-collapsed [data-top-block="customize"] {
            display: none !important;
        }
        .card-guia {
            border-top: 3px solid #6f42c1;
            background: linear-gradient(180deg, #fbf9ff 0%, #ffffff 90%);
        }
        .card-personalizar {
            border-top: 3px solid #0f7b6c;
        }
        .metric-card {
            border-radius: 12px;
            color: #fff;
            padding: 18px 20px;
            margin-bottom: 12px;
            box-shadow: 0 10px 22px rgba(0, 0, 0, .16);
            position: relative;
            overflow: hidden;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 24px rgba(0, 0, 0, .22);
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
        .metric-icon {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .22);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
        }
        .metric-progress {
            margin-top: 9px;
            height: 6px;
            border-radius: 8px;
            background: rgba(255, 255, 255, .20);
            overflow: hidden;
        }
        .metric-progress-bar {
            height: 100%;
            border-radius: 8px;
            background: rgba(255, 255, 255, .90);
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
        .chart-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .chart-type-wrap {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .chart-type-label {
            font-size: .78rem;
            text-transform: uppercase;
            color: #6c757d;
            font-weight: 700;
            letter-spacing: .02em;
        }
        .chart-type-select {
            min-width: 130px;
        }
        .chart-action-btn {
            border-radius: 8px;
            width: 30px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .tasa-entrega-wrap {
            min-width: 120px;
        }
        .chart-canvas-body {
            position: relative;
            overflow: hidden;
        }
        .chart-canvas-body-sm {
            height: 230px;
        }
        .chart-canvas-body-md {
            height: 320px;
        }
        .chart-canvas-body-lg {
            height: 340px;
        }
        .chart-canvas-body canvas {
            width: 100% !important;
            height: 100% !important;
        }
        .chart-card:fullscreen,
        .chart-card.chart-fullscreen-active {
            background: #fff;
            box-shadow: none;
            margin: 0;
            border-radius: 0;
            padding: 6px;
        }
        .chart-card:fullscreen .chart-canvas-body,
        .chart-card.chart-fullscreen-active .chart-canvas-body {
            height: calc(100vh - 170px) !important;
        }
        .chart-card .card-header .chart-helper {
            font-size: .75rem;
            color: #6c757d;
            font-weight: 600;
        }
        .tasa-entrega-bar {
            margin-top: 4px;
            width: 100%;
            height: 6px;
            border-radius: 8px;
            background: #e8edf4;
            overflow: hidden;
        }
        .tasa-entrega-fill {
            height: 100%;
            border-radius: 8px;
            background: linear-gradient(90deg, #28a745 0%, #1f8f42 100%);
        }
        #dashboardPresetStatus {
            min-height: 18px;
            font-weight: 600;
        }
        #dashboardApp.dashboard-focus-mode [data-focus-hide="true"] {
            display: none !important;
        }
        #dashboardApp.dashboard-compact-mode .card-body {
            padding: .7rem .85rem;
        }
        #dashboardApp.dashboard-compact-mode .metric-card {
            padding: 14px 15px;
        }
        #dashboardApp.dashboard-compact-mode .metric-value {
            font-size: 1.45rem;
        }
        #dashboardApp.dashboard-compact-mode .kpi-mini {
            min-height: 68px;
            padding: 8px 10px;
        }
        @media (max-width: 768px) {
            .chart-action-btn {
                width: 28px;
                height: 28px;
            }
            .chart-canvas-body-md,
            .chart-canvas-body-lg {
                height: 260px;
            }
            .chart-canvas-body-sm {
                height: 220px;
            }
            #dashboardApp .card:hover {
                transform: none;
            }
        }
    </style>
@stop

@section('js')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const chartModulosData = @json($chartModulos);
        const chartEstadosData = @json($chartEstados);
        const chartVersusData = @json($chartVersus);
        const trendLabels = @json($trendLabels);
        const trendSeries = @json($trendSeries);

        let chartModulos = null;
        let chartEstados = null;
        let chartVersus = null;
        let chartTendencia = null;

        const colors = ['#20539a', '#28a745', '#f39c12', '#17a2b8'];

        const selectModulos = document.getElementById('chartModulosType');
        const selectEstados = document.getElementById('chartEstadosType');
        const selectVersus = document.getElementById('chartVersusType');
        const selectTendencia = document.getElementById('chartTendenciaType');
        const settingsWidgetChecks = document.querySelectorAll('[data-setting-widget]');
        const settingsColumnChecks = document.querySelectorAll('[data-setting-column]');
        const resetDashboardConfigBtn = document.getElementById('resetDashboardConfig');
        const presetButtons = document.querySelectorAll('[data-preset]');
        const dashboardPresetStatus = document.getElementById('dashboardPresetStatus');
        const dashboardApp = document.getElementById('dashboardApp');
        const toggleFocusModeBtn = document.getElementById('toggleFocusMode');
        const toggleDensityBtn = document.getElementById('toggleDensity');
        const replayCountersBtn = document.getElementById('replayCounters');
        const chartDownloadButtons = document.querySelectorAll('[data-chart-download]');
        const chartFullscreenButtons = document.querySelectorAll('[data-chart-fullscreen]');
        const chartCards = document.querySelectorAll('.chart-card');
        const dashboardFiltersForm = document.getElementById('dashboardFiltersForm');
        const advancedFiltersPanel = document.getElementById('advancedFiltersPanel');
        const toggleAdvancedFiltersBtn = document.getElementById('toggleAdvancedFilters');
        const toggleAdvancedFiltersText = document.getElementById('toggleAdvancedFiltersText');
        const advancedFiltersChevron = document.getElementById('advancedFiltersChevron');
        const autoFilterFields = document.querySelectorAll('[data-auto-filter="true"]');
        const filtersCompactBar = document.getElementById('filtersCompactBar');
        const expandTopFiltersBtn = document.getElementById('expandTopFilters');

        const DASHBOARD_CONFIG_KEY = 'dash_dashboard_config_v1';
        const DASHBOARD_FOCUS_KEY = 'dash_dashboard_focus_mode';
        const DASHBOARD_COMPACT_KEY = 'dash_dashboard_compact_mode';
        const DASHBOARD_TOP_FILTERS_COLLAPSED_KEY = 'dash_dashboard_top_filters_collapsed';
        const KPI_AUTO_ANIMATION_INTERVAL_MS = 45000;
        const defaultDashboardConfig = {
            widgets: {
                guia_rapida: true,
                cards_principales: true,
                cards_periodo: true,
                alertas_operativas: true,
                ayuda_graficos: true,
                chart_modulos: true,
                chart_estados: true,
                chart_tendencia: true,
                chart_versus: true,
                tabla_resumen: true,
                ranking_entregadores: true,
                ranking_registradores: true,
                alerta_catalogo: true,
            },
            columns: {
                registrados: true,
                entregados: true,
                pendientes: true,
                atrasados: true,
                rezago: true,
                tasa_entrega: true,
                peso_total: true,
                ingresos: true,
            }
        };

        const cloneDefaultDashboardConfig = () => JSON.parse(JSON.stringify(defaultDashboardConfig));
        const mergeConfigWithDefault = (stored) => {
            const merged = cloneDefaultDashboardConfig();
            if (!stored || typeof stored !== 'object') {
                return merged;
            }
            if (stored.widgets && typeof stored.widgets === 'object') {
                Object.keys(merged.widgets).forEach((key) => {
                    if (typeof stored.widgets[key] === 'boolean') {
                        merged.widgets[key] = stored.widgets[key];
                    }
                });
            }
            if (stored.columns && typeof stored.columns === 'object') {
                Object.keys(merged.columns).forEach((key) => {
                    if (typeof stored.columns[key] === 'boolean') {
                        merged.columns[key] = stored.columns[key];
                    }
                });
            }
            return merged;
        };

        const loadDashboardConfig = () => {
            try {
                const parsed = JSON.parse(localStorage.getItem(DASHBOARD_CONFIG_KEY) || '{}');
                return mergeConfigWithDefault(parsed);
            } catch (error) {
                return cloneDefaultDashboardConfig();
            }
        };

        let dashboardConfig = loadDashboardConfig();

        const saveDashboardConfig = () => {
            localStorage.setItem(DASHBOARD_CONFIG_KEY, JSON.stringify(dashboardConfig));
        };

        const applyWidgetVisibility = () => {
            document.querySelectorAll('[data-widget]').forEach((element) => {
                const key = element.getAttribute('data-widget');
                const visible = dashboardConfig.widgets[key] !== false;
                element.classList.toggle('d-none', !visible);
            });
        };

        const applyColumnVisibility = () => {
            document.querySelectorAll('[data-col]').forEach((element) => {
                const key = element.getAttribute('data-col');
                const visible = dashboardConfig.columns[key] !== false;
                element.classList.toggle('d-none', !visible);
            });
        };

        const syncSettingsChecks = () => {
            settingsWidgetChecks.forEach((checkbox) => {
                const key = checkbox.getAttribute('data-setting-widget');
                checkbox.checked = dashboardConfig.widgets[key] !== false;
            });
            settingsColumnChecks.forEach((checkbox) => {
                const key = checkbox.getAttribute('data-setting-column');
                checkbox.checked = dashboardConfig.columns[key] !== false;
            });
        };

        const applyDashboardConfig = () => {
            applyWidgetVisibility();
            applyColumnVisibility();
        };

        let isTopFiltersCollapsed = localStorage.getItem(DASHBOARD_TOP_FILTERS_COLLAPSED_KEY) === '1';

        const applyTopFiltersState = () => {
            if (!dashboardApp) {
                return;
            }

            dashboardApp.classList.toggle('top-filters-collapsed', isTopFiltersCollapsed);

            if (filtersCompactBar) {
                filtersCompactBar.classList.toggle('d-none', !isTopFiltersCollapsed);
            }

            if (toggleAdvancedFiltersBtn) {
                toggleAdvancedFiltersBtn.setAttribute('aria-expanded', isTopFiltersCollapsed ? 'false' : 'true');
                toggleAdvancedFiltersBtn.classList.toggle('btn-outline-primary', !isTopFiltersCollapsed);
                toggleAdvancedFiltersBtn.classList.toggle('btn-primary', isTopFiltersCollapsed);
            }

            if (toggleAdvancedFiltersText) {
                toggleAdvancedFiltersText.textContent = isTopFiltersCollapsed ? 'Mostrar filtro' : 'Ocultar filtro';
            }

            if (advancedFiltersChevron) {
                advancedFiltersChevron.classList.toggle('fa-chevron-up', !isTopFiltersCollapsed);
                advancedFiltersChevron.classList.toggle('fa-chevron-down', isTopFiltersCollapsed);
            }

            if (advancedFiltersPanel) {
                advancedFiltersPanel.classList.toggle('show', !isTopFiltersCollapsed);
            }

            setTimeout(resizeAllCharts, 120);
        };

        const submitFiltersWithMessage = (message = 'Aplicando filtros...') => {
            if (!dashboardFiltersForm) {
                return;
            }

            if (dashboardPresetStatus) {
                dashboardPresetStatus.textContent = message;
            }

            dashboardFiltersForm.submit();
        };

        const getRenderedCharts = () => [chartModulos, chartEstados, chartVersus, chartTendencia].filter(Boolean);

        const resizeAllCharts = () => {
            getRenderedCharts().forEach((chart) => {
                if (!chart || !chart.canvas) {
                    return;
                }

                chart.canvas.style.width = '';
                chart.canvas.style.height = '';
                chart.resize();
            });
        };

        const forceRestoreChartLayout = () => {
            resizeAllCharts();
            setTimeout(resizeAllCharts, 120);
            setTimeout(resizeAllCharts, 300);
        };

        const updateFullscreenButtons = (activeCardId = '') => {
            chartFullscreenButtons.forEach((button) => {
                const targetId = button.getAttribute('data-chart-fullscreen') || '';
                const icon = button.querySelector('i');
                const isActive = activeCardId !== '' && targetId === activeCardId;
                button.setAttribute('title', isActive ? 'Salir de pantalla completa' : 'Pantalla completa');
                if (icon) {
                    icon.classList.toggle('fa-expand', !isActive);
                    icon.classList.toggle('fa-compress', isActive);
                }
            });
        };

        const formatCounterValue = (value, format) => {
            if (format === 'money') {
                return Number(value).toLocaleString('es-BO', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            return Math.round(value).toLocaleString('es-BO');
        };

        const runCounterAnimation = () => {
            document.querySelectorAll('.js-counter').forEach((element) => {
                const target = Number(element.getAttribute('data-counter-target') || '0');
                const format = element.getAttribute('data-counter-format') || 'int';
                const duration = 750;
                const start = performance.now();

                const tick = (now) => {
                    const progress = Math.min(1, (now - start) / duration);
                    const eased = 1 - Math.pow(1 - progress, 3);
                    const current = target * eased;
                    element.textContent = formatCounterValue(current, format);

                    if (progress < 1) {
                        requestAnimationFrame(tick);
                    }
                };

                requestAnimationFrame(tick);
            });
        };

        let isFocusMode = localStorage.getItem(DASHBOARD_FOCUS_KEY) === '1';
        let isCompactMode = localStorage.getItem(DASHBOARD_COMPACT_KEY) === '1';

        const applyVisualModes = () => {
            if (!dashboardApp) {
                return;
            }

            dashboardApp.classList.toggle('dashboard-focus-mode', isFocusMode);
            dashboardApp.classList.toggle('dashboard-compact-mode', isCompactMode);

            if (toggleFocusModeBtn) {
                toggleFocusModeBtn.textContent = isFocusMode ? 'Salir enfoque' : 'Modo enfoque';
                toggleFocusModeBtn.classList.toggle('btn-outline-info', !isFocusMode);
                toggleFocusModeBtn.classList.toggle('btn-secondary', isFocusMode);
            }

            if (toggleDensityBtn) {
                toggleDensityBtn.textContent = isCompactMode ? 'Vista normal' : 'Vista compacta';
                toggleDensityBtn.classList.toggle('btn-outline-secondary', !isCompactMode);
                toggleDensityBtn.classList.toggle('btn-secondary', isCompactMode);
            }

            setTimeout(resizeAllCharts, 80);
        };

        const applyDashboardPreset = (preset) => {
            const config = cloneDefaultDashboardConfig();

            if (preset === 'cliente') {
                config.widgets = {
                    guia_rapida: true,
                    cards_principales: true,
                    cards_periodo: true,
                    alertas_operativas: true,
                    ayuda_graficos: false,
                    chart_modulos: true,
                    chart_estados: true,
                    chart_tendencia: false,
                    chart_versus: true,
                    tabla_resumen: true,
                    ranking_entregadores: false,
                    ranking_registradores: false,
                    alerta_catalogo: true,
                };
                config.columns = {
                    registrados: true,
                    entregados: true,
                    pendientes: true,
                    atrasados: false,
                    rezago: true,
                    tasa_entrega: true,
                    peso_total: false,
                    ingresos: true,
                };
            } else if (preset === 'operativa') {
                config.widgets = {
                    guia_rapida: true,
                    cards_principales: true,
                    cards_periodo: true,
                    alertas_operativas: true,
                    ayuda_graficos: true,
                    chart_modulos: true,
                    chart_estados: true,
                    chart_tendencia: true,
                    chart_versus: true,
                    tabla_resumen: true,
                    ranking_entregadores: true,
                    ranking_registradores: true,
                    alerta_catalogo: true,
                };
                config.columns = {
                    registrados: true,
                    entregados: true,
                    pendientes: true,
                    atrasados: true,
                    rezago: true,
                    tasa_entrega: true,
                    peso_total: true,
                    ingresos: true,
                };
            }

            dashboardConfig = config;
            saveDashboardConfig();
            syncSettingsChecks();
            applyDashboardConfig();

            if (dashboardPresetStatus) {
                const labels = {
                    cliente: 'Vista cliente aplicada',
                    operativa: 'Vista operativa aplicada',
                    completa: 'Vista completa aplicada',
                };
                dashboardPresetStatus.textContent = labels[preset] || 'Vista aplicada';
                setTimeout(() => {
                    if (dashboardPresetStatus.textContent === labels[preset]) {
                        dashboardPresetStatus.textContent = '';
                    }
                }, 2600);
            }
        };

        const safeType = (value, allowed, fallback) => allowed.includes(value) ? value : fallback;
        const savedModulos = safeType(localStorage.getItem('dash_chart_modulos') || 'doughnut', ['doughnut', 'pie', 'bar', 'polarArea', 'line', 'radar'], 'doughnut');
        const savedEstados = safeType(localStorage.getItem('dash_chart_estados') || 'bar', ['bar', 'line', 'radar', 'bar_h', 'area'], 'bar');
        const savedVersus = safeType(localStorage.getItem('dash_chart_versus') || 'doughnut', ['doughnut', 'pie', 'bar', 'bar_h', 'line', 'area', 'radar', 'polarArea'], 'doughnut');
        const savedTendencia = safeType(localStorage.getItem('dash_chart_tendencia') || 'line', ['line', 'bar', 'area', 'radar', 'bar_h'], 'line');

        if (selectModulos) {
            selectModulos.value = savedModulos;
        }
        if (selectEstados) {
            selectEstados.value = savedEstados;
        }
        if (selectVersus) {
            selectVersus.value = savedVersus;
        }
        if (selectTendencia) {
            selectTendencia.value = savedTendencia;
        }

        syncSettingsChecks();
        applyDashboardConfig();
        applyTopFiltersState();
        applyVisualModes();
        runCounterAnimation();
        updateFullscreenButtons();

        function renderChartModulos(type) {
            if (chartModulos) {
                chartModulos.destroy();
            }

            const isCartesian = ['bar', 'line'].includes(type);
            const isRadar = type === 'radar';
            const dataset = {
                label: 'Paquetes',
                data: chartModulosData.totales,
                backgroundColor: isCartesian ? '#20539a' : (isRadar ? 'rgba(32, 83, 154, 0.25)' : colors),
                borderColor: isCartesian || isRadar ? '#20539a' : '#fff',
                borderWidth: 1,
            };

            if (type === 'line') {
                dataset.fill = false;
                dataset.tension = .25;
            }
            if (isRadar) {
                dataset.fill = true;
            }

            const options = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            };

            if (isCartesian) {
                options.scales = {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                };
            } else if (isRadar) {
                options.scales = {
                    r: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                };
            }

            chartModulos = new Chart(document.getElementById('chartModulos'), {
                type,
                data: {
                    labels: chartModulosData.labels,
                    datasets: [dataset]
                },
                options
            });
        }

        function renderChartEstados(type) {
            if (chartEstados) {
                chartEstados.destroy();
            }

            const chartType = type === 'area' ? 'line' : (type === 'bar_h' ? 'bar' : type);
            const datasets = [
                { label: 'Entregados', data: chartEstadosData.entregados, backgroundColor: '#28a745', borderColor: '#28a745' },
                { label: 'Pendientes', data: chartEstadosData.pendientes, backgroundColor: '#f39c12', borderColor: '#f39c12' },
                { label: 'Rezago', data: chartEstadosData.rezago, backgroundColor: '#dc3545', borderColor: '#dc3545' },
            ];

            if (chartType === 'line') {
                datasets.forEach((dataset) => {
                    dataset.fill = type === 'area';
                    dataset.tension = .25;
                    if (type === 'area') {
                        dataset.backgroundColor = dataset.backgroundColor + '33';
                    }
                });
            }

            if (chartType === 'radar') {
                datasets.forEach((dataset) => {
                    dataset.fill = true;
                    dataset.backgroundColor = dataset.backgroundColor + '33';
                });
            }

            const options = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            };

            if (chartType === 'bar') {
                options.scales = {
                    x: { stacked: true },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } }
                };
                if (type === 'bar_h') {
                    options.indexAxis = 'y';
                }
            } else if (chartType === 'line') {
                options.interaction = { mode: 'index', intersect: false };
                options.scales = {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                };
            } else if (chartType === 'radar') {
                options.scales = {
                    r: { beginAtZero: true, ticks: { precision: 0 } }
                };
            }

            chartEstados = new Chart(document.getElementById('chartEstados'), {
                type: chartType,
                data: {
                    labels: chartEstadosData.labels,
                    datasets
                },
                options
            });
        }

        function renderChartVersus(typeChoice) {
            if (chartVersus) {
                chartVersus.destroy();
            }

            const chartType = typeChoice === 'area' ? 'line' : (typeChoice === 'bar_h' ? 'bar' : typeChoice);
            const isCartesian = ['bar', 'line'].includes(chartType);
            const isRadar = chartType === 'radar';
            const isArea = typeChoice === 'area';

            const dataset = {
                label: 'Cantidad',
                data: chartVersusData.totales,
                backgroundColor: isCartesian
                    ? ['#28a745', '#f39c12']
                    : (isRadar ? 'rgba(32, 83, 154, 0.25)' : ['#28a745', '#f39c12']),
                borderColor: isRadar ? '#20539a' : ['#28a745', '#f39c12'],
                borderWidth: 2,
            };

            if (chartType === 'line') {
                dataset.fill = isArea;
                dataset.tension = .25;
                if (isArea) {
                    dataset.backgroundColor = 'rgba(32, 83, 154, 0.25)';
                    dataset.borderColor = '#20539a';
                }
            }
            if (isRadar) {
                dataset.fill = true;
            }

            const options = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            };

            if (chartType === 'bar') {
                options.scales = {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                };
                if (typeChoice === 'bar_h') {
                    options.indexAxis = 'y';
                }
            } else if (chartType === 'line') {
                options.scales = {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                };
            } else if (chartType === 'radar') {
                options.scales = {
                    r: { beginAtZero: true, ticks: { precision: 0 } }
                };
            }

            chartVersus = new Chart(document.getElementById('chartVersus'), {
                type: chartType,
                data: {
                    labels: chartVersusData.labels,
                    datasets: [dataset]
                },
                options
            });
        }

        function renderChartTendencia(typeChoice) {
            if (chartTendencia) {
                chartTendencia.destroy();
            }

            const chartType = typeChoice === 'area' ? 'line' : (typeChoice === 'bar_h' ? 'bar' : typeChoice);
            const isArea = typeChoice === 'area';

            const datasets = [
                {
                    label: 'Registrados',
                    data: trendSeries.registros,
                    borderColor: '#20539a',
                    backgroundColor: isArea ? 'rgba(32, 83, 154, 0.25)' : '#20539a',
                },
                {
                    label: 'Entregados',
                    data: trendSeries.entregados,
                    borderColor: '#28a745',
                    backgroundColor: isArea ? 'rgba(40, 167, 69, 0.25)' : '#28a745',
                }
            ];

            if (chartType === 'line') {
                datasets.forEach((dataset) => {
                    dataset.fill = isArea;
                    dataset.tension = .25;
                });
            }

            if (chartType === 'radar') {
                datasets.forEach((dataset) => {
                    dataset.fill = true;
                    dataset.backgroundColor = dataset.backgroundColor + '33';
                });
            }

            const options = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            };

            if (chartType === 'line') {
                options.interaction = { mode: 'index', intersect: false };
                options.scales = {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                };
            } else if (chartType === 'bar') {
                options.scales = {
                    y: { beginAtZero: true, ticks: { precision: 0 } }
                };
                if (typeChoice === 'bar_h') {
                    options.indexAxis = 'y';
                }
            } else if (chartType === 'radar') {
                options.scales = {
                    r: { beginAtZero: true, ticks: { precision: 0 } }
                };
            }

            chartTendencia = new Chart(document.getElementById('chartTendencia'), {
                type: chartType,
                data: {
                    labels: trendLabels,
                    datasets
                },
                options
            });
        }

        renderChartModulos(savedModulos);
        renderChartEstados(savedEstados);
        renderChartVersus(savedVersus);
        renderChartTendencia(savedTendencia);

        if (toggleFocusModeBtn) {
            toggleFocusModeBtn.addEventListener('click', () => {
                isFocusMode = !isFocusMode;
                localStorage.setItem(DASHBOARD_FOCUS_KEY, isFocusMode ? '1' : '0');
                applyVisualModes();
            });
        }

        if (toggleDensityBtn) {
            toggleDensityBtn.addEventListener('click', () => {
                isCompactMode = !isCompactMode;
                localStorage.setItem(DASHBOARD_COMPACT_KEY, isCompactMode ? '1' : '0');
                applyVisualModes();
            });
        }

        if (replayCountersBtn) {
            replayCountersBtn.addEventListener('click', () => {
                runCounterAnimation();
                if (dashboardPresetStatus) {
                    dashboardPresetStatus.textContent = 'Animacion de KPIs ejecutada';
                    setTimeout(() => {
                        if (dashboardPresetStatus.textContent === 'Animacion de KPIs ejecutada') {
                            dashboardPresetStatus.textContent = '';
                        }
                    }, 2200);
                }
            });
        }

        if (toggleAdvancedFiltersBtn) {
            toggleAdvancedFiltersBtn.addEventListener('click', () => {
                isTopFiltersCollapsed = !isTopFiltersCollapsed;
                localStorage.setItem(DASHBOARD_TOP_FILTERS_COLLAPSED_KEY, isTopFiltersCollapsed ? '1' : '0');
                applyTopFiltersState();
            });
        }

        if (expandTopFiltersBtn) {
            expandTopFiltersBtn.addEventListener('click', () => {
                isTopFiltersCollapsed = false;
                localStorage.setItem(DASHBOARD_TOP_FILTERS_COLLAPSED_KEY, '0');
                applyTopFiltersState();
            });
        }

        let autoFilterTimer = null;
        autoFilterFields.forEach((field) => {
            field.addEventListener('change', () => {
                if (autoFilterTimer) {
                    clearTimeout(autoFilterTimer);
                }

                autoFilterTimer = setTimeout(() => {
                    submitFiltersWithMessage('Actualizando KPI automaticamente...');
                }, 280);
            });
        });

        window.setInterval(() => {
            runCounterAnimation();
        }, KPI_AUTO_ANIMATION_INTERVAL_MS);

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                runCounterAnimation();
            }
        });

        chartDownloadButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const canvasId = button.getAttribute('data-chart-download');
                const canvas = canvasId ? document.getElementById(canvasId) : null;
                if (!canvas) {
                    return;
                }

                const link = document.createElement('a');
                link.href = canvas.toDataURL('image/png', 1.0);
                link.download = `${canvasId}-${new Date().toISOString().slice(0, 10)}.png`;
                link.click();
            });
        });

        chartFullscreenButtons.forEach((button) => {
            button.addEventListener('click', async () => {
                const cardId = button.getAttribute('data-chart-fullscreen');
                const card = cardId ? document.getElementById(cardId) : null;
                if (!card || !document.fullscreenEnabled) {
                    return;
                }

                try {
                    if (document.fullscreenElement === card) {
                        await document.exitFullscreen();
                    } else {
                        if (document.fullscreenElement && document.fullscreenElement !== card) {
                            await document.exitFullscreen();
                        }
                        await card.requestFullscreen();
                    }
                } catch (error) {
                    // Ignore fullscreen errors to keep dashboard usable.
                }
            });
        });

        document.addEventListener('fullscreenchange', () => {
            const fullscreenElement = document.fullscreenElement;
            const activeId = fullscreenElement ? (fullscreenElement.id || '') : '';

            chartCards.forEach((card) => {
                card.classList.toggle('chart-fullscreen-active', fullscreenElement === card);
            });

            updateFullscreenButtons(activeId);
            forceRestoreChartLayout();
        });

        window.addEventListener('resize', () => {
            resizeAllCharts();
        });

        if (selectModulos) {
            selectModulos.addEventListener('change', (event) => {
                const type = event.target.value;
                localStorage.setItem('dash_chart_modulos', type);
                renderChartModulos(type);
            });
        }

        if (selectEstados) {
            selectEstados.addEventListener('change', (event) => {
                const type = event.target.value;
                localStorage.setItem('dash_chart_estados', type);
                renderChartEstados(type);
            });
        }

        if (selectVersus) {
            selectVersus.addEventListener('change', (event) => {
                const type = event.target.value;
                localStorage.setItem('dash_chart_versus', type);
                renderChartVersus(type);
            });
        }

        if (selectTendencia) {
            selectTendencia.addEventListener('change', (event) => {
                const type = event.target.value;
                localStorage.setItem('dash_chart_tendencia', type);
                renderChartTendencia(type);
            });
        }

        settingsWidgetChecks.forEach((checkbox) => {
            checkbox.addEventListener('change', (event) => {
                const key = event.target.getAttribute('data-setting-widget');
                dashboardConfig.widgets[key] = event.target.checked;
                saveDashboardConfig();
                applyDashboardConfig();
            });
        });

        settingsColumnChecks.forEach((checkbox) => {
            checkbox.addEventListener('change', (event) => {
                const key = event.target.getAttribute('data-setting-column');
                dashboardConfig.columns[key] = event.target.checked;
                saveDashboardConfig();
                applyDashboardConfig();
            });
        });

        if (resetDashboardConfigBtn) {
            resetDashboardConfigBtn.addEventListener('click', () => {
                dashboardConfig = cloneDefaultDashboardConfig();
                saveDashboardConfig();
                syncSettingsChecks();
                applyDashboardConfig();
                if (dashboardPresetStatus) {
                    dashboardPresetStatus.textContent = 'Configuracion restablecida';
                    setTimeout(() => {
                        if (dashboardPresetStatus.textContent === 'Configuracion restablecida') {
                            dashboardPresetStatus.textContent = '';
                        }
                    }, 2600);
                }
            });
        }

        presetButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const preset = button.getAttribute('data-preset') || 'completa';
                if (preset === 'completa') {
                    dashboardConfig = cloneDefaultDashboardConfig();
                    saveDashboardConfig();
                    syncSettingsChecks();
                    applyDashboardConfig();
                    if (dashboardPresetStatus) {
                        dashboardPresetStatus.textContent = 'Vista completa aplicada';
                        setTimeout(() => {
                            if (dashboardPresetStatus.textContent === 'Vista completa aplicada') {
                                dashboardPresetStatus.textContent = '';
                            }
                        }, 2600);
                    }
                    return;
                }

                applyDashboardPreset(preset);
            });
        });
    </script>
@stop

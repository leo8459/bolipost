@extends('adminlte::page')

@section('title', 'Flujo de paquetería')

@section('content_header')
    <div class="flow-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <div class="flow-eyebrow">DIRECCIÓN DE OPERACIONES · PANEL EJECUTIVO</div>
            <h1 class="mb-1">Flujo de paquetería</h1>
            <p class="mb-0 text-muted">Resumen de volumen, transporte y tiempos de atención para {{ $periodLabel }} · Origen: {{ $departmentLabel }}.</p>
        </div>
    </div>
@stop

@section('css')
    @parent
    <style>
        #paqueteria-flow, .flow-heading { --flow-ink:#17324d; --flow-muted:#718096; --flow-line:#e5ebf1; }
        .flow-heading { padding:4px 0 12px; }
        .flow-heading h1 { color:var(--flow-ink); font-size:1.65rem; font-weight:700; letter-spacing:-.02em; }
        .flow-heading p { font-size:.92rem; }
        .flow-eyebrow { color:#56728d; font-size:.69rem; font-weight:700; letter-spacing:.09em; }
        #paqueteria-flow .flow-card { border:1px solid var(--flow-line); border-radius:10px; box-shadow:0 3px 12px rgba(25,48,74,.045); }
        #paqueteria-flow .flow-card > .card-header { background:#fff; border-bottom:1px solid var(--flow-line); border-radius:10px 10px 0 0; padding:15px 19px; }
        #paqueteria-flow .flow-card > .card-body { padding:19px; }
        #paqueteria-flow .flow-title { color:var(--flow-ink); font-size:1rem; font-weight:700; margin:0; }
        #paqueteria-flow .flow-subtitle, #paqueteria-flow .flow-note { color:var(--flow-muted); font-size:.78rem; margin-top:4px; }
        #paqueteria-flow .flow-label { color:#31465b; font-size:.78rem; font-weight:700; margin-bottom:7px; }
        #paqueteria-flow .form-control { border-color:#d9e2ec; border-radius:6px; height:39px; }
        #paqueteria-flow .filter-actions { flex-wrap:wrap; gap:8px; }
        #paqueteria-flow .month-picker { background:#f8fafc; border:1px solid #e5ebf1; border-radius:8px; padding:12px 14px; }
        #paqueteria-flow .month-option { align-items:center; background:#fff; border:1px solid #e1e8ef; border-radius:6px; color:#40566b; cursor:pointer; display:inline-flex; font-size:.78rem; margin:0 5px 6px 0; padding:6px 9px; }
        #paqueteria-flow .month-option input { margin:0 6px 0 0; }
        #paqueteria-flow .month-option:has(input:checked) { background:#edf5ff; border-color:#8ab5df; color:#174f82; }
        #paqueteria-flow .department-picker-note { color:var(--flow-muted); font-size:.74rem; margin-top:7px; }
        #paqueteria-flow .metric-card { background:#fff; border:1px solid var(--flow-line); border-radius:10px; height:100%; min-height:127px; overflow:hidden; padding:16px 18px; position:relative; }
        #paqueteria-flow .metric-card:before { background:var(--metric-color,#2364aa); content:''; height:100%; left:0; position:absolute; top:0; width:4px; }
        #paqueteria-flow .metric-label { color:var(--flow-muted); font-size:.72rem; font-weight:700; text-transform:uppercase; }
        #paqueteria-flow .metric-value { color:var(--flow-ink); font-size:1.55rem; font-weight:700; letter-spacing:-.03em; line-height:1.2; margin-top:9px; }
        #paqueteria-flow .metric-card--duration .metric-value { font-size:1.08rem; letter-spacing:-.015em; line-height:1.35; }
        #paqueteria-flow .metric-note { color:var(--flow-muted); font-size:.74rem; margin-top:5px; }
        #paqueteria-flow .metric-card--executive { min-height:118px; }
        #paqueteria-flow .flow-period-chip { background:#edf5ff; border:1px solid #d8e8f8; border-radius:20px; color:#28577f; font-size:.76rem; font-weight:700; padding:7px 12px; }
        #paqueteria-flow .averages-overview { background:linear-gradient(135deg,#f5f9fd 0%,#fbfcfe 100%); border:1px solid #e3ebf3; border-radius:12px; padding:18px 18px 2px; }
        #paqueteria-flow .flow-section { margin-top:23px; }
        #paqueteria-flow .flow-section-heading { align-items:end; display:flex; justify-content:space-between; margin:0 0 10px; }
        #paqueteria-flow .flow-section-heading h2 { color:var(--flow-ink); font-size:1.08rem; font-weight:700; margin:3px 0 0; }
        #paqueteria-flow .flow-kicker { color:#56728d; font-size:.67rem; font-weight:700; letter-spacing:.09em; }
        #paqueteria-flow .stage-track { align-items:center; background:#f7f9fc; border:1px solid #e7edf3; border-radius:8px; display:flex; gap:12px; margin:0 0 10px; padding:10px 14px; }
        #paqueteria-flow .stage-point { display:flex; flex-direction:column; min-width:0; }
        #paqueteria-flow .stage-point span { color:var(--flow-muted); font-size:.64rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; }
        #paqueteria-flow .stage-point strong { color:#29445e; font-size:.78rem; font-weight:700; }
        #paqueteria-flow .stage-arrow { color:#7893ac; font-size:.85rem; }
        #paqueteria-flow .stage-count { color:#536b81; font-size:.72rem; margin-left:auto; text-align:right; }
        #paqueteria-flow .flow-table { color:#354b60; font-size:.79rem; margin:0; }
        #paqueteria-flow .flow-table thead th { background:#f5f8fb; border-bottom:1px solid #e2e9f0; border-top:0; color:#647b91; font-size:.67rem; font-weight:700; letter-spacing:.04em; padding:10px 11px; text-transform:uppercase; white-space:nowrap; }
        #paqueteria-flow .flow-table tbody td { border-top:1px solid #edf1f5; padding:10px 11px; vertical-align:middle; white-space:nowrap; }
        #paqueteria-flow .flow-table tbody tr:hover { background:#f9fbfd; }
        #paqueteria-flow .flow-table .total-row { background:#f6f9fc; font-weight:700; }
        #paqueteria-flow .average-pill { background:#edf5ff; border-radius:6px; color:#17324d; display:inline-block; font-weight:700; padding:4px 7px; }
        #paqueteria-flow .delta { border-radius:12px; display:inline-block; font-size:.7rem; font-weight:700; padding:4px 7px; }
        #paqueteria-flow .delta-up { background:#e8f5ef; color:#24734f; }
        #paqueteria-flow .delta-down { background:#fff0ed; color:#aa493a; }
        #paqueteria-flow .delta-flat { background:#eef2f6; color:#64748b; }
        #paqueteria-flow .winner-card { background:#f8fbfe; border:1px solid var(--flow-line); border-radius:9px; height:100%; padding:15px 17px; }
        #paqueteria-flow .winner-label { color:var(--flow-muted); font-size:.7rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
        #paqueteria-flow .winner-name { color:var(--flow-ink); font-size:1.05rem; font-weight:700; margin-top:7px; }
        #paqueteria-flow .rank-badge { align-items:center; background:#edf4fb; border-radius:50%; color:#28649e; display:inline-flex; font-size:.7rem; font-weight:700; height:25px; justify-content:center; width:25px; }
        #paqueteria-flow .empty-row { color:var(--flow-muted); padding:24px !important; text-align:center; }
        #paqueteria-flow .criteria-details { background:#fff; border:1px solid var(--flow-line); border-radius:10px; box-shadow:0 3px 12px rgba(25,48,74,.045); }
        #paqueteria-flow .criteria-details summary { color:#29445e; cursor:pointer; font-size:.83rem; font-weight:700; list-style:none; padding:14px 17px; }
        #paqueteria-flow .criteria-details summary::-webkit-details-marker { display:none; }
        #paqueteria-flow .criteria-details summary:after { color:#56728d; content:'+'; float:right; font-size:1rem; }
        #paqueteria-flow .criteria-details[open] summary:after { content:'−'; }
        #paqueteria-flow .criteria-details .criteria-body { border-top:1px solid var(--flow-line); padding:14px 17px; }
        @media(max-width:767.98px) {
            .flow-heading h1 { font-size:1.35rem; }
            #paqueteria-flow .flow-card > .card-body { padding:14px; }
            #paqueteria-flow .metric-card { min-height:110px; padding:13px; }
            #paqueteria-flow .metric-value { font-size:1.3rem; }
            #paqueteria-flow .metric-card--duration .metric-value { font-size:1rem; }
            #paqueteria-flow .flow-section-heading { align-items:flex-start; flex-direction:column; }
            #paqueteria-flow .averages-overview { padding:14px 12px 1px; }
            #paqueteria-flow .flow-period-chip { margin-top:8px; }
            #paqueteria-flow .filter-actions .flow-note { margin-left:0 !important; width:100%; }
            #paqueteria-flow .stage-track { align-items:flex-start; flex-wrap:wrap; gap:8px; padding:9px 10px; }
            #paqueteria-flow .stage-point strong { font-size:.7rem; }
            #paqueteria-flow .stage-count { font-size:.65rem; margin-left:0; text-align:left; width:100%; }
        }
    </style>
@stop

@section('content')
    @php
        $formatCount = fn ($value) => \App\Support\BolivianNumber::format((int) $value);
        $formatWeight = fn ($value) => \App\Support\BolivianNumber::format((float) $value, 3);
        $metricFormat = fn ($value, $decimals) => \App\Support\BolivianNumber::format($value, $decimals);
        $delta = function ($before, $after, $decimals) use ($metricFormat) {
            if ((float) $before === 0.0) {
                return [
                    'label' => (float) $after === 0.0 ? '—' : 'Nuevo',
                    'class' => (float) $after === 0.0 ? 'delta-flat' : 'delta-up',
                ];
            }

            $difference = (float) $after - (float) $before;
            $percentage = $difference / abs((float) $before) * 100;
            $sign = $difference > 0 ? '+' : ($difference < 0 ? '−' : '');
            $percentageSign = $percentage > 0 ? '+' : ($percentage < 0 ? '−' : '');

            return [
                'label' => $sign.$metricFormat(abs($difference), $decimals).' ('.$percentageSign.$metricFormat(abs($percentage), 1).'%)',
                'class' => $difference > 0 ? 'delta-up' : ($difference < 0 ? 'delta-down' : 'delta-flat'),
            ];
        };
    @endphp

    <div id="paqueteria-flow">
        <div class="card flow-card mb-3">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div>
                    <h2 class="flow-title"><i class="fas fa-sliders-h text-primary mr-2"></i>Periodo y origen</h2>
                    <div class="flow-subtitle">Selecciona los meses y departamentos que quieres incluir en el análisis.</div>
                </div>
                <div class="mt-3 mt-md-0">
                    @canany(['dashboard.flujo-paqueteria.pdf', 'dashboard.flujo-paqueteria'])
                        <a href="{{ route('dashboard.flujo-paqueteria.pdf', ['anio' => $anio, 'meses' => $selectedMonths, 'departamentos' => $selectedDepartments]) }}" class="btn btn-danger btn-sm mr-1">
                            <i class="fas fa-file-pdf mr-1"></i> Descargar PDF
                        </a>
                    @endcan
                    @can('dashboard.flujo-paqueteria.excel')
                    <a href="{{ route('dashboard.flujo-paqueteria.excel', ['anio' => $anio, 'meses' => $selectedMonths, 'departamentos' => $selectedDepartments]) }}" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                    </a>
                    @endcan
                </div>
            </div>
            <form id="flow-filter-form" method="GET" action="{{ route('dashboard.flujo-paqueteria') }}">
                <div class="card-body">
                    <div class="row align-items-end">
                        <div class="col-sm-5 col-md-3">
                            <label for="flow-year" class="flow-label">Año</label>
                            <select id="flow-year" name="anio" class="form-control">
                                @foreach($yearOptions as $yearOption)
                                    <option value="{{ $yearOption }}" {{ (int) $yearOption === (int) $anio ? 'selected' : '' }}>{{ $yearOption }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="flow-label">Departamentos de origen</div>
                            <div class="month-picker">
                                @foreach($departmentOptions as $departmentCode => $departmentName)
                                    <label class="month-option">
                                        <input type="checkbox" name="departamentos[]" value="{{ $departmentCode }}" {{ in_array($departmentCode, $selectedDepartments, true) ? 'checked' : '' }}>
                                        {{ $departmentName }}
                                    </label>
                                @endforeach
                            </div>
                            <div class="department-picker-note">Puedes elegir uno o varios departamentos de origen. Si no marcas ninguno, se muestran todos. Los CN-33 se filtran por el origen de sus paquetes vinculados.</div>
                        </div>
                        <div class="col-12 mt-3">
                            <div class="flow-label">Meses a incluir</div>
                            <div class="month-picker">
                                @foreach($monthOptions as $monthNumber => $monthName)
                                    <label class="month-option">
                                        <input type="checkbox" name="meses[]" value="{{ $monthNumber }}" {{ in_array($monthNumber, $selectedMonths, true) ? 'checked' : '' }}>
                                        {{ $monthName }}
                                    </label>
                                @endforeach
                            </div>
                            <div id="flow-month-error" class="text-danger small mt-1 d-none">Selecciona al menos un mes para generar el reporte.</div>
                        </div>
                        <div class="col-12 mt-3 filter-actions d-flex align-items-end">
                            <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-sync-alt mr-1"></i> Actualizar indicadores</button>
                            <a href="{{ route('dashboard.flujo-paqueteria') }}" class="btn btn-light border">Restablecer filtros</a>
                            <span class="flow-note ml-3 mb-2">{{ count($selectedMonths) }} {{ count($selectedMonths) === 1 ? 'mes seleccionado' : 'meses seleccionados' }} · Origen: {{ $departmentLabel }} · {{ $periodLabel }}</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="flow-section overview-section">
            <div class="flow-section-heading">
                <div>
                    <div class="flow-kicker">RESUMEN DEL PERIODO</div>
                    <h2>Indicadores operativos</h2>
                </div>
                <div class="flow-period-chip"><i class="far fa-calendar-alt mr-1"></i>{{ $periodLabel }} <span>·</span> {{ $departmentLabel }}</div>
            </div>
            <div class="row">
                <div class="col-6 col-md-4 col-xl mb-3">
                    <div class="metric-card metric-card--executive" style="--metric-color:#2364aa">
                        <div class="metric-label">Guías procesadas</div>
                        <div class="metric-value">{{ $formatCount($totals['guias_total']) }}</div>
                        <div class="metric-note">Contratos y EMS en el periodo</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl mb-3">
                    <div class="metric-card metric-card--executive" style="--metric-color:#25805a">
                        <div class="metric-label">Peso de paquetes</div>
                        <div class="metric-value">{{ $formatWeight($totals['peso_recibido']) }} <small>kg</small></div>
                        <div class="metric-note">Suma de contratos y EMS</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl mb-3">
                    <div class="metric-card metric-card--executive" style="--metric-color:#7755a6">
                        <div class="metric-label">Paquetes EMS</div>
                        <div class="metric-value">{{ $formatCount($totals['paquetes_ems']) }}</div>
                        <div class="metric-note">{{ $formatWeight($totals['peso_ems']) }} kg registrados</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl mb-3">
                    <div class="metric-card metric-card--executive" style="--metric-color:#25805a">
                        <div class="metric-label">Carga aérea CN-33</div>
                        <div class="metric-value">{{ $formatWeight($totals['aereo']) }} <small>kg</small></div>
                        <div class="metric-note">Según transportadora en bitácora</div>
                    </div>
                </div>
                <div class="col-6 col-md-4 col-xl mb-3">
                    <div class="metric-card metric-card--executive" style="--metric-color:#bd7c27">
                        <div class="metric-label">Carga terrestre CN-33</div>
                        <div class="metric-value">{{ $formatWeight($totals['terrestre']) }} <small>kg</small></div>
                        <div class="metric-note">Otras transportadoras en bitácora</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flow-section averages-overview">
            <div class="flow-section-heading">
                <div>
                    <div class="flow-kicker">TIEMPOS DE GESTIÓN</div>
                    <h2>Promedio por etapa del proceso</h2>
                </div>
                <div class="flow-note">Solo se incluyen envíos con las fechas necesarias registradas.</div>
            </div>
            <div class="row">
                <div class="col-6 col-lg-3 mb-3">
                    <div class="metric-card metric-card--duration" style="--metric-color:#2364aa">
                        <div class="metric-label">Creación hasta entrega o devolución</div>
                        <div class="metric-value">{{ $resolutionTime['total']['promedio_total_texto'] }}</div>
                        <div class="metric-note">{{ $formatCount($resolutionTime['total']['finalizados']) }} envíos finalizados</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 mb-3">
                    <div class="metric-card metric-card--duration" style="--metric-color:#d97706">
                        <div class="metric-label">Ingreso a almacén hasta despacho</div>
                        <div class="metric-value">{{ $dispatchTime['total']['promedio_texto'] }}</div>
                        <div class="metric-note">{{ $formatCount($dispatchTime['total']['despachados']) }} envíos despachados</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 mb-3">
                    <div class="metric-card metric-card--duration" style="--metric-color:#0f766e">
                        <div class="metric-label">Despacho hasta recepción en tránsito</div>
                        <div class="metric-value">{{ $transitReceiptTime['total']['promedio_texto'] }}</div>
                        <div class="metric-note">{{ $formatCount($transitReceiptTime['total']['recibidos']) }} envíos recibidos</div>
                    </div>
                </div>
                <div class="col-6 col-lg-3 mb-3">
                    <div class="metric-card metric-card--duration" style="--metric-color:#b45309">
                        <div class="metric-label">Asignación al cartero hasta entrega o devolución</div>
                        <div class="metric-value">{{ $courierResolutionTime['total']['promedio_total_texto'] }}</div>
                        <div class="metric-note">{{ $formatCount($courierResolutionTime['total']['finalizados']) }} entregados o devueltos</div>
                    </div>
                </div>
            </div>
        </div>
        @php
            $resolutionRows = [
                ['EMS', $resolutionTime['ems']],
                ['Contratos', $resolutionTime['contrato']],
                ['Total del periodo', $resolutionTime['total']],
            ];
        @endphp
        <div class="flow-section">
            <div class="flow-section-heading">
                <div>
                    <div class="flow-kicker">CREACIÓN A ENTREGA O DEVOLUCIÓN</div>
                    <h2><i class="fas fa-stopwatch text-primary mr-2"></i>Tiempo total: creación a entrega o devolución</h2>
                </div>
                <div class="flow-note">Envíos creados en {{ $periodLabel }} · {{ $departmentLabel }}</div>
            </div>
            <div class="stage-track">
                <div class="stage-point"><span>Inicio</span><strong>Creación del paquete</strong></div>
                <i class="fas fa-arrow-right stage-arrow" aria-hidden="true"></i>
                <div class="stage-point"><span>Fin</span><strong>Entrega o devolución</strong></div>
                <div class="stage-count">{{ $formatCount($resolutionTime['total']['finalizados']) }} envíos finalizados</div>
            </div>
            <div class="card flow-card">
                <div class="table-responsive">
                    <table class="table flow-table">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th class="text-right">Entregados</th>
                                <th class="text-right">Promedio hasta entrega</th>
                                <th class="text-right">Devueltos</th>
                                <th class="text-right">Promedio hasta devolución</th>
                                <th class="text-right">Total finalizados</th>
                                <th class="text-right">Promedio general</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($resolutionRows as [$serviceName, $serviceStats])
                                <tr class="{{ $serviceName === 'Total del periodo' ? 'total-row' : '' }}">
                                    <td>{{ $serviceName }}</td>
                                    <td class="text-right">{{ $formatCount($serviceStats['entregados']) }}</td>
                                    <td class="text-right">{{ $serviceStats['promedio_entrega_texto'] }}</td>
                                    <td class="text-right">{{ $formatCount($serviceStats['devueltos']) }}</td>
                                    <td class="text-right">{{ $serviceStats['promedio_devolucion_texto'] }}</td>
                                    <td class="text-right">{{ $formatCount($serviceStats['finalizados']) }}</td>
                                    <td class="text-right"><span class="average-pill">{{ $serviceStats['promedio_total_texto'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-2">
                    <div class="flow-note">Se consideran envíos creados en el periodo y con entrega o devolución registrada. El promedio general combina ambos resultados; los pendientes y cancelados se excluyen.</div>
                </div>
            </div>
        </div>

        @php
            $dispatchRows = [
                ['EMS', $dispatchTime['ems']],
                ['Contratos', $dispatchTime['contrato']],
                ['Total del periodo', $dispatchTime['total']],
            ];
        @endphp
        <div class="flow-section">
            <div class="flow-section-heading">
                <div>
                    <div class="flow-kicker">ALMACÉN A DESPACHO</div>
                    <h2><i class="fas fa-shipping-fast text-warning mr-2"></i>Tiempo de almacén a despacho</h2>
                </div>
                <div class="flow-note">Envíos creados en {{ $periodLabel }} · {{ $departmentLabel }}</div>
            </div>
            <div class="stage-track">
                <div class="stage-point"><span>Inicio</span><strong>Ingreso a almacén</strong></div>
                <i class="fas fa-arrow-right stage-arrow" aria-hidden="true"></i>
                <div class="stage-point"><span>Fin</span><strong>Despacho a tránsito</strong></div>
                <div class="stage-count">{{ $formatCount($dispatchTime['total']['despachados']) }} envíos despachados</div>
            </div>
            <div class="card flow-card">
                <div class="table-responsive">
                    <table class="table flow-table">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th class="text-right">Despachos registrados</th>
                                <th class="text-right">Promedio almacén a tránsito</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dispatchRows as [$serviceName, $serviceStats])
                                <tr class="{{ $serviceName === 'Total del periodo' ? 'total-row' : '' }}">
                                    <td>{{ $serviceName }}</td>
                                    <td class="text-right">{{ $formatCount($serviceStats['despachados']) }}</td>
                                    <td class="text-right"><span class="average-pill">{{ $serviceStats['promedio_texto'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-2">
                    <div class="flow-note">Mide el tiempo desde el ingreso a almacén hasta el primer despacho a tránsito. Solo cuenta envíos con despacho registrado.</div>
                </div>
            </div>
        </div>

        @php
            $transitReceiptRows = [
                ['EMS', $transitReceiptTime['ems']],
                ['Contratos', $transitReceiptTime['contrato']],
                ['Total del periodo', $transitReceiptTime['total']],
            ];
        @endphp
        <div class="flow-section">
            <div class="flow-section-heading">
                <div>
                    <div class="flow-kicker">DESPACHO A RECEPCIÓN</div>
                    <h2><i class="fas fa-map-marked-alt text-info mr-2"></i>Tiempo de despacho a recepción</h2>
                </div>
                <div class="flow-note">Envíos creados en {{ $periodLabel }} · {{ $departmentLabel }}</div>
            </div>
            <div class="stage-track">
                <div class="stage-point"><span>Inicio</span><strong>Despacho a tránsito</strong></div>
                <i class="fas fa-arrow-right stage-arrow" aria-hidden="true"></i>
                <div class="stage-point"><span>Fin</span><strong>Recepción en tránsito</strong></div>
                <div class="stage-count">{{ $formatCount($transitReceiptTime['total']['recibidos']) }} envíos recibidos</div>
            </div>
            <div class="card flow-card">
                <div class="table-responsive">
                    <table class="table flow-table">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th class="text-right">Recepciones registradas</th>
                                <th class="text-right">Promedio tránsito a recepción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transitReceiptRows as [$serviceName, $serviceStats])
                                <tr class="{{ $serviceName === 'Total del periodo' ? 'total-row' : '' }}">
                                    <td>{{ $serviceName }}</td>
                                    <td class="text-right">{{ $formatCount($serviceStats['recibidos']) }}</td>
                                    <td class="text-right"><span class="average-pill">{{ $serviceStats['promedio_texto'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-2">
                    <div class="flow-note">Mide desde el despacho hacia tránsito hasta la primera recepción registrada después de ese despacho. Solo incluye envíos con ambas fechas disponibles.</div>
                </div>
            </div>
        </div>

        <div class="flow-section">
            <div class="flow-section-heading">
                @php
                    $courierResolutionRows = [
                        ['EMS', $courierResolutionTime['ems']],
                        ['Contratos', $courierResolutionTime['contrato']],
                        ['Total del periodo', $courierResolutionTime['total']],
                    ];
                @endphp
                <div>
                    <div class="flow-kicker">ASIGNACIÓN A CARTERO</div>
                    <h2><i class="fas fa-user-check text-success mr-2"></i>De asignación a entrega o devolución</h2>
                </div>
                <div class="flow-note">Envíos creados en {{ $periodLabel }} · {{ $departmentLabel }}</div>
            </div>
            <div class="stage-track">
                <div class="stage-point"><span>Inicio</span><strong>Asignación al cartero</strong></div>
                <i class="fas fa-arrow-right stage-arrow" aria-hidden="true"></i>
                <div class="stage-point"><span>Fin</span><strong>Entrega o devolución</strong></div>
                <div class="stage-count">{{ $formatCount($courierResolutionTime['total']['finalizados']) }} envíos finalizados</div>
            </div>
            <div class="card flow-card">
                <div class="table-responsive">
                    <table class="table flow-table">
                        <thead>
                            <tr>
                                <th>Servicio</th>
                                <th class="text-right">Entregados</th>
                                <th class="text-right">Promedio hasta entrega</th>
                                <th class="text-right">Devueltos</th>
                                <th class="text-right">Promedio hasta devolución</th>
                                <th class="text-right">Total finalizados</th>
                                <th class="text-right">Promedio general</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($courierResolutionRows as [$serviceName, $serviceStats])
                                <tr class="{{ $serviceName === 'Total del periodo' ? 'total-row' : '' }}">
                                    <td>{{ $serviceName }}</td>
                                    <td class="text-right">{{ $formatCount($serviceStats['entregados']) }}</td>
                                    <td class="text-right">{{ $serviceStats['promedio_entrega_texto'] }}</td>
                                    <td class="text-right">{{ $formatCount($serviceStats['devueltos']) }}</td>
                                    <td class="text-right">{{ $serviceStats['promedio_devolucion_texto'] }}</td>
                                    <td class="text-right">{{ $formatCount($serviceStats['finalizados']) }}</td>
                                    <td class="text-right"><span class="average-pill">{{ $serviceStats['promedio_total_texto'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-2">
                    <div class="flow-note">Usa la última asignación o cambio de cartero y la fecha de entrega o devolución. En registros antiguos sin evento de asignación se usa la fecha de creación del registro del cartero; los pendientes no entran en el promedio.</div>
                </div>
            </div>
        </div>

        <div class="flow-section">
            <div class="flow-section-heading">
                <div><div class="flow-kicker">{{ mb_strtoupper($periodLabel) }}</div><h2>Comparativo mes a mes</h2></div>
                <div class="flow-note">La variación compara el total de guías con el mes seleccionado anterior.</div>
            </div>
            <div class="card flow-card">
                <div class="table-responsive">
                    <table class="table flow-table">
                        <thead>
                            <tr><th>Mes</th><th class="text-right">Guías contrato</th><th class="text-right">Guías EMS</th><th class="text-right">Total guías</th><th class="text-right">Paquetes EMS</th><th class="text-right">Peso EMS (kg)</th><th class="text-right">Aéreo CN-33 (kg)</th><th class="text-right">Terrestre CN-33 (kg)</th><th class="text-center">Cambio de guías</th></tr>
                        </thead>
                        <tbody>
                            @php
                                $previousMonth = null;
                            @endphp
                            @foreach($months as $month)
                                @php
                                    $monthDelta = $previousMonth ? $delta($previousMonth['guias_total'], $month['guias_total'], 0) : null;
                                @endphp
                                <tr>
                                    <td class="font-weight-bold">{{ $month['nombre'] }}</td>
                                    <td class="text-right">{{ $formatCount($month['guias_contrato']) }}</td>
                                    <td class="text-right">{{ $formatCount($month['guias_ems']) }}</td>
                                    <td class="text-right font-weight-bold">{{ $formatCount($month['guias_total']) }}</td>
                                    <td class="text-right">{{ $formatCount($month['paquetes_ems']) }}</td>
                                    <td class="text-right">{{ $formatWeight($month['peso_ems']) }}</td>
                                    <td class="text-right">{{ $formatWeight($month['transporte']['aereo']) }}</td>
                                    <td class="text-right">{{ $formatWeight($month['transporte']['terrestre']) }}</td>
                                    <td class="text-center">@if($monthDelta)<span class="delta {{ $monthDelta['class'] }}">{{ $monthDelta['label'] }}</span>@else<span class="text-muted">—</span>@endif</td>
                                </tr>
                                @php
                                    $previousMonth = $month;
                                @endphp
                            @endforeach
                        @if(count($months) > 1)
                            <tr class="total-row"><td>Total del periodo</td><td class="text-right">{{ $formatCount($totals['guias_contrato']) }}</td><td class="text-right">{{ $formatCount($totals['guias_ems']) }}</td><td class="text-right">{{ $formatCount($totals['guias_total']) }}</td><td class="text-right">{{ $formatCount($totals['paquetes_ems']) }}</td><td class="text-right">{{ $formatWeight($totals['peso_ems']) }}</td><td class="text-right">{{ $formatWeight($totals['aereo']) }}</td><td class="text-right">{{ $formatWeight($totals['terrestre']) }}</td><td class="text-center">—</td></tr>
                        @endif
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-2">
                <div class="flow-note">El peso proviene de <strong>bitacoras.peso</strong> por CN-33. Se considera aéreo cuando la transportadora contiene BOA CARGO o BOLIVIANA DE AVIACIÓN, o es BOA; las demás transportadoras se consideran terrestres. El mes corresponde a la fecha de registro de la bitácora.</div>
                </div>
            </div>
        </div>

        <div class="flow-section">
            <div class="flow-section-heading">
                <div><div class="flow-kicker">PAQUETES CONTRATADOS</div><h2>Empresas con mayor movimiento</h2></div>
                <div class="flow-note">Acumulado de guías de contrato y kilos registrados en {{ $periodLabel }}.</div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6 mb-3 mb-md-0">
                    <div class="winner-card">
                        <div class="winner-label"><i class="fas fa-trophy text-warning mr-1"></i> Más guías</div>
                        @if($topByGuides)
                            <div class="winner-name">{{ $topByGuides['empresa'] }}</div>
                            <div class="flow-note">{{ $formatCount($topByGuides['guias_total']) }} guías · {{ $formatWeight($topByGuides['peso_total']) }} kg</div>
                        @else
                            <div class="winner-name text-muted">Sin registros de empresas identificadas</div>
                        @endif
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="winner-card">
                        <div class="winner-label"><i class="fas fa-weight-hanging text-primary mr-1"></i> Más kilos</div>
                        @if($topByWeight)
                            <div class="winner-name">{{ $topByWeight['empresa'] }}</div>
                            <div class="flow-note">{{ $formatWeight($topByWeight['peso_total']) }} kg · {{ $formatCount($topByWeight['guias_total']) }} guías</div>
                        @else
                            <div class="winner-name text-muted">Sin registros de empresas identificadas</div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="card flow-card">
                <div class="table-responsive">
                    <table class="table flow-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Empresa</th>
                                <th class="text-right">Guías en el periodo</th>
                                <th class="text-right">Kilos en el periodo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($companyRows as $index => $company)
                                <tr>
                                    <td><span class="rank-badge">{{ $index + 1 }}</span></td>
                                    <td class="font-weight-bold">{{ $company['empresa'] }}</td>
                                    <td class="text-right font-weight-bold">{{ $formatCount($company['guias_total']) }}</td>
                                    <td class="text-right font-weight-bold">{{ $formatWeight($company['peso_total']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="empty-row">No hay paquetes de contrato asociados a empresas para este periodo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top py-2">
                    <div class="flow-note">Se muestran todas las empresas identificadas excepto la empresa "EMPRESA" y las cuyo nombre contiene "prueba", ordenadas por guías. Cuando falta la empresa directa, se usa la empresa del usuario que registró la guía; los registros sin empresa identificada no entran en este ranking.</div>
                </div>
            </div>
        </div>

        <div class="flow-section mb-4">
            <div class="flow-section-heading">
                <div><div class="flow-kicker">CONSULTA</div><h2>Metodología y alcance</h2></div>
            </div>
            <details class="criteria-details flow-card">
                <summary><i class="fas fa-info-circle text-primary mr-2"></i>Ver criterios de cálculo de los indicadores</summary>
                <div class="criteria-body flow-note">
                    <p class="mb-2">Los promedios de cada etapa se calculan con envíos que tienen registradas las fechas de inicio y fin correspondientes. Los envíos pendientes se excluyen de los promedios de cierre.</p>
                    <p class="mb-2">Las guías corresponden a códigos únicos de paquetes EMS y contratos creados en los meses seleccionados. Los conteos y pesos excluyen paquetes que actualmente están cancelados; en contratos también se excluyen las empresas "EMPRESA" y los nombres que contienen "prueba".</p>
                    <p class="mb-0">El peso aéreo y terrestre se calcula aparte usando la última bitácora CN-33 de cada guía; para este dato, el mes corresponde a la fecha de registro de la bitácora. Se clasifica como aéreo cuando la transportadora contiene BOA CARGO o BOLIVIANA DE AVIACIÓN, o es BOA; las demás se consideran terrestres.</p>
                </div>
            </details>
        </div>
    </div>
@stop

@section('js')
    @parent
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('flow-filter-form');
            const error = document.getElementById('flow-month-error');
            const monthInputs = form.querySelectorAll('input[name="meses[]"]');

            form.addEventListener('submit', function (event) {
                const hasSelection = form.querySelector('input[name="meses[]"]:checked');
                if (!hasSelection) {
                    event.preventDefault();
                    error.classList.remove('d-none');
                }
            });

            monthInputs.forEach(function (input) {
                input.addEventListener('change', function () {
                    if (form.querySelector('input[name="meses[]"]:checked')) {
                        error.classList.add('d-none');
                    }
                });
            });
        });
    </script>
@stop

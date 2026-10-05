@extends('adminlte::page')

@section('title', 'Flujo de paquetería')

@section('content_header')
    <div class="flow-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <div class="flow-eyebrow">DIRECCIÓN DE OPERACIONES · COMPARATIVO MENSUAL</div>
            <h1 class="mb-1">Flujo de paquetería</h1>
            <p class="mb-0 text-muted">Guías, kilos de CN-33 tomados de bitácoras, empresas y paquetes EMS para {{ $periodLabel }}.</p>
        </div>
    </div>
@stop

@section('css')
    @parent
    <style>
        #paqueteria-flow { --flow-ink:#17324d; --flow-muted:#718096; --flow-line:#e5ebf1; }
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
        #paqueteria-flow .month-picker { background:#f8fafc; border:1px solid #e5ebf1; border-radius:8px; padding:12px 14px; }
        #paqueteria-flow .month-option { align-items:center; background:#fff; border:1px solid #e1e8ef; border-radius:6px; color:#40566b; cursor:pointer; display:inline-flex; font-size:.78rem; margin:0 5px 6px 0; padding:6px 9px; }
        #paqueteria-flow .month-option input { margin:0 6px 0 0; }
        #paqueteria-flow .month-option:has(input:checked) { background:#edf5ff; border-color:#8ab5df; color:#174f82; }
        #paqueteria-flow .metric-card { background:#fff; border:1px solid var(--flow-line); border-radius:10px; height:100%; min-height:127px; overflow:hidden; padding:16px 18px; position:relative; }
        #paqueteria-flow .metric-card:before { background:var(--metric-color,#2364aa); content:''; height:100%; left:0; position:absolute; top:0; width:4px; }
        #paqueteria-flow .metric-label { color:var(--flow-muted); font-size:.72rem; font-weight:700; text-transform:uppercase; }
        #paqueteria-flow .metric-value { color:var(--flow-ink); font-size:1.55rem; font-weight:700; letter-spacing:-.03em; line-height:1.2; margin-top:9px; }
        #paqueteria-flow .metric-note { color:var(--flow-muted); font-size:.74rem; margin-top:5px; }
        #paqueteria-flow .flow-section { margin-top:23px; }
        #paqueteria-flow .flow-section-heading { align-items:end; display:flex; justify-content:space-between; margin:0 0 10px; }
        #paqueteria-flow .flow-section-heading h2 { color:var(--flow-ink); font-size:1.08rem; font-weight:700; margin:3px 0 0; }
        #paqueteria-flow .flow-kicker { color:#56728d; font-size:.67rem; font-weight:700; letter-spacing:.09em; }
        #paqueteria-flow .flow-table { color:#354b60; font-size:.79rem; margin:0; }
        #paqueteria-flow .flow-table thead th { background:#f5f8fb; border-bottom:1px solid #e2e9f0; border-top:0; color:#647b91; font-size:.67rem; font-weight:700; letter-spacing:.04em; padding:10px 11px; text-transform:uppercase; white-space:nowrap; }
        #paqueteria-flow .flow-table tbody td { border-top:1px solid #edf1f5; padding:10px 11px; vertical-align:middle; white-space:nowrap; }
        #paqueteria-flow .flow-table tbody tr:hover { background:#f9fbfd; }
        #paqueteria-flow .flow-table .total-row { background:#f6f9fc; font-weight:700; }
        #paqueteria-flow .delta { border-radius:12px; display:inline-block; font-size:.7rem; font-weight:700; padding:4px 7px; }
        #paqueteria-flow .delta-up { background:#e8f5ef; color:#24734f; }
        #paqueteria-flow .delta-down { background:#fff0ed; color:#aa493a; }
        #paqueteria-flow .delta-flat { background:#eef2f6; color:#64748b; }
        #paqueteria-flow .winner-card { background:#f8fbfe; border:1px solid var(--flow-line); border-radius:9px; height:100%; padding:15px 17px; }
        #paqueteria-flow .winner-label { color:var(--flow-muted); font-size:.7rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; }
        #paqueteria-flow .winner-name { color:var(--flow-ink); font-size:1.05rem; font-weight:700; margin-top:7px; }
        #paqueteria-flow .rank-badge { align-items:center; background:#edf4fb; border-radius:50%; color:#28649e; display:inline-flex; font-size:.7rem; font-weight:700; height:25px; justify-content:center; width:25px; }
        #paqueteria-flow .empty-row { color:var(--flow-muted); padding:24px !important; text-align:center; }
        @media(max-width:767.98px) {
            .flow-heading h1 { font-size:1.35rem; }
            #paqueteria-flow .flow-card > .card-body { padding:14px; }
            #paqueteria-flow .metric-card { min-height:110px; padding:13px; }
            #paqueteria-flow .metric-value { font-size:1.3rem; }
            #paqueteria-flow .flow-section-heading { align-items:flex-start; flex-direction:column; }
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
                    <h2 class="flow-title"><i class="fas fa-sliders-h text-primary mr-2"></i>Periodo del reporte</h2>
                    <div class="flow-subtitle">Elige uno o varios meses para actualizar el reporte y sus exportaciones.</div>
                </div>
                <div class="mt-3 mt-md-0">
                    @canany(['dashboard.flujo-paqueteria.pdf', 'dashboard.flujo-paqueteria'])
                        <a href="{{ route('dashboard.flujo-paqueteria.pdf', ['anio' => $anio, 'meses' => $selectedMonths]) }}" class="btn btn-danger btn-sm mr-1">
                            <i class="fas fa-file-pdf mr-1"></i> Descargar PDF
                        </a>
                    @endcan
                    @can('dashboard.flujo-paqueteria.excel')
                    <a href="{{ route('dashboard.flujo-paqueteria.excel', ['anio' => $anio, 'meses' => $selectedMonths]) }}" class="btn btn-outline-success btn-sm">
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
                        <div class="col-12 mt-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-filter mr-1"></i> Aplicar</button>
                            <a href="{{ route('dashboard.flujo-paqueteria') }}" class="btn btn-light border">Limpiar</a>
                            <span class="flow-note ml-3 mb-2">{{ count($selectedMonths) }} {{ count($selectedMonths) === 1 ? 'mes seleccionado' : 'meses seleccionados' }} · {{ $periodLabel }}</span>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="row">
            <div class="col-6 col-lg-3 mb-3">
                <div class="metric-card" style="--metric-color:#2364aa">
                    <div class="metric-label">Guías procesadas</div>
                    <div class="metric-value">{{ $formatCount($totals['guias_total']) }}</div>
                    <div class="metric-note">{{ $formatCount($totals['guias_contrato']) }} de contratos · {{ $formatCount($totals['guias_ems']) }} EMS</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="metric-card" style="--metric-color:#25805a">
                    <div class="metric-label">Carga aérea CN-33</div>
                    <div class="metric-value">{{ $formatWeight($totals['aereo']) }} <small>kg</small></div>
                    <div class="metric-note">Según transportadora en bitácora</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="metric-card" style="--metric-color:#bd7c27">
                    <div class="metric-label">Carga terrestre CN-33</div>
                    <div class="metric-value">{{ $formatWeight($totals['terrestre']) }} <small>kg</small></div>
                    <div class="metric-note">Todas las demás transportadoras de bitácora</div>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-3">
                <div class="metric-card" style="--metric-color:#7755a6">
                    <div class="metric-label">Paquetes EMS</div>
                    <div class="metric-value">{{ $formatCount($totals['paquetes_ems']) }}</div>
                    <div class="metric-note">{{ $formatWeight($totals['peso_ems']) }} kg registrados</div>
                </div>
            </div>
        </div>

        <div class="flow-section">
            <div class="flow-section-heading">
                <div><div class="flow-kicker">{{ mb_strtoupper($periodLabel) }}</div><h2>Comparativo mes a mes</h2></div>
                <div class="flow-note">La última columna compara las guías con el mes seleccionado anterior.</div>
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
                <div><div class="flow-kicker">ALCANCE DE LOS DATOS</div><h2>Cómo se calcula</h2></div>
            </div>
            <div class="card flow-card"><div class="card-body flow-note">
                Las guías son códigos únicos de paquetes EMS y de contrato registrados en cada mes. Los paquetes EMS suman el campo cantidad y su peso registrado. Se excluyen de las cifras de contrato y del ranking la empresa "EMPRESA" y las empresas cuyo nombre contiene "prueba". Los kilos aéreos y terrestres suman el campo peso de las bitácoras de CN-33 registradas en cada mes y se clasifican por el texto de transportadora.
            </div></div>
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

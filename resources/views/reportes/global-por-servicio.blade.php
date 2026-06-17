@extends('adminlte::page')

@section('title', 'Global por servicio')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Global por servicio</h1>
            <small class="text-muted">Analiza cantidades, peso, ingresos y canal de recepción agrupados por servicio.</small>
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
        $modulesMap = [
            'contrato' => 'CONTRATOS',
            'ems' => 'EMS',
            'certi' => 'CERTIFICADOS',
            'ordi' => 'ORDINARIOS',
        ];
        $selectedMonthFilters = $selectedMonths ?? [];
        $serviceRows = $serviceRows ?? collect();
        $serviceTotals = $serviceTotals ?? [];
        $serviceOptionMatrix = $serviceOptionMatrix ?? [];
        $receptionOptionMatrix = $receptionOptionMatrix ?? [];
        $selectedReceptionChannels = $selectedReceptionChannels ?? [];
    @endphp

    <div class="report-hero mb-3">
        <div>
            <h2>Resumen ejecutivo por servicio</h2>
            <p>Usa los filtros para entender qué servicios tuvieron más movimiento y por qué canal fueron recibidos.</p>
        </div>
        <div class="report-hero-badge">Vista optimizada</div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <strong><i class="fas fa-filter mr-1"></i> Filtros</strong>
        </div>
        <form method="GET" action="{{ route('dashboard.global-por-servicio') }}">
            <input type="hidden" name="range" id="reportRange" value="{{ $range ?? 'all' }}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label>Fecha desde</label>
                        <input type="date" class="form-control" name="from" id="reportFrom" value="{{ $from }}">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label>Fecha hasta</label>
                        <input type="date" class="form-control" name="to" id="reportTo" value="{{ $to }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>Meses</label>
                        <div class="filter-box">
                            @foreach(($monthOptions ?? []) as $monthOption)
                                <label class="filter-check">
                                    <input
                                        type="checkbox"
                                        name="months[]"
                                        value="{{ $monthOption['value'] }}"
                                        {{ in_array($monthOption['value'], $selectedMonthFilters, true) ? 'checked' : '' }}
                                    >
                                    <span>{{ $monthOption['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label>Módulos</label>
                        <div class="filter-box">
                            @foreach($modulesMap as $moduleKey => $moduleLabel)
                                <label class="filter-check">
                                    <input
                                        type="checkbox"
                                        name="modules[]"
                                        value="{{ $moduleKey }}"
                                        data-module-filter="true"
                                        {{ in_array($moduleKey, $selectedModules ?? [], true) ? 'checked' : '' }}
                                    >
                                    <span>{{ $moduleLabel }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-7 mb-3">
                        <div class="mb-3">
                            <label>Servicios</label>
                            <div class="filter-box filter-box-services" id="serviceFilterBox">
                                @forelse($serviceOptionMatrix as $serviceOptionMeta)
                                    <label class="filter-check">
                                        <input
                                            type="checkbox"
                                            name="servicios[]"
                                            value="{{ $serviceOptionMeta['service'] }}"
                                            data-service-filter="true"
                                            data-modules='@json($serviceOptionMeta['modules'])'
                                            {{ in_array($serviceOptionMeta['service'], $selectedServices ?? [], true) ? 'checked' : '' }}
                                        >
                                        <span>{{ $serviceOptionMeta['service'] }}</span>
                                    </label>
                                @empty
                                    <span class="text-muted small">Sin servicios para los filtros actuales.</span>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <label>Canal de recepción</label>
                            <div class="filter-box" id="receptionFilterBox">
                                @foreach($receptionOptionMatrix as $channelOptionMeta)
                                    <label class="filter-check">
                                        <input
                                            type="checkbox"
                                            name="canales[]"
                                            value="{{ $channelOptionMeta['channel'] }}"
                                            data-reception-filter="true"
                                            data-modules='@json($channelOptionMeta['modules'])'
                                            {{ in_array($channelOptionMeta['channel'], $selectedReceptionChannels, true) ? 'checked' : '' }}
                                        >
                                        <span>{{ $channelOptionMeta['channel'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center">
                <div class="mb-2 mb-md-0">
                    <button type="submit" class="btn btn-primary mr-2">
                        <i class="fas fa-search mr-1"></i> Buscar
                    </button>
                    <a href="{{ route('dashboard.global-por-servicio') }}" class="btn btn-outline-secondary">
                        <i class="fas fa-undo mr-1"></i> Limpiar
                    </a>
                </div>
                <div class="text-right mr-3">
                    <a href="{{ route('dashboard.global-por-servicio.excel', request()->query()) }}" class="btn btn-success mr-2">
                        <i class="fas fa-file-excel mr-1"></i> Excel
                    </a>
                    <a href="{{ route('dashboard.global-por-servicio.pdf', request()->query()) }}" class="btn btn-danger" target="_blank">
                        <i class="fas fa-file-pdf mr-1"></i> PDF
                    </a>
                    <div class="text-muted small mt-2">
                        Exporta el resumen filtrado por servicios y canal de recepción.
                    </div>
                </div>
                <div class="text-muted small">
                    Esta es la nueva vista base. Luego la ajustamos exactamente al formato que tú definas.
                </div>
            </div>
        </form>
    </div>

    <div class="row">
        <div class="col-md-2 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-primary"><i class="fas fa-stream"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Servicios</span>
                    <span class="info-box-number">{{ number_format((int) ($serviceTotals['servicios'] ?? 0)) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-info"><i class="fas fa-hashtag"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Registros</span>
                    <span class="info-box-number">{{ number_format((int) ($serviceTotals['registros'] ?? 0)) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-secondary"><i class="fas fa-building"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Empresa</span>
                    <span class="info-box-number">{{ number_format((int) ($serviceTotals['empresa_count'] ?? 0)) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-indigo"><i class="fas fa-inbox"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Admisión</span>
                    <span class="info-box-number">{{ number_format((int) ($serviceTotals['admision_count'] ?? 0)) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-warning"><i class="fas fa-weight-hanging"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Peso total</span>
                    <span class="info-box-number">{{ number_format((float) ($serviceTotals['peso_total'] ?? 0), 3) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Ingresos</span>
                    <span class="info-box-number">Bs {{ number_format((float) ($serviceTotals['precio_total'] ?? 0), 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Resumen por servicio</strong>
            <span class="text-muted small">Ordenado de mayor a menor por ingresos</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Servicio</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Entregados</th>
                            <th class="text-right">No entregados</th>
                            <th class="text-right">Peso</th>
                            <th class="text-right">Bs</th>
                            <th>Módulos</th>
                            <th>Recepción</th>
                            <th>Último registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceRows as $serviceRow)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td class="font-weight-bold">{{ $serviceRow['servicio'] }}</td>
                                <td class="text-right">{{ number_format((int) $serviceRow['cantidad']) }}</td>
                                <td class="text-right">{{ number_format((int) $serviceRow['entregados']) }}</td>
                                <td class="text-right">{{ number_format((int) $serviceRow['no_entregados']) }}</td>
                                <td class="text-right">{{ number_format((float) $serviceRow['peso'], 3) }}</td>
                                <td class="text-right">Bs {{ number_format((float) $serviceRow['precio'], 2) }}</td>
                                <td>{{ $serviceRow['modulos_texto'] ?: '-' }}</td>
                                <td>
                                    <div class="small font-weight-bold">{{ $serviceRow['canales_texto'] ?: '-' }}</div>
                                    <div class="text-muted smaller">
                                        Empresa: {{ number_format((int) ($serviceRow['empresa_count'] ?? 0)) }}
                                        | Admisión: {{ number_format((int) ($serviceRow['admision_count'] ?? 0)) }}
                                        | Interno: {{ number_format((int) ($serviceRow['interno_count'] ?? 0)) }}
                                    </div>
                                </td>
                                <td>{{ $serviceRow['ultimo_registro'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">Sin resultados para los filtros actuales.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @include('footer')
@stop

@section('css')
    <style>
        .report-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border: 1px solid #dbe5ef;
            border-radius: 12px;
            background: linear-gradient(135deg, #f8fbff 0%, #eef6fd 100%);
        }
        .report-hero h2 {
            margin: 0 0 6px;
            font-size: 1.15rem;
            font-weight: 700;
        }
        .report-hero p {
            margin: 0;
            color: #516173;
        }
        .report-hero-badge {
            padding: 8px 12px;
            border-radius: 999px;
            background: #0f4c81;
            color: #fff;
            font-size: .8rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .filter-box {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            min-height: 42px;
            max-height: 116px;
            overflow: auto;
            padding: 8px;
            border: 1px solid #d7dde5;
            border-radius: 6px;
            background: #fff;
        }
        .filter-box-services {
            max-height: 154px;
        }
        .filter-check {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin: 0;
            padding: 5px 8px;
            border: 1px solid #d7dde5;
            border-radius: 6px;
            font-size: .84rem;
            background: #f8fafc;
            cursor: pointer;
        }
        .filter-check input {
            margin: 0;
        }
        .info-box {
            min-height: 78px;
        }
        .bg-indigo {
            background: #5c6ac4 !important;
            color: #fff !important;
        }
        .smaller {
            font-size: .72rem;
        }
        @media (max-width: 767.98px) {
            .report-hero {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
@stop

@section('js')
    <script>
        (() => {
            const rangeInput = document.getElementById('reportRange');
            const from = document.getElementById('reportFrom');
            const to = document.getElementById('reportTo');
            const moduleInputs = Array.from(document.querySelectorAll('[data-module-filter="true"]'));
            const serviceInputs = Array.from(document.querySelectorAll('[data-service-filter="true"]'));
            const receptionInputs = Array.from(document.querySelectorAll('[data-reception-filter="true"]'));

            const syncRange = () => {
                if (!rangeInput) {
                    return;
                }
                rangeInput.value = (from?.value || to?.value) ? 'custom' : 'all';
            };

            const selectedModules = () => moduleInputs
                .filter((input) => input.checked)
                .map((input) => input.value);

            const updateDependentFilters = () => {
                const activeModules = selectedModules();
                const activeSet = new Set(activeModules);

                const syncOptionVisibility = (inputs) => {
                    inputs.forEach((input) => {
                        const wrapper = input.closest('.filter-check');
                        const modules = JSON.parse(input.dataset.modules || '[]');
                        const allowed = activeModules.length === 0 || modules.some((module) => activeSet.has(module));

                        if (wrapper) {
                            wrapper.style.display = allowed ? 'inline-flex' : 'none';
                        }

                        input.disabled = !allowed;
                        if (!allowed) {
                            input.checked = false;
                        }
                    });
                };

                syncOptionVisibility(serviceInputs);
                syncOptionVisibility(receptionInputs);
            };

            from?.addEventListener('change', syncRange);
            to?.addEventListener('change', syncRange);
            moduleInputs.forEach((input) => input.addEventListener('change', updateDependentFilters));
            syncRange();
            updateDependentFilters();
        })();
    </script>
@stop

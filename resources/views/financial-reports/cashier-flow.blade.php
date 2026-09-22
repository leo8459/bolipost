@extends('adminlte::page')

@section('title', 'Flujo de cajero')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Flujo de cajero</h1>
            <small class="text-muted">Ventas facturadas consolidadas por servicio, departamento y cajero.</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('dashboard.financiera.ventas-servicios', ['servicios' => $selectedServices, 'meses' => $selectedMonths, 'anio' => $anio, 'limite' => $limite]) }}" class="btn btn-outline-primary btn-sm mr-1">
                <i class="fas fa-layer-group mr-1"></i> Ventas por servicio
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>
    </div>
@stop

@push('css')
    <style>
        .flow-section-card { border-radius: 12px; overflow: hidden; }
        .flow-section-card .card-header { background: #fff; }
        .flow-icon { display: inline-flex; width: 34px; height: 34px; margin-right: 9px; align-items: center; justify-content: center; border-radius: 9px; background: #e8f1fc; color: #13539b; }
        .flow-rank { display: inline-flex; width: 27px; height: 27px; align-items: center; justify-content: center; border-radius: 50%; background: #edf2f7; color: #4a5568; font-weight: 700; }
        .flow-progress { min-width: 130px; }
        .flow-progress .progress { height: 5px; margin-top: 4px; border-radius: 8px; background: #edf2f7; }
        .flow-progress .progress-bar { background: #13539b; }
        .cashier-name { min-width: 210px; }
        .cashier-meta { line-height: 1.25; }
        .service-group-row { cursor: pointer; background: #f8fbff !important; border-top: 2px solid #d8e6f5; }
        .service-group-row:hover { background: #edf5ff !important; }
        .service-group-row:focus { outline: 2px solid #13539b; outline-offset: -2px; }
        .group-chevron { display: inline-flex; width: 24px; height: 24px; margin-right: 3px; align-items: center; justify-content: center; border-radius: 50%; color: #13539b; background: #e2eefc; transition: transform .2s ease; }
        .service-group-row.is-open .group-chevron { transform: rotate(90deg); }
        .service-child-row { background: #fff; }
        .child-service-name { position: relative; padding-left: 45px !important; font-weight: 600; }
        .child-connector { position: absolute; left: 20px; top: 0; width: 16px; height: 50%; border-left: 2px solid #cbd5e0; border-bottom: 2px solid #cbd5e0; border-radius: 0 0 0 6px; }
        @media (max-width: 767.98px) {
            .flow-progress { min-width: 100px; }
        }
    </style>
@endpush

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-flow-group-toggle]').forEach(function (row) {
                function toggleGroup() {
                    var groupId = row.dataset.flowGroupToggle;
                    var open = row.getAttribute('aria-expanded') !== 'true';
                    row.setAttribute('aria-expanded', open ? 'true' : 'false');
                    row.classList.toggle('is-open', open);
                    document.querySelectorAll('[data-flow-group-child="' + groupId + '"]').forEach(function (child) {
                        child.classList.toggle('d-none', !open);
                    });
                }

                row.addEventListener('click', toggleGroup);
                row.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggleGroup();
                    }
                });
            });
        });
    </script>
@endpush

@section('content')
    @include('financial-reports.partials.multi-filters', [
        'action' => route('dashboard.financiera.flujo-cajero'),
        'showLimit' => true,
        'filterTitle' => 'Prepare el flujo de cajero',
        'filterHelp' => 'Seleccione los servicios y meses que desea consolidar por departamento y cajero.',
    ])

    @if($errors->isNotEmpty())
        <div class="alert alert-warning">
            <div class="font-weight-bold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> Algunos meses no pudieron cargarse</div>
            <ul class="mb-0 pl-3">
                @foreach($errors as $reportError)
                    <li>{{ $reportError }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $executiveTopRegional = $regionalRows->first();
        $executiveTopCashier = $cashierRows->first();
        $executiveTopService = $serviceGroups->first();
        $executiveFlowIncome = (float) ($summary['totalMonto'] ?? 0);
        $executiveLead = 'Durante el periodo seleccionado, '
            . \App\Support\BolivianNumber::format($cashierRows->count())
            . ' cajeros registraron '
            . \App\Support\BolivianNumber::format((float) ($summary['cantidadVentas'] ?? 0))
            . ' ventas en '
            . \App\Support\BolivianNumber::format($regionalRows->count())
            . ' departamentos, generando Bs '
            . \App\Support\BolivianNumber::format($executiveFlowIncome, 2) . '.';
        $executiveItems = [
            [
                'label' => 'Departamento con mayor ingreso',
                'value' => $executiveTopRegional['regional'] ?? 'Sin datos',
                'detail' => $executiveTopRegional ? 'Bs ' . \App\Support\BolivianNumber::format((float) $executiveTopRegional['totalMonto'], 2) . ' registrados.' : 'No existe información regional.',
                'icon' => 'fa-map-marker-alt',
                'color' => 'warning',
            ],
            [
                'label' => 'Cajero con mayor ingreso',
                'value' => $executiveTopCashier['usuarioNombre'] ?? 'Sin datos',
                'detail' => $executiveTopCashier ? 'Bs ' . \App\Support\BolivianNumber::format((float) $executiveTopCashier['totalMonto'], 2) . ' registrados.' : 'No existe información por cajero.',
                'icon' => 'fa-user-tie',
                'color' => 'info',
            ],
            [
                'label' => 'Ingresos de ventanilla',
                'value' => 'Bs ' . \App\Support\BolivianNumber::format($executiveFlowIncome, 2),
                'detail' => \App\Support\BolivianNumber::format((float) ($summary['totalCantidad'] ?? 0), 2) . ' de cantidad total de paquetería.',
                'icon' => 'fa-money-bill-wave',
                'color' => 'success',
            ],
        ];
        $executiveNote = $executiveTopService
            ? 'El servicio con mayor ingreso es ' . $executiveTopService['servicio'] . ', con Bs ' . \App\Support\BolivianNumber::format((float) $executiveTopService['totalMonto'], 2) . '.'
            : 'No existen ventas para elaborar una conclusión del periodo seleccionado.';
    @endphp
    @include('financial-reports.partials.executive-summary')

    <div class="row">
        @foreach([
            ['Servicios', $summary['cantidadServicios'] ?? 0, 'fa-layer-group', 'primary'],
            ['Ventas registradas', $summary['cantidadVentas'] ?? 0, 'fa-file-invoice', 'info'],
            ['Departamentos', $regionalRows->count(), 'fa-map-marker-alt', 'warning'],
            ['Cajeros', $cashierRows->count(), 'fa-users', 'secondary'],
            ['Cantidad total de paquetería', $summary['totalCantidad'] ?? 0, 'fa-boxes', 'warning'],
            ['Ingresos de ventanilla', 'Bs ' . \App\Support\BolivianNumber::format((float) ($summary['totalMonto'] ?? 0), 2), 'fa-money-bill-wave', 'success'],
        ] as $metric)
            @php([$label, $value, $icon, $color] = $metric)
            <div class="col-sm-6 col-xl-4 mb-3">
                <div class="info-box bg-white border mb-0">
                    <span class="info-box-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ is_numeric($value) ? \App\Support\BolivianNumber::format((float) $value) : $value }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-outline card-primary flow-section-card">
        <div class="card-header d-flex align-items-center">
            <span class="flow-icon"><i class="fas fa-map-marked-alt"></i></span>
            <div>
                <strong>Facturación por departamento</strong>
                <div class="text-muted small">Consolidado de las regionales y sucursales reportadas por el sistema de facturación.</div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                @php($regionalTotal = (float) $regionalRows->sum('totalMonto'))
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-center">#</th>
                            <th>Departamento / regional</th>
                            <th>Sucursales</th>
                            <th class="text-right">Ventas registradas</th>
                            <th class="text-right">Detalles</th>
                            <th class="text-right">Cantidad de paquetería</th>
                            <th class="text-right">Ingresos de ventanilla</th>
                            <th>Participación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($regionalRows as $regional)
                            @php($share = $regionalTotal > 0 ? ((float) $regional['totalMonto'] / $regionalTotal) * 100 : 0)
                            <tr>
                                <td class="text-center"><span class="flow-rank">{{ $loop->iteration }}</span></td>
                                <td class="font-weight-bold">{{ $regional['regional'] }}</td>
                                <td>
                                    @forelse($regional['codigosSucursal'] as $branchCode)
                                        <span class="badge badge-light border">{{ $branchCode }}</span>
                                    @empty
                                        <span class="text-muted">-</span>
                                    @endforelse
                                </td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $regional['cantidadVentas']) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $regional['cantidadDetalles']) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $regional['totalCantidad'], 2) }}</td>
                                <td class="text-right font-weight-bold text-success">Bs {{ \App\Support\BolivianNumber::format((float) $regional['totalMonto'], 2) }}</td>
                                <td class="flow-progress">
                                    <span class="small font-weight-bold">{{ \App\Support\BolivianNumber::format($share, 1) }}%</span>
                                    <div class="progress"><div class="progress-bar" style="width: {{ min(100, $share) }}%"></div></div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">La API no devolvió información por departamento.</td></tr>
                        @endforelse
                    </tbody>
                    @if($regionalRows->isNotEmpty())
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td colspan="3">Totales</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $regionalRows->sum('cantidadVentas')) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $regionalRows->sum('cantidadDetalles')) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $regionalRows->sum('totalCantidad'), 2) }}</td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format($regionalTotal, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="card card-outline card-success flow-section-card">
        <div class="card-header d-flex align-items-center">
            <span class="flow-icon"><i class="fas fa-cash-register"></i></span>
            <div>
                <strong>Facturación por cajero</strong>
                <div class="text-muted small">Usuarios que registraron facturas durante el periodo seleccionado.</div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                @php($cashierTotal = (float) $cashierRows->sum('totalMonto'))
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-center">#</th>
                            <th>Cajero</th>
                            <th>Usuario</th>
                            <th class="text-right">Ventas registradas</th>
                            <th class="text-right">Detalles</th>
                            <th class="text-right">Cantidad de paquetería</th>
                            <th class="text-right">Ingresos de ventanilla</th>
                            <th>Participación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashierRows as $cashier)
                            @php($share = $cashierTotal > 0 ? ((float) $cashier['totalMonto'] / $cashierTotal) * 100 : 0)
                            <tr>
                                <td class="text-center"><span class="flow-rank">{{ $loop->iteration }}</span></td>
                                <td class="cashier-name">
                                    <div class="font-weight-bold">{{ $cashier['usuarioNombre'] }}</div>
                                    @if($cashier['usuarioCarnet'] !== '')
                                        <small class="text-muted">CI: {{ $cashier['usuarioCarnet'] }}</small>
                                    @endif
                                </td>
                                <td class="cashier-meta">
                                    @if($cashier['usuarioAlias'] !== '')
                                        <span class="badge badge-primary">{{ $cashier['usuarioAlias'] }}</span>
                                    @endif
                                    @if($cashier['usuarioEmail'] !== '')
                                        <div class="small text-muted mt-1">{{ $cashier['usuarioEmail'] }}</div>
                                    @endif
                                </td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashier['cantidadVentas']) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashier['cantidadDetalles']) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashier['totalCantidad'], 2) }}</td>
                                <td class="text-right font-weight-bold text-success">Bs {{ \App\Support\BolivianNumber::format((float) $cashier['totalMonto'], 2) }}</td>
                                <td class="flow-progress">
                                    <span class="small font-weight-bold">{{ \App\Support\BolivianNumber::format($share, 1) }}%</span>
                                    <div class="progress"><div class="progress-bar bg-success" style="width: {{ min(100, $share) }}%"></div></div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">La API no devolvió información por cajero.</td></tr>
                        @endforelse
                    </tbody>
                    @if($cashierRows->isNotEmpty())
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td colspan="3">Totales</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('cantidadVentas')) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('cantidadDetalles')) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('totalCantidad'), 2) }}</td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format($cashierTotal, 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary flow-section-card">
        <div class="card-header d-flex align-items-center">
            <span class="flow-icon"><i class="fas fa-layer-group"></i></span>
            <div>
                <strong>Facturación por servicio</strong>
                <div class="text-muted small">Haga clic en un grupo para mostrar u ocultar sus subservicios.</div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Servicio agrupado</th>
                            <th>Meses</th>
                            <th class="text-right">Ventas registradas</th>
                            <th class="text-right">Detalles</th>
                            <th class="text-right">Cantidad de paquetería</th>
                            <th class="text-right">Ingresos de ventanilla</th>
                            <th>Última fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceGroups as $group)
                            @php($groupId = 'cashier-service-group-' . $loop->iteration)
                            <tr class="service-group-row" data-flow-group-toggle="{{ $groupId }}" tabindex="0" role="button" aria-expanded="false">
                                <td><span class="group-chevron"><i class="fas fa-chevron-right"></i></span>{{ $loop->iteration }}</td>
                                <td class="font-weight-bold">{{ $group['servicio'] ?? '-' }}</td>
                                <td>
                                    @foreach($group['_meses'] ?? [] as $includedMonth)
                                        <span class="badge badge-light border">{{ [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'][$includedMonth] ?? $includedMonth }}</span>
                                    @endforeach
                                </td>
                                <td class="text-right font-weight-bold">{{ \App\Support\BolivianNumber::format((float) ($group['cantidadVentas'] ?? 0)) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($group['cantidadDetalles'] ?? 0)) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($group['totalCantidad'] ?? 0), 2) }}</td>
                                <td class="text-right font-weight-bold text-success">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMonto'] ?? 0), 2) }}</td>
                                <td class="text-nowrap">{{ $group['ultimaFecha'] ?? '-' }}</td>
                            </tr>
                            @foreach($group['_children'] ?? [] as $child)
                                <tr class="service-child-row d-none" data-flow-group-child="{{ $groupId }}">
                                    <td></td>
                                    <td class="child-service-name"><span class="child-connector"></span>{{ $child['servicio'] ?? '-' }}</td>
                                    <td></td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($child['cantidadVentas'] ?? 0)) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($child['cantidadDetalles'] ?? 0)) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($child['totalCantidad'] ?? 0), 2) }}</td>
                                    <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMonto'] ?? 0), 2) }}</td>
                                    <td class="text-nowrap">{{ $child['ultimaFecha'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron servicios para la selección realizada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop

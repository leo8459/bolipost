@extends('adminlte::page')

@section('title', 'Flujo de cajero')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Flujo de cajero</h1>
            <small class="text-muted">Ventas facturadas consolidadas por servicio y cajero.</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('dashboard.financiera.flujo-cajero', ['servicios' => $selectedServices, 'meses' => $selectedMonths, 'anio' => $anio, 'limite' => $limite, 'departamento' => $selectedDepartment, 'actualizar' => 1]) }}" class="btn btn-outline-info btn-sm mr-1" title="Consultar nuevamente los datos de facturación">
                <i class="fas fa-sync-alt mr-1" aria-hidden="true"></i> Actualizar datos
            </a>
            <a href="{{ route('dashboard.financiera.flujo-cajero.pdf', ['servicios' => $selectedServices, 'meses' => $selectedMonths, 'anio' => $anio, 'limite' => $limite, 'departamento' => $selectedDepartment]) }}"
               class="btn btn-danger btn-sm mr-1"
               data-report-download
               data-wait-for-download
               data-loading-title="Generando reporte"
               data-loading-message="Espere por favor, estamos preparando el PDF ejecutivo.">
                <i class="fas fa-file-pdf mr-1"></i> Descargar reporte ejecutivo
            </a>
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
        .flow-icon-warning { background: #fff3cd; color: #856404; }
        .flow-rank { display: inline-flex; width: 27px; height: 27px; align-items: center; justify-content: center; border-radius: 50%; background: #edf2f7; color: #4a5568; font-weight: 700; }
        .flow-progress { min-width: 130px; }
        .flow-progress .progress { height: 5px; margin-top: 4px; border-radius: 8px; background: #edf2f7; }
        .flow-progress .progress-bar { background: #13539b; }
        .cashier-name { min-width: 210px; }
        .cashier-meta { line-height: 1.25; }
        .service-group-row { cursor: pointer; background: #f8fbff !important; border-top: 2px solid #d8e6f5; }
        .service-group-row:hover { background: #edf5ff !important; }
        .group-toggle-button { display: inline-flex; align-items: center; min-width: 44px; min-height: 40px; border: 0; background: transparent; color: #102a43; font-weight: 600; }
        .group-toggle-button:focus { outline: 3px solid #13539b; outline-offset: 2px; }
        .group-chevron { display: inline-flex; width: 24px; height: 24px; margin-right: 3px; align-items: center; justify-content: center; border-radius: 50%; color: #13539b; background: #e2eefc; transition: transform .2s ease; }
        .service-group-row.is-open .group-chevron { transform: rotate(90deg); }
        .service-child-row { background: #fff; }
        .child-service-name { position: relative; padding-left: 45px !important; font-weight: 600; }
        .child-connector { position: absolute; left: 20px; top: 0; width: 16px; height: 50%; border-left: 2px solid #cbd5e0; border-bottom: 2px solid #cbd5e0; border-radius: 0 0 0 6px; }
        .flow-detail-open { min-height: 42px; min-width: 112px; font-size: 1rem; font-weight: 600; }
        .flow-detail-dialog { width: calc(100% - 2rem); max-width: 1220px; }
        .flow-detail-modal .modal-content { border-radius: 12px; overflow: hidden; }
        .flow-detail-modal .modal-header { background: #f3f7fc; border-bottom: 2px solid #d5e2f0; align-items: center; }
        .flow-detail-modal .modal-title { color: #102a43; font-size: 1.55rem; font-weight: 700; }
        .flow-detail-modal .close { color: #102a43; opacity: 1; font-size: 2rem; min-width: 44px; min-height: 44px; }
        .flow-detail-modal .alert { font-size: 1.1rem; }
        .flow-detail-modal .close:focus, .flow-detail-open:focus, .flow-detail-modal .btn:focus, .flow-detail-pagination a:focus { outline: 3px solid #13539b; outline-offset: 2px; }
        .flow-detail-modal .modal-body { color: #172b4d; font-size: 1.2rem; line-height: 1.5; max-height: calc(100vh - 180px); overflow-y: auto; padding: 1.25rem; }
        .flow-detail-modal .modal-footer .btn { font-size: 1.1rem; min-height: 44px; padding: .5rem 1.25rem; }
        .flow-detail-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: .75rem; margin-bottom: 1.5rem; }
        .flow-detail-summary > div { background: #f3f7fc; border: 1px solid #cbd9e8; border-radius: 8px; padding: .85rem 1rem; }
        .flow-detail-summary span, .flow-detail-label { display: block; color: #334e68; font-size: 1.05rem; font-weight: 600; }
        .flow-detail-summary strong { display: block; color: #102a43; font-size: 1.3rem; line-height: 1.25; margin-top: .25rem; }
        .flow-detail-search-form { margin: 1.25rem 0 1.5rem; }
        .flow-detail-search-form label { display: block; color: #102a43; font-size: 1.15rem; font-weight: 700; margin-bottom: .5rem; }
        .flow-detail-search-form .form-control, .flow-detail-search-form .input-group-text, .flow-detail-search-form .btn { font-size: 1.1rem; min-height: 54px; }
        .flow-detail-search-form .input-group-text { color: #102a43; background: #f3f7fc; }
        .flow-detail-search-help { color: #334e68; font-size: 1rem; margin-top: .4rem; }
        .flow-detail-list-heading { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .5rem; margin-bottom: .75rem; }
        .flow-detail-list-heading h5 { color: #102a43; font-size: 1.3rem; font-weight: 700; }
        .flow-detail-list-heading span { font-weight: 600; }
        .flow-detail-item { border: 1px solid #b7c9db; border-radius: 9px; padding: 1rem 1.15rem; margin-bottom: .85rem; background: #fff; }
        .flow-detail-item:nth-of-type(even) { background: #f8fbff; }
        .flow-detail-item-heading { display: flex; flex-wrap: wrap; align-items: start; justify-content: space-between; gap: .5rem 1rem; margin-bottom: .8rem; }
        .flow-detail-item-heading > div { min-width: 0; }
        .flow-detail-item-heading strong { overflow-wrap: anywhere; }
        .flow-detail-amount { color: #116329; font-size: 1.4rem; white-space: nowrap; }
        .flow-detail-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem 1rem; }
        .flow-detail-fields strong { display: block; overflow-wrap: anywhere; }
        .flow-detail-description { margin-top: .8rem; padding-top: .7rem; border-top: 1px solid #d5e2f0; overflow-wrap: anywhere; }
        .flow-detail-empty { border: 1px solid #cbd9e8; border-radius: 8px; padding: 1.5rem; text-align: center; }
        .flow-detail-pagination { margin-top: 1.25rem; }
        .flow-detail-pagination .pagination { flex-wrap: wrap; gap: .25rem; margin-bottom: 0; }
        .flow-detail-pagination .page-link { font-size: 1.1rem; min-width: 44px; min-height: 44px; padding: .5rem .75rem; text-align: center; }
        @media (max-width: 767.98px) {
            .flow-progress { min-width: 100px; }
            .flow-detail-dialog { width: calc(100% - 1rem); margin: .5rem auto; }
            .flow-detail-modal .modal-body { max-height: calc(100vh - 145px); padding: .85rem; }
            .flow-detail-fields { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 480px) {
            .flow-detail-fields { grid-template-columns: 1fr; }
        }
        @media (max-width: 575.98px) {
            .flow-detail-search-form .input-group { display: flex; flex-wrap: wrap; }
            .flow-detail-search-form .input-group-prepend { display: none; }
            .flow-detail-search-form .form-control { flex: 0 0 100%; width: 100%; border-radius: .25rem !important; }
            .flow-detail-search-form .input-group-append { display: flex; flex: 0 0 100%; margin: .5rem 0 0; }
            .flow-detail-search-form .input-group-append .btn { flex: 1 1 50%; }
        }
    </style>
@endpush

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var pageUrl = new URL(window.location.href);
            if (pageUrl.searchParams.has('actualizar')) {
                pageUrl.searchParams.delete('actualizar');
                window.history.replaceState(window.history.state, '', pageUrl.pathname + pageUrl.search + pageUrl.hash);
            }

            document.querySelectorAll('[data-flow-group-toggle]').forEach(function (row) {
                var button = row.querySelector('[data-flow-toggle-button]');
                function toggleGroup() {
                    var groupId = row.dataset.flowGroupToggle;
                    var open = button.getAttribute('aria-expanded') !== 'true';
                    button.setAttribute('aria-expanded', open ? 'true' : 'false');
                    button.setAttribute('aria-label', (open ? 'Ocultar' : 'Mostrar') + ' subservicios de ' + button.dataset.flowToggleName);
                    row.classList.toggle('is-open', open);
                    document.querySelectorAll('[data-flow-group-child="' + groupId + '"]').forEach(function (child) {
                        child.classList.toggle('d-none', !open);
                    });
                }

                row.addEventListener('click', function (event) {
                    if (!event.target.closest('[data-flow-detail-trigger]')) {
                        toggleGroup();
                    }
                });
            });

            var detailModal = document.getElementById('flow-detail-modal');
            if (!detailModal || !window.jQuery) {
                return;
            }

            var detailBody = detailModal.querySelector('[data-flow-detail-body]');
            var detailTitle = detailModal.querySelector('[data-flow-detail-title]');
            var detailStatus = detailModal.querySelector('[data-flow-detail-status]');
            var activeRequest = null;
            var requestId = 0;
            var currentUrl = '';

            function loadDetail(url, focusSearch) {
                if (activeRequest) {
                    activeRequest.abort();
                }
                activeRequest = new AbortController();
                var thisRequest = ++requestId;
                currentUrl = url;
                detailStatus.textContent = 'Cargando detalle.';
                detailBody.innerHTML = '<div class="text-center py-5" role="status"><i class="fas fa-spinner fa-spin fa-2x mb-3" aria-hidden="true"></i><div>Cargando movimientos...</div></div>';

                fetch(url, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    signal: activeRequest.signal
                }).then(function (response) {
                    if (!response.ok || response.headers.get('X-Flow-Detail-Fragment') !== '1') {
                        throw new Error('No se pudo cargar el detalle.');
                    }
                    return response.text();
                }).then(function (html) {
                    if (thisRequest !== requestId) {
                        return;
                    }
                    detailBody.innerHTML = html;
                    if (focusSearch) {
                        var searchInput = detailBody.querySelector('[data-flow-detail-search]');
                        if (searchInput) {
                            searchInput.focus();
                            searchInput.setSelectionRange(searchInput.value.length, searchInput.value.length);
                        }
                    } else {
                        var start = detailBody.querySelector('[data-flow-detail-start]');
                        if (start) {
                            start.focus();
                        }
                    }
                    detailBody.scrollTop = 0;
                    detailStatus.textContent = 'Detalle cargado.';
                }).catch(function (error) {
                    if (error.name === 'AbortError' || thisRequest !== requestId) {
                        return;
                    }
                    detailBody.innerHTML = '<div class="alert alert-danger mb-0" role="alert">No se pudo cargar el detalle. <button type="button" class="btn btn-outline-danger ml-2" data-flow-detail-retry>Reintentar</button></div>';
                    detailStatus.textContent = 'No se pudo cargar el detalle.';
                });
            }

            document.querySelectorAll('[data-flow-detail-trigger]').forEach(function (trigger) {
                trigger.addEventListener('click', function (event) {
                    event.stopPropagation();
                    if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                        return;
                    }
                    event.preventDefault();
                    detailTitle.textContent = trigger.dataset.flowDetailTitle;
                    var url = new URL(trigger.href, window.location.href);
                    url.searchParams.set('modal', '1');
                    window.jQuery(detailModal).modal('show');
                    loadDetail(url.toString());
                });
            });

            detailModal.addEventListener('click', function (event) {
                var clearButton = event.target.closest('[data-flow-detail-clear]');
                if (clearButton) {
                    var clearUrl = new URL(currentUrl, window.location.href);
                    clearUrl.searchParams.delete('buscar');
                    clearUrl.searchParams.delete('page');
                    clearUrl.searchParams.delete('actualizar');
                    loadDetail(clearUrl.toString(), true);
                    return;
                }

                var refreshButton = event.target.closest('[data-flow-detail-refresh]');
                if (refreshButton && currentUrl) {
                    var refreshUrl = new URL(currentUrl, window.location.href);
                    refreshUrl.searchParams.set('actualizar', '1');
                    loadDetail(refreshUrl.toString());
                    return;
                }

                var retryButton = event.target.closest('[data-flow-detail-retry]');
                if (retryButton) {
                    loadDetail(currentUrl);
                    return;
                }

                var pageLink = event.target.closest('.flow-detail-pagination a');
                if (pageLink) {
                    event.preventDefault();
                    loadDetail(pageLink.href);
                }
            });

            detailModal.addEventListener('submit', function (event) {
                var searchForm = event.target.closest('[data-flow-detail-search-form]');
                if (!searchForm) {
                    return;
                }
                event.preventDefault();

                var searchUrl = new URL(currentUrl, window.location.href);
                var searchInput = searchForm.querySelector('[data-flow-detail-search]');
                var term = searchInput.value.trim();
                searchUrl.searchParams.set('modal', '1');
                searchUrl.searchParams.delete('page');
                searchUrl.searchParams.delete('actualizar');
                if (term !== '') {
                    searchUrl.searchParams.set('buscar', term);
                } else {
                    searchUrl.searchParams.delete('buscar');
                }
                loadDetail(searchUrl.toString(), true);
            });

            window.jQuery(detailModal).on('hidden.bs.modal', function () {
                requestId++;
                if (activeRequest) {
                    activeRequest.abort();
                }
                detailBody.innerHTML = '';
                detailStatus.textContent = '';
            });
        });
    </script>
@endpush

@section('content')
    @include('financial-reports.partials.multi-filters', [
        'action' => route('dashboard.financiera.flujo-cajero'),
        'showLimit' => true,
        'showDepartmentFilter' => true,
        'departmentOptions' => $departmentOptions,
        'selectedDepartment' => $selectedDepartment,
        'filterTitle' => 'Prepare el flujo de cajero',
        'filterHelp' => 'Seleccione los servicios y meses que desea consolidar. Contratos y ECA Internacional se muestran aparte; al marcar su cobro, el importe pasa a Total recaudado.',
    ])

    <div class="alert alert-info border-0 shadow-sm">
        <i class="fas fa-info-circle mr-1"></i>
        <strong>Criterio del reporte:</strong> Contratos y ECA Internacional se muestran por separado. Registra el cobro o la devolución desde cada movimiento de su detalle; los paquetes siguen separados de los totales operativos.
    </div>

    @if(session('cashierFlowSuccess'))
        <div class="alert alert-success border-0 shadow-sm" role="status">
            <i class="fas fa-check-circle mr-1"></i>{{ session('cashierFlowSuccess') }}
        </div>
    @endif
    @if(session('cashierFlowError'))
        <div class="alert alert-danger border-0 shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i>{{ session('cashierFlowError') }}
        </div>
    @endif

    @if($selectedDepartment !== '')
        <div class="alert alert-success border-0 shadow-sm">
            <i class="fas fa-map-marker-alt mr-1"></i>
            Reporte filtrado exclusivamente para <strong>{{ $selectedDepartment }}</strong>.
        </div>
    @endif

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
        $executiveTopCashier = $cashierRows->first();
        $executiveTopService = $serviceGroups->first();
        $executiveFlowIncome = (float) ($summary['totalRecaudado'] ?? $summary['totalMonto'] ?? 0);
        $executiveDepartmentScope = $selectedDepartment !== '' ? ' en ' . $selectedDepartment : '';
        $executiveLead = 'Durante el periodo seleccionado se registraron '
            . \App\Support\BolivianNumber::format((float) ($summary['cantidadVentas'] ?? 0))
            . ' ventas de ventanilla' . $executiveDepartmentScope . '; los ingresos totales, incluidos los cobros aceptados, suman Bs '
            . \App\Support\BolivianNumber::format($executiveFlowIncome, 2) . '.';
        $executiveItems = [
            [
                'label' => 'Cajero con mayor ingreso',
                'value' => $executiveTopCashier['usuarioNombre'] ?? 'Sin datos',
                'detail' => $executiveTopCashier ? 'Bs ' . \App\Support\BolivianNumber::format((float) ($executiveTopCashier['totalIngresos'] ?? $executiveTopCashier['totalMonto']), 2) . ' en ingresos totales.' : 'No existe información por cajero.',
                'icon' => 'fa-user-tie',
                'color' => 'info',
            ],
            [
                'label' => 'Ingresos totales',
                'value' => 'Bs ' . \App\Support\BolivianNumber::format($executiveFlowIncome, 2),
                'detail' => 'Incluye Contratos y ECA Internacional cobrados.',
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
            ['Ventas realizadas', $summary['cantidadVentas'] ?? 0, 'fa-file-invoice', 'info'],
            ['Cajeros', $cashierRows->count(), 'fa-users', 'secondary'],
            ['Cantidad de paquetes', $summary['totalCantidad'] ?? 0, 'fa-boxes', 'warning'],
            ['Ingresos totales (incluye Contratos y ECA cobrados)', 'Bs ' . \App\Support\BolivianNumber::format((float) ($summary['totalRecaudado'] ?? $summary['totalMonto'] ?? 0), 2), 'fa-cash-register', 'primary'],
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

    @if($receivableServices->isNotEmpty())
        <div class="card card-outline card-warning flow-section-card">
            <div class="card-header d-flex align-items-center">
                <span class="flow-icon flow-icon-warning"><i class="fas fa-file-invoice-dollar"></i></span>
                <div>
                    <strong>Cuentas por cobrar y cobros realizados</strong>
                    <div class="text-muted small">Contratos y ECA Internacional; los cobros realizados ya forman parte del total recaudado.</div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover mb-0 financial-table">
                        <thead class="thead-light">
                            <tr>
                                <th>Servicio</th>
                                <th>Meses</th>
                                <th class="text-right">Ventas</th>
                                <th class="text-right">Detalles</th>
                                <th class="text-right">Cantidad de paquetes</th>
                                <th class="text-right">Paquetes promedio por día</th>
                                <th class="text-right">Importe</th>
                                <th class="text-right">Cobrado</th>
                                <th class="text-right">Pendiente</th>
                                <th class="text-center">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($receivableServices as $receivableService)
                                @php($isContractReceivable = \Illuminate\Support\Str::startsWith((string) ($receivableService['servicio'] ?? ''), 'Servicio Contratos'))
                                <tr>
                                    <td class="font-weight-bold">
                                        {{ $isContractReceivable ? 'Contratos' : ($receivableService['servicio'] ?? '-') }}
                                        @if($isContractReceivable)
                                            <small class="d-block text-muted">{{ $receivableService['servicio'] }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @foreach($receivableService['_meses'] ?? [] as $includedMonth)
                                            <span class="badge badge-light border">{{ [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'][$includedMonth] ?? $includedMonth }}</span>
                                        @endforeach
                                    </td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($receivableService['cantidadVentas'] ?? 0)) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($receivableService['cantidadDetalles'] ?? 0)) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($receivableService['totalCantidad'] ?? 0), 2) }}</td>
                                    <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($receivableService['promedioPaquetesDiario'] ?? 0), 2) }}</td>
                                    <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableService['totalMonto'] ?? 0), 2) }}</td>
                                    <td class="text-right text-success font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableService['_montoCobrado'] ?? 0), 2) }}</td>
                                    <td class="text-right text-warning font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableService['_montoPendiente'] ?? $receivableService['totalMonto'] ?? 0), 2) }}</td>
                                    <td class="text-center">
                                        <a class="btn btn-outline-primary btn-sm flow-detail-open" href="{{ route('dashboard.financiera.ventas-servicios.detalle', ['servicios' => [$receivableService['servicio']], 'meses' => $selectedMonths, 'anio' => $anio, 'flujo_cajero' => 1, 'filtro_servicios' => $selectedServices, 'filtro_meses' => $selectedMonths, 'filtro_anio' => $anio, 'filtro_limite' => $limite, 'filtro_departamento' => $selectedDepartment]) }}" data-flow-detail-trigger data-flow-detail-title="{{ $isContractReceivable ? 'Contratos' : $receivableService['servicio'] }}" aria-haspopup="dialog" aria-controls="flow-detail-modal">
                                            <i class="fas fa-list mr-1"></i> Detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted px-3 py-2 border-top">El promedio usa los días del periodo seleccionado, de lunes a sábado; los domingos quedan fuera. Los importes marcados como cobrados se incluyen una sola vez en Total recaudado.</div>
            </div>
        </div>
    @endif

    <div class="card card-outline card-success flow-section-card">
        <div class="card-header d-flex align-items-center">
            <span class="flow-icon"><i class="fas fa-cash-register"></i></span>
            <div>
                <strong>Ingresos por cajero</strong>
                <div class="text-muted small">Los cobros aceptados se atribuyen al usuario que facturó el contrato o ECA.</div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                @php($cashierTotal = (float) $cashierRows->sum('totalIngresos'))
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-center">#</th>
                            <th>Cajero</th>
                            <th>Regional / departamento</th>
                            <th class="text-right">Ventas realizadas</th>
                            <th class="text-right">Detalles</th>
                            <th class="text-right">Cantidad de paquetes</th>
                            <th class="text-right">Paquetes promedio por día</th>
                            <th class="text-right">Ingresos totales</th>
                            <th>Participación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashierRows as $cashier)
                            @php($share = $cashierTotal > 0 ? ((float) $cashier['totalIngresos'] / $cashierTotal) * 100 : 0)
                            <tr>
                                <td class="text-center"><span class="flow-rank">{{ $loop->iteration }}</span></td>
                                <td class="cashier-name">
                                    <div class="font-weight-bold">{{ $cashier['usuarioNombre'] }}</div>
                                    @if($cashier['usuarioCarnet'] !== '')
                                        <small class="text-muted">CI: {{ $cashier['usuarioCarnet'] }}</small>
                                    @endif
                                </td>
                                <td class="cashier-meta">
                                    <span class="badge badge-primary">{{ $cashier['departamento'] }}</span>
                                </td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashier['cantidadVentas']) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashier['cantidadDetalles']) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashier['totalCantidad'], 2) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($cashier['promedioPaquetesDiario'] ?? 0), 2) }}</td>
                                <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($cashier['totalIngresos'] ?? $cashier['totalMonto']), 2) }}</td>
                                <td class="flow-progress">
                                    <span class="small font-weight-bold">{{ \App\Support\BolivianNumber::format($share, 1) }}%</span>
                                    <div class="progress"><div class="progress-bar bg-success" style="width: {{ min(100, $share) }}%"></div></div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">La API no devolvió información por cajero.</td></tr>
                        @endforelse
                    </tbody>
                    @if($cashierRows->isNotEmpty())
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td colspan="3">Totales</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('cantidadVentas')) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('cantidadDetalles')) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('totalCantidad'), 2) }}</td>
                                <td></td>
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
                <strong>Ingresos por servicio</strong>
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
                            <th class="text-right">Ventas realizadas</th>
                            <th class="text-right">Detalles</th>
                            <th class="text-right">Cantidad de paquetes</th>
                            <th class="text-right">Ingresos totales</th>
                            <th>Última fecha</th>
                            <th class="text-center">Movimientos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceGroups as $group)
                            @php($groupId = 'cashier-service-group-' . $loop->iteration)
                            <tr class="service-group-row" data-flow-group-toggle="{{ $groupId }}">
                                <td>
                                    <button type="button" class="group-toggle-button" data-flow-toggle-button data-flow-toggle-name="{{ $group['servicio'] ?? '-' }}" aria-expanded="false" aria-label="Mostrar subservicios de {{ $group['servicio'] ?? '-' }}">
                                        <span class="group-chevron"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>{{ $loop->iteration }}
                                    </button>
                                </td>
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
                                <td class="text-nowrap">
                                    {{ $group['ultimaFecha'] ?? '-' }}
                                    @if($group['_ultimaFechaEsCobro'] ?? false)<small class="d-block text-muted">Fecha del Ãºltimo cobro</small>@endif
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-outline-primary flow-detail-open" href="{{ route('dashboard.financiera.ventas-servicios.detalle', ['servicios' => collect($group['_children'] ?? [])->pluck('servicio')->all(), 'meses' => $selectedMonths, 'anio' => $anio]) }}" data-flow-detail-trigger data-flow-detail-title="{{ $group['servicio'] ?? '-' }}" aria-haspopup="dialog" aria-controls="flow-detail-modal">
                                        <i class="fas fa-list mr-1"></i> Ver detalle
                                    </a>
                                </td>
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
                                    <td class="text-nowrap">
                                        {{ $child['ultimaFecha'] ?? '-' }}
                                        @if($child['_esCobroReceivable'] ?? false)<small class="d-block text-muted">Fecha de cobro</small>@endif
                                    </td>
                                    <td class="text-center">
                                        <a class="btn btn-outline-primary flow-detail-open" href="{{ route('dashboard.financiera.ventas-servicios.detalle', ['servicios' => [$child['servicio'] ?? ''], 'meses' => $selectedMonths, 'anio' => $anio]) }}" data-flow-detail-trigger data-flow-detail-title="{{ $child['servicio'] ?? '-' }}" aria-haspopup="dialog" aria-controls="flow-detail-modal">
                                            <i class="fas fa-search mr-1"></i> Detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No se encontraron servicios para la selección realizada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="modal fade flow-detail-modal" id="flow-detail-modal" tabindex="-1" role="dialog" aria-labelledby="flow-detail-modal-title" aria-hidden="true">
        <div class="modal-dialog flow-detail-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="flow-detail-modal-title"><span data-flow-detail-title></span></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar detalle">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                @if($selectedDepartment !== '')
                    <div class="alert alert-info rounded-0 mb-0" role="note">
                        El detalle individual incluye todos los departamentos del servicio. El filtro de {{ $selectedDepartment }} se aplica al resumen de esta página.
                    </div>
                @endif
                <div class="modal-body" data-flow-detail-body></div>
                <div class="sr-only" aria-live="polite" data-flow-detail-status></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-flow-detail-refresh>
                        <i class="fas fa-sync-alt mr-1" aria-hidden="true"></i> Actualizar detalle
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@stop

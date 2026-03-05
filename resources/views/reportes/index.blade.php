@extends('adminlte::page')

@section('title', $scopeLabel)

@section('content_header')
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h1 class="mb-0">{{ $scopeLabel }}</h1>
            <small class="text-muted">Panel simple para buscar paquetes, revisar estados y sacar reportes</small>
        </div>
        <div class="mt-2 mt-lg-0 text-lg-right">
            <span class="badge badge-pill badge-primary mr-2">Registrados: {{ number_format($summary['registrados'] ?? 0) }}</span>
            <span class="badge badge-pill badge-success mr-2">Entregados: {{ number_format($summary['entregados'] ?? 0) }}</span>
            <span class="badge badge-pill badge-warning">Pendientes: {{ number_format($summary['no_entregados'] ?? 0) }}</span>
        </div>
    </div>
@stop

@section('content')
    @php
        $baseScopeParams = ['scope' => $scope];
        $modulesMap = [
            'contrato' => 'CONTRATOS',
            'ems' => 'EMS',
            'certi' => 'CERTIFICADOS',
            'ordi' => 'ORDINARIOS',
        ];
    @endphp

    <div class="card card-filtro mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Filtros de Reporte</strong>
            <button type="button" class="btn btn-sm btn-outline-primary" id="toggleReportFilters">
                <i class="fas fa-filter mr-1"></i><span id="toggleReportFiltersText">Ocultar filtros</span>
            </button>
        </div>
        <div class="card-body" id="reportFiltersBody">
            <form method="GET" action="{{ route('reportes.scope', $baseScopeParams) }}" id="reportFiltersForm">
                <input type="hidden" name="range" id="reportRange" value="{{ $range ?? 'all' }}">
                <div class="alert alert-light border d-flex flex-column flex-md-row justify-content-between align-items-md-center py-2 mb-3">
                    <div>
                        <strong>Filtro automatico activado:</strong>
                        al cambiar fechas, estados, busqueda o modulos, el reporte se actualiza solo.
                    </div>
                    <span class="badge badge-success mt-2 mt-md-0" id="autoFilterStatus">Listo para usar</span>
                </div>
                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <label class="font-weight-bold">Buscar paquete o persona</label>
                        <input
                            type="text"
                            class="form-control"
                            name="q"
                            value="{{ $search }}"
                            placeholder="Ejemplo: RP123..., EYNAR, COCHABAMBA, GESTORA"
                        >
                        <small class="text-muted">Puedes buscar por codigo, estado, origen/destino, remitente, destinatario, empresa o usuario.</small>
                    </div>

                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Que tipo de paquetes ver</label>
                        <select class="form-control" name="status">
                            <option value="all" {{ $status === 'all' ? 'selected' : '' }}>Todos los paquetes</option>
                            <option value="entregado" {{ $status === 'entregado' ? 'selected' : '' }}>Solo entregados</option>
                            <option value="no_entregado" {{ $status === 'no_entregado' ? 'selected' : '' }}>Solo no entregados</option>
                        </select>
                    </div>

                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Cuantos registros cargar</label>
                        <select class="form-control" name="limit">
                            <option value="50" {{ $limit === '50' ? 'selected' : '' }}>50</option>
                            <option value="100" {{ $limit === '100' ? 'selected' : '' }}>100</option>
                            <option value="200" {{ $limit === '200' ? 'selected' : '' }}>200</option>
                            <option value="500" {{ $limit === '500' ? 'selected' : '' }}>500</option>
                            <option value="1000" {{ $limit === '1000' ? 'selected' : '' }}>1000</option>
                            <option value="all" {{ $limit === 'all' ? 'selected' : '' }}>Todo</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Fecha desde</label>
                        <input type="date" class="form-control" name="from" id="reportFrom" value="{{ $from }}">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Fecha hasta</label>
                        <input type="date" class="form-control" name="to" id="reportTo" value="{{ $to }}">
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Registros por pagina</label>
                        <select class="form-control" name="per_page">
                            <option value="25" {{ (int) $perPage === 25 ? 'selected' : '' }}>25</option>
                            <option value="50" {{ (int) $perPage === 50 ? 'selected' : '' }}>50</option>
                            <option value="100" {{ (int) $perPage === 100 ? 'selected' : '' }}>100</option>
                        </select>
                    </div>
                    <div class="col-lg-3 mb-3">
                        <label class="font-weight-bold">Tipo de reporte</label>
                        <select class="form-control" id="scopeSwitcher">
                            <option value="general" {{ $scope === 'general' ? 'selected' : '' }}>General</option>
                            <option value="contrato" {{ $scope === 'contrato' ? 'selected' : '' }}>Contratos</option>
                            <option value="ems" {{ $scope === 'ems' ? 'selected' : '' }}>EMS</option>
                            <option value="certi" {{ $scope === 'certi' ? 'selected' : '' }}>Certificados</option>
                            <option value="ordi" {{ $scope === 'ordi' ? 'selected' : '' }}>Ordinarios</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <label class="font-weight-bold">Estados del paquete (varios)</label>
                        <select class="form-control" name="estado_ids[]" multiple size="6">
                            @foreach($states as $estado)
                                <option
                                    value="{{ $estado->id }}"
                                    {{ in_array((int) $estado->id, $selectedEstadoIds, true) ? 'selected' : '' }}
                                >
                                    {{ $estado->nombre_estado }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">Mantener CTRL para seleccionar varios estados al mismo tiempo.</small>
                    </div>

                    @if($scope === 'general')
                        <div class="col-lg-6 mb-3">
                            <label class="font-weight-bold d-block">Modulos a incluir en el reporte general</label>
                            <div class="d-flex flex-wrap border rounded p-2">
                                @foreach($modulesMap as $moduleKey => $moduleLabel)
                                    <div class="custom-control custom-checkbox mr-4 mb-2">
                                        <input
                                            type="checkbox"
                                            class="custom-control-input"
                                            id="module_{{ $moduleKey }}"
                                            name="modules[]"
                                            value="{{ $moduleKey }}"
                                            {{ in_array($moduleKey, $selectedModules, true) ? 'checked' : '' }}
                                        >
                                        <label class="custom-control-label" for="module_{{ $moduleKey }}">{{ $moduleLabel }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="d-flex flex-wrap align-items-center">
                    <button type="submit" class="btn btn-primary mr-2 mb-2">
                        <i class="fas fa-search mr-1"></i> Actualizar ahora
                    </button>
                    <a href="{{ route('reportes.scope', $baseScopeParams) }}" class="btn btn-outline-secondary mr-2 mb-2">
                        <i class="fas fa-undo mr-1"></i> Volver a empezar
                    </a>
                    <button type="button" class="btn btn-outline-info quick-range mr-2 mb-2" data-days="1">Hoy</button>
                    <button type="button" class="btn btn-outline-info quick-range mr-2 mb-2" data-days="7">Ult. 7 dias</button>
                    <button type="button" class="btn btn-outline-info quick-range mr-2 mb-2" data-days="30">Ult. 30 dias</button>
                    <button type="button" class="btn btn-outline-info quick-range mr-2 mb-2" data-month="1">Mes actual</button>
                    <button type="button" class="btn btn-outline-info quick-range-all mr-2 mb-2">Todo historial</button>
                </div>
            </form>
        </div>

        <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center">
            <div class="text-muted small mb-2 mb-md-0">
                Modulos activos:
                @foreach($moduleLabels as $label)
                    <span class="badge badge-info mr-1">{{ $label }}</span>
                @endforeach
            </div>
            <div class="d-flex flex-wrap">
                <a
                    href="{{ route('reportes.export.excel', $baseScopeParams) }}"
                    class="btn btn-success btn-sm mr-2 mb-2"
                    id="exportExcelBtn"
                    data-export="excel"
                >
                    <i class="fas fa-file-excel mr-1"></i> Exportar Excel
                </a>
                <a
                    href="{{ route('reportes.export.pdf', $baseScopeParams) }}"
                    class="btn btn-danger btn-sm mb-2"
                    id="exportPdfBtn"
                    data-export="pdf"
                >
                    <i class="fas fa-file-pdf mr-1"></i> Exportar PDF
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="small-box bg-primary mb-0">
                <div class="inner">
                    <h3>{{ number_format($summary['total'] ?? 0) }}</h3>
                    <p>Total</p>
                </div>
                <div class="icon"><i class="fas fa-boxes"></i></div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="small-box bg-success mb-0">
                <div class="inner">
                    <h3>{{ number_format($summary['entregados'] ?? 0) }}</h3>
                    <p>Entregados</p>
                </div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="small-box bg-warning mb-0">
                <div class="inner">
                    <h3>{{ number_format($summary['no_entregados'] ?? 0) }}</h3>
                    <p>No entregados</p>
                </div>
                <div class="icon"><i class="fas fa-clock"></i></div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="small-box bg-teal mb-0">
                <div class="inner">
                    <h3>{{ number_format($summary['correcto'] ?? 0) }}</h3>
                    <p>Correcto</p>
                </div>
                <div class="icon"><i class="fas fa-thumbs-up"></i></div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="small-box bg-orange mb-0">
                <div class="inner">
                    <h3>{{ number_format($summary['retraso'] ?? 0) }}</h3>
                    <p>Retraso</p>
                </div>
                <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-6 mb-3">
            <div class="small-box bg-danger mb-0">
                <div class="inner">
                    <h3>{{ number_format($summary['rezago'] ?? 0) }}</h3>
                    <p>Rezago</p>
                </div>
                <div class="icon"><i class="fas fa-fire"></i></div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Resultados encontrados</strong>
            <span class="text-muted small">
                Mostrando {{ $rows->total() }} registros segun filtros (de {{ number_format($summary['registrados'] ?? 0) }} registrados totales)
            </span>
        </div>

        <div class="px-3 pt-3">
            <div class="alert alert-light border mb-0 py-2">
                <strong>Como leer situacion:</strong>
                <span class="badge badge-success ml-2">Correcto</span>
                <span class="badge badge-warning ml-1">Retraso</span>
                <span class="badge badge-danger ml-1">Rezago</span>
                <span class="badge badge-primary ml-1">Entregado</span>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Modulo</th>
                            <th>Codigo</th>
                            <th>Estado</th>
                            <th>Situacion</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Remitente</th>
                            <th>Destinatario</th>
                            <th>Empresa</th>
                            <th>Usuario</th>
                            <th class="text-right">Peso</th>
                            <th class="text-right">Precio</th>
                            <th>Creado</th>
                            <th>Actualizado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            @php
                                $situacionClass = match($row['situacion_bucket']) {
                                    'correcto' => 'badge-success',
                                    'retraso' => 'badge-warning',
                                    'rezago' => 'badge-danger',
                                    'entregado' => 'badge-primary',
                                    default => 'badge-secondary',
                                };
                                $nro = ($rows->currentPage() - 1) * $rows->perPage() + $loop->iteration;
                            @endphp
                            <tr>
                                <td>{{ $nro }}</td>
                                <td><span class="badge badge-light border">{{ $row['modulo_label'] }}</span></td>
                                <td class="font-weight-bold">{{ $row['codigo'] }}</td>
                                <td>{{ $row['estado'] }}</td>
                                <td><span class="badge {{ $situacionClass }}">{{ $row['situacion'] }}</span></td>
                                <td>{{ $row['origen'] }}</td>
                                <td>{{ $row['destino'] }}</td>
                                <td>{{ $row['remitente'] }}</td>
                                <td>{{ $row['destinatario'] }}</td>
                                <td>{{ $row['empresa'] }}</td>
                                <td>{{ $row['usuario'] }}</td>
                                <td class="text-right">{{ number_format((float) $row['peso'], 3) }}</td>
                                <td class="text-right">Bs {{ number_format((float) $row['precio'], 2) }}</td>
                                <td>{{ $row['created_at'] }}</td>
                                <td>{{ $row['updated_at'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center py-4 text-muted">Sin resultados para los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer">
            {{ $rows->appends(request()->except('page'))->links('pagination::bootstrap-4') }}
        </div>
    </div>

    @include('footer')
@stop

@section('css')
    <style>
        .card-filtro {
            border-top: 3px solid #20539a;
        }
        .small-box .icon {
            top: 10px;
            font-size: 44px;
        }
    </style>
@stop

@section('js')
    <script>
        (() => {
            const form = document.getElementById('reportFiltersForm');
            const from = document.getElementById('reportFrom');
            const to = document.getElementById('reportTo');
            const quickButtons = document.querySelectorAll('.quick-range');
            const monthButton = document.querySelector('.quick-range[data-month="1"]');
            const allButton = document.querySelector('.quick-range-all');
            const switcher = document.getElementById('scopeSwitcher');
            const toggleBtn = document.getElementById('toggleReportFilters');
            const toggleText = document.getElementById('toggleReportFiltersText');
            const filtersBody = document.getElementById('reportFiltersBody');
            const rangeInput = document.getElementById('reportRange');
            const autoFilterStatus = document.getElementById('autoFilterStatus');
            const exportButtons = document.querySelectorAll('[data-export]');
            const autoFields = form
                ? form.querySelectorAll(
                    'input[name="q"], input[name="from"], input[name="to"], select[name="status"], select[name="limit"], select[name="per_page"], select[name="estado_ids[]"], input[name="modules[]"]'
                )
                : [];
            let autoSubmitTimer = null;
            let autoSubmitting = false;

            const toYmd = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };

            const setAutoStatus = (text, cls = 'badge-success') => {
                if (!autoFilterStatus) {
                    return;
                }
                autoFilterStatus.className = `badge ${cls} mt-2 mt-md-0`;
                autoFilterStatus.textContent = text;
            };

            const syncRangeFromDates = () => {
                if (!rangeInput || !from || !to) {
                    return;
                }
                if (from.value === '' && to.value === '') {
                    rangeInput.value = 'all';
                    return;
                }
                rangeInput.value = 'custom';
            };

            const submitAutoFilters = () => {
                if (!form || autoSubmitting) {
                    return;
                }
                autoSubmitting = true;
                setAutoStatus('Actualizando datos...', 'badge-warning');
                form.submit();
            };

            const scheduleAutoSubmit = (delay = 380) => {
                if (!form) {
                    return;
                }
                if (autoSubmitTimer) {
                    clearTimeout(autoSubmitTimer);
                }
                setAutoStatus('Cambio detectado', 'badge-info');
                autoSubmitTimer = setTimeout(() => {
                    syncRangeFromDates();
                    submitAutoFilters();
                }, delay);
            };

            const buildExportUrl = (baseUrl) => {
                if (!form) {
                    return baseUrl;
                }
                syncRangeFromDates();
                const formData = new FormData(form);
                const params = new URLSearchParams();
                for (const [key, value] of formData.entries()) {
                    if (key === 'page') {
                        continue;
                    }
                    if (typeof value === 'string' && value.trim() === '') {
                        continue;
                    }
                    params.append(key, value);
                }
                const query = params.toString();
                return query ? `${baseUrl}?${query}` : baseUrl;
            };

            quickButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    const days = Number(btn.dataset.days || 0);
                    if (!days || !from || !to || !form) {
                        return;
                    }
                    const end = new Date();
                    const start = new Date();
                    start.setDate(end.getDate() - (days - 1));
                    from.value = toYmd(start);
                    to.value = toYmd(end);
                    if (rangeInput) {
                        rangeInput.value = 'custom';
                    }
                    setAutoStatus('Actualizando...', 'badge-warning');
                    form.submit();
                });
            });

            if (monthButton) {
                monthButton.addEventListener('click', () => {
                    if (!from || !to || !form) {
                        return;
                    }
                    const now = new Date();
                    const start = new Date(now.getFullYear(), now.getMonth(), 1);
                    from.value = toYmd(start);
                    to.value = toYmd(now);
                    if (rangeInput) {
                        rangeInput.value = 'custom';
                    }
                    setAutoStatus('Actualizando...', 'badge-warning');
                    form.submit();
                });
            }

            if (allButton) {
                allButton.addEventListener('click', () => {
                    if (!form) {
                        return;
                    }
                    if (from) {
                        from.value = '';
                    }
                    if (to) {
                        to.value = '';
                    }
                    if (rangeInput) {
                        rangeInput.value = 'all';
                    }
                    setAutoStatus('Actualizando...', 'badge-warning');
                    form.submit();
                });
            }

            if (switcher) {
                switcher.addEventListener('change', () => {
                    const url = new URL(window.location.href);
                    const parts = url.pathname.split('/').filter(Boolean);
                    const idx = parts.indexOf('reportes');
                    if (idx >= 0) {
                        parts[idx + 1] = switcher.value;
                        url.pathname = '/' + parts.join('/');
                        setAutoStatus('Cambiando vista...', 'badge-warning');
                        window.location.href = url.toString();
                    }
                });
            }

            if (toggleBtn && filtersBody && toggleText) {
                toggleBtn.addEventListener('click', () => {
                    const hidden = filtersBody.style.display === 'none';
                    filtersBody.style.display = hidden ? '' : 'none';
                    toggleText.textContent = hidden ? 'Ocultar filtros' : 'Mostrar filtros';
                });
            }

            exportButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    if (autoSubmitTimer) {
                        clearTimeout(autoSubmitTimer);
                        autoSubmitTimer = null;
                    }
                    const baseUrl = button.getAttribute('href') || '';
                    if (baseUrl === '') {
                        return;
                    }
                    setAutoStatus('Generando archivo...', 'badge-primary');
                    const finalUrl = buildExportUrl(baseUrl);
                    window.location.href = finalUrl;
                });
            });

            autoFields.forEach((field) => {
                const name = field.getAttribute('name') || '';
                if (field.type === 'checkbox' || field.tagName === 'SELECT' || name === 'estado_ids[]') {
                    field.addEventListener('change', () => scheduleAutoSubmit(240));
                    return;
                }

                if (field.type === 'date') {
                    field.addEventListener('change', () => scheduleAutoSubmit(240));
                    return;
                }

                field.addEventListener('input', () => scheduleAutoSubmit(650));
                field.addEventListener('change', () => scheduleAutoSubmit(240));
            });
        })();
    </script>
@stop

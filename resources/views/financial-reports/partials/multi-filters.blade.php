@php
    $reportFilterMonths = collect($selectedMonths ?? [])->map(fn ($month) => (int) $month);
    $reportFilterServices = collect($selectedServices ?? []);
    $reportHasAppliedFilters = request()->query->has('servicios')
        && request()->query->has('meses')
        && $reportFilterServices->isNotEmpty()
        && $reportFilterMonths->isNotEmpty();
    $reportMonthNames = [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'];
    $reportServiceLabel = $reportFilterServices->take(2)->implode(', ');
    if ($reportFilterServices->count() > 2) {
        $reportServiceLabel .= ' y ' . ($reportFilterServices->count() - 2) . ' más';
    }
    $reportMonthLabel = $reportFilterMonths
        ->map(fn ($month) => $reportMonthNames[$month] ?? (string) $month)
        ->implode(', ');
    $reportFilterSummaryParts = [
        $reportServiceLabel,
        $reportMonthLabel,
        'Año ' . (string) ($anio ?? now()->year),
    ];
    if ($showDepartmentFilter ?? false) {
        $reportFilterSummaryParts[] = filled($selectedDepartment ?? '') ? $selectedDepartment : 'Todos los departamentos';
    }
    if ($showLimit ?? false) {
        $reportFilterSummaryParts[] = 'Máx. ' . (int) ($limite ?? 200) . ' por mes';
    }
    $reportFilterSummary = collect($reportFilterSummaryParts)->filter()->implode(' · ');
@endphp

<details class="card report-filter-card shadow-sm" data-report-filter-card data-filters-applied="{{ $reportHasAppliedFilters ? 'true' : 'false' }}" @unless($reportHasAppliedFilters) open @endunless>
    <summary class="card-header report-filter-summary">
        <span class="filter-title-icon"><i class="fas fa-sliders-h"></i></span>
        <span class="report-filter-heading">
            <strong>{{ $reportHasAppliedFilters ? 'Filtros aplicados' : ($filterTitle ?? 'Prepare su reporte') }}</strong>
            <span class="text-muted small d-block">{{ $reportHasAppliedFilters ? $reportFilterSummary : ($filterHelp ?? 'Elija los servicios y periodos que desea comparar.') }}</span>
        </span>
        <span class="report-filter-toggle-label report-filter-toggle-open"><i class="fas fa-sliders-h mr-1"></i> Cambiar filtros</span>
        <span class="report-filter-toggle-label report-filter-toggle-close">Ocultar filtros</span>
        <i class="fas fa-chevron-down report-filter-chevron" aria-hidden="true"></i>
    </summary>

    <form method="GET" action="{{ $action }}" class="report-filter-form" data-max-services="{{ $maxSelectedServices ?? 200 }}">
        @if($soloContratos ?? false)
            <input type="hidden" name="solo_contratos" value="1">
        @endif
        <div class="card-body pt-3">
            <div class="row">
                <div class="col-lg-7 mb-4 mb-lg-0">
                    <div class="picker-heading">
                        <div>
                            <span class="picker-step">1</span>
                            <strong>Seleccione los servicios</strong>
                        </div>
                        <span class="selection-counter" data-service-count></span>
                    </div>

                    <div class="service-picker">
                        <div class="service-search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="search" class="service-search" placeholder="Buscar un servicio por nombre..." autocomplete="off">
                            <button type="button" class="service-search-clear" title="Borrar búsqueda" aria-label="Borrar búsqueda"><i class="fas fa-times"></i></button>
                        </div>
                        <div class="service-picker-actions">
                            <button type="button" class="picker-action" data-check-all="services"><i class="fas fa-check-double mr-1"></i> Seleccionar todos</button>
                            <button type="button" class="picker-action" data-clear-all="services"><i class="fas fa-eraser mr-1"></i> Limpiar</button>
                        </div>
                        <div class="service-options" data-service-options>
                            @forelse($serviceOptions as $serviceOption)
                                <label class="service-option" data-service-option data-search-text="{{ Illuminate\Support\Str::lower($serviceOption) }}">
                                    <input type="checkbox" name="servicios[]" value="{{ $serviceOption }}" @checked(in_array($serviceOption, $selectedServices, true))>
                                    <span class="service-check"><i class="fas fa-check"></i></span>
                                    <span class="service-name">{{ $serviceOption }}</span>
                                </label>
                            @empty
                                <div class="text-center text-muted py-4">No hay servicios disponibles para los meses consultados.</div>
                            @endforelse
                            <div class="service-no-results text-center text-muted py-4 d-none">No se encontraron servicios con ese texto.</div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="picker-heading">
                        <div>
                            <span class="picker-step">2</span>
                            <strong>Seleccione los meses</strong>
                        </div>
                        <span class="selection-counter" data-month-count></span>
                    </div>

                    <div class="month-picker">
                        @foreach([1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'] as $number => $monthName)
                            <label class="month-option">
                                <input type="checkbox" name="meses[]" value="{{ $number }}" @checked(in_array($number, $selectedMonths, true))>
                                <span>{{ $monthName }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <button type="button" class="picker-action mr-3" data-check-all="months">Todos los meses</button>
                        <button type="button" class="picker-action" data-clear-all="months">Limpiar</button>
                    </div>

                    @if($showDepartmentFilter ?? false)
                        <div class="mt-4">
                            <div class="picker-heading mb-2">
                                <div>
                                    <span class="picker-step">3</span>
                                    <strong>Seleccione el departamento</strong>
                                </div>
                            </div>
                            <select name="departamento" class="form-control">
                                <option value="">Todos los departamentos</option>
                                @foreach($departmentOptions ?? [] as $department)
                                    <option value="{{ $department }}" @selected(($selectedDepartment ?? '') === $department)>{{ $department }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted d-block mt-2">La vista y el PDF mostrarán únicamente la información del lugar seleccionado.</small>
                        </div>
                    @endif

                    <div class="period-actions mt-4">
                        <div class="row">
                            <div class="{{ ($showLimit ?? false) ? 'col-6' : 'col-12' }} mb-3">
                                <label for="report-year">Año</label>
                                <input id="report-year" type="number" name="anio" class="form-control" min="2000" max="{{ now()->year + 3 }}" value="{{ $anio }}">
                            </div>
                            @if($showLimit ?? false)
                                <div class="col-6 mb-3">
                                    <label for="report-limit">Máximo por mes</label>
                                    <input id="report-limit" type="number" name="limite" class="form-control" min="1" max="200" value="{{ $limite }}">
                                </div>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block report-submit">
                            <i class="fas fa-filter mr-2"></i> Filtrar
                        </button>
                        <div class="selection-warning text-danger small mt-2 d-none" data-selection-warning>
                            <i class="fas fa-info-circle mr-1"></i> Seleccione al menos un servicio y un mes.
                        </div>
                        <div class="selection-warning text-danger small mt-2 d-none" data-service-limit-warning aria-live="polite">
                            <i class="fas fa-info-circle mr-1"></i> Puede seleccionar hasta {{ $maxSelectedServices ?? 200 }} servicios por consulta.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</details>

@once
    <div class="report-loading-modal" data-report-loading-modal role="status" aria-live="assertive" aria-hidden="true">
        <div class="report-loading-backdrop"></div>
        <div class="report-loading-panel">
            <div class="report-loading-animation" aria-hidden="true">
                <span class="report-loading-ring report-loading-ring-one"></span>
                <span class="report-loading-ring report-loading-ring-two"></span>
                <span class="report-loading-icon"><i class="fas fa-chart-line"></i></span>
            </div>
            <h2>Filtrando datos</h2>
            <p>Espere por favor, estamos preparando su reporte.</p>
            <div class="report-loading-dots" aria-hidden="true"><span></span><span></span><span></span></div>
        </div>
    </div>

    @push('css')
        <style>
            details.report-filter-card { display: block; border: 0; border-radius: 12px; overflow: visible; }
            .report-filter-card:not([open]) > .report-filter-form { display: none; }
            .report-filter-card .card-header { background: linear-gradient(135deg, #fffaf0, #fff); border-radius: 12px 12px 0 0; }
            .report-filter-card:not([open]) .card-header { border-radius: 12px; }
            .report-filter-summary { display: flex; align-items: center; gap: 12px; cursor: pointer; list-style: none; padding: 14px 18px; }
            .report-filter-summary::-webkit-details-marker { display: none; }
            .report-filter-summary:focus-visible { outline: 3px solid rgba(19, 83, 155, .35); outline-offset: -3px; }
            .report-filter-heading { flex: 1 1 auto; min-width: 0; }
            .report-filter-heading strong { display: block; color: #173b63; }
            .report-filter-heading .small { overflow-wrap: anywhere; }
            .report-filter-toggle-label { flex: 0 0 auto; color: #13539b; font-size: .85rem; font-weight: 700; white-space: nowrap; }
            .report-filter-toggle-close { display: none; }
            .report-filter-card[open] .report-filter-toggle-open { display: none; }
            .report-filter-card[open] .report-filter-toggle-close { display: inline; }
            .report-filter-chevron { flex: 0 0 auto; color: #13539b; transition: transform .18s ease; }
            .report-filter-card[open] .report-filter-chevron { transform: rotate(180deg); }
            .filter-title-icon { width: 42px; height: 42px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; margin-right: 12px; color: #13539b; background: #e8f1fc; }
            .picker-heading { min-height: 34px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
            .picker-step { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; margin-right: 7px; border-radius: 50%; background: #13539b; color: #fff; font-size: .8rem; }
            .selection-counter { padding: 4px 9px; border-radius: 12px; background: #edf2f7; color: #4a5568; font-size: .75rem; white-space: nowrap; }
            .service-picker { border: 1px solid #d9e2ec; border-radius: 10px; overflow: hidden; background: #fff; }
            .service-search-wrap { position: relative; padding: 10px; background: #f8fafc; border-bottom: 1px solid #e7edf3; }
            .service-search-wrap > i { position: absolute; left: 24px; top: 22px; color: #718096; }
            .service-search { width: 100%; height: 40px; padding: 8px 38px; border: 1px solid #cbd5e0; border-radius: 8px; outline: none; }
            .service-search:focus { border-color: #13539b; box-shadow: 0 0 0 3px rgba(19, 83, 155, .12); }
            .service-search-clear { position: absolute; right: 20px; top: 17px; border: 0; background: transparent; color: #718096; }
            .service-picker-actions { display: flex; gap: 16px; padding: 8px 12px; border-bottom: 1px solid #edf2f7; }
            .picker-action { padding: 0; border: 0; background: transparent; color: #13539b; font-size: .82rem; font-weight: 600; }
            .picker-action:hover { color: #0b376b; text-decoration: underline; }
            .service-options { max-height: 235px; overflow-y: auto; overscroll-behavior: contain; padding: 6px; }
            .service-option { position: relative; display: flex; align-items: flex-start; gap: 10px; margin: 0; padding: 9px 10px; border-radius: 7px; cursor: pointer; transition: background .15s ease; }
            .service-option:hover { background: #f2f7fd; }
            .service-option.is-selected { background: #eaf3fe; color: #0d4789; }
            .service-option input { position: absolute; top: 9px; left: 10px; width: 20px; height: 20px; opacity: 0; pointer-events: none; }
            .service-check { flex: 0 0 20px; width: 20px; height: 20px; margin-top: 1px; border: 2px solid #a0aec0; border-radius: 5px; display: inline-flex; align-items: center; justify-content: center; color: transparent; font-size: .65rem; }
            .service-option input:checked + .service-check { border-color: #13539b; background: #13539b; color: #fff; }
            .service-option input:focus-visible + .service-check { outline: 3px solid rgba(19, 83, 155, .35); outline-offset: 2px; }
            .service-name { line-height: 1.35; overflow-wrap: anywhere; }
            .month-picker { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; }
            .month-option { position: relative; margin: 0; cursor: pointer; }
            .month-option input { position: absolute; top: 0; left: 0; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
            .month-option span { display: block; padding: 9px 4px; border: 1px solid #cbd5e0; border-radius: 8px; text-align: center; color: #4a5568; background: #fff; transition: all .15s ease; }
            .month-option span:hover { border-color: #13539b; color: #13539b; }
            .month-option input:checked + span { border-color: #13539b; background: #13539b; color: #fff; box-shadow: 0 3px 8px rgba(19, 83, 155, .2); }
            .month-option input:focus-visible + span { outline: 3px solid rgba(19, 83, 155, .35); outline-offset: 2px; }
            .period-actions { padding: 16px; border-radius: 10px; background: #f8fafc; border: 1px solid #e7edf3; }
            .period-actions label { font-size: .82rem; color: #4a5568; }
            .report-submit { border-radius: 8px; font-weight: 600; }
            .report-loading-modal { position: fixed; inset: 0; z-index: 2100; display: flex; align-items: center; justify-content: center; padding: 20px; visibility: hidden; opacity: 0; transition: opacity .2s ease, visibility .2s ease; }
            .report-loading-modal.is-visible { visibility: visible; opacity: 1; }
            .report-loading-backdrop { position: absolute; inset: 0; background: rgba(8, 29, 54, .72); backdrop-filter: blur(3px); }
            .report-loading-panel { position: relative; width: min(92vw, 390px); padding: 34px 30px 30px; border-radius: 18px; background: #fff; box-shadow: 0 24px 70px rgba(0, 0, 0, .28); text-align: center; transform: translateY(14px) scale(.97); transition: transform .25s ease; }
            .report-loading-modal.is-visible .report-loading-panel { transform: translateY(0) scale(1); }
            .report-loading-panel h2 { margin: 20px 0 7px; color: #123f73; font-size: 1.45rem; font-weight: 700; }
            .report-loading-panel p { margin: 0; color: #64748b; }
            .report-loading-animation { position: relative; width: 88px; height: 88px; margin: 0 auto; }
            .report-loading-ring { position: absolute; border: 3px solid transparent; border-radius: 50%; }
            .report-loading-ring-one { inset: 0; border-top-color: #13539b; border-right-color: #13539b; animation: report-spin 1.15s linear infinite; }
            .report-loading-ring-two { inset: 10px; border-bottom-color: #f5b800; border-left-color: #f5b800; animation: report-spin-reverse .85s linear infinite; }
            .report-loading-icon { position: absolute; inset: 22px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: #e8f1fc; color: #13539b; font-size: 1.2rem; animation: report-pulse 1.3s ease-in-out infinite; }
            .report-loading-dots { display: flex; justify-content: center; gap: 7px; margin-top: 18px; }
            .report-loading-dots span { width: 8px; height: 8px; border-radius: 50%; background: #13539b; animation: report-dot 1.1s ease-in-out infinite; }
            .report-loading-dots span:nth-child(2) { animation-delay: .16s; }
            .report-loading-dots span:nth-child(3) { animation-delay: .32s; }
            body.report-is-loading { overflow: hidden; }
            @keyframes report-spin { to { transform: rotate(360deg); } }
            @keyframes report-spin-reverse { to { transform: rotate(-360deg); } }
            @keyframes report-pulse { 0%, 100% { transform: scale(.92); } 50% { transform: scale(1.05); } }
            @keyframes report-dot { 0%, 60%, 100% { opacity: .25; transform: translateY(0); } 30% { opacity: 1; transform: translateY(-5px); } }
            @media (prefers-reduced-motion: reduce) {
                .report-loading-ring, .report-loading-icon, .report-loading-dots span { animation-duration: 2.5s; }
            }
            .financial-table td, .financial-table th { vertical-align: middle; }
            .financial-table .service-cell { min-width: 230px; max-width: 360px; white-space: normal; overflow-wrap: anywhere; }
            .financial-table .description-cell { min-width: 220px; max-width: 340px; white-space: normal; }
            .financial-table .code-cell { min-width: 135px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; overflow-wrap: anywhere; }
            @media (max-width: 767.98px) {
                .report-filter-summary { flex-wrap: wrap; }
                .report-filter-heading { flex-basis: calc(100% - 60px); }
                .report-filter-toggle-label { margin-left: 54px; }
                .month-picker { grid-template-columns: repeat(4, 1fr); }
                .service-options { max-height: 280px; }
            }
        </style>
    @endpush

    @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-report-filter-card]').forEach(function (card) {
                    card.scrollTop = 0;
                });
                document.querySelectorAll('.report-filter-form').forEach(function (form) {
                    var serviceInputs = Array.from(form.querySelectorAll('input[name="servicios[]"]'));
                    var monthInputs = Array.from(form.querySelectorAll('input[name="meses[]"]'));
                    var search = form.querySelector('.service-search');
                    var noResults = form.querySelector('.service-no-results');
                    var loadingModal = document.querySelector('[data-report-loading-modal]');
                    var submitButton = form.querySelector('.report-submit');
                    var maxServices = parseInt(form.dataset.maxServices || '200', 10);
                    var serviceLimitWarning = form.querySelector('[data-service-limit-warning]');

                    form.querySelectorAll('.service-option, .month-option').forEach(function (option) {
                        option.addEventListener('click', function (event) {
                            var input = option.querySelector('input[type="checkbox"]');
                            if (!input || event.target === input || event.detail === 0) {
                                return;
                            }

                            var scrollX = window.scrollX;
                            var scrollY = window.scrollY;
                            input.focus({ preventScroll: true });
                            window.requestAnimationFrame(function () {
                                if (Math.abs(window.scrollY - scrollY) > 1 || Math.abs(window.scrollX - scrollX) > 1) {
                                    window.scrollTo(scrollX, scrollY);
                                }
                            });
                        });
                    });

                    function refresh() {
                        serviceInputs.forEach(function (input) {
                            input.closest('.service-option').classList.toggle('is-selected', input.checked);
                        });
                        var selectedServiceCount = serviceInputs.filter(function (input) { return input.checked; }).length;
                        form.querySelector('[data-service-count]').textContent = selectedServiceCount + ' seleccionados';
                        form.querySelector('[data-month-count]').textContent = monthInputs.filter(function (input) { return input.checked; }).length + ' seleccionados';
                        serviceLimitWarning.classList.toggle('d-none', selectedServiceCount <= maxServices);
                    }

                    form.addEventListener('change', refresh);
                    form.querySelectorAll('[data-check-all]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            var inputs = button.dataset.checkAll === 'services' ? serviceInputs : monthInputs;
                            inputs.forEach(function (input) { input.checked = true; });
                            refresh();
                        });
                    });
                    form.querySelectorAll('[data-clear-all]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            var inputs = button.dataset.clearAll === 'services' ? serviceInputs : monthInputs;
                            inputs.forEach(function (input) { input.checked = false; });
                            refresh();
                        });
                    });
                    search.addEventListener('input', function () {
                        var needle = search.value.trim().toLocaleLowerCase('es');
                        var visible = 0;
                        form.querySelectorAll('[data-service-option]').forEach(function (option) {
                            var matches = option.dataset.searchText.includes(needle);
                            option.classList.toggle('d-none', !matches);
                            if (matches) visible++;
                        });
                        noResults.classList.toggle('d-none', visible !== 0);
                    });
                    form.querySelector('.service-search-clear').addEventListener('click', function () {
                        search.value = '';
                        search.dispatchEvent(new Event('input'));
                        search.focus();
                    });
                    form.addEventListener('submit', function (event) {
                        var selectedServiceCount = serviceInputs.filter(function (input) { return input.checked; }).length;
                        if (selectedServiceCount > maxServices) {
                            serviceLimitWarning.classList.remove('d-none');
                            event.preventDefault();
                            return;
                        }

                        var valid = serviceInputs.some(function (input) { return input.checked; }) && monthInputs.some(function (input) { return input.checked; });
                        form.querySelector('[data-selection-warning]').classList.toggle('d-none', valid);
                        if (!valid) {
                            event.preventDefault();
                            return;
                        }

                        if (loadingModal) {
                            var loadingTitle = loadingModal.querySelector('h2');
                            var loadingMessage = loadingModal.querySelector('p');
                            if (loadingTitle) loadingTitle.textContent = 'Filtrando datos';
                            if (loadingMessage) loadingMessage.textContent = 'Espere por favor, estamos preparando su reporte.';
                            loadingModal.classList.add('is-visible');
                            loadingModal.setAttribute('aria-hidden', 'false');
                            document.body.classList.add('report-is-loading');
                        }
                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Filtrando...';
                        }
                    });
                    refresh();
                });

                document.querySelectorAll('[data-report-download]').forEach(function (link) {
                    link.addEventListener('click', function (event) {
                        event.preventDefault();

                        if (link.hasAttribute('data-wait-for-download')) {
                            if (link.dataset.downloading === 'true') {
                                return;
                            }
                            link.dataset.downloading = 'true';

                            var downloadModal = document.querySelector('[data-report-loading-modal]');
                            var downloadTitle = downloadModal ? downloadModal.querySelector('h2') : null;
                            var downloadMessage = downloadModal ? downloadModal.querySelector('p') : null;
                            function closeDownloadModal() {
                                if (downloadModal) {
                                    downloadModal.classList.remove('is-visible');
                                    downloadModal.setAttribute('aria-hidden', 'true');
                                }
                                document.body.classList.remove('report-is-loading');
                            }

                            if (downloadTitle) downloadTitle.textContent = link.dataset.loadingTitle || 'Generando reporte';
                            if (downloadMessage) downloadMessage.textContent = link.dataset.loadingMessage || 'Espere por favor, estamos preparando el PDF.';
                            if (downloadModal) {
                                downloadModal.classList.add('is-visible');
                                downloadModal.setAttribute('aria-hidden', 'false');
                            }
                            document.body.classList.add('report-is-loading');

                            fetch(link.href, { credentials: 'same-origin' })
                                .then(function (response) {
                                    if (!response.ok) {
                                        throw new Error('El servidor respondió con el código ' + response.status + '.');
                                    }

                                    var contentType = (response.headers.get('Content-Type') || '').toLowerCase();
                                    if (contentType && !contentType.includes('application/pdf') && !contentType.includes('application/octet-stream')) {
                                        throw new Error('La respuesta recibida no es un archivo PDF.');
                                    }

                                    return response.blob().then(function (blob) {
                                        if (blob.size === 0) {
                                            throw new Error('El archivo recibido está vacío.');
                                        }

                                        return { response: response, blob: blob };
                                    });
                                })
                                .then(function (result) {
                                    var disposition = result.response.headers.get('Content-Disposition') || '';
                                    var utf8Name = disposition.match(/filename\*\s*=\s*UTF-8''([^;]+)/i);
                                    var regularName = disposition.match(/filename\s*=\s*"?([^";]+)"?/i);
                                    var fileName = utf8Name ? decodeURIComponent(utf8Name[1]) : (regularName ? regularName[1].trim() : 'reporte-flujo-cajero.pdf');
                                    var objectUrl = URL.createObjectURL(result.blob);
                                    var downloadLink = document.createElement('a');
                                    downloadLink.href = objectUrl;
                                    downloadLink.download = fileName;
                                    downloadLink.style.display = 'none';
                                    document.body.appendChild(downloadLink);
                                    downloadLink.click();
                                    downloadLink.remove();
                                    window.setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 1000);

                                    if (downloadTitle) downloadTitle.textContent = 'Descarga iniciada';
                                    if (downloadMessage) downloadMessage.textContent = 'El PDF se recibió completo y se envió a las descargas.';
                                    window.setTimeout(closeDownloadModal, 900);
                                })
                                .catch(function (error) {
                                    if (downloadTitle) downloadTitle.textContent = 'No se pudo descargar el PDF';
                                    if (downloadMessage) downloadMessage.textContent = error.message || 'Revise su conexión e intente nuevamente.';
                                    window.setTimeout(closeDownloadModal, 4000);
                                })
                                .finally(function () {
                                    link.dataset.downloading = 'false';
                                });

                            return;
                        }

                        var loadingModal = document.querySelector('[data-report-loading-modal]');
                        if (loadingModal) {
                            var loadingTitle = loadingModal.querySelector('h2');
                            var loadingMessage = loadingModal.querySelector('p');
                            if (loadingTitle) loadingTitle.textContent = link.dataset.loadingTitle || 'Generando reporte';
                            if (loadingMessage) loadingMessage.textContent = link.dataset.loadingMessage || 'Espere por favor, estamos preparando el PDF.';
                            loadingModal.classList.add('is-visible');
                            loadingModal.setAttribute('aria-hidden', 'false');
                            document.body.classList.add('report-is-loading');
                        }

                        window.setTimeout(function () {
                            window.location.assign(link.href);
                        }, 180);

                        window.setTimeout(function () {
                            if (loadingModal) {
                                loadingModal.classList.remove('is-visible');
                                loadingModal.setAttribute('aria-hidden', 'true');
                            }
                            document.body.classList.remove('report-is-loading');
                        }, 4500);
                    });
                });

                window.addEventListener('pageshow', function () {
                    document.querySelectorAll('[data-report-filter-card]').forEach(function (card) {
                        card.scrollTop = 0;
                        if (card.dataset.filtersApplied === 'true') {
                            card.open = false;
                        }
                    });
                    var loadingModal = document.querySelector('[data-report-loading-modal]');
                    if (loadingModal) {
                        loadingModal.classList.remove('is-visible');
                        loadingModal.setAttribute('aria-hidden', 'true');
                    }
                    document.body.classList.remove('report-is-loading');
                    document.querySelectorAll('.report-submit').forEach(function (button) {
                        button.disabled = false;
                        button.innerHTML = '<i class="fas fa-filter mr-2"></i> Filtrar';
                    });
                });
            });
        </script>
    @endpush
@endonce

<div class="card report-filter-card shadow-sm">
    <div class="card-header border-0 pb-0">
        <div class="d-flex align-items-center">
            <span class="filter-title-icon"><i class="fas fa-sliders-h"></i></span>
            <div>
                <h3 class="card-title font-weight-bold mb-0">{{ $filterTitle ?? 'Prepare su reporte' }}</h3>
                <div class="text-muted small">{{ $filterHelp ?? 'Elija los servicios y periodos que desea comparar.' }}</div>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ $action }}" class="report-filter-form">
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

                    <div class="period-actions mt-4">
                        <div class="row">
                            <div class="{{ ($showLimit ?? false) ? 'col-6' : 'col-12' }} mb-3">
                                <label for="report-year">Año</label>
                                <input id="report-year" type="number" name="anio" class="form-control" min="2000" max="{{ now()->year + 1 }}" value="{{ $anio }}">
                            </div>
                            @if($showLimit ?? false)
                                <div class="col-6 mb-3">
                                    <label for="report-limit">Máximo por mes</label>
                                    <input id="report-limit" type="number" name="limite" class="form-control" min="1" max="200" value="{{ $limite }}">
                                </div>
                            @endif
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg btn-block report-submit">
                            <i class="fas fa-chart-bar mr-2"></i> Generar reporte
                        </button>
                        <div class="selection-warning text-danger small mt-2 d-none" data-selection-warning>
                            <i class="fas fa-info-circle mr-1"></i> Seleccione al menos un servicio y un mes.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

@once
    @push('css')
        <style>
            .report-filter-card { border: 0; border-radius: 12px; overflow: hidden; }
            .report-filter-card .card-header { background: linear-gradient(135deg, #fffaf0, #fff); }
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
            .service-options { max-height: 235px; overflow-y: auto; padding: 6px; }
            .service-option { display: flex; align-items: flex-start; gap: 10px; margin: 0; padding: 9px 10px; border-radius: 7px; cursor: pointer; transition: background .15s ease; }
            .service-option:hover { background: #f2f7fd; }
            .service-option.is-selected { background: #eaf3fe; color: #0d4789; }
            .service-option input { position: absolute; opacity: 0; pointer-events: none; }
            .service-check { flex: 0 0 20px; width: 20px; height: 20px; margin-top: 1px; border: 2px solid #a0aec0; border-radius: 5px; display: inline-flex; align-items: center; justify-content: center; color: transparent; font-size: .65rem; }
            .service-option input:checked + .service-check { border-color: #13539b; background: #13539b; color: #fff; }
            .service-name { line-height: 1.35; overflow-wrap: anywhere; }
            .month-picker { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; }
            .month-option { margin: 0; cursor: pointer; }
            .month-option input { position: absolute; opacity: 0; pointer-events: none; }
            .month-option span { display: block; padding: 9px 4px; border: 1px solid #cbd5e0; border-radius: 8px; text-align: center; color: #4a5568; background: #fff; transition: all .15s ease; }
            .month-option span:hover { border-color: #13539b; color: #13539b; }
            .month-option input:checked + span { border-color: #13539b; background: #13539b; color: #fff; box-shadow: 0 3px 8px rgba(19, 83, 155, .2); }
            .period-actions { padding: 16px; border-radius: 10px; background: #f8fafc; border: 1px solid #e7edf3; }
            .period-actions label { font-size: .82rem; color: #4a5568; }
            .report-submit { border-radius: 8px; font-weight: 600; }
            .financial-table td, .financial-table th { vertical-align: middle; }
            .financial-table .service-cell { min-width: 230px; max-width: 360px; white-space: normal; overflow-wrap: anywhere; }
            .financial-table .description-cell { min-width: 220px; max-width: 340px; white-space: normal; }
            .financial-table .code-cell { min-width: 135px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; overflow-wrap: anywhere; }
            @media (max-width: 767.98px) {
                .month-picker { grid-template-columns: repeat(4, 1fr); }
                .service-options { max-height: 280px; }
            }
        </style>
    @endpush

    @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.report-filter-form').forEach(function (form) {
                    var serviceInputs = Array.from(form.querySelectorAll('input[name="servicios[]"]'));
                    var monthInputs = Array.from(form.querySelectorAll('input[name="meses[]"]'));
                    var search = form.querySelector('.service-search');
                    var noResults = form.querySelector('.service-no-results');

                    function refresh() {
                        serviceInputs.forEach(function (input) {
                            input.closest('.service-option').classList.toggle('is-selected', input.checked);
                        });
                        form.querySelector('[data-service-count]').textContent = serviceInputs.filter(function (input) { return input.checked; }).length + ' seleccionados';
                        form.querySelector('[data-month-count]').textContent = monthInputs.filter(function (input) { return input.checked; }).length + ' seleccionados';
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
                        var valid = serviceInputs.some(function (input) { return input.checked; }) && monthInputs.some(function (input) { return input.checked; });
                        form.querySelector('[data-selection-warning]').classList.toggle('d-none', valid);
                        if (!valid) event.preventDefault();
                    });
                    refresh();
                });
            });
        </script>
    @endpush
@endonce

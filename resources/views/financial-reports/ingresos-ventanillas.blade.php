@extends('adminlte::page')

@section('title', 'Ingresos Ventanillas')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Ingresos Ventanillas</h1>
            <small class="text-muted">Ventas facturadas consolidadas por servicio y cajero.</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('dashboard.financiera.ingresos-ventanillas', ['servicios' => $selectedServices, 'meses' => $selectedMonths, 'anio' => $anio, 'limite' => $limite, 'departamento' => $selectedDepartment, 'actualizar' => 1]) }}" class="btn btn-outline-info btn-sm mr-1" title="Consultar nuevamente los datos de facturación">
                <i class="fas fa-sync-alt mr-1" aria-hidden="true"></i> Actualizar datos
            </a>
            <a href="{{ route('dashboard.financiera.ingresos-ventanillas.pdf', ['servicios' => $selectedServices, 'meses' => $selectedMonths, 'anio' => $anio, 'limite' => $limite, 'departamento' => $selectedDepartment]) }}"
               class="btn btn-danger btn-sm mr-1"
               data-report-download
               data-wait-for-download
               data-pdf-modal-ignore
               data-loading-title="Generando reporte de ingresos"
               data-loading-message="Estamos consultando los datos y armando el PDF ejecutivo.">
                <i class="fas fa-file-pdf mr-1"></i> Descargar reporte ejecutivo
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
        .flow-section-card .card-header { background: #fff; cursor: pointer; }
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
        .service-family-row { cursor: pointer; background: #edf5ff !important; }
        .service-family-row:hover { background: #e4effb !important; }
        .service-item-row { background: #fff; }
        .group-toggle-button { display: inline-flex; align-items: center; min-width: 44px; min-height: 40px; border: 0; background: transparent; color: #102a43; font-weight: 600; }
        .group-toggle-button:focus { outline: 3px solid #13539b; outline-offset: 2px; }
        .group-chevron { display: inline-flex; width: 24px; height: 24px; margin-right: 3px; align-items: center; justify-content: center; border-radius: 50%; color: #13539b; background: #e2eefc; transition: transform .2s ease; }
        .service-group-row.is-open .group-chevron { transform: rotate(90deg); }
        .service-family-row.is-open .group-chevron { transform: rotate(90deg); }
        .flow-section-toggle { display: inline-flex; align-items: center; gap: .5rem; padding: 0; border: 0; background: transparent; color: #102a43; font-weight: 700; text-align: left; }
        .flow-section-toggle:focus { outline: 3px solid #13539b; outline-offset: 3px; }
        .flow-section-toggle .group-chevron { flex: 0 0 24px; margin: 0; }
        .flow-section-toggle[aria-expanded="true"] .group-chevron { transform: rotate(90deg); }
        .child-service-name { position: relative; padding-left: 45px !important; font-weight: 600; }
        .service-family-name { font-weight: 700; }
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
        .flow-detail-classification-filters { display: flex; flex-wrap: wrap; gap: .5rem; margin: 0 0 1.25rem; }
        .flow-detail-classification-button { display: flex; flex-direction: column; align-items: flex-start; min-width: 190px; padding: .55rem .8rem; text-align: left; }
        .flow-detail-classification-button small { opacity: .85; }
        .flow-detail-payment-filters { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; margin: .75rem 0 1rem; padding: .7rem .85rem; border: 1px solid #d5e2f0; border-radius: 8px; background: #f8fbff; }
        .flow-detail-payment-filters > strong { color: #102a43; margin-right: .25rem; }
        .flow-detail-payment-button { display: flex; flex-direction: column; align-items: flex-start; min-width: 170px; padding: .5rem .75rem; text-align: left; }
        .flow-detail-payment-button small { opacity: .85; }
        .flow-detail-list-heading { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: .5rem; margin-bottom: .75rem; }
        .flow-detail-list-heading h5 { color: #102a43; font-size: 1.3rem; font-weight: 700; }
        .flow-detail-list-heading span { font-weight: 600; }
        .flow-detail-collection-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .65rem 1rem; margin: 0 0 1rem; padding: .8rem 1rem; border: 1px solid #b7c9db; border-radius: 8px; background: #f3f7fc; }
        .flow-detail-collection-toolbar label, .flow-detail-select-label { color: #102a43; font-weight: 600; }
        .flow-detail-collection-toolbar input[type="checkbox"], .flow-detail-select-label input[type="checkbox"] { width: 18px; height: 18px; vertical-align: middle; margin-right: .3rem; }
        .flow-collection-date-row { display: grid; grid-template-columns: minmax(0, 1fr) 190px; align-items: center; gap: 1rem; border: 1px solid #d5e2f0; border-radius: 8px; padding: .8rem 1rem; margin-top: .65rem; }
        .flow-collection-date-row strong, .flow-collection-date-row small { display: block; overflow-wrap: anywhere; }
        .flow-collection-date-row input { min-height: 44px; }
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
            .flow-collection-date-row { grid-template-columns: 1fr; }
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

            function setServiceGroupExpanded(row, open) {
                var groupId = row.dataset.flowToggleId;
                var button = row.querySelector('[data-flow-toggle-button]');
                if (!groupId || !button) {
                    return;
                }

                button.setAttribute('aria-expanded', open ? 'true' : 'false');
                button.setAttribute('aria-label', (open ? 'Ocultar' : 'Mostrar') + ' servicios de ' + button.dataset.flowToggleName);
                row.classList.toggle('is-open', open);
                document.querySelectorAll('[data-flow-parent="' + groupId + '"]').forEach(function (child) {
                    child.classList.toggle('d-none', !open);
                    if (!open && child.hasAttribute('data-flow-toggle-row')) {
                        setServiceGroupExpanded(child, false);
                    }
                });
            }

            document.querySelectorAll('[data-flow-toggle-row]').forEach(function (row) {
                var button = row.querySelector('[data-flow-toggle-button]');
                row.addEventListener('click', function (event) {
                    if (event.target.closest('[data-flow-detail-trigger]')) {
                        return;
                    }

                    event.stopPropagation();
                    var open = button.getAttribute('aria-expanded') !== 'true';
                    setServiceGroupExpanded(row, open);
                });
            });

            document.querySelectorAll('[data-flow-section-toggle]').forEach(function (button) {
                var header = button.closest('.card-header');
                if (!header) {
                    return;
                }

                header.addEventListener('click', function (event) {
                    if (event.target.closest('a, input, select, textarea, [data-no-section-toggle]')) {
                        return;
                    }

                    var content = document.getElementById(button.getAttribute('aria-controls'));
                    if (!content) {
                        return;
                    }

                    var open = button.getAttribute('aria-expanded') !== 'true';
                    button.setAttribute('aria-expanded', open ? 'true' : 'false');
                    button.setAttribute('aria-label', (open ? 'Ocultar' : 'Mostrar') + ' contenido de ' + button.dataset.flowSectionName);
                    content.classList.toggle('d-none', !open);
                });
            });

            var detailModal = document.getElementById('flow-detail-modal');
            if (!detailModal || !window.jQuery) {
                return;
            }

            var detailBody = detailModal.querySelector('[data-flow-detail-body]');
            var detailTitle = detailModal.querySelector('[data-flow-detail-title]');
            var detailStatus = detailModal.querySelector('[data-flow-detail-status]');
            var refreshDetailButton = detailModal.querySelector('[data-flow-detail-refresh]');
            var collectionCancelButton = detailModal.querySelector('[data-flow-collection-cancel]');
            var collectionSubmitButton = detailModal.querySelector('[data-flow-collection-submit]');
            var activeRequest = null;
            var requestId = 0;
            var currentUrl = '';
            var detailPageTitle = '';
            var savedDetail = null;
            var savedDetailScroll = 0;

            function setCollectionStage(active) {
                refreshDetailButton.classList.toggle('d-none', active);
                collectionCancelButton.classList.toggle('d-none', !active);
                collectionSubmitButton.classList.toggle('d-none', !active);
                detailTitle.textContent = active ? 'Fecha de cobro' : detailPageTitle;
            }

            function localToday() {
                var today = new Date();
                var month = String(today.getMonth() + 1).padStart(2, '0');
                var day = String(today.getDate()).padStart(2, '0');
                return today.getFullYear() + '-' + month + '-' + day;
            }

            function updateCollectionSelection() {
                var toolbar = detailBody.querySelector('[data-flow-collection-toolbar]');
                if (!toolbar) {
                    return;
                }
                var checkboxes = Array.from(detailBody.querySelectorAll('[data-flow-select-movement]'));
                var checkedCount = checkboxes.filter(function (checkbox) { return checkbox.checked; }).length;
                toolbar.querySelector('[data-flow-selected-count]').textContent = checkedCount + ' seleccionados';
                toolbar.querySelector('[data-flow-collect-selected]').disabled = checkedCount === 0;
                var selectAll = toolbar.querySelector('[data-flow-select-all-page]');
                selectAll.checked = checkboxes.length > 0 && checkedCount === checkboxes.length;
                selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
            }

            function openCollectionStage(clickedCard) {
                var toolbar = detailBody.querySelector('[data-flow-collection-toolbar]');
                if (!toolbar) {
                    return;
                }
                var cards = Array.from(detailBody.querySelectorAll('[data-flow-select-movement]:checked')).map(function (checkbox) {
                    return checkbox.closest('[data-flow-movement-key]');
                }).filter(Boolean);
                if (clickedCard && !cards.includes(clickedCard)) {
                    cards.push(clickedCard);
                }
                cards = cards.filter(function (card, index, all) {
                    return card.dataset.flowMovementKey && all.findIndex(function (item) {
                        return item.dataset.flowMovementKey === card.dataset.flowMovementKey;
                    }) === index;
                });
                if (!cards.length) {
                    return;
                }

                var form = document.createElement('form');
                form.id = 'flow-collection-form';
                form.method = 'POST';
                form.action = detailModal.dataset.collectionAction;
                toolbar.querySelectorAll('[data-flow-collection-context]').forEach(function (source) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = source.name;
                    input.value = source.value;
                    form.appendChild(input);
                });

                var instructions = document.createElement('p');
                instructions.className = 'mb-3';
                instructions.textContent = 'Indique la fecha de cobro de cada movimiento. Puede asignar una fecha diferente a cada uno.';
                form.appendChild(instructions);

                var masterLabel = document.createElement('label');
                masterLabel.className = 'font-weight-bold';
                masterLabel.textContent = 'Aplicar una fecha a todos';
                masterLabel.htmlFor = 'flow-collection-master-date';
                var masterDate = document.createElement('input');
                masterDate.type = 'date';
                masterDate.id = 'flow-collection-master-date';
                masterDate.className = 'form-control flow-collection-date-master mb-3';
                masterDate.value = localToday();
                masterLabel.appendChild(masterDate);
                form.appendChild(masterLabel);

                cards.forEach(function (card, index) {
                    var row = document.createElement('div');
                    row.className = 'flow-collection-date-row';
                    var description = document.createElement('div');
                    var title = document.createElement('strong');
                    title.textContent = card.dataset.flowService;
                    var subtitle = document.createElement('small');
                    subtitle.className = 'text-muted';
                    subtitle.textContent = 'Mes ' + card.dataset.flowMonth + ' · Venta ' + card.dataset.flowSale + ' · Detalle ' + card.dataset.flowDetail + ' · ' + card.dataset.flowAmount;
                    description.appendChild(title);
                    description.appendChild(subtitle);
                    row.appendChild(description);

                    var dateLabel = document.createElement('label');
                    dateLabel.className = 'mb-0 font-weight-bold';
                    dateLabel.textContent = 'Fecha de cobro';
                    var dateInput = document.createElement('input');
                    dateInput.type = 'date';
                    dateInput.required = true;
                    dateInput.className = 'form-control mt-1';
                    dateInput.name = 'movimientos[' + index + '][fecha_cobro]';
                    dateInput.value = masterDate.value;
                    dateLabel.appendChild(dateInput);
                    row.appendChild(dateLabel);

                    [
                        ['movement_key', card.dataset.flowMovementKey],
                        ['servicio_cobro', card.dataset.flowService],
                        ['mes_cobro', card.dataset.flowMonth]
                    ].forEach(function (field) {
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'movimientos[' + index + '][' + field[0] + ']';
                        hidden.value = field[1];
                        row.appendChild(hidden);
                    });
                    form.appendChild(row);
                });

                masterDate.addEventListener('change', function () {
                    form.querySelectorAll('[name$="[fecha_cobro]"]').forEach(function (dateInput) {
                        dateInput.value = masterDate.value;
                    });
                });

                savedDetailScroll = detailBody.scrollTop;
                savedDetail = document.createDocumentFragment();
                while (detailBody.firstChild) {
                    savedDetail.appendChild(detailBody.firstChild);
                }
                detailBody.replaceChildren(form);
                detailBody.scrollTop = 0;
                setCollectionStage(true);
                detailStatus.textContent = 'Seleccione la fecha de cobro para los movimientos.';
            }

            function loadDetail(url, focusSearch) {
                savedDetail = null;
                setCollectionStage(false);
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
                    var loadedUrl = new URL(url, window.location.href);
                    loadedUrl.searchParams.delete('actualizar');
                    currentUrl = loadedUrl.toString();
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
                    detailPageTitle = trigger.dataset.flowDetailTitle;
                    detailTitle.textContent = detailPageTitle;
                    var url = new URL(trigger.href, window.location.href);
                    url.searchParams.set('modal', '1');
                    window.jQuery(detailModal).modal('show');
                    loadDetail(url.toString());
                });
            });

            detailModal.addEventListener('click', function (event) {
                var collectOneButton = event.target.closest('[data-flow-collect-one]');
                if (collectOneButton) {
                    openCollectionStage(collectOneButton.closest('[data-flow-movement-key]'));
                    return;
                }

                var collectSelectedButton = event.target.closest('[data-flow-collect-selected]');
                if (collectSelectedButton) {
                    openCollectionStage(null);
                    return;
                }

                var selectAllButton = event.target.closest('[data-flow-select-all-page]');
                if (selectAllButton) {
                    detailBody.querySelectorAll('[data-flow-select-movement]').forEach(function (checkbox) {
                        checkbox.checked = selectAllButton.checked;
                    });
                    updateCollectionSelection();
                    return;
                }

                var cancelCollectionButton = event.target.closest('[data-flow-collection-cancel]');
                if (cancelCollectionButton && currentUrl) {
                    if (savedDetail) {
                        detailBody.replaceChildren(savedDetail);
                        savedDetail = null;
                        setCollectionStage(false);
                        detailBody.scrollTop = savedDetailScroll;
                        detailStatus.textContent = 'Detalle cargado.';
                    } else {
                        loadDetail(currentUrl);
                    }
                    return;
                }

                var classificationButton = event.target.closest('[data-flow-detail-classification]');
                if (classificationButton && currentUrl) {
                    var classificationUrl = new URL(currentUrl, window.location.href);
                    var classification = classificationButton.dataset.flowDetailClassification;
                    if (classification === 'todos') {
                        classificationUrl.searchParams.delete('clasificacion');
                    } else {
                        classificationUrl.searchParams.set('clasificacion', classification);
                    }
                    classificationUrl.searchParams.delete('page');
                    classificationUrl.searchParams.delete('actualizar');
                    loadDetail(classificationUrl.toString());
                    return;
                }

                var paymentStatusButton = event.target.closest('[data-flow-detail-payment]');
                if (paymentStatusButton && currentUrl) {
                    var statusUrl = new URL(currentUrl, window.location.href);
                    var status = paymentStatusButton.dataset.flowDetailPayment;
                    statusUrl.searchParams.delete('solo_cobrados');
                    if (status === 'todos') {
                        statusUrl.searchParams.delete('estado_cobro');
                    } else {
                        statusUrl.searchParams.set('estado_cobro', status);
                    }
                    statusUrl.searchParams.delete('page');
                    statusUrl.searchParams.delete('actualizar');
                    loadDetail(statusUrl.toString());
                    return;
                }

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

            detailModal.addEventListener('change', function (event) {
                if (event.target.matches('[data-flow-select-movement]')) {
                    updateCollectionSelection();
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
                savedDetail = null;
                detailStatus.textContent = '';
            });
        });
    </script>
@endpush

@section('content')
    @include('financial-reports.partials.ingresos-ventanillas-multi-filters', [
        'action' => route('dashboard.financiera.ingresos-ventanillas'),
        'showLimit' => true,
        'showDepartmentFilter' => true,
        'departmentOptions' => $departmentOptions,
        'selectedDepartment' => $selectedDepartment,
        'filterTitle' => 'Prepare el reporte de ingresos',
        'filterHelp' => 'Seleccione los servicios y meses que desea consolidar. La facturación de Contratos también aparece en el reporte, aunque no seleccione sus servicios aquí.',
    ])

    @if(collect($serviceOptions)->count() > count($selectedServices))
        <div class="alert alert-light border">
            Está consultando una selección de servicios. Para comparar con el cierre completo, pulse <strong>Seleccionar todos</strong> y luego <strong>Filtrar</strong>.
        </div>
    @endif

    <div class="alert alert-info border-0 shadow-sm">
        <i class="fas fa-info-circle mr-1"></i>
        <strong>Criterio del reporte:</strong> El reporte incluye la facturación de Contratos. Registre cada cobro o devolución desde el detalle del movimiento.
        <div class="small mt-1">La facturación se reutiliza durante {{ max(1, (int) ceil(config('services.facturacion_reports.cache_active_seconds', 900) / 60)) }} minutos en el mes actual y {{ max(1, (int) ceil(config('services.facturacion_reports.cache_closed_seconds', 86400) / 3600)) }} horas en los meses anteriores. Pulse <strong>Actualizar datos</strong> para consultar nuevamente. Los cobros registrados se consultan en cada carga.</div>
    </div>

    @if(collect($cashierFlowReceivableDepartmentErrors ?? [])->isNotEmpty())
        <div class="alert alert-warning border-0 shadow-sm">
            <i class="fas fa-exclamation-triangle mr-1"></i>
            El detalle de algunas facturas de Contratos no estuvo disponible o no coincidió con el resumen. El saldo se distribuye según el resumen por usuario y el departamento registrado de quien facturó; los casos sin departamento quedan en “Sin regional asignada”.
        </div>
    @endif

    @if(session('ingresosVentanillasSuccess'))
        <div class="alert alert-success border-0 shadow-sm" role="status">
            <i class="fas fa-check-circle mr-1"></i>{{ session('ingresosVentanillasSuccess') }}
        </div>
    @endif
    @if(session('ingresosVentanillasError'))
        <div class="alert alert-danger border-0 shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle mr-1"></i>{{ session('ingresosVentanillasError') }}
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
        $executiveTopCashier = $topCashier ?? $cashierRows->first();
        $executiveTopService = $topService ?? null;
        $executiveCashReceived = (float) ($cashierFlowVentanillasCollectedAmount ?? $summary['totalSinContratosEca'] ?? 0);
        $executiveContractsTotals = $cashierFlowContractsEcaTotals ?? ['facturado' => 0, 'cobrado' => 0, 'por_cobrar' => 0, 'total_recaudado' => $executiveCashReceived];
        $executiveFlowIncome = (float) ($executiveContractsTotals['total_recaudado'] ?? $executiveCashReceived);
        $executiveLead = 'Ingresos en Ventanillas: Bs ' . \App\Support\BolivianNumber::format($executiveCashReceived, 2)
            . '; Contratos cobró Bs ' . \App\Support\BolivianNumber::format((float) ($executiveContractsTotals['cobrado'] ?? 0), 2)
            . ' y mantiene Bs ' . \App\Support\BolivianNumber::format((float) ($executiveContractsTotals['por_cobrar'] ?? 0), 2) . ' por cobrar.';
        $executiveItems = [
            ['label' => 'Mejor cajero', 'value' => $executiveTopCashier['usuarioNombre'] ?? 'Sin datos', 'detail' => 'Bs ' . \App\Support\BolivianNumber::format((float) ($executiveTopCashier['totalIngresos'] ?? $executiveTopCashier['totalMonto'] ?? 0), 2) . ' en ingresos de Ventanillas.', 'icon' => 'fa-user-tie', 'color' => 'info'],
            ['label' => 'Mayor ingreso', 'value' => $executiveTopService['servicio'] ?? 'Sin datos', 'detail' => 'Bs ' . \App\Support\BolivianNumber::format((float) ($executiveTopService['totalMontoVendido'] ?? $executiveTopService['totalMonto'] ?? 0), 2), 'icon' => 'fa-layer-group', 'color' => 'primary'],
            ['label' => 'Facturas emitidas', 'value' => \App\Support\BolivianNumber::format((float) ($summary['cantidadVentas'] ?? 0)), 'detail' => '', 'icon' => 'fa-file-invoice', 'color' => 'secondary'],
            ['label' => 'Total ingresos', 'value' => 'Bs ' . \App\Support\BolivianNumber::format($executiveFlowIncome, 2), 'detail' => 'Suma de ingresos de Ventanillas más la facturación de Contratos.', 'icon' => 'fa-money-bill-wave', 'color' => 'success'],
            ['label' => 'Facturas anuladas', 'value' => \App\Support\BolivianNumber::format((float) ($summary['cantidadFacturasAnuladas'] ?? 0)), 'detail' => '', 'icon' => 'fa-ban', 'color' => 'danger'],
            ['label' => 'QR anulados', 'value' => \App\Support\BolivianNumber::format((float) ($summary['cantidadQrAnulados'] ?? 0)), 'detail' => '', 'icon' => 'fa-exclamation-circle', 'color' => 'warning'],
        ];
        $executiveNote = 'El reporte incluye ingresos de Ventanillas y facturación de Contratos.';
    @endphp
    @include('financial-reports.partials.ingresos-ventanillas-executive-summary')

    @if($includedSalesCountFromApi ?? false)
        <div class="alert alert-light border">
            <strong>Operaciones registradas:</strong> {{ \App\Support\BolivianNumber::format($summary['cantidadOperacionesRegistradas']) }}.
            <strong>Incluidas en el total vendido:</strong> {{ \App\Support\BolivianNumber::format($summary['cantidadVentasIncluidasEnTotalVendido']) }}.
            <strong>Sin aporte al total vendido:</strong> {{ \App\Support\BolivianNumber::format($summary['cantidadVentasNoIncluidasEnTotalVendido']) }}.
            Las facturas anuladas se muestran por separado. Los desgloses por servicio muestran operaciones; una venta con varios servicios puede aparecer en más de un servicio.
        </div>
    @endif

    <div class="row">
        @foreach([
            ['Servicios', $summary['cantidadServicios'] ?? 0, 'fa-layer-group', 'primary'],
            ['Facturados', $summary['cantidadVentas'] ?? 0, 'fa-file-invoice', 'info'],
            ['Cajeros', $cashierRows->count(), 'fa-users', 'secondary'],
            ['Cantidad de paquetes', $summary['totalCantidad'] ?? 0, 'fa-boxes', 'warning'],
            ['Ingresos en Ventanillas', 'Bs ' . \App\Support\BolivianNumber::format((float) ($cashierFlowVentanillasCollectedAmount ?? 0), 2), 'fa-coins', 'info'],
            ['Facturado de Contratos', 'Bs ' . \App\Support\BolivianNumber::format((float) ($cashierFlowContractsEcaTotals['facturado'] ?? 0), 2), 'fa-file-invoice-dollar', 'warning'],
            ['Cobrado de Contratos', 'Bs ' . \App\Support\BolivianNumber::format((float) ($cashierFlowContractsEcaTotals['cobrado'] ?? 0), 2), 'fa-hand-holding-usd', 'success'],
            ['Por cobrar de Contratos', 'Bs ' . \App\Support\BolivianNumber::format((float) ($cashierFlowContractsEcaTotals['por_cobrar'] ?? 0), 2), 'fa-hourglass-half', 'warning'],
            ['QR ANULADOS', 'Bs ' . \App\Support\BolivianNumber::format((float) ($summary['totalMontoNoIncluidoEnTotalVendido'] ?? 0), 2), 'fa-exclamation-circle', 'warning'],
            ['Facturas anuladas', 'Bs ' . \App\Support\BolivianNumber::format((float) ($summary['totalMontoAnulado'] ?? 0), 2), 'fa-ban', 'danger'],
            ['Total ingresos (incluye Contratos)', 'Bs ' . \App\Support\BolivianNumber::format((float) ($cashierFlowContractsEcaTotals['total_recaudado'] ?? 0), 2), 'fa-cash-register', 'primary'],
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

        <div class="card card-outline card-warning flow-section-card">
            <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
                <div class="d-flex align-items-center">
                    <button type="button" class="flow-section-toggle mr-2" data-flow-section-toggle data-flow-section-name="Contratos por cobrar" aria-controls="cashier-receivables-content" aria-expanded="true" aria-label="Ocultar contenido de Contratos por cobrar">
                        <span class="flow-icon flow-icon-warning mb-0"><i class="fas fa-file-invoice-dollar"></i></span>
                        <strong class="d-block">Contratos</strong>
                        <span class="group-chevron"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </button>
                    <div class="ml-2">
                    <div class="text-muted small">Facturas, cobros, saldos pendientes y facturadores de Contratos.</div>
                </div>
                </div>
                <div class="h5 text-warning font-weight-bold mb-0 ml-md-3 mt-2 mt-md-0">
                    Facturado: Bs {{ \App\Support\BolivianNumber::format((float) ($cashierFlowContractsEcaTotals['facturado'] ?? 0), 2) }}
                    <span class="text-success ml-2">Cobrado: Bs {{ \App\Support\BolivianNumber::format((float) ($cashierFlowContractsEcaTotals['cobrado'] ?? 0), 2) }}</span>
                    <span class="text-warning ml-2">Por cobrar: Bs {{ \App\Support\BolivianNumber::format((float) ($cashierFlowContractsEcaTotals['por_cobrar'] ?? 0), 2) }}</span>
                </div>
            </div>
            <div class="card-body p-0" id="cashier-receivables-content">
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
                                <th class="text-right">Facturado</th>
                                <th class="text-right">Cobrado</th>
                                <th class="text-right">Cobrado QR</th>
                                <th class="text-right">Total ingresos</th>
                                <th class="text-right">Pendiente por cobrar</th>
                                <th class="text-center">Detalle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($receivableServices as $receivableService)
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
                                    <td class="text-right text-success font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableService['_montoCobradoNoQr'] ?? 0), 2) }}</td>
                                    <td class="text-right text-primary">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableService['_montoCobradoQr'] ?? 0), 2) }}</td>
                                    <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableService['_montoRecaudado'] ?? $receivableService['_montoCobrado'] ?? 0), 2) }}</td>
                                    <td class="text-right text-warning font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableService['_montoPendiente'] ?? $receivableService['totalMonto'] ?? 0), 2) }}</td>
                                    <td class="text-center">
                                        <a class="btn btn-outline-primary btn-sm flow-detail-open" href="{{ route('dashboard.financiera.ingresos-ventanillas.detalle', ['servicios' => [$receivableService['servicio']], 'meses' => $selectedMonths, 'anio' => $anio, 'ingresos_ventanillas' => 1, 'filtro_servicios' => $selectedServices, 'filtro_meses' => $selectedMonths, 'filtro_anio' => $anio, 'filtro_limite' => $limite, 'filtro_departamento' => $selectedDepartment]) }}" data-flow-detail-trigger data-flow-detail-title="{{ $isContractReceivable ? 'Contratos' : $receivableService['servicio'] }}" aria-haspopup="dialog" aria-controls="flow-detail-modal">
                                            <i class="fas fa-list mr-1"></i> Detalle
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center text-muted py-4">No se encontraron facturas de Contratos para los meses y el departamento seleccionados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if($receivableServices->isNotEmpty())
                            <tfoot class="font-weight-bold">
                                <tr>
                                    <td colspan="6">TOTAL CONTRATOS</td>
                                    <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) $receivableServices->sum('totalMonto'), 2) }}</td>
                                    <td class="text-right text-success">Bs {{ \App\Support\BolivianNumber::format((float) $receivableServices->sum('_montoCobradoNoQr'), 2) }}</td>
                                    <td class="text-right text-primary">Bs {{ \App\Support\BolivianNumber::format((float) $receivableServices->sum('_montoCobradoQr'), 2) }}</td>
                                    <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) $receivableServices->sum('_montoRecaudado'), 2) }}</td>
                                    <td class="text-right text-warning">Bs {{ \App\Support\BolivianNumber::format((float) $receivableServices->sum('_montoPendiente'), 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
                <div class="small text-muted px-3 py-2 border-top">El promedio diario excluye domingos.</div>
                <div class="px-3 py-3 border-top">
                    <h6 class="font-weight-bold mb-2">Cajeros / facturadores de Contratos</h6>
                    <div class="small text-muted mb-2">El nombre corresponde al usuario que facturó el servicio; los montos se distribuyen por facturador y departamento.</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover mb-0 financial-table">
                            <thead class="thead-light">
                                <tr>
                                    <th>Facturado por</th>
                                    <th>Regional / departamento</th>
                                    <th>Servicio</th>
                                    <th class="text-right">Facturado</th>
                                    <th class="text-right">Cobrado</th>
                                    <th class="text-right">No cobrado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($cashierFlowReceivableInvoicers ?? collect()) as $receivableInvoicer)
                                    <tr>
                                        <td class="font-weight-bold">{{ $receivableInvoicer['facturadoPor'] ?? 'USUARIO SIN IDENTIFICAR' }}</td>
                                        <td><span class="badge badge-warning">{{ $receivableInvoicer['departamento'] ?? 'SIN REGIONAL ASIGNADA' }}</span></td>
                                        <td>{{ $receivableInvoicer['servicio'] ?? '-' }}</td>
                                        <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableInvoicer['totalFacturado'] ?? 0), 2) }}</td>
                                        <td class="text-right text-success">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableInvoicer['totalCobrado'] ?? 0), 2) }}</td>
                                        <td class="text-right text-warning font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($receivableInvoicer['totalNoCobrado'] ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="text-center text-muted py-3">No hay facturadores de Contratos para los filtros seleccionados.</td></tr>
                                @endforelse
                            </tbody>
                            @if(($cashierFlowReceivableInvoicers ?? collect())->isNotEmpty())
                                <tfoot class="font-weight-bold">
                                    <tr>
                                    <td colspan="3">TOTAL CONTRATOS</td>
                                        <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) $cashierFlowReceivableInvoicers->sum('totalFacturado'), 2) }}</td>
                                        <td class="text-right text-success">Bs {{ \App\Support\BolivianNumber::format((float) $cashierFlowReceivableInvoicers->sum('totalCobrado'), 2) }}</td>
                                        <td class="text-right text-warning">Bs {{ \App\Support\BolivianNumber::format((float) $cashierFlowReceivableInvoicers->sum('totalNoCobrado'), 2) }}</td>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <div class="card card-outline card-success flow-section-card">
        <div class="card-header">
            <button type="button" class="flow-section-toggle" data-flow-section-toggle data-flow-section-name="Ingresos por cajero" aria-controls="cashier-by-cashier-content" aria-expanded="true" aria-label="Ocultar contenido de Ingresos por cajero">
                <span class="flow-icon mb-0"><i class="fas fa-cash-register"></i></span>
                <strong>Ingresos por cajero</strong>
                <span class="group-chevron"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
            </button>
            <div class="ml-5">
                    <div class="text-muted small">Esta tabla presenta los ingresos generados por cada cajero de Ventanillas.</div>
            </div>
        </div>
        <div class="card-body p-0" id="cashier-by-cashier-content">
            <div class="text-muted small px-3 pt-2">El total general y los subtotales por departamento usan ventas únicas. Las filas individuales de cajeros muestran facturados por servicio y pueden solaparse si una venta incluye varios servicios.</div>
            @if(!empty($cashierFlowDepartmentSalesCountWarning))
                <div class="alert alert-warning mx-3 mt-2 mb-0 py-2">{{ $cashierFlowDepartmentSalesCountWarning }}</div>
            @endif
            <div class="table-responsive">
                @php($cashierTotal = (float) $cashierDepartmentGroups->sum('totalIngresos'))
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th class="text-center">#</th>
                            <th>Cajero</th>
                            <th>Regional / departamento</th>
                            <th class="text-right">Facturados</th>
                            <th class="text-right">Detalles</th>
                            <th class="text-right">Cantidad de paquetes</th>
                            <th class="text-right">Paquetes promedio por día</th>
                            <th class="text-right">Promedio diario de ingresos (lun-sáb)</th>
                            <th class="text-right">QR</th>
                            <th class="text-right">Efectivo</th>
                            <th class="text-right">Ingresos</th>
                            <th>Participación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashierDepartmentGroups as $departmentGroup)
                            <tr class="table-info font-weight-bold">
                                <td colspan="3">
                                    <i class="fas fa-map-marker-alt text-primary mr-1"></i>
                                    {{ $departmentGroup['departamento'] }}
                                    <span class="text-muted font-weight-normal">({{ $departmentGroup['cantidadCajeros'] }} cajeros)</span>
                                </td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format($departmentGroup['cantidadVentas']) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format($departmentGroup['cantidadDetalles']) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format($departmentGroup['totalCantidad'], 2) }}</td>
                                <td></td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format($departmentGroup['promedioGeneradoDiario'], 2) }}</td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format(collect($departmentGroup['cajeros'])->sum('paymentMethods.qr.totalMonto'), 2) }}</td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format(collect($departmentGroup['cajeros'])->sum('paymentMethods.efectivo.totalMonto'), 2) }}</td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format($departmentGroup['totalIngresos'], 2) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format($cashierTotal > 0 ? ($departmentGroup['totalIngresos'] / $cashierTotal) * 100 : 0, 1) }}%</td>
                            </tr>
                            @foreach($departmentGroup['cajeros'] as $cashier)
                                @php($share = $departmentGroup['totalIngresos'] > 0 ? ((float) $cashier['totalIngresos'] / $departmentGroup['totalIngresos']) * 100 : 0)
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
                                    <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($cashier['promedioGeneradoDiario'] ?? 0), 2) }}</td>
                                    <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) data_get($cashier, 'paymentMethods.qr.totalMonto', 0), 2) }}</td>
                                    <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) data_get($cashier, 'paymentMethods.efectivo.totalMonto', 0), 2) }}</td>
                                    <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($cashier['totalIngresos'] ?? $cashier['totalMonto']), 2) }}</td>
                                    <td class="flow-progress">
                                        <span class="small font-weight-bold">{{ \App\Support\BolivianNumber::format($share, 1) }}%</span>
                                        <div class="progress"><div class="progress-bar bg-success" style="width: {{ min(100, $share) }}%"></div></div>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="12" class="text-center text-muted py-4">La API no devolvió información por cajero.</td></tr>
                        @endforelse
                    </tbody>
                    @if($cashierDepartmentGroups->isNotEmpty())
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td colspan="3">Totales</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($summary['cantidadVentas'] ?? $cashierRows->sum('cantidadVentas'))) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('cantidadDetalles')) }}</td>
                                <td class="text-right">{{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('totalCantidad'), 2) }}</td>
                                <td></td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('promedioGeneradoDiario'), 2) }}</td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('paymentMethods.qr.totalMonto'), 2) }}</td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) $cashierRows->sum('paymentMethods.efectivo.totalMonto'), 2) }}</td>
                                <td class="text-right">Bs {{ \App\Support\BolivianNumber::format((float) $cashierDepartmentGroups->sum('totalIngresos'), 2) }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary flow-section-card">
        <div class="card-header">
            <button type="button" class="flow-section-toggle" data-flow-section-toggle data-flow-section-name="Ingresos por servicio" aria-controls="cashier-by-service-content" aria-expanded="true" aria-label="Ocultar contenido de Ingresos por servicio">
                <span class="flow-icon mb-0"><i class="fas fa-layer-group"></i></span>
                <strong>Ingresos por servicio</strong>
                <span class="group-chevron"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
            </button>
            <div class="ml-5">
                <div class="text-muted small">Abra VENTANILLAS o Contratos para ver los servicios; abra cada grupo de servicio para ver sus movimientos.</div>
                <div class="text-muted small">VENTANILLAS muestra ventas únicas; los conteos por servicio pueden solaparse cuando una venta incluye varios servicios.</div>
                <div class="text-muted small">Cobrado + Cobrado QR = Total de ingresos. Cobrado QR excluye las facturas anuladas y se suma una sola vez. El saldo de Contratos aparece en su tabla.</div>
            </div>
        </div>
        <div class="card-body p-0" id="cashier-by-service-content">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Servicio agrupado</th>
                            <th>Meses</th>
                            <th class="text-right">Facturados</th>
                            <th class="text-right">Detalles</th>
                            <th class="text-right">Cantidad de paquetes</th>
                            <th class="text-right">Cobrado</th>
                            <th class="text-right">Cobrado QR</th>
                            <th class="text-right">Total ingresos</th>
                            <th class="text-right">QR ANULADOS</th>
                            <th class="text-right">Anulado</th>
                            <th class="text-right">Por cobrar</th>
                            <th>Última fecha</th>
                            <th class="text-center">Movimientos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceGroups as $group)
                            @php($groupId = 'cashier-service-group-' . $loop->iteration)
                            @php($isReceivableGroup = ($group['servicio'] ?? '') === 'Contratos')
                            @php($groupDetailServices = collect($group['_children'] ?? [])->flatMap(fn (array $child) => collect($child['_children'] ?? [])->isNotEmpty() ? collect($child['_children'])->pluck('servicio') : collect([$child['servicio'] ?? '']))->filter()->unique()->values()->all())
                            @php($groupDetailQuery = ['servicios' => $groupDetailServices, 'meses' => $selectedMonths, 'anio' => $anio])
                            @if($isReceivableGroup)
                                @php($groupDetailQuery = array_merge($groupDetailQuery, ['ingresos_ventanillas' => 1, 'filtro_servicios' => $selectedServices, 'filtro_meses' => $selectedMonths, 'filtro_anio' => $anio, 'filtro_limite' => $limite, 'filtro_departamento' => $selectedDepartment]))
                            @endif
                            <tr class="service-group-row" data-flow-toggle-row data-flow-toggle-id="{{ $groupId }}">
                                <td>
                                    <button type="button" class="group-toggle-button" data-flow-toggle-button data-flow-toggle-name="{{ $group['servicio'] ?? '-' }}" aria-expanded="false" aria-label="Mostrar servicios de {{ $group['servicio'] ?? '-' }}">
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
                                    <td class="text-right font-weight-bold text-success">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoCobrado'] ?? 0), 2) }}</td>
                                    <td class="text-right text-primary">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoCobradoQr'] ?? 0), 2) }}</td>
                                    <td class="text-right font-weight-bold text-success">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoRecaudado'] ?? 0), 2) }}</td>
                                    <td class="text-right text-warning">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoNoIncluidoEnTotalVendido'] ?? 0), 2) }}</td>
                                    <td class="text-right text-danger">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoAnulado'] ?? 0), 2) }}</td>
                                    <td class="text-right text-info font-weight-bold">@if($isReceivableGroup)Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoPorCobrar'] ?? 0), 2) }}@else—@endif</td>
                                <td class="text-nowrap">
                                    {{ $group['ultimaFecha'] ?? '-' }}
                                    @if($group['_ultimaFechaEsCobro'] ?? false)<small class="d-block text-muted">Fecha del Ãºltimo cobro</small>@endif
                                </td>
                                <td class="text-center">
                                    <a class="btn btn-outline-primary flow-detail-open" href="{{ route('dashboard.financiera.ingresos-ventanillas.detalle', $groupDetailQuery) }}" data-flow-detail-trigger data-flow-detail-title="{{ $group['servicio'] ?? '-' }}" aria-haspopup="dialog" aria-controls="flow-detail-modal">
                                        <i class="fas fa-list mr-1"></i> Ver detalle
                                    </a>
                                </td>
                            </tr>
                            @foreach($group['_children'] ?? [] as $child)
                                @if($isReceivableGroup)
                                    @php($serviceItem = $child)
                                    @php($serviceDetailQuery = ['servicios' => [$serviceItem['servicio'] ?? ''], 'meses' => $selectedMonths, 'anio' => $anio, 'ingresos_ventanillas' => 1, 'filtro_servicios' => $selectedServices, 'filtro_meses' => $selectedMonths, 'filtro_anio' => $anio, 'filtro_limite' => $limite, 'filtro_departamento' => $selectedDepartment])
                                    <tr class="service-item-row d-none" data-flow-parent="{{ $groupId }}">
                                        <td></td>
                                        <td class="child-service-name"><span class="child-connector"></span>{{ $serviceItem['servicio'] ?? '-' }}</td>
                                        <td>@foreach($serviceItem['_meses'] ?? [] as $includedMonth)<span class="badge badge-light border">{{ [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'][$includedMonth] ?? $includedMonth }}</span>@endforeach</td>
                                        <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($serviceItem['cantidadVentas'] ?? 0)) }}</td>
                                        <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($serviceItem['cantidadDetalles'] ?? 0)) }}</td>
                                        <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalCantidad'] ?? 0), 2) }}</td>
                                        <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoCobrado'] ?? 0), 2) }}</td>
                                        <td class="text-right text-primary">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoCobradoQr'] ?? 0), 2) }}</td>
                                        <td class="text-right font-weight-bold text-success">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoRecaudado'] ?? 0), 2) }}</td>
                                        <td class="text-right text-warning">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoNoIncluidoEnTotalVendido'] ?? 0), 2) }}</td>
                                        <td class="text-right text-danger">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoAnulado'] ?? 0), 2) }}</td>
                                        <td class="text-right text-info font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoPorCobrar'] ?? $serviceItem['_montoPendiente'] ?? 0), 2) }}</td>
                                        <td class="text-nowrap">{{ $serviceItem['ultimaFecha'] ?? '-' }}</td>
                                        <td class="text-center"><a class="btn btn-outline-primary flow-detail-open" href="{{ route('dashboard.financiera.ingresos-ventanillas.detalle', $serviceDetailQuery) }}" data-flow-detail-trigger data-flow-detail-title="{{ $serviceItem['servicio'] ?? '-' }}" aria-haspopup="dialog" aria-controls="flow-detail-modal"><i class="fas fa-search mr-1"></i> Detalle</a></td>
                                    </tr>
                                @else
                                    @php($familyId = $groupId . '-family-' . $loop->iteration)
                                    @php($familyDetailServices = collect($child['_children'] ?? [])->pluck('servicio')->filter()->unique()->values()->all())
                                    @php($familyDetailQuery = ['servicios' => $familyDetailServices, 'meses' => $selectedMonths, 'anio' => $anio])
                                    <tr class="service-family-row d-none" data-flow-parent="{{ $groupId }}" data-flow-toggle-row data-flow-toggle-id="{{ $familyId }}">
                                        <td><button type="button" class="group-toggle-button" data-flow-toggle-button data-flow-toggle-name="{{ $child['servicio'] ?? '-' }}" aria-expanded="false" aria-label="Mostrar servicios de {{ $child['servicio'] ?? '-' }}"><span class="group-chevron"><i class="fas fa-chevron-right" aria-hidden="true"></i></span></button></td>
                                        <td class="service-family-name">{{ $child['servicio'] ?? '-' }}</td>
                                        <td>@foreach($child['_meses'] ?? [] as $includedMonth)<span class="badge badge-light border">{{ [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'][$includedMonth] ?? $includedMonth }}</span>@endforeach</td>
                                        <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($child['cantidadVentas'] ?? 0)) }}</td>
                                        <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($child['cantidadDetalles'] ?? 0)) }}</td>
                                        <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($child['totalCantidad'] ?? 0), 2) }}</td>
                                        <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoCobrado'] ?? 0), 2) }}</td>
                                        <td class="text-right text-primary">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoCobradoQr'] ?? 0), 2) }}</td>
                                        <td class="text-right font-weight-bold text-success">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoRecaudado'] ?? 0), 2) }}</td>
                                        <td class="text-right text-warning">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoNoIncluidoEnTotalVendido'] ?? 0), 2) }}</td>
                                        <td class="text-right text-danger">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoAnulado'] ?? 0), 2) }}</td>
                                        <td class="text-right">—</td>
                                        <td class="text-nowrap">{{ $child['ultimaFecha'] ?? '-' }}</td>
                                        <td class="text-center"><a class="btn btn-outline-primary flow-detail-open" href="{{ route('dashboard.financiera.ingresos-ventanillas.detalle', $familyDetailQuery) }}" data-flow-detail-trigger data-flow-detail-title="{{ $child['servicio'] ?? '-' }}" aria-haspopup="dialog" aria-controls="flow-detail-modal"><i class="fas fa-search mr-1"></i> Ver detalle</a></td>
                                    </tr>
                                    @foreach($child['_children'] ?? [] as $serviceItem)
                                        @php($serviceDetailQuery = ['servicios' => [$serviceItem['servicio'] ?? ''], 'meses' => $selectedMonths, 'anio' => $anio])
                                        <tr class="service-item-row d-none" data-flow-parent="{{ $familyId }}">
                                            <td></td>
                                            <td class="child-service-name"><span class="child-connector"></span>{{ $serviceItem['servicio'] ?? '-' }}</td>
                                            <td>@foreach($serviceItem['_meses'] ?? [] as $includedMonth)<span class="badge badge-light border">{{ [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'][$includedMonth] ?? $includedMonth }}</span>@endforeach</td>
                                            <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($serviceItem['cantidadVentas'] ?? 0)) }}</td>
                                            <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($serviceItem['cantidadDetalles'] ?? 0)) }}</td>
                                            <td class="text-right">{{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalCantidad'] ?? 0), 2) }}</td>
                                            <td class="text-right font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoCobrado'] ?? 0), 2) }}</td>
                                            <td class="text-right text-primary">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoCobradoQr'] ?? 0), 2) }}</td>
                                            <td class="text-right font-weight-bold text-success">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoRecaudado'] ?? 0), 2) }}</td>
                                            <td class="text-right text-warning">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoNoIncluidoEnTotalVendido'] ?? 0), 2) }}</td>
                                            <td class="text-right text-danger">Bs {{ \App\Support\BolivianNumber::format((float) ($serviceItem['totalMontoAnulado'] ?? 0), 2) }}</td>
                                            <td class="text-right">—</td>
                                            <td class="text-nowrap">{{ $serviceItem['ultimaFecha'] ?? '-' }}</td>
                                            <td class="text-center"><a class="btn btn-outline-primary flow-detail-open" href="{{ route('dashboard.financiera.ingresos-ventanillas.detalle', $serviceDetailQuery) }}" data-flow-detail-trigger data-flow-detail-title="{{ $serviceItem['servicio'] ?? '-' }}" aria-haspopup="dialog" aria-controls="flow-detail-modal"><i class="fas fa-search mr-1"></i> Detalle</a></td>
                                        </tr>
                                    @endforeach
                                @endif
                            @endforeach
                        @empty
                            <tr><td colspan="14" class="text-center text-muted py-4">No se encontraron servicios para la selección realizada.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card card-outline card-danger flow-section-card">
        <div class="card-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="d-flex align-items-center">
                <button type="button" class="flow-section-toggle mr-2" data-flow-section-toggle data-flow-section-name="Facturas canceladas" aria-controls="cashier-cancelled-invoices-content" aria-expanded="true" aria-label="Ocultar contenido de Facturas canceladas">
                    <span class="flow-icon flow-icon-warning mb-0"><i class="fas fa-file-invoice"></i></span>
                    <span>
                        <strong class="d-block">Facturas canceladas</strong>
                        <span class="text-muted small font-weight-normal">Facturas anuladas o canceladas en el periodo. Haga clic para ocultar o mostrar la tabla.</span>
                    </span>
                    <span class="group-chevron"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                </button>
            </div>
            <div class="mt-2 mt-md-0 text-md-right">
                <span class="badge badge-danger">{{ $cashierFlowCancelledInvoices->count() }} facturas</span>
                <div class="font-weight-bold text-danger mt-1">Total: Bs {{ \App\Support\BolivianNumber::format((float) $cashierFlowCancelledInvoices->sum('monto'), 2) }}</div>
            </div>
        </div>
        <div class="card-body p-0" id="cashier-cancelled-invoices-content">
            @if($cashierFlowCancellationLookupErrors->isNotEmpty())
                <div class="alert alert-warning rounded-0 mb-0" role="status">
                    <strong>Consulta incompleta:</strong> no se pudo verificar el estado de {{ $cashierFlowCancellationLookupErrors->count() }} detalle(s). Algunas facturas canceladas podrían no aparecer.
                </div>
            @endif
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th>Servicio</th>
                            <th>Fecha</th>
                            <th>Venta / detalle</th>
                            <th>Facturó</th>
                            <th>Medio de pago</th>
                            <th>Estado fiscal</th>
                            <th>Estado de pago</th>
                            <th class="text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cashierFlowCancelledInvoices as $invoice)
                            <tr>
                                <td class="font-weight-bold">{{ $invoice['servicio'] }}</td>
                                <td class="text-nowrap">{{ $invoice['fecha'] !== '' ? $invoice['fecha'] : '-' }}</td>
                                <td>
                                    {{ $invoice['venta'] }}
                                    <small class="d-block text-muted">Detalle: {{ $invoice['detalle'] }}</small>
                                </td>
                                <td>{{ $invoice['facturadoPor'] }}</td>
                                <td>{{ $invoice['medioPago'] !== '' ? $invoice['medioPago'] : '-' }}</td>
                                <td class="font-weight-bold">{{ $invoice['estadoFiscal'] !== '' ? $invoice['estadoFiscal'] : '-' }}</td>
                                <td>{{ $invoice['estadoPago'] !== '' ? $invoice['estadoPago'] : '-' }}</td>
                                <td class="text-right text-danger font-weight-bold">Bs {{ \App\Support\BolivianNumber::format((float) $invoice['monto'], 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No se encontraron facturas canceladas en los detalles consultados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="small text-muted px-3 py-2 border-top">Se buscan facturas con estado fiscal ANULADO/ANULADA o estado de pago ANULADO/ANULADA/CANCELADO/CANCELADA.</div>
        </div>
    </div>

    <div class="modal fade flow-detail-modal" id="flow-detail-modal" data-collection-action="{{ route('dashboard.financiera.ingresos-ventanillas.movimientos.cobro-realizado') }}" tabindex="-1" role="dialog" aria-labelledby="flow-detail-modal-title" aria-hidden="true">
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
                    <button type="button" class="btn btn-outline-secondary d-none" data-flow-collection-cancel>Cancelar</button>
                    <button type="submit" form="flow-collection-form" class="btn btn-success d-none" data-flow-collection-submit>
                        <i class="fas fa-check mr-1" aria-hidden="true"></i> Confirmar cobro
                    </button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
@stop

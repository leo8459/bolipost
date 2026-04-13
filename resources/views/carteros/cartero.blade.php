@extends('adminlte::page')
@section('title', 'Carteros - Cartero')
@section('template_title')
    Carteros - Cartero
@endsection

@section('content')
    @php
        $canCarteroGuide = auth()->user()?->can('feature.carteros.cartero.guide') ?? false;
        $canCarteroProvince = auth()->user()?->can('feature.carteros.cartero.province') ?? false;
        $canCarteroDeliver = auth()->user()?->can('feature.carteros.cartero.deliver') ?? false;
    @endphp
    <div class="carteros-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="cartero-header-top">
                    <div>
                        <h3 class="card-title mb-1">Paquetes en Estado CARTERO</h3>
                        <div class="carteros-meta text-white-50">Bandeja personal para gestionar entregas y envios a provincia.</div>
                    </div>
                    <div class="cartero-header-actions">
                        @if ($canCarteroGuide)
                            <button id="btn-open-guia-modal" class="btn btn-sm btn-carteros-primary">Mandar provincia</button>
                        @endif
                        @if ($canCarteroProvince)
                            <button id="btn-show-provincia" class="btn btn-sm btn-outline-light cartero-mode-btn">Mostrar provincias</button>
                            <button id="btn-show-cartero" class="btn btn-sm btn-outline-light cartero-mode-btn" style="display:none;">Mostrar cartero</button>
                        @endif
                        <span id="bandeja-chip" class="carteros-chip">Mi bandeja CARTERO</span>
                    </div>
                </div>
            </div>
            <div id="guia-message" class="px-3 pt-3" style="display:none;"></div>
            <div class="card-body">
                <div class="cartero-shell">
                    <div class="cartero-toolbar">
                        <div class="cartero-toolbar-copy">
                            <div class="cartero-toolbar-title">Busqueda rapida</div>
                            <div class="cartero-toolbar-subtitle">Filtra por codigo y cambia de bandeja sin salir de la vista.</div>
                        </div>
                        <div class="cartero-search-cluster">
                            <input
                                type="text"
                                id="codigo-search-input"
                                class="form-control"
                                placeholder="Pega el codigo y presiona Enter..."
                            >
                            <button type="button" id="btn-codigo-search" class="btn btn-carteros-secondary">Buscar</button>
                            <button type="button" id="btn-codigo-clear" class="btn btn-carteros-clear">Limpiar</button>
                        </div>
                    </div>

                    <div class="table-responsive cartero-table-wrap">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:36px;">
                                        <input type="checkbox" id="select-all-cartero">
                                    </th>
                                    <th>Tipo</th>
                                    <th>Codigo</th>
                                    <th>Destinatario</th>
                                    <th>Telefono</th>
                                    <th>Ciudad</th>
                                    <th>Zona</th>
                                    <th>Peso</th>
                                    <th>Estado</th>
                                    <th>Asignado a</th>
                                    <th>Intento</th>
                                    <th>Fecha</th>
                                    <th>Accion</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-cartero-body">
                                <tr>
                                    <td colspan="13" class="text-center py-4">Cargando datos...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-footer clearfix">
                <ul class="pagination pagination-sm m-0 float-right">
                    <li class="page-item" id="prev-page-item">
                        <a class="page-link" href="#" id="prev-page-link">Anterior</a>
                    </li>
                    <li class="page-item disabled">
                        <span class="page-link" id="page-indicator">Pagina 1 de 1</span>
                    </li>
                    <li class="page-item" id="next-page-item">
                        <a class="page-link" href="#" id="next-page-link">Siguiente</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="modal fade" id="guiaModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="guia-form">
                    <div class="modal-header">
                        <h5 class="modal-title">Registrar guia</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Transportadora</label>
                            <input type="text" class="form-control" id="guia-transportadora" required>
                        </div>
                        <div class="form-group">
                            <label>Provincia</label>
                            <input type="text" class="form-control" id="guia-provincia" required>
                        </div>
                        <div class="form-group">
                            <label>Factura</label>
                            <input type="text" class="form-control" id="guia-factura">
                        </div>
                        <div class="form-group">
                            <label>Peso total (manual)</label>
                            <input type="number" step="0.001" min="0" class="form-control" id="guia-peso-total">
                        </div>
                        <div class="form-group">
                            <label>Precio total (manual)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="guia-precio-total">
                        </div>
                        <div class="small text-muted">
                            Seleccionados: <strong id="guia-selected-count">0</strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-carteros-primary" id="btn-guardar-guia">Guardar guia</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('css')
    @include('carteros.partials.theme')
    <style>
        .cartero-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
        }

        .cartero-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .cartero-shell {
            border: 1px solid #e4e8f2;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
        }

        .cartero-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 16px 18px;
            border-bottom: 1px solid #e4e8f2;
            background: linear-gradient(180deg, #fbfcff 0%, #f7faff 100%);
            flex-wrap: wrap;
        }

        .cartero-toolbar-title {
            color: var(--carteros-primary);
            font-size: 1rem;
            font-weight: 800;
        }

        .cartero-toolbar-subtitle {
            color: #5e6b86;
            font-size: 0.85rem;
            margin-top: 2px;
        }

        .cartero-search-cluster {
            width: min(100%, 760px);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: nowrap;
        }

        .cartero-search-cluster .form-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .cartero-search-cluster .form-control:focus {
            border-color: var(--carteros-primary);
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }

        .cartero-table-wrap {
            margin-bottom: 0;
        }

        .cartero-table-wrap .table {
            margin-bottom: 0;
        }

        .cartero-table-wrap .table tbody td {
            border-top: 1px solid rgba(32, 83, 154, 0.08);
            vertical-align: middle;
        }

        .cartero-mode-btn,
        .btn-carteros-secondary,
        .btn-carteros-clear {
            min-height: 42px;
            border-radius: 12px;
            font-weight: 800;
        }

        .btn-carteros-secondary {
            background: var(--carteros-secondary);
            border-color: var(--carteros-secondary);
            color: #fff;
        }

        .btn-carteros-secondary:hover {
            background: #f4c21d;
            border-color: #f4c21d;
            color: #fff;
        }

        .btn-carteros-clear {
            background: #fff;
            border: 1px solid rgba(32, 83, 154, 0.22);
            color: var(--carteros-primary);
        }

        .btn-carteros-clear:hover {
            background: rgba(32, 83, 154, 0.05);
            color: var(--carteros-primary);
        }

        #guiaModal .modal-content {
            border: 0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18);
        }

        #guiaModal .modal-header {
            background: linear-gradient(95deg, var(--carteros-primary) 0%, #43538f 100%);
            color: #fff;
            border-bottom: 0;
        }

        #guiaModal .modal-header .close {
            color: #fff;
            opacity: 1;
            text-shadow: none;
        }

        #guiaModal .modal-body {
            padding: 1.2rem 1.25rem 1rem;
        }

        #guiaModal .modal-body label {
            color: #334155;
            font-weight: 700;
            margin-bottom: 0.45rem;
        }

        #guiaModal .form-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: #cbd5e1;
            box-shadow: none;
        }

        #guiaModal .form-control:focus {
            border-color: var(--carteros-primary);
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }

        #guiaModal .modal-footer {
            border-top: 1px solid #e4e8f2;
            background: #f8faff;
        }

        @media (max-width: 991.98px) {
            .cartero-search-cluster {
                width: 100%;
                flex-wrap: wrap;
            }

            .cartero-search-cluster .form-control,
            .cartero-search-cluster .btn {
                width: 100%;
            }
        }
    </style>
@endsection

@section('js')
    <script>
        (function() {
            let currentPage = 1;
            const perPage = 25;
            const selectedItems = {};
            let currentEndpoint = '{{ route('api.carteros.cartero') }}';
            let currentModeLabel = 'CARTERO';

            const body = document.getElementById('tabla-cartero-body');
            const pageIndicator = document.getElementById('page-indicator');
            const prevItem = document.getElementById('prev-page-item');
            const nextItem = document.getElementById('next-page-item');
            const prevLink = document.getElementById('prev-page-link');
            const nextLink = document.getElementById('next-page-link');
            const selectAll = document.getElementById('select-all-cartero');
            const btnOpenGuiaModal = document.getElementById('btn-open-guia-modal');
            const guiaForm = document.getElementById('guia-form');
            const guiaTransportadora = document.getElementById('guia-transportadora');
            const guiaProvincia = document.getElementById('guia-provincia');
            const guiaFactura = document.getElementById('guia-factura');
            const guiaPesoTotal = document.getElementById('guia-peso-total');
            const guiaPrecioTotal = document.getElementById('guia-precio-total');
            const guiaSelectedCount = document.getElementById('guia-selected-count');
            const guiaMessage = document.getElementById('guia-message');
            const btnGuardarGuia = document.getElementById('btn-guardar-guia');
            const btnShowProvincia = document.getElementById('btn-show-provincia');
            const btnShowCartero = document.getElementById('btn-show-cartero');
            const bandejaChip = document.getElementById('bandeja-chip');
            const codigoSearchInput = document.getElementById('codigo-search-input');
            const btnCodigoSearch = document.getElementById('btn-codigo-search');
            const btnCodigoClear = document.getElementById('btn-codigo-clear');
            const canCarteroGuide = @json($canCarteroGuide);
            const canCarteroProvince = @json($canCarteroProvince);
            const canCarteroDeliver = @json($canCarteroDeliver);
            let currentCodigoFilter = '';

            function showMessage(text, type) {
                guiaMessage.style.display = 'block';
                guiaMessage.innerHTML = '<div class="alert alert-' + type + ' mb-0">' + escapeHtml(text) + '</div>';
            }

            function refreshSelectedCount() {
                guiaSelectedCount.textContent = String(Object.keys(selectedItems).length);
            }

            function updateSelectAllState() {
                const checkboxes = body.querySelectorAll('.row-check');
                if (!checkboxes.length) {
                    selectAll.checked = false;
                    return;
                }
                const checked = body.querySelectorAll('.row-check:checked').length;
                selectAll.checked = checkboxes.length === checked;
            }

            function escapeHtml(value) {
                if (value === null || value === undefined) return '';
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function setLoading() {
                body.innerHTML = '<tr><td colspan="13" class="text-center py-4">Cargando datos...</td></tr>';
            }

            function setError() {
                body.innerHTML = '<tr><td colspan="13" class="text-center text-danger py-4">Error cargando datos.</td></tr>';
            }

            function renderRows(rows) {
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="13" class="text-center py-4">No hay paquetes en estado ' + escapeHtml(currentModeLabel) + ' para este usuario.</td></tr>';
                    updateSelectAllState();
                    return;
                }

                body.innerHTML = rows.map(function(row) {
                    const entregaUrl = '{{ route('carteros.entrega') }}' + '?tipo_paquete=' + encodeURIComponent(row.tipo_paquete) + '&id=' + encodeURIComponent(row.id);
                    const key = row.tipo_paquete + '-' + row.id;
                    const checked = Boolean(selectedItems[key]) ? ' checked' : '';
                    const actionHtml = canCarteroDeliver
                        ? '<a href="' + entregaUrl + '" class="btn btn-sm btn-carteros-primary">Entregar correspondencia</a>'
                        : '<span class="text-muted small">Sin accion</span>';

                    return '<tr>' +
                        '<td><input type="checkbox" class="row-check" data-key="' + escapeHtml(key) + '"' + checked + '></td>' +
                        '<td>' + escapeHtml(row.tipo_paquete) + '</td>' +
                        '<td>' + escapeHtml(row.codigo) + '</td>' +
                        '<td>' + escapeHtml(row.destinatario) + '</td>' +
                        '<td>' + escapeHtml(row.telefono) + '</td>' +
                        '<td>' + escapeHtml(row.ciudad) + '</td>' +
                        '<td>' + escapeHtml(row.zona) + '</td>' +
                        '<td>' + escapeHtml(row.peso) + '</td>' +
                        '<td>' + escapeHtml(row.estado) + '</td>' +
                        '<td>' + escapeHtml(row.asignado_a) + '</td>' +
                        '<td>' + escapeHtml(row.intento) + '</td>' +
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '<td>' + actionHtml + '</td>' +
                        '</tr>';
                }).join('');

                rows.forEach(function(row) {
                    const key = row.tipo_paquete + '-' + row.id;
                    if (selectedItems[key]) {
                        selectedItems[key] = {
                            id: Number(row.id),
                            tipo_paquete: row.tipo_paquete,
                            codigo: row.codigo,
                            peso: row.peso,
                            precio: row.precio
                        };
                    }
                });

                updateSelectAllState();
                refreshSelectedCount();
            }

            function updatePagination(meta) {
                pageIndicator.textContent = 'Pagina ' + meta.page + ' de ' + meta.last_page;
                if (meta.page <= 1) prevItem.classList.add('disabled');
                else prevItem.classList.remove('disabled');
                if (meta.page >= meta.last_page) nextItem.classList.add('disabled');
                else nextItem.classList.remove('disabled');
            }

            async function loadPage(page) {
                setLoading();
                try {
                    const params = new URLSearchParams({
                        page: String(page),
                        per_page: String(perPage),
                    });
                    if (currentCodigoFilter !== '') {
                        params.set('codigo', currentCodigoFilter);
                    }

                    const url = currentEndpoint + '?' + params.toString();
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('Request failed');
                    const payload = await response.json();
                    const meta = payload.meta || { page: 1, last_page: 1 };
                    currentPage = meta.page;
                    renderRows(payload.data || []);
                    updatePagination(meta);
                } catch (e) {
                    setError();
                }
            }

            function resetSelection() {
                Object.keys(selectedItems).forEach(function(k) { delete selectedItems[k]; });
                selectAll.checked = false;
                refreshSelectedCount();
            }

            body.addEventListener('change', function(e) {
                const target = e.target;
                if (!target.classList.contains('row-check')) return;

                const key = target.getAttribute('data-key');
                const parts = key ? key.split('-') : [];
                if (parts.length !== 2) return;

                const tipo_paquete = parts[0];
                const id = Number(parts[1]);

                if (target.checked) {
                    const row = target.closest('tr');
                    if (!row) return;
                    const cells = row.querySelectorAll('td');
                    selectedItems[key] = {
                        id: id,
                        tipo_paquete: tipo_paquete,
                        codigo: cells[2] ? cells[2].textContent.trim() : '',
                        peso: cells[7] ? cells[7].textContent.trim() : '',
                        precio: 0
                    };
                } else {
                    delete selectedItems[key];
                }

                refreshSelectedCount();
                updateSelectAllState();
            });

            selectAll.addEventListener('change', function() {
                const checkboxes = body.querySelectorAll('.row-check');
                checkboxes.forEach(function(cb) {
                    if (cb.checked !== selectAll.checked) {
                        cb.checked = selectAll.checked;
                        cb.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                });
            });

            if (canCarteroGuide && btnOpenGuiaModal && guiaForm) {
                btnOpenGuiaModal.addEventListener('click', function() {
                    if (currentModeLabel !== 'CARTERO') {
                        showMessage('Para mandar provincia, vuelve a la bandeja CARTERO.', 'danger');
                        return;
                    }
                    if (Object.keys(selectedItems).length === 0) {
                        showMessage('Selecciona al menos un paquete.', 'danger');
                        return;
                    }
                    refreshSelectedCount();
                    $('#guiaModal').modal('show');
                });

                guiaForm.addEventListener('submit', async function(e) {
                    e.preventDefault();

                const items = Object.values(selectedItems).map(function(item) {
                    return { id: item.id, tipo_paquete: item.tipo_paquete };
                });

                if (!items.length) {
                    showMessage('Selecciona al menos un paquete.', 'danger');
                    return;
                }

                const payload = {
                    transportadora: guiaTransportadora.value.trim(),
                    provincia: guiaProvincia.value.trim(),
                    factura: guiaFactura.value.trim(),
                    peso_total: guiaPesoTotal.value.trim() === '' ? null : guiaPesoTotal.value.trim(),
                    precio_total: guiaPrecioTotal.value.trim() === '' ? null : guiaPrecioTotal.value.trim(),
                    items: items
                };

                if (!payload.transportadora || !payload.provincia) {
                    showMessage('Completa transportadora y provincia.', 'danger');
                    return;
                }

                btnGuardarGuia.disabled = true;

                try {
                    const response = await fetch('{{ route('api.carteros.registrar-guia') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(payload)
                    });

                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'No se pudo registrar la guia.');
                    }

                    showMessage(
                        'Guia ' + data.data.guia + ' registrada con ' + data.data.total_registros + ' codigo(s).',
                        'success'
                    );

                    Object.keys(selectedItems).forEach(function(k) { delete selectedItems[k]; });
                    guiaForm.reset();
                    refreshSelectedCount();
                    updateSelectAllState();
                    $('#guiaModal').modal('hide');
                    loadPage(currentPage);
                } catch (err) {
                    showMessage(err.message || 'Error al registrar la guia.', 'danger');
                } finally {
                    btnGuardarGuia.disabled = false;
                }
                });
            }

            if (canCarteroProvince && btnShowProvincia && btnShowCartero) {
                btnShowProvincia.addEventListener('click', function() {
                    currentEndpoint = '{{ route('api.carteros.provincia') }}';
                    currentModeLabel = 'PROVINCIA';
                    bandejaChip.textContent = 'Mi bandeja PROVINCIA';
                    btnShowProvincia.style.display = 'none';
                    btnShowCartero.style.display = '';
                    if (btnOpenGuiaModal) {
                        btnOpenGuiaModal.disabled = true;
                    }
                    resetSelection();
                    loadPage(1);
                });

                btnShowCartero.addEventListener('click', function() {
                    currentEndpoint = '{{ route('api.carteros.cartero') }}';
                    currentModeLabel = 'CARTERO';
                    bandejaChip.textContent = 'Mi bandeja CARTERO';
                    btnShowCartero.style.display = 'none';
                    btnShowProvincia.style.display = '';
                    if (btnOpenGuiaModal) {
                        btnOpenGuiaModal.disabled = false;
                    }
                    resetSelection();
                    loadPage(1);
                });
            }

            function applyCodigoSearch() {
                currentCodigoFilter = String(codigoSearchInput ? codigoSearchInput.value : '').trim();
                resetSelection();
                loadPage(1);
            }

            if (btnCodigoSearch) {
                btnCodigoSearch.addEventListener('click', function() {
                    applyCodigoSearch();
                });
            }

            if (codigoSearchInput) {
                codigoSearchInput.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        applyCodigoSearch();
                    }
                });
            }

            if (btnCodigoClear) {
                btnCodigoClear.addEventListener('click', function() {
                    if (codigoSearchInput) {
                        codigoSearchInput.value = '';
                    }
                    currentCodigoFilter = '';
                    resetSelection();
                    loadPage(1);
                });
            }

            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!prevItem.classList.contains('disabled')) loadPage(currentPage - 1);
            });

            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!nextItem.classList.contains('disabled')) loadPage(currentPage + 1);
            });

            refreshSelectedCount();
            loadPage(1);
        })();
    </script>
@endsection

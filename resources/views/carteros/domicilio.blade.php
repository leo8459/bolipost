@extends('adminlte::page')
@section('title', 'Carteros - Domicilio')
@section('template_title')
    Carteros - Domicilio
@endsection

@section('content')
    <div class="carteros-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="domicilio-header-top">
                    <div>
                        <h3 class="card-title mb-1">Paquetes en Estado ENTREGADO</h3>
                        <div class="domicilio-header-subtitle">Consulta la bandeja final de entregas realizadas con evidencia y datos de recepcion.</div>
                    </div>
                    <span class="carteros-chip">Entregados</span>
                </div>
            </div>
            <div class="card-body">
                <div class="domicilio-shell">
                    <div class="domicilio-toolbar">
                        <div>
                            <div class="domicilio-toolbar-title">Registro de entregas</div>
                            <div class="domicilio-toolbar-subtitle">Historial consolidado de paquetes entregados por carteros.</div>
                        </div>
                        <div id="domicilio-selected-count" class="domicilio-selected-count">0 seleccionados</div>
                    </div>

                    <div class="domicilio-filters">
                        <input
                            id="domicilio-nombre"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por destinatario, cartero o recibido por"
                        >
                        <input id="domicilio-fecha-inicio" type="date" class="form-control" aria-label="Fecha inicio">
                        <input id="domicilio-fecha-fin" type="date" class="form-control" aria-label="Fecha fin">
                        <button id="domicilio-search-btn" type="button" class="btn btn-carteros-secondary">Buscar</button>
                        <button id="domicilio-clear-btn" type="button" class="btn btn-carteros-clear">Limpiar</button>
                    </div>

                    <div class="table-responsive domicilio-table-wrap">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="domicilio-check-cell">
                                        <input id="domicilio-select-page" type="checkbox" aria-label="Seleccionar pagina">
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
                                    <th>Recibido por</th>
                                    <th>Descripcion</th>
                                    <th>Imagen</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-domicilio-body">
                                <tr>
                                    <td colspan="15" class="text-center py-4">Cargando datos...</td>
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
@endsection

@section('css')
    @include('carteros.partials.theme')
    <style>
        .card-carteros .card-header .card-title {
            float: none;
            display: block;
        }

        .domicilio-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
        }

        .domicilio-header-subtitle {
            display: block;
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.92rem;
            line-height: 1.45;
            max-width: 560px;
        }

        .domicilio-shell {
            border: 1px solid #e4e8f2;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
        }

        .domicilio-toolbar {
            padding: 16px 18px;
            border-bottom: 1px solid #e4e8f2;
            background: linear-gradient(180deg, #fbfcff 0%, #f7faff 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
        }

        .domicilio-toolbar-title {
            color: var(--carteros-primary);
            font-size: 1rem;
            font-weight: 800;
        }

        .domicilio-toolbar-subtitle {
            color: #5e6b86;
            font-size: 0.85rem;
            margin-top: 2px;
        }

        .domicilio-selected-count {
            background: rgba(32, 83, 154, 0.1);
            color: var(--carteros-primary);
            border: 1px solid rgba(32, 83, 154, 0.18);
            border-radius: 999px;
            padding: 7px 12px;
            font-size: 0.86rem;
            font-weight: 800;
            white-space: nowrap;
        }

        .domicilio-filters {
            padding: 14px 18px;
            display: grid;
            grid-template-columns: minmax(220px, 1fr) 170px 170px auto auto;
            gap: 10px;
            border-bottom: 1px solid #e4e8f2;
            background: #fff;
        }

        .domicilio-filters .form-control,
        .domicilio-filters .btn {
            min-height: 42px;
            border-radius: 10px;
            font-weight: 700;
        }

        .domicilio-filters .form-control {
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .domicilio-filters .form-control:focus {
            border-color: var(--carteros-primary);
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
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

        .domicilio-check-cell {
            width: 42px;
            text-align: center;
        }

        .domicilio-check-cell input {
            width: 17px;
            height: 17px;
            cursor: pointer;
        }

        .domicilio-table-wrap .table {
            margin-bottom: 0;
        }

        .domicilio-table-wrap .table tbody td {
            border-top: 1px solid rgba(32, 83, 154, 0.08);
            vertical-align: middle;
        }

        .domicilio-table-wrap .btn {
            border-radius: 10px;
            font-weight: 700;
        }

        @media (max-width: 991.98px) {
            .domicilio-filters {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .domicilio-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('js')
    <script>
        (function() {
            let currentPage = 1;
            const perPage = 25;
            const selectedRows = new Set();
            let currentFilters = {
                nombre: '',
                fechaInicio: '',
                fechaFin: ''
            };
            let visibleKeys = [];
            let filteredTotal = 0;

            const body = document.getElementById('tabla-domicilio-body');
            const pageIndicator = document.getElementById('page-indicator');
            const prevItem = document.getElementById('prev-page-item');
            const nextItem = document.getElementById('next-page-item');
            const prevLink = document.getElementById('prev-page-link');
            const nextLink = document.getElementById('next-page-link');
            const nombreInput = document.getElementById('domicilio-nombre');
            const fechaInicioInput = document.getElementById('domicilio-fecha-inicio');
            const fechaFinInput = document.getElementById('domicilio-fecha-fin');
            const searchBtn = document.getElementById('domicilio-search-btn');
            const clearBtn = document.getElementById('domicilio-clear-btn');
            const selectedCount = document.getElementById('domicilio-selected-count');
            const selectPage = document.getElementById('domicilio-select-page');

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
                body.innerHTML = '<tr><td colspan="15" class="text-center py-4">Cargando datos...</td></tr>';
                visibleKeys = [];
                updateSelectionUi();
            }

            function setError() {
                body.innerHTML = '<tr><td colspan="15" class="text-center text-danger py-4">Error cargando datos.</td></tr>';
                visibleKeys = [];
                updateSelectionUi();
            }

            function rowKey(row) {
                return String(row.tipo_paquete || '') + ':' + String(row.id || '');
            }

            function updateSelectionUi() {
                const hasFilters = currentFilters.nombre !== '' || currentFilters.fechaInicio !== '' || currentFilters.fechaFin !== '';
                const entregadosText = filteredTotal + (filteredTotal === 1 ? ' entregado' : ' entregados');
                const filterText = hasFilters ? ' filtrados' : '';
                const selectedText = selectedRows.size > 0
                    ? ' | ' + selectedRows.size + (selectedRows.size === 1 ? ' seleccionado' : ' seleccionados')
                    : '';

                selectedCount.textContent = entregadosText + filterText + selectedText;
                if (!selectPage) {
                    return;
                }

                const visibleSelected = visibleKeys.filter(function(key) {
                    return selectedRows.has(key);
                }).length;

                selectPage.checked = visibleKeys.length > 0 && visibleSelected === visibleKeys.length;
                selectPage.indeterminate = visibleSelected > 0 && visibleSelected < visibleKeys.length;
            }

            function renderRows(rows) {
                if (!rows.length) {
                    visibleKeys = [];
                    body.innerHTML = '<tr><td colspan="15" class="text-center py-4">No hay paquetes en estado ENTREGADO.</td></tr>';
                    updateSelectionUi();
                    return;
                }

                visibleKeys = rows.map(rowKey);
                body.innerHTML = rows.map(function(row) {
                    const key = rowKey(row);
                    const checked = selectedRows.has(key) ? ' checked' : '';
                    const imageHtml = row.imagen_url
                        ? '<a href="' + escapeHtml(row.imagen_url) + '" target="_blank" class="btn btn-sm btn-outline-secondary">Ver foto</a>'
                        : '<span class="text-muted small">Sin foto</span>';
                    return '<tr>' +
                        '<td class="domicilio-check-cell"><input type="checkbox" class="domicilio-row-check" data-key="' + escapeHtml(key) + '"' + checked + ' aria-label="Seleccionar paquete"></td>' +
                        '<td>' + escapeHtml(row.tipo_paquete) + '</td>' +
                        '<td>' + escapeHtml(row.codigo) + '</td>' +
                        '<td>' + escapeHtml(row.destinatario) + '</td>' +
                        '<td>' + escapeHtml(row.telefono) + '</td>' +
                        '<td>' + escapeHtml(row.ciudad) + '</td>' +
                        '<td>' + escapeHtml(row.zona) + '</td>' +
                        '<td>' + escapeHtml(row.peso) + '</td>' +
                        '<td>' + escapeHtml(row.estado_id) + '</td>' +
                        '<td>' + escapeHtml(row.asignado_a) + '</td>' +
                        '<td>' + escapeHtml(row.intento) + '</td>' +
                        '<td>' + escapeHtml(row.recibido_por) + '</td>' +
                        '<td>' + escapeHtml(row.descripcion) + '</td>' +
                        '<td>' + imageHtml + '</td>' +
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '</tr>';
                }).join('');
                updateSelectionUi();
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
                        page: page,
                        per_page: perPage
                    });

                    if (currentFilters.nombre !== '') {
                        params.set('nombre', currentFilters.nombre);
                    }
                    if (currentFilters.fechaInicio !== '') {
                        params.set('fecha_inicio', currentFilters.fechaInicio);
                    }
                    if (currentFilters.fechaFin !== '') {
                        params.set('fecha_fin', currentFilters.fechaFin);
                    }

                    const url = '{{ route('api.carteros.domicilio') }}?' + params.toString();
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('Request failed');
                    const payload = await response.json();
                    const meta = payload.meta || { page: 1, last_page: 1 };
                    filteredTotal = Number(meta.total || 0);
                    currentPage = meta.page;
                    renderRows(payload.data || []);
                    updatePagination(meta);
                } catch (e) {
                    setError();
                }
            }

            function applyFilters() {
                currentFilters = {
                    nombre: (nombreInput.value || '').trim(),
                    fechaInicio: fechaInicioInput.value || '',
                    fechaFin: fechaFinInput.value || ''
                };
                loadPage(1);
            }

            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!prevItem.classList.contains('disabled')) loadPage(currentPage - 1);
            });

            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!nextItem.classList.contains('disabled')) loadPage(currentPage + 1);
            });

            searchBtn.addEventListener('click', applyFilters);

            clearBtn.addEventListener('click', function() {
                nombreInput.value = '';
                fechaInicioInput.value = '';
                fechaFinInput.value = '';
                currentFilters = {
                    nombre: '',
                    fechaInicio: '',
                    fechaFin: ''
                };
                loadPage(1);
            });

            nombreInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyFilters();
                }
            });

            fechaInicioInput.addEventListener('change', applyFilters);
            fechaFinInput.addEventListener('change', applyFilters);

            selectPage.addEventListener('change', function() {
                visibleKeys.forEach(function(key) {
                    if (selectPage.checked) {
                        selectedRows.add(key);
                    } else {
                        selectedRows.delete(key);
                    }
                });

                body.querySelectorAll('.domicilio-row-check').forEach(function(input) {
                    input.checked = selectPage.checked;
                });

                updateSelectionUi();
            });

            body.addEventListener('change', function(e) {
                const input = e.target.closest('.domicilio-row-check');
                if (!input) {
                    return;
                }

                const key = input.getAttribute('data-key');
                if (input.checked) {
                    selectedRows.add(key);
                } else {
                    selectedRows.delete(key);
                }

                updateSelectionUi();
            });

            loadPage(1);
        })();
    </script>
@endsection

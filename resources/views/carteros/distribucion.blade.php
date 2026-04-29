@extends('adminlte::page')
@section('title', 'Carteros - Distribucion')
@section('template_title')
    Carteros - Distribucion
@endsection

@section('content')
    @php
        $canAssignDistribucion = auth()->user()?->can('feature.carteros.distribucion.assign') ?? false;
        $canSelfAssignDistribucion = $canAssignDistribucion || (auth()->user()?->can('feature.carteros.distribucion.selfassign') ?? false);
    @endphp
    <div class="carteros-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0">Distribucion de Paquetes</h3>
                    <span class="carteros-chip">Control operativo</span>
                </div>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-lg-8 mb-3 mb-lg-0">
                        <div class="distribution-pane">
                            <div class="distribution-toolbar">
                                <div class="row">
                                    <div class="col-md-12">
                                        <label for="codigo-search" class="mb-1">Buscar por codigo</label>
                                        <input type="text" id="codigo-search" class="form-control" placeholder="Escribe codigo y presiona Enter">
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive distribution-results-hidden" aria-hidden="true">
                                <table class="table table-striped table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 40px;"><input type="checkbox" id="check-all"></th>
                                            <th>Tipo</th>
                                            <th>Codigo</th>
                                            <th>Destinatario</th>
                                            <th>Telefono</th>
                                            <th>Ciudad</th>
                                            <th>Zona / Direccion</th>
                                            <th>Peso</th>
                                            <th>Estado</th>
                                            <th>Asignado a</th>
                                            <th>Fecha</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabla-distribucion-body">
                                        <tr>
                                            <td colspan="11" class="text-center py-4">Cargando datos...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="distribution-footer distribution-results-hidden" aria-hidden="true">
                                <div class="carteros-meta" id="page-summary">Selecciona paquetes para preparar la asignacion.</div>
                                <ul class="pagination pagination-sm m-0">
                                    <li class="page-item" id="prev-page-item"><a class="page-link" href="#" id="prev-page-link">Anterior</a></li>
                                    <li class="page-item disabled"><span class="page-link" id="page-indicator">Pagina 1 de 1</span></li>
                                    <li class="page-item" id="next-page-item"><a class="page-link" href="#" id="next-page-link">Siguiente</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="selection-pane">
                            <div class="selection-pane-header">
                                <div>
                                    <div class="selection-pane-title">Por Asignar</div>
                                    <div class="selection-pane-subtitle">Prelista de paquetes seleccionados</div>
                                </div>
                                <span class="selection-count-badge" id="selected-count-badge">0</span>
                            </div>

                            @if ($canAssignDistribucion)
                                <div class="form-group mb-2">
                                    <label for="assignment-mode" class="mb-1">Tipo de asignacion</label>
                                    <select id="assignment-mode" class="form-control">
                                        <option value="auto">Autoasignarme</option>
                                        <option value="user">Asignar a usuario</option>
                                    </select>
                                </div>
                                <div class="form-group mb-2">
                                    <label for="assignee-user" class="mb-1">Usuario destino</label>
                                    <select id="assignee-user" class="form-control" disabled>
                                        <option value="">Selecciona usuario</option>
                                    </select>
                                </div>
                            @elseif ($canSelfAssignDistribucion)
                                <div class="selection-target mb-2">
                                    Modo disponible: Autoasignarme
                                </div>
                            @endif

                            <div class="selection-target" id="selection-target">
                                Destino actual: {{ auth()->user()->name }}
                            </div>

                            <div class="selection-summary" id="selected-summary">No hay paquetes seleccionados.</div>

                            <div class="selection-type-grid" id="selected-type-summary">
                                <div class="selection-type-card"><strong>EMS</strong><span>0</span></div>
                                <div class="selection-type-card"><strong>CERTI</strong><span>0</span></div>
                                <div class="selection-type-card"><strong>ORDI</strong><span>0</span></div>
                                <div class="selection-type-card"><strong>CONTRATO</strong><span>0</span></div>
                                <div class="selection-type-card"><strong>SOLICITUD</strong><span>0</span></div>
                            </div>

                            <div class="selection-actions mb-3">
                                <button type="button" id="btn-clear-selection" class="btn btn-outline-secondary btn-sm">Limpiar lista</button>
                                <a href="#" id="btn-open-report" class="btn btn-outline-primary btn-sm disabled" target="_blank" aria-disabled="true">Abrir reporte</a>
                            </div>

                            <div id="asignacion-msg" class="small mb-2"></div>

                            <div class="selection-list" id="selected-list">
                                <div class="selection-empty">Aun no seleccionaste paquetes.</div>
                            </div>

                            @if ($canSelfAssignDistribucion)
                                <button id="btn-asignar" class="btn btn-carteros-primary btn-block mt-3">Asignar seleccionados</button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    @include('carteros.partials.theme')
    <style>
        .distribution-pane, .selection-pane { border: 1px solid #e4e8f2; border-radius: 14px; background: #fff; overflow: hidden; }
        .distribution-toolbar, .selection-pane { padding: 14px; }
        .distribution-toolbar {
            border-bottom: 1px solid #e4e8f2;
            background: #fbfcff;
        }
        .distribution-toolbar label {
            color: #0f172a;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .distribution-toolbar .form-control,
        .selection-pane .form-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: #cbd5e1;
            box-shadow: none;
        }
        .distribution-toolbar .form-control:focus,
        .selection-pane .form-control:focus {
            border-color: var(--carteros-primary);
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }
        .distribution-pane .table-responsive {
            margin-bottom: 0;
        }
        .distribution-results-hidden {
            display: none;
        }
        .distribution-pane .table {
            margin-bottom: 0;
        }
        .distribution-pane .table thead th {
            background: #edf1fb;
            color: var(--carteros-primary);
            border-bottom: 2px solid rgba(32, 83, 154, 0.14);
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            white-space: nowrap;
        }
        .distribution-pane .table tbody td {
            border-top: 1px solid rgba(32, 83, 154, 0.10);
            vertical-align: middle;
        }
        .distribution-footer { border-top: 1px solid #e4e8f2; padding: 12px 14px; background: #f8faff; display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
        .selection-pane-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px; }
        .selection-pane-title { color: var(--carteros-primary); font-size: 1.05rem; font-weight: 700; }
        .selection-pane-subtitle { color: #6b7280; font-size: 0.84rem; }
        .selection-count-badge { background: rgba(32, 83, 154, 0.12); color: var(--carteros-primary); border-radius: 999px; font-weight: 700; padding: 0.35rem 0.7rem; min-width: 42px; text-align: center; }
        .selection-target { background: #f8faff; border: 1px dashed #cfd9ee; border-radius: 10px; color: #41506f; padding: 10px 12px; margin-bottom: 10px; font-size: 0.9rem; }
        .selection-summary { color: #5e6b86; font-size: 0.84rem; margin-bottom: 10px; }
        .selection-type-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-bottom: 12px; }
        .selection-type-card { background: #f8faff; border: 1px solid #e4e8f2; border-radius: 10px; padding: 8px 10px; display: flex; justify-content: space-between; align-items: center; color: #334155; font-size: 0.82rem; }
        .selection-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .selection-list { max-height: 540px; overflow-y: auto; padding-right: 4px; }
        .selection-item { border: 1px solid #e4e8f2; border-radius: 12px; padding: 10px 12px; margin-bottom: 10px; background: #fbfcff; }
        .selection-item-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; margin-bottom: 6px; }
        .selection-item-code { color: var(--carteros-primary); font-weight: 700; font-size: 0.92rem; }
        .selection-item-meta { color: #5f6d87; font-size: 0.8rem; line-height: 1.35; }
        .selection-item-remove { border: 0; background: transparent; color: #b42318; font-size: 0.82rem; font-weight: 700; padding: 0; }
        .selection-empty { border: 1px dashed #d7dfef; border-radius: 12px; padding: 20px 14px; text-align: center; color: #6b7280; background: #f9fbff; }
        .selection-actions .btn {
            min-height: 38px;
            border-radius: 10px;
            font-weight: 700;
        }
        .selection-pane .btn-carteros-primary {
            min-height: 44px;
            border-radius: 12px;
            font-weight: 800;
        }
    </style>
@endsection

@section('js')
    <script>
        (function() {
            let currentPage = 1;
            const perPage = 25;
            let lastPage = 1;
            let selectedItems = {};
            let lastReportUrl = null;

            const body = document.getElementById('tabla-distribucion-body');
            const pageIndicator = document.getElementById('page-indicator');
            const pageSummary = document.getElementById('page-summary');
            const prevItem = document.getElementById('prev-page-item');
            const nextItem = document.getElementById('next-page-item');
            const prevLink = document.getElementById('prev-page-link');
            const nextLink = document.getElementById('next-page-link');
            const codigoSearch = document.getElementById('codigo-search');
            const assignmentMode = document.getElementById('assignment-mode');
            const assigneeUser = document.getElementById('assignee-user');
            const assignButton = document.getElementById('btn-asignar');
            const assignMsg = document.getElementById('asignacion-msg');
            const checkAll = document.getElementById('check-all');
            const selectedList = document.getElementById('selected-list');
            const selectedSummary = document.getElementById('selected-summary');
            const selectedCountBadge = document.getElementById('selected-count-badge');
            const selectionTarget = document.getElementById('selection-target');
            const selectedTypeSummary = document.getElementById('selected-type-summary');
            const clearSelectionButton = document.getElementById('btn-clear-selection');
            const openReportButton = document.getElementById('btn-open-report');
            const csrfToken = '{{ csrf_token() }}';
            const canAssignDistribucion = @json($canAssignDistribucion);
            const canSelfAssignDistribucion = @json($canSelfAssignDistribucion);
            const currentUserName = @json(auth()->user()->name);

            function escapeHtml(value) {
                if (value === null || value === undefined) return '';
                return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
            }

            function selectionKey(row) { return row.tipo_paquete + ':' + row.id; }

            function buildSelectedItem(row) {
                return {
                    id: parseInt(row.id, 10),
                    tipo_paquete: row.tipo_paquete,
                    codigo: row.codigo || '',
                    destinatario: row.destinatario || '',
                    telefono: row.telefono || '',
                    ciudad: row.ciudad || '',
                    zona: row.zona || row.direccion || '',
                    peso: row.peso || '',
                    estado: row.estado || row.estado_id || '',
                    created_at: row.created_at || ''
                };
            }

            function formatWeight(value) {
                if (value === null || value === undefined || value === '') return '-';
                return String(value);
            }

            function getAssigneeName() {
                if (!canSelfAssignDistribucion) return currentUserName;
                if (!canAssignDistribucion || !assignmentMode) return currentUserName;
                if (assignmentMode.value === 'user' && assigneeUser && assigneeUser.value) {
                    return assigneeUser.options[assigneeUser.selectedIndex]?.text || 'Usuario seleccionado';
                }
                return currentUserName;
            }

            function updateReportLink(url) {
                lastReportUrl = url || null;
                if (!openReportButton) return;
                if (lastReportUrl) {
                    openReportButton.href = lastReportUrl;
                    openReportButton.classList.remove('disabled');
                    openReportButton.removeAttribute('aria-disabled');
                } else {
                    openReportButton.href = '#';
                    openReportButton.classList.add('disabled');
                    openReportButton.setAttribute('aria-disabled', 'true');
                }
            }

            function renderSelectedSummary() {
                const items = Object.values(selectedItems);
                const counts = { EMS: 0, CERTI: 0, ORDI: 0, CONTRATO: 0, SOLICITUD: 0 };
                items.forEach(function(item) { if (counts[item.tipo_paquete] !== undefined) counts[item.tipo_paquete] += 1; });
                selectedCountBadge.textContent = String(items.length);
                selectionTarget.textContent = 'Destino actual: ' + getAssigneeName();
                selectedSummary.textContent = items.length ? items.length + ' paquete(s) listos para asignar.' : 'No hay paquetes seleccionados.';
                selectedTypeSummary.innerHTML = ['EMS', 'CERTI', 'ORDI', 'CONTRATO', 'SOLICITUD'].map(function(type) {
                    return '<div class="selection-type-card"><strong>' + type + '</strong><span>' + counts[type] + '</span></div>';
                }).join('');
            }

            function renderSelectedList() {
                const items = Object.values(selectedItems).sort(function(a, b) {
                    return String(a.codigo).localeCompare(String(b.codigo));
                });
                renderSelectedSummary();
                if (!items.length) {
                    selectedList.innerHTML = '<div class="selection-empty">Aun no seleccionaste paquetes.</div>';
                    return;
                }
                selectedList.innerHTML = items.map(function(item) {
                    const key = item.tipo_paquete + ':' + item.id;
                    return '<div class="selection-item">' +
                        '<div class="selection-item-top"><span class="carteros-chip">' + escapeHtml(item.tipo_paquete) + '</span><button type="button" class="selection-item-remove" data-key="' + escapeHtml(key) + '">Quitar</button></div>' +
                        '<div class="selection-item-code">' + escapeHtml(item.codigo) + '</div>' +
                        '<div class="selection-item-meta"><strong>Destinatario:</strong> ' + escapeHtml(item.destinatario || '-') + '</div>' +
                        '<div class="selection-item-meta"><strong>Ubicacion:</strong> ' + escapeHtml(item.ciudad || '-') + ' / ' + escapeHtml(item.zona || '-') + '</div>' +
                        '<div class="selection-item-meta"><strong>Peso:</strong> ' + escapeHtml(formatWeight(item.peso)) + ' | <strong>Estado:</strong> ' + escapeHtml(item.estado || '-') + '</div>' +
                        '</div>';
                }).join('');
            }

            function setLoading() {
                body.innerHTML = '<tr><td colspan="11" class="text-center py-4">Cargando datos...</td></tr>';
            }

            function setError() {
                body.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">Error cargando datos desde la API.</td></tr>';
            }

            function setEmpty() {
                body.innerHTML = '<tr><td colspan="11" class="text-center py-4">No hay paquetes para mostrar.</td></tr>';
                checkAll.checked = false;
            }

            function showMessage(text, type) {
                assignMsg.className = 'small text-' + type;
                assignMsg.innerHTML = text;
            }

            function updatePagination(meta) {
                pageIndicator.textContent = 'Pagina ' + meta.page + ' de ' + meta.last_page;
                lastPage = meta.last_page;
                pageSummary.textContent = 'Mostrando ' + Math.min(meta.per_page, meta.total) + ' registros por pagina. Total disponible: ' + meta.total + '.';
                if (meta.page <= 1) prevItem.classList.add('disabled'); else prevItem.classList.remove('disabled');
                if (meta.page >= meta.last_page) nextItem.classList.add('disabled'); else nextItem.classList.remove('disabled');
            }

            function updateCheckAllState() {
                const checks = Array.from(document.querySelectorAll('.row-check'));
                if (!checks.length) {
                    checkAll.checked = false;
                    return;
                }
                checkAll.checked = checks.every(function(chk) { return chk.checked; });
            }

            function bindRowChecks() {
                document.querySelectorAll('.row-check').forEach(function(chk) {
                    chk.addEventListener('change', function() {
                        const row = {
                            id: parseInt(chk.dataset.id, 10),
                            tipo_paquete: chk.dataset.tipo,
                            codigo: chk.dataset.codigo,
                            destinatario: chk.dataset.destinatario,
                            telefono: chk.dataset.telefono,
                            ciudad: chk.dataset.ciudad,
                            zona: chk.dataset.zona,
                            peso: chk.dataset.peso,
                            estado: chk.dataset.estado,
                            created_at: chk.dataset.createdAt
                        };
                        const item = buildSelectedItem(row);
                        const key = selectionKey(item);

                        if (chk.checked) selectedItems[key] = item;
                        else delete selectedItems[key];

                        renderSelectedList();
                        updateCheckAllState();
                    });
                });
            }

            function renderRows(rows, autoSelect) {
                if (!rows.length) {
                    setEmpty();
                    return;
                }

                body.innerHTML = rows.map(function(row) {
                    const key = selectionKey(row);
                    const item = buildSelectedItem(row);
                    const isChecked = autoSelect || Boolean(selectedItems[key]);
                    if (isChecked) selectedItems[key] = item;

                    return '<tr>' +
                        '<td><input type="checkbox" class="row-check" data-id="' + item.id + '" data-key="' + escapeHtml(key) + '" data-tipo="' + escapeHtml(item.tipo_paquete) + '" data-codigo="' + escapeHtml(item.codigo) + '" data-destinatario="' + escapeHtml(item.destinatario) + '" data-telefono="' + escapeHtml(item.telefono) + '" data-ciudad="' + escapeHtml(item.ciudad) + '" data-zona="' + escapeHtml(item.zona) + '" data-peso="' + escapeHtml(item.peso) + '" data-estado="' + escapeHtml(item.estado) + '" data-created-at="' + escapeHtml(item.created_at) + '" ' + (isChecked ? 'checked' : '') + '></td>' +
                        '<td>' + escapeHtml(item.tipo_paquete) + '</td>' +
                        '<td>' + escapeHtml(item.codigo) + '</td>' +
                        '<td>' + escapeHtml(item.destinatario) + '</td>' +
                        '<td>' + escapeHtml(item.telefono) + '</td>' +
                        '<td>' + escapeHtml(item.ciudad) + '</td>' +
                        '<td>' + escapeHtml(item.zona) + '</td>' +
                        '<td>' + escapeHtml(formatWeight(item.peso)) + '</td>' +
                        '<td>' + escapeHtml(item.estado || row.estado_id || '') + '</td>' +
                        '<td>' + escapeHtml(row.asignado_a || '') + '</td>' +
                        '<td>' + escapeHtml(item.created_at) + '</td>' +
                        '</tr>';
                }).join('');

                bindRowChecks();
                updateCheckAllState();
                renderSelectedList();
            }

            async function loadUsers() {
                if (!canAssignDistribucion || !assigneeUser) return;

                try {
                    const response = await fetch('{{ route('api.carteros.users') }}', { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) return;

                    const payload = await response.json();
                    const users = payload.data || [];
                    assigneeUser.innerHTML = '<option value="">Selecciona usuario</option>' + users.map(function(u) {
                        return '<option value="' + u.id + '">' + escapeHtml(u.name) + '</option>';
                    }).join('');
                } catch (e) {
                }
            }

            async function loadPage(page, autoSelect) {
                setLoading();
                showMessage('', 'muted');

                try {
                    const params = new URLSearchParams({ page: String(page), per_page: String(perPage) });
                    const response = await fetch('{{ route('api.carteros.distribucion') }}?' + params.toString(), {
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!response.ok) throw new Error('Request failed');

                    const payload = await response.json();
                    const meta = payload.meta || { page: 1, last_page: 1, total: 0, per_page: perPage };
                    currentPage = meta.page;
                    renderRows(payload.data || [], autoSelect === true);
                    updatePagination(meta);
                } catch (error) {
                    setError();
                }
            }

            async function searchAndSelectByCode(codigo) {
                const trimmed = codigo.trim();
                if (!trimmed) return;

                try {
                    const params = new URLSearchParams({ page: '1', per_page: '100', codigo: trimmed });
                    const response = await fetch('{{ route('api.carteros.distribucion') }}?' + params.toString(), {
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!response.ok) {
                        showMessage('No se pudo buscar por codigo.', 'danger');
                        return;
                    }

                    const payload = await response.json();
                    const rows = payload.data || [];

                    if (!rows.length) {
                        showMessage('No se encontro ese codigo.', 'warning');
                        return;
                    }

                    rows.forEach(function(row) {
                        const item = buildSelectedItem(row);
                        selectedItems[selectionKey(item)] = item;
                    });

                    showMessage('Codigo agregado a la prelista.', 'success');
                    renderSelectedList();
                    loadPage(currentPage, false);
                } catch (error) {
                    showMessage('Error al buscar por codigo.', 'danger');
                }
            }

            function clearSelection() {
                selectedItems = {};
                checkAll.checked = false;
                renderSelectedList();
                document.querySelectorAll('.row-check').forEach(function(chk) { chk.checked = false; });
                updateCheckAllState();
            }

            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!prevItem.classList.contains('disabled')) loadPage(currentPage - 1, false);
            });

            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!nextItem.classList.contains('disabled')) loadPage(currentPage + 1, false);
            });

            checkAll.addEventListener('change', function() {
                document.querySelectorAll('.row-check').forEach(function(chk) {
                    chk.checked = checkAll.checked;
                    chk.dispatchEvent(new Event('change'));
                });
            });

            codigoSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const value = codigoSearch.value;
                    codigoSearch.value = '';
                    searchAndSelectByCode(value);
                }
            });

            selectedList.addEventListener('click', function(e) {
                const btn = e.target.closest('.selection-item-remove');
                if (!btn) return;
                const key = btn.dataset.key;
                if (!key) return;

                delete selectedItems[key];
                const escapedKey = window.CSS && CSS.escape ? CSS.escape(key) : key.replace(/"/g, '\\"');
                const checkbox = document.querySelector('.row-check[data-key="' + escapedKey + '"]');
                if (checkbox) checkbox.checked = false;
                renderSelectedList();
                updateCheckAllState();
            });

            clearSelectionButton.addEventListener('click', function() {
                clearSelection();
                showMessage('Prelista limpiada.', 'muted');
            });

            if (openReportButton) {
                openReportButton.addEventListener('click', function(e) {
                    if (!lastReportUrl) e.preventDefault();
                });
            }

            if (canAssignDistribucion && assignmentMode && assigneeUser && assignButton) {
                assignmentMode.addEventListener('change', function() {
                    assigneeUser.disabled = assignmentMode.value !== 'user';
                    renderSelectedSummary();
                });

                assigneeUser.addEventListener('change', function() {
                    renderSelectedSummary();
                });

                assignButton.addEventListener('click', async function() {
                    const items = Object.values(selectedItems).map(function(item) {
                        return { id: item.id, tipo_paquete: item.tipo_paquete };
                    });

                    if (!items.length) {
                        showMessage('Selecciona al menos un paquete.', 'danger');
                        return;
                    }

                    const mode = assignmentMode.value;
                    const userId = assigneeUser.value;

                    if (mode === 'user' && !userId) {
                        showMessage('Selecciona un usuario para asignar.', 'danger');
                        return;
                    }

                    assignButton.disabled = true;
                    updateReportLink(null);
                    showMessage('Asignando paquetes y preparando reporte...', 'info');

                    try {
                        const response = await fetch('{{ route('api.carteros.asignar') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                assignment_mode: mode,
                                user_id: mode === 'user' ? parseInt(userId, 10) : null,
                                items: items
                            })
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            showMessage(payload.message || 'No se pudo asignar.', 'danger');
                            return;
                        }

                        updateReportLink(payload.report_url || null);
                        const reportLink = payload.report_url
                            ? ' <a href="' + escapeHtml(payload.report_url) + '" target="_blank">Abrir reporte PDF</a>'
                            : '';

                        showMessage((payload.message || 'Asignado correctamente.') + reportLink, 'success');
                        if (payload.report_url) {
                            window.open(payload.report_url, '_blank', 'noopener');
                        }
                        clearSelection();

                        if (currentPage > lastPage) currentPage = lastPage;
                        loadPage(currentPage, false);
                    } catch (error) {
                        showMessage('Error al asignar paquetes.', 'danger');
                    } finally {
                        assignButton.disabled = false;
                    }
                });
            }

            if (!canAssignDistribucion && canSelfAssignDistribucion && assignButton) {
                assignButton.addEventListener('click', async function() {
                    const items = Object.values(selectedItems).map(function(item) {
                        return { id: item.id, tipo_paquete: item.tipo_paquete };
                    });

                    if (!items.length) {
                        showMessage('Selecciona al menos un paquete.', 'danger');
                        return;
                    }

                    assignButton.disabled = true;
                    updateReportLink(null);
                    showMessage('Autoasignando paquetes y preparando reporte...', 'info');

                    try {
                        const response = await fetch('{{ route('api.carteros.asignar') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                assignment_mode: 'auto',
                                user_id: null,
                                items: items
                            })
                        });

                        const payload = await response.json();

                        if (!response.ok) {
                            showMessage(payload.message || 'No se pudo autoasignar.', 'danger');
                            return;
                        }

                        updateReportLink(payload.report_url || null);
                        const reportLink = payload.report_url
                            ? ' <a href="' + escapeHtml(payload.report_url) + '" target="_blank">Abrir reporte PDF</a>'
                            : '';

                        showMessage((payload.message || 'Autoasignado correctamente.') + reportLink, 'success');
                        if (payload.report_url) {
                            window.open(payload.report_url, '_blank', 'noopener');
                        }
                        clearSelection();
                        if (currentPage > lastPage) currentPage = lastPage;
                        loadPage(currentPage, false);
                    } catch (error) {
                        showMessage('Error al autoasignar paquetes.', 'danger');
                    } finally {
                        assignButton.disabled = false;
                    }
                });
            }

            renderSelectedList();
            loadUsers();
            loadPage(1, false);
        })();
    </script>
@endsection

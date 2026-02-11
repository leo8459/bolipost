@extends('adminlte::page')
@section('title', 'Carteros - Distribucion')
@section('template_title')
    Carteros - Distribucion
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">Distribucion de Paquetes</h3>
        </div>
        <div class="card-body border-bottom">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label for="codigo-search" class="mb-1">Buscar por codigo</label>
                    <input type="text" id="codigo-search" class="form-control" placeholder="Escribe codigo y presiona Enter">
                </div>
                <div class="col-md-3 mb-2">
                    <label for="assignment-mode" class="mb-1">Tipo de asignacion</label>
                    <select id="assignment-mode" class="form-control">
                        <option value="auto">Autoasignarme</option>
                        <option value="user">Asignar a usuario</option>
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label for="assignee-user" class="mb-1">Usuario</label>
                    <select id="assignee-user" class="form-control" disabled>
                        <option value="">Selecciona usuario</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2 d-flex align-items-end">
                    <button id="btn-asignar" class="btn btn-primary btn-block">Asignar</button>
                </div>
            </div>
            <div id="asignacion-msg" class="small"></div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="check-all">
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
@endsection

@section('js')
    <script>
        (function() {
            let currentPage = 1;
            const perPage = 25;
            let lastPage = 1;
            let selectedItems = {};

            const body = document.getElementById('tabla-distribucion-body');
            const pageIndicator = document.getElementById('page-indicator');
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
            const csrfToken = '{{ csrf_token() }}';

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
                body.innerHTML = '<tr><td colspan="11" class="text-center py-4">Cargando datos...</td></tr>';
            }

            function setError() {
                body.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">Error cargando datos desde la API.</td></tr>';
            }

            function setEmpty() {
                body.innerHTML = '<tr><td colspan="11" class="text-center py-4">No hay paquetes para mostrar.</td></tr>';
            }

            function selectionKey(row) {
                return row.tipo_paquete + ':' + row.id;
            }

            function renderRows(rows, autoSelect) {
                if (!rows.length) {
                    setEmpty();
                    checkAll.checked = false;
                    return;
                }

                body.innerHTML = rows.map(function(row) {
                    const key = selectionKey(row);
                    const isChecked = autoSelect || Boolean(selectedItems[key]);
                    if (isChecked) {
                        selectedItems[key] = {
                            id: row.id,
                            tipo_paquete: row.tipo_paquete
                        };
                    }

                    return '<tr>' +
                        '<td><input type="checkbox" class="row-check" data-id="' + row.id + '" data-tipo="' + escapeHtml(row.tipo_paquete) + '" ' + (isChecked ? 'checked' : '') + '></td>' +
                        '<td>' + escapeHtml(row.tipo_paquete) + '</td>' +
                        '<td>' + escapeHtml(row.codigo) + '</td>' +
                        '<td>' + escapeHtml(row.destinatario) + '</td>' +
                        '<td>' + escapeHtml(row.telefono) + '</td>' +
                        '<td>' + escapeHtml(row.ciudad) + '</td>' +
                        '<td>' + escapeHtml(row.zona) + '</td>' +
                        '<td>' + escapeHtml(row.peso) + '</td>' +
                        '<td>' + escapeHtml(row.estado_id) + '</td>' +
                        '<td>' + escapeHtml(row.asignado_a) + '</td>' +
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '</tr>';
                }).join('');

                bindRowChecks();
                updateCheckAllState();
            }

            function updatePagination(meta) {
                pageIndicator.textContent = 'Pagina ' + meta.page + ' de ' + meta.last_page;
                lastPage = meta.last_page;

                if (meta.page <= 1) {
                    prevItem.classList.add('disabled');
                } else {
                    prevItem.classList.remove('disabled');
                }

                if (meta.page >= meta.last_page) {
                    nextItem.classList.add('disabled');
                } else {
                    nextItem.classList.remove('disabled');
                }
            }

            function updateCheckAllState() {
                const checks = Array.from(document.querySelectorAll('.row-check'));
                if (!checks.length) {
                    checkAll.checked = false;
                    return;
                }
                checkAll.checked = checks.every(function(chk) {
                    return chk.checked;
                });
            }

            function bindRowChecks() {
                document.querySelectorAll('.row-check').forEach(function(chk) {
                    chk.addEventListener('change', function() {
                        const item = {
                            id: parseInt(chk.dataset.id, 10),
                            tipo_paquete: chk.dataset.tipo
                        };
                        const key = item.tipo_paquete + ':' + item.id;
                        if (chk.checked) {
                            selectedItems[key] = item;
                        } else {
                            delete selectedItems[key];
                        }
                        updateCheckAllState();
                    });
                });
            }

            function showMessage(text, type) {
                assignMsg.className = 'small text-' + type;
                assignMsg.textContent = text;
            }

            async function loadUsers() {
                try {
                    const response = await fetch('{{ route('api.carteros.users') }}', {
                        headers: { 'Accept': 'application/json' }
                    });
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
                    const params = new URLSearchParams({
                        page: String(page),
                        per_page: String(perPage)
                    });
                    const url = '{{ route('api.carteros.distribucion') }}' + '?' + params.toString();
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Request failed');
                    }

                    const payload = await response.json();
                    const meta = payload.meta || {
                        page: 1,
                        last_page: 1
                    };

                    currentPage = meta.page;
                    renderRows(payload.data || [], autoSelect === true);
                    updatePagination(meta);
                } catch (error) {
                    setError();
                }
            }

            async function searchAndSelectByCode(codigo) {
                const trimmed = codigo.trim();
                if (!trimmed) {
                    return;
                }

                try {
                    const params = new URLSearchParams({
                        page: '1',
                        per_page: '100',
                        codigo: trimmed
                    });
                    const response = await fetch('{{ route('api.carteros.distribucion') }}' + '?' + params.toString(), {
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
                        const key = selectionKey(row);
                        selectedItems[key] = {
                            id: row.id,
                            tipo_paquete: row.tipo_paquete
                        };
                    });

                    showMessage('Codigo agregado a la seleccion.', 'success');
                    loadPage(currentPage, false);
                } catch (error) {
                    showMessage('Error al buscar por codigo.', 'danger');
                }
            }

            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!prevItem.classList.contains('disabled')) {
                    loadPage(currentPage - 1, false);
                }
            });

            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!nextItem.classList.contains('disabled')) {
                    loadPage(currentPage + 1, false);
                }
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

            assignmentMode.addEventListener('change', function() {
                const useUser = assignmentMode.value === 'user';
                assigneeUser.disabled = !useUser;
            });

            assignButton.addEventListener('click', async function() {
                const items = Object.values(selectedItems);
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
                showMessage('Asignando...', 'info');

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
                        const errorText = payload.message || 'No se pudo asignar.';
                        showMessage(errorText, 'danger');
                        return;
                    }

                    showMessage(payload.message || 'Asignado correctamente.', 'success');
                    selectedItems = {};
                    checkAll.checked = false;
                    if (currentPage > lastPage) {
                        currentPage = lastPage;
                    }
                    loadPage(currentPage, false);
                } catch (error) {
                    showMessage('Error al asignar paquetes.', 'danger');
                } finally {
                    assignButton.disabled = false;
                }
            });

            loadUsers();
            loadPage(1, false);
        })();
    </script>
@endsection

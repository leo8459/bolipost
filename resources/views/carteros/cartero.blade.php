@extends('adminlte::page')
@section('title', 'Carteros - Cartero')
@section('template_title')
    Carteros - Cartero
@endsection

@section('content')
    <div class="carteros-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0">Paquetes en Estado CARTERO (Mis Paquetes)</h3>
                    <div class="d-flex align-items-center" style="gap:10px;">
                        <button id="btn-open-guia-modal" class="btn btn-sm btn-carteros-primary">Mandar provincia</button>
                        <button id="btn-show-provincia" class="btn btn-sm btn-outline-light">Mostrar provincias</button>
                        <button id="btn-show-cartero" class="btn btn-sm btn-outline-light" style="display:none;">Mostrar cartero</button>
                        <span id="bandeja-chip" class="carteros-chip">Mi bandeja CARTERO</span>
                    </div>
                </div>
            </div>
            <div id="guia-message" class="px-3 pt-3" style="display:none;"></div>
            <div class="card-body p-0">
                <div class="table-responsive">
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
                        '<td><a href="' + entregaUrl + '" class="btn btn-sm btn-carteros-primary">Entregar correspondencia</a></td>' +
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
                    const url = currentEndpoint + '?page=' + page + '&per_page=' + perPage;
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

            btnShowProvincia.addEventListener('click', function() {
                currentEndpoint = '{{ route('api.carteros.provincia') }}';
                currentModeLabel = 'PROVINCIA';
                bandejaChip.textContent = 'Mi bandeja PROVINCIA';
                btnShowProvincia.style.display = 'none';
                btnShowCartero.style.display = '';
                btnOpenGuiaModal.disabled = true;
                resetSelection();
                loadPage(1);
            });

            btnShowCartero.addEventListener('click', function() {
                currentEndpoint = '{{ route('api.carteros.cartero') }}';
                currentModeLabel = 'CARTERO';
                bandejaChip.textContent = 'Mi bandeja CARTERO';
                btnShowCartero.style.display = 'none';
                btnShowProvincia.style.display = '';
                btnOpenGuiaModal.disabled = false;
                resetSelection();
                loadPage(1);
            });

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

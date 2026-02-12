@extends('adminlte::page')
@section('title', 'Carteros - Devolucion')
@section('template_title')
    Carteros - Devolucion
@endsection

@section('content')
    <div class="carteros-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0">Paquetes en DEVOLUCION (Mis Paquetes)</h3>
                    <span class="carteros-chip">Devolucion</span>
                </div>
            </div>
            <div class="card-body border-bottom">
                <div id="devolucion-msg" class="small"></div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
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
                                <th>Descripcion</th>
                                <th>Fecha</th>
                                <th>Accion</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-devolucion-body">
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
@endsection

@section('css')
    @include('carteros.partials.theme')
@endsection

@section('js')
    <script>
        (function() {
            let currentPage = 1;
            const perPage = 25;

            const body = document.getElementById('tabla-devolucion-body');
            const pageIndicator = document.getElementById('page-indicator');
            const prevItem = document.getElementById('prev-page-item');
            const nextItem = document.getElementById('next-page-item');
            const prevLink = document.getElementById('prev-page-link');
            const nextLink = document.getElementById('next-page-link');
            const msg = document.getElementById('devolucion-msg');
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
                body.innerHTML = '<tr><td colspan="13" class="text-center py-4">Cargando datos...</td></tr>';
            }

            function setError() {
                body.innerHTML = '<tr><td colspan="13" class="text-center text-danger py-4">Error cargando datos.</td></tr>';
            }

            function renderRows(rows) {
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="13" class="text-center py-4">No hay paquetes en DEVOLUCION para este usuario.</td></tr>';
                    return;
                }

                body.innerHTML = rows.map(function(row) {
                    return '<tr>' +
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
                        '<td>' + escapeHtml(row.descripcion) + '</td>' +
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '<td><button class="btn btn-sm btn-carteros-primary btn-recuperar" data-id="' + row.id + '" data-tipo="' + escapeHtml(row.tipo_paquete) + '">RECUPERAR</button></td>' +
                        '</tr>';
                }).join('');
            }

            function showMessage(text, type) {
                msg.className = 'small text-' + type;
                msg.textContent = text;
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
                showMessage('', 'muted');
                try {
                    const url = '{{ route('api.carteros.devolucion') }}?page=' + page + '&per_page=' + perPage;
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

            body.addEventListener('click', async function(e) {
                const btn = e.target.closest('.btn-recuperar');
                if (!btn) return;

                const id = parseInt(btn.dataset.id, 10);
                const tipo = btn.dataset.tipo;
                if (!id || !tipo) return;

                btn.disabled = true;
                showMessage('Recuperando paquete...', 'info');

                try {
                    const response = await fetch('{{ route('api.carteros.devolver-almacen') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({
                            items: [{ id: id, tipo_paquete: tipo }]
                        })
                    });
                    const payload = await response.json();
                    if (!response.ok) {
                        showMessage(payload.message || 'No se pudo recuperar el paquete.', 'danger');
                        btn.disabled = false;
                        return;
                    }

                    showMessage('Paquete recuperado y enviado a ALMACEN.', 'success');
                    loadPage(currentPage);
                } catch (err) {
                    showMessage('Error recuperando el paquete.', 'danger');
                    btn.disabled = false;
                }
            });

            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!prevItem.classList.contains('disabled')) loadPage(currentPage - 1);
            });

            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!nextItem.classList.contains('disabled')) loadPage(currentPage + 1);
            });

            loadPage(1);
        })();
    </script>
@endsection

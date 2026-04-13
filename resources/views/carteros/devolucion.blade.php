@extends('adminlte::page')
@section('title', 'Carteros - Devolucion')
@section('template_title')
    Carteros - Devolucion
@endsection

@section('content')
    @php
        $canCarteroRestore = auth()->user()?->can('feature.carteros.devolucion.restore') ?? false;
    @endphp
    <div class="carteros-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="cartero-header-top">
                    <div>
                        <h3 class="card-title mb-1">Paquetes en DEVOLUCION</h3>
                        <div class="devolucion-header-subtitle">Bandeja de paquetes observados para recuperar y reenviar a almacen.</div>
                    </div>
                    <span class="carteros-chip">Devolucion</span>
                </div>
            </div>
            <div class="card-body">
                <div class="devolucion-shell">
                    <div class="devolucion-toolbar">
                        <div>
                            <div class="devolucion-toolbar-title">Seguimiento de incidencias</div>
                            <div class="devolucion-toolbar-subtitle">Revisa observaciones, evidencia y recupera paquetes cuando corresponda.</div>
                        </div>
                        <div id="devolucion-msg" class="devolucion-status" aria-live="polite"></div>
                    </div>

                    <div class="table-responsive devolucion-table-wrap">
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
                                    <th>Imagen</th>
                                    <th>Fecha</th>
                                    <th>Accion</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-devolucion-body">
                                <tr>
                                    <td colspan="14" class="text-center py-4">Cargando datos...</td>
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
        .cartero-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
        }

        .devolucion-shell {
            border: 1px solid #e4e8f2;
            border-radius: 14px;
            background: #fff;
            overflow: hidden;
        }

        .card-carteros .card-header .card-title {
            float: none;
            display: block;
        }

        .devolucion-header-subtitle {
            display: block;
            margin-top: 4px;
            color: rgba(255, 255, 255, 0.72);
            font-size: 0.92rem;
            line-height: 1.45;
            max-width: 520px;
        }

        .devolucion-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 16px 18px;
            border-bottom: 1px solid #e4e8f2;
            background: linear-gradient(180deg, #fbfcff 0%, #f7faff 100%);
            flex-wrap: wrap;
        }

        .devolucion-toolbar-title {
            color: var(--carteros-primary);
            font-size: 1rem;
            font-weight: 800;
        }

        .devolucion-toolbar-subtitle {
            color: #5e6b86;
            font-size: 0.85rem;
            margin-top: 2px;
        }

        .devolucion-status {
            min-height: 42px;
            min-width: min(100%, 380px);
            border: 1px dashed #cfd9ee;
            border-radius: 12px;
            background: #f8faff;
            padding: 10px 12px;
            color: #5e6b86;
            font-size: 0.84rem;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .devolucion-status.is-info {
            color: #20539A;
            border-color: rgba(32, 83, 154, 0.25);
            background: rgba(32, 83, 154, 0.06);
        }

        .devolucion-status.is-success {
            color: #166534;
            border-color: rgba(22, 101, 52, 0.22);
            background: rgba(22, 101, 52, 0.07);
        }

        .devolucion-status.is-danger {
            color: #b42318;
            border-color: rgba(180, 35, 24, 0.22);
            background: rgba(180, 35, 24, 0.06);
        }

        .devolucion-table-wrap .table {
            margin-bottom: 0;
        }

        .devolucion-table-wrap .table tbody td {
            border-top: 1px solid rgba(32, 83, 154, 0.08);
            vertical-align: middle;
        }

        .devolucion-table-wrap .btn {
            border-radius: 10px;
            font-weight: 700;
        }

        @media (max-width: 991.98px) {
            .devolucion-status {
                min-width: 100%;
            }
        }
    </style>
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
            const canCarteroRestore = @json($canCarteroRestore);

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
                body.innerHTML = '<tr><td colspan="14" class="text-center py-4">Cargando datos...</td></tr>';
            }

            function setError() {
                body.innerHTML = '<tr><td colspan="14" class="text-center text-danger py-4">Error cargando datos.</td></tr>';
            }

            function renderRows(rows) {
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="14" class="text-center py-4">No hay paquetes en DEVOLUCION para este usuario.</td></tr>';
                    return;
                }

                body.innerHTML = rows.map(function(row) {
                    const imageHtml = row.imagen_devolucion
                        ? '<a href="/storage/' + encodeURIComponent(row.imagen_devolucion).replace(/%2F/g, '/') + '" target="_blank" class="btn btn-sm btn-outline-secondary">Ver foto</a>'
                        : '<span class="text-muted small">Sin foto</span>';
                    const actionHtml = canCarteroRestore
                        ? '<button class="btn btn-sm btn-carteros-primary btn-recuperar" data-id="' + row.id + '" data-tipo="' + escapeHtml(row.tipo_paquete) + '">RECUPERAR</button>'
                        : '<span class="text-muted small">Sin accion</span>';

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
                        '<td>' + imageHtml + '</td>' +
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '<td>' + actionHtml + '</td>' +
                        '</tr>';
                }).join('');
            }

            function showMessage(text, type) {
                msg.className = 'devolucion-status is-' + type;
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

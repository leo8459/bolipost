@extends('adminlte::page')
@section('title', 'Carteros - Asignados')
@section('template_title')
    Carteros - Asignados
@endsection

@section('content')
    <div class="carteros-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0">Paquetes en Estado CARTERO</h3>
                    <span class="carteros-chip">Asignados</span>
                </div>
                <div class="asignados-toolbar">
                    <div class="asignados-search-cluster">
                        <input
                            id="asignados-search"
                            type="text"
                            class="form-control"
                            placeholder="Buscar por codigo, cod_especial o nombre del cartero"
                        >
                        <button id="asignados-search-btn" type="button" class="btn btn-carteros-secondary">Buscar</button>
                        <button id="asignados-clear-btn" type="button" class="btn btn-carteros-clear">Limpiar</button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive asignados-table-wrap">
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
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody id="tabla-asignados-body">
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
    </div>
@endsection

@section('css')
    @include('carteros.partials.theme')
    <style>
        .asignados-toolbar {
            margin-top: 14px;
            display: flex;
            justify-content: flex-end;
        }

        .asignados-search-cluster {
            width: min(100%, 760px);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: nowrap;
        }

        .asignados-search-cluster .form-control {
            min-height: 44px;
            border-radius: 10px;
            border-color: #cbd5e1;
            box-shadow: none;
        }

        .asignados-search-cluster .form-control:focus {
            border-color: var(--carteros-primary);
            box-shadow: 0 0 0 0.15rem rgba(32, 83, 154, 0.12);
        }

        .btn-carteros-secondary {
            background: var(--carteros-secondary);
            border-color: var(--carteros-secondary);
            color: #fff;
            min-height: 44px;
            border-radius: 12px;
            font-weight: 800;
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
            min-height: 44px;
            border-radius: 12px;
            font-weight: 800;
        }

        .btn-carteros-clear:hover {
            background: rgba(32, 83, 154, 0.05);
            color: var(--carteros-primary);
        }

        .asignados-table-wrap {
            border-top: 1px solid #e4e8f2;
        }
    </style>
@endsection

@section('js')
    <script>
        (function() {
            let currentPage = 1;
            const perPage = 25;
            let currentSearch = '';

            const body = document.getElementById('tabla-asignados-body');
            const pageIndicator = document.getElementById('page-indicator');
            const prevItem = document.getElementById('prev-page-item');
            const nextItem = document.getElementById('next-page-item');
            const prevLink = document.getElementById('prev-page-link');
            const nextLink = document.getElementById('next-page-link');
            const searchInput = document.getElementById('asignados-search');
            const searchBtn = document.getElementById('asignados-search-btn');
            const clearBtn = document.getElementById('asignados-clear-btn');

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
                body.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-4">Error cargando datos.</td></tr>';
            }

            function renderRows(rows) {
                if (!rows.length) {
                    body.innerHTML = '<tr><td colspan="11" class="text-center py-4">No hay paquetes en estado CARTERO.</td></tr>';
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
                        '<td>' + escapeHtml(row.created_at) + '</td>' +
                        '</tr>';
                }).join('');
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
                    const query = currentSearch !== '' ? '&search=' + encodeURIComponent(currentSearch) : '';
                    const url = '{{ route('api.carteros.asignados') }}?page=' + page + '&per_page=' + perPage + query;
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

            prevLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!prevItem.classList.contains('disabled')) loadPage(currentPage - 1);
            });

            nextLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (!nextItem.classList.contains('disabled')) loadPage(currentPage + 1);
            });

            searchBtn.addEventListener('click', function() {
                currentSearch = (searchInput.value || '').trim();
                loadPage(1);
            });

            clearBtn.addEventListener('click', function() {
                currentSearch = '';
                searchInput.value = '';
                loadPage(1);
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    currentSearch = (searchInput.value || '').trim();
                    loadPage(1);
                }
            });

            loadPage(1);
        })();
    </script>
@endsection

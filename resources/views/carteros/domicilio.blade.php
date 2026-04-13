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
                        <div class="domicilio-toolbar-title">Registro de entregas</div>
                        <div class="domicilio-toolbar-subtitle">Historial consolidado de paquetes entregados por carteros.</div>
                    </div>

                    <div class="table-responsive domicilio-table-wrap">
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
                                    <th>Recibido por</th>
                                    <th>Descripcion</th>
                                    <th>Imagen</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody id="tabla-domicilio-body">
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
    </style>
@endsection

@section('js')
    <script>
        (function() {
            let currentPage = 1;
            const perPage = 25;

            const body = document.getElementById('tabla-domicilio-body');
            const pageIndicator = document.getElementById('page-indicator');
            const prevItem = document.getElementById('prev-page-item');
            const nextItem = document.getElementById('next-page-item');
            const prevLink = document.getElementById('prev-page-link');
            const nextLink = document.getElementById('next-page-link');

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
                    body.innerHTML = '<tr><td colspan="14" class="text-center py-4">No hay paquetes en estado ENTREGADO.</td></tr>';
                    return;
                }

                body.innerHTML = rows.map(function(row) {
                    const imageHtml = row.imagen
                        ? '<a href="/storage/' + encodeURIComponent(row.imagen).replace(/%2F/g, '/') + '" target="_blank" class="btn btn-sm btn-outline-secondary">Ver foto</a>'
                        : '<span class="text-muted small">Sin foto</span>';
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
                        '<td>' + escapeHtml(row.recibido_por) + '</td>' +
                        '<td>' + escapeHtml(row.descripcion) + '</td>' +
                        '<td>' + imageHtml + '</td>' +
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
                    const url = '{{ route('api.carteros.domicilio') }}?page=' + page + '&per_page=' + perPage;
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

            loadPage(1);
        })();
    </script>
@endsection

<div>
    {{-- Si no tienes bootstrap cargado en tu layout principal, agrega bootstrap ahí --}}
    <style>
        :root{
            --azul:#34447C;
            --dorado:#B99C46;
            --bg:#f5f7fb;
        }

        .plantilla-wrap{
            background: var(--bg);
            padding: 18px;
            border-radius: 16px;
        }

        .card-app{
            border:0;
            border-radius:16px;
            box-shadow:0 12px 26px rgba(0,0,0,.08);
            overflow:hidden;
        }

        .header-app{
            background: linear-gradient(90deg, var(--azul), #2c3766);
            color:#fff;
            padding:18px 20px;
        }

        .search-input{
            border-radius:12px;
            border:1px solid rgba(255,255,255,.45);
            padding:10px 12px;
            background: rgba(255,255,255,.95);
        }

        .btn-dorado{
            background: var(--dorado);
            color:#fff;
            font-weight: 800;
            border:none;
            border-radius: 12px;
            padding: 10px 14px;
        }
        .btn-dorado:hover{ filter:brightness(.95); color:#fff; }

        .btn-outline-light2{
            border:1px solid rgba(255,255,255,.7);
            color:#fff;
            font-weight:800;
            border-radius: 12px;
            padding: 10px 14px;
            background: transparent;
        }
        .btn-outline-light2:hover{
            background: rgba(255,255,255,.12);
            color:#fff;
        }

        .btn-azul{
            background: var(--azul);
            color:#fff;
            font-weight: 800;
            border:none;
            border-radius: 12px;
            padding: 10px 14px;
        }
        .btn-azul:hover{ filter:brightness(.95); color:#fff; }

        .btn-outline-azul{
            border:1px solid rgba(52,68,124,.35);
            color: var(--azul);
            font-weight: 800;
            border-radius: 12px;
            padding: 10px 14px;
            background:#fff;
        }
        .btn-outline-azul:hover{
            background: rgba(52,68,124,.06);
            color: var(--azul);
        }

        .table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .pill-id{
            background: rgba(52,68,124,.12);
            color: var(--azul);
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 999px;
            display:inline-block;
        }

        .badge-estado{
            background: rgba(185,156,70,.15);
            color: var(--dorado);
            border: 1px solid rgba(185,156,70,.35);
            font-weight: 800;
            padding: 6px 10px;
            border-radius: 999px;
        }

        .muted{ color:#6b7280; }

        .table td{
            vertical-align: middle;
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">Gestión de Plantillas</h4>
                    <small class="opacity-75">Búsqueda por todos los campos • Paginación</small>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar en toda la tabla..."
                        wire:model.live="q"
                    >
                    <button class="btn btn-dorado" type="button" wire:click="limpiar">Limpiar</button>
                </div>
            </div>

            <div class="card-body">

                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-azul" type="button">Nueva plantilla</button>
                        <button class="btn btn-outline-azul" type="button">Duplicar</button>
                        <button class="btn btn-outline-azul" type="button">Exportar</button>
                        <button class="btn btn-outline-azul" type="button">Importar</button>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($q))
                            Resultados para: <strong>{{ $q }}</strong>
                        @else
                            Mostrando todos los registros
                        @endif
                    </div>
                    <div class="muted small">
                        Total en página: <strong>{{ $plantillas->count() }}</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Ciudad</th>
                                <th>Destinatario</th>
                                <th>Remitente</th>
                                <th>Teléfono</th>
                                <th>Ciudad destino</th>
                                <th>Estado</th>
                                <th>Observación</th>
                                <th>Creado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($plantillas as $p)
                                <tr>
                                    <td><span class="pill-id">#{{ $p->id }}</span></td>
                                    <td class="fw-semibold">{{ $p->nombre }}</td>
                                    <td>{{ $p->ciudad }}</td>
                                    <td>{{ $p->destinatario }}</td>
                                    <td>{{ $p->remitente }}</td>
                                    <td>{{ $p->telefono }}</td>
                                    <td>{{ $p->ciudad_destino }}</td>
                                    <td><span class="badge-estado">{{ $p->estado }}</span></td>
                                    <td class="muted" style="min-width:220px">{{ $p->observacion ?? '—' }}</td>
                                    <td class="muted small">{{ optional($p->created_at)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">Prueba con otro texto de búsqueda.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $plantillas->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

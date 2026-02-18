<div>
    <style>
        :root{
            --azul:#B99C46;
            --dorado:#34447C;
            --bg:#f5f7fb;
            --muted:#6b7280;
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

        .btn-outline-light2{
            border:1px solid rgba(255,255,255,.7);
            color:#fff;
            font-weight:800;
            border-radius: 12px;
            padding: 10px 14px;
            background: transparent;
        }

        .table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .table td{ vertical-align: middle; white-space: nowrap; }
        .muted{ color:var(--muted); }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">Todos los despachos</h4>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar..."
                        wire:model="search"
                    >
                    <button class="btn btn-outline-light2" type="button" wire:click="searchDespachos">Buscar</button>
                </div>
            </div>

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            Resultados para: <strong>{{ $searchQuery }}</strong>
                        @else
                            Mostrando todos los estados
                        @endif
                    </div>
                    <div class="muted small">
                        Total en pagina: <strong>{{ $despachos->count() }}</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Identificador</th>
                                <th>Nro despacho</th>
                                <th>Oforigen</th>
                                <th>Ofdestino</th>
                                <th>Categoria</th>
                                <th>Subclase</th>
                                <th>Nro envase</th>
                                <th>Peso</th>
                                <th>Anio</th>
                                <th>Departamento</th>
                                <th>Estado</th>
                                <th>Fecha creacion</th>
                                <th>Fecha actualizacion</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($despachos as $despacho)
                                <tr>
                                    <td>{{ $despacho->identificador }}</td>
                                    <td>{{ $despacho->nro_despacho }}</td>
                                    <td>{{ $despacho->oforigen }}</td>
                                    <td>{{ $despacho->ofdestino }}</td>
                                    <td>{{ $despacho->categoria }}</td>
                                    <td>{{ $despacho->subclase }}</td>
                                    <td>{{ $despacho->nro_envase }}</td>
                                    <td>{{ $despacho->peso }}</td>
                                    <td>{{ $despacho->anio }}</td>
                                    <td>{{ $despacho->departamento }}</td>
                                    <td>{{ optional($despacho->estado)->nombre_estado }}</td>
                                    <td>{{ optional($despacho->created_at)->format('Y-m-d H:i:s') }}</td>
                                    <td>{{ optional($despacho->updated_at)->format('Y-m-d H:i:s') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $despachos->links() }}
                </div>
            </div>
        </div>
    </div>
</div>


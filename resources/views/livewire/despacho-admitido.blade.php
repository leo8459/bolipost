<div>
    <style>
        :root{
            --azul:#B99C46;
            --dorado:#34447C;
            --bg:#f5f7fb;
            --line:#e5e7eb;
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

        .table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .muted{ color:var(--muted); }

        .table td{ vertical-align: middle; white-space: nowrap; }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">Despachos admitidos</h4>
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

            @if (session()->has('success'))
                <div class="alert alert-success m-3 mb-0">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger m-3 mb-0">{{ $errors->first() }}</div>
            @endif

            <div class="card-body">
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
                                <th>Estado</th>
                                <th>Sacas recibidos / sacas totales</th>
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
                                    <td>{{ optional($despacho->estado)->nombre_estado }}</td>
                                    <td>{{ $despacho->sacas_recibidas }} / {{ $despacho->sacas_totales }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">No se encontraron despachos admitidos.</div>
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

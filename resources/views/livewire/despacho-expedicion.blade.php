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
                    <h4 class="fw-bold mb-0">Despachos expedicion</h4>
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
                @if (session()->has('success'))
                    <div class="alert alert-success mb-3">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            Resultados para: <strong>{{ $searchQuery }}</strong>
                        @else
                            Mostrando despachos con estado expedicion
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
                                <th>Oforigen</th>
                                <th>Ofdestino</th>
                                <th>Categoria</th>
                                <th>Subclase</th>
                                <th>Nro despacho</th>
                                <th>Nro envase</th>
                                <th>Peso</th>
                                <th>Identificador</th>
                                <th>Anio</th>
                                <th>Departamento</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($despachos as $despacho)
                                <tr>
                                    <td>{{ $despacho->oforigen }}</td>
                                    <td>{{ $despacho->ofdestino }}</td>
                                    <td>{{ $despacho->categoria }}</td>
                                    <td>{{ $despacho->subclase }}</td>
                                    <td>{{ $despacho->nro_despacho }}</td>
                                    <td>{{ $despacho->nro_envase }}</td>
                                    <td>{{ $despacho->peso }}</td>
                                    <td>{{ $despacho->identificador }}</td>
                                    <td>{{ $despacho->anio }}</td>
                                    <td>{{ $despacho->departamento }}</td>
                                    <td>{{ optional($despacho->estado)->nombre_estado }}</td>
                                    <td>
                                        <a
                                            href="{{ route('despachos.expedicion.pdf', ['id' => $despacho->id]) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-secondary"
                                            title="Reimprimir CN">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @if (optional($despacho->estado)->nombre_estado === 'EXPEDICION')
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-danger"
                                                wire:click="intervenirDespacho({{ $despacho->id }})"
                                                onclick="return confirm('Cambiar este despacho a intervencion (estado 20)?')"
                                                title="Intervenir despacho">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </button>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-warning"
                                                wire:click="volverApertura({{ $despacho->id }})"
                                                onclick="return confirm('Cambiar este despacho a apertura (estado 14)?')"
                                                title="Volver a apertura">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        @elseif (optional($despacho->estado)->nombre_estado === 'INTERVENIR')
                                            <a
                                                href="{{ route('sacas.index', ['despacho_id' => $despacho->id]) }}"
                                                class="btn btn-sm btn-primary"
                                                title="Ver sacas">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">No se encontraron despachos en expedicion.</div>
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

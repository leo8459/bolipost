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

        .btn-azul{
            background: var(--azul);
            color:#fff;
            font-weight: 800;
            border:none;
            border-radius: 12px;
            padding: 8px 12px;
        }
        .btn-azul:hover{ filter:brightness(.95); color:#fff; }

        .btn-outline-azul{
            border:1px solid rgba(52,68,124,.35);
            color: var(--azul);
            font-weight: 800;
            border-radius: 12px;
            padding: 8px 12px;
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

        .muted{ color:var(--muted); }

        .table td{ vertical-align: middle; white-space: nowrap; }

        .modal-content{
            border:0;
            border-radius:18px;
            box-shadow:0 20px 50px rgba(0,0,0,.2);
        }
        .modal-header{
            background: linear-gradient(90deg, var(--azul), #2c3766);
            color:#fff;
            border-bottom:0;
            padding:16px 20px;
        }
        .modal-title{ font-weight:800; }
        .modal-body{ padding:20px; background:#fff; }
        .modal-footer{
            border-top:1px solid var(--line);
            padding:14px 20px;
            background:#fafafa;
        }
        .form-control, .custom-select, select.form-control{
            border-radius:10px;
            border:1px solid #d1d5db;
            box-shadow:none;
        }
        .form-control:focus, select.form-control:focus{
            border-color: var(--azul);
            box-shadow:0 0 0 0.15rem rgba(52,68,124,.15);
        }
        .form-group label{
            font-weight:700;
            color:#1f2937;
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">Despachos abiertos</h4>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar por cualquier campo..."
                        wire:model="search"
                    >
                    <button class="btn btn-outline-light2" type="button" wire:click="searchDespachos">Buscar</button>
                    <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3">
                    <p class="mb-0">{{ session('success') }}</p>
                </div>
            @endif

            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            Resultados para: <strong>{{ $searchQuery }}</strong>
                        @else
                            Mostrando todos los registros
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
                                <th>Año</th>
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
                                        @if (optional($despacho->estado)->nombre_estado === 'CLAUSURA')
                                            <button wire:click="expedicion({{ $despacho->id }})"
                                                class="btn btn-sm btn-info"
                                                title="Expedicion"
                                                onclick="return confirm('Cambiar estado del despacho a expedicion?')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        @endif
                                        @if (optional($despacho->estado)->nombre_estado !== 'CLAUSURA')
                                            <a href="{{ route('sacas.index', ['despacho_id' => $despacho->id]) }}"
                                                class="btn btn-sm btn-success"
                                                title="Asignar sacas">
                                                <i class="fas fa-suitcase"></i>
                                            </a>
                                        @else
                                            <button wire:click="reaperturaSaca({{ $despacho->id }})"
                                                class="btn btn-sm btn-warning"
                                                title="Reapertura de saca"
                                                onclick="return confirm('Se reaperturara el despacho y sus sacas. Continuar?')">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        @endif
                                        <button wire:click="openEditModal({{ $despacho->id }})"
                                            class="btn btn-sm btn-azul"
                                            title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button wire:click="delete({{ $despacho->id }})"
                                            class="btn btn-sm btn-outline-azul"
                                            title="Eliminar"
                                            onclick="return confirm('Seguro que deseas eliminar este despacho?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">Prueba con otro texto de busqueda.</div>
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

    <div class="modal fade" id="despachoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingId ? 'Editar despacho' : 'Nuevo despacho' }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Ofdestino</label>
                                    <select class="form-control" wire:model.defer="ofdestino">
                                        <option value="">Seleccione...</option>
                                        @foreach($oficinas as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('ofdestino') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Categoria</label>
                                    <select class="form-control" wire:model.defer="categoria">
                                        <option value="">Seleccione...</option>
                                        @foreach($categorias as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('categoria') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Subclase</label>
                                    <select class="form-control" wire:model.defer="subclase">
                                        <option value="">Seleccione...</option>
                                        @foreach($subclases as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('subclase') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            {{ $editingId ? 'Guardar cambios' : 'Crear' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('openDespachoModal', () => {
        $('#despachoModal').modal('show');
    });

    window.addEventListener('closeDespachoModal', () => {
        $('#despachoModal').modal('hide');
    });

    window.addEventListener('printDespachoExpedicion', (event) => {
        const url = event.detail?.[0]?.url || event.detail?.url;
        if (url) {
            window.open(url, '_blank');
        }
    });
</script>

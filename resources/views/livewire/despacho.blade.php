<div>
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
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
            background: linear-gradient(90deg, var(--azul), #20539A);
            color:#fff;
            padding:18px 20px;
        }
        .header-shell{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:20px;
        }
        .header-main{
            flex:1 1 280px;
            min-width:220px;
        }
        .header-tools{
            flex:1 1 760px;
            min-width:320px;
            display:flex;
            flex-direction:column;
            align-items:stretch;
            gap:12px;
        }
        .header-search-row{
            display:flex;
            justify-content:flex-end;
        }
        .header-search-cluster{
            width:min(100%, 760px);
            display:flex;
            align-items:center;
            gap:10px;
            flex-wrap:nowrap;
        }

        .search-input{
            border-radius:12px;
            border:1px solid rgba(255,255,255,.45);
            padding:10px 12px;
            background: rgba(255,255,255,.95);
            flex:1 1 auto;
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
        .table-scroll-wrap{
            border:1px solid #dbe2f2;
            border-radius:16px;
            overflow:hidden;
            background:#fff;
        }
        .table-responsive{
            margin-bottom:0;
        }
        .table{
            margin-bottom:0;
        }
        .table tbody td{
            border-top:1px solid rgba(52,68,124,.10);
        }

        .muted{ color:var(--muted); }

        .table td{ vertical-align: middle; white-space: nowrap; }
        .action-cell{
            width:96px;
            min-width:96px;
            text-align:center;
        }
        .action-stack{
            display:flex;
            align-items:center;
            justify-content:center;
            gap:8px;
            flex-wrap:wrap;
        }
        .action-btn{
            width:40px;
            height:40px;
            padding:0;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:11px;
            box-shadow:0 6px 16px rgba(32, 83, 154, .10);
        }
        .action-btn i{
            font-size:14px;
        }
        .action-btn.btn-azul{
            box-shadow:0 8px 18px rgba(32, 83, 154, .22);
        }
        .action-btn.btn-outline-azul{
            background:#fff;
            border-color:rgba(32, 83, 154, .22);
        }
        .action-btn.btn-outline-azul:hover{
            background:rgba(32, 83, 154, .06);
        }
        .action-btn.btn-success,
        .action-btn.btn-info,
        .action-btn.btn-warning{
            border:none;
            color:#fff;
        }
        @media (max-width: 991.98px){
            .header-shell{
                flex-direction:column;
            }
            .header-tools{
                min-width:0;
                width:100%;
            }
            .header-search-cluster{
                width:100%;
            }
        }

        .modal-content{
            border:0;
            border-radius:18px;
            box-shadow:0 20px 50px rgba(0,0,0,.2);
        }
        .modal-header{
            background: linear-gradient(90deg, var(--azul), #20539A);
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
            <div class="header-app">
                <div class="header-shell">
                <div class="header-main">
                    <h4 class="fw-bold mb-0">Despachos abiertos</h4>
                </div>

                <div class="header-tools">
                    <div class="header-search-row">
                        <div class="header-search-cluster">
                            <input
                                type="text"
                                class="form-control search-input"
                                placeholder="Buscar por cualquier campo..."
                                wire:model="search"
                            >
                            <button class="btn btn-outline-light2" type="button" wire:click="searchDespachos">Buscar</button>
                            @if ($canDespachoCreate)
                                <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
                            @endif
                        </div>
                    </div>
                </div>
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

                <div class="table-scroll-wrap">
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
                                    <td class="action-cell">
                                        <div class="action-stack">
                                        @if (optional($despacho->estado)->nombre_estado === 'CLAUSURA' && $canDespachoConfirm)
                                            <button wire:click="expedicion({{ $despacho->id }})"
                                                class="btn btn-sm btn-info action-btn"
                                                title="Expedicion"
                                                onclick="return confirm('Cambiar estado del despacho a expedicion?')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        @endif
                                        @if (optional($despacho->estado)->nombre_estado !== 'CLAUSURA' && $canDespachoOpenSacas)
                                            <a href="{{ route('sacas.index', ['despacho_id' => $despacho->id], false) }}"
                                                class="btn btn-sm btn-success action-btn"
                                                title="Asignar sacas">
                                                <i class="fas fa-suitcase"></i>
                                            </a>
                                        @elseif (optional($despacho->estado)->nombre_estado === 'CLAUSURA' && $canDespachoRestore)
                                            <button wire:click="reaperturaSaca({{ $despacho->id }})"
                                                class="btn btn-sm btn-warning action-btn"
                                                title="Reapertura de saca"
                                                onclick="return confirm('Se reaperturara el despacho y sus sacas. Continuar?')">
                                                <i class="fas fa-unlock"></i>
                                            </button>
                                        @endif
                                        @if ($canDespachoEdit)
                                        <button wire:click="openEditModal({{ $despacho->id }})"
                                            class="btn btn-sm btn-azul action-btn"
                                            title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        @endif
                                        @if ($canDespachoDelete)
                                        <button wire:click="delete({{ $despacho->id }})"
                                            class="btn btn-sm btn-outline-azul action-btn"
                                            title="Eliminar"
                                            onclick="return confirm('Seguro que deseas eliminar este despacho?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                        </div>
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
                        @if (($editingId && $canDespachoEdit) || (!$editingId && $canDespachoCreate))
                            <button type="submit" class="btn btn-primary">
                                {{ $editingId ? 'Guardar cambios' : 'Crear' }}
                            </button>
                        @endif
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



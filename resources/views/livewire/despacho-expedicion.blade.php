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
            flex:0 1 260px;
            min-width:220px;
        }
        .header-tools{
            flex:1 1 860px;
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
            width:min(100%, 860px);
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
        .action-btn.btn-secondary,
        .action-btn.btn-danger,
        .action-btn.btn-warning,
        .action-btn.btn-primary{
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
        .modal-footer{
            border-top:1px solid var(--line);
            background:#fafafa;
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app">
                <div class="header-shell">
                <div class="header-main">
                    <h4 class="fw-bold mb-0">Despachos expedicion</h4>
                </div>

                <div class="header-tools">
                    <div class="header-search-row">
                        <div class="header-search-cluster">
                            <input
                                type="text"
                                class="form-control search-input"
                                placeholder="Buscar..."
                                wire:model="search"
                            >
                            <button class="btn btn-outline-light2" type="button" wire:click="searchDespachos">Buscar</button>
                        </div>
                    </div>
                </div>
                </div>
            </div>

            <div class="card-body">
                @if (session()->has('success'))
                    <div class="alert alert-success mb-3">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session()->has('error'))
                    <div class="alert alert-danger mb-3">
                        {{ session('error') }}
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
                                    <td class="action-cell">
                                        <div class="action-stack">
                                        @if ($canDespachoExpPrint)
                                        <a
                                            href="{{ route('despachos.expedicion.pdf', ['id' => $despacho->id], false) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-secondary action-btn"
                                            title="Reimprimir CN">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @endif
                                        @if (optional($despacho->estado)->nombre_estado === 'EXPEDICION' && $canDespachoExpConfirm)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-danger action-btn"
                                                wire:click="intervenirDespacho({{ $despacho->id }})"
                                                onclick="return confirm('Cambiar este despacho a intervencion (estado 20)?')"
                                                title="Intervenir despacho">
                                                <i class="fas fa-exclamation-triangle"></i>
                                            </button>
                                            @if ($canDespachoExpRestore)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-warning action-btn"
                                                wire:click="volverApertura({{ $despacho->id }})"
                                                onclick="return confirm('Cambiar este despacho a apertura (estado 14)?')"
                                                title="Volver a apertura">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            @endif
                                        @elseif (optional($despacho->estado)->nombre_estado === 'INTERVENIR' && $canDespachoExpEdit)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-primary action-btn"
                                                wire:click="openIntervencionModal({{ $despacho->id }})"
                                                title="Registrar intervencion">
                                                <i class="fas fa-clipboard-check"></i>
                                            </button>
                                        @endif
                                        </div>
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
                </div>

                <div class="d-flex justify-content-end">
                    {{ $despachos->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="intervencionModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="registrarIntervencion">
                    <div class="modal-header">
                        <h5 class="modal-title">Intervenir despacho</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label>Saca intervenida</label>
                            <select class="form-control" wire:model="intervencionSacaId">
                                <option value="">Seleccionar saca</option>
                                @foreach ($intervencionSacas as $saca)
                                    <option value="{{ $saca['id'] }}">
                                        {{ $saca['label'] }} | cod_especial: {{ $saca['cod_especial'] ?: '-' }} | peso: {{ $saca['peso'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('intervencionSacaId') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="form-group">
                            <label>Cod especial de la saca</label>
                            <input type="text" class="form-control" value="{{ $intervencionCodEspecial }}" readonly>
                        </div>

                        <div class="form-group">
                            <label>Codigo de paquete intervenido</label>
                            <input type="text" class="form-control" wire:model.debounce.350ms="intervencionCodigoPaquete" placeholder="Ej: RR123456789BO">
                            @error('intervencionCodigoPaquete') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        @error('intervencionDespachoId') <small class="text-danger d-block mt-2">{{ $message }}</small> @enderror
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        @if ($canDespachoExpEdit)
                            <button type="submit" class="btn btn-primary">Guardar intervencion</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('openIntervencionModal', () => {
        $('#intervencionModal').modal('show');
    });

    window.addEventListener('closeIntervencionModal', () => {
        $('#intervencionModal').modal('hide');
    });

    window.addEventListener('reimprimirCnDespacho', (event) => {
        const detail = event.detail;
        const url = Array.isArray(detail) ? detail[0] : (detail?.url ?? null);
        if (url) {
            window.open(url, '_blank');
        }
    });
</script>


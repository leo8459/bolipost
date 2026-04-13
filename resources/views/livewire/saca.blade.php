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
            flex:1 1 360px;
            min-width:260px;
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

        .table td{ vertical-align: middle; white-space: nowrap; }

        .muted{ color:var(--muted); }
        .action-cell{
            width:72px;
            min-width:72px;
            text-align:center;
        }
        .action-stack{
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:8px;
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
        .footer-danger-action{
            min-height:44px;
            border-radius:12px;
            font-weight:800;
            padding:10px 18px;
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
        .form-control, select.form-control{
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
                @php
                    $despachoTitulo = $lockedDespachoLabel;
                    if (!$despachoTitulo && !empty($fk_despacho)) {
                        $despachoSel = $despachos->firstWhere('id', (int) $fk_despacho);
                        if ($despachoSel) {
                            $despachoTitulo = $despachoSel->identificador . ' (' . $despachoSel->anio . '-' . $despachoSel->nro_despacho . ')';
                        }
                    }
                @endphp
                <div class="header-shell">
                    <div class="header-main">
                        <h4 class="fw-bold mb-0">
                            Sacas del despacho: {{ $despachoTitulo ?: 'Sin despacho asignado' }}
                        </h4>
                    </div>

                    <div class="header-tools">
                        <div class="header-search-row">
                            <div class="header-search-cluster">
                                <input type="text" class="form-control search-input" placeholder="Buscar..." wire:model="search">
                                <button class="btn btn-outline-light2" type="button" wire:click="searchSacas">Buscar</button>
                                @if ($canSacaCreate)
                                    <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3 mb-0">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger m-3 mb-0">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="card-body">
                <div class="table-scroll-wrap">
                    <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nro saca</th>
                                <th>Identificador</th>
                                <th>Estado</th>
                                <th>Peso</th>
                                <th>Paquetes</th>
                                <th>Busqueda</th>
                                <th>Receptaculo</th>
                                <th>Despacho</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($sacas as $saca)
                                <tr>
                                    <td>{{ $saca->nro_saca }}</td>
                                    <td>{{ $saca->identificador }}</td>
                                    <td>{{ optional($saca->estado)->nombre_estado }}</td>
                                    <td>{{ $saca->peso }}</td>
                                    <td>{{ $saca->paquetes }}</td>
                                    <td>{{ $saca->busqueda }}</td>
                                    <td>{{ $saca->receptaculo }}</td>
                                    <td>{{ optional($saca->despacho)->identificador }}</td>
                                    <td class="action-cell">
                                        <div class="action-stack">
                                        @if ($canSacaEdit)
                                        <button wire:click="openEditModal({{ $saca->id }})" class="btn btn-sm btn-azul action-btn" title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        @endif
                                        @if ($canSacaDelete)
                                        <button wire:click="delete({{ $saca->id }})" class="btn btn-sm btn-outline-azul action-btn" title="Eliminar" onclick="return confirm('Seguro que deseas eliminar esta saca?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $sacas->links() }}
                </div>

                <div class="d-flex justify-content-end mt-3">
                    @if ($canSacaConfirm && $canCerrarDespacho)
                        <button type="button"
                            class="btn btn-danger footer-danger-action"
                            wire:click="cerrarDespacho"
                            onclick="return confirm('Se cerrara el despacho y cambiara estados de sacas y despacho. Continuar?')">
                            Cerrar despacho
                        </button>
                    @elseif ($canSacaConfirm)
                        <button type="button" class="btn btn-danger footer-danger-action" disabled title="{{ $cerrarDespachoError }}">
                            Cerrar despacho
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="sacaModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Editar saca' : 'Nueva saca' }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    @if(!$editingId)
                                        <x-peso-qz-field
                                            model="peso"
                                            input-id="peso-create-saca"
                                            min="0.001"
                                            :use-scale="true"
                                            :show-clear="true"
                                        />
                                    @else
                                        <label>Peso</label>
                                        <input type="number" step="0.001" min="0.001" class="form-control" wire:model.defer="peso">
                                        @error('peso') <small class="text-danger">{{ $message }}</small> @enderror
                                    @endif
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Paquetes</label>
                                    <input type="number" class="form-control" wire:model.defer="paquetes">
                                    @error('paquetes') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Busqueda</label>
                                    <input type="text" class="form-control" wire:model.debounce.400ms="busqueda" placeholder="Buscar cod_especial en paquetes EMS/ORDI/CONTRATO">
                                    @error('busqueda') <small class="text-danger">{{ $message }}</small> @enderror
                                    @if(!empty($codEspecialSugerencias))
                                        <div class="border rounded mt-2 p-2" style="max-height: 140px; overflow-y: auto;">
                                            @foreach($codEspecialSugerencias as $codEspecial)
                                                @if ($canSacaAssign)
                                                    <button type="button"
                                                        class="btn btn-sm btn-light d-block text-left w-100 mb-1"
                                                        wire:click="seleccionarCodEspecial('{{ $codEspecial }}')">
                                                        {{ $codEspecial }}
                                                    </button>
                                                @else
                                                    <div class="btn btn-sm btn-light d-block text-left w-100 mb-1 disabled">
                                                        {{ $codEspecial }}
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if (!$editingId && $canSacaAssign)
                                <div class="col-md-12">
                                    <button type="button" class="btn btn-outline-primary" wire:click="addCurrentToBatch">
                                        Anadir a la lista
                                    </button>
                                </div>
                            @endif
                            <input type="hidden" wire:model.defer="fk_estado">
                            <input type="hidden" wire:model.defer="fk_despacho">
                            <input type="hidden" wire:model.defer="identificador">
                            <input type="hidden" wire:model.defer="receptaculo">
                        </div>

                        @if (!$editingId && !empty($batchRows))
                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Peso</th>
                                            <th>Paquetes</th>
                                            <th>Busqueda</th>
                                            <th>Accion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($batchRows as $index => $row)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $row['peso'] }}</td>
                                                <td>{{ $row['paquetes'] }}</td>
                                                <td>{{ $row['busqueda'] }}</td>
                                                <td>
                                                    @if ($canSacaAssign)
                                                        <button type="button" class="btn btn-sm btn-outline-danger" wire:click="removeBatchRow({{ $index }})">
                                                            Quitar
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        @if (($editingId && $canSacaEdit) || (!$editingId && $canSacaCreate))
                            <button type="submit" class="btn btn-primary">{{ $editingId ? 'Guardar cambios' : 'Crear' }}</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('openSacaModal', () => {
        $('#sacaModal').modal('show');
    });

    window.addEventListener('closeSacaModal', () => {
        $('#sacaModal').modal('hide');
    });
</script>



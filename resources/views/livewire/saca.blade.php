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

        .table td{ vertical-align: middle; white-space: nowrap; }

        .muted{ color:var(--muted); }

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
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                @php
                    $despachoTitulo = $lockedDespachoLabel;
                    if (!$despachoTitulo && !empty($fk_despacho)) {
                        $despachoSel = $despachos->firstWhere('id', (int) $fk_despacho);
                        if ($despachoSel) {
                            $despachoTitulo = $despachoSel->identificador . ' (' . $despachoSel->anio . '-' . $despachoSel->nro_despacho . ')';
                        }
                    }
                @endphp
                <div>
                    <h4 class="fw-bold mb-0">
                        Sacas del despacho: {{ $despachoTitulo ?: 'Sin despacho asignado' }}
                    </h4>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control search-input" placeholder="Buscar..." wire:model="search">
                    <button class="btn btn-outline-light2" type="button" wire:click="searchSacas">Buscar</button>
                    <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
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
                                    <td>
                                        <button wire:click="openEditModal({{ $saca->id }})" class="btn btn-sm btn-azul" title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button wire:click="delete({{ $saca->id }})" class="btn btn-sm btn-outline-azul" title="Eliminar" onclick="return confirm('Seguro que deseas eliminar esta saca?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
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

                <div class="d-flex justify-content-end">
                    {{ $sacas->links() }}
                </div>

                <div class="d-flex justify-content-end mt-3">
                    @if ($canCerrarDespacho)
                        <button type="button"
                            class="btn btn-danger"
                            wire:click="cerrarDespacho"
                            onclick="return confirm('Se cerrara el despacho y cambiara estados de sacas y despacho. Continuar?')">
                            Cerrar despacho
                        </button>
                    @else
                        <button type="button" class="btn btn-danger" disabled title="{{ $cerrarDespachoError }}">
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
                                    <label>Peso</label>
                                    <input type="number" step="0.001" class="form-control" wire:model.defer="peso">
                                    @error('peso') <small class="text-danger">{{ $message }}</small> @enderror
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
                                    <input type="text" class="form-control" wire:model.debounce.400ms="busqueda" placeholder="Buscar cod_especial en paquetes EMS/ORDI">
                                    @error('busqueda') <small class="text-danger">{{ $message }}</small> @enderror
                                    @if(!empty($codEspecialSugerencias))
                                        <div class="border rounded mt-2 p-2" style="max-height: 140px; overflow-y: auto;">
                                            @foreach($codEspecialSugerencias as $codEspecial)
                                                <button type="button"
                                                    class="btn btn-sm btn-light d-block text-left w-100 mb-1"
                                                    wire:click="seleccionarCodEspecial('{{ $codEspecial }}')">
                                                    {{ $codEspecial }}
                                                </button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <input type="hidden" wire:model.defer="fk_estado">
                            <input type="hidden" wire:model.defer="fk_despacho">
                            <input type="hidden" wire:model.defer="identificador">
                            <input type="hidden" wire:model.defer="receptaculo">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">{{ $editingId ? 'Guardar cambios' : 'Crear' }}</button>
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

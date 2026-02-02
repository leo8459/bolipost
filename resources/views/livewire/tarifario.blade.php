<div>
    <style>
        :root{
            --azul:#34447C;
            --dorado:#B99C46;
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

        .muted{ color:var(--muted); }

        .table td{ vertical-align: middle; }

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
                    <h4 class="fw-bold mb-0">Tarifario</h4>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar por precio u observacion..."
                        wire:model="search"
                    >
                    <button class="btn btn-outline-light2" type="button" wire:click="searchTarifarios">Buscar</button>
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
                        Total en pagina: <strong>{{ $tarifarios->count() }}</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Servicio</th>
                                <th>Destino</th>
                                <th>Peso</th>
                                <th>Origen</th>
                                <th>Precio</th>
                                <th>Observacion</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($tarifarios as $t)
                                <tr>
                                    <td>
                                        <span class="pill-id">
                                            #{{ ($tarifarios->firstItem() ?? 0) + $loop->index }}
                                        </span>
                                    </td>
                                    <td>{{ $t->servicio->nombre_servicio ?? '' }}</td>
                                    <td>{{ $t->destino->nombre_destino ?? '' }}</td>
                                    <td>
                                        @if($t->peso)
                                            {{ $t->peso->peso_inicial }} - {{ $t->peso->peso_final }}
                                        @endif
                                    </td>
                                    <td>{{ $t->origen->nombre_origen ?? '' }}</td>
                                    <td>{{ $t->precio }}</td>
                                    <td class="muted">{{ $t->observacion ?? '—' }}</td>
                                    <td class="muted small">{{ optional($t->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <button wire:click="openEditModal({{ $t->id }})"
                                            class="btn btn-sm btn-azul">
                                            Editar
                                        </button>
                                        <button wire:click="delete({{ $t->id }})"
                                            class="btn btn-sm btn-outline-azul"
                                            onclick="return confirm('Seguro que deseas eliminar este tarifario?')">
                                            Borrar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">Prueba con otro texto de busqueda.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $tarifarios->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tarifarioModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingId ? 'Editar tarifario' : 'Nuevo tarifario' }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Servicio</label>
                                <select wire:model.defer="servicio_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach($servicios as $s)
                                        <option value="{{ $s->id }}">{{ $s->nombre_servicio }}</option>
                                    @endforeach
                                </select>
                                @error('servicio_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Destino</label>
                                <select wire:model.defer="destino_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach($destinos as $d)
                                        <option value="{{ $d->id }}">{{ $d->nombre_destino }}</option>
                                    @endforeach
                                </select>
                                @error('destino_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Peso</label>
                                <select wire:model.defer="peso_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach($pesos as $p)
                                        <option value="{{ $p->id }}">
                                            {{ $p->peso_inicial }} - {{ $p->peso_final }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('peso_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Origen</label>
                                <select wire:model.defer="origen_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach($origenes as $o)
                                        <option value="{{ $o->id }}">{{ $o->nombre_origen }}</option>
                                    @endforeach
                                </select>
                                @error('origen_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Precio</label>
                                <input type="number" step="0.01" min="0" wire:model.defer="precio" class="form-control">
                                @error('precio') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Observacion</label>
                                <input type="text" wire:model.defer="observacion" class="form-control">
                                @error('observacion') <small class="text-danger">{{ $message }}</small> @enderror
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
    window.addEventListener('openTarifarioModal', () => {
        $('#tarifarioModal').modal('show');
    });

    window.addEventListener('closeTarifarioModal', () => {
        $('#tarifarioModal').modal('hide');
    });
</script>

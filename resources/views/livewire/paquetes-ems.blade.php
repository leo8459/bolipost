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

        .badge-estado{
            background: rgba(185,156,70,.15);
            color: var(--dorado);
            border: 1px solid rgba(185,156,70,.35);
            font-weight: 800;
            padding: 6px 10px;
            border-radius: 999px;
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
        .section-block{
            border:1px solid var(--line);
            border-radius:14px;
            padding:16px;
            margin-bottom:16px;
            background:#f9fafb;
        }
        .section-title{
            font-size:12px;
            letter-spacing:.08em;
            text-transform:uppercase;
            font-weight:800;
            color:var(--muted);
            margin-bottom:12px;
        }
        .badge-pill{
            background: rgba(185,156,70,.15);
            color: var(--dorado);
            border: 1px solid rgba(185,156,70,.35);
            font-weight: 800;
            padding: 4px 10px;
            border-radius: 999px;
            font-size:11px;
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
                    <h4 class="fw-bold mb-0">Paquetes EMS</h4>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar en toda la tabla..."
                        wire:model="search"
                    >
                    <button class="btn btn-outline-light2" type="button" wire:click="searchPaquetes">Buscar</button>
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
                        Total en pagina: <strong>{{ $paquetes->count() }}</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Codigo</th>
                                <th>Origen</th>
                                <th>Tipo</th>
                                <th>Servicio</th>
                                <th>Destino</th>
                                <th>Contenido</th>
                                <th>Cantidad</th>
                                <th>Peso</th>
                                <th>Precio</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Ciudad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                <tr>
                                    <td>
                                        <span class="pill-id">
                                            #{{ ($paquetes->firstItem() ?? 0) + $loop->index }}
                                        </span>
                                    </td>
                                    <td>{{ $paquete->codigo }}</td>
                                    <td>{{ $paquete->origen }}</td>
                                    <td>{{ $paquete->tipo_correspondencia }}</td>
                                    <td>{{ optional(optional($paquete->tarifario)->servicio)->nombre_servicio }}</td>
                                    <td>{{ optional(optional($paquete->tarifario)->destino)->nombre_destino }}</td>
                                    <td>{{ $paquete->contenido }}</td>
                                    <td>{{ $paquete->cantidad }}</td>
                                    <td>{{ $paquete->peso }}</td>
                                    <td>{{ $paquete->precio }}</td>
                                    <td>
                                        {{ $paquete->nombre_remitente }}
                                        <div class="text-muted" style="font-size: 12px;">
                                            {{ $paquete->telefono_remitente }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ $paquete->nombre_destinatario }}
                                        <div class="text-muted" style="font-size: 12px;">
                                            {{ $paquete->telefono_destinatario }}
                                        </div>
                                    </td>
                                    <td>{{ $paquete->ciudad }}</td>
                                    <td>
                                        <button wire:click="openEditModal({{ $paquete->id }})"
                                            class="btn btn-sm btn-azul">
                                            Editar
                                        </button>
                                        <button wire:click="delete({{ $paquete->id }})"
                                            class="btn btn-sm btn-outline-azul"
                                            onclick="return confirm('Seguro que deseas eliminar este paquete?')">
                                            Borrar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="14" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">Prueba con otro texto de busqueda.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $paquetes->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="paqueteModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingId ? 'Editar paquete' : 'Nuevo paquete' }}
                        </h5>
                        <span class="badge-pill">Formulario EMS</span>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="section-block">
                            <div class="section-title">Datos generales</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Servicio</label>
                                    <select wire:model.live="servicio_id" class="form-control">
                                        <option value="">Seleccione...</option>
                                        @foreach($servicios as $servicio)
                                            <option value="{{ $servicio->id }}">{{ $servicio->nombre_servicio }}</option>
                                        @endforeach
                                    </select>
                                    @error('servicio_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Destino</label>
                                    <select wire:model.live="destino_id" class="form-control">
                                        <option value="">Seleccione...</option>
                                        @foreach($destinos as $destino)
                                            <option value="{{ $destino->id }}">{{ $destino->nombre_destino }}</option>
                                        @endforeach
                                    </select>
                                    @error('destino_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Origen (automatico)</label>
                                    <input type="text" wire:model.defer="origen" class="form-control" readonly>
                                    @error('origen') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Tipo de correspondencia</label>
                                    <input type="text" wire:model.defer="tipo_correspondencia" class="form-control">
                                    @error('tipo_correspondencia') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-12">
                                    <label>Contenido</label>
                                    <textarea wire:model.defer="contenido" class="form-control" rows="2"></textarea>
                                    @error('contenido') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Cantidad</label>
                                    <input type="number" wire:model.defer="cantidad" class="form-control" min="1">
                                    @error('cantidad') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Peso</label>
                                    <input type="number" wire:model.live.debounce.300ms="peso" class="form-control" step="0.001" min="0">
                                    @error('peso') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Precio</label>
                                    <input type="number" wire:model.defer="precio" class="form-control" step="0.01" min="0" readonly>
                                    @error('precio') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Codigo</label>
                                    <input type="text" wire:model.defer="codigo" class="form-control">
                                    @error('codigo') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="section-block">
                            <div class="section-title">Datos del remitente</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre remitente</label>
                                    <input type="text" wire:model.defer="nombre_remitente" class="form-control">
                                    @error('nombre_remitente') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Telefono remitente</label>
                                    <input type="text" wire:model.defer="telefono_remitente" class="form-control">
                                    @error('telefono_remitente') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Carnet remitente</label>
                                    <input type="text" wire:model.defer="carnet" class="form-control">
                                    @error('carnet') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Nombre envia</label>
                                    <input type="text" wire:model.defer="nombre_envia" class="form-control">
                                    @error('nombre_envia') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="section-block">
                            <div class="section-title">Datos del destinatario</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre destinatario</label>
                                    <input type="text" wire:model.defer="nombre_destinatario" class="form-control">
                                    @error('nombre_destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Telefono destinatario</label>
                                    <input type="text" wire:model.defer="telefono_destinatario" class="form-control">
                                    @error('telefono_destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Ciudad destinatario</label>
                                    <select wire:model.defer="ciudad" class="form-control" disabled>
                                        <option value="">Seleccione...</option>
                                        @foreach($ciudades as $ciudadOpt)
                                            <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                                        @endforeach
                                    </select>
                                    @error('ciudad') <small class="text-danger">{{ $message }}</small> @enderror
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

    <div class="modal fade" id="paqueteConfirmModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar datos</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="section-block">
                        <div class="section-title">Resumen</div>
                        <div class="row">
                            <div class="col-md-6 mb-2"><strong>Servicio:</strong>
                                {{ optional($servicios->firstWhere('id', (int) $servicio_id))->nombre_servicio }}
                            </div>
                            <div class="col-md-6 mb-2"><strong>Destino:</strong>
                                {{ optional($destinos->firstWhere('id', (int) $destino_id))->nombre_destino }}
                            </div>
                            <div class="col-md-6 mb-2"><strong>Origen:</strong> {{ $origen }}</div>
                            <div class="col-md-6 mb-2"><strong>Tipo:</strong> {{ $tipo_correspondencia }}</div>
                            <div class="col-md-12 mb-2"><strong>Contenido:</strong> {{ $contenido }}</div>
                            <div class="col-md-4 mb-2"><strong>Cantidad:</strong> {{ $cantidad }}</div>
                            <div class="col-md-4 mb-2"><strong>Peso:</strong> {{ $peso }}</div>
                            <div class="col-md-4 mb-2"><strong>Precio:</strong> {{ $precio }}</div>
                            <div class="col-md-6 mb-2"><strong>Codigo:</strong> {{ $codigo }}</div>
                            <div class="col-md-6 mb-2"><strong>Ciudad:</strong> {{ $ciudad }}</div>
                            <div class="col-md-6 mb-2"><strong>Remitente:</strong> {{ $nombre_remitente }}</div>
                            <div class="col-md-6 mb-2"><strong>Telefono remitente:</strong> {{ $telefono_remitente }}</div>
                            <div class="col-md-6 mb-2"><strong>Destinatario:</strong> {{ $nombre_destinatario }}</div>
                            <div class="col-md-6 mb-2"><strong>Telefono destinatario:</strong> {{ $telefono_destinatario }}</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="saveConfirmed">
                        Confirmar y guardar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('openPaqueteModal', () => {
        $('#paqueteModal').modal('show');
    });

    window.addEventListener('closePaqueteModal', () => {
        $('#paqueteModal').modal('hide');
    });

    window.addEventListener('openPaqueteConfirm', () => {
        $('#paqueteConfirmModal').modal('show');
    });

    window.addEventListener('closePaqueteConfirm', () => {
        $('#paqueteConfirmModal').modal('hide');
    });
</script>

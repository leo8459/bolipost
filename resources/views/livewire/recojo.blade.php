<div>
    <style>
        :root{
            --azul:#34447C;
            --dorado:#B99C46;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .plantilla-wrap{ background: var(--bg); padding: 18px; border-radius: 16px; }
        .card-app{ border:0; border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.08); overflow:hidden; }
        .header-app{ background: linear-gradient(90deg, var(--azul), #2c3766); color:#fff; padding:18px 20px; }
        .search-input{ border-radius:12px; border:1px solid rgba(255,255,255,.45); padding:10px 12px; background: rgba(255,255,255,.95); }
        .btn-dorado{ background: var(--dorado); color:#fff; font-weight:800; border:none; border-radius:12px; padding:10px 14px; }
        .btn-dorado:hover{ filter:brightness(.95); color:#fff; }
        .btn-outline-light2{ border:1px solid rgba(255,255,255,.7); color:#fff; font-weight:800; border-radius:12px; padding:10px 14px; background:transparent; }
        .btn-outline-light2:hover{ background: rgba(255,255,255,.12); color:#fff; }
        .btn-azul{ background: var(--azul); color:#fff; font-weight:800; border:none; border-radius:12px; padding:10px 14px; }
        .btn-outline-azul{ border:1px solid rgba(52,68,124,.35); color: var(--azul); font-weight:800; border-radius:12px; padding:10px 14px; background:#fff; }
        .table thead th{ background: rgba(52,68,124,.08); color: var(--azul); font-weight:900; border-bottom:2px solid rgba(52,68,124,.2); white-space: nowrap; }
        .pill-id{ background: rgba(52,68,124,.12); color: var(--azul); font-weight:900; padding:4px 10px; border-radius:999px; display:inline-block; }
        .muted{ color:var(--muted); }
        .table td{ vertical-align: middle; }
        .modal-content{ border:0; border-radius:18px; box-shadow:0 20px 50px rgba(0,0,0,.2); }
        .modal-header{ background: linear-gradient(90deg, var(--azul), #2c3766); color:#fff; border-bottom:0; padding:16px 20px; }
        .modal-title{ font-weight:800; }
        .modal-body{ padding:20px; background:#fff; }
        .modal-footer{ border-top:1px solid var(--line); padding:14px 20px; background:#fafafa; }
        .form-control, .custom-select, select.form-control{
            border-radius:10px;
            border:1px solid #d1d5db;
            box-shadow:none;
        }
        .form-control:focus, select.form-control:focus{
            border-color: var(--azul);
            box-shadow:0 0 0 0.15rem rgba(52,68,124,.15);
        }
        .form-group label{ font-weight:700; color:#1f2937; }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <h4 class="fw-bold mb-0">Paquetes Contrato</h4>

                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control search-input" placeholder="Buscar..." wire:model="search">
                    <button class="btn btn-outline-light2" type="button" wire:click="searchRecojos">Buscar</button>
                    <a class="btn btn-outline-light2" href="{{ route('paquetes-contrato.reporte-hoy') }}" target="_blank">
                        Imprimir generados hoy
                    </a>
                    <a class="btn btn-dorado" href="{{ route('paquetes-contrato.create') }}">Nuevo</a>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success m-3">
                    <p class="mb-0">{{ session('success') }}</p>
                </div>
            @endif
            @if (session()->has('error'))
                <div class="alert alert-danger m-3">
                    <p class="mb-0">{{ session('error') }}</p>
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
                    <div class="muted small">Total en pagina: <strong>{{ $recojos->count() }}</strong></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Peso</th>
                                <th>Fecha recojo</th>
                                <th>Usuario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recojos as $recojo)
                                <tr>
                                    <td><span class="pill-id">{{ $recojo->codigo }}</span></td>
                                    <td>{{ $recojo->estado }}</td>
                                    <td>{{ $recojo->origen }}</td>
                                    <td>{{ $recojo->destino }}</td>
                                    <td>{{ $recojo->nombre_r }}</td>
                                    <td>{{ $recojo->nombre_d }}</td>
                                    <td>{{ $recojo->peso }}</td>
                                    <td>{{ optional($recojo->fecha_recojo)->format('d/m/Y') }}</td>
                                    <td>{{ optional($recojo->user)->name ?? '-' }}</td>
                                    <td>
                                        <a href="{{ route('paquetes-contrato.reporte', $recojo->id) }}"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-azul"
                                            title="Reimprimir rotulo">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <button wire:click="openEditModal({{ $recojo->id }})" class="btn btn-sm btn-azul" title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button wire:click="delete({{ $recojo->id }})" class="btn btn-sm btn-outline-azul"
                                            title="Eliminar" onclick="return confirm('Seguro que deseas eliminar este contrato?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">Prueba con otro texto de busqueda.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $recojos->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="recojoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Editar contrato' : 'Nuevo contrato' }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Usuario</label>
                                <select wire:model.defer="user_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                @error('user_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Codigo</label>
                                <input type="text" wire:model.defer="codigo" class="form-control">
                                @error('codigo') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Estado</label>
                                <input type="text" wire:model.defer="estado" class="form-control">
                                @error('estado') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Fecha recojo</label>
                                <input type="date" wire:model.defer="fecha_recojo" class="form-control">
                                @error('fecha_recojo') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Origen</label>
                                <input type="text" wire:model.defer="origen" class="form-control">
                                @error('origen') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Destino</label>
                                <input type="text" wire:model.defer="destino" class="form-control">
                                @error('destino') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Provincia</label>
                                <input type="text" wire:model.defer="provincia" class="form-control">
                                @error('provincia') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-3">
                                <label>Nombre remitente</label>
                                <input type="text" wire:model.defer="nombre_r" class="form-control">
                                @error('nombre_r') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Telefono remitente</label>
                                <input type="text" wire:model.defer="telefono_r" class="form-control">
                                @error('telefono_r') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Nombre destinatario</label>
                                <input type="text" wire:model.defer="nombre_d" class="form-control">
                                @error('nombre_d') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Telefono destinatario</label>
                                <input type="text" wire:model.defer="telefono_d" class="form-control">
                                @error('telefono_d') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Direccion remitente</label>
                                <input type="text" wire:model.defer="direccion_r" class="form-control">
                                @error('direccion_r') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Direccion destinatario</label>
                                <input type="text" wire:model.defer="direccion_d" class="form-control">
                                @error('direccion_d') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label>Peso</label>
                                <input type="number" step="0.001" min="0" wire:model.defer="peso" class="form-control">
                                @error('peso') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Mapa (URL o referencia)</label>
                                <input type="text" wire:model.defer="mapa" class="form-control">
                                @error('mapa') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Imagen (URL o referencia)</label>
                                <input type="text" wire:model.defer="imagen" class="form-control">
                                @error('imagen') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Contenido</label>
                            <textarea wire:model.defer="contenido" rows="2" class="form-control"></textarea>
                            @error('contenido') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group">
                            <label>Observacion</label>
                            <textarea wire:model.defer="observacion" rows="2" class="form-control"></textarea>
                            @error('observacion') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group mb-0">
                            <label>Justificacion</label>
                            <textarea wire:model.defer="justificacion" rows="2" class="form-control"></textarea>
                            @error('justificacion') <small class="text-danger">{{ $message }}</small> @enderror
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
    window.addEventListener('openRecojoModal', () => {
        $('#recojoModal').modal('show');
    });

    window.addEventListener('closeRecojoModal', () => {
        $('#recojoModal').modal('hide');
    });
</script>

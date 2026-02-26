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
        .btn-dorado{ background: var(--dorado); color:#fff; font-weight: 800; border:none; border-radius: 12px; padding: 10px 14px; }
        .btn-dorado:hover{ filter:brightness(.95); color:#fff; }
        .btn-outline-light2{ border:1px solid rgba(255,255,255,.7); color:#fff; font-weight:800; border-radius: 12px; padding: 10px 14px; background: transparent; }
        .btn-outline-light2:hover{ background: rgba(255,255,255,.12); color:#fff; }
        .btn-azul{ background: var(--azul); color:#fff; font-weight: 800; border:none; border-radius: 12px; padding: 6px 12px; }
        .btn-azul:hover{ filter:brightness(.95); color:#fff; }
        .btn-outline-azul{ border:1px solid rgba(52,68,124,.35); color: var(--azul); font-weight: 800; border-radius: 12px; padding: 6px 12px; background:#fff; }
        .btn-outline-azul:hover{ background: rgba(52,68,124,.06); color: var(--azul); }
        .table thead th{ background: rgba(52,68,124,.08); color: var(--azul); font-weight: 900; border-bottom: 2px solid rgba(52,68,124,.2); white-space: nowrap; }
        .pill-id{ background: rgba(52,68,124,.12); color: var(--azul); font-weight: 900; padding: 4px 10px; border-radius: 999px; display:inline-block; }
        .muted{ color:var(--muted); }
        .table td{ vertical-align: middle; }
        .modal-content{ border:0; border-radius:18px; box-shadow:0 20px 50px rgba(0,0,0,.2); }
        .modal-header{ background: linear-gradient(90deg, var(--azul), #2c3766); color:#fff; border-bottom:0; padding:16px 20px; }
        .modal-title{ font-weight:800; }
        .modal-body{ padding:20px; background:#fff; }
        .modal-footer{ border-top:1px solid var(--line); padding:14px 20px; background:#fafafa; }
        .form-control, .custom-select, select.form-control{ border-radius:10px; border:1px solid #d1d5db; box-shadow:none; }
        .uppercase-input{ text-transform: uppercase; }
        .form-control:focus, select.form-control:focus{ border-color: var(--azul); box-shadow:0 0 0 0.15rem rgba(52,68,124,.15); }
        .form-group label{ font-weight:700; color:#1f2937; }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">
                        {{ $this->isDespacho ? 'Despacho - Paquetes Ordinarios' : 'Paquetes Ordinarios - Clasificacion' }}
                    </h4>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control search-input" placeholder="Buscar..." wire:model="search">
                    <button class="btn btn-outline-light2" type="button" wire:click="searchPaquetes">Buscar</button>
                    @if ($this->isClasificacion)
                        <select wire:model="selectedCiudadMarcado" class="form-control search-input" style="min-width: 180px;">
                            <option value="">Marcar por ciudad</option>
                            @foreach ($ciudadesDisponibles as $ciudadDisponible)
                                <option value="{{ $ciudadDisponible }}">{{ $ciudadDisponible }}</option>
                            @endforeach
                        </select>
                        <button
                            class="btn btn-outline-light2"
                            type="button"
                            wire:click="despacharSeleccionados"
                            onclick="return confirm('Deseas despachar los paquetes seleccionados?')"
                        >
                            Despachar
                        </button>
                        <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
                    @endif
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
                                @if ($this->isClasificacion)
                                    <th>
                                        <input
                                            type="checkbox"
                                            wire:change="toggleSelectAll($event.target.checked)"
                                            @checked($selectAll)
                                            title="Marcar todos"
                                        >
                                    </th>
                                @endif
                                <th>Codigo</th>
                                <th>Destinatario</th>
                                <th>Telefono</th>
                                <th>Ciudad</th>
                                <th>Peso</th>
                                <th>Aduana</th>
                                <th>Observaciones</th>
                                <th>Ventanilla</th>
                                @if ($this->isDespacho)
                                    <th>Cod. Especial</th>
                                @endif
                                <th>Estado</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                <tr>
                                    @if ($this->isClasificacion)
                                        <td>
                                            <input type="checkbox" wire:key="select-{{ $paquete->id }}" value="{{ $paquete->id }}" wire:model="selectedPaquetes">
                                        </td>
                                    @endif
                                    <td><span class="pill-id">{{ $paquete->codigo }}</span></td>
                                    <td>{{ $paquete->destinatario }}</td>
                                    <td>{{ $paquete->telefono }}</td>
                                    <td>{{ $paquete->ciudad }}</td>
                                    <td>{{ $paquete->peso }}</td>
                                    <td>{{ $paquete->aduana }}</td>
                                    <td>{{ $paquete->observaciones ?? '-' }}</td>
                                    <td>{{ optional($paquete->ventanillaRef)->nombre_ventanilla ?? '-' }}</td>
                                    @if ($this->isDespacho)
                                        <td>{{ $paquete->cod_especial ?? '-' }}</td>
                                    @endif
                                    <td>{{ optional($paquete->estado)->nombre_estado ?? '-' }}</td>
                                    <td class="muted small">{{ optional($paquete->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <button wire:click="openEditModal({{ $paquete->id }})" class="btn btn-sm btn-azul">Editar</button>
                                        @if ($this->isClasificacion)
                                            <button wire:click="delete({{ $paquete->id }})" class="btn btn-sm btn-outline-azul" onclick="return confirm('Seguro que deseas eliminar este paquete?')">Borrar</button>
                                        @endif
                                        @if ($this->isDespacho)
                                            <button
                                                wire:click="devolverAClasificacion({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul"
                                                onclick="return confirm('Deseas devolver este paquete a CLASIFICACION?')"
                                            >
                                                Devolver
                                            </button>
                                        @endif
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
                    {{ $paquetes->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="paqueteOrdiModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Editar paquete ordinario' : 'Nuevo paquete ordinario' }}</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label>Codigo</label>
                                <input type="text" wire:model.defer="codigo" class="form-control uppercase-input">
                                @error('codigo') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Destinatario</label>
                                <input type="text" wire:model.defer="destinatario" class="form-control uppercase-input">
                                @error('destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Telefono</label>
                                <input type="text" wire:model.defer="telefono" class="form-control">
                                @error('telefono') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Ciudad</label>
                                <select wire:model="ciudad" wire:change="changeCiudad($event.target.value)" class="form-control uppercase-input">
                                    <option value="">Seleccione</option>
                                    <option value="LA PAZ">LA PAZ</option>
                                    <option value="COCHABAMBA">COCHABAMBA</option>
                                    <option value="SANTA CRUZ">SANTA CRUZ</option>
                                    <option value="ORURO">ORURO</option>
                                    <option value="POTOSI">POTOSI</option>
                                    <option value="SUCRE">SUCRE</option>
                                    <option value="TARIJA">TARIJA</option>
                                    <option value="TRINIDAD">TRINIDAD</option>
                                    <option value="COBIJA">COBIJA</option>
                                </select>
                                @error('ciudad') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Peso</label>
                                <input type="number" step="0.001" min="0" wire:model.defer="peso" class="form-control">
                                @error('peso') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Aduana</label>
                                <select wire:model.defer="aduana" class="form-control uppercase-input">
                                    <option value="">Seleccione</option>
                                    <option value="SI">SI</option>
                                    <option value="NO">NO</option>
                                </select>
                                @error('aduana') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Ventanilla</label>
                                <select wire:model.defer="fk_ventanilla" wire:key="ventanilla-{{ $ciudad ?: 'none' }}" class="form-control uppercase-input">
                                    <option value="">Seleccione</option>
                                    @foreach ($ventanillas as $ventanilla)
                                        <option value="{{ $ventanilla->id }}">{{ $ventanilla->nombre_ventanilla }}</option>
                                    @endforeach
                                </select>
                                @error('fk_ventanilla') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-12">
                                <label>Observaciones</label>
                                <textarea wire:model.defer="observaciones" class="form-control uppercase-input" rows="2"></textarea>
                                @error('observaciones') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
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
    document.addEventListener('input', (event) => {
        const target = event.target;
        if (target && target.classList && target.classList.contains('uppercase-input') && target.tagName === 'INPUT') {
            const start = target.selectionStart;
            const end = target.selectionEnd;
            target.value = target.value.toUpperCase();
            if (start !== null && end !== null) {
                target.setSelectionRange(start, end);
            }
        }
    });

    window.addEventListener('openPaqueteOrdiModal', () => {
        $('#paqueteOrdiModal').modal('show');
    });

    window.addEventListener('closePaqueteOrdiModal', () => {
        $('#paqueteOrdiModal').modal('hide');
    });
</script>

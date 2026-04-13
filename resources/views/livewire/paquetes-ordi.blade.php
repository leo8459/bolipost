<div>
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .plantilla-wrap{ background: var(--bg); padding: 18px; border-radius: 16px; }
        .card-app{ border:0; border-radius:16px; box-shadow:0 12px 26px rgba(0,0,0,.08); overflow:hidden; }
        .header-app{ background: linear-gradient(90deg, var(--azul), #20539A); color:#fff; padding:18px 20px; }
        .header-shell{ display:flex; justify-content:space-between; align-items:flex-start; gap:20px; }
        .header-main{ flex:1 1 280px; min-width:220px; }
        .header-tools{ flex:1 1 760px; min-width:320px; display:flex; flex-direction:column; align-items:stretch; gap:12px; }
        .header-search-row{ display:flex; justify-content:flex-end; }
        .header-search-cluster{ width:min(100%, 760px); display:flex; align-items:center; gap:10px; flex-wrap:nowrap; }
        .header-action-row{ display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; }
        .search-input{ border-radius:12px; border:1px solid rgba(255,255,255,.45); padding:10px 12px; background: rgba(255,255,255,.95); flex:1 1 auto; }
        .btn-dorado{ background: var(--dorado); color:#fff; font-weight: 800; border:none; border-radius: 12px; padding: 10px 14px; }
        .btn-dorado:hover{ filter:brightness(.95); color:#fff; }
        .btn-outline-light2{ border:1px solid rgba(255,255,255,.7); color:#fff; font-weight:800; border-radius: 12px; padding: 10px 14px; background: transparent; }
        .btn-outline-light2:hover{ background: rgba(255,255,255,.12); color:#fff; }
        .btn-azul{ background: var(--azul); color:#fff; font-weight: 800; border:none; border-radius: 12px; padding: 6px 12px; }
        .btn-azul:hover{ filter:brightness(.95); color:#fff; }
        .btn-outline-azul{ border:1px solid rgba(52,68,124,.35); color: var(--azul); font-weight: 800; border-radius: 12px; padding: 6px 12px; background:#fff; }
        .btn-outline-azul:hover{ background: rgba(52,68,124,.06); color: var(--azul); }
        .table thead th{ background: rgba(52,68,124,.08); color: var(--azul); font-weight: 900; border-bottom: 2px solid rgba(52,68,124,.2); white-space: nowrap; }
        .table-scroll-wrap{ border:1px solid #dbe2f2; border-radius:16px; overflow:hidden; background:#fff; }
        .table-responsive{ margin-bottom:0; }
        .table{ margin-bottom:0; }
        .table tbody td{ border-top:1px solid rgba(52,68,124,.10); }
        .pill-id{ background: rgba(52,68,124,.12); color: var(--azul); font-weight: 900; padding: 4px 10px; border-radius: 999px; display:inline-block; }
        .muted{ color:var(--muted); }
        .table td{ vertical-align: middle; }
        .action-cell{ width:72px; min-width:72px; text-align:center; }
        .action-stack{ display:flex; flex-direction:column; align-items:center; gap:8px; }
        .action-btn{ width:40px; height:40px; padding:0; display:inline-flex; align-items:center; justify-content:center; border-radius:11px; box-shadow:0 6px 16px rgba(32, 83, 154, .10); }
        .action-btn i{ font-size:14px; }
        .action-btn.btn-azul{ box-shadow:0 8px 18px rgba(32, 83, 154, .22); }
        .action-btn.btn-outline-azul{ background:#fff; border-color:rgba(32, 83, 154, .22); }
        .action-btn.btn-outline-azul:hover{ background:rgba(32, 83, 154, .06); }
        .modal-content{ border:0; border-radius:18px; box-shadow:0 20px 50px rgba(0,0,0,.2); }
        .modal-header{ background: linear-gradient(90deg, var(--azul), #20539A); color:#fff; border-bottom:0; padding:16px 20px; }
        .modal-title{ font-weight:800; }
        .modal-body{ padding:20px; background:#fff; }
        .modal-footer{ border-top:1px solid var(--line); padding:14px 20px; background:#fafafa; }
        .form-control, .custom-select, select.form-control{ border-radius:10px; border:1px solid #d1d5db; box-shadow:none; }
        .uppercase-input{ text-transform: uppercase; }
        .form-control:focus, select.form-control:focus{ border-color: var(--azul); box-shadow:0 0 0 0.15rem rgba(52,68,124,.15); }
        .form-group label{ font-weight:700; color:#1f2937; }
        @media (max-width: 991.98px){
            .header-shell{ flex-direction:column; }
            .header-tools{ min-width:0; width:100%; }
            .header-search-cluster{ width:100%; }
            .header-action-row{ justify-content:flex-start; }
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app">
                <div class="header-shell">
                <div class="header-main">
                    <h4 class="fw-bold mb-0">
                        @if ($this->isDespacho)
                            Despacho - Paquetes Ordinarios
                        @elseif ($this->isAlmacen)
                            Almacen - Paquetes Ordinarios
                        @elseif ($this->isEntregado)
                            Entregado - Paquetes Ordinarios
                        @elseif ($this->isRezago)
                            Rezago - Paquetes Ordinarios
                        @elseif ($this->isTodos)
                            Todos los Paquetes Ordinarios
                        @else
                            Paquetes Ordinarios - Clasificacion
                        @endif
                    </h4>
                </div>

                <div class="header-tools">
                    <div class="header-search-row">
                        <div class="header-search-cluster">
                            <input type="text" class="form-control search-input" placeholder="Buscar..." wire:model.live.debounce.300ms="search">
                            <button class="btn btn-outline-light2" type="button" wire:click="searchPaquetes">Buscar</button>
                            @if (($this->isClasificacion || $this->isAlmacen) && $canOrdiCreate)
                                <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
                            @endif
                        </div>
                    </div>
                    <div class="header-action-row">
                        @if ($this->isClasificacion)
                            <select wire:model="selectedCiudadMarcado" class="form-control search-input" style="min-width:180px; flex:0 0 190px;">
                                <option value="">Marcar por ciudad</option>
                                @foreach ($ciudadesDisponibles as $ciudadDisponible)
                                    <option value="{{ $ciudadDisponible }}">{{ $ciudadDisponible }}</option>
                                @endforeach
                            </select>
                            @if ($canOrdiAssign)
                            <button
                                class="btn btn-outline-light2"
                                type="button"
                                wire:click="despacharSeleccionados"
                                onclick="return confirm('Deseas despachar los paquetes seleccionados?')"
                            >
                                Despachar
                            </button>
                            @endif
                        @endif
                        @if ($this->isDespacho)
                            @if ($canOrdiPrint)
                            <button
                                class="btn btn-outline-light2"
                                type="button"
                                wire:click="$set('reprintCodEspecial', '')"
                                data-toggle="modal"
                                data-target="#reimprimirManifiestoModal"
                            >
                                Reimprimir Manifiesto
                            </button>
                            @endif
                        @endif
                        @if ($this->isAlmacen)
                            @if ($canOrdiReencaminar)
                            <button class="btn btn-outline-light2" type="button" wire:click="openReencaminarModal">
                                Reencaminar
                            </button>
                            @endif
                            @if ($canOrdiAssign)
                            <button class="btn btn-outline-light2" type="button" wire:click="openRecibirModal">
                                Recibir paquetes
                            </button>
                            @endif
                            @if ($canOrdiDropoff)
                            <button
                                class="btn btn-outline-light2"
                                type="button"
                                wire:click="bajaPaquetes"
                                wire:confirm="Deseas enviar a ENTREGADO los paquetes seleccionados?"
                            >
                                Baja de paquetes
                            </button>
                            @endif
                            @if ($canOrdiRezago)
                            <button
                                class="btn btn-outline-light2"
                                type="button"
                                wire:click="rezagoPaquetes"
                                wire:confirm="Deseas enviar a REZAGO los paquetes seleccionados?"
                            >
                                Rezago
                            </button>
                            @endif
                        @endif
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
                        Total en pagina: <strong>{{ $paquetes->count() }}</strong>
                    </div>
                </div>

                <div class="table-scroll-wrap">
                    <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                @if ($this->isClasificacion || $this->isAlmacen)
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
                                @if ($this->isAlmacen)
                                    <th>Zona</th>
                                @endif
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
                                    @if ($this->isClasificacion || $this->isAlmacen)
                                        <td>
                                            <input type="checkbox" wire:key="select-{{ $paquete->id }}" value="{{ $paquete->id }}" wire:model="selectedPaquetes">
                                        </td>
                                    @endif
                                    <td><span class="pill-id">{{ $paquete->codigo }}</span></td>
                                    <td>{{ $paquete->destinatario }}</td>
                                    <td>{{ $paquete->telefono }}</td>
                                    <td>{{ $paquete->ciudad }}</td>
                                    @if ($this->isAlmacen)
                                        <td>{{ $paquete->zona ?? '-' }}</td>
                                    @endif
                                    <td>{{ $paquete->peso }}</td>
                                    <td>{{ $paquete->aduana }}</td>
                                    <td>{{ $paquete->observaciones ?? '-' }}</td>
                                    <td>{{ optional($paquete->ventanillaRef)->nombre_ventanilla ?? '-' }}</td>
                                    @if ($this->isDespacho)
                                        <td>{{ $paquete->cod_especial ?? '-' }}</td>
                                    @endif
                                    <td>{{ optional($paquete->estado)->nombre_estado ?? '-' }}</td>
                                    <td class="muted small">{{ optional($paquete->created_at)->format('d/m/Y H:i') }}</td>
                                    <td class="action-cell">
                                        <div class="action-stack">
                                        @if ($canOrdiEdit)
                                        <button wire:click="openEditModal({{ $paquete->id }})" class="btn btn-sm btn-azul action-btn" title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        @if ($this->isAlmacen)
                                        <button wire:click="openZonaModal({{ $paquete->id }})" class="btn btn-sm btn-outline-azul action-btn" title="Editar zona">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </button>
                                        @endif
                                        @endif
                                        @if ($this->isClasificacion)
                                            @if ($canOrdiDelete)
                                            <button wire:click="delete({{ $paquete->id }})" class="btn btn-sm btn-outline-azul action-btn" onclick="return confirm('Seguro que deseas eliminar este paquete?')" title="Borrar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            @endif
                                        @endif
                                        @if ($this->isDespacho)
                                            @if ($canOrdiRestore)
                                            <button
                                                wire:click="devolverAClasificacion({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul action-btn"
                                                onclick="return confirm('Deseas devolver este paquete a CLASIFICACION?')"
                                                title="Devolver"
                                            >
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            @endif
                                        @endif
                                        @if ($this->isEntregado)
                                            @if ($canOrdiPrint)
                                            <button
                                                wire:click="reimprimirFormularioEntrega({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul action-btn"
                                                title="Reimprimir"
                                            >
                                                <i class="fas fa-print"></i>
                                            </button>
                                            @endif
                                            @if ($canOrdiRestore)
                                            <button
                                                wire:click="altaAAlmacen({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul action-btn"
                                                onclick="return confirm('Deseas dar de alta este paquete a ALMACEN?')"
                                                title="Dar alta"
                                            >
                                                <i class="fas fa-arrow-up"></i>
                                            </button>
                                            @endif
                                        @endif
                                        @if ($this->isRezago)
                                            @if ($canOrdiRestore)
                                            <button
                                                wire:click="devolverRezagoAAlmacen({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul action-btn"
                                                onclick="return confirm('Deseas devolver este paquete a ALMACEN?')"
                                                title="Devolver"
                                            >
                                                <i class="fas fa-undo"></i>
                                            </button>
                                            @endif
                                        @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $this->isClasificacion ? 12 : ($this->isAlmacen ? 13 : ($this->isDespacho ? 12 : 11)) }}" class="text-center py-5">
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
                                <input type="text" wire:model.live.debounce.300ms="destinatario" class="form-control uppercase-input">
                                @error('destinatario') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-6">
                                <label>Telefono</label>
                                <input type="text" wire:model.live.debounce.300ms="telefono" class="form-control">
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
                                @if(!$editingId)
                                    <x-peso-qz-field
                                        model="peso"
                                        input-id="peso-create-ordi"
                                        :required="true"
                                        :use-scale="true"
                                        :show-clear="true"
                                    />
                                @else
                                    <label>Peso</label>
                                    <input type="number" step="0.001" min="0" wire:model.defer="peso" class="form-control">
                                    @error('peso') <small class="text-danger">{{ $message }}</small> @enderror
                                @endif
                            </div>
                            @if ($editingId)
                            <div class="form-group col-md-6">
                                <label>Zona</label>
                                <input type="text" wire:model.defer="zona" class="form-control uppercase-input" list="zonas-list" placeholder="Seleccione o escriba una zona" autocomplete="off">
                                <datalist id="zonas-list">
                                    <option value="ACHACHICALA">
                                    <option value="ACHUMANI">
                                    <option value="ALTO OBRAJES">
                                    <option value="AUQUISAMAÑA">
                                    <option value="BELLA VISTA / BOLONIA">
                                    <option value="BUENOS AIRES">
                                    <option value="CALACOTO">
                                    <option value="CEMENTERIO">
                                    <option value="CENTRO">
                                    <option value="CIUDADELA FERROVIARIA">
                                    <option value="COTA COTA / CHASQUIPAMPA">
                                    <option value="EL ALTO">
                                    <option value="FLORIDA">
                                    <option value="IRPAVI">
                                    <option value="LA PORTADA">
                                    <option value="LLOJETA">
                                    <option value="LOS ANDES">
                                    <option value="LOS PINOS / SAN MIGUEL">
                                    <option value="MALLASILLA">
                                    <option value="MIRAFLORES">
                                    <option value="MUNAYPATA">
                                    <option value="OBRAJES">
                                    <option value="PAMPAHASSI">
                                    <option value="PASANKERI">
                                    <option value="PERIFERICA">
                                    <option value="PURA PURA">
                                    <option value="ROSARIO GRAN PODER">
                                    <option value="SAN PEDRO">
                                    <option value="SAN SEBASTIAN">
                                    <option value="SEGUENCOMA">
                                    <option value="SOPOCACHI">
                                    <option value="TEMBLADERANI">
                                    <option value="VILLA ARMONIA">
                                    <option value="VILLA COPACABANA">
                                    <option value="VILLA EL CARMEN">
                                    <option value="VILLA FATIMA">
                                    <option value="VILLA NUEVA POTOSI">
                                    <option value="VILLA PABON">
                                    <option value="VILLA SALOME">
                                    <option value="VILLA SAN ANTONIO">
                                    <option value="VILLA VICTORIA">
                                    <option value="VINO TINTO">
                                    <option value="ZONA NORTE">
                                    <option value="PROVINCIA">
                                    <option value="PG1A">
                                    <option value="PG2A">
                                    <option value="PG3A">
                                    <option value="PG4A">
                                    <option value="PG5A">
                                    <option value="PG1B">
                                    <option value="PG2B">
                                    <option value="PG3B">
                                    <option value="PG4B">
                                    <option value="PG5B">
                                    <option value="PG1C">
                                    <option value="PG2C">
                                    <option value="PG3C">
                                    <option value="PG4C">
                                    <option value="PG5C">
                                    <option value="PG1D">
                                    <option value="PG2D">
                                    <option value="PG3D">
                                    <option value="PG4D">
                                    <option value="PG5D">
                                    <option value="RETURN">
                                    <option value="DND">
                                </datalist>
                                @error('zona') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            @endif
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

    <div class="modal fade" id="reimprimirManifiestoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="reimprimirManifiesto">
                    <div class="modal-header">
                        <h5 class="modal-title">Reimprimir manifiesto</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label>Ingrese el cod_especial</label>
                            <input
                                type="text"
                                class="form-control uppercase-input"
                                placeholder="Ejemplo: O00001"
                                wire:model.defer="reprintCodEspecial"
                            >
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Reimprimir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="recibirModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form wire:submit.prevent="confirmarRecibir">
                    <div class="modal-header">
                        <h5 class="modal-title">Recibir paquetes (ENVIADO a RECIBIDO)</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex gap-2 align-items-center mb-3">
                            <input
                                type="text"
                                class="form-control uppercase-input"
                                placeholder="Ingrese codigo"
                                wire:model.defer="codigoRecibir"
                            >
                            <button type="button" class="btn btn-outline-azul" wire:click="addCodigoRecibir">
                                Agregar
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Destinatario</th>
                                        <th>Telefono</th>
                                        <th>Ciudad</th>
                                        <th>Zona</th>
                                        <th>Ventanilla</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($previewRecibirPaquetes as $paquete)
                                        <tr>
                                            <td>{{ $paquete->codigo }}</td>
                                            <td>{{ $paquete->destinatario }}</td>
                                            <td>{{ $paquete->telefono }}</td>
                                            <td>{{ $paquete->ciudad }}</td>
                                            <td>
                                                <input
                                                    type="text"
                                                    class="form-control form-control-sm uppercase-input"
                                                    placeholder="Zona"
                                                    wire:model.defer="previewRecibirZonas.{{ $paquete->id }}"
                                                    style="min-width: 90px;"
                                                >
                                            </td>
                                            <td>{{ optional($paquete->ventanillaRef)->nombre_ventanilla ?? '-' }}</td>
                                            <td>{{ optional($paquete->estado)->nombre_estado ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Sin paquetes en previsualizacion</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar recepcion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reencaminarModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form wire:submit.prevent="saveReencaminar">
                    <div class="modal-header">
                        <h5 class="modal-title">Reencaminar paquetes seleccionados</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Ciudad destino</label>
                            <select wire:model.defer="reencaminarCiudad" class="form-control uppercase-input">
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
                            @error('reencaminarCiudad') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Codigo</th>
                                        <th>Destinatario</th>
                                        <th>Telefono</th>
                                        <th>Ciudad actual</th>
                                        <th>Zona</th>
                                        <th>Ventanilla</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($previewReencaminarPaquetes as $paquete)
                                        <tr>
                                            <td>{{ $paquete->codigo }}</td>
                                            <td>{{ $paquete->destinatario }}</td>
                                            <td>{{ $paquete->telefono }}</td>
                                            <td>{{ $paquete->ciudad }}</td>
                                            <td>{{ $paquete->zona ?? '-' }}</td>
                                            <td>{{ optional($paquete->ventanillaRef)->nombre_ventanilla ?? '-' }}</td>
                                            <td>{{ optional($paquete->estado)->nombre_estado ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Sin paquetes seleccionados</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar actualizacion</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Actualizar Zona --}}
    <div class="modal fade" id="zonaModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form wire:submit.prevent="saveZona">
                    <div class="modal-header">
                        <h5 class="modal-title">Actualizar Zona</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group mb-0">
                            <label>Zona</label>
                            <input type="text" wire:model="zonaEditValue" class="form-control uppercase-input" list="zonas-list" placeholder="Seleccione o escriba una zona" autocomplete="off">
                            @error('zonaEditValue') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
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

    window.addEventListener('openRecibirModal', () => {
        $('#recibirModal').modal('show');
    });

    window.addEventListener('closeRecibirModal', () => {
        $('#recibirModal').modal('hide');
    });

    window.addEventListener('openReencaminarModal', () => {
        $('#reencaminarModal').modal('show');
    });

    window.addEventListener('closeReencaminarModal', () => {
        $('#reencaminarModal').modal('hide');
    });

    window.addEventListener('openZonaModal', () => {
        $('#zonaModal').modal('show');
    });

    window.addEventListener('closeZonaModal', () => {
        $('#zonaModal').modal('hide');
    });
</script>



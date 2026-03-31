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
        .modal-header{ background: linear-gradient(90deg, var(--azul), #20539A); color:#fff; border-bottom:0; padding:16px 20px; }
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

                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control search-input" placeholder="Buscar..." wire:model.live.debounce.300ms="search">
                    <button class="btn btn-outline-light2" type="button" wire:click="searchPaquetes">Buscar</button>
                    @if ($this->isClasificacion)
                        <select wire:model="selectedCiudadMarcado" class="form-control search-input" style="min-width: 180px;">
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
                    @if (($this->isClasificacion || $this->isAlmacen) && $canOrdiCreate)
                        <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
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
                                    <td>
                                        @if ($canOrdiEdit)
                                        <button wire:click="openEditModal({{ $paquete->id }})" class="btn btn-sm btn-azul">Editar</button>
                                        @if ($this->isAlmacen)
                                        <button wire:click="openZonaModal({{ $paquete->id }})" class="btn btn-sm btn-outline-azul">Editar Zona</button>
                                        @endif
                                        @endif
                                        @if ($this->isClasificacion)
                                            @if ($canOrdiDelete)
                                            <button wire:click="delete({{ $paquete->id }})" class="btn btn-sm btn-outline-azul" onclick="return confirm('Seguro que deseas eliminar este paquete?')">Borrar</button>
                                            @endif
                                        @endif
                                        @if ($this->isDespacho)
                                            @if ($canOrdiRestore)
                                            <button
                                                wire:click="devolverAClasificacion({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul"
                                                onclick="return confirm('Deseas devolver este paquete a CLASIFICACION?')"
                                            >
                                                Devolver
                                            </button>
                                            @endif
                                        @endif
                                        @if ($this->isEntregado)
                                            @if ($canOrdiPrint)
                                            <button
                                                wire:click="reimprimirFormularioEntrega({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul"
                                            >
                                                Reimprimir
                                            </button>
                                            @endif
                                            @if ($canOrdiRestore)
                                            <button
                                                wire:click="altaAAlmacen({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul"
                                                onclick="return confirm('Deseas dar de alta este paquete a ALMACEN?')"
                                            >
                                                Alta
                                            </button>
                                            @endif
                                        @endif
                                        @if ($this->isRezago)
                                            @if ($canOrdiRestore)
                                            <button
                                                wire:click="devolverRezagoAAlmacen({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul"
                                                onclick="return confirm('Deseas devolver este paquete a ALMACEN?')"
                                            >
                                                Devolver
                                            </button>
                                            @endif
                                        @endif
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
                                <select wire:model.defer="zona" class="form-control uppercase-input">
                                    <option value="">Seleccione</option>
                                    <option value="ACHACHICALA">ACHACHICALA</option>
                                    <option value="ACHUMANI">ACHUMANI</option>
                                    <option value="ALTO OBRAJES">ALTO OBRAJES</option>
                                    <option value="AUQUISAMAÑA">AUQUISAMAÑA</option>
                                    <option value="BELLA VISTA / BOLONIA">BELLA VISTA / BOLONIA</option>
                                    <option value="BUENOS AIRES">BUENOS AIRES</option>
                                    <option value="CALACOTO">CALACOTO</option>
                                    <option value="CEMENTERIO">CEMENTERIO</option>
                                    <option value="CENTRO">CENTRO</option>
                                    <option value="CIUDADELA FERROVIARIA">CIUDADELA FERROVIARIA</option>
                                    <option value="COTA COTA / CHASQUIPAMPA">COTA COTA / CHASQUIPAMPA</option>
                                    <option value="EL ALTO">EL ALTO</option>
                                    <option value="FLORIDA">FLORIDA</option>
                                    <option value="IRPAVI">IRPAVI</option>
                                    <option value="LA PORTADA">LA PORTADA</option>
                                    <option value="LLOJETA">LLOJETA</option>
                                    <option value="LOS ANDES">LOS ANDES</option>
                                    <option value="LOS PINOS / SAN MIGUEL">LOS PINOS / SAN MIGUEL</option>
                                    <option value="MALLASILLA">MALLASILLA</option>
                                    <option value="MIRAFLORES">MIRAFLORES</option>
                                    <option value="MUNAYPATA">MUNAYPATA</option>
                                    <option value="OBRAJES">OBRAJES</option>
                                    <option value="PAMPAHASSI">PAMPAHASSI</option>
                                    <option value="PASANKERI">PASANKERI</option>
                                    <option value="PERIFERICA">PERIFERICA</option>
                                    <option value="PURA PURA">PURA PURA</option>
                                    <option value="ROSARIO GRAN PODER">ROSARIO GRAN PODER</option>
                                    <option value="SAN PEDRO">SAN PEDRO</option>
                                    <option value="SAN SEBASTIAN">SAN SEBASTIAN</option>
                                    <option value="SEGUENCOMA">SEGUENCOMA</option>
                                    <option value="SOPOCACHI">SOPOCACHI</option>
                                    <option value="TEMBLADERANI">TEMBLADERANI</option>
                                    <option value="VILLA ARMONIA">VILLA ARMONIA</option>
                                    <option value="VILLA COPACABANA">VILLA COPACABANA</option>
                                    <option value="VILLA EL CARMEN">VILLA EL CARMEN</option>
                                    <option value="VILLA FATIMA">VILLA FATIMA</option>
                                    <option value="VILLA NUEVA POTOSI">VILLA NUEVA POTOSI</option>
                                    <option value="VILLA PABON">VILLA PABON</option>
                                    <option value="VILLA SALOME">VILLA SALOME</option>
                                    <option value="VILLA SAN ANTONIO">VILLA SAN ANTONIO</option>
                                    <option value="VILLA VICTORIA">VILLA VICTORIA</option>
                                    <option value="VINO TINTO">VINO TINTO</option>
                                    <option value="ZONA NORTE">ZONA NORTE</option>
                                    <option value="PROVINCIA">PROVINCIA</option>
                                    <option value="PG1A">PG1A</option>
                                    <option value="PG2A">PG2A</option>
                                    <option value="PG3A">PG3A</option>
                                    <option value="PG4A">PG4A</option>
                                    <option value="PG5A">PG5A</option>
                                    <option value="PG1B">PG1B</option>
                                    <option value="PG2B">PG2B</option>
                                    <option value="PG3B">PG3B</option>
                                    <option value="PG4B">PG4B</option>
                                    <option value="PG5B">PG5B</option>
                                    <option value="PG1C">PG1C</option>
                                    <option value="PG2C">PG2C</option>
                                    <option value="PG3C">PG3C</option>
                                    <option value="PG4C">PG4C</option>
                                    <option value="PG5C">PG5C</option>
                                    <option value="PG1D">PG1D</option>
                                    <option value="PG2D">PG2D</option>
                                    <option value="PG3D">PG3D</option>
                                    <option value="PG4D">PG4D</option>
                                    <option value="PG5D">PG5D</option>
                                    <option value="RETURN">RETURN</option>
                                    <option value="DND">DND</option>
                                </select>
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
                            <input type="text" wire:model="zonaEditValue" class="form-control uppercase-input" placeholder="Ingrese la zona">
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



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
        .header-meta{
            margin-top: 8px;
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }
        .header-meta-label{
            font-size: 12px;
            color: rgba(255,255,255,.8);
            font-weight: 700;
            letter-spacing: .02em;
        }
        .header-chip{
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(255,255,255,.45);
            color: #fff;
            font-weight: 800;
            font-size: 11px;
            border-radius: 999px;
            padding: 3px 10px;
            background: rgba(255,255,255,.1);
        }
        .header-city{
            font-size: 12px;
            color: rgba(255,255,255,.85);
            margin-left: 4px;
        }
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <div>
                    <h4 class="fw-bold mb-0">
                        @if ($this->isAlmacenEms)
                            Paquetes ALMACEN
                        @elseif ($this->isVentanillaEms)
                            Ventanilla EMS
                        @elseif ($this->isTransitoEms)
                            Recibir de regional ({{ $this->regionalEstadoLabel }})
                        @else
                            Paquetes EMS
                        @endif
                    </h4>
                    @php
                        $ciudadUsuarioHeader = strtoupper(trim((string) optional(auth()->user())->ciudad));
                    @endphp
                    <div class="header-meta">
                        <span class="header-meta-label">Estados visibles:</span>

                        @if ($this->isAlmacenEms)
                            @if ($this->almacenEstadoFiltro === 'ALMACEN')
                                <span class="header-chip">ALMACEN</span>
                            @elseif ($this->almacenEstadoFiltro === 'RECIBIDO')
                                <span class="header-chip">RECIBIDO</span>
                            @else
                                <span class="header-chip">ALMACEN</span>
                                <span class="header-chip">RECIBIDO</span>
                            @endif
                            <span class="header-city">
                                Ciudad aplicada: <strong>{{ $ciudadUsuarioHeader !== '' ? $ciudadUsuarioHeader : 'SIN CIUDAD' }}</strong>
                            </span>
                        @elseif ($this->isVentanillaEms)
                            <span class="header-chip">VENTANILLA EMS</span>
                        @elseif ($this->isTransitoEms)
                            <span class="header-chip">{{ $this->regionalEstadoLabel }}</span>
                        @else
                            <span class="header-chip">ADMISIONES</span>
                        @endif
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar en toda la tabla..."
                        wire:model="search"
                        wire:keydown.enter.prevent="searchPaquetes(true)"
                    >
                    <button class="btn btn-outline-light2" type="button" wire:click="searchPaquetes">Buscar</button>

                    @if ($this->isAdmision)
                        <button class="btn btn-outline-light2" type="button" wire:click="mandarSeleccionadosGeneradosHoy">
                            Generados hoy
                        </button>

                        <button class="btn btn-outline-light2" type="button" wire:click="mandarSeleccionadosSinFiltroFecha">
                            Mandar seleccionados
                        </button>
                    @elseif ($this->isAlmacenEms)
                        <button class="btn btn-outline-light2" type="button" wire:click="mandarSeleccionadosVentanillaEms">
                            Enviar a ventanilla EMS
                        </button>
                        <button class="btn btn-outline-light2" type="button" wire:click="openRegionalModal">
                            Manda a regional
                        </button>
                        <button class="btn btn-outline-light2" type="button" wire:click="toggleCn33Reprint">
                            Reimprimir CN-33
                        </button>
                    @elseif ($this->isVentanillaEms)
                        <button class="btn btn-outline-light2" type="button" wire:click="openEntregaVentanillaModal">
                            Entregar seleccionados
                        </button>
                    @elseif ($this->isTransitoEms)
                        <button class="btn btn-outline-light2" type="button" wire:click="openRecibirRegionalModal">
                            Recibir
                        </button>
                    @endif

                    @if ($this->isAdmision || $this->isAlmacenEms)
                        <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
                    @endif
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
                @if ($this->isAlmacenEms && $showCn33Reprint)
                    <div class="section-block mb-3">
                        <div class="section-title">Reimprimir CN-33</div>
                        <div class="form-row align-items-end">
                            <div class="form-group col-md-6 mb-2">
                                <label>Despacho</label>
                                <input
                                    type="text"
                                    class="form-control"
                                    placeholder="Ingresa cod_especial (ej: E00001)"
                                    wire:model.defer="cn33Despacho"
                                >
                            </div>
                            <div class="form-group col-md-6 mb-2 d-flex gap-2">
                                <button class="btn btn-azul" type="button" wire:click="reimprimirCn33">
                                    Imprimir CN-33
                                </button>
                                <button class="btn btn-outline-azul" type="button" wire:click="toggleCn33Reprint">
                                    Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                @endif

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            Resultados para: <strong>{{ $searchQuery }}</strong>
                        @else
                            Mostrando todos los registros
                        @endif
                    </div>
                    @if ($this->canSelect)
                        <div class="muted small">
                            Total en pagina: <strong>{{ $paquetes->count() }}</strong> |
                            Seleccionados: <strong>{{ count($selectedPaquetes) }}</strong>
                        </div>
                    @else
                        <div class="muted small">
                            Total en pagina: <strong>{{ $paquetes->count() }}</strong>
                        </div>
                    @endif
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                @if ($this->canSelect)
                                    <th></th>
                                @endif
                                <th>Codigo</th>
                                <th>Tipo</th>
                                <th>Serv. especial</th>
                                <th>Servicio</th>
                                <th>Destino</th>
                                <th>Contenido</th>
                                <th>Cantidad</th>
                                <th>Peso</th>
                                <th>Nombre remitente</th>
                                <th>Nombre envia</th>
                                <th>Carnet</th>
                                <th>Telefono remitente</th>
                                <th>Nombre destinatario</th>
                                <th>Telefono destinatario</th>
                                <th>Ciudad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $paquete)
                                @php
                                    $formulario = $paquete->formulario;
                                @endphp
                                <tr>
                                    @if ($this->canSelect)
                                        <td>
                                            <input type="checkbox" value="{{ $paquete->id }}" wire:model="selectedPaquetes">
                                        </td>
                                    @endif
                                    <td><span class="pill-id">{{ $paquete->codigo }}</span></td>
                                    <td>{{ $formulario->tipo_correspondencia ?? $paquete->tipo_correspondencia }}</td>
                                    <td>{{ $formulario->servicio_especial ?? $paquete->servicio_especial }}</td>
                                    <td>{{ $paquete->servicio_nombre ?? '' }}</td>
                                    <td>{{ $paquete->destino_nombre ?? '' }}</td>
                                    <td>{{ $formulario->contenido ?? $paquete->contenido }}</td>
                                    <td>{{ $formulario->cantidad ?? $paquete->cantidad }}</td>
                                    <td>{{ $formulario->peso ?? $paquete->peso }}</td>
                                    <td>{{ $formulario->nombre_remitente ?? $paquete->nombre_remitente }}</td>
                                    <td>{{ $formulario->nombre_envia ?? $paquete->nombre_envia }}</td>
                                    <td>{{ $formulario->carnet ?? $paquete->carnet }}</td>
                                    <td>{{ $formulario->telefono_remitente ?? $paquete->telefono_remitente }}</td>
                                    <td>{{ $formulario->nombre_destinatario ?? $paquete->nombre_destinatario }}</td>
                                    <td>{{ $formulario->telefono_destinatario ?? $paquete->telefono_destinatario }}</td>
                                    <td>{{ $formulario->ciudad ?? $paquete->ciudad }}</td>
                                    <td>
                                        <button wire:click="openEditModal({{ $paquete->id }})"
                                            class="btn btn-sm btn-azul"
                                            title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <a href="{{ route('paquetes-ems.boleta', $paquete->id) }}"
                                           class="btn btn-sm btn-outline-azul"
                                           target="_blank"
                                           title="Reimprimir boleta">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        @if ($this->isAlmacenEms)
                                            <button wire:click="devolverAAdmisiones({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul"
                                                title="Devolver a ADMISIONES"
                                                onclick="return confirm('Seguro que deseas devolver este paquete a ADMISIONES?')">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        @endif
                                        @if ($this->isAdmision)
                                            <button wire:click="delete({{ $paquete->id }})"
                                                class="btn btn-sm btn-outline-azul"
                                                title="Eliminar"
                                                onclick="return confirm('Seguro que deseas eliminar este paquete?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $this->canSelect ? 17 : 16 }}" class="text-center py-5">
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

                @if ($this->isAlmacenEms)
                    <div class="section-block mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-bold" style="color:var(--azul);">
                                Paquetes contrato en ALMACEN (misma ciudad)
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <button class="btn btn-outline-azul btn-sm" type="button" wire:click="openContratoRegistrarModal">
                                    Registrar
                                </button>
                                <button class="btn btn-outline-azul btn-sm" type="button" wire:click="openContratoPesoModal">
                                    Anadir peso
                                </button>
                                <button class="btn btn-outline-azul btn-sm" type="button" wire:click="openRegionalContratoModal">
                                    Manda contratos a regional
                                </button>
                                <div class="muted small">
                                    Total en pagina: <strong>{{ $contratosAlmacen ? $contratosAlmacen->count() : 0 }}</strong> |
                                    Seleccionados: <strong>{{ count($selectedContratos) }}</strong>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th>Codigo</th>
                                        <th>Cod. especial</th>
                                        <th>Estado</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Remitente</th>
                                        <th>Destinatario</th>
                                        <th>Empresa</th>
                                        <th>Telefono R</th>
                                        <th>Telefono D</th>
                                        <th>Creado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($contratosAlmacen ?? [] as $contrato)
                                        <tr>
                                            <td>
                                                <input type="checkbox" value="{{ $contrato->id }}" wire:model="selectedContratos">
                                            </td>
                                            <td><span class="pill-id">{{ $contrato->codigo }}</span></td>
                                            <td>{{ $contrato->cod_especial ?: '-' }}</td>
                                            <td>{{ optional($contrato->estadoRegistro)->nombre_estado ?? '-' }}</td>
                                            <td>{{ $contrato->origen }}</td>
                                            <td>{{ $contrato->destino }}</td>
                                            <td>{{ $contrato->nombre_r }}</td>
                                            <td>{{ $contrato->nombre_d }}</td>
                                            <td>
                                                {{ optional($contrato->empresa)->nombre ?? optional(optional($contrato->user)->empresa)->nombre ?? '-' }}
                                                @if(!empty(optional($contrato->empresa)->sigla))
                                                    ({{ optional($contrato->empresa)->sigla }})
                                                @elseif(!empty(optional(optional($contrato->user)->empresa)->sigla))
                                                    ({{ optional(optional($contrato->user)->empresa)->sigla }})
                                                @endif
                                            </td>
                                            <td>{{ $contrato->telefono_r }}</td>
                                            <td>{{ $contrato->telefono_d ?: '-' }}</td>
                                            <td>{{ optional($contrato->created_at)->format('d/m/Y H:i') }}</td>
                                            <td>
                                                <a href="{{ route('paquetes-contrato.reporte', $contrato->id) }}"
                                                   target="_blank"
                                                   class="btn btn-sm btn-outline-azul"
                                                   title="Reimprimir rotulo">
                                                    <i class="fas fa-print"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="13" class="text-center py-4">
                                                <div class="fw-bold" style="color:var(--azul);">No hay contratos en ALMACEN</div>
                                                <div class="muted">Se muestran solo contratos en estado ALMACEN y origen de tu ciudad.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if ($contratosAlmacen)
                            <div class="d-flex justify-content-end">
                                {{ $contratosAlmacen->links() }}
                            </div>
                        @endif
                    </div>
                @endif
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
                                @if (!$this->isAlmacenEms)
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
                                @endif
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
                                    <input
                                        type="text"
                                        wire:model.defer="tipo_correspondencia"
                                        class="form-control"
                                        @if($this->isAlmacenEms) readonly @endif
                                    >
                                    @error('tipo_correspondencia') <small class="text-danger">{{ $message }}</small> @enderror
                                    <small class="text-muted">Si es OFICIAL, se guarda sin precio ni tarifario.</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Servicio especial</label>
                                    <select wire:model.defer="servicio_especial" class="form-control">
                                        <option value="">Seleccione...</option>
                                        <option value="POR COBRAR">POR COBRAR</option>
                                        <option value="IDA Y VUELTA">IDA Y VUELTA</option>
                                    </select>
                                    @error('servicio_especial') <small class="text-danger">{{ $message }}</small> @enderror
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
                                    <input type="text" wire:model.defer="codigo" class="form-control" @if($auto_codigo) readonly @endif>
                                    @error('codigo') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-md-8 d-flex align-items-center" style="padding-top:28px;">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="autoCodigo" wire:model.live="auto_codigo">
                                        <label class="form-check-label" for="autoCodigo">
                                            Generar codigo automatico
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="section-block">
                            <div class="section-title">Datos del remitente</div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Nombre remitente</label>
                                    <input
                                        type="text"
                                        wire:model.live.debounce.300ms="nombre_remitente"
                                        class="form-control"
                                        list="remitentesEmsList"
                                        autocomplete="off"
                                    >
                                    <datalist id="remitentesEmsList">
                                        @foreach($remitenteSugerencias as $remitenteSugerido)
                                            <option value="{{ $remitenteSugerido }}"></option>
                                        @endforeach
                                    </datalist>
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
                                    <label>Direccion destinatario</label>
                                    <input type="text" wire:model.defer="direccion" class="form-control">
                                    @error('direccion') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
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
                                {{ $this->isAlmacenEms ? 'OFICIAL' : optional(collect($servicios)->firstWhere('id', (int) $servicio_id))->nombre_servicio }}
                            </div>
                            <div class="col-md-6 mb-2"><strong>Destino:</strong>
                                {{ optional(collect($destinos)->firstWhere('id', (int) $destino_id))->nombre_destino }}
                            </div>
                            <div class="col-md-6 mb-2"><strong>Origen:</strong> {{ $origen }}</div>
                            <div class="col-md-6 mb-2"><strong>Tipo:</strong> {{ $tipo_correspondencia }}</div>
                            <div class="col-md-12 mb-2"><strong>Contenido:</strong> {{ $contenido }}</div>
                            <div class="col-md-4 mb-2"><strong>Cantidad:</strong> {{ $cantidad }}</div>
                            <div class="col-md-4 mb-2"><strong>Peso:</strong> {{ $peso }}</div>
                            <div class="col-md-4 mb-2"><strong>Precio:</strong> {{ $precio_confirm ?? $precio }}</div>
                            <div class="col-md-6 mb-2"><strong>Codigo:</strong> {{ $codigo }}</div>
                            <div class="col-md-6 mb-2"><strong>Ciudad:</strong> {{ $ciudad }}</div>
                            <div class="col-md-6 mb-2"><strong>Remitente:</strong> {{ $nombre_remitente }}</div>
                            <div class="col-md-6 mb-2"><strong>Telefono remitente:</strong> {{ $telefono_remitente }}</div>
                            <div class="col-md-6 mb-2"><strong>Destinatario:</strong> {{ $nombre_destinatario }}</div>
                            <div class="col-md-6 mb-2"><strong>Telefono destinatario:</strong> {{ $telefono_destinatario }}</div>
                            <div class="col-md-12 mb-2"><strong>Direccion destinatario:</strong> {{ $direccion }}</div>
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

    <div class="modal fade" id="generadosHoyModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar generados hoy</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    @if ($generadosHoyCount > 0)
                        <p class="mb-0">
                            Se enviaran <strong>{{ $generadosHoyCount }}</strong> paquete(s) generados hoy desde
                            <strong>ADMISIONES</strong> a <strong>ALMACEN EMS</strong>.
                        </p>
                    @else
                        <p class="mb-0 text-muted">
                            No hay paquetes generados hoy en ADMISIONES para enviar.
                        </p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button
                        type="button"
                        class="btn btn-primary"
                        wire:click="confirmarMandarGeneradosHoy"
                        @if($generadosHoyCount <= 0) disabled @endif
                    >
                        Confirmar envio
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="regionalModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Ciudad destino regional</label>
                        <select wire:model.defer="regionalDestino" class="form-control">
                            <option value="">Seleccione...</option>
                            @foreach($ciudades as $ciudadOpt)
                                <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modo de transporte</label>
                        <select wire:model.defer="regionalTransportMode" class="form-control">
                            <option value="TERRESTRE">TERRESTRE</option>
                            <option value="AEREO">AEREO</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label>Nro de vuelo/transporte (opcional)</label>
                        <input type="text" wire:model.defer="regionalTransportNumber" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="mandarSeleccionadosRegional">
                        Confirmar y generar manifiesto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="regionalContratoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar contratos a regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Ciudad destino regional</label>
                        <select wire:model.defer="regionalDestinoContrato" class="form-control">
                            <option value="">Seleccione...</option>
                            @foreach($ciudades as $ciudadOpt)
                                <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modo de transporte</label>
                        <select wire:model.defer="regionalTransportModeContrato" class="form-control">
                            <option value="TERRESTRE">TERRESTRE</option>
                            <option value="AEREO">AEREO</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label>Nro de vuelo/transporte (opcional)</label>
                        <input type="text" wire:model.defer="regionalTransportNumberContrato" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="mandarSeleccionadosContratosRegional">
                        Confirmar y generar manifiesto
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="contratoRegistrarModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar contrato rapido</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Codigo</label>
                        <input
                            type="text"
                            class="form-control"
                            wire:model.defer="registroContratoCodigo"
                            placeholder="Ej: C0007A02011BO"
                        >
                        @error('registroContratoCodigo') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group">
                        <label>Origen (usuario logueado)</label>
                        <input type="text" class="form-control" wire:model="registroContratoOrigen" readonly>
                    </div>

                    <div class="form-group">
                        <label>Destino</label>
                        <select class="form-control" wire:model.defer="registroContratoDestino">
                            <option value="">Seleccione...</option>
                            @foreach($ciudades as $ciudadOpt)
                                <option value="{{ $ciudadOpt }}">{{ $ciudadOpt }}</option>
                            @endforeach
                        </select>
                        @error('registroContratoDestino') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group mb-0">
                        <label>Peso</label>
                        <input
                            type="number"
                            class="form-control"
                            wire:model.defer="registroContratoPeso"
                            step="0.001"
                            min="0.001"
                        >
                        @error('registroContratoPeso') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="registrarContratoRapido">
                        Registrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="contratoPesoModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Anadir peso a contrato</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Codigo</label>
                        <div class="d-flex gap-2">
                            <input
                                type="text"
                                class="form-control"
                                placeholder="Pega el codigo y presiona Enter"
                                wire:model.defer="contratoCodigoPeso"
                                wire:keydown.enter.prevent="buscarContratoParaPeso"
                            >
                            <button type="button" class="btn btn-outline-azul" wire:click="buscarContratoParaPeso">
                                Detectar
                            </button>
                        </div>
                        @error('contratoCodigoPeso') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    @if (!empty($contratoPesoResumen))
                        <div class="section-block mb-3">
                            <div class="section-title">Contrato detectado</div>
                            <div><strong>Codigo:</strong> {{ $contratoPesoResumen['codigo'] ?? '-' }}</div>
                            <div><strong>Cod. especial:</strong> {{ ($contratoPesoResumen['cod_especial'] ?? '') !== '' ? $contratoPesoResumen['cod_especial'] : '-' }}</div>
                            <div><strong>Origen:</strong> {{ $contratoPesoResumen['origen'] ?? '-' }}</div>
                            <div><strong>Remitente:</strong> {{ $contratoPesoResumen['remitente'] ?? '-' }}</div>
                            <div><strong>Destinatario:</strong> {{ $contratoPesoResumen['destinatario'] ?? '-' }}</div>
                        </div>
                    @endif

                    <div class="form-group">
                        <label>Peso</label>
                        <input type="number" class="form-control" wire:model.defer="contratoPeso" step="0.001" min="0.001">
                        @error('contratoPeso') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group mb-0">
                        <label>Destino (opcional)</label>
                        <input type="text" class="form-control" wire:model.defer="contratoDestino" placeholder="Solo si deseas cambiar destino">
                        @error('contratoDestino') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="guardarPesoContratoPorCodigo">
                        Guardar peso
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="entregaVentanillaModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar entrega</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Recibido por</label>
                        <input type="text" class="form-control" wire:model.defer="entregaRecibidoPor">
                        @error('entregaRecibidoPor') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group mb-0">
                        <label>Descripcion (opcional)</label>
                        <textarea class="form-control" rows="3" wire:model.defer="entregaDescripcion"></textarea>
                        @error('entregaDescripcion') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="confirmarEntregaVentanilla">
                        Confirmar entrega
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="recibirRegionalModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar recepcion regional</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        Paquetes a recibir: <strong>{{ count($recibirRegionalPreview) }}</strong>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Codigo</th>
                                    <th>Remitente</th>
                                    <th>Destinatario</th>
                                    <th>Ciudad</th>
                                    <th>Peso</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recibirRegionalPreview as $index => $item)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><span class="pill-id">{{ $item['codigo'] ?: 'SIN CODIGO' }}</span></td>
                                        <td>{{ $item['nombre_remitente'] ?: '-' }}</td>
                                        <td>{{ $item['nombre_destinatario'] ?: '-' }}</td>
                                        <td>{{ $item['ciudad'] ?: '-' }}</td>
                                        <td>{{ $item['peso'] ?: '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No hay paquetes seleccionados.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="recibirSeleccionadosRegional">
                        Confirmar recibido
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

    window.addEventListener('openGeneradosHoyModal', () => {
        $('#generadosHoyModal').modal('show');
    });

    window.addEventListener('closeGeneradosHoyModal', () => {
        $('#generadosHoyModal').modal('hide');
    });

    window.addEventListener('openRegionalModal', () => {
        $('#regionalModal').modal('show');
    });

    window.addEventListener('closeRegionalModal', () => {
        $('#regionalModal').modal('hide');
    });

    window.addEventListener('openRegionalContratoModal', () => {
        $('#regionalContratoModal').modal('show');
    });

    window.addEventListener('closeRegionalContratoModal', () => {
        $('#regionalContratoModal').modal('hide');
    });

    window.addEventListener('openContratoRegistrarModal', () => {
        $('#contratoRegistrarModal').modal('show');
    });

    window.addEventListener('closeContratoRegistrarModal', () => {
        $('#contratoRegistrarModal').modal('hide');
    });

    window.addEventListener('openContratoPesoModal', () => {
        $('#contratoPesoModal').modal('show');
    });

    window.addEventListener('closeContratoPesoModal', () => {
        $('#contratoPesoModal').modal('hide');
    });

    window.addEventListener('openEntregaVentanillaModal', () => {
        $('#entregaVentanillaModal').modal('show');
    });

    window.addEventListener('closeEntregaVentanillaModal', () => {
        $('#entregaVentanillaModal').modal('hide');
    });

    window.addEventListener('openRecibirRegionalModal', () => {
        $('#recibirRegionalModal').modal('show');
    });

    window.addEventListener('closeRecibirRegionalModal', () => {
        $('#recibirRegionalModal').modal('hide');
    });

</script>

<div>
    <style>
        :root{
            --siop-azul:#20539A;
            --siop-dorado:#FECC36;
            --siop-bg:#f4f7fb;
            --siop-line:#d9e3f0;
            --siop-muted:#64748b;
        }

        .siop-wrap{
            background:var(--siop-bg);
            padding:18px;
            border-radius:18px;
        }

        .siop-card{
            border:0;
            border-radius:18px;
            overflow:hidden;
            box-shadow:0 16px 36px rgba(32,83,154,.09);
        }

        .siop-header{
            background:linear-gradient(135deg, #163b72 0%, #20539A 55%, #2f6bbf 100%);
            color:#fff;
            padding:20px;
        }

        .siop-subtitle{
            color:rgba(255,255,255,.8);
            margin-top:4px;
        }

        .siop-btn-primary{
            background:var(--siop-dorado);
            color:#fff;
            border:0;
            border-radius:12px;
            padding:10px 16px;
            font-weight:800;
        }

        .siop-btn-primary:hover{
            color:#fff;
            filter:brightness(.97);
        }

        .siop-btn-secondary{
            background:#fff;
            color:var(--siop-azul);
            border:1px solid rgba(32,83,154,.18);
            border-radius:12px;
            padding:10px 16px;
            font-weight:800;
        }

        .siop-btn-secondary:hover{
            color:var(--siop-azul);
            background:#f8fbff;
        }

        .siop-filter-panel{
            background:#fff;
            border:1px solid var(--siop-line);
            border-radius:16px;
            padding:16px;
            margin:18px;
            box-shadow:0 10px 22px rgba(32,83,154,.06);
        }

        .siop-filter-grid{
            display:grid;
            grid-template-columns:minmax(220px, 1.5fr) minmax(180px, 1fr) 170px 170px auto auto;
            gap:10px;
            align-items:end;
        }

        .siop-field label{
            display:block;
            margin-bottom:6px;
            color:#334155;
            font-size:.82rem;
            font-weight:800;
        }

        .siop-field .form-control{
            min-height:42px;
            border-radius:12px;
        }

        .siop-summary{
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));
            gap:12px;
            padding:0 18px 18px;
        }

        .siop-summary-card{
            background:#fff;
            border:1px solid #dde7f3;
            border-radius:16px;
            padding:14px 16px;
            box-shadow:0 10px 18px rgba(32,83,154,.05);
        }

        .siop-summary-label{
            color:var(--siop-muted);
            font-size:.8rem;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.04em;
        }

        .siop-summary-value{
            color:var(--siop-azul);
            font-size:1.65rem;
            font-weight:900;
            line-height:1.1;
            margin-top:8px;
        }

        .siop-table thead th{
            background:#eef4fb;
            color:var(--siop-azul);
            font-weight:900;
            border-bottom:2px solid #d8e4f3;
            white-space:nowrap;
        }

        .siop-badge{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            border-radius:999px;
            padding:5px 10px;
            font-size:.75rem;
            font-weight:900;
            letter-spacing:.02em;
            background:rgba(32,83,154,.1);
            color:var(--siop-azul);
        }

        .siop-code{
            display:inline-block;
            padding:6px 11px;
            border-radius:999px;
            background:#edf4ff;
            color:#18427c;
            font-weight:900;
        }

        .siop-photo{
            width:70px;
            height:70px;
            object-fit:cover;
            border-radius:14px;
            border:1px solid #dbe6f4;
            background:#eef3fb;
            box-shadow:0 8px 16px rgba(32,83,154,.09);
        }

        .siop-photo-empty{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            width:70px;
            height:70px;
            border-radius:14px;
            border:1px dashed #c4d2e3;
            color:var(--siop-muted);
            background:#f8fafc;
            font-size:.72rem;
            text-align:center;
            line-height:1.15;
            padding:8px;
        }

        .siop-muted{
            color:var(--siop-muted);
        }

        .siop-modal .modal-content{
            border:0;
            border-radius:18px;
            box-shadow:0 20px 54px rgba(0,0,0,.2);
        }

        .siop-modal .modal-header{
            background:linear-gradient(135deg, #163b72 0%, #20539A 100%);
            color:#fff;
            border-bottom:0;
        }

        @media (max-width: 991.98px){
            .siop-filter-grid{
                grid-template-columns:1fr 1fr;
            }
        }

        @media (max-width: 767.98px){
            .siop-filter-grid{
                grid-template-columns:1fr;
            }
        }
    </style>

    <div class="siop-wrap">
        <div class="card siop-card">
            <div class="siop-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <h3 class="mb-0 font-weight-bold">Eventos SIOP</h3>
                    <div class="siop-subtitle">Busca y registra eventos de EMS, CERTI, ORDI, CONTRATO, DESPACHO y TIKTOKER desde una sola vista.</div>
                </div>

                @if ($canEventosCreate)
                    <button class="siop-btn-primary" type="button" wire:click="openCreateModal">
                        Nuevo evento
                    </button>
                @endif
            </div>

            <div class="siop-filter-panel">
                <div class="siop-filter-grid">
                    <div class="siop-field">
                        <label>Busqueda general</label>
                        <input
                            type="text"
                            class="form-control"
                            placeholder="Codigo, evento, usuario, cliente o servicio..."
                            wire:model="search"
                            wire:keydown.enter.prevent="searchRegistros"
                        >
                    </div>
                    <div class="siop-field">
                        <label>Tabla</label>
                        <select class="form-control" wire:model="source_filter">
                            <option value="">Todas</option>
                            @foreach ($sourceOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="siop-field">
                        <label>Desde</label>
                        <input type="date" class="form-control" wire:model="fecha_desde">
                    </div>
                    <div class="siop-field">
                        <label>Hasta</label>
                        <input type="date" class="form-control" wire:model="fecha_hasta">
                    </div>
                    <button class="siop-btn-primary" type="button" wire:click="searchRegistros">Filtrar</button>
                    <button class="siop-btn-secondary" type="button" wire:click="clearFilters">Limpiar</button>
                </div>
            </div>

            @if (session()->has('success'))
                <div class="alert alert-success mx-3 mb-0">
                    {{ session('success') }}
                </div>
            @endif

            <div class="siop-summary">
                @forelse ($resumen as $item)
                    <div class="siop-summary-card">
                        <div class="siop-summary-label">{{ $item->servicio }}</div>
                        <div class="siop-summary-value">{{ $item->total }}</div>
                    </div>
                @empty
                    <div class="siop-summary-card">
                        <div class="siop-summary-label">Sin resultados</div>
                        <div class="siop-summary-value">0</div>
                    </div>
                @endforelse
            </div>

            <div class="card-body pt-0">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div class="siop-muted">
                        @if ($searchQuery || $sourceFilterQuery || $fechaDesdeQuery || $fechaHastaQuery)
                            Resultados filtrados
                        @else
                            Mostrando todos los eventos disponibles menos auditoria
                        @endif
                    </div>
                    <div class="siop-muted small">
                        Total en pagina: <strong>{{ $registros->count() }}</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle siop-table">
                        <thead>
                            <tr>
                                <th>Tabla</th>
                                <th>Codigo</th>
                                <th>Evento</th>
                                <th>Actor</th>
                                <th>Foto</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registros as $registro)
                                <tr>
                                    <td>
                                        <span class="siop-badge">{{ $registro->servicio }}</span>
                                    </td>
                                    <td>
                                        <span class="siop-code">{{ $registro->codigo }}</span>
                                    </td>
                                    <td>{{ $registro->evento_nombre ?? ('#' . $registro->evento_id) }}</td>
                                    <td>
                                        <div class="font-weight-bold">{{ $registro->actor_nombre ?: '-' }}</div>
                                        @if (!empty($registro->usuario_nombre) && !empty($registro->cliente_nombre) && $registro->usuario_nombre !== $registro->cliente_nombre)
                                            <small class="siop-muted">Usuario: {{ $registro->usuario_nombre }} | Cliente: {{ $registro->cliente_nombre }}</small>
                                        @elseif (!empty($registro->cliente_nombre) && empty($registro->usuario_nombre))
                                            <small class="siop-muted">Cliente: {{ $registro->cliente_nombre }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $imagenUrl = !empty($registro->imagen) ? asset('storage/' . ltrim($registro->imagen, '/')) : null;
                                        @endphp
                                        @if ($imagenUrl)
                                            <a href="{{ $imagenUrl }}" target="_blank" rel="noopener">
                                                <img src="{{ $imagenUrl }}" alt="Foto del evento" class="siop-photo">
                                            </a>
                                        @else
                                            <span class="siop-photo-empty">Sin foto</span>
                                        @endif
                                    </td>
                                    <td class="siop-muted small">
                                        @if (!empty($registro->created_at))
                                            @php
                                                $creadoEn = \Illuminate\Support\Carbon::parse($registro->created_at)
                                                    ->timezone(config('app.timezone'))
                                                    ->locale('es');
                                            @endphp
                                            <div>{{ $creadoEn->format('d/m/Y H:i:s') }}</div>
                                            <div>{{ $creadoEn->diffForHumans() }}</div>
                                        @else
                                            Sin fecha
                                        @endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if (!empty($registro->reprint_url))
                                            <a
                                                href="{{ $registro->reprint_url }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="btn btn-sm btn-outline-success"
                                                title="Reimprimir"
                                            >
                                                <i class="fas fa-print"></i>
                                            </a>
                                        @endif
                                        @if ($canEventosEdit)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-primary"
                                                wire:click="openEditModal('{{ $registro->source_table }}', {{ $registro->record_id }})"
                                            >
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        @endif
                                        @if ($canEventosDelete)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-danger"
                                                wire:click="delete('{{ $registro->source_table }}', {{ $registro->record_id }})"
                                                onclick="return confirm('Seguro que deseas eliminar este registro?')"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="font-weight-bold" style="color:var(--siop-azul);">No hay eventos para mostrar</div>
                                        <div class="siop-muted">Prueba con otro codigo o quita algun filtro.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $registros->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade siop-modal" id="eventosSiopModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingId ? 'Editar evento SIOP' : 'Registrar evento SIOP' }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
                        <div class="form-group">
                            <label>Tabla de destino</label>
                            <select wire:model="source_table" class="form-control" @if($editingId) disabled @endif>
                                @foreach ($sourceOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('source_table') <small class="text-danger">{{ $message }}</small> @enderror
                            @if ($editingId)
                                <small class="text-muted">La tabla original del registro no se cambia al editar.</small>
                            @endif
                        </div>

                        <div class="form-group">
                            <label>Codigo</label>
                            <input type="text" wire:model.defer="codigo" class="form-control">
                            @error('codigo') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="form-group">
                            <label>Evento</label>
                            <select wire:model.defer="evento_id" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach ($eventos as $evento)
                                    <option value="{{ $evento->id }}">{{ $evento->nombre_evento }}</option>
                                @endforeach
                            </select>
                            @error('evento_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        <div class="form-group">
                            <label>Usuario</label>
                            <select wire:model.defer="user_id" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                                @endforeach
                            </select>
                            @error('user_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>

                        @if ($supportsClienteId)
                            <div class="form-group mb-0">
                                <label>Cliente</label>
                                <select wire:model.defer="cliente_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach ($clientes as $cliente)
                                        <option value="{{ $cliente->id }}">{{ $cliente->name }}</option>
                                    @endforeach
                                </select>
                                @error('cliente_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        @if (($editingId && $canEventosEdit) || (!$editingId && $canEventosCreate))
                            <button type="submit" class="btn btn-primary">
                                {{ $editingId ? 'Guardar cambios' : 'Registrar' }}
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('openEventosSiopModal', () => {
        $('#eventosSiopModal').modal('show');
    });

    window.addEventListener('closeEventosSiopModal', () => {
        $('#eventosSiopModal').modal('hide');
    });
</script>

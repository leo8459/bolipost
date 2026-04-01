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

        .contrato-preview-card{
            border:1px solid #d7e3f4;
            border-radius:16px;
            background:linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
            box-shadow:0 10px 22px rgba(32,83,154,.08);
            padding:18px;
            margin-bottom:18px;
        }
        .contrato-preview-grid{
            display:grid;
            grid-template-columns: minmax(220px, 320px) 1fr;
            gap:18px;
            align-items:start;
        }
        .contrato-preview-image{
            width:100%;
            max-height:280px;
            object-fit:cover;
            border-radius:14px;
            border:1px solid #dbe5f3;
            background:#eef3fb;
        }
        .contrato-preview-empty{
            min-height:220px;
            display:flex;
            align-items:center;
            justify-content:center;
            border:1px dashed #bfd0ea;
            border-radius:14px;
            color:#6b7280;
            background:#f8fbff;
            text-align:center;
            padding:18px;
        }
        .contrato-preview-meta{
            display:grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap:12px;
            margin-bottom:14px;
        }
        .contrato-preview-item{
            background:#fff;
            border:1px solid #e5edf8;
            border-radius:12px;
            padding:12px 14px;
        }
        .contrato-preview-label{
            font-size:.78rem;
            text-transform:uppercase;
            letter-spacing:.04em;
            color:#64748b;
            margin-bottom:4px;
            font-weight:800;
        }
        .contrato-preview-value{
            color:#1f2937;
            font-weight:700;
            word-break:break-word;
        }
        .contrato-preview-actions{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        @media (max-width: 767.98px){
            .contrato-preview-grid{
                grid-template-columns: 1fr;
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
                    <h4 class="fw-bold mb-0">{{ $config['title'] }}</h4>
                </div>

                <div class="d-flex gap-2 align-items-center">
                    <input
                        type="text"
                        class="form-control search-input"
                        placeholder="Buscar..."
                        wire:model="search"
                        wire:keydown.enter.prevent="searchRegistros"
                    >
                    <button class="btn btn-outline-light2" type="button" wire:click="searchRegistros">Buscar</button>
                    @if ($canEventosCreate)
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
                @if ($config['table'] === 'eventos_contrato' && !empty($searchQuery))
                    <div class="contrato-preview-card">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                            <div>
                                <div class="muted small">Paquete contrato encontrado</div>
                                <h5 class="mb-0" style="color:var(--azul); font-weight:900;">
                                    {{ $contratoBuscado->codigo ?? $searchQuery }}
                                </h5>
                            </div>
                        </div>

                        @if ($contratoBuscado)
                            @php
                                $contratoImagenUrl = !empty($contratoBuscado->imagen) ? asset('storage/' . $contratoBuscado->imagen) : null;
                            @endphp

                            <div class="contrato-preview-grid">
                                <div>
                                    @if ($contratoImagenUrl)
                                        <a href="{{ $contratoImagenUrl }}" target="_blank" rel="noopener">
                                            <img src="{{ $contratoImagenUrl }}" alt="Imagen del paquete contrato" class="contrato-preview-image">
                                        </a>
                                    @else
                                        <div class="contrato-preview-empty">
                                            Este paquete contrato no tiene imagen subida.
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <div class="contrato-preview-meta">
                                        <div class="contrato-preview-item">
                                            <div class="contrato-preview-label">Codigo</div>
                                            <div class="contrato-preview-value">{{ $contratoBuscado->codigo }}</div>
                                        </div>
                                        <div class="contrato-preview-item">
                                            <div class="contrato-preview-label">Cod. especial</div>
                                            <div class="contrato-preview-value">{{ $contratoBuscado->cod_especial ?: '-' }}</div>
                                        </div>
                                        <div class="contrato-preview-item">
                                            <div class="contrato-preview-label">Destinatario</div>
                                            <div class="contrato-preview-value">{{ $contratoBuscado->nombre_d ?: '-' }}</div>
                                        </div>
                                        <div class="contrato-preview-item">
                                            <div class="contrato-preview-label">Telefono</div>
                                            <div class="contrato-preview-value">{{ $contratoBuscado->telefono_d ?: '-' }}</div>
                                        </div>
                                        <div class="contrato-preview-item">
                                            <div class="contrato-preview-label">Destino</div>
                                            <div class="contrato-preview-value">{{ $contratoBuscado->destino ?: '-' }}</div>
                                        </div>
                                        <div class="contrato-preview-item">
                                            <div class="contrato-preview-label">Empresa</div>
                                            <div class="contrato-preview-value">
                                                {{ $contratoBuscado->empresa_nombre ?: '-' }}
                                                @if (!empty($contratoBuscado->empresa_sigla))
                                                    ({{ $contratoBuscado->empresa_sigla }})
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    @if ($contratoImagenUrl)
                                        <div class="contrato-preview-actions">
                                            <a href="{{ $contratoImagenUrl }}" target="_blank" rel="noopener" class="btn btn-outline-azul">
                                                Ver imagen
                                            </a>
                                            <a href="{{ $contratoImagenUrl }}" download class="btn btn-azul">
                                                Descargar imagen
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @else
                            <div class="alert alert-warning mb-0">
                                No se encontro un paquete en <strong>paquetes_contrato</strong> para la busqueda actual.
                            </div>
                        @endif
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
                    <div class="muted small">
                        Total en pagina: <strong>{{ $registros->count() }}</strong>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Evento</th>
                                <th>{{ $supportsClienteId ? 'Actor' : 'Usuario' }}</th>
                                <th>Momento de creacion</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($registros as $registro)
                                <tr>
                                    <td><span class="pill-id">{{ $registro->codigo }}</span></td>
                                    <td>{{ $registro->evento_nombre ?? ('#' . $registro->evento_id) }}</td>
                                    <td>
                                        @if ($supportsClienteId)
                                            {{ $registro->actor_nombre ?? ($registro->user_id ? ('#' . $registro->user_id) : ($registro->cliente_id ? ('Cliente #' . $registro->cliente_id) : '-')) }}
                                        @else
                                            {{ $registro->usuario_nombre ?? ('#' . $registro->user_id) }}
                                        @endif
                                    </td>
                                    <td class="muted small">
                                        @if(!empty($registro->created_at))
                                            @php
                                                $creadoEn = \Illuminate\Support\Carbon::parse($registro->created_at)
                                                    ->timezone(config('app.timezone'))
                                                    ->locale('es');
                                            @endphp
                                            <div>{{ $creadoEn->format('d/m/Y H:i:s') }}</div>
                                            <div class="text-muted">{{ $creadoEn->diffForHumans() }}</div>
                                        @else
                                            <span>Sin fecha</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($canEventosEdit)
                                        <button wire:click="openEditModal({{ $registro->id }})"
                                            class="btn btn-sm btn-azul"
                                            title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        @endif
                                        @if ($canEventosDelete)
                                        <button wire:click="delete({{ $registro->id }})"
                                            class="btn btn-sm btn-outline-azul"
                                            title="Eliminar"
                                            onclick="return confirm('Seguro que deseas eliminar este registro?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="fw-bold" style="color:var(--azul);">No hay registros</div>
                                        <div class="muted">Prueba con otro texto de busqueda.</div>
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

    <div class="modal fade" id="eventosTablaModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingId ? ('Editar ' . $config['singular']) : ('Nuevo ' . $config['singular']) }}
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <div class="modal-body">
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
                        <div class="form-group mb-0">
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
                            <div class="form-group mt-3 mb-0">
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
                                {{ $editingId ? 'Guardar cambios' : 'Crear' }}
                            </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.addEventListener('openEventosTablaModal', () => {
        $('#eventosTablaModal').modal('show');
    });

    window.addEventListener('closeEventosTablaModal', () => {
        $('#eventosTablaModal').modal('hide');
    });
</script>




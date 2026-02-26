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
    </style>

    <div class="plantilla-wrap">
        <div class="card card-app">
            <div class="header-app d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
                <h4 class="fw-bold mb-0">Generar codigos</h4>
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control search-input" placeholder="Buscar..." wire:model="search">
                    <button class="btn btn-outline-light2" type="button" wire:click="searchCodigos">Buscar</button>
                    <button class="btn btn-dorado" type="button" wire:click="openCreateModal">Nuevo</button>
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
            <div wire:loading.flex wire:target="ejecutarOperacion" class="alert alert-info m-3">
                <p class="mb-0"><strong>Generando codigos...</strong> por favor espera.</p>
            </div>

            <div class="card-body">
                <div class="section-block">
                    <div class="section-title">Operacion</div>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            <label>Tipo de operacion</label>
                            <select wire:model.live="operacion" wire:change="setOperacion($event.target.value)" class="form-control">
                                <option value="IMPRIMIR">Imprimir codigos</option>
                                <option value="REIMPRIMIR">Reimprimir codigo</option>
                                <option value="REPORTE">Reporte de codigos</option>
                            </select>
                        </div>

                        @if ($operacion === 'IMPRIMIR')
                            <div class="form-group col-md-4">
                                <label>Empresa</label>
                                <select wire:model.defer="operacion_empresa_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach($empresas as $empresa)
                                        <option value="{{ $empresa->id }}">{{ $empresa->codigo_cliente }} - {{ $empresa->nombre }} ({{ $empresa->sigla }})</option>
                                    @endforeach
                                </select>
                                @error('operacion_empresa_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-4">
                                <label>Cantidad</label>
                                <input type="number" min="1" max="1000" wire:model.defer="cantidad_generar" class="form-control">
                                @error('cantidad_generar') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        @elseif ($operacion === 'REIMPRIMIR')
                            <div class="form-group col-md-4">
                                <label>Empresa</label>
                                <select wire:model.defer="operacion_empresa_id" class="form-control">
                                    <option value="">Seleccione...</option>
                                    @foreach($empresas as $empresa)
                                        <option value="{{ $empresa->id }}">{{ $empresa->codigo_cliente }} - {{ $empresa->nombre }} ({{ $empresa->sigla }})</option>
                                    @endforeach
                                </select>
                                @error('operacion_empresa_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label>Desde</label>
                                <input type="number" min="1" max="99999" wire:model.defer="reimprimir_desde" class="form-control">
                                @error('reimprimir_desde') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-2">
                                <label>Hasta</label>
                                <input type="number" min="1" max="99999" wire:model.defer="reimprimir_hasta" class="form-control">
                                @error('reimprimir_hasta') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        @else
                            <div class="form-group col-md-4">
                                <label>Empresa (opcional)</label>
                                <select wire:model.defer="reporte_empresa_id" class="form-control">
                                    <option value="">Todas</option>
                                    @foreach($empresas as $empresa)
                                        <option value="{{ $empresa->id }}">{{ $empresa->codigo_cliente }} - {{ $empresa->nombre }} ({{ $empresa->sigla }})</option>
                                    @endforeach
                                </select>
                                @error('reporte_empresa_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Fecha desde</label>
                                <input type="date" wire:model.defer="reporte_fecha_desde" class="form-control">
                                @error('reporte_fecha_desde') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-md-3">
                                <label>Fecha hasta</label>
                                <input type="date" wire:model.defer="reporte_fecha_hasta" class="form-control">
                                @error('reporte_fecha_hasta') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        @endif

                        <div class="form-group col-md-2 d-flex align-items-end">
                            <button class="btn btn-azul w-100" type="button" wire:click="ejecutarOperacion"
                                wire:loading.attr="disabled" wire:target="ejecutarOperacion">
                                Ejecutar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="muted">
                        @if(!empty($searchQuery))
                            Resultados para: <strong>{{ $searchQuery }}</strong>
                        @else
                            Mostrando todos los registros
                        @endif
                    </div>
                    <div class="muted small">Total en pagina: <strong>{{ $codigos->count() }}</strong></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Barcode</th>
                                <th>Empresa</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($codigos as $registro)
                                <tr>
                                    <td><span class="pill-id">{{ $registro->codigo }}</span></td>
                                    <td>{{ $registro->barcode }}</td>
                                    <td>{{ optional($registro->empresa)->nombre ?? '-' }}</td>
                                    <td class="muted small">{{ optional($registro->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <button wire:click="openEditModal({{ $registro->id }})" class="btn btn-sm btn-azul" title="Editar">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button wire:click="delete({{ $registro->id }})" class="btn btn-sm btn-outline-azul"
                                            title="Eliminar" onclick="return confirm('Seguro que deseas eliminar este codigo?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
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
                    {{ $codigos->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="codigoEmpresaModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="save">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $editingId ? 'Editar codigo' : 'Nuevo codigo' }}</h5>
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
                            <label>Barcode</label>
                            <input type="text" wire:model.defer="barcode" class="form-control">
                            @error('barcode') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group mb-0">
                            <label>Empresa</label>
                            <select wire:model.defer="empresa_id" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($empresas as $empresa)
                                    <option value="{{ $empresa->id }}">{{ $empresa->codigo_cliente }} - {{ $empresa->nombre }} ({{ $empresa->sigla }})</option>
                                @endforeach
                            </select>
                            @error('empresa_id') <small class="text-danger">{{ $message }}</small> @enderror
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
    window.addEventListener('openCodigoEmpresaModal', () => {
        $('#codigoEmpresaModal').modal('show');
    });

    window.addEventListener('closeCodigoEmpresaModal', () => {
        $('#codigoEmpresaModal').modal('hide');
    });
</script>

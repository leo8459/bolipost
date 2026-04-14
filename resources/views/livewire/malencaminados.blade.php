<div>
    <style>
        :root{
            --azul:#20539A;
            --dorado:#FECC36;
            --bg:#f5f7fb;
            --line:#e5e7eb;
            --muted:#6b7280;
        }

        .page-wrap{
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

        .btn-dorado{
            background: var(--dorado);
            color:#fff;
            font-weight: 800;
            border:none;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .btn-azul{
            background: var(--azul);
            color:#fff;
            font-weight: 800;
            border:none;
            border-radius: 12px;
            padding: 10px 14px;
        }

        .btn-outline-azul{
            border:1px solid rgba(52,68,124,.35);
            color: var(--azul);
            font-weight: 800;
            border-radius: 12px;
            padding: 10px 14px;
            background:#fff;
        }

        .table thead th{
            background: rgba(52,68,124,.08);
            color: var(--azul);
            font-weight: 900;
            border-bottom: 2px solid rgba(52,68,124,.2);
            white-space: nowrap;
        }

        .muted{ color:var(--muted); }
        .table td{ vertical-align: middle; }
        .selectable-row{ cursor:pointer; }
        .selectable-row:hover{ background: rgba(32,83,154,.06); }
    </style>

    <div class="page-wrap">
        <div class="card card-app mb-4">
            <div class="header-app">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <h4 class="fw-bold mb-1">Malencaminados</h4>
                        <div class="small">Busca por codigo, presiona Enter, selecciona el paquete y cambia destino.</div>
                    </div>
                    <a href="{{ route('malencaminados.reporte') }}" class="btn btn-light btn-sm font-weight-bold">
                        Ver reporte
                    </a>
                </div>
            </div>

            <div class="card-body">
                @if (session()->has('success'))
                    <div class="alert alert-success">
                        <p class="mb-0">{{ session('success') }}</p>
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-5">
                        <label class="font-weight-bold">Buscar codigo</label>
                        <input
                            type="text"
                            class="form-control"
                            placeholder="Ej: EA123456789BO"
                            wire:model.defer="codigoBuscado"
                            wire:keydown.enter="buscarCodigo"
                        >
                        <small class="text-muted">Presiona Enter para buscar y seleccionar.</small>
                    </div>
                    <div class="col-md-3">
                        <label class="font-weight-bold">Destino actual</label>
                        <input type="text" class="form-control" value="{{ $destinoActual }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="font-weight-bold">Nuevo destino</label>
                        <select class="form-control" wire:model.defer="destinoNuevo">
                            <option value="">Seleccione...</option>
                            @foreach($destinos as $destino)
                                <option value="{{ $destino }}">{{ $destino }}</option>
                            @endforeach
                        </select>
                        @error('destinoNuevo') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-8">
                        <label class="font-weight-bold">Observacion</label>
                        <textarea class="form-control" rows="2" wire:model.defer="observacion" placeholder="Detalle del malencaminamiento..."></textarea>
                        @error('observacion') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button class="btn btn-dorado w-100" wire:click="guardarMalencaminado">
                            Guardar Malencaminado
                        </button>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-4">
                        <label class="font-weight-bold">Tipo seleccionado</label>
                        <input type="text" class="form-control" value="{{ $selectedTipo ?: '-' }}" readonly>
                    </div>
                    <div class="col-md-8">
                        <label class="font-weight-bold">Codigo seleccionado</label>
                        <input type="text" class="form-control" value="{{ $selectedCodigo ?: '-' }}" readonly>
                        @error('selectedTipo') <small class="text-danger">{{ $message }}</small> @enderror
                        @error('selectedCodigo') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="mt-4 d-none">
                    <div class="font-weight-bold mb-2">Todos los paquetes (EMS + Contrato)</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Codigo</th>
                                    <th>Destino</th>
                                    <th>Estado</th>
                                    <th>Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($paquetesDisponibles as $item)
                                    <tr class="selectable-row" wire:click="seleccionarCandidato('{{ $item->tipo }}', {{ (int) $item->source_id }})">
                                        <td>{{ $item->tipo }}</td>
                                        <td>{{ $item->codigo }}</td>
                                        <td>{{ $item->destino ?: '-' }}</td>
                                        <td>{{ (int) $item->estado }}</td>
                                        <td>
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-azul"
                                                wire:click.stop="seleccionarCandidato('{{ $item->tipo }}', {{ (int) $item->source_id }})"
                                            >Seleccionar</button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-3">No hay paquetes para mostrar.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-end">
                        {{ $paquetesDisponibles->links() }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-app">
            <div class="header-app d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Historial</h5>
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" class="form-control" placeholder="Buscar..." wire:model="search" style="max-width:220px;">
                    <button class="btn btn-azul" type="button" wire:click="searchRecords">Buscar</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Obs.</th>
                                <th>Nro malenc.</th>
                                <th>Origen tipo</th>
                                <th>Destino anterior</th>
                                <th>Destino nuevo</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $row)
                                <tr>
                                    <td>{{ $row->codigo }}</td>
                                    <td>{{ $row->observacion }}</td>
                                    <td>{{ (int) $row->malencaminamiento }}</td>
                                    <td>{{ $row->paquetes_ems_id ? 'EMS' : 'CONTRATO' }}</td>
                                    <td>{{ $row->destino_anterior ?: '-' }}</td>
                                    <td>{{ $row->destino_nuevo ?: '-' }}</td>
                                    <td class="muted small">{{ optional($row->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <button class="btn btn-sm btn-azul" wire:click="openEditModal({{ (int) $row->id }})">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <button
                                            class="btn btn-sm btn-outline-azul"
                                            wire:click="delete({{ (int) $row->id }})"
                                            onclick="return confirm('Seguro que deseas eliminar este registro?')"
                                        >
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">No hay registros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end">
                    {{ $records->links() }}
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="malencaminadoEditModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <form wire:submit.prevent="updateRecord">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Editar observacion</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Observacion</label>
                            <textarea class="form-control" rows="3" wire:model.defer="editObservacion"></textarea>
                            @error('editObservacion') <small class="text-danger">{{ $message }}</small> @enderror
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
    window.addEventListener('openMalencaminadoEditModal', () => {
        $('#malencaminadoEditModal').modal('show');
    });

    window.addEventListener('closeMalencaminadoEditModal', () => {
        $('#malencaminadoEditModal').modal('hide');
    });
</script>

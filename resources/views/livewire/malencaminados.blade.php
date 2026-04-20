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
        .step-title{
            font-size: .85rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--muted);
            font-weight: 800;
            margin-bottom: .35rem;
        }
        .soft-box{
            border:1px solid var(--line);
            border-radius: 12px;
            background:#fff;
            padding:12px;
        }
        .badge-tipo{
            font-weight: 800;
            border-radius: 999px;
            padding: .35rem .6rem;
            font-size: .72rem;
        }
        .badge-ems{ background:#dbeafe; color:#1e40af; }
        .badge-contrato{ background:#dcfce7; color:#166534; }
        .badge-certi{ background:#fef3c7; color:#92400e; }
        .badge-ordi{ background:#fce7f3; color:#9d174d; }
        .resumen-label{
            color:var(--muted);
            font-size:.78rem;
            font-weight:700;
            text-transform: uppercase;
            letter-spacing:.02em;
        }
        .resumen-value{
            font-weight:800;
            color:#111827;
        }
    </style>

    <div class="page-wrap">
        <div class="card card-app mb-4">
            <div class="header-app">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <h4 class="fw-bold mb-1">Malencaminados</h4>
                        <div class="small">Busca por codigo, presiona Enter, selecciona el paquete y cambia destino.</div>
                    </div>
                    @aclcan('report', null, 'malencaminados')
                        <a href="{{ route('malencaminados.reporte') }}" class="btn btn-light btn-sm font-weight-bold">
                            Ver reporte
                        </a>
                    @endaclcan
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
                        <div class="step-title">Paso 1. Buscar paquete</div>
                        <label class="font-weight-bold">Codigo</label>
                        <input
                            type="text"
                            class="form-control"
                            placeholder="Ej: EA123456789BO / CT260..."
                            wire:model.defer="codigoBuscado"
                            wire:keydown.enter="buscarCodigo"
                        >
                        <small class="text-muted">Presiona Enter para traer coincidencias y seleccionar.</small>
                    </div>
                    <div class="col-md-3">
                        <div class="step-title">Paso 2. Revisar</div>
                        <label class="font-weight-bold">Destino actual</label>
                        <input type="text" class="form-control" value="{{ $destinoActual }}" readonly>
                    </div>
                    <div class="col-md-4">
                        <div class="step-title">Paso 3. Corregir</div>
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

                @if(!empty($candidatos))
                    <div class="soft-box mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="font-weight-bold">Resultados de busqueda</div>
                            <small class="text-muted">{{ count($candidatos) }} coincidencia(s)</small>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Codigo</th>
                                        <th>Destino</th>
                                        <th>Estado</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($candidatos as $item)
                                        <tr>
                                            <td>
                                                @php($tipoItem = strtoupper((string)($item['tipo'] ?? '')))
                                                <span class="badge-tipo
                                                    @if($tipoItem === 'EMS') badge-ems
                                                    @elseif($tipoItem === 'CONTRATO') badge-contrato
                                                    @elseif($tipoItem === 'CERTI') badge-certi
                                                    @else badge-ordi
                                                    @endif
                                                ">
                                                    {{ $tipoItem }}
                                                </span>
                                            </td>
                                            <td class="font-weight-bold">{{ $item['codigo'] ?? '-' }}</td>
                                            <td>{{ $item['destino'] ?: '-' }}</td>
                                            <td>{{ (int) ($item['estado'] ?? 0) }}</td>
                                            <td class="text-right">
                                                <button type="button" class="btn btn-sm btn-outline-azul"
                                                    wire:click="seleccionarCandidato('{{ $item['tipo'] }}', {{ (int) $item['id'] }})">
                                                    Seleccionar
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <div class="row mt-3">
                    <div class="col-md-8">
                        <div class="step-title">Paso 4. Registrar</div>
                        <label class="font-weight-bold">Observacion</label>
                        <textarea class="form-control" rows="2" wire:model.defer="observacion" placeholder="Detalle del malencaminamiento..."></textarea>
                        @error('observacion') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        @aclcan('create', $this)
                            <button class="btn btn-dorado w-100" wire:click="guardarMalencaminado">
                                Guardar Malencaminado
                            </button>
                        @endaclcan
                    </div>
                </div>

                <div class="row mt-3 soft-box mx-0">
                    <div class="col-md-4">
                        <div class="resumen-label">Tipo seleccionado</div>
                        <div class="resumen-value mt-1">{{ $selectedTipo ?: '-' }}</div>
                    </div>
                    <div class="col-md-8">
                        <div class="resumen-label">Codigo seleccionado</div>
                        <div class="resumen-value mt-1">{{ $selectedCodigo ?: '-' }}</div>
                        @error('selectedTipo') <small class="text-danger">{{ $message }}</small> @enderror
                        @error('selectedCodigo') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="mt-4 d-none">
                    <div class="font-weight-bold mb-2">Todos los paquetes (EMS + CONTRATO + CERTI + ORDI)</div>
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
                                <th>Tipo</th>
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
                                    <td>
                                        @if($row->paquetes_ems_id)
                                            EMS
                                        @elseif($row->paquetes_contrato_id)
                                            CONTRATO
                                        @elseif($row->paquetes_certi_id)
                                            CERTI
                                        @elseif($row->paquetes_ordi_id)
                                            ORDI
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $row->destino_anterior ?: '-' }}</td>
                                    <td>{{ $row->destino_nuevo ?: '-' }}</td>
                                    <td class="muted small">{{ optional($row->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @aclcan('edit', $this)
                                            <button class="btn btn-sm btn-azul" wire:click="openEditModal({{ (int) $row->id }})">
                                                <i class="fas fa-pen"></i>
                                            </button>
                                        @endaclcan
                                        @aclcan('delete', $this)
                                            <button
                                                class="btn btn-sm btn-outline-azul"
                                                wire:click="delete({{ (int) $row->id }})"
                                                onclick="return confirm('Seguro que deseas eliminar este registro?')"
                                            >
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        @endaclcan
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
                        @aclcan('edit', $this)
                            <button type="submit" class="btn btn-primary">Guardar</button>
                        @endaclcan
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

<div>
    <style>
        .bp-select-like-vehicle {
            border-radius: 10px;
            min-height: calc(2.35rem + 2px);
            border: 1px solid #ced4da;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }

        .bp-select-like-vehicle:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
        }

        select.bp-select-like-vehicle {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding-right: 2.2rem;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 16 16'%3E%3Cpath fill='%236c757d' d='M2.646 5.646a.5.5 0 0 1 .708 0L8 10.293l4.646-4.647a.5.5 0 0 1 .708.708l-5 5a.5.5 0 0 1-.708 0l-5-5a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right .75rem center;
            background-size: 14px;
        }
    </style>

    <div class="page-title mb-4 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-tools me-2 text-primary"></i>Gestion de Mantenimiento
        </h1>
        @if(!$showForm)
            <button type="button" wire:click="create" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Registro de mantenimiento
            </button>
        @endif
    </div>

    @if (session('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($showForm)
        <div class="bp-gestiones-form-overlay">
        <div class="card shadow-sm mb-4 bp-gestiones-form-card">
            <div class="card-header">
                {{ $isEdit ? 'Editar Registro de Mantenimiento' : 'Registro de Mantenimiento' }}
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        @if((int) ($from_alert_id ?? 0) > 0)
                            <div class="col-12">
                                <div class="alert alert-info py-2 mb-0">
                                    Registro generado desde alerta #{{ $from_alert_id }}
                                </div>
                            </div>
                        @endif
                        @if((int) ($from_workshop_id ?? 0) > 0)
                            <div class="col-12">
                                <div class="alert alert-warning py-2 mb-0">
                                    Registro generado desde orden de taller #{{ $from_workshop_id }}. El vehiculo y el mantenimiento ya fueron seleccionados segun la entrega del taller.
                                </div>
                            </div>
                        @endif
                        <div class="col-12 col-md-4">
                            <label for="vehicle_id" class="form-label fw-bold">Vehiculo <span class="text-danger">*</span></label>
                            <select id="vehicle_id" wire:model="vehicle_id" class="form-select bp-select-like-vehicle @error('vehicle_id') is-invalid @enderror" required>
                                <option value="">Seleccionar vehiculo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">{{ $vehicle->placa }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">KM Actual del Vehiculo</label>
                            <input type="number" step="0.01" class="form-control bg-light" value="{{ $kilometraje_actual_vehiculo ?? '' }}" readonly>
                            <div class="form-text">Se carga automaticamente al seleccionar vehiculo.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="maintenance_type_id" class="form-label fw-bold">Tipo de Mantenimiento <span class="text-danger">*</span></label>
                            <select id="maintenance_type_id" wire:model.live="maintenance_type_id" class="form-select bp-select-like-vehicle @error('maintenance_type_id') is-invalid @enderror" required>
                                <option value="">Seleccionar tipo</option>
                                @foreach ($maintenanceTypes as $type)
                                    <option value="{{ $type->id }}">{{ $type->nombre }}</option>
                                @endforeach
                            </select>
                            @error('maintenance_type_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold">Cada Cuantos KM</label>
                            <input type="number" class="form-control" wire:model="cada_km" readonly>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold">Intervalo KM Inicio</label>
                            <input type="number" class="form-control" wire:model="intervalo_km_init" readonly>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold">Intervalo KM Fin</label>
                            <input type="number" class="form-control" wire:model="intervalo_km_fh">
                            <div class="form-text">Puede ajustar este kilometraje final manualmente.</div>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-bold">Alerta Previa (KM)</label>
                            <input type="number" class="form-control" wire:model="km_alerta_previa" readonly>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="fecha" class="form-label fw-bold">Fecha <span class="text-danger">*</span></label>
                            <input type="date" id="fecha" wire:model="fecha" class="form-control @error('fecha') is-invalid @enderror" required>
                            @error('fecha') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="kilometraje" class="form-label fw-bold">Kilometraje <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="kilometraje" wire:model="kilometraje" class="form-control @error('kilometraje') is-invalid @enderror" required>
                            <div class="form-text">
                                @if($tacometro_danado_vehiculo)
                                    Si el tacometro esta danado, puede conservar el ultimo kilometraje valido.
                                @elseif((int) ($from_alert_id ?? 0) > 0)
                                    Debe ser igual o mayor al KM actual del vehiculo.
                                @else
                                    Debe ser mayor al KM actual del vehiculo.
                                @endif
                            </div>
                            @error('kilometraje') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" id="tacometro_danado_vehiculo" wire:model="tacometro_danado_vehiculo" class="form-check-input">
                                <label class="form-check-label fw-bold" for="tacometro_danado_vehiculo">
                                    Marcar vehiculo con tacometro danado
                                </label>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="costo" class="form-label fw-bold">Costo <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" id="costo" wire:model="costo" class="form-control @error('costo') is-invalid @enderror" required>
                            @error('costo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="taller" class="form-label fw-bold">Taller</label>
                            <input type="text" id="taller" wire:model="taller" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="comprobante_file" class="form-label fw-bold">Comprobante</label>
                            <input type="file" id="comprobante_file" wire:model="comprobante_file" class="form-control @error('comprobante_file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            @error('comprobante_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($comprobante)
                                <div class="form-text">
                                    Archivo actual:
                                    @if($editingMaintenanceId)
                                        <a href="{{ route('maintenance-logs.comprobante', ['maintenanceLog' => $editingMaintenanceId]) }}" target="_blank" rel="noopener noreferrer">ver comprobante</a>
                                    @else
                                        <span class="text-muted">disponible al guardar</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-bold">Descripcion</label>
                            <textarea id="descripcion" wire:model="descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="observaciones" class="form-label fw-bold">Observaciones</label>
                            <textarea id="observaciones" wire:model="observaciones" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Guardar' }}
                        </button>
                        <button type="button" wire:click="cancelForm" class="btn btn-secondary">Volver al listado</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    @else
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <button
                        type="button"
                        wire:click="setTableView('pending')"
                        class="btn {{ $tableView === 'pending' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="fas fa-truck-ramp-box me-2"></i>Pendientes desde taller
                        <span class="badge {{ $tableView === 'pending' ? 'bg-light text-dark' : 'bg-primary ms-1' }}">{{ $pendingWorkshopRecords->count() }}</span>
                    </button>
                    <button
                        type="button"
                        wire:click="setTableView('history')"
                        class="btn {{ $tableView === 'history' ? 'btn-primary' : 'btn-outline-primary' }}">
                        <i class="fas fa-tools me-2"></i>Registros de mantenimiento
                        <span class="badge {{ $tableView === 'history' ? 'bg-light text-dark' : 'bg-primary ms-1' }}">{{ $maintenanceLogs->total() }}</span>
                    </button>
                </div>
            </div>
        </div>

        @if($tableView === 'pending')
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    Vehiculos entregados desde taller pendientes de registro
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>OT</th>
                                    <th>Vehiculo</th>
                                    <th>Taller</th>
                                    <th>Mantenimiento</th>
                                    <th>Fecha entrega</th>
                                    <th class="text-end">Costo</th>
                                    <th>Registro</th>
                                    <th class="text-center">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($pendingWorkshopRecords as $workshop)
                                    <tr>
                                        <td>{{ $workshop->order_number ?: ('#'.$workshop->id) }}</td>
                                        <td>{{ $workshop->vehicle?->display_name ?? ($workshop->vehicle?->placa ?? 'N/A') }}</td>
                                        <td>{{ $workshop->workshopCatalog?->nombre ?? $workshop->nombre_taller ?? 'Sin taller' }}</td>
                                        <td>{{ $workshop->maintenanceAlert?->maintenanceType?->nombre ?? $workshop->maintenanceAppointment?->tipoMantenimiento?->nombre ?? 'Mantenimiento realizado' }}</td>
                                        <td>{{ optional($workshop->fecha_salida)->format('d/m/Y') ?: optional($workshop->fecha_listo)->format('d/m/Y') ?: 'Pendiente' }}</td>
                                        <td class="text-end">BOB{{ number_format((float) ($workshop->total_cost ?? 0), 2) }}</td>
                                        <td>
                                            @if($workshop->maintenanceLog)
                                                <span class="badge bg-success">Registro #{{ $workshop->maintenanceLog->id }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Pendiente</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <button type="button" wire:click="registerFromWorkshop({{ $workshop->id }})" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-file-medical me-1"></i>{{ $workshop->maintenanceLog ? 'Abrir registro' : 'Registrar mantenimiento' }}
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if(!$pendingWorkshopRecords->count())
                        <div class="p-5 text-center text-muted">
                            <i class="fas fa-clipboard-check fa-3x mb-3 opacity-25"></i>
                            <h5>Sin vehiculos pendientes desde taller</h5>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        @if($tableView === 'history')
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por cualquier campo">
                </div>
                @if($maintenanceLogs->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Vehiculo</th>
                                    <th>Tipo</th>
                                    <th>Fecha</th>
                                    <th>Comprobante</th>
                                    <th class="text-end">Costo</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($maintenanceLogs as $log)
                                    <tr>
                                        <td>{{ $log->vehicle?->display_name ?? 'N/A' }}</td>
                                        <td>{{ $log->tipo }}</td>
                                        <td>{{ optional($log->fecha)->format('d/m/Y') }}</td>
                                        <td>
                                            @if($log->comprobante)
                                                <a href="{{ route('maintenance-logs.comprobante', ['maintenanceLog' => $log->id]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-file-invoice-dollar me-1"></i>Ver
                                                </a>
                                            @else
                                                <span class="text-muted">Sin archivo</span>
                                            @endif
                                        </td>
                                        <td class="text-end">BOB{{ number_format($log->costo, 2) }}</td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button wire:click="edit({{ $log->id }})" class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="delete({{ $log->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-tools fa-3x mb-3 opacity-25"></i>
                        <h5>Sin registros de mantenimiento</h5>
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-4">
            {{ $maintenanceLogs->links() }}
        </div>
        @endif
    @endif
</div>

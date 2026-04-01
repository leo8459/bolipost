<div>
    <style>
        .profile-card {
            border: 0;
            border-radius: 14px;
            overflow: hidden;
        }
        .profile-card .card-header {
            background: linear-gradient(120deg, #14532d 0%, #166534 100%);
            color: #fff;
            border: 0;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }
        .profile-field {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 10px 12px;
            background: #fff;
        }
        .profile-label {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            color: #6c757d;
            margin-bottom: 2px;
            letter-spacing: 0.04em;
        }
        .profile-value {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
        }
        .vehicle-confirm-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 20;
            border-radius: 14px;
        }
        .vehicle-confirm-card {
            width: min(760px, 100%);
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 22px 50px rgba(15, 23, 42, 0.28);
            overflow: hidden;
        }
    </style>

    <div class="page-title mb-4 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-car me-2 text-primary"></i>{{ auth()->user()?->role === 'conductor' ? 'Mi Vehiculo' : 'Gestion de Vehiculos' }}
        </h1>
        @if(!$showForm && auth()->user()?->role !== 'conductor')
            <button type="button" wire:click="create" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nuevo Vehiculo
            </button>
        @endif
    </div>

    @if (session('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(auth()->user()?->role === 'conductor' && !$showForm)
        <div class="card profile-card shadow-sm mb-4">
            <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fas fa-car-side me-2"></i>Mi Vehiculo Asignado</span>
                @php
                    $pendingAlerts = (int) ($assignedVehicle?->pending_maintenance_alerts_count ?? 0);
                @endphp
                @if($pendingAlerts > 0)
                    <span class="badge bg-warning text-dark px-3 py-2">En mantenimiento</span>
                @else
                    <span class="badge bg-light text-success px-3 py-2">
                        {{ ($assignedVehicle?->activo ?? false) ? 'Activo' : 'Inactivo' }}
                    </span>
                @endif
            </div>
            <div class="card-body">
                @if($assignedVehicle)
                    <div class="profile-grid">
                        <div class="profile-field"><span class="profile-label">Placa</span><span class="profile-value">{{ $assignedVehicle->placa ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Marca</span><span class="profile-value">{{ $assignedVehicle->brand?->nombre ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Modelo</span><span class="profile-value">{{ $assignedVehicle->modelo ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Formulario</span><span class="profile-value">{{ $assignedVehicle->maintenance_form_type_label ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Combustible</span><span class="profile-value">{{ $assignedVehicle->tipo_combustible ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Color</span><span class="profile-value">{{ $assignedVehicle->color ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Año</span><span class="profile-value">{{ $assignedVehicle->anio ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Tacometro</span><span class="profile-value">{{ ($assignedVehicle->tacometro_danado ?? false) ? 'Danado' : 'Operativo' }}</span></div>
                        <div class="profile-field"><span class="profile-label">KM Actual</span><span class="profile-value">{{ $assignedVehicle->kilometraje_actual ?? $assignedVehicle->kilometraje_inicial ?? $assignedVehicle->kilometraje ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Capacidad tanque</span><span class="profile-value">{{ $assignedVehicle->capacidad_tanque ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Tipo asignacion</span><span class="profile-value">{{ $currentAssignment?->tipo_asignacion ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Desde</span><span class="profile-value">{{ optional($currentAssignment?->fecha_inicio)->format('d/m/Y') ?? '-' }}</span></div>
                        <div class="profile-field"><span class="profile-label">Hasta</span><span class="profile-value">{{ optional($currentAssignment?->fecha_fin)->format('d/m/Y') ?? 'Sin fecha fin' }}</span></div>
                    </div>

                    <hr class="my-4">
                    <h6 class="fw-bold mb-3">
                        <i class="fas fa-wrench me-2 text-success"></i>Mantenimientos asignados/programados
                    </h6>
                    @if(($scheduledMaintenances ?? collect())->count())
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Mantenimiento</th>
                                        <th>Cada KM</th>
                                        <th>Programado</th>
                                        <th>Estado</th>
                                        <th class="text-end">Accion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($scheduledMaintenances as $maintenance)
                                        <tr>
                                            <td>{{ $maintenance['nombre'] }}</td>
                                            <td>{{ $maintenance['cada_km'] ? number_format($maintenance['cada_km']) . ' km' : '-' }}</td>
                                            <td>{{ $maintenance['fecha_programada'] ?? '-' }}</td>
                                            <td>
                                                @if($maintenance['fuente'] === 'programado')
                                                    <span class="badge bg-info text-dark">{{ $maintenance['estado'] ?? 'Programado' }}</span>
                                                @else
                                                    <span class="badge bg-secondary">Tipo asignado</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @if($maintenance['can_request'] ?? false)
                                                    <button type="button" class="btn btn-sm btn-success"
                                                            wire:click="requestMaintenance({{ $maintenance['maintenance_type_id'] }})">
                                                        <i class="fas fa-bell me-1"></i>Solicitar mantenimiento
                                                    </button>
                                                @elseif($maintenance['fuente'] === 'programado')
                                                    <span class="badge bg-info text-dark">Ya programado</span>
                                                @else
                                                    <span class="badge bg-warning text-dark">Solicitud pendiente</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="alert alert-light border mb-0">
                            No hay mantenimientos asignados/programados para este vehiculo.
                        </div>
                    @endif
                @else
                    <div class="text-muted">No tiene vehiculo asignado activo.</div>
                @endif
            </div>
        </div>
    @endif

    @if($showForm)
        <div class="bp-gestiones-form-overlay">
        <div class="card shadow-sm mb-4 bp-gestiones-form-card position-relative">
            <div class="card-header">{{ $isEdit ? 'Editar Vehiculo' : 'Nuevo Vehiculo' }}</div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Placa *</label>
                            <input type="text" wire:model="placa" class="form-control @error('placa') is-invalid @enderror" required>
                            @error('placa') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Marca *</label>
                            <select wire:model="marca_id" class="form-select @error('marca_id') is-invalid @enderror" required>
                                <option value="0">Seleccionar marca</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->nombre }}</option>
                                @endforeach
                            </select>
                            @error('marca_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Modelo *</label>
                            <input type="text" wire:model="modelo" class="form-control @error('modelo') is-invalid @enderror" required>
                            @error('modelo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Tipo Combustible *</label>
                            <select wire:model="tipo_combustible" class="form-select @error('tipo_combustible') is-invalid @enderror" required>
                                <option value="">Seleccionar tipo</option>
                                @foreach($fuelTypes as $fuelType)
                                    <option value="{{ $fuelType }}">{{ ucfirst($fuelType) }}</option>
                                @endforeach
                            </select>
                            @error('tipo_combustible') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Tipo de formulario mantenimiento *</label>
                            <select wire:model="maintenance_form_type" class="form-select @error('maintenance_form_type') is-invalid @enderror">
                                @foreach($maintenanceFormTypes as $formType)
                                    <option value="{{ $formType }}">{{ $formType === 'moto' ? 'Moto' : 'Vehiculo' }}</option>
                                @endforeach
                            </select>
                            @error('maintenance_form_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Color *</label>
                            <input type="text" wire:model="color" class="form-control @error('color') is-invalid @enderror" required>
                            @error('color') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Año</label>
                            <input type="number" wire:model="anio" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Kilometraje Inicial *</label>
                            <input type="number" step="0.01" wire:model="kilometraje" class="form-control @error('kilometraje') is-invalid @enderror" required>
                            @error('kilometraje') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Si ingresa danado, el sistema guardara 0 al crear el vehiculo.</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Capacidad Tanque *</label>
                            <input type="number" step="0.01" wire:model="capacidad_tanque" class="form-control @error('capacidad_tanque') is-invalid @enderror" required>
                            @error('capacidad_tanque') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="vehiculo_tacometro_danado" wire:model="tacometro_danado">
                                <label class="form-check-label" for="vehiculo_tacometro_danado">Tacometro danado</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="vehiculo_activo" wire:model="activo">
                                <label class="form-check-label" for="vehiculo_activo">Activo</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Guardar' }}</button>
                        <button type="button" wire:click="cancelForm" class="btn btn-secondary">Volver al listado</button>
                    </div>
                </form>
            </div>
            @if($showMaintenanceBackfillConfirm)
                <div class="vehicle-confirm-overlay">
                    <div class="vehicle-confirm-card">
                        <div class="card-header bg-warning text-dark fw-bold">
                            <i class="fas fa-triangle-exclamation me-2"></i>Advertencia de mantenimientos
                        </div>
                        <div class="card-body">
                            <p class="mb-3">
                                Este vehiculo no tiene informacion de si ya se le realizaron estos mantenimientos
                                y su kilometraje actual supera varios intervalos programados.
                                Decide si se asignaran o no se asignaran estos mantenimientos segun la lista.
                            </p>
                            @if(count($maintenanceBackfillPreview))
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Asignar</th>
                                                <th>Mantenimiento</th>
                                                <th>Cada KM</th>
                                                <th>KM objetivo</th>
                                                <th>KM excedidos</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($maintenanceBackfillPreview as $item)
                                                <tr>
                                                    <td>
                                                        <div class="form-check">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                wire:model="maintenanceBackfillSelections.{{ $item['key'] }}"
                                                                id="backfill-{{ $item['key'] }}">
                                                            <label class="form-check-label small" for="backfill-{{ $item['key'] }}">
                                                                Si
                                                            </label>
                                                        </div>
                                                    </td>
                                                    <td>{{ $item['nombre'] ?? '-' }}</td>
                                                    <td>{{ number_format((float) ($item['interval_km'] ?? 0), 0) }}</td>
                                                    <td>{{ number_format((float) ($item['target_km'] ?? 0), 0) }}</td>
                                                    <td>{{ number_format((float) ($item['overdue_km'] ?? 0), 2) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                        <div class="card-footer d-flex justify-content-end gap-2">
                            <button type="button" wire:click="cancelMaintenanceBackfill" class="btn btn-outline-secondary">No asignar</button>
                            <button type="button" wire:click="confirmMaintenanceBackfill" class="btn btn-warning text-dark">Guardar y aplicar seleccionados</button>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        </div>
    @elseif(auth()->user()?->role !== 'conductor')
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por cualquier campo">
                </div>
                @if($vehicles->count())
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Placa</th>
                                    <th>Marca</th>
                                    <th>Modelo</th>
                                    <th>Clase</th>
                                    <th>Formulario</th>
                                    <th>Tacometro</th>
                                    <th>Km Inicial</th>
                                    <th>Combustible</th>
                                    <th>Estado</th>
                                    @if(auth()->user()?->role !== 'conductor')
                                        <th class="text-center">Acciones</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vehicles as $vehicle)
                                    <tr>
                                        <td>{{ $vehicle->placa }}</td>
                                        <td>{{ $vehicle->brand?->nombre ?? $vehicle->marca ?? '-' }}</td>
                                        <td>{{ $vehicle->modelo }}</td>
                                        <td>{{ $vehicle->vehicleClass?->nombre ?? '-' }}</td>
                                        <td>{{ $vehicle->maintenance_form_type_label ?? '-' }}</td>
                                        <td>
                                            <span class="badge {{ ($vehicle->tacometro_danado ?? false) ? 'bg-danger' : 'bg-success' }}">
                                                {{ ($vehicle->tacometro_danado ?? false) ? 'Danado' : 'Operativo' }}
                                            </span>
                                        </td>
                                        <td>{{ number_format((float) ($vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje ?? 0), 2) }}</td>
                                        <td>{{ $vehicle->tipo_combustible }}</td>
                                        <td>
                                            @if((int) ($vehicle->pending_maintenance_alerts_count ?? 0) > 0)
                                                <span class="badge bg-warning text-dark">En mantenimiento</span>
                                            @else
                                                <span class="badge {{ $vehicle->activo ? 'bg-success' : 'bg-danger' }}">
                                                    {{ $vehicle->activo ? 'Activo' : 'Inactivo' }}
                                                </span>
                                            @endif
                                        </td>
                                        @if(auth()->user()?->role !== 'conductor')
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button wire:click="edit({{ $vehicle->id }})" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></button>
                                                    <button wire:click="delete({{ $vehicle->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-car fa-3x mb-3 opacity-25"></i>
                        <h5>No hay vehiculos registrados</h5>
                    </div>
                @endif
            </div>
        </div>
        <div class="mt-4">{{ $vehicles->links() }}</div>
    @else
        <div class="card shadow-sm">
            <div class="card-body text-muted">
                Esta vista muestra el vehiculo asignado segun la tabla de asignaciones.
            </div>
        </div>
    @endif
</div>

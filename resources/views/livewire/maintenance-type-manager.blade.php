<div class="bp-livewire-skin">
    @include('livewire.partials.button-theme')
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

        .bp-switch {
            display: flex;
            align-items: center;
            gap: .55rem;
        }
        .bp-switch .form-check-input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            width: 42px;
            min-width: 42px;
            height: 24px;
            margin-top: 0;
            border-radius: 999px;
            border: 2px solid #c8d2e1;
            background: #eef3f9;
            position: relative;
            cursor: pointer;
            transition: background-color .18s ease, border-color .18s ease, box-shadow .18s ease;
            box-shadow: none;
        }

        .bp-switch .form-check-input[type="checkbox"]::after {
            content: "";
            position: absolute;
            top: 2px;
            left: 2px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(22, 40, 74, .25);
            transition: transform .18s ease;
        }

        .bp-switch .form-check-input[type="checkbox"]:checked {
            background: #1e88ff;
            border-color: #1e88ff;
        }

        .bp-switch .form-check-input[type="checkbox"]:checked::after {
            transform: translateX(18px);
        }

        .bp-switch .form-check-input[type="checkbox"]:focus {
            box-shadow: 0 0 0 .2rem rgba(30, 136, 255, .18);
            outline: 0;
        }

        .plate-badge-btn {
            border: 0;
            cursor: pointer;
        }

        .maintenance-vehicle-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 1080;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .maintenance-vehicle-modal-card {
            width: min(720px, 100%);
            max-height: 90vh;
            overflow: auto;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.35);
        }
    </style>

    <div class="page-title mb-4 d-flex justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0">
            <i class="fas fa-wrench me-2 text-primary"></i>Gestion de Tipos de Mantenimiento
        </h1>
        @if(!$showForm)
            <button wire:click="openForm" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nuevo Tipo de Mantenimiento
            </button>
        @endif
    </div>

    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($showForm)
        <div class="bp-gestiones-form-overlay">
        <div class="card shadow-sm mb-4 bp-gestiones-form-card">
            <div class="card-header">
                {{ $isEdit ? 'Editar Tipo de Mantenimiento' : 'Nuevo Tipo de Mantenimiento' }}
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="nombre" class="form-label fw-bold">Nombre <span class="text-danger">*</span></label>
                            <input type="text" id="nombre" wire:model="nombre"
                                   class="form-control @error('nombre') is-invalid @enderror"
                                   placeholder="Ej: Cambio de aceite">
                            @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="maintenance_form_type" class="form-label fw-bold">Tipo de Vehiculo <span class="text-danger">*</span></label>
                            <select id="maintenance_form_type" wire:model.live="maintenance_form_type" class="form-control bp-select-like-vehicle @error('maintenance_form_type') is-invalid @enderror" required>
                                @foreach($maintenanceFormTypes as $formType)
                                    <option value="{{ $formType }}">{{ $formType === 'moto' ? 'Moto' : 'Vehiculo' }}</option>
                                @endforeach
                            </select>
                            @error('maintenance_form_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-lg-8">
                            <label for="selected_vehicle_ids" class="form-label fw-bold">{{ $maintenance_form_type === 'moto' ? 'Motos especificas para este mantenimiento' : 'Vehiculos especificos para este mantenimiento' }}</label>
                            <div class="row g-2">
                                <div class="col-12 col-md-6">
                                    <select id="selected_vehicle_ids" wire:model="vehicle_to_add" class="form-control bp-select-like-vehicle @error('vehicle_to_add') is-invalid @enderror">
                                        <option value="">{{ $maintenance_form_type === 'moto' ? 'Seleccionar moto para agregar' : 'Seleccionar vehiculo para agregar' }}</option>
                                        @foreach($vehicles as $vehicle)
                                            <option value="{{ $vehicle->id }}">{{ $vehicle->placa }}</option>
                                        @endforeach
                                    </select>
                                    @if($vehicles->isEmpty())
                                        <div class="form-text text-warning">No hay {{ $maintenance_form_type === 'moto' ? 'motos' : 'vehiculos' }} activos disponibles para este tipo.</div>
                                    @endif
                                </div>
                                <div class="col-12 col-md-6 d-flex gap-2">
                                    <button type="button" wire:click="addSelectedVehicle" class="btn btn-outline-primary flex-fill">
                                        <i class="fas fa-plus me-1"></i>Anadir
                                    </button>
                                    <button type="button" wire:click="addAllVisibleVehicles" class="btn btn-outline-warning flex-fill">
                                        <i class="fas fa-layer-group me-1"></i>Anadir todo
                                    </button>
                                    <button type="button" wire:click="clearSelectedVehicles" class="btn btn-outline-secondary flex-fill">
                                        Limpiar lista
                                    </button>
                                </div>
                            </div>
                            @error('vehicle_to_add') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            @error('selected_vehicle_ids') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-lg-4 d-flex align-items-end">
                            <div class="form-check bp-switch mb-2">
                                <input type="checkbox" id="es_preventivo" wire:model="es_preventivo" class="form-check-input">
                                <label class="form-check-label fw-bold" for="es_preventivo">Es mantenimiento preventivo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Vehiculos seleccionados</label>
                            <div class="d-flex flex-wrap gap-2">
                                @forelse($selectedVehicles as $selectedVehicle)
                                    <span class="badge bg-primary d-inline-flex align-items-center gap-2 px-3 py-2">
                                        {{ $selectedVehicle->placa }}
                                        <button type="button" wire:click="removeSelectedVehicle({{ $selectedVehicle->id }})" class="btn btn-sm btn-link text-white p-0 text-decoration-none">x</button>
                                    </span>
                                @empty
                                    <span class="text-muted small">Aun no seleccionaste vehiculos especificos.</span>
                                @endforelse
                            </div>
                            @if($selectedVehicles->count() > 0)
                                <div class="small text-success mt-2">Al guardar, todos estos vehiculos recibiran este mantenimiento.</div>
                            @endif
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="cada_km" class="form-label fw-bold">Cada Cuantos KM</label>
                            <div class="input-group">
                                <input type="number" id="cada_km" wire:model="cada_km"
                                       class="form-control @error('cada_km') is-invalid @enderror"
                                       placeholder="2000" min="1">
                                <span class="input-group-text">KM</span>
                            </div>
                            @error('cada_km') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                            <div class="form-text">Define el ciclo: 2000, 4000, 6000...</div>
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="intervalo_km_init" class="form-label fw-bold">KM Inicio</label>
                            <div class="input-group">
                                <input type="number" id="intervalo_km_init" wire:model="intervalo_km_init"
                                       class="form-control @error('intervalo_km_init') is-invalid @enderror"
                                       placeholder="5000">
                                <span class="input-group-text">KM</span>
                            </div>
                            @error('intervalo_km_init') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="intervalo_km_fh" class="form-label fw-bold">KM Fin</label>
                            <div class="input-group">
                                <input type="number" id="intervalo_km_fh" wire:model="intervalo_km_fh"
                                       class="form-control @error('intervalo_km_fh') is-invalid @enderror"
                                       placeholder="7000">
                                <span class="input-group-text">KM</span>
                            </div>
                            @error('intervalo_km_fh') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-3">
                            <label for="km_alerta_previa" class="form-label fw-bold">Alerta Previa</label>
                            <div class="input-group">
                                <input type="number" id="km_alerta_previa" wire:model="km_alerta_previa"
                                       class="form-control @error('km_alerta_previa') is-invalid @enderror"
                                       placeholder="15">
                                <span class="input-group-text">KM</span>
                            </div>
                            @error('km_alerta_previa') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-bold">Descripcion</label>
                            <textarea id="descripcion" wire:model="descripcion"
                                      class="form-control @error('descripcion') is-invalid @enderror"
                                      rows="3" placeholder="Detalles opcionales..."></textarea>
                            @error('descripcion') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Guardar' }}
                        </button>
                        <button type="button" wire:click="resetForm" class="btn btn-secondary px-4">Volver al listado</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por cualquier campo">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Impacto incentivo</th>
                                <th>Vehiculos asignados</th>
                                <th>Cada KM</th>
                                <th>Intervalo KM</th>
                                <th>Alerta Previa</th>
                                <th>Descripcion</th>
                                <th class="text-end px-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($types as $type)
                                <tr>
                                    <td class="fw-bold">{{ $type->nombre }}</td>
                                    <td>{{ $type->maintenance_form_type_label ?? 'Vehiculo' }}</td>
                                    <td>
                                        <span class="badge {{ ($type->es_preventivo ?? false) ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ ($type->es_preventivo ?? false) ? 'No descuenta estrellas' : 'Descuenta estrellas' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($type->vehicles->count() > 0)
                                            <div class="d-flex flex-wrap gap-1">
                                                @foreach($type->vehicles as $vehicle)
                                                    <button
                                                        type="button"
                                                        wire:click="openVehicleInfoModal({{ $vehicle->id }})"
                                                        class="badge bg-secondary plate-badge-btn"
                                                        title="Ver informacion del vehiculo {{ $vehicle->placa }}"
                                                    >
                                                        {{ $vehicle->placa }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-muted small">Todos los {{ ($type->maintenance_form_type ?? 'vehiculo') === 'moto' ? 'tipo moto' : 'tipo vehiculo' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ $type->cada_km ?? $type->intervalo_km ?? $type->intervalo_km_init ?? 'N/A' }} KM</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            {{ $type->intervalo_km_init ?? $type->intervalo_km ?? 'N/A' }} - {{ $type->intervalo_km_fh ?? $type->intervalo_km ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning text-dark">{{ $type->km_alerta_previa ?? 15 }} KM</span>
                                    </td>
                                    <td><small class="text-muted">{{ Str::limit($type->descripcion, 60) }}</small></td>
                                    <td class="text-end px-4">
                                        <button wire:click="edit({{ $type->id }})" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="confirm('Eliminar este tipo?') || event.stopImmediatePropagation()"
                                                wire:click="delete({{ $type->id }})" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No hay registros disponibles.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3">
            {{ $types->links() }}
        </div>
    @endif

    @if($showVehicleInfoModal)
        <div class="maintenance-vehicle-modal-backdrop" wire:click="closeVehicleInfoModal">
            <div class="maintenance-vehicle-modal-card card shadow-sm" wire:click.stop>
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-bold">
                        <i class="fas fa-car me-2 text-primary"></i>Informacion del vehiculo
                    </span>
                    <button type="button" class="btn-close" wire:click="closeVehicleInfoModal"></button>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold mb-1">Placa</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['placa'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold mb-1">Estado</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['estado'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold mb-1">Marca</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['marca'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold mb-1">Modelo</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['modelo'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold mb-1">Clase</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['clase'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold mb-1">Tipo formulario</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['formulario'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold mb-1">Combustible</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['combustible'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold mb-1">Color</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['color'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold mb-1">Año</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['anio'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold mb-1">Capacidad tanque</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['capacidad_tanque'] ?? '-' }}</div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold mb-1">Kilometraje</label>
                            <div class="form-control bg-light">{{ $vehicleInfo['kilometraje'] ?? '-' }}</div>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary" wire:click="closeVehicleInfoModal">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>

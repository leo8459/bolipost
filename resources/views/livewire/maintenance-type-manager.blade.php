<div>
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
                            <label for="vehicle_class_id" class="form-label fw-bold">Clase de Vehiculo (global)</label>
                            <select id="vehicle_class_id" wire:model="vehicle_class_id" class="form-select">
                                <option value="">Todas las clases (regla general)</option>
                                @foreach($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Ejemplo: Toyota 2025 y Honda 2023 pueden tener intervalos distintos.</div>
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
                                <th>Clase de Vehiculo</th>
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
                                    <td>{{ $type->vehicleClass?->nombre ?? 'General' }}</td>
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
                                    <td colspan="7" class="text-center py-4 text-muted">No hay registros disponibles.</td>
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
</div>

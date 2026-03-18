<div>
    <div class="page-title mb-4 d-flex justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0">
            <i class="fas fa-link me-2 text-primary"></i>Gestion de Asignaciones de Vehiculos
        </h1>
        @if(!$showForm)
            <button wire:click="openForm" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nueva Asignacion
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
                {{ $isEdit ? 'Editar Asignacion' : 'Nueva Asignacion' }}
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Conductor <span class="text-danger">*</span></label>
                            <select wire:model="driver_id" class="form-select @error('driver_id') is-invalid @enderror">
                                <option value="0">Seleccionar conductor</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                                @endforeach
                            </select>
                            @error('driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Vehiculo <span class="text-danger">*</span></label>
                            <select wire:model="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror">
                                <option value="0">Seleccionar vehiculo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">
                                        {{ $vehicle->placa }} | {{ $vehicle->marca }} {{ $vehicle->modelo }} | {{ $vehicle->color ?? 'S/COLOR' }} | {{ $vehicle->anio ?? 'S/ANIO' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Tipo de Asignacion</label>
                            <select wire:model="tipo_asignacion" class="form-select">
                                <option value="Fijo">Fijo</option>
                                <option value="Temporal">Temporal/Prestamo</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Fecha Inicio <span class="text-danger">*</span></label>
                            <input type="date" wire:model="fecha_inicio" class="form-control @error('fecha_inicio') is-invalid @enderror">
                            @error('fecha_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Fecha Fin</label>
                            <input type="date" wire:model="fecha_fin" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" id="activo" wire:model="activo" class="form-check-input">
                                <label class="form-check-label" for="activo">Asignacion activa</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar' : 'Crear Asignacion' }}
                        </button>
                        <button type="button" wire:click="resetForm" class="btn btn-secondary">Volver al listado</button>
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
                                <th>Conductor</th>
                                <th>Vehiculo</th>
                                <th>Tipo</th>
                                <th>Inicio</th>
                                <th>Estado</th>
                                <th class="text-end px-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr>
                                    <td>{{ $assignment->driver?->nombre }}</td>
                                    <td><span class="badge bg-dark">{{ $assignment->vehicle?->placa }}</span></td>
                                    <td>{{ $assignment->tipo_asignacion }}</td>
                                    <td>{{ optional($assignment->fecha_inicio)->format('d/m/Y') }}</td>
                                    <td>
                                        <span class="badge {{ $assignment->activo ? 'bg-success' : 'bg-danger' }}">
                                            {{ $assignment->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="text-end px-4">
                                        <button wire:click="edit({{ $assignment->id }})" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="confirm('Eliminar asignacion?') || event.stopImmediatePropagation()"
                                                wire:click="delete({{ $assignment->id }})" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No hay asignaciones registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $assignments->links() }}
        </div>
    @endif
</div>

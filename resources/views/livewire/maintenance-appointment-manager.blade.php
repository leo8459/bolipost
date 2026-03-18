<div>
    <div class="page-title mb-4 d-flex justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0">
            <i class="fas fa-calendar-check me-2 text-primary"></i>Gestion de Citas de Mantenimiento
        </h1>
        @if(!$showForm)
            <button wire:click="openForm" class="btn btn-primary">
                <i class="fas fa-calendar-plus me-2"></i>Agendar Nueva Cita
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
                {{ $isEdit ? 'Editar Cita de Mantenimiento' : 'Nueva Cita de Mantenimiento' }}
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Vehiculo <span class="text-danger">*</span></label>
                            <select wire:model="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror">
                                <option value="0">Seleccionar vehiculo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">{{ $vehicle->placa }} - {{ $vehicle->marca }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Conductor</label>
                            <select wire:model="driver_id" class="form-select">
                                <option value="">Seleccionar conductor</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Tipo de Mantenimiento</label>
                            <select wire:model="tipo_mantenimiento_id" class="form-select">
                                <option value="">Seleccionar tipo</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}">{{ $type->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Fecha Programada <span class="text-danger">*</span></label>
                            <input type="datetime-local" wire:model="fecha_programada" class="form-control @error('fecha_programada') is-invalid @enderror">
                            @error('fecha_programada') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Estado <span class="text-danger">*</span></label>
                            <select wire:model="estado" class="form-select @error('estado') is-invalid @enderror">
                                <option value="Pendiente">Pendiente</option>
                                <option value="Realizado">Realizado</option>
                                <option value="Cancelado">Cancelado</option>
                            </select>
                            @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-center">
                            <div class="form-check mt-md-4">
                                <input type="checkbox" id="es_accidente" wire:model.live="es_accidente" class="form-check-input">
                                <label class="form-check-label fw-bold" for="es_accidente">
                                    <i class="fas fa-exclamation-triangle text-danger me-1"></i>Es reporte de accidente
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold">Descripcion del Problema / Observaciones</label>
                            <textarea wire:model="descripcion_problema" class="form-control" rows="3" placeholder="Detalle los trabajos a realizar..."></textarea>
                            @error('descripcion_problema') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 border-top pt-3 mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar Cita' : 'Programar Cita' }}
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
                                <th>Vehiculo</th>
                                <th>Conductor</th>
                                <th>Tipo / Motivo</th>
                                <th>Fecha y Hora</th>
                                <th>Estado</th>
                                <th class="text-end px-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($appointments as $appointment)
                                <tr>
                                    <td><strong>{{ $appointment->vehicle?->placa }}</strong></td>
                                    <td>{{ $appointment->driver?->nombre ?? 'Sin asignar' }}</td>
                                    <td>
                                        @if($appointment->es_accidente)
                                            <span class="badge bg-danger">ACCIDENTE</span>
                                        @else
                                            <span class="badge bg-info text-dark">{{ $appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento' }}</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($appointment->fecha_programada)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        @php
                                            $color = match($appointment->estado) {
                                                'Realizado' => 'success',
                                                'Cancelado' => 'secondary',
                                                default => 'warning'
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $color }}">{{ $appointment->estado }}</span>
                                    </td>
                                    <td class="text-end px-4">
                                        <button wire:click="edit({{ $appointment->id }})" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="confirm('Eliminar esta cita?') || event.stopImmediatePropagation()"
                                                wire:click="delete({{ $appointment->id }})" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fas fa-calendar-times d-block mb-2" style="font-size: 2rem;"></i>
                                        No hay citas programadas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3">
            {{ $appointments->links() }}
        </div>
    @endif
</div>

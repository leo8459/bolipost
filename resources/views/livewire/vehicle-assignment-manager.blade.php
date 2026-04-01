<div>
    <style>
        #vehicleReassignModal {
            z-index: 1095;
        }

        .modal-backdrop.show.vehicle-reassign-backdrop {
            z-index: 1090;
        }
        .assignment-form-select {
            border-radius: 10px;
            min-height: calc(2.35rem + 2px);
            border: 1px solid #ced4da;
            transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
        }
        .assignment-form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .15);
        }
        select.assignment-form-select {
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
        <div class="alert alert-success alert-dismissible fade show js-auto-dismiss-alert" data-auto-dismiss="4000" role="alert">
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
                {{ $isEdit ? 'Editar Asignacion' : 'Nueva Asignacion' }}
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12">

                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Conductor <span class="text-danger">*</span></label>
                            <select wire:model="driver_id" class="form-control assignment-form-select @error('driver_id') is-invalid @enderror" required>
                                <option value="0">Seleccionar conductor</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                                @endforeach
                            </select>
                            @error('driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Vehiculo <span class="text-danger">*</span></label>
                            <select wire:model="vehicle_id" class="form-control assignment-form-select @error('vehicle_id') is-invalid @enderror" required>
                                <option value="0">Seleccionar vehiculo</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">
                                        {{ $vehicle->vehicleClass?->nombre ?? ($vehicle->marca ?: 'Vehiculo') }} | Modelo {{ $vehicle->modelo ?: 'S/MODELO' }} | Placa {{ $vehicle->placa ?: 'S/PLACA' }}
                                    </option>
                                @endforeach
                            </select>
                            @if($vehicles->isEmpty())
                                <div class="form-text text-warning">No hay vehiculos libres para asignar en la fecha seleccionada.</div>
                            @endif
                            @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Tipo de Asignacion <span class="text-danger">*</span></label>
                            <select wire:model="tipo_asignacion" class="form-control assignment-form-select @error('tipo_asignacion') is-invalid @enderror" required>
                                <option value="Fijo">Fijo</option>
                                <option value="Temporal">Temporal/Prestamo</option>
                            </select>
                            @error('tipo_asignacion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Fecha Inicio <span class="text-danger">*</span></label>
                            <input
                                type="date"
                                wire:model="fecha_inicio"
                                class="form-control @error('fecha_inicio') is-invalid @enderror"
                                required
                                @if(!$isEdit) min="{{ now()->toDateString() }}" @endif>
                            @error('fecha_inicio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">
                                Fecha Fin
                                @if($tipo_asignacion === 'Temporal')
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            <input
                                type="date"
                                wire:model="fecha_fin"
                                class="form-control @error('fecha_fin') is-invalid @enderror"
                                min="{{ $fecha_inicio ?: now()->toDateString() }}"
                                @if($tipo_asignacion === 'Temporal') required @endif>
                            @error('fecha_fin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @if($tipo_asignacion === 'Temporal')
                                <div class="form-text">Las asignaciones temporales deben indicar hasta cuando estaran vigentes.</div>
                            @endif
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
                <div class="p-3 border-bottom d-flex flex-wrap align-items-center gap-3">
                    <input
                        type="text"
                        class="form-control"
                        style="max-width: 360px;"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por cualquier campo">
                    <input
                        type="text"
                        class="form-control"
                        style="max-width: 220px;"
                        wire:model.live.debounce.350ms="plateFilter"
                        placeholder="Filtrar por placa">
                    <div class="form-check mb-0">
                        <input
                            type="checkbox"
                            id="showUnassignedDrivers"
                            class="form-check-input"
                            wire:model.live="showUnassignedDrivers"
                        >
                        <label class="form-check-label fw-semibold" for="showUnassignedDrivers">
                            Personas sin vehiculo asignado
                        </label>
                    </div>
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
                            @if($showUnassignedDrivers)
                                @forelse ($unassignedDrivers as $driver)
                                    <tr>
                                        <td>{{ $driver->nombre }}</td>
                                        <td>
                                            <span class="badge bg-secondary">Sin vehiculo asignado</span>
                                        </td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>
                                            <span class="badge bg-warning text-dark">Sin asignacion</span>
                                        </td>
                                        <td class="text-end px-4 text-muted">Sin acciones</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No hay personas sin vehiculo asignado.</td>
                                    </tr>
                                @endforelse
                            @else
                                @forelse ($assignments as $assignment)
                                    <tr>
                                        <td>{{ $assignment->driver?->nombre }}</td>
                                        <td>
                                            <span class="badge bg-dark">
                                                {{ $assignment->vehicle?->vehicleClass?->nombre ?? ($assignment->vehicle?->marca ?: 'Vehiculo') }}
                                            </span>
                                            <div class="small text-muted mt-1">
                                                Modelo {{ $assignment->vehicle?->modelo ?: 'S/MODELO' }} | Placa {{ $assignment->vehicle?->placa ?: 'S/PLACA' }}
                                            </div>
                                        </td>
                                        <td>{{ $assignment->tipo_asignacion }}</td>
                                        <td>{{ optional($assignment->fecha_inicio)->format('d/m/Y') }}</td>
                                        <td>
                                            <span class="badge {{ $assignment->activo ? 'bg-success' : 'bg-danger' }}">
                                                {{ $assignment->activo ? 'Activo' : 'Inactivo' }}
                                            </span>
                                        </td>
                                        <td class="text-end px-4">
                                            @if($assignment->activo)
                                                <button
                                                    onclick="confirm('Desasignar este vehiculo del conductor?') || event.stopImmediatePropagation()"
                                                    wire:click="unassign({{ $assignment->id }})"
                                                    class="btn btn-sm btn-outline-secondary"
                                                    title="Desasignar vehiculo"
                                                >
                                                    <i class="fas fa-unlink"></i>
                                                </button>
                                            @endif
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
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @unless($showUnassignedDrivers)
            <div class="mt-3">
                {{ $assignments->links() }}
            </div>
        @endunless
    @endif

    <div class="modal fade" id="vehicleReassignModal" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar cambio de asignacion</h5>
                    <button
                        type="button"
                        wire:click="closeReassignConfirm"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        data-dismiss="modal"
                        onclick="window.__closeVehicleReassignModal?.()"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">Este vehiculo ya tiene una asignacion activa. Quieres cambiar a la persona que lo tiene asignado?</p>

                    @if($conflictVehicleAssignmentId)
                        <div class="alert alert-warning mb-2">
                            El vehiculo <strong>{{ $conflictVehiclePlate ?? 'Sin placa' }}</strong>
                            esta asignado a <strong>{{ $conflictVehicleDriverName ?? 'otro conductor' }}</strong>.
                        </div>
                    @endif

                    @if($conflictDriverAssignmentId)
                        <div class="alert alert-secondary mb-0">
                            El conductor <strong>{{ $conflictDriverName ?? 'seleccionado' }}</strong>
                            tambien tiene asignado el vehiculo
                            <strong>{{ $conflictDriverVehiclePlate ?? 'sin placa' }}</strong>.
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        wire:click="cancelReassignment"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                        data-dismiss="modal"
                        onclick="window.__closeVehicleReassignModal?.()">No</button>
                    <button type="button" wire:click="confirmReassignment" class="btn btn-primary">
                        Si, cambiar asignacion
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    (function () {
        if (window.__vehicleAssignmentModalHandlersInitialized) return;
        window.__vehicleAssignmentModalHandlersInitialized = true;

        function getVehicleReassignModalElement() {
            return document.getElementById('vehicleReassignModal');
        }

        function getVehicleReassignModal() {
            const el = getVehicleReassignModalElement();
            if (!el) return null;

            if (window.bootstrap?.Modal) {
                if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                    return window.bootstrap.Modal.getOrCreateInstance(el);
                }

                if (typeof window.bootstrap.Modal.getInstance === 'function') {
                    return window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                }

                return new window.bootstrap.Modal(el);
            }

            if (window.jQuery?.fn?.modal) {
                return {
                    show: () => window.jQuery(el).modal('show'),
                    hide: () => window.jQuery(el).modal('hide'),
                };
            }

            return null;
        }

        function markLatestBackdrop() {
            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                backdrop.classList.remove('vehicle-reassign-backdrop');
            });

            const backdrops = document.querySelectorAll('.modal-backdrop');
            const latestBackdrop = backdrops.length ? backdrops[backdrops.length - 1] : null;
            if (latestBackdrop) {
                latestBackdrop.classList.add('vehicle-reassign-backdrop');
            }
        }

        function forceModalOnTop() {
            const modalEl = getVehicleReassignModalElement();
            if (!modalEl) return;

            modalEl.style.zIndex = '1095';
            markLatestBackdrop();
        }

        function closeVehicleReassignModal() {
            const modal = getVehicleReassignModal();
            if (modal) modal.hide();

            const modalEl = getVehicleReassignModalElement();
            if (modalEl) {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
                modalEl.setAttribute('aria-hidden', 'true');
                modalEl.removeAttribute('aria-modal');
            }

            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.body.style.removeProperty('overflow');

            document.querySelectorAll('.modal-backdrop').forEach((backdrop) => {
                backdrop.remove();
            });
        }

        window.__closeVehicleReassignModal = closeVehicleReassignModal;

        $wire.on('openVehicleReassignModal', () => {
            const modal = getVehicleReassignModal();
            if (!modal) return;

            modal.show();
            window.setTimeout(forceModalOnTop, 30);
            window.setTimeout(forceModalOnTop, 120);
        });

        $wire.on('closeVehicleReassignModal', () => {
            closeVehicleReassignModal();
        });
    })();
</script>
@endscript

@script
<script>
    (function () {
        if (window.__vehicleAssignmentFlashAutoDismissInitialized) return;
        window.__vehicleAssignmentFlashAutoDismissInitialized = true;

        function scheduleDismiss(scope) {
            (scope || document).querySelectorAll('.js-auto-dismiss-alert').forEach((alertEl) => {
                if (alertEl.dataset.dismissBound === '1') return;
                alertEl.dataset.dismissBound = '1';

                const delay = Number.parseInt(alertEl.getAttribute('data-auto-dismiss') || '4000', 10);
                window.setTimeout(() => {
                    alertEl.classList.remove('show');
                    alertEl.classList.add('fade');
                    window.setTimeout(() => {
                        alertEl.remove();
                    }, 220);
                }, Number.isFinite(delay) ? delay : 4000);
            });
        }

        scheduleDismiss(document);
        new MutationObserver(() => scheduleDismiss(document)).observe(document.body, { childList: true, subtree: true });
    })();
</script>
@endscript

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
    </style>

    <style>
        .maintenance-file-viewer {
            width: 100%;
            min-height: 70vh;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            background: #fff;
        }
        .maintenance-file-image {
            display: block;
            max-width: 100%;
            max-height: 70vh;
            margin: 0 auto;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            background: #fff;
        }
    </style>

    <div class="page-title mb-4 d-flex justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0">
            <i class="fas fa-calendar-check me-2 text-primary"></i>Gestion de Solicitudes y Citas de Mantenimiento
        </h1>
        @if(!$showForm)
            <button wire:click="openForm" class="btn btn-primary">
                <i class="fas fa-calendar-plus me-2"></i>Registrar Solicitud
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
                {{ $isEdit ? 'Editar Solicitud de Mantenimiento' : 'Nueva Solicitud de Mantenimiento' }}
            </div>
            <div class="card-body">
                <form wire:submit.prevent="save">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-0">
                                Este formulario registra una solicitud. La agencia puede aprobarla o rechazarla antes de ejecutar el mantenimiento.
                            </div>
                        </div>
                        @if($isEdit && $editingRequiresAgencyForm)
                            <div class="col-12">
                                <div class="alert alert-warning py-2 mb-0">
                                    Esta solicitud llego con un documento adjunto. La agencia debe revisar ese documento y completar o corregir el formulario interno antes de aprobarla.
                                </div>
                            </div>
                        @endif
                        @if($isEdit && $editingEvidenceUrl)
                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                        <div>
                                            <div class="fw-bold">Documento de solicitud enviado</div>
                                            <div class="text-muted small">Origen: {{ $editingOriginLabel ?? 'Documento' }}</div>
                                        </div>
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary maintenance-view-file-btn"
                                            data-url="{{ $editingEvidenceUrl }}"
                                            data-kind="{{ $this->evidenceIsPdf() ? 'pdf' : 'image' }}"
                                            data-name="Documento de solicitud"
                                        >
                                            <i class="fas fa-eye me-1"></i>Ver documento
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Tipo de formulario <span class="text-danger">*</span></label>
                            <select wire:model.live="maintenance_form_type" class="form-select bp-select-like-vehicle">
                                <option value="vehiculo">Vehiculo</option>
                                <option value="moto">Moto</option>
                            </select>
                            <div class="form-text">Este filtro limita los vehiculos y tipos de mantenimiento segun corresponda.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Vehiculo <span class="text-danger">*</span></label>
                            <select wire:model="vehicle_id" class="form-select bp-select-like-vehicle @error('vehicle_id') is-invalid @enderror">
                                <option value="0">Seleccionar {{ $maintenance_form_type === 'moto' ? 'moto' : 'vehiculo' }}</option>
                                @foreach ($vehicles as $vehicle)
                                    <option value="{{ $vehicle->id }}">{{ $vehicle->display_name }}</option>
                                @endforeach
                            </select>
                            @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Conductor</label>
                            <select wire:model="driver_id" class="form-select bp-select-like-vehicle">
                                <option value="">Seleccionar conductor</option>
                                @foreach ($drivers as $driver)
                                    <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Si el vehiculo tiene una asignacion activa, el conductor se completa automaticamente.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Tipo de Mantenimiento</label>
                            <select wire:model="tipo_mantenimiento_id" class="form-select bp-select-like-vehicle">
                                <option value="">Seleccionar tipo</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}">{{ $type->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Fecha Programada <span class="text-danger">*</span></label>
                            <input type="datetime-local" wire:model="fecha_programada" min="{{ now()->format('Y-m-d\TH:i') }}" class="form-control @error('fecha_programada') is-invalid @enderror">
                            @error('fecha_programada') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">La cita solo puede programarse hacia adelante.</div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-bold">Estado de solicitud <span class="text-danger">*</span></label>
                            @if($isEdit)
                                <select wire:model="estado" class="form-select bp-select-like-vehicle @error('estado') is-invalid @enderror">
                                    <option value="Pendiente">Pendiente</option>
                                    <option value="Aprobado">Aprobado</option>
                                    <option value="Rechazado">Rechazado</option>
                                    <option value="Realizado">Realizado</option>
                                    <option value="Cancelado">Cancelado</option>
                                </select>
                                @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            @else
                                <input type="text" class="form-control" value="Pendiente" readonly>
                                <div class="form-text">Toda cita nueva se registra inicialmente en estado Pendiente.</div>
                            @endif
                        </div>
                        <div class="col-12 col-md-6 d-flex align-items-center">
                            <div class="form-check bp-switch mt-md-4">
                                <input type="checkbox" id="es_accidente" wire:model.live="es_accidente" class="form-check-input">
                                <label class="form-check-label fw-bold" for="es_accidente">
                                    <i class="fas fa-exclamation-triangle text-danger me-1"></i>Es reporte de accidente
                                </label>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="formulario_documento_file" class="form-label fw-bold">Adjuntar foto o PDF del formulario</label>
                            <input
                                type="file"
                                id="formulario_documento_file"
                                wire:model="formulario_documento_file"
                                class="form-control @error('formulario_documento_file') is-invalid @enderror"
                                accept="image/*,.pdf,application/pdf"
                            >
                            @error('formulario_documento_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Puedes seleccionar una fotografia o un archivo PDF del formulario completado.</div>
                            @if($formulario_documento_file)
                                <div class="mt-3 border rounded-3 p-3 bg-light">
                                    <div class="fw-bold mb-2">Archivo seleccionado</div>
                                    <div class="text-muted small">{{ method_exists($formulario_documento_file, 'getClientOriginalName') ? $formulario_documento_file->getClientOriginalName() : 'Adjunto pendiente de guardar' }}</div>
                                    @if($this->uploadedFormIsPdf())
                                        <div class="small text-secondary mt-2">Se cargara como PDF al guardar la solicitud.</div>
                                    @else
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-primary mt-3 maintenance-view-file-btn"
                                            data-url="{{ $formulario_documento_file->temporaryUrl() }}"
                                            data-kind="image"
                                            data-name="Vista previa del formulario"
                                        >
                                            <i class="fas fa-eye me-1"></i>Ver imagen seleccionada
                                        </button>
                                    @endif
                                </div>
                            @elseif($formulario_documento_path)
                                <div class="mt-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-primary maintenance-view-file-btn{{ $editingFormUrl ? '' : ' disabled' }}"
                                        data-url="{{ $editingFormUrl ?? '' }}"
                                        data-kind="{{ $this->currentFormIsPdf() ? 'pdf' : 'image' }}"
                                        data-name="Formulario actual"
                                        {{ $editingFormUrl ? '' : 'disabled' }}
                                    >
                                        <i class="fas fa-eye me-1"></i>Ver documento actual
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2 border-top pt-3 mt-4">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar Solicitud' : 'Guardar Solicitud' }}
                        </button>
                        <button type="button" wire:click="resetForm" class="btn btn-secondary px-4">Volver al listado</button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    @else
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label fw-bold mb-1">Filtro de citas</label>
                        <select wire:model.live="statusFilter" class="form-select bp-select-like-vehicle">
                            <option value="">Todas</option>
                            <option value="Pendiente">Pendientes</option>
                            <option value="Aprobado">Aprobadas</option>
                            <option value="Realizado">Realizadas</option>
                            <option value="Rechazado">Rechazadas</option>
                            <option value="Cancelado">Canceladas</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-8 col-lg-9">
                        <label class="form-label fw-bold mb-1">Busqueda</label>
                        <input
                            type="text"
                            class="form-control"
                            wire:model.live.debounce.350ms="search"
                            placeholder="Buscar por cualquier campo">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Tipo de vehiculo</th>
                                <th>Marca</th>
                                <th>Placa</th>
                                <th>Conductor</th>
                                <th>Solicitud</th>
                                <th>Tipo / Motivo</th>
                                <th>Solicitud / Cita</th>
                                <th>Estado</th>
                                <th class="text-end px-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($appointments as $appointment)
                                <tr>
                                    <td>{{ $appointment->vehicle?->vehicleClass?->nombre ?? '-' }}</td>
                                    <td>{{ $appointment->vehicle?->brand?->nombre ?? $appointment->vehicle?->marca ?? '-' }}</td>
                                    <td><strong>{{ $appointment->vehicle?->placa ?? '-' }}</strong></td>
                                    <td>{{ $appointment->driver?->nombre ?? 'Sin asignar' }}</td>
                                    <td>
                                        <div class="d-flex flex-column gap-2">
                                        @if($appointment->formulario_documento_path)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-secondary maintenance-view-file-btn"
                                                data-url="{{ route('maintenance-appointments.form', $appointment) }}"
                                                data-kind="{{ strtolower(pathinfo((string) $appointment->formulario_documento_path, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'image' }}"
                                                data-name="Formulario de la solicitud #{{ $appointment->id }}"
                                            >
                                                <i class="fas fa-file-alt me-1"></i>Ver formulario
                                            </button>
                                        @endif
                                        @if($appointment->evidencia_path)
                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-primary maintenance-view-file-btn"
                                                data-url="{{ route('maintenance-appointments.evidence', $appointment) }}"
                                                data-kind="{{ strtolower(pathinfo((string) $appointment->evidencia_path, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'image' }}"
                                                data-name="Documento de solicitud #{{ $appointment->id }}"
                                            >
                                                <i class="fas fa-file-lines me-1"></i>Ver documento solicitud
                                            </button>
                                        @endif
                                        @if(!$appointment->formulario_documento_path && !$appointment->evidencia_path)
                                            <span class="text-muted small">Sin formulario</span>
                                        @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if($appointment->es_accidente)
                                            <span class="badge bg-danger">ACCIDENTE</span>
                                        @else
                                            <span class="badge bg-info text-dark">{{ $appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento' }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div><strong>Solicitud:</strong> {{ optional($appointment->solicitud_fecha ?? $appointment->created_at)->format('d/m/Y H:i') }}</div>
                                        <div class="text-muted small"><strong>Cita:</strong> {{ optional($appointment->fecha_programada)->format('d/m/Y H:i') ?? '-' }}</div>
                                    </td>
                                    <td>
                                        @php
                                            $color = match($appointment->estado) {
                                                'Aprobado' => 'primary',
                                                'Realizado' => 'success',
                                                'Rechazado' => 'danger',
                                                'Cancelado' => 'secondary',
                                                default => 'warning'
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $color }}">{{ $appointment->estado }}</span>
                                    </td>
                                    <td class="text-end px-4">
                                        @if($appointment->estado === 'Pendiente')
                                            <div class="btn-group me-1">
                                                <button
                                                    wire:click="approve({{ $appointment->id }})"
                                                    class="btn btn-sm btn-outline-primary"
                                                    title="Aprobar solicitud"
                                                >
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button
                                                    wire:click="reject({{ $appointment->id }})"
                                                    class="btn btn-sm btn-outline-danger"
                                                    title="Rechazar solicitud"
                                                >
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        @endif
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
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fas fa-calendar-times d-block mb-2" style="font-size: 2rem;"></i>
                                        No hay solicitudes o citas registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3">
            {{ $appointments->links('pagination::bootstrap-4') }}
        </div>
    @endif

    <div class="modal fade" id="maintenanceAppointmentFileModal" tabindex="-1" aria-labelledby="maintenanceAppointmentFileModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="maintenanceAppointmentFileModalLabel">Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <img id="maintenance-appointment-file-image" class="maintenance-file-image d-none" alt="Documento">
                    <iframe id="maintenance-appointment-file-frame" class="maintenance-file-viewer d-none" title="Documento"></iframe>
                    <div id="maintenance-appointment-file-empty" class="text-muted text-center py-4">No se pudo cargar el archivo.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function () {
        if (window.__maintenanceAppointmentFileModalInitialized) return;
        window.__maintenanceAppointmentFileModalInitialized = true;

        function getModalInstance(el) {
            if (!el) return null;

            if (window.bootstrap && window.bootstrap.Modal) {
                if (typeof window.bootstrap.Modal.getOrCreateInstance === 'function') {
                    return window.bootstrap.Modal.getOrCreateInstance(el);
                }
                if (typeof window.bootstrap.Modal.getInstance === 'function') {
                    return window.bootstrap.Modal.getInstance(el) || new window.bootstrap.Modal(el);
                }
                return new window.bootstrap.Modal(el);
            }

            if (window.jQuery) {
                return {
                    show: () => window.jQuery(el).modal('show'),
                    hide: () => window.jQuery(el).modal('hide'),
                };
            }

            return null;
        }

        const modalEl = document.getElementById('maintenanceAppointmentFileModal');
        if (!modalEl) return;
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        modalEl.setAttribute('data-bs-backdrop', 'true');
        modalEl.setAttribute('data-bs-keyboard', 'true');

        const titleEl = document.getElementById('maintenanceAppointmentFileModalLabel');
        const imageEl = document.getElementById('maintenance-appointment-file-image');
        const frameEl = document.getElementById('maintenance-appointment-file-frame');
        const emptyEl = document.getElementById('maintenance-appointment-file-empty');

        document.addEventListener('click', function (event) {
            const btn = event.target.closest('.maintenance-view-file-btn');
            if (!btn || btn.hasAttribute('disabled')) return;

            const url = btn.getAttribute('data-url') || '';
            const kind = btn.getAttribute('data-kind') || 'image';
            const name = btn.getAttribute('data-name') || 'Documento';

            if (titleEl) {
                titleEl.textContent = name;
            }

            imageEl?.classList.add('d-none');
            frameEl?.classList.add('d-none');
            emptyEl?.classList.add('d-none');
            imageEl?.removeAttribute('src');
            frameEl?.removeAttribute('src');

            if (!url) {
                emptyEl?.classList.remove('d-none');
            } else if (kind === 'pdf') {
                if (frameEl) {
                    frameEl.src = url;
                    frameEl.classList.remove('d-none');
                }
            } else {
                if (imageEl) {
                    imageEl.src = url;
                    imageEl.classList.remove('d-none');
                }
            }

            const modal = getModalInstance(modalEl);
            if (modal) modal.show();
        });

        document.addEventListener('click', function (event) {
            const closeBtn = event.target.closest(
                "#maintenanceAppointmentFileModal .btn-close, #maintenanceAppointmentFileModal [data-bs-dismiss='modal'], #maintenanceAppointmentFileModal [data-dismiss='modal']"
            );

            if (closeBtn) {
                event.preventDefault();
                const modal = getModalInstance(modalEl);
                if (modal) modal.hide();
                return;
            }

            if (event.target === modalEl) {
                const modal = getModalInstance(modalEl);
                if (modal) modal.hide();
            }
        });
    })();
</script>

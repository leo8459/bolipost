<div>
    <div class="page-title mb-4 d-flex justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0">
            <i class="fas fa-warehouse me-2 text-primary"></i>Gestion de Talleres
        </h1>
        @if(!$showForm)
            <button type="button" wire:click="create" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nueva orden de taller
            </button>
        @endif
    </div>

    @if (session('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12 col-lg-7">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Tablero de seguimiento</h5>
                        <span class="badge bg-primary">{{ $activeWorkshops->count() }} vehiculo(s) en taller</span>
                    </div>
                    <div class="row g-3">
                        @forelse($activeWorkshops as $activeWorkshop)
                            <div class="col-12 col-md-6">
                                <div class="border rounded-3 p-3 h-100 bg-light">
                                    <div class="small text-muted">{{ $activeWorkshop->order_number ?: 'Orden pendiente' }}</div>
                                    <div class="fw-bold">{{ $activeWorkshop->vehicle?->placa ?? 'Sin placa' }}</div>
                                    <div class="small text-muted">
                                        {{ $activeWorkshop->workshopCatalog?->nombre ?? $activeWorkshop->nombre_taller }}
                                    </div>
                                    <div class="mt-2 small">
                                        <strong>Estado:</strong> {{ $activeWorkshop->estado }}
                                    </div>
                                    <div class="small">
                                        <strong>Salida estimada:</strong>
                                        {{ optional($activeWorkshop->fecha_prometida_entrega)->format('d/m/Y') ?: 'Pendiente' }}
                                    </div>
                                    <div class="small">
                                        <strong>Costo acumulado:</strong>
                                        Bs {{ number_format((float) ($activeWorkshop->total_cost ?? 0), 2) }}
                                    </div>
                                    <div class="small">
                                        <strong>Mantenimiento:</strong>
                                        {{ $activeWorkshop->maintenanceAlert?->maintenanceType?->nombre ?? 'Orden general' }}
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12">
                                <div class="alert alert-light border mb-0">
                                    No hay vehiculos actualmente en taller.
                                </div>
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="border rounded-3 p-3 h-100">
                        <h5 class="mb-3">Catalogo de talleres</h5>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Nombre</label>
                                <input type="text" wire:model="catalog_name" class="form-control @error('catalog_name') is-invalid @enderror">
                                @error('catalog_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label fw-bold">Tipo</label>
                                <select wire:model="catalog_type" class="form-select @error('catalog_type') is-invalid @enderror">
                                    <option value="Interno">Interno</option>
                                    <option value="Externo">Externo</option>
                                </select>
                                @error('catalog_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12 col-md-3">
                                <button type="button" wire:click="createCatalog" class="btn btn-outline-primary w-100">
                                    <i class="fas fa-plus me-1"></i>Crear
                                </button>
                            </div>
                        </div>
                        <div class="mt-3">
                            @foreach($workshopCatalogs as $catalog)
                                <div class="d-flex justify-content-between align-items-center border rounded-3 px-3 py-2 mb-2">
                                    <div>
                                        <div class="fw-semibold">{{ $catalog->nombre }}</div>
                                        <div class="small text-muted">{{ $catalog->tipo }} | {{ $catalog->activo ? 'Activo' : 'Inactivo' }}</div>
                                    </div>
                                    <button type="button" wire:click="toggleCatalog({{ $catalog->id }})" class="btn btn-sm btn-outline-secondary">
                                        {{ $catalog->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($showForm)
        <div class="bp-gestiones-form-overlay">
            <div class="card shadow-sm mb-4 bp-gestiones-form-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>{{ $isEdit ? 'Editar orden de taller' : 'Nueva orden de taller' }}</span>
                    @if($order_number)
                        <span class="badge bg-light text-dark">{{ $order_number }}</span>
                    @endif
                </div>
                <div class="card-body">
                    @if($sourceAlert)
                        <div class="alert alert-warning border mb-4">
                            <strong>Despacho desde alerta:</strong>
                            vehiculo {{ $sourceAlert->vehicle?->placa ?? 'N/A' }},
                            mantenimiento {{ $sourceAlert->maintenanceType?->nombre ?? $sourceAlert->tipo }},
                            alerta #{{ $sourceAlert->id }}.
                        </div>
                    @endif

                    <form wire:submit.prevent="save">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Vehiculo <span class="text-danger">*</span></label>
                                <select wire:model="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror">
                                    <option value="">Seleccionar vehiculo disponible</option>
                                    @foreach($vehicles as $vehicle)
                                        <option value="{{ $vehicle->id }}">
                                            {{ $vehicle->placa }}{{ ($vehicle->operational_status ?? 'Disponible') !== 'Disponible' ? ' - '.$vehicle->operational_status : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vehicle_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Conductor</label>
                                <select wire:model="driver_id" class="form-select @error('driver_id') is-invalid @enderror">
                                    <option value="">Seleccionar conductor</option>
                                    @foreach($drivers as $driver)
                                        <option value="{{ $driver->id }}">{{ $driver->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('driver_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Estado de orden <span class="text-danger">*</span></label>
                                <select wire:model="estado" class="form-select @error('estado') is-invalid @enderror">
                                    <option value="{{ \App\Models\Workshop::STATUS_PENDING }}">Pendiente</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_DISPATCHED }}">Despachado a taller</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_DIAGNOSIS }}">Diagnosticado</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_APPROVED }}">Aprobado</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_REPAIR }}">En reparacion</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_READY }}">Listo para recoger</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_DELIVERED }}">Entregado</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_CLOSED }}">Cerrado</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_REJECTED }}">Rechazado</option>
                                    <option value="{{ \App\Models\Workshop::STATUS_CANCELLED }}">Cancelado</option>
                                </select>
                                @error('estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Flujo del mantenimiento <span class="text-danger">*</span></label>
                                <select wire:model.live="workflow_kind" class="form-select @error('workflow_kind') is-invalid @enderror">
                                    <option value="{{ \App\Models\Workshop::FLOW_LIGHT }}">Leve</option>
                                    <option value="{{ \App\Models\Workshop::FLOW_HEAVY }}">Grave</option>
                                </select>
                                @error('workflow_kind') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">
                                    Leve: catalogo + costo fijo. Grave: diagnostico + repuestos + mano de obra + aprobacion.
                                </div>
                            </div>

                            <div class="col-12 col-md-4 d-flex align-items-end">
                                <div class="form-check">
                                    <input type="checkbox" id="approval_required" wire:model="approval_required" class="form-check-input">
                                    <label for="approval_required" class="form-check-label fw-bold">Requiere aprobacion</label>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Taller <span class="text-danger">*</span></label>
                                <select wire:model.live="workshop_catalog_id" class="form-select @error('workshop_catalog_id') is-invalid @enderror">
                                    <option value="">Seleccionar taller</option>
                                    @foreach($workshopCatalogs->where('activo', true) as $catalog)
                                        <option value="{{ $catalog->id }}">{{ $catalog->nombre }} | {{ $catalog->tipo }}</option>
                                    @endforeach
                                </select>
                                @error('workshop_catalog_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Solicitud / cita ligada</label>
                                <select wire:model.live="maintenance_appointment_id" class="form-select @error('maintenance_appointment_id') is-invalid @enderror">
                                    <option value="">Sin solicitud ligada</option>
                                    @foreach($appointments as $appointment)
                                        <option value="{{ $appointment->id }}">
                                            #{{ $appointment->id }} - {{ $appointment->vehicle?->placa ?? '-' }} - {{ $appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('maintenance_appointment_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Registro de mantenimiento</label>
                                <select wire:model="maintenance_log_id" class="form-select @error('maintenance_log_id') is-invalid @enderror">
                                    <option value="">Sin registro ligado</option>
                                    @foreach($maintenanceLogs as $log)
                                        <option value="{{ $log->id }}">#{{ $log->id }} - {{ $log->vehicle?->placa ?? '-' }} - {{ $log->tipo }}</option>
                                    @endforeach
                                </select>
                                @error('maintenance_log_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <input type="hidden" wire:model="maintenance_alert_id">

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Fecha ingreso <span class="text-danger">*</span></label>
                                <input type="date" wire:model="fecha_ingreso" class="form-control @error('fecha_ingreso') is-invalid @enderror">
                                @error('fecha_ingreso') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Fecha prometida de entrega</label>
                                <input type="date" wire:model="fecha_prometida_entrega" class="form-control @error('fecha_prometida_entrega') is-invalid @enderror">
                                @error('fecha_prometida_entrega') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Fecha listo</label>
                                <input type="date" wire:model="fecha_listo" class="form-control @error('fecha_listo') is-invalid @enderror">
                                @error('fecha_listo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Fecha salida / recojo</label>
                                <input type="date" wire:model="fecha_salida" class="form-control @error('fecha_salida') is-invalid @enderror">
                                @error('fecha_salida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Costo catalogado</label>
                                <input type="number" step="0.01" min="0" wire:model="fixed_catalog_cost" class="form-control @error('fixed_catalog_cost') is-invalid @enderror">
                                @error('fixed_catalog_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Mano de obra</label>
                                <input type="number" step="0.01" min="0" wire:model="labor_cost" class="form-control @error('labor_cost') is-invalid @enderror">
                                @error('labor_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label fw-bold">Costo adicional</label>
                                <input type="number" step="0.01" min="0" wire:model="additional_cost" class="form-control @error('additional_cost') is-invalid @enderror">
                                @error('additional_cost') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Pre entrada: estado del vehiculo al dejarlo <span class="text-danger">*</span></label>
                                <textarea wire:model="pre_entrada_estado" rows="3" class="form-control @error('pre_entrada_estado') is-invalid @enderror"></textarea>
                                @error('pre_entrada_estado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Observaciones tecnicas del taller</label>
                                <textarea wire:model="observaciones_tecnicas" rows="3" class="form-control @error('observaciones_tecnicas') is-invalid @enderror"></textarea>
                                @error('observaciones_tecnicas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label fw-bold">Diagnostico / trabajo realizado</label>
                                <textarea wire:model="diagnostico" rows="3" class="form-control @error('diagnostico') is-invalid @enderror"></textarea>
                                @error('diagnostico') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-bold">Observaciones adicionales</label>
                                <textarea wire:model="observaciones" rows="3" class="form-control @error('observaciones') is-invalid @enderror"></textarea>
                                @error('observaciones') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            @if($estado === \App\Models\Workshop::STATUS_REJECTED)
                                <div class="col-12">
                                    <label class="form-label fw-bold">Motivo de rechazo <span class="text-danger">*</span></label>
                                    <textarea wire:model="rejection_reason" rows="2" class="form-control @error('rejection_reason') is-invalid @enderror"></textarea>
                                    @error('rejection_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            @if($estado === \App\Models\Workshop::STATUS_CANCELLED)
                                <div class="col-12">
                                    <label class="form-label fw-bold">Motivo de cancelacion <span class="text-danger">*</span></label>
                                    <textarea wire:model="cancellation_reason" rows="2" class="form-control @error('cancellation_reason') is-invalid @enderror"></textarea>
                                    @error('cancellation_reason') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            @endif

                            <div class="col-12">
                                <div class="border rounded-3 p-3 bg-light">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label fw-bold">Foto de ingreso</label>
                                            <input type="file" wire:model="reception_photo_file" class="form-control @error('reception_photo_file') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                            @error('reception_photo_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            @if($reception_photo_path)
                                                <div class="form-text">Archivo cargado en la orden actual.</div>
                                            @endif
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label fw-bold">Foto del dano</label>
                                            <input type="file" wire:model="damage_photo_file" class="form-control @error('damage_photo_file') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                            @error('damage_photo_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            @if($damage_photo_path)
                                                <div class="form-text">Archivo cargado en la orden actual.</div>
                                            @endif
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label fw-bold">Factura</label>
                                            <input type="file" wire:model="invoice_file" class="form-control @error('invoice_file') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                            @error('invoice_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            @if($invoice_file_path)
                                                <div class="form-text">Archivo cargado en la orden actual.</div>
                                            @endif
                                        </div>
                                        <div class="col-12 col-md-6 col-xl-3">
                                            <label class="form-label fw-bold">Comprobante / recibo</label>
                                            <input type="file" wire:model="receipt_file" class="form-control @error('receipt_file') is-invalid @enderror" accept=".jpg,.jpeg,.png,.webp,.pdf">
                                            @error('receipt_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            @if($receipt_file_path)
                                                <div class="form-text">Archivo cargado en la orden actual.</div>
                                            @endif
                                        </div>
                                    </div>
                                    @if($isEdit && $editingId)
                                        <div class="d-flex flex-wrap gap-2 mt-3">
                                            @if($reception_photo_path)
                                                <a href="{{ route('workshops.reception', $editingId) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver ingreso</a>
                                            @endif
                                            @if($damage_photo_path)
                                                <a href="{{ route('workshops.damage', $editingId) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver dano</a>
                                            @endif
                                            @if($invoice_file_path)
                                                <a href="{{ route('workshops.invoice', $editingId) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver factura</a>
                                            @endif
                                            @if($receipt_file_path)
                                                <a href="{{ route('workshops.receipt', $editingId) }}" target="_blank" class="btn btn-sm btn-outline-secondary">Ver comprobante</a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold mb-0">Repuestos / insumos usados</label>
                                    <button type="button" wire:click="addPartRow" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-plus me-1"></i>Anadir repuesto
                                    </button>
                                </div>
                                <div class="row g-2">
                                    @foreach($partChanges as $index => $partChange)
                                        <div class="col-12">
                                            <div class="border rounded-3 p-3">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-12 col-md-3">
                                                        <label class="form-label fw-bold">Codigo pieza antigua</label>
                                                        <input type="text" wire:model="partChanges.{{ $index }}.codigo_pieza_antigua" class="form-control @error('partChanges.'.$index.'.codigo_pieza_antigua') is-invalid @enderror">
                                                        @error('partChanges.'.$index.'.codigo_pieza_antigua') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-12 col-md-3">
                                                        <label class="form-label fw-bold">Codigo pieza nueva</label>
                                                        <input type="text" wire:model="partChanges.{{ $index }}.codigo_pieza_nueva" class="form-control @error('partChanges.'.$index.'.codigo_pieza_nueva') is-invalid @enderror">
                                                        @error('partChanges.'.$index.'.codigo_pieza_nueva') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-12 col-md-4">
                                                        <label class="form-label fw-bold">Repuesto / detalle</label>
                                                        <input type="text" wire:model="partChanges.{{ $index }}.descripcion" class="form-control @error('partChanges.'.$index.'.descripcion') is-invalid @enderror">
                                                        @error('partChanges.'.$index.'.descripcion') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-12 col-md-1">
                                                        <label class="form-label fw-bold">Costo</label>
                                                        <input type="number" step="0.01" min="0" wire:model="partChanges.{{ $index }}.costo" class="form-control @error('partChanges.'.$index.'.costo') is-invalid @enderror">
                                                        @error('partChanges.'.$index.'.costo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                                    </div>
                                                    <div class="col-12 col-md-1">
                                                        <button type="button" wire:click="removePartRow({{ $index }})" class="btn btn-outline-danger w-100">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-2"></i>{{ $isEdit ? 'Actualizar orden' : 'Guardar orden' }}
                            </button>
                            <div class="btn btn-light border px-3 disabled">
                                Total estimado: Bs
                                {{ number_format((float) collect($partChanges)->sum(fn ($row) => (float) (($row['costo'] ?? '') !== '' && ($row['costo'] ?? null) !== null ? $row['costo'] : 0)) + (float) ($fixed_catalog_cost ?: 0) + (float) ($labor_cost ?: 0) + (float) ($additional_cost ?: 0), 2) }}
                            </div>
                            <button type="button" wire:click="cancelForm" class="btn btn-secondary">Volver al listado</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="p-3 border-bottom">
                    <div class="row g-2">
                        <div class="col-12 col-md-8">
                            <input type="text" class="form-control" wire:model.live.debounce.350ms="search" placeholder="Buscar por OT, vehiculo, taller, conductor o pieza">
                        </div>
                        <div class="col-12 col-md-4">
                            <select wire:model.live="statusFilter" class="form-select">
                                <option value="">Todos los estados</option>
                                <option value="{{ \App\Models\Workshop::STATUS_PENDING }}">Pendiente</option>
                                <option value="{{ \App\Models\Workshop::STATUS_DISPATCHED }}">Despachado a taller</option>
                                <option value="{{ \App\Models\Workshop::STATUS_DIAGNOSIS }}">Diagnosticado</option>
                                <option value="{{ \App\Models\Workshop::STATUS_APPROVED }}">Aprobado</option>
                                <option value="{{ \App\Models\Workshop::STATUS_REPAIR }}">En reparacion</option>
                                <option value="{{ \App\Models\Workshop::STATUS_READY }}">Listo para recoger</option>
                                <option value="{{ \App\Models\Workshop::STATUS_DELIVERED }}">Entregado</option>
                                <option value="{{ \App\Models\Workshop::STATUS_CLOSED }}">Cerrado</option>
                                <option value="{{ \App\Models\Workshop::STATUS_REJECTED }}">Rechazado</option>
                                <option value="{{ \App\Models\Workshop::STATUS_CANCELLED }}">Cancelado</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>OT</th>
                                <th>Vehiculo</th>
                                <th>Taller</th>
                                <th>Ingreso</th>
                                <th>Entrega estimada</th>
                                <th>Estado</th>
                                <th>Flujo</th>
                                <th>Costo</th>
                                <th>Observaciones tecnicas</th>
                                <th>Repuestos</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($workshops as $workshop)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $workshop->order_number ?: 'Pendiente' }}</div>
                                        @if($workshop->maintenance_alert_id)
                                            <div class="small text-muted">Alerta #{{ $workshop->maintenance_alert_id }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div>{{ $workshop->vehicle?->display_name ?? 'N/A' }}</div>
                                        <div class="small text-muted">{{ $workshop->driver?->nombre ?? 'Sin conductor' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $workshop->workshopCatalog?->nombre ?? $workshop->nombre_taller }}</div>
                                        <div class="small text-muted">{{ $workshop->workshopCatalog?->tipo ?? 'Sin tipo' }}</div>
                                    </td>
                                    <td>{{ optional($workshop->fecha_ingreso)->format('d/m/Y') }}</td>
                                    <td>{{ optional($workshop->fecha_prometida_entrega)->format('d/m/Y') ?: 'Pendiente' }}</td>
                                    <td>
                                        <span class="badge {{ in_array($workshop->estado, [\App\Models\Workshop::STATUS_READY, \App\Models\Workshop::STATUS_DELIVERED, \App\Models\Workshop::STATUS_CLOSED]) ? 'bg-success' : (in_array($workshop->estado, [\App\Models\Workshop::STATUS_REJECTED, \App\Models\Workshop::STATUS_CANCELLED]) ? 'bg-danger' : 'bg-warning text-dark') }}">
                                            {{ $workshop->estado }}
                                        </span>
                                    </td>
                                    <td>
                                        <div>{{ $workshop->workflow_kind }}</div>
                                        @if($workshop->approval_required)
                                            <div class="small text-muted">Con aprobacion</div>
                                        @endif
                                    </td>
                                    <td>Bs {{ number_format((float) ($workshop->total_cost ?? 0), 2) }}</td>
                                    <td><small class="text-muted">{{ \Illuminate\Support\Str::limit($workshop->observaciones_tecnicas ?: $workshop->diagnostico, 90) }}</small></td>
                                    <td>
                                        @if($workshop->partChanges->count() > 0)
                                            <div class="small">{{ $workshop->partChanges->count() }} item(s)</div>
                                            <div class="small text-muted">Bs {{ number_format((float) $workshop->partChanges->sum('costo'), 2) }}</div>
                                        @else
                                            <span class="text-muted">Sin repuestos</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @if($workshop->reception_photo_path)
                                            <a href="{{ route('workshops.reception', $workshop) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-camera"></i>
                                            </a>
                                        @endif
                                        <button wire:click="edit({{ $workshop->id }})" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button wire:click="delete({{ $workshop->id }})" onclick="return confirm('Confirmar eliminacion?')" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="text-center py-4 text-muted">No hay ordenes de taller registradas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $workshops->links() }}
        </div>
    @endif
</div>

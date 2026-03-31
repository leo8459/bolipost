<div>
    <div class="page-title mb-4 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">
            <i class="fas fa-bell me-2 text-danger"></i>Alertas de Mantenimiento
        </h1>
        <button type="button" wire:click="markAllAsRead" class="btn btn-outline-primary">
            <i class="fas fa-check-double me-2"></i>Marcar pendientes como leidas
        </button>
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
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Buscar</label>
                    <input
                        type="text"
                        class="form-control"
                        wire:model.live.debounce.350ms="search"
                        placeholder="Buscar por cualquier campo">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Tipo</label>
                    <select wire:model.live="filterTipo" class="form-select">
                        <option value="">Todos</option>
                        <option value="Preventivo">Preventivo</option>
                        <option value="Programado">Programado</option>
                        <option value="Solicitud">Solicitud</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Estado</label>
                    <select wire:model.live="filterEstado" class="form-select">
                        <option value="todas">Todas</option>
                        <option value="activa">Activas</option>
                        <option value="resuelta">Resueltas</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <div class="alert alert-warning mb-0 py-2">
                        <strong>Pendientes:</strong> {{ $pendingCount }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            @if($alerts->count())
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th>Vehiculo</th>
                                <th>Tipo</th>
                                <th>Mensaje</th>
                                <th>KM Actual</th>
                                <th>KM Objetivo</th>
                                <th>Faltante</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alerts as $alert)
                                <tr>
                                    <td>{{ $alert->vehicle?->placa ?? 'N/A' }}</td>
                                    <td>
                                        @if($alert->tipo === 'Solicitud')
                                            <span class="badge bg-success">Solicitud</span>
                                        @elseif($alert->tipo === 'Programado')
                                            <span class="badge bg-info text-dark">Programado</span>
                                        @else
                                            <span class="badge bg-primary">{{ $alert->tipo }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $alert->mensaje }}</td>
                                    <td>{{ $alert->kilometraje_actual ?? '-' }}</td>
                                    <td>{{ $alert->kilometraje_objetivo ?? '-' }}</td>
                                    <td>
                                        @if($alert->faltante_km !== null)
                                            <span class="{{ (float) $alert->faltante_km < 0 ? 'text-danger fw-bold' : '' }}">
                                                {{ $alert->faltante_km }}
                                            </span>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $isOverdue = $alert->status === 'Activa' && $alert->faltante_km !== null && (float) $alert->faltante_km < 0;
                                            $isPostponed = $alert->status === 'Activa' && $alert->postponed_until && $alert->postponed_until->isFuture();
                                        @endphp
                                        <span class="badge {{ $alert->status === 'Activa' ? ($isPostponed ? 'bg-warning text-dark' : 'bg-danger') : ($alert->status === 'Resuelta' ? 'bg-success' : 'bg-secondary') }}">
                                            {{ $isPostponed ? ('Pospuesta hasta ' . $alert->postponed_until->format('d/m/Y')) : ($isOverdue ? 'Vencida' : ($alert->status ?? 'Activa')) }}
                                        </span>
                                    </td>
                                    <td>{{ optional($alert->created_at)->format('d/m/Y H:i') }}</td>
                                    <td class="text-center">
                                        @if(auth()->user()?->role !== 'conductor' && $alert->status === 'Activa')
                                            @if(!$alert->leida)
                                                <button type="button" wire:click="markAsRead({{ $alert->id }})" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-check me-1"></i>Leida
                                                </button>
                                            @else
                                                <button type="button" wire:click="requestDiagnosis({{ $alert->id }})" class="btn btn-sm btn-outline-secondary ms-1">
                                                    <i class="fas fa-stethoscope me-1"></i>Solicitar diagnostico
                                                </button>
                                                <button type="button" wire:click="dispatchToWorkshop({{ $alert->id }})" class="btn btn-sm btn-outline-warning ms-1">
                                                    <i class="fas fa-truck-moving me-1"></i>Despachar a taller
                                                </button>
                                            @endif
                                            @if(!($alert->postponed_once ?? false))
                                                <button type="button" wire:click="postponeAlert({{ $alert->id }})" class="btn btn-sm btn-outline-secondary ms-1">
                                                    <i class="fas fa-clock me-1"></i>Posponer 3 dias
                                                </button>
                                            @endif
                                        @elseif($alert->status === 'Activa' && $alert->leida)
                                            <button type="button" wire:click="markAsUnread({{ $alert->id }})" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-undo me-1"></i>Pendiente
                                            </button>
                                        @elseif($alert->status === 'Activa')
                                            <button type="button" wire:click="markAsRead({{ $alert->id }})" class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-check me-1"></i>Leida
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-5 text-center text-muted">
                    <i class="fas fa-bell-slash fa-3x mb-3 opacity-25"></i>
                    <h5>Sin alertas para mostrar</h5>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-4">
        {{ $alerts->links() }}
    </div>
</div>

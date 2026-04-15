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
                    <select wire:model.live="filterTipo" class="form-select bp-select-like-vehicle">
                        <option value="">Todos</option>
                        <option value="Preventivo">Preventivo</option>
                        <option value="Programado">Programado</option>
                        <option value="Solicitud">Solicitud</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-bold">Estado</label>
                    <select wire:model.live="filterEstado" class="form-select bp-select-like-vehicle">
                        <option value="abiertas">Abiertas</option>
                        <option value="todas">Todas</option>
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
                                            $isOpen = in_array($alert->status, \App\Models\MaintenanceAlert::openStatuses(), true);
                                            $isOverdue = $alert->status === \App\Models\MaintenanceAlert::STATUS_ACTIVE && $alert->faltante_km !== null && (float) $alert->faltante_km < 0;
                                            $isPostponed = in_array($alert->status, [\App\Models\MaintenanceAlert::STATUS_ACTIVE, \App\Models\MaintenanceAlert::STATUS_REQUESTED], true) && $alert->postponed_until && $alert->postponed_until->isFuture();
                                            $badgeClass = match ($alert->status) {
                                                \App\Models\MaintenanceAlert::STATUS_IN_WORKSHOP => 'bg-info text-dark',
                                                \App\Models\MaintenanceAlert::STATUS_REQUESTED => 'bg-warning text-dark',
                                                \App\Models\MaintenanceAlert::STATUS_RESOLVED => 'bg-success',
                                                \App\Models\MaintenanceAlert::STATUS_OMITTED => 'bg-secondary',
                                                default => ($isPostponed ? 'bg-warning text-dark' : 'bg-danger'),
                                            };
                                        @endphp
                                        <span class="badge {{ $badgeClass }}">
                                            {{ $isPostponed ? ('Pospuesta hasta ' . $alert->postponed_until->format('d/m/Y')) : ($isOverdue ? 'Vencida' : ($alert->status ?? 'Activa')) }}
                                        </span>
                                    </td>
                                    <td>{{ optional($alert->created_at)->format('d/m/Y H:i') }}</td>
                                    <td class="text-center">
                                        @if(auth()->user()?->role !== 'conductor' && in_array($alert->status, [\App\Models\MaintenanceAlert::STATUS_ACTIVE, \App\Models\MaintenanceAlert::STATUS_REQUESTED], true))
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
                                        @elseif($isOpen && $alert->leida && $alert->status !== \App\Models\MaintenanceAlert::STATUS_IN_WORKSHOP)
                                            <button type="button" wire:click="markAsUnread({{ $alert->id }})" class="btn btn-sm btn-outline-warning">
                                                <i class="fas fa-undo me-1"></i>Pendiente
                                            </button>
                                        @elseif($isOpen && $alert->status !== \App\Models\MaintenanceAlert::STATUS_IN_WORKSHOP)
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

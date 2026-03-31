<div>
    <div class="page-title mb-4 d-flex justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0">
            <i class="fas fa-star me-2 text-warning"></i>Incentivos de Mantenimiento
        </h1>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Fecha desde</label>
            <input type="date" wire:model.live="date_from" class="form-control">
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label fw-bold">Fecha hasta</label>
            <input type="date" wire:model.live="date_to" class="form-control">
        </div>
        <div class="col-12 col-md-4 d-flex align-items-end">
            <div class="form-check">
                <input type="checkbox" id="onlyPerfect" wire:model.live="onlyPerfect" class="form-check-input">
                <label for="onlyPerfect" class="form-check-label fw-bold">Mostrar solo quienes mantienen {{ $maxStars }} estrellas</label>
            </div>
        </div>
        <div class="col-12 col-md-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase">Periodo</div>
                    <div class="fw-bold">{{ $periodLabel }}</div>
                    <div class="small text-muted">Mientras menos mantenimientos aprobados o realizados que descuenten tenga el conductor en el rango elegido, mas estrellas conserva.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">Conductores con incentivo completo</div>
                    <div class="fs-3 fw-bold text-success">{{ $perfectCount }}</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">Conductores con rebaja de estrellas</div>
                    <div class="fs-3 fw-bold text-danger">{{ $discountedCount }}</div>
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
                            <th>Conductor</th>
                            <th>Inicio</th>
                            <th>Total solicitudes</th>
                            <th>Eventos que descuentan</th>
                            <th>Solicitudes no preventivas</th>
                            <th>Solicitudes preventivas</th>
                            <th>Estrellas finales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                            <tr>
                                <td>{{ $report->driver?->nombre ?? 'Sin conductor' }}</td>
                                <td><span class="badge bg-secondary">{{ $report->stars_start }}</span></td>
                                <td><span class="badge bg-dark">{{ $report->total_requests }}</span></td>
                                <td><span class="badge bg-warning text-dark">{{ $report->discountable_events }}</span></td>
                                <td><span class="badge bg-danger">{{ $report->non_preventive_requests }}</span></td>
                                <td><span class="badge bg-info text-dark">{{ $report->preventive_requests }}</span></td>
                                <td>
                                    <span class="badge {{ $report->stars_end === $maxStars ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $report->stars_end }} / {{ $maxStars }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No hay conductores para este reporte.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

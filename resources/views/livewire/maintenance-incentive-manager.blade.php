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
                    <div class="text-muted small text-uppercase">Periodo evaluado</div>
                    <div class="fw-bold">{{ $periodLabel }}</div>
                    <div class="small text-muted">
                        Todos empiezan con {{ $maxStars }} estrellas.
                        Solo bajan estrellas los mantenimientos aprobados o realizados que no sean preventivos.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">Conductores con puntaje completo</div>
                    <div class="fs-3 fw-bold text-success">{{ $perfectCount }}</div>
                    <div class="small text-muted">Mantuvieron sus {{ $maxStars }} estrellas.</div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body">
                    <div class="text-muted small">Conductores con descuento</div>
                    <div class="fs-3 fw-bold text-danger">{{ $discountedCount }}</div>
                    <div class="small text-muted">Tuvieron al menos un mantenimiento que sí descuenta.</div>
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
                            <th>Estrellas</th>
                            <th>Resultado</th>
                            <th>Resumen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                            @php
                                $starsEnd = (int) $report->stars_end;
                                $discounts = (int) $report->discountable_events;
                                $preventives = (int) $report->preventive_requests;
                                $statusLabel = $starsEnd === $maxStars
                                    ? 'Excelente'
                                    : ($starsEnd >= max($maxStars - 1, 1) ? 'Con observacion' : 'Bajo');
                                $statusClass = $starsEnd === $maxStars
                                    ? 'bg-success'
                                    : ($starsEnd >= max($maxStars - 1, 1) ? 'bg-warning text-dark' : 'bg-danger');
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-bold">{{ $report->driver?->nombre ?? 'Sin conductor' }}</div>
                                    <div class="small text-muted">
                                        Inicio con {{ $report->stars_start }} estrella(s)
                                    </div>
                                </td>
                                <td style="min-width: 170px;">
                                    <div class="fw-bold fs-5 text-warning">
                                        {{ str_repeat('★', $starsEnd) }}{{ str_repeat('☆', max($maxStars - $starsEnd, 0)) }}
                                    </div>
                                    <div class="small text-muted">{{ $starsEnd }} de {{ $maxStars }} estrellas</div>
                                </td>
                                <td>
                                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td style="min-width: 280px;">
                                    <div class="small">
                                        <strong>{{ $discounts }}</strong> mantenimiento(s) le descontaron estrellas.
                                    </div>
                                    <div class="small text-muted">
                                        Preventivos sin descuento: {{ $preventives }}.
                                    </div>
                                    <div class="small text-muted">
                                        Total revisado en el periodo: {{ $report->total_requests }}.
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No hay conductores para este reporte.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

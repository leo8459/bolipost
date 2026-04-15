<div>
    <style>
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
        .incentive-detail-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.48);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            z-index: 1050;
        }
        .incentive-detail-card {
            width: min(960px, 100%);
            max-height: 88vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
        }
        .incentive-detail-body {
            overflow-y: auto;
        }
        .incentive-detail-list-item {
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 12px 14px;
            background: #fff;
        }
        .incentive-detail-meta {
            font-size: 12px;
            color: #6b7280;
        }
    </style>

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
            <div class="form-check bp-switch">
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
                            <th class="text-end">Detalle</th>
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
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm"
                                        wire:click="showDetail({{ $report->driver_id }})"
                                    >
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No hay conductores para este reporte.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($detailModalVisible && $selectedReport)
        @php
            $starsEnd = (int) $selectedReport->stars_end;
        @endphp
        <div class="incentive-detail-overlay" wire:key="incentive-detail-overlay">
            <div class="incentive-detail-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-bold">
                            Detalle de calificacion: {{ $selectedReport->driver?->nombre ?? 'Sin conductor' }}
                        </div>
                        <div class="small text-muted">Periodo: {{ $periodLabel }}</div>
                    </div>
                    <button type="button" class="btn-close" aria-label="Cerrar" wire:click="closeDetail"></button>
                </div>
                <div class="card-body incentive-detail-body">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <div class="text-muted small">Estrellas finales</div>
                                <div class="fw-bold fs-4 text-warning">{{ $starsEnd }} / {{ $maxStars }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <div class="text-muted small">Mantenimientos que descontaron</div>
                                <div class="fw-bold fs-4 text-danger">{{ count($selectedReport->discounted_appointments) }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <div class="text-muted small">Mantenimientos que no descontaron</div>
                                <div class="fw-bold fs-4 text-success">{{ count($selectedReport->non_discounted_appointments) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="border rounded-3 p-3 mb-3 bg-light">
                        <div class="fw-bold text-danger mb-3">Infracciones o mantenimientos que descontaron estrellas</div>
                        @forelse($selectedReport->discounted_appointments as $detail)
                            <div class="incentive-detail-list-item mb-2">
                                <div class="fw-semibold text-danger">{{ $detail->maintenance_type }}</div>
                                <div class="small text-muted">{{ $detail->vehicle_label }}</div>
                                <div class="incentive-detail-meta mt-2"><strong>Tipo de mantenimiento realizado:</strong> {{ $detail->maintenance_type }}</div>
                                <div class="incentive-detail-meta mt-2"><strong>Vehiculo:</strong> {{ $detail->vehicle_plate }}</div>
                                <div class="incentive-detail-meta"><strong>Solicitud:</strong> {{ $detail->request_date }}</div>
                                <div class="incentive-detail-meta"><strong>Cita:</strong> {{ $detail->scheduled_date }}</div>
                                <div class="incentive-detail-meta"><strong>Estado:</strong> {{ $detail->status }}</div>
                                <div class="small text-danger mt-2"><strong>Motivo del descuento:</strong> {{ $detail->reason }}</div>
                            </div>
                        @empty
                            <div class="text-muted small">No hubo mantenimientos que descontaran estrellas en este periodo.</div>
                        @endforelse
                    </div>

                    <div class="border rounded-3 p-3 bg-light">
                        <div class="fw-bold text-success mb-3">Mantenimientos que no descontaron</div>
                        @forelse($selectedReport->non_discounted_appointments as $detail)
                            <div class="incentive-detail-list-item mb-2">
                                <div class="fw-semibold text-success">{{ $detail->maintenance_type }}</div>
                                <div class="small text-muted">{{ $detail->vehicle_label }}</div>
                                <div class="incentive-detail-meta mt-2"><strong>Tipo de mantenimiento realizado:</strong> {{ $detail->maintenance_type }}</div>
                                <div class="incentive-detail-meta mt-2"><strong>Vehiculo:</strong> {{ $detail->vehicle_plate }}</div>
                                <div class="incentive-detail-meta"><strong>Solicitud:</strong> {{ $detail->request_date }}</div>
                                <div class="incentive-detail-meta"><strong>Cita:</strong> {{ $detail->scheduled_date }}</div>
                                <div class="incentive-detail-meta"><strong>Estado:</strong> {{ $detail->status }}</div>
                                <div class="small text-success mt-2"><strong>Motivo:</strong> {{ $detail->reason }}</div>
                            </div>
                        @empty
                            <div class="text-muted small">No hubo mantenimientos preventivos aprobados o realizados en este periodo.</div>
                        @endforelse
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <button type="button" class="btn btn-secondary" wire:click="closeDetail">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>

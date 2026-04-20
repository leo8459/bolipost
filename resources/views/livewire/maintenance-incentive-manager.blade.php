<div class="bp-livewire-skin">
    @include('livewire.partials.button-theme')
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

        .incentive-shell {
            background: #f5f7fb;
            border-radius: 20px;
            padding: 22px;
            border: 1px solid #e6ebf3;
        }
        .incentive-topnote {
            color: #4f6fa8;
            font-size: 12px;
            letter-spacing: .2em;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 2px;
        }
        .incentive-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            color: #12284d;
            line-height: 1.05;
        }
        .incentive-tabs {
            background: #eef2f8;
            border: 1px solid #dde4ef;
            border-radius: 16px;
            padding: 6px;
            display: inline-flex;
            gap: 8px;
        }
        .incentive-tab-btn {
            border: 0;
            border-radius: 12px;
            padding: 10px 22px;
            font-weight: 700;
            background: transparent;
            color: #4b5f84;
        }
        .incentive-tab-btn.is-active {
            background: #1f4c97;
            color: #fff;
            box-shadow: 0 6px 14px rgba(31, 76, 151, .25);
        }
        .incentive-filter-row {
            margin-top: 18px;
            background: #fff;
            border: 1px solid #e4e9f3;
            border-radius: 18px;
            padding: 14px;
        }
        .incentive-search {
            border-radius: 14px;
            background: #f3f6fb;
            border: 1px solid #e4e9f3;
            min-height: 44px;
        }
        .incentive-chip {
            border: 0;
            border-radius: 12px;
            padding: 9px 16px;
            font-weight: 700;
            font-size: 13px;
            background: #eef2f7;
            color: #516789;
        }
        .incentive-chip.is-active {
            background: #0f1f3d;
            color: #fff;
            box-shadow: 0 6px 14px rgba(15, 31, 61, .24);
        }
        .incentive-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e5ecf8;
            color: #4b6287;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 4px;
        }
        .incentive-grid {
            margin-top: 18px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 16px;
        }
        .incentive-card {
            background: #fff;
            border: 1px solid #e4e9f3;
            border-radius: 24px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(13, 35, 79, .05);
        }
        .incentive-rank {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: #f3efd9;
            color: #b57900;
            font-size: 30px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .incentive-driver {
            font-size: 32px;
            font-weight: 800;
            color: #1a2f53;
            line-height: 1;
        }
        .incentive-kpis {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: 14px 0;
        }
        .incentive-kpi {
            border-radius: 14px;
            border: 1px solid #edf1f7;
            background: #f8faff;
            padding: 12px;
        }
        .incentive-kpi-label {
            font-size: 11px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #7b8ca9;
            font-weight: 700;
        }
        .incentive-kpi-value {
            font-size: 28px;
            font-weight: 800;
            color: #223a60;
            line-height: 1.1;
        }
        .incentive-card-footer {
            border-top: 1px solid #e8edf5;
            padding-top: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .incentive-bars {
            display: inline-flex;
            gap: 4px;
            align-items: flex-end;
            min-height: 28px;
        }
        .incentive-bars span {
            width: 5px;
            border-radius: 3px;
            background: #4bdb77;
        }
        .incentive-detail-btn {
            border: 0;
            border-radius: 14px;
            padding: 10px 18px;
            background: #0f1f3d;
            color: #fff;
            font-weight: 700;
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
        @media (max-width: 768px) {
            .incentive-shell {
                padding: 14px;
            }
            .incentive-filter-row {
                padding: 10px;
            }
            .incentive-driver {
                font-size: 24px;
            }
        }
    </style>

    <div class="incentive-shell">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="incentive-topnote">Panel central</div>
                <div class="incentive-title">Incentivos y Metas</div>
            </div>
            <div class="incentive-tabs">
                <button type="button" wire:click="setViewMode('panel')" class="incentive-tab-btn {{ $viewMode === 'panel' ? 'is-active' : '' }}">PANEL</button>
                <button type="button" wire:click="setViewMode('reporte')" class="incentive-tab-btn {{ $viewMode === 'reporte' ? 'is-active' : '' }}">REPORTE</button>
            </div>
        </div>

        <div class="incentive-filter-row">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg-5">
                    <input type="text" wire:model.live.debounce.350ms="search" class="form-control incentive-search" placeholder="Buscar conductor de elite...">
                </div>
                <div class="col-12 col-lg-4 d-flex flex-wrap gap-2">
                    <button type="button" wire:click="$set('statusFilter','todos')" class="incentive-chip {{ $statusFilter === 'todos' ? 'is-active' : '' }}">Todos</button>
                    <button type="button" wire:click="$set('statusFilter','excelente')" class="incentive-chip {{ $statusFilter === 'excelente' ? 'is-active' : '' }}">Excelente</button>
                    <button type="button" wire:click="$set('statusFilter','regular')" class="incentive-chip {{ $statusFilter === 'regular' ? 'is-active' : '' }}">Regular</button>
                </div>
                <div class="col-12 col-lg-3 d-flex align-items-center justify-content-lg-end">
                    <div>
                        @foreach($reports->take(4) as $avatarReport)
                            @php
                                $name = (string) ($avatarReport->driver?->nombre ?? 'N/A');
                                $parts = preg_split('/\s+/', trim($name)) ?: [];
                                $initials = strtoupper(substr((string) ($parts[0] ?? 'N'), 0, 1) . substr((string) ($parts[1] ?? ''), 0, 1));
                            @endphp
                            <span class="incentive-avatar">{{ $initials !== '' ? $initials : 'NA' }}</span>
                        @endforeach
                    </div>
                    <span class="small text-muted fw-bold ms-2">Activos ahora</span>
                </div>
            </div>
            <div class="row g-2 align-items-end mt-1">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold mb-1">Fecha desde</label>
                    <input type="date" wire:model.live="date_from" class="form-control">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold mb-1">Fecha hasta</label>
                    <input type="date" wire:model.live="date_to" class="form-control">
                </div>
                <div class="col-12 col-md-4 d-flex align-items-center">
                    <div class="form-check bp-switch mt-3">
                        <input type="checkbox" id="onlyPerfectPanel" wire:model.live="onlyPerfect" class="form-check-input">
                        <label for="onlyPerfectPanel" class="form-check-label fw-bold">Solo conductores con {{ $maxStars }} estrellas</label>
                    </div>
                </div>
            </div>
        </div>

        @if($viewMode === 'panel')
            <div class="incentive-grid">
                @forelse($reports as $report)
                    @php
                        $starsEnd = (int) $report->stars_end;
                        $healthLabel = $starsEnd === $maxStars ? 'Optimo' : ($starsEnd >= max($maxStars - 1, 1) ? 'Atencion' : 'Riesgo');
                        $healthClass = $starsEnd === $maxStars ? 'text-success' : ($starsEnd >= max($maxStars - 1, 1) ? 'text-warning' : 'text-danger');
                        $trendBars = min(5, max(1, $maxStars - max(0, (int) $report->discountable_events)));
                    @endphp
                    <div class="incentive-card">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <span class="incentive-rank">{{ $loop->iteration }}</span>
                            <div class="flex-grow-1">
                                <div class="incentive-driver">{{ $report->driver?->nombre ?? 'Sin conductor' }}</div>
                                <div class="small text-muted">{{ $periodLabel }}</div>
                            </div>
                            <span class="text-muted"><i class="fas fa-ellipsis-v"></i></span>
                        </div>
                        <div class="incentive-kpis">
                            <div class="incentive-kpi">
                                <div class="incentive-kpi-label">Incentivo</div>
                                <div class="incentive-kpi-value"><i class="fas fa-star text-warning me-1"></i>{{ number_format((float) $report->stars_end, 1) }}</div>
                            </div>
                            <div class="incentive-kpi">
                                <div class="incentive-kpi-label">Salud activo</div>
                                <div class="incentive-kpi-value {{ $healthClass }}"><i class="fas fa-shield-alt me-1"></i>{{ $healthLabel }}</div>
                            </div>
                        </div>
                        <div class="incentive-card-footer">
                            <div>
                                <div class="small text-muted fw-bold mb-1">Tendencia</div>
                                <div class="incentive-bars">
                                    @for($i = 0; $i < 5; $i++)
                                        <span style="height: {{ 16 + ($i < $trendBars ? 8 : 2) }}px; opacity: {{ $i < $trendBars ? '1' : '.25' }}"></span>
                                    @endfor
                                </div>
                            </div>
                            <button type="button" class="incentive-detail-btn" wire:click="showDetail({{ $report->driver_id }})">Ver Detalles</button>
                        </div>
                    </div>
                @empty
                    <div class="card border-0 shadow-sm p-4 text-center text-muted">No hay conductores para mostrar en este panel.</div>
                @endforelse
            </div>
        @else
            <div class="row g-3 mt-2 mb-3">
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
                <div class="col-12 col-md-4 d-flex align-items-end">
                    <button type="button" class="btn btn-outline-primary w-100" wire:click="exportReportPdf">
                        <i class="fas fa-file-pdf me-2"></i>Descargar reporte PDF
                    </button>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Conductores con puntaje completo</div>
                            <div class="fs-3 fw-bold text-success">{{ $perfectCount }}</div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">Conductores con descuento</div>
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
                                        $statusLabel = $starsEnd === $maxStars ? 'Excelente' : ($starsEnd >= max($maxStars - 1, 1) ? 'Con observacion' : 'Bajo');
                                        $statusClass = $starsEnd === $maxStars ? 'bg-success' : ($starsEnd >= max($maxStars - 1, 1) ? 'bg-warning text-dark' : 'bg-danger');
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold">{{ $report->driver?->nombre ?? 'Sin conductor' }}</div>
                                            <div class="small text-muted">Inicio con {{ $report->stars_start }} estrella(s)</div>
                                        </td>
                                        <td style="min-width: 170px;">
                                            <div class="fw-bold fs-5 text-warning">{{ str_repeat('★', $starsEnd) }}{{ str_repeat('☆', max($maxStars - $starsEnd, 0)) }}</div>
                                            <div class="small text-muted">{{ $starsEnd }} de {{ $maxStars }} estrellas</div>
                                        </td>
                                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td style="min-width: 280px;">
                                            <div class="small"><strong>{{ $discounts }}</strong> mantenimiento(s) le descontaron estrellas.</div>
                                            <div class="small text-muted">Preventivos sin descuento: {{ $preventives }}.</div>
                                            <div class="small text-muted">Total revisado en el periodo: {{ $report->total_requests }}.</div>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-outline-primary btn-sm" wire:click="showDetail({{ $report->driver_id }})"><i class="fas fa-eye"></i></button>
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
        @endif
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

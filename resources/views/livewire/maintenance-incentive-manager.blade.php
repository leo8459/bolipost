<div class="bp-livewire-skin">
    @include('livewire.partials.button-theme')
    <style>
        .bp-livewire-skin {
            font-family: Verdana, Geneva, Tahoma, sans-serif;
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

        .report-shell {
            margin-top: 18px;
            display: grid;
            gap: 18px;
        }
        .report-hero {
            position: relative;
            overflow: hidden;
            border-radius: 24px;
            padding: 24px;
            background:
                radial-gradient(circle at top right, rgba(255, 199, 67, .25), transparent 30%),
                linear-gradient(135deg, #0f2348 0%, #1f4c97 58%, #7fa8e8 100%);
            color: #fff;
            box-shadow: 0 18px 40px rgba(15, 35, 72, .18);
        }
        .report-hero::after {
            content: "";
            position: absolute;
            right: -60px;
            bottom: -60px;
            width: 220px;
            height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,.08);
        }
        .report-eyebrow {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .24em;
            font-weight: 700;
            opacity: .8;
            margin-bottom: 8px;
        }
        .report-title {
            font-size: clamp(1.7rem, 3vw, 2.4rem);
            line-height: 1.05;
            font-weight: 800;
            margin-bottom: 10px;
        }
        .report-subtitle {
            max-width: 720px;
            color: rgba(255,255,255,.86);
            font-size: 14px;
        }
        .report-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 999px;
            padding: 8px 14px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.14);
            font-size: 13px;
            font-weight: 700;
        }
        .report-toolbar {
            background: #fff;
            border: 1px solid #e4e9f3;
            border-radius: 22px;
            padding: 18px;
            box-shadow: 0 8px 22px rgba(18, 40, 77, .06);
        }
        .report-toolbar-title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .16em;
            font-weight: 800;
            color: #5a739e;
            margin-bottom: 12px;
        }
        .report-download-btn {
            border-radius: 14px;
            min-height: 46px;
            font-weight: 800;
        }
        .report-stats {
            display: grid;
            grid-template-columns: 1.35fr repeat(3, minmax(0, 1fr));
            gap: 16px;
        }
        .report-stat-card {
            background: #fff;
            border: 1px solid #e5ebf4;
            border-radius: 22px;
            padding: 20px;
            box-shadow: 0 8px 24px rgba(15, 35, 72, .05);
        }
        .report-stat-card.is-highlight {
            background: linear-gradient(135deg, #fff8e6 0%, #ffffff 100%);
            border-color: #f4d78f;
        }
        .report-stat-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .14em;
            font-weight: 800;
            color: #6f81a1;
            margin-bottom: 8px;
        }
        .report-stat-value {
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            line-height: 1;
            font-weight: 800;
            color: #17345f;
        }
        .report-stat-text {
            font-size: 15px;
            font-weight: 700;
            color: #17345f;
        }
        .report-stat-meta {
            color: #6d7f9d;
            font-size: 13px;
            margin-top: 8px;
        }
        .report-table-card {
            background: #fff;
            border: 1px solid #e5ebf4;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 28px rgba(15, 35, 72, .05);
        }
        .report-table-head {
            padding: 18px 20px;
            border-bottom: 1px solid #e8edf5;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            background: linear-gradient(180deg, #fbfcfe 0%, #f3f6fb 100%);
        }
        .report-table-title {
            font-size: 20px;
            font-weight: 800;
            color: #17345f;
            margin: 0;
        }
        .report-table-subtitle {
            margin: 4px 0 0;
            color: #6d7f9d;
            font-size: 13px;
        }
        .report-count-badge {
            border-radius: 999px;
            padding: 8px 14px;
            background: #eef3fb;
            color: #244a87;
            font-size: 13px;
            font-weight: 800;
        }
        .report-table {
            margin: 0;
        }
        .report-table thead th {
            border: 0;
            border-bottom: 1px solid #e8edf5;
            background: #f8faff;
            color: #335487;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-size: 12px;
            font-weight: 800;
            padding: 15px 18px;
        }
        .report-table tbody td {
            padding: 18px;
            vertical-align: top;
            border-color: #edf1f7;
        }
        .report-row-driver {
            min-width: 220px;
        }
        .report-driver-name {
            font-size: 18px;
            font-weight: 800;
            color: #17345f;
            margin-bottom: 4px;
        }
        .report-driver-meta {
            color: #7a8ba6;
            font-size: 13px;
        }
        .report-stars {
            color: #ffb400;
            font-size: 20px;
            line-height: 1;
            letter-spacing: .08em;
            margin-bottom: 6px;
        }
        .report-summary-box {
            border-radius: 16px;
            background: #f7f9fd;
            border: 1px solid #e8edf5;
            padding: 12px 14px;
        }
        .report-summary-line {
            font-size: 13px;
            color: #405372;
        }
        .report-summary-line strong {
            color: #18365f;
        }
        .report-view-btn {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #d7e2f2;
            color: #1f4c97;
            background: #fff;
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }
        .report-view-btn:hover {
            transform: translateY(-1px);
            border-color: #8aa7d8;
            box-shadow: 0 10px 20px rgba(31, 76, 151, .12);
            color: #163f81;
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
            .report-stats {
                grid-template-columns: 1fr;
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
                <a href="{{ route('livewire.maintenance-incentives', array_merge(request()->query(), ['view' => 'panel'])) }}" class="incentive-tab-btn {{ $viewMode === 'panel' ? 'is-active' : '' }}" style="text-decoration:none;">PANEL</a>
                <a href="{{ route('livewire.maintenance-incentives', array_merge(request()->query(), ['view' => 'reporte'])) }}" class="incentive-tab-btn {{ $viewMode === 'reporte' ? 'is-active' : '' }}" style="text-decoration:none;">REPORTE</a>
            </div>
        </div>

        <div class="incentive-filter-row">
            <form method="GET" action="{{ route('livewire.maintenance-incentives') }}" class="row g-2 align-items-center">
                <input type="hidden" name="view" value="{{ $viewMode }}">
                <div class="col-12 col-lg-5">
                    <input type="text" name="search" value="{{ $search }}" class="form-control incentive-search" placeholder="Buscar conductor de elite...">
                </div>
                <div class="col-12 col-lg-4 d-flex flex-wrap gap-2">
                    <a href="{{ route('livewire.maintenance-incentives', array_merge(request()->query(), ['status' => 'todos'])) }}" class="incentive-chip {{ $statusFilter === 'todos' ? 'is-active' : '' }}" style="text-decoration:none;">Todos</a>
                    <a href="{{ route('livewire.maintenance-incentives', array_merge(request()->query(), ['status' => 'excelente'])) }}" class="incentive-chip {{ $statusFilter === 'excelente' ? 'is-active' : '' }}" style="text-decoration:none;">Excelente</a>
                    <a href="{{ route('livewire.maintenance-incentives', array_merge(request()->query(), ['status' => 'regular'])) }}" class="incentive-chip {{ $statusFilter === 'regular' ? 'is-active' : '' }}" style="text-decoration:none;">Regular</a>
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
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold mb-1">Fecha desde</label>
                    <input type="date" name="date_from" value="{{ $date_from }}" class="form-control">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label fw-bold mb-1">Fecha hasta</label>
                    <input type="date" name="date_to" value="{{ $date_to }}" class="form-control">
                </div>
                <div class="col-12 col-md-4 d-flex align-items-center gap-2 pt-3">
                    <a
                        href="{{ route('livewire.maintenance-incentives', array_merge(request()->query(), ['perfect' => $onlyPerfect ? 0 : 1])) }}"
                        class="btn btn-outline-primary btn-sm"
                    >
                        {{ $onlyPerfect ? 'Ver todos' : 'Solo 5 estrellas' }}
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm">Aplicar filtros</button>
                </div>
            </form>
            <div class="row g-2 align-items-end mt-1 d-none">
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
                            <a
                                href="{{ route('livewire.maintenance-incentives', array_merge(request()->query(), ['detail_driver_id' => $report->driver_id])) }}"
                                class="incentive-detail-btn"
                                style="text-decoration:none;"
                            >
                                Ver Detalles
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="card border-0 shadow-sm p-4 text-center text-muted">No hay conductores para mostrar en este panel.</div>
                @endforelse
            </div>
        @else
            <div class="report-shell">
                <div class="report-hero">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 position-relative" style="z-index:1;">
                        <div>
                            <div class="report-eyebrow">Resumen ejecutivo</div>
                            <div class="report-title">Reporte de incentivos por mantenimiento</div>
                            <div class="report-subtitle">
                                Visualiza cumplimiento preventivo, descuentos de estrellas y rendimiento del equipo en un solo panel, con un formato más claro para seguimiento y cierre mensual.
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="report-pill"><i class="fas fa-calendar-alt"></i>{{ $periodLabel }}</span>
                            <span class="report-pill"><i class="fas fa-star"></i>{{ $perfectCount }} con puntaje completo</span>
                        </div>
                    </div>
                </div>

                <div class="report-toolbar">
                    <div class="report-toolbar-title">Configuracion del reporte</div>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Fecha desde</label>
                            <input type="date" wire:model.live="date_from" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-bold">Fecha hasta</label>
                            <input type="date" wire:model.live="date_to" class="form-control">
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-check bp-switch mb-3">
                                <input type="checkbox" id="onlyPerfect" wire:model.live="onlyPerfect" class="form-check-input">
                                <label for="onlyPerfect" class="form-check-label fw-bold">Mostrar solo quienes mantienen {{ $maxStars }} estrellas</label>
                            </div>
                            <a
                                class="btn btn-primary report-download-btn w-100"
                                href="{{ route('maintenance-incentives.export.pdf', ['date_from' => $date_from, 'date_to' => $date_to, 'search' => $search, 'status' => $statusFilter, 'perfect' => $onlyPerfect ? 1 : 0]) }}"
                                target="_blank"
                            >
                                <i class="fas fa-file-pdf me-2"></i>Descargar reporte PDF
                            </a>
                        </div>
                    </div>
                </div>

                <div class="report-stats">
                    <div class="report-stat-card is-highlight">
                        <div class="report-stat-label">Mejor conductor del periodo</div>
                        <div class="report-stat-text">{{ $bestDriver?->driver?->nombre ?? 'Sin datos para ranking' }}</div>
                        <div class="report-stars mt-3">
                            @if($bestDriver)
                                {{ str_repeat('★', (int) $bestDriver->stars_end) }}{{ str_repeat('☆', max($maxStars - (int) $bestDriver->stars_end, 0)) }}
                            @else
                                -
                            @endif
                        </div>
                        <div class="report-stat-meta">
                            @if($bestDriver)
                                {{ (int) $bestDriver->preventive_requests }} preventivos cumplidos durante {{ $periodLabel }}.
                            @else
                                No hay suficientes registros para generar el ranking.
                            @endif
                        </div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-label">Conductores evaluados</div>
                        <div class="report-stat-value">{{ $reports->count() }}</div>
                        <div class="report-stat-meta">Total incluidos en el corte actual.</div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-label">Puntaje completo</div>
                        <div class="report-stat-value text-success">{{ $perfectCount }}</div>
                        <div class="report-stat-meta">Mantienen las {{ $maxStars }} estrellas.</div>
                    </div>
                    <div class="report-stat-card">
                        <div class="report-stat-label">Con descuento</div>
                        <div class="report-stat-value text-danger">{{ $discountedCount }}</div>
                        <div class="report-stat-meta">Tuvieron observaciones o descuentos.</div>
                    </div>
                </div>

                <div class="report-table-card">
                    <div class="report-table-head">
                        <div>
                            <h3 class="report-table-title">Detalle por conductor</h3>
                            <p class="report-table-subtitle">Lectura consolidada del rendimiento, descuento de estrellas y cumplimiento preventivo.</p>
                        </div>
                        <span class="report-count-badge">{{ $reports->count() }} registro(s)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table report-table align-middle">
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
                                        <td class="report-row-driver">
                                            <div class="report-driver-name">{{ $report->driver?->nombre ?? 'Sin conductor' }}</div>
                                            <div class="report-driver-meta">Inicio con {{ $report->stars_start }} estrella(s)</div>
                                        </td>
                                        <td style="min-width: 170px;">
                                            <div class="report-stars">{{ str_repeat('★', $starsEnd) }}{{ str_repeat('☆', max($maxStars - $starsEnd, 0)) }}</div>
                                            <div class="report-driver-meta">{{ $starsEnd }} de {{ $maxStars }} estrellas</div>
                                        </td>
                                        <td><span class="badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                                        <td style="min-width: 280px;">
                                            <div class="report-summary-box">
                                                <div class="report-summary-line"><strong>{{ $discounts }}</strong> mantenimiento(s) le descontaron estrellas.</div>
                                                <div class="report-summary-line">Preventivos sin descuento: <strong>{{ $preventives }}</strong>.</div>
                                                <div class="report-summary-line">Total revisado en el periodo: <strong>{{ $report->total_requests }}</strong>.</div>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a
                                                href="{{ route('livewire.maintenance-incentives', array_merge(request()->query(), ['detail_driver_id' => $report->driver_id])) }}"
                                                class="report-view-btn"
                                                title="Ver detalle de {{ $report->driver?->nombre ?? 'conductor' }}"
                                            ><i class="fas fa-eye"></i></a>
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
                    <a href="{{ route('livewire.maintenance-incentives', request()->except('detail_driver_id')) }}" class="btn-close" aria-label="Cerrar"></a>
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
                        <div class="col-12 col-md-4">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <div class="text-muted small">Historial total conductor</div>
                                <div class="fw-bold fs-4 text-primary">{{ (int) ($detailHistoryStats['total'] ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <div class="text-muted small">Historial con descuento</div>
                                <div class="fw-bold fs-4 text-danger">{{ (int) ($detailHistoryStats['discounts'] ?? 0) }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="border rounded-3 p-3 h-100 bg-light">
                                <div class="text-muted small">Historial sin descuento</div>
                                <div class="fw-bold fs-4 text-success">{{ (int) ($detailHistoryStats['ok'] ?? 0) }}</div>
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

                    <div class="border rounded-3 p-3 bg-light mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="fw-bold text-primary">Historial completo de mantenimientos del conductor</div>
                            <span class="badge bg-primary">{{ count($detailFullHistory) }} registro(s)</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Tipo</th>
                                        <th>Vehiculo</th>
                                        <th>Estado</th>
                                        <th>Solicitud</th>
                                        <th>Cita</th>
                                        <th>Impacto</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($detailFullHistory as $item)
                                        <tr>
                                            <td>{{ $item['id'] }}</td>
                                            <td>
                                                <div class="fw-semibold">{{ $item['maintenance_type'] }}</div>
                                                <div class="small text-muted">{{ $item['origin'] }}</div>
                                            </td>
                                            <td>
                                                <div>{{ $item['vehicle_label'] }}</div>
                                                <div class="small text-muted">Placa: {{ $item['vehicle_plate'] }}</div>
                                            </td>
                                            <td>{{ $item['status'] }}</td>
                                            <td>{{ $item['request_date'] }}</td>
                                            <td>{{ $item['scheduled_date'] }}</td>
                                            <td>
                                                <span class="badge bg-{{ $item['impact_class'] }}">{{ $item['impact'] }}</span>
                                                <div class="small text-muted mt-1">{{ $item['impact_reason'] }}</div>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    @if(!empty($item['evidence_url']))
                                                        <a href="{{ $item['evidence_url'] }}" target="_blank" class="btn btn-outline-primary btn-sm">Evidencia</a>
                                                    @endif
                                                    @if(!empty($item['form_url']))
                                                        <a href="{{ $item['form_url'] }}" target="_blank" class="btn btn-outline-secondary btn-sm">Documento</a>
                                                    @endif
                                                    @if(empty($item['evidence_url']) && empty($item['form_url']))
                                                        <span class="small text-muted">Sin archivos</span>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-3">No hay historial de mantenimientos para este conductor.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    <a href="{{ route('livewire.maintenance-incentives', request()->except('detail_driver_id')) }}" class="btn btn-secondary">Cerrar</a>
                </div>
            </div>
        </div>
    @endif
</div>

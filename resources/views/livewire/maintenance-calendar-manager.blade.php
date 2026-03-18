<div>
    <style>
        .maintenance-calendar .calendar-cell {
            min-height: 165px;
            vertical-align: top;
            width: 14.285%;
        }
        .maintenance-calendar .calendar-day-muted {
            background: #f4f6f8;
            color: #8a949e;
        }
        .maintenance-calendar .calendar-day-today {
            outline: 2px solid #0d6efd;
            outline-offset: -2px;
        }
        .maintenance-calendar .calendar-day-selected {
            box-shadow: inset 0 0 0 2px #198754;
            background: #f3fbf6;
        }
        .maintenance-calendar .calendar-cell-clickable {
            cursor: pointer;
        }
        .maintenance-calendar .event-pill {
            border-width: 1px;
            border-style: solid;
            border-radius: 8px;
            padding: 6px 8px;
            margin-bottom: 6px;
            font-size: 0.78rem;
            line-height: 1.2;
            word-break: break-word;
        }
        .maintenance-calendar .event-title {
            font-weight: 700;
            margin-bottom: 2px;
        }
        .maintenance-calendar .event-meta {
            opacity: 0.9;
        }
        .maintenance-calendar .event-dots {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 6px;
        }
        .maintenance-calendar .event-dot {
            width: 9px;
            height: 9px;
            border-radius: 999px;
            display: inline-block;
        }
        .maintenance-calendar .event-dot-success {
            background: #198754;
        }
        .maintenance-calendar .event-dot-warning {
            background: #ffc107;
        }
        .maintenance-calendar .event-dot-danger {
            background: #dc3545;
        }
        .maintenance-calendar .event-dot-secondary {
            background: #6c757d;
        }
        @media (max-width: 992px) {
            .maintenance-calendar .calendar-cell {
                min-height: 130px;
            }
        }
    </style>

    <div class="page-title mb-4 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h1 class="h3 mb-0">
            <i class="fas fa-calendar-alt me-2 text-primary"></i>Calendario de Mantenimiento
        </h1>
        <div class="d-flex gap-2">
            <button type="button" wire:click="previousMonth" class="btn btn-outline-primary">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button type="button" wire:click="goToCurrentMonth" class="btn btn-primary">Hoy</button>
            <button type="button" wire:click="nextMonth" class="btn btn-outline-primary">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h5 class="mb-0">{{ $monthLabel }}</h5>
            <div class="d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label class="form-label mb-1 small fw-bold">Ir a fecha</label>
                    <input type="date" wire:model.live="selected_date" class="form-control form-control-sm">
                </div>
                <div class="d-flex flex-wrap gap-2 small">
                    <span class="badge border border-success bg-success-subtle text-success-emphasis">Verde: En ventana segura</span>
                    <span class="badge border border-warning bg-warning-subtle text-warning-emphasis">Amarillo: <=2 dias o <=5 km</span>
                    <span class="badge border border-danger bg-danger-subtle text-danger-emphasis">Rojo: Vencido/no realizado</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 align-items-start">
        <div class="col-12 col-xl-9">
            <div class="card shadow-sm maintenance-calendar">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    @foreach($weekDays as $dayName)
                                        <th class="text-center">{{ $dayName }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($weeks as $week)
                                    <tr>
                                        @foreach($week as $day)
                                            <td
                                                wire:click="selectDate('{{ $day['date_key'] }}')"
                                                class="calendar-cell calendar-cell-clickable {{ !$day['is_current_month'] ? 'calendar-day-muted' : '' }} {{ $day['is_today'] ? 'calendar-day-today' : '' }} {{ $day['is_selected'] ? 'calendar-day-selected' : '' }}"
                                            >
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="fw-bold">{{ $day['date']->format('d') }}</span>
                                                    @if($day['is_today'])
                                                        <span class="badge bg-primary">Hoy</span>
                                                    @endif
                                                </div>

                                                @if(empty($day['events']))
                                                    <div class="small text-muted">Sin eventos</div>
                                                @else
                                                    <div class="small text-muted">{{ count($day['events']) }} evento(s)</div>
                                                    <div class="event-dots">
                                                        @foreach($day['events'] as $event)
                                                            @php
                                                                $dotClass = 'event-dot-secondary';
                                                                if (str_contains($event['css'] ?? '', 'danger')) {
                                                                    $dotClass = 'event-dot-danger';
                                                                } elseif (str_contains($event['css'] ?? '', 'warning')) {
                                                                    $dotClass = 'event-dot-warning';
                                                                } elseif (str_contains($event['css'] ?? '', 'success')) {
                                                                    $dotClass = 'event-dot-success';
                                                                }
                                                            @endphp
                                                            <span class="event-dot {{ $dotClass }}" title="{{ $event['source'] }} | {{ $event['stage'] }} - {{ $event['title'] }}"></span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-3">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Agenda del dia</strong>
                    <span class="badge bg-success">{{ $selectedDateLabel }}</span>
                </div>
                <div class="card-body p-0">
                    @php
                        $topEvents = array_slice($selectedDayEvents, 0, 5);
                    @endphp
                    <table class="table mb-0">
                        <tbody>
                            @forelse($topEvents as $event)
                                <tr>
                                    <td>
                                        <div class="small fw-semibold">{{ $event['source'] }} | {{ $event['stage'] }}</div>
                                        <div class="small">{{ $event['title'] }}</div>
                                        <div class="small text-muted">{{ $event['detail'] }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="small text-muted">No hay mantenimientos para esta fecha.</td>
                                </tr>
                            @endforelse
                            @for($i = count($topEvents); $i < 5; $i++)
                                <tr>
                                    <td class="small text-muted">Sin registro</td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                @if(count($selectedDayEvents) > 5)
                    <div class="card-footer small text-muted">
                        Mostrando 5 de {{ count($selectedDayEvents) }} registros del dia.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

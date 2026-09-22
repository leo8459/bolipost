@php
    $executiveMonthNames = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];
    $executivePeriod = collect($selectedMonths ?? [])
        ->map(fn ($month) => $executiveMonthNames[(int) $month] ?? (string) $month)
        ->implode(', ');
    $executivePeriod = ($executivePeriod !== '' ? $executivePeriod : 'Periodo no definido') . ' de ' . ($anio ?? now()->year);
    $executiveSummaryItems = collect([[
        'label' => 'Periodo analizado',
        'value' => $executivePeriod,
        'detail' => 'Información consolidada de los meses seleccionados.',
        'icon' => 'fa-calendar-alt',
        'color' => 'primary',
    ]])->concat($executiveItems ?? []);
@endphp

<section class="card executive-summary-card shadow-sm" aria-labelledby="executive-summary-title">
    <div class="card-header executive-summary-header border-0">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
            <div class="d-flex align-items-center">
                <span class="executive-summary-main-icon"><i class="fas fa-chart-pie"></i></span>
                <div>
                    <h2 id="executive-summary-title" class="h5 font-weight-bold mb-1">{{ $executiveTitle ?? 'Reporte ejecutivo resumido' }}</h2>
                    <div class="small text-white-50">Lectura rápida para apoyar la toma de decisiones.</div>
                </div>
            </div>
            <span class="executive-summary-badge mt-2 mt-md-0"><i class="fas fa-bolt mr-1"></i> Resumen claro</span>
        </div>
    </div>
    <div class="card-body">
        <p class="executive-summary-lead mb-4">{{ $executiveLead ?? 'Este resumen presenta los principales resultados del periodo seleccionado.' }}</p>

        <div class="row">
            @foreach($executiveSummaryItems as $item)
                <div class="col-sm-6 col-xl-3 mb-3">
                    <div class="executive-summary-item h-100">
                        <span class="executive-summary-item-icon text-{{ $item['color'] ?? 'primary' }}">
                            <i class="fas {{ $item['icon'] ?? 'fa-check-circle' }}"></i>
                        </span>
                        <div class="executive-summary-item-label">{{ $item['label'] ?? '' }}</div>
                        <div class="executive-summary-item-value">{{ $item['value'] ?? '-' }}</div>
                        @if(filled($item['detail'] ?? null))
                            <div class="executive-summary-item-detail">{{ $item['detail'] }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if(filled($executiveNote ?? null))
            <div class="executive-summary-note">
                <i class="fas fa-lightbulb mr-2"></i>
                <div><strong>Conclusión:</strong> {{ $executiveNote }}</div>
            </div>
        @endif
    </div>
</section>

@once
    @push('css')
        <style>
            .executive-summary-card { overflow: hidden; border: 0; border-radius: 14px; }
            .executive-summary-header { padding: 17px 20px; background: linear-gradient(120deg, #123f73 0%, #13539b 68%, #1f6abf 100%); color: #fff; }
            .executive-summary-main-icon { display: inline-flex; width: 44px; height: 44px; margin-right: 12px; align-items: center; justify-content: center; flex: 0 0 44px; border-radius: 12px; background: rgba(255, 255, 255, .14); color: #f5c518; font-size: 1.15rem; }
            .executive-summary-badge { padding: 6px 11px; border: 1px solid rgba(255, 255, 255, .28); border-radius: 16px; background: rgba(255, 255, 255, .1); color: #fff; font-size: .76rem; font-weight: 700; }
            .executive-summary-lead { padding: 13px 15px; border-left: 4px solid #f5b800; border-radius: 0 8px 8px 0; background: #fff9e6; color: #3e4c59; font-size: .96rem; line-height: 1.55; }
            .executive-summary-item { position: relative; padding: 15px; border: 1px solid #e2e8f0; border-radius: 11px; background: #fff; }
            .executive-summary-item-icon { position: absolute; top: 13px; right: 13px; font-size: 1.05rem; opacity: .85; }
            .executive-summary-item-label { padding-right: 25px; color: #64748b; font-size: .72rem; font-weight: 700; letter-spacing: .35px; text-transform: uppercase; }
            .executive-summary-item-value { margin-top: 7px; color: #163f6d; font-size: 1.08rem; font-weight: 700; overflow-wrap: anywhere; }
            .executive-summary-item-detail { margin-top: 5px; color: #718096; font-size: .78rem; line-height: 1.35; }
            .executive-summary-note { display: flex; align-items: flex-start; padding: 12px 14px; border-radius: 9px; background: #eef6ff; color: #24496f; line-height: 1.45; }
            .executive-summary-note > i { margin-top: 3px; color: #f0ad00; }
        </style>
    @endpush
@endonce

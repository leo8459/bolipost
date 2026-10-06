@extends('adminlte::page')

@section('title', 'Facturado - Contratos')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Facturado</h1>
            <small class="text-muted">
                Facturación correspondiente únicamente al servicio de contratos.
            </small>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i> Dashboard
        </a>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        </div>
    @endif
    @if(session('errors')?->any())
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('errors')->first() }}
        </div>
    @endif
        <div class="card card-outline card-primary mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('dashboard.conciliacion.facturado') }}" class="form-row align-items-end">
                    <input type="hidden" name="limite" value="{{ $limite }}">
                    <div class="col-sm-5 col-md-3 mb-2 mb-sm-0">
                        <label for="contract-report-month" class="small mb-1">Mes facturado</label>
                        <select id="contract-report-month" name="meses[]" class="form-control">
                            @foreach([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $number => $monthName)
                                <option value="{{ $number }}" @selected(in_array($number, $selectedMonths, true))>{{ $monthName }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-4 col-md-2 mb-2 mb-sm-0">
                        <label for="contract-report-year" class="small mb-1">Año</label>
                        <input id="contract-report-year" type="number" name="anio" class="form-control" min="2000" max="{{ now()->year + 3 }}" value="{{ $anio }}">
                    </div>
                    <div class="col-sm-3 col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-filter mr-1"></i> Mostrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

    @if($errors->isNotEmpty())
        <div class="alert alert-warning">
            <div class="font-weight-bold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> Algunos meses no pudieron cargarse</div>
            <ul class="mb-0 pl-3">
                @foreach($errors as $reportError)
                    <li>{{ $reportError }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $executiveTopService = $serviceGroups->first();
        $executiveTotalIncome = (float) ($summary['totalMontoVendido'] ?? $summary['totalMonto'] ?? 0);
        $executiveTopIncome = (float) ($executiveTopService['totalMontoVendido'] ?? $executiveTopService['totalMonto'] ?? 0);
        $executiveTopShare = $executiveTotalIncome > 0 ? ($executiveTopIncome / $executiveTotalIncome) * 100 : 0;
        $executiveLead = 'En el periodo seleccionado se registraron '
            . \App\Support\BolivianNumber::format((float) ($summary['cantidadVentas'] ?? 0))
            . ' ventas y una cantidad total de paquetería de '
            . \App\Support\BolivianNumber::format((float) ($summary['totalCantidad'] ?? 0), 2)
            . ', con un total vendido de Bs '
            . \App\Support\BolivianNumber::format($executiveTotalIncome, 2) . '.';
        $executiveItems = [
            [
                'label' => 'Ventas registradas',
                'value' => \App\Support\BolivianNumber::format((float) ($summary['cantidadVentas'] ?? 0)),
                'detail' => \App\Support\BolivianNumber::format((float) ($summary['cantidadServicios'] ?? 0)) . ' servicios agrupados analizados.',
                'icon' => 'fa-file-invoice',
                'color' => 'info',
            ],
            [
                'label' => 'Total vendido',
                'value' => 'Bs ' . \App\Support\BolivianNumber::format($executiveTotalIncome, 2),
                'detail' => 'Importe incluido en el total financiero del periodo.',
                'icon' => 'fa-money-bill-wave',
                'color' => 'success',
            ],
            [
                'label' => 'Servicio con mayor ingreso',
                'value' => $executiveTopService['servicio'] ?? 'Sin datos',
                'detail' => $executiveTopService
                    ? 'Bs ' . \App\Support\BolivianNumber::format($executiveTopIncome, 2) . ' (' . \App\Support\BolivianNumber::format($executiveTopShare, 1) . '% del ingreso).'
                    : 'No existen ventas para la selección realizada.',
                'icon' => 'fa-trophy',
                'color' => 'warning',
            ],
        ];
        $executiveNote = (float) ($summary['contratosPorCobrarMonto'] ?? 0) > 0
            ? 'Existen Bs ' . \App\Support\BolivianNumber::format((float) $summary['contratosPorCobrarMonto'], 2) . ' en cuentas por cobrar correspondientes a contratos pendientes de validación.'
            : 'No se registran cuentas por cobrar de contratos dentro de la selección actual.';
    @endphp
    @include('financial-reports.partials.executive-summary')

        @include('financial-reports.partials.invoice-rows-table', [
            'tableTitle' => 'Facturas del servicio de contratos',
            'tableHelp' => 'Detalle completo de las ventas facturadas para el período seleccionado.',
            'salesCount' => $summary['cantidadVentas'] ?? 0,
            'showReceivableActions' => true,
        ])
@stop


@extends('adminlte::page')

@section('title', 'Ventas por servicio')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Ventas por servicio</h1>
            <small class="text-muted">Resumen consolidado de uno o varios servicios y meses.</small>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i> Dashboard
        </a>
    </div>
@stop

@push('css')
    <style>
        .service-group-row { cursor: pointer; background: #f8fbff !important; border-top: 2px solid #d8e6f5; }
        .service-group-row:hover { background: #edf5ff !important; }
        .service-group-row:focus { outline: 2px solid #13539b; outline-offset: -2px; }
        .group-chevron { display: inline-flex; width: 24px; height: 24px; margin-right: 3px; align-items: center; justify-content: center; border-radius: 50%; color: #13539b; background: #e2eefc; transition: transform .2s ease; }
        .service-group-row.is-open .group-chevron { transform: rotate(90deg); }
        .group-icon { display: inline-flex; width: 30px; height: 30px; margin-right: 7px; align-items: center; justify-content: center; border-radius: 8px; background: #13539b; color: #fff; }
        .service-child-row { background: #fff; }
        .service-child-row:hover { background: #fffdf5 !important; }
        .child-service-name { position: relative; padding-left: 52px !important; font-weight: 600; }
        .child-connector { position: absolute; left: 24px; top: 0; width: 18px; height: 50%; border-left: 2px solid #cbd5e0; border-bottom: 2px solid #cbd5e0; border-radius: 0 0 0 6px; }
    </style>
@endpush

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-group-toggle]').forEach(function (row) {
                function toggleGroup() {
                    var groupId = row.dataset.groupToggle;
                    var open = row.getAttribute('aria-expanded') !== 'true';
                    row.setAttribute('aria-expanded', open ? 'true' : 'false');
                    row.classList.toggle('is-open', open);
                    document.querySelectorAll('[data-group-child="' + groupId + '"]').forEach(function (child) {
                        child.classList.toggle('d-none', !open);
                    });
                }

                row.addEventListener('click', function (event) {
                    if (!event.target.closest('a, button')) toggleGroup();
                });
                row.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        toggleGroup();
                    }
                });
            });
        });
    </script>
@endpush

@section('content')
    @include('financial-reports.partials.multi-filters', [
        'action' => route('dashboard.financiera.ventas-servicios'),
        'showLimit' => true,
        'filterTitle' => 'Construya su resumen',
        'filterHelp' => 'Seleccione los servicios y meses que desea comparar; los totales se consolidan automáticamente.',
    ])

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

    <div class="row">
        @foreach([
            ['Servicios', $summary['cantidadServicios'] ?? 0, 'fa-layer-group', 'primary'],
            ['Ventas', $summary['cantidadVentas'] ?? 0, 'fa-file-invoice', 'info'],
            ['Cantidad total', $summary['totalCantidad'] ?? 0, 'fa-boxes', 'warning'],
            ['Monto total', 'Bs ' . number_format((float) ($summary['totalMonto'] ?? 0), 2), 'fa-money-bill-wave', 'success'],
        ] as [$label, $value, $icon, $color])
            <div class="col-sm-6 col-xl-3 mb-3">
                <div class="info-box bg-white border mb-0">
                    <span class="info-box-icon bg-{{ $color }}"><i class="fas {{ $icon }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ is_numeric($value) ? number_format((float) $value) : $value }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong><i class="fas fa-layer-group mr-1"></i> Servicios agrupados</strong>
                <div class="text-muted small">Haga clic en un grupo para mostrar u ocultar sus subservicios.</div>
            </div>
            <div class="text-right">
                <span class="badge badge-primary">{{ number_format($serviceGroups->count()) }} grupos</span>
                <span class="badge badge-light border">{{ number_format($services->count()) }} subservicios</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Servicio agrupado</th>
                            <th>Meses incluidos</th>
                            <th>Composición</th>
                            <th class="text-right">Ventas</th>
                            <th class="text-right">Detalles</th>
                            <th class="text-right">Cantidad</th>
                            <th class="text-right">Monto</th>
                            <th>Última fecha</th>
                            <th class="text-center">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serviceGroups as $group)
                            @php($groupId = 'service-group-' . $loop->iteration)
                            <tr class="service-group-row" data-group-toggle="{{ $groupId }}" tabindex="0" role="button" aria-expanded="false">
                                <td>
                                    <span class="group-chevron"><i class="fas fa-chevron-right"></i></span>
                                    {{ $loop->iteration }}
                                </td>
                                <td class="font-weight-bold service-cell">
                                    <span class="group-icon"><i class="fas fa-box-open"></i></span>
                                    {{ $group['servicio'] ?? '-' }}
                                </td>
                                <td>
                                    @foreach($group['_meses'] ?? [] as $includedMonth)
                                        <span class="badge badge-light border">{{ [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'][$includedMonth] ?? $includedMonth }}</span>
                                    @endforeach
                                </td>
                                <td><span class="badge badge-info">{{ count($group['_children'] ?? []) }} subservicios</span></td>
                                <td class="text-right font-weight-bold">{{ number_format((int) ($group['cantidadVentas'] ?? 0)) }}</td>
                                <td class="text-right">{{ number_format((int) ($group['cantidadDetalles'] ?? 0)) }}</td>
                                <td class="text-right">{{ number_format((float) ($group['totalCantidad'] ?? 0), 2) }}</td>
                                <td class="text-right font-weight-bold text-success">Bs {{ number_format((float) ($group['totalMonto'] ?? 0), 2) }}</td>
                                <td class="text-nowrap">{{ $group['ultimaFecha'] ?? '-' }}</td>
                                <td class="text-center">
                                    <a class="btn btn-sm btn-primary" href="{{ route('dashboard.financiera.ventas-servicios.detalle', ['servicios' => collect($group['_children'] ?? [])->pluck('servicio')->all(), 'meses' => $selectedMonths, 'anio' => $anio]) }}" onclick="event.stopPropagation()">
                                        <i class="fas fa-list mr-1"></i> Ver todo
                                    </a>
                                </td>
                            </tr>
                            @foreach($group['_children'] ?? [] as $child)
                                <tr class="service-child-row d-none" data-group-child="{{ $groupId }}">
                                    <td></td>
                                    <td class="service-cell child-service-name">
                                        <span class="child-connector"></span>
                                        {{ $child['servicio'] ?? '-' }}
                                    </td>
                                    <td>
                                        @foreach($child['_meses'] ?? [] as $includedMonth)
                                            <span class="badge badge-light border">{{ [1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr', 5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic'][$includedMonth] ?? $includedMonth }}</span>
                                        @endforeach
                                    </td>
                                    <td class="description-cell text-muted">{{ $child['descripcionMuestra'] ?? '-' }}</td>
                                    <td class="text-right">{{ number_format((int) ($child['cantidadVentas'] ?? 0)) }}</td>
                                    <td class="text-right">{{ number_format((int) ($child['cantidadDetalles'] ?? 0)) }}</td>
                                    <td class="text-right">{{ number_format((float) ($child['totalCantidad'] ?? 0), 2) }}</td>
                                    <td class="text-right font-weight-bold">Bs {{ number_format((float) ($child['totalMonto'] ?? 0), 2) }}</td>
                                    <td class="text-nowrap">{{ $child['ultimaFecha'] ?? '-' }}</td>
                                    <td class="text-center">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('dashboard.financiera.ventas-servicios.detalle', ['servicios' => [$child['servicio'] ?? ''], 'meses' => $selectedMonths, 'anio' => $anio]) }}">
                                            <i class="fas fa-search mr-1"></i> Detalle
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No se encontraron servicios para la selección realizada.</td></tr>
                        @endforelse
                    </tbody>
                    @if($serviceGroups->isNotEmpty())
                        <tfoot class="font-weight-bold">
                            <tr>
                                <td colspan="4">Totales consolidados</td>
                                <td class="text-right">{{ number_format((int) ($summary['cantidadVentas'] ?? 0)) }}</td>
                                <td class="text-right">{{ number_format((int) ($summary['cantidadDetalles'] ?? 0)) }}</td>
                                <td class="text-right">{{ number_format((float) ($summary['totalCantidad'] ?? 0), 2) }}</td>
                                <td class="text-right">Bs {{ number_format((float) ($summary['totalMonto'] ?? 0), 2) }}</td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@stop

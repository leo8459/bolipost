@extends('adminlte::page')

@section('title', 'Detalle de ventas por servicio')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Detalle de ventas por servicio</h1>
            <small class="text-muted">Combine uno o varios servicios y meses en una sola tabla.</small>
        </div>
        <a href="{{ route('dashboard.financiera.ventas-servicios', ['mes' => $mes, 'anio' => $anio]) }}" class="btn btn-outline-secondary btn-sm mt-2 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i> Volver al resumen
        </a>
    </div>
@stop

@section('content')
    @include('financial-reports.partials.multi-filters', [
        'action' => route('dashboard.financiera.ventas-servicios.detalle'),
        'showLimit' => false,
        'filterTitle' => 'Arme el detalle que necesita',
        'filterHelp' => 'Busque servicios, marque uno o varios meses y genere una sola tabla consolidada.',
    ])

    @if($errors->isNotEmpty())
        <div class="alert alert-warning">
            <div class="font-weight-bold mb-1"><i class="fas fa-exclamation-triangle mr-1"></i> Algunas consultas no pudieron cargarse</div>
            <ul class="mb-0 pl-3">
                @foreach($errors as $reportError)
                    <li>{{ $reportError }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        @foreach([
            ['Ventas', $service['cantidadVentas'] ?? 0, 'fa-file-invoice', 'primary'],
            ['Detalles', $service['cantidadDetalles'] ?? 0, 'fa-list', 'info'],
            ['Cantidad total', $service['totalCantidad'] ?? 0, 'fa-boxes', 'warning'],
            ['Monto total', 'Bs ' . number_format((float) ($service['totalMonto'] ?? 0), 2), 'fa-money-bill-wave', 'success'],
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
            <strong><i class="fas fa-table mr-1"></i> Resultado combinado</strong>
            <span class="badge badge-light">{{ number_format($rows->total()) }} registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped table-hover mb-0 financial-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>Servicio</th>
                            <th>Mes</th>
                            <th>Venta</th>
                            <th>Detalle</th>
                            <th>Descripción</th>
                            <th>Código de orden</th>
                            <th>Código de seguimiento</th>
                            <th>Fecha</th>
                            <th class="text-right">Total línea</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $rows->firstItem() + $loop->index }}</td>
                                <td class="font-weight-bold service-cell">{{ $row['_servicio'] ?? '-' }}</td>
                                <td>{{ [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'][$row['_mes'] ?? 0] ?? '-' }}</td>
                                <td>{{ $row['ventaId'] ?? '-' }}</td>
                                <td>{{ $row['detalleId'] ?? '-' }}</td>
                                <td class="description-cell">{{ $row['descripcion'] ?? '-' }}</td>
                                <td class="code-cell">{{ $row['codigoOrden'] ?? '-' }}</td>
                                <td class="font-weight-bold code-cell">{{ $row['codigoSeguimiento'] ?? '-' }}</td>
                                <td class="text-nowrap">{{ $row['fecha'] ?? '-' }}</td>
                                <td class="text-right font-weight-bold">Bs {{ number_format((float) ($row['totalLinea'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No se encontraron movimientos para los servicios y meses seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($rows->hasPages())
            <div class="card-footer bg-white">
                {{ $rows->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@stop

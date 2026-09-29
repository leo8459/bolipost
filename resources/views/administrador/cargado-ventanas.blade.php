@extends('adminlte::page')

@section('title', 'Cargado de ventanas')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Cargado de ventanas</h1>
            <p class="text-muted mb-0">Tiempo promedio por pantalla, combinado entre todas las cuentas que la visitan.</p>
        </div>
        <span class="badge badge-light border p-2">Últimos 30 días · Desde {{ $periodStart->format('d/m/Y') }}</span>
    </div>
@stop

@section('content')
    <div class="card card-outline card-primary">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <h3 class="card-title"><i class="fas fa-stopwatch mr-2"></i>Promedios por ventana</h3>
            <span class="text-muted small">Incluye personal interno y clientes; no se filtra por usuario.</span>
        </div>
        <div class="card-body p-0">
            @if($metrics->isEmpty())
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <p class="mb-1">Aún no hay mediciones en este periodo.</p>
                    <small>Las ventanas aparecerán aquí después de ser visitadas.</small>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Ventana</th>
                                <th scope="col">Ruta</th>
                                <th scope="col" class="text-right">Carga promedio</th>
                                <th scope="col" class="text-right">Respuesta servidor</th>
                                <th scope="col" class="text-right">Mediciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metrics as $metric)
                                <tr>
                                    <th scope="row">{{ $metric->window_name }}</th>
                                    <td><code>{{ $metric->route_name }}</code></td>
                                    <td class="text-right font-weight-bold">{{ number_format(((float) $metric->average_load_time_ms) / 1000, 2) }} s</td>
                                    <td class="text-right">
                                        @if($metric->average_server_time_ms !== null)
                                            {{ number_format(((float) $metric->average_server_time_ms) / 1000, 2) }} s
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ number_format((int) $metric->sample_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        <div class="card-footer small text-muted">
            Cada apertura de pantalla aporta una medición. La carga promedio incluye respuesta, recursos y renderizado percibidos por el navegador; la respuesta del servidor mide el tiempo de la aplicación.
        </div>
    </div>
@stop

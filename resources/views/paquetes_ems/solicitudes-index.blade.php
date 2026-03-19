@extends('adminlte::page')

@section('title', 'Solicitudes EMS')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Solicitudes EMS</h1>
            <small class="text-muted">Consulta todas las solicitudes registradas desde cliente y desde Admisiones.</small>
        </div>
        <div class="d-flex flex-column flex-md-row">
            <a href="{{ route('paquetes-ems.solicitudes.create') }}" class="btn btn-warning mt-3 mt-md-0 mr-md-2">
                Nuevo
            </a>
            <a href="{{ route('paquetes-ems.index') }}" class="btn btn-outline-primary mt-3 mt-md-0">
                Volver a admisiones
            </a>
        </div>
    </div>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">Listado de solicitudes</h3>
        </div>
        <div class="card-body p-0">
            @if ($solicitudes->isEmpty())
                <div class="p-3 text-muted">Todavia no hay solicitudes registradas.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Canal</th>
                                <th>Servicio</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Peso</th>
                                <th>Precio</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($solicitudes as $solicitud)
                                @php($estadoNombre = (string) optional($solicitud->estadoRegistro)->nombre_estado)
                                <tr>
                                    <td><strong>{{ $solicitud->codigo_solicitud ?: 'SIN CODIGO' }}</strong></td>
                                    <td>{{ $solicitud->cliente_id ? 'Cliente' : 'Admisiones' }}</td>
                                    <td>{{ $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? '-') }}</td>
                                    <td>{{ $solicitud->origen ?: '-' }}</td>
                                    <td>{{ $solicitud->destino?->nombre_destino ?: ($solicitud->ciudad ?: '-') }}</td>
                                    <td>{{ $solicitud->nombre_remitente ?: '-' }}</td>
                                    <td>{{ $solicitud->nombre_destinatario ?: '-' }}</td>
                                    <td>{{ $solicitud->peso !== null ? number_format((float) $solicitud->peso, 3, '.', '') : '-' }}</td>
                                    <td>{{ $solicitud->precio !== null ? number_format((float) $solicitud->precio, 2, '.', '') : '-' }}</td>
                                    <td>
                                        <span class="badge badge-{{ strtoupper($estadoNombre) === 'SOLICITUD' ? 'warning' : 'success' }}">
                                            {{ $estadoNombre !== '' ? $estadoNombre : '-' }}
                                        </span>
                                    </td>
                                    <td>{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('paquetes-ems.solicitudes.ticket', $solicitud) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                            Ticket
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
        @if ($solicitudes->hasPages())
            <div class="card-footer clearfix">
                {{ $solicitudes->links() }}
            </div>
        @endif
    </div>
@endsection

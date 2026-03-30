@extends('layouts.cliente-adminlte')

@section('title', 'Mis Solicitudes')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Mis solicitudes</h1>
            <small class="text-muted">Revisa todas las solicitudes que registraste con tu cuenta cliente.</small>
        </div>
        <div class="d-flex flex-column flex-md-row">
            <a href="{{ route('clientes.solicitudes.create') }}" class="btn btn-outline-primary mt-3 mt-md-0 mr-md-2">
                Nueva solicitud
            </a>
            <a href="{{ route('clientes.dashboard') }}" class="btn btn-outline-warning mt-3 mt-md-0">
                Volver al panel
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

    <div class="card card-outline card-warning">
        <div class="card-header">
            <h3 class="card-title">Historial de solicitudes</h3>
        </div>
        <div class="card-body p-0">
            @if ($solicitudes->isEmpty())
                <div class="p-3 text-muted">Todavia no registraste solicitudes.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Codigo</th>
                                <th>Servicio</th>
                                <th>Tarifario</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($solicitudes as $solicitud)
                                <tr>
                                    <td><strong>{{ $solicitud->codigo_solicitud }}</strong></td>
                                    <td>{{ $solicitud->servicioExtra?->descripcion ?: ($solicitud->servicioExtra?->nombre ?? '-') }}</td>
                                    <td>
                                        @if($solicitud->tarifarioTiktoker)
                                            <span class="d-block">#{{ $solicitud->tarifarioTiktoker->id }}</span>
                                            <small class="text-muted">
                                                {{ $solicitud->tarifarioTiktoker->origen?->nombre_origen }}
                                                /
                                                {{ $solicitud->tarifarioTiktoker->destino?->nombre_destino }}
                                                @if($solicitud->tarifarioTiktoker->servicioExtra)
                                                    / {{ $solicitud->tarifarioTiktoker->servicioExtra->nombre }}
                                                @endif
                                            </small>
                                        @else
                                            <span class="text-muted">Sin tarifa</span>
                                        @endif
                                    </td>
                                    <td>{{ $solicitud->origen }}</td>
                                    <td>{{ $solicitud->destino?->nombre_destino }}</td>
                                    <td>
                                        @php($estadoNombre = (string) optional($solicitud->estadoRegistro)->nombre_estado)
                                        <span class="badge badge-{{ strtoupper($estadoNombre) === 'SOLICITUD' ? 'warning' : 'success' }}">
                                            {{ $estadoNombre !== '' ? $estadoNombre : '-' }}
                                        </span>
                                    </td>
                                    <td>{{ optional($solicitud->created_at)->format('d/m/Y H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection

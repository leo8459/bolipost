@extends('adminlte::page')
@section('title', 'Preregistros')

@section('content')
    <div class="container-fluid">
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h3 class="card-title mb-1 font-weight-bold">Preregistros desde casa</h3>
                    <div class="text-muted small">Solicitudes publicas pendientes para validar y convertir en EMS.</div>
                </div>
                <div class="d-flex">
                    <a href="{{ route('preregistros.public.create') }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mr-2">Abrir formulario publico</a>
                    <a href="{{ route('paquetes-ems.almacen-admisiones') }}" class="btn btn-outline-secondary btn-sm">Volver a admisiones</a>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('preregistros.index') }}" class="row mb-3">
                    <div class="col-md-6 mb-2">
                        <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar por nombre, carnet, telefono o codigo generado...">
                    </div>
                    <div class="col-md-3 mb-2">
                        <select name="estado" class="form-control">
                            @foreach($estadosDisponibles as $estadoItem)
                                <option value="{{ $estadoItem }}" {{ strtoupper($estado) === strtoupper($estadoItem) ? 'selected' : '' }}>{{ $estadoItem }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 mb-2">
                        <button type="submit" class="btn btn-primary btn-block">Filtrar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Codigo preregistro</th>
                                <th>Estado</th>
                                <th>Origen</th>
                                <th>Servicio</th>
                                <th>Destino</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Peso</th>
                                <th>Precio</th>
                                <th>Creado</th>
                                <th>Codigo EMS</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($preregistros as $preregistro)
                                <tr>
                                    <td>#{{ $preregistro->id }}</td>
                                    <td><span class="font-weight-bold">{{ $preregistro->codigo_preregistro ?: '-' }}</span></td>
                                    <td><span class="badge badge-{{ strtoupper($preregistro->estado) === 'VALIDADO' ? 'success' : 'warning' }}">{{ $preregistro->estado }}</span></td>
                                    <td>{{ $preregistro->origen }}</td>
                                    <td>{{ optional($preregistro->servicio)->nombre_servicio ?: '-' }}</td>
                                    <td>{{ optional($preregistro->destino)->nombre_destino ?: $preregistro->ciudad }}</td>
                                    <td>{{ $preregistro->nombre_remitente }}</td>
                                    <td>{{ $preregistro->nombre_destinatario }}</td>
                                    <td>{{ number_format((float) $preregistro->peso, 3) }}</td>
                                    <td>{{ $preregistro->precio !== null ? number_format((float) $preregistro->precio, 2) : '-' }}</td>
                                    <td>{{ optional($preregistro->created_at)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $preregistro->codigo_generado ?: '-' }}</td>
                                    <td class="text-nowrap">
                                        @if (strtoupper($preregistro->estado) === 'PENDIENTE')
                                            <form method="POST" action="{{ route('preregistros.approve', $preregistro) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Validar este preregistro y crear el EMS?')">
                                                    Validar
                                                </button>
                                            </form>
                                        @elseif($preregistro->paqueteEms)
                                            <a href="{{ route('paquetes-ems.boleta', $preregistro->paqueteEms) }}" class="btn btn-sm btn-outline-success">Boleta</a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="13" class="text-center py-4 text-muted">No hay preregistros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $preregistros->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

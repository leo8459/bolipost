@extends('adminlte::page')
@section('title', 'Almacen admisiones EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
    <div class="container-fluid">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h3 class="card-title mb-1 font-weight-bold">Almacen admisiones</h3>
                    <div class="text-muted small">Paquetes creados en admisiones y enviados a ALMACEN EMS con su usuario.</div>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('preregistros.index') }}" class="btn btn-outline-primary btn-sm mr-2">Preregistros desde casa</a>
                    <a href="{{ route('paquetes-ems.index') }}" class="btn btn-outline-secondary btn-sm">Volver a admisiones</a>
                </div>
            </div>

            <div class="card-body">
                @if (!$estadoAlmacenDisponible)
                    <div class="alert alert-warning mb-3">
                        No existe el estado ALMACEN en la tabla estados.
                    </div>
                @endif

                <form method="GET" action="{{ route('paquetes-ems.almacen-admisiones') }}" class="row mb-3">
                    <div class="col-md-10 mb-2 mb-md-0">
                        <input
                            type="text"
                            name="q"
                            value="{{ $search }}"
                            class="form-control"
                            placeholder="Buscar por codigo, remitente, destinatario, destino o usuario..."
                        >
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-block">Buscar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>Codigo</th>
                                <th>Remitente</th>
                                <th>Destinatario</th>
                                <th>Destino</th>
                                <th class="text-right">Peso</th>
                                <th>Usuario</th>
                                <th>Fecha creado</th>
                                <th>Fecha enviado almacen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paquetes as $item)
                                <tr>
                                    <td>{{ ($paquetes->currentPage() - 1) * $paquetes->perPage() + $loop->iteration }}</td>
                                    <td class="font-weight-bold">{{ $item->codigo ?: '-' }}</td>
                                    <td>{{ $item->remitente ?: '-' }}</td>
                                    <td>{{ $item->destinatario ?: '-' }}</td>
                                    <td>{{ $item->destino ?: '-' }}</td>
                                    <td class="text-right">{{ number_format((float) $item->peso, 3) }}</td>
                                    <td>{{ $item->usuario ?: 'Sin usuario' }}</td>
                                    <td>{{ optional($item->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                    <td>{{ optional($item->updated_at)->format('d/m/Y H:i') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No hay paquetes enviados a ALMACEN EMS.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 d-flex justify-content-end">
                    {{ $paquetes->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection

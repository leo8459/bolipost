@extends('adminlte::page')

@section('title', 'Envios Oficiales')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Envios Oficiales</h1>
            <small class="text-muted">Listado de envios EMS registrados como OFICIAL.</small>
        </div>
        <div class="mt-2 mt-md-0">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left mr-1"></i> Dashboard
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-primary"><i class="fas fa-stamp"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Envios oficiales</span>
                    <span class="info-box-number">{{ number_format((int) $totalOficiales) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-warning"><i class="fas fa-weight-hanging"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Peso total</span>
                    <span class="info-box-number">{{ number_format((float) $pesoTotal, 3) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="info-box bg-white border">
                <span class="info-box-icon bg-success"><i class="fas fa-money-bill-wave"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Importe total</span>
                    <span class="info-box-number">Bs {{ number_format((float) $precioTotal, 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <strong><i class="fas fa-filter mr-1"></i> Filtros</strong>
        </div>
        <form method="GET" action="{{ route('dashboard.envios-oficiales') }}">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label>Buscar</label>
                        <input type="text" name="q" value="{{ $search }}" class="form-control"
                            placeholder="Codigo, origen, destino, remitente, destinatario...">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label>Origen</label>
                        <select name="origen" class="form-control">
                            <option value="">Todos</option>
                            @foreach($origenOptions as $origenOption)
                                <option value="{{ $origenOption }}" {{ $origen === $origenOption ? 'selected' : '' }}>
                                    {{ $origenOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label>Destino</label>
                        <select name="destino" class="form-control">
                            <option value="">Todos</option>
                            @foreach($destinoOptions as $destinoOption)
                                <option value="{{ $destinoOption }}" {{ $destino === $destinoOption ? 'selected' : '' }}>
                                    {{ $destinoOption }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label>Fecha desde</label>
                        <input type="date" name="from" value="{{ $from }}" class="form-control">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label>Fecha hasta</label>
                        <input type="date" name="to" value="{{ $to }}" class="form-control">
                    </div>
                    <div class="col-md-12 mb-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary flex-fill mr-2">
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                        <a href="{{ route('dashboard.envios-oficiales') }}" class="btn btn-outline-secondary" title="Limpiar">
                            <i class="fas fa-undo"></i>
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Detalle de envios oficiales</strong>
            <span class="text-muted small">Solo registros OFICIAL</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Codigo</th>
                            <th>Cod. especial</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Remitente</th>
                            <th>Destinatario</th>
                            <th>Direccion</th>
                            <th>Usuario</th>
                            <th class="text-right">Peso</th>
                            <th class="text-right">Bs</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($envios as $envio)
                            <tr>
                                <td class="font-weight-bold">{{ $envio->codigo ?: '-' }}</td>
                                <td>{{ $envio->cod_especial ?: '-' }}</td>
                                <td>{{ $envio->estado ?: '-' }}</td>
                                <td>{{ $envio->origen ?: '-' }}</td>
                                <td>{{ $envio->destino ?: '-' }}</td>
                                <td>{{ $envio->remitente ?: '-' }}</td>
                                <td>{{ $envio->destinatario ?: '-' }}</td>
                                <td>{{ $envio->direccion ?: '-' }}</td>
                                <td>{{ $envio->usuario ?: '-' }}</td>
                                <td class="text-right">{{ number_format((float) $envio->peso, 3) }}</td>
                                <td class="text-right">Bs {{ number_format((float) $envio->precio, 2) }}</td>
                                <td>{{ optional($envio->created_at ? \Carbon\Carbon::parse($envio->created_at) : null)->format('d/m/Y H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">No hay envios oficiales para los filtros actuales.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end">
            {{ $envios->links() }}
        </div>
    </div>

    @include('footer')
@stop

@extends('adminlte::page')

@section('title', 'Movimiento de toda la vida')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-1">Movimiento de toda la vida</h1>
            <p class="text-muted mb-0">Historial completo de eventos de Contratos y EMS.</p>
        </div>
        <span class="badge badge-primary p-2 mt-2 mt-md-0">{{ number_format($movements->total(), 0, ',', '.') }} movimientos encontrados</span>
    </div>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card card-primary card-outline">
        <div class="card-header"><h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filtros</h3></div>
        <div class="card-body">
            <form method="GET" action="{{ route('reportes.lifetime-movements') }}">
                <div class="row align-items-end">
                    <div class="col-lg-4 mb-3">
                        <label for="q">Código, evento o usuario</label>
                        <input type="search" id="q" name="q" value="{{ $search }}" class="form-control" maxlength="150" placeholder="Buscar en todo el historial">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label for="service">Servicio</label>
                        <select id="service" name="service" class="form-control">
                            <option value="all" @selected($service === 'all')>Todos</option>
                            <option value="contrato" @selected($service === 'contrato')>Contratos</option>
                            <option value="ems" @selected($service === 'ems')>EMS</option>
                        </select>
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label for="from">Desde</label>
                        <input type="date" id="from" name="from" value="{{ $from }}" class="form-control">
                    </div>
                    <div class="col-lg-2 mb-3">
                        <label for="to">Hasta</label>
                        <input type="date" id="to" name="to" value="{{ $to }}" class="form-control">
                    </div>
                    <div class="col-lg-2 mb-3 d-flex">
                        <button class="btn btn-primary flex-fill mr-1"><i class="fas fa-search mr-1"></i>Aplicar</button>
                        <a href="{{ route('reportes.lifetime-movements') }}" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fas fa-eraser"></i></a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row">
        @foreach (['Contratos', 'EMS'] as $module)
            @php($moduleSummary = $summary->get($module))
            <div class="col-md-6">
                <div class="small-box {{ $module === 'Contratos' ? 'bg-info' : 'bg-success' }}">
                    <div class="inner">
                        <h3>{{ number_format((int) ($moduleSummary->movements ?? 0), 0, ',', '.') }}</h3>
                        <p>{{ $module }} · {{ number_format((int) ($moduleSummary->packages ?? 0), 0, ',', '.') }} envíos distintos</p>
                    </div>
                    <div class="icon"><i class="fas {{ $module === 'Contratos' ? 'fa-file-contract' : 'fa-box' }}"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Historial de movimientos</h3></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr><th>Fecha y hora</th><th>Servicio</th><th>Código</th><th>Evento</th><th>Origen</th><th>Destino</th><th>Estado actual</th><th>Usuario</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $movement)
                            <tr>
                                <td class="text-nowrap">{{ \Carbon\CarbonImmutable::parse($movement->moved_at, config('app.timezone'))->setTimezone('America/La_Paz')->format('d/m/Y H:i:s') }}</td>
                                <td><span class="badge {{ $movement->service === 'Contratos' ? 'badge-info' : 'badge-success' }}">{{ $movement->service }}</span></td>
                                <td>{{ $movement->code }}</td>
                                <td>{{ $movement->event_name ?? 'Evento '.$movement->event_id }}</td>
                                <td>{{ $movement->origin ?: '—' }}</td>
                                <td>{{ $movement->destination ?: '—' }}</td>
                                <td>{{ $movement->current_state ?: 'Sin estado' }}</td>
                                <td>{{ $movement->user_name ?: 'Usuario no disponible' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">No hay movimientos que coincidan con los filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($movements->hasPages())
            <div class="card-footer">{{ $movements->links() }}</div>
        @endif
    </div>
@stop

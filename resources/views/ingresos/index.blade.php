@extends('adminlte::page')

@section('title', 'Ingresos')

@section('content_header')
    <h1>Ingresos de usuarios</h1>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <div class="small-box bg-info">
                <div class="inner">
                    <h3>{{ number_format($totalLogs) }}</h3>
                    <p>Ingresos registrados</p>
                </div>
                <div class="icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ number_format($uniqueUsers) }}</h3>
                    <p>Usuarios que ingresaron</p>
                </div>
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Filtros</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('ingresos.index') }}" class="row">
                <div class="col-md-5">
                    <div class="form-group">
                        <label for="search">Usuario, alias, correo o IP</label>
                        <input
                            type="text"
                            name="search"
                            id="search"
                            class="form-control"
                            value="{{ $search }}"
                            placeholder="Buscar ingreso"
                        >
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="from">Desde</label>
                        <input type="date" name="from" id="from" class="form-control" value="{{ $from }}">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label for="to">Hasta</label>
                        <input type="date" name="to" id="to" class="form-control" value="{{ $to }}">
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-group w-100">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i> Buscar
                        </button>
                        <a href="{{ route('ingresos.index') }}" class="btn btn-outline-secondary">
                            <i class="fas fa-times mr-1"></i> Limpiar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Historial de ingresos</h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Fecha y hora</th>
                            <th>Usuario</th>
                            <th>Alias</th>
                            <th>IP</th>
                            <th>Navegador / equipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td>{{ optional($log->logged_in_at)->format('d/m/Y H:i:s') }}</td>
                                <td>{{ $log->user?->name ?? $log->user_name ?? 'Usuario eliminado' }}</td>
                                <td>{{ $log->user?->alias ?? $log->user_alias ?? '-' }}</td>
                                <td><span class="badge badge-light">{{ $log->ip_address ?? '-' }}</span></td>
                                <td class="text-break">{{ $log->user_agent ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4">No hay ingresos registrados con esos filtros.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($logs->hasPages())
            <div class="card-footer">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
@endsection

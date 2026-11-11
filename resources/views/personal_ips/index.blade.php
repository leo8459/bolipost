@extends('adminlte::page')

@section('title', 'Personal IPS')

@section('content_header')
    <h1>Personal IPS</h1>
@endsection

@section('content')
    @if($error)
        <div class="alert alert-warning" role="alert">{{ $error }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h2 class="card-title">Usuarios registrados en IPS</h2>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('personal-ips.index') }}" class="row align-items-end">
                <div class="col-md-7 form-group">
                    <label for="q">Buscar personal</label>
                    <input type="search" id="q" name="q" value="{{ $search }}" maxlength="100" class="form-control" placeholder="Nombre, usuario o dominio">
                </div>
                <div class="col-md-2 form-group">
                    <label for="per_page">Por página</label>
                    <select id="per_page" name="per_page" class="form-control">
                        @foreach([25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected($perPage === $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 form-group">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                    <a href="{{ route('personal-ips.index') }}" class="btn btn-default">Limpiar</a>
                </div>
            </form>
            <p class="text-muted small mb-0">El estado corresponde a la vigencia de la cuenta en IPS. La consulta actual de SITRA solo incluye usuarios activos; los inactivos no se incluyen en los resultados.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th scope="col">PID IPS</th>
                        <th scope="col">Nombre</th>
                        <th scope="col">Usuario</th>
                        <th scope="col">Correo</th>
                        <th scope="col">Oficinas asignadas</th>
                        <th scope="col">Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user['user_pid'] ?? '—' }}</td>
                            <td>{{ $user['user_name'] ?? '—' }}</td>
                            <td>
                                <strong>{{ $user['user_fid'] ?? '—' }}</strong>
                                <div class="text-muted small">{{ $user['user_domain'] ?? '' }}</div>
                            </td>
                            <td>{{ ($user['email'] ?? '') ?: '—' }}</td>
                            <td>
                                @forelse($user['assigned_offices'] as $office)
                                    <div>{{ $office['office_name'] ?: 'Oficina '.$office['office_cd'] }}
                                        @if($office['office_fcd'] !== '')
                                            <span class="text-muted small">({{ $office['office_fcd'] }})</span>
                                        @endif
                                    </div>
                                @empty
                                    <span class="text-muted">Sin oficina asignada</span>
                                @endforelse
                            </td>
                            <td>
                                @if($user['active_status'] === true)
                                    <span class="badge badge-success">Activo</span>
                                @elseif($user['active_status'] === false)
                                    <span class="badge badge-danger">Inactivo</span>
                                @else
                                    <span class="badge badge-secondary">Sin estado informado</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                {{ $error ? 'No se pudo cargar el listado.' : 'No se encontraron usuarios IPS.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @unless($error)
            <div class="card-footer d-flex justify-content-between align-items-center flex-wrap">
                <span class="text-muted small">Página {{ $users->currentPage() }} · {{ $users->count() }} usuarios en esta página</span>
                {{ $users->links('pagination::simple-bootstrap-4') }}
            </div>
        @endunless
    </div>
@endsection

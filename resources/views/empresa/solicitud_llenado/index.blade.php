@extends('adminlte::page')

@section('title', 'Mandar solicitud de llenado a empresas')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="mb-1">Mandar solicitud de llenado a empresas</h1>
            <small class="text-muted">Solicita a los usuarios que registren si su entidad está en un municipio o provincia.</small>
        </div>
    </div>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
        </div>
    @endif

    <div class="row mb-3">
        <div class="col-sm-4 col-lg-3 mb-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted text-uppercase small font-weight-bold">Usuarios de empresa</div>
                <div class="h3 mb-0 text-primary">{{ number_format($totalUsers, 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-sm-4 col-lg-3 mb-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted text-uppercase small font-weight-bold">Solicitudes pendientes</div>
                <div class="h3 mb-0 text-warning">{{ number_format($pendingUsers, 0, ',', '.') }}</div>
            </div></div>
        </div>
        <div class="col-sm-4 col-lg-3 mb-2">
            <div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted text-uppercase small font-weight-bold">Respuestas recibidas</div>
                <div class="h3 mb-0 text-success">{{ number_format($answeredUsers, 0, ',', '.') }}</div>
            </div></div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <form method="GET" action="{{ route('empresa.solicitud-llenado.index') }}" class="form-inline">
                <label for="buscar" class="sr-only">Buscar empresa o usuario</label>
                <input id="buscar" type="search" name="buscar" value="{{ $search }}" class="form-control mr-2 mb-2 mb-sm-0"
                    placeholder="Empresa, nombre, alias o correo" style="min-width: 280px">
                <button class="btn btn-outline-primary mr-2" type="submit"><i class="fas fa-search mr-1"></i>Buscar</button>
                @if ($search !== '')
                    <a class="btn btn-outline-secondary" href="{{ route('empresa.solicitud-llenado.index') }}">Limpiar</a>
                @endif
            </form>
        </div>

        <form method="POST" action="{{ route('empresa.solicitud-llenado.send', request()->only('buscar')) }}" id="send-location-request-form">
            @csrf
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 46px"><input type="checkbox" id="select-visible-users" aria-label="Seleccionar usuarios visibles"></th>
                            <th>Usuario</th>
                            <th>Empresa</th>
                            <th>Municipio / provincia</th>
                            <th>Estado de solicitud</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            @php
                                $needsProvince = trim((string) $user->provincia_origen) === '';
                                $responded = (bool) $user->empresa_ubicacion_respondida_at;
                                $pending = (bool) $user->empresa_ubicacion_solicitada_at && ! $responded;
                            @endphp
                            <tr>
                                <td class="text-center align-middle">
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}" class="user-select"
                                        aria-label="Seleccionar {{ $user->name }}" @disabled(! $needsProvince)
                                        title="{{ $needsProvince ? '' : 'Ya tiene provincia origen registrada' }}">
                                </td>
                                <td class="align-middle">
                                    <strong>{{ $user->name }}</strong><br>
                                    <small class="text-muted">{{ $user->alias }} · {{ $user->email }}</small>
                                </td>
                                <td class="align-middle">
                                    @if ($user->empresa)
                                        <strong>{{ $user->empresa->nombre }}</strong><br>
                                        <small class="text-muted">{{ $user->empresa->codigo_cliente }} · {{ $user->empresa->sigla }}</small>
                                    @else
                                        <span class="text-muted">Empresa no disponible</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if (filled($user->provincia_origen))
                                        {{ $user->provincia_origen }}
                                    @elseif ($user->empresa_ubicacion_capital)
                                        <span class="text-muted">Ciudad capital</span>
                                    @else
                                        <span class="text-muted">Sin registrar</span>
                                    @endif
                                </td>
                                <td class="align-middle">
                                    @if (! $needsProvince)
                                        <span class="badge badge-info">Provincia registrada</span>
                                    @elseif ($responded)
                                        <span class="badge badge-success">Respondió</span>
                                        @if ($user->empresa_ubicacion_capital)
                                            <small class="d-block text-muted mt-1">Entidad en ciudad capital</small>
                                        @endif
                                    @elseif ($pending)
                                        <span class="badge badge-warning">Pendiente</span>
                                        <small class="d-block text-muted mt-1">Enviada {{ $user->empresa_ubicacion_solicitada_at->format('d/m/Y H:i') }}</small>
                                    @else
                                        <span class="badge badge-secondary">Sin solicitud</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No se encontraron usuarios de empresa.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex flex-wrap align-items-center justify-content-between">
                <div class="d-flex flex-wrap">
                    <button type="submit" class="btn btn-primary mr-2 mb-2 mb-sm-0" id="send-location-request-button" @disabled($users->isEmpty())>
                        <i class="fas fa-paper-plane mr-1"></i>Enviar solicitud a seleccionados
                    </button>
                    <button type="submit" name="todos" value="1" class="btn btn-outline-primary" @disabled($eligibleUsers === 0)>
                        <i class="fas fa-users mr-1"></i>Enviar a todos sin provincia origen
                    </button>
                </div>
                <div class="mt-2 mt-md-0">{{ $users->links() }}</div>
            </div>
        </form>
    </div>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const selectAll = document.getElementById('select-visible-users');
            const checkboxes = Array.from(document.querySelectorAll('.user-select:not(:disabled)'));
            const form = document.getElementById('send-location-request-form');

            selectAll?.addEventListener('change', function () {
                checkboxes.forEach((checkbox) => { checkbox.checked = selectAll.checked; });
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    if (selectAll) {
                        selectAll.checked = checkboxes.length > 0 && checkboxes.every((item) => item.checked);
                    }
                });
            });

            form?.addEventListener('submit', function (event) {
                const sendToAll = event.submitter?.name === 'todos';
                const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
                const eligibleUsers = {{ (int) $eligibleUsers }};

                if (sendToAll) {
                    if (!window.confirm(`Se enviará la solicitud a los ${eligibleUsers} usuarios de empresa que no tienen provincia origen. ¿Deseas continuar?`)) {
                        event.preventDefault();
                    }
                    return;
                }

                if (selected === 0) {
                    event.preventDefault();
                    window.alert('Selecciona al menos un usuario de empresa.');
                    return;
                }

                if (!window.confirm(`Se enviará la solicitud a ${selected} usuario(s). ¿Deseas continuar?`)) {
                    event.preventDefault();
                }
            });
        });
    </script>
@stop

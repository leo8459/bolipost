@extends('layouts.cliente-adminlte')

@section('title', 'Dashboard Cliente')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Panel de Cliente</h1>
            <small class="text-muted">Acceso publico con rol {{ $cliente->getRoleNames()->implode(', ') ?: $cliente->rol }}</small>
        </div>
        <form method="POST" action="{{ route('clientes.logout') }}" class="mt-3 mt-md-0">
            @csrf
            <button type="submit" class="btn btn-outline-primary">
                Cerrar sesion
            </button>
        </form>
    </div>
@endsection

@section('content')
    @php
        $canCreateSolicitud = auth('cliente')->user()?->can('feature.clientes.dashboard.create') ?? false;
        $canHistorySolicitud = auth('cliente')->user()?->can('feature.clientes.dashboard.history') ?? false;
    @endphp

    <div class="row">
        <div class="col-12">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <div class="card card-outline card-warning">
                <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <div>
                        <h5 class="mb-1">Modulo de Solicitudes</h5>
                        <p class="text-muted mb-0">Registra nuevas solicitudes con los campos del preregistro y consulta tu historial por separado.</p>
                    </div>
                    <div class="d-flex flex-column flex-md-row mt-3 mt-md-0">
                        @if ($canCreateSolicitud)
                            <a href="{{ route('clientes.solicitudes.create') }}" class="btn btn-warning mr-md-2 mb-2 mb-md-0">
                                <i class="fas fa-file-signature mr-1"></i> Nueva solicitud
                            </a>
                        @endif
                        @if ($canHistorySolicitud)
                            <a href="{{ route('clientes.solicitudes.history') }}" class="btn btn-outline-warning">
                                <i class="fas fa-folder-open mr-1"></i> Mis solicitudes
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12">
            <div class="card card-outline card-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-4">
                        <img
                            src="{{ $cliente->avatar ?: 'https://picsum.photos/120/120' }}"
                            alt="Avatar del cliente"
                            class="img-circle elevation-2 mr-3"
                            style="width: 72px; height: 72px; object-fit: cover;"
                        >
                        <div>
                            <h3 class="mb-1">{{ $cliente->name }}</h3>
                            <div class="text-muted">{{ $cliente->email }}</div>
                            <span class="badge badge-primary mt-2">{{ strtoupper((string) ($cliente->getRoleNames()->implode(', ') ?: $cliente->rol)) }}</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h4>Correo</h4>
                                    <p>{{ $cliente->email }}</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="small-box bg-primary">
                                <div class="inner">
                                    <h4>Proveedor</h4>
                                    <p>{{ strtoupper((string) $cliente->provider) }}</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h4>Codigo</h4>
                                    <p>{{ $cliente->codigo_cliente }}</p>
                                </div>
                                <div class="icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Tipo documento</dt>
                                <dd class="col-sm-7">{{ $cliente->tipodocumentoidentidad }} - {{ $cliente->tipoDocumentoIdentidadLabel() }}</dd>
                                <dt class="col-sm-5">Numero documento</dt>
                                <dd class="col-sm-7">{{ $cliente->numero_carnet }}</dd>
                                <dt class="col-sm-5">Complemento</dt>
                                <dd class="col-sm-7">{{ $cliente->complemento ?: '-' }}</dd>
                                <dt class="col-sm-5">Razon social</dt>
                                <dd class="col-sm-7">{{ $cliente->razon_social }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Telefono</dt>
                                <dd class="col-sm-8">{{ $cliente->telefono }}</dd>
                                <dt class="col-sm-4">Direccion</dt>
                                <dd class="col-sm-8">{{ $cliente->direccion }}</dd>
                            </dl>
                        </div>
                    </div>

                    <div class="alert alert-success mb-0">
                        Iniciaste sesion correctamente en AdminLTE usando la tabla <strong>clientes</strong>.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

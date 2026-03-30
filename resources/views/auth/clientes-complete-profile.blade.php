@extends('layouts.cliente-adminlte')

@section('title', 'Completar Datos')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Completar datos del cliente</h1>
            <small class="text-muted">Antes de usar el panel, necesitamos tus datos de contacto y facturacion.</small>
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
    <div class="row justify-content-center">
        <div class="col-lg-8">
            @if (session('warning'))
                <div class="alert alert-warning">
                    {{ session('warning') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 pl-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card card-outline card-primary">
                <div class="card-body">
                    <div class="mb-4">
                        <h4 class="mb-1">{{ $cliente->name }}</h4>
                        <div class="text-muted">{{ $cliente->email }}</div>
                        <div class="mt-2">
                            <span class="badge badge-info">Codigo: {{ $cliente->codigo_cliente }}</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('clientes.profile.complete.store') }}">
                        @csrf

                        <div class="form-group">
                            <label for="tipodocumentoidentidad">Tipo de documento</label>
                            <select id="tipodocumentoidentidad" name="tipodocumentoidentidad" class="form-control" required>
                                <option value="">Selecciona una opcion</option>
                                @foreach ($tiposDocumento as $codigo => $label)
                                    <option value="{{ $codigo }}" @selected(old('tipodocumentoidentidad', $cliente->tipodocumentoidentidad) === $codigo)>{{ $codigo }} : {{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="numero_carnet">Numero de documento</label>
                            <input
                                id="numero_carnet"
                                name="numero_carnet"
                                type="text"
                                class="form-control"
                                value="{{ old('numero_carnet', $cliente->numero_carnet) }}"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="complemento">Complemento</label>
                            <input
                                id="complemento"
                                name="complemento"
                                type="text"
                                class="form-control"
                                value="{{ old('complemento', $cliente->complemento) }}"
                            >
                            <small class="form-text text-muted">Opcional. Ejemplo: extension, letra o dato adicional de tu documento.</small>
                        </div>

                        <div class="form-group">
                            <label for="razon_social">Razon social</label>
                            <input
                                id="razon_social"
                                name="razon_social"
                                type="text"
                                class="form-control"
                                value="{{ old('razon_social', $cliente->razon_social) }}"
                                required
                            >
                            <small class="form-text text-muted">Si eres persona natural, escribe tu primer apellido o tu nombre fiscal. Si eres empresa, escribe el nombre de la empresa.</small>
                        </div>

                        <div class="form-group">
                            <label for="telefono">Telefono</label>
                            <input
                                id="telefono"
                                name="telefono"
                                type="text"
                                class="form-control"
                                value="{{ old('telefono', $cliente->telefono) }}"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label for="direccion">Direccion</label>
                            <input
                                id="direccion"
                                name="direccion"
                                type="text"
                                class="form-control"
                                value="{{ old('direccion', $cliente->direccion) }}"
                                required
                            >
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Guardar datos
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

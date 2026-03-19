@extends('layouts.cliente-adminlte')

@section('title', 'Solicitudes')

@section('content_header')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div>
            <h1 class="m-0 text-dark">Nueva solicitud</h1>
            <small class="text-muted">Registra una solicitud tipo preregistro desde tu panel de cliente.</small>
        </div>
        <div class="d-flex flex-column flex-md-row">
            <a href="{{ route('clientes.solicitudes.history') }}" class="btn btn-outline-warning mt-3 mt-md-0 mr-md-2">
                Ver mis solicitudes
            </a>
            <a href="{{ route('clientes.dashboard') }}" class="btn btn-outline-primary mt-3 mt-md-0">
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

    @if (session('warning'))
        <div class="alert alert-warning">
            {{ session('warning') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            Revisa los campos del formulario y vuelve a intentar.
        </div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">Formulario de solicitud</h3>
        </div>
        <form method="POST" action="{{ route('clientes.solicitudes.store') }}">
            @csrf
            <div class="card-body">
                <div class="border rounded p-3 mb-4">
                    <h5 class="mb-3">Datos del servicio</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Servicio</label>
                            <select name="servicio_extra_id" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($servicioExtras as $servicioExtra)
                                    <option value="{{ $servicioExtra->id }}" @selected((int) old('servicio_extra_id') === (int) $servicioExtra->id)>
                                        {{ $servicioExtra->descripcion ?: $servicioExtra->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Tipo de correspondencia</label>
                            <input type="text" name="tipo_correspondencia" value="{{ old('tipo_correspondencia') }}" class="form-control" placeholder="Documento, paquete, sobre...">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Origen</label>
                            <select name="origen" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($ciudades as $ciudad)
                                    <option value="{{ $ciudad }}" @selected(old('origen') === $ciudad)>{{ $ciudad }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Destino</label>
                            <select name="destino_id" class="form-control">
                                <option value="">Seleccione...</option>
                                @foreach($destinos as $destino)
                                    <option value="{{ $destino->id }}" @selected((int) old('destino_id') === (int) $destino->id)>{{ $destino->nombre_destino }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-md-0">
                            <label>Cantidad</label>
                            <input type="number" min="1" name="cantidad" value="{{ old('cantidad', 1) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label>Contenido</label>
                            <textarea name="contenido" rows="2" class="form-control">{{ old('contenido') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mb-4">
                    <h5 class="mb-3">Datos del remitente</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nombre remitente</label>
                            <input type="text" name="nombre_remitente" value="{{ old('nombre_remitente', $cliente->name) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Carnet</label>
                            <input type="text" name="carnet" value="{{ old('carnet') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Telefono remitente</label>
                            <input type="text" name="telefono_remitente" value="{{ old('telefono_remitente') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label>Direccion de recojo</label>
                            <input type="text" name="direccion_recojo" value="{{ old('direccion_recojo') }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3">
                    <h5 class="mb-3">Datos del destinatario</h5>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Nombre destinatario</label>
                            <input type="text" name="nombre_destinatario" value="{{ old('nombre_destinatario') }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Telefono destinatario</label>
                            <input type="text" name="telefono_destinatario" value="{{ old('telefono_destinatario') }}" class="form-control">
                        </div>
                        <div class="col-md-12 form-group mb-0">
                            <label>Direccion de entrega</label>
                            <input type="text" name="direccion_entrega" value="{{ old('direccion_entrega') }}" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-right">
                <button type="submit" class="btn btn-primary">
                    Guardar solicitud
                </button>
            </div>
        </form>
    </div>
@endsection

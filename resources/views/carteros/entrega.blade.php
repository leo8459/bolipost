@extends('adminlte::page')
@section('title', 'Carteros - Entrega')
@section('template_title')
    Entregar Correspondencia
@endsection

@section('content')
    <div class="carteros-wrap">
        <div class="card card-carteros">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <h3 class="card-title mb-0">Entregar Correspondencia</h3>
                    <span class="carteros-chip">{{ $tipo_paquete }}</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-2"><strong>Tipo:</strong></div>
                    <div class="col-md-4">{{ $tipo_paquete }}</div>
                    <div class="col-md-2"><strong>Codigo:</strong></div>
                    <div class="col-md-4">{{ $paquete['codigo'] ?? '' }}</div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-2"><strong>Destinatario:</strong></div>
                    <div class="col-md-4">{{ $paquete['destinatario'] ?? '' }}</div>
                    <div class="col-md-2"><strong>Ciudad:</strong></div>
                    <div class="col-md-4">{{ $paquete['ciudad'] ?? '' }}</div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-2"><strong>Intentos:</strong></div>
                    <div class="col-md-10">{{ (int) ($asignacion->intento ?? 0) }}</div>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        <form method="POST" action="{{ route('carteros.entrega.store') }}" class="mb-3">
                            @csrf
                            <input type="hidden" name="tipo_paquete" value="{{ $tipo_paquete }}">
                            <input type="hidden" name="id" value="{{ $id }}">

                            <div class="form-group">
                                <label for="recibido_por">Recibido por</label>
                                <input type="text" name="recibido_por" id="recibido_por" class="form-control" required>
                            </div>

                            <div class="form-group">
                                <label for="descripcion">Descripcion</label>
                                <textarea name="descripcion" id="descripcion" rows="3" class="form-control"></textarea>
                            </div>

                            <button type="submit" class="btn btn-success">Confirmar entrega</button>
                        </form>
                    </div>
                    <div class="col-lg-5">
                        <form method="POST" action="{{ route('carteros.entrega.intento') }}">
                            @csrf
                            <input type="hidden" name="tipo_paquete" value="{{ $tipo_paquete }}">
                            <input type="hidden" name="id" value="{{ $id }}">

                            <div class="form-group">
                                <label for="descripcion_intento">Descripcion del intento (opcional)</label>
                                <textarea name="descripcion" id="descripcion_intento" rows="5" class="form-control"></textarea>
                            </div>

                            <button type="submit" class="btn btn-warning">Agregar intento</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('css')
    @include('carteros.partials.theme')
@endsection

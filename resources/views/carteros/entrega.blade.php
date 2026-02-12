@extends('adminlte::page')
@section('title', 'Carteros - Entrega')
@section('template_title')
    Entregar Correspondencia
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">Entregar Correspondencia</h3>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <strong>Tipo:</strong> {{ $tipo_paquete }}<br>
                <strong>Codigo:</strong> {{ $paquete['codigo'] ?? '' }}<br>
                <strong>Destinatario:</strong> {{ $paquete['destinatario'] ?? '' }}<br>
                <strong>Ciudad:</strong> {{ $paquete['ciudad'] ?? '' }}<br>
                <strong>Intentos:</strong> {{ (int) ($asignacion->intento ?? 0) }}
            </div>

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

                <button type="submit" class="btn btn-success">Entregar</button>
            </form>

            <form method="POST" action="{{ route('carteros.entrega.intento') }}">
                @csrf
                <input type="hidden" name="tipo_paquete" value="{{ $tipo_paquete }}">
                <input type="hidden" name="id" value="{{ $id }}">

                <div class="form-group">
                    <label for="descripcion_intento">Descripcion (opcional)</label>
                    <textarea name="descripcion" id="descripcion_intento" rows="2" class="form-control"></textarea>
                </div>

                <button type="submit" class="btn btn-warning">Agregar intento</button>
            </form>
        </div>
    </div>
@endsection


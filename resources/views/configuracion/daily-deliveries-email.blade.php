@extends('adminlte::page')

@section('title', 'Entregas diarias')

@section('content_header')
    <h1 class="mb-1">Entregas diarias</h1>
    <p class="text-muted mb-0">Envío de correo · EMS y Contratos</p>
@stop

@section('content')
    @foreach (['status' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $type)
        @if (session($key))
            <div class="alert alert-{{ $type }}">{{ session($key) }}</div>
        @endif
    @endforeach
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        @include('configuracion.partials.daily-mail-controls', [
            'routePrefix' => 'daily-deliveries-email',
            'mailTitle' => 'Entregas diarias',
            'reportLabel' => 'reporte',
        ])

        <div class="col-lg-8">
            <div class="card card-info card-outline">
                <div class="card-header"><h2 class="card-title">Vista previa del correo</h2></div>
                <div class="card-body">
                    @include('emails.partials.daily-deliveries-report')
                </div>
            </div>
        </div>
    </div>
@stop

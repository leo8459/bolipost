@extends('adminlte::page')
@section('title', 'Crear tarifas de contrato')
@section('template_title', 'Crear tarifas de contrato')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/tarifarios-contrato.css') }}?v={{ filemtime(public_path('css/tarifarios-contrato.css')) }}">
@endpush

@section('content')
@php
    $puedePrecargar = ($tarifariosOrigen ?? collect())->isNotEmpty() && \Illuminate\Support\Facades\Blade::check('aclcan', 'duplicate', null, 'tarifa-contrato');
    $puedeImportar = \Illuminate\Support\Facades\Blade::check('aclcan', 'import', null, 'tarifa-contrato');
@endphp
<section class="content container-fluid pt-3 tarifario-pagina">
    <header class="tarifario-encabezado">
        <div>
            <h1>Crear tarifas de contrato</h1>
            <p>Elige el tarifario de destino y carga sus precios en la hoja o desde Excel.</p>
        </div>
        <nav class="tarifario-encabezado-acciones" aria-label="Administración de tarifas">
            <a href="{{ route('tarifa-contrato.index') }}" class="btn tarifario-nav-secundario">Ver tarifas</a>
            <a href="{{ route('tarifa-contrato.tarifarios') }}#tarifarios-registrados" class="btn tarifario-nav-principal">Tarifarios por empresa y año</a>
        </nav>
    </header>

    @include('tarifa_contrato.import-feedback', ['successClass' => 'tarifario-aviso'])
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if($puedePrecargar)
        <form method="GET" action="{{ route('tarifa-contrato.create') }}" id="tarifario-preload-form">
            <input type="hidden" name="tarifario_id" value="{{ old('tarifario_contrato_id', $defaults['tarifario_contrato_id'] ?? '') }}">
        </form>
    @endif
    @if($puedeImportar)
        <form method="POST" action="{{ route('tarifa-contrato.import') }}" id="tarifas-excel-import-form" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="volver_a_crear" value="1">
            <input type="hidden" name="tarifario_contrato_id">
            <input type="hidden" name="tarifario_anio">
            @if(request()->integer('copy_id') > 0)
                <input type="hidden" name="copy_id" value="{{ request()->integer('copy_id') }}">
            @endif
        </form>
    @endif

    <form method="POST" action="{{ route('tarifa-contrato.store') }}" id="tarifas-sheet-form">
        @csrf
        @if(!empty($tarifarioOrigen))
            <input type="hidden" name="tarifario_origen_id" value="{{ $tarifarioOrigen->id }}">
            <input type="hidden" name="tarifas_serializadas">
        @endif
        @include('tarifa_contrato.spreadsheet')
    </form>
</section>
@include('footer')
@endsection

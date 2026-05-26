@extends('adminlte::page')
@section('title', 'Paquetes EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@if (session('download_boleta_url'))
    <div class="alert alert-success m-3">
        <strong>Paquete guardado.</strong>
        Elige el formato para descargar e imprimir.
        <a href="{{ session('download_boleta_url') }}" target="_blank" class="btn btn-sm btn-outline-success ml-2">
            Factura termica
        </a>
        @if (session('download_boleta_carta_url'))
            <a href="{{ session('download_boleta_carta_url') }}" target="_blank" class="btn btn-sm btn-outline-dark ml-2">
                Diseno carta
            </a>
        @endif
    </div>
@endif
@livewire('paquetes-ems')
@endsection

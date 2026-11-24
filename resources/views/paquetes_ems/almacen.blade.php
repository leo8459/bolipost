@extends('adminlte::page')
@section('title', 'Paquetes ALMACEN_EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
<script src="{{ asset('js/almacen-ems-prelista.js') }}?v={{ filemtime(public_path('js/almacen-ems-prelista.js')) }}"></script>
@livewire('paquetes-ems', ['mode' => 'almacen_ems'])
@endsection

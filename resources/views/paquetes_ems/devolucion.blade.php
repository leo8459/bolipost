@extends('adminlte::page')
@section('title', 'Devolver paquetes EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@livewire('paquetes-ems', ['mode' => 'devolucion_ems'])
@endsection

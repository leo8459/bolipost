@extends('adminlte::page')
@section('title', 'Nuevo paquete EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@livewire('paquetes-ems', ['mode' => 'create_ems'])
@endsection


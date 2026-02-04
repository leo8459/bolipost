@extends('adminlte::page')
@section('title', 'Paquetes ALMACEN_EMS')
@section('template_title')
    Paqueteria Postal
@endsection

@section('content')
@livewire('paquetes-ems', ['mode' => 'almacen_ems'])
@endsection

@extends('adminlte::page')
@section('title', 'Paquetes Certificados - Inventario')
@section('template_title')
    Paquetes Certificados - Inventario
@endsection

@section('content')
@livewire('paquete-certi', ['mode' => 'inventario'])
@endsection

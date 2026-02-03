@extends('adminlte::page')
@section('title', 'Paquetes Certificados - Almacen')
@section('template_title')
    Paquetes Certificados - Almacen
@endsection

@section('content')
@livewire('paquete-certi', ['mode' => 'almacen'])
@endsection

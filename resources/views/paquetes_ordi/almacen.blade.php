@extends('adminlte::page')
@section('title', 'Paquetes Ordinarios - Almacen')
@section('template_title')
    Paquetes Ordinarios - Almacen
@endsection

@section('content')
@livewire('paquetes-ordi', ['mode' => 'almacen'])
@endsection

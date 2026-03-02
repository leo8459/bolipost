@extends('adminlte::page')
@section('title', 'Almacen Contratos')
@section('template_title')
    Almacen Contratos
@endsection

@section('content')
@livewire('recojo', ['mode' => 'almacen'])
@endsection

